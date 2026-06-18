import 'realtime_event.dart';

/// Transport-agnostic source of live ticket updates.
///
/// The status screen consumes a single [events] stream and never cares whether
/// updates arrive by polling `/queue/status` or over a Reverb/Pusher WebSocket.
/// Two drivers implement this contract:
///   - [PollingRealtimeClient]  — default; refetches status every N seconds.
///     Dependency-free and reliable for the thesis demo.
///   - a WebSocket driver        — see `ws_realtime_client.dart` for the seam +
///     enablement notes; swap it in behind this same interface, no screen change.
///
/// Lifecycle: [start] is called once a ticket is active and [stop]/[dispose]
/// when it clears, so no transport runs while there's nothing to watch
/// (battery-friendly, §9). Implementations must be safe to [start]/[stop]
/// repeatedly.
abstract interface class RealtimeClient {
  /// Broadcast stream of [RealtimeEvent]s. Multiple listeners are allowed; late
  /// subscribers should still receive subsequent events.
  Stream<RealtimeEvent> get events;

  /// Begin watching the ticket identified by [ticketId]. Emits an initial
  /// snapshot as soon as one is available.
  void start(int ticketId);

  /// Stop watching but keep the client reusable (e.g. ticket changed).
  void stop();

  /// Force an immediate refresh (e.g. pull-to-refresh or after a `ticket.called`
  /// hint, to reconcile with the authoritative server state). No-op if stopped.
  Future<void> refreshNow();

  /// Release the stream + any transport resources permanently.
  Future<void> dispose();
}
