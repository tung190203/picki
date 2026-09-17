<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="isOpen"
                class="fixed inset-0 bg-black backdrop-blur-[1px] bg-opacity-50 flex items-center justify-center z-50 p-3 sm:p-4"
                @click.self="closeModal">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[95vh] sm:max-h-[90vh] sm:h-[90%] flex flex-col">
                    <!-- Header -->
                    <div class="flex items-center justify-between p-4 sm:p-6 flex-shrink-0">
                        <h2 class="text-base sm:text-xl font-semibold text-gray-800">{{ title }}</h2>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <XMarkIcon class="w-5 h-5 sm:w-6 sm:h-6" />
                        </button>
                    </div>
                    <!-- Search and Filter -->
                    <div class="grid grid-cols-1 gap-3 px-4 sm:px-6">
                        <div class="relative flex items-center">
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2" />
                            <input v-model="searchQuery" type="text" placeholder="Tìm kiếm" @input="onSearch"
                                class="w-full pl-10 pr-4 py-2 h-10 border border-[#EDEEF2] bg-[#EDEEF2] rounded focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder:text-[#838799]" />
                        </div>
                    </div>

                    <!-- User List -->
                    <div class="flex-1 overflow-y-auto px-4 sm:px-6 pb-4 sm:pb-6" v-if="filteredUsers.length > 0">
                        <div v-for="user in filteredUsers" :key="user.id"
                            class="flex items-center gap-3 py-3 border-b border-gray-100 last:border-b-0 cursor-pointer hover:bg-gray-50">
                            <!-- Avatar -->
                            <div class="relative flex-shrink-0">
                                <div
                                    class="w-16 h-16 bg-red-300 rounded-full flex items-center justify-center overflow-hidden">
                                    <img :src="user.user.avatar_url"
                                        alt="User Avatar" class="w-full h-full object-cover" />
                                </div>
                                <div
                                    class="absolute -bottom-1 -left-1 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center border border-1 border-white">
                                    <span class="text-white font-bold text-[9px]">{{ convertLevel(user.user?.sports[0]?.scores) }}</span>
                                </div>
                            </div>

                            <!-- User Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="font-semibold text-gray-800">{{user.user.full_name }}</span>
                                    <span :class="[
                                        'px-2 py-0.5 rounded text-xs font-medium',
                                        user.user.visibility === 'open'
                                            ? 'bg-blue-100 text-blue-700'
                                            : 'bg-green-100 text-green-700'
                                    ]">
                                        {{ user.user.visibility === 'open' ? 'Open' : 'Friend-Only' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 text-sm text-gray-500 mt-0.5">
                                    <component :is="user.user.gender == 1 ? MaleIcon : FemaleIcon" class="w-4 h-4" />
                                    <span>{{ user.user.gender_text }}</span>
                                </div>
                            </div>

                            <!-- Invite Button -->
                            <button @click="addUser(user.user.id)" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex-shrink-0 bg-blue-500 text-white hover:bg-blue-600">
                            Thêm vào đội
                            </button>
                        </div>
                    </div>
                    <div v-else class="flex-1 flex items-center justify-center px-6 pb-6">
                        <span class="text-gray-500">
                            {{ emptyText }}
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import { XMarkIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import MaleIcon from '@/assets/images/male.svg';
import FemaleIcon from '@/assets/images/female.svg';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    data: {
        type: Object,
        default: () => ({})
    },
    title: {
        type: String,
        default: 'Mời bạn bè'
    },
    emptyText: {
        type: String,
        default: 'Không tìm thấy bạn bè phù hợp với cài đặt giải đấu hiện tại.'
    }
})

const emit = defineEmits(['update:modelValue', 'add', 'search'])

const convertLevel = (level = []) => {
    if (!Array.isArray(level) || level.length === 0) return 0;
    const item = level.find(i => i.score_type === "vndupr_score");

    return item ? Number(item.score_value).toFixed(1) : 0;
};

const onSearch = () => {
  emit('search', searchQuery.value)
}

const isOpen = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

const closeModal = () => {
    isOpen.value = false
}

const searchQuery = ref('')

const filteredUsers = computed(() => {
    return props.data.filter(user =>
        user.user.full_name.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
})

const addUser = (userId) => {
    const user = props.data.find(u => u.user.id === userId)
    if(user) {
        emit('add', user)
    }
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