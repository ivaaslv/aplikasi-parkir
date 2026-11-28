<script setup>
import {
  CarFront,
  ParkingCircle,
  LogOut,
  Wallet,
  LogIn,
  QrCode,
  RefreshCw,
  ShieldCheck,
  TicketX,
  ArrowRight,
  Clock,
  CheckCircle2,
} from 'lucide-vue-next'
import { motion } from 'motion-v'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '../layouts/DashboardLayout.vue'
import api from '../../services/api'
import { getAuthUser } from '../../router'

const MAX_DASHBOARD_AREAS = 4

const router = useRouter()
const authState = getAuthUser()

const currentRole = ref(String(authState.role || 'petugas').toLowerCase())
const authUser = ref(authState.user)
const activeMenu = ref('dashboard-petugas')

const isLoading = ref(false)
const fetchError = ref('')
const lastSyncLabel = ref('Baru saja')

const transactionsRaw = ref([])
const areasRaw = ref([])
const tarifsRaw = ref([])
const dendaConfig = ref(null)

const petugasDisplayName = computed(
  () =>
    authUser.value?.nama_lengkap ||
    authUser.value?.nama ||
    authUser.value?.name ||
    authUser.value?.username ||
    'Petugas Gerbang'
)

const petugasInitials = computed(
  () =>
    petugasDisplayName.value
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase())
      .join('') || 'PG'
)

const operatorBadgeCode = computed(() => {
  const idNum = Number(authUser.value?.id_user ?? authUser.value?.id ?? 1) || 1
  return `USER-${String(idNum).padStart(4, '0')}`
})

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

const extractList = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  if (Array.isArray(payload?.areas)) return payload.areas
  if (Array.isArray(payload?.data?.areas)) return payload.data.areas
  return []
}

const isSameToday = (dateInput) => {
  if (!dateInput) return false
  const d = new Date(dateInput)
  if (Number.isNaN(d.getTime())) return false
  const now = new Date()
  return (
    d.getFullYear() === now.getFullYear() &&
    d.getMonth() === now.getMonth() &&
    d.getDate() === now.getDate()
  )
}

const formatJamMenit = (dateInput) => {
  if (!dateInput) return '-'
  const d = new Date(dateInput)
  if (Number.isNaN(d.getTime())) return String(dateInput)
  return new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(d)
}

const navigateSafe = async (candidates) => {
  for (const path of candidates) {
    const resolved = router.resolve(path)
    if (resolved.matched.length > 0) {
      await router.push(path)
      return
    }
  }
}

const goToParkirMasuk = async () => {
  activeMenu.value = 'parkir-masuk'
  await navigateSafe(['/petugas/parkir-masuk', '/operasional/parkir-masuk'])
}

const goToParkirKeluar = async () => {
  activeMenu.value = 'parkir-keluar'
  await navigateSafe([
    '/petugas/parkir-keluar',
    '/petugas/parkir-keluar-scan',
    '/operasional/parkir-keluar',
  ])
}

const goToDaftarTransaksi = async () => {
  activeMenu.value = 'daftar-transaksi'
  await navigateSafe(['/petugas/transaksi', '/petugas/daftar-transaksi'])
}

const goToAreaParkirPetugas = async () => {
  activeMenu.value = 'area-parkir-petugas'
  await navigateSafe(['/petugas/area-parkir'])
}

const handleKeydown = (event) => {
  const tag = String(event.target?.tagName || '').toLowerCase()
  const isTyping =
    tag === 'input' ||
    tag === 'textarea' ||
    tag === 'select' ||
    Boolean(event.target?.isContentEditable)

  if (event.key === 'F1') {
    if (!isTyping) event.preventDefault()
    goToParkirMasuk()
  } else if (event.key === 'F2') {
    if (!isTyping) event.preventDefault()
    goToParkirKeluar()
  }
}

const fetchPetugasDashboard = async () => {
  isLoading.value = true
  fetchError.value = ''

  const areaEndpoint =
    currentRole.value === 'admin' ? '/admin/area-parkir' : '/operasional/area-parkir'
  const tarifEndpoint =
    currentRole.value === 'admin' ? '/admin/tarif' : '/operasional/tarif'

  try {
    const [trxRes, areaRes, tarifRes, dendaRes] = await Promise.allSettled([
      api.get('/operasional/transaksi'),
      api.get(areaEndpoint),
      api.get(tarifEndpoint),
      api.get('/operasional/pengaturan-parkir'),
    ])

    if (trxRes.status === 'fulfilled') {
      transactionsRaw.value = extractList(trxRes.value.data)
    }

    if (areaRes.status === 'fulfilled') {
      areasRaw.value = extractList(areaRes.value.data)
    }

    if (tarifRes.status === 'fulfilled') {
      tarifsRaw.value = extractList(tarifRes.value.data)
    }

    if (dendaRes.status === 'fulfilled') {
      dendaConfig.value = dendaRes.value.data?.data ?? dendaRes.value.data
    }

    if (trxRes.status === 'rejected' && areaRes.status === 'rejected') {
      fetchError.value =
        'Gagal memuat data operasional dari server. Pastikan koneksi API dan sesi login aktif.'
    }

    lastSyncLabel.value = new Intl.DateTimeFormat('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    }).format(new Date())
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
  window.addEventListener('keydown', handleKeydown)

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

  await fetchPetugasDashboard()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})

const activeParkirList = computed(() =>
  transactionsRaw.value.filter(
    (trx) =>
      String(trx.status || '').toLowerCase() === 'masuk' ||
      (!trx.waktu_keluar && String(trx.status || '').toLowerCase() !== 'keluar')
  )
)

const keluarHariIniList = computed(() =>
  transactionsRaw.value.filter(
    (trx) =>
      String(trx.status || '').toLowerCase() === 'keluar' &&
      isSameToday(trx.waktu_keluar || trx.updated_at)
  )
)

const pendapatanHariIni = computed(() =>
  keluarHariIniList.value.reduce(
    (sum, trx) => sum + Number(trx.biaya_total ?? 0),
    0
  )
)

const zonaCards = computed(() =>
  areasRaw.value.map((area, idx) => {
    const total = Math.max(0, Number(area.kapasitas ?? 0))
    const used = Math.max(0, Number(area.terisi ?? 0))
    const sisa = Math.max(0, total - used)
    const percent = total > 0 ? Math.min(100, Math.round((used / total) * 100)) : 0

    let badgeText = `Sisa ${sisa}`
    let badgeCls = 'bg-[#F1F5F9] text-[#334155]'
    let dotCls = 'bg-slate-500'
    let barCls = 'bg-[#50631D]'

    if (percent >= 85) {
      badgeText = 'Hampir Penuh'
      badgeCls = 'bg-rose-100 text-rose-700'
      dotCls = 'bg-rose-600'
      barCls = 'bg-rose-600'
    } else if (percent >= 75) {
      badgeText = 'Ramai'
      badgeCls = 'bg-amber-100 text-amber-800'
      dotCls = 'bg-amber-500'
      barCls = 'bg-olive'
    }

    return {
      id: area.id_area ?? area.id ?? idx,
      name: area.nama_area || `Zona ${idx + 1}`,
      subtitle:
        area.deskripsi ||
        `Jenis: ${String(area.jenis_kendaraan || 'semua').toUpperCase()}`,
      used,
      total,
      sisa,
      percent,
      badgeText,
      badgeCls,
      dotCls,
      barCls,
    }
  })
)

const displayedZonaCards = computed(() =>
  zonaCards.value.slice(0, MAX_DASHBOARD_AREAS)
)

const totalKapasitas = computed(() =>
  zonaCards.value.reduce((sum, z) => sum + z.total, 0)
)
const totalTerisi = computed(() =>
  zonaCards.value.reduce((sum, z) => sum + z.used, 0)
)
const totalSlotKosong = computed(() =>
  Math.max(0, totalKapasitas.value - totalTerisi.value)
)
const okupansiPersen = computed(() =>
  totalKapasitas.value > 0
    ? Math.min(100, Math.round((totalTerisi.value / totalKapasitas.value) * 100))
    : 0
)

const recentActiveVehicles = computed(() =>
  activeParkirList.value.slice(0, 5).map((trx, idx) => ({
    id: trx.id_parkir ?? trx.id ?? idx,
    plat: trx.kendaraan?.plat_nomor || '-',
    jenis: String(trx.kendaraan?.jenis_kendaraan || 'kendaraan').toUpperCase(),
    area: trx.area_parkir?.nama_area || trx.areaParkir?.nama_area || 'Area Parkir',
    jamMasuk: formatJamMenit(trx.waktu_masuk),
  }))
)
</script>

<template>
  <DashboardLayout
    :current-role="currentRole"
    :active-menu="activeMenu"
    :user="authUser"
    @update:active-menu="(val) => (activeMenu = val)"
    @logout="handleLogout"
  >
    <!-- Banner Identitas Petugas Gerbang -->
    <motion.div
      :initial="{ opacity: 0, y: 10 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.35 }"
      class="flex flex-col justify-between gap-4 rounded-[24px] border border-[#E2E8F0] bg-white px-6 py-4 shadow-2xs sm:flex-row sm:items-center will-change-transform"
    >
      <div class="flex items-center gap-3.5">
        <div class="relative">
          <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF6C9] text-sm font-extrabold text-olive">
            {{ petugasInitials }}
          </span>
          <span
            class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"
            title="Shift Aktif"
          />
        </div>

        <div>
          <h1 class="text-base font-extrabold tracking-tight text-[#0F172A] sm:text-lg">
            Petugas Gerbang
          </h1>
          <p class="mt-0.5 text-[11px] text-[#64748B]">
            <span class="font-semibold text-[#334155]">{{ petugasDisplayName }}</span>
            <span class="mx-1.5 text-[#CBD5E1]">•</span>
            <span class="font-mono text-[10.5px] text-[#64748B]">
              ID-OP: {{ operatorBadgeCode }}
            </span>
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2.5 self-start sm:self-auto">
        <span class="hidden text-[10.5px] text-[#94A3B8] md:inline">
          Sinkron: {{ lastSyncLabel }}
        </span>
        <button
          type="button"
          @click="fetchPetugasDashboard"
          :disabled="isLoading"
          class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2 text-[11px] font-bold text-[#334155] transition-colors hover:border-olive hover:bg-[#F4F8EA] hover:text-olive disabled:opacity-60"
        >
          <RefreshCw :size="13" :class="isLoading ? 'animate-spin' : ''" />
          <span>{{ isLoading ? 'Menyinkronkan...' : 'Sync' }}</span>
        </button>
      </div>
    </motion.div>

    <p
      v-if="fetchError"
      class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700"
    >
      {{ fetchError }}
    </p>

    <!-- 4 Kartu KPI Operasional -->
    <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.34, delay: 0.04 }"
        class="rounded-[22px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            TERPARKIR AKTIF
          </span>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#64748B]">
            <CarFront :size="15" />
          </span>
        </div>

        <div class="mt-3 flex items-baseline gap-1.5">
          <p class="text-2xl font-extrabold tracking-tight text-[#0F172A]">
            {{ formatAngka(activeParkirList.length) }}
          </p>
          <span class="text-xs font-medium text-[#64748B]">unit</span>
        </div>

        <div class="mt-4">
          <div class="h-1.5 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
            <div
              class="h-full rounded-full bg-olive transition-all duration-500"
              :style="{ width: `${okupansiPersen}%` }"
            />
          </div>
          <div class="mt-2 flex items-center justify-between text-[10.5px]">
            <span class="text-[#64748B]">Okupansi Total</span>
            <span class="font-bold text-olive">{{ okupansiPersen }}%</span>
          </div>
        </div>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.34, delay: 0.08 }"
        class="rounded-[22px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            SLOT KOSONG
          </span>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#EAF6C9] text-xs font-extrabold text-olive">
            P
          </span>
        </div>

        <div class="mt-3 flex items-baseline gap-1.5">
          <p class="text-2xl font-extrabold tracking-tight text-olive">
            {{ formatAngka(totalSlotKosong) }}
          </p>
          <span class="text-xs font-medium text-[#64748B]">bays</span>
        </div>

        <div class="mt-4">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-[#EAF6C9]/80 px-3 py-1 text-[10px] font-bold text-[#3E4E14]">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600" />
            {{ okupansiPersen >= 85 ? 'Kapasitas Padat' : 'Kapasitas Normal • Lancar' }}
          </span>
        </div>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.34, delay: 0.12 }"
        class="rounded-[22px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            KELUAR HARI INI
          </span>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#64748B]">
            <LogOut :size="15" />
          </span>
        </div>

        <div class="mt-3 flex items-baseline gap-1.5">
          <p class="text-2xl font-extrabold tracking-tight text-[#0F172A]">
            {{ formatAngka(keluarHariIniList.length) }}
          </p>
          <span class="text-xs font-medium text-[#64748B]">kendaraan</span>
        </div>

        <div class="mt-4">
          <span class="inline-flex items-center gap-1 rounded-full bg-[#F4F8EA] px-3 py-1 text-[10px] font-bold text-olive">
            <CheckCircle2 :size="11" />
            Transaksi Selesai Hari Ini
          </span>
        </div>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.34, delay: 0.16 }"
        class="rounded-[22px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            PENDAPATAN
          </span>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#F3E8E2] text-[#8C4A32]">
            <Wallet :size="15" />
          </span>
        </div>

        <div class="mt-3">
          <p class="text-xl font-extrabold tracking-tight text-[#0F172A] sm:text-2xl">
            {{ formatRupiah(pendapatanHariIni) }}
          </p>
        </div>

        <div class="mt-4 flex items-center justify-between gap-2 text-[10px]">
          <span class="font-medium text-[#64748B]">Shift Hari Ini</span>
          <button
            type="button"
            @click="goToDaftarTransaksi"
            class="cursor-pointer font-bold text-olive hover:text-olive-dark"
          >
            Riwayat →
          </button>
        </div>
      </motion.article>
    </div>

    <!-- 2 Kartu Aksi Utama: Parkir Masuk [F1] & Parkir Keluar & Scan [F2] -->
    <div class="mt-6 grid gap-5 lg:grid-cols-2">
      <motion.section
        :initial="{ opacity: 0, y: 14 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.38, delay: 0.18 }"
        class="flex flex-col justify-between rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
      >
        <div>
          <div class="flex items-center justify-between gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[#EAF6C9] px-3 py-1 text-[10px] font-bold text-[#3E4E14]">
              <span class="h-1.5 w-1.5 rounded-full bg-emerald-600" />
              Kamera LPR &amp; Sensor Aktif
            </span>
            <kbd class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-2 py-0.5 font-mono text-[10px] font-bold text-[#64748B]">
              F1
            </kbd>
          </div>

          <h2 class="mt-4 text-lg font-extrabold text-[#0F172A]">Parkir Masuk</h2>
          <p class="mt-1.5 text-xs leading-relaxed text-[#64748B]">
            Catat kendaraan masuk, verifikasi plat nomor serta ketersediaan area parkir, dan terbitkan tiket masuk secara langsung.
          </p>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
          <button
            type="button"
            @click="goToParkirMasuk"
            class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-olive px-5 py-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-olive-dark"
          >
            <LogIn :size="15" />
            <span>Proses Masuk</span>
            <ArrowRight :size="14" />
          </button>
          <span class="text-[10.5px] text-[#94A3B8]">
            Tekan <kbd class="font-mono font-bold text-[#475569]">[F1]</kbd> untuk aksi instan
          </span>
        </div>
      </motion.section>

      <motion.section
        :initial="{ opacity: 0, y: 14 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.38, delay: 0.22 }"
        class="flex flex-col justify-between rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
      >
        <div>
          <div class="flex items-center justify-between gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-3 py-1 text-[10px] font-bold text-sky-700">
              <span class="h-1.5 w-1.5 rounded-full bg-sky-500" />
              Scanner Optik &amp; RFID Siap
            </span>
            <kbd class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-2 py-0.5 font-mono text-[10px] font-bold text-[#64748B]">
              F2
            </kbd>
          </div>

          <h2 class="mt-4 text-lg font-extrabold text-[#0F172A]">Parkir Keluar &amp; Scan</h2>
          <p class="mt-1.5 text-xs leading-relaxed text-[#64748B]">
            Proses kendaraan keluar berdasarkan plat nomor atau ID transaksi, hitung durasi, tarif, serta denda karcis hilang otomatis.
          </p>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
          <button
            type="button"
            @click="goToParkirKeluar"
            class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-[#0F172A] px-5 py-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-[#1E293B]"
          >
            <QrCode :size="15" />
            <span>Proses Keluar</span>
            <ArrowRight :size="14" />
          </button>
          <p class="mt-2 text-[10.5px] text-[#94A3B8]">
            Tekan <kbd class="font-mono font-bold text-[#475569]">[F2]</kbd> untuk pembacaan scanner
          </p>
        </div>
      </motion.section>
    </div>

    <!-- Ringkasan Slot Per Area (Maksimal 4 Area) -->
    <motion.section
      :initial="{ opacity: 0, y: 16 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.4, delay: 0.26 }"
      class="mt-6 rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
    >
      <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
        <div>
          <h2 class="text-base font-extrabold text-[#0F172A]">Ringkasan Slot Per Area</h2>
          <p class="text-[11px] text-[#64748B]">
            Menampilkan maksimal {{ MAX_DASHBOARD_AREAS }} area dari {{ zonaCards.length }} zona terdaftar secara real-time
          </p>
        </div>

        <div class="flex items-center gap-3 text-[11px]">
          <span class="font-bold text-[#475569]">
            Total Kapasitas: {{ formatAngka(totalKapasitas) }} Slot
          </span>
          <button
            v-if="zonaCards.length > MAX_DASHBOARD_AREAS"
            type="button"
            @click="goToAreaParkirPetugas"
            class="cursor-pointer font-bold text-olive hover:text-olive-dark"
          >
            Lihat Semua →
          </button>
        </div>
      </div>

      <div v-if="displayedZonaCards.length > 0" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div
          v-for="zona in displayedZonaCards"
          :key="zona.id"
          class="flex flex-col justify-between rounded-[20px] border border-[#E2E8F0] bg-[#FAFBF9] p-4"
        >
          <div>
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <span :class="['h-2 w-2 rounded-full', zona.dotCls]" />
                <span class="text-xs font-extrabold text-[#0F172A]">{{ zona.name }}</span>
              </div>
              <span :class="['rounded-full px-2.5 py-0.5 text-[9.5px] font-bold', zona.badgeCls]">
                {{ zona.badgeText }}
              </span>
            </div>
            <p class="mt-1 line-clamp-1 text-[10.5px] text-[#64748B]">
              {{ zona.subtitle }}
            </p>
          </div>

          <div class="mt-4">
            <div class="flex items-baseline justify-between">
              <p class="text-base font-extrabold text-[#0F172A]">
                {{ zona.used }}
                <span class="text-[11px] font-normal text-[#94A3B8]">/ {{ zona.total }} bay</span>
              </p>
              <span class="text-[11px] font-bold text-[#475569]">{{ zona.percent }}%</span>
            </div>

            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-[#E2E8F0]">
              <div
                :class="['h-full rounded-full transition-all duration-500', zona.barCls]"
                :style="{ width: `${zona.percent}%` }"
              />
            </div>
          </div>
        </div>
      </div>

      <div
        v-else
        class="mt-5 rounded-2xl border border-dashed border-[#E2E8F0] bg-[#F8FAFC] px-4 py-7 text-center text-xs text-[#64748B]"
      >
        {{ isLoading ? 'Memuat data zona parkir...' : 'Data zona parkir belum tersedia.' }}
      </div>
    </motion.section>

    <!-- Bagian Bawah: Kendaraan Sedang Terparkir + Referensi Tarif & Denda -->
    <div class="mt-6 grid gap-6 lg:grid-cols-[1.45fr_1fr]">
      <motion.section
        :initial="{ opacity: 0, y: 16 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.42, delay: 0.3 }"
        class="rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-4">
          <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#EAF6C9] text-olive">
              <ParkingCircle :size="16" />
            </span>
            <div>
              <h3 class="text-sm font-extrabold text-[#0F172A]">
                Kendaraan Sedang Terparkir
              </h3>
              <p class="text-[10.5px] text-[#64748B]">
                Unit aktif yang siap diproses saat keluar gerbang
              </p>
            </div>
          </div>

          <button
            type="button"
            @click="goToAreaParkirPetugas"
            class="cursor-pointer text-[11px] font-bold text-olive hover:text-olive-dark"
          >
            Lihat Semua →
          </button>
        </div>

        <div v-if="recentActiveVehicles.length > 0" class="mt-4 divide-y divide-slate-100">
          <div
            v-for="item in recentActiveVehicles"
            :key="item.id"
            class="flex items-center justify-between gap-3 py-3 text-xs"
          >
            <div class="flex items-center gap-3">
              <span class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-2.5 py-1 font-mono text-[11px] font-extrabold text-[#0F172A]">
                {{ item.plat }}
              </span>
              <div>
                <p class="font-bold text-[#0F172A]">{{ item.area }}</p>
                <p class="text-[10px] text-[#64748B]">{{ item.jenis }}</p>
              </div>
            </div>

            <div class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#475569]">
              <Clock :size="12" class="text-olive" />
              <span>Masuk {{ item.jamMasuk }}</span>
            </div>
          </div>
        </div>

        <div
          v-else
          class="mt-4 rounded-2xl border border-dashed border-[#E2E8F0] bg-[#F8FAFC] px-4 py-7 text-center text-xs text-[#64748B]"
        >
          Tidak ada kendaraan yang sedang parkir saat ini.
        </div>
      </motion.section>

      <motion.section
        :initial="{ opacity: 0, y: 16 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.42, delay: 0.34 }"
        class="flex flex-col justify-between rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
      >
        <div>
          <div class="flex items-center gap-2.5 border-b border-slate-100 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#F3E8E2] text-[#8C4A32]">
              <TicketX :size="16" />
            </span>
            <div>
              <h3 class="text-sm font-extrabold text-[#0F172A]">
                Tarif &amp; Denda Karcis Hilang
              </h3>
              <p class="text-[10.5px] text-[#64748B]">
                Aturan billing aktif yang dipakai kasir keluar
              </p>
            </div>
          </div>

          <div class="mt-4 space-y-2 text-xs">
            <div
              v-for="tarif in tarifsRaw"
              :key="tarif.id_tarif"
              class="flex items-center justify-between rounded-xl bg-[#F8FAFC] px-3.5 py-2"
            >
              <span class="font-semibold capitalize text-[#334155]">
                {{ tarif.jenis_kendaraan }}
              </span>
              <span class="font-extrabold text-[#0F172A]">
                {{ formatRupiah(tarif.tarif_per_jam) }} / jam
              </span>
            </div>
          </div>

          <div class="mt-4 rounded-2xl border border-[#E2E8F0] bg-[#FAFBF9] p-3.5">
            <div class="flex items-center justify-between text-[11px]">
              <span class="font-bold text-[#0F172A]">Denda Karcis Hilang</span>
              <span class="rounded-full bg-[#EAF6C9] px-2.5 py-0.5 text-[9.5px] font-bold uppercase text-[#3E4E14]">
                {{ dendaConfig?.skema_denda || 'tambahan' }}
              </span>
            </div>
            <p class="mt-1 text-base font-extrabold text-[#8C4A32]">
              {{ formatRupiah(dendaConfig?.denda_karcis_hilang ?? 25000) }}
            </p>
          </div>
        </div>

        <p class="mt-4 flex items-center gap-1.5 text-[10.5px] text-[#64748B]">
          <ShieldCheck :size="13" class="shrink-0 text-olive" />
          <span>Pastikan plat nomor dan kondisi karcis diperiksa sebelum konfirmasi keluar.</span>
        </p>
      </motion.section>
    </div>
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