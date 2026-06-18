// Auth/user types mirroring the Laravel API contract (POST /api/login, GET /api/me).

/** Roles returned by the backend. Only staff/admin may use this dashboard. */
export type UserRole = 'admin' | 'staff' | 'student'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: UserRole
  student_no: string | null
}

/** Shape nested under the `data` envelope of POST /api/login. */
export interface LoginPayload {
  access_token: string
  token_type: string
  expires_in: number
  user: AuthUser
}

/** Roles permitted to access the staff/admin dashboard. */
export const STAFF_ROLES: readonly UserRole[] = ['admin', 'staff'] as const

export function isStaffRole(role: UserRole | undefined | null): boolean {
  return role != null && STAFF_ROLES.includes(role)
}
