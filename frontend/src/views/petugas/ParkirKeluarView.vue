<script setup>
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode'
import JsBarcode from 'jsbarcode'
import {
  QrCode,
  Camera,
  CameraOff,
  CheckCircle2,
  AlertCircle,
  Printer,
  RotateCcw,
  Search,
  RefreshCw,
  Receipt,
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
const activeMenu = ref('parkir-keluar')

const scanInputRef = ref(null)
const strukBarcodeRef = ref(null)

const scanValue = ref('')
const karcisHilang = ref(false)
const searchAktif = ref('')
const selectedAreaFilter = ref('semua')

const transactionsRaw = ref([])
const areasRaw = ref([])
const dendaConfig = ref(null)

const isSubmitting = ref(false)
const isLoadingData = ref(false)
const feedbackError = ref('')
const feedbackSuccess = ref('')
const lastKeluarReceipt = ref(null)

// State Kamera Scanner
const isCameraOpen = ref(false)
const isCameraStarting = ref(false)
const cameraError = ref('')
let html5QrInstance = null
let isHandlingCameraHit = false

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

const formatJamWib = (dateInput) => {
  if (!dateInput) return '-'
  const d = new Date(dateInput)
  if (Number.isNaN(d.getTime())) return String(dateInput)
  return (
    new Intl.DateTimeFormat('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
    }).format(d) + ' WIB'
  )
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

const hitungDurasiText = (waktuMasuk, waktuKeluar = null) => {
  if (!waktuMasuk) return '-'
  const start = new Date(waktuMasuk)
  const end = waktuKeluar ? new Date(waktuKeluar) : new Date()
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return '-'
  const diffMenit = Math.max(1, Math.floor((end.getTime() - start.getTime()) / 60000))
  const jam = Math.floor(diffMenit / 60)
  const menit = diffMenit % 60
  return `${jam}j ${menit}m`
}

const formatNoTiket = (idParkir) =>
  `PRK-${String(idParkir ?? 0).padStart(6, '0')}`

const playBeepSound = () => {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext
    if (!AudioCtx) return

    const ctx = new AudioCtx()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()

    osc.type = 'sine'
    osc.frequency.setValueAtTime(1050, ctx.currentTime)

    gain.gain.setValueAtTime(0.18, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12)

    osc.connect(gain)
    gain.connect(ctx.destination)

    osc.start()
    osc.stop(ctx.currentTime + 0.12)

    osc.onended = () => {
      ctx.close().catch(() => {})
    }
  } catch {
    // Abaikan jika browser memblokir audio
  }
}

const petugasNama = computed(
  () =>
    authUser.value?.nama_lengkap ||
    authUser.value?.nama ||
    authUser.value?.name ||
    authUser.value?.username ||
    'Petugas Pos'
)

const aktifList = computed(() => {
  return transactionsRaw.value
    .filter((t) => String(t.status || '').toLowerCase() === 'masuk')
    .map((trx) => {
      const idParkir = trx.id_parkir ?? trx.id
      const plat = String(trx.kendaraan?.plat_nomor || '-').toUpperCase()
      const jenisRaw = String(trx.kendaraan?.jenis_kendaraan || 'kendaraan')
      const jenisLabel = jenisRaw.charAt(0).toUpperCase() + jenisRaw.slice(1)
      const areaNama =
        trx.area_parkir?.nama_area ||
        trx.areaParkir?.nama_area ||
        'Area Parkir'
      const tarifPerJam = Number(trx.tarif?.tarif_per_jam ?? 0)
      const durasiJamTagih = Math.max(
        1,
        Math.ceil(
          Math.max(
            1,
            Math.floor(
              (Date.now() - new Date(trx.waktu_masuk || Date.now()).getTime()) / 60000
            )
          ) / 60
        )
      )
      const estimasiBiaya = durasiJamTagih * tarifPerJam

      return {
        idParkir,
        noTiket: formatNoTiket(idParkir),
        plat,
        jenisRaw: jenisRaw.toLowerCase(),
        jenisLabel,
        areaNama,
        waktuMasuk: trx.waktu_masuk,
        jamMasukWib: formatJamWib(trx.waktu_masuk),
        durasiText: hitungDurasiText(trx.waktu_masuk),
        durasiJamTagih,
        tarifPerJam,
        estimasiBiaya,
      }
    })
})

const areaFilterChips = computed(() => {
  const names = new Set()
  areasRaw.value.forEach((a) => {
    if (a.nama_area) names.add(a.nama_area)
  })
  aktifList.value.forEach((item) => {
    if (item.areaNama) names.add(item.areaNama)
  })
  return ['semua', ...Array.from(names).slice(0, 4)]
})

const filteredAktifList = computed(() => {
  const q = searchAktif.value.trim().toUpperCase()
  return aktifList.value.filter((item) => {
    const cocokArea =
      selectedAreaFilter.value === 'semua' || item.areaNama === selectedAreaFilter.value
    const cocokCari =
      !q ||
      item.plat.includes(q) ||
      item.noTiket.toUpperCase().includes(q) ||
      item.jenisLabel.toUpperCase().includes(q) ||
      item.areaNama.toUpperCase().includes(q)
    return cocokArea && cocokCari
  })
})

const rekomendasiCepatList = computed(() => aktifList.value.slice(0, 4))

const matchedPreviewAktif = computed(() => {
  const clean = scanValue.value.trim().replace(/^\*+|\*+$/g, '').toUpperCase()
  if (!clean) return aktifList.value[0] || null
  return (
    aktifList.value.find(
      (item) =>
        item.noTiket.toUpperCase() === clean ||
        item.plat.toUpperCase() === clean ||
        String(item.idParkir) === clean
    ) || null
  )
})

const posKasirSummary = computed(() => {
  if (lastKeluarReceipt.value) {
    return {
      badge: 'TERVERIFIKASI KELUAR',
      title: `${lastKeluarReceipt.value.jenis} • ${lastKeluarReceipt.value.plat}`,
      durasiLabel: `${lastKeluarReceipt.value.durasiJam} jam`,
      durasiSub: `Masuk ${formatTanggalJamSingkat(lastKeluarReceipt.value.waktuMasuk)}`,
      tarifLabel: formatRupiah(lastKeluarReceipt.value.tarifPerJam),
      tarifSub: lastKeluarReceipt.value.karcisHilang
        ? `Termasuk denda ${formatRupiah(lastKeluarReceipt.value.biayaDenda)}`
        : 'Tarif per jam berlaku',
      total: lastKeluarReceipt.value.biayaTotal,
    }
  }

  if (matchedPreviewAktif.value) {
    const item = matchedPreviewAktif.value
    const denda = Number(dendaConfig.value?.denda_karcis_hilang ?? 25000)
    const skema = dendaConfig.value?.skema_denda || 'tambahan'
    const totalEstimasi = karcisHilang.value
      ? skema === 'pengganti'
        ? denda
        : item.estimasiBiaya + denda
      : item.estimasiBiaya

    return {
      badge: 'MENUNGGU PROSES KELUAR',
      title: `${item.jenisLabel} • ${item.plat}`,
      durasiLabel: item.durasiText,
      durasiSub: `Dihitung ${item.durasiJamTagih} Jam Penuh`,
      tarifLabel: formatRupiah(item.tarifPerJam),
      tarifSub: `Area ${item.areaNama}`,
      total: totalEstimasi,
    }
  }

  return {
    badge: 'SIAP PINDAI',
    title: 'Belum ada tiket dipilih',
    durasiLabel: '0j 0m',
    durasiSub: 'Menunggu scan / plat',
    tarifLabel: 'Rp 0',
    tarifSub: '-',
    total: 0,
  }
})

const strukDisplay = computed(() => {
  if (lastKeluarReceipt.value) return lastKeluarReceipt.value
  const item = matchedPreviewAktif.value
  return {
    noTransaksi: item?.noTiket || 'PRK-000000',
    plat: item?.plat || 'B 1234 XYZ',
    jenis: item?.jenisLabel?.toUpperCase() || 'KENDARAAN',
    area: item?.areaNama || '-',
    waktuMasuk: item?.waktuMasuk || new Date().toISOString(),
    waktuKeluar: new Date().toISOString(),
    durasiJam: item?.durasiJamTagih ?? 0,
    tarifPerJam: item?.tarifPerJam ?? 0,
    biayaDenda: karcisHilang.value
      ? Number(dendaConfig.value?.denda_karcis_hilang ?? 25000)
      : 0,
    biayaTotal: posKasirSummary.value.total,
    karcisHilang: karcisHilang.value,
    petugas: petugasNama.value,
  }
})

const renderStrukBarcode = async () => {
  await nextTick()
  const svgEl = strukBarcodeRef.value
  if (!svgEl) return
  const kode = String(strukDisplay.value?.noTransaksi || 'PRK-000000').trim()
  try {
    JsBarcode(svgEl, kode, {
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

watch(
  () => strukDisplay.value.noTransaksi,
  () => {
    renderStrukBarcode()
  }
)

const fetchData = async () => {
  isLoadingData.value = true
  try {
    const [trxRes, areaRes, dendaRes] = await Promise.allSettled([
      api.get('/operasional/transaksi'),
      api.get('/operasional/area-parkir'),
      api.get('/operasional/pengaturan-parkir'),
    ])

    if (trxRes.status === 'fulfilled') {
      transactionsRaw.value = extractList(trxRes.value.data)
    }

    if (areaRes.status === 'fulfilled') {
      areasRaw.value = extractList(areaRes.value.data)
    }

    if (dendaRes.status === 'fulfilled') {
      dendaConfig.value = dendaRes.value.data?.data ?? dendaRes.value.data
    }
  } finally {
    isLoadingData.value = false
  }
}

const focusScanner = () => {
  scanInputRef.value?.focus()
  scanInputRef.value?.select?.()
}

const stopCameraScanner = async () => {
  if (html5QrInstance) {
    try {
      if (html5QrInstance.isScanning) {
        await html5QrInstance.stop()
      }
      await html5QrInstance.clear()
    } catch {
      // Abaikan error saat menutup kamera
    } finally {
      html5QrInstance = null
    }
  }
  isCameraOpen.value = false
  isCameraStarting.value = false
  isHandlingCameraHit = false
}

const startCameraScanner = async () => {
  cameraError.value = ''
  feedbackError.value = ''
  isHandlingCameraHit = false

  if (isCameraOpen.value) {
    await stopCameraScanner()
    return
  }

  isCameraOpen.value = true
  isCameraStarting.value = true
  await nextTick()

  try {
    html5QrInstance = new Html5Qrcode('parkflow-camera-reader', {
      formatsToSupport: [
        Html5QrcodeSupportedFormats.CODE_128,
        Html5QrcodeSupportedFormats.QR_CODE,
        Html5QrcodeSupportedFormats.CODE_39,
      ],
      verbose: false,
    })

    await html5QrInstance.start(
      { facingMode: 'environment' },
      {
        fps: 12,
        qrbox: { width: 250, height: 110 },
        aspectRatio: 1.777778,
      },
      async (decodedText) => {
        if (isHandlingCameraHit || isSubmitting.value) return
        const cleanDecoded = String(decodedText || '').trim()
        if (!cleanDecoded) return

        isHandlingCameraHit = true
        playBeepSound()
        scanValue.value = cleanDecoded
        await stopCameraScanner()
        await prosesKeluar(null, true)
      },
      () => {
        // Frame tanpa barcode, abaikan
      }
    )
  } catch (err) {
    await stopCameraScanner()
    cameraError.value =
      'Kamera tidak dapat diakses. Pastikan izin kamera diizinkan di browser dan halaman dibuka lewat localhost atau HTTPS.'
    console.error('Camera scanner error:', err)
  } finally {
    isCameraStarting.value = false
  }
}

const pilihRekomendasi = async (item) => {
  scanValue.value = item.noTiket
  feedbackError.value = ''
  await renderStrukBarcode()
  focusScanner()
}

const prosesKeluar = async (overridePayload = null, autoPrint = true) => {
  feedbackError.value = ''
  feedbackSuccess.value = ''

  let payload = overridePayload
  if (!payload) {
    const raw = scanValue.value.trim()
    if (!raw) {
      feedbackError.value = 'Scan barcode tiket atau masukkan nomor tiket / plat nomor.'
      focusScanner()
      return
    }
    const clean = raw.replace(/^\*+|\*+$/g, '').trim()
    payload = /^PRK-\d+$/i.test(clean)
      ? { kode_tiket: clean, plat_nomor: clean, karcis_hilang: karcisHilang.value }
      : { plat_nomor: clean.toUpperCase(), karcis_hilang: karcisHilang.value }
  }

  isSubmitting.value = true
  try {
    const { data } = await api.post('/operasional/transaksi/keluar', payload)
    const trx = data?.data || {}
    const idParkir = trx.id_parkir ?? payload.id_parkir

    lastKeluarReceipt.value = {
      noTransaksi: data?.no_transaksi || formatNoTiket(idParkir),
      plat: String(trx.kendaraan?.plat_nomor || payload.plat_nomor || '-').toUpperCase(),
      jenis: String(trx.kendaraan?.jenis_kendaraan || 'kendaraan').toUpperCase(),
      area: trx.area_parkir?.nama_area || trx.areaParkir?.nama_area || '-',
      waktuMasuk: trx.waktu_masuk,
      waktuKeluar: trx.waktu_keluar || new Date().toISOString(),
      durasiJam: Number(trx.durasi_jam ?? 1),
      tarifPerJam: Number(trx.tarif?.tarif_per_jam ?? 0),
      biayaDenda: Number(trx.biaya_denda ?? 0),
      biayaTotal: Number(trx.biaya_total ?? 0),
      karcisHilang: Boolean(trx.karcis_hilang),
      petugas: trx.user?.nama_lengkap || petugasNama.value,
    }

    feedbackSuccess.value =
      data?.message || 'Kendaraan berhasil keluar dan struk pembayaran siap dicetak.'
    scanValue.value = ''
    karcisHilang.value = false

    await fetchData()
    await renderStrukBarcode()

    if (autoPrint) {
      window.print()
    }
    focusScanner()
  } catch (err) {
    const apiMsg =
      err?.response?.data?.message ||
      (err?.response?.data?.errors
        ? Object.values(err.response.data.errors).flat().join(' ')
        : null) ||
      'Gagal memproses kendaraan keluar.'
    feedbackError.value = apiMsg
    focusScanner()
  } finally {
    isSubmitting.value = false
  }
}

const handleCetakStrukManual = async () => {
  await renderStrukBarcode()
  window.print()
}

const handleResetSesi = async () => {
  scanValue.value = ''
  karcisHilang.value = false
  feedbackError.value = ''
  feedbackSuccess.value = ''
  cameraError.value = ''
  lastKeluarReceipt.value = null
  await renderStrukBarcode()
  focusScanner()
}

const handleKeydown = (event) => {
  if (event.key === 'F4') {
    event.preventDefault()
    handleCetakStrukManual()
  }
}

const handleLogout = async () => {
  await stopCameraScanner()
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

  await fetchData()
  await renderStrukBarcode()
  focusScanner()
})

onBeforeUnmount(async () => {
  window.removeEventListener('keydown', handleKeydown)
  await stopCameraScanner()
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
    <!-- Header Bar -->
    <div class="no-print mb-5 flex flex-wrap items-center justify-between gap-3 rounded-[20px] border border-[#E2E8F0] bg-white px-5 py-3 shadow-2xs">
      <div class="flex flex-wrap items-center gap-3 text-xs">
        <span class="text-[11px] font-semibold text-[#475569]">
          Kendaraan Keluar
        </span>
      </div>

      <div class="flex items-center gap-3 text-[11px] text-[#64748B]">
        <span>Petugas: <strong class="text-[#0F172A]">{{ petugasNama }}</strong></span>
        <button
          type="button"
          @click="fetchData"
          :disabled="isLoadingData"
          class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1 text-[10.5px] font-bold text-[#334155] hover:border-olive hover:text-olive"
        >
          <RefreshCw :size="12" :class="isLoadingData ? 'animate-spin' : ''" />
          <span>Sync</span>
        </button>
      </div>
    </div>

    <!-- Bagian Atas: Pemeriksaan Tiket + Pos Kasir & Struk 56x46mm -->
    <div class="grid gap-6 xl:grid-cols-[1.45fr_0.95fr]">
      <!-- Kolom Kiri: Scanner & Input Manual -->
      <section class="no-print rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs">
        <p class="text-[10px] font-extrabold tracking-widest text-olive uppercase">
          OPERASIONAL KELUAR
        </p>
        <h1 class="mt-1 text-xl font-extrabold text-[#0F172A]">
          Pemeriksaan Tiket &amp; Verifikasi Keluar
        </h1>
        <p class="mt-1 text-xs text-[#64748B]">
          Scan barcode karcis via kamera atau ketik nomor tiket / plat lalu tekan Enter untuk keluar otomatis.
        </p>

        <div class="mt-5 rounded-[20px] border border-[#E2E8F0] bg-[#FAFBF9] p-5">
          <div class="flex flex-wrap items-center justify-between gap-2 text-[10.5px]">
            <span class="inline-flex items-center gap-1.5 font-extrabold tracking-wider text-[#334155]">
              <QrCode :size="14" class="text-olive" />
              <span>CAMERA BARCODE | SCANNER HID | MANUAL</span>
            </span>

            <button
              type="button"
              @click="startCameraScanner"
              :disabled="isCameraStarting"
              :class="[
                'inline-flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1 text-[10.5px] font-extrabold transition-colors',
                isCameraOpen
                  ? 'bg-rose-600 text-white hover:bg-rose-700'
                  : 'bg-olive text-white hover:bg-[#3E4E14]',
              ]"
            >
              <CameraOff v-if="isCameraOpen" :size="13" />
              <Camera v-else :size="13" />
              <span>
                {{
                  isCameraStarting
                    ? 'Membuka Kamera...'
                    : isCameraOpen
                      ? 'Tutup Kamera'
                      : 'Scan via Kamera'
                }}
              </span>
            </button>
          </div>

          <!-- Area Preview Kamera Saat Aktif -->
          <div
            v-if="isCameraOpen"
            class="mt-4 overflow-hidden rounded-2xl border-2 border-olive/40 bg-black p-2"
          >
            <div id="parkflow-camera-reader" class="w-full overflow-hidden rounded-xl" />
            <p class="mt-3 text-center text-[10.5px] font-semibold text-slate-200">
              Arahkan barcode Code128 (PRK-...) dari layar HP / struk ke dalam kotak kamera
            </p>
          </div>

          <p
            v-if="cameraError"
            class="mt-3.5 flex items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-xs font-semibold text-amber-800"
          >
            <AlertCircle :size="15" class="shrink-0" />
            <span>{{ cameraError }}</span>
          </p>

          <h2 class="mt-3.5 text-sm font-extrabold text-[#0F172A]">
            Pindai Barcode Karcis atau Masukkan Nomor Plat
          </h2>
          <p class="mt-0.5 text-[11px] text-[#64748B]">
            Klik <strong>Scan via Kamera</strong> untuk memakai webcam laptop, atau ketik kode tiket / plat secara manual.
          </p>

          <p
            v-if="feedbackError"
            class="mt-3.5 flex items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-700"
          >
            <AlertCircle :size="15" class="shrink-0" />
            <span>{{ feedbackError }}</span>
          </p>

          <p
            v-if="feedbackSuccess"
            class="mt-3.5 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs font-semibold text-emerald-800"
          >
            <CheckCircle2 :size="15" class="shrink-0" />
            <span>{{ feedbackSuccess }}</span>
          </p>

          <form @submit.prevent="prosesKeluar(null, true)" class="mt-4 space-y-3.5">
            <div class="flex flex-col gap-2.5 sm:flex-row">
              <input
                ref="scanInputRef"
                v-model="scanValue"
                type="text"
                placeholder="PRK-000048 atau B 1982 PKZ"
                autocomplete="off"
                class="flex-1 rounded-2xl border-2 border-[#E2E8F0] bg-white px-4 py-3 font-mono text-sm font-extrabold tracking-wider text-[#0F172A] uppercase focus:border-olive focus:outline-none"
              />
              <button
                type="submit"
                :disabled="isSubmitting"
                class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-2xl bg-[#0F172A] px-5 py-3 text-xs font-extrabold text-white transition-colors hover:bg-[#1E293B] disabled:opacity-60"
              >
                <span>{{ isSubmitting ? 'Memproses...' : 'Proses Keluar' }}</span>
                <kbd class="rounded bg-white/15 px-1.5 py-0.5 font-mono text-[10px]">Enter</kbd>
              </button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
              <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-semibold text-[#334155]">
                <input
                  v-model="karcisHilang"
                  type="checkbox"
                  class="h-4 w-4 rounded border-slate-300 text-olive focus:ring-olive"
                />
                <span>Karcis Hilang (Denda)</span>
              </label>

              <button
                type="button"
                @click="handleResetSesi"
                class="inline-flex cursor-pointer items-center gap-1 text-[11px] font-bold text-[#64748B] hover:text-[#0F172A]"
              >
                <RotateCcw :size="12" />
                <span>Reset Input &amp; Struk</span>
              </button>
            </div>
          </form>

          <!-- Rekomendasi Cepat -->
          <div v-if="rekomendasiCepatList.length > 0" class="mt-4 border-t border-slate-200/80 pt-3.5">
            <p class="text-[10.5px] font-bold text-[#64748B]">Rekomendasi Cepat (Aktif):</p>
            <div class="mt-2 flex flex-wrap gap-2">
              <button
                v-for="item in rekomendasiCepatList"
                :key="item.idParkir"
                type="button"
                @click="pilihRekomendasi(item)"
                class="cursor-pointer rounded-xl border border-[#E2E8F0] bg-white px-3 py-1.5 font-mono text-[10.5px] font-bold text-[#0F172A] transition-colors hover:border-olive hover:bg-[#F4F8EA]"
              >
                {{ item.noTiket }} • {{ item.plat }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Kolom Kanan: Pos Kasir + Struk 56x46mm -->
      <div class="space-y-5">
        <section class="no-print rounded-[24px] border border-[#DCE8BE] bg-stone-50 p-5 text-[#161F00] shadow-2xs">
          <div class="flex items-center justify-between gap-2 text-[10px]">
            <span class="font-mono font-bold tracking-wider text-[#64748B] uppercase">
              RINGKASAN PARKIR
            </span>
            <span class="rounded-full bg-[#E3F1BC] px-2.5 py-0.5 font-extrabold text-[#3E4E14]">
              {{ posKasirSummary.badge }}
            </span>
          </div>

          <p class="mt-2.5 text-sm font-extrabold text-[#3E4E14]">
            {{ posKasirSummary.title }}
          </p>

          <div class="mt-3.5 grid grid-cols-2 gap-2.5">
            <div class="rounded-2xl border border-[#E6EED1] bg-white p-3">
              <p class="text-[10px] text-[#64748B]">Durasi Parkir</p>
              <p class="mt-0.5 text-sm font-extrabold text-[#0F172A]">
                {{ posKasirSummary.durasiLabel }}
              </p>
              <p class="text-[9.5px] text-[#64748B]">{{ posKasirSummary.durasiSub }}</p>
            </div>

            <div class="rounded-2xl border border-[#E6EED1] bg-white p-3">
              <p class="text-[10px] text-[#64748B]">Tarif Berlaku</p>
              <p class="mt-0.5 text-sm font-extrabold text-[#0F172A]">
                {{ posKasirSummary.tarifLabel }}
              </p>
              <p class="truncate text-[9.5px] text-[#64748B]">{{ posKasirSummary.tarifSub }}</p>
            </div>
          </div>

          <div class="mt-3.5 rounded-2xl border border-[#E6EED1] bg-white px-4 py-3">
            <p class="text-[9.5px] font-bold tracking-wider text-[#64748B] uppercase">
              TOTAL TAGIHAN FINAL
            </p>
            <p class="mt-0.5 text-2xl font-extrabold text-[#161F00]">
              {{ formatRupiah(posKasirSummary.total) }}
            </p>
          </div>

          <button
            type="button"
            @click="handleCetakStrukManual"
            class="mt-4 flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-[#5A920A] px-4 py-3 text-xs font-extrabold text-white transition-colors hover:bg-[#487607]"
          >
            <Printer :size="15" />
            <span>Cetak Struk (56×46mm)</span>
            <kbd class="rounded bg-black/15 px-1.5 py-0.5 font-mono text-[10px]">F4</kbd>
          </button>
        </section>

        <!-- Struk Keluar Ukuran 56x46mm -->
        <section class="rounded-[24px] border border-[#E2E8F0] bg-white p-5 shadow-2xs">
          <div class="no-print mb-3 flex items-center justify-between text-[10.5px]">
            <span class="inline-flex items-center gap-1.5 font-bold text-[#334155]">
              <Receipt :size="13" class="text-olive" />
              <span>STRUK KELUAR (56×46mm)</span>
            </span>
            <span class="font-mono text-[10px] text-[#94A3B8]">Code128</span>
          </div>

          <div class="flex justify-center">
            <div class="print-area w-[212px] rounded-xl border border-dashed border-[#94A3B8] bg-white p-2 text-center text-[#0F172A]">
              <div class="flex items-center justify-between border-b border-dashed border-[#CBD5E1] pb-0.5 text-[7.5px] font-extrabold">
                <span>STRUK KELUAR</span>
                <span class="font-mono">{{ strukDisplay.noTransaksi }}</span>
              </div>

              <p class="mt-0.5 font-mono text-[11px] font-extrabold tracking-wider">
                {{ strukDisplay.plat }}
              </p>
              <p class="text-[7.5px] text-[#475569]">
                {{ strukDisplay.jenis }} • {{ strukDisplay.area }}
              </p>

              <div class="mt-1 flex justify-between text-[7px] text-[#334155]">
                <span>Masuk: {{ formatTanggalJamSingkat(strukDisplay.waktuMasuk) }}</span>
                <span>{{ strukDisplay.durasiJam }}j</span>
              </div>

              <div class="mt-0.5 flex items-center justify-between text-[7.5px] font-extrabold">
                <span>TOTAL BAYAR</span>
                <span>{{ formatRupiah(strukDisplay.biayaTotal) }}</span>
              </div>

              <div class="mt-1 flex flex-col items-center justify-center">
                <svg ref="strukBarcodeRef" class="max-w-full" />
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- Bagian Bawah: Daftar Kendaraan Aktif Parkir -->
    <section class="no-print mt-6 rounded-[24px] border border-[#E2E8F0] bg-white p-6 shadow-2xs">
      <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
        <div class="flex flex-wrap items-center gap-2.5">
          <h2 class="text-base font-extrabold text-[#0F172A]">
            Daftar Kendaraan Aktif Parkir
          </h2>
          <span class="rounded-full bg-[#EAF6C9] px-3 py-0.5 text-[11px] font-bold text-[#3E4E14]">
            {{ aktifList.length }} Terparkir
          </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <div class="relative">
            <Search :size="13" class="absolute top-1/2 left-3 -translate-y-1/2 text-[#94A3B8]" />
            <input
              v-model="searchAktif"
              type="text"
              placeholder="Cari Plat atau Karcis..."
              class="rounded-full border border-[#E2E8F0] bg-[#F8FAFC] py-1.5 pr-3.5 pl-8 text-xs text-[#0F172A] focus:border-olive focus:bg-white focus:outline-none"
            />
          </div>

          <div class="flex flex-wrap gap-1">
            <button
              v-for="chip in areaFilterChips"
              :key="chip"
              type="button"
              @click="selectedAreaFilter = chip"
              :class="[
                'cursor-pointer rounded-full px-3 py-1.5 text-[10.5px] font-bold transition-colors',
                selectedAreaFilter === chip
                  ? 'bg-[#0F172A] text-white'
                  : 'bg-[#F8FAFC] text-[#475569] hover:bg-slate-200',
              ]"
            >
              {{ chip === 'semua' ? 'Semua' : chip }}
            </button>
          </div>
        </div>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] font-extrabold tracking-wider text-[#94A3B8] uppercase">
              <th class="py-3 pr-4">No Transaksi</th>
              <th class="py-3 px-4">Plat Nomor</th>
              <th class="py-3 px-4">Jenis Kendaraan</th>
              <th class="py-3 px-4">Area Parkir</th>
              <th class="py-3 px-4">Waktu Masuk</th>
              <th class="py-3 px-4">Durasi Saat Ini</th>
              <th class="py-3 pl-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="row in filteredAktifList"
              :key="row.idParkir"
              class="hover:bg-[#FAFBF9]"
            >
              <td class="py-3.5 pr-4 font-mono text-[11px] font-bold text-[#334155]">
                {{ row.noTiket }}
              </td>
              <td class="py-3.5 px-4 font-mono text-xs font-extrabold text-[#0F172A]">
                {{ row.plat }}
              </td>
              <td class="py-3.5 px-4 font-semibold text-[#334155]">
                {{ row.jenisLabel }}
              </td>
              <td class="py-3.5 px-4 text-[#475569]">
                {{ row.areaNama }}
              </td>
              <td class="py-3.5 px-4 font-mono text-[11px] text-[#475569]">
                {{ row.jamMasukWib }}
              </td>
              <td class="py-3.5 px-4 font-bold text-olive">
                {{ row.durasiText }}
              </td>
              <td class="py-3.5 pl-4 text-right">
                <button
                  type="button"
                  :disabled="isSubmitting"
                  @click="prosesKeluar({ id_parkir: row.idParkir, karcis_hilang: karcisHilang }, true)"
                  class="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-[#0F172A] px-3.5 py-1.5 text-[11px] font-bold text-white transition-colors hover:bg-olive disabled:opacity-60"
                >
                  <span>Proses Keluar</span>
                </button>
              </td>
            </tr>
            <tr v-if="filteredAktifList.length === 0">
              <td colspan="7" class="py-8 text-center text-xs text-[#64748B]">
                Tidak ada kendaraan aktif parkir yang cocok.
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

#parkflow-camera-reader video {
  transform: none !important;
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
