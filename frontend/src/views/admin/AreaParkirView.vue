<script setup>
import {
  SquareParking,
  Bike,
  CarFront,
  Truck,
  Layers,
  Plus,
  Pencil,
  Trash2,
  SlidersHorizontal,
  Search,
  CheckCircle2,
  AlertCircle,
  X,
  MapPin,
  ParkingSquare,
} from 'lucide-vue-next'
import { AnimatePresence, motion } from 'motion-v'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '../layouts/DashboardLayout.vue'
import api from '../../services/api'
import { getAuthUser } from '../../router'

const JENIS_OPTIONS = [
  {
    value: 'semua',
    label: 'Semua Kendaraan',
    shortLabel: 'Semua',
    icon: Layers,
    badgeClass: 'bg-[#F4F8EA] text-olive border-[#C7E182]',
    iconBg: 'bg-[#EAF6C9] text-[#50631D]',
  },
  {
    value: 'mobil',
    label: 'Mobil',
    shortLabel: 'Mobil',
    icon: CarFront,
    badgeClass: 'bg-[#F3E8E2] text-[#8C4A32] border-[#E7CFC3]',
    iconBg: 'bg-[#F3E8E2] text-[#8C4A32]',
  },
  {
    value: 'motor',
    label: 'Motor',
    shortLabel: 'Motor',
    icon: Bike,
    badgeClass: 'bg-sky-50 text-sky-800 border-sky-200',
    iconBg: 'bg-sky-100 text-sky-800',
  },
  {
    value: 'lainnya',
    label: 'Lainnya',
    shortLabel: 'Lainnya',
    icon: Truck,
    badgeClass: 'bg-slate-100 text-slate-700 border-slate-200',
    iconBg: 'bg-slate-100 text-slate-700',
  },
]

const router = useRouter()
const authState = getAuthUser()

const currentRole = ref(authState.role || 'admin')
const authUser = ref(authState.user)
const activeMenu = ref('kelola-area')

const areaList = ref([])
const isLoading = ref(false)
const isSubmitting = ref(false)
const isCalibrating = ref(false)
const deletingId = ref(null)

const searchQuery = ref('')
const activeJenisFilter = ref('semua_tab')

// Modal Tambah / Edit Area
const isModalOpen = ref(false)
const editingAreaId = ref(null)
const formError = ref('')

const form = reactive({
  nama_area: '',
  deskripsi: '',
  jenis_kendaraan: 'semua',
  kapasitas: '',
  terisi: 0,
})

// Modal Kalibrasi Slot Terisi
const calibratingArea = ref(null)
const kalibrasiTerisi = ref(0)
const kalibrasiError = ref('')

// Modal Konfirmasi Hapus
const areaToDelete = ref(null)

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

const formatAngka = (num) =>
  new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(num) || 0)

const getJenisMeta = (jenis) => {
  const key = String(jenis || 'semua').toLowerCase().trim()
  return (
    JENIS_OPTIONS.find((opt) => opt.value === key) || {
      value: key || 'semua',
      label: key ? key.charAt(0).toUpperCase() + key.slice(1) : 'Semua Kendaraan',
      shortLabel: key || 'Semua',
      icon: SquareParking,
      badgeClass: 'bg-slate-100 text-slate-700 border-slate-200',
      iconBg: 'bg-[#F4F8EA] text-olive',
    }
  )
}

const getOccupancyPercent = (area) => {
  const cap = Number(area?.kapasitas || 0)
  const used = Number(area?.terisi || 0)
  if (cap <= 0) return 0
  return Math.min(100, Math.max(0, Math.round((used / cap) * 100)))
}

const getStatusBadge = (area) => {
  const pct = getOccupancyPercent(area)
  if (pct >= 90) {
    return {
      text: '• Padat',
      cls: 'bg-rose-50 text-rose-700 border border-rose-200',
      barCls: 'bg-rose-500',
      textCls: 'text-rose-600',
      note: 'Kepadatan Ruang (Hampir Penuh)',
    }
  }
  if (pct >= 65) {
    return {
      text: 'Sedang',
      cls: 'bg-amber-50 text-amber-800 border border-amber-200',
      barCls: 'bg-olive',
      textCls: 'text-[#0F172A]',
      note: 'Kepadatan Ruang',
    }
  }
  return {
    text: 'Aman / Lancar',
    cls: 'bg-[#EAF6C9] text-[#50631D] border border-[#C7E182]',
    barCls: 'bg-[#94B83D]',
    textCls: 'text-[#0F172A]',
    note: 'Kepadatan Ruang',
  }
}

const extractList = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  return []
}

const fetchAreas = async () => {
  isLoading.value = true
  try {
    const { data } = await api.get('/admin/area-parkir')
    areaList.value = extractList(data)
  } catch (err) {
    showToast(
      err?.response?.data?.message || 'Gagal memuat data area parkir dari server.',
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
  await fetchAreas()
})

// Ringkasan KPI Real-Time
const totalKapasitas = computed(() =>
  areaList.value.reduce((sum, a) => sum + (Number(a.kapasitas) || 0), 0)
)

const totalTerisi = computed(() =>
  areaList.value.reduce((sum, a) => sum + (Number(a.terisi) || 0), 0)
)

const totalSlotKosong = computed(() =>
  Math.max(0, totalKapasitas.value - totalTerisi.value)
)

const rataRataOkupansi = computed(() => {
  if (totalKapasitas.value <= 0) return '0.0'
  return ((totalTerisi.value / totalKapasitas.value) * 100).toFixed(1)
})

// Filter & Pencarian
const filteredAreas = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return areaList.value.filter((area) => {
    const jenis = String(area.jenis_kendaraan || 'semua').toLowerCase()
    const matchesJenis =
      activeJenisFilter.value === 'semua_tab' || jenis === activeJenisFilter.value

    if (!matchesJenis) return false
    if (!q) return true

    return (
      String(area.nama_area || '').toLowerCase().includes(q) ||
      String(area.deskripsi || '').toLowerCase().includes(q) ||
      jenis.includes(q) ||
      getJenisMeta(jenis).label.toLowerCase().includes(q)
    )
  })
})

// Modal Tambah / Edit
const openCreateModal = () => {
  editingAreaId.value = null
  formError.value = ''
  form.nama_area = ''
  form.deskripsi = ''
  form.jenis_kendaraan = 'semua'
  form.kapasitas = ''
  form.terisi = 0
  isModalOpen.value = true
}

const openEditModal = (area) => {
  editingAreaId.value = area.id_area
  formError.value = ''
  form.nama_area = area.nama_area || ''
  form.deskripsi = area.deskripsi || ''
  form.jenis_kendaraan = String(area.jenis_kendaraan || 'semua').toLowerCase()
  form.kapasitas = Number(area.kapasitas || 0)
  form.terisi = Number(area.terisi || 0)
  isModalOpen.value = true
}

const closeModal = () => {
  if (isSubmitting.value) return
  isModalOpen.value = false
  formError.value = ''
}

const submitAreaForm = async () => {
  formError.value = ''
  const nama = form.nama_area.trim()
  const kapasitas = Number(form.kapasitas)
  const terisi = Number(form.terisi || 0)

  if (!nama) {
    formError.value = 'Nama area parkir wajib diisi.'
    return
  }

  if (nama.length > 50) {
    formError.value = 'Nama area parkir maksimal 50 karakter.'
    return
  }

  if (!Number.isInteger(kapasitas) || kapasitas < 1) {
    formError.value = 'Kapasitas harus berupa bilangan bulat minimal 1.'
    return
  }

  if (!Number.isInteger(terisi) || terisi < 0) {
    formError.value = 'Jumlah slot terisi tidak boleh negatif.'
    return
  }

  if (terisi > kapasitas) {
    formError.value = 'Jumlah slot terisi tidak boleh melebihi kapasitas area.'
    return
  }

  isSubmitting.value = true

  try {
    const payload = {
      nama_area: nama,
      deskripsi: form.deskripsi.trim() ? form.deskripsi.trim() : null,
      jenis_kendaraan: form.jenis_kendaraan || 'semua',
      kapasitas,
      terisi,
    }

    if (editingAreaId.value) {
      await api.put(`/admin/area-parkir/${editingAreaId.value}`, payload)
      showToast(`Area "${nama}" berhasil diperbarui.`, 'success')
    } else {
      await api.post('/admin/area-parkir', payload)
      showToast(`Area "${nama}" berhasil ditambahkan.`, 'success')
    }

    isModalOpen.value = false
    await fetchAreas()
  } catch (err) {
    const resData = err?.response?.data
    if (resData?.errors) {
      const firstErr = Object.values(resData.errors).flat()[0]
      formError.value = firstErr || resData?.message || 'Validasi data gagal.'
    } else {
      formError.value = resData?.message || 'Gagal menyimpan data area parkir.'
    }
  } finally {
    isSubmitting.value = false
  }
}

// Kalibrasi Cepat Jumlah Slot Terisi
const openCalibrateModal = (area) => {
  calibratingArea.value = area
  kalibrasiTerisi.value = Number(area.terisi || 0)
  kalibrasiError.value = ''
}

const submitCalibration = async () => {
  if (!calibratingArea.value) return
  kalibrasiError.value = ''

  const cap = Number(calibratingArea.value.kapasitas || 0)
  const val = Number(kalibrasiTerisi.value)

  if (!Number.isInteger(val) || val < 0) {
    kalibrasiError.value = 'Jumlah slot terisi minimal 0.'
    return
  }
  if (val > cap) {
    kalibrasiError.value = `Slot terisi tidak boleh melebihi kapasitas (${cap} slot).`
    return
  }

  isCalibrating.value = true
  try {
    await api.put(`/admin/area-parkir/${calibratingArea.value.id_area}`, {
      nama_area: calibratingArea.value.nama_area,
      deskripsi: calibratingArea.value.deskripsi || null,
      jenis_kendaraan: calibratingArea.value.jenis_kendaraan || 'semua',
      kapasitas: cap,
      terisi: val,
    })
    showToast(
      `Slot terisi "${calibratingArea.value.nama_area}" dikalibrasi menjadi ${val}/${cap}.`,
      'success'
    )
    calibratingArea.value = null
    await fetchAreas()
  } catch (err) {
    kalibrasiError.value =
      err?.response?.data?.message || 'Gagal mengkalibrasi jumlah slot.'
  } finally {
    isCalibrating.value = false
  }
}

// Hapus Area
const confirmDeleteArea = (area) => {
  areaToDelete.value = area
}

const handleDeleteArea = async () => {
  if (!areaToDelete.value) return
  const target = areaToDelete.value
  deletingId.value = target.id_area

  try {
    await api.delete(`/admin/area-parkir/${target.id_area}`)
    showToast(`Area parkir "${target.nama_area}" berhasil dihapus.`, 'success')
    areaToDelete.value = null
    await fetchAreas()
  } catch (err) {
    showToast(
      err?.response?.data?.message ||
        'Gagal menghapus area parkir. Pastikan tidak ada slot terisi atau transaksi terkait.',
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
          INFRASTRUKTUR &amp; ZONA
        </p>
        <h1 class="mt-1 text-xl font-extrabold tracking-tight text-[#0F172A] sm:text-2xl">
          Kelola Area &amp; Slot Parkir
        </h1>
        <p class="mt-1 max-w-2xl text-[11.5px] text-[#64748B]">
          Atur kapasitas slot, lokasi spesifik, dan aturan jenis kendaraan untuk setiap zona parkir secara terintegrasi dengan database.
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
        <span>Tambah Area Baru</span>
      </motion.button>
    </motion.div>

    <!-- 4 Kartu KPI Ringkasan -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32 }"
        class="relative overflow-hidden rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            TOTAL KAPASITAS
          </span>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#EAF6C9] text-olive">
            <ParkingSquare :size="15" />
          </span>
        </div>
        <div class="mt-3 flex items-baseline gap-1.5">
          <span class="text-2xl font-extrabold text-[#0F172A]">{{ formatAngka(totalKapasitas) }}</span>
          <span class="text-[11px] font-medium text-[#64748B]">Slot</span>
        </div>
        <p class="mt-3 text-[10.5px] font-medium text-olive">
          {{ areaList.length }} Zona Terdaftar
        </p>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32, delay: 0.05 }"
        class="relative overflow-hidden rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            TERISI SAAT INI
          </span>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#F3E8E2] text-[#8C4A32]">
            <CarFront :size="15" />
          </span>
        </div>
        <div class="mt-3 flex items-baseline gap-1.5">
          <span class="text-2xl font-extrabold text-[#0F172A]">{{ formatAngka(totalTerisi) }}</span>
          <span class="text-[11px] font-medium text-[#64748B]">Unit</span>
        </div>
        <p class="mt-3 text-[10.5px] text-[#64748B]">
          Akumulasi slot terpakai
        </p>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32, delay: 0.1 }"
        class="relative overflow-hidden rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            SLOT KOSONG
          </span>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#EAF6C9] text-[#50631D]">
            <CheckCircle2 :size="15" />
          </span>
        </div>
        <div class="mt-3 flex items-baseline gap-1.5">
          <span class="text-2xl font-extrabold text-olive">{{ formatAngka(totalSlotKosong) }}</span>
          <span class="text-[11px] font-medium text-[#64748B]">Slot Tersedia</span>
        </div>
        <p class="mt-3 text-[10.5px] text-[#64748B]">
          Siap menampung kendaraan masuk
        </p>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32, delay: 0.15 }"
        class="relative overflow-hidden rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            RATA-RATA OKUPANSI
          </span>
          <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-[#475569]">
            <SquareParking :size="15" />
          </span>
        </div>
        <div class="mt-3 flex items-baseline gap-1">
          <span class="text-2xl font-extrabold text-[#0F172A]">{{ rataRataOkupansi }}%</span>
        </div>
        <p class="mt-3 text-[10.5px] text-[#64748B]">
          Rasio terisi terhadap kapasitas
        </p>
      </motion.article>
    </div>

    <!-- Bar Pencarian & Filter Jenis Kendaraan -->
    <div class="mt-6 flex flex-col justify-between gap-3 rounded-[22px] border border-[#E2E8F0] bg-white p-3.5 shadow-2xs md:flex-row md:items-center">
      <div class="relative flex-1">
        <Search
          :size="14"
          class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari nama area, lokasi/deskripsi, atau jenis kendaraan..."
          class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] py-2 pl-9 pr-4 text-xs text-[#0F172A] placeholder-[#94A3B8] transition-colors focus:border-olive focus:bg-white focus:outline-none"
        />
      </div>

      <div class="flex flex-wrap items-center gap-1.5">
        <button
          type="button"
          @click="activeJenisFilter = 'semua_tab'"
          :class="[
            'cursor-pointer rounded-full px-3.5 py-1.5 text-[11px] font-bold transition-colors',
            activeJenisFilter === 'semua_tab'
              ? 'bg-[#161F00] text-white'
              : 'bg-[#F8FAFC] text-[#475569] hover:bg-[#F4F8EA] hover:text-olive',
          ]"
        >
          Semua ({{ areaList.length }})
        </button>

        <button
          v-for="opt in JENIS_OPTIONS"
          :key="opt.value"
          type="button"
          @click="activeJenisFilter = opt.value"
          :class="[
            'cursor-pointer rounded-full px-3.5 py-1.5 text-[11px] font-semibold transition-colors',
            activeJenisFilter === opt.value
              ? 'bg-olive text-white'
              : 'bg-[#F8FAFC] text-[#475569] hover:bg-[#F4F8EA] hover:text-olive',
          ]"
        >
          {{ opt.shortLabel }}
        </button>
      </div>
    </div>

    <!-- Grid Kartu Area Parkir -->
    <div
      v-if="filteredAreas.length > 0"
      class="mt-6 grid gap-5 md:grid-cols-2"
    >
      <motion.article
        v-for="(area, idx) in filteredAreas"
        :key="area.id_area"
        :initial="{ opacity: 0, y: 14 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.34, delay: Math.min(idx * 0.05, 0.25) }"
        class="flex flex-col justify-between rounded-[26px] border border-[#E2E8F0] bg-white p-6 shadow-2xs transition-colors hover:border-[#CBD5E1] will-change-transform"
      >
        <div>
          <!-- Bagian Atas Kartu: Ikon, Nama, Deskripsi Lokasi, Jenis Kendaraan & Status -->
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3.5 min-w-0">
              <span
                :class="[
                  'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl',
                  getJenisMeta(area.jenis_kendaraan).iconBg,
                ]"
              >
                <component :is="getJenisMeta(area.jenis_kendaraan).icon" :size="19" />
              </span>

              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h2 class="truncate text-sm font-extrabold text-[#0F172A]">
                    {{ area.nama_area }}
                  </h2>
                  <span
                    :class="[
                      'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[9.5px] font-bold',
                      getJenisMeta(area.jenis_kendaraan).badgeClass,
                    ]"
                  >
                    {{ getJenisMeta(area.jenis_kendaraan).label }}
                  </span>
                </div>

                <p class="mt-1 flex items-start gap-1 text-[11px] leading-relaxed text-[#64748B]">
                  <MapPin :size="12" class="mt-0.5 shrink-0 text-[#94A3B8]" />
                  <span class="break-words">
                    {{ area.deskripsi || 'Lokasi belum dideskripsikan' }}
                  </span>
                </p>
              </div>
            </div>

            <span
              :class="[
                'shrink-0 rounded-full px-2.5 py-1 text-[9.5px] font-bold',
                getStatusBadge(area).cls,
              ]"
            >
              {{ getStatusBadge(area).text }}
            </span>
          </div>

          <!-- Ringkasan Kapasitas / Terisi / Sisa Slot -->
          <div class="mt-5 grid grid-cols-3 gap-2.5 rounded-2xl border border-[#F1F5F9] bg-[#F8FAFC] p-3.5 text-center">
            <div>
              <p class="text-[9.5px] font-medium text-[#64748B]">Kapasitas</p>
              <p class="mt-0.5 text-sm font-extrabold text-[#0F172A]">
                {{ formatAngka(area.kapasitas) }}
              </p>
            </div>

            <div class="border-x border-[#E2E8F0]">
              <p class="text-[9.5px] font-medium text-[#64748B]">Terisi</p>
              <p
                :class="[
                  'mt-0.5 text-sm font-extrabold',
                  getOccupancyPercent(area) >= 90 ? 'text-rose-600' : 'text-[#0F172A]',
                ]"
              >
                {{ formatAngka(area.terisi) }}
              </p>
            </div>

            <div>
              <p class="text-[9.5px] font-medium text-[#64748B]">Sisa Slot</p>
              <p class="mt-0.5 text-sm font-extrabold text-olive">
                {{ formatAngka(Math.max(0, (Number(area.kapasitas) || 0) - (Number(area.terisi) || 0))) }}
              </p>
            </div>
          </div>

          <!-- Progress Bar Kepadatan -->
          <div class="mt-4">
            <div class="mb-1.5 flex items-center justify-between text-[10.5px]">
              <span class="font-medium text-[#64748B]">
                {{ getStatusBadge(area).note }}
              </span>
              <span :class="['font-extrabold', getStatusBadge(area).textCls]">
                {{ getOccupancyPercent(area) }}%
              </span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
              <div
                :style="{ width: `${getOccupancyPercent(area)}%` }"
                :class="['h-full rounded-full transition-all duration-300', getStatusBadge(area).barCls]"
              />
            </div>
          </div>
        </div>

        <!-- Tombol Aksi: Kalibrasi, Edit, Hapus -->
        <div class="mt-5 flex items-center justify-between gap-2 border-t border-slate-100 pt-4">
          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="openCalibrateModal(area)"
              class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-1.5 text-[11px] font-semibold text-[#334155] transition-colors hover:border-olive hover:bg-[#F4F8EA] hover:text-olive"
            >
              <SlidersHorizontal :size="12" />
              <span>Kalibrasi</span>
            </button>

            <button
              type="button"
              @click="openEditModal(area)"
              class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-[#E2E8F0] bg-white px-3.5 py-1.5 text-[11px] font-semibold text-[#334155] transition-colors hover:border-olive hover:bg-[#F4F8EA] hover:text-olive"
            >
              <Pencil :size="12" />
              <span>Edit Area</span>
            </button>
          </div>

          <button
            type="button"
            @click="confirmDeleteArea(area)"
            title="Hapus area parkir"
            class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-full border border-[#E2E8F0] bg-white text-[#64748B] transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
          >
            <Trash2 :size="13" />
          </button>
        </div>
      </motion.article>
    </div>

    <!-- Empty / Loading State -->
    <div
      v-else
      class="mt-6 rounded-[26px] border border-dashed border-[#CBD5E1] bg-white px-6 py-12 text-center"
    >
      <p class="text-xs font-semibold text-[#475569]">
        {{
          isLoading
            ? 'Memuat data area parkir dari server...'
            : 'Area parkir tidak tersedia.'
        }}
      </p>
      <button
        v-if="!isLoading"
        type="button"
        @click="openCreateModal"
        class="mt-4 inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-olive px-4 py-2 text-xs font-bold text-white hover:bg-olive-dark"
      >
        <Plus :size="14" />
        <span>Tambah Area Parkir</span>
      </button>
    </div>

    <!-- MODAL TAMBAH / EDIT AREA PARKIR -->
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
                {{ editingAreaId ? 'PERBARUI ZONA' : 'ZONA BARU' }}
              </p>
              <h3 class="mt-0.5 text-base font-extrabold text-[#0F172A]">
                {{ editingAreaId ? 'Edit Area Parkir' : 'Tambah Area Parkir Baru' }}
              </h3>
              <p class="mt-0.5 text-[11px] text-[#64748B]">
                Isi nama zona, lokasi spesifik pada deskripsi, jenis kendaraan, dan kapasitas slot.
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

          <form @submit.prevent="submitAreaForm" class="mt-5 space-y-4">
            <p
              v-if="formError"
              class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-medium text-rose-700"
            >
              {{ formError }}
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                  NAMA AREA (MAKS. 50 KARAKTER)
                </label>
                <input
                  v-model="form.nama_area"
                  type="text"
                  maxlength="50"
                  required
                  placeholder="Contoh: Zona A / Basement 1"
                  class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs font-semibold text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
                />
              </div>

              <div>
                <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                  JENIS KENDARAAN DIIZINKAN
                </label>
                <select
                  v-model="form.jenis_kendaraan"
                  class="w-full cursor-pointer rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs font-semibold text-[#0F172A] focus:border-olive focus:bg-white focus:outline-none"
                >
                  <option
                    v-for="opt in JENIS_OPTIONS"
                    :key="opt.value"
                    :value="opt.value"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
            </div>

            <div>
              <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                DESKRIPSI / LOKASI PASTI
              </label>
              <textarea
                v-model="form.deskripsi"
                rows="2"
                maxlength="255"
                placeholder="Contoh: Lantai Basement 1 dekat pintu masuk utama atau sisi timur gedung"
                class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
              />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                  KAPASITAS TOTAL (SLOT)
                </label>
                <input
                  v-model.number="form.kapasitas"
                  type="number"
                  min="1"
                  step="1"
                  required
                  placeholder="Contoh: 100"
                  class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs font-semibold text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
                />
              </div>

              <div>
                <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                  SLOT TERISI SAAT INI
                </label>
                <input
                  v-model.number="form.terisi"
                  type="number"
                  min="0"
                  step="1"
                  placeholder="0"
                  class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs font-semibold text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
                />
              </div>
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
                    : editingAreaId
                      ? 'Simpan Perubahan'
                      : 'Simpan Area'
                }}
              </button>
            </div>
          </form>
        </motion.div>
      </div>
    </AnimatePresence>

    <!-- MODAL KALIBRASI SLOT TERISI -->
    <AnimatePresence>
      <div
        v-if="calibratingArea"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <motion.div
          :initial="{ opacity: 0 }"
          :animate="{ opacity: 1 }"
          :exit="{ opacity: 0 }"
          :transition="{ duration: 0.2 }"
          @click="calibratingArea = null"
          class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs"
        />

        <motion.div
          :initial="{ opacity: 0, scale: 0.94, y: 12 }"
          :animate="{ opacity: 1, scale: 1, y: 0 }"
          :exit="{ opacity: 0, scale: 0.95, y: 10 }"
          :transition="{ duration: 0.2 }"
          class="relative z-10 w-full max-w-sm rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-xl will-change-transform"
        >
          <h3 class="text-base font-extrabold text-[#0F172A]">Kalibrasi Slot Terisi</h3>
          <p class="mt-1 text-xs text-[#64748B]">
            Sesuaikan jumlah unit terisi di <span class="font-bold text-[#0F172A]">{{ calibratingArea.nama_area }}</span> (Kapasitas maks: {{ calibratingArea.kapasitas }} slot).
          </p>

          <form @submit.prevent="submitCalibration" class="mt-4 space-y-4">
            <p
              v-if="kalibrasiError"
              class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700"
            >
              {{ kalibrasiError }}
            </p>

            <div>
              <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                JUMLAH TERISI
              </label>
              <input
                v-model.number="kalibrasiTerisi"
                type="number"
                min="0"
                :max="calibratingArea.kapasitas"
                required
                class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs font-semibold text-[#0F172A] focus:border-olive focus:bg-white focus:outline-none"
              />
            </div>

            <div class="flex items-center justify-end gap-2">
              <button
                type="button"
                @click="calibratingArea = null"
                :disabled="isCalibrating"
                class="cursor-pointer rounded-full border border-[#E2E8F0] bg-white px-4 py-2 text-xs font-semibold text-[#475569] hover:bg-slate-50"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="isCalibrating"
                class="cursor-pointer rounded-full bg-olive px-5 py-2 text-xs font-bold text-white hover:bg-olive-dark disabled:opacity-60"
              >
                {{ isCalibrating ? 'Menyimpan...' : 'Simpan Slot' }}
              </button>
            </div>
          </form>
        </motion.div>
      </div>
    </AnimatePresence>

    <!-- MODAL KONFIRMASI HAPUS AREA -->
    <AnimatePresence>
      <div
        v-if="areaToDelete"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <motion.div
          :initial="{ opacity: 0 }"
          :animate="{ opacity: 1 }"
          :exit="{ opacity: 0 }"
          :transition="{ duration: 0.2 }"
          @click="areaToDelete = null"
          class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs"
        />

        <motion.div
          :initial="{ opacity: 0, scale: 0.94, y: 12 }"
          :animate="{ opacity: 1, scale: 1, y: 0 }"
          :exit="{ opacity: 0, scale: 0.95, y: 10 }"
          :transition="{ duration: 0.2 }"
          class="relative z-10 w-full max-w-md rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-xl will-change-transform"
        >
          <h3 class="text-base font-extrabold text-[#0F172A]">Hapus Area Parkir?</h3>
          <p class="mt-2 text-xs leading-relaxed text-[#64748B]">
            Area <span class="font-bold text-[#0F172A]">{{ areaToDelete.nama_area }}</span>
            (Kapasitas {{ areaToDelete.kapasitas }} slot) akan dihapus permanen. Area yang masih memiliki slot terisi tidak dapat dihapus.
          </p>

          <div class="mt-6 flex items-center justify-end gap-2.5">
            <button
              type="button"
              @click="areaToDelete = null"
              :disabled="deletingId !== null"
              class="cursor-pointer rounded-full border border-[#E2E8F0] bg-white px-4 py-2 text-xs font-semibold text-[#475569] hover:bg-slate-50"
            >
              Batal
            </button>

            <button
              type="button"
              @click="handleDeleteArea"
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