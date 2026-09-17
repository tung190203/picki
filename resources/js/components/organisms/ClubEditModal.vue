<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[9999] p-3 sm:p-4" @click.self="closeModal">
        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[95vh] sm:max-h-[90vh]">
          <!-- Header -->
          <div class="p-4 sm:p-6 pb-3 sm:pb-4 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10 flex-shrink-0">
            <h3 class="text-lg sm:text-xl font-bold text-gray-900">Chỉnh sửa thông tin CLB</h3>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
              <XMarkIcon class="w-5 h-5 sm:w-6 sm:h-6" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-4 sm:p-6 overflow-y-auto custom-scrollbar">
            <!-- Cover & Avatar -->
            <div class="relative mb-8">
              <!-- Cover Image -->
              <div class="aspect-[4/1] w-full rounded-xl bg-gray-100 overflow-hidden relative group">
                <img :src="form.cover_image_url || defaultCover" class="w-full h-full object-cover" alt="Cover" />
                <div class="absolute bottom-2 right-2 bg-[#4392E0] rounded-full p-1 shadow-sm cursor-pointer hover:bg-[#4392E0] transition-colors" @click="triggerFileInput('coverInput')">
                  <PencilIcon class="w-3 h-3 text-white" />
                </div>
                <input type="file" ref="coverInput" class="hidden" accept="image/*" @change="handleFileChange($event, 'cover')" />
              </div>

              <!-- Avatar -->
              <div class="absolute -bottom-10 left-1/2 transform -translate-x-1/2">
                <div class="relative group">
                  <div class="w-20 h-20 rounded-full border border-white overflow-hidden bg-white shadow-md">
                   <img :src="form.logo_url || defaultAvatar" class="w-full h-full object-cover" alt="Avatar" />
                  </div>
                  <div class="absolute bottom-0 right-0 bg-[#4392E0] rounded-full p-1 shadow-md cursor-pointer hover:bg-[#4392E0] transition-colors z-10" @click="triggerFileInput('avatarInput')">
                     <PencilIcon class="w-3 h-3 text-white" />
                  </div>
                  <input type="file" ref="avatarInput" class="hidden" accept="image/*" @change="handleFileChange($event, 'avatar')" />
                </div>
              </div>
            </div>

            <!-- Form Fields -->
            <div class="space-y-5 mt-4">
              <!-- Name -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tên CLB</label>
                <div class="relative">
                   <input v-model="form.name" type="text" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors" placeholder="Nhập tên CLB" />
                   <CheckIcon v-if="form.name" class="w-5 h-5 text-green-500 absolute right-3 top-1/2 transform -translate-y-1/2" />
                </div>
              </div>

              <!-- Description -->
              <div>
                <div class="flex justify-between mb-1">
                    <label class="block text-sm font-medium text-gray-700">Chỉnh sửa giới thiệu</label>
                    <span class="text-xs text-gray-400">{{ form.description?.length || 0 }}/300</span>
                </div>
                <textarea v-model="form.description" rows="4" maxlength="300" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors placeholder:text-gray-400" placeholder="Hãy chia sẻ một chút về CLB"></textarea>
              </div>

              <!-- Footer -->
              <div>
                <div class="flex justify-between mb-1">
                    <label class="block text-sm font-medium text-gray-700">Footer</label>
                    <span class="text-xs text-gray-400">{{ form.footer?.length || 0 }}/300</span>
                </div>
                <textarea v-model="form.footer" rows="4" maxlength="300" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors placeholder:text-gray-400" placeholder="Nhập nội dung footer"></textarea>
              </div>

              <!-- Visibility -->
              <Toggle :value="form.is_public" label="Công khai CLB" description="Cho phép mọi người có thể tìm thấy CLB" @update="val => form.is_public = val" />
            </div>
          </div>

          <!-- Footer -->
          <div class="p-6 pt-2 bg-white sticky bottom-0 z-10 text-center">
            <Button size="md" color="danger" class="w-fit bg-[#D72D36] hover:bg-[#c9252e] text-white rounded-[4px] px-[69px] py-3 font-semibold" @click="handleSubmit" :disabled="isLoading">
                <span v-if="isLoading">Đang lưu...</span>
                <span v-else>Lưu</span>
            </Button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <ImageCropperModal
    :is-open="cropper.isOpen"
    :image="cropper.image"
    :stencil-props="cropper.stencilProps"
    @close="cropper.isOpen = false"
    @save="onCropSave"
  />
</template>

<script setup>
import { ref, watch } from 'vue'
import { XMarkIcon, PencilIcon, CheckIcon } from '@heroicons/vue/24/outline'
import Button from '@/components/atoms/Button.vue'
import Toggle from '@/components/atoms/Toggle.vue'
import ImageCropperModal from '@/components/molecules/ImageCropperModal.vue'
import defaultCover from '@/assets/images/club-default-thumbnail.svg?url'

const defaultAvatar = "/images/default-avatar.png";

const props = defineProps({
  modelValue: Boolean,
  club: {
    type: Object,
    default: () => ({})
  },
  isLoading: Boolean
})

const emit = defineEmits(['update:modelValue', 'save'])

const form = ref({
    name: '',
    description: '',
    footer: '',
    is_public: true,
    cover_image_url: null,
    logo_url: null,
    cover_file: null,
    logo_file: null
})

const coverInput = ref(null)
const avatarInput = ref(null)

const cropper = ref({
    isOpen: false,
    image: null,
    type: null,
    stencilProps: {
        aspectRatio: 1
    }
})

watch(() => props.club, (newVal) => {
    if (newVal) {
        form.value = {
            name: newVal.name || '',
            description: newVal.profile?.description || '',
            footer: newVal.profile?.footer || newVal.footer || '',
            is_public: newVal.is_public ?? true,
            cover_image_url: newVal.profile.cover_image_url || null,
            logo_url: newVal.logo_url || null,
            cover_file: null,
            logo_file: null
        }
    }
}, { immediate: true })

const closeModal = () => {
  emit('update:modelValue', false)
}

const triggerFileInput = (refName) => {
    if (refName === 'coverInput' && coverInput.value) coverInput.value.click()
    if (refName === 'avatarInput' && avatarInput.value) avatarInput.value.click()
}

const handleFileChange = (event, type) => {
    const file = event.target.files[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = (e) => {
        if (type === 'cover') {
            cropper.value = {
                isOpen: true,
                image: e.target.result,
                type: 'cover',
                stencilProps: {
                    aspectRatio: 4 / 1
                }
            }
        } else {
            form.value.logo_url = e.target.result
            form.value.logo_file = file
        }
    }
    reader.readAsDataURL(file)
}

const onCropSave = (blob) => {
    const reader = new FileReader()
    reader.onload = (e) => {
        if (cropper.value.type === 'cover') {
            form.value.cover_image_url = e.target.result
            form.value.cover_file = blob
        }
        cropper.value.isOpen = false
    }
    reader.readAsDataURL(blob)
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
