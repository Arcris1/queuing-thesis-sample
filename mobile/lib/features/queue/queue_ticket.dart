import 'package:flutter/foundation.dart';

import 'ticket_eta.dart';
import 'ticket_status.dart';

/// Lightweight {id, name} reference embedded in a ticket payload.
@immutable
class TicketRef {
  const TicketRef({required this.id, required this.name, this.prefix});

  final int id;
  final String name;

  /// Only present on the queue-group ref.
  final String? prefix;

  factory TicketRef.fromJson(Map<String, dynamic> json) {
    return TicketRef(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? '',
      prefix: json['prefix'] as String?,
    );
  }

  @override
  bool operator ==(Object other) =>
      other is TicketRef &&
      other.id == id &&
      other.name == name &&
      other.prefix == prefix;

  @override
  int get hashCode => Object.hash(id, name, prefix);
}

/// A queue ticket as returned by `POST /api/queue/join` and
/// `GET /api/queue/status`. Position/people-ahead are computed within the
/// ticket's queue group (§5); [etaPrediction] is the structured AI estimate
/// (§10) and may be null until the model has data.
@immutable
class QueueTicket {
  const QueueTicket({
    required this.id,
    required this.ticketNumber,
    required this.status,
    this.statusLabel,
    this.priority,
    this.position,
    this.peopleAhead,
    this.currentNumber,
    this.etaPrediction,
    this.windowName,
    this.calledMessage,
    this.joinedAt,
    required this.office,
    required this.queueGroup,
    required this.service,
  });

  final int id;
  final String ticketNumber;

  /// Raw server status string (e.g. `waiting`, `called`, `served`).
  final String status;

  /// Human-friendly status label from the API, when provided.
  final String? statusLabel;

  final int? priority;
  final int? position;
  final int? peopleAhead;

  /// The number currently being served in this group (`now_serving`).
  final String? currentNumber;

  /// Structured AI wait-time estimate (§10), or null when unavailable.
  final TicketEta? etaPrediction;

  /// Window the student is called to, surfaced on a `ticket.called` event.
  final String? windowName;

  /// Optional copy from a `ticket.called` event ("Proceed to window 3").
  final String? calledMessage;

  final DateTime? joinedAt;
  final TicketRef office;
  final TicketRef queueGroup;
  final TicketRef service;

  /// Classified lifecycle state (§11) derived from [status].
  TicketStatus get lifecycle => TicketStatus.fromApi(status);

  factory QueueTicket.fromJson(Map<String, dynamic> json) {
    return QueueTicket(
      id: (json['id'] as num).toInt(),
      ticketNumber: json['ticket_number']?.toString() ?? '',
      status: json['status'] as String? ?? '',
      statusLabel: json['status_label'] as String?,
      priority: (json['priority'] as num?)?.toInt(),
      position: (json['position'] as num?)?.toInt(),
      peopleAhead: (json['people_ahead'] as num?)?.toInt(),
      currentNumber:
          (json['current_number'] ?? json['now_serving'])?.toString(),
      etaPrediction: TicketEta.fromJson(json['eta']),
      windowName: switch (json['window']) {
        final Map<String, dynamic> w => w['name']?.toString(),
        final Object w => w.toString(),
        _ => null,
      },
      joinedAt: switch (json['joined_at']) {
        final String s => DateTime.tryParse(s),
        _ => null,
      },
      office: TicketRef.fromJson(json['office'] as Map<String, dynamic>),
      queueGroup:
          TicketRef.fromJson(json['queue_group'] as Map<String, dynamic>),
      service: TicketRef.fromJson(json['service'] as Map<String, dynamic>),
    );
  }

  QueueTicket copyWith({
    String? status,
    String? statusLabel,
    int? position,
    int? peopleAhead,
    String? currentNumber,
    TicketEta? etaPrediction,
    String? windowName,
    String? calledMessage,
  }) {
    return QueueTicket(
      id: id,
      ticketNumber: ticketNumber,
      status: status ?? this.status,
      statusLabel: statusLabel ?? this.statusLabel,
      priority: priority,
      position: position ?? this.position,
      peopleAhead: peopleAhead ?? this.peopleAhead,
      currentNumber: currentNumber ?? this.currentNumber,
      etaPrediction: etaPrediction ?? this.etaPrediction,
      windowName: windowName ?? this.windowName,
      calledMessage: calledMessage ?? this.calledMessage,
      joinedAt: joinedAt,
      office: office,
      queueGroup: queueGroup,
      service: service,
    );
  }
}
