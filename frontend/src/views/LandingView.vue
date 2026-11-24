<script setup>
import {
  Car, LogIn, ShieldCheck, Zap, Clock3, Printer, QrCode,
  Activity, CheckCircle2, Radio, Users, UserRound, ChartNoAxesCombined,
  ArrowRight, ArrowUpRight, CarFront, ReceiptText, FileText, Menu, X
} from 'lucide-vue-next'
import { motion } from 'motion-v'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const route = useRouter()

const GoToLogin = () => {
  router.push('/login')
}

const mobileMenuOpen = ref(false)
const activeNav = ref('beranda')

const navLinks = [
  { id: 'beranda', label: 'Beranda', href: '#beranda' },
  { id: 'fitur', label: 'Fitur', href: '#fitur' },
  { id: 'alur-kerja', label: 'Alur Kerja', href: '#alur-kerja' },
  { id: 'peran', label: 'Peran', href: '#peran' },
]

const selectNav = (id) => {
  activeNav.value = id
  mobileMenuOpen.value = false
}

let observer = null

onMounted(() => {
  const sectionIds = navLinks.map((n) => n.id)
  const elements = sectionIds
    .map((id) => document.getElementById(id))
    .filter(Boolean)

  if (!elements.length || typeof IntersectionObserver === 'undefined') return

  observer = new IntersectionObserver(
    (entries) => {
      // Ambil section yang paling terlihat di area atas/tengah viewport
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)

      if (visible.length > 0) {
        activeNav.value = visible[0].target.id
      }
    },
    {
      rootMargin: '-18% 0px -55% 0px',
      threshold: [0.1, 0.25, 0.5],
    }
  )

  elements.forEach((el) => observer.observe(el))
})

onBeforeUnmount(() => {
  if (observer) {
    observer.disconnect()
    observer = null
  }
})

const stats = [
  {
    value: '99.9%',
    title: 'Akurasi Hitung Tarif',
    description: 'Kalkulasi durasi parkir dan biaya otomatis berdasarkan tarif per jam tanpa salah hitung.',
    icon: ShieldCheck,
  },
  {
    value: '< 20 detik',
    title: 'Proses Masuk & Keluar Cepat',
    description: 'Catat kendaraan masuk, cetak karcis barcode Code128, dan proses keluar hanya dalam hitungan detik.',
    icon: Zap,
  },
  {
    value: 'Multi-Area',
    title: 'Pantau Kapasitas Area',
    description: 'Pantau jumlah kendaraan terisi dan sisa slot di tiap area parkir secara langsung.',
    icon: Radio,
  },
  {
    value: '100%',
    title: 'Laporan & Log Aktivitas',
    description: 'Rekap pendapatan per jenis kendaraan dan area serta pantau riwayat aktivitas pengguna.',
    icon: Activity,
  },
]

const steps = [
  {
    number: '01',
    title: 'Masuk & Cetak Code128',
    description: 'Petugas menginput data kendaraan dan memilih area parkir, lalu sistem mencetak karcis dengan barcode Code128.',
    note: 'Slot area otomatis ter-update',
    icon: Printer,
    noteIcon: CheckCircle2,
  },
  {
    number: '02',
    title: 'Scan Keluar & Hitung Tarif',
    description: 'Scan barcode atau masukkan kode/plat kendaraan saat keluar, sistem langsung menghitung durasi dan total biaya parkir.',
    note: 'Tarif per jam terhitung otomatis',
    icon: QrCode,
    noteIcon: ReceiptText,
  },
  {
    number: '03',
    title: 'Rekap & Audit Transparan',
    description: 'Data transaksi tersimpan rapi beserta riwayat aktivitas untuk memantau pendapatan dan operasional.',
    note: 'Log aktivitas tercatat rapi',
    icon: ChartNoAxesCombined,
    noteIcon: Activity,
  },
]

const roles = [
  {
    title: 'Admin Sistem',
    subtitle: 'Kelola Users, Tarif & Area',
    description: 'Atur akun pengguna, tarif per jam, kapasitas area parkir, data kendaraan, dan pantau log aktivitas.',
    items: ['Kelola User & Role', 'Tarif & Area Parkir', 'Data Kendaraan & Log Aktivitas'],
    button: 'Akses Kelola Sistem',
    icon: UserRound,
    photo: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=700&q=75',
  },
  {
    title: 'Petugas',
    subtitle: 'Catat Masuk, Scan Keluar & Transaksi',
    description: 'Layani kendaraan masuk dengan cetak karcis barcode, proses keluar dengan scan/kode, dan cek daftar transaksi.',
    items: ['Catat Masuk & Cetak Barcode', 'Scan Keluar & Hitung Biaya', 'Riwayat & Cetak Struk'],
    button: 'Akses Operasional',
    icon: Users,
    photo: 'https://images.unsplash.com/photo-1590674899484-d5640e854abe?auto=format&fit=crop&w=700&q=75',
  },
  {
    title: 'Owner',
    subtitle: 'Ringkasan & Laporan Pendapatan',
    description: 'Pantau ringkasan performa parkir dan filter laporan pendapatan berdasarkan tanggal, area, maupun jenis kendaraan.',
    items: ['Ringkasan Pendapatan & Slot', 'Filter Tanggal, Area & Jenis', 'Breakdown & Cetak Laporan'],
    button: 'Akses Laporan',
    icon: FileText,
    photo: 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=700&q=75',
  },
]
</script>

<template>
  <div class="min-h-screen bg-[#FFFFFF] text-[#0F172A]">
    <!-- Navbar -->
    <header class="sticky top-0 z-50 border-b border-[#E2E8F0] bg-[#FFFFFF]/95">
      <div class="mx-auto flex max-w-[1440px] items-center justify-between px-5 py-3 lg:px-8">
        <a href="#beranda" @click="selectNav('beranda')" class="flex items-center gap-2.5" aria-label="ParkFlow beranda">
          <span class="flex h-9 w-9 items-center justify-center rounded-full text-olive">
            <Car :size="30" />
          </span>
          <span class="text-lg font-bold tracking-tight">Park<span class="text-olive">Flow</span></span>
        </a>

        <!-- Nav Links: tanpa animasi hover, BG putih berpindah smooth pakai layout-id -->
        <nav class="hidden items-center gap-1 rounded-full border border-[#E2E8F0] bg-[#F1F5F9]/80 p-1.5 text-sm text-[#475569] md:flex">
          <a
            v-for="nav in navLinks"
            :key="nav.id"
            :href="nav.href"
            @click="selectNav(nav.id)"
            :class="[
              'relative rounded-full px-5 py-1.5 transition-colors duration-200',
              activeNav === nav.id ? 'font-semibold text-olive' : 'hover:text-olive'
            ]"
          >
            <motion.span
              v-if="activeNav === nav.id"
              layout-id="navbar-smooth-pill"
              :transition="{ type: 'spring', stiffness: 360, damping: 32 }"
              class="absolute inset-0 rounded-full bg-white shadow-2xs will-change-transform"
            />
            <span class="relative z-10">{{ nav.label }}</span>
          </a>
        </nav>

        <!-- Tombol Masuk Navbar dengan hover motion + animasi ikon -->
        <motion.a
          href="/login"
          :while-hover="{ y: -2, scale: 1.03 }"
          :while-press="{ scale: 0.96 }"
          :transition="{ type: 'spring', stiffness: 420, damping: 24 }"
          class="group hidden items-center gap-2 rounded-full bg-olive px-7 py-2.5 text-sm font-semibold text-white hover:bg-olive-dark sm:inline-flex will-change-transform"
        >
          <span>Masuk</span>
          <LogIn :size="15" class="transition-transform duration-200 ease-out group-hover:translate-x-1" />
        </motion.a>

        <button
          class="rounded-lg p-2 text-[#475569] hover:text-olive md:hidden"
          @click="mobileMenuOpen = !mobileMenuOpen"
          aria-label="Buka menu"
        >
          <X v-if="mobileMenuOpen" :size="22" />
          <Menu v-else :size="22" />
        </button>
      </div>
    </header>

    <div v-if="mobileMenuOpen" class="mx-5 mb-3 grid gap-2 rounded-2xl border border-[#E2E8F0] bg-[#FFFFFF] p-4 text-sm shadow-md md:hidden">
      <a
        v-for="nav in navLinks"
        :key="nav.id"
        :href="nav.href"
        @click="selectNav(nav.id)"
        :class="activeNav === nav.id ? 'font-semibold text-olive' : 'text-[#475569]'"
      >
        {{ nav.label }}
      </a>
      <a href="#masuk" @click="mobileMenuOpen = false" class="font-semibold text-olive">Masuk</a>
    </div>

    <main class="mx-auto max-w-[1440px] px-5 pb-10 pt-6 lg:px-8">
      <!-- Hero -->
      <section id="beranda" class="grid overflow-hidden rounded-[24px] border border-[#E2E8F0] bg-gradient-to-br from-[#F8FAFC] to-[#FFFFFF] px-7 py-10 sm:px-10 lg:min-h-[490px] lg:grid-cols-[1.2fr_0.9fr] lg:items-center lg:gap-10 lg:px-16 lg:py-14">
        <motion.div
          :initial="{ opacity: 0, y: 14 }"
          :animate="{ opacity: 1, y: 0 }"
          :transition="{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }"
          class="max-w-2xl will-change-transform"
        >
          <h1 class="max-w-xl text-4xl font-extrabold leading-[1.12] tracking-tight sm:text-5xl lg:text-[54px]">
            Manajemen Parkir &amp;<br class="hidden sm:block" /> Karcis Barcode.
          </h1>
          <p class="mt-5 max-w-xl text-sm leading-7 text-[#64748B] sm:text-base">
            Catat kendaraan masuk-keluar,<br class="hidden sm:block" />
            scan karcis barcode Code128, pantau kapasitas slot real-time,<br class="hidden xl:block" />
            dan rekap laporan dalam satu dashboard.
          </p>
          <div class="mt-7 flex flex-wrap gap-3">
            <!-- Tombol Mulai Sekarang + animasi ikon -->
            <motion.a
              href="/login"
              :while-hover="{ y: -2, scale: 1.03 }"
              :while-press="{ scale: 0.96 }"
              :transition="{ type: 'spring', stiffness: 420, damping: 24 }"
              class="group inline-flex items-center gap-2 rounded-full bg-olive px-7 py-3 text-sm font-semibold text-white hover:bg-olive-dark will-change-transform"
            >
              <span>Mulai Sekarang</span>
              <ArrowRight :size="15" class="transition-transform duration-200 ease-out group-hover:translate-x-1" />
            </motion.a>

            <!-- Tombol Lihat Alur Kerja + animasi ikon -->
            <motion.a
              href="#alur-kerja"
              @click="selectNav('alur-kerja')"
              :while-hover="{ y: -2, scale: 1.03 }"
              :while-press="{ scale: 0.96 }"
              :transition="{ type: 'spring', stiffness: 420, damping: 24 }"
              class="group inline-flex items-center gap-2 rounded-full border border-[#E2E8F0] bg-[#FFFFFF] px-7 py-3 text-sm font-semibold text-[#334155] hover:border-olive hover:text-olive will-change-transform"
            >
              <span>Lihat Alur Kerja</span>
              <ArrowUpRight :size="15" class="transition-transform duration-200 ease-out group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
            </motion.a>
          </div>
          <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-xs text-[#64748B]">
            <span class="inline-flex items-center gap-2"><ShieldCheck :size="15" class="text-olive" /> Scan Barcode Code128</span>
            <span class="inline-flex items-center gap-2"><Zap :size="15" class="text-olive" /> Hitung Tarif Otomatis</span>
            <span class="inline-flex items-center gap-2"><Clock3 :size="15" class="text-olive" /> Pantau Slot Real-Time</span>
          </div>
        </motion.div>

        <!-- Live parking ticket preview -->
        <div class="mx-auto mt-12 w-full max-w-[490px] lg:mt-0">
          <motion.div
            :initial="{ opacity: 0, y: 16 }"
            :animate="{ opacity: 1, y: 0 }"
            :transition="{ duration: 0.5, delay: 0.08, ease: [0.22, 1, 0.36, 1] }"
            :while-hover="{ y: -3 }"
            class="rounded-2xl border border-[#E2E8F0] bg-[#FFFFFF] p-5 shadow-2xs will-change-transform"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#D5EC96] text-olive"><CarFront :size="19" /></span>
                <span class="text-sm font-bold">Karcis Masuk Aktif</span>
              </div>
              <span class="rounded-md bg-[#F8FAFC] px-2.5 py-1 text-[10px] font-semibold text-[#64748B]">Karcis Masuk</span>
            </div>
            <div class="mt-5 rounded-xl bg-[#F1F5F9] p-4">
              <div class="grid grid-cols-2 gap-y-3 text-xs">
                <span class="text-[#64748B]">ID Karcis</span><span class="text-right font-mono font-bold text-olive">PRK-000128</span>
                <span class="text-[#64748B]">Plat Nomor</span><span class="text-right font-mono font-bold">N 1234 AB</span>
                <span class="text-[#64748B]">Waktu Masuk</span><span class="text-right font-mono text-[#475569]">14:32:08 WIB</span>
              </div>
              <div class="mt-5 rounded-2xl border border-[#E2E8F0] bg-[#FFFFFF] px-4 py-5 text-center">
                <div class="barcode mx-auto h-9 w-full max-w-[230px]"></div>
                <p class="mt-2 font-mono text-[9px] tracking-[0.25em] text-[#64748B]">*PRK-000128*</p>
              </div>
            </div>
          </motion.div>

          <motion.div
            :initial="{ opacity: 0, y: 16 }"
            :animate="{ opacity: 1, y: 0 }"
            :transition="{ duration: 0.5, delay: 0.16, ease: [0.22, 1, 0.36, 1] }"
            :while-hover="{ y: -3 }"
            class="mt-4 rounded-2xl border border-[#E2E8F0] bg-[#FFFFFF] p-4 shadow-2xs will-change-transform"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#D5EC96] font-bold text-olive">P</span>
                <div>
                  <p class="text-xs font-semibold">Area A <span class="font-normal text-[#94A3B8]">• 18/25 Terisi</span></p>
                  <p class="mt-1 text-[10px] text-[#64748B]">Okupansi 72% • Kapasitas RealTime</p>
                </div>
              </div>
            </div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-[#F8FAFC]">
              <div class="h-full w-[72%] rounded-full bg-olive"></div>
            </div>
          </motion.div>
        </div>
      </section>

      <!-- Benefits -->
      <section id="fitur" class="py-20 lg:py-24">
        <motion.div
          :initial="{ opacity: 0, y: 16 }"
          :while-in-view="{ opacity: 1, y: 0 }"
          :in-view-options="{ once: true, amount: 0.15 }"
          :transition="{ duration: 0.45 }"
          class="mb-8 flex flex-col justify-between gap-4 border-b border-slate-100 pb-6 md:flex-row md:items-end will-change-transform"
        >
          <div>
            <p class="text-[10px] font-bold tracking-widest text-olive">KEUNGGULAN UTAMA</p>
            <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Operasional Parkir Lebih Rapi.</h2>
          </div>
          <p class="max-w-md text-sm leading-6 text-[#64748B]">Dirancang untuk mempermudah pencatatan masuk-keluar, perhitungan tarif, dan rekap laporan harian.</p>
        </motion.div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <motion.article
            v-for="(item, index) in stats"
            :key="item.title"
            :initial="{ opacity: 0, y: 18 }"
            :while-in-view="{ opacity: 1, y: 0 }"
            :in-view-options="{ once: true, amount: 0.15 }"
            :transition="{ duration: 0.42, delay: index * 0.06, ease: [0.22, 1, 0.36, 1] }"
            :while-hover="{ y: -4 }"
            :while-press="{ scale: 0.99 }"
            class="group rounded-2xl border border-[#E2E8F0] bg-[#FFFFFF] p-6 shadow-2xs transition-colors duration-200 hover:border-olive/35 hover:shadow-sm will-change-transform"
          >
            <div class="flex items-center justify-between">
              <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#F1F5F9] text-olive transition-transform duration-200 group-hover:scale-105 group-hover:bg-[#D5EC96]/70">
                <component :is="item.icon" :size="18" />
              </span>
            </div>
            <p class="mt-8 text-3xl font-extrabold tracking-tight">{{ item.value }}</p>
            <h3 class="mt-2 text-sm font-bold">{{ item.title }}</h3>
            <p class="mt-3 text-xs leading-5 text-[#64748B]">{{ item.description }}</p>
          </motion.article>
        </div>
      </section>

      <!-- Live dashboard preview -->
      <motion.section
        :initial="{ opacity: 0, y: 20 }"
        :while-in-view="{ opacity: 1, y: 0 }"
        :in-view-options="{ once: true, amount: 0.15 }"
        :transition="{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }"
        class="mb-16 grid items-center gap-8 overflow-hidden rounded-[24px] border border-[#E2E8F0] bg-[#F8FAFC] p-6 sm:p-9 lg:grid-cols-[0.8fr_1.2fr] lg:gap-12 lg:p-12 will-change-transform"
      >
        <div class="max-w-lg">
          <p class="text-[10px] font-bold tracking-[0.18em] text-[#50631D]">PARKING OVERVIEW</p>
          <h2 class="mt-3 text-2xl font-bold leading-tight tracking-tight sm:text-3xl">Semua Area Parkir, Terpantau dalam Satu Dashboard.</h2>
          <p class="mt-4 text-sm leading-6 text-[#64748B]">Lihat kapasitas area, aktivitas kendaraan, dan ringkasan operasional tanpa perlu berpindah halaman.</p>
          <ul class="mt-6 space-y-3 text-sm text-[#475569]">
            <li class="flex items-center gap-3"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#D5EC96]/60 text-[#50631D]"><CheckCircle2 :size="15" /></span> Monitoring kapasitas secara real-time</li>
            <li class="flex items-center gap-3"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#D5EC96]/60 text-[#50631D]"><Activity :size="15" /></span> Aktivitas masuk dan keluar tercatat</li>
            <li class="flex items-center gap-3"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#D5EC96]/60 text-[#50631D]"><ChartNoAxesCombined :size="15" /></span> Ringkasan performa lebih mudah dibaca</li>
          </ul>
        </div>

        <motion.div
          :while-hover="{ y: -3 }"
          :transition="{ type: 'spring', stiffness: 360, damping: 26 }"
          class="rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm sm:p-6 will-change-transform"
        >
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E8F0] pb-4">
            <div>
              <p class="text-sm font-bold text-[#0F172A]">Ringkasan Parkir</p>
              <p class="mt-1 text-[10px] text-[#94A3B8]">Pantauan operasional hari ini</p>
            </div>
            <span class="inline-flex items-center gap-2 rounded-full bg-[#D5EC96]/40 px-3 py-1.5 text-[10px] font-semibold text-[#50631D]"> Live update</span>
          </div>
          <div class="mt-5 grid grid-cols-3 gap-2 sm:gap-3">
            <div class="rounded-xl bg-[#F8FAFC] p-3">
              <p class="text-[9px] text-[#64748B]">Kendaraan</p>
              <p class="mt-2 text-xl font-extrabold text-[#0F172A]">128</p>
              <p class="mt-1 text-[9px] text-[#50631D]">Hari ini</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] p-3">
              <p class="text-[9px] text-[#64748B]">Slot Terisi</p>
              <p class="mt-2 text-xl font-extrabold text-[#0F172A]">72%</p>
              <p class="mt-1 text-[9px] text-[#50631D]">18 dari 25</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] p-3">
              <p class="text-[9px] text-[#64748B]">Area Aktif</p>
              <p class="mt-2 text-xl font-extrabold text-[#0F172A]">04</p>
              <p class="mt-1 text-[9px] text-[#50631D]">Semua normal</p>
            </div>
          </div>
          <div class="mt-5 grid gap-4 sm:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-xl border border-[#E2E8F0] p-4">
              <div class="flex items-center justify-between">
                <p class="text-xs font-bold">Kapasitas Area</p>
                <span class="text-[9px] text-[#94A3B8]">Terisi / Total</span>
              </div>
              <div class="mt-4 space-y-4">
                <div v-for="area in [{name:'Area A',used:72,count:'18 / 25'},{name:'Area B',used:54,count:'13 / 24'},{name:'Area C',used:86,count:'43 / 50'}]" :key="area.name">
                  <div class="mb-2 flex justify-between text-[10px]"><span class="font-semibold text-[#334155]">{{ area.name }}</span><span class="text-[#64748B]">{{ area.count }}</span></div>
                  <div class="h-2 overflow-hidden rounded-full bg-[#E2E8F0]"><div class="h-full rounded-full bg-[#50631D]" :style="{ width: `${area.used}%` }"></div></div>
                </div>
              </div>
            </div>
            <div class="rounded-xl border border-[#E2E8F0] p-4">
              <p class="text-xs font-bold">Aktivitas Terbaru</p>
              <div class="mt-4 space-y-4">
                <div class="flex items-start gap-2.5"><span class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-[#D5EC96]/60 text-[#50631D]"><CarFront :size="12" /></span><div><p class="text-[10px] font-semibold">Kendaraan masuk</p><p class="mt-0.5 text-[9px] text-[#94A3B8]">N 1234 AB · 14:32</p></div></div>
                <div class="flex items-start gap-2.5"><span class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-[#F1F5F9] text-[#475569]"><QrCode :size="12" /></span><div><p class="text-[10px] font-semibold">Barcode dipindai</p><p class="mt-0.5 text-[9px] text-[#94A3B8]">B 1020 XY · 14:28</p></div></div>
                <div class="flex items-start gap-2.5"><span class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-[#D5EC96]/60 text-[#50631D]"><CheckCircle2 :size="12" /></span><div><p class="text-[10px] font-semibold">Transaksi selesai</p><p class="mt-0.5 text-[9px] text-[#94A3B8]">Rp 5.000 · 14:20</p></div></div>
              </div>
            </div>
          </div>
          <p class="mt-4 text-center text-[9px] text-[#94A3B8]">*Ilustrasi tampilan dashboard ParkFlow</p>
        </motion.div>
      </motion.section>

      <!-- Workflow -->
      <section id="alur-kerja" class="rounded-[24px] border border-[#E2E8F0] bg-[#F1F5F9]/80 px-6 py-10 sm:px-10 lg:px-14 lg:py-16">
        <motion.div
          :initial="{ opacity: 0, y: 16 }"
          :while-in-view="{ opacity: 1, y: 0 }"
          :in-view-options="{ once: true, amount: 0.15 }"
          :transition="{ duration: 0.45 }"
          class="max-w-2xl will-change-transform"
        >
          <div class="inline-flex items-center gap-2 rounded-full border border-[#E2E8F0] bg-[#FFFFFF] px-3 py-1.5 text-[10px] font-bold tracking-wide text-olive">
            <Activity :size="13" /> ALUR KERJA
          </div>
          <h2 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">Tiga Langkah Simpel di Lapangan</h2>
          <p class="mt-2 text-sm leading-6 text-[#64748B]">Alur otomatis mulai dari pencatatan kendaraan masuk, scan karcis keluar, hingga rekap laporan.</p>
        </motion.div>

        <div class="mt-8 grid gap-4 lg:grid-cols-3">
          <motion.article
            v-for="(step, index) in steps"
            :key="step.number"
            :initial="{ opacity: 0, y: 18 }"
            :while-in-view="{ opacity: 1, y: 0 }"
            :in-view-options="{ once: true, amount: 0.15 }"
            :transition="{ duration: 0.42, delay: index * 0.07, ease: [0.22, 1, 0.36, 1] }"
            :while-hover="{ y: -4 }"
            :while-press="{ scale: 0.99 }"
            class="group flex min-h-[235px] flex-col rounded-2xl border border-[#E2E8F0] bg-[#FFFFFF] p-6 shadow-2xs transition-colors duration-200 hover:border-olive/35 hover:shadow-sm will-change-transform"
          >
            <div class="flex items-center justify-between">
              <span class="rounded-full bg-[#F1F5F9] px-3 py-1 text-[10px] font-bold text-olive transition-colors group-hover:bg-[#D5EC96]/60">{{ step.number }}</span>
              <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#D5EC96] text-olive transition-transform duration-200 group-hover:scale-105">
                <component :is="step.icon" :size="17" />
              </span>
            </div>
            <h3 class="mt-5 text-base font-bold">{{ step.title }}</h3>
            <p class="mt-3 flex-1 text-xs leading-5 text-[#64748B]">{{ step.description }}</p>
            <div class="mt-5 flex items-center gap-2 border-t border-slate-100 pt-4 text-[10px] text-olive">
              <component :is="step.noteIcon" :size="13" /> {{ step.note }}
            </div>
          </motion.article>
        </div>
      </section>

      <!-- Roles -->
      <section id="peran" class="py-16 lg:py-20">
        <motion.div
          :initial="{ opacity: 0, y: 16 }"
          :while-in-view="{ opacity: 1, y: 0 }"
          :in-view-options="{ once: true, amount: 0.15 }"
          :transition="{ duration: 0.45 }"
          class="mx-auto mb-10 max-w-2xl text-center will-change-transform"
        >
          <p class="text-[10px] font-bold tracking-widest text-olive">EKOSISTEM PENGGUNA</p>
          <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Dirancang Khusus untuk Setiap Peran</h2>
          <p class="mt-3 text-sm leading-6 text-[#64748B]">Antarmuka yang dipersonalisasi memudahkan staf lapangan bertindak cepat dan pengambil keputusan melihat data makro.</p>
        </motion.div>

        <div class="grid gap-5 lg:grid-cols-3">
          <motion.article
            v-for="(role, index) in roles"
            :key="role.title"
            :initial="{ opacity: 0, y: 18 }"
            :while-in-view="{ opacity: 1, y: 0 }"
            :in-view-options="{ once: true, amount: 0.15 }"
            :transition="{ duration: 0.42, delay: index * 0.07, ease: [0.22, 1, 0.36, 1] }"
            :while-hover="{ y: -4 }"
            :while-press="{ scale: 0.99 }"
            class="group flex flex-col rounded-2xl border border-[#E2E8F0] bg-[#FFFFFF] p-6 shadow-2xs transition-colors duration-200 hover:border-olive/35 hover:shadow-sm will-change-transform"
          >
            <div class="mb-4 overflow-hidden rounded-xl border border-[#E2E8F0]">
              <img
                :src="role.photo"
                :alt="role.title"
                loading="lazy"
                class="h-28 w-full object-cover"
              />
            </div>
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#D5EC96] text-olive transition-transform duration-200 group-hover:scale-105">
                <component :is="role.icon" :size="18" />
              </span>
              <div>
                <h3 class="text-sm font-bold">{{ role.title }}</h3>
                <p class="mt-0.5 text-[10px] text-[#94A3B8]">{{ role.subtitle }}</p>
              </div>
            </div>
            <p class="mt-4 border-t border-slate-100 pt-4 text-xs leading-5 text-[#64748B]">{{ role.description }}</p>
            <ul class="mt-4 flex-1 space-y-2">
              <li v-for="item in role.items" :key="item" class="flex items-start gap-2 text-xs text-[#475569]">
                <CheckCircle2 :size="14" class="mt-0.5 shrink-0 text-olive" /> {{ item }}
              </li>
            </ul>
            <!-- Tombol Peran dengan hover motion + animasi ikon -->
            <motion.a
              href="/login"
              :while-hover="{ y: -2, scale: 1.02 }"
              :while-press="{ scale: 0.97 }"
              :transition="{ type: 'spring', stiffness: 420, damping: 24 }"
              class="group/btn mt-6 inline-flex items-center justify-center gap-1.5 rounded-full border border-[#E2E8F0] bg-[#F1F5F9] px-4 py-2.5 text-center text-xs font-semibold text-[#475569] hover:border-olive hover:text-olive will-change-transform"
            >
              <span>{{ role.button }}</span>
              <ArrowUpRight :size="14" class="transition-transform duration-200 ease-out group-hover/btn:translate-x-0.5 group-hover/btn:-translate-y-0.5" />
            </motion.a>
          </motion.article>
        </div>
      </section>

      <!-- CTA -->
      <motion.section
        id="masuk"
        :initial="{ opacity: 0, y: 18 }"
        :while-in-view="{ opacity: 1, y: 0 }"
        :in-view-options="{ once: true, amount: 0.15 }"
        :transition="{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }"
        class="flex flex-col items-start justify-between gap-6 rounded-[22px] bg-[#50631D] px-7 py-9 text-white shadow-sm sm:px-10 md:flex-row md:items-center lg:px-12 lg:py-12 will-change-transform"
      >
        <div>
          <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Siap Mengelola Parkir Lebih Tertata?</h2>
          <p class="mt-2 max-w-xl text-xs leading-5 text-white/75">Masuk ke sistem untuk mulai mencatat kendaraan, memantau slot, dan melihat laporan pendapatan.</p>
        </div>
        <!-- Tombol Masuk Sekarang di CTA dengan hover motion + animasi ikon -->
        <motion.a
          href="/login"
          :while-hover="{ y: -2, scale: 1.03 }"
          :while-press="{ scale: 0.96 }"
          :transition="{ type: 'spring', stiffness: 420, damping: 24 }"
          class="group inline-flex shrink-0 items-center gap-3 rounded-full bg-[#FFFFFF] px-7 py-3 text-xs font-bold text-[#0F172A] hover:bg-[#D5EC96] will-change-transform"
        >
          <span>Masuk Sekarang</span>
          <ArrowRight :size="16" class="transition-transform duration-200 ease-out group-hover:translate-x-1" />
        </motion.a>
      </motion.section>
    </main>

    <!-- Footer -->
    <footer class="mt-12 border-t border-[#E2E8F0]">
      <div class="mx-auto flex max-w-[1440px] flex-col items-center justify-between gap-4 px-5 py-6 text-xs sm:flex-row lg:px-8">
        <a href="#beranda" @click="selectNav('beranda')" class="flex items-center gap-2 font-bold">
          <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#D5EC96] text-olive"><Car :size="15" /></span>
          ParkFlow System
        </a>
        <p class="text-center text-[10px] text-[#94A3B8]">© 2026 ParkFlow Smart Parking Management. Seluruh hak cipta dilindungi.</p>
        <span class="inline-flex items-center gap-2 rounded-full bg-[#F8FAFC] px-3 py-2 text-[10px] text-[#475569]"> Sistem Aktif Normal</span>
      </div>
    </footer>
  </div>
</template>

<style>
:root {
  font-family: "Plus Jakarta Sans", Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  font-synthesis: none;
  text-rendering: optimizeLegibility;
  color: #0F172A;
  background: #FFFFFF;
}
html { scroll-behavior: smooth; scroll-padding-top: 88px; }
body { margin: 0; }
.text-olive { color: #50631D; }
.bg-olive { background-color: #50631D; }
.bg-olive-dark { background-color: #161F00; }

.barcode {
  background: repeating-linear-gradient(
    90deg,
    #0F172A 0 2px, transparent 2px 4px,
    #0F172A 4px 5px, transparent 5px 7px,
    #0F172A 7px 10px, transparent 10px 12px,
    #0F172A 12px 13px, transparent 13px 16px,
    #0F172A 16px 19px, transparent 19px 21px
  );
}
</style>