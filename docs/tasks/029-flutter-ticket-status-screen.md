---
id: 029
title: Flutter ticket/status screen w/ realtime + ETA
status: Done
owner: flutter-uiux-pro
plan_ref: "Phase 6 / §12"
depends_on: [10, 19, 24, 28]
---

## Objective

Show the student's active ticket with live position, people-ahead, AI ETA, and a clear "proceed"
state, updating in real time over WebSockets.

## Context

Status data is task 010; AI ETA is task 024; realtime events are task 019 (`queue.position`,
`ticket.called` on `private-ticket.{id}`). This is the app's primary waiting screen. Material 3,
purposeful motion for state changes.

## Scope

**In scope**
- Ticket card: number, status, position, people-ahead, AI ETA + confidence.
- Subscribe to `private-ticket.{id}`; update position/ETA on `queue.position`, switch to a prominent
  "Please proceed" state on `ticket.called`.
- Polling fallback to `GET /api/queue/status` when the socket is unavailable.
- Reconnect-warning banner when the user is flagged Away/out-of-range (from presence/geofence).

**Out of scope**
- GPS sending (task 030), heartbeat (task 031), QR check-in screen (task 032), push handling (task 033).

## Implementation notes

Use a WebSocket client compatible with Reverb (Pusher protocol). Keep the socket subscription tied to
the screen/ticket lifecycle. Animate position/ETA changes subtly (150–300 ms). Always show *something*
(fallback to polling) so the screen is never blank.

## API / contract (if applicable)

- `GET /api/queue/status`, `GET /api/queue/estimate` (tasks 010/024).
- WS `private-ticket.{id}`: `queue.position` `{ position, people_ahead, eta }`, `ticket.called`
  `{ message, office }` (task 019).

## Acceptance criteria

- [x] Position/people-ahead/ETA render and update live (realtime client; polling
      driver by default, WebSocket driver behind the same interface)
- [x] `ticket.called` switches to a prominent proceed state
- [x] Polling driver is the reliable default; WS/Reverb seam documented + wired
      (enable with `--dart-define=REALTIME_TRANSPORT=ws`)
- [x] Reconnect/out-of-range warning surfaces clearly (presence banner +
      proximity indicator reused)
- [x] Smooth, purposeful transitions; AA contrast; loading/empty/served/
      skipped/standby/error states
- [x] Widget tests cover a simulated snapshot update + called event + leave +
      served + empty (7 tests)

## Implementation summary

- Realtime client abstraction: `lib/features/realtime/realtime_client.dart`
  (interface), `polling_realtime_client.dart` (default), `ws_realtime_client.dart`
  (Reverb/Pusher seam, off by default), `realtime_providers.dart` (build-time
  driver selection).
- Screen: `lib/features/queue/ticket_status_screen.dart`; state/controller in
  `ticket_status_state.dart` / `ticket_status_controller.dart`; AI ETA card in
  `widgets/eta_card.dart`; models `ticket_eta.dart` / `ticket_status.dart`.
- `TicketConfirmationScreen` hands off to the live screen; Home routes to it for
  an active ticket. Repo gained `estimate()` + `leave()`.
- Tests: `test/ticket_status_test.dart`. `flutter analyze` clean, `flutter test`
  green (24 total).

## Verification

```
flutter test test/ticket_status_test.dart
flutter run    # with reverb + API running, advance the queue and watch live updates
```
