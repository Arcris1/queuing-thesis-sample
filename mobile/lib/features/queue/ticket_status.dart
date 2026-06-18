/// Lifecycle of a queue ticket (§11), as returned in `status` on the ticket
/// payloads. The string is server-authoritative; this enum is a client-side
/// classification so the status screen can branch on a small, total set of
/// states instead of matching raw strings everywhere.
enum TicketStatus {
  /// In line, not yet called.
  waiting,

  /// This ticket is being called to a window right now ("please proceed").
  called,

  /// Currently being served at a window.
  serving,

  /// Service finished — the celebratory "done" state.
  served,

  /// Called but the student wasn't present; they may rejoin.
  skipped,

  /// Moved to a standby/grace lane after being away when called (§9 grace).
  standby,

  /// The ticket no longer holds a slot (cancelled/left/removed/no-show).
  closed,

  /// Unrecognised — render generically and keep polling.
  unknown;

  static TicketStatus fromApi(String? value) => switch (value?.toLowerCase()) {
        'waiting' || 'queued' || 'pending' => TicketStatus.waiting,
        'called' || 'calling' => TicketStatus.called,
        'serving' || 'in_service' || 'in-progress' => TicketStatus.serving,
        'served' || 'completed' || 'done' => TicketStatus.served,
        'skipped' || 'no_show' || 'no-show' => TicketStatus.skipped,
        'standby' || 'on_hold' || 'on-hold' || 'grace' => TicketStatus.standby,
        'cancelled' || 'canceled' || 'left' || 'removed' => TicketStatus.closed,
        _ => TicketStatus.unknown,
      };

  /// True while the student still holds (or is actively being given) a slot.
  bool get isActive => switch (this) {
        TicketStatus.waiting ||
        TicketStatus.called ||
        TicketStatus.serving ||
        TicketStatus.standby =>
          true,
        _ => false,
      };

  /// Terminal states that end the waiting experience (stop realtime + presence).
  bool get isTerminal =>
      this == TicketStatus.served ||
      this == TicketStatus.skipped ||
      this == TicketStatus.closed;
}
