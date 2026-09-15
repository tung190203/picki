<template>
  <div>
    <SplashScreen v-if="showSplash" />
    <template v-else>
      <router-view />
      <ScrollToTop />
      <ChatWidget v-if="showChatWidget" />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import ScrollToTop from './components/atoms/ScrollToTop.vue'
import SplashScreen from './components/atoms/SplashScreen.vue'
import ChatWidget from './components/chatbot/ChatWidget.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const route = useRoute()
const showSplash = ref(true)

const unauthRouteNames = [
  'login',
  'register',
  'onboarding',
  'complete-registration',
  'verify',
  'verify-email',
  'forgot-password',
  'reset-password',
  'verify-change-password',
  'login-success',
  'complete-profile',
  'update-profile',
]

const unauthPathPrefixes = [
  '/login',
  '/register',
  '/onboarding',
  '/complete-registration',
  '/verify',
  '/forgot-password',
  '/reset-password',
  '/complete-profile',
  '/update-profile',
]

const showChatWidget = computed(() => {
  const path = route.path || ''
  const name = route.name ? String(route.name) : ''

  // 1. Ẩn ở tất cả các màn Admin
  if (
    path.startsWith('/admin') ||
    route.meta?.requiresAdmin ||
    name.startsWith('admin.')
  ) {
    return false
  }

  // 2. Ẩn ở các màn unauth (login, register, forgot-password, onboarding,...)
  if (unauthRouteNames.includes(name)) {
    return false
  }

  if (unauthPathPrefixes.some((prefix) => path.startsWith(prefix))) {
    return false
  }

  // Kiểm tra nếu route dùng AuthLayout
  const usesAuthLayout = route.matched?.some(
    (record) =>
      record.components?.default === AuthLayout ||
      record.component === AuthLayout
  )
  if (usesAuthLayout) {
    return false
  }

  return true
})

onMounted(() => {
  setTimeout(() => {
    showSplash.value = false
  }, 1000)
});
</script>
