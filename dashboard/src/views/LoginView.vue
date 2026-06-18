<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const showPassword = ref(false)

// Inline validation only surfaces after the field is blurred or a submit is attempted.
const touched = ref({ email: false, password: false })
const submitted = ref(false)

const emailError = computed(() => {
  if (!touched.value.email && !submitted.value) return null
  const value = email.value.trim()
  if (!value) return 'Email is required.'
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Enter a valid email address.'
  return null
})

const passwordError = computed(() => {
  if (!touched.value.password && !submitted.value) return null
  if (!password.value) return 'Password is required.'
  return null
})

const formValid = computed(
  () => !emailError.value && !passwordError.value && !!email.value && !!password.value,
)

function intendedTarget(): string {
  const redirect = route.query.redirect
  if (typeof redirect === 'string' && redirect.startsWith('/')) return redirect
  return '/'
}

async function handleSubmit() {
  submitted.value = true
  auth.setError(null)
  // Re-evaluates validation computeds via `submitted`.
  if (!formValid.value) return

  try {
    await auth.login(email.value.trim(), password.value)
    await router.replace(intendedTarget())
  } catch {
    // Error message is set on the store and rendered in the banner below;
    // keep the password so the user can correct just the email if needed.
    password.value = ''
    submitted.value = false
    touched.value.password = false
  }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <span
          class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white shadow-sm"
          aria-hidden="true"
        >
          SQ
        </span>
        <h1 class="text-2xl font-bold text-slate-900">Staff Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Sign in to manage the smart queue.</p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <!-- Error banner (401 / forbidden / network) -->
        <div
          v-if="auth.error"
          class="mb-5 flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"
          role="alert"
          aria-live="assertive"
        >
          <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
              clip-rule="evenodd"
            />
          </svg>
          <span>{{ auth.error }}</span>
        </div>

        <form class="space-y-5" novalidate @submit.prevent="handleSubmit">
          <!-- Email -->
          <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
            <input
              id="email"
              v-model="email"
              type="email"
              name="email"
              autocomplete="username"
              inputmode="email"
              placeholder="you@university.edu"
              :aria-invalid="!!emailError"
              :aria-describedby="emailError ? 'email-error' : undefined"
              class="block w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/40 disabled:cursor-not-allowed disabled:bg-slate-50"
              :class="
                emailError
                  ? 'border-red-300 focus:border-red-400 focus:ring-red-500/30'
                  : 'border-slate-300 focus:border-brand-500'
              "
              :disabled="auth.loading"
              @blur="touched.email = true"
            />
            <p v-if="emailError" id="email-error" class="mt-1.5 text-xs text-red-600">{{ emailError }}</p>
          </div>

          <!-- Password -->
          <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
            <div class="relative">
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                name="password"
                autocomplete="current-password"
                placeholder="••••••••"
                :aria-invalid="!!passwordError"
                :aria-describedby="passwordError ? 'password-error' : undefined"
                class="block w-full rounded-lg border bg-white px-3.5 py-2.5 pr-11 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/40 disabled:cursor-not-allowed disabled:bg-slate-50"
                :class="
                  passwordError
                    ? 'border-red-300 focus:border-red-400 focus:ring-red-500/30'
                    : 'border-slate-300 focus:border-brand-500'
                "
                :disabled="auth.loading"
                @blur="touched.password = true"
              />
              <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center rounded-r-lg px-3 text-slate-400 transition hover:text-slate-600 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand-600"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                :aria-pressed="showPassword"
                tabindex="0"
                @click="showPassword = !showPassword"
              >
                <svg v-if="showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.5 10.5 0 001.93 12C3.23 16.34 7.24 19.5 12 19.5c.99 0 1.95-.14 2.86-.39M6.23 6.23A10.45 10.45 0 0112 4.5c4.76 0 8.77 3.16 10.07 7.5a10.52 10.52 0 01-4.29 5.27M6.23 6.23L3 3m3.23 3.23l3.65 3.65m7.89 7.89L21 21m-3.23-3.23l-3.65-3.65m0 0a3 3 0 00-4.24-4.24m4.24 4.24L9.88 9.88" />
                </svg>
                <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 010-.64C3.42 7.51 7.36 4.5 12 4.5c4.64 0 8.57 3.01 9.96 7.18a1 1 0 010 .64C20.58 16.49 16.64 19.5 12 19.5c-4.64 0-8.57-3.01-9.96-7.18z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
              </button>
            </div>
            <p v-if="passwordError" id="password-error" class="mt-1.5 text-xs text-red-600">{{ passwordError }}</p>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 active:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="auth.loading"
          >
            <svg
              v-if="auth.loading"
              class="h-4 w-4 animate-spin"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
            </svg>
            <span>{{ auth.loading ? 'Signing in…' : 'Sign in' }}</span>
          </button>
        </form>
      </div>

      <p class="mt-6 text-center text-xs text-slate-400">
        Restricted to authorized staff and administrators.
      </p>
    </div>
  </main>
</template>
