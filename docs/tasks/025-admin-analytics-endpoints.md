---
id: 025
title: admin/analytics + admin live queue endpoints
status: Done
owner: laravel-backend-engineer
plan_ref: "Phase 8 / §7,§12"
depends_on: [21]
---

## Objective

Provide the read APIs that power the staff/admin dashboard: a live per-office queue snapshot and
aggregate analytics.

## Context

§7 lists `GET /api/admin/analytics` and `GET /api/admin/queue/{office}/live` ("live board across queue
groups & windows"). §12 analytics: average waiting time, peak hours, students served, missed-queue
count, service duration **per office, queue group, service, and window**, window utilization/idle
time, plus live counts (waiting/active/away/offline) and prediction accuracy. The live board is
organized **by queue group and by window** (§5). Dynamic queue-group attach/detach is its own endpoint
set (task 043) — this task only reads/reports. Consumed by Vue tasks 036/038.

## Scope

**In scope**
- `GET /api/admin/queue/{office}/live` — per **queue group**: current_number, counts by status
  (waiting/active/away/offline/standby); per **window**: status, attached queue groups, current
  assignment.
- `GET /api/admin/analytics` — averages and aggregates from `service_history` + tickets +
  `window_assignments`: avg wait, peak hours, served count, missed/skip count, avg service duration
  (by office/queue group/service/window), window utilization/idle time, prediction accuracy (from
  task 023 metrics).
- `AnalyticsService` doing the aggregation; `Resource`-shaped responses; admin/staff authorization.

**Out of scope**
- Dynamic queue-group attach/detach (task 043); the Vue rendering (tasks 036/038); realtime board
  updates come via task 019 broadcasts.

## Implementation notes

Support a date-range query on analytics. Compute presence counts from latest heartbeats + config
thresholds (reuse `PresenceService`). Keep aggregation queries indexed (tasks 005/007 indexes). Avoid
N+1 by aggregating in SQL where possible.

## API / contract (if applicable)

- `GET /api/admin/queue/{office}/live` (auth, staff) → `200 { data: { queue_groups: [ { name, prefix,
  current_number, counts:{waiting,active,away,offline,standby} } ], windows: [ { name, status,
  queue_groups, current_assignment } ] } }`
- `GET /api/admin/analytics?office_id=&from=&to=` (auth, admin/staff) → `200 { data: { avg_wait_min,
  peak_hours, served, missed, avg_service_min, by_queue_group, by_window, window_utilization,
  prediction_accuracy } }`

## Acceptance criteria

- [x] Live endpoint returns per-queue-group counts/now-serving AND per-window status + current assignment
- [x] Analytics returns avg wait, peak hours, served, missed, avg duration, window utilization
      (prediction accuracy deferred — see note below; task 023 metrics not yet present)
- [x] Date-range filtering works on analytics
- [x] Staff/admin authorization enforced
- [x] Aggregations avoid N+1; Resource-shaped responses
- [x] Feature tests cover live + analytics with seeded data

> Note: prediction-accuracy is intentionally out of this delivery — it depends on
> task 023's stored prediction-vs-actual metrics, which do not exist yet. The
> analytics shape leaves room to add an `prediction_accuracy` key once 023 lands.

## Verification

```
php artisan test --filter=AdminAnalyticsTest
curl localhost:8000/api/admin/queue/1/live -H 'Authorization: Bearer <staff>'
curl "localhost:8000/api/admin/analytics?office_id=1" -H 'Authorization: Bearer <admin>'
```
