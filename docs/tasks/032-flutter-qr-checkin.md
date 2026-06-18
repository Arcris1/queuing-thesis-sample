---
id: 032
title: Flutter QR scan check-in
status: Done
owner: flutter-uiux-pro
plan_ref: "Phase 6 / §12"
depends_on: [14, 29]
---

## Objective

Let a student scan the office QR code on arrival to confirm presence, validating ticket, account, and
location server-side.

## Context

§12: scan QR → office validates queue number, account, and location. The check-in endpoint is task
014 (sends `qr_token` + coordinates). Builds on the ticket screen (task 029). Camera permission
required.

## Scope

**In scope**
- QR scanner screen (camera) reachable from the ticket screen.
- On scan, send `POST /api/checkin` with the decoded `qr_token` + current coordinates.
- Success → reflect checked-in/`Ready` state; failures → clear messages (wrong office, out of range,
  invalid token).
- Camera permission request with rationale; handle denial.

**Out of scope**
- Server validation logic (task 014); generating office QR codes.

## Implementation notes

Use a maintained QR scanner plugin. Reuse the location source from task 030 for coordinates. Map the
endpoint's distinct errors to specific, friendly messages. Provide visual scan feedback and a success
animation (150–300 ms).

## API / contract (if applicable)

- Live contract (implemented): `POST /api/checkin` `{ ticket_number, latitude,
  longitude }` → `200 { data: <ticket> }` (Ready) within range; `409 OutOfRange
  { distance_m, radius_m }` when too far. The office QR encodes JSON
  `{"t":"qms-checkin","ticket_number":"A-007"}`.
- Deviation from the original draft: the QR carries the **ticket number** inside
  a tagged envelope (not an opaque `qr_token`), and the body keys the ticket as
  `ticket_number`. The server is still authoritative (re-validates ticket +
  account + location); the client only relays raw GPS.

## Acceptance criteria

- [x] Scanning a valid office QR within range checks the student in
- [x] Wrong-office, out-of-range, and invalid-token errors are shown distinctly
- [x] Camera permission handled (request + denial path)
- [x] Success state reflected on the ticket screen
- [x] Accessible scanner UI with clear feedback
- [x] Widget/integration test covers a mocked successful scan

## Implementation

- `lib/features/checkin/`: `checkin_payload.dart` (QR envelope decode +
  validation), `checkin_repository.dart` (POST /checkin; maps 409 →
  `CheckinOutOfRange`), `checkin_controller.dart` (Riverpod Notifier: debounced
  scan → GPS → post → phase state), `qr_scan_screen.dart` (mobile_scanner camera
  + torch/flip + permission/unavailable handling + per-phase result panel),
  `widgets/scanner_overlay.dart` (framed reticle, scan line, instruction).
- Entry point: "Scan QR to check in" CTA on the active ticket status view; on a
  successful scan it pops back with the Ready ticket and re-seeds the screen.
- `data/api_client.dart`: `ApiException` now carries the raw `body` so the
  repository can read `distance_m`/`radius_m` off a 409.
- Manifests: Android `CAMERA`; iOS `NSCameraUsageDescription`.
- Tests: `test/checkin_test.dart` (payload parsing, success+routing, 409 message,
  non-checkin ignored, denied-location, repository 409 mapping).

## Verification

```
flutter run    # scan a test QR; verify success and each failure message
```
