import 'package:dio/dio.dart';

import '../core/config.dart';
import 'token_storage.dart';

/// Friendly, typed error surfaced to the UI for any failed API call.
class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

/// Thin wrapper around a configured [Dio] instance.
///
/// Responsibilities:
///   - base URL + JSON headers from [AppConfig]
///   - attach `Authorization: Bearer <token>` from [TokenStorage]
///   - unwrap the Laravel `{ "data": ... }` envelope
///   - map Dio errors (401/422/network) to friendly [ApiException]s
class ApiClient {
  ApiClient({Dio? dio, required TokenStorage tokenStorage})
      : _tokenStorage = tokenStorage,
        _dio = dio ?? Dio() {
    _dio
      ..options.baseUrl = AppConfig.apiBaseUrl
      ..options.connectTimeout = AppConfig.apiTimeout
      ..options.receiveTimeout = AppConfig.apiTimeout
      ..options.headers.addAll({
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      });

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStorage.read();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );
  }

  final Dio _dio;
  final TokenStorage _tokenStorage;

  /// Unwraps the `data` envelope and returns it as a map.
  Future<Map<String, dynamic>> _unwrap(Future<Response<dynamic>> future) async {
    try {
      final response = await future;
      final body = response.data;
      final dynamic data =
          (body is Map<String, dynamic> && body.containsKey('data'))
              ? body['data']
              : body;
      if (data is Map<String, dynamic>) return data;
      return <String, dynamic>{};
    } on DioException catch (e) {
      throw _mapError(e);
    }
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Object? data,
  }) =>
      _unwrap(_dio.post<dynamic>(path, data: data));

  Future<Map<String, dynamic>> get(String path) =>
      _unwrap(_dio.get<dynamic>(path));

  /// GET that expects a `{ "data": [...] }` envelope and returns the list.
  Future<List<dynamic>> getList(String path) async {
    try {
      final response = await _dio.get<dynamic>(path);
      final body = response.data;
      final dynamic data =
          (body is Map<String, dynamic> && body.containsKey('data'))
              ? body['data']
              : body;
      if (data is List) return data;
      return const <dynamic>[];
    } on DioException catch (e) {
      throw _mapError(e);
    }
  }

  /// POST that ignores the response body (e.g. logout).
  Future<void> postVoid(String path, {Object? data}) async {
    try {
      await _dio.post<dynamic>(path, data: data);
    } on DioException catch (e) {
      throw _mapError(e);
    }
  }

  ApiException _mapError(DioException e) {
    final status = e.response?.statusCode;
    final body = e.response?.data;

    // Laravel validation: 422 { message, errors: { field: [..] } }
    if (status == 422 && body is Map<String, dynamic>) {
      final errors = body['errors'];
      if (errors is Map<String, dynamic> && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) {
          return ApiException(first.first.toString(), statusCode: 422);
        }
      }
      final message = body['message'];
      if (message is String && message.isNotEmpty) {
        return ApiException(message, statusCode: 422);
      }
    }

    if (status == 401) {
      return const ApiException(
        'Incorrect email or password.',
        statusCode: 401,
      );
    }

    if (body is Map<String, dynamic> && body['message'] is String) {
      return ApiException(body['message'] as String, statusCode: status);
    }

    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return const ApiException(
          'The connection timed out. Please try again.',
        );
      case DioExceptionType.connectionError:
        return const ApiException(
          'Could not reach the server. Check your connection.',
        );
      default:
        return ApiException(
          'Something went wrong. Please try again.',
          statusCode: status,
        );
    }
  }
}
