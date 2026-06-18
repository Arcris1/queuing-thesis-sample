---
id: 008
title: Queue join endpoint (POST /api/queue/join)
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §5,§7,§11"
depends_on: [2, 4, 5]
---

## Objective

Let an authenticated student join a queue by selecting a **service**; the endpoint resolves the
service's **queue group**, atomically issues the next group-prefixed ticket number, and returns the
new ticket with its position within that group.

## Context

The model is **Office → Queue Group → Service** (§5): a student picks a service, but the ticket waits
in the service's queue group (the shared line). §7 lists `POST /api/queue/join` with body `service_id`.
§11 starts the flow at "Select Service → Ticket issued in the service's QUEUE GROUP (prefixed number)".
Ticket numbers use the **queue group prefix** (A/R/RG/T/C, task 004), e.g. `A-001`, `RG-014`. A student
may not hold two active tickets in the same queue group on the same day. Thin controller →
`JoinQueueRequest` → DTO → `QueueService` → `QueueTicketResource`. No Repository pattern.

## Scope

**In scope**
- `JoinQueueRequest` validating `service_id` (must exist and belong to an open queue group).
- `QueueService::join(JoinQueueDTO)` — resolve `service → queue_group → office`, find/create today's
  `Queue` session, increment the **per-queue-group** ticket counter inside `DB::transaction()`, create
  the ticket with `queue_group_id`, `service_id`, status `Waiting`, `priority` 0, `joined_at = now()`.
- Guard against duplicate active tickets (same user + queue group + day) → `409`/`422`.
- `QueueTicketResource` returning ticket_number, status, position-in-group, office/queue-group/service
  summary.

**Out of scope**
- Realtime broadcast on join (task 019); ETA (task 024); routing/assignment (task 041); leave (task 009).

## Implementation notes

Derive office and queue group from the chosen `service_id` — do not trust a client-sent office_id.
Position = count of `Waiting`/`Ready` tickets ahead **in the same queue group** (ordered by priority
desc, then `joined_at`). Compute the next number with a locked read (`lockForUpdate`) on the group's
tickets inside the transaction to avoid race-condition duplicates. Build ticket_number as
`<GROUP_PREFIX>-<zero-padded sequence>`.

## API / contract (if applicable)

- `POST /api/queue/join` (auth) → body `{ service_id }`
  → `201 { data: { id, ticket_number, status, position, office, queue_group, service, joined_at } }`
- Errors: `422` invalid service / duplicate active ticket, `401` unauthenticated.

## Acceptance criteria

- [ ] Joining creates a `Waiting` ticket in the service's queue group with a correct group-prefixed number
- [ ] Position reflects the student's place **within the queue group** (priority-aware)
- [ ] Concurrent joins never produce duplicate ticket numbers (transaction + lock)
- [ ] Duplicate active ticket for same queue group/day is rejected
- [ ] Office/queue group are derived server-side from the service (client office_id not trusted)
- [ ] Logic in `QueueService`; controller is thin; response via Resource
- [ ] Feature tests cover success, duplicate, and concurrency

## Verification

```
php artisan test --filter=QueueJoinTest
curl -X POST localhost:8000/api/queue/join -H 'Authorization: Bearer <t>' \
  -d '{"service_id":1}' -H 'Content-Type: application/json'   # expect ticket_number like A-001
```
