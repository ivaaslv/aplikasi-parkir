<script setup>
import {
  SquareParking,
  CarFront,
  Search,
  RefreshCw,
  Clock,
  LogIn,
  QrCode,
} from 'lucide-vue-next'
import { motion } from 'motion-v'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '../layouts/DashboardLayout.vue'
import api from '../../services/api'
import { getAuthUser } from '../../router'

const router = useRouter()
const authState = getAuthUser()

const currentRole = ref(String(authState.role || 'petugas').toLowerCase())
const authUser = ref(authState.user)
const activeMenu = ref('area-parkir-petugas')

const isLoading = ref(false)
const isMountedAnim = ref(false)
const fetchError = ref('')
const lastSyncLabel = ref('Baru saja')

const areasRaw = ref([])
const transactionsRaw = ref([])
const selectedAreaId = ref('all')
const searchQuery = ref('')
const vehicleFilter = ref('semua')

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

const formatJamMasuk = (dateInput) => {
  if (!dateInput) return '-'
  const d = new Date(dateInput)
  if (Number.isNaN(d.getTime())) return String(dateInput)
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d)
}

const formatDurasiSingkat = (waktuMasuk) => {
  if (!waktuMasuk) return '-'
  const start = new Date(waktuMasuk).getTime()
  if (Number.isNaN(start)) return '-'
  const diffMenit = Math.max(1, Math.floor((Date.now() - start) / 60000))
  const jam = Math.floor(diffMenit / 60)
  const menit = diffMenit % 60
  if (jam <= 0) return `${menit} mnt`
  return `${jam}j ${menit}m`
}

const fetchAreaMonitoringData = async () => {
  isLoading.value = true
  fetchError.value = ''

  const areaEndpoint =
    currentRole.value === 'admin' ? '/admin/area-parkir' : '/operasional/area-parkir'

  try {
    const [areaRes, trxRes] = await Promise.allSettled([
      api.get(areaEndpoint),
      api.get('/operasional/transaksi'),
    ])

    if (areaRes.status === 'fulfilled') {
      areasRaw.value = extractList(areaRes.value.data)
    }

    if (trxRes.status === 'fulfilled') {
      transactionsRaw.value = extractList(trxRes.value.data)
    }

    if (areaRes.status === 'rejected' && trxRes.status === 'rejected') {
      fetchError.value =
        'Gagal memuat data area parkir dan transaksi aktif. Periksa koneksi API dan sesi login.'
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

  await fetchAreaMonitoringData()
})

// Hanya transaksi yang masih berstatus masuk (belum keluar)
const activeTransactions = computed(() =>
  transactionsRaw.value.filter(
    (trx) =>
      String(trx.status || '').toLowerCase() === 'masuk' ||
      (!trx.waktu_keluar && String(trx.status || '').toLowerCase() !== 'keluar')
  )
)

const enrichedAreas = computed(() =>
  areasRaw.value.map((area, idx) => {
    const areaId = area.id_area ?? area.id ?? idx
    const total = Math.max(0, Number(area.kapasitas ?? 0))

    const vehiclesInArea = activeTransactions.value
      .filter((trx) => {
        const trxAreaId =
          trx.id_area ?? trx.area_parkir?.id_area ?? trx.areaParkir?.id_area
        return String(trxAreaId) === String(areaId)
      })
      .map((trx, tIdx) => ({
        id: trx.id_parkir ?? trx.id ?? `${areaId}-${tIdx}`,
        plat: String(trx.kendaraan?.plat_nomor || '-').toUpperCase(),
        jenis: String(trx.kendaraan?.jenis_kendaraan || 'kendaraan').toLowerCase(),
        warna: trx.kendaraan?.warna || '',
        pemilik: trx.kendaraan?.pemilik || '',
        jamMasuk: formatJamMasuk(trx.waktu_masuk),
        durasi: formatDurasiSingkat(trx.waktu_masuk),
      }))

    const used = Math.max(Number(area.terisi ?? 0), vehiclesInArea.length)
    const sisa = Math.max(0, total - used)
    const percent = total > 0 ? Math.min(100, Math.round((used / total) * 100)) : 0

    let statusLabel = 'Lega'
    let statusCls = 'bg-[#EAF6C9] text-[#3E4E14]'
    let barCls = 'bg-olive'

    if (percent >= 90 || sisa === 0) {
      statusLabel = 'Penuh'
      statusCls = 'bg-rose-100 text-rose-700'
      barCls = 'bg-rose-600'
    } else if (percent >= 70) {
      statusLabel = 'Ramai'
      statusCls = 'bg-amber-100 text-amber-800'
      barCls = 'bg-amber-500'
    }

    return {
      id: areaId,
      name: area.nama_area || `Area ${idx + 1}`,
      deskripsi: area.deskripsi || '',
      jenisKendaraan: String(area.jenis_kendaraan || 'semua').toLowerCase(),
      total,
      used,
      sisa,
      percent,
      statusLabel,
      statusCls,
      barCls,
      vehicles: vehiclesInArea,
    }
  })
)

const filteredAreas = computed(() =>
  enrichedAreas.value.filter((area) => {
    if (selectedAreaId.value !== 'all' && String(area.id) !== String(selectedAreaId.value)) {
      return false
    }
    if (
      vehicleFilter.value !== 'semua' &&
      area.jenisKendaraan !== 'semua' &&
      area.jenisKendaraan !== vehicleFilter.value
    ) {
      return false
    }
    return true
  })
)

const filteredVehicles = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()

  return filteredAreas.value.flatMap((area) =>
    area.vehicles
      .filter((v) => {
        if (vehicleFilter.value !== 'semua' && v.jenis !== vehicleFilter.value) {
          return false
        }
        if (!q) return true
        return (
          v.plat.toLowerCase().includes(q) ||
          v.jenis.toLowerCase().includes(q) ||
          v.warna.toLowerCase().includes(q) ||
          v.pemilik.toLowerCase().includes(q) ||
          area.name.toLowerCase().includes(q)
        )
      })
      .map((v) => ({
        ...v,
        areaName: area.name,
        areaId: area.id,
      }))
  )
})

const totalKapasitasSemua = computed(() =>
  enrichedAreas.value.reduce((sum, a) => sum + a.total, 0)
)
const totalTerparkirSemua = computed(() =>
  enrichedAreas.value.reduce((sum, a) => sum + a.used, 0)
)
const totalKosongSemua = computed(() =>
  Math.max(0, totalKapasitasSemua.value - totalTerparkirSemua.value)
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
    <!-- Header Halaman -->
    <motion.div
      :initial="{ opacity: 0, y: 10 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.35 }"
      class="flex flex-col justify-between gap-4 rounded-[24px] border border-[#E2E8F0] bg-white px-6 py-4 shadow-2xs sm:flex-row sm:items-center will-change-transform"
    >
      <div class="flex items-center gap-3.5">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF6C9] text-olive transition-transform duration-300 hover:scale-105">
          <SquareParking :size="20" />
        </span>
        <div>
          <p class="text-[9.5px] font-bold tracking-[0.16em] text-olive">
            MONITORING ZONA OPERASIONAL
          </p>
          <h1 class="text-base font-extrabold tracking-tight text-[#0F172A] sm:text-lg">
            Area Parkir &amp; Kendaraan Aktif
          </h1>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2.5 self-start sm:self-auto">
        <span class="hidden text-[10.5px] text-[#94A3B8] md:inline">
          Sinkron: {{ lastSyncLabel }}
        </span>
        <button
          type="button"
          @click="fetchAreaMonitoringData"
          :disabled="isLoading"
          class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2 text-[11px] font-bold text-[#334155] transition-all duration-200 hover:border-olive hover:bg-[#F4F8EA] hover:text-olive active:scale-95 disabled:opacity-60"
        >
          <RefreshCw :size="13" :class="isLoading ? 'animate-spin text-olive' : ''" />
          <span>{{ isLoading ? 'Memuat...' : 'Sync' }}</span>
        </button>
        <button
          type="button"
          @click="router.push('/petugas/parkir-masuk')"
          class="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-olive px-4 py-2 text-[11px] font-bold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-olive-dark active:translate-y-0 active:scale-95"
        >
          <LogIn :size="13" />
          <span>Parkir Masuk</span>
        </button>
      </div>
    </motion.div>

    <Transition name="soft-expand">
      <p
        v-if="fetchError"
        class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700"
      >
        {{ fetchError }}
      </p>
    </Transition>

    <!-- 3 Kartu Ringkasan Slot -->
    <div
      :class="[
        'mt-6 grid gap-5 sm:grid-cols-3 transition-all duration-500 delay-75 ease-out',
        isMountedAnim ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
      ]"
    >
      <div class="rounded-[22px] border border-[#E2E8F0] bg-white p-5 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
        <p class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">TOTAL AREA TERDAFTAR</p>
        <p class="mt-2.5 text-2xl font-extrabold text-[#0F172A]">
          {{ formatAngka(enrichedAreas.length) }}
          <span class="text-xs font-medium text-[#64748B]">zona</span>
        </p>
        <p class="mt-2 text-[11px] text-[#64748B]">
          Kapasitas keseluruhan {{ formatAngka(totalKapasitasSemua) }} slot
        </p>
      </div>

      <div class="rounded-[22px] border border-[#E2E8F0] bg-white p-5 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
        <p class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">KENDARAAN SEDANG PARKIR</p>
        <p class="mt-2.5 text-2xl font-extrabold text-olive">
          {{ formatAngka(totalTerparkirSemua) }}
          <span class="text-xs font-medium text-[#64748B]">unit aktif</span>
        </p>
        <p class="mt-2 text-[11px] text-[#64748B]">
          Otomatis berkurang saat kendaraan keluar
        </p>
      </div>

      <div class="rounded-[22px] border border-[#E2E8F0] bg-white p-5 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
        <p class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">TOTAL SLOT TERSEDIA</p>
        <p class="mt-2.5 text-2xl font-extrabold text-[#0F172A]">
          {{ formatAngka(totalKosongSemua) }}
          <span class="text-xs font-medium text-[#64748B]">slot kosong</span>
        </p>
        <p class="mt-2 text-[11px] text-[#64748B]">
          Siap menerima kendaraan masuk baru
        </p>
      </div>
    </div>

    <!-- Filter Area, Jenis Kendaraan & Pencarian Plat -->
    <div
      :class="[
        'mt-6 flex flex-col justify-between gap-3 rounded-[22px] border border-[#E2E8F0] bg-white p-4 shadow-2xs lg:flex-row lg:items-center transition-all duration-500 delay-100 ease-out',
        isMountedAnim ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
      ]"
    >
      <div class="flex flex-wrap items-center gap-2">
        <select
          v-model="selectedAreaId"
          class="cursor-pointer rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2 text-xs font-semibold text-[#334155] transition-all duration-200 focus:border-olive focus:outline-none"
        >
          <option value="all">Semua Area ({{ enrichedAreas.length }})</option>
          <option v-for="area in enrichedAreas" :key="area.id" :value="area.id">
            {{ area.name }} ({{ area.used }}/{{ area.total }})
          </option>
        </select>

        <div class="flex items-center gap-1 rounded-full border border-[#E2E8F0] bg-[#F8FAFC] p-1 text-[11px]">
          <button
            v-for="jenis in [
              { id: 'semua', label: 'Semua Jenis' },
              { id: 'motor', label: 'Motor' },
              { id: 'mobil', label: 'Mobil' },
            ]"
            :key="jenis.id"
            type="button"
            @click="vehicleFilter = jenis.id"
            :class="[
              'cursor-pointer rounded-full px-3 py-1 font-bold transition-all duration-200 active:scale-95',
              vehicleFilter === jenis.id
                ? 'bg-olive text-white shadow-2xs'
                : 'text-[#64748B] hover:text-olive',
            ]"
          >
            {{ jenis.label }}
          </button>
        </div>
      </div>

      <div class="relative w-full lg:w-72">
        <Search :size="14" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari plat nomor / pemilik / area..."
          class="w-full rounded-full border border-[#E2E8F0] bg-[#F8FAFC] py-2 pl-9 pr-4 text-xs text-[#0F172A] placeholder:text-[#94A3B8] transition-all duration-200 focus:border-olive focus:bg-white focus:outline-none"
        />
      </div>
    </div>

    <!-- Grid Kartu Semua Area Parkir -->
    <TransitionGroup
      v-if="filteredAreas.length > 0"
      name="card-pop"
      tag="div"
      :class="[
        'mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3 transition-all duration-500 delay-150 ease-out',
        isMountedAnim ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
      ]"
    >
      <div
        v-for="area in filteredAreas"
        :key="area.id"
        @click="selectedAreaId = String(selectedAreaId) === String(area.id) ? 'all' : area.id"
        :class="[
          'cursor-pointer rounded-[24px] border bg-white p-5 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md active:scale-[0.99]',
          String(selectedAreaId) === String(area.id)
            ? 'border-olive ring-2 ring-olive/15'
            : 'border-[#E2E8F0] hover:border-olive/40',
        ]"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <h2 class="text-sm font-extrabold text-[#0F172A]">{{ area.name }}</h2>
            <p class="mt-0.5 text-[10.5px] text-[#64748B]">
              {{ area.deskripsi || `Tipe: ${area.jenisKendaraan.toUpperCase()}` }}
            </p>
          </div>
          <span :class="['rounded-full px-2.5 py-0.5 text-[9.5px] font-bold transition-colors', area.statusCls]">
            {{ area.statusLabel }}
          </span>
        </div>

        <div class="mt-4 flex items-baseline justify-between">
          <p class="text-lg font-extrabold text-[#0F172A]">
            {{ area.used }}
            <span class="text-xs font-normal text-[#94A3B8]">/ {{ area.total }} slot</span>
          </p>
          <span class="text-[11px] font-bold text-olive">
            Sisa {{ area.sisa }} ({{ area.percent }}%)
          </span>
        </div>

        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
          <div
            :class="['h-full rounded-full transition-all duration-500 ease-out', area.barCls]"
            :style="{ width: `${area.percent}%` }"
          />
        </div>

        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-[10.5px] text-[#64748B]">
          <span class="inline-flex items-center gap-1 font-semibold text-[#334155]">
            <CarFront :size="13" class="text-olive" />
            <span>{{ area.vehicles.length }} kendaraan aktif</span>
          </span>
          <span class="font-bold text-olive">
            {{ String(selectedAreaId) === String(area.id) ? 'Tampilkan Semua' : 'Fokus Area →' }}
          </span>
        </div>
      </div>
    </TransitionGroup>

    <div
      v-else
      class="mt-6 rounded-[24px] border border-dashed border-[#E2E8F0] bg-white px-4 py-8 text-center text-xs text-[#64748B]"
    >
      {{ isLoading ? 'Memuat daftar area parkir...' : 'Tidak ada area parkir yang sesuai filter.' }}
    </div>

    <!-- Daftar Kendaraan Yang Sedang Parkir di Area -->
    <section
      :class="[
        'mt-7 rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs transition-all duration-500 delay-200 ease-out',
        isMountedAnim ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
      ]"
    >
      <div class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
        <div>
          <h2 class="text-sm font-extrabold text-[#0F172A]">
            Daftar Kendaraan Sedang Terparkir
          </h2>
          <p class="mt-0.5 text-[11px] text-[#64748B]">
            Menampilkan {{ filteredVehicles.length }} kendaraan yang masih berada di dalam area (otomatis hilang setelah keluar)
          </p>
        </div>

        <button
          type="button"
          @click="router.push('/petugas/parkir-keluar')"
          class="inline-flex cursor-pointer items-center gap-1.5 self-start rounded-full bg-[#0F172A] px-4 py-2 text-[11px] font-bold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1E293B] active:translate-y-0 active:scale-95 sm:self-auto"
        >
          <QrCode :size="13" />
          <span>Proses Kendaraan Keluar</span>
        </button>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[680px] border-collapse text-left">
          <thead>
            <tr class="border-b border-slate-100 text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
              <th class="py-3 pr-4">PLAT NOMOR</th>
              <th class="py-3 pr-4">JENIS &amp; DETAIL</th>
              <th class="py-3 pr-4">AREA PARKIR</th>
              <th class="py-3 pr-4">WAKTU MASUK</th>
              <th class="py-3 text-right">DURASI BERJALAN</th>
            </tr>
          </thead>
          <TransitionGroup v-if="filteredVehicles.length > 0" name="row-fade" tag="tbody" class="divide-y divide-slate-100 text-xs">
            <tr
              v-for="v in filteredVehicles"
              :key="v.id"
              class="transition-colors duration-200 hover:bg-[#F8FAFC]/80"
            >
              <td class="py-3.5 pr-4 align-middle">
                <span class="inline-block rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-2.5 py-1 font-mono text-[11px] font-extrabold text-[#0F172A]">
                  {{ v.plat }}
                </span>
              </td>
              <td class="py-3.5 pr-4 align-middle">
                <p class="font-bold capitalize text-[#0F172A]">{{ v.jenis }}</p>
                <p v-if="v.warna || v.pemilik" class="text-[10.5px] text-[#64748B]">
                  {{ [v.warna, v.pemilik].filter(Boolean).join(' • ') }}
                </p>
              </td>
              <td class="py-3.5 pr-4 align-middle">
                <span class="rounded-full bg-[#EAF6C9]/80 px-2.5 py-1 text-[10.5px] font-bold text-[#3E4E14]">
                  {{ v.areaName }}
                </span>
              </td>
              <td class="py-3.5 pr-4 align-middle">
                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-[#475569]">
                  <Clock :size="12" class="text-olive" />
                  <span>{{ v.jamMasuk }}</span>
                </span>
              </td>
              <td class="py-3.5 text-right align-middle">
                <span class="rounded-full bg-[#F1F5F9] px-2.5 py-1 text-[10.5px] font-bold text-[#334155]">
                  {{ v.durasi }}
                </span>
              </td>
            </tr>
          </TransitionGroup>
          <tbody v-else>
            <tr>
              <td colspan="5" class="py-9 text-center text-xs text-[#64748B]">
                {{
                  isLoading
                    ? 'Memuat kendaraan aktif...'
                    : 'Tidak ada kendaraan yang sedang parkir pada area/filter ini.'
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
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

.soft-expand-enter-active,
.soft-expand-leave-active {
  transition: all 0.22s ease;
}
.soft-expand-enter-from,
.soft-expand-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

.card-pop-enter-active,
.card-pop-leave-active {
  transition: all 0.25s ease;
}
.card-pop-enter-from,
.card-pop-leave-to {
  opacity: 0;
  transform: scale(0.98) translateY(6px);
}
.card-pop-move {
  transition: transform 0.25s ease;
}

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
</style>
