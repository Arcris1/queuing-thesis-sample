---
id: 035
title: Vue staff login/auth
status: Todo
owner: vue-frontend-designer
plan_ref: "Phase 8 / §7 Auth"
depends_on: [2, 34]
---

## Objective

Build the staff/admin login view and auth flow that authenticates against the API, stores the JWT, and
gates dashboard routes by role.

## Context

Auth endpoints are task 002. The Pinia auth store skeleton and API interceptor come from task 034.
Only `Staff`/`Admin` roles should reach the dashboard. Accessible Tailwind form with full states.

## Scope

**In scope**
- Login view (email/password) with validation, loading/error states.
- Pinia auth store: login/logout actions, token persistence, current-user + role.
- Route guard redirecting unauthenticated/non-staff users to login; logout clears state.
- Friendly handling of `401`/`422` and role-forbidden access.

**Out of scope**
- Live board (task 036), controls (task 037), analytics (task 038).

## Implementation notes

Persist the token (localStorage or httpOnly cookie strategy) and rehydrate on reload. Enforce role on
both the guard and conditional UI. Map API errors to inline messages. Use the base form components from
task 034.

## API / contract (if applicable)

- `POST /api/login`, `POST /api/logout` (task 002). Reject non-staff at the UI/guard layer.

## Acceptance criteria

- [ ] Staff/admin can log in; token persists across reload
- [ ] Non-staff or unauthenticated users are redirected to login
- [ ] Logout clears the session and token
- [ ] 401/422/forbidden handled with clear messages
- [ ] Accessible form (focus/error states), AA contrast
- [ ] Component test covers validation + login flow

## Verification

```
npm run test
npm run dev    # log in as staff, confirm guard + persistence; try a student account (rejected)
```
