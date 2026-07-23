<template>
    <div>
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div></div>
            <span class="text-xs text-gray-400 italic">Cập nhật lúc: {{ formattedUpdateTime }}</span>
        </div>

        <!-- Top 3 Section -->
        <div v-if="topThree.length > 0" class="flex justify-center items-end gap-6 mb-12 py-4">
            <!-- Rank 2 -->
            <div v-if="topThree[1]" class="flex flex-col items-center flex-1 max-w-[120px]">
                <span class="text-xl font-semibold text-[#4F80FF] mb-2">Top 2</span>
                <div class="relative mb-3">
                    <img :src="topThree[1].user.avatar_url || defaultAvatar" alt="Rank 2"
                        class="w-20 h-20 rounded-full border-2 border-[#4F80FF] shadow-lg object-cover" />
                    <div
                        class="absolute -bottom-24 left-1/2 -translate-x-1/2 px-3 py-1 bg-[#4F80FF] text-[10px] text-white rounded-full font-bold whitespace-nowrap">
                        Win {{ topThree[1].all_time_stats?.win_rate || 0 }}%
                    </div>
                </div>
                <div class="text-center">
                    <h3 class="font-semibold text-gray-800 text-lg leading-tight line-clamp-1">{{ topThree[1].user.full_name }}</h3>
                    <div class="text-[#4F80FF] font-bold text-xl">{{ topThree[1].vndupr_score }}</div>
                </div>
            </div>

            <!-- Rank 1 -->
            <div v-if="topThree[0]" class="flex flex-col items-center flex-1 max-w-[140px] -mt-6">
                <span class="text-xl font-semibold text-[#D72D36] mb-2">Top 1</span>
                <div class="relative mb-3">
                    <div class="absolute top-[80px] left-1/2 -translate-x-1/2">
                        <div
                            class="bg-[#D72D36] text-[10px] text-white px-3 py-1 rounded-full font-bold shadow-sm whitespace-nowrap">
                            Xuất sắc
                        </div>
                    </div>
                    <img :src="topThree[0].user.avatar_url || defaultAvatar" alt="Rank 1"
                        class="w-24 h-24 rounded-full border-2 border-[#D72D36] shadow-xl object-cover" />
                    <div
                        class="absolute -bottom-[103px] left-1/2 -translate-x-1/2 px-3 py-1 bg-[#D72D36] text-[10px] text-white rounded-full font-bold whitespace-nowrap">
                        Win {{ topThree[0].all_time_stats?.win_rate || 0 }}%
                    </div>
                </div>
                <div class="text-center">
                    <h3 class="font-semibold text-gray-800 text-xl leading-tight line-clamp-1">{{ topThree[0].user.full_name }}</h3>
                    <div class="text-[#D72D36] font-bold text-2xl">{{ topThree[0].vndupr_score }}</div>
                </div>
            </div>

            <!-- Rank 3 -->
            <div v-if="topThree[2]" class="flex flex-col items-center flex-1 max-w-[120px]">
                <span class="text-xl font-semibold text-[#FFB84F] mb-2">Top 3</span>
                <div class="relative mb-3">
                    <img :src="topThree[2].user.avatar_url || defaultAvatar" alt="Rank 3"
                        class="w-20 h-20 rounded-full border-2 border-[#FFB84F] shadow-lg object-cover" />
                    <div
                        class="absolute -bottom-24 left-1/2 -translate-x-1/2 px-3 py-1 bg-[#FFB84F] text-[10px] text-white rounded-full font-bold whitespace-nowrap">
                        Win {{ topThree[2].all_time_stats?.win_rate || 0 }}%
                    </div>
                </div>
                <div class="text-center">
                    <h3 class="font-semibold text-gray-800 text-lg leading-tight line-clamp-1">{{ topThree[2].user.full_name }}</h3>
                    <div class="text-[#FFB84F] font-bold text-xl">{{ topThree[2].vndupr_score }}</div>
                </div>
            </div>
        </div>

        <!-- Ranking List (>= 4 or Paginated) -->
        <div class="relative min-h-[300px]">
             <!-- Loading Overlay -->
            <div v-if="loading" 
                class="absolute inset-0 z-10 flex justify-center items-start pt-12 bg-white/60 backdrop-blur-[1px] transition-all duration-300 rounded-xl">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>

            <div :class="{ 'opacity-40 pointer-events-none': loading }" class="transition-opacity duration-300">
                <div class="space-y-4 mb-8">
                    <template v-if="leaderboard.length > 0">
                        <div v-for="item in leaderboard" :key="item.member_id"
                            class="flex items-center justify-between pb-4 border-b border-gray-100 last:border-0 rounded-xl transition-colors px-2">
                            <div class="flex items-center gap-4">
                                <span class="text-lg font-bold text-gray-400 w-6 text-center">{{ item.rank }}</span>
                                <div class="relative">
                                    <img :src="item.user.avatar_url || defaultAvatar" :alt="item.user.full_name"
                                        class="w-12 h-12 rounded-full object-cover border-2 border-white shadow-sm" />
                                    <div
                                        class="absolute -bottom-1 -left-1 w-5 h-5 bg-[#4F80FF] text-[9px] text-white rounded-full flex items-center justify-center font-bold border-2 border-white">
                                        {{ Number(item.vndupr_score).toFixed(1) }}
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-800 line-clamp-1">{{ item.user.full_name }}</h4>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-400">{{ item.all_time_stats?.matches_played || 0 }} Trận • Win {{ item.all_time_stats?.win_rate || 0 }}%</span>
                                        <!-- Score change badge -->
                                        <div v-if="item.all_time_stats?.score_change" :class="[
                                            'px-1.5 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-0.5',
                                            item.all_time_stats.score_change >= 0 ? 'bg-[#00B377] text-white' : 'bg-[#D72D36] text-white'
                                        ]">
                                            <component :is="item.all_time_stats.score_change >= 0 ? TriangleUp : TriangleDown" class="w-2 h-2" />
                                            {{ Math.abs(item.all_time_stats.score_change) }}
                                        </div>
                                        <!-- Weekly change badge -->
                                        <div v-if="item.weekly_change !== undefined && item.weekly_change !== null" :class="[
                                            'px-1.5 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-0.5',
                                            item.weekly_change > 0 ? 'bg-green-100 text-green-700' : item.weekly_change < 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500'
                                        ]">
                                            <svg v-if="item.weekly_change > 0" class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                            <svg v-else-if="item.weekly_change < 0" class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                            <span v-else>-</span>
                                            {{ Math.abs(item.weekly_change) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-[#3E414C] text-lg">{{ item.vndupr_score }}</div>
                            </div>
                        </div>
                    </template>

                    <div v-if="leaderboard.length === 0 && topThree.length === 0" class="text-center py-12 text-gray-400">
                        Chưa có dữ liệu xếp hạng
                    </div>
                </div>

                <!-- Pagination -->
                <Pagination :meta="meta" @page-change="changePage" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import TriangleUp from '@/assets/images/triangle_up.svg'
import TriangleDown from '@/assets/images/triangle_down.svg'
import Pagination from '@/components/molecules/Pagination.vue'
import dayjs from 'dayjs'

const props = defineProps({
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
        default: () => ({
            current_page: 1,
            last_page: 1,
            total: 0
        })
    },
    loading: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['page-change'])

const defaultAvatar = 'https://picki.vn/images/default-avatar.png'

const formattedUpdateTime = computed(() => {
    return dayjs().format('HH:mm')
})

const changePage = (page) => {
    if (page >= 1 && page <= props.meta.last_page) {
        emit('page-change', page)
    }
}
</script>
