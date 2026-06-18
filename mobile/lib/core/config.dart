/// App-wide configuration constants.
///
/// Centralizes the single source of truth for the backend base URL so screens
/// and the API client never hard-code endpoints.
class AppConfig {
  AppConfig._();

  /// Base URL for the Laravel API (includes the `/api` prefix).
  ///
  /// Defaults to this machine's LAN IP, which is reachable from the Android
  /// emulator, the iOS simulator, and physical devices on the same network
  /// (the dockerized backend is published on the host's 0.0.0.0:8000).
  ///
  /// Override per environment at build time, e.g.:
  ///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api   # Android emu alias
  ///   flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000/api  # iOS sim / web
  ///   flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8000/api # other LAN IP
  ///
  /// If your machine's LAN IP changes, update the default below or pass
  /// --dart-define.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://192.168.11.144:8000/api',
  );

  /// Wall-clock timeout for connecting and receiving.
  static const Duration apiTimeout = Duration(seconds: 20);

  /// Cadence of the presence heartbeat while a ticket is active (§9: ~30 s).
  ///
  /// Override at build time:
  ///   flutter run --dart-define=HEARTBEAT_SECONDS=30
  static const Duration heartbeatInterval = Duration(
    seconds: int.fromEnvironment('HEARTBEAT_SECONDS', defaultValue: 30),
  );

  /// How often the polling realtime driver refetches `/queue/status` while a
  /// ticket is active (task 029). Short enough to feel live for the demo,
  /// long enough to stay battery-friendly.
  ///
  ///   flutter run --dart-define=QUEUE_POLL_SECONDS=5
  static const Duration queuePollInterval = Duration(
    seconds: int.fromEnvironment('QUEUE_POLL_SECONDS', defaultValue: 5),
  );

  // --- Realtime WebSocket (Reverb / Pusher protocol) seam --------------------
  //
  // The default realtime transport is polling (above). To switch on the true
  // WebSocket driver, set `--dart-define=REALTIME_TRANSPORT=ws` and supply the
  // Reverb values from the backend `.env` (REVERB_APP_KEY / REVERB_HOST /
  // REVERB_PORT / REVERB_SCHEME). See `ws_realtime_client.dart` for the wiring.

  /// `polling` (default) or `ws`. Selects the realtime driver at build time.
  static const String realtimeTransport = String.fromEnvironment(
    'REALTIME_TRANSPORT',
    defaultValue: 'polling',
  );

  static const bool useWebSocketRealtime = realtimeTransport == 'ws';

  /// Reverb app key (backend `.env` `REVERB_APP_KEY`).
  static const String reverbKey =
      String.fromEnvironment('REVERB_APP_KEY', defaultValue: '');

  /// Reverb host — defaults to this machine's LAN IP (same host as the API).
  static const String reverbHost =
      String.fromEnvironment('REVERB_HOST', defaultValue: '192.168.11.144');

  static const int reverbPort =
      int.fromEnvironment('REVERB_PORT', defaultValue: 8080);

  /// `ws` (default, local) or `wss` (TLS).
  static const String reverbScheme =
      String.fromEnvironment('REVERB_SCHEME', defaultValue: 'ws');

  // --- Push notifications (task 033) -----------------------------------------
  //
  // The notification *display* path (flutter_local_notifications) and the
  // realtime-driven triggers always work — no Firebase needed. [pushTransport]
  // only selects how *remote* pushes are delivered:
  //
  //   - `local` (default) — no remote transport. Notifications are driven by the
  //     in-app realtime client (polling / WebSocket). This is the demo default
  //     and runs without any Firebase project, google-services.json, or
  //     GoogleService-Info.plist.
  //   - `fcm`             — enable Firebase Cloud Messaging. Requires a
  //     provisioned Firebase project + platform config files (see
  //     `lib/features/notifications/README_FCM.md`). When enabled, the device
  //     token is POSTed to `/api/me/fcm-token` and foreground/background/
  //     terminated messages route into the same notification display path.
  //
  // Mirrors the backend's pluggable sender (LogPushSender default, FCM stub):
  // the app demos end-to-end without provisioning Firebase, and a single
  // `--dart-define=PUSH_TRANSPORT=fcm` flips on the real transport.
  static const String pushTransport = String.fromEnvironment(
    'PUSH_TRANSPORT',
    defaultValue: 'local',
  );

  /// True when remote FCM delivery is enabled (see [pushTransport]).
  static const bool useFirebaseMessaging = pushTransport == 'fcm';
}
