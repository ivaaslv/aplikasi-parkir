<script setup>
import { CalendarDays, Bell, LogOut, Menu, PanelLeftClose, PanelLeftOpen } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps({
  collapsed: { type: Boolean, default: false },
  currentRole: { type: String, default: 'admin' },
  user: { type: Object, default: null },
})

const emit = defineEmits(['open-mobile', 'toggle-collapse', 'logout'])

const displayName = computed(
  () =>
    props.user?.nama ||
    props.user?.name ||
    props.user?.username ||
    (props.currentRole === 'petugas'
      ? 'Petugas Operasional'
      : props.currentRole === 'owner'
        ? 'Owner Executive'
        : 'Administrator')
)

const displayInitials = computed(
  () =>
    displayName.value
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase())
      .join('') || 'PF'
)

const roleBadge = computed(() => {
  if (props.currentRole === 'petugas') {
    return { text: 'Petugas', cls: 'bg-sky-100 text-sky-800' }
  }
  if (props.currentRole === 'owner') {
    return { text: 'Owner', cls: 'bg-[#F3E8E2] text-[#8C4A32]' }
  }
  return { text: 'Admin', cls: 'bg-[#D5EC96] text-[#50631D]' }
})

const formattedToday = computed(() =>
  new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date())
)
</script>

<template>
  <header class="sticky top-0 z-30 flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E8F0] bg-[#FFFFFF]/95 px-6 py-3 backdrop-blur-xs lg:px-8">
    <!-- Kiri: Toggle Sidebar + Tanggal -->
    <div class="flex items-center gap-2.5">
      <button
        type="button"
        @click="emit('open-mobile')"
        class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-2 text-[#475569] hover:border-olive hover:text-olive lg:hidden"
        aria-label="Buka menu"
      >
        <Menu :size="16" />
      </button>

      <button
        type="button"
        @click="emit('toggle-collapse')"
        :title="collapsed ? 'Buka sidebar' : 'Tutup sidebar'"
        class="hidden rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-2 text-[#475569] transition-colors hover:border-olive hover:text-olive lg:inline-flex"
      >
        <PanelLeftOpen v-if="collapsed" :size="15" />
        <PanelLeftClose v-else :size="15" />
      </button>

      <div class="inline-flex items-center gap-2 rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-1.5 text-[10.5px] font-medium text-[#475569]">
        <CalendarDays :size="13" class="text-olive" />
        <span class="capitalize">{{ formattedToday }}</span>
      </div>
    </div>

    <!-- Kanan: Notif + Profil Akun Login + Logout -->
    <div class="flex items-center gap-2.5">
      <button
        type="button"
        class="relative flex h-8 w-8 items-center justify-center rounded-full border border-[#E2E8F0] bg-[#FFFFFF] text-[#475569] transition-colors hover:border-olive hover:text-olive"
        aria-label="Notifikasi"
      >
        <Bell :size="14" />
        <span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-olive" />
      </button>

      <div class="flex items-center gap-2.5 rounded-full border border-[#E2E8F0] bg-[#FFFFFF] py-2 px-4 pr-6">
        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#161F00] text-[10px] font-bold text-white">
          {{ displayInitials }}
        </span>
        <div class="hidden text-left sm:block">
          <p class="text-[11px] font-bold leading-tight text-[#0F172A] ">{{ displayName }}</p>
          <span
            :class="[
              'inline-block rounded-full px-2 py-0.5 text-[8.5px] font-bold',
              roleBadge.cls,
            ]"
          >
            {{ roleBadge.text }}
          </span>
        </div>
      </div>

      <button
        type="button"
        @click="emit('logout')"
        title="Keluar sistem"
        class="flex h-8 w-8 items-center justify-center rounded-full border border-[#E2E8F0] bg-[#FFFFFF] text-[#64748B] transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
      >
        <LogOut :size="14" />
      </button>
    </div>
  </header>
</template>