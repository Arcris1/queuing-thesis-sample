import 'package:flutter/foundation.dart';

/// AI wait-time prediction for a ticket / queue group (§10, task 024).
///
/// Returned inline on `GET /api/queue/status` (`eta: { ... } | null`) and as the
/// full payload of `GET /api/queue/estimate`. The model is a small server-side
/// regression — the client only renders the number, a confidence cue, and the
/// [basis] so a `fallback` estimate (model not yet trained) is shown honestly
/// rather than dressed up as a confident prediction.
@immutable
class TicketEta {
  const TicketEta({
    required this.estimatedMinutes,
    this.confidence,
    this.basis,
    this.peopleAhead,
    this.activeWindows,
    this.trainedAt,
  });

  /// Predicted minutes until the student is served.
  final int estimatedMinutes;

  /// Model confidence in `[0, 1]`, when the server provides it.
  final double? confidence;

  /// How the estimate was produced, e.g. `model` (trained regression) or
  /// `fallback` (heuristic when there isn't enough history yet).
  final String? basis;

  /// People ahead the estimate was computed against (estimate endpoint).
  final int? peopleAhead;

  /// Active staff windows the estimate assumed (estimate endpoint).
  final int? activeWindows;

  /// When the underlying model was last trained (estimate endpoint).
  final DateTime? trainedAt;

  /// True when this is a heuristic fallback rather than a trained prediction.
  bool get isFallback => basis?.toLowerCase() == 'fallback';

  /// Coarse confidence band for the UI, derived from [confidence]. A fallback
  /// basis is always treated as low confidence regardless of any score.
  EtaConfidence get band {
    if (isFallback) return EtaConfidence.low;
    final c = confidence;
    if (c == null) return EtaConfidence.unknown;
    if (c >= 0.75) return EtaConfidence.high;
    if (c >= 0.4) return EtaConfidence.medium;
    return EtaConfidence.low;
  }

  static TicketEta? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    final minutes = (json['estimated_minutes'] as num?)?.round();
    if (minutes == null) return null;
    return TicketEta(
      estimatedMinutes: minutes,
      confidence: (json['confidence'] as num?)?.toDouble(),
      basis: json['basis'] as String?,
      peopleAhead: (json['people_ahead'] as num?)?.toInt(),
      activeWindows: (json['active_windows'] as num?)?.toInt(),
      trainedAt: switch (json['trained_at']) {
        final String s => DateTime.tryParse(s),
        _ => null,
      },
    );
  }

  @override
  bool operator ==(Object other) =>
      other is TicketEta &&
      other.estimatedMinutes == estimatedMinutes &&
      other.confidence == confidence &&
      other.basis == basis &&
      other.peopleAhead == peopleAhead &&
      other.activeWindows == activeWindows &&
      other.trainedAt == trainedAt;

  @override
  int get hashCode => Object.hash(
        estimatedMinutes,
        confidence,
        basis,
        peopleAhead,
        activeWindows,
        trainedAt,
      );
}

/// Coarse confidence band the UI maps to a label, icon, and tonal colour.
enum EtaConfidence { high, medium, low, unknown }
