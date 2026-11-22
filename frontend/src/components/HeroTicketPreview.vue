<script setup>
const bars = [
  3, 1, 2, 1, 4, 1, 2, 3, 1, 2, 1, 3, 2, 1, 4, 1, 2, 2, 1, 3, 1, 2, 4, 1, 2, 1, 3, 2, 1, 3,
]
</script>

<template>
  <div class="flex flex-col gap-4">
    <!-- Kartu Karcis Masuk Aktif -->
    <div class="rounded-3xl border border-[#E2E8F0]/90 bg-white p-6 shadow-[0_12px_32px_-12px_rgba(15,23,42,0.08)]">
      <div class="mb-4 flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#D5EC96]/35 text-[#50631D]">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 7V4h3M17 4h3v3M4 17v3h3M17 20h3v-3" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M9 9v6M12 9v6M15 9v6" stroke-linecap="round" />
            </svg>
          </span>
          <span class="text-sm font-bold text-[#0F172A]">Karcis Masuk Aktif</span>
        </div>

        <span class="rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1 text-[11px] font-medium text-[#64748B]">
          Karcis Masuk
        </span>
      </div>

      <div class="rounded-2xl border border-[#E2E8F0]/70 bg-[#F8FAFC] p-4">
        <div class="space-y-2.5 text-xs">
          <div class="flex items-center justify-between">
            <span class="text-[#64748B]">ID Karcis</span>
            <span class="font-mono font-bold text-[#50631D]">PRK-000128</span>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-[#64748B]">Plat Nomor</span>
            <span class="rounded-lg border border-[#E2E8F0] bg-white px-2.5 py-0.5 font-mono text-[11px] font-bold text-[#0F172A]">
              N 1234 AB
            </span>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-[#64748B]">Waktu Masuk</span>
            <span class="font-mono text-[11px] font-semibold text-[#334155]">14:32:08 WIB</span>
          </div>
        </div>

        <!-- Barcode Code128 -->
        <div class="mt-4 rounded-xl border border-[#E2E8F0]/80 bg-white px-4 py-3.5 text-center">
          <div class="flex h-10 items-center justify-center gap-[2px]">
            <span
              v-for="(w, i) in bars"
              :key="i"
              class="inline-block h-full bg-[#191D18]"
              :style="{ width: `${w * 1.8}px` }"
            />
          </div>
          <p class="mt-2 font-mono text-[10px] tracking-[0.22em] text-[#94A3B8]">
            *PRK-000128*
          </p>
        </div>
      </div>
    </div>

    <!-- Kartu Slot Area A -->
    <div class="rounded-3xl border border-[#E2E8F0]/90 bg-white px-5 py-4 shadow-[0_10px_28px_-12px_rgba(15,23,42,0.06)]">
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#D5EC96]/40 text-sm font-bold text-[#50631D]">
            P
          </span>

          <div>
            <div class="flex items-center gap-1.5 text-xs">
              <span class="font-bold text-[#0F172A]">Area A</span>
              <span class="text-[#94A3B8]">•</span>
              <span class="font-semibold text-[#50631D]">18/25 Terisi</span>
            </div>
            <p class="mt-0.5 text-[11px] text-[#64748B]">
              Okupansi 72% • Kapasitas Real-Time
            </p>
          </div>
        </div>

        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-[#A7F3D0]/60 bg-[#ECFDF5] px-3 py-1 text-[11px] font-semibold text-[#065F46]">
          <span class="h-1.5 w-1.5 rounded-full bg-[#10B981]" />
          Lancar
        </span>
      </div>

      <div class="mt-3.5 h-2 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
        <div class="h-full rounded-full bg-[#50631D]" style="width: 72%" />
      </div>
    </div>
  </div>
</template>