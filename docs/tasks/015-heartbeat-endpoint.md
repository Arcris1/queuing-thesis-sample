---
id: 015
title: heartbeat endpoint (POST /api/heartbeat)
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 4 / §9"
depends_on: [8, 6]
---

## Objective

Receive the app's periodic heartbeat (every 30 s) to record liveness, battery, network, and last GPS,
so the system can derive Active/Away/Offline/Removed presence.

## Context

§9: app sends a heartbeat every 30 s with last_seen, battery_level, gps_location, network_status. The
status thresholds (2/5/10 min) are evaluated by the scheduled job (task 016); this task only ingests
and timestamps heartbeats. Schema from task 006.

## Scope

**In scope**
- `HeartbeatRequest` validating optional `battery_level` (0–100), `network_status`, optional
  `latitude`/`longitude`.
- `PresenceService::heartbeat(HeartbeatDTO)` — upsert/insert a `heartbeats` row with
  `last_seen = now()` for the user's active ticket; optionally write a `location_logs` sample.
- Return the user's currently derived `presence_status`.

**Out of scope**
- The away/offline transition job (task 016); skip/grace logic (task 017).

## Implementation notes

Compute `presence_status` from `now() - last_seen` using config thresholds (§15). Keep the endpoint
cheap — it's called frequently. If coordinates are included, reuse the location pipeline from task 013
rather than duplicating distance logic.

## API / contract (if applicable)

- `POST /api/heartbeat` (auth) → body `{ battery_level?, network_status?, latitude?, longitude? }`
  → `200 { data: { presence_status, last_seen } }`
- Errors: `422` invalid fields, `401`.

## Acceptance criteria

- [ ] Each call records `last_seen = now()` for the user's active ticket
- [ ] Returns derived `presence_status` from config thresholds
- [ ] Battery/network/coords optional and validated
- [ ] Endpoint is lightweight (no N+1, minimal writes)
- [ ] Feature tests cover heartbeat update and status derivation

## Verification

```
php artisan test --filter=HeartbeatTest
curl -X POST localhost:8000/api/heartbeat -H 'Authorization: Bearer <t>' \
  -d '{"battery_level":80,"network_status":"wifi"}' -H 'Content-Type: application/json'
```
