<template>
    <button
      v-if="showButton"
      @click="scrollToTop"
      class="fixed z-[9990] w-12 h-12 rounded-full bg-black/80 hover:bg-black text-white shadow-lg backdrop-blur-sm hover:scale-105 active:scale-95 transition duration-300 flex items-center justify-center"
      style="bottom: 132px; right: 12px;"
      aria-label="Scroll to top"
    >
      <!-- SVG Arrow Up -->
      <ArrowUpIcon class="w-6 h-6" />
    </button>
  </template>
  
  <script setup>
  import { ref, onMounted, onUnmounted } from 'vue'
  import { ArrowUpIcon } from '@heroicons/vue/24/solid'
  
  const showButton = ref(false)
  
  const handleScroll = () => {
    showButton.value = window.scrollY > 300
  }
  
  const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
  
  onMounted(() => {
    window.addEventListener('scroll', handleScroll)
  })
  
  onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll)
  })
  </script>
  