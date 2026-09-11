<template>
  <div>
    <SplashScreen v-if="showSplash" />
    <template v-else>
      <router-view />
      <ScrollToTop />
      <ChatWidget />
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import ScrollToTop from './components/atoms/ScrollToTop.vue'
import SplashScreen from './components/atoms/SplashScreen.vue'
import ChatWidget from './components/chatbot/ChatWidget.vue'

const showSplash = ref(true)

onMounted(() => {
  setTimeout(() => {
    showSplash.value = false
  }, 1000)
});
</script>
