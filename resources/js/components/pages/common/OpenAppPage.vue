<template>
    <div class="min-h-screen flex flex-col items-center justify-center p-6 bg-gray-50">
        <div class="w-full max-w-sm mx-auto text-center">
            <!-- Brand logo -->
            <div class="mb-8 animate-slideInUp">
                <div class="w-20 h-20 bg-[#D72D36] rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <span class="text-white text-3xl font-bold">P</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Mở ứng dụng Picki</h1>
                <p class="text-gray-500 mt-2 text-sm">
                    {{ contentTitle || 'Đang chuyển hướng đến ứng dụng Picki...' }}
                </p>
            </div>

            <!-- Loading state -->
            <div v-if="isLoading" class="mb-8">
                <div class="w-12 h-12 border-4 border-gray-200 border-t-[#D72D36] rounded-full animate-spin mx-auto"></div>
                <p class="text-gray-400 text-sm mt-4">Đang xử lý...</p>
            </div>

            <!-- App opened state -->
            <div v-else-if="appOpened" class="mb-8 animate-slideInUp">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <CheckCircleIcon class="w-8 h-8 text-green-600" />
                </div>
                <p class="text-green-700 font-medium">Đã mở ứng dụng Picki!</p>
                <p class="text-gray-400 text-sm mt-1">Nếu app không mở, hãy thử các tùy chọn bên dưới.</p>
            </div>

            <!-- Primary: Open App button -->
            <div v-if="!appOpened" class="space-y-3 mb-6 animate-slideInUp" style="animation-delay: 0.1s;">
                <button
                    @click="openApp"
                    class="w-full flex items-center justify-center gap-3 px-6 py-3.5 bg-[#D72D36] text-white font-semibold rounded-xl shadow-md hover:bg-[#c0252e] focus:outline-none focus:ring-2 focus:ring-[#D72D36] focus:ring-offset-2 transition-colors"
                >
                    <span class="text-xl">P</span>
                    <span>Mở ứng dụng Picki</span>
                </button>

                <!-- Platform-specific badge -->
                <div class="flex items-center justify-center gap-2">
                    <span v-if="isIOS" class="inline-flex items-center gap-1 text-xs text-gray-500">
                        <DevicePhoneMobileIcon class="w-4 h-4" />
                        iOS Universal Link
                    </span>
                    <span v-else-if="isAndroid" class="inline-flex items-center gap-1 text-xs text-gray-500">
                        <DevicePhoneMobileIcon class="w-4 h-4" />
                        Android App Link
                    </span>
                    <span v-else class="inline-flex items-center gap-1 text-xs text-gray-500">
                        <DevicePhoneMobileIcon class="w-4 h-4" />
                        Deep Link
                    </span>
                </div>
            </div>

            <!-- Fallback actions -->
            <div class="space-y-2 animate-slideInUp" style="animation-delay: 0.2s;">
                <!-- App Store / Play Store -->
                <a
                    v-if="!appOpened"
                    :href="storeUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center justify-center gap-2 w-full px-6 py-3 bg-gray-100 text-gray-800 font-medium rounded-xl hover:bg-gray-200 transition-colors"
                >
                    <ArrowDownTrayIcon class="w-5 h-5" />
                    <span>Tải app trên {{ storeName }}</span>
                </a>

                <!-- Web fallback -->
                <a
                    :href="webFallbackUrl"
                    class="flex items-center justify-center gap-2 w-full px-6 py-3 text-gray-500 font-medium rounded-xl hover:text-gray-700 hover:bg-gray-100 transition-colors"
                >
                    <GlobeAltIcon class="w-5 h-5" />
                    <span>Xem trên web</span>
                    <ArrowRightIcon class="w-4 h-4" />
                </a>
            </div>

            <!-- Note for Zalo users -->
            <div v-if="isInZalo" class="mt-8 p-4 bg-blue-50 rounded-xl text-left animate-slideInUp" style="animation-delay: 0.3s;">
                <div class="flex items-start gap-2">
                    <InformationCircleIcon class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium text-blue-800">Lưu ý khi dùng Zalo</p>
                        <p class="text-xs text-blue-600 mt-1">
                            Nếu app không mở tự động, hãy nhấn nút <strong>"Mở ứng dụng Picki"</strong> bên trên.
                            Nếu vẫn không được, hãy sao chép link và mở trong trình duyệt (Chrome/Safari).
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
    CheckCircleIcon,
    ArrowDownTrayIcon,
    ArrowRightIcon,
    GlobeAltIcon,
    InformationCircleIcon,
    DevicePhoneMobileIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const appOpened = ref(false)
const isLoading = ref(true)

const type = computed(() => route.params.type)
const id = computed(() => route.params.id)

// Platform detection
const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent)
const isAndroid = /Android/i.test(navigator.userAgent)
const isInZalo = /Zalo/i.test(navigator.userAgent)

// Deep link mapping - maps short type to internal paths
const typeConfig = {
    tournament: {
        universalPath: '/tournament-detail',
        webPath: `/tournament-landing/${id.value}`,
        label: 'Giải đấu',
    },
    mini_tournament: {
        universalPath: '/mini-tournament-detail',
        webPath: `/mini-tournament/${id.value}`,
        label: 'Kèo đấu',
    },
    club: {
        universalPath: '/clubs',
        webPath: `/clubs/${id.value}`,
        label: 'Câu lạc bộ',
    },
    profile: {
        universalPath: '/profile',
        webPath: `/profile/${id.value}`,
        label: 'Hồ sơ',
    },
}

const config = computed(() => typeConfig[type.value] || typeConfig.tournament)
const contentTitle = computed(() => config.value.label ? `Đang mở ${config.value.label}...` : null)

const storeName = computed(() => isAndroid ? 'Google Play' : 'App Store')

const storeUrl = computed(() => {
    if (isAndroid) {
        return 'https://play.google.com/store/apps/details?id=com.picki.pickleball'
    }
    // iOS App Store - replace with actual app ID
    return 'https://apps.apple.com/app/picki/idREPLACE_WITH_APP_ID'
})

const webFallbackUrl = computed(() => {
    const base = window.location.origin
    return base + config.value.webPath
})

// Universal Link URL (HTTPS) - triggers OS popup to open app
const universalLinkUrl = computed(() => {
    const base = window.location.origin
    return `${base}${config.value.universalPath}/${id.value}`
})

function openApp() {
    // Set location to Universal Link URL
    // On iOS/Android with Universal/App Link configured, this triggers OS popup
    window.location.href = universalLinkUrl.value

    // After 2 seconds, check if page is still visible (app didn't open)
    setTimeout(() => {
        if (!document.hidden) {
            appOpened.value = true
            isLoading.value = false
        }
    }, 2000)
}

onMounted(() => {
    // Auto-attempt to open app on page load
    openApp()

    // Stop loading indicator after max 3 seconds
    setTimeout(() => {
        isLoading.value = false
    }, 3000)
})
</script>
