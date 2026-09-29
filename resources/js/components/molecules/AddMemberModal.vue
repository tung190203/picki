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
                        <div v-for="item in filteredUsers" :key="entryKey(item)"
                            class="flex items-center gap-3 py-3 border-b border-gray-100 last:border-b-0 cursor-pointer hover:bg-gray-50">
                            <!-- Avatar -->
                            <div class="relative flex-shrink-0">
                                <div
                                    class="w-16 h-16 bg-red-300 rounded-full flex items-center justify-center overflow-hidden">
                                    <img :src="display(item).avatar_url || defaultAvatar"
                                        alt="User Avatar" class="w-full h-full object-cover" />
                                </div>
                                <div
                                    class="absolute -bottom-1 -left-1 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center border border-1 border-white">
                                    <span class="text-white font-bold text-[9px]">{{ convertLevel(display(item).sportsScores) }}</span>
                                </div>
                            </div>

                            <!-- User Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="font-semibold text-gray-800">{{ display(item).full_name }}</span>
                                    <span v-if="item.is_virtual" class="px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">
                                        Virtual
                                    </span>
                                    <span v-else :class="[
                                        'px-2 py-0.5 rounded text-xs font-medium',
                                        display(item).visibility === 'open'
                                            ? 'bg-blue-100 text-blue-700'
                                            : 'bg-green-100 text-green-700'
                                    ]">
                                        {{ display(item).visibility === 'open' ? 'Open' : 'Friend-Only' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 text-sm text-gray-500 mt-0.5">
                                    <component :is="display(item).gender == 1 ? MaleIcon : FemaleIcon" class="w-4 h-4" />
                                    <span>{{ display(item).gender_text || '—' }}</span>
                                </div>
                            </div>

                            <!-- Invite Button -->
                            <button @click="addUser(item)" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex-shrink-0 bg-blue-500 text-white hover:bg-blue-600">
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

const defaultAvatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2NjY2NjYyI+PHBhdGggZD0iTTEyIDEyYzIuMjEgMCA0LTEuNzkgNC00cy0xLjc5LTQtNC00LTQgMS43OS00IDQgMS43OSA0IDQgNHptMCAyYy0yLjY3IDAtOCAxLjM0LTggNHYyaDE2di0yYzAtMi42Ni01LjMzLTQtOC00eiIvPjwvc3ZnPg==';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    data: {
        type: Array,
        default: () => []
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

// Build a normalized user-shaped view from a participant entry:
// - real user       -> participant.user fields
// - guest           -> guest_name / guest_avatar; user fields absent
// - virtual member  -> guest_name / guest_avatar from club virtual member
const display = (item) => {
    const u = item?.user || {}
    const name = item?.is_virtual || (!item?.user && item?.guest_name)
        ? (item?.guest_name || '')
        : (u.full_name || '')
    return {
        full_name: name,
        avatar_url: u.avatar_url || item?.guest_avatar || null,
        gender: typeof u.gender === 'number' ? u.gender : null,
        gender_text: u.gender_text || null,
        visibility: u.visibility || (item?.is_virtual ? 'open' : 'open'),
        sportsScores: u?.sports?.[0]?.scores || [],
    }
}

const entryKey = (item) => {
    if (item?.is_virtual && item?.virtual_member_id) return `vm-${item.virtual_member_id}`
    if (item?.is_guest) return `g-${item.id ?? item.guest_name}`
    return `u-${item?.user?.id ?? item?.id}`
}

const filteredUsers = computed(() => {
    const q = searchQuery.value.toLowerCase()
    return (Array.isArray(props.data) ? props.data : []).filter(item => {
        const name = (display(item).full_name || '').toLowerCase()
        return name.includes(q)
    })
})

const addUser = (item) => {
    emit('add', item)
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