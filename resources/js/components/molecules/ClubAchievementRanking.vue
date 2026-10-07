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
            <ClubRanking :top-three="topThree" :leaderboard="leaderboard" :meta="meta" :loading="loading"
                @page-change="$emit('page-change', $event)" />
        </div>

        <!-- Mode 2: Thành tích (Sao & Cúp) -->
        <div v-else class="space-y-6">
            <!-- Sub-tabs & Time Filter -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 border-b border-gray-100 dark:border-slate-800 pb-4">
                <!-- Sub-tabs: Sao vs Cúp -->
                <div class="flex items-center space-x-2 bg-gray-50 dark:bg-slate-800/80 p-1 rounded-xl w-full sm:w-auto border border-gray-200/50 dark:border-slate-700/60">
                    <button @click="setSubType('star')"
                        class="px-4 py-2 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5"
                        :class="subType === 'star' ? 'bg-[#D72D36] text-white shadow-sm' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700'">
                        <span>Sao</span>
                        <span class="text-[10px] opacity-80">(Kèo đấu)</span>
                    </button>
                    <button @click="setSubType('cup')"
                        class="px-4 py-2 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5"
                        :class="subType === 'cup' ? 'bg-[#D72D36] text-white shadow-sm' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700'">
                        <span>Cúp</span>
                        <span class="text-[10px] opacity-80">(Giải đấu)</span>
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
                <!-- Top 3 Podium (clickable) -->
                <div v-if="achievementTopThree.length" class="grid grid-cols-3 gap-2 sm:gap-4 items-end pt-4 pb-6">
                    <!-- Rank 2 -->
                    <button v-if="achievementTopThree[1]" @click="openAchievementDetail(achievementTopThree[1])"
                        class="flex flex-col items-center text-center hover:scale-105 transition-transform">
                        <div class="relative mb-2">
                            <img :src="achievementTopThree[1].avatar_url || defaultAvatar"
                                class="w-12 h-12 sm:w-16 sm:h-16 rounded-full object-cover border-2 border-gray-300 dark:border-slate-600 shadow-md" />
                            <span class="absolute -bottom-1 -right-1 bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-200 text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center border border-white dark:border-slate-800">2</span>
                        </div>
                        <h4 class="font-bold text-xs sm:text-sm text-gray-800 dark:text-slate-100 truncate max-w-[90px] sm:max-w-[120px]">
                            {{ achievementTopThree[1].name }}
                        </h4>
                        <span v-if="achievementTopThree[1].is_guest || achievementTopThree[1].is_virtual" class="text-[9px] bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 font-bold px-1 rounded mt-0.5">CLB GUEST</span>
                        <div class="text-xs font-extrabold text-[#D72D36] dark:text-red-400 mt-1">
                            {{ achievementTopThree[1].total_points }} {{ subType === 'star' ? '⭐' : '🏆' }}
                        </div>
                        <div class="text-[10px] text-gray-500 dark:text-slate-400 mt-0.5 space-x-1">
                            <span>🥇{{ achievementTopThree[1].gold }}</span>
                            <span>🥈{{ achievementTopThree[1].silver }}</span>
                            <span>🥉{{ achievementTopThree[1].bronze }}</span>
                        </div>
                    </button>

                    <!-- Rank 1 -->
                    <button v-if="achievementTopThree[0]" @click="openAchievementDetail(achievementTopThree[0])"
                        class="flex flex-col items-center text-center hover:scale-105 transition-transform">
                        <div class="relative mb-2">
                            <img :src="achievementTopThree[0].avatar_url || defaultAvatar"
                                class="w-16 h-16 sm:w-20 sm:h-20 rounded-full object-cover border-2 border-amber-400 dark:border-amber-500 shadow-lg ring-4 ring-amber-100 dark:ring-amber-950/60" />
                            <span class="absolute -bottom-1 -right-1 bg-amber-400 text-white text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center border border-white dark:border-slate-800">1</span>
                        </div>
                        <h4 class="font-bold text-sm sm:text-base text-gray-900 dark:text-slate-100 truncate max-w-[100px] sm:max-w-[140px]">
                            {{ achievementTopThree[0].name }}
                        </h4>
                        <span v-if="achievementTopThree[0].is_guest || achievementTopThree[0].is_virtual" class="text-[9px] bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 font-bold px-1 rounded mt-0.5">CLB GUEST</span>
                        <div class="text-sm font-extrabold text-[#D72D36] dark:text-red-400 mt-1">
                            {{ achievementTopThree[0].total_points }} {{ subType === 'star' ? '⭐' : '🏆' }}
                        </div>
                        <div class="text-[11px] text-gray-600 dark:text-slate-400 font-medium mt-0.5 space-x-1">
                            <span>🥇{{ achievementTopThree[0].gold }}</span>
                            <span>🥈{{ achievementTopThree[0].silver }}</span>
                            <span>🥉{{ achievementTopThree[0].bronze }}</span>
                        </div>
                    </button>

                    <!-- Rank 3 -->
                    <button v-if="achievementTopThree[2]" @click="openAchievementDetail(achievementTopThree[2])"
                        class="flex flex-col items-center text-center hover:scale-105 transition-transform">
                        <div class="relative mb-2">
                            <img :src="achievementTopThree[2].avatar_url || defaultAvatar"
                                class="w-12 h-12 sm:w-16 sm:h-16 rounded-full object-cover border-2 border-amber-700/40 dark:border-amber-600/50 shadow-md" />
                            <span class="absolute -bottom-1 -right-1 bg-amber-700 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center border border-white dark:border-slate-800">3</span>
                        </div>
                        <h4 class="font-bold text-xs sm:text-sm text-gray-800 dark:text-slate-100 truncate max-w-[90px] sm:max-w-[120px]">
                            {{ achievementTopThree[2].name }}
                        </h4>
                        <span v-if="achievementTopThree[2].is_guest || achievementTopThree[2].is_virtual" class="text-[9px] bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 font-bold px-1 rounded mt-0.5">CLB GUEST</span>
                        <div class="text-xs font-extrabold text-[#D72D36] dark:text-red-400 mt-1">
                            {{ achievementTopThree[2].total_points }} {{ subType === 'star' ? '⭐' : '🏆' }}
                        </div>
                        <div class="text-[10px] text-gray-500 dark:text-slate-400 mt-0.5 space-x-1">
                            <span>🥇{{ achievementTopThree[2].gold }}</span>
                            <span>🥈{{ achievementTopThree[2].silver }}</span>
                            <span>🥉{{ achievementTopThree[2].bronze }}</span>
                        </div>
                    </button>
                </div>

                <!-- Leaderboard List Table (clickable rows) -->
                <div class="divide-y divide-gray-100 dark:divide-slate-800/80 border border-gray-100 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
                    <button v-for="item in achievementList" :key="item.user_id || item.id || item.name"
                        @click="openAchievementDetail(item)"
                        class="w-full flex items-center justify-between p-3.5 hover:bg-gray-50 dark:hover:bg-slate-800/60 transition-colors text-left">
                        <div class="flex items-center space-x-3">
                            <span class="font-bold text-sm text-gray-400 dark:text-slate-400 w-6 text-center">{{ item.rank }}</span>
                            <img :src="item.avatar_url || defaultAvatar" class="w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-slate-700" />
                            <div>
                                <div class="flex items-center space-x-1.5">
                                    <span class="font-semibold text-sm text-gray-800 dark:text-slate-100">{{ item.name }}</span>
                                    <span v-if="item.is_guest || item.is_virtual" class="text-[9px] bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 font-bold px-1.5 py-0.5 rounded">CLB GUEST</span>
                                </div>
                                <div class="text-xs text-gray-400 dark:text-slate-400 space-x-2 mt-0.5">
                                    <span>🥇 {{ item.gold }}</span>
                                    <span>🥈 {{ item.silver }}</span>
                                    <span>🥉 {{ item.bronze }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="font-extrabold text-sm text-[#D72D36] dark:text-red-400 flex items-center gap-1">
                                {{ item.total_points }}
                                <span>{{ subType === 'star' ? '⭐' : '🏆' }}</span>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </button>
                </div>
            </template>
        </div>

        <!-- Modal: Chi tiết sao/cúp của 1 member -->
        <transition name="fade">
            <div v-if="detailOpen" class="fixed inset-0 z-50 bg-black/60 dark:bg-black/80 flex items-end sm:items-center justify-center p-0 sm:p-4"
                @click.self="closeAchievementDetail">
                <div class="bg-white dark:bg-slate-900 w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl shadow-2xl max-h-[90vh] flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-800 shrink-0">
                        <div class="flex items-center gap-3">
                            <img v-if="detailData?.user" :src="detailData.user.avatar_url || defaultAvatar"
                                class="w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-slate-700" />
                            <div>
                                <h3 class="font-bold text-gray-900 dark:text-slate-100 line-clamp-1">
                                    {{ detailData?.user?.full_name || 'Chi tiết thành tích' }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-slate-400">
                                    Lịch sử Sao & Cúp tại {{ detailData?.club?.name }}
                                </p>
                            </div>
                        </div>
                        <button @click="closeAchievementDetail"
                            class="w-8 h-8 rounded-full flex items-center justify-center text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Loading -->
                    <div v-if="detailLoading" class="flex-1 flex items-center justify-center py-12 text-gray-400 dark:text-slate-400">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-500"></div>
                    </div>

                    <!-- Content -->
                    <div v-else class="flex-1 overflow-y-auto p-5 space-y-5">
                        <!-- Empty -->
                        <div v-if="!detailData || detailEvents.length === 0"
                            class="py-10 text-center text-gray-400 dark:text-slate-400">
                            <p class="font-semibold">Chưa có thành tích nào</p>
                            <p class="text-xs mt-1">Member này chưa đạt top 3 ở bất kỳ kèo/giải nào của CLB.</p>
                        </div>

                        <template v-else>
                            <!-- Summary -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/60 p-3">
                                    <div class="text-[11px] font-bold text-amber-700 dark:text-amber-300 uppercase tracking-wide">
                                        ⭐ Sao
                                    </div>
                                    <div class="mt-2 flex items-end gap-3 text-sm font-semibold text-amber-900 dark:text-amber-200">
                                        <span>🥇 {{ detailData.summary.star.gold }}</span>
                                        <span>🥈 {{ detailData.summary.star.silver }}</span>
                                        <span>🥉 {{ detailData.summary.star.bronze }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-amber-700/80 dark:text-amber-300/80">
                                        {{ detailData.summary.star.total_points }} điểm
                                    </div>
                                </div>
                                <div class="rounded-xl bg-yellow-50 dark:bg-yellow-950/30 border border-yellow-100 dark:border-yellow-900/60 p-3">
                                    <div class="text-[11px] font-bold text-yellow-700 dark:text-yellow-300 uppercase tracking-wide">
                                        🏆 Cúp
                                    </div>
                                    <div class="mt-2 flex items-end gap-3 text-sm font-semibold text-yellow-900 dark:text-yellow-200">
                                        <span>🥇 {{ detailData.summary.cup.gold }}</span>
                                        <span>🥈 {{ detailData.summary.cup.silver }}</span>
                                        <span>🥉 {{ detailData.summary.cup.bronze }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-yellow-700/80 dark:text-yellow-300/80">
                                        {{ detailData.summary.cup.total_points }} cúp
                                    </div>
                                </div>
                            </div>

                            <!-- Filter chips -->
                            <div class="flex items-center gap-2">
                                <button @click="detailFilter = 'all'"
                                    class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors"
                                    :class="detailFilter === 'all' ? 'bg-gray-800 dark:bg-red-600 text-white' : 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">
                                    Tất cả ({{ detailData.summary.total_events }})
                                </button>
                                <button @click="detailFilter = 'star'"
                                    class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors"
                                    :class="detailFilter === 'star' ? 'bg-amber-500 text-white' : 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">
                                    ⭐ Sao
                                </button>
                                <button @click="detailFilter = 'cup'"
                                    class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors"
                                    :class="detailFilter === 'cup' ? 'bg-yellow-500 text-white' : 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">
                                    🏆 Cúp
                                </button>
                            </div>

                            <!-- Event list -->
                            <div class="space-y-2">
                                <div v-for="ev in detailEvents" :key="`${ev.event_type}-${ev.event_id}`"
                                    class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 dark:bg-slate-800/60 border border-gray-100 dark:border-slate-800">
                                    <div class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-base font-bold"
                                        :class="medalBg(ev.medal)">
                                        {{ medalEmoji(ev.medal) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="font-semibold text-sm text-gray-900 dark:text-slate-100 truncate">
                                                {{ ev.event_name }}
                                            </h4>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold"
                                                :class="ev.is_cup ? 'bg-yellow-100 dark:bg-yellow-950/60 text-yellow-700 dark:text-yellow-300' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'">
                                                {{ ev.is_cup ? '🏆 Cúp' : '⭐ Sao' }}
                                            </span>
                                            <span v-if="ev.is_club_hosted" class="text-[10px] px-1.5 py-0.5 rounded-full font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                                                CLB tổ chức
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-slate-400 mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                            <span>{{ formatEventDate(ev.event_date) }}</span>
                                            <span v-if="ev.partner_names && ev.partner_names.length">• Cùng: {{ ev.partner_names.join(', ') }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="font-extrabold text-sm text-[#D72D36] dark:text-red-400">
                                            +{{ ev.points }}
                                        </div>
                                        <div class="text-[10px] uppercase font-bold"
                                            :class="medalText(ev.medal)">
                                            Hạng {{ ev.rank }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import dayjs from 'dayjs'
import 'dayjs/locale/vi'
import ClubRanking from '@/components/molecules/ClubRanking.vue'
const defaultAvatar = 'https://picki.vn/images/default-avatar.png'
import axiosInstance from "@/utils/httpRequest.js";
import { API_ENDPOINT } from "@/constants/index.js";

dayjs.locale('vi');

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
    { id: 'year', name: 'Năm nay' },
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

// ===== Modal chi tiết sao/cúp =====
const detailOpen = ref(false)
const detailLoading = ref(false)
const detailData = ref(null)
const detailFilter = ref('all')

const detailEvents = computed(() => {
    if (!detailData.value?.events) return []
    if (detailFilter.value === 'all') return detailData.value.events
    if (detailFilter.value === 'star') return detailData.value.events.filter(e => e.is_star)
    if (detailFilter.value === 'cup') return detailData.value.events.filter(e => e.is_cup)
    return detailData.value.events
})

const openAchievementDetail = async (item) => {
    if (!item?.user_id) {
        // Guest không có user_id thật → không mở modal
        return
    }
    detailOpen.value = true
    detailLoading.value = true
    detailData.value = null
    detailFilter.value = 'all'
    try {
        const response = await axiosInstance.get(
            `${API_ENDPOINT.CLUB}/${props.clubId}/members/${item.user_id}/achievements`
        )
        detailData.value = response.data?.data || null
    } catch (e) {
        detailData.value = null
    } finally {
        detailLoading.value = false
    }
}

const closeAchievementDetail = () => {
    detailOpen.value = false
    detailData.value = null
}

const medalEmoji = (medal) => {
    switch (medal) {
        case 'gold': return '🥇'
        case 'silver': return '🥈'
        case 'bronze': return '🥉'
        default: return '🏅'
    }
}

const medalBg = (medal) => {
    switch (medal) {
        case 'gold': return 'bg-amber-100 dark:bg-amber-950/60'
        case 'silver': return 'bg-gray-200 dark:bg-slate-700'
        case 'bronze': return 'bg-orange-100 dark:bg-orange-950/60'
        default: return 'bg-gray-100 dark:bg-slate-800'
    }
}

const medalText = (medal) => {
    switch (medal) {
        case 'gold': return 'text-amber-700 dark:text-amber-300'
        case 'silver': return 'text-gray-600 dark:text-slate-300'
        case 'bronze': return 'text-orange-700 dark:text-orange-300'
        default: return 'text-gray-500 dark:text-slate-400'
    }
}

const formatEventDate = (iso) => {
    if (!iso) return ''
    return dayjs(iso).format('DD/MM/YYYY')
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>