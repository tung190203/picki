<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="isOpen"
                class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4"
                @click.self="closeModal"
            >
                <div class="bg-white rounded-lg shadow-xl w-full max-w-lg h-[90%] flex flex-col">

                    <!-- Header -->
                    <div class="flex items-center justify-between p-5 border-b">
                        <h2 class="text-lg font-semibold">Chọn người chơi</h2>
                        <button @click="closeModal">
                            <XMarkIcon class="w-6 h-6 text-gray-500" />
                        </button>
                    </div>

                    <!-- Search -->
                    <div class="p-4">
                        <div class="relative">
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"/>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Tìm kiếm người chơi"
                                class="w-full pl-10 pr-3 py-2 border rounded"
                            />
                        </div>
                    </div>

                    <!-- User list -->
                    <div class="flex-1 overflow-y-auto px-4">
                        <div
                            v-for="user in filteredUsers"
                            :key="user.id"
                            class="flex items-center gap-3 p-3 rounded cursor-pointer hover:bg-gray-50"
                            @click="selectUser(user)"
                        >
                            <img
                                :src="user.avatar_url || defaultAvatar"
                                class="w-12 h-12 rounded-full object-cover"
                            />

                            <div class="flex-1">
                                <div class="font-medium">{{ user.full_name }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ user.gender_text }}
                                </div>
                            </div>
                        </div>

                        <div v-if="filteredUsers.length === 0"
                             class="text-center text-gray-400 mt-10">
                            Không có người phù hợp
                        </div>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>


<script setup>
import { ref, computed } from 'vue'
import { XMarkIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const defaultAvatar = '/images/default-avatar.png'

const props = defineProps({
    modelValue: Boolean,
    users: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['update:modelValue', 'select'])

const isOpen = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
})

const closeModal = () => {
    isOpen.value = false
}

const search = ref('')

const filteredUsers = computed(() => {
    return props.users.filter(u =>
        u.full_name.toLowerCase().includes(search.value.toLowerCase())
    )
})

const selectUser = (user) => {
    emit('select', user)
    closeModal()
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

/* Swiper custom styles - Fix overflow */
.swiper-container {
    overflow: hidden;
    margin: 0;
    padding: 0;
}

:deep(.swiper-wrapper) {
    display: flex;
    align-items: center;
}

:deep(.swiper-slide) {
    width: auto !important;
    flex-shrink: 0;
}
</style>
