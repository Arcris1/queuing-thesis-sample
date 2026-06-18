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

- [ ] Sends ETA/position/warning/proceed pushes to the user's FCM token
- [ ] Each send persists a `notifications` row
- [ ] Sends run via queued Jobs; missing tokens handled without error
- [ ] Debounced — no duplicate spam for unchanged state
- [ ] Tests fake the FCM client and assert payloads + persisted rows

## Verification

```
php artisan test --filter=NotificationServiceTest
php artisan queue:work &
# trigger an ETA change and confirm a queued FCM job + notifications row
```
