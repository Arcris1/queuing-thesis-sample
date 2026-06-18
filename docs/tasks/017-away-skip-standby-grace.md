---
id: 017
title: Away/offline skip + standby + reconnect grace on call
status: Done
owner: laravel-backend-engineer
plan_ref: "Phase 4 / §9,§11"
depends_on: [16]
---

## Objective

When a ticket reaches its turn but the student is Away/Offline or outside the geofence, give a short
reconnect grace window, then skip the ticket or move it to standby.

## Context

§9: "When 103's turn arrives... Away → 2 minutes to reconnect... if still unavailable, skipped or
moved to standby." §11 combines this with geofence eligibility (within 15 m → Ready; outside →
warning → grace → skip/standby). Reconnect grace default 2 min (§15). Builds on presence (task 016),
location eligibility (task 013), and staff call (task 021).

## Scope

**In scope**
- `QueueService::evaluateCallEligibility(ticket)` — when a ticket is next/called, check presence AND
  within-radius; if not eligible, set a `grace_until = now()+2min`, send a warning, keep place.
- After grace expiry with still-ineligible student → transition to `Skipped` or `Standby` and advance.
- Standby re-entry rules: a standby student who becomes eligible can be reinserted near the front.

**Out of scope**
- The push delivery mechanism (task 020) — call its notify hook; the staff call action (task 021).

## Implementation notes

Store `grace_until` on the ticket (add a nullable column/migration). Make the decision deterministic
and transactional. Decide skip-vs-standby via config (default standby). Ensure the warning fires
exactly once per grace window.

## API / contract (if applicable)

N/A directly — invoked by the staff call-next flow (task 021) and presence job; effects visible via
status (task 010) and dashboards.

## Acceptance criteria

- [x] Ineligible-at-turn ticket gets a single warning (one-time hook) and a 2-min grace window
- [x] Becoming eligible within grace clears the window and the ticket is assignable again
- [x] Still ineligible after grace → `Standby` (recoverable), queue advances
- [x] Standby student who becomes eligible is reinstated to Waiting (heartbeat or check-in)
- [x] Within-radius (task 013) AND presence (task 016) both required for eligibility
- [x] Tests use time travel to cover grace expiry and recovery

## Resolution notes

- **Ready-vs-routing flag resolved:** `scopeWaitingEligibleOldest` now selects both `Ready` and
  `Waiting`, ordered priority desc → Ready-before-Waiting → FIFO by `joined_at`. A checked-in (Ready)
  student is assignable and preferred at equal priority; priority still outranks Ready.
- **Standby vs Skipped:** a voluntary `/queue/leave` sets `Skipped` (terminal); a missed call (grace
  lapsed) sets `Standby` (recoverable). Reinstatement happens on the next valid heartbeat
  (`PresenceService::reinstateOnReturn`) or QR check-in (sets `Ready`).
- **Grace window** is tracked by two nullable `queue_tickets` columns: `grace_until` (deadline) and
  `grace_offered_at` (one-time warning marker), applied inside the routing transaction.

## Verification

```
php artisan test --filter=AwaySkipStandbyTest
```
