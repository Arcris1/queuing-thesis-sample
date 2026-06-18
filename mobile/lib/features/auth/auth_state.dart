import 'package:flutter/foundation.dart';

import 'auth_user.dart';

/// Discriminated auth status used by the router gate and screens.
enum AuthStatus { unknown, unauthenticated, authenticated }

/// Immutable snapshot of the auth feature.
///
/// [status] drives routing; [errorMessage] is a transient, user-facing string
/// surfaced on the login/register forms; [isSubmitting] disables the form while
/// a request is in flight.
@immutable
class AuthState {
  const AuthState({
    this.status = AuthStatus.unknown,
    this.user,
    this.isSubmitting = false,
    this.errorMessage,
  });

  final AuthStatus status;
  final AuthUser? user;
  final bool isSubmitting;
  final String? errorMessage;

  AuthState copyWith({
    AuthStatus? status,
    AuthUser? user,
    bool? isSubmitting,
    String? errorMessage,
    bool clearError = false,
    bool clearUser = false,
  }) {
    return AuthState(
      status: status ?? this.status,
      user: clearUser ? null : (user ?? this.user),
      isSubmitting: isSubmitting ?? this.isSubmitting,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }
}
