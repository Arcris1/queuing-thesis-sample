import 'dart:convert';

import 'package:flutter/foundation.dart';

/// Decoded contents of an office check-in QR code.
///
/// The office QR encodes a small JSON envelope so the scanner can tell a valid
/// check-in code apart from any other QR a camera might catch:
///
/// ```json
/// { "t": "qms-checkin", "ticket_number": "A-007" }
/// ```
///
/// We validate the envelope tag (`t`) and require a non-empty `ticket_number`
/// before ever hitting the network — the server is still authoritative (§8/§11:
/// it re-validates the ticket, the account, and the location), but a malformed
/// or unrelated QR should never reach it.
@immutable
class CheckinPayload {
  const CheckinPayload({required this.ticketNumber});

  /// The envelope tag every Smart Queue check-in QR carries.
  static const String envelopeTag = 'qms-checkin';

  final String ticketNumber;

  /// Parses [raw] (the scanned string) into a [CheckinPayload], or returns null
  /// when it is not a valid Smart Queue check-in code. Never throws.
  static CheckinPayload? tryParse(String? raw) {
    if (raw == null || raw.trim().isEmpty) return null;
    final Object? decoded;
    try {
      decoded = jsonDecode(raw);
    } catch (_) {
      return null;
    }
    if (decoded is! Map) return null;
    if (decoded['t'] != envelopeTag) return null;
    final ticket = decoded['ticket_number'];
    if (ticket is! String || ticket.trim().isEmpty) return null;
    return CheckinPayload(ticketNumber: ticket.trim());
  }
}
