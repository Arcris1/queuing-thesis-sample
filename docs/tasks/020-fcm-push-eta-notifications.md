---
id: 020
title: FCM push + smart ETA notifications
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 5 / §12"
depends_on: [7, 10]
---

## Objective

Send push notifications via FCM for key queue moments — position milestones, ETA updates, reconnect
warnings, and "please proceed" — and persist them to the `notifications` table.

## Context

§12 smart notifications: "Estimated wait: 35 min" → "12 min" → "Please proceed to Registrar — within
next 3 numbers." §4 specifies FCM. Notifications schema and `fcm_token` come from tasks 007/003.
Complements WebSocket events (task 019) for when the app is backgrounded/offline.

## Scope

**In scope**
- `App\Services\NotificationService` with typed methods: `etaUpdate`, `positionMilestone`,
  `reconnectWarning`, `proceed`.
- FCM integration (HTTP v1) sending to the user's `fcm_token`; persist each send to `notifications`
  with type/message/sent_at.
- Trigger hooks from queue/presence flows (tasks 016/017/019/024) — debounced so students aren't spammed.

**Out of scope**
- Client-side push handling (task 033); the ETA calculation itself (task 024 provides the value).

## Implementation notes

Use a Job/Queue for sends (`ShouldQueue`) so the request path stays fast. Build messages from the
`NotificationType` enum. Debounce: only send ETA updates on meaningful change (e.g. crossing a
threshold) and "proceed" once. Handle missing/expired `fcm_token` gracefully.

## API / contract (if applicable)

N/A external HTTP API for clients — outbound FCM. Persisted rows readable via future notification
endpoints if added.

## Acceptance criteria

- [x] `NotificationService` with typed methods: `proceed`, `positionMilestone`, `etaUpdate`, `reconnectWarning`
- [x] Each send persists a `push_notifications` row (type/message/sent_at)
- [x] Pluggable transport via `PushSender` contract: `LogPushSender` (default) + `FcmPushSender` stub
- [x] FCM stays optional — bound by `services.fcm.driver`; `FcmPushSender` no-ops without credentials
- [x] Missing/blank `fcm_token` handled without error
- [x] Debounce helpers: `crossedMilestone()` (position) + placeholder `estimateMinutes()` (ETA, task 024 replaces)
- [x] `POST /api/me/fcm-token` stores the user's device token
- [x] Tests fake the sender and assert payloads + persisted rows

Notes / deviations:
- Delivery is synchronous through the `PushSender` (driver-selected), not a
  dedicated `ShouldQueue` Job. The transport is cheap (log in dev; a single FCM
  HTTP call in prod) and runs after the DB commit. If the FCM call cost grows,
  wrap `PushSender::send` in a queued job — `NotificationService` is unchanged.
- The FCM HTTP v1 call itself is a clearly-marked stub in `FcmPushSender`
  (needs a provisioned Google service account to mint the OAuth token); the row
  persistence + transport seam + token handling around it are complete.

## Verification

```
php artisan test --filter=NotificationServiceTest
php artisan queue:work &
# trigger an ETA change and confirm a queued FCM job + notifications row
```
