---
id: 018
title: Reverb / broadcasting setup
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 5 / §3"
depends_on: [1]
---

## Objective

Configure Laravel Reverb/WebSocket broadcasting so the backend can push live queue updates to both
frontends.

## Context

§3 shows realtime queue updates over Laravel Reverb/WebSockets. CLAUDE.md requires events to
implement `ShouldBroadcast`, define `broadcastOn/broadcastAs/broadcastWith`, authorize channels in
`routes/channels.php`, and document the client subscription contract. This task is the plumbing;
events come in task 019.

## Scope

**In scope**
- Install/configure Reverb; set `BROADCAST_CONNECTION=reverb` and Reverb env vars + `config/reverb.php`.
- Define channel conventions: presence/private channels per office (`queue.office.{id}`) and per
  student ticket (`ticket.{id}`).
- `routes/channels.php` authorization callbacks (student authorizes own ticket; staff authorize their
  office channel).
- Document the subscription contract (channel names, auth, event naming) in the task/README or code.

**Out of scope**
- The actual broadcast events/payloads (task 019); FCM push (task 020).

## Implementation notes

Use `PrivateChannel` for ticket channels and `PresenceChannel` for office boards if member presence is
useful. Keep payloads DTO-shaped (set in task 019). Provide a working local Reverb run command for
verification.

## API / contract (if applicable)

- WebSocket channels:
  - `private-ticket.{ticketId}` — owner only.
  - `private-queue.office.{officeId}` — staff/admin of that office.
- Auth via the JWT/guard on the broadcasting auth endpoint.

## Acceptance criteria

- [ ] Reverb runs locally (`php artisan reverb:start`) and accepts connections
- [ ] `BROADCAST_CONNECTION=reverb` configured; env documented in `.env.example`
- [ ] Channel authorization enforces ownership (student) and office membership (staff)
- [ ] Subscription contract documented
- [ ] A trivial test event broadcasts and is received by a subscribed client

## Verification

```
php artisan reverb:start &
php artisan tinker --execute="broadcast(new App\Events\PingEvent());"
# subscribe a local client and confirm receipt
```
