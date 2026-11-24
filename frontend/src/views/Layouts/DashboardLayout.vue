<script setup>
import { ref } from 'vue'
import Sidebar from '/src/components/Sidebar.vue'
import Topbar from '/src/components/Topbar.vue'

const props = defineProps({
  currentRole: { type: String, default: 'admin' },
  activeMenu: { type: String, default: 'dashboard-admin' },
  user: { type: Object, default: null },
})

const emit = defineEmits(['update:activeMenu', 'logout'])

const sidebarCollapsed = ref(false)
const mobileSidebarOpen = ref(false)
</script>

<template>
  <div class="flex min-h-screen gap-x-6 bg-[#F8FAFC] text-[#0F172A] lg:gap-x-8">
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

      <main class="flex-1 px-6 py-7 lg:px-10 lg:py-9">
        <div class="mx-auto max-w-[1320px]">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>