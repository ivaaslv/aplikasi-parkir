<script setup>
import {
  CarFront,
  Bike,
  Truck,
  Plus,
  Pencil,
  Trash2,
  Search,
  Download,
  CheckCircle2,
  AlertCircle,
  X,
  UserCheck,
  Palette,
} from 'lucide-vue-next'
import { AnimatePresence, motion } from 'motion-v'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '../layouts/DashboardLayout.vue'
import api from '../../services/api'
import { getAuthUser } from '../../router'

const KATEGORI_TABS = [
  { key: 'semua', label: 'Semua Jenis' },
  { key: 'mobil', label: 'Mobil' },
  { key: 'motor', label: 'Motor' },
  { key: 'lainnya', label: 'Lainnya' },
]

const JENIS_OPTIONS = [
  { value: 'mobil', label: 'Mobil', hint: 'Mobil pribadi, MPV, SUV, sedan' },
  { value: 'motor', label: 'Motor', hint: 'Sepeda motor, skuter, motor sport' },
  { value: 'lainnya', label: 'Lainnya', hint: 'Box, truk ringan, bus, VIP/khusus' },
]

const router = useRouter()
const authState = getAuthUser()

const currentRole = ref(authState.role || 'admin')
const authUser = ref(authState.user)
const activeMenu = ref('kelola-kendaraan')

const kendaraanList = ref([])
const isLoading = ref(false)
const isSubmitting = ref(false)
const deletingId = ref(null)

const searchQuery = ref('')
const activeKategori = ref('semua')
const currentPage = ref(1)
const pageSize = 8

// Modal Tambah / Edit
const isModalOpen = ref(false)
const editingId = ref(null)
const formError = ref('')

const form = reactive({
  plat_nomor: '',
  jenis_kendaraan: 'mobil',
  warna: '',
  pemilik: '',
})

// Modal Konfirmasi Hapus
const kendaraanToDelete = ref(null)

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

const normalizeJenis = (val) => {
  const raw = String(val || '').toLowerCase().trim()
  if (['mobil', 'car', 'mpv', 'suv', 'sedan'].includes(raw)) return 'mobil'
  if (['motor', 'sepeda motor', 'motorcycle', 'skuter', 'scooter'].includes(raw)) return 'motor'
  return raw || 'lainnya'
}

const getKategoriMeta = (jenis) => {
  const norm = normalizeJenis(jenis)
  if (norm === 'mobil') {
    return {
      key: 'mobil',
      label: 'Mobil',
      icon: CarFront,
      badgeCls: 'bg-[#EAF6C9] text-[#50631D] border-[#C7E182]',
      kpiBg: 'bg-[#EAF6C9] text-[#50631D]',
      barCls: 'bg-olive',
    }
  }
  if (norm === 'motor') {
    return {
      key: 'motor',
      label: 'Motor',
      icon: Bike,
      badgeCls: 'bg-sky-50 text-sky-800 border-sky-200',
      kpiBg: 'bg-sky-100 text-sky-800',
      barCls: 'bg-sky-600',
    }
  }
  const customLabel =
    norm && norm !== 'lainnya'
      ? norm.charAt(0).toUpperCase() + norm.slice(1)
      : 'Lainnya'
  return {
    key: 'lainnya',
    label: customLabel,
    icon: Truck,
    badgeCls: 'bg-[#F3E8E2] text-[#8C4A32] border-[#E7CFC3]',
    kpiBg: 'bg-[#F3E8E2] text-[#8C4A32]',
    barCls: 'bg-[#8C4A32]',
  }
}

const getPencatatName = (item) =>
  item?.user?.nama ||
  item?.user?.nama_lengkap ||
  item?.user?.name ||
  item?.user?.username ||
  (item?.id_user ? `User #${item.id_user}` : 'Sistem')

const extractList = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  return []
}

const fetchKendaraan = async () => {
  isLoading.value = true
  try {
    const { data } = await api.get('/admin/kendaraan')
    kendaraanList.value = extractList(data)
  } catch (err) {
    showToast(
      err?.response?.data?.message || 'Gagal memuat data kendaraan dari server.',
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
        currentRole.value = String(data.user.role || currentRole.value).toLowerCase()
        localStorage.setItem('user', JSON.stringify(data.user))
      }
    } catch {
      // Ditangani interceptor
    }
  }
  await fetchKendaraan()
})

// Ringkasan KPI
const totalCount = computed(() => kendaraanList.value.length)

const mobilCount = computed(
  () => kendaraanList.value.filter((k) => normalizeJenis(k.jenis_kendaraan) === 'mobil').length
)

const motorCount = computed(
  () => kendaraanList.value.filter((k) => normalizeJenis(k.jenis_kendaraan) === 'motor').length
)

const lainnyaCount = computed(
  () =>
    kendaraanList.value.filter((k) => {
      const n = normalizeJenis(k.jenis_kendaraan)
      return n !== 'mobil' && n !== 'motor'
    }).length
)

const pctMobil = computed(() =>
  totalCount.value ? ((mobilCount.value / totalCount.value) * 100).toFixed(1) : '0.0'
)
const pctMotor = computed(() =>
  totalCount.value ? ((motorCount.value / totalCount.value) * 100).toFixed(1) : '0.0'
)
const pctLainnya = computed(() =>
  totalCount.value ? ((lainnyaCount.value / totalCount.value) * 100).toFixed(1) : '0.0'
)

const countByTab = (key) => {
  if (key === 'semua') return totalCount.value
  if (key === 'mobil') return mobilCount.value
  if (key === 'motor') return motorCount.value
  return lainnyaCount.value
}

// Filter & Pencarian
const filteredKendaraan = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return kendaraanList.value.filter((item) => {
    const norm = normalizeJenis(item.jenis_kendaraan)
    const matchesTab =
      activeKategori.value === 'semua' ||
      (activeKategori.value === 'lainnya'
        ? norm !== 'mobil' && norm !== 'motor'
        : norm === activeKategori.value)

    if (!matchesTab) return false
    if (!q) return true

    return (
      String(item.plat_nomor || '').toLowerCase().includes(q) ||
      String(item.pemilik || '').toLowerCase().includes(q) ||
      String(item.warna || '').toLowerCase().includes(q) ||
      String(item.jenis_kendaraan || '').toLowerCase().includes(q) ||
      getPencatatName(item).toLowerCase().includes(q)
    )
  })
})

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredKendaraan.value.length / pageSize))
)

const paginatedKendaraan = computed(() => {
  const safePage = Math.min(currentPage.value, totalPages.value)
  const start = (safePage - 1) * pageSize
  return filteredKendaraan.value.slice(start, start + pageSize)
})

const rangeStart = computed(() =>
  filteredKendaraan.value.length === 0 ? 0 : (Math.min(currentPage.value, totalPages.value) - 1) * pageSize + 1
)

const rangeEnd = computed(() =>
  Math.min(Math.min(currentPage.value, totalPages.value) * pageSize, filteredKendaraan.value.length)
)

const selectTab = (key) => {
  activeKategori.value = key
  currentPage.value = 1
}

const onSearchInput = () => {
  currentPage.value = 1
}

// Ekspor CSV (kompatibel dengan Microsoft Excel)
const handleExportCsv = () => {
  if (!filteredKendaraan.value.length) {
    showToast('Tidak ada data kendaraan untuk diekspor.', 'error')
    return
  }

  const headers = ['ID Kendaraan', 'Plat Nomor', 'Jenis Kendaraan', 'Warna', 'Pemilik', 'Dicatat Oleh']
  const rows = filteredKendaraan.value.map((item) => [
    item.id_kendaraan ?? '',
    item.plat_nomor ?? '',
    item.jenis_kendaraan ?? '',
    item.warna ?? '',
    item.pemilik ?? '',
    getPencatatName(item),
  ])

  const escapeCsv = (cell) => {
    const str = String(cell ?? '').replace(/"/g, '""')
    return `"${str}"`
  }

  const csvContent =
    '\uFEFF' + [headers, ...rows].map((row) => row.map(escapeCsv).join(';')).join('\n')

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', `data-kendaraan-parkflow-${new Date().toISOString().slice(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)

  showToast(`Berhasil mengekspor ${filteredKendaraan.value.length} data kendaraan.`, 'success')
}

// Modal Tambah / Edit
const openCreateModal = () => {
  editingId.value = null
  formError.value = ''
  form.plat_nomor = ''
  form.jenis_kendaraan = 'mobil'
  form.warna = ''
  form.pemilik = ''
  isModalOpen.value = true
}

const openEditModal = (item) => {
  editingId.value = item.id_kendaraan
  formError.value = ''
  form.plat_nomor = item.plat_nomor || ''
  const norm = normalizeJenis(item.jenis_kendaraan)
  form.jenis_kendaraan = ['mobil', 'motor', 'lainnya'].includes(norm) ? norm : 'lainnya'
  form.warna = item.warna || ''
  form.pemilik = item.pemilik || ''
  isModalOpen.value = true
}

const closeModal = () => {
  if (isSubmitting.value) return
  isModalOpen.value = false
  formError.value = ''
}

const submitForm = async () => {
  formError.value = ''
  const plat = form.plat_nomor.toUpperCase().trim()
  const jenis = String(form.jenis_kendaraan || '').toLowerCase().trim()
  const warna = form.warna.trim()
  const pemilik = form.pemilik.trim()

  if (!plat) {
    formError.value = 'Plat nomor wajib diisi.'
    return
  }

  if (plat.length > 15) {
    formError.value = 'Plat nomor maksimal 15 karakter.'
    return
  }

  if (!jenis) {
    formError.value = 'Jenis kendaraan wajib dipilih.'
    return
  }

  if (warna.length > 20) {
    formError.value = 'Warna maksimal 20 karakter.'
    return
  }

  if (pemilik.length > 100) {
    formError.value = 'Nama pemilik maksimal 100 karakter.'
    return
  }

  isSubmitting.value = true

  try {
    const payload = {
      plat_nomor: plat,
      jenis_kendaraan: jenis,
      warna: warna || null,
      pemilik: pemilik || null,
      id_user: authUser.value?.id_user || authUser.value?.id || undefined,
    }

    if (editingId.value) {
      await api.put(`/admin/kendaraan/${editingId.value}`, payload)
      showToast(`Kendaraan "${plat}" berhasil diperbarui.`, 'success')
    } else {
      await api.post('/admin/kendaraan', payload)
      showToast(`Kendaraan "${plat}" berhasil didaftarkan.`, 'success')
    }

    isModalOpen.value = false
    await fetchKendaraan()
  } catch (err) {
    const resData = err?.response?.data
    if (resData?.errors) {
      const firstErr = Object.values(resData.errors).flat()[0]
      formError.value = firstErr || resData?.message || 'Validasi data gagal.'
    } else {
      formError.value = resData?.message || 'Gagal menyimpan data kendaraan.'
    }
  } finally {
    isSubmitting.value = false
  }
}

// Hapus Kendaraan
const confirmDelete = (item) => {
  kendaraanToDelete.value = item
}

const handleDelete = async () => {
  if (!kendaraanToDelete.value) return
  const target = kendaraanToDelete.value
  deletingId.value = target.id_kendaraan

  try {
    await api.delete(`/admin/kendaraan/${target.id_kendaraan}`)
    showToast(`Kendaraan "${target.plat_nomor}" berhasil dihapus.`, 'success')
    kendaraanToDelete.value = null
    await fetchKendaraan()
  } catch (err) {
    showToast(
      err?.response?.data?.message ||
        'Gagal menghapus kendaraan. Periksa apakah masih terhubung dengan transaksi.',
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
      class="mb-7 flex flex-col justify-between gap-4 lg:flex-row lg:items-center will-change-transform"
    >
      <div>
        <p class="text-[9.5px] font-bold tracking-[0.16em] text-olive">
          DATABASE KENDARAAN
        </p>
        <h1 class="mt-1 text-xl font-extrabold tracking-tight text-[#0F172A] sm:text-2xl">
          Kelola Kendaraan Terdaftar
        </h1>
        <p class="mt-1 max-w-2xl text-[11.5px] text-[#64748B]">
          Kelola data plat nomor, kategori kendaraan, warna, dan informasi pemilik yang tersimpan pada sistem parkir.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <button
          type="button"
          @click="handleExportCsv"
          class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-[#E2E8F0] bg-white px-4 py-2.5 text-xs font-semibold text-[#334155] transition-colors hover:border-olive hover:bg-[#F4F8EA] hover:text-olive"
        >
          <Download :size="14" />
          <span>Export CSV</span>
        </button>

        <motion.button
          type="button"
          @click="openCreateModal"
          :while-hover="{ scale: 1.02 }"
          :while-press="{ scale: 0.97 }"
          class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-olive px-5 py-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-olive-dark will-change-transform"
        >
          <Plus :size="15" />
          <span>Daftarkan Kendaraan</span>
        </motion.button>
      </div>
    </motion.div>

    <!-- 4 Kartu KPI -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32 }"
        class="rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-[#EAF6C9] text-[#50631D]">
            <CarFront :size="17" />
          </span>
          <span class="rounded-full bg-[#F4F8EA] px-2.5 py-0.5 text-[10px] font-bold text-olive">
            100% Terdata
          </span>
        </div>
        <p class="mt-3.5 text-2xl font-extrabold text-[#0F172A]">{{ totalCount }}</p>
        <p class="mt-0.5 text-[11px] font-medium text-[#64748B]">Total Kendaraan Terdaftar</p>
        <div class="mt-3.5 h-1.5 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
          <div class="h-full w-full rounded-full bg-[#84A92C]" />
        </div>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32, delay: 0.05 }"
        class="rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-[#EAF6C9] text-[#50631D]">
            <CarFront :size="17" />
          </span>
          <span class="text-[10.5px] font-bold text-[#64748B]">{{ pctMobil }}%</span>
        </div>
        <p class="mt-3.5 text-2xl font-extrabold text-[#0F172A]">{{ mobilCount }}</p>
        <p class="mt-0.5 text-[11px] font-medium text-[#64748B]">Mobil</p>
        <div class="mt-3.5 h-1.5 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
          <div
            :style="{ width: `${pctMobil}%` }"
            class="h-full rounded-full bg-olive transition-all duration-300"
          />
        </div>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32, delay: 0.1 }"
        class="rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-sky-100 text-sky-800">
            <Bike :size="17" />
          </span>
          <span class="text-[10.5px] font-bold text-[#64748B]">{{ pctMotor }}%</span>
        </div>
        <p class="mt-3.5 text-2xl font-extrabold text-[#0F172A]">{{ motorCount }}</p>
        <p class="mt-0.5 text-[11px] font-medium text-[#64748B]">Sepeda Motor</p>
        <div class="mt-3.5 h-1.5 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
          <div
            :style="{ width: `${pctMotor}%` }"
            class="h-full rounded-full bg-sky-600 transition-all duration-300"
          />
        </div>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.32, delay: 0.15 }"
        class="rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs will-change-transform"
      >
        <div class="flex items-center justify-between">
          <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-[#F3E8E2] text-[#8C4A32]">
            <Truck :size="17" />
          </span>
          <span class="text-[10.5px] font-bold text-[#64748B]">{{ pctLainnya }}%</span>
        </div>
        <p class="mt-3.5 text-2xl font-extrabold text-[#0F172A]">{{ lainnyaCount }}</p>
        <p class="mt-0.5 text-[11px] font-medium text-[#64748B]">Lainnya</p>
        <div class="mt-3.5 h-1.5 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
          <div
            :style="{ width: `${pctLainnya}%` }"
            class="h-full rounded-full bg-[#8C4A32] transition-all duration-300"
          />
        </div>
      </motion.article>
    </div>

    <!-- Container Tabel & Filter -->
    <motion.section
      :initial="{ opacity: 0, y: 14 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.36, delay: 0.1 }"
      class="mt-6 rounded-[26px] border border-[#E2E8F0] bg-white p-5 shadow-2xs sm:p-6 will-change-transform"
    >
      <!-- Pencarian & Tab Filter -->
      <div class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-5 lg:flex-row lg:items-center">
        <div class="relative flex-1">
          <Search
            :size="14"
            class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]"
          />
          <input
            v-model="searchQuery"
            @input="onSearchInput"
            type="text"
            placeholder="Cari plat nomor, nama pemilik, atau warna kendaraan..."
            class="w-full rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] py-2.5 pl-9 pr-4 text-xs text-[#0F172A] placeholder-[#94A3B8] transition-colors focus:border-olive focus:bg-white focus:outline-none"
          />
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
          <button
            v-for="tab in KATEGORI_TABS"
            :key="tab.key"
            type="button"
            @click="selectTab(tab.key)"
            :class="[
              'cursor-pointer rounded-full px-3.5 py-1.5 text-[11px] font-bold transition-colors',
              activeKategori === tab.key
                ? 'bg-[#161F00] text-white'
                : 'bg-[#F8FAFC] text-[#475569] hover:bg-[#F4F8EA] hover:text-olive',
            ]"
          >
            {{ tab.label }} ({{ countByTab(tab.key) }})
          </button>
        </div>
      </div>

      <!-- Tabel Kendaraan -->
      <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[680px] border-collapse text-left">
          <thead>
            <tr class="border-b border-slate-100 text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
              <th class="py-3 pr-4">PLAT NOMOR</th>
              <th class="py-3 pr-4">KATEGORI</th>
              <th class="py-3 pr-4">WARNA</th>
              <th class="py-3 pr-4">PEMILIK &amp; PENCATAT</th>
              <th class="py-3 text-right">AKSI</th>
            </tr>
          </thead>

          <tbody v-if="paginatedKendaraan.length > 0" class="divide-y divide-slate-100 text-xs">
            <tr
              v-for="item in paginatedKendaraan"
              :key="item.id_kendaraan"
              class="transition-colors hover:bg-[#F8FAFC]/80"
            >
              <td class="py-4 pr-4">
                <span class="inline-flex items-center rounded-xl border border-[#CBD5E1] bg-[#F1F5F9] px-3 py-1.5 font-mono text-xs font-extrabold tracking-wider text-[#0F172A]">
                  {{ item.plat_nomor }}
                </span>
              </td>

              <td class="py-4 pr-4">
                <span
                  :class="[
                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[10.5px] font-bold',
                    getKategoriMeta(item.jenis_kendaraan).badgeCls,
                  ]"
                >
                  <component :is="getKategoriMeta(item.jenis_kendaraan).icon" :size="12" />
                  <span>{{ getKategoriMeta(item.jenis_kendaraan).label }}</span>
                </span>
              </td>

              <td class="py-4 pr-4">
                <div class="inline-flex items-center gap-2 text-xs font-medium text-[#334155]">
                  <Palette :size="13" class="text-[#94A3B8]" />
                  <span>{{ item.warna || '-' }}</span>
                </div>
              </td>

              <td class="py-4 pr-4">
                <p class="font-bold text-[#0F172A]">
                  {{ item.pemilik || 'Tanpa Nama Pemilik' }}
                </p>
                <p class="mt-0.5 inline-flex items-center gap-1 text-[10.5px] text-[#64748B]">
                  <UserCheck :size="11" class="text-[#94A3B8]" />
                  <span>Dicatat: {{ getPencatatName(item) }}</span>
                </p>
              </td>

              <td class="py-4 text-right">
                <div class="inline-flex items-center gap-1.5">
                  <button
                    type="button"
                    @click="openEditModal(item)"
                    title="Edit data kendaraan"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-xl border border-[#E2E8F0] bg-white text-[#475569] transition-colors hover:border-olive hover:bg-[#F4F8EA] hover:text-olive"
                  >
                    <Pencil :size="13" />
                  </button>

                  <button
                    type="button"
                    @click="confirmDelete(item)"
                    title="Hapus kendaraan"
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
              <td colspan="5" class="py-12 text-center text-xs text-[#64748B]">
                {{
                  isLoading
                    ? 'Memuat data kendaraan dari server...'
                    : 'Belum ada data kendaraan yang cocok dengan filter atau pencarian.'
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer & Paginasi -->
      <div class="mt-5 flex flex-col items-center justify-between gap-3 border-t border-slate-100 pt-4 text-[11px] text-[#64748B] sm:flex-row">
        <p>
          Menampilkan <span class="font-bold text-[#0F172A]">{{ rangeStart }} - {{ rangeEnd }}</span>
          dari <span class="font-bold text-[#0F172A]">{{ filteredKendaraan.length }}</span> kendaraan terdaftar
        </p>

        <div v-if="totalPages > 1" class="flex items-center gap-1.5">
          <button
            type="button"
            :disabled="currentPage <= 1"
            @click="currentPage--"
            class="cursor-pointer rounded-xl border border-[#E2E8F0] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569] hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Prev
          </button>

          <button
            v-for="page in totalPages"
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
            class="cursor-pointer rounded-xl border border-[#E2E8F0] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569] hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Next
          </button>
        </div>
      </div>
    </motion.section>

    <!-- MODAL TAMBAH / EDIT KENDARAAN -->
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
          class="relative z-10 w-full max-w-md rounded-[26px] border border-[#E2E8F0] bg-white p-6 shadow-xl will-change-transform"
        >
          <div class="flex items-start justify-between gap-3 border-b border-[#E2E8F0] pb-4">
            <div>
              <p class="text-[9.5px] font-bold tracking-[0.16em] text-olive">
                {{ editingId ? 'PERBARUI DATA' : 'REGISTRASI KENDARAAN' }}
              </p>
              <h3 class="mt-0.5 text-base font-extrabold text-[#0F172A]">
                {{ editingId ? 'Edit Kendaraan' : 'Daftarkan Kendaraan Baru' }}
              </h3>
              <p class="mt-0.5 text-[11px] text-[#64748B]">
                Lengkapi plat nomor, kategori, warna, dan nama pemilik kendaraan.
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

          <form @submit.prevent="submitForm" class="mt-5 space-y-4">
            <p
              v-if="formError"
              class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-medium text-rose-700"
            >
              {{ formError }}
            </p>

            <div>
              <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                PLAT NOMOR (MAKS. 15 KARAKTER)
              </label>
              <input
                v-model="form.plat_nomor"
                type="text"
                maxlength="15"
                required
                placeholder="Contoh: N 1234 AB"
                class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 font-mono text-xs font-bold uppercase text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
              />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                  JENIS KENDARAAN
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

              <div>
                <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                  WARNA (MAKS. 20 KARAKTER)
                </label>
                <input
                  v-model="form.warna"
                  type="text"
                  maxlength="20"
                  placeholder="Contoh: Hitam Metalik"
                  class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs font-medium text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
                />
              </div>
            </div>

            <div>
              <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                NAMA PEMILIK (MAKS. 100 KARAKTER)
              </label>
              <input
                v-model="form.pemilik"
                type="text"
                maxlength="100"
                placeholder="Contoh: Rian Pratama"
                class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs font-medium text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
              />
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
                    : editingId
                      ? 'Simpan Perubahan'
                      : 'Simpan Kendaraan'
                }}
              </button>
            </div>
          </form>
        </motion.div>
      </div>
    </AnimatePresence>

    <!-- MODAL KONFIRMASI HAPUS KENDARAAN -->
    <AnimatePresence>
      <div
        v-if="kendaraanToDelete"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <motion.div
          :initial="{ opacity: 0 }"
          :animate="{ opacity: 1 }"
          :exit="{ opacity: 0 }"
          :transition="{ duration: 0.2 }"
          @click="kendaraanToDelete = null"
          class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs"
        />

        <motion.div
          :initial="{ opacity: 0, scale: 0.94, y: 12 }"
          :animate="{ opacity: 1, scale: 1, y: 0 }"
          :exit="{ opacity: 0, scale: 0.95, y: 10 }"
          :transition="{ duration: 0.2 }"
          class="relative z-10 w-full max-w-md rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-xl will-change-transform"
        >
          <h3 class="text-base font-extrabold text-[#0F172A]">Hapus Data Kendaraan?</h3>
          <p class="mt-2 text-xs leading-relaxed text-[#64748B]">
            Kendaraan dengan plat nomor
            <span class="font-mono font-bold text-[#0F172A]">
              {{ kendaraanToDelete.plat_nomor }}
            </span>
            <span v-if="kendaraanToDelete.pemilik">
              (Pemilik: <span class="font-semibold text-[#0F172A]">{{ kendaraanToDelete.pemilik }}</span>)
            </span>
            akan dihapus permanen dari database.
          </p>

          <div class="mt-6 flex items-center justify-end gap-2.5">
            <button
              type="button"
              @click="kendaraanToDelete = null"
              :disabled="deletingId !== null"
              class="cursor-pointer rounded-full border border-[#E2E8F0] bg-white px-4 py-2 text-xs font-semibold text-[#475569] hover:bg-slate-50"
            >
              Batal
            </button>

            <button
              type="button"
              @click="handleDelete"
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