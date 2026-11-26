<script setup>
import {
  Wallet,
  CarFront,
  PieChart,
  Users,
  Plus,
  Tag,
  UserCog,
  History,
  ArrowRight,
  ChevronLeft,
  ChevronRight,
  ShieldCheck,
  RefreshCw,
  ParkingSquare,
} from 'lucide-vue-next'
import { motion } from 'motion-v'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '../layouts/DashboardLayout.vue'
import api from '../../services/api'
import { getAuthUser } from '../../router'

const LOGS_PER_PAGE = 10
const MAX_DASHBOARD_ZONES = 3

const router = useRouter()
const authState = getAuthUser()

const currentRole = ref(authState.role || 'admin')
const authUser = ref(authState.user)
const activeMenu = ref('dashboard-admin')
const activeLogFilter = ref('semua')

const isLoading = ref(false)
const fetchError = ref('')
const currentPage = ref(1)
const serverPaginated = ref(false)
const serverLastPage = ref(1)
const serverTotalLogs = ref(0)

const areasRaw = ref([])
const usersRaw = ref([])
const vehiclesRaw = ref([])
const transactionsRaw = ref([])
const logsRaw = ref([])
const pendapatanData = ref(null)

const occupancyAngleDeg = computed(
  () => `${Math.round(occupancyPercentNum.value * 3.6)}deg`
)

const occupancyRingStyle = computed(() => ({
  background: `conic-gradient(#50631D 0deg ${occupancyAngleDeg.value}, #E2E8F0 ${occupancyAngleDeg.value} 360deg)`,
}))

const adminDisplayName = computed(
  () =>
    authUser.value?.nama_lengkap ||
    authUser.value?.nama ||
    authUser.value?.name ||
    authUser.value?.username ||
    'Administrator'
)

const adminInitials = computed(
  () =>
    adminDisplayName.value
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase())
      .join('') || 'AD'
)

const formatRupiah = (amount) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(amount) || 0)

const formatAngka = (num) =>
  new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
  }).format(Number(num) || 0)

const formatPercentValue = (used, total) => {
  const cap = Number(total) || 0
  if (cap <= 0) return 0
  const rawPct = (Math.max(0, Number(used) || 0) / cap) * 100
  const clamped = Math.min(100, Math.max(0, rawPct))
  return Number.isInteger(clamped) ? clamped : Number(clamped.toFixed(1))
}

const extractList = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  return []
}

const fetchDashboardData = async (page = 1) => {
  isLoading.value = true
  fetchError.value = ''

  try {
    const [areaRes, userRes, kendaraanRes, transaksiRes, logRes, laporanRes] =
      await Promise.allSettled([
        api.get('/admin/area-parkir'),
        api.get('/admin/users'),
        api.get('/admin/kendaraan'),
        api.get('/operasional/transaksi'),
        api.get('/admin/log-aktivitas', {
          params: { page, per_page: LOGS_PER_PAGE },
        }),
        api.get('/laporan/pendapatan'),
      ])

    if (areaRes.status === 'fulfilled') {
      areasRaw.value = extractList(areaRes.value.data)
    }
    if (userRes.status === 'fulfilled') {
      usersRaw.value = extractList(userRes.value.data)
    }
    if (kendaraanRes.status === 'fulfilled') {
      vehiclesRaw.value = extractList(kendaraanRes.value.data)
    }
    if (transaksiRes.status === 'fulfilled') {
      transactionsRaw.value = extractList(transaksiRes.value.data)
    }
    if (laporanRes.status === 'fulfilled') {
      pendapatanData.value = laporanRes.value.data?.data ?? laporanRes.value.data
    }

    if (logRes.status === 'fulfilled') {
      const resData = logRes.value.data
      const list = extractList(resData)
      logsRaw.value = list

      const hasServerMeta =
        typeof resData?.last_page === 'number' ||
        typeof resData?.meta?.last_page === 'number'

      if (hasServerMeta) {
        serverPaginated.value = true
        currentPage.value =
          resData?.current_page ?? resData?.meta?.current_page ?? page
        serverLastPage.value = Math.max(
          1,
          resData?.last_page ?? resData?.meta?.last_page ?? 1
        )
        serverTotalLogs.value =
          resData?.total ?? resData?.meta?.total ?? list.length
      } else {
        serverPaginated.value = false
        serverTotalLogs.value = list.length
      }
    }

    const allFailed = [
      areaRes,
      userRes,
      kendaraanRes,
      transaksiRes,
      logRes,
      laporanRes,
    ].every((r) => r.status === 'rejected')

    if (allFailed) {
      fetchError.value =
        'Tidak dapat memuat data dari backend. Pastikan server API aktif dan token valid.'
    }
  } finally {
    isLoading.value = false
  }
}

const handleLogout = async () => {
  try {
    await api.post('/auth/logout')
  } catch {
    // Abaikan jika token sudah expired saat logout
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
      // Ditangani oleh interceptor / guard
    }
  }
  await fetchDashboardData(1)
})

const allZones = computed(() =>
  areasRaw.value.map((area, idx) => {
    const code =
      area.kode_area ||
      area.kode ||
      area.code ||
      String(area.nama_area || area.nama || `Z${idx + 1}`)
        .slice(0, 3)
        .toUpperCase()
    const name = area.nama_area || area.nama || area.name || `Area ${idx + 1}`
    const total = Math.max(
      0,
      Number(area.kapasitas ?? area.kapasitas_slot ?? area.total_slot ?? 0)
    )
    const used = Math.max(
      0,
      Number(
        area.terisi ??
          area.slot_terisi ??
          area.kendaraan_aktif ??
          Math.max(0, total - Number(area.sisa_slot ?? area.slot_tersedia ?? total))
      )
    )
    const percentNum = formatPercentValue(used, total)

    let status = 'Lega'
    let statusCls = 'bg-[#F1F5F9] text-[#475569]'
    let barCls = 'bg-[#64748B]'
    let badgeCls = 'bg-[#F1F5F9] text-[#334155]'

    if (percentNum >= 80) {
      status = 'Penuh'
      statusCls = 'bg-rose-100 text-rose-700'
      barCls = 'bg-rose-600'
      badgeCls = 'bg-rose-100 text-rose-700'
    } else if (percentNum >= 35) {
      status = 'Aman'
      statusCls = 'bg-[#D5EC96]/70 text-[#50631D]'
      barCls = 'bg-[#50631D]'
      badgeCls = 'bg-[#D5EC96] text-[#50631D]'
    }

    const sortKey = area.created_at
      ? new Date(area.created_at).getTime()
      : Number(area.id_area ?? area.id ?? idx)

    return {
      id: area.id_area ?? area.id ?? idx,
      sortKey: Number.isNaN(sortKey) ? idx : sortKey,
      code,
      name,
      deskripsi: area.deskripsi || '',
      jenis_kendaraan: area.jenis_kendaraan || 'semua',
      used,
      total,
      percent: percentNum,
      status,
      statusCls,
      barCls,
      badgeCls,
    }
  })
)

const recentZones = computed(() =>
  [...allZones.value]
    .sort((a, b) => Number(b.sortKey) - Number(a.sortKey))
    .slice(0, MAX_DASHBOARD_ZONES)
)

const totalPendapatan = computed(() => {
  if (pendapatanData.value?.total_pendapatan !== undefined) {
    return Number(pendapatanData.value.total_pendapatan)
  }
  if (pendapatanData.value?.total !== undefined) {
    return Number(pendapatanData.value.total)
  }
  return transactionsRaw.value.reduce(
    (sum, trx) =>
      sum + Number(trx.biaya_total ?? trx.total_biaya ?? trx.biaya ?? trx.tarif ?? 0),
    0
  )
})

const activeTransactions = computed(() =>
  transactionsRaw.value.filter(
    (trx) =>
      !trx.waktu_keluar &&
      !trx.jam_keluar &&
      !['keluar', 'selesai', 'lunas'].includes(
        String(trx.status || '').toLowerCase()
      )
  )
)

const totalCapacity = computed(() =>
  allZones.value.reduce((sum, z) => sum + z.total, 0)
)
const totalUsedSlots = computed(() =>
  allZones.value.reduce((sum, z) => sum + z.used, 0)
)
const totalFreeSlots = computed(() =>
  Math.max(0, totalCapacity.value - totalUsedSlots.value)
)
const occupancyPercentNum = computed(() =>
  formatPercentValue(totalUsedSlots.value, totalCapacity.value)
)
const occupancyPercentLabel = computed(() => `${occupancyPercentNum.value}%`)

const roleCounts = computed(() => {
  const counts = { admin: 0, petugas: 0, owner: 0 }
  for (const u of usersRaw.value) {
    const r = String(u.role || '').toLowerCase()
    if (r in counts) counts[r] += 1
  }
  return counts
})

const kpiCards = computed(() => [
  {
    label: 'TOTAL PENDAPATAN',
    value: formatRupiah(totalPendapatan.value),
    sub: `${transactionsRaw.value.length} Transaksi`,
    subBadge: true,
    icon: Wallet,
  },
  {
    label: 'KENDARAAN AKTIF',
    value: String(activeTransactions.value.length),
    unit: 'Unit',
    status: '● Aktif',
    foot: `${vehiclesRaw.value.length} Kendaraan Terdaftar`,
    icon: CarFront,
  },
  {
    label: 'OKUPANSI SLOT',
    value: formatAngka(totalUsedSlots.value),
    subValue: `/${formatAngka(totalCapacity.value)}`,
    percent: occupancyPercentLabel.value,
    percentWidth: occupancyPercentNum.value,
    foot: `${formatAngka(totalFreeSlots.value)} Slot Kosong`,
    icon: PieChart,
  },
  {
    label: 'PERSONEL BERTUGAS',
    value: String(usersRaw.value.length),
    unit: 'User',
    roles: [
      { label: `${roleCounts.value.admin} Admin`, cls: 'bg-[#161F00] text-white' },
      {
        label: `${roleCounts.value.petugas} Petugas`,
        cls: 'bg-[#D5EC96] text-[#50631D]',
      },
      {
        label: `${roleCounts.value.owner} Owner`,
        cls: 'bg-[#F1F5F9] text-[#475569]',
      },
    ],
    icon: Users,
  },
])

const quickActions = [
  {
    label: 'Kelola Area Parkir',
    to: '/admin/area-parkir',
    menuId: 'kelola-area',
    icon: Plus,
    primary: true,
  },
  {
    label: 'Kelola Tarif Parkir',
    to: '/admin/tarif',
    menuId: 'kelola-tarif',
    icon: Tag,
    tag: 'NEW',
  },
  {
    label: 'Kelola Data Kendaraan',
    to: '/admin/kendaraan',
    menuId: 'kelola-kendaraan',
    icon: CarFront,
  },
  {
    label: 'Kelola Akun Pengguna',
    to: '/admin/users',
    menuId: 'kelola-users',
    icon: UserCog,
  },
  {
    label: 'Log Aktivitas Sistem',
    to: '/admin/log-aktivitas',
    menuId: 'log-aktivitas',
    icon: History,
  },
]

const handleQuickAction = async (action) => {
  activeMenu.value = action.menuId
  if (!action.to) return

  const resolved = router.resolve(action.to)
  if (resolved.matched.length > 0) {
    await router.push(action.to)
    return
  }

  if (action.to === '/admin/area-parkir') {
    const alt = router.resolve('/admin/area')
    if (alt.matched.length > 0) {
      await router.push('/admin/area')
    }
  }
}

const cleanText = (val) => {
  if (!val || String(val).trim() === '' || String(val).trim() === '-') return ''
  return String(val).trim()
}

const normalizedLogs = computed(() =>
  logsRaw.value.map((item, idx) => {
    const userObj = item.user || item.pengguna || {}
    const userName =
      cleanText(userObj.nama_lengkap) ||
      cleanText(userObj.nama) ||
      cleanText(userObj.name) ||
      cleanText(userObj.username) ||
      cleanText(item.nama_user) ||
      cleanText(item.username) ||
      'Pengguna Sistem'

    const roleLabel =
      cleanText(userObj.role) || cleanText(item.role) || 'Sistem'
    const activityText =
      cleanText(item.aktivitas) ||
      cleanText(item.keterangan) ||
      cleanText(item.deskripsi) ||
      'Aktivitas Tercatat'

    const rawTime = cleanText(item.waktu_aktivitas) || cleanText(item.created_at)
    const parsedDate = rawTime ? new Date(rawTime) : null
    const hasValidDate = parsedDate && !Number.isNaN(parsedDate.getTime())

    const timePart = hasValidDate
      ? new Intl.DateTimeFormat('id-ID', {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
        }).format(parsedDate)
      : 'Baru saja'

    const datePart = hasValidDate
      ? new Intl.DateTimeFormat('id-ID', {
          day: '2-digit',
          month: 'short',
          year: 'numeric',
        }).format(parsedDate)
      : ''

    const lowerAct = activityText.toLowerCase()
    const type =
      lowerAct.includes('keluar') || lowerAct.includes('bayar')
        ? 'keluar'
        : 'masuk'

    const initials =
      userName
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase())
        .join('') || 'PF'

    const plate = cleanText(item.plat_nomor) || cleanText(item.no_plat)
    const vehicle = cleanText(item.jenis_kendaraan) || cleanText(item.merk)
    const zone = cleanText(item.nama_area) || cleanText(item.area)

    return {
      id: item.id_log ?? item.id ?? idx,
      time: timePart,
      ago: datePart,
      initials,
      avatarCls:
        type === 'keluar'
          ? 'bg-[#F3E8E2] text-[#8C4A32]'
          : 'bg-[#D5EC96] text-[#50631D]',
      user: userName,
      role: roleLabel,
      plate,
      vehicle,
      activity: activityText,
      zone,
      amount:
        Number(item.total_biaya ?? item.biaya_total) > 0
          ? formatRupiah(item.total_biaya ?? item.biaya_total)
          : '',
      status: cleanText(item.status) || 'Tercatat',
      statusCls:
        type === 'keluar'
          ? 'text-[#50631D]'
          : 'bg-[#D5EC96]/75 text-[#50631D]',
      type,
    }
  })
)

const filteredAllLogs = computed(() => {
  if (activeLogFilter.value === 'semua') return normalizedLogs.value
  return normalizedLogs.value.filter((l) => l.type === activeLogFilter.value)
})

const totalLogsCount = computed(() =>
  serverPaginated.value ? serverTotalLogs.value : filteredAllLogs.value.length
)

const lastPage = computed(() => {
  if (serverPaginated.value) return Math.max(1, serverLastPage.value)
  return Math.max(1, Math.ceil(filteredAllLogs.value.length / LOGS_PER_PAGE))
})

const pageNumbers = computed(() => {
  const total = lastPage.value
  const curr = currentPage.value
  const start = Math.max(1, curr - 1)
  const end = Math.min(total, start + 2)
  const adjustedStart = Math.max(1, end - 2)
  const pages = []
  for (let p = adjustedStart; p <= end; p += 1) {
    pages.push(p)
  }
  return pages
})

const paginatedLogs = computed(() => {
  if (serverPaginated.value) {
    return filteredAllLogs.value.slice(0, LOGS_PER_PAGE)
  }
  const start = (currentPage.value - 1) * LOGS_PER_PAGE
  return filteredAllLogs.value.slice(start, start + LOGS_PER_PAGE)
})

watch(activeLogFilter, () => {
  currentPage.value = 1
})

const goToPage = async (page) => {
  const target = Math.min(Math.max(1, page), lastPage.value)
  if (target === currentPage.value) return
  currentPage.value = target
  if (serverPaginated.value) {
    await fetchDashboardData(target)
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
    <!-- Heading Page -->
    <motion.div
      :initial="{ opacity: 0, y: 10 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }"
      class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end will-change-transform"
    >
      <div>
        <p class="text-[9.5px] font-bold tracking-[0.16em] text-olive">
          OPERASIONAL PUSAT
        </p>
        <h1 class="mt-1.5 text-xl font-extrabold tracking-tight text-[#0F172A] sm:text-2xl">
          Dasbor Manajemen Parkir
        </h1>
      </div>

      <div class="flex items-center gap-3 self-start sm:self-auto">
        <button
          type="button"
          @click="fetchDashboardData(currentPage)"
          :disabled="isLoading"
          class="inline-flex cursor-pointer items-center gap-1.5 rounded-2xl border border-[#E2E8F0] bg-white px-3.5 py-2 text-[11px] font-semibold text-[#475569] shadow-2xs transition-colors hover:border-olive hover:text-olive disabled:opacity-60"
        >
          <RefreshCw :size="13" :class="isLoading ? 'animate-spin' : ''" />
          <span>{{ isLoading ? 'Memuat...' : 'Refresh' }}</span>
        </button>

        <div class="inline-flex items-center gap-2.5 rounded-2xl border border-[#E2E8F0] bg-white px-3.5 py-2 shadow-2xs">
          <span class="flex h-7 w-7 items-center justify-center rounded-full bg-olive text-[11px] font-bold text-white">
            {{ adminInitials }}
          </span>
          <div>
            <p class="text-[11.5px] font-bold text-[#0F172A]">{{ adminDisplayName }}</p>
            <p class="text-[9.5px] capitalize text-[#64748B]">{{ currentRole }}</p>
          </div>
        </div>
      </div>
    </motion.div>

    <p
      v-if="fetchError"
      class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-[11.5px] font-semibold text-rose-700"
    >
      {{ fetchError }}
    </p>

    <!-- 4 Kartu KPI -->
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
      <motion.article
        v-for="(kpi, index) in kpiCards"
        :key="kpi.label"
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{
          duration: 0.35,
          delay: index * 0.05,
          ease: [0.22, 1, 0.36, 1],
        }"
        :while-hover="{ y: -2 }"
        class="rounded-[22px] border border-[#E2E8F0] bg-white p-5 shadow-2xs transition-colors hover:border-olive/35 will-change-transform"
      >
        <div class="flex items-start justify-between gap-2">
          <p class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            {{ kpi.label }}
          </p>
          <span
            v-if="kpi.status"
            class="rounded-full bg-[#161F00] px-2.5 py-0.5 text-[9.5px] font-semibold text-[#D5EC96]"
          >
            {{ kpi.status }}
          </span>
          <span
            v-else-if="kpi.percent"
            :style="occupancyRingStyle"
            class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full p-[3px]"
          >
            <span class="flex h-full w-full items-center justify-center rounded-full bg-white text-[9px] font-extrabold text-olive">
              {{ kpi.percent }}
            </span>
          </span>
          <span
            v-else
            class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#64748B]"
          >
            <component :is="kpi.icon" :size="15" />
          </span>
        </div>

        <div class="mt-3.5 flex items-baseline gap-1.5">
          <p class="text-xl font-extrabold tracking-tight text-[#0F172A]">
            {{ kpi.value }}
          </p>
          <span v-if="kpi.subValue" class="text-xs font-semibold text-[#94A3B8]">
            {{ kpi.subValue }}
          </span>
          <span v-if="kpi.unit" class="text-[11px] font-medium text-[#64748B]">
            {{ kpi.unit }}
          </span>
        </div>

        <div class="mt-4">
          <span
            v-if="kpi.subBadge"
            class="inline-flex items-center rounded-full bg-[#D5EC96]/60 px-2.5 py-1 text-[9.5px] font-bold text-olive"
          >
            {{ kpi.sub }}
          </span>

          <div v-else-if="kpi.percent" class="space-y-1.5">
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
              <div
                class="h-full rounded-full bg-olive transition-all duration-500"
                :style="{ width: `${kpi.percentWidth ?? 0}%` }"
              />
            </div>
            <p class="text-[10.5px] font-medium text-[#64748B]">{{ kpi.foot }}</p>
          </div>

          <p v-else-if="kpi.foot" class="text-[10.5px] font-medium text-[#64748B]">
            {{ kpi.foot }}
          </p>
          <div v-else-if="kpi.roles" class="flex flex-wrap gap-1.5">
            <span
              v-for="r in kpi.roles"
              :key="r.label"
              :class="['rounded-full px-2.5 py-0.5 text-[9.5px] font-bold', r.cls]"
            >
              {{ r.label }}
            </span>
          </div>
        </div>
      </motion.article>
    </div>

    <!-- Middle Grid: Kapasitas Area Terbaru (Kiri) + Aksi Cepat & Ketersediaan Slot (Kanan) -->
    <div class="mt-7 grid gap-6 lg:grid-cols-[1.55fr_0.95fr]">
      <motion.section
        :initial="{ opacity: 0, y: 14 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.4, delay: 0.1 }"
        class="rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#D5EC96] text-xs font-extrabold text-olive">
              P
            </span>
            <div>
              <h2 class="text-sm font-bold text-[#0F172A]">Kapasitas Area Terbaru</h2>
              <p class="mt-0.5 text-[11px] text-[#64748B]">
                Menampilkan maksimal {{ MAX_DASHBOARD_ZONES }} area parkir terbaru dari {{ allZones.length }} zona terdaftar.
              </p>
            </div>
          </div>

          <button
            type="button"
            @click="router.push('/admin/area-parkir')"
            class="shrink-0 cursor-pointer text-[11px] font-bold text-olive transition-colors hover:text-olive-dark"
          >
            Semua Area →
          </button>
        </div>

        <div v-if="recentZones.length > 0" class="mt-5 space-y-4">
          <div
            v-for="zone in recentZones"
            :key="zone.id"
            class="rounded-2xl border border-[#E2E8F0]/80 bg-[#F8FAFC]/75 p-4 transition-colors hover:border-olive/30"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex items-center gap-3 min-w-0">
                <span
                  :class="[
                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-xl text-[11px] font-extrabold',
                    zone.badgeCls,
                  ]"
                >
                  {{ zone.code }}
                </span>
                <div class="min-w-0">
                  <p class="truncate text-xs font-bold text-[#0F172A]">
                    {{ zone.name }}
                  </p>
                  <p v-if="zone.deskripsi" class="truncate text-[10px] text-[#64748B]">
                    {{ zone.deskripsi }}
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-2.5 text-[11px]">
                <span class="font-bold text-[#0F172A]">
                  {{ zone.used }}<span class="font-normal text-[#94A3B8]">/{{ zone.total }} Slot</span>
                </span>
                <span class="font-semibold text-[#64748B]">{{ zone.percent }}%</span>
                <span
                  :class="[
                    'rounded-full px-2.5 py-0.5 text-[9.5px] font-bold',
                    zone.statusCls,
                  ]"
                >
                  {{ zone.status }}
                </span>
              </div>
            </div>

            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-[#E2E8F0]">
              <div
                :class="['h-full rounded-full transition-all duration-500', zone.barCls]"
                :style="{ width: `${zone.percent}%` }"
              />
            </div>
          </div>
        </div>
        <div
          v-else
          class="mt-5 rounded-2xl border border-dashed border-[#E2E8F0] bg-[#F8FAFC] px-4 py-8 text-center text-[11.5px] text-[#64748B]"
        >
          {{
            isLoading
              ? 'Memuat data area parkir...'
              : 'Belum ada data area parkir di database.'
          }}
        </div>

        <p class="mt-5 flex items-center gap-2 text-[10.5px] text-[#64748B]">
          <ShieldCheck :size="13" class="text-olive" />
          Sistem pembatasan tiket otomatis beroperasi bila zona mencapai kapasitas maksimal.
        </p>
      </motion.section>

      <div class="flex flex-col gap-5">
        <motion.section
          :initial="{ opacity: 0, y: 14 }"
          :animate="{ opacity: 1, y: 0 }"
          :transition="{ duration: 0.4, delay: 0.14 }"
          class="flex-1 rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
        >
          <p class="text-[9.5px] font-bold tracking-[0.16em] text-olive">
            AKSI CEPAT ADMIN
          </p>
          <h2 class="mt-1 text-sm font-bold text-[#0F172A]">Kelola Data Utama</h2>
          <p class="mt-1 text-[11px] text-[#64748B]">
            Akses cepat ke pengaturan inti sistem dan data master.
          </p>

          <div class="mt-4 space-y-2.5">
            <motion.button
              v-for="action in quickActions"
              :key="action.label"
              type="button"
              @click="handleQuickAction(action)"
              :while-hover="{ x: 2 }"
              :while-press="{ scale: 0.98 }"
              :transition="{ type: 'spring', stiffness: 420, damping: 24 }"
              :class="[
                'group flex w-full cursor-pointer items-center justify-between rounded-full px-4 py-2.5 text-[11.5px] font-semibold transition-colors will-change-transform',
                action.primary
                  ? 'bg-olive text-white hover:bg-olive-dark'
                  : 'border border-[#E2E8F0] bg-[#F8FAFC] text-[#334155] hover:border-olive/40 hover:bg-[#F4F8EA] hover:text-olive',
              ]"
            >
              <span class="flex items-center gap-2.5">
                <component :is="action.icon" :size="14" />
                <span>{{ action.label }}</span>
              </span>

              <span
                v-if="action.tag"
                class="rounded-full bg-[#F3E8E2] px-2 py-0.5 text-[9px] font-bold text-[#8C4A32]"
              >
                {{ action.tag }}
              </span>
              <ArrowRight
                v-else-if="action.primary"
                :size="14"
                class="transition-transform duration-200 group-hover:translate-x-1"
              />
            </motion.button>
          </div>
        </motion.section>

        <motion.div
          :initial="{ opacity: 0, y: 14 }"
          :animate="{ opacity: 1, y: 0 }"
          :transition="{ duration: 0.4, delay: 0.18 }"
          class="flex items-center justify-between rounded-[22px] bg-[#0B1105] px-6 py-4 text-white shadow-2xs will-change-transform"
        >
          <div>
            <p class="text-[9.5px] font-bold tracking-[0.16em] text-[#D5EC96]">
              ● SLOT TERSEDIA SAAT INI
            </p>
            <p class="mt-1 text-base font-extrabold tracking-tight">
              {{ formatAngka(totalFreeSlots) }} Slot Kosong
              <span class="ml-1 text-xs font-medium text-white/70">
                ({{ occupancyPercentLabel }} terpakai)
              </span>
            </p>
          </div>
          <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/10 text-[#D5EC96]">
            <ParkingSquare :size="19" />
          </span>
        </motion.div>
      </div>
    </div>

    <!-- Log Aktivitas Terbaru (Maks 10 per halaman) -->
    <motion.section
      :initial="{ opacity: 0, y: 16 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.42, delay: 0.2 }"
      class="mt-7 rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
    >
      <div class="flex flex-col justify-between gap-4 border-b border-[#E2E8F0] pb-5 sm:flex-row sm:items-center">
        <div>
          <h2 class="text-sm font-bold text-[#0F172A]">Log Aktivitas Terbaru</h2>
          <p class="mt-0.5 text-[11px] text-[#64748B]">
            Menampilkan maksimal 10 catatan aktivitas per halaman.
          </p>
        </div>

        <div class="flex flex-wrap gap-1.5 rounded-full border border-[#E2E8F0] bg-[#F8FAFC] p-1 text-[11px]">
          <button
            v-for="tab in [
              { id: 'semua', label: 'Semua Log' },
              { id: 'masuk', label: 'Masuk / Sistem' },
              { id: 'keluar', label: 'Keluar & Bayar' },
            ]"
            :key="tab.id"
            type="button"
            @click="activeLogFilter = tab.id"
            :class="[
              'cursor-pointer rounded-full px-3.5 py-1 font-semibold transition-colors',
              activeLogFilter === tab.id
                ? 'bg-olive text-white'
                : 'text-[#64748B] hover:text-olive',
            ]"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[780px] border-collapse text-left">
          <thead>
            <tr class="border-b border-slate-100 text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
              <th class="py-3 pr-4">WAKTU</th>
              <th class="py-3 pr-4">PENGGUNA</th>
              <th class="py-3 pr-4">KENDARAAN</th>
              <th class="py-3 pr-4">AKTIVITAS</th>
              <th class="py-3 text-right">STATUS</th>
            </tr>
          </thead>
          <tbody v-if="paginatedLogs.length > 0" class="divide-y divide-slate-100 text-[11.5px]">
            <tr
              v-for="row in paginatedLogs"
              :key="row.id"
              class="transition-colors hover:bg-[#F8FAFC]/80"
            >
              <td class="py-3.5 pr-4 align-middle">
                <p class="text-[11px] font-bold text-[#0F172A]">{{ row.time }}</p>
                <p v-if="row.ago" class="mt-0.5 text-[9.5px] text-[#94A3B8]">
                  {{ row.ago }}
                </p>
              </td>

              <td class="py-3.5 pr-4 align-middle">
                <div class="flex items-center gap-2.5">
                  <span
                    :class="[
                      'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                      row.avatarCls,
                    ]"
                  >
                    {{ row.initials }}
                  </span>
                  <div>
                    <p class="font-bold text-[#0F172A]">{{ row.user }}</p>
                    <p class="text-[9.5px] capitalize text-[#94A3B8]">
                      {{ row.role }}
                    </p>
                  </div>
                </div>
              </td>

              <td class="py-3.5 pr-4 align-middle">
                <template v-if="row.plate || row.vehicle">
                  <span
                    v-if="row.plate"
                    class="inline-block rounded-md border border-[#E2E8F0] bg-[#F8FAFC] px-2 py-0.5 font-mono text-[10.5px] font-bold text-[#0F172A]"
                  >
                    {{ row.plate }}
                  </span>
                  <p v-if="row.vehicle" class="mt-1 text-[10px] text-[#64748B]">
                    {{ row.vehicle }}
                  </p>
                </template>
                <span
                  v-else
                  class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[9.5px] font-medium text-[#94A3B8]"
                >
                  -
                </span>
              </td>

              <td class="py-3.5 pr-4 align-middle">
                <p class="font-semibold text-[#0F172A]">{{ row.activity }}</p>
                <p v-if="row.zone" class="mt-0.5 text-[10px] text-[#94A3B8]">
                  {{ row.zone }}
                </p>
              </td>

              <td class="py-3.5 text-right align-middle">
                <p v-if="row.amount" class="mb-1 text-[11px] font-bold text-[#0F172A]">
                  {{ row.amount }}
                </p>
                <span
                  :class="[
                    'inline-block rounded-full px-2.5 py-0.5 text-[9.5px] font-bold',
                    row.statusCls,
                  ]"
                >
                  {{ row.status }}
                </span>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="5" class="py-9 text-center text-[11.5px] text-[#64748B]">
                {{
                  isLoading
                    ? 'Memuat log aktivitas...'
                    : 'Belum ada log aktivitas untuk ditampilkan.'
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer Tabel & Pagination (Maks 10 / halaman) -->
      <div class="mt-5 flex flex-col items-center justify-between gap-3 border-t border-slate-100 pt-4 text-[11px] text-[#64748B] sm:flex-row">
        <p>
          Menampilkan <span class="font-bold text-[#0F172A]">{{ paginatedLogs.length }}</span> dari
          <span class="font-bold text-[#0F172A]">{{ totalLogsCount }}</span> log aktivitas (maks. {{ LOGS_PER_PAGE }}/hal)
        </p>

        <div class="flex items-center gap-1.5">
          <button
            type="button"
            :disabled="currentPage <= 1 || isLoading"
            @click="goToPage(currentPage - 1)"
            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-[#E2E8F0] text-[#64748B] transition-colors hover:border-olive hover:text-olive disabled:cursor-not-allowed disabled:opacity-40"
          >
            <ChevronLeft :size="13" />
          </button>

          <button
            v-for="p in pageNumbers"
            :key="p"
            type="button"
            :disabled="isLoading"
            @click="goToPage(p)"
            :class="[
              'h-7 min-w-7 cursor-pointer rounded-full px-2.5 text-[10.5px] font-bold transition-colors',
              currentPage === p
                ? 'bg-olive text-white'
                : 'border border-[#E2E8F0] text-[#475569] hover:border-olive hover:text-olive',
            ]"
          >
            {{ p }}
          </button>

          <button
            type="button"
            :disabled="currentPage >= lastPage || isLoading"
            @click="goToPage(currentPage + 1)"
            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-[#E2E8F0] text-[#64748B] transition-colors hover:border-olive hover:text-olive disabled:cursor-not-allowed disabled:opacity-40"
          >
            <ChevronRight :size="13" />
          </button>
        </div>
      </div>
    </motion.section>
  </DashboardLayout>
</template>

<style>
.text-olive {
  color: #50631D;
}
.bg-olive {
  background-color: #50631D;
}
.bg-olive-dark {
  background-color: #161F00;
}
</style>