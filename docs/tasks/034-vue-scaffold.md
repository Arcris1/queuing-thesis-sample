---
id: 034
title: Vue scaffold + Tailwind/Pinia/Router
status: Todo
owner: vue-frontend-designer
plan_ref: "Phase 8 / §4"
depends_on: [1]
---

## Objective

Create the staff/admin web dashboard project (Vue 3 + Tailwind + Pinia + Vue Router) with the API
client, auth store wiring, and base layout all dashboard views build on.

## Context

§4/§12: the staff/admin dashboard shows the live queue, presence states, and analytics. CLAUDE.md/
vue-frontend-designer mandates Vue 3 Composition API `<script setup>`, TypeScript when supported,
Pinia, Vue Router, Tailwind utility-first, WCAG AA, and full loading/empty/error/success states.

## Scope

**In scope**
- `npm create vue@latest` (TS, Pinia, Router, ESLint) + Tailwind setup with design tokens
  (colors/spacing/typography) and dark mode strategy.
- API client (axios/fetch wrapper) with base URL config + JWT auth interceptor.
- App shell/layout (sidebar/topbar), route structure, and a Pinia auth store skeleton.
- Base UI components (button, card, table, badge, loading/empty/error states).

**Out of scope**
- Login view (task 035) and feature views (036–038).

## Implementation notes

Centralize Tailwind theme tokens in `tailwind.config`. Make the API base URL env-driven. Build the
auth store so task 035 can populate it; protect routes with a navigation guard. Provide reusable
status-badge and table components since the live board and analytics reuse them.

## API / contract (if applicable)

- Consumes `GET /api/health` to validate connectivity.

## Acceptance criteria

- [ ] App builds and runs (`npm run dev`) with routing + Pinia
- [ ] Tailwind configured with design tokens + dark mode
- [ ] API client with env base URL + JWT interceptor
- [ ] App shell/layout and protected route structure in place
- [ ] Reusable base components incl. loading/empty/error states
- [ ] `npm run lint` and `npm run build` pass

## Verification

```
npm install
npm run lint && npm run build
npm run dev    # shell renders, routes resolve, /api/health reachable
```
