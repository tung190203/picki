<template>
    <div
      class="unauth-layout min-h-screen flex items-center justify-center bg-cover bg-center relative"
      :style="{ backgroundImage: `url(${authBg})` }"
    >
        <div class="w-full max-w-md rounded">
      <router-view />
    </div>
    </div>
</template>
  
<script setup>
import { onMounted, onUnmounted } from 'vue'
import authBg from '@/assets/images/splash-bg.png'
import { initTheme } from '@/utils/theme.js'

onMounted(() => {
    document.documentElement.classList.remove('dark')
})

onUnmounted(() => {
    initTheme()
})
</script>
  
<style scoped>
.bg-cover {
    background-size: cover;
    background-position: center;
}
</style>