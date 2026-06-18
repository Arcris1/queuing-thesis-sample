---
id: 007
title: Notifications & ServiceHistory schema
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §6"
depends_on: [3, 4, 40]
---

## Objective

Store sent notifications and the historical service-transaction records that the AI wait-time model
trains on.

## Context

§6 defines `notifications` (user_id, type, message, sent_at, read_at) and `service_history`
(office_id, queue_group_id, service_id, window_id, served_at, duration_minutes, day_of_week,
hour_of_day, active_windows). `service_history` is the training set for §10; the model is
**queue-group and window aware**, so it records which queue group and window served the ticket and how
many windows were serving that group at the time. Capturing it correctly later (task 022) depends on
this schema.

## Scope

**In scope**
- Migration `create_notifications_table`: `user_id` FK, `type` (string), `message` (text),
  `sent_at`, `read_at` (nullable), timestamps.
- Migration `create_service_history_table`: `office_id` FK, **`queue_group_id` FK**, `service_id` FK,
  **`window_id` FK**, `served_at`, `duration_minutes` (decimal), `day_of_week` (tiny int 0–6),
  `hour_of_day` (tiny int 0–23), **`active_windows` (tiny int)** — number of windows serving that
  queue group at serve time, timestamps.
- `Notification` and `ServiceHistory` models + relationships and factories.
- Optional `NotificationType` backed enum (PositionUpdate, EtaUpdate, Proceed, ReconnectWarning).

**Out of scope**
- Sending notifications / FCM (task 020); writing history rows on serve (task 022); training (task 023).

## Implementation notes

Index `service_history` on (`office_id`,`served_at`), (`queue_group_id`,`served_at`), and
(`day_of_week`,`hour_of_day`) for training queries. Keep `day_of_week`/`hour_of_day`/`active_windows`
denormalized at write time so the model reads features directly. The `window_id`/`queue_group_id` FKs
require the `windows` and `queue_groups` tables (tasks 040, 004) — hence the dependency on 040; make
`window_id` nullable so historical rows survive a window being removed. Use the app's own
`notifications` table (distinct from Laravel's built-in notifications) — name the model explicitly to
avoid clashes.

## API / contract (if applicable)

N/A — schema only.

## Acceptance criteria

- [ ] Both migrations run with FK constraints and the training indexes
- [ ] Models + factories exist and relationships resolve
- [ ] `NotificationType` enum (if added) is backed and cast
- [ ] `php artisan migrate:fresh` succeeds

## Verification

```
php artisan migrate:fresh
php artisan tinker --execute="App\Models\ServiceHistory::factory()->count(5)->create()"
php artisan test --filter=NotificationHistorySchemaTest
```
