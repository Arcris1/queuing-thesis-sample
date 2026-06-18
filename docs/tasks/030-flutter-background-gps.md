---
id: 030
title: Flutter background GPS location updates
status: Todo
owner: flutter-uiux-pro
plan_ref: "Phase 6 / §8"
depends_on: [13, 28]
---

## Objective

Periodically capture the device's GPS position while the student holds an active ticket and send raw
coordinates to the API so the server can evaluate geofence eligibility.

## Context

§8: the client sends raw lat/lng; the **server** computes distance and decides eligibility (task 013).
The app must keep sending location as the student approaches, especially near their turn (§11 triggers
verification when ~5 remain). Requires location permissions and background handling.

## Scope

**In scope**
- Request foreground + (where allowed) background location permissions with clear rationale UI.
- A location service that sends `POST /api/location/update` on a sensible cadence/displacement while a
  ticket is active, throttled to save battery.
- Reflect the server's `within_radius`/`distance_m` response in the ticket screen (task 029).
- Graceful handling of denied/disabled location (prompt + degraded state).

**Out of scope**
- Server eligibility logic (task 013); heartbeat (task 031, separate cadence).

## Implementation notes

Never compute distance on-device for eligibility — send raw coordinates and trust the server's
response. Increase cadence when the user is near their turn; back off when far down the queue. Use a
well-maintained geolocation plugin; document iOS/Android permission strings.

## API / contract (if applicable)

- `POST /api/location/update` `{ latitude, longitude }` → `{ distance_m, within_radius, radius_m }`
  (task 013).

## Acceptance criteria

- [ ] Sends raw coordinates while a ticket is active, throttled for battery
- [ ] Cadence increases as the user nears their turn
- [ ] Server `within_radius`/`distance_m` reflected in the UI
- [ ] Permission denied/disabled handled gracefully with guidance
- [ ] No on-device eligibility decision (server is authoritative)
- [ ] Test/mocked-location verification documented

## Verification

```
flutter run    # mock locations inside/outside 15 m; confirm server responses drive the UI
```
