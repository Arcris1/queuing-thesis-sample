import 'package:flutter/foundation.dart';

import '../location/geofence_result.dart';

/// Server-side presence status returned by a heartbeat (§9 state machine).
///
/// Thresholds (2/5/10 min) are decided server-side; the client just renders the
/// label/colour for the current status.
enum PresenceStatus {
  active,
  away,
  offline,
  removed,
  unknown;

  static PresenceStatus fromApi(String? value) => switch (value) {
        'active' => PresenceStatus.active,
        'away' => PresenceStatus.away,
        'offline' => PresenceStatus.offline,
        'removed' => PresenceStatus.removed,
        _ => PresenceStatus.unknown,
      };

  /// True when the student is in a non-active, reconnecting state we should
  /// warn about on the ticket screen.
  bool get needsAttention =>
      this == PresenceStatus.away || this == PresenceStatus.offline;
}

/// Parsed `POST /api/heartbeat` response.
///
/// When the heartbeat carried `gps_location`, the backend also evaluates the
/// geofence, so [geofence] may be populated from the same call (avoids a
/// duplicate `/location/update`).
@immutable
class HeartbeatResult {
  const HeartbeatResult({
    required this.status,
    this.lastSeen,
    this.geofence,
  });

  final PresenceStatus status;
  final DateTime? lastSeen;

  /// Present when the server echoed geofence fields alongside presence.
  final GeofenceResult? geofence;

  factory HeartbeatResult.fromJson(Map<String, dynamic> json) {
    final hasGeofence = json.containsKey('within_range') ||
        json.containsKey('within_radius') ||
        json.containsKey('distance_m');
    return HeartbeatResult(
      status: PresenceStatus.fromApi(json['presence_status'] as String?),
      lastSeen: switch (json['last_seen']) {
        final String s => DateTime.tryParse(s),
        _ => null,
      },
      geofence: hasGeofence ? GeofenceResult.fromJson(json) : null,
    );
  }
}
