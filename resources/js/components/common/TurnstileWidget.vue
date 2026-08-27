<template>
  <div v-if="siteKey" class="turnstile-wrapper my-3 w-full flex justify-center">
    <div ref="container" class="turnstile-container w-full flex justify-center"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  siteKey: {
    type: String,
    default: () => import.meta.env.VITE_TURNSTILE_SITE_KEY || ''
  },
  theme: {
    type: String,
    default: 'auto'
  },
  size: {
    type: String,
    default: 'flexible' // 'normal', 'compact', 'flexible'
  }
})

const emit = defineEmits(['verify', 'expire', 'error'])

const container = ref(null)
let widgetId = null

const loadScript = () => {
  return new Promise((resolve, reject) => {
    if (window.turnstile) {
      resolve(window.turnstile)
      return
    }

    const existingScript = document.getElementById('cf-turnstile-script')
    if (existingScript) {
      existingScript.addEventListener('load', () => resolve(window.turnstile))
      return
    }

    const script = document.createElement('script')
    script.id = 'cf-turnstile-script'
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    script.async = true
    script.defer = true
    script.onload = () => resolve(window.turnstile)
    script.onerror = (err) => reject(err)
    document.head.appendChild(script)
  })
}

const renderWidget = async () => {
  if (!props.siteKey || !container.value) return

  try {
    const turnstile = await loadScript()
    if (widgetId !== null && turnstile.remove) {
      turnstile.remove(widgetId)
    }

    widgetId = turnstile.render(container.value, {
      sitekey: props.siteKey,
      theme: props.theme,
      size: props.size,
      callback: (token) => {
        emit('verify', token)
      },
      'expired-callback': () => {
        emit('expire')
      },
      'error-callback': (err) => {
        emit('error', err)
      }
    })
  } catch (err) {
    console.error('Turnstile widget load error:', err)
  }
}

onMounted(() => {
  renderWidget()
})

onBeforeUnmount(() => {
  if (widgetId !== null && window.turnstile && window.turnstile.remove) {
    window.turnstile.remove(widgetId)
  }
})
</script>

<style scoped>
.turnstile-container :deep(iframe) {
  width: 100% !important;
  min-width: 100% !important;
  max-width: 100% !important;
}
</style>
