<template>
  <aside class="hidden md:flex flex-col h-screen w-64 docked left-0 bg-surface-container-low py-6 px-4 gap-2 fixed z-50 border-r border-outline-variant/5">
    <!-- Logo Section -->
    <div class="px-2 mb-8">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 flex items-center justify-center">
          <LogoUrl class="w-10 h-10" />
        </div>
        <div>
          <LogoExplainUrl class="h-6" />
        </div>
      </div>
    </div>
    
    <!-- Navigation Links -->
    <nav class="flex-1 flex flex-col gap-1">
      <router-link 
        v-for="item in navItems" 
        :key="item.routeName"
        :to="{ name: item.routeName }"
        class="nav-item"
        :class="{ 'active': isRouteActive(item.routeName) }"
      >
        <span class="material-symbols-outlined" :class="{ 'icon-fill': isRouteActive(item.routeName) }">
          {{ item.icon }}
        </span>
        <span>{{ item.label }}</span>
      </router-link>
      
      <!-- Static / Coming Soon Links -->
      <a class="nav-item">
        <span class="material-symbols-outlined">podcasts</span>
        <span>Broadcast</span>
      </a>
    </nav>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import LogoUrl from '@/assets/images/logo.svg'
import LogoExplainUrl from '@/assets/images/logo-explain.svg'

const route = useRoute()

const navItems = [
  { label: 'Tổng quan', routeName: 'admin.dashboard', icon: 'dashboard' },
  { label: 'Quản lý', routeName: 'admin.moderation', icon: 'gavel' },
  { label: 'Cấu hình', routeName: 'admin.config', icon: 'settings' }
]

const isRouteActive = (routeName) => {
  return route.name === routeName
}
</script>

<style scoped>
.nav-item {
  @apply flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-[#af101a] hover:bg-surface-container-high transition-all duration-200 active:translate-x-1 cursor-pointer rounded-xl font-manrope font-semibold text-sm;
}

.nav-item.active {
  @apply text-white bg-[#af101a] shadow-lg shadow-red-900/10;
}

.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}

.icon-fill {
  font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
</style>
