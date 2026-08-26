import { ref } from 'vue'
import * as AuthService from '@/service/auth.js'

const THEME_KEY = 'vpick_theme_mode'

// Theme mode options: 'light' | 'dark' | 'system' - Default is strictly 'light'
export const themeMode = ref(localStorage.getItem(THEME_KEY) || 'light')
export const isDark = ref(false)

export const applyTheme = () => {
  // Super Admin area (/admin/*) retains its dedicated default theme design system
  if (window.location.pathname.startsWith('/admin')) {
    isDark.value = false
    document.documentElement.classList.remove('dark')
    return
  }

  const mode = themeMode.value
  let shouldBeDark = false

  if (mode === 'dark') {
    shouldBeDark = true
  } else if (mode === 'system') {
    // 'system' mode: check OS preference
    shouldBeDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
  } else {
    // Default mode 'light': strictly false
    shouldBeDark = false
  }

  isDark.value = shouldBeDark

  if (shouldBeDark) {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}

export const setThemeMode = (mode, syncToDatabase = true) => {
  themeMode.value = mode
  localStorage.setItem(THEME_KEY, mode)
  applyTheme()

  if (syncToDatabase) {
    AuthService.updateUserSettings({ theme_mode: mode }).catch(err => {
      console.warn('Sync theme to DB warning:', err)
    })
  }
}

export const syncUserThemeFromDatabase = (userThemeMode) => {
  if (userThemeMode && ['light', 'dark', 'system'].includes(userThemeMode)) {
    setThemeMode(userThemeMode, false)
  }
}

export const initTheme = () => {
  applyTheme()

  if (window.matchMedia) {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    const handler = () => {
      if (themeMode.value === 'system') {
        applyTheme()
      }
    }
    if (mediaQuery.addEventListener) {
      mediaQuery.addEventListener('change', handler)
    } else if (mediaQuery.addListener) {
      mediaQuery.addListener(handler)
    }
  }
}
