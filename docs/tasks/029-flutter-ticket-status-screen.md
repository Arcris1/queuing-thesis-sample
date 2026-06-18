---
id: 029
title: Flutter ticket/status screen w/ realtime + ETA
status: Todo
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

- [ ] Position/people-ahead/ETA render and update live over WebSocket
- [ ] `ticket.called` switches to a prominent proceed state
- [ ] Polling fallback works when the socket drops
- [ ] Reconnect/out-of-range warning surfaces clearly
- [ ] Smooth, purposeful transitions; AA contrast; loading/empty/error states
- [ ] Widget test covers a simulated position update + called event

## Verification

```
flutter test test/ticket_status_test.dart
flutter run    # with reverb + API running, advance the queue and watch live updates
```
