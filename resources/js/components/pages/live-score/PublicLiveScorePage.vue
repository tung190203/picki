<template>
    <div class="min-h-screen bg-[#1C2336] flex flex-col select-none">

        <!-- Header -->
        <div class="bg-[#1C2336] border-b border-[rgba(220,222,230,0.22)] px-4 py-3 shrink-0">
            <div class="flex items-center gap-4">
                <!-- PICKI Logo -->
                <LogoUrl class="w-9 h-9 shrink-0" />

                <!-- Tournament Info (Center) -->
                <div class="flex-1 min-w-0 flex flex-col items-center">
                    <p class="text-white font-bold text-2xl leading-tight text-center truncate max-w-md">{{ tournamentName }}</p>
                    <div class="flex items-center gap-1 mt-0.5 flex-wrap justify-center">
                        <span v-if="tournamentDateTime" class="text-[#838799] text-xs">{{ tournamentDateTime }}</span>
                        <span v-if="tournamentDateTime && tournamentLocation" class="text-[#838799] text-xs">•</span>
                        <span v-if="tournamentLocation" class="text-[#838799] text-xs truncate max-w-[150px]">{{ tournamentLocation }}</span>
                    </div>
                </div>

                <!-- Sponsor Logo -->
                <div v-if="tournamentPoster" class="w-14 h-9 shrink-0 rounded border border-[rgba(220,222,230,0.12)] overflow-hidden">
                    <img :src="tournamentPoster" alt="poster" class="w-full h-full object-cover" />
                </div>
                <div v-else class="w-14 h-9 flex items-center justify-center shrink-0 bg-[rgba(237,238,242,0.12)] rounded border border-[rgba(220,222,230,0.12)]">
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
        <div v-else class="flex-1 flex flex-col px-6 py-4">
            <!-- Dashed border container -->
            <div class="flex-1 flex items-stretch justify-center border border-dashed border-[rgba(220,222,230,0.35)] my-2 px-6 py-4">
                <div class="flex items-center justify-between gap-8 w-full max-w-7xl">

                    <!-- ====== TEAM A (Left Column) ====== -->
                    <div class="flex flex-col items-center gap-4 flex-1 max-w-[380px]">
                        <!-- Team A Label -->
                        <p class="text-[#838799] font-bold text-base tracking-wide text-center uppercase">{{ team1Name }}</p>

                        <!-- Timeout Badge -->
                        <div v-if="team1TimeoutUsed" class="flex items-center gap-1.5 px-3 py-1 rounded bg-[rgba(255,0,54,0.15)] border border-[rgba(255,0,54,0.3)]">
                            <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-red-400 font-semibold text-xs tracking-wide">Đã timeout</span>
                        </div>

                        <!-- 2 Members Horizontal -->
                        <div class="flex flex-row items-start justify-center gap-10 w-full">
                            <div v-for="member in team1Members" :key="member.id" class="flex flex-col items-center gap-2">
                                <!-- Avatar -->
                                <div class="w-[88px] h-[88px] rounded-full overflow-hidden bg-[rgba(237,238,242,0.1)] flex items-center justify-center ring-2 ring-white/10">
                                    <img v-if="member.avatar" :src="member.avatar" alt="" class="w-full h-full object-cover" />
                                    <span v-else class="text-white font-bold text-2xl">{{ getInitials(member.name) }}</span>
                                </div>
                                <!-- Name Below Avatar -->
                                <p class="text-white font-bold text-base tracking-wide text-center leading-tight max-w-[100px]">{{ member.name }}</p>
                                <!-- DUPR Badge -->
                                <div class="px-3 py-1 rounded bg-[rgba(237,238,242,0.22)]">
                                    <span class="text-[#838799] font-semibold text-xs tracking-wide">DUPR {{ getMemberVndupr(member) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Serving Badge (under whole team) -->
                        <div v-if="isTeamServing(1)" class="px-3 py-1.5 rounded bg-[#FEFBEB] border border-[#F7D79B] flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-[#A97B35]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h13m0 0l-4-4m4 4l-4 4M21 4v16"/>
                            </svg>
                            <span class="text-[#A97B35] font-semibold text-sm tracking-wide">Đang giao bóng</span>
                        </div>
                    </div>

                    <!-- ====== SCORE CENTER (Middle Column) ====== -->
                    <div class="flex flex-col items-center gap-3 shrink-0">
                        <!-- Score (Horizontal: 7 - 4) -->
                        <div class="flex items-center gap-4">
                            <span class="text-[72px] font-black leading-none tracking-tight text-[#4392E0] tabular-nums">{{ currentSetScore.team1 }}</span>
                            <span class="text-[#838799] text-5xl font-bold mt-1">-</span>
                            <span class="text-[72px] font-black leading-none tracking-tight text-[#E53E3E] tabular-nums">{{ currentSetScore.team2 }}</span>
                        </div>

                        <!-- SET Label -->
                        <p class="text-[#838799] font-bold text-base tracking-wide">SET {{ currentSetNumber }}</p>

                        <!-- Set Tabs: S1: 11-5 / S2: - / S3: - -->
                        <div class="flex items-center gap-2">
                            <template v-for="n in 3" :key="n">
                                <button
                                    class="px-3 py-1.5 rounded text-sm font-bold tracking-wide transition-colors min-w-[64px]"
                                    :class="n === currentSetNumber
                                        ? 'bg-[rgba(67,146,224,0.22)] text-[#4392E0]'
                                        : 'bg-[rgba(237,238,242,0.22)] text-[#838799]'"
                                >S{{ n }}: {{ getSetTabLabel(n) }}</button>
                                <span v-if="n < 3" class="text-[#838799] text-sm">•</span>
                            </template>
                        </div>

                        <!-- Rules Line -->
                        <p v-if="ruleLine" class="text-[#838799] text-sm tracking-wide text-center">{{ ruleLine }}</p>
                    </div>

                    <!-- ====== TEAM B (Right Column) ====== -->
                    <div class="flex flex-col items-center gap-4 flex-1 max-w-[380px]">
                        <!-- Team B Label -->
                        <p class="text-[#838799] font-bold text-base tracking-wide text-center uppercase">{{ team2Name }}</p>

                        <!-- Timeout Badge -->
                        <div v-if="team2TimeoutUsed" class="flex items-center gap-1.5 px-3 py-1 rounded bg-[rgba(255,0,54,0.15)] border border-[rgba(255,0,54,0.3)]">
                            <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-red-400 font-semibold text-xs tracking-wide">Đã timeout</span>
                        </div>

                        <!-- 2 Members Horizontal -->
                        <div class="flex flex-row items-start justify-center gap-10 w-full">
                            <div v-for="member in team2Members" :key="member.id" class="flex flex-col items-center gap-2">
                                <!-- Avatar -->
                                <div class="w-[88px] h-[88px] rounded-full overflow-hidden bg-[rgba(237,238,242,0.1)] flex items-center justify-center ring-2 ring-white/10">
                                    <img v-if="member.avatar" :src="member.avatar" alt="" class="w-full h-full object-cover" />
                                    <span v-else class="text-white font-bold text-2xl">{{ getInitials(member.name) }}</span>
                                </div>
                                <!-- Name Below Avatar -->
                                <p class="text-white font-bold text-base tracking-wide text-center leading-tight max-w-[100px]">{{ member.name }}</p>
                                <!-- DUPR Badge -->
                                <div class="px-3 py-1 rounded bg-[rgba(237,238,242,0.22)]">
                                    <span class="text-[#838799] font-semibold text-xs tracking-wide">DUPR {{ getMemberVndupr(member) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Serving Badge (under whole team) -->
                        <div v-if="isTeamServing(2)" class="px-3 py-1.5 rounded bg-[#FEFBEB] border border-[#F7D79B] flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-[#A97B35]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h13m0 0l-4-4m4 4l-4 4M21 4v16"/>
                            </svg>
                            <span class="text-[#A97B35] font-semibold text-sm tracking-wide">Đang giao bóng</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div v-if="!isLoading && !isError" class="bg-[#1C2336] border-t border-[rgba(220,222,230,0.22)] px-6 py-3 shrink-0">
            <div class="flex items-center justify-between gap-4 max-w-7xl mx-auto">
                <!-- Left: Match Rules -->
                <div class="flex items-center gap-3 whitespace-nowrap">
                    <p v-if="matchRules?.sets_per_match" class="text-[#838799] text-xs tracking-wide">Best of {{ matchRules.sets_per_match }}</p>
                    <span v-if="matchRules?.sets_per_match && matchRules?.points_to_win_set" class="w-px h-3 bg-[rgba(220,222,230,0.22)]"></span>
                    <p v-if="matchRules?.points_to_win_set" class="text-[#838799] text-xs tracking-wide">{{ matchRules.points_to_win_set }} điểm</p>
                    <span v-if="(matchRules?.points_to_win_set || matchRules?.sets_per_match) && winningRuleText" class="w-px h-3 bg-[rgba(220,222,230,0.22)]"></span>
                    <p v-if="winningRuleText" class="text-[#838799] text-xs tracking-wide">{{ winningRuleText }}</p>
                </div>

                <!-- Center: Timer -->
                <div class="px-4 py-1.5 rounded bg-[#FEFBEB] border border-[#F7D79B] flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-[#A97B35]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4l3 3"/>
                    </svg>
                    <span class="text-[#A97B35] font-bold text-sm tabular-nums tracking-wide">{{ elapsedTimeText }}</span>
                </div>

                <!-- Right: Referee -->
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full overflow-hidden bg-[rgba(237,238,242,0.1)] flex items-center justify-center">
                        <span v-if="!refereeName" class="text-[#838799] text-[10px]">—</span>
                        <span v-else class="text-white font-bold text-[10px]">{{ getInitials(refereeName) }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[#838799] text-xs">Trọng tài:</span>
                        <span class="text-white font-bold text-sm">{{ refereeName || '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Timeout Overlay -->
        <Transition name="timeout-fade">
            <div v-if="showTimeoutOverlay" class="fixed inset-0 z-[70] flex flex-col items-center justify-center bg-black/70 backdrop-blur-sm">
                <p class="text-white text-5xl font-black tracking-widest mb-8 uppercase drop-shadow-lg" style="font-family: Impact, sans-serif;">
                    Timeout
                </p>
                <div class="relative w-48 h-48 flex items-center justify-center mb-6">
                    <svg class="absolute inset-0 w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" stroke="currentColor" stroke-width="6" fill="transparent" class="text-white/10" />
                        <circle cx="50" cy="50" r="45" stroke="currentColor" stroke-width="6" stroke-linecap="round" fill="transparent" class="text-[#E53E3E] transition-all duration-1000 ease-linear" :stroke-dasharray="283" :stroke-dashoffset="283 * (1 - timeoutSecondsDisplay / 60)" />
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-white" style="font-family: Impact, sans-serif; font-size: 72px; line-height: 1;">
                        {{ timeoutSecondsDisplay }}
                    </span>
                </div>
                <p v-if="timeoutTeam" class="text-[#838799] text-lg font-semibold tracking-wide mb-6">
                    {{ timeoutTeam === 'team1' ? team1Name : team2Name }} timeout
                </p>
                <!-- Close Timeout Button -->
                <button
                    @click="closeTimeout"
                    class="px-6 py-3 rounded-lg bg-[#4392E0] hover:bg-[#3377C9] text-white font-bold text-base tracking-wide transition-colors flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Đóng Timeout
                </button>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getPublicLiveScore } from '@/service/publicLiveScore.js'
import { MATCH_STATUS_LABEL } from '@/constants/index.js'
import LogoUrl from '@/assets/images/logo.svg'

const route = useRoute()

const matchData = ref(null)
const isLoading = ref(true)
const isError = ref(false)
const errorMessage = ref('')
const lastUpdated = ref(null)
const now = ref(Date.now())
const tickStart = ref(Date.now())
const elapsedTick = ref(0)
const isClosingTimeout = ref(false)
const showTimeoutOverlay = ref(false) // Local state to control overlay visibility

let echoChannel = null
let nowTimer = null

// Tournament
const tournamentName = computed(() => matchData.value?.tournament?.name || 'Trận đấu')
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
const team1Name = computed(() => matchData.value?.team1?.name || 'Đội A')
const team2Name = computed(() => matchData.value?.team2?.name || 'Đội B')
const team1Members = computed(() => matchData.value?.team1?.members || [])
const team2Members = computed(() => matchData.value?.team2?.members || [])
const refereeName = computed(() => matchData.value?.referee_name || null)

// Sets
const sets = computed(() => matchData.value?.sets || [])
const currentSetNumber = computed(() => matchData.value?.current_set || (sets.value.length > 0 ? sets.value.length : 1))

const currentSetScore = computed(() => {
    const current = sets.value.find(s => s.set_number === currentSetNumber.value)
    if (current) return { team1: current.team1_score, team2: current.team2_score }
    return { team1: 0, team2: 0 }
})

// Point difference (kept for potential future use; not in footer anymore)
const pointDifference = computed(() => {
    return Math.abs(currentSetScore.value.team1 - currentSetScore.value.team2)
})

// Side switch count (kept for potential future use; not in footer anymore)
const sideSwitchCount = computed(() => {
    const interval = matchData.value?.side_switch_interval ?? 11
    const total = currentSetScore.value.team1 + currentSetScore.value.team2
    return Math.floor(total / interval)
})

// Winning rule text (e.g. "Cách 1" / "Cách 2")
const winningRuleText = computed(() => {
    const rule = matchRules.value?.winning_rule
    if (rule == null) return null
    return `Cách ${rule}`
})

// Elapsed time text MM:SS
// Source of truth: elapsed_seconds from BE (computed at moment BE responded/Echo broadcast).
// We add a local 1s tick so the counter keeps moving without re-fetching.
const elapsedTimeText = computed(() => {
    const baseline = matchData.value?.elapsed_seconds
    if (baseline == null) return '00:00'
    const elapsed = Math.max(0, Number(baseline) + Math.floor(elapsedTick.value / 1000))
    const minutes = Math.floor(elapsed / 60)
    const seconds = elapsed % 60
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
})

// Timeout
const isTimeoutActive = computed(() => {
    return matchData.value?.live_status === 'timeout'
})
const team1TimeoutUsed = computed(() => matchData.value?.team1_timeout_used === 1)
const team2TimeoutUsed = computed(() => matchData.value?.team2_timeout_used === 1)
const timeoutTeam = computed(() => {
    if (!isTimeoutActive.value) return null
    const t1Id = matchData.value?.team1?.id
    const servingId = matchData.value?.serving_team_id
    return servingId === t1Id ? 'team1' : 'team2'
})
const timeoutSecondsDisplay = computed(() => {
    if (!isTimeoutActive.value) return 0
    const updatedAt = matchData.value?.updated_at
    if (!updatedAt) return 0
    const updated = new Date(updatedAt).getTime()
    const elapsed = Math.max(0, Math.floor((now.value - updated) / 1000))
    return Math.max(0, 60 - elapsed)
})

// Close timeout - hide overlay locally (actual timeout end is handled by referee via Echo/polling)
const closeTimeout = () => {
    showTimeoutOverlay.value = false
}

// Set tab label: "11-5" if both scores exist, else "-"
const getSetTabLabel = (n) => {
    const s = sets.value.find(x => x.set_number === n)
    if (!s) return '-'
    if (s.team1_score == null && s.team2_score == null) return '-'
    return `${s.team1_score}-${s.team2_score}`
}

// Team serving
const isTeamServing = (teamIndex) => {
    const servingTeamId = matchData.value?.serving_team_id
    if (!servingTeamId) return false
    if (teamIndex === 1) return servingTeamId === matchData.value?.team1?.id
    if (teamIndex === 2) return servingTeamId === matchData.value?.team2?.id
    return false
}

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
// match_rules from BE may be either an object or an array containing a single object (DB stored as [{...}]).
// Normalize to an object so the FE has a stable shape.
const matchRules = computed(() => {
    const raw = matchData.value?.match_rules
    if (!raw) return null
    if (Array.isArray(raw)) return raw[0] ?? null
    if (typeof raw === 'object') return raw
    return null
})

// Tournament poster (may live on the match payload directly or nested under a tournament object)
const tournamentPoster = computed(() => {
    const d = matchData.value
    if (!d) return null
    return d.poster_url || d.tournament?.poster_url || null
})

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

// Auto-close timeout when countdown reaches 0
watch(timeoutSecondsDisplay, (seconds) => {
    if (showTimeoutOverlay.value && seconds === 0) {
        showTimeoutOverlay.value = false
    }
})

// Watch for timeout becoming active - show overlay
watch(isTimeoutActive, (isActive) => {
    if (isActive) {
        showTimeoutOverlay.value = true
    }
})

const fetchLiveScore = async () => {
    try {
        const { type, matchId } = route.params
        if (!matchId) return
        const res = await getPublicLiveScore(type, matchId)
        if (res.success) {
            matchData.value = res.data
            lastUpdated.value = new Date()
            isError.value = false
            // Reset tick baseline so elapsed counter starts from fresh BE value
            tickStart.value = Date.now()
            elapsedTick.value = 0
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

    // Timer for elapsed time display (1s tick). Baseline (elapsed_seconds) comes from BE;
    // we just count ticks locally so the counter advances without re-fetching.
    nowTimer = setInterval(() => {
        const nowMs = Date.now()
        now.value = nowMs
        elapsedTick.value = nowMs - tickStart.value
    }, 1000)

    tickStart.value = Date.now()

    // Echo real-time subscription for public view (no auth required)
    if (matchId && window.Echo) {
        echoChannel = window.Echo.channel(`match.${matchId}`)
        echoChannel.listen('.match.score_updated', (data) => {
            if (!matchData.value) return
            matchData.value = {
                ...matchData.value,
                live_status: data.live_status,
                current_set: data.current_set,
                serving_team_id: data.serving_team_id,
                team1_timeout_used: data.team1_timeout_used,
                team2_timeout_used: data.team2_timeout_used,
                elapsed_seconds: data.elapsed_seconds ?? matchData.value.elapsed_seconds,
                version: data.version,
                sets: data.sets || [],
                updated_at: data.updated_at || new Date().toISOString(),
            }
            // Reset local tick baseline so we add seconds on top of fresh BE value
            if (typeof data.elapsed_seconds === 'number') {
                tickStart.value = Date.now()
                elapsedTick.value = 0
            }
            lastUpdated.value = new Date()
        })
    }
})

onUnmounted(() => {
    if (echoChannel) {
        echoChannel.stopListening('.match.score_updated')
        window.Echo.leave(`match.${route.params.matchId}`)
        echoChannel = null
    }
    if (nowTimer) {
        clearInterval(nowTimer)
        nowTimer = null
    }
})
</script>

<style scoped>
.timeout-fade-enter-active,
.timeout-fade-leave-active {
    transition: opacity 0.3s ease;
}
.timeout-fade-enter-from,
.timeout-fade-leave-to {
    opacity: 0;
}
</style>
