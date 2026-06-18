import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import api from '@/lib/api'
import { isStaffRole, type AuthUser, type LoginPayload } from '@/types/auth'

const TOKEN_KEY = 'sq_token'

/**
 * Auth store — JWT login against the Laravel API.
 *
 * The API wraps responses in a `data` envelope, so axios responses are shaped
 * `{ data: { data: <payload> } }` — we unwrap one extra level here.
 *
 * Only staff/admin roles may use this dashboard; a student login is rejected
 * client-side (token cleared, friendly error surfaced) even though the
 * credentials are valid.
 */
export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))
  const user = ref<AuthUser | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => token.value !== null && user.value !== null)
  const isStaff = computed(() => isStaffRole(user.value?.role))

  function setToken(value: string | null) {
    token.value = value
    if (value) localStorage.setItem(TOKEN_KEY, value)
    else localStorage.removeItem(TOKEN_KEY)
  }

  function clearSession() {
    setToken(null)
    user.value = null
  }

  function setError(value: string | null) {
    error.value = value
  }

  /**
   * Authenticate with email/password. On success, persists the token and
   * hydrates the user. Throws if credentials are bad or the account is not
   * staff/admin (so the caller can avoid redirecting).
   */
  async function login(email: string, password: string): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.post<{ data: LoginPayload }>('/login', {
        email,
        password,
      })
      const payload = data.data
      setToken(payload.access_token)

      // Enforce the role gate before considering the session established.
      if (!isStaffRole(payload.user.role)) {
        clearSession()
        throw new RoleForbiddenError()
      }
      user.value = payload.user
    } catch (err) {
      // Don't leak a half-open session on any failure path.
      if (!isAuthenticated.value) setToken(null)
      error.value = toMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Rehydrate the current user from a persisted token (called on app start).
   * Returns true when a valid staff session was restored. Silently clears the
   * session for an invalid/expired token or a non-staff account.
   */
  async function fetchMe(): Promise<boolean> {
    if (!token.value) return false
    loading.value = true
    try {
      const { data } = await api.get<{ data: AuthUser }>('/me')
      if (!isStaffRole(data.data.role)) {
        clearSession()
        return false
      }
      user.value = data.data
      return true
    } catch {
      clearSession()
      return false
    } finally {
      loading.value = false
    }
  }

  /** Revoke the token server-side (best effort) and clear local state. */
  async function logout(): Promise<void> {
    try {
      if (token.value) await api.post('/logout')
    } catch {
      // Ignore network/401 errors — we clear locally regardless.
    } finally {
      clearSession()
      error.value = null
    }
  }

  return {
    token,
    user,
    loading,
    error,
    isAuthenticated,
    isStaff,
    login,
    logout,
    fetchMe,
    clearSession,
    setError,
  }
})

/** Thrown when a valid login belongs to a non-staff (student) account. */
export class RoleForbiddenError extends Error {
  constructor() {
    super('This dashboard is for staff and administrators only.')
    this.name = 'RoleForbiddenError'
  }
}

function toMessage(err: unknown): string {
  if (err instanceof RoleForbiddenError) return err.message

  if (isAxiosError(err)) {
    const status = err.response?.status
    if (status === 401) return 'Incorrect email or password.'
    if (status === 422) {
      // Laravel validation envelope: { message, errors: { field: [msg] } }.
      const data = err.response?.data as
        | { message?: string; errors?: Record<string, string[]> }
        | undefined
      const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined
      return firstError ?? data?.message ?? 'Please check the form and try again.'
    }
    if (status === 429) return 'Too many attempts. Please wait a moment and try again.'
    if (!err.response) return 'Cannot reach the server. Check your connection and try again.'
    return 'Something went wrong. Please try again.'
  }
  return 'Something went wrong. Please try again.'
}
