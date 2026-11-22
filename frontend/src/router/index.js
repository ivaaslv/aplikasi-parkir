import { createRouter, createWebHistory } from 'vue-router'
import LandingView from '../views/LandingView.vue'

// Mapping dashboard default per role
const roleHomeMap = {
  admin: '/admin/dashboard',
  petugas: '/petugas/dashboard',
  owner: '/owner/dashboard',
}

const routes = [
  {
    path: '/',
    name: 'landing',
    component: LandingView,
    meta: { public: true, title: 'Parkly — Sistem Manajemen Parkir' },
  },
  {
    path: '/login',
    name: 'login',
    // Aktifkan import di bawah setelah file LoginView.vue dibuat:
    // component: () => import('@/views/LoginView.vue'),
    component: LandingView,
    meta: { guestOnly: true, title: 'Masuk — Parkly' },
  },

  // --- Placeholder Route Role (aktifkan bertahap saat view-nya dibuat) ---
  // {
  //   path: '/admin/dashboard',
  //   name: 'admin-dashboard',
  //   component: () => import('@/views/admin/AdminDashboardView.vue'),
  //   meta: { requiresAuth: true, role: 'admin', title: 'Dashboard Admin — Parkly' },
  // },
  // {
  //   path: '/petugas/dashboard',
  //   name: 'petugas-dashboard',
  //   component: () => import('@/views/petugas/PetugasDashboardView.vue'),
  //   meta: { requiresAuth: true, role: 'petugas', title: 'Dashboard Petugas — Parkly' },
  // },
  // {
  //   path: '/owner/dashboard',
  //   name: 'owner-dashboard',
  //   component: () => import('@/views/owner/OwnerDashboardView.vue'),
  //   meta: { requiresAuth: true, role: 'owner', title: 'Dashboard Owner — Parkly' },
  // },

  // Fallback jika path tidak ditemukan
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
        top: 96, // Offset agar tidak tertutup floating pill navbar
      }
    }
    return { top: 0, behavior: 'smooth' }
  },
})

// Navigation Guard (cek token & role dari localStorage)
// Ganti bagian pembacaan user di router.beforeEach (src/router/index.js) menjadi:
const token = localStorage.getItem('token')
const userRaw = localStorage.getItem('user')

let user = null
if (userRaw && userRaw !== 'undefined') {
  try {
    user = JSON.parse(userRaw)
  } catch {
    localStorage.removeItem('user')
  }
}

const userRole = user?.role

export default router