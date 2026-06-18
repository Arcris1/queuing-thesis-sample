import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../theme/app_theme.dart';
import '../queue/queue_ticket.dart';
import 'checkin_controller.dart';
import 'widgets/scanner_overlay.dart';

/// Full-screen QR check-in scanner (task 032).
///
/// Shows the live camera behind a framed reticle, with torch + camera-flip
/// controls and a persistent instruction. Decoded codes are validated and
/// posted by [CheckinController]; this widget only renders the camera and the
/// per-phase feedback (verifying / success / out-of-range / error).
///
/// On success it pops back to the live ticket status screen with the now-Ready
/// ticket, which the status screen already reflects (the controller mirrors it
/// into the queue controller).
class QrScanScreen extends ConsumerStatefulWidget {
  const QrScanScreen({super.key});

  @override
  ConsumerState<QrScanScreen> createState() => _QrScanScreenState();
}

class _QrScanScreenState extends ConsumerState<QrScanScreen>
    with WidgetsBindingObserver {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    formats: const [BarcodeFormat.qrCode],
  );

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Fresh scan each time the screen is opened.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) ref.read(checkinControllerProvider.notifier).reset();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Release the camera when backgrounded; resume when we return.
    if (!_controller.value.isInitialized) return;
    switch (state) {
      case AppLifecycleState.resumed:
        _controller.start();
      case AppLifecycleState.inactive:
      case AppLifecycleState.paused:
      case AppLifecycleState.detached:
      case AppLifecycleState.hidden:
        _controller.stop();
    }
  }

  void _onDetect(BarcodeCapture capture) {
    if (capture.barcodes.isEmpty) return;
    final raw = capture.barcodes.first.rawValue;
    ref.read(checkinControllerProvider.notifier).onScan(raw);
  }

  void _scanAgain() => ref.read(checkinControllerProvider.notifier).reset();

  void _finishSuccess(QueueTicket ticket) {
    if (!mounted) return;
    Navigator.of(context).pop(ticket);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(checkinControllerProvider);

    return Scaffold(
      backgroundColor: Colors.black,
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text('Check in'),
        leading: IconButton(
          icon: const Icon(Icons.close_rounded),
          tooltip: 'Close scanner',
          onPressed: () => Navigator.of(context).maybePop(),
        ),
        actions: [
          _TorchButton(controller: _controller),
          _FlipCameraButton(controller: _controller),
          const SizedBox(width: AppSpacing.sm),
        ],
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
            errorBuilder: (context, error) =>
                _CameraErrorView(error: error, controller: _controller),
          ),
          // Reticle + instruction overlay (hidden while a result is showing).
          if (state.phase == CheckinPhase.scanning ||
              state.phase == CheckinPhase.verifying)
            ScannerOverlay(verifying: state.phase == CheckinPhase.verifying),
          // Terminal result panel.
          if (state.isTerminal)
            _ResultPanel(
              state: state,
              onScanAgain: _scanAgain,
              onSuccess: _finishSuccess,
            ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Camera controls
// ---------------------------------------------------------------------------

class _TorchButton extends StatelessWidget {
  const _TorchButton({required this.controller});

  final MobileScannerController controller;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<MobileScannerState>(
      valueListenable: controller,
      builder: (context, state, _) {
        if (state.torchState == TorchState.unavailable) {
          return const SizedBox.shrink();
        }
        final on = state.torchState == TorchState.on;
        return IconButton(
          icon: Icon(on ? Icons.flash_on_rounded : Icons.flash_off_rounded),
          tooltip: on ? 'Turn off torch' : 'Turn on torch',
          onPressed: () => controller.toggleTorch(),
        );
      },
    );
  }
}

class _FlipCameraButton extends StatelessWidget {
  const _FlipCameraButton({required this.controller});

  final MobileScannerController controller;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      icon: const Icon(Icons.cameraswitch_rounded),
      tooltip: 'Switch camera',
      onPressed: () => controller.switchCamera(),
    );
  }
}

// ---------------------------------------------------------------------------
// Camera permission denied / unavailable
// ---------------------------------------------------------------------------

class _CameraErrorView extends StatelessWidget {
  const _CameraErrorView({required this.error, required this.controller});

  final MobileScannerException error;
  final MobileScannerController controller;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final denied =
        error.errorCode == MobileScannerErrorCode.permissionDenied;
    final unsupported =
        error.errorCode == MobileScannerErrorCode.unsupported;

    final (icon, title, body) = denied
        ? (
            Icons.no_photography_outlined,
            'Camera access needed',
            'To check in, allow camera access so we can scan the office QR '
                'code. Enable it in Settings, then come back and retry.',
          )
        : unsupported
            ? (
                Icons.videocam_off_outlined,
                'No camera available',
                "We couldn't find a usable camera on this device. You can ask "
                    'staff to check you in manually.',
              )
            : (
                Icons.error_outline_rounded,
                "Camera couldn't start",
                'Something interrupted the camera. Please try again.',
              );

    return ColoredBox(
      color: Colors.black,
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, color: Colors.white70, size: 64),
              const SizedBox(height: AppSpacing.xl),
              Text(
                title,
                textAlign: TextAlign.center,
                style: theme.textTheme.headlineSmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                body,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyLarge
                    ?.copyWith(color: Colors.white70),
              ),
              const SizedBox(height: AppSpacing.xxl),
              FilledButton.icon(
                onPressed: () => controller.start(),
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Result panel (verifying outcome)
// ---------------------------------------------------------------------------

class _ResultPanel extends StatelessWidget {
  const _ResultPanel({
    required this.state,
    required this.onScanAgain,
    required this.onSuccess,
  });

  final CheckinState state;
  final VoidCallback onScanAgain;
  final void Function(QueueTicket ticket) onSuccess;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.black.withValues(alpha: 0.78),
      alignment: Alignment.center,
      padding: const EdgeInsets.all(AppSpacing.xl),
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 220),
        child: switch (state.phase) {
          CheckinPhase.success => _SuccessCard(
              key: const ValueKey('success'),
              ticket: state.ticket!,
              onContinue: () => onSuccess(state.ticket!),
            ),
          CheckinPhase.outOfRange => _OutcomeCard(
              key: const ValueKey('out-of-range'),
              icon: Icons.location_searching_rounded,
              tone: _Tone.warning,
              title: 'Move a little closer',
              body: _outOfRangeBody(state),
              primaryLabel: 'Try again',
              onPrimary: onScanAgain,
            ),
          _ => _OutcomeCard(
              key: const ValueKey('error'),
              icon: Icons.error_outline_rounded,
              tone: _Tone.error,
              title: "Check-in didn't work",
              body: state.message ??
                  'We could not check you in. Please try again.',
              primaryLabel: 'Try again',
              onPrimary: onScanAgain,
            ),
        },
      ),
    );
  }

  String _outOfRangeBody(CheckinState state) {
    final d = state.distanceM;
    final r = state.radiusM;
    if (d != null && r != null) {
      return "You're about ${d.round()} m away — you need to be within "
          '${r.round()} m of the office. Walk closer and scan again.';
    }
    if (d != null) {
      return "You're about ${d.round()} m from the office. Walk closer and "
          'scan again.';
    }
    return "You're outside the office area. Walk closer and scan again.";
  }
}

class _SuccessCard extends StatefulWidget {
  const _SuccessCard({
    super.key,
    required this.ticket,
    required this.onContinue,
  });

  final QueueTicket ticket;
  final VoidCallback onContinue;

  @override
  State<_SuccessCard> createState() => _SuccessCardState();
}

class _SuccessCardState extends State<_SuccessCard>
    with SingleTickerProviderStateMixin {
  late final AnimationController _anim = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 280),
  )..forward();

  @override
  void dispose() {
    _anim.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return _Sheet(
      child: Semantics(
        liveRegion: true,
        label: "You're checked in for ${widget.ticket.ticketNumber}. "
            "You're now Ready.",
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ScaleTransition(
              scale: CurvedAnimation(
                parent: _anim,
                curve: Curves.elasticOut,
              ),
              child: Container(
                height: 88,
                width: 88,
                decoration: BoxDecoration(
                  color: scheme.primary,
                  shape: BoxShape.circle,
                ),
                child: Icon(Icons.check_rounded,
                    color: scheme.onPrimary, size: 52),
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            Text(
              "You're checked in",
              textAlign: TextAlign.center,
              style: theme.textTheme.headlineSmall
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'Ticket ${widget.ticket.ticketNumber} is now Ready. Keep an eye '
              'on your screen — we\'ll call you shortly.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyLarge
                  ?.copyWith(color: scheme.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.xxl),
            FilledButton(
              onPressed: widget.onContinue,
              child: const Text('Back to my ticket'),
            ),
          ],
        ),
      ),
    );
  }
}

enum _Tone { warning, error }

class _OutcomeCard extends StatelessWidget {
  const _OutcomeCard({
    super.key,
    required this.icon,
    required this.tone,
    required this.title,
    required this.body,
    required this.primaryLabel,
    required this.onPrimary,
  });

  final IconData icon;
  final _Tone tone;
  final String title;
  final String body;
  final String primaryLabel;
  final VoidCallback onPrimary;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final color =
        tone == _Tone.error ? scheme.error : scheme.tertiary;

    return _Sheet(
      child: Semantics(
        liveRegion: true,
        label: '$title. $body',
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color, size: 56),
            const SizedBox(height: AppSpacing.lg),
            Text(
              title,
              textAlign: TextAlign.center,
              style: theme.textTheme.headlineSmall
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              body,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyLarge
                  ?.copyWith(color: scheme.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.xxl),
            FilledButton.icon(
              onPressed: onPrimary,
              icon: const Icon(Icons.qr_code_scanner_rounded),
              label: Text(primaryLabel),
            ),
          ],
        ),
      ),
    );
  }
}

/// Rounded surface used by every result card.
class _Sheet extends StatelessWidget {
  const _Sheet({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 420),
      child: Material(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(24),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: child,
        ),
      ),
    );
  }
}
