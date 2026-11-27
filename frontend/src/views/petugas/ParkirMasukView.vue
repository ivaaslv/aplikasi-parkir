<script setup>
import JsBarcode from 'jsbarcode'
import {
  Car,
  Bike,
  Truck,
  Sparkles,
  ScanBarcode,
  Printer,
  RotateCcw,
  CheckCircle2,
  AlertCircle,
  Clock,
  RefreshCw,
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
const activeMenu = ref('parkir-masuk')

const platInputRef = ref(null)
const barcodeSvgRef = ref(null)
const platNomor = ref('')
const selectedJenis = ref('')
const selectedAreaId = ref('')
const warnaKendaraan = ref('')
const namaPengemudi = ref('')

const tarifsRaw = ref([])
const areasRaw = ref([])
const transactionsRaw = ref([])
const dendaConfig = ref(null)

const isSubmitting = ref(false)
const isLoadingData = ref(false)
const feedbackError = ref('')
const feedbackSuccess = ref('')
const lastCreatedTicket = ref(null)

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

const formatJamDetik = (dateInput) => {
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

const normalizeOptionalText = (val) => {
  const clean = String(val ?? '').trim()
  if (!clean || clean === '-') return ''
  return clean
}

const petugasNama = computed(
  () =>
    authUser.value?.nama_lengkap ||
    authUser.value?.nama ||
    authUser.value?.name ||
    authUser.value?.username ||
    'Petugas Pos'
)

const kategoriTarifList = computed(() =>
  tarifsRaw.value.map((t, idx) => {
    const jenis = String(t.jenis_kendaraan || '').toLowerCase()
    let subLabel = 'Kendaraan'
    let icon = Car

    if (jenis.includes('motor') || jenis.includes('roda 2')) {
      subLabel = 'Roda 2'
      icon = Bike
    } else if (jenis.includes('mobil') || jenis.includes('roda 4')) {
      subLabel = 'Roda 4'
      icon = Car
    } else if (jenis.includes('box') || jenis.includes('truk') || jenis.includes('bus')) {
      subLabel = 'Roda 4+'
      icon = Truck
    } else if (jenis.includes('vip') || jenis.includes('valet')) {
      subLabel = 'Khusus'
      icon = Sparkles
    }

    return {
      id: t.id_tarif ?? idx,
      jenis,
      label: jenis.charAt(0).toUpperCase() + jenis.slice(1),
      subLabel,
      tarifPerJam: Number(t.tarif_per_jam ?? 0),
      icon,
    }
  })
)

const activeTarifObj = computed(() =>
  kategoriTarifList.value.find((k) => k.jenis === selectedJenis.value) || null
)

const areaOptions = computed(() =>
  areasRaw.value.map((a, idx) => {
    const total = Math.max(0, Number(a.kapasitas ?? 0))
    const used = Math.max(0, Number(a.terisi ?? 0))
    const sisa = Math.max(0, total - used)
    const jenisArea = String(a.jenis_kendaraan || 'semua').toLowerCase()
    const cocokJenis =
      !selectedJenis.value ||
      jenisArea === 'semua' ||
      jenisArea === selectedJenis.value

    return {
      id: a.id_area ?? a.id ?? idx,
      namaArea: a.nama_area || `Area ${idx + 1}`,
      deskripsi: a.deskripsi || '',
      jenisArea,
      total,
      used,
      sisa,
      cocokJenis,
      penuh: sisa <= 0,
    }
  })
)

const totalSlotKosongSemua = computed(() =>
  areaOptions.value.reduce((sum, a) => sum + a.sisa, 0)
)

const selectedAreaObj = computed(
  () => areaOptions.value.find((a) => String(a.id) === String(selectedAreaId.value)) || null
)

const autoPickBestArea = () => {
  const tersediaCocok = areaOptions.value.find((a) => a.cocokJenis && !a.penuh)
  if (tersediaCocok) {
    selectedAreaId.value = tersediaCocok.id
    return
  }
  const tersediaAny = areaOptions.value.find((a) => !a.penuh)
  if (tersediaAny) {
    selectedAreaId.value = tersediaAny.id
  }
}

watch(selectedJenis, () => {
  if (selectedAreaObj.value && (!selectedAreaObj.value.cocokJenis || selectedAreaObj.value.penuh)) {
    autoPickBestArea()
  }
})

const masukTerakhirList = computed(() =>
  transactionsRaw.value.slice(0, 3).map((trx, idx) => ({
    id: trx.id_parkir ?? trx.id ?? idx,
    plat: String(trx.kendaraan?.plat_nomor || '-').toUpperCase(),
    jenis: String(trx.kendaraan?.jenis_kendaraan || 'kendaraan').toUpperCase(),
    area: trx.area_parkir?.nama_area || trx.areaParkir?.nama_area || 'Area Parkir',
    jam: formatJamDetik(trx.waktu_masuk),
    noTiket: `Tiket #${String(trx.id_parkir ?? idx + 1).padStart(3, '0')}`,
  }))
)

const previewTicket = computed(() => {
  if (lastCreatedTicket.value) return lastCreatedTicket.value
  return {
    noTransaksi: 'PRK-000000',
    plat: platNomor.value.trim().toUpperCase() || 'B 1234 XYZ',
    jenis: (selectedJenis.value || 'mobil').toUpperCase(),
    area: selectedAreaObj.value?.namaArea || 'Area Parkir',
    waktuMasuk: new Date().toISOString(),
    tarifPerJam: activeTarifObj.value?.tarifPerJam ?? 0,
    warna: normalizeOptionalText(warnaKendaraan.value),
    pemilik: normalizeOptionalText(namaPengemudi.value),
    petugas: petugasNama.value,
    dendaHilang: Number(dendaConfig.value?.denda_karcis_hilang ?? 25000),
  }
})

const renderBarcode = async () => {
  await nextTick()
  const svgEl = barcodeSvgRef.value
  if (!svgEl) return

  const valueToEncode = String(previewTicket.value?.noTransaksi || 'PRK-000000').trim()
  try {
    JsBarcode(svgEl, valueToEncode, {
      format: 'CODE128',
      lineColor: '#0F172A',
      background: '#FFFFFF',
      width: 1.25,
      height: 22,
      displayValue: false,
      margin: 1,
    })
  } catch {
    // Abaikan jika nilai kosong
  }
}

watch(
  () => previewTicket.value.noTransaksi,
  () => {
    renderBarcode()
  }
)

const fetchInitialData = async () => {
  isLoadingData.value = true
  try {
    const [tarifRes, areaRes, trxRes, dendaRes] = await Promise.allSettled([
      api.get('/operasional/tarif'),
      api.get('/operasional/area-parkir'),
      api.get('/operasional/transaksi'),
      api.get('/operasional/pengaturan-parkir'),
    ])

    if (tarifRes.status === 'fulfilled') {
      tarifsRaw.value = extractList(tarifRes.value.data)
      if (!selectedJenis.value && tarifsRaw.value.length > 0) {
        selectedJenis.value = String(tarifsRaw.value[0].jenis_kendaraan || '').toLowerCase()
      }
    }

    if (areaRes.status === 'fulfilled') {
      areasRaw.value = extractList(areaRes.value.data)
      autoPickBestArea()
    }

    if (trxRes.status === 'fulfilled') {
      transactionsRaw.value = extractList(trxRes.value.data)
    }

    if (dendaRes.status === 'fulfilled') {
      dendaConfig.value = dendaRes.value.data?.data ?? dendaRes.value.data
    }
  } finally {
    isLoadingData.value = false
  }
}

const focusPlatInput = () => {
  platInputRef.value?.focus()
  platInputRef.value?.select?.()
}

const resetForm = async (clearTicket = false) => {
  platNomor.value = ''
  warnaKendaraan.value = ''
  namaPengemudi.value = ''
  feedbackError.value = ''
  if (clearTicket) {
    feedbackSuccess.value = ''
    lastCreatedTicket.value = null
  }
  autoPickBestArea()
  await renderBarcode()
  focusPlatInput()
}

const triggerPrintNow = async () => {
  await renderBarcode()
  window.print()
}

const handleSubmitMasuk = async () => {
  feedbackError.value = ''
  feedbackSuccess.value = ''

  const platClean = platNomor.value.trim().toUpperCase()
  if (!platClean) {
    feedbackError.value = 'Plat nomor kendaraan wajib diisi.'
    focusPlatInput()
    return
  }

  if (!selectedJenis.value) {
    feedbackError.value = 'Pilih kategori & tarif parkir terlebih dahulu.'
    return
  }

  if (!selectedAreaId.value) {
    feedbackError.value = 'Pilih area parkir tujuan.'
    return
  }

  const cleanWarna = normalizeOptionalText(warnaKendaraan.value)
  const cleanPemilik = normalizeOptionalText(namaPengemudi.value)

  isSubmitting.value = true
  try {
    const { data } = await api.post('/operasional/transaksi/masuk', {
      plat_nomor: platClean,
      jenis_kendaraan: selectedJenis.value,
      id_area: Number(selectedAreaId.value),
      warna: cleanWarna || null,
      pemilik: cleanPemilik || null,
    })

    const created = data?.data || null
    const noTiket =
      data?.no_transaksi ||
      (created?.id_parkir
        ? `PRK-${String(created.id_parkir).padStart(6, '0')}`
        : 'PRK-000001')

    lastCreatedTicket.value = {
      noTransaksi: noTiket,
      plat: created?.kendaraan?.plat_nomor || platClean,
      jenis: String(created?.kendaraan?.jenis_kendaraan || selectedJenis.value).toUpperCase(),
      area:
        created?.area_parkir?.nama_area ||
        created?.areaParkir?.nama_area ||
        selectedAreaObj.value?.namaArea ||
        '-',
      waktuMasuk: created?.waktu_masuk || new Date().toISOString(),
      tarifPerJam: Number(
        created?.tarif?.tarif_per_jam ?? activeTarifObj.value?.tarifPerJam ?? 0
      ),
      warna: normalizeOptionalText(created?.kendaraan?.warna ?? cleanWarna),
      pemilik: normalizeOptionalText(created?.kendaraan?.pemilik ?? cleanPemilik),
      petugas: created?.user?.nama_lengkap || petugasNama.value,
      dendaHilang: Number(
        data?.denda_karcis_hilang ?? dendaConfig.value?.denda_karcis_hilang ?? 25000
      ),
    }

    feedbackSuccess.value = 'Transaksi masuk tersimpan. Siap dicetak / disimpan PDF.'
    platNomor.value = ''
    warnaKendaraan.value = ''
    namaPengemudi.value = ''

    await fetchInitialData()
    await triggerPrintNow()
    focusPlatInput()
  } catch (err) {
    const apiMsg =
      err?.response?.data?.message ||
      (err?.response?.data?.errors
        ? Object.values(err.response.data.errors).flat().join(' ')
        : null) ||
      'Gagal mencatat transaksi parkir masuk.'
    feedbackError.value = apiMsg
  } finally {
    isSubmitting.value = false
  }
}

const handleKeydown = (event) => {
  if (event.key === 'F2') {
    event.preventDefault()
    focusPlatInput()
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

  await fetchInitialData()
  await renderBarcode()
  focusPlatInput()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <DashboardLayout
    :current-role="currentRole"
    :active-menu="activeMenu"
    :user="authUser"
    @update:active-menu="(val) => (activeMenu = val)"
    @logout="handleLogout"
  >
    <div class="no-print mb-5 flex flex-wrap items-center justify-between gap-3 rounded-[20px] border border-[#E2E8F0] bg-white px-5 py-3 shadow-2xs">
      <div class="flex flex-wrap items-center gap-3 text-xs">

        <span class="text-[11px] font-semibold text-[#475569]">
          Kendaraan Masuk • Label 56×46mm
        </span>
      </div>

      <div class="flex items-center gap-3 text-[11px] text-[#64748B]">
        <span>Petugas: <strong class="text-[#0F172A]">{{ petugasNama }}</strong></span>
        <button
          type="button"
          @click="fetchInitialData"
          :disabled="isLoadingData"
          class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1 text-[10.5px] font-bold text-[#334155] hover:border-olive hover:text-olive"
        >
          <RefreshCw :size="12" :class="isLoadingData ? 'animate-spin' : ''" />
          <span>Sync</span>
        </button>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.45fr_0.95fr]">
      <!-- Kolom Kiri: Form Kendaraan Masuk -->
      <section class="no-print rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-4">
          <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#EAF6C9] text-olive">
              <ScanBarcode :size="15" />
            </span>
            <h1 class="text-base font-extrabold text-[#0F172A]">Kendaraan Masuk</h1>
          </div>
          <button
            type="button"
            @click="focusPlatInput"
            class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1 text-[10px] font-bold text-[#475569] hover:border-olive hover:text-olive"
          >
            <kbd class="font-mono text-olive">F2</kbd>
            <span>Fokus Plat</span>
          </button>
        </div>

        <p
          v-if="feedbackError"
          class="mt-4 flex items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700"
        >
          <AlertCircle :size="15" class="shrink-0" />
          <span>{{ feedbackError }}</span>
        </p>

        <p
          v-if="feedbackSuccess"
          class="mt-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800"
        >
          <CheckCircle2 :size="15" class="shrink-0" />
          <span>{{ feedbackSuccess }}</span>
        </p>

        <form @submit.prevent="handleSubmitMasuk" class="mt-5 space-y-5">
          <div>
            <div class="flex items-center justify-between text-xs">
              <label for="plat_nomor" class="font-bold text-[#0F172A]">
                Nomor Polisi Kendaraan <span class="text-rose-600">*</span>
              </label>
              <span class="text-[10.5px] font-medium text-[#94A3B8]">Wajib Diisi</span>
            </div>
            <input
              id="plat_nomor"
              ref="platInputRef"
              v-model="platNomor"
              type="text"
              placeholder="B 1234 XYZ"
              autocomplete="off"
              class="mt-2 w-full rounded-2xl border-2 border-[#E2E8F0] bg-[#FAFBF9] px-4 py-3.5 text-center font-mono text-xl font-extrabold tracking-widest text-[#0F172A] uppercase placeholder:font-sans placeholder:text-base placeholder:font-semibold placeholder:tracking-normal placeholder:text-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
            />
          </div>

          <div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-[#0F172A]">Kategori &amp; Tarif Parkir</span>
              <span class="text-[10.5px] text-[#94A3B8]">Pilih jenis tarif yang sesuai</span>
            </div>

            <div class="mt-2.5 grid grid-cols-2 gap-3 sm:grid-cols-4 ">
              <button
                v-for="kat in kategoriTarifList"
                :key="kat.id"
                type="button"
                @click="selectedJenis = kat.jenis"
                :class="[
                  'flex cursor-pointer flex-col items-start justify-between rounded-2xl border p-3.5 text-left transition-all',
                  selectedJenis === kat.jenis
                    ? 'border-olive bg-olive text-white shadow-xs'
                    : 'border-[#E2E8F0] bg-[#FAFBF9] text-[#0F172A] hover:border-olive/50 hover:bg-[#F4F8EA]',
                ]"
              >
                <div class="flex w-full items-center justify-between gap-1">
                  <component
                    :is="kat.icon"
                    :size="16"
                    :class="selectedJenis === kat.jenis ? 'text-[#EAF6C9]' : 'text-olive'"
                  />
                  <span
                    :class="[
                      'rounded-full px-2 py-0.5 text-[9px] font-bold',
                      selectedJenis === kat.jenis
                        ? 'bg-white/20 text-white'
                        : 'bg-[#EEF2F6] text-[#475569]',
                    ]"
                  >
                    {{ selectedJenis === kat.jenis ? 'Aktif' : kat.subLabel }}
                  </span>
                </div>

                <div class="mt-3">
                  <p class="text-xs font-extrabold">{{ kat.label }}</p>
                  <p
                    :class="[
                      'mt-0.5 text-[10.5px] font-semibold',
                      selectedJenis === kat.jenis ? 'text-[#EAF6C9]' : 'text-[#64748B]',
                    ]"
                  >
                    {{ formatRupiah(kat.tarifPerJam) }}/jam
                  </p>
                </div>
              </button>
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between text-xs">
              <label for="id_area" class="font-bold text-[#0F172A]">
                Rekomendasi Area Parkir
              </label>
              <span class="text-[10.5px] font-bold text-olive">
                • {{ totalSlotKosongSemua }} Slot Kosong Tersedia
              </span>
            </div>

            <select
              id="id_area"
              v-model="selectedAreaId"
              class="mt-2 w-full cursor-pointer rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-xs font-semibold text-[#0F172A] focus:border-olive focus:bg-white focus:outline-none"
            >
              <option value="" disabled>Pilih area parkir</option>
              <option
                v-for="area in areaOptions"
                :key="area.id"
                :value="area.id"
                :disabled="area.penuh"
              >
                {{ area.namaArea }}
                {{ area.deskripsi ? `• ${area.deskripsi}` : '' }}
                — Sisa {{ area.sisa }} slot{{ area.penuh ? ' (Penuh)' : '' }}
              </option>
            </select>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label for="warna_kendaraan" class="block text-xs font-bold text-[#0F172A]">
                Warna Kendaraan <span class="font-normal text-[#94A3B8]">(Opsional)</span>
              </label>
              <input
                id="warna_kendaraan"
                v-model="warnaKendaraan"
                type="text"
                placeholder="Kosongkan jika tidak perlu"
                class="mt-2 w-full rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-xs text-[#0F172A] placeholder:text-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
              />
            </div>

            <div>
              <label for="nama_pengemudi" class="block text-xs font-bold text-[#0F172A]">
                Nama Pengemudi / Tamu <span class="font-normal text-[#94A3B8]">(Opsional)</span>
              </label>
              <input
                id="nama_pengemudi"
                v-model="namaPengemudi"
                type="text"
                placeholder="Kosongkan jika tidak perlu"
                class="mt-2 w-full rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-xs text-[#0F172A] placeholder:text-[#94A3B8] focus:border-olive focus:bg-white focus:outline-none"
              />
            </div>
          </div>

          <button
            type="submit"
            :disabled="isSubmitting"
            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-full bg-olive px-6 py-3.5 text-xs font-extrabold text-white shadow-xs transition-colors hover:bg-olive-dark disabled:opacity-60"
          >
            <span>
              {{ isSubmitting ? 'Mencatat Transaksi...' : 'Catat Masuk & Cetak Karcis' }}
            </span>
            <kbd class="rounded-md bg-white/15 px-2 py-0.5 font-mono text-[10px] text-white">
              Enter ↵
            </kbd>
          </button>
        </form>
      </section>

      <!-- Kolom Kanan: Preview Label 56x46mm + Masuk Terakhir -->
      <div class="space-y-6">
        <section class="rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs">
          <div class="no-print flex items-center justify-between border-b border-dashed border-[#CBD5E1] pb-3 text-[10.5px]">
            <span class="font-bold tracking-wider text-[#475569]"> PREVIEW KARCIS (56×46mm)</span>
            <span class="font-mono text-[#94A3B8]">Thermal 56×46mm</span>
          </div>

          <div class="mt-4 flex justify-center">
            <div class="print-area w-[212px] rounded-xl border border-dashed border-[#94A3B8] bg-white p-2.5 text-center text-[#0F172A]">
              <div class="flex items-center justify-between border-b border-dashed border-[#CBD5E1] pb-1 text-[8px] font-extrabold">
                <span>PARKFLOW</span>
                <span class="font-mono">{{ previewTicket.noTransaksi }}</span>
              </div>

              <p class="mt-1 font-mono text-xs font-extrabold tracking-wider">
                {{ previewTicket.plat }}
              </p>
              <p class="text-[8px] font-semibold text-[#475569]">
                {{ previewTicket.jenis }} • {{ previewTicket.area }}
              </p>

              <p
                v-if="previewTicket.warna || previewTicket.pemilik"
                class="mt-0.5 truncate text-[7.5px] text-[#475569]"
              >
                {{ [previewTicket.warna, previewTicket.pemilik].filter(Boolean).join(' • ') }}
              </p>

              <div class="mt-1 flex items-center justify-between text-[7.5px] text-[#334155]">
                <span>{{ formatTanggalJamSingkat(previewTicket.waktuMasuk) }}</span>
                <span class="font-bold">{{ formatRupiah(previewTicket.tarifPerJam) }}/j</span>
              </div>

              <div class="mt-1 flex flex-col items-center justify-center">
                <svg ref="barcodeSvgRef" class="max-w-full" />
              </div>

              <p class="mt-0.5 text-[7px] leading-tight text-[#475569]">
                Denda hilang: {{ formatRupiah(previewTicket.dendaHilang) }}
              </p>
            </div>
          </div>

          <div class="no-print mt-4 grid grid-cols-2 gap-3">
            <button
              type="button"
              @click="triggerPrintNow"
              :disabled="!lastCreatedTicket"
              class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-full border border-[#E2E8F0] bg-[#FAFBF9] px-4 py-2.5 text-xs font-bold text-[#0F172A] transition-colors hover:border-olive hover:bg-[#F4F8EA] disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Printer :size="14" />
              <span>Cetak / PDF</span>
            </button>

            <button
              type="button"
              @click="resetForm(true)"
              class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-full border border-[#E2E8F0] bg-[#FAFBF9] px-4 py-2.5 text-xs font-bold text-[#334155] transition-colors hover:border-slate-400 hover:bg-slate-100"
            >
              <RotateCcw :size="14" />
              <span>Reset Form</span>
            </button>
          </div>
        </section>

        <section class="no-print rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs">
          <div class="flex items-center justify-between text-xs">
            <h2 class="font-extrabold text-[#0F172A]">Masuk Terakhir</h2>
            <span class="text-[10.5px] text-[#94A3B8]">Gate Activity</span>
          </div>

          <div v-if="masukTerakhirList.length > 0" class="mt-3.5 space-y-2.5">
            <div
              v-for="item in masukTerakhirList"
              :key="item.id"
              class="flex items-center justify-between gap-2 rounded-2xl border border-[#F1F5F9] bg-[#FAFBF9] px-3.5 py-2.5 text-xs"
            >
              <div>
                <p class="font-mono text-[11.5px] font-extrabold text-[#0F172A]">
                  {{ item.plat }}
                </p>
                <p class="text-[10px] text-[#64748B]">
                  {{ item.jenis }} • {{ item.area }}
                </p>
              </div>
              <div class="text-right">
                <p class="inline-flex items-center gap-1 font-mono text-[10.5px] font-bold text-[#0F172A]">
                  <Clock :size="11" class="text-olive" />
                  <span>{{ item.jam }}</span>
                </p>
                <p class="text-[9.5px] text-[#94A3B8]">{{ item.noTiket }}</p>
              </div>
            </div>
          </div>

          <p v-else class="mt-3 text-center text-xs text-[#64748B]">
            Belum ada transaksi masuk tercatat.
          </p>
        </section>
      </div>
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
    overflow: hidden;
  }

  .no-print {
    display: none !important;
  }
}
</style>