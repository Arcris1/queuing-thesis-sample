import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/api_client.dart';
import '../auth/auth_controller.dart';
import 'queue_repository.dart';
import 'queue_state.dart';
import 'queue_ticket.dart';

/// Queue endpoints (join + status). Overridable in tests.
final queueRepositoryProvider = Provider<QueueRepository>((ref) {
  return QueueRepository(apiClient: ref.watch(apiClientProvider));
});

/// Owns the join flow and the resulting/current ticket.
final queueControllerProvider =
    NotifierProvider<QueueController, QueueState>(QueueController.new);

class QueueController extends Notifier<QueueState> {
  QueueRepository get _repo => ref.read(queueRepositoryProvider);

  @override
  QueueState build() => const QueueState();

  /// Joins the queue for [serviceId].
  ///
  /// On success → [JoinStatus.joined] with the new ticket. On a 409 (already in
  /// an active queue) → [JoinStatus.alreadyQueued]; we then try to recover the
  /// existing ticket via `/queue/status` so the UI can route the student to it
  /// instead of duplicating the join. Any other failure → [JoinStatus.error].
  Future<void> join(int serviceId) async {
    state = state.copyWith(
      status: JoinStatus.submitting,
      clearError: true,
      clearTicket: true,
    );
    try {
      final ticket = await _repo.join(serviceId);
      state = state.copyWith(status: JoinStatus.joined, ticket: ticket);
    } on ApiException catch (e) {
      if (e.statusCode == 409) {
        final existing = await _safeStatus();
        state = QueueState(
          status: JoinStatus.alreadyQueued,
          ticket: existing,
          errorMessage: existing == null ? e.message : null,
        );
        return;
      }
      state = state.copyWith(status: JoinStatus.error, errorMessage: e.message);
    } catch (_) {
      state = state.copyWith(
        status: JoinStatus.error,
        errorMessage: 'Something went wrong. Please try again.',
      );
    }
  }

  /// Resets to idle (e.g. when the confirm sheet is dismissed before routing,
  /// or after leaving the queue from the status screen).
  void reset() {
    state = const QueueState();
  }

  /// Mirrors the latest live ticket back into queue state so
  /// [activeTicketProvider] (and the presence loop) stays in sync with what the
  /// status screen is showing (task 029). A terminal ticket clears it.
  void syncTicket(QueueTicket ticket) {
    if (ticket.lifecycle.isTerminal) {
      state = const QueueState();
      return;
    }
    state = state.copyWith(ticket: ticket);
  }

  Future<QueueTicket?> _safeStatus() async {
    try {
      return await _repo.status();
    } catch (_) {
      return null;
    }
  }
}
