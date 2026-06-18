import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'queue_controller.dart';
import 'queue_ticket.dart';

/// The student's currently-active ticket, or null when there is none.
///
/// Derived from the queue controller's state: a ticket counts as active only
/// while it exists and has not reached a terminal status (§11). This is the
/// single signal the presence/heartbeat loop and the realtime client react to,
/// decoupling them from the join flow's transient [JoinStatus] phases.
final activeTicketProvider = Provider<QueueTicket?>((ref) {
  final ticket = ref.watch(queueControllerProvider.select((s) => s.ticket));
  if (ticket == null) return null;
  if (ticket.lifecycle.isTerminal) return null;
  return ticket;
});

/// Boolean view of [activeTicketProvider] — cheaper to listen to in the
/// heartbeat / realtime controllers (they only care that *a* ticket is held).
final hasActiveTicketProvider = Provider<bool>(
  (ref) => ref.watch(activeTicketProvider) != null,
);
