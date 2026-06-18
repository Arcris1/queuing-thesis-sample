import '../../data/api_client.dart';
import '../location/location_service.dart';
import '../queue/queue_ticket.dart';

/// Raised when `POST /api/checkin` rejects the request because the student is
/// outside the office geofence (HTTP 409 `{ distance_m, radius_m }`).
///
/// Carries the server-computed distance + required radius so the UI can say
/// exactly how far the student needs to move (§8: the server is authoritative;
/// the client only renders its verdict).
class CheckinOutOfRange implements Exception {
  const CheckinOutOfRange({this.distanceM, this.radiusM});

  final double? distanceM;
  final double? radiusM;
}

/// Talks to the QR check-in endpoint (§8/§11/§12).
///
/// The student scans the office QR (which encodes the ticket number); the app
/// adds the phone's raw GPS and the server re-validates the ticket, the account
/// (from the JWT), and the location before flipping the ticket to Ready.
class CheckinRepository {
  CheckinRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  /// POST /api/checkin `{ ticket_number, latitude, longitude }`.
  ///
  /// Returns the updated [QueueTicket] (now Ready) on success. Throws
  /// [CheckinOutOfRange] on a 409 out-of-range response, or [ApiException] for
  /// any other failure (invalid ticket, wrong account, network).
  Future<QueueTicket> checkin({
    required String ticketNumber,
    required LocationFix fix,
  }) async {
    try {
      final data = await _api.post('/checkin', data: {
        'ticket_number': ticketNumber,
        ...fix.toJson(),
      });
      return QueueTicket.fromJson(data);
    } on ApiException catch (e) {
      if (e.statusCode == 409) {
        final body = e.body;
        throw CheckinOutOfRange(
          distanceM: (body?['distance_m'] as num?)?.toDouble(),
          radiusM: (body?['radius_m'] as num?)?.toDouble(),
        );
      }
      rethrow;
    }
  }
}
