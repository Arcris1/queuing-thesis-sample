import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../theme/app_theme.dart';
import '../checkin/qr_scan_screen.dart';
import '../location/widgets/location_permission_card.dart';
import '../location/widgets/proximity_indicator.dart';
import '../presence/heartbeat_controller.dart';
import '../presence/heartbeat_result.dart';
import 'queue_ticket.dart';
import 'ticket_status.dart';
import 'ticket_status_controller.dart';
import 'ticket_status_state.dart';
import 'widgets/eta_card.dart';

/// The student's live waiting screen (task 029). Streams `/queue/status` (and
/// the AI estimate) through the realtime client and renders the right view for
/// the current [StatusPhase] / ticket lifecycle:
///
///   - loading            → branded spinner
///   - empty / left        → CTA back to join
///   - called              → prominent "please proceed to {office} {window}"
///   - served              → celebratory done state
///   - skipped / standby   → explanation + rejoin
///   - waiting / serving   → ticket card, live position + people-ahead, AI ETA,
///                           proximity + presence, leave button
///
/// [initialTicket] is the ticket handed off from the join flow so the screen is
/// never blank for a frame before the first poll lands.
class TicketStatusScreen extends ConsumerStatefulWidget {
  const TicketStatusScreen({super.key, required this.initialTicket});

  final QueueTicket initialTicket;

  @override
  ConsumerState<TicketStatusScreen> createState() => _TicketStatusScreenState();
}

class _TicketStatusScreenState extends ConsumerState<TicketStatusScreen> {
  @override
  void initState() {
    super.initState();
    // Defer to the next frame so the provider container is ready before we
    // mutate state (Riverpod forbids writing during build).
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      ref
          .read(ticketStatusControllerProvider.notifier)
          .start(widget.initialTicket);
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(ticketStatusControllerProvider);

    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        title: const Text('Your ticket'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          tooltip: 'Back',
          onPressed: () => Navigator.of(context).maybePop(),
        ),
      ),
      body: SafeArea(
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 280),
          switchInCurve: Curves.easeOut,
          child: KeyedSubtree(
            key: ValueKey(_viewKey(state)),
            child: _buildBody(context, state),
          ),
        ),
      ),
    );
  }

  String _viewKey(TicketStatusState state) {
    if (state.beingCalled) return 'called';
    return switch (state.phase) {
      StatusPhase.loading => 'loading',
      StatusPhase.empty => 'empty',
      StatusPhase.left => state.ticket?.lifecycle == TicketStatus.served
          ? 'served'
          : 'left',
      StatusPhase.error => 'error',
      StatusPhase.active => 'active-${state.ticket?.lifecycle.name}',
    };
  }

  Widget _buildBody(BuildContext context, TicketStatusState state) {
    if (state.phase == StatusPhase.loading && !state.hasTicket) {
      return const _LoadingView();
    }

    final ticket = state.ticket;

    // Left → either "served/done" (if the last status was being served) or the
    // neutral empty CTA.
    if (state.phase == StatusPhase.left) {
      if (ticket?.lifecycle == TicketStatus.served) {
        return _DoneView(ticket: ticket!);
      }
      return const _EmptyView();
    }
    if (state.phase == StatusPhase.empty || ticket == null) {
      return const _EmptyView();
    }

    if (state.beingCalled || ticket.lifecycle == TicketStatus.called) {
      return _CalledView(ticket: ticket);
    }

    return switch (ticket.lifecycle) {
      TicketStatus.served => _DoneView(ticket: ticket),
      TicketStatus.skipped => _SkippedView(ticket: ticket),
      TicketStatus.standby => _ActiveView(state: state, standby: true),
      _ => _ActiveView(state: state),
    };
  }
}

// ---------------------------------------------------------------------------
// Active waiting view
// ---------------------------------------------------------------------------

class _ActiveView extends ConsumerWidget {
  const _ActiveView({required this.state, this.standby = false});

  final TicketStatusState state;
  final bool standby;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ticket = state.ticket!;
    final peopleAhead = ticket.peopleAhead;
    final presence = ref.watch(presenceControllerProvider);

    return RefreshIndicator(
      onRefresh: () =>
          ref.read(ticketStatusControllerProvider.notifier).refresh(),
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.xl),
        children: [
          if (standby) ...[
            const _StandbyBanner(),
            const SizedBox(height: AppSpacing.lg),
          ],
          if (presence.presence.needsAttention) ...[
            _PresenceBanner(status: presence.presence),
            const SizedBox(height: AppSpacing.lg),
          ],
          if (!presence.locationOk)
            LocationPermissionCard(
              availability: presence.locationAvailability,
              onGrant: () => ref
                  .read(presenceControllerProvider.notifier)
                  .ensurePermissionAndPing(),
            )
          else
            ProximityIndicator(result: presence.geofence),
          const SizedBox(height: AppSpacing.xl),
          _TicketCard(ticket: ticket),
          const SizedBox(height: AppSpacing.xl),
          Row(
            children: [
              Expanded(
                child: _StatTile(
                  label: 'Position',
                  value: ticket.position?.toString() ?? '—',
                  icon: Icons.format_list_numbered_rounded,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: _StatTile(
                  label: peopleAhead == 1 ? 'Person ahead' : 'People ahead',
                  value: peopleAhead?.toString() ?? '—',
                  icon: Icons.groups_2_outlined,
                ),
              ),
            ],
          ),
          if (ticket.currentNumber != null) ...[
            const SizedBox(height: AppSpacing.md),
            _NowServingTile(number: ticket.currentNumber!),
          ],
          const SizedBox(height: AppSpacing.xl),
          EtaCard(eta: ticket.etaPrediction),
          const SizedBox(height: AppSpacing.xl),
          const _CheckinButton(),
          const SizedBox(height: AppSpacing.lg),
          _LeaveButton(isLeaving: state.isLeaving),
          if (state.errorMessage != null) ...[
            const SizedBox(height: AppSpacing.md),
            Text(
              state.errorMessage!,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.error,
                  ),
            ),
          ],
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// "Please proceed" called view
// ---------------------------------------------------------------------------

class _CalledView extends StatelessWidget {
  const _CalledView({required this.ticket});

  final QueueTicket ticket;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final window = ticket.windowName;
    final headline = window == null
        ? "You're being called"
        : "Proceed to $window";
    final detail = ticket.calledMessage ??
        'Head to ${ticket.office.name} now. Show ticket '
            '${ticket.ticketNumber} at the window.';

    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Semantics(
          liveRegion: true,
          label: "It's your turn. $headline. $detail",
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _PulsingGlyph(
                color: scheme.primary,
                onColor: scheme.onPrimary,
                icon: Icons.campaign_rounded,
              ),
              const SizedBox(height: AppSpacing.xl),
              Text(
                "It's your turn",
                textAlign: TextAlign.center,
                style: theme.textTheme.labelLarge?.copyWith(
                  color: scheme.primary,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.2,
                ),
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                headline,
                textAlign: TextAlign.center,
                style: theme.textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              _TicketCard(ticket: ticket, highlight: true),
              const SizedBox(height: AppSpacing.lg),
              Text(
                detail,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyLarge
                    ?.copyWith(color: scheme.onSurfaceVariant),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Done / served view
// ---------------------------------------------------------------------------

class _DoneView extends StatelessWidget {
  const _DoneView({required this.ticket});

  final QueueTicket ticket;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Icon(Icons.verified_rounded, color: scheme.primary, size: 72),
            const SizedBox(height: AppSpacing.xl),
            Text(
              'All done',
              textAlign: TextAlign.center,
              style: theme.textTheme.headlineMedium
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'Your ${ticket.service.name} at ${ticket.office.name} is complete. '
              'Thanks for your patience!',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyLarge
                  ?.copyWith(color: scheme.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.xxl),
            FilledButton(
              onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst),
              child: const Text('Back to home'),
            ),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Skipped view
// ---------------------------------------------------------------------------

class _SkippedView extends StatelessWidget {
  const _SkippedView({required this.ticket});

  final QueueTicket ticket;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Icon(Icons.running_with_errors_rounded,
                color: scheme.error, size: 64),
            const SizedBox(height: AppSpacing.xl),
            Text(
              'You were skipped',
              textAlign: TextAlign.center,
              style: theme.textTheme.headlineSmall
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              "We called ${ticket.ticketNumber} but couldn't reach you in time. "
              'You can rejoin the queue for ${ticket.service.name}.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyLarge
                  ?.copyWith(color: scheme.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.xxl),
            FilledButton.icon(
              onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst),
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Rejoin a queue'),
            ),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Empty / no-ticket view
// ---------------------------------------------------------------------------

class _EmptyView extends StatelessWidget {
  const _EmptyView();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Icon(Icons.confirmation_number_outlined,
                color: scheme.onSurfaceVariant, size: 64),
            const SizedBox(height: AppSpacing.xl),
            Text(
              "You're not in a queue",
              textAlign: TextAlign.center,
              style: theme.textTheme.headlineSmall
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'Join a queue and your live ticket will show up here.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyLarge
                  ?.copyWith(color: scheme.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.xxl),
            FilledButton.icon(
              onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst),
              icon: const Icon(Icons.add_circle_outline),
              label: const Text('Join a queue'),
            ),
          ],
        ),
      ),
    );
  }
}

class _LoadingView extends StatelessWidget {
  const _LoadingView();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: SizedBox(
        height: 28,
        width: 28,
        child: CircularProgressIndicator(strokeWidth: 2.6),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Shared pieces
// ---------------------------------------------------------------------------

/// "Scan QR to check in" CTA. Opens the camera scanner; when it returns a
/// checked-in (Ready) ticket, re-seeds the status controller so the screen
/// reflects the new state immediately, and asks the realtime client to refresh.
class _CheckinButton extends ConsumerWidget {
  const _CheckinButton();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return FilledButton.icon(
      onPressed: () => _openScanner(context, ref),
      icon: const Icon(Icons.qr_code_scanner_rounded),
      label: const Text('Scan QR to check in'),
    );
  }

  Future<void> _openScanner(BuildContext context, WidgetRef ref) async {
    final ticket = await Navigator.of(context).push<QueueTicket>(
      MaterialPageRoute(builder: (_) => const QrScanScreen()),
    );
    if (ticket == null) return;
    final notifier = ref.read(ticketStatusControllerProvider.notifier);
    notifier.start(ticket);
    await notifier.refresh();
  }
}

class _LeaveButton extends ConsumerWidget {
  const _LeaveButton({required this.isLeaving});

  final bool isLeaving;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return OutlinedButton.icon(
      onPressed: isLeaving ? null : () => _confirm(context, ref),
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(52),
        foregroundColor: Theme.of(context).colorScheme.error,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
        ),
      ),
      icon: isLeaving
          ? const SizedBox(
              height: 18,
              width: 18,
              child: CircularProgressIndicator(strokeWidth: 2.2),
            )
          : const Icon(Icons.logout_rounded),
      label: Text(isLeaving ? 'Leaving…' : 'Leave queue'),
    );
  }

  Future<void> _confirm(BuildContext context, WidgetRef ref) async {
    final leave = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Leave the queue?'),
        content: const Text(
          "You'll lose your place in line and will need to rejoin from the "
          'start. This cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Stay'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(ctx).colorScheme.error,
            ),
            child: const Text('Leave'),
          ),
        ],
      ),
    );
    if (leave == true) {
      await ref.read(ticketStatusControllerProvider.notifier).leave();
    }
  }
}

class _NowServingTile extends StatelessWidget {
  const _NowServingTile({required this.number});

  final String number;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.lg,
        vertical: AppSpacing.md,
      ),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Icon(Icons.campaign_outlined, size: 20, color: scheme.primary),
          const SizedBox(width: AppSpacing.md),
          Text(
            'Now serving',
            style: theme.textTheme.bodyMedium
                ?.copyWith(color: scheme.onSurfaceVariant),
          ),
          const Spacer(),
          Text(
            number,
            style: theme.textTheme.titleMedium
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
        ],
      ),
    );
  }
}

class _StandbyBanner extends StatelessWidget {
  const _StandbyBanner();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          color: scheme.tertiaryContainer,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          children: [
            Icon(Icons.pause_circle_outline_rounded,
                color: scheme.onTertiaryContainer, size: 24),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Text(
                "You're on standby — stay close, we'll call you back in shortly.",
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: scheme.onTertiaryContainer),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PulsingGlyph extends StatefulWidget {
  const _PulsingGlyph({
    required this.color,
    required this.onColor,
    required this.icon,
  });

  final Color color;
  final Color onColor;
  final IconData icon;

  @override
  State<_PulsingGlyph> createState() => _PulsingGlyphState();
}

class _PulsingGlyphState extends State<_PulsingGlyph>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ScaleTransition(
        scale: Tween<double>(begin: 0.94, end: 1.06).animate(
          CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
        ),
        child: Container(
          height: 96,
          width: 96,
          decoration: BoxDecoration(
            color: widget.color,
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(
                color: widget.color.withValues(alpha: 0.35),
                blurRadius: 28,
                spreadRadius: 4,
              ),
            ],
          ),
          child: Icon(widget.icon, color: widget.onColor, size: 44),
        ),
      ),
    );
  }
}

/// Prominent ticket card. [highlight] uses the primary surface for the
/// "called" hero; otherwise a calmer container.
class _TicketCard extends StatelessWidget {
  const _TicketCard({required this.ticket, this.highlight = false});

  final QueueTicket ticket;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final group = ticket.queueGroup;
    final bg = highlight ? scheme.primary : scheme.primaryContainer;
    final fg = highlight ? scheme.onPrimary : scheme.onPrimaryContainer;

    return Card(
      color: bg,
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
                color: fg.withValues(alpha: 0.8),
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
                  color: fg,
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
                  fg: fg,
                ),
                _MetaChip(label: ticket.service.name, fg: fg),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              ticket.office.name,
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: fg.withValues(alpha: 0.9)),
            ),
          ],
        ),
      ),
    );
  }
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({required this.label, required this.fg});

  final String label;
  final Color fg;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.xs,
      ),
      decoration: BoxDecoration(
        color: fg.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: fg,
              fontWeight: FontWeight.w600,
            ),
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: scheme.primary, size: 22),
            const SizedBox(height: AppSpacing.sm),
            // Animate value changes (position/people-ahead tick down live).
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 220),
              transitionBuilder: (child, anim) => FadeTransition(
                opacity: anim,
                child: SizeTransition(
                  sizeFactor: anim,
                  axisAlignment: -1,
                  child: child,
                ),
              ),
              child: Text(
                value,
                key: ValueKey(value),
                style: theme.textTheme.headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w700),
              ),
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              label,
              style: theme.textTheme.bodySmall
                  ?.copyWith(color: scheme.onSurfaceVariant),
            ),
          ],
        ),
      ),
    );
  }
}

/// Away / offline reconnect banner (§9). Icon + copy carry the meaning, never
/// colour alone.
class _PresenceBanner extends StatelessWidget {
  const _PresenceBanner({required this.status});

  final PresenceStatus status;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    final message = status == PresenceStatus.offline
        ? 'You appear offline. Reconnect soon or you may lose your spot.'
        : "You're away — we're trying to keep your spot. Tap back in.";

    return Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          color: scheme.errorContainer,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          children: [
            Icon(Icons.wifi_tethering_error_rounded,
                color: scheme.onErrorContainer, size: 24),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Text(
                message,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: scheme.onErrorContainer),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
