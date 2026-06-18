import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../theme/app_theme.dart';
import '../auth/auth_controller.dart';
import '../catalog/office_select_screen.dart';
import '../queue/active_ticket_provider.dart';
import '../queue/ticket_status_screen.dart';

/// Authenticated landing placeholder. Real join/ticket flow lands in tasks
/// 028/029; this confirms the session and offers logout.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final user = ref.watch(authControllerProvider).user;
    final isSubmitting = ref.watch(authControllerProvider).isSubmitting;
    final activeTicket = ref.watch(activeTicketProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Smart Queue'),
        actions: [
          IconButton(
            onPressed: isSubmitting
                ? null
                : () => ref.read(authControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout),
            tooltip: 'Log out',
          ),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.xl),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Hero(
                  tag: 'app-glyph',
                  child: Container(
                    height: 72,
                    width: 72,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [scheme.primary, scheme.tertiary],
                      ),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Icon(Icons.check_circle_outline,
                        color: scheme.onPrimary, size: 36),
                  ),
                ),
                const SizedBox(height: AppSpacing.xl),
                Text(
                  'You are signed in',
                  style: theme.textTheme.titleMedium
                      ?.copyWith(color: scheme.onSurfaceVariant),
                ),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  user?.name ?? 'Student',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.headlineSmall
                      ?.copyWith(fontWeight: FontWeight.w700),
                ),
                if (user?.email != null) ...[
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    user!.email,
                    style: theme.textTheme.bodyMedium
                        ?.copyWith(color: scheme.onSurfaceVariant),
                  ),
                ],
                const SizedBox(height: AppSpacing.xxl),
                if (activeTicket != null) ...[
                  FilledButton.icon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) =>
                            TicketStatusScreen(initialTicket: activeTicket),
                      ),
                    ),
                    icon: const Icon(Icons.timelapse_rounded),
                    label: Text('View ticket ${activeTicket.ticketNumber}'),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  OutlinedButton.icon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => const OfficeSelectScreen(),
                      ),
                    ),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(52),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    icon: const Icon(Icons.add_circle_outline),
                    label: const Text('Join another queue'),
                  ),
                ] else
                  FilledButton.icon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => const OfficeSelectScreen(),
                      ),
                    ),
                    icon: const Icon(Icons.add_circle_outline),
                    label: const Text('Join a queue'),
                  ),
                const SizedBox(height: AppSpacing.md),
                TextButton.icon(
                  onPressed: isSubmitting
                      ? null
                      : () =>
                          ref.read(authControllerProvider.notifier).logout(),
                  icon: const Icon(Icons.logout),
                  label: const Text('Log out'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
