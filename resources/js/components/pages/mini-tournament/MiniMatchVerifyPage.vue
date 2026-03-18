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

            <template v-else-if="miniMatchData">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="bg-red-700 px-6 py-4 text-center" :style="{ backgroundColor: '#D72D36' }">
                        <h2 class="text-2xl font-bold text-white mb-2">Xác Nhận Kết Quả</h2>
                        <p class="text-red-100 text-sm">Vui lòng kiểm tra thông tin trước khi xác nhận</p>
                    </div>

                    <div class="px-8 py-8">
                        <div class="text-center mb-6">
                            <span class="inline-block px-4 py-2 bg-red-100 text-red-700 rounded-full text-sm font-semibold">
                              {{ miniMatchData.name || '—' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-4 items-center mb-8">
                            <div class="text-center">
                                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6 shadow-lg">
                                    <div class="font-bold text-md mb-2">{{ miniMatchData.team1?.name }}</div>
                                    <div class="flex justify-center gap-2">
                                        <img v-for="member in miniMatchData.team1?.members" :key="member.id"
                                             :src="member.user.avatar_url"
                                             :alt="member.user.full_name"
                                             class="w-14 h-14 rounded-full border-2 border-white shadow-md"
                                             :title="member.user.full_name">
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
                                    <div class="font-bold text-md mb-2">{{ miniMatchData.team2?.name }}</div>
                                    <div class="flex justify-center gap-2">
                                        <img v-for="member in miniMatchData.team2?.members" :key="member.id"
                                             :src="member.user.avatar_url"
                                             :alt="member.user.full_name"
                                             class="w-14 h-14 rounded-full border-2 border-white shadow-md"
                                             :title="member.user.full_name">
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
                                        <span class="text-2xl font-bold" :class="set.team1 > set.team2 ? 'text-green-600' : 'text-red-400'">
                                            {{ set.team1 }}
                                        </span>
                                        <span class="text-gray-400 font-medium">-</span>
                                        <span class="text-2xl font-bold" :class="set.team2 > set.team1 ? 'text-green-600' : 'text-red-400'">
                                            {{ set.team2 }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="miniMatchData.team_win_id" class="text-center mb-8">
                            <div class="inline-flex items-center gap-2 px-6 py-3 bg-green-100 border-2 border-green-300 rounded-full">
                                <CheckBadgeIcon class="w-6 h-6 text-green-600" />
                                <span class="font-bold text-green-800">
                                    Đội thắng: {{ miniMatchData.team_win_id === miniMatchData.team1?.id ? miniMatchData.team1?.name : miniMatchData.team2?.name }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <button @click="confirmMatch" :disabled="isConfirming" v-if="miniMatchData.status === 'pending' && !miniMatchData.team_win_id"
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
import * as MiniMatchService from '@/service/miniMatch.js';
import { CheckBadgeIcon, ExclamationCircleIcon } from '@heroicons/vue/24/solid'

const route = useRoute()
const router = useRouter()
const miniMatchId = route.params.id
const isLoading = ref(true)
const isConfirming = ref(false)
const miniMatchData = ref(null)
const statusMessage = ref('')
const isSuccess = ref(false)

// Hàm lấy dữ liệu trận đấu
const fetchMiniMatchData = async () => {
    try {
        isLoading.value = true
        const response = await MiniMatchService.detailMiniMatches(miniMatchId)
        miniMatchData.value = response
        if (miniMatchData.value && miniMatchData.value.status !== 'pending') {
            statusMessage.value = 'Trận đấu đã được xác nhận hoặc không cần xác nhận.'
        }
    } catch (error) {
        console.error("Error fetching match data:", error)
        statusMessage.value = error.response?.data?.message || 'Không thể tải thông tin trận đấu. Vui lòng kiểm tra lại đường link.'
        miniMatchData.value = null
    } finally {
        isLoading.value = false
    }
}

// Kết quả set
const formattedScores = computed(() => {
    const result = []
    const data = miniMatchData.value

    if (!data || !data.results_by_sets) return result

    const team1Id = data.team1?.id
    const team2Id = data.team2?.id

    Object.values(data.results_by_sets).forEach(setArr => {
        if (!Array.isArray(setArr)) return
        let team1 = 0
        let team2 = 0
        setArr.forEach(item => {
            if (item.team?.id === team1Id) team1 = item.score
            if (item.team?.id === team2Id) team2 = item.score
        })

        result.push({ team1, team2 })
    })

    return result
})


// Confirm mini-match
const confirmMatch = async () => {
    if (isConfirming.value) return

    try {
        isConfirming.value = true
        await MiniMatchService.confirmResults(miniMatchId)

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
    if (miniMatchId) {
        await fetchMiniMatchData()
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
