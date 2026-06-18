/// App-wide configuration constants.
///
/// Centralizes the single source of truth for the backend base URL so screens
/// and the API client never hard-code endpoints.
class AppConfig {
  AppConfig._();

  /// Base URL for the Laravel API (includes the `/api` prefix).
  ///
  /// Defaults to the Android emulator loopback alias. The host machine's
  /// `127.0.0.1` is reachable from the Android emulator only via `10.0.2.2`.
  ///
  /// Override per platform/environment at build time:
  ///   flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000/api
  ///
  /// Notes:
  ///   - iOS simulator: use `http://127.0.0.1:8000/api` (localhost works).
  ///   - Flutter web:   use `http://127.0.0.1:8000/api` (and ensure CORS).
  ///   - Physical device: use your machine's LAN IP, e.g.
  ///     `http://192.168.x.x:8000/api`.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
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

  /// Reverb host — defaults to the Android emulator loopback alias.
  static const String reverbHost =
      String.fromEnvironment('REVERB_HOST', defaultValue: '10.0.2.2');

  static const int reverbPort =
      int.fromEnvironment('REVERB_PORT', defaultValue: 8080);

  /// `ws` (default, local) or `wss` (TLS).
  static const String reverbScheme =
      String.fromEnvironment('REVERB_SCHEME', defaultValue: 'ws');
}
