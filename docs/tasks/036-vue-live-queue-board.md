---
id: 036
title: Vue live queue board w/ presence states
status: Done
owner: vue-frontend-designer
plan_ref: "Phase 8 / §12"
depends_on: [19, 25, 34]
---

## Objective

Build the real-time staff queue board showing the current number, the waiting list, and each ticket's
presence state (Active/Away/Offline), updating live over WebSocket.

## Context

§12 dashboard: waiting/active/away/offline counts, now-serving, average wait, prediction accuracy. Live
snapshot is task 025 (`/api/admin/queue/{office_id}/live`); realtime updates come from task 019
(`private-queue.office.{id}`, event `queue.updated`). Presence badges use the `PresenceStatus` colors.

## Scope

**In scope**
- Office selector + board view: now-serving number, ordered waiting list, per-ticket presence badge.
- Summary tiles: waiting / active / away / offline / standby counts.
- Subscribe to `private-queue.office.{id}` and apply `queue.updated`; polling fallback to the live
  endpoint.
- Loading/empty/error states; responsive layout.

**Out of scope**
- Call/serve/skip actions (task 037); analytics charts (task 038).

## Implementation notes

Use a Reverb-compatible client (laravel-echo + pusher-js). Tie the subscription to the selected office
and clean up on change/unmount. Drive presence badge colors from a shared map mirroring the backend
enum. Make the table component reusable for task 037.

## API / contract (if applicable)

- `GET /api/admin/queue/{office_id}/live` (task 025).
- WS `private-queue.office.{id}` event `queue.updated` `{ counts, current_number, serving }` (task 019).

## Acceptance criteria

- [ ] Board shows now-serving, waiting list, and presence badges per ticket
- [ ] Summary counts (waiting/active/away/offline/standby) accurate
- [ ] Live updates apply over WebSocket; polling fallback works
- [ ] Office switching re-subscribes correctly (no leaks)
- [ ] Responsive, accessible, with loading/empty/error states
- [ ] Component test covers a simulated `queue.updated` event

## Verification

```
npm run test
npm run dev    # with reverb+API, advance a queue and watch the board update live
```
