import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import 'notification_service.dart';
import 'push_transport.dart';

/// Background isolate handler (required by firebase_messaging to be a top-level
/// function). The OS already shows the system notification for data+notification
/// messages while backgrounded; we only log here. Tapping it is routed by
/// [FcmPushTransport] via `onMessageOpenedApp` / `getInitialMessage`.
@pragma('vm:entry-point')
Future<void> firebaseBackgroundHandler(RemoteMessage message) async {
  // Intentionally minimal — no plugin/UI work in the background isolate.
  if (kDebugMode) {
    debugPrint('[FCM] background message: ${message.messageId}');
  }
}

/// Real FCM transport (task 033), enabled with `--dart-define=PUSH_TRANSPORT=fcm`
/// and a provisioned Firebase project (see `README_FCM.md`).
///
/// Crucially, this is *guarded*: if Firebase isn't configured (no
/// `google-services.json` / `GoogleService-Info.plist`, or no default app),
/// [start] catches the failure and degrades to a no-op so the app keeps running
/// and the local-notification + realtime path remains fully functional.
class FcmPushTransport implements PushTransport {
  FcmPushTransport();

  final _tokenController = StreamController<String>.broadcast();
  final _messageController = StreamController<QueueNotification>.broadcast();
  final List<StreamSubscription<dynamic>> _subs = [];
  bool _active = false;

  @override
  Stream<String> get onToken => _tokenController.stream;

  @override
  Stream<QueueNotification> get onRemoteMessage => _messageController.stream;

  @override
  Future<void> start() async {
    try {
      // No-op if a default app already exists; throws if platform config is
      // missing — which we catch and degrade gracefully.
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp();
      }

      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();
      FirebaseMessaging.onBackgroundMessage(firebaseBackgroundHandler);

      final token = await messaging.getToken();
      if (token != null) _tokenController.add(token);
      _subs.add(messaging.onTokenRefresh.listen(_tokenController.add));

      // Foreground messages → display via the same path as in-app events.
      _subs.add(FirebaseMessaging.onMessage.listen((m) {
        final n = _toNotification(m);
        if (n != null) _messageController.add(n);
      }));

      _active = true;
    } catch (e) {
      // Firebase not configured / unavailable — stay a no-op. Local
      // notifications + realtime still work.
      if (kDebugMode) {
        debugPrint('[FCM] disabled (not configured): $e');
      }
      _active = false;
    }
  }

  @override
  Future<int?> initialMessageTicketId() async {
    if (!_active) return null;
    try {
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (initial == null) return null;
      return _ticketId(initial);
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> stop() async {
    for (final s in _subs) {
      await s.cancel();
    }
    _subs.clear();
    await _tokenController.close();
    await _messageController.close();
  }

  // --- Mapping --------------------------------------------------------------

  /// Maps an FCM payload to a [QueueNotification]. The backend (task 020) sets
  /// a `type` data field ("call" / "milestone" / ...) and a ticket id; we fall
  /// back to the notification block when present.
  QueueNotification? _toNotification(RemoteMessage m) {
    final data = m.data;
    final type = data['type']?.toString();
    final channel = (type == 'call' || type == 'proceed')
        ? NotificationChannel.call
        : NotificationChannel.milestone;

    final title = m.notification?.title ??
        data['title']?.toString() ??
        (channel == NotificationChannel.call
            ? "It's your turn"
            : 'Queue update');
    final body = m.notification?.body ?? data['body']?.toString() ?? '';

    return QueueNotification(
      id: _ticketId(m) ?? m.hashCode,
      channel: channel,
      title: title,
      body: body,
      ticketId: _ticketId(m),
    );
  }

  int? _ticketId(RemoteMessage m) {
    final raw = m.data['ticket_id'] ?? m.data['ticket'];
    if (raw is int) return raw;
    return int.tryParse(raw?.toString() ?? '');
  }
}
