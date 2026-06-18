import 'package:flutter/foundation.dart';

import 'queue_ticket.dart';

/// Phase of the join flow, driving the confirm sheet and result routing.
enum JoinStatus {
  /// No join attempt in progress.
  idle,

  /// A `POST /api/queue/join` request is in flight.
  submitting,

  /// Join succeeded; [QueueState.ticket] holds the new ticket.
  joined,

  /// The student was already queued (409); [QueueState.ticket] holds the
  /// recovered existing ticket if it could be fetched, else null.
  alreadyQueued,

  /// The attempt failed; [QueueState.errorMessage] explains why.
  error,
}

/// Immutable snapshot of the queue feature.
@immutable
class QueueState {
  const QueueState({
    this.status = JoinStatus.idle,
    this.ticket,
    this.errorMessage,
  });

  final JoinStatus status;
  final QueueTicket? ticket;
  final String? errorMessage;

  bool get isSubmitting => status == JoinStatus.submitting;

  QueueState copyWith({
    JoinStatus? status,
    QueueTicket? ticket,
    String? errorMessage,
    bool clearTicket = false,
    bool clearError = false,
  }) {
    return QueueState(
      status: status ?? this.status,
      ticket: clearTicket ? null : (ticket ?? this.ticket),
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }
}
