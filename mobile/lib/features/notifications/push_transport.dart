import '../../core/config.dart';
import 'notification_service.dart';

/// How *remote* push notifications reach the device.
///
/// Mirrors the backend's pluggable sender (LogPushSender default, FCM stub):
/// the demo works end-to-end with no remote transport at all (the realtime
/// client drives [NotificationService] directly), and a config flag swaps in
/// real FCM. See [AppConfig.pushTransport].
///
/// A [PushTransport] is responsible for obtaining a device token (and reporting
/// it via [onToken]) and for funnelling inbound remote messages into the same
/// [QueueNotification] display path used by in-app events ([onRemoteMessage]).
abstract class PushTransport {
  /// Set up the transport. Never throws — a misconfigured transport degrades to
  /// a no-op so the app still runs (and local notifications still work).
  Future<void> start();

  /// Tear down listeners.
  Future<void> stop();

  /// Emits the device token whenever it is obtained or rotated.
  Stream<String> get onToken;

  /// Emits remote messages already mapped to a displayable notification.
  Stream<QueueNotification> get onRemoteMessage;

  /// The ticket id of a notification that *launched* the app from terminated
  /// state (so the UI can deep-link straight to it), or null.
  Future<int?> initialMessageTicketId();
}

/// Default transport: no remote delivery. Notifications are driven entirely by
/// the in-app realtime client, so the demo needs no Firebase project.
class LocalPushTransport implements PushTransport {
  const LocalPushTransport();

  @override
  Future<void> start() async {}

  @override
  Future<void> stop() async {}

  @override
  Stream<String> get onToken => const Stream<String>.empty();

  @override
  Stream<QueueNotification> get onRemoteMessage =>
      const Stream<QueueNotification>.empty();

  @override
  Future<int?> initialMessageTicketId() async => null;
}
