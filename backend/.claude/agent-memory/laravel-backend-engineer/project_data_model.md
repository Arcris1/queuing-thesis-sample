---
name: project-data-model
description: Smart Queue DB layer conventions — notifications table renamed, routing scope, seeder shape, service count deviation
metadata:
  type: project
---

Conventions established when building the DB layer (migrations/models/enums/seeders).

**Why:** Non-obvious decisions a future task could trip over.
**How to apply:** Reuse these when extending the schema or routing.

- The app's notifications table is **`push_notifications`** (model `PushNotification`), deliberately NOT `notifications`, to avoid colliding with Laravel's notification system. Task 007 mandated this.
- Routing engine entry point: `QueueTicket::scopeWaitingEligibleOldest(array $queueGroupIds)` — filters waiting tickets in those groups, orders `priority desc, joined_at asc`. Backed by index `queue_tickets_routing_index (queue_group_id, status, priority, joined_at)`. This is what the Phase 2 routing service should call. Presence/geofence eligibility is layered on top later (scope only handles status + ordering).
- `window_assignments.served_at IS NULL` = the window's current open assignment (`WindowAssignment::scopeOpen` / `Window::currentAssignment`). "At most one open per window" must be enforced in the service layer (task 041), not the DB.
- Seeder: single coherent `OfficeServiceSeeder` (not 4 separate seeders) driven by a `catalog()` data array — seeds offices + queue groups + services + windows + pivot in one pass so prefixes/group wiring stay consistent. Called from `DatabaseSeeder`, which also seeds admin@/staff@/student@example.com (password `password`).
- **Deviation from task 004's note:** plan §5.1 lists **10** services (Registrar group has Enrollment+Document+Grades+Transcript = 4), so seeded count is 10, not the "9" the task file guessed. The plan is authoritative; 10 is correct.
- Office coords: Registrar 14.6001/121.0501, Accounting 14.6002/121.0502, Cashier 14.6003/121.0503, all geofence_radius_m=15.
- New migrations are timestamped `2026_06_18_1000xx`..`1011xx` in FK-dependency order. The existing users migration (`0001_01_01_000000`) was extended in place to add student_no/role/fcm_token.
