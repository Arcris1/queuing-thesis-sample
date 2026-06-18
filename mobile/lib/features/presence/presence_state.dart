import 'package:flutter/foundation.dart';

import '../location/geofence_result.dart';
import '../location/location_service.dart';
import 'heartbeat_result.dart';

/// Immutable snapshot of the presence/geofence loop, surfaced to the ticket
/// screen (task 029) via providers.
///
/// Holds the latest server-decided [presence] and [geofence], the device
/// [locationAvailability] (so the UI can prompt to grant/enable), and whether
/// the heartbeat loop is currently [running].
@immutable
class PresenceState {
  const PresenceState({
    this.running = false,
    this.presence = PresenceStatus.unknown,
    this.geofence,
    this.locationAvailability = LocationAvailability.ok,
    this.lastSeen,
  });

  /// True while the 30 s heartbeat timer is active (a ticket is held).
  final bool running;

  /// Latest server presence status.
  final PresenceStatus presence;

  /// Latest geofence verdict, or null before the first successful report.
  final GeofenceResult? geofence;

  /// Device-side location permission/services state.
  final LocationAvailability locationAvailability;

  final DateTime? lastSeen;

  /// True when we have a fix and the server says we're in range.
  bool get withinRange => geofence?.withinRange ?? false;

  /// True when location is usable (granted + services on).
  bool get locationOk => locationAvailability == LocationAvailability.ok;

  PresenceState copyWith({
    bool? running,
    PresenceStatus? presence,
    GeofenceResult? geofence,
    LocationAvailability? locationAvailability,
    DateTime? lastSeen,
    bool clearGeofence = false,
  }) {
    return PresenceState(
      running: running ?? this.running,
      presence: presence ?? this.presence,
      geofence: clearGeofence ? null : (geofence ?? this.geofence),
      locationAvailability: locationAvailability ?? this.locationAvailability,
      lastSeen: lastSeen ?? this.lastSeen,
    );
  }
}
