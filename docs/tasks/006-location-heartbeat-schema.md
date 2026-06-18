---
id: 006
title: LocationLogs & Heartbeats schema + PresenceStatus enum
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §6"
depends_on: [3, 5]
---

## Objective

Persist student GPS location samples and app heartbeats, and define the presence status enum that
drives Active/Away/Offline/Removed handling.

## Context

§6 defines `location_logs` (user_id, ticket_id, latitude, longitude, distance_m, recorded_at) and
`heartbeats` (user_id, ticket_id, last_seen, battery_level, network_status). §9 defines presence
thresholds (Active <2m, Away >2m, Offline >5m, Removed >10m). Distance is computed server-side
(task 012/013); this task just stores it.

## Scope

**In scope**
- Migration `create_location_logs_table`: `user_id` FK, `ticket_id` FK (nullable), `latitude`,
  `longitude` (decimal 10,7), `distance_m` (decimal, nullable), `recorded_at`, timestamps.
- Migration `create_heartbeats_table`: `user_id` FK, `ticket_id` FK (nullable), `last_seen`,
  `battery_level` (tiny int nullable), `network_status` (string nullable), timestamps.
- `App\Enums\PresenceStatus` backed enum (Active, Away, Offline, Removed) with `label()`/`color()`.
- `LocationLog` and `Heartbeat` models + relationships; index (`ticket_id`,`recorded_at`/`last_seen`).

**Out of scope**
- The heartbeat endpoint (task 015) and the state-machine job (task 016); eligibility math (task 012).

## Implementation notes

`PresenceStatus` is derived from `last_seen` deltas, so it is computed (not a stored column on
heartbeats) — expose a helper/accessor or compute in the presence service later. Keep thresholds in
config (`config/queue_system.php`) so §15 stays configurable.

## API / contract (if applicable)

N/A — schema/enum only.

## Acceptance criteria

- [ ] Both migrations run with FK constraints and time indexes
- [ ] `PresenceStatus` enum backed with `label()`/`color()`
- [ ] Threshold values live in config, defaulting to 2/5/10 minutes
- [ ] Factories exist for both models
- [ ] `php artisan migrate:fresh` succeeds

## Verification

```
php artisan migrate:fresh
php artisan tinker --execute="App\Models\Heartbeat::factory()->create()"
php artisan test --filter=PresenceSchemaTest
```
