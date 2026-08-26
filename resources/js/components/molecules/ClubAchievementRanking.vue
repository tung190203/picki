<template>
    <div class="space-y-6">
        <!-- Main Toggle: Điểm trình / Thành tích -->
        <div class="flex bg-gray-100 dark:bg-slate-800/90 border border-gray-200/80 dark:border-slate-700/80 p-1.5 rounded-xl max-w-md mx-auto shadow-inner">
            <button @click="setMainType('rating')"
                class="flex-1 py-2 text-center text-sm font-bold rounded-lg transition-all"
                :class="mainType === 'rating' ? 'bg-white dark:bg-[#1E293B] text-[#D72D36] dark:text-red-400 shadow-md dark:shadow-slate-950/60 border border-gray-100 dark:border-slate-700' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'">
                Điểm trình
            </button>
            <button @click="setMainType('achievement')"
                class="flex-1 py-2 text-center text-sm font-bold rounded-lg transition-all"
                :class="mainType === 'achievement' ? 'bg-white dark:bg-[#1E293B] text-[#D72D36] dark:text-red-400 shadow-md dark:shadow-slate-950/60 border border-gray-100 dark:border-slate-700' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'">
                Thành tích
            </button>
        </div>

        <!-- Mode 1: Điểm trình -->
        <div v-if="mainType === 'rating'">
            <!-- Render Rating Leaderboard -->
            <ClubRanking :top-three="topThree" :leaderboard="leaderboard" :meta="meta" :loading="loading"
                @page-change="$emit('page-change', $event)" />
        </div>

        <!-- Mode 2: Thành tích (Sao & Cúp) -->
        <div v-else class="space-y-6">
            <!-- Sub-tabs & Time Filter -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 border-b border-gray-100 dark:border-slate-800 pb-4">
                <!-- Sub-tabs: ⭐ Sao vs 🏆 Cúp -->
                <div class="flex items-center space-x-2 bg-gray-50 dark:bg-slate-800/80 p-1 rounded-xl w-full sm:w-auto border border-gray-200/50 dark:border-slate-700/60">
                    <button @click="setSubType('star')"
                        class="px-4 py-2 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5"
                        :class="subType === 'star' ? 'bg-[#D72D36] text-white shadow-sm' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700'">
                        Kèo đấu
                    </button>
                    <button @click="setSubType('cup')"
                        class="px-4 py-2 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5"
                        :class="subType === 'cup' ? 'bg-[#D72D36] text-white shadow-sm' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700'">
                        Giải đấu
                    </button>
                </div>

                <!-- Time Filter Chips -->
                <div class="flex items-center space-x-1.5 overflow-x-auto w-full sm:w-auto custom-scrollbar pb-1 sm:pb-0">
                    <button v-for="tf in timeFrames" :key="tf.id" @click="setTimeFrame(tf.id)"
                        class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors"
                        :class="timeFrame === tf.id ? 'bg-gray-800 dark:bg-red-600 text-white font-bold' : 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">
                        {{ tf.name }}
                    </button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="achievementLoading" class="py-12 text-center text-gray-400 dark:text-slate-400">
                Đang tải bảng xếp hạng thành tích...
            </div>

            <!-- Empty State -->
            <div v-else-if="!achievementList.length" class="py-12 text-center text-gray-400 dark:text-slate-400">
                <p>Chưa có thành tích nào trong khung thời gian đã chọn</p>
            </div>

            <template v-else>
                <!-- Top 3 Podium (Rút gọn huy chương: 🥇2 🥈1 🥉1) -->
                <div v-if="achievementTopThree.length" class="grid grid-cols-3 gap-2 sm:gap-4 items-end pt-4 pb-6">
                    <!-- Rank 2 (Nhì) -->
                    <div v-if="achievementTopThree[1]" class="flex flex-col items-center">
                        <div class="relative mb-2">
                            <img :src="achievementTopThree[1].avatar_url || defaultAvatar"
                                class="w-12 h-12 sm:w-16 sm:h-16 rounded-full object-cover border-2 border-gray-300 dark:border-slate-600 shadow-md" />
                            <span class="absolute -bottom-1 -right-1 bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-200 text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center border border-white dark:border-slate-800">2</span>
                        </div>
                        <h4 class="font-bold text-xs sm:text-sm text-gray-800 dark:text-slate-100 truncate max-w-[90px] sm:max-w-[120px] text-center">
                            {{ achievementTopThree[1].name }}
                        </h4>
                        <span v-if="achievementTopThree[1].is_virtual" class="text-[9px] bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 font-bold px-1 rounded mt-0.5">ẢO</span>
                        <div class="text-xs font-extrabold text-[#D72D36] dark:text-red-400 mt-1">
                            {{ achievementTopThree[1].total_points }} {{ subType === 'star' ? '⭐' : '🏆' }}
                        </div>
                        <div class="text-[10px] text-gray-500 dark:text-slate-400 mt-0.5 space-x-1">
                            <span>🥇{{ achievementTopThree[1].gold }}</span>
                            <span>🥈{{ achievementTopThree[1].silver }}</span>
                            <span>🥉{{ achievementTopThree[1].bronze }}</span>
                        </div>
                    </div>

                    <!-- Rank 1 (Nhất) -->
                    <div v-if="achievementTopThree[0]" class="flex flex-col items-center">
                        <div class="relative mb-2">
                            <img :src="achievementTopThree[0].avatar_url || defaultAvatar"
                                class="w-16 h-16 sm:w-20 sm:h-20 rounded-full object-cover border-2 border-amber-400 dark:border-amber-500 shadow-lg ring-4 ring-amber-100 dark:ring-amber-950/60" />
                            <span class="absolute -bottom-1 -right-1 bg-amber-400 text-white text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center border border-white dark:border-slate-800">1</span>
                        </div>
                        <h4 class="font-bold text-sm sm:text-base text-gray-900 dark:text-slate-100 truncate max-w-[100px] sm:max-w-[140px] text-center">
                            {{ achievementTopThree[0].name }}
                        </h4>
                        <span v-if="achievementTopThree[0].is_virtual" class="text-[9px] bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 font-bold px-1 rounded mt-0.5">ẢO</span>
                        <div class="text-sm font-extrabold text-[#D72D36] dark:text-red-400 mt-1">
                            {{ achievementTopThree[0].total_points }} {{ subType === 'star' ? '⭐' : '🏆' }}
                        </div>
                        <div class="text-[11px] text-gray-600 dark:text-slate-400 font-medium mt-0.5 space-x-1">
                            <span>🥇{{ achievementTopThree[0].gold }}</span>
                            <span>🥈{{ achievementTopThree[0].silver }}</span>
                            <span>🥉{{ achievementTopThree[0].bronze }}</span>
                        </div>
                    </div>

                    <!-- Rank 3 (Ba) -->
                    <div v-if="achievementTopThree[2]" class="flex flex-col items-center">
                        <div class="relative mb-2">
                            <img :src="achievementTopThree[2].avatar_url || defaultAvatar"
                                class="w-12 h-12 sm:w-16 sm:h-16 rounded-full object-cover border-2 border-amber-700/40 dark:border-amber-600/50 shadow-md" />
                            <span class="absolute -bottom-1 -right-1 bg-amber-700 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center border border-white dark:border-slate-800">3</span>
                        </div>
                        <h4 class="font-bold text-xs sm:text-sm text-gray-800 dark:text-slate-100 truncate max-w-[90px] sm:max-w-[120px] text-center">
                            {{ achievementTopThree[2].name }}
                        </h4>
                        <span v-if="achievementTopThree[2].is_virtual" class="text-[9px] bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 font-bold px-1 rounded mt-0.5">ẢO</span>
                        <div class="text-xs font-extrabold text-[#D72D36] dark:text-red-400 mt-1">
                            {{ achievementTopThree[2].total_points }} {{ subType === 'star' ? '⭐' : '🏆' }}
                        </div>
                        <div class="text-[10px] text-gray-500 dark:text-slate-400 mt-0.5 space-x-1">
                            <span>🥇{{ achievementTopThree[2].gold }}</span>
                            <span>🥈{{ achievementTopThree[2].silver }}</span>
                            <span>🥉{{ achievementTopThree[2].bronze }}</span>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard List Table -->
                <div class="divide-y divide-gray-100 dark:divide-slate-800/80 border border-gray-100 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
                    <div v-for="item in achievementList" :key="item.user_id || item.virtual_member_id || item.name"
                        class="flex items-center justify-between p-3.5 hover:bg-gray-50 dark:hover:bg-slate-800/60 transition-colors">
                        <div class="flex items-center space-x-3">
                            <span class="font-bold text-sm text-gray-400 dark:text-slate-400 w-6 text-center">{{ item.rank }}</span>
                            <img :src="item.avatar_url || defaultAvatar" class="w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-slate-700" />
                            <div>
                                <div class="flex items-center space-x-1.5">
                                    <span class="font-semibold text-sm text-gray-800 dark:text-slate-100">{{ item.name }}</span>
                                    <span v-if="item.is_virtual" class="text-[9px] bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 font-bold px-1.5 py-0.5 rounded">ẢO</span>
                                </div>
                                <div class="text-xs text-gray-400 dark:text-slate-400 space-x-2 mt-0.5">
                                    <span>🥇 {{ item.gold }}</span>
                                    <span>🥈 {{ item.silver }}</span>
                                    <span>🥉 {{ item.bronze }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="font-extrabold text-sm text-[#D72D36] dark:text-red-400 flex items-center gap-1">
                            {{ item.total_points }}
                            <span>{{ subType === 'star' ? '⭐' : '🏆' }}</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import ClubRanking from '@/components/molecules/ClubRanking.vue'
const defaultAvatar = 'https://picki.vn/images/default-avatar.png'
import axiosInstance from "@/utils/httpRequest.js";
import { API_ENDPOINT } from "@/constants/index.js";

const props = defineProps({
    clubId: {
        type: [Number, String],
        required: true
    },
    topThree: {
        type: Array,
        default: () => []
    },
    leaderboard: {
        type: Array,
        default: () => []
    },
    meta: {
        type: Object,
        default: () => ({})
    },
    loading: {
        type: Boolean,
        default: false
    }
})

const mainType = ref('rating') // 'rating' | 'achievement'
const subType = ref('star') // 'star' | 'cup'
const timeFrame = ref('month') // 'month' | 'quarter' | 'year' | 'all'

const timeFrames = [
    { id: 'month', name: 'Tháng này' },
    { id: 'quarter', name: 'Quý này' },
    { id: 'year', name: 'Năm này' },
    { id: 'all', name: 'Tất cả' }
]

const achievementList = ref([])
const achievementLoading = ref(false)

const achievementTopThree = computed(() => {
    return achievementList.value.slice(0, 3)
})

const fetchAchievementLeaderboard = async () => {
    if (mainType.value !== 'achievement') return
    achievementLoading.value = true
    try {
        const response = await axiosInstance.get(`${API_ENDPOINT.CLUB}/${props.clubId}/leaderboard`, {
            params: {
                type: 'achievement',
                sub_type: subType.value,
                time_frame: timeFrame.value
            }
        })
        achievementList.value = response.data?.data?.leaderboard || []
    } catch (e) {
        achievementList.value = []
    } finally {
        achievementLoading.value = false
    }
}

const setMainType = (type) => {
    mainType.value = type
    if (type === 'achievement' && !achievementList.value.length) {
        fetchAchievementLeaderboard()
    }
}

const setSubType = (st) => {
    subType.value = st
    fetchAchievementLeaderboard()
}

const setTimeFrame = (tf) => {
    timeFrame.value = tf
    fetchAchievementLeaderboard()
}

watch(() => props.clubId, () => {
    if (mainType.value === 'achievement') {
        fetchAchievementLeaderboard()
    }
})
</script>
