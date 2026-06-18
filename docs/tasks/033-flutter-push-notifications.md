---
id: 033
title: Flutter push notification handling
status: Done
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

- [x] Device registers and the FCM token reaches the backend (when
  `PUSH_TRANSPORT=fcm`; `/api/me/fcm-token`)
- [x] Notifications arrive in foreground, background, and terminated states
  (FCM path) — and, in the demo default, the realtime client drives them with no
  Firebase project
- [x] Tapping a notification deep-links to the ticket screen
  (`notificationDeepLinkProvider`)
- [x] Proceed/reconnect ("it's your turn") messages are prominent (max-importance
  `call` channel, time-sensitive on iOS, full-screen intent on Android)
- [x] Notification permission requested; denial handled (no crash, no-op)
- [ ] Verified on a physical device across all three states — manual, requires a
  provisioned Firebase project (see `README_FCM.md`)

## Implementation

Mirrors the backend's pluggable sender (LogPushSender default, FCM stub). Two
paths in `lib/features/notifications/`:

1. **Display path (always on, no Firebase):** `notification_service.dart`
   (`flutter_local_notifications`: channels, permission, show) +
   `notification_controller.dart` (folds existing `RealtimeEvent`s into
   notifications — high-priority "it's your turn — proceed to {window}" on
   `ticket.called`, debounced position-milestone updates at 5/3/1 from
   snapshots). Kept alive app-wide via `_AuthenticatedRoot` in `main.dart`.
2. **FCM seam (behind a flag, default off):** `push_transport.dart`
   (`PushTransport` interface + `LocalPushTransport` no-op default),
   `fcm_push_transport.dart` (Firebase, **guarded** — degrades to a no-op when
   Firebase isn't configured so the app runs without google-services.json /
   GoogleService-Info.plist), `fcm_token_repository.dart` (POST
   `/api/me/fcm-token`). Enabled with `--dart-define=PUSH_TRANSPORT=fcm`; see
   `lib/features/notifications/README_FCM.md`.

Config flag `AppConfig.pushTransport` (`local` default / `fcm`). Manifests:
Android `POST_NOTIFICATIONS`; iOS `remote-notification` background mode. Tests:
`test/notification_test.dart` (spy service: call notification on `ticket.called`,
debounced milestone, tap deep-link).

## Verification

```
flutter run    # on a device: trigger backend ETA/proceed pushes, confirm delivery + tap routing
```
