---
id: 003
title: Users schema — migration, model, Role enum
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §6"
depends_on: [1]
---

## Objective

Define the `users` table, Eloquent model, and a `Role` enum so accounts can represent students,
staff, and admins across the system.

## Context

§6 lists `users` fields: id, name, student_no, role (student/staff/admin), email, password_hash,
fcm_token. Per CLAUDE.md, fixed value sets must be native backed enums used in model `$casts`, and no
Repository pattern — query Eloquent directly.

## Scope

**In scope**
- Migration `create_users_table` with: `name`, `student_no` (unique, nullable for staff/admin),
  `email` (unique), `password`, `role`, `fcm_token` (nullable), timestamps.
- `App\Enums\Role` backed enum: `Student`, `Staff`, `Admin` with `label()` helper.
- `User` model: `$casts['role' => Role::class]`, `$hidden` for password, relationships stub
  (`queueTickets()`), `$fillable`.
- `UserFactory` with role states (`student()`, `staff()`, `admin()`).

**Out of scope**
- Auth flow (task 002); ticket relationship implementation depends on task 005.

## Implementation notes

Use Laravel's default users migration as a base and extend it. Add an index on `student_no`. Keep
business logic out of the model — only casts, relationships, scopes (e.g. `scopeStaff`).

## API / contract (if applicable)

N/A — schema/model only.

## Acceptance criteria

- [ ] Migration creates `users` with all §6 columns and correct uniqueness/nullability
- [ ] `Role` enum is backed, has `label()`, and is cast on the model
- [ ] Password is hashed and hidden from serialization
- [ ] `UserFactory` produces all three roles
- [ ] `php artisan migrate:fresh` succeeds

## Verification

```
php artisan migrate:fresh
php artisan tinker --execute="App\Models\User::factory()->staff()->create()"
php artisan test --filter=UserModelTest
```
