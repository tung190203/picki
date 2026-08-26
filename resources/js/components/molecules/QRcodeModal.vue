<template>
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4"
        @click.self="$emit('close')">
        <!-- Modal content with animation -->
        <div
            class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-2xl shadow-2xl p-8 w-full max-w-md relative transform transition-all">
            <!-- Top gradient overlay - THÊM pointer-events-none -->
            <div
                class="absolute top-0 left-0 right-0 h-32 bg-gradient-to-br from-[#db2627] via-[#e63946] to-[#ff6b6b] rounded-t-2xl opacity-20 pointer-events-none">
            </div>

            <!-- Close button - TĂNG z-index lên z-20 -->
            <button 
                @click="$emit('close')"
                class="absolute top-4 right-4 flex items-center justify-center text-gray-400 hover:text-white hover:bg-[#db2627] rounded-full p-2 transition-all duration-200 z-20"
            >
                <XMarkIcon class="h-6 w-6" />
            </button>

            <!-- Header - GIỮ z-10 -->
            <div class="relative z-10 mb-12">
                <div class="flex justify-center mb-3">
                </div>
                <h2
                    class="text-2xl font-bold text-center bg-gradient-to-r from-[#db2627] via-[#e63946] to-[#ff6b6b] bg-clip-text text-transparent">
                    Scan QR Code
                </h2>
                <p class="text-center text-gray-500 dark:text-slate-400 text-sm mt-2">
                    Quét mã để truy cập nhanh
                </p>
            </div>

            <!-- QR Code Container -->
            <div class="flex justify-center mb-6">
                <div
                    class="bg-white p-6 rounded-2xl shadow-lg border-4 border-gray-100 dark:border-slate-700 hover:border-[#db2627]/30 transition-all duration-300 hover:shadow-xl">
                    <qrcode-vue :value="value" :size="220" level="H" />
                </div>
            </div>

            <!-- URL Display -->
            <div class="bg-gray-100 dark:bg-[#1E293B] rounded-xl p-3 mb-4 border border-gray-200 dark:border-slate-700">
                <p class="text-xs text-gray-600 dark:text-slate-300 text-center truncate font-mono">
                    {{ value }}
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <button @click="copyToClipboard"
                    class="w-full px-6 py-3 bg-gradient-to-r from-[#db2627] via-[#e63946] to-[#ff6b6b] text-white rounded-xl font-semibold hover:from-[#e63946] hover:to-[#ff6b6b] transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex items-center justify-center gap-2"
                    :class="{ 'bg-green-500': copied }">
                    <svg v-if="!copied" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ copied ? 'Đã sao chép!' : 'Sao chép liên kết' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { XMarkIcon } from '@heroicons/vue/24/solid'
import QrcodeVue from 'qrcode.vue'
import { defineProps, ref } from 'vue'

const props = defineProps({
    value: {
        type: String,
        required: true
    }
})

const copied = ref(false)

function copyToClipboard() {
    navigator.clipboard.writeText(props.value)
        .then(() => {
            copied.value = true
            setTimeout(() => {
                copied.value = false
            }, 2000)
        })
        .catch(err => console.error(err))
}
</script>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

div[class*="bg-gradient-to-br from-white"] {
    animation: fadeIn 0.3s ease-out;
}
</style>