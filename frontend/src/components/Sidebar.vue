<script setup>
import {
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
  Car,
} from 'lucide-vue-next'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const SIDEBAR_SCROLL_KEY = 'parkflow_sidebar_scroll_top'

const props = defineProps({
  collapsed: { type: Boolean, default: false },
  mobileOpen: { type: Boolean, default: false },
  currentRole: { type: String, default: 'admin' },
  activeMenu: { type: String, default: '' },
  user: { type: Object, default: null },
})

const emit = defineEmits(['close-mobile', 'select-menu', 'logout'])

const route = useRoute()
const router = useRouter()

const navRef = ref(null)
const lockedToast = ref('')
let toastTimer = null

const normalizedRole = computed(() =>
  String(props.currentRole || props.user?.role || 'admin').trim().toLowerCase()
)

const menuGroups = [
  {
    id: 'admin-group',
    title: 'ADMIN WORKSPACE',
    badge: 'Admin',
    badgeClass: 'bg-[#161F00] text-white',
    allowedRoles: ['admin'],
    items: [
      { id: 'dashboard-admin', label: 'Dashboard Admin', to: '/admin/dashboard', icon: LayoutGrid },
      { id: 'kelola-users', label: 'Kelola Users', to: '/admin/users', icon: Users },
      { id: 'kelola-tarif', label: 'Kelola Tarif', to: '/admin/tarif', icon: Tag },
      { id: 'kelola-area', label: 'Kelola Area Parkir', to: '/admin/area-parkir', icon: SquareParking },
      { id: 'kelola-kendaraan', label: 'Kelola Kendaraan', to: '/admin/kendaraan', icon: CarFront },
      { id: 'log-aktivitas', label: 'Log Aktivitas', to: '/admin/log-aktivitas', icon: History },
    ],
  },
  {
    id: 'petugas-group',
    title: 'OPERASIONAL',
    badge: 'Petugas',
    badgeClass: 'bg-[#D5EC96] text-[#50631D]',
    allowedRoles: ['petugas'],
    items: [
      { id: 'dashboard-petugas', label: 'Dashboard Petugas', to: '/petugas/dashboard', icon: ClipboardList },
      { id: 'area-parkir-petugas', label: 'Area Parkir Petugas', to: '/petugas/area-parkir', icon: SquareParking },
      { id: 'parkir-masuk', label: 'Parkir Masuk', to: '/petugas/parkir-masuk', icon: LogIn },
      { id: 'parkir-keluar', label: 'Parkir Keluar & Scan', to: '/petugas/parkir-keluar', icon: QrCode },
      { id: 'daftar-transaksi', label: 'Daftar Transaksi', to: '/petugas/transaksi', icon: ReceiptText },
    ],
  },
  {
    id: 'owner-group',
    title: 'EXECUTIVE',
    badge: 'Owner',
    badgeClass: 'bg-[#F3E8E2] text-[#8C4A32]',
    allowedRoles: ['owner'],
    items: [
      { id: 'dashboard-owner', label: 'Dashboard Owner', to: '/owner/dashboard', icon: TrendingUp },
      { id: 'laporan-pendapatan', label: 'Laporan Pendapatan', to: '/owner/laporan-pendapatan', icon: FileBarChart2 },
    ],
  },
]

const displayName = computed(
  () =>
    props.user?.nama_lengkap ||
    props.user?.nama ||
    props.user?.name ||
    props.user?.username ||
    (normalizedRole.value === 'petugas'
      ? 'Petugas Operasional'
      : normalizedRole.value === 'owner'
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
  if (normalizedRole.value === 'petugas') return 'Petugas'
  if (normalizedRole.value === 'owner') return 'Owner'
  return 'Admin'
})

const isGroupLocked = (group) => !group.allowedRoles.includes(normalizedRole.value)

const isItemActive = (item) => {
  if (props.activeMenu && props.activeMenu === item.id) return true
  return route.path === item.to || route.path.startsWith(`${item.to}/`)
}

const saveSidebarScroll = () => {
  if (!navRef.value) return
  localStorage.setItem(SIDEBAR_SCROLL_KEY, String(navRef.value.scrollTop))
}

const restoreSidebarScroll = async () => {
  await nextTick()
  if (!navRef.value) return
  const savedTop = Number(localStorage.getItem(SIDEBAR_SCROLL_KEY) || 0)
  if (!Number.isNaN(savedTop)) {
    navRef.value.scrollTop = savedTop
  }
}

const handleLockedClick = (item) => {
  lockedToast.value = `Menu "${item.label}" terkunci untuk role ${roleLabel.value}.`
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    lockedToast.value = ''
  }, 2600)
}

const handleNavigate = (item) => {
  saveSidebarScroll()
  emit('select-menu', item.id)
  emit('close-mobile')
  if (route.path !== item.to) {
    router.push(item.to)
  }
}

watch(
  () => [route.path, props.collapsed],
  () => {
    restoreSidebarScroll()
  }
)

onMounted(() => {
  restoreSidebarScroll()
})
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
    <!-- Header Brand -->
    <div
      :class="[
        'flex h-[65px] shrink-0 items-center border-b border-[#E2E8F0] px-4',
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
          <Car :size="24" />
        </span>
        <div v-if="!collapsed" class="min-w-0">
          <p class="truncate text-[13px] font-extrabold tracking-tight text-[#0F172A]">
            Park<span class="text-olive">Flow</span>
          </p>
          <p class="truncate text-[9.5px] text-[#94A3B8]">Smart Parking</p>
        </div>
      </div>

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
    <nav
      ref="navRef"
      @scroll="saveSidebarScroll"
      class="no-scrollbar min-h-0 flex-1 space-y-6 overflow-y-auto px-3.5 py-4"
    >
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

        <ul class="space-y-1.5">
          <li v-for="item in group.items" :key="item.id">
            <!-- Jika terkunci: button dengan toast peringatan -->
            <button
              v-if="isGroupLocked(group)"
              type="button"
              @click="handleLockedClick(item)"
              :title="collapsed ? `${item.label} (Terkunci)` : undefined"
              :class="[
                'group relative flex w-full cursor-not-allowed items-center rounded-2xl text-left text-[11px] text-[#94A3B8] opacity-60 transition-all duration-200 hover:bg-slate-50',
                collapsed ? 'justify-center px-2 py-3' : 'gap-3.5 px-4 py-3',
              ]"
            >
              <component :is="item.icon" :size="15" class="shrink-0 text-[#94A3B8]" />
              <span v-if="!collapsed" class="flex-1 truncate leading-snug">
                {{ item.label }}
              </span>
              <span
                v-if="!collapsed"
                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[#94A3B8]"
              >
                <Lock :size="10" />
              </span>
              <span
                v-else
                class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[#94A3B8]"
              >
                <Lock :size="8.5" />
              </span>
            </button>

            <!-- Jika aktif/unlocked: RouterLink langsung ke path halaman -->
            <RouterLink
              v-else
              :to="item.to"
              @click="handleNavigate(item)"
              :title="collapsed ? item.label : undefined"
              :class="[
                'group relative flex w-full cursor-pointer items-center rounded-2xl text-left text-[11px] transition-all duration-200',
                collapsed ? 'justify-center px-2 py-3' : 'gap-3.5 px-4 py-3',
                isItemActive(item)
                  ? 'bg-olive font-semibold text-white shadow-2xs'
                  : 'font-medium text-[#475569] hover:bg-[#F4F8EA] hover:text-olive',
              ]"
            >
              <component
                :is="item.icon"
                :size="15"
                :class="[
                  'shrink-0 transition-transform duration-200',
                  isItemActive(item)
                    ? 'text-white'
                    : 'text-[#64748B] group-hover:scale-105 group-hover:text-olive',
                ]"
              />
              <span v-if="!collapsed" class="flex-1 truncate leading-snug">
                {{ item.label }}
              </span>
            </RouterLink>
          </li>
        </ul>
      </div>
    </nav>

    <!-- Footer Sesi User -->
    <div class="shrink-0 border-t border-[#E2E8F0] p-3.5">
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
