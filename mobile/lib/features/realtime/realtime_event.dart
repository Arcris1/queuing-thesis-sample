import 'package:flutter/foundation.dart';

import '../queue/queue_ticket.dart';

/// A live update about the student's active ticket, emitted by a
/// [RealtimeClient] regardless of transport (polling or WebSocket).
///
/// Modelled to mirror the backend's Reverb events (task 019):
///   - [TicketSnapshotEvent]   ← a fresh `/queue/status` poll, or `queue.updated`
///   - [TicketCalledEvent]     ← the private `user.{id}` → `ticket.called` event
///   - [TicketClearedEvent]    ← status came back null (no active ticket)
@immutable
sealed class RealtimeEvent {
  const RealtimeEvent();
}

/// A full, current view of the ticket (the common case). The screen replaces
/// its model with [ticket]; position / people-ahead / ETA / now-serving all
/// flow through here so a single code path drives every live field.
@immutable
class TicketSnapshotEvent extends RealtimeEvent {
  const TicketSnapshotEvent(this.ticket);

  final QueueTicket ticket;
}

/// The student is being called to a window — the prominent "please proceed"
/// state. Carries the window/office so the screen can say *where* to go even if
/// a follow-up status poll hasn't landed yet.
@immutable
class TicketCalledEvent extends RealtimeEvent {
  const TicketCalledEvent({
    required this.ticketId,
    this.windowName,
    this.officeName,
    this.message,
  });

  final int ticketId;
  final String? windowName;
  final String? officeName;
  final String? message;

  /// Builds from a Reverb `ticket.called` payload
  /// (`{ ticket, window, queue_group, office, message }`).
  factory TicketCalledEvent.fromJson(Map<String, dynamic> json) {
    int readId(Object? v) => switch (v) {
          final num n => n.toInt(),
          final Map<String, dynamic> m => (m['id'] as num?)?.toInt() ?? 0,
          _ => 0,
        };
    String? readName(Object? v) => switch (v) {
          final Map<String, dynamic> m => m['name']?.toString(),
          final String s => s,
          _ => null,
        };
    return TicketCalledEvent(
      ticketId: readId(json['ticket'] ?? json['ticket_id']),
      windowName: readName(json['window']),
      officeName: readName(json['office']),
      message: json['message']?.toString(),
    );
  }
}

/// No active ticket remains (left, served-and-cleared, or removed server-side).
@immutable
class TicketClearedEvent extends RealtimeEvent {
  const TicketClearedEvent();
}
