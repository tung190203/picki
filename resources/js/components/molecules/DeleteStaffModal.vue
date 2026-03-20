<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="isOpen"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        @click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-scaleIn relative">

          <!-- Header -->
          <div class="px-6 pt-5 flex items-center justify-between border-b border-gray-100">
            <h2 class="text-[18px] font-bold text-[#374151]">Xóa người bảo lãnh</h2>
            <button @click="closeModal" class="p-2 text-gray-500 hover:text-gray-700 transition-colors">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <!-- Content -->
          <div class="px-6 pb-6 pt-4">

            <!-- Staff info -->
            <div class="flex items-center gap-3 mb-4 p-3 bg-gray-50 rounded-xl">
              <img :src="guarantor?.avatar_url || 'https://picki.vn/images/default-avatar.png'"
                   :alt="guarantor?.full_name"
                   class="w-10 h-10 rounded-full object-cover border border-gray-200">
              <div class="flex-1">
                <p class="text-sm font-semibold text-[#374151]">{{ guarantor?.full_name }}</p>
                <p class="text-xs text-gray-500">Người bảo lãnh</p>
              </div>
            </div>

            <!-- Warning -->
            <div class="flex items-start gap-3 p-3 bg-yellow-50 border border-yellow-200 rounded-xl mb-4">
              <ExclamationTriangleIcon class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" />
              <div class="text-xs text-yellow-800 leading-relaxed">
                <p class="font-semibold mb-1">Người này đang bảo lãnh <strong>{{ guests?.length || 0 }} guest(s)</strong>.</p>
                <p>Bạn cần chọn một trong hai cách xử lý bên dưới.</p>
              </div>
            </div>

            <!-- Guest list preview -->
            <div v-if="guests?.length" class="mb-4">
              <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Guest được bảo lãnh</p>
              <div class="space-y-2 max-h-32 overflow-y-auto">
                <div v-for="guest in guests" :key="guest.id"
                     class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg">
                  <img :src="guest.user?.avatar_url || 'https://picki.vn/images/default-avatar.png'"
                       class="w-7 h-7 rounded-full object-cover">
                  <span class="text-sm text-gray-700 flex-1 truncate">
                    {{ guest.user?.full_name || guest.guest_name || 'Guest' }}
                  </span>
                  <span class="text-[10px] bg-[#FEF3C7] text-[#92400E] px-1.5 py-0.5 rounded font-medium">Guest</span>
                </div>
              </div>
            </div>

            <!-- Option 1: Delete guests -->
            <div class="mb-3">
              <button
                @click="selectedAction = 'delete_guests'"
                class="w-full text-left p-4 border-2 rounded-2xl transition-all"
                :class="selectedAction === 'delete_guests'
                  ? 'border-[#D72D36] bg-[#FEF2F2]'
                  : 'border-[#EDEEF2] hover:border-[#D72D36] hover:bg-[#FEF2F2]'">
                <div class="flex items-start gap-3">
                  <div class="mt-0.5">
                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                      :class="selectedAction === 'delete_guests' ? 'border-[#D72D36]' : 'border-gray-300'">
                      <div v-if="selectedAction === 'delete_guests'" class="w-3 h-3 rounded-full bg-[#D72D36]"></div>
                    </div>
                  </div>
                  <div class="flex-1">
                    <h3 class="font-semibold text-[#374151] mb-1">Xóa luôn các guest</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">
                      Xóa người bảo lãnh và toàn bộ {{ guests?.length || 0 }} guest(s) được bảo lãnh bởi họ.
                    </p>
                  </div>
                </div>
              </button>
            </div>

            <!-- Option 2: Transfer guarantor -->
            <div class="mb-4">
              <button
                @click="selectedAction = 'transfer_guarantor'"
                class="w-full text-left p-4 border-2 rounded-2xl transition-all"
                :class="selectedAction === 'transfer_guarantor'
                  ? 'border-[#207AD5] bg-blue-50'
                  : 'border-[#EDEEF2] hover:border-[#207AD5] hover:bg-blue-50'">
                <div class="flex items-start gap-3">
                  <div class="mt-0.5">
                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                      :class="selectedAction === 'transfer_guarantor' ? 'border-[#207AD5]' : 'border-gray-300'">
                      <div v-if="selectedAction === 'transfer_guarantor'" class="w-3 h-3 rounded-full bg-[#207AD5]"></div>
                    </div>
                  </div>
                  <div class="flex-1">
                    <h3 class="font-semibold text-[#374151] mb-1">Chuyển bảo lãnh</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">
                      Xóa người bảo lãnh và chuyển {{ guests?.length || 0 }} guest(s) cho người khác bảo lãnh.
                    </p>
                  </div>
                </div>
              </button>

              <!-- Guarantor selector -->
              <div v-if="selectedAction === 'transfer_guarantor'" class="mt-3 ml-8">
                <label class="text-xs font-semibold text-gray-500 block mb-2">Chọn người bảo lãnh mới</label>
                <div v-if="candidates?.length" class="space-y-2 max-h-40 overflow-y-auto">
                  <button
                    v-for="candidate in candidates"
                    :key="candidate.user_id"
                    @click="selectedNewGuarantor = candidate.user_id"
                    class="w-full flex items-center gap-2 p-2 rounded-lg border transition-all"
                    :class="selectedNewGuarantor === candidate.user_id
                      ? 'border-[#207AD5] bg-blue-50'
                      : 'border-gray-200 hover:border-[#207AD5]'">
                    <img :src="candidate.avatar_url || 'https://picki.vn/images/default-avatar.png'"
                         class="w-7 h-7 rounded-full object-cover">
                    <span class="text-sm text-gray-700 flex-1 text-left truncate">{{ candidate.full_name }}</span>
                    <span v-if="candidate.is_organizer" class="text-[10px] bg-[#D72D36] text-white px-1.5 py-0.5 rounded font-medium">Chủ kèo</span>
                  </button>
                </div>
                <p v-else class="text-xs text-gray-400 italic">Không có người bảo lãnh nào khả dụng.</p>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3">
              <button @click="closeModal"
                class="flex-1 py-3 bg-[#F3F4F6] text-[#374151] font-bold rounded-full transition-all hover:bg-gray-200 active:scale-[0.98]">
                Đóng
              </button>
              <button
                @click="handleConfirm"
                :disabled="!canConfirm"
                class="flex-1 py-3 font-bold rounded-full transition-all active:scale-[0.98]"
                :class="canConfirm
                  ? 'bg-[#D72D36] text-white hover:bg-[#c4252e]'
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'">
                Xác nhận
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { XMarkIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: Boolean,
  staffId: { type: [Number, String], default: null },
  guarantor: { type: Object, default: null },
  guests: { type: Array, default: () => [] },
  candidates: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const selectedAction = ref(null)
const selectedNewGuarantor = ref(null)

const isOpen = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    selectedAction.value = null
    selectedNewGuarantor.value = null
  }
})

const canConfirm = computed(() => {
  if (!selectedAction.value) return false
  if (selectedAction.value === 'delete_guests') return true
  if (selectedAction.value === 'transfer_guarantor') return !!selectedNewGuarantor.value
  return false
})

const closeModal = () => {
  isOpen.value = false
}

const handleConfirm = () => {
  if (!canConfirm.value) return
  emit('confirm', {
    staffId: props.staffId,
    action: selectedAction.value,
    newGuarantorUserId: selectedNewGuarantor.value,
  })
  closeModal()
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
@keyframes scaleIn {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
.animate-scaleIn {
  animation: scaleIn 0.3s ease-out;
}
</style>
