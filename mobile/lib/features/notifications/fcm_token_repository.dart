import '../../data/api_client.dart';

/// Persists the device's FCM token on the user (task 033 / backend task 003).
class FcmTokenRepository {
  FcmTokenRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  /// POST /api/me/fcm-token `{ fcm_token }` (auth:api).
  Future<void> register(String token) =>
      _api.postVoid('/me/fcm-token', data: {'fcm_token': token});
}
