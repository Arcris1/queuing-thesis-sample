import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

/// A queue notification ready to display. The realtime/FCM layers build these;
/// the [NotificationService] only renders them.
@immutable
class QueueNotification {
  const QueueNotification({
    required this.id,
    required this.channel,
    required this.title,
    required this.body,
    this.ticketId,
  });

  final int id;
  final NotificationChannel channel;
  final String title;
  final String body;

  /// The ticket this notification is about, used to deep-link a tap back to the
  /// live status screen.
  final int? ticketId;
}

/// Notification importance buckets, mapped to OS channels (Android) and
/// interruption levels (iOS). "It's your turn" is the loudest; milestones are
/// quiet status updates.
enum NotificationChannel { call, milestone }

/// Displays OS-level notifications for queue events (task 033).
///
/// Abstracted so the realtime/FCM layers depend on an interface a test can spy
/// on — and so the display path is identical whether a notification is driven
/// by the in-app realtime client or a remote FCM push.
abstract class NotificationService {
  /// Initialise channels + plugin. Safe to call more than once.
  Future<void> init();

  /// Ask the OS for notification permission (Android 13+/iOS). Returns whether
  /// it was granted; never throws.
  Future<bool> requestPermission();

  /// Show [notification].
  Future<void> show(QueueNotification notification);

  /// Stream of taps, carrying the deep-link ticket id when present.
  Stream<int?> get onTap;
}

/// Production [NotificationService] backed by `flutter_local_notifications`.
///
/// This is the always-on display path: it works in the demo with zero remote
/// infrastructure — the realtime client triggers it directly. When FCM is
/// enabled, remote messages funnel through the very same [show].
class LocalNotificationService implements NotificationService {
  LocalNotificationService({FlutterLocalNotificationsPlugin? plugin})
      : _plugin = plugin ?? FlutterLocalNotificationsPlugin();

  final FlutterLocalNotificationsPlugin _plugin;
  bool _initialised = false;

  // Channels (Android). iOS derives interruption level from the request.
  static const _callChannel = AndroidNotificationChannel(
    'queue_call',
    'Queue calls',
    description: "Alerts you when it's your turn at the window.",
    importance: Importance.max,
  );
  static const _milestoneChannel = AndroidNotificationChannel(
    'queue_milestone',
    'Queue updates',
    description: 'Position and wait-time updates while you wait.',
    importance: Importance.defaultImportance,
  );

  @override
  Future<void> init() async {
    if (_initialised) return;
    _initialised = true;

    const settings = InitializationSettings(
      android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      iOS: DarwinInitializationSettings(
        // We request permission explicitly in [requestPermission].
        requestAlertPermission: false,
        requestBadgePermission: false,
        requestSoundPermission: false,
      ),
    );

    await _plugin.initialize(
      settings: settings,
      onDidReceiveNotificationResponse: _onResponse,
    );

    final android = _plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    await android?.createNotificationChannel(_callChannel);
    await android?.createNotificationChannel(_milestoneChannel);
  }

  @override
  Future<bool> requestPermission() async {
    final android = _plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    if (android != null) {
      return await android.requestNotificationsPermission() ?? false;
    }
    final ios = _plugin.resolvePlatformSpecificImplementation<
        IOSFlutterLocalNotificationsPlugin>();
    if (ios != null) {
      return await ios.requestPermissions(
            alert: true,
            badge: true,
            sound: true,
          ) ??
          false;
    }
    return false;
  }

  @override
  Future<void> show(QueueNotification notification) async {
    if (!_initialised) await init();
    final isCall = notification.channel == NotificationChannel.call;
    final android = AndroidNotificationDetails(
      isCall ? _callChannel.id : _milestoneChannel.id,
      isCall ? _callChannel.name : _milestoneChannel.name,
      channelDescription:
          isCall ? _callChannel.description : _milestoneChannel.description,
      importance: isCall ? Importance.max : Importance.defaultImportance,
      priority: isCall ? Priority.max : Priority.defaultPriority,
      category: isCall ? AndroidNotificationCategory.call : null,
      fullScreenIntent: isCall,
    );
    final ios = DarwinNotificationDetails(
      interruptionLevel: isCall
          ? InterruptionLevel.timeSensitive
          : InterruptionLevel.active,
    );
    await _plugin.show(
      id: notification.id,
      title: notification.title,
      body: notification.body,
      notificationDetails: NotificationDetails(android: android, iOS: ios),
      payload: notification.ticketId?.toString(),
    );
  }

  @override
  Stream<int?> get onTap => _tapController.stream;
  final _tapController = StreamController<int?>.broadcast();

  void _onResponse(NotificationResponse response) {
    final raw = response.payload;
    _tapController.add(raw == null ? null : int.tryParse(raw));
  }
}
