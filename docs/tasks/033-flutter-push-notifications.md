---
id: 033
title: Flutter push notification handling
status: Todo
owner: flutter-uiux-pro
plan_ref: "Phase 6 / §12"
depends_on: [20, 29]
---

## Objective

Register the device for FCM, deliver smart queue notifications (ETA, position milestones, reconnect
warnings, proceed) in foreground and background, and deep-link taps into the ticket screen.

## Context

§12 smart notifications are sent by the backend (task 020) to the user's `fcm_token` (stored on the
user, task 003). The app must register the token, handle the three FCM states (foreground/background/
terminated), and route taps to the ticket screen (task 029).

## Scope

**In scope**
- FCM setup (iOS APNs + Android), request notification permission, obtain the token and send it to the
  backend (e.g. on login/refresh).
- Handle foreground (in-app banner), background, and terminated-state messages.
- Tap handling → deep-link to the ticket screen.
- Render the smart-notification copy from the server (ETA/position/warning/proceed).

**Out of scope**
- Server send logic (task 020); the ticket screen UI (task 029).

## Implementation notes

Push the FCM token to the backend whenever it changes (update the user's `fcm_token`). Use the
`NotificationType` to style/route messages. Ensure "proceed" and "reconnect warning" are prominent.
Test all three app states on a real device (FCM background needs a device, not just an emulator for iOS).

## API / contract (if applicable)

- Send token via an authenticated profile/update call (store on `users.fcm_token`).
- Inbound FCM payloads shaped by `NotificationService` (task 020).

## Acceptance criteria

- [ ] Device registers and the FCM token reaches the backend
- [ ] Notifications arrive in foreground, background, and terminated states
- [ ] Tapping a notification deep-links to the ticket screen
- [ ] Proceed/reconnect messages are prominent
- [ ] Notification permission requested with rationale; denial handled
- [ ] Verified on a physical device across all three states

## Verification

```
flutter run    # on a device: trigger backend ETA/proceed pushes, confirm delivery + tap routing
```
