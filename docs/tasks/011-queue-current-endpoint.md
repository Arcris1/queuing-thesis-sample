---
id: 011
title: Queue current endpoint (GET /api/queue/current)
status: Done
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §7"
depends_on: [5]
---

## Objective

Expose the public "now serving" number **per queue group** plus basic counts for display boards and
the mobile app's office picker — no authentication required.

## Context

§7 lists `GET /api/queue/current` ("public display: current number per queue group"). Because the
waiting lines are queue groups (§5), the now-serving number is tracked **per group** (e.g. Accounting
General `A-014`, Refund `R-003`), not per office. This drives the join screen (task 028) and physical
displays. Read-only; query Eloquent directly. No Repository.

## Scope

**In scope**
- `GET /api/queue/current?office_id=` (and/or all offices) returning, for each office, its queue
  groups with `current_number` (latest called/serving number in that group) and `waiting_count`.
- `QueueService::current(?int $officeId)` aggregating today's tickets grouped by queue group.
- `QueueGroupCurrentResource` shaping `{ office, queue_group, current_number, waiting_count }`.

**Out of scope**
- Per-user position (task 010); realtime updates (task 019); window-level board (task 025).

## Implementation notes

Make it cache-friendly (short TTL acceptable) since display boards poll it. If no `office_id`, return
all three offices with their groups. `current_number` = the group's most recent `serving`/`called`
ticket number; `waiting_count` counts only `Waiting`/`Ready` in that group.

## API / contract (if applicable)

- `GET /api/queue/current` (public) →
  `200 { data: [ { office, queue_groups: [ { name, prefix, current_number, waiting_count } ] } ] }`
- `GET /api/queue/current?office_id=1` → single office object with its queue groups.

## Acceptance criteria

- [x] Returns correct `current_number` and `waiting_count` **per queue group**
- [x] Works with and without `office_id`
- [x] No authentication required
- [x] Resource-shaped response; no N+1 (queue groups eager-loaded)
- [x] Feature test covers single and all-office responses with multiple groups

## Verification

```
php artisan test --filter=QueueCurrentTest
curl localhost:8000/api/queue/current
curl "localhost:8000/api/queue/current?office_id=1"   # expect queue_groups array with per-group current_number
```
