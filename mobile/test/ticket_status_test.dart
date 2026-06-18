import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:smart_queue_mobile/features/location/geofence_result.dart';
import 'package:smart_queue_mobile/features/location/location_repository.dart';
import 'package:smart_queue_mobile/features/location/location_service.dart';
import 'package:smart_queue_mobile/features/presence/device_telemetry.dart';
import 'package:smart_queue_mobile/features/presence/heartbeat_controller.dart';
import 'package:smart_queue_mobile/features/presence/heartbeat_repository.dart';
import 'package:smart_queue_mobile/features/presence/heartbeat_result.dart';
import 'package:smart_queue_mobile/features/queue/queue_controller.dart';
import 'package:smart_queue_mobile/features/queue/queue_repository.dart';
import 'package:smart_queue_mobile/features/queue/ticket_eta.dart';
import 'package:smart_queue_mobile/features/queue/ticket_status_screen.dart';
import 'package:smart_queue_mobile/features/queue/queue_ticket.dart';
import 'package:smart_queue_mobile/features/realtime/realtime_client.dart';
import 'package:smart_queue_mobile/features/realtime/realtime_event.dart';
import 'package:smart_queue_mobile/features/realtime/realtime_providers.dart';
import 'package:smart_queue_mobile/theme/app_theme.dart';

/// Hand-driven realtime client: tests push events to flip the screen's state.
class FakeRealtimeClient implements RealtimeClient {
  final _controller = StreamController<RealtimeEvent>.broadcast();
  bool started = false;
  int startCount = 0;

  void emit(RealtimeEvent event) => _controller.add(event);

  @override
  Stream<RealtimeEvent> get events => _controller.stream;

  @override
  void start(int ticketId) {
    started = true;
    startCount++;
  }

  @override
  void stop() => started = false;

  @override
  Future<void> refreshNow() async {}

  @override
  Future<void> dispose() async {
    await _controller.close();
  }
}

/// Records leave() so the test can assert it was called.
class FakeQueueRepository implements QueueRepository {
  bool leaveCalled = false;
  TicketEta? estimateResult;

  @override
  Future<QueueTicket> join(int serviceId) async => throw UnimplementedError();
  @override
  Future<QueueTicket?> status() async => null;
  @override
  Future<TicketEta?> estimate() async => estimateResult;
  @override
  Future<void> leave() async => leaveCalled = true;
}

// Offline presence stubs so the heartbeat loop never touches platform plugins.
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

QueueTicket _ticket({
  String status = 'waiting',
  int? position = 4,
  int? peopleAhead = 3,
  TicketEta? eta,
  String? windowName,
}) =>
    QueueTicket(
      id: 555,
      ticketNumber: 'REG-042',
      status: status,
      position: position,
      peopleAhead: peopleAhead,
      currentNumber: 'REG-038',
      etaPrediction: eta,
      windowName: windowName,
      office: const TicketRef(id: 1, name: 'Registrar'),
      queueGroup: const TicketRef(id: 10, name: 'Records', prefix: 'REG'),
      service: const TicketRef(id: 100, name: 'Request TOR'),
    );

Widget _wrap({
  required QueueTicket initial,
  required FakeRealtimeClient client,
  FakeQueueRepository? queue,
}) {
  return ProviderScope(
    overrides: [
      realtimeClientProvider.overrideWithValue(client),
      if (queue != null) queueRepositoryProvider.overrideWithValue(queue),
      heartbeatRepositoryProvider
          .overrideWithValue(_OfflineHeartbeatRepository()),
      locationRepositoryProvider.overrideWithValue(_OfflineLocationRepository()),
      locationServiceProvider.overrideWithValue(_OfflineLocationService()),
      deviceTelemetryProvider.overrideWithValue(_OfflineTelemetry()),
    ],
    child: MaterialApp(
      theme: AppTheme.light(),
      home: TicketStatusScreen(initialTicket: initial),
    ),
  );
}

void main() {
  testWidgets('renders ticket number, position, people-ahead and AI ETA',
      (tester) async {
    final client = FakeRealtimeClient();
    await tester.pumpWidget(
      _wrap(
        initial: _ticket(
          eta: const TicketEta(
            estimatedMinutes: 12,
            confidence: 0.8,
            basis: 'model',
          ),
        ),
        client: client,
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('REG-042'), findsOneWidget);
    expect(find.text('4'), findsOneWidget); // position
    expect(find.text('3'), findsOneWidget); // people ahead
    expect(client.started, isTrue);

    // ETA card sits lower in the scroll view — bring it into view first.
    await tester.scrollUntilVisible(find.text('AI estimate'), 200);
    expect(find.text('AI estimate'), findsOneWidget);
    expect(find.text('~12 minutes'), findsOneWidget);
    expect(find.text('High confidence'), findsOneWidget);
  });

  testWidgets('fallback ETA is shown gracefully', (tester) async {
    final client = FakeRealtimeClient();
    await tester.pumpWidget(
      _wrap(
        initial: _ticket(
          eta: const TicketEta(estimatedMinutes: 20, basis: 'fallback'),
        ),
        client: client,
      ),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(find.text('~20 minutes'), 200);
    expect(find.text('~20 minutes'), findsOneWidget);
    expect(find.textContaining('Rough estimate'), findsOneWidget);
  });

  testWidgets('a snapshot update reflects new position live', (tester) async {
    final client = FakeRealtimeClient();
    await tester.pumpWidget(
      _wrap(initial: _ticket(position: 4, peopleAhead: 3), client: client),
    );
    await tester.pumpAndSettle();
    expect(find.text('4'), findsOneWidget);

    client.emit(TicketSnapshotEvent(_ticket(position: 2, peopleAhead: 1)));
    await tester.pumpAndSettle();

    expect(find.text('2'), findsOneWidget);
    expect(find.text('1'), findsOneWidget);
  });

  testWidgets('a ticket.called event flips to the proceed state',
      (tester) async {
    final client = FakeRealtimeClient();
    await tester.pumpWidget(_wrap(initial: _ticket(), client: client));
    await tester.pumpAndSettle();

    expect(find.text("It's your turn"), findsNothing);

    client.emit(const TicketCalledEvent(
      ticketId: 555,
      windowName: 'Window 3',
      officeName: 'Registrar',
    ));
    // The proceed state has a looping pulse animation, so pump fixed frames
    // (pumpAndSettle would never settle on the repeating controller).
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text("It's your turn"), findsOneWidget);
    expect(find.text('Proceed to Window 3'), findsOneWidget);
  });

  testWidgets('a served snapshot shows the done state', (tester) async {
    final client = FakeRealtimeClient();
    await tester.pumpWidget(
      _wrap(initial: _ticket(status: 'serving'), client: client),
    );
    await tester.pumpAndSettle();

    client.emit(TicketSnapshotEvent(_ticket(status: 'served')));
    await tester.pumpAndSettle();

    expect(find.text('All done'), findsOneWidget);
    expect(find.text('Back to home'), findsOneWidget);
  });

  testWidgets('leaving the queue confirms then clears to the empty CTA',
      (tester) async {
    final client = FakeRealtimeClient();
    final queue = FakeQueueRepository();
    await tester.pumpWidget(
      _wrap(initial: _ticket(), client: client, queue: queue),
    );
    await tester.pumpAndSettle();

    final leaveBtn = find.widgetWithText(OutlinedButton, 'Leave queue');
    await tester.scrollUntilVisible(leaveBtn, 200);
    await tester.tap(leaveBtn);
    await tester.pumpAndSettle();

    // Confirm dialog.
    expect(find.text('Leave the queue?'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Leave'));
    await tester.pumpAndSettle();

    expect(queue.leaveCalled, isTrue);
    expect(find.text("You're not in a queue"), findsOneWidget);
  });

  testWidgets('a cleared event from waiting shows the empty CTA',
      (tester) async {
    final client = FakeRealtimeClient();
    await tester.pumpWidget(_wrap(initial: _ticket(), client: client));
    await tester.pumpAndSettle();

    client.emit(const TicketClearedEvent());
    await tester.pumpAndSettle();

    expect(find.text("You're not in a queue"), findsOneWidget);
  });
}
