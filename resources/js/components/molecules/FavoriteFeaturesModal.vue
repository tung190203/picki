<template>
    <Transition name="modal">
        <div v-if="isOpen" class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" @click="close"></div>
            
            <div class="relative w-full max-w-xl bg-white dark:bg-[#161F33] rounded-2xl shadow-2xl border border-gray-100 dark:border-slate-800 p-6 z-10 overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100 flex items-center gap-2">
                            <span>Tùy chỉnh tính năng yêu thích</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                            Gỡ phím tắt ở từng vị trí cố định mà không làm lệch thứ tự các vị trí khác
                        </p>
                    </div>
                    <button @click="close" class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-white rounded-full hover:bg-gray-100 dark:hover:bg-slate-800 transition">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <div class="overflow-y-auto py-4 space-y-5 flex-1 pr-1">
                    <!-- Top 4 Fixed Position Slots Box (Khung 4 Vị Trí Cố Định) -->
                    <div class="bg-gray-50/80 dark:bg-[#1E293B] border border-gray-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-inner">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">
                                Vị trí phím tắt ghim ({{ activeCount }}/{{ maxSelection }})
                            </span>
                        </div>

                        <div class="grid grid-cols-4 gap-2 sm:gap-3">
                            <!-- 4 Fixed Slot Elements -->
                            <div 
                                v-for="(slotId, slotIdx) in slots" 
                                :key="`slot-${slotIdx}`"
                                class="relative min-h-[92px]"
                            >
                                <!-- If Slot is Occupied -->
                                <div 
                                    v-if="getFeatureObj(slotId)"
                                    class="relative h-full flex flex-col items-center text-center p-2 rounded-xl bg-white dark:bg-[#161F33] border border-gray-200 dark:border-slate-700 shadow-sm transition-all group"
                                >
                                    <button 
                                        @click.stop="removeSlotAt(slotIdx)"
                                        class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-slate-500 hover:bg-red-600 text-white flex items-center justify-center shadow-md transition-all z-10"
                                        title="Gỡ khỏi vị trí này"
                                    >
                                        <XMarkIcon class="w-3.5 h-3.5 stroke-[2.5]" />
                                    </button>
                                    <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-950/50 text-[#D72D36] dark:text-red-400 flex items-center justify-center mb-1.5 shadow-sm">
                                        <component :is="getFeatureObj(slotId).icon" class="w-5 h-5" />
                                    </div>
                                    <p class="text-[11px] font-semibold text-gray-800 dark:text-slate-200 leading-tight line-clamp-2">{{ getFeatureObj(slotId).label }}</p>
                                </div>

                                <!-- If Slot is Empty (Trống vị trí này) -->
                                <div 
                                    v-else
                                    class="h-full flex flex-col items-center justify-center text-center p-2 rounded-xl border-2 border-dashed border-gray-300 dark:border-slate-700 bg-white/40 dark:bg-[#161F33]/40 text-gray-400 dark:text-slate-500"
                                >
                                    <div class="w-8 h-8 rounded-full border border-dashed border-gray-300 dark:border-slate-600 flex items-center justify-center mb-1">
                                        <PlusIcon class="w-4 h-4" />
                                    </div>
                                    <span class="text-[10px]">Vị trí {{ slotIdx + 1 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- All Available Services Section (Tất cả tính năng) -->
                    <div>
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h4 class="text-sm font-bold text-gray-900 dark:text-slate-100">
                                Tất cả tính năng
                            </h4>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div 
                                v-for="item in ALL_FAVORITE_FEATURES" 
                                :key="item.id"
                                @click="toggleFeature(item.id)"
                                :class="[
                                    'flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer select-none',
                                    isSelected(item.id)
                                        ? 'border-red-200 dark:border-red-900/50 bg-red-50/30 dark:bg-red-950/20'
                                        : 'border-gray-200 dark:border-slate-700/60 bg-white dark:bg-[#1E293B] hover:border-gray-300 dark:hover:border-slate-600'
                                ]"
                            >
                                <div 
                                    :class="[
                                        'w-9 h-9 rounded-full flex items-center justify-center shrink-0 transition-colors',
                                        isSelected(item.id)
                                            ? 'bg-[#D72D36] text-white shadow-sm'
                                            : 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300'
                                    ]"
                                >
                                    <component :is="item.icon" class="w-5 h-5" />
                                </div>
                                
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-gray-800 dark:text-slate-200 leading-snug break-words">{{ item.label }}</p>
                                </div>

                                <!-- Heart / Status Indicator -->
                                <button type="button" class="shrink-0 p-1">
                                    <HeartIconSolid v-if="isSelected(item.id)" class="w-5 h-5 text-[#D72D36]" />
                                    <HeartIconOutline v-else class="w-5 h-5 text-gray-400 hover:text-red-500 transition-colors" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="pt-4 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between gap-3">
                    <button 
                        @click="resetToDefault"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-slate-300 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-slate-700 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-800 transition"
                    >
                        Đặt lại mặc định
                    </button>
                    <button 
                        @click="save"
                        :disabled="activeCount === 0"
                        class="px-6 py-2.5 bg-[#D72D36] hover:bg-[#c22830] disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-bold rounded-xl shadow-md transition"
                    >
                        Lưu cài đặt
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script>
import { 
    UserGroupIcon,
    PlusCircleIcon,
    MapPinIcon,
    ChartBarIcon,
    TrophyIcon,
    ArrowPathIcon,
    PuzzlePieceIcon,
    BellIcon,
    Cog6ToothIcon,
    UserIcon
} from '@heroicons/vue/24/outline'

export const ALL_FAVORITE_FEATURES = [
    { id: 'club', label: 'CLB', icon: UserGroupIcon, route: '/club' },
    { id: 'quick_match', label: 'Tạo trận đấu nhanh', icon: PlusCircleIcon, route: '/quick-match/create' },
    { id: 'map', label: 'Tìm sân', icon: MapPinIcon, route: '/map' },
    { id: 'leaderboard', label: 'Bảng xếp hạng', icon: ChartBarIcon, route: '/leaderboard' },
    { id: 'tournament_create', label: 'Tạo giải đấu', icon: TrophyIcon, route: '/tournament/create' },
    { id: 'pairing_wheel', label: 'Vòng quay ghép cặp', icon: ArrowPathIcon, route: '/pairing-wheel' },
    { id: 'group_draw_wheel', label: 'Vòng quay chia bảng', icon: PuzzlePieceIcon, route: '/group-draw-wheel' },
    { id: 'notifications', label: 'Thông báo', icon: BellIcon, route: '/notifications' },
    { id: 'settings', label: 'Cài đặt', icon: Cog6ToothIcon, route: '/settings' },
    { id: 'profile', label: 'Trang cá nhân', icon: UserIcon, route: '/profile' }
]

const DEFAULT_PINNED_SLOTS = ['club', 'quick_match', 'map', 'leaderboard']

export const loadSavedSlots = (user = null) => {
    if (user?.settings?.favorite_features && Array.isArray(user.settings.favorite_features) && user.settings.favorite_features.length > 0) {
        const res = [null, null, null, null]
        for (let i = 0; i < 4; i++) {
            res[i] = user.settings.favorite_features[i] || null
        }
        return res
    }
    if (user?.id) {
        try {
            const saved = localStorage.getItem(`vpick_favorite_features_${user.id}`)
            if (saved) {
                const parsed = JSON.parse(saved)
                if (Array.isArray(parsed) && parsed.length > 0) {
                    const res = [null, null, null, null]
                    for (let i = 0; i < 4; i++) {
                        res[i] = parsed[i] || null
                    }
                    return res
                }
            }
        } catch (e) {}
    }
    return [...DEFAULT_PINNED_SLOTS]
}

export const getSavedFavoriteFeatureIds = (user = null) => {
    const slots = loadSavedSlots(user)
    return slots.filter(Boolean)
}

export const getSavedFavoriteFeatures = (user = null) => {
    const slots = loadSavedSlots(user)
    return slots
        .map(id => ALL_FAVORITE_FEATURES.find(f => f.id === id))
        .filter(Boolean)
}
</script>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { 
    XMarkIcon, 
    PlusIcon,
    HeartIcon as HeartIconOutline
} from '@heroicons/vue/24/outline'
import { HeartIcon as HeartIconSolid } from '@heroicons/vue/24/solid'
import { useUserStore } from '@/store/auth.js'
import * as AuthService from '@/service/auth.js'

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    maxSelection: {
        type: Number,
        default: 4
    }
})

const emit = defineEmits(['update:isOpen', 'saved'])
const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)

const slots = ref(loadSavedSlots(getUser.value))

const syncSlotsFromUser = () => {
    slots.value = loadSavedSlots(getUser.value)
}

watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        syncSlotsFromUser()
    }
})

watch(getUser, (newUser) => {
    if (newUser) {
        syncSlotsFromUser()
    }
}, { immediate: true })

onMounted(() => {
    syncSlotsFromUser()
})

const getFeatureObj = (id) => {
    if (!id) return null
    return ALL_FAVORITE_FEATURES.find(f => f.id === id) || null
}

const activeCount = computed(() => {
    return slots.value.filter(Boolean).length
})

const isSelected = (id) => slots.value.includes(id)

const removeSlotAt = (index) => {
    slots.value[index] = null
}

const toggleFeature = (id) => {
    const existingIndex = slots.value.indexOf(id)
    if (existingIndex !== -1) {
        slots.value[existingIndex] = null
    } else {
        const emptyIdx = slots.value.indexOf(null)
        if (emptyIdx !== -1) {
            slots.value[emptyIdx] = id
        }
    }
}

const resetToDefault = () => {
    slots.value = [...DEFAULT_PINNED_SLOTS]
}

const close = () => {
    emit('update:isOpen', false)
}

const save = async () => {
    if (activeCount.value === 0) return

    const user = userStore.getUser

    // Cache per user id
    if (user?.id) {
        localStorage.setItem(`vpick_favorite_features_${user.id}`, JSON.stringify(slots.value))
    }

    // Persist to user settings in database for this specific user
    try {
        const res = await AuthService.updateUserSettings({ favorite_features: slots.value })
        if (res && res.settings) {
            if (user) {
                user.settings = res.settings
            }
        }
    } catch (err) {
        console.warn('Sync favorite features to DB error:', err)
    }

    emit('saved', getSavedFavoriteFeatures(userStore.getUser))
    close()
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
</style>
