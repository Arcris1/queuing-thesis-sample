import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:smart_queue_mobile/data/api_client.dart';
import 'package:smart_queue_mobile/features/catalog/catalog_providers.dart';
import 'package:smart_queue_mobile/features/catalog/catalog_repository.dart';
import 'package:smart_queue_mobile/features/catalog/office.dart';
import 'package:smart_queue_mobile/features/catalog/office_select_screen.dart';
import 'package:smart_queue_mobile/features/catalog/office_services.dart';
import 'package:smart_queue_mobile/features/catalog/queue_group.dart';
import 'package:smart_queue_mobile/features/catalog/service.dart';
import 'package:smart_queue_mobile/features/catalog/service_select_screen.dart';
import 'package:smart_queue_mobile/features/location/geofence_result.dart';
import 'package:smart_queue_mobile/features/location/location_repository.dart';
import 'package:smart_queue_mobile/features/location/location_service.dart';
import 'package:smart_queue_mobile/features/presence/device_telemetry.dart';
import 'package:smart_queue_mobile/features/presence/heartbeat_controller.dart';
import 'package:smart_queue_mobile/features/presence/heartbeat_repository.dart';
import 'package:smart_queue_mobile/features/presence/heartbeat_result.dart';
import 'package:smart_queue_mobile/features/queue/queue_controller.dart';
import 'package:smart_queue_mobile/features/queue/queue_repository.dart';
import 'package:smart_queue_mobile/features/queue/queue_ticket.dart';
import 'package:smart_queue_mobile/features/queue/ticket_eta.dart';
import 'package:smart_queue_mobile/theme/app_theme.dart';

/// Offline presence stubs so the ticket screen's heartbeat loop never touches
/// Dio / geolocator / secure storage during these join-flow widget tests.
class _OfflineHeartbeatRepository implements HeartbeatRepository {
  @override
  Future<HeartbeatResult> send({
    int? batteryLevel,
    String? networkStatus,
    LocationFix? gpsLocation,
  }) async =>
      const HeartbeatResult(status: PresenceStatus.active);
}

class _OfflineLocationRepository implements LocationRepository {
  @override
  Future<GeofenceResult> update(LocationFix fix, {int? ticketId}) async =>
      const GeofenceResult(withinRange: true);
}

class _OfflineLocationService implements LocationService {
  @override
  Future<LocationAvailability> ensurePermission() async =>
      LocationAvailability.ok;
  @override
  Future<LocationResult> currentFix() async => const LocationResult(
        LocationAvailability.ok,
        LocationFix(latitude: 0, longitude: 0),
      );
  @override
  Stream<LocationFix> positionStream({int distanceFilterMeters = 5}) =>
      const Stream<LocationFix>.empty();
}

class _OfflineTelemetry implements DeviceTelemetry {
  @override
  Future<int?> batteryLevel() async => 100;
  @override
  Future<String?> networkStatus() async => 'online';
}

/// In-memory fake of [CatalogRepository] — no Dio.
class FakeCatalogRepository implements CatalogRepository {
  FakeCatalogRepository({this.offices = const [], this.servicesByOffice});

  List<Office> offices;
  Map<int, OfficeServices>? servicesByOffice;
  Object? officesError;

  @override
  Future<List<Office>> getOffices() async {
    if (officesError != null) throw officesError!;
    return offices;
  }

  @override
  Future<OfficeServices> getServices(int officeId) async {
    final result = servicesByOffice?[officeId];
    if (result == null) {
      throw const ApiException('not found', statusCode: 404);
    }
    return result;
  }
}

/// In-memory fake of [QueueRepository] — scripts join success / 409 / error.
class FakeQueueRepository implements QueueRepository {
  FakeQueueRepository({this.joinResult, this.joinError, this.statusResult});

  QueueTicket? joinResult;
  Object? joinError;
  QueueTicket? statusResult;

  @override
  Future<QueueTicket> join(int serviceId) async {
    if (joinError != null) throw joinError!;
    return joinResult!;
  }

  @override
  Future<QueueTicket?> status() async => statusResult;

  @override
  Future<TicketEta?> estimate() async => null;

  @override
  Future<void> leave() async {}
}

const _registrar = Office(
  id: 1,
  name: 'Registrar',
  latitude: 14.5,
  longitude: 120.9,
  geofenceRadiusM: 15,
);

OfficeServices _registrarServices() => const OfficeServices(
      office: _registrar,
      queueGroups: [
        QueueGroup(
          id: 10,
          name: 'Records',
          prefix: 'REG',
          services: [
            Service(id: 100, name: 'Request TOR', avgServiceMinutes: 8),
            Service(id: 101, name: 'Certificate of Enrollment'),
          ],
        ),
        QueueGroup(
          id: 11,
          name: 'Evaluation',
          prefix: 'EVL',
          services: [
            Service(id: 102, name: 'Grade Evaluation', avgServiceMinutes: 12),
          ],
        ),
      ],
    );

QueueTicket _ticket({int? peopleAhead = 3, int? position = 4}) => QueueTicket(
      id: 555,
      ticketNumber: 'REG-042',
      status: 'waiting',
      priority: 0,
      position: position,
      peopleAhead: peopleAhead,
      currentNumber: 'REG-038',
      joinedAt: DateTime(2026, 6, 18, 9, 0),
      office: const TicketRef(id: 1, name: 'Registrar'),
      queueGroup: const TicketRef(id: 10, name: 'Records', prefix: 'REG'),
      service: const TicketRef(id: 100, name: 'Request TOR'),
    );

Widget _wrap({
  required Widget home,
  FakeCatalogRepository? catalog,
  FakeQueueRepository? queue,
}) {
  return ProviderScope(
    overrides: [
      if (catalog != null)
        catalogRepositoryProvider.overrideWithValue(catalog),
      if (queue != null) queueRepositoryProvider.overrideWithValue(queue),
      // Keep the ticket screen's presence loop fully offline in these tests.
      heartbeatRepositoryProvider
          .overrideWithValue(_OfflineHeartbeatRepository()),
      locationRepositoryProvider
          .overrideWithValue(_OfflineLocationRepository()),
      locationServiceProvider.overrideWithValue(_OfflineLocationService()),
      deviceTelemetryProvider.overrideWithValue(_OfflineTelemetry()),
    ],
    child: MaterialApp(theme: AppTheme.light(), home: home),
  );
}

void main() {
  testWidgets('Office select renders all offices', (tester) async {
    final catalog = FakeCatalogRepository(offices: const [
      _registrar,
      Office(id: 2, name: 'Accounting'),
      Office(id: 3, name: 'Cashier'),
    ]);

    await tester.pumpWidget(
      _wrap(catalog: catalog, home: const OfficeSelectScreen()),
    );
    await tester.pumpAndSettle();

    expect(find.text('Registrar'), findsOneWidget);
    expect(find.text('Accounting'), findsOneWidget);
    expect(find.text('Cashier'), findsOneWidget);
  });

  testWidgets('Office select shows empty state when no offices',
      (tester) async {
    final catalog = FakeCatalogRepository(offices: const []);

    await tester.pumpWidget(
      _wrap(catalog: catalog, home: const OfficeSelectScreen()),
    );
    await tester.pumpAndSettle();

    expect(find.text('No offices available'), findsOneWidget);
  });

  testWidgets('Service select groups services under their queue group',
      (tester) async {
    final catalog = FakeCatalogRepository(
      servicesByOffice: {1: _registrarServices()},
    );

    await tester.pumpWidget(
      _wrap(
        catalog: catalog,
        home: const ServiceSelectScreen(office: _registrar),
      ),
    );
    await tester.pumpAndSettle();

    // Queue-group headers with prefix chips.
    expect(find.text('Records'), findsOneWidget);
    expect(find.text('Evaluation'), findsOneWidget);
    expect(find.text('REG'), findsOneWidget);
    expect(find.text('EVL'), findsOneWidget);

    // Services rendered beneath their groups.
    expect(find.text('Request TOR'), findsOneWidget);
    expect(find.text('Certificate of Enrollment'), findsOneWidget);
    expect(find.text('Grade Evaluation'), findsOneWidget);

    // Avg duration surfaced as context.
    expect(find.text('About 8 min'), findsOneWidget);
  });

  testWidgets('Joining a service shows the issued ticket number',
      (tester) async {
    final catalog = FakeCatalogRepository(
      servicesByOffice: {1: _registrarServices()},
    );
    final queue = FakeQueueRepository(joinResult: _ticket());

    await tester.pumpWidget(
      _wrap(
        catalog: catalog,
        queue: queue,
        home: const ServiceSelectScreen(office: _registrar),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Request TOR'));
    await tester.pumpAndSettle();

    // Confirm sheet → join.
    expect(find.text('Join this queue?'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Join queue'));
    await tester.pumpAndSettle();

    // Confirmation screen with the prominent ticket number.
    expect(find.text('REG-042'), findsOneWidget);
    expect(find.text("You're in line"), findsOneWidget);
  });

  testWidgets('Already-in-queue (409) routes to the existing ticket',
      (tester) async {
    final catalog = FakeCatalogRepository(
      servicesByOffice: {1: _registrarServices()},
    );
    final queue = FakeQueueRepository(
      joinError: const ApiException(
        'You are already in an active queue for this group.',
        statusCode: 409,
      ),
      statusResult: _ticket(position: 7, peopleAhead: 6),
    );

    await tester.pumpWidget(
      _wrap(
        catalog: catalog,
        queue: queue,
        home: const ServiceSelectScreen(office: _registrar),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Request TOR'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Join queue'));
    await tester.pumpAndSettle();

    expect(find.text("You're already in this line"), findsOneWidget);
    expect(find.text('REG-042'), findsOneWidget);
  });

  testWidgets('Already-in-queue with no recoverable ticket shows a message',
      (tester) async {
    final catalog = FakeCatalogRepository(
      servicesByOffice: {1: _registrarServices()},
    );
    final queue = FakeQueueRepository(
      joinError: const ApiException(
        'You are already in an active queue for this group.',
        statusCode: 409,
      ),
      statusResult: null,
    );

    await tester.pumpWidget(
      _wrap(
        catalog: catalog,
        queue: queue,
        home: const ServiceSelectScreen(office: _registrar),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Request TOR'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Join queue'));
    await tester.pumpAndSettle();

    expect(
      find.text('You are already in an active queue for this group.'),
      findsOneWidget,
    );
    // Did not navigate to a ticket screen.
    expect(find.text("You're already in this line"), findsNothing);
  });
}
