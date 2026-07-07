<template>
    <div class="min-h-screen bg-[#1C2336] flex flex-col select-none">

        <!-- Header -->
        <div class="bg-[#1C2336] border-b border-[rgba(220,222,230,0.22)] px-4 py-3 shrink-0">
            <div class="flex items-center gap-4">
                <!-- PICKI Logo -->
                <div class="w-9 h-9 rounded-lg bg-red-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                    </svg>
                </div>

                <!-- Tournament Info (Center) -->
                <div class="flex-1 min-w-0 flex flex-col items-center">
                    <p class="text-white font-bold text-sm leading-tight text-center truncate max-w-md">{{ tournamentName }}</p>
                    <div class="flex items-center gap-1 mt-0.5 flex-wrap justify-center">
                        <span v-if="tournamentDateTime" class="text-[#838799] text-xs">{{ tournamentDateTime }}</span>
                        <span v-if="tournamentDateTime && tournamentLocation" class="text-[#838799] text-xs">•</span>
                        <span v-if="tournamentLocation" class="text-[#838799] text-xs truncate max-w-[150px]">{{ tournamentLocation }}</span>
                    </div>
                </div>

                <!-- Sponsor Logo -->
                <div class="w-14 h-9 flex items-center justify-center shrink-0 bg-[rgba(237,238,242,0.12)] rounded border border-[rgba(220,222,230,0.12)]">
                    <span class="text-[#838799] text-[10px] font-semibold text-center leading-tight">LOGO NHÀ TÀI TRỢ</span>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="flex-1 flex flex-col items-center justify-center gap-4">
            <div class="w-14 h-14 border-4 border-white/20 border-t-red-500 rounded-full animate-spin"></div>
            <p class="text-[#838799] text-sm">Đang tải dữ liệu...</p>
        </div>

        <!-- Error -->
        <div v-else-if="isError" class="flex-1 flex flex-col items-center justify-center p-6">
            <div class="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-[#838799]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </div>
            <p class="text-white font-semibold text-base mb-1">Không tìm thấy trận đấu</p>
            <p class="text-[#838799] text-sm text-center">{{ errorMessage }}</p>
        </div>

        <!-- Body: 3 Column Layout - Full Height -->
        <div v-else class="flex-1 flex items-stretch justify-center px-6 py-4">

            <!-- 3 Columns -->
            <div class="flex items-center justify-between gap-8 w-full max-w-7xl">

                <!-- ====== TEAM A (Left Column) ====== -->
                <div class="flex flex-col items-center gap-6 flex-1 max-w-[380px]">
                    <!-- Team A Label -->
                    <p class="text-[#838799] font-bold text-base tracking-wide text-center uppercase">{{ team1Name }}</p>

                    <!-- 2 Members Horizontal -->
                    <div class="flex flex-row items-center justify-center gap-12 w-full">
                        <div v-for="(member, idx) in team1Members" :key="member.id" class="flex flex-col items-center gap-3">
                            <!-- Name Above Avatar -->
                            <p class="text-white font-bold text-lg tracking-wide text-center leading-tight max-w-[100px]">{{ member.name }}</p>
                            <!-- Avatar -->
                            <div class="w-[96px] h-[96px] rounded-full overflow-hidden bg-[rgba(237,238,242,0.1)] flex items-center justify-center ring-2 ring-white/10">
                                <img v-if="member.avatar" :src="member.avatar" alt="" class="w-full h-full object-cover" />
                                <span v-else class="text-white font-bold text-2xl">{{ getInitials(member.name) }}</span>
                            </div>
                            <!-- DUPR Badge -->
                            <div class="px-3 py-1.5 rounded bg-[rgba(237,238,242,0.22)]">
                                <span class="text-[#838799] font-semibold text-xs tracking-wide">DUPR {{ getMemberVndupr(member) }}</span>
                            </div>
                            <!-- Serving Badge -->
                            <div v-if="isServingMember(1, idx)" class="px-3 py-1.5 rounded bg-[#FEFBEB] border border-[#F7D79B] flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-[#A97B35]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 8v4l3 3"/>
                                </svg>
                                <span class="text-[#A97B35] font-semibold text-sm tracking-wide">Đang giao bóng</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ====== SCORE CENTER (Middle Column) ====== -->
                <div class="flex flex-col items-center gap-4 shrink-0">
                    <!-- Score (Horizontal: 7 - 4) -->
                    <div class="flex items-center gap-4">
                        <span class="text-[72px] font-black leading-none tracking-tight text-[#4392E0] tabular-nums">{{ currentSetScore.team1 }}</span>
                        <span class="text-[#838799] text-5xl font-bold mt-1">-</span>
                        <span class="text-[72px] font-black leading-none tracking-tight text-white tabular-nums">{{ currentSetScore.team2 }}</span>
                    </div>

                    <!-- SET Label -->
                    <p class="text-[#838799] font-bold text-base tracking-wide">SET {{ currentSetNumber }}</p>

                    <!-- Set Tabs: S1 S2 S3 -->
                    <div class="flex items-center gap-2">
                        <template v-for="n in 3" :key="n">
                            <button
                                class="px-4 py-2 rounded text-sm font-bold tracking-wide transition-colors"
                                :class="n === currentSetNumber
                                    ? 'bg-[rgba(67,146,224,0.22)] text-[#4392E0]'
                                    : 'bg-[rgba(237,238,242,0.22)] text-[#838799]'"
                            >S{{ n }}</button>
                            <span v-if="n < 3" class="text-[#838799] text-sm">•</span>
                        </template>
                    </div>

                    <!-- Rules Line -->
                    <p v-if="ruleLine" class="text-[#838799] text-sm tracking-wide text-center">{{ ruleLine }}</p>
                </div>

                <!-- ====== TEAM B (Right Column) ====== -->
                <div class="flex flex-col items-center gap-6 flex-1 max-w-[380px]">
                    <!-- Team B Label -->
                    <p class="text-[#838799] font-bold text-base tracking-wide text-center uppercase">{{ team2Name }}</p>

                    <!-- 2 Members Horizontal -->
                    <div class="flex flex-row items-center justify-center gap-12 w-full">
                        <div v-for="(member, idx) in team2Members" :key="member.id" class="flex flex-col items-center gap-3">
                            <!-- Name Above Avatar -->
                            <p class="text-white font-bold text-lg tracking-wide text-center leading-tight max-w-[100px]">{{ member.name }}</p>
                            <!-- Avatar -->
                            <div class="w-[96px] h-[96px] rounded-full overflow-hidden bg-[rgba(237,238,242,0.1)] flex items-center justify-center ring-2 ring-white/10">
                                <img v-if="member.avatar" :src="member.avatar" alt="" class="w-full h-full object-cover" />
                                <span v-else class="text-white font-bold text-2xl">{{ getInitials(member.name) }}</span>
                            </div>
                            <!-- DUPR Badge -->
                            <div class="px-3 py-1.5 rounded bg-[rgba(237,238,242,0.22)]">
                                <span class="text-[#838799] font-semibold text-xs tracking-wide">DUPR {{ getMemberVndupr(member) }}</span>
                            </div>
                            <!-- Serving Badge -->
                            <div v-if="isServingMember(2, idx)" class="px-3 py-1.5 rounded bg-[#FEFBEB] border border-[#F7D79B] flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-[#A97B35]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 8v4l3 3"/>
                                </svg>
                                <span class="text-[#A97B35] font-semibold text-sm tracking-wide">Đang giao bóng</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-[#1C2336] border-t border-[rgba(220,222,230,0.22)] px-6 py-3 shrink-0">
            <div class="flex items-center justify-center gap-2">
                <div class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></div>
                <span class="text-[#838799] text-xs font-medium">Cập nhật tự động</span>
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
let echoChannel = null

// Tournament
const tournamentName = computed(() => matchData.value?.tournament?.name || 'Trận đấu')
const tournamentPoster = computed(() => matchData.value?.tournament?.poster_url || null)
const tournamentLocation = computed(() => {
    const t = matchData.value?.tournament
    if (!t) return null
    return t.location_name || t.location_address || null
})
const tournamentDateTime = computed(() => {
    const matchTime = matchData.value?.scheduled_at
    if (matchTime) {
        const d = new Date(matchTime)
        return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    }
    const t = matchData.value?.tournament
    if (t?.start_date) {
        const d = new Date(t.start_date)
        return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
    }
    return null
})

// Teams
const team1Name = computed(() => matchData.value?.team1?.name || 'Team 1')
const team2Name = computed(() => matchData.value?.team2?.name || 'Team 2')
const team1Members = computed(() => matchData.value?.team1?.members || [])
const team2Members = computed(() => matchData.value?.team2?.members || [])

// Sets
const sets = computed(() => matchData.value?.sets || [])
const currentSetNumber = computed(() => matchData.value?.current_set || (sets.value.length > 0 ? sets.value.length : 1))

const currentSetScore = computed(() => {
    const current = sets.value.find(s => s.set_number === currentSetNumber.value)
    if (current) return { team1: current.team1_score, team2: current.team2_score }
    return { team1: 0, team2: 0 }
})

// Status
const status = computed(() => {
    const d = matchData.value
    if (!d) return 'pending'
    if (d.live_status === 'playing' || d.live_status === 'live') return 'going_on'
    if (d.live_status === 'completed' || d.live_status === 'done' || d.live_status === 'finished') return 'completed'
    return 'pending'
})
const statusLabel = computed(() => MATCH_STATUS_LABEL[status.value] || 'Chờ đấu')

// Rules
const rulesText = computed(() => matchData.value?.rules || null)
const matchRules = computed(() => matchData.value?.match_rules || null)

// Rules line: Best of 3 • 11 điểm • Cách 2
const ruleLine = computed(() => {
    const mr = matchRules.value
    if (!mr) return null
    const bestOf = mr.sets_per_match ? `Best of ${mr.sets_per_match}` : null
    const points = mr.points_to_win_set ? `${mr.points_to_win_set} điểm` : null
    const winRuleMap = { 1: 'Cách 1', 2: 'Cách 2' }
    const winRule = winRuleMap[mr.winning_rule] ?? null
    return [bestOf, points, winRule].filter(Boolean).join(' • ')
})

// Serving member detection
const isServingMember = (teamIndex, memberIndex) => {
    const servingTeamId = matchData.value?.serving_team_id
    const servingPosition = matchData.value?.serving_position ?? 0
    if (teamIndex === 1) return servingTeamId === matchData.value?.team1?.id && servingPosition === memberIndex
    if (teamIndex === 2) return servingTeamId === matchData.value?.team2?.id && servingPosition === memberIndex
    return false
}

// Last updated
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
    const first = parts[0][0]
    const last = parts[parts.length - 1][0]
    return (first + last).toUpperCase()
}

const getMemberVndupr = (member) => {
    if (!member) return '—'
    const score = member.vndupr_score ?? member.vndupr ?? member.scores?.vndupr_score ?? null
    if (score == null) return '—'
    const num = Number.parseFloat(score)
    return Number.isNaN(num) ? '—' : num.toFixed(1)
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
    const { matchId, type } = route.params
    if (!matchId) {
        isLoading.value = false
        isError.value = true
        errorMessage.value = 'ID trận đấu không hợp lệ'
        return
    }
    await fetchLiveScore()

    // Echo real-time subscription for tournament matches
    if (type === 'tournament' && matchId && window.Echo) {
        echoChannel = window.Echo.private(`match.${matchId}`)
        echoChannel.listen('match.score_updated', (data) => {
            matchData.value = { ...matchData.value, ...data }
            lastUpdated.value = new Date()
        })
    }

    // Poll every 5 seconds as fallback
    pollInterval = setInterval(fetchLiveScore, 5000)
})

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval)
        pollInterval = null
    }
    if (echoChannel) {
        echoChannel.stopListening('match.score_updated')
        window.Echo.leave(`match.${route.params.matchId}`)
        echoChannel = null
    }
})
</script>
