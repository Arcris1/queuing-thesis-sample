---
id: 032
title: Flutter QR scan check-in
status: Todo
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

- `POST /api/checkin` `{ qr_token, latitude, longitude }` →
  `{ ticket_number, status, within_radius, distance_m }` (task 014).

## Acceptance criteria

- [ ] Scanning a valid office QR within range checks the student in
- [ ] Wrong-office, out-of-range, and invalid-token errors are shown distinctly
- [ ] Camera permission handled (request + denial path)
- [ ] Success state reflected on the ticket screen
- [ ] Accessible scanner UI with clear feedback
- [ ] Widget/integration test covers a mocked successful scan

## Verification

```
flutter run    # scan a test QR; verify success and each failure message
```
