import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:smart_queue_mobile/data/api_client.dart';
import 'package:smart_queue_mobile/features/checkin/checkin_controller.dart';
import 'package:smart_queue_mobile/features/checkin/checkin_payload.dart';
import 'package:smart_queue_mobile/features/checkin/checkin_repository.dart';
import 'package:smart_queue_mobile/features/location/location_service.dart';
import 'package:smart_queue_mobile/features/presence/heartbeat_controller.dart'
    show locationServiceProvider;
import 'package:smart_queue_mobile/features/queue/active_ticket_provider.dart';
import 'package:smart_queue_mobile/features/queue/queue_ticket.dart';

QueueTicket _readyTicket() => const QueueTicket(
      id: 7,
      ticketNumber: 'A-007',
      status: 'ready',
      office: TicketRef(id: 1, name: 'Registrar'),
      queueGroup: TicketRef(id: 10, name: 'Records', prefix: 'A'),
      service: TicketRef(id: 100, name: 'Request TOR'),
    );

/// Records the check-in call and returns a scripted outcome.
class _FakeCheckinRepository implements CheckinRepository {
  _FakeCheckinRepository({this.result, this.error});

  final QueueTicket? result;
  final Object? error;

  String? lastTicketNumber;
  LocationFix? lastFix;

  @override
  Future<QueueTicket> checkin({
    required String ticketNumber,
    required LocationFix fix,
  }) async {
    lastTicketNumber = ticketNumber;
    lastFix = fix;
    if (error != null) throw error!;
    return result!;
  }
}

class _FixedLocationService implements LocationService {
  _FixedLocationService(this._result);
  final LocationResult _result;

  @override
  Future<LocationAvailability> ensurePermission() async => _result.availability;
  @override
  Future<LocationResult> currentFix() async => _result;
  @override
  Stream<LocationFix> positionStream({int distanceFilterMeters = 5}) =>
      const Stream<LocationFix>.empty();
}

ProviderContainer _container({
  required CheckinRepository repo,
  required LocationService gps,
}) {
  final c = ProviderContainer(
    overrides: [
      checkinRepositoryProvider.overrideWithValue(repo),
      locationServiceProvider.overrideWithValue(gps),
    ],
  );
  addTearDown(c.dispose);
  return c;
}

void main() {
  group('CheckinPayload', () {
    test('parses a valid envelope', () {
      final p = CheckinPayload.tryParse('{"t":"qms-checkin","ticket_number":"A-007"}');
      expect(p, isNotNull);
      expect(p!.ticketNumber, 'A-007');
    });

    test('rejects wrong tag, missing ticket, and non-JSON', () {
      expect(CheckinPayload.tryParse('{"t":"other","ticket_number":"A-1"}'), isNull);
      expect(CheckinPayload.tryParse('{"t":"qms-checkin"}'), isNull);
      expect(CheckinPayload.tryParse('https://example.com'), isNull);
      expect(CheckinPayload.tryParse(null), isNull);
    });
  });

  test('a valid QR payload checks in and routes on success', () async {
    final repo = _FakeCheckinRepository(result: _readyTicket());
    final gps = _FixedLocationService(
      const LocationResult(
        LocationAvailability.ok,
        LocationFix(latitude: 14.6, longitude: 121.0),
      ),
    );
    final container = _container(repo: repo, gps: gps);
    // Hold derived providers so they aren't disposed mid-async-tick.
    container.listen(activeTicketProvider, (_, _) {});

    final notifier = container.read(checkinControllerProvider.notifier);
    await notifier.onScan('{"t":"qms-checkin","ticket_number":"A-007"}');

    final state = container.read(checkinControllerProvider);
    expect(state.phase, CheckinPhase.success);
    expect(state.ticket?.ticketNumber, 'A-007');
    // The decoded ticket number + the GPS fix reached the repository.
    expect(repo.lastTicketNumber, 'A-007');
    expect(repo.lastFix?.latitude, 14.6);

    // Routing signal: the Ready ticket is now the active ticket, so the live
    // status screen reflects it when we pop back.
    final active = container.read(activeTicketProvider);
    expect(active?.ticketNumber, 'A-007');
  });

  test('an out-of-range 409 surfaces the distance message', () async {
    final repo = _FakeCheckinRepository(
      error: const CheckinOutOfRange(distanceM: 32, radiusM: 15),
    );
    final gps = _FixedLocationService(
      const LocationResult(
        LocationAvailability.ok,
        LocationFix(latitude: 1, longitude: 2),
      ),
    );
    final container = _container(repo: repo, gps: gps);

    final notifier = container.read(checkinControllerProvider.notifier);
    await notifier.onScan('{"t":"qms-checkin","ticket_number":"A-007"}');

    final state = container.read(checkinControllerProvider);
    expect(state.phase, CheckinPhase.outOfRange);
    expect(state.distanceM, 32);
    expect(state.radiusM, 15);
  });

  test('a non-checkin QR is ignored (stays scanning)', () async {
    final repo = _FakeCheckinRepository(result: _readyTicket());
    final gps = _FixedLocationService(
      const LocationResult(
        LocationAvailability.ok,
        LocationFix(latitude: 0, longitude: 0),
      ),
    );
    final container = _container(repo: repo, gps: gps);

    final notifier = container.read(checkinControllerProvider.notifier);
    await notifier.onScan('not a smart-queue code');

    expect(container.read(checkinControllerProvider).phase,
        CheckinPhase.scanning);
    expect(repo.lastTicketNumber, isNull);
  });

  test('a denied location stops before the network with a clear message',
      () async {
    final repo = _FakeCheckinRepository(result: _readyTicket());
    final gps = _FixedLocationService(
      const LocationResult(LocationAvailability.denied),
    );
    final container = _container(repo: repo, gps: gps);

    final notifier = container.read(checkinControllerProvider.notifier);
    await notifier.onScan('{"t":"qms-checkin","ticket_number":"A-007"}');

    final state = container.read(checkinControllerProvider);
    expect(state.phase, CheckinPhase.error);
    expect(state.message, contains('location'));
    expect(repo.lastTicketNumber, isNull); // never hit the server
  });

  test('CheckinRepository maps a 409 body to CheckinOutOfRange', () async {
    final repo = CheckinRepository(apiClient: _ThrowingApiClient());
    await expectLater(
      repo.checkin(
        ticketNumber: 'A-007',
        fix: const LocationFix(latitude: 0, longitude: 0),
      ),
      throwsA(isA<CheckinOutOfRange>()
          .having((e) => e.distanceM, 'distanceM', 40)
          .having((e) => e.radiusM, 'radiusM', 15)),
    );
  });
}

/// Minimal ApiClient stand-in that throws the 409 the real client would map.
class _ThrowingApiClient implements ApiClient {
  @override
  Future<Map<String, dynamic>> post(String path, {Object? data}) async {
    throw const ApiException(
      'Something went wrong. Please try again.',
      statusCode: 409,
      body: {'distance_m': 40, 'radius_m': 15},
    );
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
