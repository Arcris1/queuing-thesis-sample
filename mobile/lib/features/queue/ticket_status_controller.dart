import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/api_client.dart';
import '../realtime/realtime_client.dart';
import '../realtime/realtime_event.dart';
import '../realtime/realtime_providers.dart';
import 'queue_controller.dart';
import 'queue_repository.dart';
import 'queue_ticket.dart';
import 'ticket_status.dart';
import 'ticket_status_state.dart';

/// Owns the live ticket/status screen (task 029).
///
/// On [start] it seeds from the ticket the join flow handed off, then starts the
/// [realtimeClientProvider] and folds every [RealtimeEvent] into [state] — so
/// the screen updates the same way whether updates arrive by polling or over a
/// WebSocket. Terminal transitions (served / skipped / left) stop the transport
/// and the presence loop by clearing the active ticket.
final ticketStatusControllerProvider =
    NotifierProvider<TicketStatusController, TicketStatusState>(
  TicketStatusController.new,
);

class TicketStatusController extends Notifier<TicketStatusState> {
  StreamSubscription<RealtimeEvent>? _sub;
  bool _started = false;

  /// Captured once on [start] so [build]'s onDispose can stop it without reading
  /// a provider during the dispose life-cycle (which Riverpod forbids).
  RealtimeClient? _client;

  QueueRepository get _repo => ref.read(queueRepositoryProvider);

  @override
  TicketStatusState build() {
    ref.onDispose(() {
      _sub?.cancel();
      _client?.stop();
    });
    return const TicketStatusState();
  }

  /// Begins live tracking for [initial] (the ticket handed off from join /
  /// confirmation, or recovered via `/queue/status`). Idempotent.
  void start(QueueTicket initial) {
    if (_started) {
      // Already streaming — just refresh the seed in case it changed.
      state = state.copyWith(ticket: initial, phase: StatusPhase.active);
      return;
    }
    _started = true;

    final calling = initial.lifecycle == TicketStatus.called;
    state = TicketStatusState(
      phase: StatusPhase.active,
      ticket: initial,
      beingCalled: calling,
    );
    _syncActiveTicket(initial);

    final client = ref.read(realtimeClientProvider);
    _client = client;
    _sub = client.events.listen(_onEvent);
    client.start(initial.id);
  }

  /// Refetch immediately (pull-to-refresh).
  Future<void> refresh() async => await _client?.refreshNow();

  void _onEvent(RealtimeEvent event) {
    switch (event) {
      case TicketSnapshotEvent(:final ticket):
        final calling = ticket.lifecycle == TicketStatus.called;
        state = state.copyWith(
          phase: StatusPhase.active,
          ticket: ticket,
          beingCalled: state.beingCalled || calling,
          clearError: true,
        );
        _syncActiveTicket(ticket);

      case TicketCalledEvent(
          :final windowName,
          :final officeName,
          :final message,
        ):
        final base = state.ticket;
        final updated = base?.copyWith(
          status: 'called',
          windowName: windowName,
          calledMessage: message,
        );
        state = state.copyWith(
          phase: StatusPhase.active,
          ticket: updated,
          beingCalled: true,
        );
        if (updated != null) {
          _syncActiveTicket(updated);
        } else if (officeName != null) {
          // No cached ticket yet — keep the flag; the next snapshot fills detail.
        }

      case TicketClearedEvent():
        // Server says no active ticket. If we were mid-service this is the
        // natural "done"; otherwise the student simply has no ticket.
        final wasServing = state.ticket?.lifecycle == TicketStatus.serving ||
            state.ticket?.lifecycle == TicketStatus.called ||
            state.beingCalled;
        state = state.copyWith(
          phase: wasServing ? StatusPhase.left : StatusPhase.empty,
          beingCalled: false,
        );
        if (wasServing) {
          // Surface the served state by marking the cached ticket served rather
          // than dropping it, so the done screen can name the service.
          final last = state.ticket;
          if (last != null && !last.lifecycle.isTerminal) {
            state = state.copyWith(ticket: last.copyWith(status: 'served'));
          }
        }
        _clearActiveTicket();
        _client?.stop();
    }
  }

  /// Leaves the queue: POST `/queue/leave`, stop the transport, show the empty
  /// CTA. Resilient to the network — clears locally even if the call fails so a
  /// student is never trapped on the screen.
  Future<void> leave() async {
    if (state.isLeaving) return;
    state = state.copyWith(isLeaving: true, clearError: true);
    try {
      await _repo.leave();
    } on ApiException catch (e) {
      // 404/409 (already gone) are fine — fall through to the cleared state.
      if (e.statusCode != 404 && e.statusCode != 409) {
        state = state.copyWith(isLeaving: false, errorMessage: e.message);
        return;
      }
    } catch (_) {
      // Network hiccup — still clear locally; the server reclaims via presence.
    }
    _client?.stop();
    _clearActiveTicket();
    state = const TicketStatusState(phase: StatusPhase.left);
  }

  /// Mirror the latest ticket into the queue controller so
  /// [activeTicketProvider] (and therefore the presence loop) stays in sync.
  void _syncActiveTicket(QueueTicket ticket) {
    ref.read(queueControllerProvider.notifier).syncTicket(ticket);
  }

  void _clearActiveTicket() {
    ref.read(queueControllerProvider.notifier).reset();
  }
}
