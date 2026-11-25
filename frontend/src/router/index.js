import { createRouter, createWebHistory } from 'vue-router'
import LandingView from '../views/LandingView.vue'
import LoginView from '../views/LoginView.vue'

export const roleHomeMap = {
  admin: '/admin/dashboard',
  petugas: '/petugas/dashboard',
  owner: '/owner/dashboard',
}

export function getAuthUser() {
  const token = localStorage.getItem('token')
  const userRaw = localStorage.getItem('user')
  if (!token || !userRaw || userRaw === 'undefined') {
    return { token: null, user: null, role: null }
  }
  try {
    const user = JSON.parse(userRaw)
    return { token, user, role: user?.role || null }
  } catch {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    return { token: null, user: null, role: null }
  }
}

const routes = [
  {
    path: '/',
    name: 'landing',
    component: LandingView,
    meta: { public: true, title: 'ParkFlow — Sistem Manajemen Parkir' },
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { guestOnly: true, title: 'Masuk — ParkFlow' },
  },
  {
    path: '/admin/dashboard',
    name: 'admin-dashboard',
    component: () => import('../views/admin/DashboardView.vue'),
    meta: { requiresAuth: true, role: 'admin', title: 'Dashboard Admin — ParkFlow' },
  },
  {
    path: '/admin/users',
    name: 'admin-users',
    component: () => import('../views/admin/UsersView.vue'),
    meta: { requiresAuth: true, role: 'admin', title: 'User Management — ParkFlow' },
  },
  // Aktifkan saat view petugas & owner sudah dibuat:
  // {
  //   path: '/petugas/dashboard',
  //   name: 'petugas-dashboard',
  //   component: () => import('../views/petugas/PetugasDashboardView.vue'),
  //   meta: { requiresAuth: true, role: 'petugas', title: 'Dashboard Petugas — ParkFlow' },
  // },
  // {
  //   path: '/owner/dashboard',
  //   name: 'owner-dashboard',
  //   component: () => import('../views/owner/OwnerDashboardView.vue'),
  //   meta: { requiresAuth: true, role: 'owner', title: 'Dashboard Owner — ParkFlow' },
  // },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) {
      return {
        el: to.hash,
        behavior: 'smooth',
        top: 96,
      }
    }
    return { top: 0, behavior: 'smooth' }
  },
})

router.beforeEach((to, from, next) => {
  if (to.meta?.title) {
    document.title = to.meta.title
  }

  const { token, role } = getAuthUser()

  if (to.meta?.requiresAuth && !token) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  if (to.meta?.guestOnly && token && role && roleHomeMap[role]) {
    return next(roleHomeMap[role])
  }

  if (to.meta?.role && role && to.meta.role !== role) {
    return next(roleHomeMap[role] || '/login')
  }

  return next()
})

export default router