---
id: 031
title: Flutter background heartbeat service
status: Done
owner: flutter-uiux-pro
plan_ref: "Phase 6 / §9"
depends_on: [15, 28]
---

## Objective

Send a heartbeat every ~30 seconds while a ticket is active so the server can keep the student marked
Active and avoid an unwarranted Away/Offline status.

## Context

§9: heartbeat every 30 s with battery_level, network_status, and optional GPS. Server thresholds
(2/5/10 min) decide Active/Away/Offline/Removed (tasks 015/016). The app must keep the heartbeat alive
in background within OS limits.

## Scope

**In scope**
- A periodic heartbeat (~30 s) calling `POST /api/heartbeat` while a ticket is active.
- Include `battery_level` and `network_status`; optionally piggyback coordinates.
- Best-effort background execution within iOS/Android constraints; pause when no active ticket.
- Surface the returned `presence_status` (e.g. an "Away — reconnecting" banner) on the ticket screen.

**Out of scope**
- Server state machine (task 016); GPS cadence (task 030, separate concern).

## Implementation notes

Keep the heartbeat lightweight and resilient to transient network errors (retry/backoff). Respect OS
background-execution limits; document expected behavior when the app is killed (server will mark
Away/Offline, which is the intended design). Stop heartbeats once the ticket reaches a terminal state.

## API / contract (if applicable)

- `POST /api/heartbeat` `{ battery_level?, network_status?, latitude?, longitude? }` →
  `{ presence_status, last_seen }` (task 015).

## Acceptance criteria

- [x] Heartbeat fires ~every 30 s while a ticket is active (PresenceController
      timer; interval = `AppConfig.heartbeatInterval`, HEARTBEAT_SECONDS define)
- [x] battery (`battery_plus`) + network included; transient errors swallowed
      and retried on the next tick (no overlap via an in-flight guard)
- [x] Heartbeats pause when there is no active ticket — loop is gated on
      `hasActiveTicketProvider`; idle (no timer, no network) until a ticket is
      active, and torn down on terminal status / logout
- [x] Returned `presence_status` reflected in the UI (Away/Offline banner on the
      ticket screen)
- [x] Background behavior documented: foreground/whileInUse only. True OS
      background execution is out of scope for the thesis demo — when the app is
      backgrounded the OS suspends the timer and, if the app is killed, the
      server's 2/5/10-min thresholds move the student to Away→Offline, which is
      the intended design (heartbeat_controller.dart + this file).
- [x] Verified by design: with no heartbeats the server reclaims the slot via
      the presence state machine (tasks 015/016). Client-side loop start/stop is
      unit-tested in `test/presence_heartbeat_test.dart`.

## Verification

```
flutter run    # observe ~30s heartbeats; background the app and watch status transition server-side
```
