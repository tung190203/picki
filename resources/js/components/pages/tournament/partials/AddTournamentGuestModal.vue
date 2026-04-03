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
          class="bg-white rounded-[16px] w-full max-w-[480px] max-h-[90vh] overflow-y-auto transition-all duration-300 flex flex-col p-6 relative shadow-2xl"
        >
          <div class="flex items-center justify-between mb-5 flex-shrink-0">
            <h2 class="text-lg font-bold text-[#2D3139]">Thêm khách mời (Guest)</h2>
            <button
              type="button"
              @click="close"
              class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded"
              aria-label="Đóng"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>

          <div class="space-y-4 flex-1">
            <div>
              <label for="guest-display-name" class="block text-[13px] font-semibold text-[#6B7280] mb-1.5 uppercase tracking-wide">
                Tên hiển thị <span class="text-red-500">*</span>
              </label>
              <input
                id="guest-display-name"
                v-model="form.guest_name"
                type="text"
                placeholder="Ví dụ: Tuấn Nguyễn, Văn Khải,..."
                class="w-full bg-[#F9FAFB] border border-gray-200 rounded-lg py-2.5 px-3 text-[13px] text-[#1F2937] placeholder:text-[#9CA3AF] focus:outline-none focus:ring-2 focus:ring-[#D72D36]/30 focus:border-[#D72D36] transition"
              />
              <p v-if="errors.guest_name" class="text-red-500 text-xs mt-1">{{ errors.guest_name }}</p>
            </div>

            <div>
              <label class="block text-[13px] font-semibold text-[#6B7280] mb-1.5 uppercase tracking-wide">
                Ảnh đại diện
              </label>
              <div class="flex items-center gap-4">
                <div class="relative w-16 h-16 rounded-full overflow-hidden flex-shrink-0 border-2 border-gray-200 bg-gray-50">
                  <img
                    v-if="avatarPreview || form.guest_avatar"
                    :src="avatarPreview || form.guest_avatar"
                    alt="Avatar Preview"
                    class="w-full h-full object-cover"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                  <div
                    class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity cursor-pointer"
                    @click="triggerAvatarInput"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </div>
                </div>
                <div class="flex-1">
                  <p class="text-[12px] text-[#6B7280] mb-2">Tải lên ảnh đại diện cho khách mời (tùy chọn)</p>
                  <button
                    type="button"
                    @click="triggerAvatarInput"
                    class="text-[12px] text-[#D72D36] font-medium hover:underline"
                  >
                    Chọn ảnh
                  </button>
                  <button
                    v-if="avatarPreview || form.guest_avatar"
                    type="button"
                    @click="removeAvatar"
                    class="ml-3 text-[12px] text-gray-400 hover:text-red-500"
                  >
                    Xóa
                  </button>
                </div>
              </div>
              <input
                ref="avatarInput"
                type="file"
                accept="image/*"
                class="hidden"
                @change="handleAvatarChange"
              />
            </div>

            <div>
              <label for="guest-phone" class="block text-[13px] font-semibold text-[#6B7280] mb-1.5 uppercase tracking-wide">
                Số điện thoại
              </label>
              <input
                id="guest-phone"
                v-model="form.guest_phone"
                type="tel"
                placeholder="Nhập SĐT để định danh khách (tùy chọn)"
                class="w-full bg-[#F9FAFB] border border-gray-200 rounded-lg py-2.5 px-3 text-[13px] text-[#1F2937] placeholder:text-[#9CA3AF] focus:outline-none focus:ring-2 focus:ring-[#D72D36]/30 focus:border-[#D72D36] transition"
              />
              <p v-if="errors.guest_phone" class="text-red-500 text-xs mt-1">{{ errors.guest_phone }}</p>
            </div>

            <div>
              <label for="guest-estimated-level" class="block text-[13px] font-semibold text-[#6B7280] mb-1.5 uppercase tracking-wide">
                Trình độ ước tính
              </label>
              <input
                id="guest-estimated-level"
                v-model.number="form.estimated_level"
                type="number"
                min="1"
                max="8"
                step="0.5"
                placeholder="1.0 - 8.0"
                class="w-full bg-[#F9FAFB] border border-gray-200 rounded-lg py-2.5 px-3 text-[13px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#D72D36]/30 focus:border-[#D72D36] transition"
              />
              <p class="text-[11px] text-[#9CA3AF] mt-1">Trình độ ước tính từ 1.0 đến 8.0</p>
            </div>

            <div>
              <label for="guest-guarantor" class="block text-[13px] font-semibold text-[#6B7280] mb-1.5 uppercase tracking-wide">
                Người bảo lãnh (Thu tiền) <span class="text-red-500">*</span>
              </label>
              <select
                id="guest-guarantor"
                v-model="form.guarantor_user_id"
                class="w-full bg-[#F9FAFB] border border-gray-200 rounded-lg py-2.5 px-3 text-[13px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#D72D36]/30 focus:border-[#D72D36] transition appearance-none cursor-pointer"
              >
                <option value="" disabled>-- Chọn người bảo lãnh --</option>
                <option
                  v-for="candidate in guarantorCandidates"
                  :key="candidate.user_id"
                  :value="candidate.user_id"
                >
                  {{ candidate.is_organizer && candidate.user_id === currentUserId ? 'Tôi (Host)' : candidate.full_name }}{{ candidate.is_organizer && candidate.user_id !== currentUserId ? ' (Chủ kèo)' : '' }}
                </option>
              </select>
              <p class="text-[11px] text-[#6B7280] mt-1.5">
                *Người bảo lãnh là Host hoặc người đã tham gia và đã đóng phí kèo đấu (trong trường hợp kèo có phí). Người bảo lãnh có trách nhiệm thu tiền từ Guest và thanh toán chi phí (nếu có) của kèo đấu.
              </p>
              <p v-if="errors.guarantor_user_id" class="text-red-500 text-xs mt-1">{{ errors.guarantor_user_id }}</p>
            </div>

            <div class="flex gap-3 p-3 bg-[#FEFCE8] border border-[#FEF08A] rounded-lg">
              <div class="flex-shrink-0 w-5 h-5 rounded-full bg-[#EAB308] flex items-center justify-center mt-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
              </div>
              <p class="text-[12px] text-[#713F12] leading-snug">
                Trận đấu có sự tham gia của Khách mời sẽ chuyển sang chế độ <strong>Giao lưu</strong>. Hệ thống sẽ <strong>KHÔNG tính điểm xếp hạng (Picki-Rating)</strong> cho tất cả người chơi trong trận này.
              </p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 mt-6 pt-4 border-t border-gray-100 flex-shrink-0">
            <button
              type="button"
              @click="close"
              class="w-full py-3 bg-[#F2F3F5] text-[#2D3139] rounded-lg font-semibold text-[13px] hover:bg-gray-200 transition-colors"
            >
              Hủy bỏ
            </button>
            <button
              type="button"
              @click="handleSubmit"
              :disabled="isSubmitting"
              class="w-full py-3 bg-[#D72D36] text-white rounded-lg font-semibold text-[13px] hover:bg-[#b91c1c] transition-colors flex items-center justify-center disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <template v-if="isSubmitting">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Đang xử lý...
              </template>
              <template v-else>Thêm vào kèo</template>
            </button>
          </div>
        </div>
      </Transition>
    </div>
  </Transition>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { toast } from 'vue3-toastify'
import { addTournamentGuest, getTournamentGuarantorCandidates } from '@/service/guest.js'
import { useUserStore } from '@/store/auth'
import { storeToRefs } from 'pinia'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  tournament: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:isOpen', 'success'])

const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)
const currentUserId = computed(() => getUser.value?.id ?? null)

const form = ref({
  guest_name: '',
  guest_phone: '',
  guest_avatar: null,
  guarantor_user_id: '',
  estimated_level: null,
})

const avatarInput = ref(null)
const avatarPreview = ref('')
const errors = ref({})
const isSubmitting = ref(false)
const guarantorCandidates = ref([])

const validateForm = () => {
  errors.value = {}

  if (!form.value.guest_name.trim()) {
    errors.value.guest_name = 'Vui lòng nhập tên hiển thị'
  }

  if (form.value.guest_phone) {
    const phoneRegex = /^(0\d{9,10})$/
    const cleanPhone = form.value.guest_phone.replaceAll(/\s/g, '')
    if (!phoneRegex.test(cleanPhone)) {
      errors.value.guest_phone = 'Số điện thoại không hợp lệ (0xxx xxx xxx)'
    }
  }

  if (!form.value.guarantor_user_id) {
    errors.value.guarantor_user_id = 'Vui lòng chọn người bảo lãnh (Thu tiền)'
  }

  return Object.keys(errors.value).length === 0
}

const resetForm = () => {
  form.value = {
    guest_name: '',
    guest_phone: '',
    guest_avatar: null,
    guarantor_user_id: '',
    estimated_level: null,
  }
  avatarPreview.value = ''
  errors.value = {}
}

const close = () => {
  resetForm()
  emit('update:isOpen', false)
}

const fetchGuarantorCandidates = async () => {
  if (!props.tournament?.id) return
  try {
    const data = await getTournamentGuarantorCandidates(props.tournament.id)
    guarantorCandidates.value = data || []
    const meAsOrganizer = (guarantorCandidates.value || []).find(
      c => c.is_organizer && c.user_id === currentUserId.value
    )
    if (meAsOrganizer && !form.value.guarantor_user_id) {
      form.value.guarantor_user_id = meAsOrganizer.user_id
    }
  } catch {
    guarantorCandidates.value = []
  }
}

const triggerAvatarInput = () => {
  avatarInput.value?.click()
}

const handleAvatarChange = (event) => {
  const file = event.target.files[0]
  if (!file) return

  if (file.size > 5 * 1024 * 1024) {
    toast.error('Kích thước ảnh không được quá 5MB')
    return
  }

  form.value.guest_avatar = file

  const reader = new FileReader()
  reader.onload = (e) => {
    avatarPreview.value = e.target.result
  }
  reader.readAsDataURL(file)
}

const removeAvatar = () => {
  avatarPreview.value = ''
  form.value.guest_avatar = null
  if (avatarInput.value) {
    avatarInput.value.value = ''
  }
}

const handleSubmit = async () => {
  if (!validateForm()) return

  try {
    isSubmitting.value = true
    const payload = new FormData()
    payload.append('guest_name', form.value.guest_name.trim())
    if (form.value.guest_phone) {
      payload.append('guest_phone', form.value.guest_phone.replaceAll(/\s/g, ''))
    }
    if (form.value.guest_avatar) {
      payload.append('guest_avatar', form.value.guest_avatar)
    }
    if (form.value.guarantor_user_id) {
      payload.append('guarantor_user_id', Number(form.value.guarantor_user_id))
    }
    if (form.value.estimated_level != null) {
      payload.append('estimated_level', Number(form.value.estimated_level))
    }

    const response = await addTournamentGuest(props.tournament.id, payload)
    toast.success(response?.message || 'Thêm khách mời thành công')
    emit('success', response?.data || null)
    close()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi thêm khách mời')
  } finally {
    isSubmitting.value = false
  }
}

watch(
  () => props.isOpen,
  async (open) => {
    if (open) {
      resetForm()
      await fetchGuarantorCandidates()
    }
  }
)
</script>

<style scoped>
.slider-red::-webkit-slider-thumb {
  appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #D72D36;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.slider-red::-moz-range-thumb {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #D72D36;
  cursor: pointer;
  border: none;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
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
