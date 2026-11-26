<!-- src/layouts/DashboardLayout.vue -->
<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from '/src/components/Sidebar.vue'
import Topbar from '/src/components/Topbar.vue'

const SIDEBAR_COLLAPSED_KEY = 'parkflow_sidebar_collapsed'

const props = defineProps({
  currentRole: { type: String, default: 'admin' },
  activeMenu: { type: String, default: 'dashboard-admin' },
  user: { type: Object, default: null },
})

const emit = defineEmits(['update:activeMenu', 'logout'])

const route = useRoute()
const sidebarCollapsed = ref(localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === 'true')
const mobileSidebarOpen = ref(false)

watch(sidebarCollapsed, (val) => {
  localStorage.setItem(SIDEBAR_COLLAPSED_KEY, String(val))
})

watch(
  () => route.path,
  () => {
    mobileSidebarOpen.value = false
  }
)

onMounted(() => {
  sidebarCollapsed.value = localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === 'true'
})
</script>

<template>
  <div class="flex min-h-screen w-full bg-[#F8FAFC] text-[#0F172A]">
    <Sidebar
      :collapsed="sidebarCollapsed"
      :mobile-open="mobileSidebarOpen"
      :current-role="props.currentRole"
      :active-menu="props.activeMenu"
      :user="props.user"
      @close-mobile="mobileSidebarOpen = false"
      @select-menu="(id) => emit('update:activeMenu', id)"
      @logout="emit('logout')"
    />

    <div class="flex min-w-0 flex-1 flex-col">
      <Topbar
        :collapsed="sidebarCollapsed"
        :current-role="props.currentRole"
        :user="props.user"
        @open-mobile="mobileSidebarOpen = true"
        @toggle-collapse="sidebarCollapsed = !sidebarCollapsed"
        @logout="emit('logout')"
      />

      <main class="min-w-0 flex-1 px-6 py-6 lg:px-8 lg:py-8">
        <div class="mx-auto w-full max-w-[1320px]">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>