import 'dart:async';

import '../queue/queue_repository.dart';
import '../queue/queue_ticket.dart';
import '../queue/ticket_status.dart';
import 'realtime_client.dart';
import 'realtime_event.dart';

/// Polling implementation of [RealtimeClient] — the default, dependency-free
/// transport. Every [interval] it refetches `/queue/status` (and, when the
/// status payload lacks a fresh ETA, `/queue/estimate`) and emits the result as
/// a [TicketSnapshotEvent], a [TicketCalledEvent] when the status flips to
/// "called", or a [TicketClearedEvent] when no ticket remains.
///
/// "Realtime enough" for the demo: a short interval (default 5 s) keeps the
/// position / now-serving / ETA visibly live without a socket. Failures are
/// swallowed so a transient blip never tears the screen down — the next tick
/// retries. Stops cleanly when the ticket clears (no work while idle).
class PollingRealtimeClient implements RealtimeClient {
  PollingRealtimeClient({
    required QueueRepository repository,
    this.interval = const Duration(seconds: 5),
  }) : _repo = repository;

  final QueueRepository _repo;
  final Duration interval;

  final _controller = StreamController<RealtimeEvent>.broadcast();
  Timer? _timer;
  bool _running = false;
  bool _fetching = false;

  /// The last status we emitted a "called" event for, so we don't re-announce
  /// the same call on every subsequent poll.
  bool _announcedCalled = false;

  @override
  Stream<RealtimeEvent> get events => _controller.stream;

  @override
  void start(int ticketId) {
    if (_running) return;
    _running = true;
    _announcedCalled = false;
    unawaited(refreshNow());
    _timer = Timer.periodic(interval, (_) => unawaited(_tick()));
  }

  @override
  void stop() {
    _running = false;
    _timer?.cancel();
    _timer = null;
  }

  @override
  Future<void> refreshNow() => _tick();

  Future<void> _tick() async {
    if (!_running || _fetching) return;
    _fetching = true;
    try {
      final ticket = await _repo.status();
      if (!_running) return;

      if (ticket == null) {
        _announcedCalled = false;
        _emit(const TicketClearedEvent());
        return;
      }

      final enriched = await _withEstimate(ticket);
      if (!_running) return;

      // Announce the "called" transition once, ahead of the snapshot, so the
      // screen can flip to the prominent proceed state even before it diffs
      // the snapshot status. Mirrors the WS `ticket.called` push.
      if (enriched.lifecycle == TicketStatus.called) {
        if (!_announcedCalled) {
          _announcedCalled = true;
          _emit(TicketCalledEvent(
            ticketId: enriched.id,
            windowName: enriched.windowName,
            officeName: enriched.office.name,
            message: enriched.calledMessage,
          ));
        }
      } else {
        _announcedCalled = false;
      }

      _emit(TicketSnapshotEvent(enriched));
    } catch (_) {
      // Transient — keep polling; the next tick retries.
    } finally {
      _fetching = false;
    }
  }

  /// Folds a fresh `/queue/estimate` into the ticket when the status payload
  /// didn't already carry one, so the ETA card stays populated and current.
  Future<QueueTicket> _withEstimate(QueueTicket ticket) async {
    if (ticket.etaPrediction != null) return ticket;
    try {
      final eta = await _repo.estimate();
      if (eta == null) return ticket;
      return ticket.copyWith(etaPrediction: eta);
    } catch (_) {
      return ticket;
    }
  }

  void _emit(RealtimeEvent event) {
    if (!_controller.isClosed) _controller.add(event);
  }

  @override
  Future<void> dispose() async {
    stop();
    await _controller.close();
  }
}
