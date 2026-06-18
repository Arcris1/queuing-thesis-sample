---
id: 022
title: Service history capture on serve + seed data
status: Done
owner: laravel-backend-engineer
plan_ref: "Phase 7 / §10"
depends_on: [21]
---

## Objective

Record a `service_history` row every time a ticket is served, capturing the features the AI model
needs, and provide a realistic seeded dataset to bootstrap training before live data exists.

## Context

§10 lists model inputs: people ahead in the queue group, service type, **active windows serving the
group**, office, time of day, day of week, recent service speed. `service_history` (task 007) stores
per-transaction outcomes and is **queue-group and window aware** (`queue_group_id`, `window_id`,
`active_windows`). The plan notes bootstrapping with seeded/synthetic data for the defense when real
history is thin.

## Scope

**In scope**
- Hook into `serve()` (task 021): on `Served`, compute `duration_minutes` from the
  `window_assignments` row (`served_at - assigned_at`), and capture `office_id`, `queue_group_id`,
  `service_id`, `window_id`, `day_of_week`, `hour_of_day`, and `active_windows` (count of windows then
  serving that queue group), then write a `service_history` row.
- `ServiceHistorySeeder` generating realistic synthetic history (3–6 months) per office/queue
  group/service using the `avg_service_minutes` from task 004 with plausible variance, peak-hour
  effects, and a realistic spread of `active_windows`.

**Out of scope**
- Training the model (task 023); the estimate endpoint (task 024).

## Implementation notes

Capture features at serve time (denormalized) so training reads them directly. `active_windows` =
count of windows attached to the served ticket's queue group that were open at serve time (drives the
`(people × avg) ÷ windows` signal). Seeder should vary by hour/day and window count to give the
regression signal (peak hours slower, more windows faster).

## API / contract (if applicable)

N/A — write path on serve + seeder.

## Acceptance criteria

- [x] Every serve writes one `service_history` row incl. `queue_group_id`, `window_id`, `active_windows`
- [x] `duration_minutes` derived correctly from the `window_assignments` timestamps
- [x] Seeder produces a few months of varied synthetic data per office/queue group/service
- [x] Peak-hour/day and window-count variance is visible in seeded data
- [x] Tests assert a history row is created on serve

## Verification

```
php artisan test --filter=ServiceHistoryCaptureTest
php artisan db:seed --class=ServiceHistorySeeder
php artisan tinker --execute="echo App\Models\ServiceHistory::count()"
```
