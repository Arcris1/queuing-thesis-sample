import 'package:geolocator/geolocator.dart';

/// Discriminated outcome of asking the device for a location fix.
///
/// We never throw out of [LocationService]: callers switch on this so the UI
/// can render a precise, recoverable state (rationale, open settings, etc.).
enum LocationAvailability {
  /// A fix was obtained; [LocationFix.position] is non-null.
  ok,

  /// The OS location services (GPS) are switched off device-wide.
  servicesDisabled,

  /// Permission was denied this time (can be asked again).
  denied,

  /// Permission was permanently denied — must be re-enabled in app settings.
  deniedForever,

  /// A fix was requested but could not be acquired (timeout/sensor error).
  unavailable,
}

/// A plain lat/lng pair the repository serializes into the API payload.
///
/// We keep it independent of geolocator's [Position] so it is trivial to
/// construct in tests and to send raw coordinates (§8: the server decides
/// eligibility — the client only reports where it is).
class LocationFix {
  const LocationFix({required this.latitude, required this.longitude});

  final double latitude;
  final double longitude;

  Map<String, double> toJson() => {
        'latitude': latitude,
        'longitude': longitude,
      };
}

/// Result of [LocationService.currentFix]: an availability verdict plus an
/// optional fix (present only when [availability] is [LocationAvailability.ok]).
class LocationResult {
  const LocationResult(this.availability, [this.fix]);

  final LocationAvailability availability;
  final LocationFix? fix;

  bool get isOk => availability == LocationAvailability.ok && fix != null;
}

/// Wraps `geolocator`: permission flow + one-shot fixes + a position stream.
///
/// Extracted behind an interface so tests can inject a fake (mirrors the
/// repository fakes used across the app) without touching platform channels.
abstract class LocationService {
  /// Ensures we hold a usable (whileInUse) permission, requesting it if needed.
  /// Returns the resulting availability — never throws.
  Future<LocationAvailability> ensurePermission();

  /// One-shot current position, gated by [ensurePermission]. Never throws.
  Future<LocationResult> currentFix();

  /// A throttled stream of fixes (distance-filtered) for continuous tracking.
  /// Emits nothing until permission is granted and services are on.
  Stream<LocationFix> positionStream({int distanceFilterMeters = 5});
}

/// Production implementation backed by the `geolocator` plugin.
class GeolocatorLocationService implements LocationService {
  const GeolocatorLocationService();

  @override
  Future<LocationAvailability> ensurePermission() async {
    final servicesOn = await Geolocator.isLocationServiceEnabled();
    if (!servicesOn) return LocationAvailability.servicesDisabled;

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    return switch (permission) {
      LocationPermission.denied => LocationAvailability.denied,
      LocationPermission.deniedForever => LocationAvailability.deniedForever,
      LocationPermission.whileInUse ||
      LocationPermission.always =>
        LocationAvailability.ok,
      LocationPermission.unableToDetermine => LocationAvailability.unavailable,
    };
  }

  @override
  Future<LocationResult> currentFix() async {
    final availability = await ensurePermission();
    if (availability != LocationAvailability.ok) {
      return LocationResult(availability);
    }
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          // Geofence radius is small (15 m); a bounded fix keeps us responsive.
          timeLimit: Duration(seconds: 12),
        ),
      );
      return LocationResult(
        LocationAvailability.ok,
        LocationFix(
          latitude: position.latitude,
          longitude: position.longitude,
        ),
      );
    } catch (_) {
      return const LocationResult(LocationAvailability.unavailable);
    }
  }

  @override
  Stream<LocationFix> positionStream({int distanceFilterMeters = 5}) {
    return Geolocator.getPositionStream(
      locationSettings: LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: distanceFilterMeters,
      ),
    ).map(
      (p) => LocationFix(latitude: p.latitude, longitude: p.longitude),
    );
  }

  /// Opens the OS app-settings page so a deniedForever user can re-grant.
  static Future<void> openSettings() => Geolocator.openAppSettings();
}
