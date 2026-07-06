<template>
    <div class="min-h-screen bg-[#0f0f1a] flex flex-col">
        <!-- Header -->
        <div class="bg-[#1a1a2e] border-b border-white/10 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-red-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-white/50 text-xs font-medium uppercase tracking-wider">Xem điểm trực tiếp</p>
                        <p class="text-white font-semibold">{{ matchData?.name || 'Trận đấu' }}</p>
                    </div>
                </div>
                <button @click="exitFullscreen"
                    class="flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Thoát
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <div class="w-16 h-16 border-4 border-white/20 border-t-red-500 rounded-full animate-spin mx-auto mb-4"></div>
                <p class="text-white/60 text-sm">Đang tải dữ liệu...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex-1 flex items-center justify-center p-6">
            <div class="text-center max-w-md">
                <div class="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <p class="text-white font-semibold text-lg mb-2">Không tải được dữ liệu</p>
                <p class="text-white/60 text-sm mb-4">{{ error }}</p>
                <button @click="fetchMatchDetail"
                    class="px-6 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                    Thử lại
                </button>
            </div>
        </div>

        <!-- Match Content -->
        <div v-else class="flex-1 flex flex-col items-center justify-center px-6 py-8 gap-8">
            <!-- Team Headers -->
            <div class="flex items-center justify-between w-full max-w-5xl gap-4">
                <!-- Team 1 -->
                <div class="flex-1 flex flex-col items-center gap-3">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center shadow-lg">
                        <img v-if="team1Avatar" :src="team1Avatar" alt="" class="w-full h-full rounded-full object-cover" />
                        <span v-else class="text-white text-2xl font-bold">{{ getInitials(team1Name) }}</span>
                    </div>
                    <p class="text-white font-bold text-xl text-center leading-tight">{{ team1Name }}</p>
                </div>

                <!-- Score / VS -->
                <div class="flex flex-col items-center gap-2 shrink-0">
                    <p class="text-white/40 text-sm font-medium uppercase tracking-wider">Tổng điểm</p>
                    <div class="flex items-center gap-4">
                        <span class="text-6xl font-black text-white tabular-nums">{{ totalTeam1 }}</span>
                        <span class="text-white/30 text-4xl font-bold">-</span>
                        <span class="text-6xl font-black text-white tabular-nums">{{ totalTeam2 }}</span>
                    </div>
                </div>

                <!-- Team 2 -->
                <div class="flex-1 flex flex-col items-center gap-3">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center shadow-lg">
                        <img v-if="team2Avatar" :src="team2Avatar" alt="" class="w-full h-full rounded-full object-cover" />
                        <span v-else class="text-white text-2xl font-bold">{{ getInitials(team2Name) }}</span>
                    </div>
                    <p class="text-white font-bold text-xl text-center leading-tight">{{ team2Name }}</p>
                </div>
            </div>

            <!-- Sets Scoreboard -->
            <div class="w-full max-w-5xl">
                <div v-if="displaySets.length === 0" class="text-center py-12">
                    <p class="text-white/40 text-lg">Chưa có điểm</p>
                </div>

                <div v-else class="space-y-3">
                    <div v-for="(set, index) in displaySets" :key="index"
                        class="bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl px-6 py-4 transition-colors">
                        <div class="flex items-center justify-between">
                            <!-- Team 1 Score -->
                            <div class="flex-1 flex justify-end pr-8">
                                <span class="text-5xl font-black tabular-nums transition-all duration-300"
                                    :class="[
                                        set.winner === 'team1' ? 'text-green-400' : 'text-white',
                                        set.winner === 'team2' ? 'text-white/30' : ''
                                    ]">
                                    {{ set.team1Score }}
                                </span>
                            </div>

                            <!-- Set Label -->
                            <div class="flex flex-col items-center shrink-0 w-32">
                                <span class="text-white/40 text-xs font-medium uppercase tracking-wider">Set</span>
                                <span class="text-white font-bold text-lg">{{ index + 1 }}</span>
                                <span v-if="set.winner"
                                    class="text-xs font-semibold mt-1 px-2 py-0.5 rounded-full"
                                    :class="set.winner === 'team1' ? 'bg-blue-500/20 text-blue-400' : 'bg-red-500/20 text-red-400'">
                                    {{ set.winner === 'team1' ? team1ShortName : team2ShortName }}
                                </span>
                            </div>

                            <!-- Team 2 Score -->
                            <div class="flex-1 flex justify-start pl-8">
                                <span class="text-5xl font-black tabular-nums transition-all duration-300"
                                    :class="[
                                        set.winner === 'team2' ? 'text-green-400' : 'text-white',
                                        set.winner === 'team1' ? 'text-white/30' : ''
                                    ]">
                                    {{ set.team2Score }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Indicator -->
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div>
                <span class="text-white/60 text-sm font-medium">Cập nhật tự động</span>
                <span class="text-white/30 text-xs">|</span>
                <span class="text-white/40 text-xs">{{ lastUpdatedText }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as MatchesServices from '@/service/match.js'
import * as MiniMatchService from '@/service/miniMatch.js'

const route = useRoute()
const router = useRouter()

const matchData = ref(null)
const isLoading = ref(true)
const error = ref(null)
const lastUpdated = ref(null)

let pollInterval = null

/* ===================== FETCH ===================== */
const fetchMatchDetail = async () => {
    error.value = null
    try {
        const { matchType, matchId } = route.params
        if (!matchId || matchId === 'undefined') return
        let res
        if (matchType === 'tournament') {
            res = await MatchesServices.detailMatches(matchId)
        } else {
            res = await MiniMatchService.detailMiniMatches(matchId)
        }
        matchData.value = res
        lastUpdated.value = new Date()
    } catch (err) {
        error.value = err.response?.data?.message || 'Không thể tải dữ liệu trận đấu'
    } finally {
        isLoading.value = false
    }
}

/* ===================== FULLSCREEN ===================== */
const enterFullscreen = () => {
    const el = document.documentElement
    if (el.requestFullscreen) {
        el.requestFullscreen().catch(() => {})
    } else if (el.webkitRequestFullscreen) {
        el.webkitRequestFullscreen()
    }
}

const exitFullscreen = async () => {
    if (document.fullscreenElement || document.webkitFullscreenElement) {
        if (document.exitFullscreen) {
            document.exitFullscreen().catch(() => {})
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen().catch(() => {})
        }
    }
    router.back()
}

/* ===================== HELPERS ===================== */
const getInitials = (name) => {
    if (!name) return '??'
    const parts = String(name).trim().split(' ')
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

/* ===================== TEAM DATA ===================== */
const team1Name = computed(() => {
    const m = matchData.value
    if (!m) return 'Team 1'
    // Mini-tournament
    if (m.team1?.name) return m.team1.name
    if (m.home_team?.name) return m.home_team.name
    return 'Team 1'
})

const team2Name = computed(() => {
    const m = matchData.value
    if (!m) return 'Team 2'
    if (m.team2?.name) return m.team2.name
    if (m.away_team?.name) return m.away_team.name
    return 'Team 2'
})

const team1Avatar = computed(() => {
    const m = matchData.value
    if (!m) return null
    if (m.team1?.avatar) return m.team1.avatar
    if (m.home_team?.avatar) return m.home_team.avatar
    return null
})

const team2Avatar = computed(() => {
    const m = matchData.value
    if (!m) return null
    if (m.team2?.avatar) return m.team2.avatar
    if (m.away_team?.avatar) return m.away_team.avatar
    return null
})

const team1ShortName = computed(() => team1Name.value.substring(0, 12))
const team2ShortName = computed(() => team2Name.value.substring(0, 12))

/* ===================== SETS ===================== */
const displaySets = computed(() => {
    const m = matchData.value
    if (!m) return []

    // Mini-tournament: results_by_sets
    if (m.results_by_sets) {
        const t1Id = m.team1?.id
        const t2Id = m.team2?.id
        return Object.entries(m.results_by_sets).map(([key, arr]) => {
            if (!Array.isArray(arr)) return null
            const entry1 = arr.find(e => String(e.team?.id) === String(t1Id))
            const entry2 = arr.find(e => String(e.team?.id) === String(t2Id))
            const s1 = Number(entry1?.score ?? 0)
            const s2 = Number(entry2?.score ?? 0)
            let winner = null
            if (s1 > s2) winner = 'team1'
            else if (s2 > s1) winner = 'team2'
            return { team1Score: s1, team2Score: s2, winner }
        }).filter(Boolean)
    }

    // Tournament: legs + sets
    if (m.legs && Array.isArray(m.legs)) {
        const sets = []
        m.legs.forEach(leg => {
            if (leg.sets && typeof leg.sets === 'object') {
                Object.entries(leg.sets).forEach(([key, arr]) => {
                    if (!Array.isArray(arr)) return
                    const t1Id = m.home_team?.id
                    const t2Id = m.away_team?.id
                    const entry1 = arr.find(e => String(e.team_id) === String(t1Id))
                    const entry2 = arr.find(e => String(e.team_id) === String(t2Id))
                    const s1 = Number(entry1?.score ?? 0)
                    const s2 = Number(entry2?.score ?? 0)
                    let winner = null
                    if (s1 > s2) winner = 'team1'
                    else if (s2 > s1) winner = 'team2'
                    sets.push({ team1Score: s1, team2Score: s2, winner })
                })
            }
        })
        return sets
    }

    return []
})

/* ===================== TOTALS ===================== */
const totalTeam1 = computed(() => displaySets.value.reduce((s, set) => s + set.team1Score, 0))
const totalTeam2 = computed(() => displaySets.value.reduce((s, set) => s + set.team2Score, 0))

/* ===================== LAST UPDATED TEXT ===================== */
const lastUpdatedText = computed(() => {
    if (!lastUpdated.value) return 'Chưa cập nhật'
    const now = new Date()
    const diff = Math.floor((now - lastUpdated.value) / 1000)
    if (diff < 5) return 'Vừa xong'
    if (diff < 60) return `${diff}s trước`
    return `${Math.floor(diff / 60)}m trước`
})

/* ===================== LIFECYCLE ===================== */
onMounted(async () => {
    const { matchId } = route.params
    if (!matchId || matchId === 'undefined') {
        isLoading.value = false
        error.value = 'ID trận đấu không hợp lệ'
        return
    }
    await fetchMatchDetail()
    enterFullscreen()

    // Poll every 5 seconds
    pollInterval = setInterval(async () => {
        await fetchMatchDetail()
    }, 5000)
})

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval)
        pollInterval = null
    }
})
</script>
