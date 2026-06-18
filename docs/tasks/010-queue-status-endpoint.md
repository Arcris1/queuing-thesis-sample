---
id: 010
title: Queue status endpoint (GET /api/queue/status)
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §7"
depends_on: [8]
---

## Objective

Return the authenticated student's current ticket with live position and the number of people ahead,
so the mobile app can render the waiting screen.

## Context

§7 lists `GET /api/queue/status` ("caller's ticket + position within its queue group + ETA"). Position
is computed **within the ticket's queue group** (the shared line, §5), not office-wide. ETA itself
comes from task 024 — this task returns position/people-ahead and leaves an `eta` field that task 024
fills. Read directly via Eloquent; no Repository.

## Scope

**In scope**
- `QueueService::statusFor(User)` returning the active ticket, `position` (within its queue group),
  `people_ahead`, office/queue-group/service summary, and the queue group's `current_number`.
- `QueueTicketResource` (reuse from task 008) extended with `position`/`people_ahead`.
- Handle "no active ticket" → `200 { data: null }` (not an error).

**Out of scope**
- ETA computation (task 024 augments the response); realtime push (task 019).

## Implementation notes

`people_ahead` = count of `Waiting`/`Ready` tickets ahead **in the same queue group** (priority desc,
then `joined_at`). `current_number` is the queue group's latest called/serving number. Eager-load
office/queue-group/service to avoid N+1. Keep the `eta` key present (nullable) so the contract is
stable before task 024 lands.

## API / contract (if applicable)

- `GET /api/queue/status` (auth) → `200 { data: { ticket_number, status, position, people_ahead,
  current_number, eta, office, queue_group, service } | null }`

## Acceptance criteria

- [ ] Returns correct position and people_ahead computed within the ticket's queue group
- [ ] No active ticket returns `data: null` with `200`
- [ ] No N+1 (office/queue-group/service eager-loaded)
- [ ] `eta` key present (nullable) for forward compatibility
- [ ] Feature tests cover with-ticket and without-ticket

## Verification

```
php artisan test --filter=QueueStatusTest
curl localhost:8000/api/queue/status -H 'Authorization: Bearer <t>'
```
