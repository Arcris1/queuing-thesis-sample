import '../../data/api_client.dart';
import '../../data/token_storage.dart';
import 'auth_user.dart';

/// Talks to the auth endpoints and owns token persistence side-effects.
class AuthRepository {
  AuthRepository({
    required ApiClient apiClient,
    required TokenStorage tokenStorage,
  })  : _api = apiClient,
        _tokenStorage = tokenStorage;

  final ApiClient _api;
  final TokenStorage _tokenStorage;

  /// POST /api/login → persists the JWT and returns the user.
  Future<AuthUser> login({
    required String email,
    required String password,
  }) async {
    final data = await _api.post('/login', data: {
      'email': email,
      'password': password,
    });
    return _persistAndExtractUser(data);
  }

  /// POST /api/register → persists the JWT and returns the user.
  Future<AuthUser> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? studentNo,
  }) async {
    final data = await _api.post('/register', data: {
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      if (studentNo != null && studentNo.isNotEmpty) 'student_no': studentNo,
    });
    return _persistAndExtractUser(data);
  }

  /// GET /api/me → restores the session from a stored token.
  Future<AuthUser> me() async {
    final data = await _api.get('/me');
    return AuthUser.fromJson(data);
  }

  /// POST /api/logout, then clears the token regardless of server outcome.
  Future<void> logout() async {
    try {
      await _api.postVoid('/logout');
    } finally {
      await _tokenStorage.clear();
    }
  }

  Future<String?> readToken() => _tokenStorage.read();

  Future<AuthUser> _persistAndExtractUser(Map<String, dynamic> data) async {
    final token = data['access_token'] as String?;
    if (token == null || token.isEmpty) {
      throw const ApiException('No access token returned by the server.');
    }
    await _tokenStorage.write(token);
    final user = data['user'];
    if (user is Map<String, dynamic>) {
      return AuthUser.fromJson(user);
    }
    // Fallback: fetch the profile if the auth response omitted the user.
    return me();
  }
}
