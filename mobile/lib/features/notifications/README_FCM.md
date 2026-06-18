# Push notifications (task 033)

The app has two notification paths, mirroring the backend's pluggable sender
(`LogPushSender` default, FCM stub):

1. **Display path — always on, no infrastructure.** `flutter_local_notifications`
   shows OS notifications. `NotificationController` listens to the existing
   realtime client (`RealtimeEvent`s from polling or WebSocket) and fires:
   - a high-priority **"It's your turn — proceed to {window}"** on `ticket.called`
   - debounced **position-milestone** updates (positions 5 / 3 / 1) from snapshots

   This works in the demo with **no Firebase project**. It is the default
   (`PUSH_TRANSPORT=local`).

2. **Remote transport — FCM, behind a flag.** `FcmPushTransport` obtains a device
   token, POSTs it to `POST /api/me/fcm-token`, and routes
   foreground/background/terminated messages into the **same** display path.

## Enabling real FCM

By default `AppConfig.pushTransport == 'local'` and **no Firebase code runs** —
the app starts fine without `google-services.json` / `GoogleService-Info.plist`.
`FcmPushTransport.start()` is additionally guarded: if Firebase isn't configured,
it catches the failure and degrades to a no-op, so the local path keeps working.

To switch on real FCM:

1. **Provision Firebase**: create a project, add Android + iOS apps, and run
   `flutterfire configure` (or place the platform config files manually):
   - Android: `android/app/google-services.json` + the Google Services Gradle
     plugin in `android/app/build.gradle` and `android/build.gradle`.
   - iOS: `ios/Runner/GoogleService-Info.plist`, enable the **Push Notifications**
     and **Background Modes → Remote notifications** capabilities, and upload the
     **APNs auth key** to Firebase. (iOS background/terminated delivery requires a
     real device — the simulator cannot receive APNs.)
2. **Run with the flag:**
   ```
   flutter run --dart-define=PUSH_TRANSPORT=fcm
   ```
3. The device token is sent to the backend automatically on start and on
   refresh. The backend (task 020) sends payloads with a `type` data field
   (`call` / `proceed` → high-priority; otherwise milestone) and a `ticket_id`
   used to deep-link a tap back to the live ticket screen.

## Manifests

- Android: `POST_NOTIFICATIONS` (Android 13+) is declared in
  `AndroidManifest.xml`; `flutter_local_notifications` requests it at runtime.
- iOS: `Info.plist` declares `remote-notification` background mode. Notification
  permission is requested at runtime by `LocalNotificationService` /
  `FcmPushTransport`.

## Deep-linking a tap

A tapped notification sets `notificationDeepLinkProvider` to the ticket id (or
`-1` when the payload carries none). A router/host widget can watch it and route
to `TicketStatusScreen`. Wiring the watch into navigation is left to the host
since the app currently uses imperative `Navigator` rather than a router.
