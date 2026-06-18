import '../../data/api_client.dart';
import '../location/location_service.dart';
import 'heartbeat_result.dart';

/// Posts the presence heartbeat (§9).
///
/// A heartbeat optionally carries `gps_location`; when it does, the backend
/// treats it as a location ping too, so we prefer sending location *through*
/// the heartbeat when both are due rather than duplicating `/location/update`.
class HeartbeatRepository {
  HeartbeatRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  /// POST /api/heartbeat → `{ presence_status, last_seen, ... }`.
  Future<HeartbeatResult> send({
    int? batteryLevel,
    String? networkStatus,
    LocationFix? gpsLocation,
  }) async {
    final data = await _api.post('/heartbeat', data: {
      'battery_level': ?batteryLevel,
      'network_status': ?networkStatus,
      if (gpsLocation != null) 'gps_location': gpsLocation.toJson(),
    });
    return HeartbeatResult.fromJson(data);
  }
}
