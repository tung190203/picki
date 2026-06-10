<template>
    <div class="leaderboard-wrapper">
        <!-- Rank Pairing: tabs -->
        <div v-if="isRankPairing" class="flex gap-2 mb-4">
            <button v-for="group in ['A', 'B']" :key="group"
                @click="activeGroupTab = group"
                :class="[
                    'px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
                    activeGroupTab === group
                        ? 'bg-red-500 text-white'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                ]">
                BXH Hạng {{ group }}
            </button>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[#6B6F80] text-xs uppercase border-b">
                        <th class="pb-2 pr-2 w-10">Hạng</th>
                        <th class="pb-2">Người chơi</th>
                        <th class="pb-2 text-center w-16">T/TT</th>
                        <th class="pb-2 text-center w-16">TL%</th>
                        <th class="pb-2 text-center w-20">Hiệu số</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in currentLeaderboard" :key="row.participant_id"
                        class="border-b border-[#F0F0F5] hover:bg-gray-50 transition-colors">
                        <!-- Rank -->
                        <td class="py-3 pr-2">
                            <span v-if="row.rank === 1" class="text-lg">🥇</span>
                            <span v-else-if="row.rank === 2" class="text-lg">🥈</span>
                            <span v-else-if="row.rank === 3" class="text-lg">🥉</span>
                            <span v-else class="font-semibold text-[#6B6F80]">{{ row.rank }}</span>
                        </td>
                        <!-- Name -->
                        <td class="py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-[#3E414C]">{{ row.name }}</span>
                                <span v-if="row.player_group"
                                    :class="[
                                        'text-[10px] px-1.5 py-0.5 rounded font-semibold',
                                        getGroupColor(row.player_group)
                                    ]">
                                    {{ getGroupLabel(row.player_group) }}
                                </span>
                            </div>
                        </td>
                        <!-- Wins / Total -->
                        <td class="py-3 text-center">
                            <span class="font-semibold text-[#3E414C]">{{ row.wins }}</span>
                            <span class="text-[#9EA2B3]">/{{ row.total_matches }}</span>
                        </td>
                        <!-- Win Rate -->
                        <td class="py-3 text-center">
                            <span :class="getWinRateClass(row.win_rate)">{{ row.win_rate }}%</span>
                        </td>
                        <!-- Point Diff -->
                        <td class="py-3 text-center">
                            <span :class="getDiffClass(row.avg_point_diff)">
                                {{ row.avg_point_diff > 0 ? '+' : '' }}{{ row.avg_point_diff }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="currentLeaderboard.length === 0">
                        <td colspan="5" class="py-8 text-center text-[#9EA2B3] text-sm">
                            Chưa có dữ liệu thi đấu.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import { ref, computed } from 'vue'

export default {
    name: 'MiniTournamentLeaderboard',
    props: {
        leaderboard: {
            type: Array,
            default: () => []
        },
        groupALeaderboard: {
            type: Array,
            default: () => []
        },
        groupBLeaderboard: {
            type: Array,
            default: () => []
        },
        isRankPairing: {
            type: Boolean,
            default: false
        }
    },
    setup(props) {
        const activeGroupTab = ref('A')

        const currentLeaderboard = computed(() => {
            if (!props.isRankPairing) {
                return props.leaderboard
            }
            return activeGroupTab.value === 'A'
                ? (props.groupALeaderboard || [])
                : (props.groupBLeaderboard || [])
        })

        const getGroupColor = (group) => {
            const colors = {
                male: 'bg-blue-100 text-blue-700',
                female: 'bg-pink-100 text-pink-700',
                a: 'bg-indigo-100 text-indigo-700',
                b: 'bg-orange-100 text-orange-700',
            }
            return colors[group] || 'bg-gray-100 text-gray-600'
        }

        const getGroupLabel = (group) => {
            const labels = {
                male: 'Nam',
                female: 'Nữ',
                a: 'Hạng A',
                b: 'Hạng B',
            }
            return labels[group] || group
        }

        const getWinRateClass = (rate) => {
            if (rate >= 70) return 'font-semibold text-green-600'
            if (rate >= 40) return 'font-medium text-yellow-600'
            return 'text-red-500'
        }

        const getDiffClass = (diff) => {
            if (diff > 0) return 'font-semibold text-green-600'
            if (diff < 0) return 'text-red-500'
            return 'text-gray-400'
        }

        return {
            activeGroupTab,
            currentLeaderboard,
            getGroupColor,
            getGroupLabel,
            getWinRateClass,
            getDiffClass,
        }
    }
}
</script>
