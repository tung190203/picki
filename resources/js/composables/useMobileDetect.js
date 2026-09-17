// composables/useMobileDetect.js
import { ref, onMounted, onBeforeUnmount } from 'vue'

/**
 * Composable để phát hiện mobile device và mobile browser
 * @returns {Object} { isMobileDevice, isMobileBrowser }
 */
export function useMobileDetect() {
  const isMobileDevice = ref(false)
  const isMobileBrowser = ref(false)
  
  /**
   * Kiểm tra user agent và display mode
   */
  const checkMobile = () => {
    const ua = navigator.userAgent || navigator.vendor || window.opera
    
    // Phát hiện mobile device qua user agent
    const hasMobileUA = /android|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(ua.toLowerCase())
    
    // THÊM: Kiểm tra screen width để hỗ trợ Chrome DevTools emulator
    // Nếu width <= 1024px, coi như mobile (giống breakpoint lg của Tailwind)
    const hasSmallScreen = window.innerWidth <= 1024
    
    isMobileDevice.value = hasMobileUA || hasSmallScreen
    
    // Kiểm tra xem có phải đang dùng browser không (không phải native app/PWA)
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
    const isInWebAppiOS = window.navigator.standalone === true
    
    // Chỉ hiện banner khi: mobile device + đang dùng browser (không phải PWA/native app)
    isMobileBrowser.value = isMobileDevice.value && !isStandalone && !isInWebAppiOS
    
    // Debug log (có thể xóa sau khi test xong)
    console.log('[useMobileDetect]', {
      ua: ua.substring(0, 50) + '...',
      hasMobileUA,
      hasSmallScreen,
      windowWidth: window.innerWidth,
      isMobileDevice: isMobileDevice.value,
      isMobileBrowser: isMobileBrowser.value,
      isStandalone,
      isInWebAppiOS
    })
  }
  
  // Lắng nghe sự kiện resize để re-check khi thay đổi viewport
  const handleResize = () => {
    checkMobile()
  }
  
  onMounted(() => {
    checkMobile()
    window.addEventListener('resize', handleResize)
  })
  
  onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize)
  })
  
  return {
    isMobileDevice,
    isMobileBrowser
  }
}
