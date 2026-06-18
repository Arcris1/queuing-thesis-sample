---
id: 009
title: Queue leave endpoint (POST /api/queue/leave)
status: Done
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §7"
depends_on: [8]
---

## Objective

Allow a student to voluntarily leave their active queue, freeing their slot and updating positions
for everyone behind them.

## Context

§7 lists `POST /api/queue/leave`. A student leaving should not be counted in positions/ETAs anymore.
Logic belongs in `QueueService`; the controller stays thin.

## Scope

**In scope**
- `QueueService::leave(User)` — locate the user's active ticket (`Waiting`/`Ready`/`Standby`), mark a
  terminal `left`/`skipped` state (extend `TicketStatus` with `Left` if cleaner) and clear it from
  active position counts.
- Endpoint resolves the current user's active ticket without needing an id in the body.
- Idempotent: leaving with no active ticket returns a clear `404`/`409`.

**Out of scope**
- Broadcasting position changes (task 019); auto-skip via presence (task 017).

## Implementation notes

If you add a `Left` status, update `TicketStatus` and the position query in one change. Wrap the state
change in a transaction. Do not hard-delete tickets (history/analytics need them).

## API / contract (if applicable)

- `POST /api/queue/leave` (auth) → no body → `200 { data: { id, status } }`
- Errors: `404` no active ticket, `401` unauthenticated.

## Acceptance criteria

- [x] Leaving marks the ticket terminal and removes it from active position counts
- [x] No active ticket → clear error, no side effects
- [x] Tickets are never hard-deleted
- [x] Logic in service; thin controller; Resource response
- [x] Feature tests cover leave-success and leave-without-ticket

## Verification

```
php artisan test --filter=QueueLeaveTest
curl -X POST localhost:8000/api/queue/leave -H 'Authorization: Bearer <t>'
```
