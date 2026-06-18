---
name: project-routing-engine
description: Window routing engine + staff window endpoints — eligibility seam, locking, broadcast hooks (tasks 041/021).
metadata:
  type: project
---

The core contribution (plan §5.3/§5.4) lives in `app/Services/RoutingService.php`. Built in tasks 041 (engine) + 021 (staff endpoints); both Done as of 2026-06-18.

**Engine shape:** `assignNext(Window): ?WindowAssignment`, `serve/skip/recall(Window): ?QueueTicket`.
- Reads candidate queue groups live from the `window_queue_groups` pivot, so dynamic-enable (§5.4) needs no code — a unit test proves it.
- Selection uses `QueueTicket::scopeWaitingEligibleOldest($groupIds)` (priority DESC, joined_at ASC) inside `DB::transaction()` + `lockForUpdate()`. Candidates are fetched as a locked collection and scanned in order; first one passing `isEligible()` wins, so an ineligible ticket never blocks those behind it. This is how double-assignment is prevented.
- One open assignment per window enforced via `assignments()->open()->lockForUpdate()` guard.

**Eligibility seam:** `protected isEligible(QueueTicket): bool`. Checks status=Waiting + best-effort presence (via `QueueTicket::latestHeartbeat` → `Heartbeat->presence_status->isEligible()`; no heartbeat = eligible, pre-016) + geofence via `protected isWithinGeofence()` (task 013, Done 2026-06-18). Geofence decision is a single dedicated method: uses `QueueTicket::latestLocationLog` (HasOne, `latestOfMany('recorded_at')`); a sample counts only if fresh (≤ `queue_system.geofence.max_age_seconds`, default 120) AND its recomputed Haversine distance ≤ office radius. NO usable sample → governed by `queue_system.geofence.require_location` (default false → eligible/best-effort; flip to true for strict, no code change). RoutingService now constructor-injects `GeofenceService`; its candidate query eager-loads `latestHeartbeat,latestLocationLog,queueGroup.office`. Tasks 013/016 tighten only this method. **Why:** keeps the research contributions independently buildable + the no-log policy a one-line config flip.

**Broadcast hooks (task 019):** commented one-liners in assignNext/serve/skip/recall mark where `TicketCalled`/`QueueUpdated` dispatch after commit. Reverb itself is task 018/019 — do not build it in the routing layer.

**Endpoints** (`routes/api.php`, `auth:api`): `POST /api/windows/{window}/{available|serve|skip|recall}`. Thin `WindowController` → RoutingService → `AssignedTicketResource` (staff-facing: wraps QueueTicket directly with student identity + call/serve timestamps, NOT QueueTicketResource which wraps TicketStatusData for students). available/skip return `{data:null}` on empty; serve/recall/skip 404 via `NoActiveAssignmentException` when no open assignment.

**Auth gotcha:** staff/admin gate is in `WindowActionRequest` (abstract base for the 4 requests) via role check. Route-model binding for `{window}` does NOT auto-resolve into a Form Request — `WindowActionRequest::window()` resolves it manually with `Window::findOrFail()` (cached per-request). Office-scoped authz is a documented TODO (User has no office_id yet).

See [[project_data_model]] for the Office→QueueGroup→Service→Window data rule.
