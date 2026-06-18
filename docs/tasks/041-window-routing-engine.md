---
id: 041
title: Window routing engine (oldest-eligible assignment service)
status: Done
owner: laravel-backend-engineer
plan_ref: "Phase 2 / §5,§7"
depends_on: [40, 5]
---

## Objective

Implement the core routing engine: when a window becomes available, assign it the oldest **eligible**
waiting ticket across the queue groups that window serves, recording the assignment and triggering the
call broadcast.

## Context

This is research contribution #1 (§5.3) — the system maintains **per-queue-group** waiting lines (not
per-window queues) and routes tickets to capable windows so no window sits idle. A window attaches to
one or more queue groups via `window_queue_groups` (task 040). Eligibility = ticket `Waiting`, presence
**Active**, and (once geofencing lands) **within the office radius**. Ordering is **priority desc, then
FIFO by `joined_at`** within the groups. The engine is consumed by the window endpoints (task 021).
Geofence gating integrates with task 013 and presence gating with task 016 as they land — keep the
eligibility check pluggable so those simply tighten the predicate. No Repository pattern; this is a
Service class with Eloquent + transactions.

## Scope

**In scope**
- `WindowRoutingService::assignNext(Window $window): ?QueueTicket`:
  1. Load the window's attached queue groups (via the pivot).
  2. Select the oldest **eligible** `Waiting` ticket across those groups (priority desc, `joined_at`
     asc), using `lockForUpdate()` inside `DB::transaction()`.
  3. Create an open `window_assignments` row (`assigned_at = now()`, `served_at = null`), set the
     ticket → `Serving` / `called_at`.
  4. Return the ticket (or `null` if no eligible ticket); broadcasting is triggered by the caller
     (task 021) / this service dispatches `TicketCalled` after commit.
- An `Eligibility` predicate seam: presence Active (task 016) + within geofence (task 013) when those
  exist; until then, treat `Waiting` as eligible.
- Enforce **one open assignment per window** (skip if the window already has an unfinished assignment).
- Support the **dynamic-enable** scenario (§5.4): because selection reads the live pivot, attaching an
  extra queue group to an idle window (task 043) immediately widens what it can be assigned — no code
  change.

**Out of scope**
- The HTTP endpoints (task 021); attach/detach endpoints (task 043); the `service_history` write on
  serve (task 022); the broadcast event definition (task 019).

## Implementation notes

Do selection + assignment in a single transaction with row locking to prevent two windows grabbing the
same ticket. Keep the eligibility predicate in one place so tasks 013/016 only extend it. FIFO tie-break
strictly by `joined_at` within equal priority. The "current number per queue group" advances from the
assigned ticket. Document the broadcast contract handoff to task 019 (`TicketCalled` payload:
`{ window_id, window_name, ticket_number, queue_group }`).

## API / contract (if applicable)

- Internal Service contract: `WindowRoutingService::assignNext(Window): ?QueueTicket`.
- Dispatches `TicketCalled` (defined in task 019) after commit.
- No direct HTTP surface (exposed via task 021).

## Acceptance criteria

- [x] Assigns the oldest eligible ticket across the window's queue groups (priority desc, then FIFO)
- [x] Two concurrent available-calls never assign the same ticket (transaction + lock)
- [x] Returns `null` cleanly when no eligible ticket exists
- [x] Enforces at most one open `window_assignments` row per window
- [x] Ineligible (away/offline/out-of-range) tickets are skipped via the eligibility predicate
- [x] Attaching a queue group to an idle window immediately changes what it can be assigned (no code change)
- [x] Unit/feature tests cover multi-group selection, priority, concurrency, and empty-queue

## Verification

```
php artisan test --filter=WindowRoutingEngineTest
# scenario: Win1 & Win2 on Accounting General, tickets A-001..A-004 → each available() assigns next in order
```
