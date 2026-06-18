<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import AppTopBar from '@/components/AppTopBar.vue'
import OfficeSelector from '@/components/queue/OfficeSelector.vue'
import BoardSummary from '@/components/queue/BoardSummary.vue'
import QueueGroupColumn from '@/components/queue/QueueGroupColumn.vue'
import WindowControlPanel from '@/components/queue/WindowControlPanel.vue'
import { useQueueBoardStore } from '@/stores/queueBoard'
import { useAuthStore } from '@/stores/auth'

// Live queue board (task 036) + queue controls (task 037).
//
// Realtime: the store drives refreshes through the polling RealtimeDriver
// (src/lib/realtime.ts, ~5s). A Reverb/Echo driver is wired behind the same seam
// and selected via VITE_REALTIME_DRIVER=echo — no view changes needed.
const store = useQueueBoardStore()
const auth = useAuthStore()

const {
  offices,
  officesLoading,
  officesError,
  selectedOfficeId,
  selectedOffice,
  queueGroups,
  windows,
  totals,
  board,
  boardLoading,
  refreshing,
  boardError,
  lastUpdatedAt,
} = storeToRefs(store)

// Only admins may mutate window↔group attachments (§5.4); staff can still operate
// the queue (call/serve/skip/recall).
const canManageWindows = computed(() => auth.user?.role === 'admin')

const lastUpdatedLabel = computed(() => {
  if (lastUpdatedAt.value === null) return null
  return new Date(lastUpdatedAt.value).toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
})

function onSelectOffice(id: number) {
  void store.selectOffice(id)
}

onMounted(async () => {
  await store.fetchOffices()
  if (selectedOfficeId.value !== null) {
    await store.fetchBoard(false)
    store.startRealtime()
  }
})

// If offices load and one becomes selected after mount, kick off the board.
watch(selectedOfficeId, (id, prev) => {
  if (id !== null && prev === null) {
    void store.fetchBoard(false)
    store.startRealtime()
  }
})

onBeforeUnmount(() => store.dispose())
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppTopBar />

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
      <!-- Header row: title + office selector + live indicator -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Live queue board</h1>
          <p class="mt-1 text-sm text-slate-500">
            {{ selectedOffice?.name ?? 'Select an office' }} · real-time tickets and windows
          </p>
        </div>

        <div class="flex items-end gap-3">
          <div class="w-56 max-w-[60vw]">
            <OfficeSelector
              :offices="offices"
              :model-value="selectedOfficeId"
              :loading="officesLoading"
              @update:model-value="onSelectOffice"
            />
          </div>
        </div>
      </div>

      <!-- Live status strip -->
      <div class="mt-3 flex items-center gap-2 text-xs text-slate-500" aria-live="polite">
        <span class="relative flex h-2 w-2" aria-hidden="true">
          <span
            class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"
            :class="refreshing ? 'animate-ping' : ''"
          />
          <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
        </span>
        <span>
          Live
          <template v-if="lastUpdatedLabel"> · updated {{ lastUpdatedLabel }}</template>
          <template v-if="refreshing"> · refreshing…</template>
        </span>
      </div>

      <!-- Offices error -->
      <div
        v-if="officesError"
        class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"
        role="alert"
      >
        <p class="font-semibold">Could not load offices</p>
        <p class="mt-0.5">{{ officesError }}</p>
        <button
          type="button"
          class="mt-3 rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-sm font-medium text-rose-700 transition hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600"
          @click="store.fetchOffices()"
        >
          Retry
        </button>
      </div>

      <!-- No offices -->
      <div
        v-else-if="!officesLoading && offices.length === 0"
        class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
      >
        <p class="text-sm font-medium text-slate-600">No offices configured</p>
        <p class="mt-1 text-sm text-slate-400">Add an office in the backend to start a queue.</p>
      </div>

      <!-- Board loading skeleton (first load) -->
      <div v-else-if="boardLoading && !board" class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="n in 3"
          :key="n"
          class="h-72 animate-pulse rounded-2xl border border-slate-200 bg-white"
          aria-hidden="true"
        />
        <span class="sr-only">Loading queue board…</span>
      </div>

      <!-- Board error (no cached board) -->
      <div
        v-else-if="boardError && !board"
        class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"
        role="alert"
      >
        <p class="font-semibold">Could not load the queue board</p>
        <p class="mt-0.5">{{ boardError }}</p>
        <button
          type="button"
          class="mt-3 rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-sm font-medium text-rose-700 transition hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600"
          @click="store.fetchBoard(false)"
        >
          Retry
        </button>
      </div>

      <!-- Board content -->
      <template v-else-if="board">
        <!-- Soft banner when a background refresh failed but we still have data -->
        <div
          v-if="boardError"
          class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
          role="status"
        >
          Showing the last known data — {{ boardError }}
        </div>

        <!-- Summary tiles -->
        <div class="mt-6">
          <BoardSummary
            :waiting="totals.waiting"
            :active="totals.active"
            :away="totals.away"
            :offline="totals.offline"
          />
        </div>

        <!-- Windows (controls) -->
        <section class="mt-8" aria-labelledby="windows-heading">
          <h2 id="windows-heading" class="mb-3 text-sm font-semibold text-slate-700">
            Windows ({{ windows.length }})
          </h2>
          <div v-if="windows.length > 0" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <WindowControlPanel
              v-for="win in windows"
              :key="win.id"
              :window="win"
              :all-groups="queueGroups"
              :can-manage="canManageWindows"
            />
          </div>
          <div
            v-else
            class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500"
          >
            No windows configured for this office.
          </div>
        </section>

        <!-- Queue groups -->
        <section class="mt-8" aria-labelledby="groups-heading">
          <h2 id="groups-heading" class="mb-3 text-sm font-semibold text-slate-700">
            Queue groups ({{ queueGroups.length }})
          </h2>
          <div v-if="queueGroups.length > 0" class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <QueueGroupColumn v-for="group in queueGroups" :key="group.id" :group="group" />
          </div>
          <div
            v-else
            class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500"
          >
            No queue groups in this office yet.
          </div>
        </section>
      </template>
    </main>
  </div>
</template>
