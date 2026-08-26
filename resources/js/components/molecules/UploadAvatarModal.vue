<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="isOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                @click.self="closeModal">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden animate-scaleIn">
                    <!-- Header -->
                    <header class="flex items-center justify-between p-4 border-b">
                        <h2 class="text-lg font-semibold text-gray-800">
                            Chụp ảnh tạm thời
                        </h2>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </header>

                    <!-- Content -->
                    <section class="p-5">
                        <p class="text-sm text-gray-600 mb-4">
                            Ảnh tạm thời sẽ chỉ hiển thị trong kèo đấu này, ra khỏi kèo sẽ trở về avatar cũ.
                        </p>

                        <!-- Preview -->
                        <div class="flex justify-center mb-4">
                            <div class="relative">
                                <div v-if="previewUrl" class="w-32 h-32 rounded-full overflow-hidden border-4 border-gray-200">
                                    <img :src="previewUrl" alt="Preview" class="w-full h-full object-cover" />
                                </div>
                                <div v-else class="w-32 h-32 rounded-full bg-gray-100 border-4 border-dashed border-gray-300 flex items-center justify-center">
                                    <CameraIcon class="w-12 h-12 text-gray-400" />
                                </div>
                                <button v-if="previewUrl" @click="clearPreview"
                                    class="absolute -top-1 -right-1 w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition">
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <!-- File Input -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Chọn ảnh từ máy
                            </label>
                            <input 
                                ref="fileInput"
                                type="file" 
                                accept="image/*"
                                @change="handleFileSelect"
                                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-600 hover:file:bg-red-100"
                            />
                            <p class="text-xs text-gray-500 mt-1">Định dạng: JPEG, PNG, JPG, GIF. Tối đa 2MB.</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3">
                            <button @click="closeModal"
                                class="flex-1 py-2.5 px-4 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition font-medium text-sm">
                                Hủy
                            </button>
                            <button @click="handleUpload" :disabled="!selectedFile || uploading"
                                class="flex-1 py-2.5 px-4 rounded-lg font-medium text-sm transition flex items-center justify-center gap-2"
                                :class="selectedFile && !uploading 
                                    ? 'bg-[#D72D36] text-white hover:bg-red-700' 
                                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'">
                                <svg v-if="uploading" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ uploading ? 'Đang tải...' : 'Xác nhận' }}
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import { toast } from 'vue3-toastify'
import { XMarkIcon, CameraIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: Boolean,
    member: { type: Object, default: null },
    miniTournamentId: { type: [Number, String], required: true },
})

const emit = defineEmits(['update:modelValue', 'success', 'error'])

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
})

const fileInput = ref(null)
const selectedFile = ref(null)
const previewUrl = ref(null)
const uploading = ref(false)

const closeModal = () => {
    if (!uploading.value) {
        isOpen.value = false
        clearPreview()
    }
}

const clearPreview = () => {
    selectedFile.value = null
    previewUrl.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const handleFileSelect = (event) => {
    const file = event.target.files[0]
    if (!file) return

    if (file.size > 2 * 1024 * 1024) {
        toast.error('Kích thước ảnh không được vượt quá 2MB')
        clearPreview()
        return
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif']
    if (!allowedTypes.includes(file.type)) {
        toast.error('Định dạng ảnh không hợp lệ. Chỉ chấp nhận JPEG, PNG, JPG, GIF.')
        clearPreview()
        return
    }

    selectedFile.value = file
    previewUrl.value = URL.createObjectURL(file)
}

const handleUpload = async () => {
    if (!selectedFile.value || !props.member) return

    uploading.value = true

    try {
        const { modifyParticipantAvatar } = await import('@/service/miniParticipant.js')
        
        const response = await modifyParticipantAvatar(
            props.miniTournamentId,
            props.member.id,
            selectedFile.value
        )

        if (response && response.status) {
            toast.success('Cập nhật avatar thành công')
            emit('success', response.data)
            closeModal()
        } else {
            toast.error(response?.message || 'Có lỗi xảy ra')
            emit('error', response)
        }
    } catch (error) {
        console.error('Upload avatar error:', error)
        toast.error(error?.response?.data?.message || 'Có lỗi xảy ra khi tải ảnh lên')
        emit('error', error)
    } finally {
        uploading.value = false
    }
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.25s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

@keyframes scaleIn {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.animate-scaleIn {
    animation: scaleIn 0.25s ease;
}
</style>
