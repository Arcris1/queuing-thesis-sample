import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../theme/app_theme.dart';
import '../queue/queue_controller.dart';
import '../queue/queue_state.dart';
import '../queue/ticket_confirmation_screen.dart';
import 'catalog_providers.dart';
import 'office.dart';
import 'queue_group.dart';
import 'service.dart';
import 'widgets/state_views.dart';

/// Step 2 of the join flow: pick a *service* (§5 — students queue by service,
/// never by window). Services are grouped under their queue group; the group is
/// shown as context (header + prefix chip), making it visually clear that the
/// tappable choice is the service.
class ServiceSelectScreen extends ConsumerWidget {
  const ServiceSelectScreen({super.key, required this.office});

  final Office office;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final servicesAsync = ref.watch(officeServicesProvider(office.id));

    return Scaffold(
      appBar: AppBar(title: Text(office.name)),
      body: SafeArea(
        child: servicesAsync.when(
          loading: () => const LoadingView(label: 'Loading services'),
          error: (err, _) => MessageView(
            icon: Icons.cloud_off_outlined,
            title: 'Could not load services',
            message: err.toString(),
            onRetry: () => ref.invalidate(officeServicesProvider(office.id)),
          ),
          data: (catalog) {
            final groups = catalog.queueGroups
                .where((g) => g.services.isNotEmpty)
                .toList(growable: false);
            if (groups.isEmpty) {
              return MessageView(
                icon: Icons.inbox_outlined,
                title: 'No services available',
                message: 'This office has no open services right now.',
                onRetry: () =>
                    ref.invalidate(officeServicesProvider(office.id)),
              );
            }
            return ListView(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.lg,
                AppSpacing.lg,
                AppSpacing.xxl,
              ),
              children: [
                Padding(
                  padding: const EdgeInsets.only(
                    left: AppSpacing.xs,
                    bottom: AppSpacing.md,
                  ),
                  child: Text(
                    'Choose the service you need',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                  ),
                ),
                for (final group in groups) ...[
                  _QueueGroupSection(office: office, group: group),
                  const SizedBox(height: AppSpacing.xl),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

/// A queue-group header (name + prefix chip) with its services listed beneath.
class _QueueGroupSection extends StatelessWidget {
  const _QueueGroupSection({required this.office, required this.group});

  final Office office;
  final QueueGroup group;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(
            left: AppSpacing.xs,
            bottom: AppSpacing.sm,
          ),
          child: Row(
            children: [
              if (group.prefix.isNotEmpty) ...[
                _PrefixChip(prefix: group.prefix),
                const SizedBox(width: AppSpacing.sm),
              ],
              Expanded(
                child: Text(
                  group.name,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: scheme.onSurface,
                  ),
                ),
              ),
            ],
          ),
        ),
        Card(
          child: Column(
            children: [
              for (var i = 0; i < group.services.length; i++) ...[
                if (i > 0)
                  Divider(
                    height: 1,
                    thickness: 1,
                    indent: AppSpacing.lg,
                    endIndent: AppSpacing.lg,
                    color: scheme.outlineVariant.withValues(alpha: 0.5),
                  ),
                _ServiceTile(
                  office: office,
                  group: group,
                  service: group.services[i],
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _PrefixChip extends StatelessWidget {
  const _PrefixChip({required this.prefix});

  final String prefix;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Semantics(
      label: 'Queue group $prefix',
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: AppSpacing.xs,
        ),
        decoration: BoxDecoration(
          color: scheme.secondaryContainer,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(
          prefix,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: scheme.onSecondaryContainer,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.5,
              ),
        ),
      ),
    );
  }
}

class _ServiceTile extends ConsumerWidget {
  const _ServiceTile({
    required this.office,
    required this.group,
    required this.service,
  });

  final Office office;
  final QueueGroup group;
  final Service service;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final mins = service.avgServiceMinutes;

    return Semantics(
      button: true,
      label: 'Join queue for ${service.name}',
      child: InkWell(
        onTap: () => _confirmAndJoin(context, ref),
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg,
            vertical: AppSpacing.lg,
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      service.name,
                      style: theme.textTheme.bodyLarge
                          ?.copyWith(fontWeight: FontWeight.w600),
                    ),
                    if (mins != null) ...[
                      const SizedBox(height: AppSpacing.xs),
                      Row(
                        children: [
                          Icon(Icons.schedule,
                              size: 14, color: scheme.onSurfaceVariant),
                          const SizedBox(width: AppSpacing.xs),
                          Text(
                            'About $mins min',
                            style: theme.textTheme.bodySmall
                                ?.copyWith(color: scheme.onSurfaceVariant),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
              Icon(Icons.add_circle_outline, color: scheme.primary),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _confirmAndJoin(BuildContext context, WidgetRef ref) async {
    // The sheet performs the join itself (so its spinner tracks the real
    // request) and pops once a terminal state is reached.
    final didJoin = await showModalBottomSheet<bool>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      isDismissible: false,
      enableDrag: false,
      builder: (sheetContext) => _ConfirmJoinSheet(
        office: office,
        group: group,
        service: service,
      ),
    );

    if (didJoin != true || !context.mounted) return;

    final state = ref.read(queueControllerProvider);
    switch (state.status) {
      case JoinStatus.joined:
        Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => TicketConfirmationScreen(ticket: state.ticket!),
          ),
        );
      case JoinStatus.alreadyQueued:
        final existing = state.ticket;
        if (existing != null) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text("You're already in this queue — here's your ticket."),
            ),
          );
          Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => TicketConfirmationScreen(
                ticket: existing,
                alreadyQueued: true,
              ),
            ),
          );
        } else {
          _showError(
            context,
            state.errorMessage ??
                "You're already in a queue for this group.",
          );
        }
      case JoinStatus.error:
        _showError(
          context,
          state.errorMessage ?? 'Could not join. Please try again.',
        );
      case JoinStatus.idle:
      case JoinStatus.submitting:
        break;
    }
  }

  void _showError(BuildContext context, String message) {
    final scheme = Theme.of(context).colorScheme;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: scheme.errorContainer,
        showCloseIcon: true,
      ),
    );
  }
}

/// Confirmation sheet making it explicit which service the student is joining.
/// Mirrors the queue-group-as-context framing of the list. The sheet owns the
/// join request so its button spinner reflects real progress, then pops with
/// `true` once a terminal join state is reached.
class _ConfirmJoinSheet extends ConsumerStatefulWidget {
  const _ConfirmJoinSheet({
    required this.office,
    required this.group,
    required this.service,
  });

  final Office office;
  final QueueGroup group;
  final Service service;

  @override
  ConsumerState<_ConfirmJoinSheet> createState() => _ConfirmJoinSheetState();
}

class _ConfirmJoinSheetState extends ConsumerState<_ConfirmJoinSheet> {
  Office get office => widget.office;
  QueueGroup get group => widget.group;
  Service get service => widget.service;

  Future<void> _join() async {
    await ref.read(queueControllerProvider.notifier).join(service.id);
    if (!mounted) return;
    Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final isSubmitting =
        ref.watch(queueControllerProvider.select((s) => s.isSubmitting));

    return Padding(
      padding: EdgeInsets.fromLTRB(
        AppSpacing.xl,
        0,
        AppSpacing.xl,
        AppSpacing.xl + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Join this queue?',
            style: theme.textTheme.titleLarge
                ?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: AppSpacing.lg),
          Container(
            padding: const EdgeInsets.all(AppSpacing.lg),
            decoration: BoxDecoration(
              color: scheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  service.name,
                  style: theme.textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  '${group.prefix.isNotEmpty ? '${group.prefix} · ' : ''}'
                  '${group.name} • ${office.name}',
                  style: theme.textTheme.bodyMedium
                      ?.copyWith(color: scheme.onSurfaceVariant),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          FilledButton(
            onPressed: isSubmitting ? null : _join,
            child: isSubmitting
                ? const SizedBox(
                    height: 22,
                    width: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.4,
                      color: Colors.white,
                    ),
                  )
                : const Text('Join queue'),
          ),
          const SizedBox(height: AppSpacing.sm),
          TextButton(
            onPressed:
                isSubmitting ? null : () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
        ],
      ),
    );
  }
}
