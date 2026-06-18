import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the JWT access token in the platform secure store (Keychain on
/// iOS, EncryptedSharedPreferences on Android). Never use plain prefs for
/// credentials.
class TokenStorage {
  TokenStorage({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const String _tokenKey = 'access_token';

  final FlutterSecureStorage _storage;

  Future<String?> read() => _storage.read(key: _tokenKey);

  Future<void> write(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<void> clear() => _storage.delete(key: _tokenKey);
}
