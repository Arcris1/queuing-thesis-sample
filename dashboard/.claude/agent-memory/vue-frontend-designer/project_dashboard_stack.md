---
name: project-dashboard-stack
description: Staff/admin dashboard tech stack, Tailwind v4 token setup, build command, and TS config gotchas
metadata:
  type: project
---

The staff/admin web dashboard lives at `dashboard/` (separate from `backend/`).

Stack: Vue 3.5 + TypeScript, Tailwind CSS v4 (`@tailwindcss/vite`), Pinia 3, Vue Router 5, axios 1.18.

**Why:** Per CLAUDE.md, the dashboard is a pure REST/WebSocket client of the Laravel API with zero business logic.

**How to apply:**
- Build/type-check: `npm run build` (runs `vue-tsc -b && vite build`). There is **no** `npm run dev`-less test script yet and no `lint` script despite CLAUDE.md mentioning them — only `dev`, `build`, `preview` exist in package.json.
- Tailwind v4 uses CSS-based theme config in `src/style.css` via `@theme { --color-brand-50 ... --color-brand-700 }`. Brand tokens are blue (brand-600 = #2563eb). Use utilities like `bg-brand-600`, `text-brand-700`. No `tailwind.config.js`.
- Path alias `@/*` -> `src/*`.
- `tsconfig.node.json` enables `verbatimModuleSyntax` (vite config only), but the app tsconfig (`tsconfig.app.json`, extends `@vue/tsconfig/tsconfig.dom.json`) does not. Type-only imports still recommended for safety; `import { x, type Y }` mixed form builds fine.
- `noUnusedLocals`/`noUnusedParameters` are on — no dead vars or the build fails.
- Auth/role types live in `src/types/auth.ts`. Reusable shell components in `src/components/` (e.g. `AppTopBar.vue`).
