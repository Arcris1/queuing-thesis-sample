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

- [ ] Position changes broadcast to the owning ticket channel
- [ ] Being called broadcasts immediately (`ShouldBroadcastNow`) with a proceed message
- [ ] Office board counts broadcast to the office channel on change
- [ ] Payloads are DTO-shaped and match the documented contract
- [ ] Events are NOT emitted on every heartbeat — only on real changes
- [ ] Tests assert events dispatched with `Event::fake()`

## Verification

```
php artisan test --filter=QueueBroadcastTest
# with reverb running, join/advance a queue and observe channel messages
```
