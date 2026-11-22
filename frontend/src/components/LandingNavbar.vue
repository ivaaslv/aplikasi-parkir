<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'

const mobileOpen = ref(false)

const navItems = [
  { label: 'Beranda', href: '#beranda' },
  { label: 'Fitur', href: '#fitur' },
  { label: 'Alur Kerja', href: '#alur-kerja' },
  { label: 'Peran', href: '#peran' },
]
</script>

<template>
  <header class="sticky top-0 z-40 border-b border-[#E2E8F0]/80 bg-white/95 backdrop-blur-md">
    <div class="mx-auto flex h-[72px] max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-10">
      <!-- Logo Kiri -->
      <a href="#beranda" class="flex items-center gap-3">
        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#D5EC96]/40 text-[#50631D]">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
            <rect x="4" y="4" width="16" height="16" rx="5" />
            <path d="M10 16V8h3a2.5 2.5 0 0 1 0 5h-3" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <span class="text-base font-bold tracking-tight text-[#0F172A]">Parkly</span>
      </a>

      <!-- Menu Tengah (Pill Container) -->
      <nav
        aria-label="Navigasi Utama"
        class="hidden items-center gap-1 rounded-full border border-[#E2E8F0]/80 bg-[#F8FAFC] p-1 md:flex"
      >
        <a
          v-for="item in navItems"
          :key="item.href"
          :href="item.href"
          class="rounded-full px-4 py-1.5 text-[13px] font-medium text-[#475569] transition hover:bg-white hover:text-[#0F172A] hover:shadow-xs"
        >
          {{ item.label }}
        </a>
      </nav>

      <!-- Tombol Masuk Kanan -->
      <div class="flex items-center gap-2.5">
        <RouterLink
          to="/login"
          class="rounded-full bg-[#50631D] px-6 py-2.5 text-[13px] font-semibold text-white shadow-[0_6px_16px_-4px_rgba(80,99,29,0.35)] transition hover:bg-[#3F4F16] active:scale-[0.98]"
        >
          Masuk
        </RouterLink>

        <button
          type="button"
          class="flex h-10 w-10 items-center justify-center rounded-full border border-[#E2E8F0] bg-[#F8FAFC] text-[#0F172A] md:hidden"
          :aria-expanded="mobileOpen"
          aria-label="Toggle Menu"
          @click="mobileOpen = !mobileOpen"
        >
          <svg v-if="!mobileOpen" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" />
          </svg>
          <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile Dropdown -->
    <div v-if="mobileOpen" class="border-t border-[#E2E8F0] bg-white px-4 py-3 md:hidden">
      <div class="mx-auto flex max-w-7xl flex-col gap-1">
        <a
          v-for="item in navItems"
          :key="item.href"
          :href="item.href"
          class="rounded-full px-4 py-2 text-sm font-medium text-[#475569] hover:bg-[#F8FAFC] hover:text-[#0F172A]"
          @click="mobileOpen = false"
        >
          {{ item.label }}
        </a>
      </div>
    </div>
  </header>
</template>