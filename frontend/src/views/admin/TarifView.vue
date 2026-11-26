<script setup>
import {
  Tag,
  Bike,
  CarFront,
  Truck,
  Plus,
  Pencil,
  Trash2,
  Search,
  Calculator,
  Clock,
  CheckCircle2,
  AlertCircle,
  X,
  TrendingUp,
  Layers,
  TicketX,
  Save,
} from 'lucide-vue-next'
import { AnimatePresence, motion } from 'motion-v'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '../layouts/DashboardLayout.vue'
import api from '../../services/api'
import { getAuthUser } from '../../router'

const JENIS_OPTIONS = [
  {
    value: 'motor',
    label: 'Motor',
    subtitle: 'Kendaraan roda dua & skuter',
    icon: Bike,
    badgeBg: 'bg-[#EAF6C9] text-[#50631D]',
  },
  {
    value: 'mobil',
    label: 'Mobil',
    subtitle: 'Mobil pribadi, MPV & SUV',
    icon: CarFront,
    badgeBg: 'bg-[#F3E8E2] text-[#8C4A32]',
  },
  {
    value: 'lainnya',
    label: 'Lainnya',
    subtitle: 'Box, truk ringan & kendaraan khusus',
    icon: Truck,
    badgeBg: 'bg-slate-100 text-slate-700',
  },
]

const router = useRouter()
const authState = getAuthUser()

const currentRole = ref(authState.role || 'admin')
const authUser = ref(authState.user)
const activeMenu = ref('kelola-tarif')

const tarifList = ref([])
const isLoading = ref(false)
const isSubmitting = ref(false)
const deletingId = ref(null)
const searchQuery = ref('')

// Pengaturan Denda Karcis Hilang (tb_pengaturan_parkir)
const isLoadingPengaturan = ref(false)
const isSavingPengaturan = ref(false)
const pengaturanError = ref('')
const pengaturanForm = reactive({
  denda_karcis_hilang: 25000,
  skema_denda: 'tambahan',
})

// Modal Form Tambah / Edit Tarif
const isModalOpen = ref(false)
const editingTarifId = ref(null)
const formError = ref('')

const form = reactive({
  jenis_kendaraan: 'motor',
  tarif_per_jam: '',
})

// Modal Konfirmasi Hapus
const tarifToDelete = ref(null)

// Simulator Hitung Tarif
const simTarifId = ref('')
const simDurasiJam = ref(2)
const simKarcisHilang = ref(false)

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

const formatRupiah = (amount) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(amount) || 0)

const formatAngka = (amount) =>
  new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
  }).format(Number(amount) || 0)

const getJenisMeta = (jenis) => {
  const key = String(jenis || '').toLowerCase().trim()
  return (
    JENIS_OPTIONS.find((opt) => opt.value === key) || {
      value: key || 'lainnya',
      label: key ? key.charAt(0).toUpperCase() + key.slice(1) : 'Lainnya',
      subtitle: 'Kategori kendaraan terdaftar',
      icon: Tag,
      badgeBg: 'bg-[#F4F8EA] text-olive',
    }
  )
}

const extractList = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  return []
}

const fetchTarif = async () => {
  isLoading.value = true
  try {
    const { data } = await api.get('/admin/tarif')
    tarifList.value = extractList(data)

    if (tarifList.value.length > 0) {
      const stillExists = tarifList.value.some(
        (t) => String(t.id_tarif) === String(simTarifId.value)
      )
      if (!stillExists) {
        simTarifId.value = String(tarifList.value[0].id_tarif)
      }
    } else {
      simTarifId.value = ''
    }
  } catch (err) {
    showToast(
      err?.response?.data?.message || 'Gagal memuat data tarif dari server.',
      'error'
    )
  } finally {
    isLoading.value = false
  }
}

const fetchPengaturanParkir = async () => {
  isLoadingPengaturan.value = true
  pengaturanError.value = ''
  try {
    const { data } = await api.get('/admin/pengaturan-parkir')
    const cfg = data?.data || data
    if (cfg) {
      pengaturanForm.denda_karcis_hilang = Number(cfg.denda_karcis_hilang ?? 25000)
      pengaturanForm.skema_denda =
        cfg.skema_denda === 'pengganti' ? 'pengganti' : 'tambahan'
    }
  } catch (err) {
    pengaturanError.value =
      err?.response?.data?.message || 'Gagal memuat pengaturan denda karcis hilang.'
  } finally {
    isLoadingPengaturan.value = false
  }
}

const handleSavePengaturan = async () => {
  pengaturanError.value = ''
  const nominal = Number(pengaturanForm.denda_karcis_hilang)

  if (Number.isNaN(nominal) || nominal < 0) {
    pengaturanError.value = 'Nominal denda karcis hilang minimal Rp 0.'
    return
  }

  if (!['tambahan', 'pengganti'].includes(pengaturanForm.skema_denda)) {
    pengaturanError.value = 'Skema denda tidak valid.'
    return
  }

  isSavingPengaturan.value = true
  try {
    const { data } = await api.put('/admin/pengaturan-parkir', {
      denda_karcis_hilang: Math.round(nominal),
      skema_denda: pengaturanForm.skema_denda,
    })

    const updated = data?.data
    if (updated) {
      pengaturanForm.denda_karcis_hilang = Number(updated.denda_karcis_hilang ?? nominal)
      pengaturanForm.skema_denda =
        updated.skema_denda === 'pengganti' ? 'pengganti' : 'tambahan'
    }

    showToast('Pengaturan denda karcis hilang berhasil disimpan.', 'success')
  } catch (err) {
    const resData = err?.response?.data
    if (resData?.errors) {
      const firstErr = Object.values(resData.errors).flat()[0]
      pengaturanError.value = firstErr || resData?.message || 'Validasi gagal.'
    } else {
      pengaturanError.value =
        resData?.message || 'Gagal menyimpan pengaturan denda.'
    }
  } finally {
    isSavingPengaturan.value = false
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
  await Promise.all([fetchTarif(), fetchPengaturanParkir()])
})

// Statistik & KPI dari data real
const averageTarif = computed(() => {
  if (!tarifList.value.length) return 0
  const total = tarifList.value.reduce(
    (sum, item) => sum + Number(item.tarif_per_jam || 0),
    0
  )
  return Math.round(total / tarifList.value.length)
})

const lowestTarif = computed(() => {
  if (!tarifList.value.length) return null
  return [...tarifList.value].sort(
    (a, b) => Number(a.tarif_per_jam) - Number(b.tarif_per_jam)
  )[0]
})

const highestTarif = computed(() => {
  if (!tarifList.value.length) return null
  return [...tarifList.value].sort(
    (a, b) => Number(b.tarif_per_jam) - Number(a.tarif_per_jam)
  )[0]
})

const lastUpdatedText = computed(() => {
  if (!tarifList.value.length) return 'Belum ada data'
  const timestamps = tarifList.value
    .map((t) => new Date(t.updated_at || t.created_at).getTime())
    .filter((n) => !Number.isNaN(n))
  if (!timestamps.length) return 'Tersinkron'
  const latest = new Date(Math.max(...timestamps))
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(latest)
})

const barChartItems = computed(() => {
  if (!tarifList.value.length) return []
  const maxVal = Math.max(
    ...tarifList.value.map((t) => Number(t.tarif_per_jam || 0)),
    1
  )
  return tarifList.value.map((t) => ({
    id: t.id_tarif,
    label: getJenisMeta(t.jenis_kendaraan).label,
    value: Number(t.tarif_per_jam || 0),
    heightPercent: Math.max(
      24,
      Math.round((Number(t.tarif_per_jam || 0) / maxVal) * 100)
    ),
  }))
})

const filteredTarif = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return tarifList.value
  return tarifList.value.filter((item) => {
    const meta = getJenisMeta(item.jenis_kendaraan)
    return (
      String(item.jenis_kendaraan || '').toLowerCase().includes(q) ||
      meta.label.toLowerCase().includes(q) ||
      meta.subtitle.toLowerCase().includes(q) ||
      String(item.tarif_per_jam || '').includes(q)
    )
  })
})

// Simulator Hitung Tarif + Denda Karcis Hilang
const selectedSimTarif = computed(
  () =>
    tarifList.value.find((t) => String(t.id_tarif) === String(simTarifId.value)) ||
    tarifList.value[0] ||
    null
)

const simBilledHours = computed(() =>
  Math.max(1, Math.ceil(Number(simDurasiJam.value) || 1))
)

const simBiayaParkirNormal = computed(() => {
  if (!selectedSimTarif.value) return 0
  return Number(selectedSimTarif.value.tarif_per_jam || 0) * simBilledHours.value
})

const simNominalDenda = computed(() => {
  if (!simKarcisHilang.value) return 0
  return Math.max(0, Number(pengaturanForm.denda_karcis_hilang || 0))
})

const simEstimatedTotal = computed(() => {
  if (!simKarcisHilang.value) return simBiayaParkirNormal.value
  if (pengaturanForm.skema_denda === 'pengganti') {
    return simNominalDenda.value
  }
  return simBiayaParkirNormal.value + simNominalDenda.value
})

watch(tarifList, (list) => {
  if (list.length && !simTarifId.value) {
    simTarifId.value = String(list[0].id_tarif)
  }
})

// Modal Tambah / Edit
const openCreateModal = () => {
  editingTarifId.value = null
  formError.value = ''

  const usedJenis = tarifList.value.map((t) =>
    String(t.jenis_kendaraan).toLowerCase()
  )
  const available = JENIS_OPTIONS.find((opt) => !usedJenis.includes(opt.value))
  form.jenis_kendaraan = available ? available.value : 'motor'
  form.tarif_per_jam = ''
  isModalOpen.value = true
}

const openEditModal = (item) => {
  editingTarifId.value = item.id_tarif
  formError.value = ''
  form.jenis_kendaraan = String(item.jenis_kendaraan || 'motor').toLowerCase()
  form.tarif_per_jam = Number(item.tarif_per_jam || 0)
  isModalOpen.value = true
}

const closeModal = () => {
  if (isSubmitting.value) return
  isModalOpen.value = false
  formError.value = ''
}

const submitTarifForm = async () => {
  formError.value = ''
  const nominal = Number(form.tarif_per_jam)

  if (!form.jenis_kendaraan) {
    formError.value = 'Jenis kendaraan wajib dipilih.'
    return
  }

  if (Number.isNaN(nominal) || nominal <= 0) {
    formError.value = 'Tarif per jam harus berupa angka lebih dari 0.'
    return
  }

  isSubmitting.value = true

  try {
    const payload = {
      jenis_kendaraan: form.jenis_kendaraan,
      tarif_per_jam: Math.round(nominal),
    }

    if (editingTarifId.value) {
      await api.put(`/admin/tarif/${editingTarifId.value}`, payload)
      showToast('Skema tarif berhasil diperbarui.', 'success')
    } else {
      await api.post('/admin/tarif', payload)
      showToast('Tarif baru berhasil ditambahkan.', 'success')
    }

    isModalOpen.value = false
    await fetchTarif()
  } catch (err) {
    const resData = err?.response?.data
    if (resData?.errors) {
      const firstErr = Object.values(resData.errors).flat()[0]
      formError.value = firstErr || resData?.message || 'Validasi gagal.'
    } else {
      formError.value = resData?.message || 'Gagal menyimpan data tarif.'
    }
  } finally {
    isSubmitting.value = false
  }
}

const confirmDeleteTarif = (item) => {
  tarifToDelete.value = item
}

const handleDeleteTarif = async () => {
  if (!tarifToDelete.value) return
  const targetId = tarifToDelete.value.id_tarif
  deletingId.value = targetId

  try {
    await api.delete(`/admin/tarif/${targetId}`)
    showToast(
      `Tarif "${getJenisMeta(tarifToDelete.value.jenis_kendaraan).label}" berhasil dihapus.`,
      'success'
    )
    tarifToDelete.value = null
    await fetchTarif()
  } catch (err) {
    showToast(
      err?.response?.data?.message ||
        'Gagal menghapus tarif. Pastikan tidak sedang dipakai transaksi.',
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
        <AlertCircle
          v-if="toast.type === 'error'"
          :size="15"
          class="shrink-0 text-rose-600"
        />
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
          KONFIGURASI TARIF
        </p>
        <h1 class="mt-1 text-xl font-extrabold tracking-tight text-[#0F172A] sm:text-2xl">
          Kelola Skema Tarif Parkir
        </h1>
        <p class="mt-1 max-w-2xl text-[11.5px] text-[#64748B]">
          Atur tarif parkir per jam untuk setiap jenis kendaraan (<span class="font-semibold text-[#334155]">Motor</span>, <span class="font-semibold text-[#334155]">Mobil</span>, dan <span class="font-semibold text-[#334155]">Lainnya</span>) serta kebijakan denda karcis hilang.
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
        <span>Tambah Tarif Baru</span>
      </motion.button>
    </motion.div>

    <!-- Baris KPI: Metrik Finansial Operasional & Status Konfigurasi -->
    <div class="grid gap-5 lg:grid-cols-12">
      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.34 }"
        class="flex flex-col justify-between rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs lg:col-span-7 will-change-transform"
      >
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
              METRIK FINANSIAL OPERASIONAL
            </p>
            <div class="mt-3 flex flex-wrap items-baseline gap-2.5">
              <span class="text-2xl font-extrabold tracking-tight text-[#0F172A] sm:text-3xl">
                Rp {{ formatAngka(averageTarif) }}
              </span>
              <span class="text-xs font-medium text-[#64748B]">/ jam rata-rata</span>
              <span
                v-if="tarifList.length > 0"
                class="inline-flex items-center gap-1 rounded-full bg-[#EAF6C9] px-2.5 py-0.5 text-[10px] font-bold text-[#50631D]"
              >
                <TrendingUp :size="11" />
                {{ tarifList.length }} Skema Aktif
              </span>
            </div>
          </div>

          <div
            v-if="barChartItems.length > 0"
            class="flex h-14 items-end gap-2 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5"
            title="Perbandingan tarif antar kategori"
          >
            <div
              v-for="(bar, i) in barChartItems"
              :key="bar.id"
              class="flex h-full flex-col items-center justify-end"
              :title="`${bar.label}: ${formatRupiah(bar.value)}/jam`"
            >
              <span
                :style="{ height: `${bar.heightPercent}%` }"
                :class="[
                  'w-2.5 rounded-full transition-all duration-300',
                  i === barChartItems.length - 1 ? 'bg-olive' : 'bg-[#C7E182]',
                ]"
              />
            </div>
          </div>
        </div>

        <p class="mt-5 border-t border-slate-100 pt-3.5 text-[11px] text-[#64748B]">
          <template v-if="lowestTarif && highestTarif">
            Rentang tarif aktif mulai dari
            <span class="font-bold text-[#0F172A]">{{ formatRupiah(lowestTarif.tarif_per_jam) }}/jam</span>
            ({{ getJenisMeta(lowestTarif.jenis_kendaraan).label }}) hingga
            <span class="font-bold text-[#0F172A]">{{ formatRupiah(highestTarif.tarif_per_jam) }}/jam</span>
            ({{ getJenisMeta(highestTarif.jenis_kendaraan).label }}).
          </template>
          <template v-else>
            Belum ada skema tarif tersimpan di database. Klik tombol <strong>Tambah Tarif Baru</strong> untuk memulai.
          </template>
        </p>
      </motion.article>

      <motion.article
        :initial="{ opacity: 0, y: 12 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ duration: 0.34, delay: 0.06 }"
        class="flex flex-col justify-between rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs lg:col-span-5 will-change-transform"
      >
        <div>
          <p class="text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            STATUS KONFIGURASI
          </p>

          <div class="mt-3.5 grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-3.5">
              <div class="flex items-center gap-1.5 text-[10.5px] font-semibold text-olive">
                <Layers :size="13" />
                <span>Kategori Terdaftar</span>
              </div>
              <p class="mt-1.5 text-xl font-extrabold text-[#0F172A]">
                {{ tarifList.length }}
                <span class="text-[11px] font-medium text-[#64748B]">Jenis</span>
              </p>
            </div>

            <div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-3.5">
              <div class="flex items-center gap-1.5 text-[10.5px] font-semibold text-[#8C4A32]">
                <Clock :size="13" />
                <span>Pembaruan Terakhir</span>
              </div>
              <p class="mt-1.5 text-xs font-extrabold text-[#0F172A]">
                {{ lastUpdatedText }}
              </p>
              <p class="mt-0.5 text-[10px] text-[#64748B]">Tersinkron database</p>
            </div>
          </div>
        </div>

        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3.5 text-[11px]">
          <span class="inline-flex items-center gap-1.5 font-medium text-[#475569]">
            <span class="h-2 w-2 rounded-full bg-olive" />
            Perubahan tercatat otomatis di Log Aktivitas
          </span>
          <RouterLink
            to="/admin/log-aktivitas"
            class="font-bold text-olive transition-colors hover:text-olive-dark"
          >
            Lihat Log →
          </RouterLink>
        </div>
      </motion.article>
    </div>

    <!-- Matriks Tarif Aktif (CRUD Table) -->
    <motion.section
      :initial="{ opacity: 0, y: 14 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.38, delay: 0.1 }"
      class="mt-6 rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
    >
      <div class="flex flex-col justify-between gap-3 border-b border-[#E2E8F0] pb-4 sm:flex-row sm:items-center">
        <div>
          <h2 class="text-sm font-bold text-[#0F172A]">Daftar Skema Tarif Per Jam</h2>
          <p class="mt-0.5 text-[11px] text-[#64748B]">
            Tarif dasar per jam yang diterapkan otomatis saat kendaraan keluar pos parkir.
          </p>
        </div>

        <div class="relative w-full sm:w-64">
          <Search
            :size="14"
            class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]"
          />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari jenis kendaraan..."
            class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] py-2 pl-9 pr-3.5 text-xs text-[#0F172A] placeholder-[#94A3B8] transition-colors focus:border-olive focus:bg-white focus:outline-none"
          />
        </div>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[560px] border-collapse text-left">
          <thead>
            <tr class="border-b border-slate-100 text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
              <th class="py-3 pr-4">JENIS KENDARAAN</th>
              <th class="py-3 pr-4">TARIF / JAM</th>
              <th class="py-3 pr-4">KODE ENUM</th>
              <th class="py-3 text-right">AKSI</th>
            </tr>
          </thead>

          <tbody v-if="filteredTarif.length > 0" class="divide-y divide-slate-100 text-xs">
            <tr
              v-for="item in filteredTarif"
              :key="item.id_tarif"
              class="transition-colors hover:bg-[#F8FAFC]/80"
            >
              <td class="py-4 pr-4">
                <div class="flex items-center gap-3.5">
                  <span
                    :class="[
                      'flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl',
                      getJenisMeta(item.jenis_kendaraan).badgeBg,
                    ]"
                  >
                    <component :is="getJenisMeta(item.jenis_kendaraan).icon" :size="17" />
                  </span>
                  <div>
                    <p class="font-bold text-[#0F172A]">
                      {{ getJenisMeta(item.jenis_kendaraan).label }}
                    </p>
                    <p class="text-[10.5px] text-[#64748B]">
                      {{ getJenisMeta(item.jenis_kendaraan).subtitle }}
                    </p>
                  </div>
                </div>
              </td>

              <td class="py-4 pr-4">
                <span class="text-sm font-extrabold text-[#0F172A]">
                  {{ formatRupiah(item.tarif_per_jam) }}
                </span>
                <span class="ml-1 text-[10.5px] text-[#64748B]">/ jam</span>
              </td>

              <td class="py-4 pr-4">
                <span class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-2.5 py-1 font-mono text-[11px] text-[#475569]">
                  {{ item.jenis_kendaraan }}
                </span>
              </td>

              <td class="py-4 text-right">
                <div class="inline-flex items-center gap-1.5">
                  <button
                    type="button"
                    @click="openEditModal(item)"
                    title="Edit tarif"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-xl border border-[#E2E8F0] bg-white text-[#475569] transition-colors hover:border-olive hover:bg-[#F4F8EA] hover:text-olive"
                  >
                    <Pencil :size="13" />
                  </button>

                  <button
                    type="button"
                    @click="confirmDeleteTarif(item)"
                    title="Hapus tarif"
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
              <td colspan="4" class="py-10 text-center text-xs text-[#64748B]">
                {{
                  isLoading
                    ? 'Memuat skema tarif dari server...'
                    : 'Belum ada data tarif yang sesuai.'
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </motion.section>

    <!-- Kebijakan Denda Karcis Hilang (tb_pengaturan_parkir) -->
    <motion.section
      :initial="{ opacity: 0, y: 14 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.38, delay: 0.13 }"
      class="mt-6 rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
    >
      <div class="flex flex-col justify-between gap-2 border-b border-[#E2E8F0] pb-4 sm:flex-row sm:items-center">
        <div class="flex items-start gap-3">
          <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[#F3E8E2] text-[#8C4A32]">
            <TicketX :size="17" />
          </span>
          <div>
            <h2 class="text-sm font-bold text-[#0F172A]">Kebijakan Denda Karcis Hilang</h2>
            <p class="mt-0.5 text-[11px] text-[#64748B]">
              Atur nominal denda dan skema penagihan saat pengunjung kehilangan karcis parkir saat keluar.
            </p>
          </div>
        </div>

        <span class="self-start rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1 text-[10px] font-bold text-[#475569] sm:self-center">
          {{ isLoadingPengaturan ? 'Memuat...' : 'tb_pengaturan_parkir' }}
        </span>
      </div>

      <form @submit.prevent="handleSavePengaturan" class="mt-5">
        <p
          v-if="pengaturanError"
          class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-medium text-rose-700"
        >
          {{ pengaturanError }}
        </p>

        <div class="grid gap-4 md:grid-cols-12 md:items-end">
          <div class="md:col-span-5">
            <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
              NOMINAL DENDA KARCIS HILANG (RUPIAH)
            </label>
            <div class="relative">
              <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-[#64748B]">
                Rp
              </span>
              <input
                v-model.number="pengaturanForm.denda_karcis_hilang"
                type="number"
                min="0"
                step="500"
                :disabled="isLoadingPengaturan || isSavingPengaturan"
                class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] py-2.5 pl-10 pr-4 text-xs font-bold text-[#0F172A] focus:border-olive focus:bg-white focus:outline-none disabled:opacity-60"
              />
            </div>
          </div>

          <div class="md:col-span-4">
            <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
              SKEMA PERHITUNGAN DENDA
            </label>
            <select
              v-model="pengaturanForm.skema_denda"
              :disabled="isLoadingPengaturan || isSavingPengaturan"
              class="w-full cursor-pointer rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs font-semibold text-[#0F172A] focus:border-olive focus:bg-white focus:outline-none disabled:opacity-60"
            >
              <option value="tambahan">Tambahan (Biaya Parkir + Denda)</option>
              <option value="pengganti">Pengganti (Hanya Bayar Denda)</option>
            </select>
          </div>

          <div class="flex justify-end md:col-span-3">
            <button
              type="submit"
              :disabled="isLoadingPengaturan || isSavingPengaturan"
              class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-full bg-olive px-5 py-2.5 text-xs font-bold text-white transition-colors hover:bg-olive-dark disabled:cursor-not-allowed disabled:opacity-60 md:w-auto"
            >
              <Save :size="14" />
              <span>{{ isSavingPengaturan ? 'Menyimpan...' : 'Simpan Denda' }}</span>
            </button>
          </div>
        </div>

        <p class="mt-3 text-[10.5px] text-[#64748B]">
          {{
            pengaturanForm.skema_denda === 'pengganti'
              ? `Skema Pengganti: Jika karcis hilang, pengunjung hanya membayar denda tetap sebesar ${formatRupiah(pengaturanForm.denda_karcis_hilang)}.`
              : `Skema Tambahan: Jika karcis hilang, pengunjung membayar biaya parkir sesuai durasi ditambah denda ${formatRupiah(pengaturanForm.denda_karcis_hilang)}.`
          }}
        </p>
      </form>
    </motion.section>

    <!-- Simulator Hitung Tarif Real-time -->
    <motion.section
      :initial="{ opacity: 0, y: 14 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.38, delay: 0.16 }"
      class="mt-6 rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs will-change-transform"
    >
      <div class="grid gap-6 lg:grid-cols-12 lg:items-center">
        <!-- Kontrol Simulator -->
        <div class="lg:col-span-7">
          <div class="flex items-center justify-between gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[#F3E8E2] px-3 py-1 text-[9.5px] font-bold tracking-wider text-[#8C4A32]">
              <Calculator :size="12" />
              SIMULATOR HITUNG TARIF
            </span>
            <span class="text-[10.5px] font-medium text-[#64748B]">Simulasi Billing</span>
          </div>

          <h3 class="mt-3 text-base font-extrabold text-[#0F172A]">
            Uji Estimasi Biaya Parkir
          </h3>
          <p class="mt-0.5 text-[11px] text-[#64748B]">
            Simulasikan total tagihan pengunjung berdasarkan tarif per jam aktif, durasi parkir, dan status karcis hilang.
          </p>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-[9.5px] font-bold tracking-wider text-[#475569]">
                PILIH KATEGORI KENDARAAN
              </label>
              <select
                v-model="simTarifId"
                :disabled="!tarifList.length"
                class="w-full cursor-pointer rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-2.5 text-xs font-semibold text-[#0F172A] focus:border-olive focus:bg-white focus:outline-none disabled:opacity-50"
              >
                <option v-if="!tarifList.length" value="">Belum ada tarif</option>
                <option
                  v-for="item in tarifList"
                  :key="item.id_tarif"
                  :value="String(item.id_tarif)"
                >
                  {{ getJenisMeta(item.jenis_kendaraan).label }} ({{ formatRupiah(item.tarif_per_jam) }}/jam)
                </option>
              </select>
            </div>

            <div>
              <div class="mb-1.5 flex items-center justify-between text-[9.5px] font-bold tracking-wider text-[#475569]">
                <span>DURASI PARKIR</span>
                <span class="text-olive">{{ simDurasiJam }} Jam</span>
              </div>
              <input
                v-model.number="simDurasiJam"
                type="range"
                min="1"
                max="24"
                step="1"
                class="mt-2 h-2 w-full cursor-pointer appearance-none rounded-lg bg-[#E2E8F0] accent-olive"
              />
              <div class="mt-1.5 flex justify-between text-[9.5px] text-[#94A3B8]">
                <span>1 Jam</span>
                <span>6 Jam</span>
                <span>12 Jam</span>
                <span>24 Jam</span>
              </div>
            </div>
          </div>

          <!-- Toggle Kondisi Karcis Hilang -->
          <div class="mt-4 flex items-center justify-between rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
            <div>
              <p class="text-xs font-bold text-[#0F172A]">Kondisi Karcis Hilang</p>
              <p class="text-[10.5px] text-[#64748B]">
                Terapkan denda {{ formatRupiah(pengaturanForm.denda_karcis_hilang) }} (skema {{ pengaturanForm.skema_denda }})
              </p>
            </div>

            <button
              type="button"
              role="switch"
              :aria-checked="simKarcisHilang"
              @click="simKarcisHilang = !simKarcisHilang"
              :class="[
                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                simKarcisHilang ? 'bg-rose-600' : 'bg-slate-300',
              ]"
            >
              <span
                :class="[
                  'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-xs transition duration-200',
                  simKarcisHilang ? 'translate-x-5' : 'translate-x-0',
                ]"
              />
            </button>
          </div>
        </div>

        <!-- Hasil Kalkulasi -->
        <div class="rounded-[20px] border border-[#E2E8F0] bg-[#F8FAFC] p-5 lg:col-span-5">
          <div class="flex items-center justify-between text-[9.5px] font-bold tracking-wider text-[#94A3B8]">
            <span>TOTAL ESTIMASI TAGIHAN</span>
            <span
              :class="[
                'rounded-full px-2.5 py-0.5 font-bold',
                simKarcisHilang
                  ? 'bg-rose-50 text-rose-700'
                  : 'bg-white text-[#475569]',
              ]"
            >
              {{ simKarcisHilang ? `DENDA (${pengaturanForm.skema_denda.toUpperCase()})` : 'NORMAL' }}
            </span>
          </div>

          <p class="mt-3 text-3xl font-extrabold tracking-tight text-[#0F172A]">
            {{ formatRupiah(simEstimatedTotal) }}
          </p>

          <div class="mt-3 space-y-1 border-t border-[#E2E8F0] pt-3 text-[11px] text-[#64748B]">
            <p v-if="selectedSimTarif">
              • Biaya parkir ({{ simBilledHours }} jam × {{ formatRupiah(selectedSimTarif.tarif_per_jam) }}):
              <span class="font-semibold text-[#0F172A]">
                {{ formatRupiah(simBiayaParkirNormal) }}
              </span>
            </p>
            <p v-if="simKarcisHilang">
              • Denda karcis hilang ({{ pengaturanForm.skema_denda }}):
              <span class="font-bold text-rose-600">
                {{ formatRupiah(simNominalDenda) }}
              </span>
            </p>
            <p v-else>
              • Status karcis: <span class="font-semibold text-[#0F172A]">Normal (tanpa denda)</span>
            </p>
          </div>
        </div>
      </div>
    </motion.section>

    <!-- MODAL TAMBAH / EDIT TARIF -->
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
                {{ editingTarifId ? 'PERBARUI SKEMA' : 'SKEMA BARU' }}
              </p>
              <h3 class="mt-0.5 text-base font-extrabold text-[#0F172A]">
                {{ editingTarifId ? 'Edit Tarif Parkir' : 'Tambah Tarif Baru' }}
              </h3>
              <p class="mt-0.5 text-[11px] text-[#64748B]">
                Tentukan jenis kendaraan dan nominal tarif parkir per jam.
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

          <form @submit.prevent="submitTarifForm" class="mt-5 space-y-4">
            <p
              v-if="formError"
              class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-medium text-rose-700"
            >
              {{ formError }}
            </p>

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
                  {{ opt.label }} ({{ opt.subtitle }})
                </option>
              </select>
            </div>

            <div>
              <label class="mb-1.5 block text-[10px] font-bold tracking-wider text-[#475569]">
                TARIF PER JAM (RUPIAH)
              </label>
              <div class="relative">
                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-[#64748B]">
                  Rp
                </span>
                <input
                  v-model="form.tarif_per_jam"
                  type="number"
                  min="500"
                  step="500"
                  required
                  placeholder="Contoh: 2000"
                  class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] py-2.5 pl-10 pr-4 text-xs font-semibold text-[#0F172A] placeholder-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
                />
              </div>
              <p class="mt-1.5 text-[10.5px] text-[#64748B]">
                Pratinjau format:
                <span class="font-bold text-[#0F172A]">
                  {{ formatRupiah(form.tarif_per_jam || 0) }} / jam
                </span>
              </p>
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
                    : editingTarifId
                      ? 'Simpan Perubahan'
                      : 'Simpan Tarif'
                }}
              </button>
            </div>
          </form>
        </motion.div>
      </div>
    </AnimatePresence>

    <!-- MODAL KONFIRMASI HAPUS TARIF -->
    <AnimatePresence>
      <div
        v-if="tarifToDelete"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <motion.div
          :initial="{ opacity: 0 }"
          :animate="{ opacity: 1 }"
          :exit="{ opacity: 0 }"
          :transition="{ duration: 0.2 }"
          @click="tarifToDelete = null"
          class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs"
        />

        <motion.div
          :initial="{ opacity: 0, scale: 0.94, y: 12 }"
          :animate="{ opacity: 1, scale: 1, y: 0 }"
          :exit="{ opacity: 0, scale: 0.95, y: 10 }"
          :transition="{ duration: 0.2 }"
          class="relative z-10 w-full max-w-md rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-xl will-change-transform"
        >
          <h3 class="text-base font-extrabold text-[#0F172A]">Hapus Skema Tarif?</h3>
          <p class="mt-2 text-xs leading-relaxed text-[#64748B]">
            Tarif untuk kategori
            <span class="font-bold text-[#0F172A]">
              {{ getJenisMeta(tarifToDelete.jenis_kendaraan).label }}
            </span>
            ({{ formatRupiah(tarifToDelete.tarif_per_jam) }}/jam) akan dihapus dari daftar aktif.
          </p>

          <div class="mt-6 flex items-center justify-end gap-2.5">
            <button
              type="button"
              @click="tarifToDelete = null"
              :disabled="deletingId !== null"
              class="cursor-pointer rounded-full border border-[#E2E8F0] bg-white px-4 py-2 text-xs font-semibold text-[#475569] hover:bg-slate-50"
            >
              Batal
            </button>

            <button
              type="button"
              @click="handleDeleteTarif"
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