<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[9999] p-3 sm:p-4" @click.self="closeModal">
        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[95vh] sm:max-h-[90vh]">
          <!-- Header -->
          <div class="p-4 sm:p-6 pb-3 sm:pb-4 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10 flex-shrink-0">
            <h3 class="text-lg sm:text-xl font-bold text-gray-900">Báo cáo câu lạc bộ</h3>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
              <XMarkIcon class="w-5 h-5 sm:w-6 sm:h-6" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-4 sm:p-6 overflow-y-auto custom-scrollbar space-y-6">
            <!-- Reason Type -->
            <div class="relative" ref="typeDropdownRef">
              <label class="block font-semibold text-[#3E414C] mb-2">Lý do báo cáo <span class="text-red-500">*</span></label>
              
              <div 
                class="w-full flex items-center justify-between px-4 py-3 bg-white border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
                :class="isTypeDropdownOpen ? 'ring-2 ring-[#D72D36]/20 border-[#D72D36]' : 'border-gray-200'"
                @click="toggleTypeDropdown"
              >
                 <span class="font-medium" :class="selectedTypeObj ? 'text-gray-700' : 'text-gray-400'">
                   {{ selectedTypeObj ? selectedTypeObj.label : 'Chọn loại báo cáo' }}
                 </span>
                 <ChevronDownIcon class="h-4 w-4 text-gray-500 transition-transform duration-200" :class="{'rotate-180': isTypeDropdownOpen}" />
              </div>

              <Transition
                  enter-active-class="transition duration-100 ease-out"
                  enter-from-class="transform scale-95 opacity-0"
                  enter-to-class="transform scale-100 opacity-100"
                  leave-active-class="transition duration-75 ease-in"
                  leave-from-class="transform scale-100 opacity-100"
                  leave-to-class="transform scale-95 opacity-0"
              >
                <div v-if="isTypeDropdownOpen" class="absolute z-20 w-full mt-2 bg-white rounded-xl shadow-xl border border-gray-100 py-2 max-h-48 overflow-y-auto custom-scrollbar">
                    <div 
                        v-for="type in reportTypes" 
                        :key="type.value"
                        class="px-4 py-3 cursor-pointer flex items-center gap-3 transition-colors"
                        :class="form.reason_type === type.value ? 'bg-[#fff5f5] text-[#D72D36]' : 'hover:bg-gray-50 text-[#3E414C]'"
                        @click="selectType(type.value)"
                    >
                        <span class="font-normal">{{ type.label }}</span>
                        <CheckIcon v-if="form.reason_type === type.value" class="w-4 h-4 ml-auto" stroke-width="3" />
                    </div>
                </div>
              </Transition>
            </div>

            <!-- Reason Description (Optional) -->
            <div>
              <div class="flex justify-between mb-2">
                <label class="block font-semibold text-[#3E414C]">Chi tiết báo cáo (Không bắt buộc)</label>
                <span class="text-xs text-gray-400">{{ form.reason.length }}/500</span>
              </div>
              <textarea 
                v-model="form.reason" 
                rows="4" 
                maxlength="500" 
                class="w-full px-4 py-3 bg-[#EDEEF2] border-none rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 transition-colors resize-none placeholder-[#9EA2B3]" 
                placeholder="Vui lòng mô tả thêm chi tiết... (tối đa 500 ký tự)"
              ></textarea>
            </div>
          </div>

          <!-- Footer -->
          <div class="p-6 pt-4 bg-white sticky bottom-0 z-10 text-right border-t border-gray-100 flex gap-3 justify-end">
            <Button size="md" color="white" class="border border-gray-200 text-gray-600 rounded-[4px] px-6 py-2.5 font-semibold hover:bg-gray-50 transition-colors" @click="closeModal" :disabled="isLoading">
                Hủy
            </Button>
            <Button size="md" color="danger" class="bg-[#D72D36] hover:bg-[#c9252e] text-white rounded-[4px] px-6 py-2.5 font-semibold" @click="handleSubmit" :disabled="isLoading || !form.reason_type">
                <span v-if="isLoading">Đang xử lý...</span>
                <span v-else>Gửi báo cáo</span>
            </Button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue'
import { XMarkIcon, ChevronDownIcon, CheckIcon } from '@heroicons/vue/24/outline'
import Button from '@/components/atoms/Button.vue'

const props = defineProps({
  modelValue: Boolean,
  isLoading: Boolean
})

const emit = defineEmits(['update:modelValue', 'submit'])

const typeDropdownRef = ref(null)
const isTypeDropdownOpen = ref(false)

const form = reactive({
    reason_type: '',
    reason: ''
})

const reportTypes = [
    { value: 'spam', label: 'Spam' },
    { value: 'inappropriate', label: 'Nội dung không phù hợp' },
    { value: 'fraud', label: 'Lừa đảo' },
    { value: 'harassment', label: 'Quấy rối' },
    { value: 'other', label: 'Khác' }
]

const selectedTypeObj = computed(() => {
    return reportTypes.find(t => t.value === form.reason_type) || null
})

const toggleTypeDropdown = () => {
    isTypeDropdownOpen.value = !isTypeDropdownOpen.value
}

const selectType = (value) => {
    form.reason_type = value
    isTypeDropdownOpen.value = false
}

const handleClickOutside = (event) => {
    if (typeDropdownRef.value && !typeDropdownRef.value.contains(event.target)) {
        isTypeDropdownOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

const closeModal = () => {
  emit('update:modelValue', false)
}

const handleSubmit = () => {
    emit('submit', { ...form })
}

const resetForm = () => {
    form.reason_type = ''
    form.reason = ''
}

watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        resetForm()
    }
})
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

.modal-enter-active .bg-white,
.modal-leave-active .bg-white {
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white {
  transform: scale(0.95);
  opacity: 0;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #E5E7EB;
  border-radius: 20px;
}
</style>
