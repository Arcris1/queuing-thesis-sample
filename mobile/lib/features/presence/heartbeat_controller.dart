import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/config.dart';
import '../auth/auth_controller.dart';
import '../location/geofence_result.dart';
import '../location/location_repository.dart';
import '../location/location_service.dart';
import '../queue/active_ticket_provider.dart';
import 'device_telemetry.dart';
import 'heartbeat_repository.dart';
import 'heartbeat_result.dart';
import 'presence_state.dart';

/// Heartbeat endpoint wrapper. Overridable in tests.
final heartbeatRepositoryProvider = Provider<HeartbeatRepository>((ref) {
  return HeartbeatRepository(apiClient: ref.watch(apiClientProvider));
});

/// Geofence (`/location/update`) endpoint wrapper. Overridable in tests.
final locationRepositoryProvider = Provider<LocationRepository>((ref) {
  return LocationRepository(apiClient: ref.watch(apiClientProvider));
});

/// Wraps geolocator (permission + fixes). Overridable in tests.
final locationServiceProvider = Provider<LocationService>(
  (ref) => const GeolocatorLocationService(),
);

/// Device battery/network signals. Overridable in tests.
final deviceTelemetryProvider = Provider<DeviceTelemetry>(
  (ref) => PlatformDeviceTelemetry(),
);

/// Owns the presence/geofence loop: a ~30 s timer that, while a ticket is
/// active, sends a heartbeat carrying battery, network, and the current GPS fix
/// (§9). Because a heartbeat with `gps_location` doubles as a location ping, we
/// route location through it instead of a separate `/location/update`.
final presenceControllerProvider =
    NotifierProvider<PresenceController, PresenceState>(PresenceController.new);

/// Convenience selectors for the ticket screen (task 029).
final presenceStatusProvider = Provider<PresenceStatus>(
  (ref) => ref.watch(presenceControllerProvider).presence,
);
final geofenceResultProvider = Provider<GeofenceResult?>(
  (ref) => ref.watch(presenceControllerProvider).geofence,
);

class PresenceController extends Notifier<PresenceState> {
  Timer? _timer;
  bool _sending = false;

  /// True while the loop is meant to be running. An in-flight [_tick] that
  /// resolves after [_stop] checks this and discards its writes, so a late
  /// response can't resurrect a stopped loop's geofence/presence.
  bool _active = false;

  HeartbeatRepository get _heartbeat => ref.read(heartbeatRepositoryProvider);
  LocationRepository get _location => ref.read(locationRepositoryProvider);
  LocationService get _gps => ref.read(locationServiceProvider);
  DeviceTelemetry get _telemetry => ref.read(deviceTelemetryProvider);

  bool _disposed = false;

  @override
  PresenceState build() {
    // Lifecycle-aware: start/stop the loop as the active ticket appears or
    // clears. Keeps the loop off when there's nothing to keep alive (battery).
    //
    // The callback is deferred to a microtask so it never touches `state`
    // while `build` is still running (which would read an uninitialized
    // provider) — important because the ticket may already be active when this
    // controller first builds.
    ref.listen<bool>(
      hasActiveTicketProvider,
      (previous, hasTicket) {
        Future.microtask(() {
          if (_disposed) return;
          if (hasTicket) {
            _start();
          } else if (previous == true) {
            _stop();
          }
        });
      },
      fireImmediately: true,
    );

    ref.onDispose(() {
      _disposed = true;
      _active = false;
      _timer?.cancel();
    });
    return const PresenceState();
  }

  /// Begins the heartbeat loop and fires one immediately so the server marks
  /// the student Active without waiting a full interval.
  void _start() {
    if (_timer != null) return;
    _active = true;
    state = state.copyWith(running: true);
    unawaited(ensurePermissionAndPing());
    _timer = Timer.periodic(
      AppConfig.heartbeatInterval,
      (_) => unawaited(_tick()),
    );
  }

  void _stop() {
    _active = false;
    _timer?.cancel();
    _timer = null;
    if (state.running || state.geofence != null) {
      state = PresenceState(
        locationAvailability: state.locationAvailability,
      );
    }
  }

  /// Public hook for startup wiring: request permission (rationale UI is shown
  /// before this is called), then send the first heartbeat.
  Future<void> ensurePermissionAndPing() async {
    final availability = await _gps.ensurePermission();
    if (_disposed) return;
    state = state.copyWith(locationAvailability: availability);
    await _tick();
  }

  /// One heartbeat: gather telemetry + a GPS fix, POST, and fold the result.
  /// Resilient — transient failures are swallowed; the next tick retries.
  Future<void> _tick() async {
    if (_sending) return; // never overlap if a prior tick is still in flight
    _sending = true;
    try {
      final battery = await _telemetry.batteryLevel();
      final network = await _telemetry.networkStatus();

      LocationFix? fix;
      final fixResult = await _gps.currentFix();
      if (_disposed || !_active) return;
      state = state.copyWith(locationAvailability: fixResult.availability);
      if (fixResult.isOk) fix = fixResult.fix;

      final result = await _heartbeat.send(
        batteryLevel: battery,
        networkStatus: network,
        gpsLocation: fix,
      );

      // Prefer the geofence the heartbeat already computed; only fall back to a
      // dedicated /location/update if we had a fix but the server didn't echo
      // one back (keeps us from a duplicate call in the common path).
      var geofence = result.geofence;
      if (geofence == null && fix != null) {
        geofence = await _safeLocationUpdate(fix);
      }

      // Loop may have been stopped while this tick was in flight — discard.
      if (_disposed || !_active) return;
      state = state.copyWith(
        presence: result.status,
        lastSeen: result.lastSeen,
        geofence: geofence,
      );
    } catch (_) {
      // Transient network/server error — keep the loop alive, retry next tick.
    } finally {
      _sending = false;
    }
  }

  Future<GeofenceResult?> _safeLocationUpdate(LocationFix fix) async {
    final ticketId = ref.read(activeTicketProvider)?.id;
    try {
      return await _location.update(fix, ticketId: ticketId);
    } catch (_) {
      return null;
    }
  }
}
