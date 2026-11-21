const TOKEN_KEY = 'token'
const USER_KEY = 'user'

export function setAuth(token, user) {
  localStorage.setItem(TOKEN_KEY, token)
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}

export function clearAuth() {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(USER_KEY)
}

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function getUser() {
  const raw = localStorage.getItem(USER_KEY)
  if (!raw) return null
  try {
    return JSON.parse(raw)
  } catch {
    return null
  }
}

export function getRole() {
  return getUser()?.role || null
}

export function getHomeRouteByRole(role) {
  switch (role) {
    case 'admin':
      return '/admin/dashboard'
    case 'petugas':
      return '/petugas/transaksi'
    case 'owner':
      return '/owner/laporan'
    default:
      return '/login'
  }
}