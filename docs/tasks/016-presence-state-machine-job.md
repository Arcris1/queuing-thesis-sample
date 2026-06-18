---
id: 016
title: Presence state machine + scheduled re-evaluation job
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 4 / §9"
depends_on: [15]
---

## Objective

Continuously re-evaluate every active ticket's presence (Active/Away/Offline/Removed) from the latest
heartbeat and apply the resulting state transitions on a schedule.

## Context

§9 thresholds: Active <2 min, Away >2 min, Offline >5 min, Removed >10 min (configurable, §15). The
endpoint (task 015) records heartbeats; this task derives and persists status changes so the queue
and dashboards reflect them. Use Jobs/scheduler per CLAUDE.md.

## Scope

**In scope**
- `App\Enums\PresenceStatus` transition logic centralized in `PresenceService::evaluate(ticket)`.
- A scheduled command/job `EvaluatePresence` registered in the scheduler (every minute) that scans
  active tickets and updates derived status; "Removed" detaches the ticket from the active queue.
- Emit an event/notification hook on transitions (consumed later by tasks 019/020).

**Out of scope**
- The skip/standby + reconnect-grace decision at call-time (task 017); broadcasting (task 019).

## Implementation notes

Read thresholds from `config/queue_system.php`. Batch the scan to avoid loading all tickets at once.
Keep transitions idempotent (re-running the job changes nothing if state is already correct). Removed
should move the ticket out of active counts but never hard-delete it.

## API / contract (if applicable)

N/A — scheduled job; effects observed via status endpoints and dashboards.

## Acceptance criteria

- [ ] Tickets transition Active→Away→Offline→Removed at the configured thresholds
- [ ] Job is registered on the scheduler and is idempotent
- [ ] Removed tickets leave active position counts but persist in the DB
- [ ] Transition emits an event/hook for downstream notify/broadcast
- [ ] Tests use time travel (`travel()`) to assert each threshold

## Verification

```
php artisan test --filter=PresenceStateMachineTest
php artisan schedule:list   # shows EvaluatePresence
php artisan presence:evaluate   # manual run
```
