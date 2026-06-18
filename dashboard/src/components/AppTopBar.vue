<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const loggingOut = ref(false)

const navLinks = [
  { name: 'board', label: 'Queue board' },
  { name: 'analytics', label: 'Analytics' },
] as const

const user = computed(() => auth.user)
const initials = computed(() => {
  const name = user.value?.name?.trim()
  if (!name) return '?'
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
})
const roleLabel = computed(() => {
  const role = user.value?.role
  if (!role) return ''
  return role.charAt(0).toUpperCase() + role.slice(1)
})

async function handleLogout() {
  if (loggingOut.value) return
  loggingOut.value = true
  try {
    await auth.logout()
    await router.replace({ name: 'login' })
  } finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
      <div class="flex items-center gap-5">
        <RouterLink
          :to="{ name: 'board' }"
          class="flex items-center gap-2.5 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
        >
          <span
            class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white"
            aria-hidden="true"
          >
            SQ
          </span>
          <span class="text-sm font-semibold text-slate-900 sm:text-base">Smart Queue</span>
        </RouterLink>

        <nav aria-label="Primary" class="hidden items-center gap-1 sm:flex">
          <RouterLink
            v-for="link in navLinks"
            :key="link.name"
            :to="{ name: link.name }"
            class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
            active-class="bg-brand-50 text-brand-700 hover:bg-brand-50 hover:text-brand-700"
          >
            {{ link.label }}
          </RouterLink>
        </nav>
      </div>

      <div class="flex items-center gap-3">
        <div class="flex items-center gap-2.5">
          <span
            class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700"
            aria-hidden="true"
          >
            {{ initials }}
          </span>
          <div class="hidden text-right leading-tight sm:block">
            <p class="text-sm font-medium text-slate-900">{{ user?.name }}</p>
            <p class="text-xs text-slate-500">{{ roleLabel }}</p>
          </div>
        </div>

        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 active:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="loggingOut"
          @click="handleLogout"
        >
          <svg
            v-if="loggingOut"
            class="h-4 w-4 animate-spin text-slate-500"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
          </svg>
          <span>{{ loggingOut ? 'Signing out…' : 'Logout' }}</span>
        </button>
      </div>
    </div>

    <!-- Mobile nav row -->
    <nav
      aria-label="Primary"
      class="flex items-center gap-1 border-t border-slate-100 px-4 py-1.5 sm:hidden"
    >
      <RouterLink
        v-for="link in navLinks"
        :key="link.name"
        :to="{ name: link.name }"
        class="flex-1 rounded-lg px-3 py-2 text-center text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
        active-class="bg-brand-50 text-brand-700 hover:bg-brand-50 hover:text-brand-700"
      >
        {{ link.label }}
      </RouterLink>
    </nav>
  </header>
</template>
