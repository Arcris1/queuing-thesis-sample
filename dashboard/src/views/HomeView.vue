<script setup lang="ts">
import { computed } from 'vue'
import AppTopBar from '@/components/AppTopBar.vue'
import { useAuthStore } from '@/stores/auth'

// Protected landing view. Replaced/extended by the live queue board (task 036).
const auth = useAuthStore()
const firstName = computed(() => auth.user?.name?.trim().split(/\s+/)[0] ?? 'there')
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppTopBar />

    <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <div class="mb-8">
        <span class="rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700">
          Smart Queue — Staff Dashboard
        </span>
        <h1 class="mt-4 text-3xl font-bold text-slate-900">Welcome back, {{ firstName }}</h1>
        <p class="mt-2 max-w-prose text-slate-600">
          You are signed in. The live queue board, queue controls, and analytics land in
          tasks 036–038.
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="card in cards"
          :key="card.title"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
          <h2 class="text-sm font-semibold text-slate-900">{{ card.title }}</h2>
          <p class="mt-1 text-sm text-slate-500">{{ card.body }}</p>
        </article>
      </div>
    </main>
  </div>
</template>

<script lang="ts">
const cards = [
  { title: 'Live queue board', body: 'Real-time tickets and window assignments. (Task 036)' },
  { title: 'Queue controls', body: 'Call next, skip, recall, and manage windows. (Task 037)' },
  { title: 'Analytics', body: 'Wait-time trends and presence insights. (Task 038)' },
]
</script>
