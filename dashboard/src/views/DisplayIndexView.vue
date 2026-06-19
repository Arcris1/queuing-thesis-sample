<script setup lang="ts">
// Public index for the "Now Serving" wall displays. Lists every office as a
// large card/button so staff can pick which monitor to launch on a given screen.
// PUBLIC route — no auth (see router/index.ts: no meta.requiresAuth).
import { computed } from 'vue'
import { useQueueDisplay } from '@/composables/useQueueDisplay'

// Poll slowly here — the index only needs to know which offices exist and a
// rough waiting tally; the per-office board polls fast.
const { offices, loading, error, hasData, fetchCurrent } = useQueueDisplay({ pollMs: 15000 })

const sortedOffices = computed(() =>
  [...offices.value].sort((a, b) => a.office.name.localeCompare(b.office.name)),
)

function totalWaiting(groups: { waiting_count: number }[]): number {
  return groups.reduce((sum, g) => sum + (g.waiting_count ?? 0), 0)
}
</script>

<template>
  <main class="min-h-screen bg-slate-950 px-4 py-10 text-slate-100 sm:px-8 sm:py-14">
    <div class="mx-auto max-w-5xl">
      <header class="mb-10 text-center">
        <span
          class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white shadow-lg shadow-brand-900/40"
          aria-hidden="true"
        >
          SQ
        </span>
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Now Serving</h1>
        <p class="mt-2 text-base text-slate-400">
          Pick an office to open its public queue display.
        </p>
      </header>

      <!-- Loading skeleton -->
      <div
        v-if="loading && !hasData"
        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
        aria-hidden="true"
      >
        <div
          v-for="n in 4"
          :key="n"
          class="h-32 animate-pulse rounded-2xl border border-slate-800 bg-slate-900"
        />
      </div>

      <!-- Error (no data yet) -->
      <div
        v-else-if="error && !hasData"
        class="mx-auto max-w-md rounded-2xl border border-red-900/60 bg-red-950/40 p-8 text-center"
        role="alert"
      >
        <p class="text-lg font-semibold text-red-200">Couldn’t load offices</p>
        <p class="mt-1 text-sm text-red-300/80">{{ error }}</p>
        <button
          type="button"
          class="mt-5 inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-400"
          @click="fetchCurrent"
        >
          Try again
        </button>
      </div>

      <!-- Empty -->
      <div
        v-else-if="hasData && sortedOffices.length === 0"
        class="mx-auto max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-10 text-center"
      >
        <p class="text-lg font-semibold text-slate-200">No offices available</p>
        <p class="mt-1 text-sm text-slate-400">There are no queue displays to show right now.</p>
      </div>

      <!-- Office cards -->
      <ul v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <li v-for="entry in sortedOffices" :key="entry.office.id">
          <RouterLink
            :to="{ name: 'display-office', params: { officeId: String(entry.office.id) } }"
            class="group flex h-full flex-col justify-between rounded-2xl border border-slate-800 bg-slate-900 p-6 transition hover:border-brand-500 hover:bg-slate-900/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
          >
            <div class="flex items-start justify-between gap-3">
              <h2 class="text-xl font-semibold text-white">{{ entry.office.name }}</h2>
              <svg
                class="mt-1 h-5 w-5 shrink-0 text-slate-500 transition group-hover:translate-x-0.5 group-hover:text-brand-400"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
              >
                <path
                  fill-rule="evenodd"
                  d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
            <p class="mt-4 text-sm text-slate-400">
              {{ entry.queue_groups.length }}
              {{ entry.queue_groups.length === 1 ? 'queue' : 'queues' }}
              ·
              {{ totalWaiting(entry.queue_groups) }} waiting
            </p>
          </RouterLink>
        </li>
      </ul>
    </div>
  </main>
</template>
