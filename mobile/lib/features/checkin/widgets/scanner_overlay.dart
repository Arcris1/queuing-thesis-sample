import 'package:flutter/material.dart';

import '../../../theme/app_theme.dart';

/// Camera overlay for the QR scanner: a dimmed scrim with a clear, rounded
/// reticle "window", animated corner accents, a sweeping scan line, and a
/// persistent instruction. While [verifying] is true the reticle calms and a
/// progress chip replaces the instruction.
class ScannerOverlay extends StatefulWidget {
  const ScannerOverlay({super.key, this.verifying = false});

  final bool verifying;

  @override
  State<ScannerOverlay> createState() => _ScannerOverlayState();
}

class _ScannerOverlayState extends State<ScannerOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _scan = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2200),
  )..repeat(reverse: true);

  static const double _reticle = 248;

  @override
  void dispose() {
    _scan.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return LayoutBuilder(
      builder: (context, constraints) {
        final size = constraints.biggest;
        final rect = Rect.fromCenter(
          center: Offset(size.width / 2, size.height / 2 - 24),
          width: _reticle,
          height: _reticle,
        );

        return Stack(
          children: [
            // Dimmed scrim with a transparent cut-out for the reticle.
            Positioned.fill(
              child: CustomPaint(
                painter: _ScrimPainter(
                  hole: RRect.fromRectAndRadius(
                    rect,
                    const Radius.circular(28),
                  ),
                ),
              ),
            ),
            // Reticle border + corner accents.
            Positioned.fromRect(
              rect: rect,
              child: _Reticle(
                color: widget.verifying ? scheme.tertiary : scheme.primary,
              ),
            ),
            // Sweeping scan line (hidden while verifying).
            if (!widget.verifying)
              Positioned.fromRect(
                rect: rect.deflate(14),
                child: AnimatedBuilder(
                  animation: _scan,
                  builder: (context, _) {
                    return Align(
                      alignment: Alignment(0, _scan.value * 2 - 1),
                      child: Container(
                        height: 2.5,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [
                              scheme.primary.withValues(alpha: 0),
                              scheme.primary,
                              scheme.primary.withValues(alpha: 0),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            // Instruction / progress beneath the reticle.
            Positioned(
              left: AppSpacing.xl,
              right: AppSpacing.xl,
              top: rect.bottom + AppSpacing.xl,
              child: Center(
                child: widget.verifying
                    ? const _VerifyingChip()
                    : Semantics(
                        label: 'Point your camera at the office QR code to '
                            'check in.',
                        child: const _InstructionChip(),
                      ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _ScrimPainter extends CustomPainter {
  _ScrimPainter({required this.hole});

  final RRect hole;

  @override
  void paint(Canvas canvas, Size size) {
    final scrim = Paint()..color = Colors.black.withValues(alpha: 0.6);
    final full = Path()..addRect(Offset.zero & size);
    final cut = Path()..addRRect(hole);
    final overlay = Path.combine(PathOperation.difference, full, cut);
    canvas.drawPath(overlay, scrim);
  }

  @override
  bool shouldRepaint(covariant _ScrimPainter old) => old.hole != hole;
}

class _Reticle extends StatelessWidget {
  const _Reticle({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: color.withValues(alpha: 0.9), width: 2),
      ),
      child: CustomPaint(painter: _CornerPainter(color: color)),
    );
  }
}

/// Draws the four bold corner accents on the reticle.
class _CornerPainter extends CustomPainter {
  _CornerPainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = 4
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;
    const len = 26.0;
    const inset = 6.0;
    final w = size.width;
    final h = size.height;

    // Top-left
    canvas.drawLine(const Offset(inset, len), const Offset(inset, inset), paint);
    canvas.drawLine(const Offset(inset, inset), const Offset(len, inset), paint);
    // Top-right
    canvas.drawLine(Offset(w - inset, len), Offset(w - inset, inset), paint);
    canvas.drawLine(Offset(w - inset, inset), Offset(w - len, inset), paint);
    // Bottom-left
    canvas.drawLine(Offset(inset, h - len), Offset(inset, h - inset), paint);
    canvas.drawLine(Offset(inset, h - inset), Offset(len, h - inset), paint);
    // Bottom-right
    canvas.drawLine(Offset(w - inset, h - len), Offset(w - inset, h - inset),
        paint);
    canvas.drawLine(Offset(w - inset, h - inset), Offset(w - len, h - inset),
        paint);
  }

  @override
  bool shouldRepaint(covariant _CornerPainter old) => old.color != color;
}

class _InstructionChip extends StatelessWidget {
  const _InstructionChip();

  @override
  Widget build(BuildContext context) {
    return _Pill(
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: const [
          Icon(Icons.qr_code_scanner_rounded, color: Colors.white, size: 20),
          SizedBox(width: AppSpacing.sm),
          Flexible(
            child: Text(
              'Point at the office QR to check in',
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

class _VerifyingChip extends StatelessWidget {
  const _VerifyingChip();

  @override
  Widget build(BuildContext context) {
    return _Pill(
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: const [
          SizedBox(
            height: 18,
            width: 18,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
            ),
          ),
          SizedBox(width: AppSpacing.md),
          Text(
            'Checking you in…',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.lg,
        vertical: AppSpacing.md,
      ),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(999),
      ),
      child: child,
    );
  }
}
