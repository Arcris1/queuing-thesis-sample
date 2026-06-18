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
