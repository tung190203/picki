<template>
    <Transition name="modal">
        <div v-if="modelValue" class="fixed inset-0 z-[10000] flex items-center justify-center p-3 sm:p-4">
            <Transition name="backdrop">
                <div v-if="modelValue" class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close"></div>
            </Transition>
            <Transition name="modal-content">
                <div v-if="modelValue" class="relative bg-white rounded-2xl shadow-2xl p-4 sm:p-6 max-w-md w-full mx-auto z-10 max-h-[95vh] sm:max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                        <h3 class="text-lg sm:text-xl font-bold text-[#3E414C]">Thêm CLB guest</h3>
                        <button @click="close" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <XMarkIcon class="w-5 h-5 sm:w-6 sm:h-6" />
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-[#3E414C] mb-1">
                                Họ và tên <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.guest_name" type="text" placeholder="Nhập tên khách (ví dụ: Anh Tuấn)"
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[#3E414C] text-gray-900 font-medium placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors"
                                required />
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-[#3E414C] mb-1">
                                Số điện thoại (Không bắt buộc)
                            </label>
                            <input v-model="form.guest_phone" type="text" placeholder="VD: 0987654321"
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[#3E414C] text-gray-900 font-medium placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors" />
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-[#3E414C] mb-1">
                                Ảnh đại diện (Không bắt buộc)
                            </label>
                            <div class="group mt-1 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer
                                    hover:border-[#D72D36] transition-colors p-4 text-center"
                                @click="fileInput.click()" @dragover.prevent @drop.prevent="handleDrop">

                                <input type="file" ref="fileInput" @change="handleFileChange" accept="image/*"
                                    class="hidden" />

                                <div v-if="previewUrl" class="flex flex-col items-center">
                                    <div class="relative w-24 h-24">
                                        <img :src="previewUrl" alt="Ảnh đại diện xem trước"
                                            class="w-24 h-24 object-cover rounded-full shadow-md" />
                                        <button type="button" @click.stop="clearAvatar"
                                            class="absolute -top-2 -right-2 bg-white rounded-full p-1 shadow-md hover:bg-[#D72D36] hover:text-white transition-colors">
                                            <XMarkIcon class="w-4 h-4 text-gray-700 hover:text-white" />
                                        </button>
                                    </div>
                                </div>
                                <div v-else>
                                    <ArrowUpTrayIcon class="w-10 h-10 text-gray-400 mx-auto group-hover:text-[#D72D36] transition-colors" />
                                    <p class="mt-1 text-sm text-gray-600">Kéo thả ảnh vào đây</p>
                                    <p class="text-xs text-gray-500">hoặc bấm để chọn file</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-[#3E414C] mb-1">
                                Trình độ ước tính (Không bắt buộc, 1.0–8.0)
                            </label>
                            <input v-model.number="form.estimated_level" type="number" min="1" max="8" step="0.5" placeholder="VD: 4.5"
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[#3E414C] text-gray-900 font-medium placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors" />
                        </div>

                        <div class="pt-3 flex items-center gap-3">
                            <button type="button" @click="close"
                                class="flex-1 px-4 py-2.5 rounded-xl border border-gray-300 text-[#3E414C] font-semibold hover:bg-gray-50 transition-colors">
                                Hủy
                            </button>
                            <button type="submit" :disabled="isSubmitting || !form.guest_name.trim()"
                                class="flex-1 px-4 py-2.5 rounded-xl bg-[#D72D36] text-white font-semibold hover:bg-[#c4252e] transition-colors disabled:opacity-50">
                                {{ isSubmitting ? 'Đang tạo...' : 'Tạo CLB guest' }}
                            </button>
                        </div>
                    </form>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { ArrowUpTrayIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    isSubmitting: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'submit'])

const form = reactive({
    guest_name: '',
    guest_phone: '',
    estimated_level: null
})

const fileInput = ref(null)
const previewUrl = ref(null)
const avatarFile = ref(null)

const close = () => {
    emit('update:modelValue', false)
}

const handleFileChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        setAvatar(file)
    }
}

const handleDrop = (event) => {
    const file = event.dataTransfer.files[0]
    if (file && file.type.startsWith('image/')) {
        if (fileInput.value) {
            const dt = new DataTransfer()
            dt.items.add(file)
            fileInput.value.files = dt.files
        }
        setAvatar(file)
    }
}

const setAvatar = (file) => {
    avatarFile.value = file
    if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
        URL.revokeObjectURL(previewUrl.value)
    }
    previewUrl.value = URL.createObjectURL(file)
}

const clearAvatar = () => {
    if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
        URL.revokeObjectURL(previewUrl.value)
    }
    previewUrl.value = null
    avatarFile.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const submit = () => {
    if (!form.guest_name.trim()) return

    if (avatarFile.value) {
        const payload = new FormData()
        payload.append('guest_name', form.guest_name.trim())
        if (form.guest_phone.trim()) {
            payload.append('guest_phone', form.guest_phone.trim())
        }
        payload.append('guest_avatar', avatarFile.value)
        if (form.estimated_level != null) {
            payload.append('estimated_level', Number(form.estimated_level))
        }
        emit('submit', payload)
    } else {
        const payload = {
            guest_name: form.guest_name.trim(),
        }
        if (form.guest_phone.trim()) {
            payload.guest_phone = form.guest_phone.trim()
        }
        if (form.estimated_level != null) {
            payload.estimated_level = Number(form.estimated_level)
        }
        emit('submit', payload)
    }
}

watch(() => props.modelValue, (val) => {
    if (val) {
        form.guest_name = ''
        form.guest_phone = ''
        form.estimated_level = null
        clearAvatar()
    }
})
</script>
