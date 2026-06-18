---
id: 021
title: Window/staff endpoints (available/serve/skip/recall)
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 2 / §7"
depends_on: [41, 19, 5]
---

## Objective

Give staff the window-level actions to operate a counter: mark the window available (which delegates
to the routing engine to assign the oldest eligible ticket), mark the current ticket served, skip it,
and recall (re-announce) it — each broadcasting updates and feeding analytics/history.

## Context

The system is **window/routing driven** (§5.3), not office-FIFO. §7 lists the endpoints under
**Window / staff**: `POST /api/window/{window}/available`, `/serve`, `/skip`, `/recall`. "Available"
does not pick a ticket itself — it calls the **routing engine** (task 041), which scans the window's
queue groups and returns the oldest *eligible* (Active + in-range) ticket, creating a
`window_assignments` row. Serving feeds `service_history` (task 022). Staff-only authorization via the
`Role` enum and an office/window policy. Thin controllers → Form Requests → Services → Resources.

## Scope

**In scope**
- `WindowAvailableRequest` / `ServeRequest` / `SkipRequest` / `RecallRequest` (window scoping, staff
  authorization).
- `available(window, staff)` — delegate to `WindowRoutingService::assignNext(window)` (task 041);
  on a hit, broadcast `TicketCalled` + `QueueUpdated`; on empty, return `data: null`.
- `serve(window)` — close the current `window_assignments` row (`served_at`), set ticket `Served`,
  trigger the `service_history` write (task 022).
- `skip(window)` — set the current ticket `Skipped`/`Standby` (per task 017 grace rules) and
  immediately attempt the next assignment.
- `recall(window)` — re-broadcast the current assignment's `TicketCalled` without changing state.
- A `Policy`/gate restricting these to `Staff`/`Admin` mapped to that window's office.

**Out of scope**
- The routing/eligibility algorithm itself (task 041); writing the `service_history` row body
  (task 022 hooks into serve); dynamic group attach/detach (task 043); the Vue UI (task 037).

## Implementation notes

Wrap state changes in `DB::transaction()`. `available` must not bypass the routing engine — all
selection logic (oldest eligible, priority, geofence/presence gating) lives in task 041. Emit
broadcasts (task 019) after commit. Record `assigned_at`/`served_at` on `window_assignments` for
duration analytics. The "current number per queue group" advances naturally from the assigned ticket.

## API / contract (if applicable)

- `POST /api/window/{window}/available` (auth, staff) → `200 { data: assignedTicket | null }`
- `POST /api/window/{window}/serve` (auth, staff) → `200 { data: ticket }`
- `POST /api/window/{window}/skip` (auth, staff) → `200 { data: nextAssignedTicket | null }`
- `POST /api/window/{window}/recall` (auth, staff) → `200 { data: ticket }`
- Broadcast: `TicketCalled` (per task 019) with `{ window, ticket_number, queue_group }`.
- Errors: `403` non-staff / wrong office, `404` no current assignment (serve/skip/recall).

## Acceptance criteria

- [ ] `available` assigns the oldest eligible ticket via the routing engine (task 041), not inline logic
- [ ] Ineligible tickets are skipped/graced per task 017, never assigned
- [ ] `serve`/`skip` set correct terminal state + close the `window_assignments` row with timestamps
- [ ] `recall` re-announces without mutating state
- [ ] Only staff/admin of the window's office may call these (policy enforced)
- [ ] Actions broadcast the relevant events after commit
- [ ] Feature tests cover available/serve/skip/recall + authorization + empty-queue

## Verification

```
php artisan test --filter=WindowControlTest
curl -X POST localhost:8000/api/window/1/available -H 'Authorization: Bearer <staff>'
curl -X POST localhost:8000/api/window/1/serve -H 'Authorization: Bearer <staff>'
```
