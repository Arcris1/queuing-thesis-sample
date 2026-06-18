import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../theme/app_theme.dart';
import 'catalog_providers.dart';
import 'office.dart';
import 'service_select_screen.dart';
import 'widgets/state_views.dart';

/// Step 1 of the join flow: pick the office to queue at.
///
/// Offices are shown as tappable cards. Distance/geofence is decided
/// server-side (§1), so we never gate selection here — eligibility is enforced
/// when the student is about to be served.
class OfficeSelectScreen extends ConsumerWidget {
  const OfficeSelectScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final officesAsync = ref.watch(officesProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Choose an office')),
      body: SafeArea(
        child: officesAsync.when(
          loading: () => const LoadingView(label: 'Loading offices'),
          error: (err, _) => MessageView(
            icon: Icons.cloud_off_outlined,
            title: 'Could not load offices',
            message: err.toString(),
            onRetry: () => ref.invalidate(officesProvider),
          ),
          data: (offices) {
            if (offices.isEmpty) {
              return MessageView(
                icon: Icons.location_off_outlined,
                title: 'No offices available',
                message: 'Please check back a little later.',
                onRetry: () => ref.invalidate(officesProvider),
              );
            }
            return RefreshIndicator(
              onRefresh: () async => ref.invalidate(officesProvider),
              child: ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(AppSpacing.lg),
                itemCount: offices.length,
                separatorBuilder: (_, _) =>
                    const SizedBox(height: AppSpacing.md),
                itemBuilder: (context, i) => _OfficeCard(office: offices[i]),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _OfficeCard extends StatelessWidget {
  const _OfficeCard({required this.office});

  final Office office;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final radius = office.geofenceRadiusM;

    return Card(
      clipBehavior: Clip.antiAlias,
      child: Semantics(
        button: true,
        label: 'Select ${office.name}',
        child: InkWell(
          onTap: () {
            Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => ServiceSelectScreen(office: office),
              ),
            );
          },
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Row(
              children: [
                Container(
                  height: 48,
                  width: 48,
                  decoration: BoxDecoration(
                    color: scheme.primaryContainer,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(Icons.apartment_rounded,
                      color: scheme.onPrimaryContainer),
                ),
                const SizedBox(width: AppSpacing.lg),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        office.name,
                        style: theme.textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w600),
                      ),
                      if (radius != null) ...[
                        const SizedBox(height: AppSpacing.xs),
                        Text(
                          'Be within ${radius}m to be served',
                          style: theme.textTheme.bodySmall
                              ?.copyWith(color: scheme.onSurfaceVariant),
                        ),
                      ],
                    ],
                  ),
                ),
                Icon(Icons.chevron_right, color: scheme.onSurfaceVariant),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
