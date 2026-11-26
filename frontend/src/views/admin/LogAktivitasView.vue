<script setup>
import {
  History,
  Search,
  Trash2,
  CheckCircle2,
  AlertCircle,
  RotateCcw,
  Calendar,
  User,
  ShieldCheck,
  Clock,
  CheckSquare,
  Square,
} from 'lucide-vue-next'
import { AnimatePresence, motion } from 'motion-v'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '../layouts/DashboardLayout.vue'
import api from '../../services/api'
import { getAuthUser } from '../../router'

const router = useRouter()
const authState = getAuthUser()

const currentRole = ref(authState.role || 'admin')
const authUser = ref(authState.user)
const activeMenu = ref('log-aktivitas')

const logs = ref([])
const isLoading = ref(false)
const isDeletingBulk = ref(false)
const showBulkConfirm = ref(false)

// Filter & Pencarian
const searchQuery = ref('')
const selectedRole = ref('')
const selectedStatus = ref('')
const dateFrom = ref('')
const dateTo = ref('')

// Seleksi Multi-Item (berisi array id_log)
const selectedIds = ref([])

// Pagination
const currentPage = ref(1)
const pageSize = ref(8)

// Toast Notifikasi
const toast = reactive({
  show: false,
  type: 'success',
  message: '',
})
let toastTimer = null

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

const getUserName = (log) =>
  log?.user?.nama_lengkap ||
  log?.user?.nama ||
  log?.user?.name ||
  log?.user?.username ||
  (log?.id_user ? `User #${log.id_user}` : 'Sistem')

const getUserRole = (log) =>
  String(log?.user?.role || '').trim().toLowerCase()

const getStatusNormalized = (log) => {
  const st = String(log?.status || 'berhasil').trim().toLowerCase()
  return st === 'gagal' ? 'gagal' : 'berhasil'
}

const formatTimeOnly = (dateVal) => {
  if (!dateVal) return '-'
  const d = new Date(dateVal)
  if (Number.isNaN(d.getTime())) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(d)
}

const formatDateShort = (dateVal) => {
  if (!dateVal) return '-'
  const d = new Date(dateVal)
  if (Number.isNaN(d.getTime())) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(d)
}

const fetchLogs = async () => {
  isLoading.value = true
  try {
    const params = {}
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    if (dateFrom.value) params.tanggal_mulai = dateFrom.value
    if (dateTo.value) params.tanggal_selesai = dateTo.value

    const { data } = await api.get('/admin/log-aktivitas', { params })
    logs.value = extractList(data)

    // Bersihkan selectedIds yang sudah tidak ada di data terbaru
    const validIds = new Set(logs.value.map((item) => item.id_log))
    selectedIds.value = selectedIds.value.filter((id) => validIds.has(id))
  } catch (err) {
    showToast(
      err?.response?.data?.message || 'Gagal memuat log aktivitas dari server.',
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
    // Abaikan jika token expired
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
        currentRole.value = String(data.user.role || currentRole.value).toLowerCase()
        localStorage.setItem('user', JSON.stringify(data.user))
      }
    } catch {
      // Ditangani interceptor
    }
  }
  await fetchLogs()
})

// Filter di sisi klien untuk role, status, dan pencarian tambahan
const filteredLogs = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return logs.value.filter((log) => {
    const role = getUserRole(log)
    const status = getStatusNormalized(log)

    if (selectedRole.value && role !== selectedRole.value) return false
    if (selectedStatus.value && status !== selectedStatus.value) return false

    if (!q) return true
    return (
      String(log.aktivitas || '').toLowerCase().includes(q) ||
      getUserName(log).toLowerCase().includes(q) ||
      role.includes(q) ||
      status.includes(q)
    )
  })
})

// Statistik Ringkas
const totalLogsCount = computed(() => logs.value.length)
const gagalLogsCount = computed(
  () => logs.value.filter((l) => getStatusNormalized(l) === 'gagal').length
)
const uniqueActorsCount = computed(() => {
  const set = new Set(
    logs.value.map((l) => l.id_user || getUserName(l)).filter(Boolean)
  )
  return set.size
})

// Pagination
const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredLogs.value.length / pageSize.value))
)

watch([filteredLogs, pageSize], () => {
  if (currentPage.value > totalPages.value) {
    currentPage.value = totalPages.value
  }
})

watch([selectedRole, selectedStatus, dateFrom, dateTo, pageSize], () => {
  currentPage.value = 1
})

const paginatedLogs = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return filteredLogs.value.slice(start, start + pageSize.value)
})

const rangeStart = computed(() =>
  filteredLogs.value.length === 0 ? 0 : (currentPage.value - 1) * pageSize.value + 1
)

const rangeEnd = computed(() =>
  Math.min(currentPage.value * pageSize.value, filteredLogs.value.length)
)

const visiblePageNumbers = computed(() => {
  const total = totalPages.value
  const curr = currentPage.value
  if (total <= 5) {
    return Array.from({ length: total }, (_, idx) => idx + 1)
  }
  const pages = new Set([1, total, curr - 1, curr, curr + 1])
  return [...pages]
    .filter((p) => p >= 1 && p <= total)
    .sort((a, b) => a - b)
})

// Multi-select helpers
const isSelected = (id) => selectedIds.value.includes(id)

const toggleSelectItem = (id) => {
  if (isSelected(id)) {
    selectedIds.value = selectedIds.value.filter((itemId) => itemId !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
}

const pageItemIds = computed(() =>
  paginatedLogs.value.map((item) => item.id_log).filter((id) => id !== undefined)
)

const isAllPageSelected = computed(
  () =>
    pageItemIds.value.length > 0 &&
    pageItemIds.value.every((id) => selectedIds.value.includes(id))
)

const toggleSelectAllPage = () => {
  if (isAllPageSelected.value) {
    const pageSet = new Set(pageItemIds.value)
    selectedIds.value = selectedIds.value.filter((id) => !pageSet.has(id))
  } else {
    const merged = new Set([...selectedIds.value, ...pageItemIds.value])
    selectedIds.value = [...merged]
  }
}

const clearSelection = () => {
  selectedIds.value = []
}

const resetFilters = async () => {
  searchQuery.value = ''
  selectedRole.value = ''
  selectedStatus.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  currentPage.value = 1
  await fetchLogs()
}

// Hapus beberapa log sekaligus
const handleBulkDelete = async () => {
  if (!selectedIds.value.length) return
  isDeletingBulk.value = true

  const idsToDelete = [...selectedIds.value]

  try {
    await api.delete('/admin/log-aktivitas/bulk', {
      data: { ids: idsToDelete },
    })
    showToast(`${idsToDelete.length} log aktivitas berhasil dihapus.`, 'success')
    selectedIds.value = []
    showBulkConfirm.value = false
    await fetchLogs()
  } catch (err) {
    showToast(
      err?.response?.data?.message ||
        'Gagal menghapus log terpilih. Pastikan endpoint DELETE /api/admin/log-aktivitas/bulk sudah tersedia.',
      'error'
    )
  } finally {
    isDeletingBulk.value = false
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
          'fixed right-6 top-20 z-50 flex items-center gap-2.5 rounded-2xl border px-4 py-3 text-xs font-semibold shadow-lg will-change-transform',
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
          REAL-TIME AUDIT TRAIL
        </p>
        <h1 class="mt-1 text-xl font-extrabold tracking-tight text-[#0F172A] sm:text-2xl">
          Log Aktivitas Sistem
        </h1>
        <p class="mt-1 max-w-2xl text-[11.5px] text-[#64748B]">
          Pantau riwayat aktivitas pengguna, perubahan data master, serta operasional parkir secara kronologis. Pilih beberapa baris untuk menghapus sekaligus.
        </p>
      </div>

      <button
        type="button"
        @click="fetchLogs"
        :disabled="isLoading"
        class="inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-full border border-[#E2E8F0] bg-white px-4 py-2.5 text-xs font-semibold text-[#334155] transition-colors hover:border-olive hover:bg-[#F4F8EA] hover:text-olive disabled:opacity-50"
      >
        <RotateCcw :size="14" :class="isLoading ? 'animate-spin' : ''" />
        <span>{{ isLoading ? 'Memuat...' : 'Muat Ulang Log' }}</span>
      </button>
    </motion.div>

    <!-- 3 Kartu Ringkasan -->
    <div class="grid gap-4 sm:grid-cols-3">
      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32 }"
        class="rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            TOTAL LOG TERSIMPAN
          </span>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#EAF6C9] text-olive">
            <History :size="15" />
          </span>
        </div>
        <div class="mt-3 flex items-baseline gap-2">
          <span class="text-2xl font-extrabold text-[#0F172A]">{{ totalLogsCount }}</span>
          <span class="text-[11px] text-[#64748B]">catatan aktivitas</span>
        </div>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32, delay: 0.05 }"
        class="rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            STATUS GAGAL
          </span>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
            <AlertCircle :size="15" />
          </span>
        </div>
        <div class="mt-3 flex items-baseline gap-2">
          <span class="text-2xl font-extrabold text-[#0F172A]">{{ gagalLogsCount }}</span>
          <span class="text-[11px] text-[#64748B]">kejadian gagal</span>
        </div>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32, delay: 0.1 }"
        class="rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            PENGGUNA TERCATAT
          </span>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#F3E8E2] text-[#8C4A32]">
            <User :size="15" />
          </span>
        </div>
        <div class="mt-3 flex items-baseline gap-2">
          <span class="text-2xl font-extrabold text-[#0F172A]">{{ uniqueActorsCount }}</span>
          <span class="text-[11px] text-[#64748B]">akun aktif di log</span>
        </div>
      </motion.article>
    </div>

    <!-- Filter Bar -->
    <div class="mt-6 rounded-[24px] border border-[#E2E8F0] bg-white p-4 shadow-2xs">
      <div class="grid gap-3 md:grid-cols-12 md:items-center">
        <div class="relative md:col-span-4">
          <Search
            :size="14"
            class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]"
          />
          <input
            v-model="searchQuery"
            @keydown.enter="fetchLogs"
            type="text"
            placeholder="Cari aktivitas atau nama pengguna..."
            class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] py-2 pl-9 pr-3.5 text-xs text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
          />
        </div>

        <div class="md:col-span-2">
          <select
            v-model="selectedRole"
            class="w-full cursor-pointer rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs font-semibold text-[#334155] focus:border-olive focus:bg-white focus:outline-none"
          >
            <option value="">Semua Role</option>
            <option value="admin">Admin</option>
            <option value="petugas">Petugas</option>
            <option value="owner">Owner</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <select
            v-model="selectedStatus"
            class="w-full cursor-pointer rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs font-semibold text-[#334155] focus:border-olive focus:bg-white focus:outline-none"
          >
            <option value="">Semua Status</option>
            <option value="berhasil">Berhasil</option>
            <option value="gagal">Gagal</option>
          </select>
        </div>

        <div class="flex items-center gap-1.5 md:col-span-3">
          <input
            v-model="dateFrom"
            @change="fetchLogs"
            type="date"
            title="Tanggal mulai"
            class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-2.5 py-2 text-[11px] text-[#334155] focus:border-olive focus:bg-white focus:outline-none"
          />
          <span class="text-[10px] text-[#94A3B8]">s/d</span>
          <input
            v-model="dateTo"
            @change="fetchLogs"
            type="date"
            title="Tanggal selesai"
            class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-2.5 py-2 text-[11px] text-[#334155] focus:border-olive focus:bg-white focus:outline-none"
          />
        </div>

        <div class="flex justify-end md:col-span-1">
          <button
            type="button"
            @click="resetFilters"
            title="Reset filter"
            class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] text-[#475569] transition-colors hover:border-olive hover:text-olive"
          >
            <RotateCcw :size="14" />
          </button>
        </div>
      </div>
    </div>

    <!-- Action Bar Multi-Select Saat Ada Item Dipilih -->
    <AnimatePresence>
      <motion.div
        v-if="selectedIds.length > 0"
        :initial="{ opacity: 0, y: -8 }"
        :animate="{ opacity: 1, y: 0 }"
        :exit="{ opacity: 0, y: -8 }"
        :transition="{ duration: 0.18 }"
        class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#C7E182] bg-[#F4F8EA] px-4 py-3 text-xs will-change-transform"
      >
        <div class="flex items-center gap-2.5 font-semibold text-[#3E4E14]">
          <CheckSquare :size="16" class="text-olive" />
          <span>{{ selectedIds.length }} log aktivitas dipilih</span>
          <button
            type="button"
            @click="clearSelection"
            class="cursor-pointer underline decoration-[#84A92C] underline-offset-2 hover:text-[#161F00]"
          >
            Batalkan pilihan
          </button>
        </div>

        <button
          type="button"
          @click="showBulkConfirm = true"
          class="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-rose-600 px-4 py-1.5 text-xs font-bold text-white transition-colors hover:bg-rose-700"
        >
          <Trash2 :size="13" />
          <span>Hapus Terpilih ({{ selectedIds.length }})</span>
        </button>
      </motion.div>
    </AnimatePresence>

    <!-- Daftar Log Aktivitas -->
    <motion.section
      :initial="{ opacity: 0, y: 14 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.36, delay: 0.08 }"
      class="mt-5 rounded-[26px] border border-[#E2E8F0] bg-white p-5 shadow-2xs sm:p-6 will-change-transform"
    >
      <!-- Header List & Select All Halaman Ini -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="toggleSelectAllPage"
            :disabled="paginatedLogs.length === 0"
            class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1.5 text-[11px] font-bold text-[#334155] transition-colors hover:border-olive hover:text-olive disabled:cursor-not-allowed disabled:opacity-40"
          >
            <CheckSquare v-if="isAllPageSelected" :size="14" class="text-olive" />
            <Square v-else :size="14" class="text-[#94A3B8]" />
            <span>{{ isAllPageSelected ? 'Batal Pilih Halaman Ini' : 'Pilih Halaman Ini' }}</span>
          </button>

          <div>
            <h2 class="text-sm font-bold text-[#0F172A]">Arus Riwayat Aktivitas</h2>
            <p class="text-[10.5px] text-[#64748B]">
              Menampilkan {{ rangeStart }} - {{ rangeEnd }} dari {{ filteredLogs.length }} log
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 text-[11px] text-[#64748B]">
          <span>Baris / hal:</span>
          <select
            v-model.number="pageSize"
            class="cursor-pointer rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-2.5 py-1 text-xs font-bold text-[#0F172A] focus:border-olive focus:outline-none"
          >
            <option :value="8">8</option>
            <option :value="15">15</option>
            <option :value="25">25</option>
          </select>
        </div>
      </div>

      <!-- Item Log -->
      <div v-if="paginatedLogs.length > 0" class="mt-4 divide-y divide-slate-100">
        <div
          v-for="log in paginatedLogs"
          :key="log.id_log"
          @click="toggleSelectItem(log.id_log)"
          :class="[
            'flex cursor-pointer items-start gap-3.5 rounded-2xl px-3 py-4 transition-colors',
            isSelected(log.id_log)
              ? 'bg-[#F4F8EA]/70'
              : 'hover:bg-[#F8FAFC]',
          ]"
        >
          <input
            type="checkbox"
            :checked="isSelected(log.id_log)"
            @click.stop
            @change="toggleSelectItem(log.id_log)"
            class="mt-1 h-4 w-4 cursor-pointer rounded border-slate-300 accent-olive"
          />

          <!-- Waktu -->
          <div class="w-24 shrink-0 text-left">
            <p class="inline-flex items-center gap-1 text-xs font-extrabold text-[#0F172A]">
              <Clock :size="11" class="text-[#94A3B8]" />
              <span>{{ formatTimeOnly(log.waktu_aktivitas || log.created_at) }}</span>
            </p>
            <p class="mt-0.5 inline-flex items-center gap-1 text-[10px] text-[#64748B]">
              <Calendar :size="10" class="text-[#94A3B8]" />
              <span>{{ formatDateShort(log.waktu_aktivitas || log.created_at) }}</span>
            </p>
          </div>

          <!-- Konten Aktivitas -->
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-xs font-bold text-[#0F172A]">
                {{ getUserName(log) }}
              </span>

              <span
                v-if="getUserRole(log)"
                class="inline-flex items-center gap-1 rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-2 py-0.5 text-[9.5px] font-bold uppercase text-[#475569]"
              >
                <ShieldCheck :size="10" class="text-olive" />
                {{ getUserRole(log) }}
              </span>
            </div>

            <p class="mt-1 text-xs leading-relaxed text-[#334155]">
              {{ log.aktivitas }}
            </p>
          </div>

          <!-- Status Badge -->
          <div class="shrink-0 text-right">
            <span
              :class="[
                'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold capitalize',
                getStatusNormalized(log) === 'gagal'
                  ? 'bg-rose-50 text-rose-700 border border-rose-200'
                  : 'bg-[#EAF6C9] text-[#50631D] border border-[#C7E182]',
              ]"
            >
              <AlertCircle v-if="getStatusNormalized(log) === 'gagal'" :size="11" />
              <CheckCircle2 v-else :size="11" />
              {{ getStatusNormalized(log) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Empty / Loading State -->
      <div
        v-else
        class="py-12 text-center text-xs text-[#64748B]"
      >
        {{
          isLoading
            ? 'Memuat riwayat log aktivitas...'
            : 'Tidak ada log aktivitas yang sesuai dengan filter saat ini.'
        }}
      </div>

      <!-- Pagination Footer -->
      <div class="mt-5 flex flex-col items-center justify-between gap-3 border-t border-slate-100 pt-4 text-[11px] text-[#64748B] sm:flex-row">
        <p>
          Halaman <span class="font-bold text-[#0F172A]">{{ currentPage }}</span> dari
          <span class="font-bold text-[#0F172A]">{{ totalPages }}</span> halaman log
        </p>

        <div class="flex items-center gap-1.5">
          <button
            type="button"
            :disabled="currentPage <= 1"
            @click="currentPage--"
            class="cursor-pointer rounded-xl border border-[#E2E8F0] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569] transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Prev
          </button>

          <button
            v-for="page in visiblePageNumbers"
            :key="page"
            type="button"
            @click="currentPage = page"
            :class="[
              'h-8 w-8 cursor-pointer rounded-xl text-xs font-bold transition-colors',
              currentPage === page
                ? 'bg-olive text-white'
                : 'border border-[#E2E8F0] bg-white text-[#475569] hover:bg-slate-50',
            ]"
          >
            {{ page }}
          </button>

          <button
            type="button"
            :disabled="currentPage >= totalPages"
            @click="currentPage++"
            class="cursor-pointer rounded-xl border border-[#E2E8F0] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569] transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Next
          </button>
        </div>
      </div>
    </motion.section>

    <!-- Modal Konfirmasi Hapus Massal -->
    <AnimatePresence>
      <div
        v-if="showBulkConfirm"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <motion.div
          :initial="{ opacity: 0 }"
          :animate="{ opacity: 1 }"
          :exit="{ opacity: 0 }"
          :transition="{ duration: 0.2 }"
          @click="showBulkConfirm = false"
          class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs"
        />

        <motion.div
          :initial="{ opacity: 0, scale: 0.94, y: 12 }"
          :animate="{ opacity: 1, scale: 1, y: 0 }"
          :exit="{ opacity: 0, scale: 0.95, y: 10 }"
          :transition="{ duration: 0.2 }"
          class="relative z-10 w-full max-w-md rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-xl will-change-transform"
        >
          <h3 class="text-base font-extrabold text-[#0F172A]">Hapus Log Terpilih?</h3>
          <p class="mt-2 text-xs leading-relaxed text-[#64748B]">
            Kamu akan menghapus permanen
            <span class="font-bold text-[#0F172A]">{{ selectedIds.length }}</span>
            catatan log aktivitas yang dipilih. Tindakan ini tidak dapat dibatalkan.
          </p>

          <div class="mt-6 flex items-center justify-end gap-2.5">
            <button
              type="button"
              @click="showBulkConfirm = false"
              :disabled="isDeletingBulk"
              class="cursor-pointer rounded-full border border-[#E2E8F0] bg-white px-4 py-2 text-xs font-semibold text-[#475569] hover:bg-slate-50"
            >
              Batal
            </button>

            <button
              type="button"
              @click="handleBulkDelete"
              :disabled="isDeletingBulk"
              class="cursor-pointer rounded-full bg-rose-600 px-5 py-2 text-xs font-bold text-white transition-colors hover:bg-rose-700 disabled:opacity-60"
            >
              {{ isDeletingBulk ? 'Menghapus...' : `Ya, Hapus (${selectedIds.length})` }}
            </button>
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  </DashboardLayout>
</template>