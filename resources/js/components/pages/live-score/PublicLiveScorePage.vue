<template>
    <div class="min-h-screen bg-[#0f0f1a] flex flex-col">

        <!-- Header -->
        <div class="bg-[#1a1a2e] border-b border-white/10 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <!-- PICKI Logo -->
                    <div class="w-9 h-9 rounded-lg bg-red-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-white/50 text-xs font-medium uppercase tracking-wider">Xem điểm trực tiếp</p>
                        <p class="text-white font-semibold text-sm">{{ matchName }}</p>
                    </div>
                </div>

                <!-- Live Indicator -->
                <div v-if="!isError" class="flex items-center gap-2 bg-red-500/20 px-3 py-1.5 rounded-full">
                    <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div>
                    <span class="text-red-400 text-xs font-semibold">LIVE</span>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="flex-1 flex flex-col items-center justify-center gap-4">
            <div class="w-14 h-14 border-4 border-white/20 border-t-red-500 rounded-full animate-spin"></div>
            <p class="text-white/50 text-sm">Đang tải dữ liệu...</p>
        </div>

        <!-- Error -->
        <div v-else-if="isError" class="flex-1 flex flex-col items-center justify-center p-6">
            <div class="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </div>
            <p class="text-white/80 font-semibold text-base mb-1">Không tìm thấy trận đấu</p>
            <p class="text-white/40 text-sm text-center">{{ errorMessage }}</p>
        </div>

        <!-- Scoreboard -->
        <div v-else class="flex-1 flex flex-col items-center justify-center px-6 py-8 gap-6">

            <!-- Match Header -->
            <div class="text-center">
                <p class="text-white/30 text-xs font-medium uppercase tracking-wider">Trận {{ currentSetNumber }}</p>
            </div>

            <!-- Score Display -->
            <div class="flex items-center justify-center gap-6">
                <!-- Team 1 -->
                <div class="flex flex-col items-center gap-2">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-[#5b4fcb] to-[#8b7ff5] flex items-center justify-center shadow-lg ring-2 ring-white/10">
                        <img v-if="team1Avatar" :src="team1Avatar" alt="" class="w-full h-full rounded-full object-cover" />
                        <span v-else class="text-white font-bold text-lg">{{ getInitials(team1Name) }}</span>
                    </div>
                    <p class="text-white/90 font-semibold text-sm text-center max-w-[100px] leading-tight">{{ team1Name }}</p>
                </div>

                <!-- Score -->
                <div class="flex items-center gap-3">
                    <span class="text-5xl font-black text-white tabular-nums">{{ totalTeam1 }}</span>
                    <span class="text-white/30 text-3xl font-bold">-</span>
                    <span class="text-5xl font-black text-white tabular-nums">{{ totalTeam2 }}</span>
                </div>

                <!-- Team 2 -->
                <div class="flex flex-col items-center gap-2">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-[#c05b5b] to-[#f57c7c] flex items-center justify-center shadow-lg ring-2 ring-white/10">
                        <img v-if="team2Avatar" :src="team2Avatar" alt="" class="w-full h-full rounded-full object-cover" />
                        <span v-else class="text-white font-bold text-lg">{{ getInitials(team2Name) }}</span>
                    </div>
                    <p class="text-white/90 font-semibold text-sm text-center max-w-[100px] leading-tight">{{ team2Name }}</p>
                </div>
            </div>

            <!-- Match Status Badge -->
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="statusBadgeClass">
                    {{ statusLabel }}
                </span>
                <span class="text-white/30 text-xs">|</span>
                <span class="text-white/40 text-xs">{{ lastUpdatedText }}</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-[#1a1a2e] border-t border-white/10 px-6 py-3">
            <div class="flex items-center justify-center gap-2">
                <div class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></div>
                <span class="text-white/50 text-xs font-medium">Cập nhật tự động</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { getPublicLiveScore } from '@/service/publicLiveScore.js'
import { MATCH_STATUS_LABEL } from '@/constants/index.js'

const route = useRoute()

const matchData = ref(null)
const isLoading = ref(true)
const isError = ref(false)
const errorMessage = ref('')
const lastUpdated = ref(null)

let pollInterval = null

const matchName = computed(() => matchData.value?.name || 'Trận đấu')

const team1Name = computed(() => {
    const t = matchData.value?.team1
    return t?.name || 'Team 1'
})

const team2Name = computed(() => {
    const t = matchData.value?.team2
    return t?.name || 'Team 2'
})

const team1Avatar = computed(() => matchData.value?.team1?.avatar || null)
const team2Avatar = computed(() => matchData.value?.team2?.avatar || null)

const sets = computed(() => {
    if (!matchData.value?.sets) return []
    return matchData.value.sets
})

const totalTeam1 = computed(() => sets.value.reduce((s, set) => s + (set.team1_score ?? 0), 0))
const totalTeam2 = computed(() => sets.value.reduce((s, set) => s + (set.team2_score ?? 0), 0))

const currentSetNumber = computed(() => matchData.value?.current_set || (sets.value.length > 0 ? sets.value.length : 1))

const status = computed(() => {
    const d = matchData.value
    if (!d) return 'pending'
    if (d.live_status === 'playing' || d.live_status === 'live') return 'going_on'
    if (d.live_status === 'completed' || d.live_status === 'done') return 'completed'
    return 'pending'
})

const statusLabel = computed(() => MATCH_STATUS_LABEL[status.value] || 'Chờ đấu')

const statusBadgeClass = computed(() => {
    switch (status.value) {
        case 'going_on':
            return 'bg-red-500/20 text-red-400'
        case 'completed':
            return 'bg-green-500/20 text-green-400'
        default:
            return 'bg-white/10 text-white/60'
    }
})

const lastUpdatedText = computed(() => {
    if (!lastUpdated.value) return 'Chưa cập nhật'
    const diff = Math.floor((Date.now() - lastUpdated.value.getTime()) / 1000)
    if (diff < 5) return 'Vừa xong'
    if (diff < 60) return `${diff}s trước`
    return `${Math.floor(diff / 60)}m trước`
})

const getInitials = (name) => {
    if (!name) return '??'
    const parts = String(name).trim().split(' ')
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

const fetchLiveScore = async () => {
    try {
        const { type, matchId } = route.params
        if (!matchId) return
        const res = await getPublicLiveScore(type, matchId)
        if (res.success) {
            matchData.value = res.data
            lastUpdated.value = new Date()
            isError.value = false
        } else {
            isError.value = true
            errorMessage.value = res.message || 'Không thể tải dữ liệu'
        }
    } catch (err) {
        isError.value = true
        errorMessage.value = err?.response?.data?.message || 'Không thể tải dữ liệu trận đấu'
    } finally {
        isLoading.value = false
    }
}

onMounted(async () => {
    const { matchId } = route.params
    if (!matchId) {
        isLoading.value = false
        isError.value = true
        errorMessage.value = 'ID trận đấu không hợp lệ'
        return
    }
    await fetchLiveScore()
    pollInterval = setInterval(fetchLiveScore, 5000)
})

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval)
        pollInterval = null
    }
})
</script>
