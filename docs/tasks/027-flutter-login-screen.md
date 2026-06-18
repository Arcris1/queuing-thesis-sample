---
id: 027
title: Flutter login screen
status: Todo
owner: flutter-uiux-pro
plan_ref: "Phase 6 / §7 Auth"
depends_on: [2, 26]
---

## Objective

Build a polished, accessible login (and registration) screen that authenticates against the API and
securely stores the JWT for subsequent requests.

## Context

Auth endpoints come from task 002 (`/api/login`, `/api/register`, `/api/logout`). The design system,
API client, and token interceptor come from task 026. Material 3, full input states, accessibility.

## Scope

**In scope**
- Login screen (student_no/email + password) with validation, loading/error states, and submit.
- Registration screen for students.
- Secure token storage (`flutter_secure_storage`) and wiring the token into the API interceptor.
- Auth state gate: route to the home/join screen when authenticated, back to login on logout/401.

**Out of scope**
- Join/ticket screens (tasks 028/029); push registration (task 033).

## Implementation notes

Use Material 3 `TextField`s with inline validation and visible focus/error states. Map API `422`/`401`
to friendly messages. Persist the token securely (not plain prefs). Handle token expiry → redirect to
login.

## API / contract (if applicable)

- `POST /api/login`, `POST /api/register`, `POST /api/logout` (see task 002 contract).

## Acceptance criteria

- [ ] Successful login stores the JWT securely and routes to home
- [ ] Validation + API errors shown clearly (422/401)
- [ ] Registration creates an account and logs in
- [ ] Logout clears the token and returns to login
- [ ] Inputs accessible with focus/error/disabled states; AA contrast
- [ ] Widget tests cover form validation and the auth flow

## Verification

```
flutter test test/login_test.dart
flutter run    # log in against the local API, confirm routing + token persistence
```
