<template>
    <Transition name="modal">
        <div v-if="modelValue" class="fixed inset-0 z-[10000] flex items-center justify-center p-4">
            <Transition name="backdrop">
                <div v-if="modelValue" class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close"></div>
            </Transition>
            <Transition name="modal-content">
                <div v-if="modelValue" class="relative bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full mx-auto z-10">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                        <h3 class="text-xl font-bold text-[#3E414C]">Thêm thành viên</h3>
                        <button @click="close" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-[#3E414C] mb-1">
                                Họ và tên <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.name" type="text" placeholder="Nhập tên thành viên (ví dụ: Anh Tuấn Guest)"
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[#3E414C] text-gray-900 font-medium placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors"
                                required />
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-[#3E414C] mb-1">
                                Link ảnh đại diện (Không bắt buộc)
                            </label>
                            <input v-model="form.avatar_url" type="url" placeholder="https://..."
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[#3E414C] text-gray-900 font-medium placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors" />
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-[#3E414C] mb-1">
                                Ghi chú
                            </label>
                            <textarea v-model="form.notes" rows="2" placeholder="Ghi chú thông tin phụ (khách vãng lai, sđt người bảo lãnh...)"
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[#3E414C] text-gray-900 font-medium placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors"></textarea>
                        </div>

                        <div class="pt-3 flex items-center gap-3">
                            <button type="button" @click="close"
                                class="flex-1 px-4 py-2.5 rounded-xl border border-gray-300 text-[#3E414C] font-semibold hover:bg-gray-50 transition-colors">
                                Hủy
                            </button>
                            <button type="submit" :disabled="isSubmitting || !form.name.trim()"
                                class="flex-1 px-4 py-2.5 rounded-xl bg-[#D72D36] text-white font-semibold hover:bg-[#c4252e] transition-colors disabled:opacity-50">
                                {{ isSubmitting ? 'Đang tạo...' : 'Tạo mới' }}
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
    name: '',
    avatar_url: '',
    notes: ''
})

const close = () => {
    emit('update:modelValue', false)
}

const submit = () => {
    if (!form.name.trim()) return
    emit('submit', { ...form })
}

watch(() => props.modelValue, (val) => {
    if (val) {
        form.name = ''
        form.avatar_url = ''
        form.notes = ''
    }
})
</script>
