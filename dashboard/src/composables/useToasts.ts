import { ref } from 'vue'

// Lightweight global toast queue for action feedback (task 037).
// Shared singleton state so any component can push and the <ToastHost> renders.

export type ToastTone = 'success' | 'error' | 'info'

export interface Toast {
  id: number
  tone: ToastTone
  message: string
}

const toasts = ref<Toast[]>([])
let nextId = 1
const DEFAULT_TTL = 4000

function push(message: string, tone: ToastTone = 'info', ttl = DEFAULT_TTL): number {
  const id = nextId++
  toasts.value = [...toasts.value, { id, tone, message }]
  if (ttl > 0) {
    setTimeout(() => dismiss(id), ttl)
  }
  return id
}

function dismiss(id: number): void {
  toasts.value = toasts.value.filter((t) => t.id !== id)
}

export function useToasts() {
  return {
    toasts,
    dismiss,
    success: (msg: string) => push(msg, 'success'),
    error: (msg: string) => push(msg, 'error', 6000),
    info: (msg: string) => push(msg, 'info'),
  }
}
