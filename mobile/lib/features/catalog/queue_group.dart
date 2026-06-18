import 'package:flutter/foundation.dart';

import 'service.dart';

/// A shared queue line that groups related [Service]s (§5: Office → Queue Group
/// → Service). Students queue by service; the queue group is the line those
/// services share, identified to the user by its [prefix] (e.g. "REG").
@immutable
class QueueGroup {
  const QueueGroup({
    required this.id,
    required this.name,
    required this.prefix,
    this.services = const [],
  });

  final int id;
  final String name;
  final String prefix;
  final List<Service> services;

  factory QueueGroup.fromJson(Map<String, dynamic> json) {
    final rawServices = json['services'];
    final services = (rawServices is List)
        ? rawServices
            .whereType<Map<String, dynamic>>()
            .map(Service.fromJson)
            .toList(growable: false)
        : const <Service>[];
    return QueueGroup(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? '',
      prefix: json['prefix'] as String? ?? '',
      services: services,
    );
  }

  @override
  bool operator ==(Object other) =>
      other is QueueGroup &&
      other.id == id &&
      other.name == name &&
      other.prefix == prefix &&
      listEquals(other.services, services);

  @override
  int get hashCode => Object.hash(id, name, prefix, Object.hashAll(services));
}
