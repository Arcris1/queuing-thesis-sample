import axios from 'axios'

// Shared axios instance for the Laravel API. Base URL is proxied to the backend
// in dev (see vite.config.ts) and configurable via VITE_API_BASE in prod.
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE ?? '/api',
  headers: { Accept: 'application/json' },
})

// Attach the JWT bearer token (set by the auth store after login).
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('sq_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default api
