<script setup lang="ts">
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  BarElement,
  CategoryScale,
  LinearScale,
  type ChartData,
  type ChartOptions,
} from 'chart.js'
import type { PeakHour } from '@/types/analytics'

// Peak-hours bar chart (Chart.js via vue-chartjs). Chart.js is registered locally
// (tree-shaken) so the bundle only pulls the bar + scales we use. A visually
// hidden data table mirrors the chart for screen readers (charts aren't a11y-safe
// on their own).
ChartJS.register(Title, Tooltip, BarElement, CategoryScale, LinearScale)

const props = defineProps<{
  peakHours: PeakHour[]
}>()

const sorted = computed(() => [...(props.peakHours ?? [])].sort((a, b) => a.hour - b.hour))

function hourLabel(hour: number): string {
  const h = ((hour % 24) + 24) % 24
  const suffix = h < 12 ? 'AM' : 'PM'
  const display = h % 12 === 0 ? 12 : h % 12
  return `${display}${suffix}`
}

const chartData = computed<ChartData<'bar'>>(() => ({
  labels: sorted.value.map((p) => hourLabel(p.hour)),
  datasets: [
    {
      label: 'Tickets served',
      data: sorted.value.map((p) => p.served),
      backgroundColor: '#2563eb',
      hoverBackgroundColor: '#1d4ed8',
      borderRadius: 6,
      maxBarThickness: 36,
    },
  ],
}))

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (ctx) => ` ${ctx.parsed.y} ticket${ctx.parsed.y === 1 ? '' : 's'}`,
      },
    },
  },
  scales: {
    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 11 } } },
    y: {
      beginAtZero: true,
      ticks: { precision: 0, color: '#64748b', font: { size: 11 } },
      grid: { color: '#f1f5f9' },
    },
  },
}))

const hasData = computed(() => sorted.value.length > 0)
</script>

<template>
  <div>
    <div v-if="hasData" class="h-64">
      <Bar :data="chartData" :options="chartOptions" aria-hidden="true" />
    </div>
    <div
      v-else
      class="flex h-64 items-center justify-center rounded-xl border border-dashed border-slate-200 text-sm text-slate-400"
    >
      No activity in this range.
    </div>

    <!-- Accessible data-table fallback -->
    <table v-if="hasData" class="sr-only">
      <caption>
        Tickets served by hour of day
      </caption>
      <thead>
        <tr>
          <th scope="col">Hour</th>
          <th scope="col">Tickets served</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in sorted" :key="p.hour">
          <th scope="row">{{ hourLabel(p.hour) }}</th>
          <td>{{ p.served }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
