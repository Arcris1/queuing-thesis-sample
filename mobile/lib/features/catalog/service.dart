import 'package:flutter/foundation.dart';

/// A single service a student can queue for (§5: students queue by *service*).
///
/// A service belongs to a [QueueGroup]; never to a physical window.
@immutable
class Service {
  const Service({
    required this.id,
    required this.name,
    this.avgServiceMinutes,
  });

  final int id;
  final String name;

  /// Average handling time in minutes; used only as light context for the user.
  final int? avgServiceMinutes;

  factory Service.fromJson(Map<String, dynamic> json) {
    return Service(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? '',
      avgServiceMinutes: (json['avg_service_minutes'] as num?)?.toInt(),
    );
  }

  @override
  bool operator ==(Object other) =>
      other is Service &&
      other.id == id &&
      other.name == name &&
      other.avgServiceMinutes == avgServiceMinutes;

  @override
  int get hashCode => Object.hash(id, name, avgServiceMinutes);
}
