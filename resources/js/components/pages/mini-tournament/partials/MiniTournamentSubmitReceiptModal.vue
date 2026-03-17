<template>
  <Transition name="fade">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[10000] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
      @click.self="close"
    >
      <Transition name="scale">
        <div
          v-if="isOpen"
          class="bg-white rounded-[16px] w-full max-w-[420px] transition-all duration-300 flex flex-col p-6 relative shadow-2xl overflow-hidden"
        >
          <!-- Nội dung -->
          <div class="flex flex-col items-center justify-center w-full mb-6 mt-2">
            <p class="text-[14px] font-bold text-[#1F2937] text-center mb-4 px-4">
              {{ titleText }}
            </p>

            <div class="w-36 h-36 bg-gray-50 flex items-center justify-center mb-4 overflow-hidden">
              <img
                v-if="qrCodeUrl"
                :src="qrCodeUrl"
                alt="QR thanh toán"
                class="w-full h-full object-contain mix-blend-multiply"
              />
              <div
                v-else
                class="text-sm border border-dashed border-gray-300 w-full h-full flex items-center justify-center text-gray-400"
              >
                Không có mã QR
              </div>
            </div>

            <div class="flex items-center space-x-1.5 font-bold" v-if="feePerPerson">
              <span class="text-[14px] text-[#1F2937]">VNĐ</span>
              <span class="text-[20px] text-[#4392E0]">{{ formatAmount(feePerPerson) }}</span>
            </div>
          </div>

          <div class="space-y-5">
            <!-- Upload -->
            <div>
              <label
                for="mini-receipt-upload"
                class="block text-[13px] font-bold text-[#838799] mb-2 uppercase tracking-wide"
              >
                Ảnh biên lai thanh toán
              </label>
              <div
                class="w-full h-[140px] bg-white border-2 border-dashed border-gray-200 rounded-[8px] flex flex-col items-center justify-center cursor-pointer hover:border-[#D72D36] transition-colors relative group overflow-hidden"
                @click="triggerFileInput"
              >
                <input
                  type="file"
                  id="mini-receipt-upload"
                  ref="fileInput"
                  class="hidden"
                  accept="image/jpeg, image/png, image/jpg, image/gif, image/svg"
                  @change="handleFileUpload"
                />

                <!-- Preview -->
                <template v-if="previewImage">
                  <img :src="previewImage" alt="Biên lai" class="w-full h-full object-contain" />
                  <div
                    class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center"
                  >
                    <button
                      @click.stop="removePreview"
                      class="bg-white/20 hover:bg-white/40 p-2 rounded-full backdrop-blur-md transition-colors"
                    >
                      <TrashIcon class="w-5 h-5 text-white" />
                    </button>
                  </div>
                </template>

                <!-- Placeholder -->
                <template v-else>
                  <div
                    class="w-10 h-10 bg-[#EDEEF2] rounded-full flex items-center justify-center mb-2 group-hover:bg-[#FEE2E2] transition-colors"
                  >
                    <PhotoIcon class="w-5 h-5 text-[#838799] group-hover:text-[#D72D36]" />
                  </div>
                  <p class="font-bold text-[14px] text-[#1F2937]">Nhấn để tải ảnh lên</p>
                  <p class="text-[12px] text-[#838799] mt-0.5">PNG, JPG (Tối đa 5MB)</p>
                </template>
              </div>
            </div>

            <!-- Ghi chú -->
            <div>
              <div class="flex justify-between items-center mb-1">
                <label
                  for="mini-payment-note"
                  class="block text-[14px] font-bold text-[#1F2937]"
                >
                  Ghi chú thêm
                </label>
                <span class="text-[12px] text-[#838799]">{{ note.length }}/300</span>
              </div>
              <textarea
                v-model="note"
                id="mini-payment-note"
                rows="3"
                maxlength="300"
                placeholder="Ghi chú cho chủ kèo về giao dịch của bạn"
                class="w-full bg-[#f9fafb] border-none rounded-xl py-3 px-4 text-[13px] focus:ring-0 placeholder:text-[#9EA2B3] resize-none"
              ></textarea>
            </div>

            <!-- Nút -->
            <div class="grid grid-cols-2 gap-3 pt-2">
              <button
                @click="close"
                class="w-full py-3.5 bg-[#F2F3F5] text-[#2D3139] rounded-[4px] font-bold text-[14px] hover:bg-gray-200 transition-colors"
              >
                Đóng
              </button>
              <button
                @click="handleSubmit"
                :disabled="isSubmitting || !selectedFile"
                class="w-full py-3.5 bg-[#F3F4F6] text-[#9CA3AF] rounded-[4px] font-bold text-[14px] transition-colors flex items-center justify-center"
                :class="{
                  '!bg-[#D72D36] !text-white hover:!bg-[#b91c1c] shadow-md': selectedFile && !isSubmitting,
                }"
              >
                <template v-if="isSubmitting">
                  <svg
                    class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
                    <circle
                      class="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      stroke-width="4"
                    ></circle>
                    <path
                      class="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                  </svg>
                  Đang gửi...
                </template>
                <template v-else>Gửi yêu cầu</template>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </Transition>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { PhotoIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { toast } from 'vue3-toastify'
import {
  getMyMiniTournamentPayment,
  payMiniTournament,
} from '@/service/miniTournament'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  miniId: {
    type: [String, Number],
    required: true,
  },
  hasFee: {
    type: Boolean,
    default: null,
  },
})

const emit = defineEmits(['update:isOpen', 'success'])

const fileInput = ref(null)
const selectedFile = ref(null)
const previewImage = ref(null)
const note = ref('')
const isSubmitting = ref(false)

const participantId = ref(null)
const hasFee = ref(false)
const feePerPerson = ref(0)
const qrCodeUrl = ref(null)
const feeDescription = ref('')

const titleText = computed(() =>
  hasFee.value ? 'Thanh toán phí tham gia kèo' : 'Kèo này không thu phí'
)

const formatAmount = (value) =>
  new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(value || 0)

const resetForm = () => {
  selectedFile.value = null
  previewImage.value = null
  note.value = ''
  if (fileInput.value) fileInput.value.value = ''
}

const close = () => {
  resetForm()
  emit('update:isOpen', false)
}

const triggerFileInput = () => {
  fileInput.value?.click()
}

const handleFileUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    if (!file.type.startsWith('image/')) {
      toast.error('Vui lòng chọn tệp hình ảnh')
      return
    }
    if (file.size > 5 * 1024 * 1024) {
      toast.error('Kích thước ảnh không quá 5MB')
      return
    }
    selectedFile.value = file
    previewImage.value = URL.createObjectURL(file)
  }
}

const removePreview = () => {
  selectedFile.value = null
  previewImage.value = null
  if (fileInput.value) fileInput.value.value = ''
}

const fetchMyPayment = async () => {
  try {
    const data = await getMyMiniTournamentPayment(props.miniId)
    // Contract mới: trả trực tiếp top-level, không bọc key `payment`.
    // Fallback cho môi trường cũ (nếu còn trả data.payment).
    const payload = data?.payment ? { ...data, ...data.payment } : (data || {})

    participantId.value = payload.participant_id ?? null
    // Ưu tiên lấy has_fee từ API mini detail truyền xuống.
    // Fallback dùng my-payment để tương thích dữ liệu cũ.
    hasFee.value = props.hasFee !== null ? Boolean(props.hasFee) : Boolean(payload.has_fee)
    feePerPerson.value = Number(payload.fee_per_person || 0)
    qrCodeUrl.value = payload.qr_code_url || null
    feeDescription.value = payload.fee_description || ''

    if (!hasFee.value) {
      toast.info('Kèo này không thu phí tham gia.')
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể lấy thông tin thanh toán')
    close()
  }
}

const handleSubmit = async () => {
  if (!selectedFile.value) {
    toast.error('Vui lòng chọn ảnh biên lai')
    return
  }

  try {
    isSubmitting.value = true
    const formData = new FormData()
    // Không cần truyền participant_id, BE sẽ tự xử lý
    formData.append('receipt_image', selectedFile.value)
    if (note.value) {
      formData.append('note', note.value)
    }

    const response = await payMiniTournament(props.miniId, formData)
    toast.success(response?.message || 'Gửi biên lai thành công')
    emit('success', response?.data || null)
    close()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi gửi biên lai')
  } finally {
    isSubmitting.value = false
  }
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      fetchMyPayment()
    }
  }
)

onMounted(() => {
  if (props.isOpen) {
    fetchMyPayment()
  }
})
</script>

<style scoped>
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

