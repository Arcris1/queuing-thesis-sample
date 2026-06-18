---
id: 026
title: Flutter scaffold + Material 3 design system
status: Todo
owner: flutter-uiux-pro
plan_ref: "Phase 6 / §4"
depends_on: [1]
---

## Objective

Create the Flutter student app project with a cohesive Material 3 design system (theming, typography,
spacing, light/dark mode) and the API/networking foundation all later screens build on.

## Context

§4: Flutter student app with queue ticket, push, GPS, QR. CLAUDE.md/flutter-uiux-pro mandates Material
3, semantic `ColorScheme`, light+dark, a 4/8/12/16/24/32 spacing scale, WCAG AA contrast, and
purposeful 150–300 ms motion. All data comes from the Laravel API (no business logic on device).

## Scope

**In scope**
- `flutter create` app; set up state management (e.g. Riverpod/Bloc) and routing.
- `ThemeData`/`ColorScheme` (light + dark), typography scale, spacing tokens/constants.
- API client layer (Dio/http) with base URL config, auth-token interceptor, error mapping.
- Base reusable widgets (buttons, cards, loading/empty/error states) and app scaffold/navigation.

**Out of scope**
- Login (task 027) and feature screens (028+); push/GPS/heartbeat plugins (tasks 030/031/033).

## Implementation notes

Centralize design tokens in a `theme/` folder. Make the API base URL build-config driven (dev vs
prod). Build the token interceptor so task 027 can store/attach the JWT. Provide a consistent
loading/empty/error widget set since every screen needs them.

## API / contract (if applicable)

- Consumes `GET /api/health` to validate connectivity during setup.

## Acceptance criteria

- [ ] App builds and runs on iOS + Android
- [ ] Material 3 theme with working light/dark mode and AA contrast
- [ ] Spacing/typography tokens centralized and used by base widgets
- [ ] API client with configurable base URL + auth interceptor + error handling
- [ ] Reusable loading/empty/error widgets exist
- [ ] `flutter analyze` passes

## Verification

```
flutter pub get
flutter analyze
flutter run    # app boots, toggles light/dark, hits /api/health
```
