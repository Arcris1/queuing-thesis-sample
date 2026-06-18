import '../../data/api_client.dart';
import 'queue_ticket.dart';
import 'ticket_eta.dart';

/// Talks to the authenticated queue endpoints (§7).
class QueueRepository {
  QueueRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  /// POST /api/queue/join `{ service_id }` → the issued ticket.
  ///
  /// Throws [ApiException] with `statusCode == 409` when the student is already
  /// in an active queue for that group.
  Future<QueueTicket> join(int serviceId) async {
    final data = await _api.post('/queue/join', data: {'service_id': serviceId});
    return QueueTicket.fromJson(data);
  }

  /// GET /api/queue/status → the student's active ticket, or null when none.
  Future<QueueTicket?> status() async {
    final data = await _api.get('/queue/status');
    if (data.isEmpty) return null;
    return QueueTicket.fromJson(data);
  }

  /// GET /api/queue/estimate → the AI wait-time prediction for the caller's
  /// queue group (§10, task 024). Returns null when the server has no estimate
  /// (e.g. the student isn't queued or the model has no data).
  Future<TicketEta?> estimate() async {
    final data = await _api.get('/queue/estimate');
    if (data.isEmpty) return null;
    return TicketEta.fromJson(data);
  }

  /// POST /api/queue/leave → removes the student from the queue.
  Future<void> leave() => _api.postVoid('/queue/leave');
}
