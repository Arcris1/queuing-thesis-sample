---
name: dashboard-stack
description: Smart Queue Vue dashboard conventions — stack, API envelope, router auth-guard model, public-route pattern
metadata:
  type: project
---

The `dashboard/` app is the staff/admin web client for the Smart Queue system.

**Stack:** Vue 3 `<script setup lang="ts">`, Tailwind v4 (`@import "tailwindcss"` + `@theme` brand tokens `brand-50/100/500/600/700` in `src/style.css`), Pinia stores, Vue Router, axios. Build gate is `npm run build` (vue-tsc `-b` then Vite). No unit-test runner configured — the type-check IS the gate.

**API layer:** `src/lib/api.ts` exports an axios instance, baseURL `/api` (dev-proxied to `http://127.0.0.1:8000`). Responses use the `{ data: ... }` envelope — read `response.data.data`. Request interceptor adds a Bearer token only if `localStorage.sq_token` exists, so **public/unauthenticated API calls work fine through the same instance**.

**Router auth model (`src/router/index.ts`):** the single `beforeEach` guard acts ONLY on `meta.requiresAuth` (redirect to /login if not authed) and `meta.guestOnly` (bounce authed users off /login). A route with **neither meta flag is fully public** — it falls through to `return true`. The guard also calls `auth.fetchMe()` once on first navigation (harmless no-op without a token). To add a public route, just omit both meta flags. This is how the public kiosk display routes (`/display`, `/display/:officeId`) stay unauthenticated.

**Patterns:** stores use `loading` (first load → skeleton) vs `refreshing` (background poll) split, keep last-known data on transient poll failure, `lastUpdatedAt` epoch ms. Composables clean up timers via `onScopeDispose`. Toasts via `useToasts()` singleton composable. Style reference for forms: `src/views/LoginView.vue`.
