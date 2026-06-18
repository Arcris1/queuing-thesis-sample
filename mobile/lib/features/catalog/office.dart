import 'package:flutter/foundation.dart';

/// A university administrative office (Registrar, Accounting, Cashier).
///
/// Coordinates + geofence radius come from the API (§1); the client never
/// computes distance — it is shown here purely for context.
@immutable
class Office {
  const Office({
    required this.id,
    required this.name,
    this.latitude,
    this.longitude,
    this.geofenceRadiusM,
  });

  final int id;
  final String name;
  final double? latitude;
  final double? longitude;
  final int? geofenceRadiusM;

  factory Office.fromJson(Map<String, dynamic> json) {
    return Office(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? '',
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      geofenceRadiusM: (json['geofence_radius_m'] as num?)?.toInt(),
    );
  }

  @override
  bool operator ==(Object other) =>
      other is Office &&
      other.id == id &&
      other.name == name &&
      other.latitude == latitude &&
      other.longitude == longitude &&
      other.geofenceRadiusM == geofenceRadiusM;

  @override
  int get hashCode =>
      Object.hash(id, name, latitude, longitude, geofenceRadiusM);
}
