import 'package:flutter/foundation.dart';

/// Server's verdict for a reported position (§8: the API is authoritative).
///
/// Returned by both `POST /api/location/update` and a heartbeat that carries
/// `gps_location`. The client never computes eligibility — it only renders
/// what the server decided.
@immutable
class GeofenceResult {
  const GeofenceResult({
    required this.withinRange,
    this.distanceM,
    this.radiusM,
    this.officeName,
  });

  /// True when the device is inside the office's geofence radius.
  final bool withinRange;

  /// Server-computed distance to the office, in metres.
  final double? distanceM;

  /// The office's configured radius, in metres (default 15).
  final double? radiusM;

  /// Office name, when the server echoes it.
  final String? officeName;

  factory GeofenceResult.fromJson(Map<String, dynamic> json) {
    // The API may key range as `within_range` (live contract) or
    // `within_radius` (task 013 spec) — accept either to stay resilient.
    final within = json['within_range'] ?? json['within_radius'];
    final office = json['office'];
    return GeofenceResult(
      withinRange: within == true,
      distanceM: (json['distance_m'] as num?)?.toDouble(),
      radiusM: (json['radius_m'] as num?)?.toDouble(),
      officeName: office is Map<String, dynamic>
          ? office['name'] as String?
          : office?.toString(),
    );
  }

  @override
  bool operator ==(Object other) =>
      other is GeofenceResult &&
      other.withinRange == withinRange &&
      other.distanceM == distanceM &&
      other.radiusM == radiusM &&
      other.officeName == officeName;

  @override
  int get hashCode =>
      Object.hash(withinRange, distanceM, radiusM, officeName);
}
