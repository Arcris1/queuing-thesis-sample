---
id: 002
title: JWT auth + register/login/logout endpoints
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §7 Auth"
depends_on: [1]
---

## Objective

Provide JWT-based authentication so students, staff, and admins can register, log in, and log out,
returning a token used by every protected endpoint and both frontends.

## Context

§4 mandates JWT auth. The `users` table/model and `Role` enum come from task 003; this task wires
the auth flow and middleware. Controllers must stay thin — validation via Form Requests, logic in an
`AuthService`, responses via API Resources (CLAUDE.md).

## Scope

**In scope**
- Install/configure a JWT package (e.g. `tymon/jwt-auth` or Sanctum-token strategy) and the
  `auth:api` guard.
- `RegisterRequest`, `LoginRequest` Form Requests → `AuthDTO`/`RegisterDTO`.
- `AuthService` with `register()`, `login()`, `logout()` returning DTO-shaped results.
- `AuthController` (thin) + `UserResource` and a consistent token envelope.
- Route protection middleware applied to all later authenticated routes.

**Out of scope**
- User schema and Role enum (task 003); per-role authorization rules beyond basic guard.

## Implementation notes

Place token issuance in `AuthService`. Return `{ token, token_type: "bearer", expires_in, user }`.
Use `RegisterRequest::rules()` to enforce unique `student_no`/`email`. Convert validated input to a
DTO before passing to the service. Never put token logic in the controller.

## API / contract (if applicable)

- `POST /api/register` → body `{ name, student_no, email, password, password_confirmation, role? }`
  → `201 { token, token_type, expires_in, user: UserResource }`
- `POST /api/login` → body `{ email|student_no, password }` → `200 { token, ... }`
- `POST /api/logout` (auth) → `204`
- Errors: `422` validation, `401` invalid credentials.

## Acceptance criteria

- [ ] Register creates a user and returns a valid JWT
- [ ] Login with correct credentials returns a token; wrong credentials return `401`
- [ ] Logout invalidates the token (subsequent authed call returns `401`)
- [ ] Validation handled by Form Requests; logic lives in `AuthService`
- [ ] `UserResource` shapes the user payload (no password/hash leaked)
- [ ] Feature tests cover register/login/logout happy + failure paths

## Verification

```
php artisan test --filter=AuthTest
# manual:
curl -X POST localhost:8000/api/register -d '{...}' -H 'Content-Type: application/json'
curl -X POST localhost:8000/api/login -d '{...}' -H 'Content-Type: application/json'
```
