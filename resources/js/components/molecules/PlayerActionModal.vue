<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="isOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                @click.self="closeModal">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-sm max-h-[95vh] sm:max-h-[90vh] overflow-hidden animate-scaleIn flex flex-col">
                    <!-- Header -->
                    <header class="flex items-center justify-between p-4 border-b flex-shrink-0">
                        <h2 class="text-base sm:text-lg font-semibold text-gray-800">
                            {{ user?.user?.name }}
                        </h2>

                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5 sm:w-6 sm:h-6" />
                        </button>
                    </header>

                    <!-- Content -->
                    <section class="p-4 sm:p-6 text-center text-gray-700 leading-relaxed overflow-y-auto flex-1 text-sm sm:text-base">
                        Chọn hành động muốn thực hiện với người chơi này.
                    </section>

                    <!-- Footer -->
                    <footer class="p-4 border-t flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3 flex-shrink-0">
                        <button v-if="!isGuest" @click="emitViewProfile"
                            class="w-full sm:w-auto px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition">
                            Xem hồ sơ
                        </button>

                        <button @click="emitConfirm"
                            class="w-full sm:w-auto px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 transition">
                            Xác nhận người dùng
                        </button>
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { toast } from 'vue3-toastify'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: Boolean,
    user: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue', 'confirm', 'view-profile'])

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
})

const isGuest = computed(() => {
    if (!props.user) return false
    return props.user.is_guest || props.user.is_virtual || !props.user.user?.id
})

const closeModal = () => (isOpen.value = false)

const emitConfirm = () => {
    emit('confirm', props.user)
    closeModal()
}

const emitViewProfile = () => {
    if (isGuest.value) {
        toast.info('Thành viên ảo (khách vãng lai) không có hồ sơ cá nhân.')
        return
    }
    emit('view-profile', props.user)
    closeModal()
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
