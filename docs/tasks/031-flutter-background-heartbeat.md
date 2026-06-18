---
id: 031
title: Flutter background heartbeat service
status: Todo
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

- [ ] Heartbeat fires ~every 30 s while a ticket is active
- [ ] battery/network included; transient errors retried
- [ ] Heartbeats pause when there is no active ticket
- [ ] Returned `presence_status` reflected in the UI (Away/offline banner)
- [ ] Background behavior documented for iOS/Android
- [ ] Verified that stopping the app leads to server Away/Offline as designed

## Verification

```
flutter run    # observe ~30s heartbeats; background the app and watch status transition server-side
```
