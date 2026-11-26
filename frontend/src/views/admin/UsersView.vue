<script setup>
import {
  Users,
  UserCheck,
  ShieldCheck,
  KeyRound,
  Search,
  RotateCcw,
  Plus,
  Pencil,
  Trash2,
  X,
  ChevronLeft,
  ChevronRight,
  CheckCircle2,
  AlertCircle,
} from 'lucide-vue-next'
import { AnimatePresence, motion } from 'motion-v'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '../layouts/DashboardLayout.vue'
import api from '../../services/api'
import { getAuthUser } from '../../router'

const USERS_PER_PAGE = 6

const router = useRouter()
const authState = getAuthUser()

const currentRole = ref(authState.role || 'admin')
const authUser = ref(authState.user)
const activeMenu = ref('kelola-users')

const usersList = ref([])
const isLoading = ref(false)
const isSubmitting = ref(false)
const deletingId = ref(null)

const searchQuery = ref('')
const roleFilter = ref('semua')
const statusFilter = ref('semua')
const currentPage = ref(1)

// State Modal Form (Tambah / Edit)
const isModalOpen = ref(false)
const editingUserId = ref(null)
const formError = ref('')

// State Modal Konfirmasi Hapus
const userToDelete = ref(null)

// Toast Notifikasi
const toast = reactive({
  show: false,
  type: 'success', // 'success' | 'error'
  message: '',
})
let toastTimer = null

const form = reactive({
  nama_lengkap: '',
  username: '',
  password: '',
  role: 'petugas',
  status_aktif: true,
})

const showToast = (message, type = 'success') => {
  toast.message = message
  toast.type = type
  toast.show = true
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.show = false
  }, 3200)
}

const extractList = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  return []
}

const fetchUsers = async () => {
  isLoading.value = true
  try {
    const { data } = await api.get('/admin/users')
    usersList.value = extractList(data)
  } catch (err) {
    showToast(
      err?.response?.data?.message || 'Gagal memuat data pengguna dari server.',
      'error'
    )
  } finally {
    isLoading.value = false
  }
}

const handleLogout = async () => {
  try {
    await api.post('/auth/logout')
  } catch {
    // Abaikan jika token sudah expired
  } finally {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    router.push('/login')
  }
}

onMounted(async () => {
  if (authState.token) {
    try {
      const { data } = await api.get('/auth/me')
      if (data?.user) {
        authUser.value = data.user
        currentRole.value = data.user.role || currentRole.value
        localStorage.setItem('user', JSON.stringify(data.user))
      }
    } catch {
      // Ditangani interceptor
    }
  }
  await fetchUsers()
})

// Statistik KPI
const totalUsersCount = computed(() => usersList.value.length)

const activeUsersCount = computed(
  () => usersList.value.filter((u) => Number(u.status_aktif) === 1 || u.status_aktif === true).length
)

const activePercent = computed(() => {
  if (!totalUsersCount.value) return '0.0%'
  return `${((activeUsersCount.value / totalUsersCount.value) * 100).toFixed(1)}%`
})

const petugasCount = computed(
  () => usersList.value.filter((u) => String(u.role).toLowerCase() === 'petugas').length
)

const adminCount = computed(
  () => usersList.value.filter((u) => String(u.role).toLowerCase() === 'admin').length
)

const ownerCount = computed(
  () => usersList.value.filter((u) => String(u.role).toLowerCase() === 'owner').length
)

const adminOwnerCount = computed(() => adminCount.value + ownerCount.value)

const kpiCards = computed(() => [
  {
    label: 'TOTAL USER',
    value: String(totalUsersCount.value),
    unit: 'Pengguna',
    foot: 'Tersinkron database',
    icon: Users,
  },
  {
    label: 'AKUN AKTIF',
    value: String(activeUsersCount.value),
    unit: 'Tersinkronisasi',
    foot: `${activePercent.value} ketersediaan`,
    icon: UserCheck,
  },
  {
    label: 'PETUGAS GERBANG',
    value: String(petugasCount.value),
    unit: 'Petugas',
    foot: 'Akses pos masuk & keluar',
    icon: ShieldCheck,
  },
  {
    label: 'ADMIN & OWNER',
    value: String(adminOwnerCount.value),
    unit: 'Otoritas',
    foot: `${adminCount.value} Admin, ${ownerCount.value} Owner`,
    icon: KeyRound,
  },
])

// Filter & Pencarian
const filteredUsers = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()

  return usersList.value.filter((user) => {
    const nama = String(user.nama_lengkap || '').toLowerCase()
    const username = String(user.username || '').toLowerCase()
    const role = String(user.role || '').toLowerCase()
    const isActive = Number(user.status_aktif) === 1 || user.status_aktif === true

    const matchSearch = !q || nama.includes(q) || username.includes(q) || role.includes(q)
    const matchRole = roleFilter.value === 'semua' || role === roleFilter.value
    const matchStatus =
      statusFilter.value === 'semua' ||
      (statusFilter.value === 'aktif' && isActive) ||
      (statusFilter.value === 'nonaktif' && !isActive)

    return matchSearch && matchRole && matchStatus
  })
})

const lastPage = computed(() =>
  Math.max(1, Math.ceil(filteredUsers.value.length / USERS_PER_PAGE))
)

const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * USERS_PER_PAGE
  return filteredUsers.value.slice(start, start + USERS_PER_PAGE)
})

const rangeStart = computed(() => {
  if (!filteredUsers.value.length) return 0
  return (currentPage.value - 1) * USERS_PER_PAGE + 1
})

const rangeEnd = computed(() =>
  Math.min(filteredUsers.value.length, currentPage.value * USERS_PER_PAGE)
)

const pageNumbers = computed(() => {
  const pages = []
  for (let i = 1; i <= lastPage.value; i += 1) {
    pages.push(i)
  }
  return pages
})

watch([searchQuery, roleFilter, statusFilter], () => {
  currentPage.value = 1
})

watch(lastPage, (maxPage) => {
  if (currentPage.value > maxPage) {
    currentPage.value = maxPage
  }
})

const resetFilters = () => {
  searchQuery.value = ''
  roleFilter.value = 'semua'
  statusFilter.value = 'semua'
  currentPage.value = 1
}

const goToPage = (page) => {
  const target = Math.min(Math.max(1, page), lastPage.value)
  currentPage.value = target
}

// Helper tampilan baris
const getInitials = (name) =>
  String(name || 'PF')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join('') || 'PF'

const getRoleStyle = (role) => {
  const r = String(role || '').toLowerCase()
  if (r === 'admin') {
    return {
      label: 'Admin',
      badge: 'border border-[#E2E8F0] bg-[#F8FAFC] text-[#0F172A]',
    }
  }
  if (r === 'owner') {
    return {
      label: 'Owner',
      badge: 'border border-[#E8D5CC] bg-[#F3E8E2] text-[#8C4A32]',
    }
  }
  return {
    label: 'Petugas',
    badge: 'border border-[#C7E182] bg-[#EAF6C9] text-[#50631D]',
  }
}

const formatDateTime = (val) => {
  if (!val) return 'Baru saja'
  const d = new Date(val)
  if (Number.isNaN(d.getTime())) return String(val)
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d)
}

// Buka Modal Tambah
const openCreateModal = () => {
  editingUserId.value = null
  formError.value = ''
  form.nama_lengkap = ''
  form.username = ''
  form.password = ''
  form.role = 'petugas'
  form.status_aktif = true
  isModalOpen.value = true
}

// Buka Modal Edit
const openEditModal = (user) => {
  editingUserId.value = user.id_user ?? user.id
  formError.value = ''
  form.nama_lengkap = user.nama_lengkap || ''
  form.username = user.username || ''
  form.password = ''
  form.role = String(user.role || 'petugas').toLowerCase()
  form.status_aktif = Number(user.status_aktif) === 1 || user.status_aktif === true
  isModalOpen.value = true
}

const closeModal = () => {
  if (isSubmitting.value) return
  isModalOpen.value = false
  formError.value = ''
}

// Submit Tambah / Edit ke Laravel API
const submitUserForm = async () => {
  formError.value = ''

  if (!form.nama_lengkap.trim() || !form.username.trim()) {
    formError.value = 'Nama lengkap dan username wajib diisi.'
    return
  }

  if (!editingUserId.value && !form.password.trim()) {
    formError.value = 'Kata sandi awal wajib diisi untuk akun baru.'
    return
  }

  isSubmitting.value = true

  try {
    if (editingUserId.value) {
      const payload = {
        nama_lengkap: form.nama_lengkap.trim(),
        username: form.username.trim(),
        role: form.role,
        status_aktif: form.status_aktif ? 1 : 0,
      }
      if (form.password.trim()) {
        payload.password = form.password.trim()
      }

      await api.put(`/admin/users/${editingUserId.value}`, payload)
      showToast('Data pengguna berhasil diperbarui.', 'success')
    } else {
      await api.post('/admin/users', {
        nama_lengkap: form.nama_lengkap.trim(),
        username: form.username.trim(),
        password: form.password.trim(),
        role: form.role,
        status_aktif: form.status_aktif ? 1 : 0,
      })
      showToast('Pengguna baru berhasil ditambahkan.', 'success')
    }

    isModalOpen.value = false
    await fetchUsers()
  } catch (err) {
    const resData = err?.response?.data
    if (resData?.errors) {
      const firstError = Object.values(resData.errors).flat()[0]
      formError.value = firstError || resData?.message || 'Validasi gagal.'
    } else {
      formError.value = resData?.message || 'Gagal menyimpan data pengguna.'
    }
  } finally {
    isSubmitting.value = false
  }
}

// Hapus User
const confirmDeleteUser = (user) => {
  const currentId = authUser.value?.id_user ?? authUser.value?.id
  const targetId = user.id_user ?? user.id

  if (currentId && Number(currentId) === Number(targetId)) {
    showToast('Tidak bisa menghapus akun sendiri yang sedang login.', 'error')
    return
  }
  userToDelete.value = user
}

const handleDeleteUser = async () => {
  if (!userToDelete.value) return
  const targetId = userToDelete.value.id_user ?? userToDelete.value.id
  deletingId.value = targetId

  try {
    await api.delete(`/admin/users/${targetId}`)
    showToast(`Pengguna "${userToDelete.value.username}" berhasil dihapus.`, 'success')
    userToDelete.value = null
    await fetchUsers()
  } catch (err) {
    showToast(
      err?.response?.data?.message || 'Gagal menghapus pengguna.',
      'error'
    )
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <DashboardLayout
    :current-role="currentRole"
    :active-menu="activeMenu"
    :user="authUser"
    @update:active-menu="(val) => (activeMenu = val)"
    @logout="handleLogout"
  >
    <!-- Toast Notifikasi -->
    <AnimatePresence>
      <motion.div
        v-if="toast.show"
        :initial="{ opacity: 0, y: -14, scale: 0.96 }"
        :animate="{ opacity: 1, y: 0, scale: 1 }"
        :exit="{ opacity: 0, y: -10, scale: 0.96 }"
        :transition="{ duration: 0.22 }"
        :class="[
          'fixed right-6 top-20 z-60 flex items-center gap-2.5 rounded-2xl border px-4 py-3 text-xs font-semibold shadow-lg will-change-transform',
          toast.type === 'error'
            ? 'border-rose-200 bg-rose-50 text-rose-700'
            : 'border-[#C7E182] bg-[#F4F8EA] text-[#3E4E14]',
        ]"
      >
        <AlertCircle v-if="toast.type === 'error'" :size="15" class="shrink-0 text-rose-600" />
        <CheckCircle2 v-else :size="15" class="shrink-0 text-olive" />
        <span>{{ toast.message }}</span>
      </motion.div>
    </AnimatePresence>

    <!-- Header Halaman -->
    <motion.div
      :initial="{ opacity: 0, y: 10 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }"
      class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-center will-change-transform"
    >
      <div>
        <p class="text-[9.5px] font-bold tracking-[0.16em] text-olive">
          ADMINISTRASI USER
        </p>
        <h1 class="mt-1 text-xl font-extrabold tracking-tight text-[#0F172A] sm:text-2xl">
          Kelola Pengguna Sistem
        </h1>
        <p class="mt-1 max-w-2xl text-[11.5px] text-[#64748B]">
          Pusat kendali akun multi-peran ParkFlow. Kelola hak akses terverifikasi dan status akun untuk Admin, Petugas, serta Owner.
        </p>
      </div>

      <motion.button
        type="button"
        @click="openCreateModal"
        :while-hover="{ scale: 1.02 }"
        :while-press="{ scale: 0.97 }"
        class="inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-full bg-olive px-5 py-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-olive-dark will-change-transform"
      >
        <Plus :size="15" />
        <span>Tambah User Baru</span>
      </motion.button>
    </motion.div>

    <!-- 4 Kartu KPI -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <motion.article
        v-for="(kpi, idx) in kpiCards"
        :key="kpi.label"
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32, delay: idx * 0.05 }"
        class="rounded-[22px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <p class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">{{ kpi.label }}</p>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#F4F8EA] text-olive">
            <component :is="kpi.icon" :size="15" />
          </span>
        </div>

        <div class="mt-3 flex items-baseline gap-2">
          <p class="text-2xl font-extrabold tracking-tight text-[#0F172A]">{{ kpi.value }}</p>
          <span class="text-[11px] font-medium text-[#64748B]">{{ kpi.unit }}</span>
        </div>

        <p class="mt-3 text-[10.5px] text-[#64748B]">{{ kpi.foot }}</p>
      </motion.article>
    </div>

    <!-- Filter & Pencarian -->
    <div class="mt-6 flex flex-col justify-between gap-3 rounded-[22px] border border-[#E2E8F0] bg-white p-4 shadow-2xs md:flex-row md:items-center">
      <div class="relative flex-1">
        <Search
          :size="14"
          class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari nama lengkap atau username..."
          class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] py-2 pl-9 pr-4 text-xs text-[#0F172A] placeholder-[#94A3B8] transition-colors focus:border-olive focus:bg-white focus:outline-none"
        />
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <select
          v-model="roleFilter"
          class="cursor-pointer rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2 text-xs font-medium text-[#334155] focus:border-olive focus:bg-white focus:outline-none"
        >
          <option value="semua">Semua Peran</option>
          <option value="admin">Admin</option>
          <option value="petugas">Petugas</option>
          <option value="owner">Owner</option>
        </select>

        <select
          v-model="statusFilter"
          class="cursor-pointer rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2 text-xs font-medium text-[#334155] focus:border-olive focus:bg-white focus:outline-none"
        >
          <option value="semua">Semua Status</option>
          <option value="aktif">Aktif</option>
          <option value="nonaktif">Nonaktif</option>
        </select>

        <button
          type="button"
          @click="resetFilters"
          class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-[#E2E8F0] bg-white px-3.5 py-2 text-xs font-semibold text-[#475569] transition-colors hover:border-olive hover:text-olive"
        >
          <RotateCcw :size="13" />
          <span>Reset</span>
        </button>
      </div>
    </div>

    <!-- Tabel Daftar Akun Pengguna -->
    <motion.section
      :initial="{ opacity: 0, y: 14 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.38, delay: 0.12 }"
      class="mt-6 rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
    >
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E8F0] pb-4">
        <div>
          <h2 class="text-sm font-bold text-[#0F172A]">Daftar Akun Pengguna</h2>
          <p class="mt-0.5 text-[11px] text-[#64748B]">
            Menampilkan seluruh operator terdaftar beserta hak otorisasi dan status akun.
          </p>
        </div>

        <span class="rounded-full bg-[#F4F8EA] px-3 py-1 text-[10.5px] font-bold text-olive">
          {{ filteredUsers.length }} ditampilkan
        </span>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[760px] border-collapse text-left">
          <thead>
            <tr class="border-b border-slate-100 text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
              <th class="py-3 pr-4">PENGGUNA</th>
              <th class="py-3 pr-4">USERNAME</th>
              <th class="py-3 pr-4">ROLE</th>
              <th class="py-3 pr-4">STATUS</th>
              <th class="py-3 pr-4">TERDAFTAR</th>
              <th class="py-3 text-right">AKSI</th>
            </tr>
          </thead>

          <tbody v-if="paginatedUsers.length > 0" class="divide-y divide-slate-100 text-xs">
            <tr
              v-for="user in paginatedUsers"
              :key="user.id_user ?? user.id"
              class="transition-colors hover:bg-[#F8FAFC]/80"
            >
              <td class="py-3.5 pr-4">
                <div class="flex items-center gap-3">
                  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#D5EC96] text-[10.5px] font-bold text-[#50631D]">
                    {{ getInitials(user.nama_lengkap) }}
                  </span>
                  <span class="font-bold text-[#0F172A]">{{ user.nama_lengkap }}</span>
                </div>
              </td>

              <td class="py-3.5 pr-4">
                <span class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-2.5 py-1 font-mono text-[11px] text-[#334155]">
                  @{{ user.username }}
                </span>
              </td>

              <td class="py-3.5 pr-4">
                <span
                  :class="[
                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold',
                    getRoleStyle(user.role).badge,
                  ]"
                >
                  {{ getRoleStyle(user.role).label }}
                </span>
              </td>

              <td class="py-3.5 pr-4">
                <span
                  v-if="Number(user.status_aktif) === 1 || user.status_aktif === true"
                  class="inline-flex items-center gap-1.5 rounded-full bg-[#EAF6C9] px-2.5 py-0.5 text-[10px] font-bold text-[#50631D]"
                >
                  <span class="h-1.5 w-1.5 rounded-full bg-[#50631D]" />
                  Aktif
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-600"
                >
                  <span class="h-1.5 w-1.5 rounded-full bg-rose-500" />
                  Nonaktif
                </span>
              </td>

              <td class="py-3.5 pr-4 text-[11px] text-[#64748B]">
                {{ formatDateTime(user.created_at) }}
              </td>

              <td class="py-3.5 text-right">
                <div class="inline-flex items-center gap-1.5">
                  <button
                    type="button"
                    @click="openEditModal(user)"
                    title="Edit pengguna"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-xl border border-[#E2E8F0] bg-white text-[#475569] transition-colors hover:border-olive hover:bg-[#F4F8EA] hover:text-olive"
                  >
                    <Pencil :size="13" />
                  </button>

                  <button
                    type="button"
                    @click="confirmDeleteUser(user)"
                    title="Hapus pengguna"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-xl border border-[#E2E8F0] bg-white text-[#475569] transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                  >
                    <Trash2 :size="13" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>

          <tbody v-else>
            <tr>
              <td colspan="6" class="py-10 text-center text-xs text-[#64748B]">
                {{
                  isLoading
                    ? 'Memuat data pengguna...'
                    : 'Tidak ada data pengguna yang sesuai dengan filter.'
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="mt-5 flex flex-col items-center justify-between gap-3 border-t border-slate-100 pt-4 text-[11px] text-[#64748B] sm:flex-row">
        <p>
          Menampilkan
          <span class="font-bold text-[#0F172A]">{{ rangeStart }}–{{ rangeEnd }}</span>
          dari
          <span class="font-bold text-[#0F172A]">{{ filteredUsers.length }}</span>
          pengguna
        </p>

        <div class="flex items-center gap-1.5">
          <button
            type="button"
            :disabled="currentPage <= 1"
            @click="goToPage(currentPage - 1)"
            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-[#E2E8F0] text-[#475569] transition-colors hover:border-olive hover:text-olive disabled:cursor-not-allowed disabled:opacity-40"
          >
            <ChevronLeft :size="13" />
          </button>

          <button
            v-for="page in pageNumbers"
            :key="page"
            type="button"
            @click="goToPage(page)"
            :class="[
              'h-7 min-w-7 cursor-pointer rounded-full px-2.5 text-[11px] font-bold transition-colors',
              currentPage === page
                ? 'bg-olive text-white'
                : 'border border-[#E2E8F0] text-[#475569] hover:border-olive hover:text-olive',
            ]"
          >
            {{ page }}
          </button>

          <button
            type="button"
            :disabled="currentPage >= lastPage"
            @click="goToPage(currentPage + 1)"
            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-[#E2E8F0] text-[#475569] transition-colors hover:border-olive hover:text-olive disabled:cursor-not-allowed disabled:opacity-40"
          >
            <ChevronRight :size="13" />
          </button>
        </div>
      </div>
    </motion.section>

    <!-- MODAL FORM TAMBAH / EDIT USER -->
    <AnimatePresence>
      <div
        v-if="isModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <motion.div
          :initial="{ opacity: 0 }"
          :animate="{ opacity: 1 }"
          :exit="{ opacity: 0 }"
          :transition="{ duration: 0.2 }"
          @click="closeModal"
          class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs"
        />

        <motion.div
          :initial="{ opacity: 0, scale: 0.94, y: 16 }"
          :animate="{ opacity: 1, scale: 1, y: 0 }"
          :exit="{ opacity: 0, scale: 0.95, y: 12 }"
          :transition="{ type: 'spring', stiffness: 360, damping: 26 }"
          class="relative z-10 w-full max-w-lg rounded-[26px] border border-[#E2E8F0] bg-white p-6 shadow-xl will-change-transform"
        >
          <div class="flex items-start justify-between gap-3 border-b border-[#E2E8F0] pb-4">
            <div>
              <p class="text-[9.5px] font-bold tracking-[0.16em] text-olive">
                {{ editingUserId ? 'PERBARUI AKUN' : 'AKUN BARU' }}
              </p>
              <h3 class="mt-0.5 text-base font-extrabold text-[#0F172A]">
                {{ editingUserId ? 'Edit Data Pengguna' : 'Tambah User Baru' }}
              </h3>
              <p class="mt-0.5 text-[11px] text-[#64748B]">
                {{
                  editingUserId
                    ? 'Ubah informasi akun atau atur ulang kata sandi bila diperlukan.'
                    : 'Buat kredensial login dan tentukan peran pengguna sistem.'
                }}
              </p>
            </div>

            <button
              type="button"
              @click="closeModal"
              class="cursor-pointer rounded-xl border border-[#E2E8F0] p-1.5 text-[#64748B] transition-colors hover:border-rose-200 hover:text-rose-600"
            >
              <X :size="15" />
            </button>
          </div>

          <form @submit.prevent="submitUserForm" class="mt-5 space-y-4">
            <p
              v-if="formError"
              class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-medium text-rose-700"
            >
              {{ formError }}
            </p>

            <div>
              <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                NAMA LENGKAP
              </label>
              <input
                v-model="form.nama_lengkap"
                type="text"
                required
                placeholder="Contoh: Farhan Syahputra"
                class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
              />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                  USERNAME SISTEM
                </label>
                <input
                  v-model="form.username"
                  type="text"
                  required
                  placeholder="Contoh: farhan.pos03"
                  class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
                />
              </div>

              <div>
                <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                  TINGKAT HAK AKSES (ROLE)
                </label>
                <select
                  v-model="form.role"
                  class="w-full cursor-pointer rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs font-medium text-[#0F172A] focus:border-olive focus:bg-white focus:outline-none"
                >
                  <option value="admin">Admin</option>
                  <option value="petugas">Petugas</option>
                  <option value="owner">Owner</option>
                </select>
              </div>
            </div>

            <div>
              <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                {{ editingUserId ? 'KATA SANDI BARU (OPSIONAL)' : 'KATA SANDI AWAL' }}
              </label>
              <input
                v-model="form.password"
                type="password"
                :required="!editingUserId"
                :placeholder="
                  editingUserId
                    ? 'Kosongkan jika tidak ingin mengganti password'
                    : 'Masukkan kata sandi awal'
                "
                class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
              />
            </div>

            <!-- Toggle Status Aktif -->
            <div class="flex items-center justify-between rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
              <div>
                <p class="text-xs font-bold text-[#0F172A]">Status Akun Aktif</p>
                <p class="text-[10.5px] text-[#64748B]">
                  Izinkan pengguna masuk dan mengakses fitur sesuai peran.
                </p>
              </div>

              <button
                type="button"
                role="switch"
                :aria-checked="form.status_aktif"
                @click="form.status_aktif = !form.status_aktif"
                :class="[
                  'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200',
                  form.status_aktif ? 'bg-olive' : 'bg-slate-300',
                ]"
              >
                <span
                  :class="[
                    'inline-block h-5 w-5 transform rounded-full bg-white shadow-xs transition-transform duration-200 mt-0.5',
                    form.status_aktif ? 'translate-x-5.5' : 'translate-x-0.5',
                  ]"
                />
              </button>
            </div>

            <div class="flex items-center justify-end gap-2.5 border-t border-[#E2E8F0] pt-4">
              <button
                type="button"
                @click="closeModal"
                :disabled="isSubmitting"
                class="cursor-pointer rounded-full border border-[#E2E8F0] bg-white px-4 py-2 text-xs font-semibold text-[#475569] transition-colors hover:bg-slate-50 disabled:opacity-50"
              >
                Batal
              </button>

              <button
                type="submit"
                :disabled="isSubmitting"
                class="cursor-pointer rounded-full bg-olive px-5 py-2 text-xs font-bold text-white transition-colors hover:bg-olive-dark disabled:opacity-60"
              >
                {{
                  isSubmitting
                    ? 'Menyimpan...'
                    : editingUserId
                      ? 'Simpan Perubahan'
                      : 'Simpan User'
                }}
              </button>
            </div>
          </form>
        </motion.div>
      </div>
    </AnimatePresence>

    <!-- MODAL KONFIRMASI HAPUS USER -->
    <AnimatePresence>
      <div
        v-if="userToDelete"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <motion.div
          :initial="{ opacity: 0 }"
          :animate="{ opacity: 1 }"
          :exit="{ opacity: 0 }"
          :transition="{ duration: 0.2 }"
          @click="userToDelete = null"
          class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs"
        />

        <motion.div
          :initial="{ opacity: 0, scale: 0.94, y: 12 }"
          :animate="{ opacity: 1, scale: 1, y: 0 }"
          :exit="{ opacity: 0, scale: 0.95, y: 10 }"
          :transition="{ duration: 0.2 }"
          class="relative z-10 w-full max-w-md rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-xl will-change-transform"
        >
          <h3 class="text-base font-extrabold text-[#0F172A]">Hapus Pengguna?</h3>
          <p class="mt-2 text-xs leading-relaxed text-[#64748B]">
            Akun <span class="font-bold text-[#0F172A]">{{ userToDelete.nama_lengkap }}</span>
            (<span class="font-mono">@{{ userToDelete.username }}</span>) akan dihapus permanen dari sistem.
          </p>

          <div class="mt-6 flex items-center justify-end gap-2.5">
            <button
              type="button"
              @click="userToDelete = null"
              :disabled="deletingId !== null"
              class="cursor-pointer rounded-full border border-[#E2E8F0] bg-white px-4 py-2 text-xs font-semibold text-[#475569] hover:bg-slate-50"
            >
              Batal
            </button>

            <button
              type="button"
              @click="handleDeleteUser"
              :disabled="deletingId !== null"
              class="cursor-pointer rounded-full bg-rose-600 px-5 py-2 text-xs font-bold text-white transition-colors hover:bg-rose-700 disabled:opacity-60"
            >
              {{ deletingId !== null ? 'Menghapus...' : 'Ya, Hapus' }}
            </button>
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  </DashboardLayout>
</template>