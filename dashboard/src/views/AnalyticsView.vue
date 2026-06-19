<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import AppTopBar from '@/components/AppTopBar.vue'
import OfficeSelector from '@/components/queue/OfficeSelector.vue'
import KpiCard from '@/components/analytics/KpiCard.vue'
import PeakHoursChart from '@/components/analytics/PeakHoursChart.vue'
import BreakdownBars, { type BarItem } from '@/components/analytics/BreakdownBars.vue'
import { useAnalyticsStore } from '@/stores/analytics'
import { useQueueBoardStore } from '@/stores/queueBoard'

// Analytics dashboard (task 038). Office + date-range filters drive the API
// query; filter changes are debounced so dragging a date doesn't spam requests.
const analytics = useAnalyticsStore()
const queueBoard = useQueueBoardStore() // reuse the offices list

const { summary, loading, error, filters, isEmpty } = storeToRefs(analytics)
const { offices, officesLoading } = storeToRefs(queueBoard)

let debounceTimer: ReturnType<typeof setTimeout> | null = null

function scheduleFetch() {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => void analytics.fetchAnalytics(), 300)
}

function onSelectOffice(id: number) {
  analytics.setFilters({ office_id: id })
  scheduleFetch()
}

function onFromChange(event: Event) {
  analytics.setFilters({ from: (event.target as HTMLInputElement).value || null })
  scheduleFetch()
}

function onToChange(event: Event) {
  analytics.setFilters({ to: (event.target as HTMLInputElement).value || null })
  scheduleFetch()
}

// --- Derived view models ---
const fmt = (n: number) => (Number.isInteger(n) ? n.toString() : n.toFixed(1))

const serviceBars = computed<BarItem[]>(() =>
  (summary.value?.by_service ?? []).map((s) => ({
    key: s.service_id,
    label: s.name,
    value: s.served,
    display: `${s.served} served · ${fmt(s.avg_service_minutes)} min avg`,
  })),
)

const groupBars = computed<BarItem[]>(() =>
  (summary.value?.by_queue_group ?? []).map((g) => ({
    key: g.queue_group_id,
    label: g.name,
    value: g.served,
    display: `${g.served} served · ${fmt(g.avg_service_minutes)} min avg`,
  })),
)

const utilizationBars = computed<BarItem[]>(() => {
  const byWindowName = new Map((summary.value?.by_window ?? []).map((w) => [w.window_id, w.name]))
  return (summary.value?.window_utilization ?? []).map((u) => ({
    key: u.window_id,
    label: u.window_name ?? byWindowName.get(u.window_id) ?? `Window ${u.window_id}`,
    value: u.busy_minutes,
    display: `${fmt(u.busy_minutes)} min busy`,
  }))
})

const rangeLabel = computed(() => {
  if (filters.value.from && filters.value.to) return `${filters.value.from} → ${filters.value.to}`
  return 'Selected range'
})

onMounted(async () => {
  // Ensure offices are loaded (reused store may already have them).
  if (offices.value.length === 0) await queueBoard.fetchOffices()
  if (filters.value.office_id === null && offices.value.length > 0) {
    analytics.setFilters({ office_id: offices.value[0].id })
  }
  if (filters.value.office_id !== null) void analytics.fetchAnalytics()
})

// If offices arrive after mount, default-select the first and fetch.
watch(offices, (list) => {
  if (filters.value.office_id === null && list.length > 0) {
    analytics.setFilters({ office_id: list[0].id })
    scheduleFetch()
  }
})

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
})
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppTopBar />

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
      <div class="flex flex-col gap-4">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Analytics</h1>
          <p class="mt-1 text-sm text-slate-500">Queue performance · {{ rangeLabel }}</p>
        </div>

        <!-- Filters -->
        <div class="grid gap-3 sm:grid-cols-3 lg:max-w-2xl">
          <OfficeSelector
            :offices="offices"
            :model-value="filters.office_id"
            :loading="officesLoading"
            @update:model-value="onSelectOffice"
          />
          <div class="flex flex-col gap-1.5">
            <label for="from-date" class="text-xs font-medium text-slate-500">From</label>
            <input
              id="from-date"
              type="date"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
              :value="filters.from ?? ''"
              :max="filters.to ?? undefined"
              @change="onFromChange"
            />
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="to-date" class="text-xs font-medium text-slate-500">To</label>
            <input
              id="to-date"
              type="date"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
              :value="filters.to ?? ''"
              :min="filters.from ?? undefined"
              @change="onToChange"
            />
          </div>
        </div>
      </div>

      <!-- Error -->
      <div
        v-if="error"
        class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"
        role="alert"
      >
        <p class="font-semibold">Could not load analytics</p>
        <p class="mt-0.5">{{ error }}</p>
        <button
          type="button"
          class="mt-3 rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-sm font-medium text-rose-700 transition hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600"
          @click="analytics.fetchAnalytics()"
        >
          Retry
        </button>
      </div>

      <!-- Loading skeleton -->
      <div v-else-if="loading && !summary" class="mt-6 space-y-6" aria-busy="true">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="n in 4"
            :key="n"
            class="h-24 animate-pulse rounded-2xl border border-slate-200 bg-white"
          />
        </div>
        <div class="h-72 animate-pulse rounded-2xl border border-slate-200 bg-white" />
        <span class="sr-only">Loading analytics…</span>
      </div>

      <!-- Empty -->
      <div
        v-else-if="summary && isEmpty"
        class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
      >
        <p class="text-sm font-medium text-slate-600">No activity in this range</p>
        <p class="mt-1 text-sm text-slate-400">Try a wider date range or another office.</p>
      </div>

      <!-- Content -->
      <template v-else-if="summary">
        <div
          v-if="loading"
          class="mt-4 text-xs text-slate-400"
          role="status"
          aria-live="polite"
        >
          Updating…
        </div>

        <!-- KPI cards -->
        <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Key metrics">
          <KpiCard
            label="Avg wait"
            :value="fmt(summary.avg_wait_minutes)"
            unit="min"
            accent="brand"
          />
          <KpiCard
            label="Avg service"
            :value="fmt(summary.avg_service_minutes)"
            unit="min"
            accent="slate"
          />
          <KpiCard label="Served" :value="summary.served" accent="emerald" />
          <KpiCard label="Missed / skipped" :value="summary.missed" accent="rose" />
        </section>

        <!-- Peak hours -->
        <section
          class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
          aria-labelledby="peak-heading"
        >
          <h2 id="peak-heading" class="text-sm font-semibold text-slate-700">Peak hours</h2>
          <p class="mt-0.5 text-xs text-slate-400">Tickets served by hour of day</p>
          <div class="mt-4">
            <PeakHoursChart :peak-hours="summary.peak_hours" />
          </div>
        </section>

        <!-- Breakdowns -->
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
          <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            aria-labelledby="service-heading"
          >
            <h2 id="service-heading" class="text-sm font-semibold text-slate-700">By service</h2>
            <div class="mt-4">
              <BreakdownBars :items="serviceBars" empty-label="No services served in this range." />
            </div>
          </section>

          <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            aria-labelledby="group-heading"
          >
            <h2 id="group-heading" class="text-sm font-semibold text-slate-700">By queue group</h2>
            <div class="mt-4">
              <BreakdownBars
                :items="groupBars"
                bar-class="bg-indigo-500"
                empty-label="No queue-group activity in this range."
              />
            </div>
          </section>

          <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2"
            aria-labelledby="util-heading"
          >
            <h2 id="util-heading" class="text-sm font-semibold text-slate-700">
              Window utilization
            </h2>
            <p class="mt-0.5 text-xs text-slate-400">Busy minutes per window</p>
            <div class="mt-4">
              <BreakdownBars
                :items="utilizationBars"
                bar-class="bg-emerald-500"
                empty-label="No window activity in this range."
              />
            </div>
          </section>
        </div>
      </template>
    </main>
  </div>
</template>
