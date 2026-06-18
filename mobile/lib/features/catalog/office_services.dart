import 'package:flutter/foundation.dart';

import 'office.dart';
import 'queue_group.dart';

/// Response of `GET /api/offices/{office}/services`: an office plus its
/// services grouped under their queue groups (§5).
@immutable
class OfficeServices {
  const OfficeServices({
    required this.office,
    required this.queueGroups,
  });

  final Office office;
  final List<QueueGroup> queueGroups;

  factory OfficeServices.fromJson(Map<String, dynamic> json) {
    final rawGroups = json['queue_groups'];
    final groups = (rawGroups is List)
        ? rawGroups
            .whereType<Map<String, dynamic>>()
            .map(QueueGroup.fromJson)
            .toList(growable: false)
        : const <QueueGroup>[];
    return OfficeServices(
      office: Office.fromJson(json['office'] as Map<String, dynamic>),
      queueGroups: groups,
    );
  }
}
