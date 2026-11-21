import { createRouter, createWebHistory } from 'vue-router'
import { getRole, getHomeRouteByRole, getToken } from '../utils/auth'

import LoginView from '../views/LoginView.vue'
import AdminDashboardView from '../views/admin/DashboardView.vue'
import PetugasTransaksiView from '../views/petugas/TransaksiView.vue'
import OwnerLaporanView from '../views/owner/LaporanView.vue'

const routes = [
  { path: '/', redirect: () => getHomeRouteByRole(getRole()) },
  { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },

  // Admin
  {
    path: '/admin/dashboard',
    name: 'admin.dashboard',
    component: AdminDashboardView,
    meta: { requiresAuth: true, roles: ['admin'] },
  },

  // Petugas
  {
    path: '/petugas/transaksi',
    name: 'petugas.transaksi',
    component: PetugasTransaksiView,
    meta: { requiresAuth: true, roles: ['petugas', 'admin'] },
  },

  // Owner
  {
    path: '/owner/laporan',
    name: 'owner.laporan',
    component: OwnerLaporanView,
    meta: { requiresAuth: true, roles: ['owner', 'admin'] },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, from, next) => {
  const token = getToken()
  const role = getRole()

  if (to.meta.requiresAuth && !token) {
    return next('/login')
  }

  if (to.meta.guestOnly && token) {
    return next(getHomeRouteByRole(role))
  }

  if (to.meta.roles && !to.meta.roles.includes(role)) {
    return next(getHomeRouteByRole(role))
  }

  next()
})

export default router