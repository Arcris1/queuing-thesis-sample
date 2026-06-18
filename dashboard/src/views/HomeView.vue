<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import AppTopBar from '@/components/AppTopBar.vue'
import { useAuthStore } from '@/stores/auth'

// Protected landing hub. Links into the live queue board (036/037) and analytics (038).
const auth = useAuthStore()
const firstName = computed(() => auth.user?.name?.trim().split(/\s+/)[0] ?? 'there')

const cards = [
  {
    to: 'board',
    title: 'Live queue board',
    body: 'Real-time tickets, presence states, and window controls.',
    cta: 'Open board',
  },
  {
    to: 'analytics',
    title: 'Analytics',
    body: 'Wait-time trends, peak hours, and window utilization.',
    cta: 'View analytics',
  },
] as const
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppTopBar />

    <main class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <div class="mb-8">
        <span class="rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700">
          Smart Queue — Staff Dashboard
        </span>
        <h1 class="mt-4 text-3xl font-bold text-slate-900">Welcome back, {{ firstName }}</h1>
        <p class="mt-2 max-w-prose text-slate-600">
          Operate the live queue, control windows, and review performance from here.
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:max-w-3xl">
        <RouterLink
          v-for="card in cards"
          :key="card.title"
          :to="{ name: card.to }"
          class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-brand-300 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
        >
          <h2 class="text-base font-semibold text-slate-900">{{ card.title }}</h2>
          <p class="mt-1 flex-1 text-sm text-slate-500">{{ card.body }}</p>
          <span
            class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 group-hover:gap-2 transition-[gap]"
          >
            {{ card.cta }}
            <span aria-hidden="true">→</span>
          </span>
        </RouterLink>
      </div>
    </main>
  </div>
</template>
