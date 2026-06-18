---
id: 005
title: Queues & QueueTickets schema + TicketStatus enum
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §5,§6"
depends_on: [3, 4]
---

## Objective

Model the daily per-office queue session and the individual tickets students hold, where each ticket
waits in a **queue group** (its line) and references the **service** the student picked — including
the full lifecycle status enum and a `priority` hook for future fast lanes.

## Context

The core model is **Office → Queue Group → Service** (§5). The actual waiting lines are the
`queue_groups` from task 004; a ticket belongs to a queue group (the line it waits in) **and**
references its service (drives `avg_service_minutes` and analytics). §6 defines `queue_tickets`
(id, queue_group_id, service_id, user_id, ticket_number, status, priority, joined_at, called_at,
served_at). §11 lists statuses: waiting, ready, serving, served, skipped, standby. The `queues` table
remains the lightweight per-office daily session container. Enums are native + cast (CLAUDE.md).

## Scope

**In scope**
- Migration `create_queues_table`: `office_id` FK, `date`, `status` (open/closed), unique(`office_id`,`date`).
  (Per-day session container; the actual lines are `queue_groups`.)
- Migration `create_queue_tickets_table`: `queue_id` FK, **`queue_group_id` FK** (the line it waits
  in), `service_id` FK, `user_id` FK, `ticket_number` (string, group-prefixed e.g. `A-001`, `RG-014`),
  `status`, **`priority` (unsigned tinyint, default 0)**, `joined_at`, `called_at`, `served_at`
  (nullable).
- `App\Enums\TicketStatus` backed enum (Waiting, Ready, Serving, Served, Skipped, Standby) with
  `label()` and `color()` helpers.
- `Queue` and `QueueTicket` models with relationships (`QueueTicket belongsTo QueueGroup/Service/User`)
  + scopes (`scopeWaiting`, `scopeForToday`).

**Out of scope**
- Join/leave/status endpoints (tasks 008–011); windows & window_assignments (task 040); routing
  engine (task 041); presence fields (task 006).

## Implementation notes

`ticket_number` uses the **queue group's prefix** (A/R/RG/T/C from task 004) plus a per-group running
sequence — the issuing logic lives in the join service (task 008), not the model. `priority` (default
0) is the hook the routing engine (task 041) will honor for future PWD/Senior/Faculty lanes; higher =
served sooner, FIFO within the same priority. Index (`queue_group_id`,`status`,`priority`,`joined_at`)
for fast oldest-eligible lookups. The "current number per group" (§7) is derived from the latest
called/serving ticket per group — keep that in the service layer, not the model.

## API / contract (if applicable)

N/A — schema/model only.

## Acceptance criteria

- [ ] Both migrations run with FK constraints and the unique(office,date) index
- [ ] `queue_tickets` has both `queue_group_id` and `service_id` FKs, plus `priority` (default 0)
- [ ] `TicketStatus` enum is backed, cast on the model, with `label()`/`color()`
- [ ] Relationships resolve (`$ticket->queueGroup`, `$ticket->service`, `$ticket->user`)
- [ ] Composite index supporting oldest-eligible-per-group lookups exists
- [ ] Factories exist for `Queue` and `QueueTicket`
- [ ] `php artisan migrate:fresh` succeeds

## Verification

```
php artisan migrate:fresh
php artisan tinker --execute="echo implode(',', Schema::getColumnListing('queue_tickets'))"  # includes queue_group_id, priority
php artisan tinker --execute="App\Models\QueueTicket::factory()->create()"
php artisan test --filter=QueueTicketModelTest
```
