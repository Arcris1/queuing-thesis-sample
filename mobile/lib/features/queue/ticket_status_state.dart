import 'package:flutter/foundation.dart';

import 'queue_ticket.dart';

/// Loading phase of the live status screen, distinct from the ticket lifecycle.
enum StatusPhase {
  /// First load in flight, nothing to show yet.
  loading,

  /// We have a ticket and are streaming live updates.
  active,

  /// No active ticket (the student is not in any queue) — show the empty CTA.
  empty,

  /// The student left or terminated the queue from this screen.
  left,

  /// Initial load failed and we have nothing cached to fall back to.
  error,
}

/// Immutable snapshot driving [TicketStatusScreen].
///
/// Holds the current [ticket] (continuously refreshed by the realtime client),
/// the load [phase], whether a `ticket.called` push has fired ([beingCalled],
/// the prominent "proceed" state), and any transient [errorMessage].
@immutable
class TicketStatusState {
  const TicketStatusState({
    this.phase = StatusPhase.loading,
    this.ticket,
    this.beingCalled = false,
    this.isLeaving = false,
    this.errorMessage,
  });

  final StatusPhase phase;
  final QueueTicket? ticket;

  /// True once a `ticket.called` event arrives (or the status flips to
  /// `called`) — drives the celebratory "please proceed" hero state.
  final bool beingCalled;

  /// A leave request is in flight (disables the button, shows a spinner).
  final bool isLeaving;

  final String? errorMessage;

  bool get hasTicket => ticket != null;

  TicketStatusState copyWith({
    StatusPhase? phase,
    QueueTicket? ticket,
    bool? beingCalled,
    bool? isLeaving,
    String? errorMessage,
    bool clearTicket = false,
    bool clearError = false,
  }) {
    return TicketStatusState(
      phase: phase ?? this.phase,
      ticket: clearTicket ? null : (ticket ?? this.ticket),
      beingCalled: beingCalled ?? this.beingCalled,
      isLeaving: isLeaving ?? this.isLeaving,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }
}
