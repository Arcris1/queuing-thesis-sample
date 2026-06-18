import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/config.dart';
import '../auth/auth_controller.dart';
import '../queue/queue_ticket.dart';
import '../queue/ticket_status.dart';
import '../realtime/realtime_client.dart';
import '../realtime/realtime_event.dart';
import '../realtime/realtime_providers.dart';
import 'fcm_push_transport.dart';
import 'fcm_token_repository.dart';
import 'notification_service.dart';
import 'push_transport.dart';

/// The notification display service (local notifications). Overridable in tests.
final notificationServiceProvider = Provider<NotificationService>((ref) {
  return LocalNotificationService();
});

/// The remote push transport, selected by [AppConfig.pushTransport].
/// Defaults to a no-op local transport (realtime-driven). Overridable in tests.
final pushTransportProvider = Provider<PushTransport>((ref) {
  if (AppConfig.useFirebaseMessaging) return FcmPushTransport();
  return const LocalPushTransport();
});

/// Sends the FCM token to the backend. Overridable in tests.
final fcmTokenRepositoryProvider = Provider<FcmTokenRepository>((ref) {
  return FcmTokenRepository(apiClient: ref.watch(apiClientProvider));
});

/// A ticket id to deep-link to, set when the user taps a notification. The
/// router/UI watches this and routes to the live status screen. `-1` is a
/// generic "open the ticket screen" signal (tap without a specific id).
final notificationDeepLinkProvider =
    NotifierProvider<NotificationDeepLink, int?>(NotificationDeepLink.new);

class NotificationDeepLink extends Notifier<int?> {
  @override
  int? build() => null;

  void open(int? ticketId) => state = ticketId ?? -1;

  void clear() => state = null;
}

/// Wires queue events to OS notifications (task 033).
///
/// Always-on display path: it folds [RealtimeEvent]s from the active transport
/// into notifications — a high-priority "it's your turn" on a `ticket.called`,
/// and debounced position-milestone updates from snapshots. This gives a
/// working, demoable push experience with no Firebase project.
///
/// FCM seam: when [AppConfig.pushTransport] is `fcm`, the [PushTransport]
/// additionally yields a device token (POSTed to `/api/me/fcm-token`) and
/// routes remote foreground/background/terminated messages into the same
/// [NotificationService.show] path. Off by default, no-op when unconfigured.
///
/// Kept alive app-wide (watched by the authenticated root) so it runs for the
/// whole signed-in session regardless of which screen is on top.
final notificationControllerProvider =
    NotifierProvider<NotificationController, NotificationStatus>(
  NotificationController.new,
);

@immutable
class NotificationStatus {
  const NotificationStatus({this.permissionGranted = false});

  final bool permissionGranted;

  NotificationStatus copyWith({bool? permissionGranted}) => NotificationStatus(
        permissionGranted: permissionGranted ?? this.permissionGranted,
      );
}

class NotificationController extends Notifier<NotificationStatus> {
  StreamSubscription<RealtimeEvent>? _eventSub;
  StreamSubscription<String>? _tokenSub;
  StreamSubscription<QueueNotification>? _remoteSub;
  StreamSubscription<int?>? _tapSub;
  RealtimeClient? _client;
  PushTransport? _transport;
  bool _disposed = false;
  bool _started = false;

  NotificationService get _service => ref.read(notificationServiceProvider);

  /// The last position we announced a milestone for, so we don't re-notify on
  /// every poll. Resets when the ticket clears.
  int? _lastMilestonePosition;

  /// Notification ids by purpose, so an updated call/milestone replaces (not
  /// stacks on) the previous one.
  static const int _callId = 1001;
  static const int _milestoneId = 1002;

  /// Position thresholds worth a heads-up. Crossing one (downward) notifies.
  static const Set<int> _milestones = {5, 3, 1};

  @override
  NotificationStatus build() {
    ref.onDispose(() {
      _disposed = true;
      _eventSub?.cancel();
      _tokenSub?.cancel();
      _remoteSub?.cancel();
      _tapSub?.cancel();
      _client?.stop();
      _transport?.stop();
    });
    // Defer all side-effects: touching providers / starting streams during
    // build risks reading an uninitialised provider (see PresenceController).
    Future.microtask(_start);
    return const NotificationStatus();
  }

  Future<void> _start() async {
    if (_disposed || _started) return;
    _started = true;

    await _service.init();
    final granted = await _service.requestPermission();
    if (_disposed) return;
    state = state.copyWith(permissionGranted: granted);

    // Taps on any notification (local or remote) → deep-link to the ticket.
    _tapSub = _service.onTap.listen(_onTap);

    // In-app realtime events → notifications (the always-on path).
    final client = ref.read(realtimeClientProvider);
    _client = client;
    _eventSub = client.events.listen(_onEvent);

    // Remote transport (no-op unless FCM is enabled + configured).
    await _startTransport();
  }

  Future<void> _startTransport() async {
    final transport = ref.read(pushTransportProvider);
    _transport = transport;
    await transport.start();
    if (_disposed) return;

    _tokenSub = transport.onToken.listen(_registerToken);
    _remoteSub = transport.onRemoteMessage.listen((n) async {
      await _service.show(n);
    });

    // Cold-start deep link (app launched by tapping a push).
    final launchTicket = await transport.initialMessageTicketId();
    if (!_disposed && launchTicket != null) _onTap(launchTicket);
  }

  Future<void> _registerToken(String token) async {
    try {
      await ref.read(fcmTokenRepositoryProvider).register(token);
    } catch (e) {
      // Non-fatal: a missing token just means no remote pushes this session.
      if (kDebugMode) debugPrint('[push] token register failed: $e');
    }
  }

  void _onEvent(RealtimeEvent event) {
    switch (event) {
      case TicketCalledEvent(:final windowName):
        unawaited(_showCall(windowName));
      case TicketSnapshotEvent(:final ticket):
        if (ticket.lifecycle == TicketStatus.called) {
          unawaited(_showCall(ticket.windowName));
        } else {
          _maybeMilestone(ticket);
        }
      case TicketClearedEvent():
        _lastMilestonePosition = null;
    }
  }

  Future<void> _showCall(String? windowName) async {
    final where = windowName ?? 'the counter';
    await _service.show(QueueNotification(
      id: _callId,
      channel: NotificationChannel.call,
      title: "It's your turn",
      body: 'Proceed to $where now.',
    ));
  }

  /// Notifies once when the ticket crosses a milestone position downward
  /// (debounced: never the same or a higher position twice).
  void _maybeMilestone(QueueTicket ticket) {
    final pos = ticket.position;
    if (pos == null) return;
    final last = _lastMilestonePosition;
    if (last != null && pos >= last) {
      _lastMilestonePosition = pos;
      return;
    }
    _lastMilestonePosition = pos;
    if (!_milestones.contains(pos)) return;

    final body = pos == 1
        ? "You're next — stay close and watch for your call."
        : '$pos people ahead of you. Stay nearby.';
    unawaited(_service.show(QueueNotification(
      id: _milestoneId,
      channel: NotificationChannel.milestone,
      title: 'Queue update',
      body: body,
      ticketId: ticket.id,
    )));
  }

  void _onTap(int? ticketId) {
    if (_disposed) return;
    ref.read(notificationDeepLinkProvider.notifier).open(ticketId);
  }
}
