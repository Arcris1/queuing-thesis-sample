import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// Route table for the staff/admin dashboard. Feature views (live queue board,
// queue controls, analytics) are added by tasks 036–038.
//
// `meta.requiresAuth` marks protected routes; `meta.guestOnly` marks routes that
// authenticated users should be bounced away from (e.g. /login).
const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/HomeView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/board',
    name: 'board',
    component: () => import('@/views/QueueBoardView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/analytics',
    name: 'analytics',
    component: () => import('@/views/AnalyticsView.vue'),
    meta: { requiresAuth: true },
  },
  // --- Public "Now Serving" wall displays (UNAUTHENTICATED kiosks) ---
  // Deliberately no `meta.requiresAuth` / `meta.guestOnly`: the guard below only
  // acts on those flags, so these routes fall through to `return true` and are
  // never redirected to /login nor bounced by the staff role gate. A kiosk has
  // no login; the underlying GET /api/queue/current is a public endpoint.
  {
    path: '/display',
    name: 'display-index',
    component: () => import('@/views/DisplayIndexView.vue'),
  },
  {
    path: '/display/:officeId',
    name: 'display-office',
    component: () => import('@/views/QueueDisplayView.vue'),
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Hydrate the session once, lazily, on the first navigation. This lets a page
// refresh restore the user from the persisted token before the guard decides.
let sessionHydrated = false

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!sessionHydrated) {
    sessionHydrated = true
    await auth.fetchMe()
  }

  // Protected route: require an authenticated staff session.
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Guest-only route (login): send authenticated users to the dashboard.
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'home' }
  }

  return true
})

export default router
