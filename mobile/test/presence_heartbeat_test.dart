import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:smart_queue_mobile/features/location/geofence_result.dart';
import 'package:smart_queue_mobile/features/location/location_repository.dart';
import 'package:smart_queue_mobile/features/location/location_service.dart';
import 'package:smart_queue_mobile/features/location/widgets/proximity_indicator.dart';
import 'package:smart_queue_mobile/features/presence/device_telemetry.dart';
import 'package:smart_queue_mobile/features/presence/heartbeat_controller.dart';
import 'package:smart_queue_mobile/features/presence/heartbeat_repository.dart';
import 'package:smart_queue_mobile/features/presence/heartbeat_result.dart';
import 'package:smart_queue_mobile/features/queue/queue_controller.dart';
import 'package:smart_queue_mobile/features/queue/queue_repository.dart';
import 'package:smart_queue_mobile/features/queue/queue_state.dart';
import 'package:smart_queue_mobile/features/queue/queue_ticket.dart';
import 'package:smart_queue_mobile/features/queue/ticket_eta.dart';
import 'package:smart_queue_mobile/theme/app_theme.dart';

/// Records every heartbeat payload so tests can assert cadence + coordinates.
class FakeHeartbeatRepository implements HeartbeatRepository {
  final List<({int? battery, String? network, LocationFix? fix})> calls = [];
  HeartbeatResult result = const HeartbeatResult(status: PresenceStatus.active);

  @override
  Future<HeartbeatResult> send({
    int? batteryLevel,
    String? networkStatus,
    LocationFix? gpsLocation,
  }) async {
    calls.add((battery: batteryLevel, network: networkStatus, fix: gpsLocation));
    return result;
  }
}

class FakeLocationRepository implements LocationRepository {
  GeofenceResult result =
      const GeofenceResult(withinRange: true, distanceM: 3, radiusM: 15);
  final List<LocationFix> calls = [];

  @override
  Future<GeofenceResult> update(LocationFix fix, {int? ticketId}) async {
    calls.add(fix);
    return result;
  }
}

class FakeLocationService implements LocationService {
  FakeLocationService({
    this.availability = LocationAvailability.ok,
    this.fix = const LocationFix(latitude: 14.5, longitude: 120.9),
  });

  LocationAvailability availability;
  LocationFix fix;

  @override
  Future<LocationAvailability> ensurePermission() async => availability;

  @override
  Future<LocationResult> currentFix() async {
    if (availability != LocationAvailability.ok) {
      return LocationResult(availability);
    }
    return LocationResult(LocationAvailability.ok, fix);
  }

  @override
  Stream<LocationFix> positionStream({int distanceFilterMeters = 5}) =>
      Stream<LocationFix>.value(fix);
}

class FakeDeviceTelemetry implements DeviceTelemetry {
  @override
  Future<int?> batteryLevel() async => 88;

  @override
  Future<String?> networkStatus() async => 'online';
}

/// Minimal queue repo so the queue controller builds without Dio.
class _StubQueueRepository implements QueueRepository {
  @override
  Future<QueueTicket> join(int serviceId) async => throw UnimplementedError();
  @override
  Future<QueueTicket?> status() async => null;
  @override
  Future<TicketEta?> estimate() async => null;
  @override
  Future<void> leave() async {}
}

QueueTicket _ticket({String status = 'waiting'}) => QueueTicket(
      id: 555,
      ticketNumber: 'REG-042',
      status: status,
      office: const TicketRef(id: 1, name: 'Registrar'),
      queueGroup: const TicketRef(id: 10, name: 'Records', prefix: 'REG'),
      service: const TicketRef(id: 100, name: 'Request TOR'),
    );

ProviderContainer _container({
  required FakeHeartbeatRepository heartbeat,
  required FakeLocationRepository location,
  required FakeLocationService gps,
}) {
  final container = ProviderContainer(
    overrides: [
      heartbeatRepositoryProvider.overrideWithValue(heartbeat),
      locationRepositoryProvider.overrideWithValue(location),
      locationServiceProvider.overrideWithValue(gps),
      deviceTelemetryProvider.overrideWithValue(FakeDeviceTelemetry()),
      queueRepositoryProvider.overrideWithValue(_StubQueueRepository()),
    ],
  );
  addTearDown(container.dispose);
  return container;
}

void main() {
  test('heartbeat fires on an active ticket and includes coordinates',
      () async {
    final heartbeat = FakeHeartbeatRepository();
    final container = _container(
      heartbeat: heartbeat,
      location: FakeLocationRepository(),
      gps: FakeLocationService(),
    );

    // Hold a subscription so the controller stays alive across rebuilds.
    container.listen(presenceControllerProvider, (_, _) {});
    await Future<void>.delayed(Duration.zero);
    expect(heartbeat.calls, isEmpty);
    expect(container.read(presenceControllerProvider).running, isFalse);

    // A ticket becomes active → loop starts and fires an immediate heartbeat.
    container.read(queueControllerProvider.notifier).state =
        QueueState(status: JoinStatus.joined, ticket: _ticket());
    await Future<void>.delayed(const Duration(milliseconds: 50));

    expect(container.read(presenceControllerProvider).running, isTrue);
    expect(heartbeat.calls, isNotEmpty);
    final first = heartbeat.calls.first;
    expect(first.battery, 88);
    expect(first.network, 'online');
    expect(first.fix, isNotNull);
    expect(first.fix!.latitude, 14.5);
    expect(first.fix!.longitude, 120.9);
    expect(
      container.read(presenceControllerProvider).presence,
      PresenceStatus.active,
    );
  });

  test('heartbeat stops and presence resets when no active ticket', () async {
    final heartbeat = FakeHeartbeatRepository();
    final container = _container(
      heartbeat: heartbeat,
      location: FakeLocationRepository(),
      gps: FakeLocationService(),
    );

    container.listen(presenceControllerProvider, (_, _) {});
    container.read(queueControllerProvider.notifier).state =
        QueueState(status: JoinStatus.joined, ticket: _ticket());
    await Future<void>.delayed(const Duration(milliseconds: 50));
    expect(container.read(presenceControllerProvider).running, isTrue);

    // Ticket reaches a terminal status → no longer "active" → loop stops.
    container.read(queueControllerProvider.notifier).state =
        QueueState(status: JoinStatus.joined, ticket: _ticket(status: 'served'));
    await Future<void>.delayed(const Duration(milliseconds: 20));

    final state = container.read(presenceControllerProvider);
    expect(state.running, isFalse);
    expect(state.geofence, isNull);
    expect(state.presence, PresenceStatus.unknown);
  });

  test('heartbeat carrying gps surfaces the server geofence verdict', () async {
    final heartbeat = FakeHeartbeatRepository()
      ..result = const HeartbeatResult(
        status: PresenceStatus.active,
        geofence: GeofenceResult(withinRange: false, distanceM: 42, radiusM: 15),
      );
    final location = FakeLocationRepository();
    final container = _container(
      heartbeat: heartbeat,
      location: location,
      gps: FakeLocationService(),
    );

    container.listen(presenceControllerProvider, (_, _) {});
    container.read(queueControllerProvider.notifier).state =
        QueueState(status: JoinStatus.joined, ticket: _ticket());
    await Future<void>.delayed(const Duration(milliseconds: 50));

    final geofence = container.read(presenceControllerProvider).geofence;
    expect(geofence, isNotNull);
    expect(geofence!.withinRange, isFalse);
    expect(geofence.distanceM, 42);
    // Heartbeat already carried the verdict → no duplicate /location/update.
    expect(location.calls, isEmpty);
  });

  testWidgets('ProximityIndicator renders in-range state', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: const Scaffold(
          body: ProximityIndicator(
            result: GeofenceResult(withinRange: true, distanceM: 3, radiusM: 15),
          ),
        ),
      ),
    );

    expect(find.text('You are within range'), findsOneWidget);
    expect(find.byIcon(Icons.check_circle_rounded), findsOneWidget);
  });

  testWidgets('ProximityIndicator renders out-of-range with distance',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: const Scaffold(
          body: ProximityIndicator(
            result:
                GeofenceResult(withinRange: false, distanceM: 42, radiusM: 15),
          ),
        ),
      ),
    );

    expect(find.text('Please move closer'), findsOneWidget);
    expect(find.textContaining('42m'), findsOneWidget);
    expect(find.byIcon(Icons.directions_walk_rounded), findsOneWidget);
  });
}
