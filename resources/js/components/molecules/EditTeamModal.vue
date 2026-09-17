<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="isOpen"
                class="fixed inset-0 bg-black backdrop-blur-[1px] bg-opacity-50 flex items-center justify-center z-50 p-3 sm:p-4"
                @click.self="closeModal">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[95vh] sm:max-h-[90vh] h-fit flex flex-col">
                    <div class="flex items-center justify-between p-4 sm:p-6 flex-shrink-0">
                        <h2 class="text-base sm:text-xl font-semibold text-gray-800">Chỉnh sửa thông tin đội</h2>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <XMarkIcon class="w-5 h-5 sm:w-6 sm:h-6" />
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-4 sm:px-6 pb-4 sm:pb-6 mt-4">
                        <label class="block text-gray-700 font-medium">Tên đội:</label>
                        <input type="text" v-model="localData.name"
                            class="w-full mt-1 p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#D72D36]" />

                        <label class="block mt-6 text-gray-700 font-medium">Ảnh đội:</label>

                        <div class="group mt-2 border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer 
                                    hover:border-[#D72D36] transition-colors" @click="fileInput.click()"
                            @dragover.prevent @dragover="handleDragOver" @dragleave.prevent @drop="handleDrop">

                            <input type="file" ref="fileInput" @change="handleFileChange" accept="image/*"
                                class="hidden" />

                            <div v-if="previewUrl" class="flex flex-col items-center">
                                <div class="relative w-24 h-24">
                                    <img :src="previewUrl" alt="Ảnh đội xem trước"
                                        class="w-24 h-24 object-cover rounded shadow-md" />

                                    <button @click.stop="clearAvatar"
                                        class="absolute -top-2 -right-2 bg-white rounded-full p-1 shadow-md hover:bg-[#D72D36] hover:text-white transition-colors z-10">
                                        <XMarkIcon class="w-4 h-4 text-gray-700 hover:text-white" />
                                    </button>
                                </div>
                            </div>
                            <div v-else>
                                <ArrowUpTrayIcon
                                    class="w-10 h-10 text-gray-400 mx-auto group-hover:text-[#D72D36] transition-colors" />
                                <p class="mt-1 text-sm text-gray-600">Kéo thả ảnh vào đây</p>
                                <p class="text-xs text-gray-500">hoặc bấm để chọn file</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 sm:p-4 border-t flex flex-col-reverse sm:flex-row sm:justify-start gap-2 flex-shrink-0">
                        <button @click="handleUpdate" :disabled="isSaving || !localData.name"
                            :class="[(isSaving || !localData.name) ? 'bg-gray-400 cursor-not-allowed' : 'bg-[#D72D36] hover:bg-red-700']"
                            class="w-full sm:w-auto px-4 py-2 text-white rounded-lg transition-colors flex items-center justify-center">
                            <svg v-if="isSaving" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            {{ isSaving ? 'Đang lưu...' : 'Cập nhật' }}
                        </button>
                        <button @click="deleteTeam(props.data.id)"
                            class="w-full sm:w-auto px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            Xóa đội
                        </button>
                        <button @click="closeModal"
                            class="w-full sm:w-auto px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                            Hủy
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { ArrowUpTrayIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    data: {
        type: Object,
        default: () => ({ name: '', avatar: '', id: null })
    },
    isSaving: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'update-info', 'delete'])
const localData = ref({ name: '' })
const fileInput = ref(null)
const previewUrl = ref(null)
const newAvatarFile = ref(null)

const isOpen = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        localData.value = { ...props.data };
        previewUrl.value = props.data?.avatar || null;
        newAvatarFile.value = null;
    } else {
        if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
            URL.revokeObjectURL(previewUrl.value)
        }
    }
}, { immediate: true });

const closeModal = () => {
    isOpen.value = false;
}

const handleFileChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        newAvatarFile.value = file
        if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
            URL.revokeObjectURL(previewUrl.value)
        }
        previewUrl.value = URL.createObjectURL(file)
    }
}

const clearAvatar = () => {
    if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
        URL.revokeObjectURL(previewUrl.value)
    }
    previewUrl.value = null
    newAvatarFile.value = null
    if (fileInput.value) fileInput.value.value = ''
}

const handleDrop = (event) => {
    event.preventDefault()
    const file = event.dataTransfer.files[0]
    if (file && file.type.startsWith('image/')) {
        handleFileChange({ target: { files: [file] } })
    }
}

const handleDragOver = (event) => {
    event.preventDefault()
}

const handleUpdate = () => {
    let updatePayload = {
        id: props.data.id,
        name: localData.value.name,
    };
    if (newAvatarFile.value) {
        updatePayload.avatar = newAvatarFile.value; // File mới
    } else if (previewUrl.value === null) {
        updatePayload.avatar = null; // Đã xóa ảnh
    } else {
        updatePayload.avatar = props.data.avatar; // Giữ ảnh cũ
    }

    emit('update-info', updatePayload);
};

const deleteTeam = (teamId) => {
    emit('delete', teamId);
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
    transition: transform 0.3s ease;
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white {
    transform: scale(0.9);
}
</style>