import 'package:flutter/material.dart';

import '../../../theme/app_theme.dart';
import '../location_service.dart';

/// Surfaces a degraded location state (denied / disabled) with a clear,
/// recoverable action. Shown on the ticket screen when [availability] is not
/// [LocationAvailability.ok], so the student understands why the queue can't
/// verify they're nearby (§8) and how to fix it.
///
/// [onGrant] re-runs the permission flow; for a permanently-denied state we
/// instead deep-link to the OS app settings.
class LocationPermissionCard extends StatelessWidget {
  const LocationPermissionCard({
    super.key,
    required this.availability,
    required this.onGrant,
  });

  final LocationAvailability availability;
  final Future<void> Function() onGrant;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    final forever = availability == LocationAvailability.deniedForever;
    final disabled = availability == LocationAvailability.servicesDisabled;

    final (icon, title, body, action) = switch (availability) {
      LocationAvailability.servicesDisabled => (
        Icons.location_off_rounded,
        'Turn on location',
        'Location services are off. Turn them on so we can confirm you’re at '
            'the office when it’s your turn.',
        'Open settings',
      ),
      LocationAvailability.deniedForever => (
        Icons.lock_outline_rounded,
        'Location access blocked',
        'You’ve blocked location for this app. Enable it in Settings so the '
            'queue can verify you’re nearby.',
        'Open settings',
      ),
      _ => (
        Icons.my_location_rounded,
        'Allow location',
        'We use your location only while you’re in the queue, to check you’re '
            'within range of the office. We never track you in the background.',
        'Allow location',
      ),
    };

    return Card(
      color: scheme.surfaceContainerHighest,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, color: scheme.primary, size: 24),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: Text(
                    title,
                    style: theme.textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              body,
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: scheme.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.lg),
            Align(
              alignment: Alignment.centerRight,
              child: FilledButton.tonalIcon(
                onPressed: () async {
                  if (forever || disabled) {
                    await GeolocatorLocationService.openSettings();
                  } else {
                    await onGrant();
                  }
                },
                icon: Icon(
                  forever || disabled
                      ? Icons.settings_rounded
                      : Icons.check_rounded,
                  size: 18,
                ),
                label: Text(action),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
