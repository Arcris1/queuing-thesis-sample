import 'package:flutter/material.dart';

import '../../../theme/app_theme.dart';
import '../ticket_eta.dart';

/// AI wait-time card (§10). Shows the estimated minutes prominently with a
/// confidence cue and a subtle "AI estimate" label, and degrades gracefully when
/// the estimate is a heuristic fallback (basis=fallback) — it says so plainly
/// rather than implying a confident prediction.
///
/// Meaning is never carried by colour alone: the confidence band has its own
/// icon + text label alongside the tonal colour, for AA and colour-blind users.
class EtaCard extends StatelessWidget {
  const EtaCard({super.key, required this.eta});

  /// Null while the estimate hasn't loaded — the card shows a calm placeholder.
  final TicketEta? eta;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final eta = this.eta;

    return Semantics(
      container: true,
      label: _semanticLabel(),
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 250),
        switchInCurve: Curves.easeOut,
        child: Card(
          key: ValueKey(eta == null ? 'eta-loading' : 'eta-${eta.estimatedMinutes}-${eta.band}'),
          color: scheme.secondaryContainer,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Row(
              children: [
                Container(
                  height: 48,
                  width: 48,
                  decoration: BoxDecoration(
                    color: scheme.onSecondaryContainer.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(
                    Icons.auto_awesome_rounded,
                    color: scheme.onSecondaryContainer,
                    size: 24,
                  ),
                ),
                const SizedBox(width: AppSpacing.lg),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text(
                            'Estimated wait',
                            style: theme.textTheme.labelMedium?.copyWith(
                              color: scheme.onSecondaryContainer
                                  .withValues(alpha: 0.85),
                            ),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          _AiBadge(scheme: scheme),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.xs),
                      if (eta == null)
                        Text(
                          'Calculating…',
                          style: theme.textTheme.titleMedium?.copyWith(
                            color: scheme.onSecondaryContainer,
                            fontWeight: FontWeight.w700,
                          ),
                        )
                      else ...[
                        Text(
                          _minutesText(eta.estimatedMinutes),
                          style: theme.textTheme.headlineSmall?.copyWith(
                            color: scheme.onSecondaryContainer,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        _ConfidenceRow(eta: eta),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _minutesText(int minutes) {
    if (minutes <= 0) return 'Any moment now';
    if (minutes == 1) return '~1 minute';
    return '~$minutes minutes';
  }

  String _semanticLabel() {
    final eta = this.eta;
    if (eta == null) return 'AI estimated wait, calculating.';
    final base = 'AI estimated wait, ${_minutesText(eta.estimatedMinutes)}.';
    return '$base ${_confidenceLabel(eta)} confidence.';
  }

  static String _confidenceLabel(TicketEta eta) => switch (eta.band) {
        EtaConfidence.high => 'High',
        EtaConfidence.medium => 'Moderate',
        EtaConfidence.low => 'Low',
        EtaConfidence.unknown => 'Estimated',
      };
}

class _AiBadge extends StatelessWidget {
  const _AiBadge({required this.scheme});

  final ColorScheme scheme;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.sm,
        vertical: 2,
      ),
      decoration: BoxDecoration(
        color: scheme.onSecondaryContainer.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        'AI estimate',
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: scheme.onSecondaryContainer,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.3,
            ),
      ),
    );
  }
}

class _ConfidenceRow extends StatelessWidget {
  const _ConfidenceRow({required this.eta});

  final TicketEta eta;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    final (icon, label) = switch (eta.band) {
      EtaConfidence.high => (Icons.signal_cellular_alt_rounded, 'High confidence'),
      EtaConfidence.medium => (
          Icons.signal_cellular_alt_2_bar_rounded,
          'Moderate confidence',
        ),
      EtaConfidence.low => (
          Icons.signal_cellular_alt_1_bar_rounded,
          eta.isFallback ? 'Rough estimate' : 'Low confidence',
        ),
      EtaConfidence.unknown => (Icons.insights_rounded, 'Estimated'),
    };

    final fg = scheme.onSecondaryContainer.withValues(alpha: 0.9);

    return Row(
      children: [
        Icon(icon, size: 16, color: fg),
        const SizedBox(width: AppSpacing.xs),
        Flexible(
          child: Text(
            eta.isFallback
                ? '$label — improves as more people are served'
                : label,
            style: theme.textTheme.bodySmall?.copyWith(color: fg),
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}
