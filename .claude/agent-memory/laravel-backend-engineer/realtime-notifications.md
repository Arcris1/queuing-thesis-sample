---
name: realtime-notifications
description: Reverb broadcasting + push notification architecture — channels, event dispatch points, and the PushSender transport seam
metadata:
  type: project
---

Realtime + push were built in tasks 018/019/020. Architecture decisions worth keeping:

**Broadcast channels** (`routes/channels.php`, all PrivateChannel, authorized over the
JWT `api` guard via `withBroadcasting(..., middleware: ['auth:api'])` in `bootstrap/app.php`):
- `user.{id}` — student's personal channel (owner only). Stable across tickets (NOT
  `ticket.{id}` — the 018 sketch was superseded for this reason).
- `queue-group.{id}` and `office.{id}` — staff boards, gated to Role::Staff/Admin.
- The PUBLIC "now serving" board has no socket — it polls `GET /api/queue/current`.

**Events** (`app/Events/`): `TicketCalled` (ShouldBroadcastNow — latency-sensitive call),
`QueueUpdated` (ShouldBroadcast, lightweight: `{queue_group_id, office_id, now_serving,
waiting_count}` — clients refetch detail), `PresenceChanged` (ShouldBroadcast). Each defines
`broadcastAs()` → `ticket.called` / `queue.updated` / `presence.changed`. `QueueUpdated::forGroup()`
and `PresenceChanged::forTicket()` are static factories; dispatch built instances with `event()`,
not `::dispatch()`.

**Dispatch points** are post-`DB::transaction`-commit hooks in the services (not models/controllers):
RoutingService (assignNext/serve/skip/recall), QueueService (join/leave), PresenceService
(moveToStandby/reclaimAbandoned). Private helpers `announceCall()` + `broadcastQueueUpdated()`
in RoutingService/PresenceService eager-load relations once to avoid N+1 in event payloads.

**Push notifications** (`NotificationService`): writes a `push_notifications` row per send,
then hands a `PushMessageData` DTO to the injected `PushSender` contract. Transport is
config-selected in `AppServiceProvider` by `services.fcm.driver`: `LogPushSender` (default,
dev/CI — no FCM needed) or `FcmPushSender` (stub; no-ops safely without credentials). Senders
MUST tolerate a null/blank token. ETA is a placeholder (`estimateMinutes` = people_ahead ×
`queue_system.notifications.avg_service_minutes`) until task 024's regression lands. Milestone
debounce via `crossedMilestone()` against `queue_system.notifications.position_milestones`.

**Tests** fake broadcasting/senders — never require a running Reverb server. Channel auth is
tested by reflecting `Broadcast::driver()`'s `channels` property and invoking the closures
directly (the null test broadcaster's `auth()` is a no-op, so HTTP `/broadcasting/auth` status
codes are NOT reliable in tests).

Related: [[routing-eligibility-seam]], [[presence-grace-model]].
