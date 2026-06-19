<script setup lang="ts">
// Full-viewport public "Now Serving" board for ONE office — the wall-mounted
// kiosk monitor in the waiting area. PUBLIC route: no auth (router has no
// meta.requiresAuth, so the guard never redirects it to /login).
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useQueueDisplay } from '@/composables/useQueueDisplay'
import NowServingPanel from '@/components/queue/NowServingPanel.vue'

const route = useRoute()

const officeId = computed(() => {
  const raw = route.params.officeId
  return Array.isArray(raw) ? raw[0] : raw
})

const { loading, error, hasData, lastUpdatedAt, officeById, fetchCurrent } = useQueueDisplay({
  pollMs: 4000,
})

const office = computed(() => officeById(officeId.value))
const groups = computed(() => office.value?.queue_groups ?? [])

// "office not found" only after we have a successful snapshot (otherwise we
// might just be mid-first-load).
const officeMissing = computed(() => hasData.value && office.value === null)

// Responsive grid density: 1 group = single big panel, 2 = side by side,
// 3+ = up to three columns. Drives the Tailwind grid class below.
const gridClass = computed(() => {
  const n = groups.value.length
  if (n <= 1) return 'grid-cols-1'
  if (n === 2) return 'grid-cols-1 sm:grid-cols-2'
  return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3'
})

// --- Live clock (HH:MM:SS) ---
const now = ref(new Date())
let clockTimer: ReturnType<typeof setInterval> | null = null

const clock = computed(() =>
  now.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }),
)

// --- "updated Xs ago" ticker ---
const updatedAgo = computed(() => {
  if (lastUpdatedAt.value === null) return null
  const secs = Math.max(0, Math.round((now.value.getTime() - lastUpdatedAt.value) / 1000))
  if (secs < 2) return 'just now'
  return `${secs}s ago`
})

// True when the last poll failed but we still have a previous snapshot to show.
const stale = computed(() => error.value !== null && hasData.value)

// --- Fullscreen toggle (optional, progressive) ---
const isFullscreen = ref(false)
function syncFullscreen() {
  isFullscreen.value = document.fullscreenElement !== null
}
async function toggleFullscreen() {
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen()
    } else {
      await document.documentElement.requestFullscreen()
    }
  } catch {
    // Fullscreen can be blocked (no user gesture / unsupported) — ignore quietly.
  }
}

onMounted(() => {
  clockTimer = setInterval(() => (now.value = new Date()), 1000)
  document.addEventListener('fullscreenchange', syncFullscreen)
  syncFullscreen()
})

onBeforeUnmount(() => {
  if (clockTimer) clearInterval(clockTimer)
  document.removeEventListener('fullscreenchange', syncFullscreen)
})
</script>

<template>
  <!-- `cursor-none` auto-hides the pointer over the board area for a clean TV look;
       interactive controls re-enable it so they stay usable. -->
  <main class="flex min-h-screen flex-col bg-slate-950 text-slate-100 cursor-none">
    <!-- ============ Header ============ -->
    <header class="flex items-center justify-between gap-4 px-6 py-5 sm:px-10 sm:py-6">
      <div class="min-w-0">
        <h1 class="truncate text-3xl font-bold tracking-tight sm:text-5xl">
          {{ office?.office.name ?? 'Queue Display' }}
        </h1>
        <div class="mt-1.5 flex items-center gap-3 text-sm text-slate-400 sm:text-base">
          <span class="inline-flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5" aria-hidden="true">
              <span
                v-if="!stale"
                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
              />
              <span
                class="relative inline-flex h-2.5 w-2.5 rounded-full"
                :class="stale ? 'bg-amber-400' : 'bg-emerald-400'"
              />
            </span>
            <span :class="stale ? 'text-amber-300' : ''">
              {{ stale ? 'Reconnecting' : 'Live' }}
            </span>
          </span>
          <span v-if="updatedAgo" class="text-slate-500">· updated {{ updatedAgo }}</span>
        </div>
      </div>

      <div class="flex shrink-0 items-center gap-4 sm:gap-6">
        <time
          class="font-mono text-3xl font-bold tabular-nums text-white sm:text-5xl"
          :datetime="now.toISOString()"
          aria-label="Current time"
        >
          {{ clock }}
        </time>
        <button
          type="button"
          class="cursor-pointer rounded-lg border border-slate-700 bg-slate-900 p-2.5 text-slate-300 transition hover:border-slate-500 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
          :aria-label="isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'"
          :aria-pressed="isFullscreen"
          @click="toggleFullscreen"
        >
          <svg v-if="!isFullscreen" class="h-5 w-5 sm:h-6 sm:w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" />
          </svg>
          <svg v-else class="h-5 w-5 sm:h-6 sm:w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5" />
          </svg>
        </button>
      </div>
    </header>

    <!-- ============ Body ============ -->
    <div class="flex flex-1 flex-col px-6 pb-4 sm:px-10">
      <!-- Office not found -->
      <div
        v-if="officeMissing"
        class="flex flex-1 flex-col items-center justify-center text-center"
      >
        <div class="rounded-3xl border border-slate-800 bg-slate-900 p-12 cursor-auto">
          <p class="text-5xl">🔎</p>
          <h2 class="mt-4 text-2xl font-bold text-white">Office not found</h2>
          <p class="mt-2 text-slate-400">We couldn’t find a display for this office.</p>
          <RouterLink
            :to="{ name: 'display-index' }"
            class="mt-6 inline-flex items-center justify-center rounded-lg bg-brand-600 px-5 py-3 text-base font-semibold text-white transition hover:bg-brand-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-400"
          >
            ← Back to all displays
          </RouterLink>
        </div>
      </div>

      <!-- Loading skeleton (first load only) -->
      <div
        v-else-if="loading && !hasData"
        class="grid flex-1 grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
        aria-hidden="true"
      >
        <div
          v-for="n in 3"
          :key="n"
          class="animate-pulse rounded-3xl border border-slate-800 bg-slate-900"
        />
      </div>

      <!-- Error with no data at all -->
      <div
        v-else-if="error && !hasData"
        class="flex flex-1 flex-col items-center justify-center text-center"
      >
        <div class="rounded-3xl border border-red-900/60 bg-red-950/40 p-12 cursor-auto">
          <h2 class="text-2xl font-bold text-red-200">Can’t reach the queue</h2>
          <p class="mt-2 text-red-300/80">{{ error }}</p>
          <button
            type="button"
            class="mt-6 inline-flex items-center justify-center rounded-lg bg-red-600 px-5 py-3 text-base font-semibold text-white transition hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-400"
            @click="fetchCurrent"
          >
            Retry now
          </button>
        </div>
      </div>

      <!-- Empty: office exists but has no queue groups -->
      <div
        v-else-if="groups.length === 0"
        class="flex flex-1 flex-col items-center justify-center text-center"
      >
        <p class="text-7xl">☕</p>
        <h2 class="mt-6 text-3xl font-bold text-slate-200">No active queues</h2>
        <p class="mt-2 text-lg text-slate-400">This office has no services queued right now.</p>
      </div>

      <!-- The board -->
      <div v-else class="grid flex-1 content-center gap-5 sm:gap-6" :class="gridClass">
        <NowServingPanel v-for="g in groups" :key="g.id" :group="g" />
      </div>
    </div>

    <!-- ============ Footer ============ -->
    <footer class="px-6 pb-5 pt-2 text-center sm:px-10">
      <p class="text-sm text-slate-500 sm:text-base">
        Watch your place live on the
        <span class="font-semibold text-slate-300">Smart Queue</span> app.
      </p>
    </footer>
  </main>
</template>
