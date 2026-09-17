<template>
    <Transition name="slide-up">
        <div
            v-if="showBanner"
            class="fixed bottom-0 left-0 right-0 z-[100] bg-gradient-red shadow-lg"
        >
            <div class="flex items-center justify-between px-4 py-3 text-white">
                <!-- Icon + Text -->
                <div class="flex items-center flex-1 min-w-0">
                    <div class="flex-shrink-0 mr-3">
                        <svg
                            class="w-10 h-10"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M12 2L2 7V12C2 16.97 5.69 21.57 12 23C18.31 21.57 22 16.97 22 12V7L12 2Z"
                                fill="white"
                                opacity="0.9"
                            />
                            <path
                                d="M10 17L6 13L7.41 11.59L10 14.17L16.59 7.58L18 9L10 17Z"
                                fill="#b3111b"
                            />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm leading-tight">
                            Tải ứng dụng Picki
                        </p>
                        <p class="text-xs opacity-90 leading-tight mt-0.5">
                            Trải nghiệm tốt hơn trên app
                        </p>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex items-center space-x-2 ml-3 flex-shrink-0">
                    <button
                        @click="handleInstall"
                        class="bg-white text-primary font-semibold text-sm px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors whitespace-nowrap"
                    >
                        Cài đặt
                    </button>
                    <button
                        @click="handleDismiss"
                        class="text-white hover:bg-white/20 rounded-full p-1.5 transition-colors"
                        aria-label="Đóng"
                    >
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, onMounted } from 'vue'

// App Store Links
const APP_LINKS = {
    ios: 'https://apps.apple.com/vn/app/picki/id6755412423?l=vi',
    android: 'https://play.google.com/store/apps/details?id=com.picki.pickleball'
}

// Banner luôn hiển thị mặc định, chỉ ẩn khi user click đóng (trong session hiện tại)
const showBanner = ref(true)

/**
 * Xử lý khi user click "Cài đặt"
 */
const handleInstall = () => {
    const ua = navigator.userAgent || navigator.vendor || window.opera
    const isIOS = /iphone|ipad|ipod/i.test(ua.toLowerCase())
    const isAndroid = /android/i.test(ua.toLowerCase())

    let targetUrl = APP_LINKS.android // Default

    if (isIOS) {
        targetUrl = APP_LINKS.ios
    } else if (isAndroid) {
        targetUrl = APP_LINKS.android
    }

    // Mở link trong tab mới
    window.open(targetUrl, '_blank')

    // Tự động dismiss sau khi click (chỉ trong session hiện tại)
    handleDismiss()
}

/**
 * Xử lý khi user click "Đóng" - chỉ ẩn trong session hiện tại, không lưu localStorage
 */
const handleDismiss = () => {
    showBanner.value = false
}

/**
 * Banner luôn hiển thị mỗi lần load trang
 */
onMounted(() => {
    showBanner.value = true
})
</script>

<style scoped>
/* Transition animation cho banner (slide từ dưới lên) */
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.3s ease-out, opacity 0.3s ease-out;
}

.slide-up-enter-from {
    transform: translateY(100%);
    opacity: 0;
}

.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}

.slide-up-enter-to,
.slide-up-leave-from {
    transform: translateY(0);
    opacity: 1;
}
</style>
