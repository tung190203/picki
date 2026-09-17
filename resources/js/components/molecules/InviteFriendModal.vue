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
                            <input 
                                v-model="searchQuery" 
                                type="text" 
                                placeholder="Tìm kiếm" 
                                @input="onSearch"
                                class="w-full pl-10 pr-4 py-2 h-10 border border-[#EDEEF2] bg-[#EDEEF2] rounded focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder:text-[#838799]" 
                            />
                        </div>
                    </div>

                    <!-- User List with Infinite Scroll -->
                    <div 
                        ref="scrollContainer"
                        @scroll="handleScroll"
                        class="flex-1 overflow-y-auto px-4 sm:px-6 pb-4 sm:pb-6"
                        v-if="data && data.length > 0"
                    >
                        <div v-for="user in data" :key="user.id"
                            class="flex items-center gap-3 py-3 border-b border-gray-100 last:border-b-0 cursor-pointer hover:bg-gray-50">
                            <!-- Avatar -->
                            <div class="relative flex-shrink-0">
                                <div class="w-16 h-16 bg-red-300 rounded-full flex items-center justify-center overflow-hidden">
                                    <img :src="user.avatar_url || defaultAvatar" @error="e => e.target.src = defaultAvatar" alt="User Avatar" class="w-full h-full object-cover" />
                                </div>
                                <div class="absolute -bottom-1 -left-1 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center border border-1 border-white">
                                    <span class="text-white font-bold text-[9px]">{{ convertLevel(user) }}</span>
                                </div>
                            </div>

                            <!-- User Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="font-semibold text-gray-800">{{ user.full_name }}</span>
                                    <span :class="[
                                        'px-2 py-0.5 rounded text-xs font-medium',
                                        user.visibility === 'open'
                                            ? 'bg-blue-100 text-blue-700'
                                            : 'bg-green-100 text-green-700'
                                    ]">
                                        {{ user.visibility === 'open' ? 'Open' : 'Friend-Only' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 text-sm text-gray-500 mt-0.5">
                                    <component :is="user.gender == 1 ? MaleIcon : FemaleIcon" class="w-4 h-4" />
                                    <span>{{ user.gender_text }}</span>
                                </div>
                            </div>

                            <!-- Invite Button -->
                            <button 
                                @click="inviteUser(user.id)" 
                                :disabled="user.invited" 
                                :class="[
                                    'px-4 py-2 rounded-lg text-sm font-medium transition-colors flex-shrink-0',
                                    user.invited
                                        ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                        : 'bg-blue-500 text-white hover:bg-blue-600'
                                ]">
                                {{ user.invited ? 'Đã mời' : 'Mời bạn' }}
                            </button>
                        </div>
                        
                        <!-- Loading Indicator -->
                        <div v-if="isLoading" class="flex justify-center py-4">
                            <div class="flex items-center gap-2 text-gray-500">
                                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-sm">Đang tải...</span>
                            </div>
                        </div>

                        <!-- End Message -->
                        <div v-else-if="!hasMore && data.length > 0" class="text-center py-4 text-gray-400 text-sm">
                            Đã hiển thị tất cả
                        </div>
                    </div>
                    
                    <!-- Empty State -->
                    <div v-else class="flex-1 flex items-center justify-center px-6 pb-6">
                        <span class="text-gray-500">{{ emptyText }}</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { XMarkIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import MaleIcon from '@/assets/images/male.svg'
import FemaleIcon from '@/assets/images/female.svg'

const defaultAvatar = "/images/default-avatar.png";

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    data: {
        type: Array,
        default: () => []
    },
    hasMore: {
        type: Boolean,
        default: false
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

const emit = defineEmits(['update:modelValue', 'invite', 'loadMore', 'search'])

const searchQuery = ref('')
const isLoading = ref(false)
const scrollContainer = ref(null)

const convertLevel = (user, sportId = 1) => {
    if (!user?.sports || user.sports.length === 0) return '0'

    const sport = user.sports.find(s => s.sport_id === sportId)
    if (!sport?.scores?.vndupr_score) return '0'

    return parseFloat(sport.scores.vndupr_score).toFixed(1)
}

const handleScroll = (e) => {
    const { scrollTop, scrollHeight, clientHeight } = e.target
    
    // Khi scroll gần đến cuối (còn 150px)
    const nearBottom = scrollHeight - scrollTop - clientHeight < 150
    
    if (nearBottom && props.hasMore && !isLoading.value) {
        isLoading.value = true
        emit('loadMore')
    }
}

// Reset loading state khi data thay đổi
watch(() => props.data, () => {
    isLoading.value = false
}, { deep: true })

const onSearch = () => {
    emit('search', searchQuery.value)
}

const isOpen = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

const closeModal = () => {
    isOpen.value = false
    searchQuery.value = '' // Reset search khi đóng modal
}

const inviteUser = (userId) => {
    const user = props.data.find(u => u.id === userId)
    if (user && !user.invited) {
        user.invited = true
        emit('invite', user)
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

/* Custom scrollbar */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Loading spinner animation */
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
</style>