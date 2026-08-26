<template>
  <Transition name="modal-fade">
    <div v-if="modelValue"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-50 backdrop-blur-sm"
      @click.self="closeModal">

      <div class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-xl shadow-2xl w-full max-w-md transform transition-all duration-300 overflow-hidden"
        role="dialog" aria-modal="true">

        <div class="p-5 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between">
          <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100">
            Huỷ sự kiện
          </h3>
          <button @click="closeModal" class="text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-white transition-colors">
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <div class="p-5 space-y-4">
          <div>
            <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-200 mb-1">Lý do huỷ sự kiện</label>
                <p class="text-sm text-gray-500 dark:text-slate-400">{{ reason.length }}/300</p>
            </div>
            <textarea
              v-model="reason"
              rows="3"
              maxlength="300"
              class="w-full px-3 py-2 bg-[#F8F9FA] dark:bg-slate-800 border border-transparent dark:border-slate-700 rounded-lg text-gray-900 dark:text-slate-100 placeholder:text-gray-400 dark:placeholder:text-slate-500 resize-none focus:outline-none focus:bg-white dark:focus:bg-slate-800 focus:border-[#D72D36] focus:ring-1 focus:ring-[#D72D36]"
              placeholder="Nhập lý do huỷ..."
            ></textarea>
          </div>

          <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-800/60 rounded-lg border border-gray-100 dark:border-slate-700">
            <div>
              <div class="text-sm font-medium text-gray-900 dark:text-slate-100">Hoàn tiền</div>
              <div class="text-xs text-gray-500 dark:text-slate-400">Tự động hoàn trả phí cho người tham gia</div>
            </div>
            <Toggle v-model="refund" />
          </div>

          <div v-if="recurrenceSeriesId" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-800/60 rounded-lg border border-gray-100 dark:border-slate-700">
            <div>
              <div class="text-sm font-medium text-gray-900 dark:text-slate-100">Hủy chuỗi</div>
              <div class="text-xs text-gray-500 dark:text-slate-400">Hủy toàn bộ chuỗi sự kiện</div>
            </div>
            <Toggle v-model="cancelSeries" />
          </div>

          <p class="text-[13px] text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 p-2 rounded border border-amber-100 dark:border-amber-900/60 italic">
            * Lưu ý: Thao tác huỷ sự kiện không thể hoàn tác.
          </p>
        </div>

        <div class="px-5 py-4 bg-gray-50 dark:bg-slate-900 border-t border-gray-100 dark:border-slate-800 flex justify-end gap-3">
          <button @click="closeModal"
            class="px-5 py-2 text-sm font-medium text-gray-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
            Đóng
          </button>
          <button @click="handleConfirm"
            :disabled="isSubmitting"
            class="px-5 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
            <span v-if="isSubmitting">Đang xử lý...</span>
            <span v-else>Xác nhận huỷ</span>
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import Toggle from '@/components/atoms/Toggle.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  isSubmitting: {
    type: Boolean,
    default: false
  },
  recurrenceSeriesId: {
    type: [String, Number],
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const reason = ref('')
const refund = ref(true)
const cancelSeries = ref(false)
// Reset form when modal opens
watch(() => props.modelValue, (newVal) => {
  if (newVal) {
    reason.value = ''
    refund.value = true
    cancelSeries.value = false
  }
})

const closeModal = () => {
  emit('update:modelValue', false)
}

const handleConfirm = () => {
  emit('confirm', {
    cancellation_reason: reason.value,
    cancel_transactions: refund.value,
    cancel_series: cancelSeries.value
  })
}
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.25s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-fade-enter-active .bg-white,
.modal-fade-leave-active .bg-white {
  transition: transform 0.25s ease;
}

.modal-fade-enter-from .bg-white,
.modal-fade-leave-to .bg-white {
  transform: scale(0.95) translateY(10px);
}
</style>
