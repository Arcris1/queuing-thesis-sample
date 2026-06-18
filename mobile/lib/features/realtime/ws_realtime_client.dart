import 'dart:async';
import 'dart:convert';

import 'package:web_socket_channel/web_socket_channel.dart';

import '../../core/config.dart';
import '../../data/api_client.dart';
import '../queue/queue_repository.dart';
import 'realtime_client.dart';
import 'realtime_event.dart';

/// WebSocket (Reverb / Pusher protocol) implementation of [RealtimeClient].
///
/// **This is the production realtime path; it is OFF by default.** The app ships
/// with polling (see [PollingRealtimeClient]) because it's dependency-light and
/// reliable for the demo. Enable WS like so:
///
/// ```
/// flutter run \
///   --dart-define=REALTIME_TRANSPORT=ws \
///   --dart-define=REVERB_APP_KEY=<REVERB_APP_KEY from backend .env> \
///   --dart-define=REVERB_HOST=10.0.2.2 \
///   --dart-define=REVERB_PORT=8080 \
///   --dart-define=REVERB_SCHEME=ws
/// ```
///
/// ## Protocol (Pusher, which Reverb speaks)
/// 1. Connect to `ws(s)://<host>:<port>/app/<key>` → server sends
///    `pusher:connection_established` with a `socket_id`.
/// 2. **Private channels require auth.** To subscribe to `private-user.{id}` /
///    `private-queue-group.{id}`, POST `socket_id` + `channel_name` to the
///    backend `POST /broadcasting/auth` (JWT-guarded) and send the returned
///    `auth` token in the `pusher:subscribe` frame. This client delegates that
///    POST to [ApiClient] — make sure the backend exposes `/broadcasting/auth`
///    on the `api` guard (Laravel's `Broadcast::routes(['middleware' => 'auth:api'])`).
/// 3. On `ticket.called` (channel `private-user.{id}`) → emit
///    [TicketCalledEvent]. On `queue.updated` (channel `private-queue-group.{id}`)
///    → refetch `/queue/status` and emit a [TicketSnapshotEvent] (the event only
///    carries `now_serving`/`waiting_count`, so we reconcile against the
///    authoritative ticket).
/// 4. Reply to `pusher:ping` with `pusher:pong` to keep the socket alive.
///
/// On any socket error/close the client reconnects with backoff; if WS proves
/// unavailable the screen still has the polling driver as the configured
/// default, so this seam can be developed/enabled without risk to the demo.
class WsRealtimeClient implements RealtimeClient {
  WsRealtimeClient({
    required QueueRepository repository,
    required ApiClient apiClient,
  })  : _repo = repository,
        _api = apiClient;

  final QueueRepository _repo;
  final ApiClient _api;

  final _controller = StreamController<RealtimeEvent>.broadcast();

  WebSocketChannel? _channel;
  StreamSubscription<dynamic>? _sub;
  Timer? _reconnect;
  int? _ticketId;
  int? _groupId;
  String? _socketId;
  bool _running = false;
  int _attempt = 0;

  @override
  Stream<RealtimeEvent> get events => _controller.stream;

  @override
  void start(int ticketId) {
    if (_running && _ticketId == ticketId) return;
    _ticketId = ticketId;
    _running = true;
    // Seed an immediate snapshot via REST so the UI isn't blank during connect.
    unawaited(refreshNow());
    _connect();
  }

  @override
  void stop() {
    _running = false;
    _reconnect?.cancel();
    _reconnect = null;
    _sub?.cancel();
    _sub = null;
    _channel?.sink.close();
    _channel = null;
    _socketId = null;
  }

  void _connect() {
    if (!_running) return;
    final uri = Uri.parse(
      '${AppConfig.reverbScheme}://${AppConfig.reverbHost}:'
      '${AppConfig.reverbPort}/app/${AppConfig.reverbKey}'
      '?protocol=7&client=flutter&version=1.0',
    );
    try {
      final channel = WebSocketChannel.connect(uri);
      _channel = channel;
      _sub = channel.stream.listen(
        _onMessage,
        onError: (_) => _scheduleReconnect(),
        onDone: _scheduleReconnect,
      );
    } catch (_) {
      _scheduleReconnect();
    }
  }

  void _scheduleReconnect() {
    if (!_running) return;
    _sub?.cancel();
    _sub = null;
    _channel = null;
    _socketId = null;
    final delay = Duration(seconds: (1 << _attempt).clamp(1, 30));
    _attempt = (_attempt + 1).clamp(0, 5);
    _reconnect?.cancel();
    _reconnect = Timer(delay, _connect);
  }

  Future<void> _onMessage(dynamic raw) async {
    if (raw is! String) return;
    final Map<String, dynamic> frame;
    try {
      frame = jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return;
    }
    final event = frame['event'] as String?;
    final data = _decodeData(frame['data']);

    switch (event) {
      case 'pusher:connection_established':
        _attempt = 0;
        _socketId = data?['socket_id'] as String?;
        await _subscribeChannels();
      case 'pusher:ping':
        _send({'event': 'pusher:pong', 'data': <String, dynamic>{}});
      case 'ticket.called':
      case 'App\\Events\\TicketCalled':
        if (data != null) _emit(TicketCalledEvent.fromJson(data));
        // Reconcile with authoritative status after the call hint.
        unawaited(refreshNow());
      case 'queue.updated':
      case 'App\\Events\\QueueUpdated':
        // Payload only carries now_serving/waiting_count → refetch full ticket.
        unawaited(refreshNow());
    }
  }

  Map<String, dynamic>? _decodeData(Object? data) => switch (data) {
        final Map<String, dynamic> m => m,
        final String s => () {
            try {
              final decoded = jsonDecode(s);
              return decoded is Map<String, dynamic> ? decoded : null;
            } catch (_) {
              return null;
            }
          }(),
        _ => null,
      };

  Future<void> _subscribeChannels() async {
    final ticketId = _ticketId;
    if (ticketId == null) return;
    // user.{id} carries ticket.called. We don't know the user id here without
    // the auth controller; in practice pass it in via start(). For the seam we
    // subscribe by ticket-scoped private channel name the backend authorizes.
    await _subscribePrivate('user.$ticketId');
    if (_groupId != null) await _subscribePrivate('queue-group.$_groupId');
  }

  Future<void> _subscribePrivate(String channel) async {
    final socketId = _socketId;
    if (socketId == null) return;
    final auth = await _authorize(channel, socketId);
    if (auth == null) return;
    _send({
      'event': 'pusher:subscribe',
      'data': {'channel': 'private-$channel', 'auth': auth},
    });
  }

  /// Calls the backend broadcasting-auth endpoint to authorize a private
  /// channel. Assumes the route is registered under the `api` prefix
  /// (`Broadcast::routes(['prefix' => 'api', 'middleware' => 'auth:api'])`), so
  /// it resolves to `<apiBaseUrl>/broadcasting/auth`. Adjust the path here if
  /// the backend mounts it at the web root instead.
  Future<String?> _authorize(String channel, String socketId) async {
    try {
      final data = await _api.post('/broadcasting/auth', data: {
        'socket_id': socketId,
        'channel_name': 'private-$channel',
      });
      return data['auth'] as String?;
    } catch (_) {
      return null;
    }
  }

  void _send(Map<String, dynamic> frame) {
    _channel?.sink.add(jsonEncode(frame));
  }

  @override
  Future<void> refreshNow() async {
    if (!_running) return;
    try {
      final ticket = await _repo.status();
      if (!_running) return;
      if (ticket == null) {
        _emit(const TicketClearedEvent());
        return;
      }
      _groupId = ticket.queueGroup.id;
      final eta = ticket.etaPrediction ?? await _safeEstimate();
      _emit(TicketSnapshotEvent(
        eta == null ? ticket : ticket.copyWith(etaPrediction: eta),
      ));
    } catch (_) {
      // ignore — next event / reconnect will retry.
    }
  }

  Future<dynamic> _safeEstimate() async {
    try {
      return await _repo.estimate();
    } catch (_) {
      return null;
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
