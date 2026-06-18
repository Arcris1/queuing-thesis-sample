import 'package:battery_plus/battery_plus.dart';

/// Supplies the lightweight device signals a heartbeat reports (§9):
/// `battery_level` and `network_status`.
///
/// Behind an interface so tests inject deterministic values without the
/// `battery_plus` platform channel.
abstract class DeviceTelemetry {
  /// Battery charge 0–100, or null if unavailable.
  Future<int?> batteryLevel();

  /// Coarse connectivity label, e.g. `online`. We avoid a heavyweight
  /// connectivity plugin for the thesis demo; the heartbeat round-trip itself
  /// is the real liveness signal, so a best-effort label suffices.
  Future<String?> networkStatus();
}

/// Production telemetry: real battery via `battery_plus`; a static `online`
/// network label (the successful POST is the authoritative liveness proof).
class PlatformDeviceTelemetry implements DeviceTelemetry {
  PlatformDeviceTelemetry({Battery? battery})
      : _battery = battery ?? Battery();

  final Battery _battery;

  @override
  Future<int?> batteryLevel() async {
    try {
      return await _battery.batteryLevel;
    } catch (_) {
      return null;
    }
  }

  @override
  Future<String?> networkStatus() async => 'online';
}
