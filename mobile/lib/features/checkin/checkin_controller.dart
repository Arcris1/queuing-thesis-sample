import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/api_client.dart';
import '../auth/auth_controller.dart';
import '../location/location_service.dart';
import '../presence/heartbeat_controller.dart' show locationServiceProvider;
import '../queue/queue_controller.dart';
import '../queue/queue_ticket.dart';
import 'checkin_payload.dart';
import 'checkin_repository.dart';

/// Check-in endpoint wrapper. Overridable in tests.
final checkinRepositoryProvider = Provider<CheckinRepository>((ref) {
  return CheckinRepository(apiClient: ref.watch(apiClientProvider));
});

/// Where the check-in flow currently is. The scan screen renders one view per
/// phase, so a single switch drives the whole UX (idle → scanning → verifying →
/// success / out-of-range / error).
enum CheckinPhase {
  /// Camera live, waiting for a valid QR.
  scanning,

  /// A valid QR was decoded; getting GPS + posting to the server.
  verifying,

  /// Server accepted the check-in — the ticket is Ready.
  success,

  /// The student is outside the office geofence.
  outOfRange,

  /// Any other failure (invalid/unknown QR, wrong ticket, GPS off, network).
  error,
}

@immutable
class CheckinState {
  const CheckinState({
    this.phase = CheckinPhase.scanning,
    this.ticket,
    this.message,
    this.distanceM,
    this.radiusM,
  });

  final CheckinPhase phase;

  /// The Ready ticket returned on success (handed back to the status screen).
  final QueueTicket? ticket;

  /// User-facing copy for the [CheckinPhase.error] state.
  final String? message;

  /// Out-of-range detail (metres).
  final double? distanceM;
  final double? radiusM;

  bool get isBusy => phase == CheckinPhase.verifying;
  bool get isTerminal =>
      phase == CheckinPhase.success ||
      phase == CheckinPhase.outOfRange ||
      phase == CheckinPhase.error;

  CheckinState copyWith({
    CheckinPhase? phase,
    QueueTicket? ticket,
    String? message,
    double? distanceM,
    double? radiusM,
  }) {
    return CheckinState(
      phase: phase ?? this.phase,
      ticket: ticket ?? this.ticket,
      message: message ?? this.message,
      distanceM: distanceM ?? this.distanceM,
      radiusM: radiusM ?? this.radiusM,
    );
  }
}

/// Owns the QR check-in flow (task 032).
///
/// The scan screen feeds raw QR strings into [onScan]; the controller debounces
/// duplicate/rapid scans, validates the envelope, takes one GPS fix, and posts
/// to `/api/checkin`. Outcomes map to discrete [CheckinPhase]s the screen turns
/// into precise, recoverable messages.
final checkinControllerProvider =
    NotifierProvider<CheckinController, CheckinState>(CheckinController.new);

class CheckinController extends Notifier<CheckinState> {
  CheckinRepository get _repo => ref.read(checkinRepositoryProvider);
  LocationService get _gps => ref.read(locationServiceProvider);

  /// True once a valid scan is being processed — guards against the scanner
  /// firing the same code many times per second while the camera lingers.
  bool _handling = false;

  @override
  CheckinState build() => const CheckinState();

  /// Handles one decoded QR string from the camera. Safe to call rapidly: only
  /// the first valid scan is processed until [reset] is called.
  Future<void> onScan(String? raw) async {
    if (_handling || state.isBusy) return;

    final payload = CheckinPayload.tryParse(raw);
    if (payload == null) {
      // Not a Smart Queue check-in code — keep scanning, don't nag on every
      // stray QR. The screen shows the persistent "point at the office QR" hint.
      return;
    }

    _handling = true;
    state = state.copyWith(phase: CheckinPhase.verifying);
    await _submit(payload);
  }

  Future<void> _submit(CheckinPayload payload) async {
    // 1) One GPS fix — the server decides eligibility from raw coordinates.
    final fixResult = await _gps.currentFix();
    if (!fixResult.isOk) {
      _fail(_locationMessage(fixResult.availability));
      return;
    }

    // 2) Post the check-in; map the distinct outcomes.
    try {
      final ticket = await _repo.checkin(
        ticketNumber: payload.ticketNumber,
        fix: fixResult.fix!,
      );
      // Mirror the Ready ticket into the queue controller so the live status
      // screen reflects it the moment we route back.
      ref.read(queueControllerProvider.notifier).syncTicket(ticket);
      state = CheckinState(phase: CheckinPhase.success, ticket: ticket);
    } on CheckinOutOfRange catch (e) {
      state = CheckinState(
        phase: CheckinPhase.outOfRange,
        distanceM: e.distanceM,
        radiusM: e.radiusM,
      );
    } on ApiException catch (e) {
      _fail(e.message);
    } catch (_) {
      _fail('We could not check you in. Please try again.');
    }
  }

  void _fail(String message) {
    state = CheckinState(phase: CheckinPhase.error, message: message);
  }

  /// Returns to the live camera so the student can scan again. Re-arms the
  /// duplicate-scan guard.
  void reset() {
    _handling = false;
    state = const CheckinState();
  }

  String _locationMessage(LocationAvailability availability) =>
      switch (availability) {
        LocationAvailability.servicesDisabled =>
          'Location is turned off. Switch on GPS to check in.',
        LocationAvailability.denied =>
          'We need location access to confirm you are at the office.',
        LocationAvailability.deniedForever =>
          'Location is blocked. Enable it in Settings to check in.',
        LocationAvailability.unavailable =>
          "We couldn't get your location. Move to open sky and retry.",
        LocationAvailability.ok =>
          'We could not read your location. Please try again.',
      };
}
