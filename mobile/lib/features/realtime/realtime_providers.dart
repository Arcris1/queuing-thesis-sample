import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/config.dart';
import '../auth/auth_controller.dart';
import '../queue/queue_controller.dart';
import 'polling_realtime_client.dart';
import 'realtime_client.dart';
import 'ws_realtime_client.dart';

/// The live realtime transport, selected at build time by
/// [AppConfig.realtimeTransport]. Defaults to the dependency-free polling
/// driver; `--dart-define=REALTIME_TRANSPORT=ws` swaps in the Reverb WebSocket
/// driver behind the same [RealtimeClient] interface — no screen changes.
///
/// Overridable in tests with a fake client.
final realtimeClientProvider = Provider<RealtimeClient>((ref) {
  final repo = ref.watch(queueRepositoryProvider);
  final client = AppConfig.useWebSocketRealtime
      ? WsRealtimeClient(
          repository: repo,
          apiClient: ref.watch(apiClientProvider),
        )
      : PollingRealtimeClient(
          repository: repo,
          interval: AppConfig.queuePollInterval,
        );
  ref.onDispose(client.dispose);
  return client;
});
