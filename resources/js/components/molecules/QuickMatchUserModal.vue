<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="isOpen"
                class="fixed inset-0 bg-black backdrop-blur-[1px] bg-opacity-50 flex items-center justify-center z-50 p-4"
                @click.self="closeModal"
            >
                <div class="bg-white rounded-lg shadow-xl w-full max-w-lg h-[90%] flex flex-col">

                    <!-- Header -->
                    <div class="flex items-center justify-between p-6 pb-4">
                        <h2 class="text-xl font-semibold text-gray-800">{{ title }}</h2>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="px-6 pb-4">
                        <Swiper
                            :slides-per-view="'auto'"
                            :space-between="8"
                            :freeMode="true"
                            :mousewheel="{ forceToAxis: true }"
                            :modules="modules"
                            class="swiper-container"
                        >
                            <SwiperSlide v-for="tab in tabs" :key="tab.id" class="!w-auto">
                                <button
                                    @click="setActiveTab(tab.id)"
                                    :class="[
                                        'px-4 py-2 rounded-full text-sm font-semibold cursor-pointer transition select-none whitespace-nowrap',
                                        activeTab === tab.id
                                            ? 'bg-red-500 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                    ]"
                                >
                                    {{ tab.label }}
                                </button>
                            </SwiperSlide>
                        </Swiper>
                    </div>

                    <!-- Area tab controls -->
                    <div v-if="activeTab === 'area'" class="px-6 pb-4 space-y-3">
                        <!-- Radius Slider -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-semibold text-red-600">{{ localRadius }} km</span>
                            </div>
                            <input
                                type="range"
                                v-model.number="localRadius"
                                @change="onRadiusChange"
                                min="1"
                                max="50"
                                step="1"
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-red-600"
                                :style="{ background: `linear-gradient(to right,#dc2626 ${radiusPercent}%,#e5e7eb ${radiusPercent}%)` }"
                            />
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>1 km</span>
                                <span>50 km</span>
                            </div>
                        </div>

                        <!-- Friend Only Toggle -->
                        <div class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="qm-friend-only"
                                v-model="friendOnly"
                                class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500 cursor-pointer"
                            />
                            <label for="qm-friend-only" class="text-sm font-medium text-gray-700 cursor-pointer select-none">
                                Chỉ bạn bè (friend only)
                            </label>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="px-6 pb-4">
                        <div class="relative flex items-center">
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="localSearchQuery"
                                @input="onSearch"
                                type="text"
                                placeholder="Tìm kiếm người chơi"
                                class="w-full pl-10 pr-4 py-2 h-10 border border-[#EDEEF2] bg-[#EDEEF2] rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>

                    <!-- User List -->
                    <div
                        ref="scrollContainer"
                        class="flex-1 overflow-y-auto px-6 pb-6"
                    >
                        <!-- Loading -->
                        <div v-if="isLoading" class="text-center text-gray-400 mt-10">
                            Đang tải danh sách...
                        </div>

                        <!-- Empty -->
                        <div v-else-if="displayedUsers.length === 0" class="text-center text-gray-400 mt-10">
                            Không tìm thấy người dùng.
                        </div>

                        <!-- List -->
                        <div v-else>
                            <div
                                v-for="user in displayedUsers"
                                :key="user.id"
                                class="flex items-center gap-3 py-3 border-b border-gray-100 last:border-b-0 hover:bg-gray-50"
                            >
                                <!-- Avatar -->
                                <div class="relative">
                                    <div class="w-16 h-16 bg-red-300 rounded-full overflow-hidden">
                                        <img
                                            :src="user.avatar_url || defaultAvatar"
                                            @error="e => e.target.src = defaultAvatar"
                                            alt="Avatar"
                                            class="w-full h-full object-cover"
                                        />
                                        <div
                                            class="absolute -bottom-1 -left-1 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center border border-white"
                                        >
                                            <span class="text-white text-[9px] font-bold">
                                                {{ convertLevel(user) }}
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Friend badge -->
                                    <div
                                        v-if="user.is_friend"
                                        class="absolute -top-1 -right-1 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center border border-white"
                                        title="Bạn bè"
                                    >
                                        <span class="text-white text-[8px] font-bold">F</span>
                                    </div>
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0 pr-2">
                                    <div class="w-full">
                                        <div class="font-semibold text-gray-800 truncate" :title="user.full_name">
                                            {{ user.full_name }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-500 mt-0.5">
                                        <component
                                            :is="user.gender == 1 ? maleIcon : femaleIcon"
                                            class="w-4 h-4 flex-shrink-0"
                                        />
                                        <span class="truncate">{{ user.gender_text }}</span>
                                    </div>
                                </div>

                                <!-- Add Button -->
                                <button
                                    @click="selectUser(user)"
                                    class="px-4 py-2 rounded-lg text-sm bg-blue-500 text-white hover:bg-blue-600 transition-colors"
                                >
                                    Chọn
                                </button>
                            </div>

                            <!-- Load more -->
                            <div v-if="isLoadingMore" class="text-center py-4 text-gray-400">
                                Đang tải thêm...
                            </div>
                            <div v-else-if="hasMore" class="text-center py-4">
                                <button @click="loadMore" class="text-sm text-blue-500 hover:underline">
                                    Tải thêm
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { XMarkIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { FreeMode, Mousewheel } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/free-mode'
import maleIcon from '@/assets/images/male.svg'
import femaleIcon from '@/assets/images/female.svg'
import { search } from '@/service/search.js'

const defaultAvatar = '/images/default-avatar.png'
const modules = [FreeMode, Mousewheel]

const props = defineProps({
    modelValue: Boolean,
    title: {
        type: String,
        default: 'Chọn người chơi'
    },
})

const emit = defineEmits(['update:modelValue', 'select'])

const isOpen = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
})

const closeModal = () => (isOpen.value = false)

const tabs = [
    { id: 'all', label: 'Tất cả' },
    { id: 'friends', label: 'Bạn bè của bạn' },
    { id: 'area', label: 'Trong khu vực' }
]

const activeTab = ref('all')
const localSearchQuery = ref('')
const localRadius = ref(10)
const isLoading = ref(false)
const isLoadingMore = ref(false)
const hasMore = ref(false)
const friendOnly = ref(false)
const currentPage = ref(1)
const allUsers = ref([])
const scrollContainer = ref(null)

const radiusPercent = computed(() => ((localRadius.value - 1) / 49) * 100)

const convertLevel = user => {
    if (!user?.sports?.length) return '0'
    return Number.parseFloat(user?.sports[0]?.scores?.vndupr_score || 0).toFixed(1)
}

const fetchUsers = async ({ page = 1, append = false } = {}) => {
    if (page === 1) isLoading.value = true
    else isLoadingMore.value = true

    try {
        const subTabMap = {
            all: 'all',
            friends: 'friends',
            area: 'all',
        }

        const res = await search({
            tab: 'user',
            sub_tab: subTabMap[activeTab.value] || 'all',
            keyword: localSearchQuery.value,
            per_page: 20,
            page,
            ...(activeTab.value === 'area' && localRadius.value ? { radius: localRadius.value } : {}),
        })

        const newUsers = res.data?.data || []

        if (append) {
            allUsers.value.push(...newUsers)
        } else {
            allUsers.value = newUsers
        }

        hasMore.value = newUsers.length === 20
    } catch (e) {
        console.error('Failed to load users', e)
        if (!append) allUsers.value = []
    } finally {
        isLoading.value = false
        isLoadingMore.value = false
    }
}

const displayedUsers = computed(() => {
    const kw = localSearchQuery.value.toLowerCase()
    if (!kw) return allUsers.value
    return allUsers.value.filter(u =>
        (u.full_name || '').toLowerCase().includes(kw)
    )
})

// Reset when modal opens
watch(isOpen, (val) => {
    if (val) {
        localSearchQuery.value = ''
        activeTab.value = 'all'
        localRadius.value = 10
        allUsers.value = []
        currentPage.value = 1
        hasMore.value = false
        fetchUsers({ page: 1 })
    }
})

// Reload when tab changes
watch(activeTab, () => {
    if (isOpen.value) {
        allUsers.value = []
        currentPage.value = 1
        hasMore.value = false
        fetchUsers({ page: 1 })
    }
})

let searchTimer = null
const onSearch = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        allUsers.value = []
        currentPage.value = 1
        hasMore.value = false
        fetchUsers({ page: 1 })
    }, 300)
}

const onRadiusChange = () => {
    allUsers.value = []
    currentPage.value = 1
    hasMore.value = false
    fetchUsers({ page: 1 })
}

const selectUser = (user) => {
    emit('select', user)
    closeModal()
}

const setActiveTab = (tab) => {
    activeTab.value = tab
}

const loadMore = () => {
    if (isLoadingMore.value || !hasMore.value) return
    currentPage.value++
    fetchUsers({ page: currentPage.value, append: true })
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
    transform: scale(0.95);
}
</style>
