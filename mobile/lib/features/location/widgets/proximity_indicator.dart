import 'package:flutter/material.dart';

import '../../../theme/app_theme.dart';
import '../geofence_result.dart';

/// Compact in/out-of-range banner for the ticket screen (task 029).
///
/// Renders the server's geofence verdict (§8) with AA-contrast colours and a
/// shape/icon difference — never colour alone — plus a [Semantics] label so the
/// state is announced to assistive tech. Animates between states.
class ProximityIndicator extends StatelessWidget {
  const ProximityIndicator({super.key, required this.result});

  /// Latest geofence verdict, or null before the first fix is reported.
  final GeofenceResult? result;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final result = this.result;

    final (bg, fg, icon, headline, detail, semantic) = switch (result) {
      null => (
        scheme.surfaceContainerHighest,
        scheme.onSurfaceVariant,
        Icons.location_searching_rounded,
        'Locating you…',
        'Checking your distance to the office.',
        'Locating you. Checking your distance to the office.',
      ),
      final r when r.withinRange => (
        // Tonal success surface derived from the scheme for AA contrast in
        // both light and dark; never rely on the green alone — icon + copy
        // carry the same meaning.
        scheme.tertiaryContainer,
        scheme.onTertiaryContainer,
        Icons.check_circle_rounded,
        'You are within range',
        'Keep this screen open so we can call you in.',
        'You are within range of the office.',
      ),
      final r => (
        scheme.errorContainer,
        scheme.onErrorContainer,
        Icons.directions_walk_rounded,
        'Please move closer',
        _outOfRangeDetail(r),
        'You are out of range. ${_outOfRangeDetail(r)}',
      ),
    };

    return Semantics(
      liveRegion: true,
      label: semantic,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          children: [
            Icon(icon, color: fg, size: 28),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    headline,
                    style: theme.textTheme.titleSmall?.copyWith(
                      color: fg,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    detail,
                    style: theme.textTheme.bodySmall?.copyWith(color: fg),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  static String _outOfRangeDetail(GeofenceResult r) {
    final distance = r.distanceM;
    if (distance == null) {
      return 'Walk toward the office to become eligible.';
    }
    final rounded = distance >= 100 ? distance.round() : distance.ceil();
    return "You're about ${rounded}m away — walk a little closer.";
  }
}
