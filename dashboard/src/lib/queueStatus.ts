// Shared display metadata for queue/presence/window enums.
//
// These maps mirror the backend enums (PresenceStatus, TicketStatus, WindowStatus)
// and are the single source of truth for colors, labels, and accessible icons on
// the dashboard. WCAG: never rely on color alone — every badge pairs color with a
// label and a glyph so it remains distinguishable for color-blind users.

import type { PresenceStatus, TicketStatus, WindowStatus } from '@/types/queue'

export interface BadgeMeta {
  label: string
  /** Tailwind classes for the badge chip (bg + text + ring). */
  classes: string
  /** Tailwind classes for a small solid dot indicator. */
  dot: string
  /** Short unicode glyph used as a non-color cue. */
  glyph: string
}

export const PRESENCE_META: Record<PresenceStatus, BadgeMeta> = {
  active: {
    label: 'Active',
    classes: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    dot: 'bg-emerald-500',
    glyph: '●',
  },
  away: {
    label: 'Away',
    classes: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    dot: 'bg-amber-500',
    glyph: '◐',
  },
  offline: {
    label: 'Offline',
    classes: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    dot: 'bg-slate-400',
    glyph: '○',
  },
  removed: {
    label: 'Removed',
    classes: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    dot: 'bg-rose-500',
    glyph: '✕',
  },
}

export function presenceMeta(status: PresenceStatus | null | undefined): BadgeMeta {
  return PRESENCE_META[status ?? 'offline'] ?? PRESENCE_META.offline
}

export const TICKET_STATUS_META: Record<TicketStatus, BadgeMeta> = {
  waiting: {
    label: 'Waiting',
    classes: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    dot: 'bg-blue-500',
    glyph: '…',
  },
  called: {
    label: 'Called',
    classes: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    dot: 'bg-indigo-500',
    glyph: '!',
  },
  serving: {
    label: 'Serving',
    classes: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    dot: 'bg-emerald-500',
    glyph: '▶',
  },
  served: {
    label: 'Served',
    classes: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    dot: 'bg-slate-400',
    glyph: '✓',
  },
  skipped: {
    label: 'Skipped',
    classes: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    dot: 'bg-rose-500',
    glyph: '↷',
  },
  standby: {
    label: 'Standby',
    classes: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    dot: 'bg-amber-500',
    glyph: '⏸',
  },
  cancelled: {
    label: 'Cancelled',
    classes: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    dot: 'bg-slate-400',
    glyph: '✕',
  },
  no_show: {
    label: 'No-show',
    classes: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    dot: 'bg-rose-500',
    glyph: '✕',
  },
}

export function ticketStatusMeta(status: TicketStatus | null | undefined): BadgeMeta {
  return TICKET_STATUS_META[status ?? 'waiting'] ?? TICKET_STATUS_META.waiting
}

export const WINDOW_STATUS_META: Record<WindowStatus, BadgeMeta> = {
  available: {
    label: 'Available',
    classes: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    dot: 'bg-emerald-500',
    glyph: '●',
  },
  busy: {
    label: 'Busy',
    classes: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    dot: 'bg-blue-500',
    glyph: '▶',
  },
  paused: {
    label: 'Paused',
    classes: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    dot: 'bg-amber-500',
    glyph: '⏸',
  },
  closed: {
    label: 'Closed',
    classes: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    dot: 'bg-slate-400',
    glyph: '○',
  },
}

export function windowStatusMeta(status: WindowStatus | null | undefined): BadgeMeta {
  return WINDOW_STATUS_META[status ?? 'closed'] ?? WINDOW_STATUS_META.closed
}

/** Format a confidence fraction (0..1) as a percentage label, or null. */
export function formatConfidence(confidence: number | null | undefined): string | null {
  if (confidence == null || Number.isNaN(confidence)) return null
  const pct = confidence <= 1 ? confidence * 100 : confidence
  return `${Math.round(pct)}%`
}

/** Humanize an ISO timestamp into an elapsed label, e.g. "3m 12s". */
export function elapsedSince(iso: string | null | undefined): string | null {
  if (!iso) return null
  const start = new Date(iso).getTime()
  if (Number.isNaN(start)) return null
  const seconds = Math.max(0, Math.floor((Date.now() - start) / 1000))
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return m > 0 ? `${m}m ${s}s` : `${s}s`
}
