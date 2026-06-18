---
name: project-api-conventions
description: Auth/catalog API conventions — JWT guard wiring, resource envelope, the JsonResource 201 gotcha.
metadata:
  type: project
---

API layer conventions established with the AUTH + CATALOG endpoints (tasks 002, 042).

**JWT guard**: `config/auth.php` `defaults.guard = api`; an `api` guard (driver `jwt`, provider `users`) sits alongside the intact `web` session guard. `AuthService` constructor-injects `PHPOpenSourceSaver\JWTAuth\JWTGuard`, bound in `AppServiceProvider::register()` to `Auth::guard('api')` so services get DI instead of facades.

**Response envelope**: plain Laravel API Resource JSON wrapped in `{ "data": ... }` (the default `JsonResource` wrapper). No custom envelope — controllers return Resources / `Resource->response()`.

**Auth token shape**: `AuthTokenResource` over an `AuthTokenData` DTO → `{ data: { access_token, token_type: "bearer", expires_in, user: UserResource } }`. expires_in = `guard->factory()->getTTL() * 60` (seconds).

**Catalog is PUBLIC** (no auth) so the mobile app can render the join catalog pre-login. `GET /api/offices/{office}/services` uses a dedicated `OfficeServicesResource` returning `office` and `queue_groups` as siblings (NOT nested under office), each group with `services[]`. Only **open** queue groups returned (`CatalogService` filters `QueueGroupStatus::Open`, eager-loads `queueGroups.services`).

**GOTCHA — JsonResource 201**: returning a bare `JsonResource` from a controller can emit HTTP 201 instead of 200 when the underlying model's `wasRecentlyCreated` flag is true (it propagates through the JWT guard's cached user). For non-creation endpoints (e.g. `GET /api/me`) explicitly force the status: `Resource::make($x)->response()->setStatusCode(Response::HTTP_OK)`.

**Domain HTTP errors**: thrown as small exceptions under `App\Exceptions` that extend `RuntimeException` and implement `public function render(): JsonResponse` returning `{ message: ... }` at the right status (e.g. `InvalidCredentialsException` 401, `AlreadyInQueueException` 409, `NotInQueueException` 404). No global handler mapping needed — `bootstrap/app.php` only sets `shouldRenderJsonWhen(api/*)`. Services throw these directly; controllers stay thin.

**Why**: keeps clients' contract stable and matches task contracts.
**How to apply**: reuse this guard binding, the `{data:...}` envelope, the AuthTokenResource shape, and the explicit-status pattern for any future read endpoints that return a freshly-loaded model.

Related: [[project-stack]], [[project-data-model]].
