<script setup>
import {
  Leaf,
  LayoutGrid,
  Users,
  Tag,
  SquareParking,
  CarFront,
  History,
  ClipboardList,
  LogIn,
  QrCode,
  ReceiptText,
  TrendingUp,
  FileBarChart2,
  Lock,
  LogOut,
  X,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'

const props = defineProps({
  collapsed: { type: Boolean, default: false },
  mobileOpen: { type: Boolean, default: false },
  currentRole: { type: String, default: 'admin' },
  activeMenu: { type: String, default: 'dashboard-admin' },
  user: { type: Object, default: null },
})

const emit = defineEmits(['close-mobile', 'select-menu', 'logout'])

const lockedToast = ref('')
let toastTimer = null

const menuGroups = [
  {
    id: 'admin-group',
    title: 'ADMIN WORKSPACE',
    badge: 'Admin',
    badgeClass: 'bg-[#161F00] text-white',
    allowedRoles: ['admin'],
    items: [
      { id: 'dashboard-admin', label: 'Dashboard Admin', icon: LayoutGrid },
      { id: 'kelola-users', label: 'Kelola Users', icon: Users },
      { id: 'kelola-tarif', label: 'Kelola Tarif', icon: Tag },
      { id: 'kelola-area', label: 'Kelola Area Parkir', icon: SquareParking },
      { id: 'kelola-kendaraan', label: 'Kelola Kendaraan', icon: CarFront },
      { id: 'log-aktivitas', label: 'Log Aktivitas', icon: History },
    ],
  },
  {
    id: 'petugas-group',
    title: 'OPERASIONAL',
    badge: 'Petugas',
    badgeClass: 'bg-[#D5EC96] text-[#50631D]',
    allowedRoles: ['petugas'],
    items: [
      { id: 'dashboard-petugas', label: 'Dashboard Petugas', icon: ClipboardList },
      { id: 'parkir-masuk', label: 'Parkir Masuk', icon: LogIn },
      { id: 'parkir-keluar', label: 'Parkir Keluar & Scan', icon: QrCode },
      { id: 'daftar-transaksi', label: 'Daftar Transaksi', icon: ReceiptText },
    ],
  },
  {
    id: 'owner-group',
    title: 'EXECUTIVE',
    badge: 'Owner',
    badgeClass: 'bg-[#F3E8E2] text-[#8C4A32]',
    allowedRoles: ['owner'],
    items: [
      { id: 'dashboard-owner', label: 'Dashboard Owner', icon: TrendingUp },
      { id: 'laporan-pendapatan', label: 'Laporan Pendapatan', icon: FileBarChart2 },
    ],
  },
]

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

const roleLabel = computed(() => {
  if (props.currentRole === 'petugas') return 'Petugas'
  if (props.currentRole === 'owner') return 'Owner'
  return 'Admin'
})

const isGroupLocked = (group) => !group.allowedRoles.includes(props.currentRole)

const handleItemClick = (group, item) => {
  if (isGroupLocked(group)) {
    lockedToast.value = `Menu "${item.label}" terkunci untuk role ${roleLabel.value}.`
    clearTimeout(toastTimer)
    toastTimer = setTimeout(() => {
      lockedToast.value = ''
    }, 2600)
    return
  }
  emit('select-menu', item.id)
  emit('close-mobile')
}
</script>

<template>
  <!-- Backdrop Mobile -->
  <Transition
    enter-active-class="transition-opacity duration-200"
    enter-from-class="opacity-0"
    leave-active-class="transition-opacity duration-200"
    leave-to-class="opacity-0"
  >
    <div
      v-if="mobileOpen"
      @click="emit('close-mobile')"
      class="fixed inset-0 z-40 bg-slate-900/35 backdrop-blur-xs lg:hidden"
    />
  </Transition>

  <aside
    :class="[
      'fixed inset-y-0 left-0 z-50 flex h-screen flex-col border-r border-[#E2E8F0] bg-[#FFFFFF] transition-all duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] lg:sticky lg:top-0 lg:translate-x-0',
      collapsed ? 'lg:w-[80px]' : 'lg:w-[272px]',
      mobileOpen ? 'w-[272px] translate-x-0' : 'w-[272px] -translate-x-full',
    ]"
  >
    <!-- Header Brand Bersih -->
    <div
      :class="[
        'flex shrink-0 items-center border-b border-[#E2E8F0] px-4 py-4',
        collapsed ? 'lg:justify-center lg:px-2' : 'justify-between gap-2',
      ]"
    >
      <div
        :class="[
          'flex items-center overflow-hidden',
          collapsed ? 'lg:justify-center' : 'gap-2.5',
        ]"
      >
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#D5EC96] text-olive">
          <Leaf :size="17" />
        </span>
        <div v-if="!collapsed" class="min-w-0">
          <p class="truncate text-[13px] font-extrabold tracking-tight text-[#0F172A]">
            Park<span class="text-olive">Flow</span>
          </p>
          <p class="truncate text-[9.5px] text-[#94A3B8]">Smart Parking</p>
        </div>
      </div>

      <!-- Close Mobile -->
      <button
        type="button"
        @click="emit('close-mobile')"
        class="rounded-xl p-1.5 text-[#64748B] hover:text-olive lg:hidden"
      >
        <X :size="16" />
      </button>
    </div>

    <!-- Toast saat klik menu terkunci -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div
        v-if="lockedToast && !collapsed"
        class="mx-4 mt-3 flex shrink-0 items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[10.5px] font-medium text-amber-800"
      >
        <Lock :size="12" class="shrink-0" />
        <span>{{ lockedToast }}</span>
      </div>
    </Transition>

    <!-- Menu List -->
    <nav class="no-scrollbar min-h-0 flex-1 space-y-6 overflow-y-auto px-4 py-4">
      <div v-for="group in menuGroups" :key="group.id">
        <div
          v-if="!collapsed"
          class="mb-2.5 flex items-center justify-between px-2"
        >
          <span class="text-[9px] font-bold tracking-wider text-[#94A3B8]">
            {{ group.title }}
          </span>
          <span
            :class="[
              'rounded-full px-2 py-0.5 text-[8.5px] font-bold',
              group.badgeClass,
            ]"
          >
            {{ group.badge }}
          </span>
        </div>
        <div v-else class="mb-2.5 border-t border-slate-100 pt-2 text-center">
          <span class="text-[8.5px] font-bold uppercase text-[#94A3B8]">
            {{ group.badge }}
          </span>
        </div>

        <ul class="space-y-2">
          <li v-for="item in group.items" :key="item.id">
            <button
              type="button"
              @click="handleItemClick(group, item)"
              :title="
                collapsed
                  ? `${item.label}${isGroupLocked(group) ? ' (Terkunci)' : ''}`
                  : undefined
              "
              :class="[
                'group relative flex w-full items-center rounded-2xl text-left text-[11px] transition-all duration-200',
                collapsed
                  ? 'justify-center px-2 py-3'
                  : 'gap-3.5 px-4 py-3',
                isGroupLocked(group)
                  ? 'cursor-not-allowed text-[#94A3B8] opacity-65 hover:bg-slate-50'
                  : activeMenu === item.id
                    ? 'bg-olive font-semibold text-white shadow-2xs'
                    : 'font-medium text-[#475569] hover:bg-[#F4F8EA] hover:text-olive',
              ]"
            >
              <component
                :is="item.icon"
                :size="15"
                :class="[
                  'shrink-0 transition-transform duration-200',
                  !isGroupLocked(group) && activeMenu !== item.id
                    ? 'group-hover:scale-105'
                    : '',
                ]"
              />

              <span v-if="!collapsed" class="flex-1 truncate leading-snug">
                {{ item.label }}
              </span>

              <span
                v-if="isGroupLocked(group) && !collapsed"
                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[#94A3B8]"
                title="Terkunci untuk role ini"
              >
                <Lock :size="10" />
              </span>

              <span
                v-if="isGroupLocked(group) && collapsed"
                class="absolute right-1.5 top-1.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-slate-100 text-[#94A3B8]"
              >
                <Lock :size="8.5" />
              </span>
            </button>
          </li>
        </ul>
      </div>
    </nav>

    <!-- Footer Sesi User -->
    <div class="shrink-0 border-t border-[#E2E8F0] p-4">
      <div
        :class="[
          'flex items-center rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-2.5',
          collapsed ? 'justify-center' : 'justify-between gap-2.5',
        ]"
      >
        <div class="flex min-w-0 items-center gap-2.5">
          <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#D5EC96] text-[10.5px] font-bold text-olive">
            {{ displayInitials }}
          </span>
          <div v-if="!collapsed" class="min-w-0">
            <p class="truncate text-[11px] font-bold text-[#0F172A]">{{ displayName }}</p>
            <p class="truncate text-[9.5px] text-olive">● {{ roleLabel }} Aktif</p>
          </div>
        </div>

        <button
          v-if="!collapsed"
          type="button"
          @click="emit('logout')"
          title="Keluar"
          class="shrink-0 rounded-xl p-1.5 text-[#64748B] transition-colors hover:bg-white hover:text-rose-600"
        >
          <LogOut :size="14" />
        </button>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>