---
id: 019
title: Queue broadcast events (position/called)
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 5 / §12"
depends_on: [18, 10]
---

## Objective

Broadcast real-time queue changes — position updates, "you're being called", and office board updates
— to the relevant ticket and office channels.

## Context

§12 requires real-time monitoring for students and a live staff dashboard. Builds on Reverb plumbing
(task 018) and the status data (task 010). Events implement `ShouldBroadcast` with DTO-shaped
payloads (CLAUDE.md).

## Scope

**In scope**
- `QueuePositionUpdated` event → `private-ticket.{id}` with `{ ticket_number, position, people_ahead,
  status, eta }`.
- `TicketCalled` event → `private-ticket.{id}` with `{ ticket_number, message, office }`.
- `OfficeQueueUpdated` event → `private-queue.office.{id}` with counts (waiting/active/away/offline).
- Fire these events from `QueueService`/`PresenceService` on join, advance, call, and presence
  transitions.

**Out of scope**
- FCM push for offline/background users (task 020); ETA computation internals (task 024 feeds `eta`).

## Implementation notes

Define `broadcastAs()` names (`queue.position`, `ticket.called`, `queue.updated`) and `broadcastWith()`
returning DTO arrays. Queue broadcasts (`ShouldBroadcast`) unless latency demands `ShouldBroadcastNow`
(use for `TicketCalled`). Avoid broadcasting on every heartbeat — only on meaningful change.

## API / contract (if applicable)

- Channel `private-ticket.{id}`: events `queue.position`, `ticket.called`.
- Channel `private-queue.office.{id}`: event `queue.updated`.
- Payload shapes as above; document for the Flutter (task 029) and Vue (task 036) clients.

## Acceptance criteria

- [x] Being-called broadcasts to the owning student channel + boards
- [x] Being called broadcasts immediately (`ShouldBroadcastNow`) with a proceed message
- [x] Office/queue-group board counts broadcast on change (`QueueUpdated`)
- [x] Payloads are DTO-shaped and match the documented contract
- [x] Events are NOT emitted on every heartbeat — only on real changes
- [x] Tests assert events dispatched with `Event::fake()` (no Reverb server needed)

## Delivered — client subscription contract

Auth: all channels are PrivateChannels. Clients POST their JWT Bearer token to
`/broadcasting/auth` (registered with the `auth:api` guard in `bootstrap/app.php`)
to subscribe. Mirror of `routes/channels.php`.

Channels and events (event name = `broadcastAs()`):

- `private-user.{userId}` — the student's personal channel (owner only).
  - `ticket.called` — `App\Events\TicketCalled` (ShouldBroadcastNow).
    Payload: `{ ticket: { id, ticket_number, status, student_id, called_at },
    window: { id, name }, queue_group: { id, name, prefix },
    office: { id, name }, message }`.

- `private-queue-group.{queueGroupId}` — a group's live board (staff/admin only).
  - `ticket.called` — same payload as above.
  - `queue.updated` — `App\Events\QueueUpdated` (ShouldBroadcast).
    Payload: `{ queue_group_id, office_id, now_serving, waiting_count }`.
    Lightweight — clients refetch `GET /api/queue/current` / `/api/queue/status`
    for full detail.
  - `presence.changed` — `App\Events\PresenceChanged` (ShouldBroadcast).
    Payload: `{ ticket_id, queue_group_id, office_id, presence, ticket_status }`.

- `private-office.{officeId}` — an office-wide board (staff/admin only). Carries
  `ticket.called`, `queue.updated`, `presence.changed` (same shapes).

The PUBLIC "now serving" board has no socket — it polls `GET /api/queue/current`.

Dispatch points (all after the DB::transaction commit):
- `RoutingService::assignNext` → `TicketCalled` + proceed push + `QueueUpdated`.
- `RoutingService::serve` → `QueueUpdated`.
- `RoutingService::skip` → `QueueUpdated` (skipped) then `assignNext`'s call.
- `RoutingService::recall` → re-`TicketCalled` + proceed push (no state change).
- `QueueService::join` / `leave` → `QueueUpdated`.
- `PresenceService::moveToStandby` / `reclaimAbandoned` → `PresenceChanged` + `QueueUpdated`.

## Verification

```
php artisan test --filter=QueueBroadcastTest
# with reverb running, join/advance a queue and observe channel messages
```
