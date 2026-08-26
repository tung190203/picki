<template>
    <Transition name="fade">
        <div v-if="isOpen" 
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="close">
            <Transition name="scale">
                <div v-if="isOpen" 
                    class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-[24px] w-full max-w-[500px] max-h-[90vh] transition-all duration-300 flex flex-col p-6 relative shadow-2xl overflow-hidden">
                    <!-- Modal Close -->
                    <button 
                        @click="close"
                        class="absolute right-6 top-6 text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-white transition-colors z-10">
                        <XMarkIcon class="w-7 h-7" />
                    </button>

                    <!-- Modal Header -->
                    <div class="mb-8 flex-shrink-0">
                        <h2 class="text-[20px] font-bold text-[#1F2937] dark:text-slate-100">Tạo Khoản Chi</h2>
                    </div>

                    <div class="flex flex-col space-y-6 min-h-0 flex-1 overflow-y-auto overflow-x-hidden custom-scrollbar pt-2">
                        <!-- Amount Section -->
                        <div class="flex flex-col items-center">
                            <label class="block text-[11px] font-bold text-[#838799] dark:text-slate-400 uppercase tracking-wider mb-4">SỐ TIỀN CHI</label>
                            <div class="flex items-center justify-center space-x-1 border-b-2 rounded-[4px] border-[#E36C72] pb-2 px-8 w-fit mx-auto">
                                <input 
                                    type="text" 
                                    :value="expenseAmount"
                                    @input="onAmountInput"
                                    placeholder="0"
                                    class="text-[36px] md:text-[48px] font-bold text-[#2D3139] dark:text-slate-100 bg-transparent border-none p-0 focus:ring-0 focus:outline-none w-40 md:w-60 text-center"
                                />
                                <span class="text-[20px] md:text-[24px] font-bold text-[#838799] dark:text-slate-400">đ</span>
                            </div>
                        </div>

                        <!-- Form Fields -->
                        <div class="space-y-6">
                            <!-- Title -->
                            <div class="space-y-2">
                                <label class="block text-[11px] font-bold text-[#838799] dark:text-slate-400 uppercase tracking-wider ml-1">TIÊU ĐỀ KHOẢN CHI</label>
                                <div class="bg-[#F9FAFB] dark:bg-slate-800 border border-[#F2F3F5] dark:border-slate-700 rounded-xl p-4">
                                    <input 
                                        type="text" 
                                        v-model="expenseDescription"
                                        class="w-full font-semibold text-[#3E414C] dark:text-slate-100 bg-transparent border-none p-0 focus:ring-0 focus:outline-none placeholder:text-gray-300 dark:placeholder:text-slate-500"
                                        placeholder="VD: Mua bóng mới"
                                    />
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div class="space-y-3">
                                <label class="block text-[11px] font-bold text-[#838799] dark:text-slate-400 uppercase tracking-wider ml-1">PHƯƠNG THỨC THANH TOÁN</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <button 
                                        v-for="method in paymentMethods" 
                                        :key="method.value"
                                        @click="expensePaymentMethod = method.value"
                                        :class="[
                                            'py-3 px-4 rounded-xl text-[13px] font-bold transition-all border flex items-center justify-center space-x-2',
                                            expensePaymentMethod === method.value 
                                                ? 'bg-[#2D3139] dark:bg-slate-700 text-white border-[#2D3139] dark:border-slate-600 shadow-md' 
                                                : 'bg-[#F9FAFB] dark:bg-slate-800 text-[#3E414C] dark:text-slate-300 border-[#F2F3F5] dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600'
                                        ]"
                                    >
                                        <component :is="method.icon" class="w-4 h-4 shrink-0" />
                                        <span class="truncate">{{ method.label }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Note -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between ml-1">
                                    <label class="block text-[11px] font-bold text-[#838799] dark:text-slate-400 uppercase tracking-wider">GHI CHÚ</label>
                                    <span class="text-[10px] text-[#9EA2B3] dark:text-slate-400 font-normal uppercase">{{ expenseNote.length }}/300</span>
                                </div>
                                <div class="bg-[#F9FAFB] dark:bg-slate-800 border border-[#F2F3F5] dark:border-slate-700 rounded-xl p-4">
                                    <textarea 
                                        v-model="expenseNote"
                                        rows="3"
                                        placeholder="Nhập nội dung chi tiết..."
                                        class="w-full bg-transparent border-none p-0 text-sm font-medium text-[#3E414C] dark:text-slate-100 focus:ring-0 focus:outline-none placeholder:text-gray-300 dark:placeholder:text-slate-500 resize-none"
                                        maxlength="300"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="pt-6 border-t border-[#F2F3F5] dark:border-slate-800 mt-auto">
                        <button 
                            @click="submitCreateExpense"
                            :disabled="isSubmitting"
                            class="w-full bg-[#E36C72] text-white py-4 rounded-[4px] font-bold text-lg hover:bg-[#d05a60] transition-all shadow-lg active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
                        >
                            <template v-if="isSubmitting">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Đang xử lý...
                            </template>
                            <template v-else>
                                Tạo khoản chi
                            </template>
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<script setup>
import { ref } from 'vue'
import {
    XMarkIcon,
    BanknotesIcon,
    ArrowsRightLeftIcon,
    QrCodeIcon,
    EllipsisHorizontalIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    initialData: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['update:isOpen', 'submit'])

const expenseAmount = ref('')
const expenseDescription = ref('')
const expensePaymentMethod = ref('cash')
const expenseNote = ref('')
const isSubmitting = ref(false)

import { watch } from 'vue'
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.initialData) {
        if (props.initialData.title) expenseDescription.value = props.initialData.title
        if (props.initialData.amount) {
            let val = props.initialData.amount.toString()
            expenseAmount.value = val.replace(/\B(?=(\d{3})+(?!\d))/g, ".")
        }
    }
}, { immediate: true })

const paymentMethods = [
    { value: 'cash', label: 'Tiền mặt', icon: BanknotesIcon },
    { value: 'bank_transfer', label: 'Chuyển khoản', icon: ArrowsRightLeftIcon },
    { value: 'qr_code', label: 'Quét mã QR', icon: QrCodeIcon },
    { value: 'other', label: 'Khác', icon: EllipsisHorizontalIcon }
]

const close = () => {
    emit('update:isOpen', false)
    resetForm()
}

const onAmountInput = (event) => {
    let value = event.target.value.replace(/[^\d]/g, '')
    if (value) {
        value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".")
    }
    expenseAmount.value = value
}

const submitCreateExpense = async () => {
    const rawAmount = parseInt(expenseAmount.value.replace(/\./g, '')) || 0
    
    if (rawAmount <= 0) {
        return // Should probably show an error toast, but let the parent handle validation if needed
    }

    if (!expenseDescription.value.trim()) {
        return
    }

    isSubmitting.value = true
    
    try {
        await emit('submit', {
            description: expenseDescription.value,
            amount: rawAmount,
            payment_method: expensePaymentMethod.value,
            note: expenseNote.value
        })
        close()
    } finally {
        isSubmitting.value = false
    }
}

const resetForm = () => {
    expenseAmount.value = ''
    expenseDescription.value = ''
    expensePaymentMethod.value = 'cash'
    expenseNote.value = ''
}
</script>

<style scoped>
/* Custom scrollbar for a cleaner look */
::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #e5e7eb;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #d1d5db;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.scale-enter-active,
.scale-leave-active {
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.scale-enter-from,
.scale-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(20px);
}
</style>
