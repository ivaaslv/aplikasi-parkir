<script setup>
import JsBarcode from 'jsbarcode'
import {
  Receipt,
  Printer,
  Search,
  RefreshCw,
  Download,
  RotateCcw,
  Car,
  Bike,
  Truck,
  Sparkles,
  Wallet,
  Clock,
  FileSpreadsheet,
  X,
} from 'lucide-vue-next'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '../layouts/DashboardLayout.vue'
import api from '../../services/api'
import { getAuthUser } from '../../router'

const router = useRouter()
const authState = getAuthUser()

const currentRole = ref(String(authState.role || 'petugas').toLowerCase())
const authUser = ref(authState.user)
const activeMenu = ref('daftar-transaksi')

const transactionsRaw = ref([])
const areasRaw = ref([])
const isLoading = ref(false)
const isMountedAnim = ref(false)
const autoSyncEnabled = ref(true)

// Filter & Sorting
const searchQuery = ref('')
const statusFilter = ref('semua') // 'semua' | 'masuk' | 'keluar'
const areaFilter = ref('semua')
const dateFilter = ref('') // YYYY-MM-DD, kosong = semua tanggal
const sortBy = ref('terbaru') // 'terbaru' | 'terlama' | 'biaya_tinggi' | 'durasi_lama'
const currentPage = ref(1)
const pageSize = 8

// Modal & Cetak Ulang Struk
const activePrintTicket = ref(null)
const modalBarcodeRef = ref(null)
const printAreaBarcodeRef = ref(null)

let syncTimer = null

const extractList = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  return []
}

const formatRupiah = (amount) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(amount) || 0)

const formatJam = (dateInput) => {
  if (!dateInput) return '-'
  const d = new Date(dateInput)
  if (Number.isNaN(d.getTime())) return String(dateInput)
  return new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(d)
}

const formatTanggalJamSingkat = (dateInput) => {
  if (!dateInput) return '-'
  const d = new Date(dateInput)
  if (Number.isNaN(d.getTime())) return String(dateInput)
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d)
}

const toLocalDateKey = (dateInput) => {
  if (!dateInput) return ''
  const d = new Date(dateInput)
  if (Number.isNaN(d.getTime())) return ''
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const formatNoTiket = (idParkir) =>
  `PRK-${String(idParkir ?? 0).padStart(6, '0')}`

const hitungDurasiMenit = (waktuMasuk, waktuKeluar = null) => {
  if (!waktuMasuk) return 0
  const start = new Date(waktuMasuk)
  const end = waktuKeluar ? new Date(waktuKeluar) : new Date()
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 0
  return Math.max(1, Math.floor((end.getTime() - start.getTime()) / 60000))
}

const formatDurasiText = (menit, durasiJamBackend = 0, isKeluar = false) => {
  if (isKeluar && Number(durasiJamBackend) > 0 && menit <= 0) {
    return `${Number(durasiJamBackend)}j 0m`
  }
  const jam = Math.floor(menit / 60)
  const sisaMenit = menit % 60
  return `${jam}j ${sisaMenit}m`
}

const petugasNama = computed(
  () =>
    authUser.value?.nama_lengkap ||
    authUser.value?.nama ||
    authUser.value?.name ||
    authUser.value?.username ||
    'Petugas Pos'
)

const normalizedRows = computed(() => {
  return transactionsRaw.value.map((trx, idx) => {
    const idParkir = trx.id_parkir ?? trx.id ?? idx + 1
    const plat = String(trx.kendaraan?.plat_nomor || '-').toUpperCase()
    const jenisRaw = String(
      trx.kendaraan?.jenis_kendaraan || trx.tarif?.jenis_kendaraan || 'kendaraan'
    ).toLowerCase()
    const jenisLabel = jenisRaw.charAt(0).toUpperCase() + jenisRaw.slice(1)

    let icon = Car
    if (jenisRaw.includes('motor') || jenisRaw.includes('roda 2')) icon = Bike
    else if (jenisRaw.includes('box') || jenisRaw.includes('truk') || jenisRaw.includes('bus'))
      icon = Truck
    else if (jenisRaw.includes('vip') || jenisRaw.includes('valet')) icon = Sparkles

    const areaNama =
      trx.area_parkir?.nama_area ||
      trx.areaParkir?.nama_area ||
      'Area Parkir'
    const rawStatus = String(trx.status || 'masuk').toLowerCase()
    const isSelesai = rawStatus === 'keluar' || rawStatus === 'selesai'

    const tarifPerJam = Number(trx.tarif?.tarif_per_jam ?? 0)
    const durasiMenit = hitungDurasiMenit(
      trx.waktu_masuk,
      isSelesai ? trx.waktu_keluar : null
    )
    const durasiJamTagih = isSelesai
      ? Math.max(1, Number(trx.durasi_jam || Math.ceil(durasiMenit / 60)))
      : Math.max(1, Math.ceil(durasiMenit / 60))

    const biayaTotal = isSelesai
      ? Number(trx.biaya_total ?? 0)
      : durasiJamTagih * tarifPerJam

    return {
      idParkir,
      noTiket: formatNoTiket(idParkir),
      plat,
      jenisRaw,
      jenisLabel,
      icon,
      areaNama,
      waktuMasuk: trx.waktu_masuk,
      waktuKeluar: trx.waktu_keluar,
      dateKey: toLocalDateKey(trx.waktu_masuk),
      jamMasukText: formatJam(trx.waktu_masuk),
      jamKeluarText: isSelesai ? formatJam(trx.waktu_keluar) : '-',
      durasiMenit,
      durasiJamTagih,
      durasiText: formatDurasiText(durasiMenit, trx.durasi_jam, isSelesai),
      tarifPerJam,
      biayaDenda: Number(trx.biaya_denda ?? 0),
      biayaTotal,
      karcisHilang: Boolean(trx.karcis_hilang),
      rawStatus,
      isSelesai,
      petugas: trx.user?.nama_lengkap || petugasNama.value,
    }
  })
})

const areaOptions = computed(() => {
  const names = new Set()
  areasRaw.value.forEach((a) => {
    if (a.nama_area) names.add(a.nama_area)
  })
  normalizedRows.value.forEach((r) => {
    if (r.areaNama) names.add(r.areaNama)
  })
  return Array.from(names)
})

const filteredRows = computed(() => {
  const q = searchQuery.value.trim().toUpperCase()
  const list = normalizedRows.value.filter((row) => {
    if (statusFilter.value === 'masuk' && row.isSelesai) return false
    if (statusFilter.value === 'keluar' && !row.isSelesai) return false
    if (areaFilter.value !== 'semua' && row.areaNama !== areaFilter.value) return false
    if (dateFilter.value && row.dateKey !== dateFilter.value) return false

    if (q) {
      const matchPlat = row.plat.includes(q)
      const matchTiket = row.noTiket.toUpperCase().includes(q)
      const matchJenis = row.jenisLabel.toUpperCase().includes(q)
      const matchArea = row.areaNama.toUpperCase().includes(q)
      if (!matchPlat && !matchTiket && !matchJenis && !matchArea) return false
    }

    return true
  })

  return [...list].sort((a, b) => {
    if (sortBy.value === 'terlama') return Number(a.idParkir) - Number(b.idParkir)
    if (sortBy.value === 'biaya_tinggi') return b.biayaTotal - a.biayaTotal
    if (sortBy.value === 'durasi_lama') return b.durasiMenit - a.durasiMenit
    return Number(b.idParkir) - Number(a.idParkir)
  })
})

const summaryStats = computed(() => {
  const source = dateFilter.value ? filteredRows.value : normalizedRows.value
  const totalVolume = source.length

  const selesaiList = source.filter((r) => r.isSelesai)
  const totalPenerimaan = selesaiList.reduce((sum, r) => sum + r.biayaTotal, 0)

  const avgMenit =
    source.length > 0
      ? source.reduce((sum, r) => sum + r.durasiMenit, 0) / source.length
      : 0
  const avgJam = (avgMenit / 60).toFixed(1)

  const aktifCount = source.filter((r) => !r.isSelesai).length

  return {
    totalVolume,
    selesaiCount: selesaiList.length,
    aktifCount,
    totalPenerimaan,
    avgJam,
  }
})

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredRows.value.length / pageSize))
)

const paginatedRows = computed(() => {
  const safePage = Math.min(Math.max(1, currentPage.value), totalPages.value)
  const start = (safePage - 1) * pageSize
  return filteredRows.value.slice(start, start + pageSize)
})

const paginationRangeText = computed(() => {
  const total = filteredRows.value.length
  if (total === 0) return 'Menampilkan 0 dari 0 transaksi'
  const safePage = Math.min(Math.max(1, currentPage.value), totalPages.value)
  const start = (safePage - 1) * pageSize + 1
  const end = Math.min(total, safePage * pageSize)
  return `Menampilkan ${start} – ${end} dari ${total} transaksi`
})

watch([searchQuery, statusFilter, areaFilter, dateFilter, sortBy], () => {
  currentPage.value = 1
})

const drawBarcode = (el, kode) => {
  if (!el) return
  try {
    JsBarcode(el, String(kode || 'PRK-000000').trim(), {
      format: 'CODE128',
      lineColor: '#0F172A',
      background: '#FFFFFF',
      width: 1.25,
      height: 20,
      displayValue: false,
      margin: 1,
    })
  } catch {
    // Abaikan jika gagal render
  }
}

const renderPrintBarcodes = async () => {
  await nextTick()
  const kode = activePrintTicket.value?.noTiket || 'PRK-000000'
  drawBarcode(modalBarcodeRef.value, kode)
  drawBarcode(printAreaBarcodeRef.value, kode)
}

const openPrintModal = async (row) => {
  activePrintTicket.value = row
  await renderPrintBarcodes()
}

const printActiveTicketNow = async () => {
  await renderPrintBarcodes()
  window.print()
}

const resetFilters = () => {
  searchQuery.value = ''
  statusFilter.value = 'semua'
  areaFilter.value = 'semua'
  dateFilter.value = ''
  sortBy.value = 'terbaru'
  currentPage.value = 1
}

const exportCsv = () => {
  const headers = [
    'No Transaksi',
    'Plat Nomor',
    'Jenis Kendaraan',
    'Area Parkir',
    'Waktu Masuk',
    'Waktu Keluar',
    'Durasi',
    'Total Biaya',
    'Status',
  ]

  const rows = filteredRows.value.map((r) => [
    r.noTiket,
    r.plat,
    r.jenisLabel,
    r.areaNama,
    r.waktuMasuk || '-',
    r.waktuKeluar || '-',
    r.durasiText,
    r.biayaTotal,
    r.isSelesai ? 'Selesai' : 'Masuk',
  ])

  const csvContent =
    'data:text/csv;charset=utf-8,' +
    [headers, ...rows]
      .map((line) =>
        line.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(',')
      )
      .join('\n')

  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  link.setAttribute('download', `daftar-transaksi-${new Date().toISOString().slice(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const fetchTransactions = async () => {
  isLoading.value = true
  try {
    const [trxRes, areaRes] = await Promise.allSettled([
      api.get('/operasional/transaksi'),
      api.get('/operasional/area-parkir'),
    ])

    if (trxRes.status === 'fulfilled') {
      transactionsRaw.value = extractList(trxRes.value.data)
    }

    if (areaRes.status === 'fulfilled') {
      areasRaw.value = extractList(areaRes.value.data)
    }
  } finally {
    isLoading.value = false
  }
}

const startAutoSync = () => {
  stopAutoSync()
  if (!autoSyncEnabled.value) return
  syncTimer = window.setInterval(() => {
    fetchTransactions()
  }, 30000)
}

const stopAutoSync = () => {
  if (syncTimer) {
    clearInterval(syncTimer)
    syncTimer = null
  }
}

watch(autoSyncEnabled, () => {
  startAutoSync()
})

const handleLogout = async () => {
  stopAutoSync()
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
  requestAnimationFrame(() => {
    isMountedAnim.value = true
  })

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

  await fetchTransactions()
  startAutoSync()
})

onBeforeUnmount(() => {
  stopAutoSync()
})
</script>

<template>
  <div>
    <DashboardLayout
      :current-role="currentRole"
      :active-menu="activeMenu"
      :user="authUser"
      @update:active-menu="(val) => (activeMenu = val)"
      @logout="handleLogout"
    >
      <!-- Header Halaman -->
      <div
        :class="[
          'no-print mb-6 flex flex-wrap items-start justify-between gap-4 transition-all duration-500 ease-out',
          isMountedAnim ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
        ]"
      >
        <div>
          <p class="text-[10px] font-extrabold tracking-widest text-olive uppercase">
            OPERASIONAL POS • RIWAYAT &amp; STATUS
          </p>
          <h1 class="mt-1 text-2xl font-extrabold text-[#0F172A]">
            Daftar Transaksi Parkir
          </h1>
          <p class="mt-1 text-xs text-[#64748B]">
            Log komprehensif kendaraan masuk, durasi parkir, dan status penyelesaian pembayaran.
          </p>
        </div>

        <div class="flex items-center gap-2.5">
          <button
            type="button"
            @click="autoSyncEnabled = !autoSyncEnabled"
            :class="[
              'inline-flex cursor-pointer items-center gap-2 rounded-full border px-3.5 py-2 text-[11px] font-bold transition-all duration-200 active:scale-95',
              autoSyncEnabled
                ? 'border-[#DCE8BE] bg-[#F3F8E4] text-[#3E4E14]'
                : 'border-[#E2E8F0] bg-white text-[#64748B]',
            ]"
          >
            <span
              :class="[
                'h-2 w-2 rounded-full transition-transform duration-300',
                autoSyncEnabled ? 'scale-110 animate-pulse bg-[#5A920A]' : 'bg-slate-400',
              ]"
            />
            <span>{{ autoSyncEnabled ? 'Auto-Sync: Aktif' : 'Auto-Sync: Off' }}</span>
          </button>

          <button
            type="button"
            @click="fetchTransactions"
            :disabled="isLoading"
            title="Muat ulang data"
            class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-[#E2E8F0] bg-white text-[#334155] transition-all duration-200 hover:border-olive hover:text-olive active:scale-95"
          >
            <RefreshCw :size="14" :class="isLoading ? 'animate-spin text-olive' : ''" />
          </button>
        </div>
      </div>

      <!-- 3 Kartu KPI Ringkasan -->
      <div
        :class="[
          'no-print mb-6 grid gap-4 transition-all duration-500 delay-75 ease-out md:grid-cols-3',
          isMountedAnim ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
        ]"
      >
        <div class="group rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs transition-all duration-300 hover:-translate-y-0.5 hover:border-[#DCE8BE] hover:shadow-md">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-extrabold tracking-wider text-[#64748B] uppercase">
              VOLUME TRANSAKSI
            </span>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#F3F8E4] text-olive transition-transform duration-300 group-hover:scale-110">
              <FileSpreadsheet :size="15" />
            </span>
          </div>
          <div class="mt-3 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold text-[#0F172A] transition-colors duration-200 group-hover:text-olive">
              {{ summaryStats.totalVolume }}
            </span>
            <span class="text-xs text-[#64748B]">transaksi</span>
          </div>
          <p class="mt-2.5 text-[11px] font-semibold text-[#475569]">
            <span class="text-olive">{{ summaryStats.aktifCount }} masuk</span> •
            <span class="text-[#334155]">{{ summaryStats.selesaiCount }} selesai</span>
          </p>
        </div>

        <div class="group rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs transition-all duration-300 hover:-translate-y-0.5 hover:border-[#DCE8BE] hover:shadow-md">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-extrabold tracking-wider text-[#64748B] uppercase">
              TOTAL PENERIMAAN (SELESAI)
            </span>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#F3F8E4] text-olive transition-transform duration-300 group-hover:scale-110">
              <Wallet :size="15" />
            </span>
          </div>
          <p class="mt-3 text-2xl font-extrabold text-[#0F172A] transition-colors duration-200 group-hover:text-olive">
            {{ formatRupiah(summaryStats.totalPenerimaan) }}
          </p>
          <div class="mt-2.5 flex items-center gap-2 text-[10px] font-bold">
            <span class="rounded-md bg-[#F1F5F9] px-2 py-0.5 text-[#334155]">
              {{ summaryStats.selesaiCount }} Transaksi Keluar
            </span>
            <span class="rounded-md bg-[#EAF6C9] px-2 py-0.5 text-[#3E4E14]">
              Tercatat Sistem
            </span>
          </div>
        </div>

        <div class="group rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs transition-all duration-300 hover:-translate-y-0.5 hover:border-[#DCE8BE] hover:shadow-md">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-extrabold tracking-wider text-[#64748B] uppercase">
              DURASI RATA-RATA
            </span>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#F3F8E4] text-olive transition-transform duration-300 group-hover:scale-110">
              <Clock :size="15" />
            </span>
          </div>
          <div class="mt-3 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold text-[#0F172A] transition-colors duration-200 group-hover:text-olive">
              {{ summaryStats.avgJam }}
            </span>
            <span class="text-xs text-[#64748B]">jam / kendaraan</span>
          </div>
          <p class="mt-2.5 text-[11px] text-[#64748B]">
            Petugas aktif: <strong class="text-[#334155]">{{ petugasNama }}</strong>
          </p>
        </div>
      </div>

      <!-- Bar Filter & Ekspor -->
      <section
        :class="[
          'no-print mb-6 rounded-[24px] border border-[#E2E8F0] bg-white p-4 shadow-2xs transition-all duration-500 delay-100 ease-out',
          isMountedAnim ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
        ]"
      >
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-1 flex-wrap items-center gap-2.5">
            <div class="relative min-w-[210px] flex-1 sm:max-w-xs">
              <Search :size="14" class="absolute top-1/2 left-3.5 -translate-y-1/2 text-[#94A3B8]" />
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Cari Plat Nomor / No Transaksi..."
                class="w-full rounded-full border border-[#E2E8F0] bg-[#FAFBF9] py-2 pr-4 pl-9 text-xs text-[#0F172A] transition-all duration-200 focus:border-olive focus:bg-white focus:outline-none"
              />
            </div>

            <select
              v-model="statusFilter"
              class="cursor-pointer rounded-full border border-[#E2E8F0] bg-[#FAFBF9] px-4 py-2 text-xs font-semibold text-[#334155] transition-all duration-200 focus:border-olive focus:bg-white focus:outline-none"
            >
              <option value="semua">Semua Status</option>
              <option value="masuk">Masuk (Aktif)</option>
              <option value="keluar">Selesai (Keluar)</option>
            </select>

            <select
              v-model="areaFilter"
              class="cursor-pointer rounded-full border border-[#E2E8F0] bg-[#FAFBF9] px-4 py-2 text-xs font-semibold text-[#334155] transition-all duration-200 focus:border-olive focus:bg-white focus:outline-none"
            >
              <option value="semua">Semua Area</option>
              <option v-for="area in areaOptions" :key="area" :value="area">
                {{ area }}
              </option>
            </select>

            <input
              v-model="dateFilter"
              type="date"
              class="cursor-pointer rounded-full border border-[#E2E8F0] bg-[#FAFBF9] px-3.5 py-1.5 text-xs font-semibold text-[#334155] transition-all duration-200 focus:border-olive focus:bg-white focus:outline-none"
            />

            <button
              type="button"
              @click="resetFilters"
              class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-[#E2E8F0] bg-white px-3.5 py-2 text-xs font-bold text-[#475569] transition-all duration-200 hover:border-slate-300 hover:bg-slate-50 active:scale-95"
            >
              <RotateCcw :size="13" class="transition-transform duration-300 hover:-rotate-45" />
              <span>Reset Filter</span>
            </button>
          </div>

          <button
            type="button"
            @click="exportCsv"
            class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-[#5A920A] px-4 py-2 text-xs font-extrabold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#487607] hover:shadow-sm active:translate-y-0 active:scale-95"
          >
            <Download :size="14" />
            <span>Ekspor CSV</span>
          </button>
        </div>
      </section>

      <!-- Tabel Data Aktivitas Parkir -->
      <section
        :class="[
          'no-print rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs transition-all duration-500 delay-150 ease-out',
          isMountedAnim ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
        ]"
      >
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
          <div class="flex items-center gap-2.5">
            <h2 class="text-base font-extrabold text-[#0F172A]">Data Aktivitas Parkir</h2>
            <span class="rounded-full bg-[#F1F5F9] px-3 py-0.5 text-[11px] font-bold text-[#475569] transition-all duration-200">
              Menampilkan {{ paginatedRows.length }} baris
            </span>
          </div>

          <div class="flex items-center gap-2 text-xs">
            <span class="text-[#94A3B8]">Sortir:</span>
            <select
              v-model="sortBy"
              class="cursor-pointer rounded-xl border border-transparent bg-transparent py-1 pr-2 pl-1 text-xs font-extrabold text-[#0F172A] transition-colors focus:border-[#E2E8F0] focus:bg-[#FAFBF9] focus:outline-none"
            >
              <option value="terbaru">Terbaru Masuk</option>
              <option value="terlama">Terlama Masuk</option>
              <option value="biaya_tinggi">Biaya Tertinggi</option>
              <option value="durasi_lama">Durasi Terlama</option>
            </select>
          </div>
        </div>

        <div class="mt-4 overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-slate-100 text-[10px] font-extrabold tracking-wider text-[#94A3B8] uppercase">
                <th class="py-3 pr-3">NO TRANSAKSI</th>
                <th class="py-3 px-3">PLAT NOMOR</th>
                <th class="py-3 px-3">JENIS</th>
                <th class="py-3 px-3">AREA</th>
                <th class="py-3 px-3">WAKTU MASUK</th>
                <th class="py-3 px-3">WAKTU KELUAR</th>
                <th class="py-3 px-3">DURASI</th>
                <th class="py-3 px-3">TOTAL BIAYA</th>
                <th class="py-3 px-3">STATUS</th>
                <th class="py-3 pl-3 text-right">AKSI</th>
              </tr>
            </thead>

            <TransitionGroup name="row-fade" tag="tbody" class="divide-y divide-slate-100">
              <tr
                v-for="row in paginatedRows"
                :key="row.idParkir"
                class="transition-colors duration-200 hover:bg-[#FAFBF9]"
              >
                <td class="py-3.5 pr-3 font-mono text-[11px] font-extrabold text-olive">
                  {{ row.noTiket }}
                </td>

                <td class="py-3.5 px-3">
                  <span class="inline-block rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-2 py-1 font-mono text-[10.5px] font-extrabold leading-tight text-[#0F172A]">
                    {{ row.plat }}
                  </span>
                </td>

                <td class="py-3.5 px-3">
                  <span class="inline-flex items-center gap-1.5 font-semibold text-[#334155]">
                    <component :is="row.icon" :size="14" class="text-olive" />
                    <span>{{ row.jenisLabel }}</span>
                  </span>
                </td>

                <td class="py-3.5 px-3 font-bold text-[#0F172A]">
                  {{ row.areaNama }}
                </td>

                <td class="py-3.5 px-3 font-mono text-[11px] text-[#334155]">
                  {{ row.jamMasukText }}
                </td>

                <td class="py-3.5 px-3 font-mono text-[11px] text-[#64748B]">
                  {{ row.jamKeluarText }}
                </td>

                <td
                  :class="[
                    'py-3.5 px-3 font-bold',
                    row.isSelesai ? 'text-[#334155]' : 'text-olive',
                  ]"
                >
                  {{ row.durasiText }}
                </td>

                <td class="py-3.5 px-3">
                  <div class="font-extrabold text-[#0F172A]">
                    {{ formatRupiah(row.biayaTotal) }}
                  </div>
                  <div v-if="row.karcisHilang" class="text-[9.5px] font-semibold text-rose-600">
                    Denda: {{ formatRupiah(row.biayaDenda) }}
                  </div>
                </td>

                <td class="py-3.5 px-3">
                  <span
                    v-if="row.isSelesai"
                    class="inline-flex items-center gap-1.5 rounded-full bg-[#EAF6C9] px-2.5 py-1 text-[10.5px] font-extrabold text-[#3E4E14]"
                  >
                    <span class="h-1.5 w-1.5 rounded-full bg-[#5A920A]" />
                    <span>Selesai</span>
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1.5 rounded-full bg-[#F1F5F9] px-2.5 py-1 text-[10.5px] font-extrabold text-[#475569]"
                  >
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-slate-500" />
                    <span>Masuk</span>
                  </span>
                </td>

                <td class="py-3.5 pl-3 text-right">
                  <button
                    type="button"
                    @click="openPrintModal(row)"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-[#E2E8F0] bg-white px-3 py-1.5 text-[10.5px] font-extrabold text-[#0F172A] transition-all duration-200 hover:-translate-y-0.5 hover:border-olive hover:bg-[#F3F8E4] active:translate-y-0 active:scale-95"
                  >
                    <Receipt :size="12" class="text-olive" />
                    <span>{{ row.isSelesai ? 'Cetak Struk' : 'Cetak Karcis' }}</span>
                  </button>
                </td>
              </tr>

              <tr v-if="!isLoading && paginatedRows.length === 0" key="empty-row">
                <td colspan="10" class="py-10 text-center text-xs text-[#64748B]">
                  Tidak ada data transaksi yang sesuai dengan filter.
                </td>
              </tr>
            </TransitionGroup>
          </table>
        </div>

        <!-- Footer Pagination -->
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 text-xs">
          <span class="text-[#64748B]">{{ paginationRangeText }}</span>

          <div class="flex items-center gap-1">
            <button
              type="button"
              :disabled="currentPage <= 1"
              @click="currentPage--"
              class="cursor-pointer rounded-lg border border-[#E2E8F0] bg-white px-2.5 py-1 text-xs font-bold text-[#334155] transition-all duration-150 hover:bg-slate-50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
              &lt;
            </button>

            <button
              v-for="page in totalPages"
              :key="page"
              type="button"
              @click="currentPage = page"
              :class="[
                'cursor-pointer rounded-lg px-2.5 py-1 text-xs font-extrabold transition-all duration-150 active:scale-95',
                currentPage === page
                  ? 'bg-[#0F172A] text-white shadow-2xs'
                  : 'border border-[#E2E8F0] bg-white text-[#334155] hover:bg-slate-50',
              ]"
            >
              {{ page }}
            </button>

            <button
              type="button"
              :disabled="currentPage >= totalPages"
              @click="currentPage++"
              class="cursor-pointer rounded-lg border border-[#E2E8F0] bg-white px-2.5 py-1 text-xs font-bold text-[#334155] transition-all duration-150 hover:bg-slate-50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
              &gt;
            </button>
          </div>
        </div>
      </section>

      <!-- Modal Preview & Cetak Ulang -->
      <Transition name="modal-pop">
        <div
          v-if="activePrintTicket"
          class="no-print fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-2xs"
        >
          <div class="modal-card w-full max-w-sm rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <p class="text-[10px] font-extrabold tracking-wider text-olive uppercase">
                  CETAK ULANG THERMAL
                </p>
                <h3 class="text-sm font-extrabold text-[#0F172A]">
                  {{ activePrintTicket.isSelesai ? 'Struk Keluar' : 'Karcis Masuk' }} •
                  {{ activePrintTicket.noTiket }}
                </h3>
              </div>
              <button
                type="button"
                @click="activePrintTicket = null"
                class="cursor-pointer rounded-full p-1.5 text-[#64748B] transition-colors hover:bg-slate-100 hover:text-[#0F172A]"
              >
                <X :size="16" />
              </button>
            </div>

            <div class="mt-4 flex justify-center">
              <div class="w-[212px] rounded-xl border border-dashed border-[#94A3B8] bg-white p-2 text-center text-[#0F172A]">
                <div class="flex items-center justify-between border-b border-dashed border-[#CBD5E1] pb-0.5 text-[7.5px] font-extrabold">
                  <span>{{ activePrintTicket.isSelesai ? 'STRUK KELUAR' : 'KARCIS MASUK' }}</span>
                  <span class="font-mono">{{ activePrintTicket.noTiket }}</span>
                </div>

                <p class="mt-0.5 font-mono text-[11px] font-extrabold tracking-wider">
                  {{ activePrintTicket.plat }}
                </p>
                <p class="text-[7.5px] text-[#475569]">
                  {{ activePrintTicket.jenisLabel }} • {{ activePrintTicket.areaNama }}
                </p>

                <div class="mt-1 flex justify-between text-[7px] text-[#334155]">
                  <span>Masuk: {{ formatTanggalJamSingkat(activePrintTicket.waktuMasuk) }}</span>
                  <span>{{ activePrintTicket.durasiText }}</span>
                </div>

                <div class="mt-0.5 flex items-center justify-between text-[7.5px] font-extrabold">
                  <span>{{ activePrintTicket.isSelesai ? 'TOTAL BAYAR' : 'TARIF/JAM' }}</span>
                  <span>
                    {{
                      activePrintTicket.isSelesai
                        ? formatRupiah(activePrintTicket.biayaTotal)
                        : `${formatRupiah(activePrintTicket.tarifPerJam)}/j`
                    }}
                  </span>
                </div>

                <div class="mt-1 flex flex-col items-center justify-center">
                  <svg ref="modalBarcodeRef" class="max-w-full" />
                </div>
              </div>
            </div>

            <div class="mt-5 flex gap-2.5">
              <button
                type="button"
                @click="printActiveTicketNow"
                class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-full bg-[#5A920A] px-4 py-2.5 text-xs font-extrabold text-white transition-all duration-200 hover:bg-[#487607] active:scale-95"
              >
                <Printer :size="14" />
                <span>Cetak Sekarang</span>
              </button>
              <button
                type="button"
                @click="activePrintTicket = null"
                class="cursor-pointer rounded-full border border-[#E2E8F0] bg-white px-4 py-2.5 text-xs font-bold text-[#475569] transition-colors hover:bg-slate-50 active:scale-95"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </DashboardLayout>

    <!-- Area Cetak Khusus (Pola Sama dengan ParkirKeluarView_5.vue) -->
    <div v-if="activePrintTicket" class="print-area">
      <div class="flex items-center justify-between border-b border-dashed border-[#CBD5E1] pb-0.5 text-[7.5px] font-extrabold">
        <span>{{ activePrintTicket.isSelesai ? 'STRUK KELUAR' : 'KARCIS MASUK' }}</span>
        <span class="font-mono">{{ activePrintTicket.noTiket }}</span>
      </div>

      <p class="mt-0.5 font-mono text-[11px] font-extrabold tracking-wider">
        {{ activePrintTicket.plat }}
      </p>
      <p class="text-[7.5px] text-[#475569]">
        {{ activePrintTicket.jenisLabel }} • {{ activePrintTicket.areaNama }}
      </p>

      <div class="mt-1 flex justify-between text-[7px] text-[#334155]">
        <span>Masuk: {{ formatTanggalJamSingkat(activePrintTicket.waktuMasuk) }}</span>
        <span>{{ activePrintTicket.durasiText }}</span>
      </div>

      <div class="mt-0.5 flex items-center justify-between text-[7.5px] font-extrabold">
        <span>{{ activePrintTicket.isSelesai ? 'TOTAL BAYAR' : 'TARIF/JAM' }}</span>
        <span>
          {{
            activePrintTicket.isSelesai
              ? formatRupiah(activePrintTicket.biayaTotal)
              : `${formatRupiah(activePrintTicket.tarifPerJam)}/j`
          }}
        </span>
      </div>

      <div class="mt-1 flex flex-col items-center justify-center">
        <svg ref="printAreaBarcodeRef" class="max-w-full" />
      </div>
    </div>
  </div>
</template>

<style>
.text-olive {
  color: #50631D;
}
.bg-olive {
  background-color: #50631D;
}

.print-area {
  display: none;
}

/* Animasi baris tabel */
.row-fade-enter-active,
.row-fade-leave-active {
  transition: all 0.25s ease;
}
.row-fade-enter-from,
.row-fade-leave-to {
  opacity: 0;
  transform: translateY(6px);
}
.row-fade-move {
  transition: transform 0.25s ease;
}

/* Animasi modal cetak */
.modal-pop-enter-active,
.modal-pop-leave-active {
  transition: opacity 0.2s ease;
}
.modal-pop-enter-active .modal-card,
.modal-pop-leave-active .modal-card {
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
}
.modal-pop-enter-from,
.modal-pop-leave-to {
  opacity: 0;
}
.modal-pop-enter-from .modal-card,
.modal-pop-leave-to .modal-card {
  opacity: 0;
  transform: scale(0.95) translateY(8px);
}

@page {
  size: 56mm 46mm;
  margin: 0;
}

@media print {
  html,
  body {
    width: 56mm;
    height: 46mm;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden;
  }

  body * {
    visibility: hidden;
  }

  .print-area,
  .print-area * {
    visibility: visible;
  }

  .print-area {
    display: block !important;
    position: fixed;
    left: 0;
    top: 0;
    width: 56mm;
    height: 46mm;
    max-width: 56mm;
    max-height: 46mm;
    margin: 0 !important;
    padding: 1.5mm 2mm !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: #ffffff !important;
    color: #0f172a !important;
    text-align: center;
    overflow: hidden;
  }

  .no-print {
    display: none !important;
  }
}
</style>