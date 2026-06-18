import 'package:flutter/foundation.dart';

/// Authenticated user returned by `/api/me`, `/api/login`, `/api/register`.
@immutable
class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    this.role,
    this.studentNo,
  });

  final int id;
  final String name;
  final String email;
  final String? role;
  final String? studentNo;

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: json['role'] as String?,
      studentNo: json['student_no'] as String?,
    );
  }

  @override
  bool operator ==(Object other) =>
      other is AuthUser &&
      other.id == id &&
      other.name == name &&
      other.email == email &&
      other.role == role &&
      other.studentNo == studentNo;

  @override
  int get hashCode => Object.hash(id, name, email, role, studentNo);
}
