<template>
    <div class="min-h-screen bg-gradient-to-br from-red-50 via-white to-red-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto">
            
            <template v-if="isLoading">
                <div class="bg-white rounded-2xl shadow-xl p-12 text-center">
                    <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-red-700 mx-auto mb-4"></div>
                    <h2 class="text-xl font-semibold text-gray-700">Đang tải thông tin...</h2>
                </div>
            </template>

            <template v-else-if="isSuccess">
                <div class="bg-white rounded-2xl shadow-xl p-12 text-center animate-[fadeIn_0.4s_ease]">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-6">
                        <CheckBadgeIcon class="w-10 h-10 text-green-700" /> 
                    </div>

                    <h2 class="text-3xl font-bold text-green-700 mb-3">Xác Nhận Thành Công!</h2>
                    <p class="text-gray-600 mb-8">Kết quả trận đấu đã được ghi nhận.</p>

                    <button @click="router.push({ name: 'dashboard' })"
                        class="px-8 py-3 bg-red-700 text-white rounded-md font-semibold shadow-lg hover:shadow-xl transition-all"
                        :style="{ backgroundColor: '#D72D36' }">
                        Về Trang Chủ
                    </button>
                </div>
            </template>

            <template v-else-if="matchData">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="bg-red-700 px-6 py-4 text-center" :style="{ backgroundColor: '#D72D36' }">
                        <h2 class="text-2xl font-bold text-white mb-2">Xác Nhận Kết Quả</h2>
                        <p class="text-red-100 text-sm">Vui lòng kiểm tra thông tin trước khi xác nhận</p>
                    </div>

                    <div class="px-8 py-8">
                        <div class="text-center mb-6">
                            <span class="inline-block px-4 py-2 bg-red-100 text-red-700 rounded-full text-sm font-semibold">
                                {{ matchData.round_name }}
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-4 items-center mb-8">
                            <div class="text-center">
                                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6 shadow-lg">
                                    <div class="font-bold text-md mb-2">{{ matchData.home_team?.name }}</div>
                                    <div class="flex justify-center gap-2">
                                        <img v-for="member in matchData.home_team?.members" :key="member.id" 
                                             :src="member.avatar" 
                                             :alt="member.name"
                                             class="w-14 h-14 rounded-full border-2 border-white shadow-md"
                                             :title="member.name">
                                    </div>
                                </div>
                            </div>

                            <div class="text-center">
                                <div class="w-16 h-16 flex items-center justify-center mx-auto">
                                    <span class="text-2xl font-bold text-gray-600">VS</span>
                                </div>
                            </div>

                            <div class="text-center">
                                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6 shadow-lg">
                                    <div class="font-bold text-md mb-2">{{ matchData.away_team?.name }}</div>
                                    <div class="flex justify-center gap-2">
                                        <img v-for="member in matchData.away_team?.members" :key="member.id" 
                                             :src="member.avatar" 
                                             :alt="member.name"
                                             class="w-14 h-14 rounded-full border-2 border-white shadow-md"
                                             :title="member.name">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-6 mb-8">
                            <h3 class="text-center text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4">Kết Quả Các Set</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div v-for="(set, index) in formattedScores" :key="index" 
                                     class="bg-white rounded-lg p-4 shadow-sm border-2 border-gray-200 hover:border-green-400 transition-all">
                                    <div class="text-xs text-gray-500 font-semibold mb-2 text-center">SET {{ index + 1 }}</div>
                                    <div class="flex items-center justify-center gap-3">
                                        <span class="text-2xl font-bold" :class="set.home > set.away ? 'text-green-600' : 'text-red-400'">
                                            {{ set.home }}
                                        </span>
                                        <span class="text-gray-400 font-medium">-</span>
                                        <span class="text-2xl font-bold" :class="set.away > set.home ? 'text-green-600' : 'text-red-400'">
                                            {{ set.away }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- RALLY SCORING: kết quả các chặng theo mẫu mobile -->
                        <div v-if="hasRallyData && rallyStages.length" class="mb-6">
                            <!-- Header -->
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                <h3 class="text-sm font-bold text-gray-700">Rally Scoring — Kết quả các chặng</h3>
                            </div>

                            <!-- Total cumulative result -->
                            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-center">
                                <div class="text-xs font-bold text-green-700 uppercase tracking-wide mb-1">Kết quả chung cuộc</div>
                                <div class="flex items-center justify-center gap-3">
                                    <span class="text-3xl font-black" :class="rallyHomeWins ? 'text-green-600' : 'text-blue-600'">{{ rallyHomeCumulative }}</span>
                                    <span class="text-2xl font-light text-gray-400">–</span>
                                    <span class="text-3xl font-black" :class="rallyAwayWins ? 'text-green-600' : 'text-red-500'">{{ rallyAwayCumulative }}</span>
                                </div>
                                <div v-if="matchData.winner_id" class="mt-2 text-sm font-semibold text-green-700">
                                    {{ matchData.winner_id === matchData.home_team?.id ? matchData.home_team?.name : matchData.away_team?.name }} thắng
                                </div>
                            </div>

                            <!-- Stage cards -->
                            <div class="space-y-3">
                                <div v-for="stage in rallyStages" :key="stage.stage_no"
                                    class="border rounded-xl overflow-hidden transition-all"
                                    :class="stage.status === 'completed' ? 'border-green-300 bg-white' : 'border-gray-200 bg-gray-50'">

                                    <!-- Stage header -->
                                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-sm" :class="stage.status === 'completed' ? 'text-green-700' : 'text-blue-700'">
                                                Chặng {{ stage.stage_no }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-500 font-medium">mốc {{ stage.target_point }}</span>
                                            <span v-if="stage.status === 'completed'" class="text-xs font-bold px-2 py-0.5 rounded-full text-white" :class="stage.winner_team_id === matchData.home_team?.id ? 'bg-blue-600' : 'bg-red-500'">
                                                THẮNG CHẶNG
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Stage body -->
                                    <div class="p-4">
                                        <!-- Members row -->
                                        <div class="grid grid-cols-[1fr_auto_1fr] gap-3 items-center mb-3">
                                            <div class="flex flex-wrap gap-1">
                                                <span v-for="m in stage.members?.team_a" :key="m.user_id"
                                                    class="text-xs bg-blue-50 border border-blue-200 rounded px-2 py-1 font-medium text-gray-700">
                                                    {{ m.name }}
                                                </span>
                                            </div>
                                            <span class="text-xs font-bold text-gray-400">VS</span>
                                            <div class="flex flex-wrap gap-1 justify-end">
                                                <span v-for="m in stage.members?.team_b" :key="m.user_id"
                                                    class="text-xs bg-red-50 border border-red-200 rounded px-2 py-1 font-medium text-gray-700">
                                                    {{ m.name }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Scores -->
                                        <div class="flex items-center justify-center gap-3">
                                            <div class="text-center">
                                                <div class="w-14 h-14 rounded-xl border-2 flex items-center justify-center font-black text-2xl transition-colors"
                                                    :class="stage.winner_team_id === matchData.home_team?.id
                                                        ? 'border-green-400 bg-green-50 text-green-600'
                                                        : 'border-gray-200 bg-gray-50 text-gray-600'">
                                                    {{ stage.stage_score_a ?? 0 }}
                                                </div>
                                                <div class="text-[10px] text-gray-400 mt-1 font-semibold uppercase">điểm chặng</div>
                                            </div>
                                            <span class="text-xl font-bold text-gray-400 mt-2">–</span>
                                            <div class="text-center">
                                                <div class="w-14 h-14 rounded-xl border-2 flex items-center justify-center font-black text-2xl transition-colors"
                                                    :class="stage.winner_team_id === matchData.away_team?.id
                                                        ? 'border-green-400 bg-green-50 text-green-600'
                                                        : 'border-gray-200 bg-gray-50 text-gray-600'">
                                                    {{ stage.stage_score_b ?? 0 }}
                                                </div>
                                                <div class="text-[10px] text-gray-400 mt-1 font-semibold uppercase">điểm chặng</div>
                                            </div>
                                        </div>

                                        <!-- Cumulative label -->
                                        <div class="text-center mt-2">
                                            <div class="inline-block text-[11px] font-semibold text-gray-400 uppercase tracking-wide">
                                                Điểm luỹ kế: {{ stage.cumulative_score_a ?? 0 }} – {{ stage.cumulative_score_b ?? 0 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="matchData.winner_id" class="text-center mb-8">
                            <div class="inline-flex items-center gap-2 px-6 py-3 bg-green-100 border-2 border-green-300 rounded-full">
                                <CheckBadgeIcon class="w-6 h-6 text-green-600" />
                                <span class="font-bold text-green-800">
                                    Đội thắng: {{ matchData.winner_id === matchData.home_team?.id ? matchData.home_team?.name : matchData.away_team?.name }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <button @click="confirmMatch" :disabled="isConfirming"
                                class="flex-1 px-8 py-4 bg-red-700 text-white rounded-md font-bold text-lg shadow-lg hover:shadow-xl transition-all transform disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                :style="{ backgroundColor: '#D72D36', '--tw-ring-color': '#D72D36' /* Dùng custom style cho màu cụ thể */ }">
                                <span v-if="isConfirming" class="flex items-center justify-center gap-2">
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                                    Đang gửi...
                                </span>
                                <span v-else class="flex items-center justify-center gap-2">
                                    Xác Nhận Kết Quả
                                </span>
                            </button>
                            <button @click="cancelConfirmation" :disabled="isConfirming"
                                class="flex-1 px-8 py-4 bg-gray-200 text-gray-700 rounded-md font-bold text-lg shadow hover:shadow-md hover:bg-gray-300 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                Hủy Bỏ
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="bg-white rounded-2xl shadow-xl p-12 text-center">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full mb-6">
                        <ExclamationCircleIcon class="w-10 h-10 text-red-600" />
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-3">
                        Có Lỗi Xảy Ra
                    </h2>
                    <p class="text-gray-600 mb-8">
                        {{ statusMessage }}
                    </p>
                    <button @click="router.push({ name: 'dashboard' })"
                        class="px-8 py-3 bg-red-700 text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all transform hover:scale-105"
                        :style="{ backgroundColor: '#D72D36' }">
                        Về Trang Chủ
                    </button>
                </div>
            </template>

        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'
import * as MatchesServices from '@/service/match.js'
import { CheckBadgeIcon, ExclamationCircleIcon } from '@heroicons/vue/24/solid'
import { useMatchRallyDisplay } from '@/composables/useRallyScoringDisplay.js'

const route = useRoute()
const router = useRouter()
const matchId = route.params.id
const isLoading = ref(true)
const isConfirming = ref(false)
const matchData = ref(null)
const statusMessage = ref('')
const isSuccess = ref(false) 

// Hàm lấy dữ liệu trận đấu
const fetchMatchData = async () => {
    try {
        isLoading.value = true
        const response = await MatchesServices.detailMatches(matchId)
        matchData.value = response
        if (matchData.value && !matchData.value.is_verifiable) {
            statusMessage.value = 'Trận đấu đã được xác nhận hoặc không cần xác nhận.'
        }
    } catch (error) {
        console.error("Error fetching match data:", error)
        statusMessage.value = error.response?.data?.message || 'Không thể tải thông tin trận đấu. Vui lòng kiểm tra lại đường link.'
        matchData.value = null
    } finally {
        isLoading.value = false
    }
}

// Kết quả set
const formattedScores = computed(() => {
  if (!matchData.value?.legs?.length) return [];

  const leg = matchData.value.legs[0];
  const sets = leg.sets ?? {};
  const result = [];

  Object.values(sets).forEach(scores => {
    // scores === array [{team_id, score}, ...]

    const homeScore =
      scores.find(s => s.team_id === matchData.value.home_team?.id)?.score || 0;

    const awayScore =
      scores.find(s => s.team_id === matchData.value.away_team?.id)?.score || 0;

    result.push({
      home: homeScore,
      away: awayScore
    });
  });

  return result;
});

// Rally Scoring display
const { rallyStages, hasRallyData, lastCompletedStage } = useMatchRallyDisplay(computed(() => matchData.value))
const rallyHomeCumulative = computed(() => {
    if (!rallyStages.value?.length) return 0
    const last = [...rallyStages.value].sort((a, b) => a.stage_no - b.stage_no).at(-1)
    return last?.cumulative_score_a ?? 0
})

const rallyAwayCumulative = computed(() => {
    if (!rallyStages.value?.length) return 0
    const last = [...rallyStages.value].sort((a, b) => a.stage_no - b.stage_no).at(-1)
    return last?.cumulative_score_b ?? 0
})

const rallyHomeWins = computed(() => {
    if (!lastCompletedStage.value) return false
    return lastCompletedStage.value.winner_team_id === matchData.value?.home_team?.id
})

const rallyAwayWins = computed(() => {
    if (!lastCompletedStage.value) return false
    return lastCompletedStage.value.winner_team_id === matchData.value?.away_team?.id
})

// Confirm match
const confirmMatch = async () => {
    if (isConfirming.value) return

    try {
        isConfirming.value = true
        await MatchesServices.confirmResults(matchId)

        isSuccess.value = true 

        toast.success('Xác nhận kết quả trận đấu thành công!')
        
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể xác nhận trận đấu. Vui lòng thử lại.')
    } finally {
        isConfirming.value = false
    }
}

const cancelConfirmation = () => {
    router.push({ name: 'dashboard' })
}

onMounted(async () => {
    if (matchId) {
        await fetchMatchData()
    } else {
        isLoading.value = false
        statusMessage.value = 'Trận đấu cần xác nhận không tồn tại hoặc có lỗi xảy ra.'
    }
})
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: scale(.95); }
    to   { opacity: 1; transform: scale(1); }
}
</style>