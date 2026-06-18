import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:smart_queue_mobile/features/notifications/notification_controller.dart';
import 'package:smart_queue_mobile/features/notifications/notification_service.dart';
import 'package:smart_queue_mobile/features/notifications/push_transport.dart';
import 'package:smart_queue_mobile/features/queue/queue_ticket.dart';
import 'package:smart_queue_mobile/features/realtime/realtime_client.dart';
import 'package:smart_queue_mobile/features/realtime/realtime_event.dart';
import 'package:smart_queue_mobile/features/realtime/realtime_providers.dart';

/// Spy notification service: records everything [show]n + replays taps.
class SpyNotificationService implements NotificationService {
  final List<QueueNotification> shown = [];
  final _tap = StreamController<int?>.broadcast();
  bool initCalled = false;
  bool permissionRequested = false;

  void tap(int? ticketId) => _tap.add(ticketId);

  @override
  Future<void> init() async => initCalled = true;
  @override
  Future<bool> requestPermission() async {
    permissionRequested = true;
    return true;
  }

  @override
  Future<void> show(QueueNotification notification) async =>
      shown.add(notification);

  @override
  Stream<int?> get onTap => _tap.stream;
}

/// Hand-driven realtime client (same pattern as ticket_status_test).
class FakeRealtimeClient implements RealtimeClient {
  final _controller = StreamController<RealtimeEvent>.broadcast();
  void emit(RealtimeEvent e) => _controller.add(e);

  @override
  Stream<RealtimeEvent> get events => _controller.stream;
  @override
  void start(int ticketId) {}
  @override
  void stop() {}
  @override
  Future<void> refreshNow() async {}
  @override
  Future<void> dispose() async => _controller.close();
}

QueueTicket _ticket({String status = 'waiting', int? position}) => QueueTicket(
      id: 7,
      ticketNumber: 'A-007',
      status: status,
      position: position,
      windowName: 'Window 3',
      office: const TicketRef(id: 1, name: 'Registrar'),
      queueGroup: const TicketRef(id: 10, name: 'Records', prefix: 'A'),
      service: const TicketRef(id: 100, name: 'Request TOR'),
    );

ProviderContainer _container(SpyNotificationService spy, FakeRealtimeClient rt) {
  final c = ProviderContainer(
    overrides: [
      notificationServiceProvider.overrideWithValue(spy),
      realtimeClientProvider.overrideWithValue(rt),
      pushTransportProvider.overrideWithValue(const LocalPushTransport()),
    ],
  );
  addTearDown(c.dispose);
  return c;
}

/// Pumps microtasks so the controller's deferred `_start` (and its async
/// init/permission/subscribe) completes before we assert.
Future<void> _settle() => Future<void>.delayed(Duration.zero);

void main() {
  test('a ticket.called event shows a high-priority call notification',
      () async {
    final spy = SpyNotificationService();
    final rt = FakeRealtimeClient();
    final container = _container(spy, rt);

    // Watch keeps the controller alive (mirrors _AuthenticatedRoot).
    container.listen(notificationControllerProvider, (_, _) {});
    await _settle();
    expect(spy.initCalled, isTrue);
    expect(spy.permissionRequested, isTrue);

    rt.emit(const TicketCalledEvent(
      ticketId: 7,
      windowName: 'Window 3',
      officeName: 'Registrar',
    ));
    await _settle();

    expect(spy.shown, isNotEmpty);
    final note = spy.shown.last;
    expect(note.channel, NotificationChannel.call);
    expect(note.title, "It's your turn");
    expect(note.body, contains('Window 3'));
  });

  test('a milestone position fires a single debounced update notification',
      () async {
    final spy = SpyNotificationService();
    final rt = FakeRealtimeClient();
    final container = _container(spy, rt);
    container.listen(notificationControllerProvider, (_, _) {});
    await _settle();

    // Seed at position 6 (no milestone), then cross 5, then poll 5 again.
    rt.emit(TicketSnapshotEvent(_ticket(position: 6)));
    rt.emit(TicketSnapshotEvent(_ticket(position: 5)));
    rt.emit(TicketSnapshotEvent(_ticket(position: 5)));
    await _settle();

    final milestones =
        spy.shown.where((n) => n.channel == NotificationChannel.milestone);
    expect(milestones.length, 1); // debounced: only the downward crossing
    expect(milestones.first.body, contains('5 people ahead'));
  });

  test('a notification tap sets the deep-link ticket id', () async {
    final spy = SpyNotificationService();
    final rt = FakeRealtimeClient();
    final container = _container(spy, rt);
    container.listen(notificationControllerProvider, (_, _) {});
    container.listen(notificationDeepLinkProvider, (_, _) {});
    await _settle();

    spy.tap(7);
    await _settle();

    expect(container.read(notificationDeepLinkProvider), 7);
  });
}
