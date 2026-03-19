<template>
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ title }}
                </h3>
                <p class="text-sm text-gray-600 mb-6">
                    {{ message }}
                </p>

                <div class="space-y-3">
                    <button
                        @click="handleCancelSingle"
                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-900 rounded-lg transition"
                    >
                        <span class="font-medium">Chỉ hủy kèo này</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <button
                        @click="handleCancelSeries"
                        class="w-full flex items-center justify-between px-4 py-3 bg-red-100 hover:bg-red-200 text-red-900 rounded-lg transition"
                    >
                        <span class="font-medium">Hủy toàn bộ chuỗi lặp lại</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>

                <button
                    @click="close"
                    class="w-full mt-4 px-4 py-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded-lg transition"
                >
                    Đóng
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CancelRecurrenceModal',
    props: {
        modelValue: {
            type: Boolean,
            default: false
        },
        title: {
            type: String,
            default: 'Hủy kèo đấu'
        },
        message: {
            type: String,
            default: 'Kèo này thuộc chuỗi lặp lại. Bạn muốn hủy như thế nào?'
        }
    },
    emits: ['update:modelValue', 'cancelSingle', 'cancelSeries'],
    methods: {
        close() {
            this.$emit('update:modelValue', false)
        },
        handleCancelSingle() {
            this.$emit('cancelSingle')
            this.close()
        },
        handleCancelSeries() {
            this.$emit('cancelSeries')
            this.close()
        }
    }
}
</script>
