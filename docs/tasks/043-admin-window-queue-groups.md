---
id: 043
title: Admin dynamic queue-group attach/detach endpoints
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 8 / §7"
depends_on: [40, 41]
---

## Objective

Let an admin attach or detach a queue group to/from a window at runtime, so an idle window can be
temporarily widened to serve another group — the key "no idle windows" efficiency mechanism.

## Context

§5.4 (dynamic window enabling): because windows attach to **queue groups** via `window_queue_groups`,
widening an idle window is purely a one-row pivot change — no code, no redesign. The routing engine
(task 041) reads the live pivot, so a newly attached group is served immediately; detaching reverts it.
§7 lists `POST /api/admin/windows/{window}/queue-groups` (attach) and
`DELETE /api/admin/windows/{window}/queue-groups/{group}` (detach). Admin authorization required. Thin
controller → Form Request → Service → Resource. No Repository pattern.

## Scope

**In scope**
- `POST /api/admin/windows/{window}/queue-groups` — body `{ queue_group_id }`: attach (idempotent;
  the group must belong to the window's office).
- `DELETE /api/admin/windows/{window}/queue-groups/{group}` — detach.
- Validation: queue group and window share the same office; cannot detach the window's last group if
  business rules require ≥1 (configurable — default allow).
- `WindowResource` returning the window's updated attached groups; broadcast a board update (task 019)
  so dashboards reflect the change live.
- Admin-only policy/gate.

**Out of scope**
- The pivot schema (task 040); the routing/selection logic (task 041); the Vue control UI (task 037).

## Implementation notes

Attach/detach is a pivot `attach()`/`detach()` inside a transaction; keep it idempotent (re-attaching
an existing group is a no-op, not an error). Validate same-office to prevent a window serving another
office's group. Emit a `QueueUpdated`/board broadcast after commit so the live board (task 025/036)
updates without a refresh. This endpoint changes *capability*, not in-flight assignments.

## API / contract (if applicable)

- `POST /api/admin/windows/{window}/queue-groups` (auth, admin) → body `{ queue_group_id }`
  → `200 { data: { window, queue_groups } }`
- `DELETE /api/admin/windows/{window}/queue-groups/{group}` (auth, admin) → `200 { data: { window,
  queue_groups } }`
- Errors: `403` non-admin, `404` unknown window/group, `422` cross-office attach.

## Acceptance criteria

- [ ] Attaching a queue group to a window makes the routing engine (task 041) feed it that group immediately
- [ ] Detaching reverts capability; in-flight assignments are unaffected
- [ ] Cross-office attach is rejected (422); attach is idempotent
- [ ] Admin-only authorization enforced
- [ ] A live board broadcast fires after attach/detach
- [ ] Feature tests cover attach, detach, idempotency, cross-office rejection, and authorization

## Verification

```
php artisan test --filter=WindowQueueGroupAdminTest
curl -X POST localhost:8000/api/admin/windows/3/queue-groups -H 'Authorization: Bearer <admin>' \
  -d '{"queue_group_id":1}' -H 'Content-Type: application/json'   # Window 3 now also serves Accounting General
```
