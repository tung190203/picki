<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[9999] p-3 sm:p-4" @click.self="closeModal">
        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[95vh] sm:max-h-[90vh]">
          <!-- Header -->
          <div class="p-4 sm:p-6 pb-3 sm:pb-4 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10 flex-shrink-0">
            <h3 class="text-lg sm:text-xl font-bold text-gray-900">Thêm nhóm Zalo</h3>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
              <XMarkIcon class="w-5 h-5 sm:w-6 sm:h-6" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-4 sm:p-6 overflow-y-auto custom-scrollbar space-y-6">
            
            <!-- Section 1: Zalo Link -->
            <div class="bg-white rounded-xl p-4 border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full p-2 bg-[#FBEAEB] flex items-center justify-center shadow-sm text-[#D72D36]">
                          <ShieldCheckIcon class="w-4 h-4" />
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">Link Zalo</p>
                            <p class="text-xs text-gray-500">Ưu tiên mở App</p>
                        </div>
                    </div>
                     <!-- Toggle Switch -->
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="form.zalo_link_enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#D72D36]"></div>
                    </label>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700">Đường dẫn nhóm Zalo (URL)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <LinkIcon class="h-5 w-5 text-[#3E414C]" />
                        </span>
                        <input type="text" v-model="form.zalo_link" class="block w-full pl-10 pr-3 py-2.5 bg-[#EDEEF2] border border-gray-200 rounded-lg text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36]" placeholder="https://zalo.me/g/..." :disabled="!form.zalo_link_enabled" />
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-2 bg-[#F6F7F9] p-3 rounded-lg border border-[#F6F7F9]">
                     <InformationCircleIcon class="w-5 h-5 text-[#141519] flex-shrink-0 mt-0.5" />
                     <p class="text-xs text-[#838799] leading-relaxed">Hệ thống sẽ tự động mở liên kết này trên App cho người dùng cài đặt Zalo</p>
                </div>
            </div>

             <!-- Section 2: QR Code -->
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#fcecec] flex items-center justify-center">
                             <QrCodeIcon class="w-6 h-6 text-[#D72D36]" />
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">QR CODE</p>
                            <p class="text-xs text-gray-500">Hiển thị cho người dùng quét</p>
                        </div>
                    </div>
                     <!-- Toggle Switch -->
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="form.qr_zalo_enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#D72D36]"></div>
                    </label>
                </div>

                <!-- Image Uploader -->
                <div class="border-2 border-dashed border-gray-200 rounded-xl bg-white p-6 flex flex-col items-center justify-center cursor-pointer hover:border-[#D72D36]/50 hover:bg-gray-50 transition-all relative overflow-hidden" @click="triggerFileInput" :class="{'opacity-50 pointer-events-none': !form.qr_zalo_enabled}">
                    
                    <template v-if="form.qr_preview_url">
                         <img :src="form.qr_preview_url" class="max-h-64 object-contain rounded-lg" alt="QR Code" />
                         <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                             <p class="text-white font-medium">Thay đổi ảnh</p>
                         </div>
                    </template>
                    
                    <template v-else>
                         <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-3 text-gray-400">
                             <PhotoIcon class="w-6 h-6" />
                         </div>
                         <p class="text-sm font-bold text-gray-700">Nhấn để tải ảnh lên</p>
                         <p class="text-xs text-gray-400 mt-1">PNG, JPG, GIF (Tối đa 5MB)</p>
                    </template>

                    <input type="file" ref="qrInput" class="hidden" accept="image/*" @change="handleFileChange" :disabled="!form.qr_zalo_enabled" />
                </div>
            </div>

          </div>

          <!-- Footer -->
          <div class="p-6 pt-2 bg-white sticky bottom-0 z-10 text-center border-t border-gray-100">
            <Button size="md" color="danger" class="w-fit bg-[#D72D36] hover:bg-[#c9252e] text-white rounded-[4px] px-[69px] py-3 font-semibold" @click="handleSubmit" :disabled="isLoading">
                <span v-if="isLoading">Đang lưu...</span>
                <span v-else>Lưu</span>
            </Button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'
import { XMarkIcon, LinkIcon, InformationCircleIcon, PhotoIcon } from '@heroicons/vue/24/outline'
import ShieldCheckIcon from "@/assets/images/shield_check.svg";
import QrCodeIcon from '@/assets/images/qr_code.svg';
import Button from '@/components/atoms/Button.vue'

const props = defineProps({
  modelValue: Boolean,
  club: {
    type: Object,
    default: () => ({})
  },
  isLoading: Boolean
})

const emit = defineEmits(['update:modelValue', 'save'])
const qrInput = ref(null)

const form = ref({
    zalo_link_enabled: false,
    zalo_link: '',
    qr_zalo_enabled: false,
    qr_preview_url: null,
    qr_code_image_url: null
})

watch(() => props.club, (newVal) => {
    if (newVal?.profile) {
        const profile = newVal.profile

        form.value.zalo_link = profile.zalo_link || ''
        form.value.zalo_link_enabled = !!profile.zalo_link_enabled
        form.value.qr_preview_url = profile.qr_code_image_url || null
        form.value.qr_zalo_enabled = !!profile.qr_zalo_enabled
    }
}, { immediate: true })

const closeModal = () => {
  emit('update:modelValue', false)
}

const triggerFileInput = () => {
    if (form.value.qr_zalo_enabled && qrInput.value) {
        qrInput.value.click()
    }
}

const handleFileChange = (event) => {
    const file = event.target.files[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = (e) => {
        form.value.qr_preview_url = e.target.result
        form.value.qr_code_image_url = file
    }
    reader.readAsDataURL(file)
}

const handleSubmit = () => {
    emit('save', { ...form.value })
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

.modal-enter-active .bg-white,
.modal-leave-active .bg-white {
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white {
  transform: scale(0.95);
  opacity: 0;
}

/* Custom scrollbar matching other modal */
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
