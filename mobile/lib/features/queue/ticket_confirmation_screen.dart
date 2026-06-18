import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../theme/app_theme.dart';
import 'queue_ticket.dart';
import 'ticket_status_screen.dart';

/// Brief success confirmation shown right after a join (or when routing an
/// already-queued student to their existing ticket). It is a hand-off step into
/// the live [TicketStatusScreen] (task 029): it surfaces the issued ticket
/// number, then the student taps through to the live waiting screen.
///
/// Kept deliberately thin — all the live position / ETA / presence / "proceed"
/// behaviour lives on [TicketStatusScreen]. This screen exists so the moment of
/// "you got in" reads as a distinct, celebratory beat before the waiting UI.
class TicketConfirmationScreen extends ConsumerWidget {
  const TicketConfirmationScreen({
    super.key,
    required this.ticket,
    this.alreadyQueued = false,
  });

  final QueueTicket ticket;

  /// When true, this is an existing ticket recovered after a duplicate-join
  /// attempt — the heading reflects that rather than "You're in line".
  final bool alreadyQueued;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        title: const Text('Your ticket'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: AppSpacing.lg),
              Icon(
                alreadyQueued
                    ? Icons.event_available_outlined
                    : Icons.check_circle_rounded,
                color: scheme.primary,
                size: 56,
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(
                alreadyQueued ? "You're already in this line" : "You're in line",
                textAlign: TextAlign.center,
                style: theme.textTheme.titleMedium
                    ?.copyWith(color: scheme.onSurfaceVariant),
              ),
              const SizedBox(height: AppSpacing.xl),
              _TicketCard(ticket: ticket),
              const SizedBox(height: AppSpacing.xxl),
              FilledButton.icon(
                onPressed: () => _openLiveStatus(context),
                icon: const Icon(Icons.timelapse_rounded),
                label: const Text('View live status'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _openLiveStatus(BuildContext context) {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => TicketStatusScreen(initialTicket: ticket),
      ),
    );
  }
}

class _TicketCard extends StatelessWidget {
  const _TicketCard({required this.ticket});

  final QueueTicket ticket;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final group = ticket.queueGroup;

    return Card(
      color: scheme.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.symmetric(
          vertical: AppSpacing.xxl,
          horizontal: AppSpacing.xl,
        ),
        child: Column(
          children: [
            Text(
              'TICKET NUMBER',
              style: theme.textTheme.labelMedium?.copyWith(
                color: scheme.onPrimaryContainer.withValues(alpha: 0.8),
                letterSpacing: 1.5,
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Semantics(
              label: 'Ticket number ${ticket.ticketNumber}',
              child: Text(
                ticket.ticketNumber,
                textAlign: TextAlign.center,
                style: theme.textTheme.displaySmall?.copyWith(
                  color: scheme.onPrimaryContainer,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Wrap(
              alignment: WrapAlignment.center,
              spacing: AppSpacing.sm,
              runSpacing: AppSpacing.xs,
              children: [
                _MetaChip(
                  label: group.prefix == null || group.prefix!.isEmpty
                      ? group.name
                      : '${group.prefix} · ${group.name}',
                  scheme: scheme,
                ),
                _MetaChip(label: ticket.service.name, scheme: scheme),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              ticket.office.name,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: scheme.onPrimaryContainer.withValues(alpha: 0.9),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({required this.label, required this.scheme});

  final String label;
  final ColorScheme scheme;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.xs,
      ),
      decoration: BoxDecoration(
        color: scheme.onPrimaryContainer.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: scheme.onPrimaryContainer,
              fontWeight: FontWeight.w600,
            ),
      ),
    );
  }
}
