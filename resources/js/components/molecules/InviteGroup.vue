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
                    <div class="flex items-center justify-between p-6">
                        <div class="flex items-center gap-3">
                            <h2 class="text-xl font-semibold text-gray-800">{{ title }}</h2>
                            <select
                                v-if="inviteType === 'staff'"
                                v-model="selectedRole"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="organizer">Organizer</option>
                                <option value="referee">Trọng tài</option>
                            </select>
                        </div>
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

                    <!-- Radius & Auto-Invite Controls (area tab only) -->
                    <div v-if="activeTab === 'area'" class="px-6 pb-4 space-y-3">
                        <!-- Source Toggle -->
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-700">Nguồn toạ độ:</span>
                            <div class="flex rounded-lg border border-gray-300 overflow-hidden text-xs">
                                <button
                                    @click="locationSource = 'venue'"
                                    :class="[
                                        'px-3 py-1.5 transition',
                                        locationSource === 'venue'
                                            ? 'bg-red-500 text-white'
                                            : 'bg-white text-gray-600 hover:bg-gray-50'
                                    ]"
                                >
                                    Theo sân đấu
                                </button>
                                <button
                                    @click="locationSource = 'user'"
                                    :class="[
                                        'px-3 py-1.5 transition',
                                        locationSource === 'user'
                                            ? 'bg-red-500 text-white'
                                            : 'bg-white text-gray-600 hover:bg-gray-50'
                                    ]"
                                >
                                    Theo vị trí tôi
                                </button>
                            </div>
                        </div>

                        <!-- Radius Slider -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-sm font-medium text-gray-700">Bán kính tìm kiếm</label>
                                <span class="text-sm font-semibold text-red-600">{{ localRadius }} km</span>
                            </div>
                            <input
                                type="range"
                                v-model.number="localRadius"
                                @change="onRadiusChange"
                                min="1"
                                max="50"
                                step="1"
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-red-600 custom-range"
                                :style="sliderStyle"
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
                                id="friend-only"
                                v-model="friendOnly"
                                class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500 cursor-pointer"
                            />
                            <label for="friend-only" class="text-sm font-medium text-gray-700 cursor-pointer select-none">
                                Chỉ bạn bè (friend only)
                            </label>
                        </div>

                        <!-- Auto-Invite Section -->
                        <div class="border border-gray-200 rounded-lg p-3 bg-gray-50 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">
                                    Cần mời:
                                    <span class="font-bold text-red-600">{{ neededCount }}</span> người
                                </span>
                                <button
                                    v-if="!isAutoInviting && !autoInviteResult"
                                    @click="handleAutoInvite"
                                    :disabled="neededCount <= 0"
                                    :class="[
                                        'px-4 py-2 rounded-lg text-sm font-semibold transition',
                                        neededCount > 0
                                            ? 'bg-red-600 text-white hover:bg-red-700'
                                            : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                    ]"
                                >
                                    Mời tự động
                                </button>
                            </div>

                            <!-- Auto-invite loading -->
                            <div v-if="isAutoInviting" class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <div class="animate-spin w-4 h-4 border-2 border-red-600 border-t-transparent rounded-full"></div>
                                    <span class="text-sm text-gray-600">
                                        Đang mời {{ autoInviteProgress }} người...
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div
                                        class="bg-red-600 h-2 rounded-full transition-all duration-300"
                                        :style="{ width: autoInviteProgressPercent + '%' }"
                                    ></div>
                                </div>
                            </div>

                            <!-- Auto-invite result -->
                            <div v-if="autoInviteResult" class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <CheckCircleIcon v-if="autoInviteResult.invited_count > 0" class="w-5 h-5 text-green-500" />
                                    <ExclamationCircleIcon v-else class="w-5 h-5 text-yellow-500" />
                                    <span class="text-sm font-medium text-gray-700">
                                        <template v-if="autoInviteResult.already_full">
                                            Kèo đã đủ số lượng người chơi.
                                        </template>
                                        <template v-else-if="autoInviteResult.invited_count > 0">
                                            Đã mời thành công
                                            <span class="font-bold text-green-600">{{ autoInviteResult.invited_count }}</span> người
                                            <template v-if="autoInviteResult.failed_count > 0">
                                                ({{ autoInviteResult.failed_count }} thất bại)
                                            </template>
                                            <template v-if="autoInviteResult.reached_max_radius">
                                                - Đã quét hết bán kính tối đa
                                            </template>
                                        </template>
                                        <template v-else>
                                            Không tìm thấy người chơi phù hợp.
                                        </template>
                                    </span>
                                </div>
                                <button
                                    @click="resetAutoInvite"
                                    class="text-xs text-blue-600 hover:underline"
                                >
                                    Mời lại
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 px-6 py-4">
                        <div :class="activeTab === 'club' ? '' : 'md:col-span-2'" class="relative flex items-center">
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="localSearchQuery"
                                @input="onSearch"
                                type="text"
                                placeholder="Tìm kiếm"
                                class="w-full pl-10 pr-4 py-2 h-10 border border-[#EDEEF2] bg-[#EDEEF2] rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <div v-if="activeTab === 'club'" class="flex items-center">
                            <select
                                v-model="selectedClub"
                                @change="$emit('change-club', selectedClub)"
                                class="w-full px-4 py-2 h-10 border border-[#EDEEF2] bg-[#EDEEF2] rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Chọn CLB</option>
                                <option v-for="club in clubs" :key="club.id" :value="club.id">
                                    {{ club.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- User List -->
                    <div
                        ref="scrollContainer"
                        class="flex-1 overflow-y-auto px-6 pb-6"
                        @scroll="onScroll"
                    >
                        <template v-if="filteredUsers.length === 0">
                            <div class="text-center text-gray-400 mt-10">
                                Không tìm thấy người dùng.
                            </div>
                        </template>

                        <template v-else>
                            <div
                                v-for="user in filteredUsers"
                                :key="user.id"
                                class="flex items-center gap-3 py-3 border-b border-gray-100 last:border-b-0 hover:bg-gray-50"
                            >
                                <!-- Avatar -->
                                <div class="relative">
                                    <div class="w-16 h-16 bg-red-300 rounded-full overflow-hidden">
                                        <img
                                            :src="user.avatar_url || defaultAvatar"
                                            @error="e => e.target.src = defaultAvatar"
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
                                        <div class="font-semibold text-gray-800 truncate" :title="user.name">
                                            {{ user.name }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-500 mt-0.5">
                                        <component :is="user.gender == 1 ? maleIcon : femaleIcon" class="w-4 h-4 flex-shrink-0" />
                                        <span class="truncate">{{ user.gender_text }}</span>
                                    </div>
                                </div>

                                <!-- Invite -->
                                <button
                                    @click="inviteUser(user.id)"
                                    :disabled="user.invited"
                                    :class="[
                                        'px-4 py-2 rounded-lg text-sm',
                                        user.invited
                                            ? 'bg-gray-100 text-gray-400'
                                            : 'bg-blue-500 text-white hover:bg-blue-600'
                                    ]"
                                >
                                    {{ user.invited ? 'Đã mời' : 'Mời bạn' }}
                                </button>
                            </div>

                            <!-- Loading more -->
                            <div v-if="isLoadingMore" class="text-center py-4 text-gray-400">
                                Đang tải thêm...
                            </div>

                            <div v-else-if="!hasMore" class="text-center py-4 text-gray-400">
                                Đã tải hết dữ liệu
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { XMarkIcon, MagnifyingGlassIcon, CheckCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { FreeMode, Mousewheel } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/free-mode'
import maleIcon from '@/assets/images/male.svg'
import femaleIcon from '@/assets/images/female.svg'
import { autoInviteArea } from '@/service/miniParticipant.js'

const defaultAvatar = '/images/default-avatar.png'
const modules = [FreeMode, Mousewheel]

const props = defineProps({
    modelValue: Boolean,
    data: Object,
    clubs: Array,
    searchQuery: String,
    activeScope: String,
    currentRadius: Number,
    currentClubId: [String, Number],
    isLoadingMore: Boolean,
    hasMore: {
        type: Boolean,
        default: true
    },
    title: {
        type: String,
        default: 'Mời nhóm'
    },
    inviteType: {
        type: String,
        default: 'participant'
    },
    selectedStaffRole: {
        type: String,
        default: 'organizer'
    },
    tournamentMaxPlayers: {
        type: Number,
        default: null
    },
    currentParticipantsCount: {
        type: Number,
        default: 0
    },
    competitionLocation: {
        type: Object,
        default: null
    },
    tournamentId: {
        type: [Number, String],
        default: null
    },
})

const emit = defineEmits([
    'update:modelValue',
    'invite',
    'change-scope',
    'change-club',
    'update:searchQuery',
    'update:radius',
    'load-more',
    'invite-complete',
])

const isOpen = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
})

const closeModal = () => (isOpen.value = false)

const tabs = [
    { id: 'all', label: 'Tất cả' },
    { id: 'club', label: 'Trong CLB của bạn' },
    { id: 'friends', label: 'Bạn bè của bạn' },
    { id: 'area', label: 'Trong khu vực' }
]

const selectedClub = ref(props.currentClubId || '')
const localSearchQuery = ref(props.searchQuery || '')
const localRadius = ref(props.currentRadius || 10)
const scrollContainer = ref(null)
const activeTab = ref('all')
const selectedRole = ref('organizer')
const friendOnly = ref(false)
const locationSource = ref('venue')
const isAutoInviting = ref(false)
const autoInviteResult = ref(null)
const autoInviteProgress = ref(0)

const neededCount = computed(() => {
    if (props.tournamentMaxPlayers == null) return 0
    const remaining = props.tournamentMaxPlayers - props.currentParticipantsCount
    return Math.max(0, remaining)
})

const autoInviteProgressPercent = computed(() => {
    if (neededCount.value <= 0) return 100
    return Math.min(100, Math.round((autoInviteProgress.value / neededCount.value) * 100))
})

watch(
  () => props.activeScope,
  (val) => {
    if (val) activeTab.value = val
  },
  { immediate: true }
)

watch(
  () => props.selectedStaffRole,
  (val) => {
    if (val) selectedRole.value = val
  },
  { immediate: true }
)

watch(() => props.searchQuery, v => (localSearchQuery.value = v))
watch(() => props.currentRadius, v => (localRadius.value = v))
watch(() => props.currentClubId, v => (selectedClub.value = v))

const sliderStyle = computed(() => {
    const percent = ((localRadius.value - 1) / 49) * 100
    return {
        background: `linear-gradient(to right,#dc2626 ${percent}%,#e5e7eb ${percent}%)`
    }
})

const filteredUsers = computed(() =>
    (props.data?.result || []).filter(u =>
        u.name.toLowerCase().includes(localSearchQuery.value.toLowerCase())
    )
)

const onSearch = () => emit('update:searchQuery', localSearchQuery.value)
const onRadiusChange = () => emit('update:radius', localRadius.value)

const inviteUser = id => {
    const user = props.data.result.find(u => u.id === id)
    if (user) {
        user.invited = true
        emit('invite', { ...user, role: selectedRole.value })
    }
}

const setActiveTab = tab => {
    activeTab.value = tab
    emit('change-scope', tab)
}

const onScroll = () => {
    const el = scrollContainer.value
    if (!el || props.isLoadingMore || !props.hasMore) return

    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 50) {
        emit('load-more')
    }
}

const convertLevel = user => {
    if (!user?.sports?.length) return '0'
    return Number.parseFloat(user?.sports[0]?.scores?.vndupr_score || 0).toFixed(1)
}

const handleAutoInvite = async () => {
    if (neededCount.value <= 0) return

    isAutoInviting.value = true
    autoInviteResult.value = null
    autoInviteProgress.value = 0

    // Build payload
    const payload = {
        friend_only: friendOnly.value,
        source: locationSource.value,
        radius_start: localRadius.value,
        radius_max: 200,
    }

    // Only send lat/lng when source is "user"
    if (locationSource.value === 'user') {
        const userLat = props.competitionLocation?.latitude
        const userLng = props.competitionLocation?.longitude
        if (userLat && userLng) {
            payload.lat = userLat
            payload.lng = userLng
        }
    }
    // When source is 'venue', backend will auto-fetch from competition_location

    try {
        const result = await autoInviteArea(props.tournamentId, payload)

        autoInviteProgress.value = result.invited_count || 0
        autoInviteResult.value = result
        emit('invite-complete', result)
    } catch (error) {
        autoInviteResult.value = {
            invited_count: 0,
            failed_count: 0,
            total_found: 0,
            reached_max_radius: false,
            already_full: false,
        }
        // eslint-disable-next-line no-console
        console.error('Auto invite error:', error)
    } finally {
        isAutoInviting.value = false
    }
}

const resetAutoInvite = () => {
    autoInviteResult.value = null
    autoInviteProgress.value = 0
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
</style>
