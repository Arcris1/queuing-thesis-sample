import '../../data/api_client.dart';
import 'geofence_result.dart';
import 'location_service.dart';

/// Posts raw device coordinates to the geofence endpoint (§8).
///
/// The client sends lat/lng (and the active ticket id, when known); the server
/// computes the distance and decides eligibility. We only relay its verdict.
class LocationRepository {
  LocationRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  /// POST /api/location/update `{ latitude, longitude, ticket_id? }`
  /// → `{ distance_m, within_range, radius_m, office }`.
  Future<GeofenceResult> update(LocationFix fix, {int? ticketId}) async {
    final data = await _api.post('/location/update', data: {
      ...fix.toJson(),
      'ticket_id': ?ticketId,
    });
    return GeofenceResult.fromJson(data);
  }
}
