<template>
    <div class="ls-shell">
        <div class="ls-root flex flex-col select-none" :class="themeClass" :style="themeVars">

            <!-- Background layers: base gradient -> photo -> overlay (mandatory for legibility) -->
            <div class="ls-bg"></div>
            <img v-if="isPhotoMode && tournamentPoster" :src="tournamentPoster" class="ls-photo" alt="" />
            <div class="ls-ov"></div>

            <!-- Content -->
            <div class="lsc">

                <!-- Header -->
                <div class="lt">
                    <LogoUrl class="ls-logo" />

                    <!-- LIVE badge -->
                    <div v-if="status === 'going_on'" class="live"><i></i>LIVE</div>

                    <div class="lt-center">
                        <p class="ltn">{{ tournamentName }}</p>
                        <div class="lt-meta">
                            <span v-if="tournamentDateTime">{{ tournamentDateTime }}</span>
                            <span v-if="tournamentDateTime && tournamentLocation">•</span>
                            <span v-if="tournamentLocation" class="truncate">{{ tournamentLocation }}</span>
                        </div>
                    </div>

                    <!-- Sponsor poster -->
                    <div v-if="tournamentPoster" class="ls-sponsor">
                        <img :src="tournamentPoster" alt="poster" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="ls-sponsor ls-sponsor--ph">LOGO NHÀ TÀI TRỢ</div>

                    <!-- Settings -->
                    <div class="ls-settings">
                        <button type="button" class="gear" :aria-expanded="showSettings" aria-label="Cài đặt giao diện"
                            @click="showSettings = !showSettings">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>

                        <Transition name="pop">
                            <div v-if="showSettings" class="panel">
                                <p class="panel-cap">GIAO DIỆN</p>
                                <button v-for="opt in THEME_OPTIONS" :key="opt.value" type="button" class="opt"
                                    :class="{ 'opt--on': effectiveMode === opt.value, 'opt--off': opt.disabled }"
                                    :disabled="opt.disabled" @click="setTheme(opt.value)">
                                    <span class="opt-name">{{ opt.label }}</span>
                                    <span class="opt-hint">{{ opt.hint }}</span>
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="isLoading" class="state">
                    <div class="spinner"></div>
                    <p class="state-txt">Đang tải dữ liệu...</p>
                </div>

                <!-- Error -->
                <div v-else-if="isError" class="state">
                    <div class="err-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <p class="state-title">Không tìm thấy trận đấu</p>
                    <p class="state-txt">{{ errorMessage }}</p>
                </div>

                <!-- Body -->
                <div v-else class="lb">
                    <!-- Team A -->
                    <div class="side side--A">
                        <p class="tnm">{{ team1Name }}</p>
                        <div v-if="team1TimeoutUsed" class="timeout"><span>Đã timeout</span></div>
                        <div class="avs">
                            <div v-for="member in team1Members" :key="member.id" class="member">
                                <div class="av">
                                    <img v-if="member.avatar" :src="member.avatar" alt="" class="w-full h-full object-cover" />
                                    <span v-else>{{ getInitials(member.name) }}</span>
                                </div>
                                <p class="pn">{{ member.name }}</p>
                                <div class="dupr"><span>{{ getMemberVndupr(member) }}</span></div>
                            </div>
                        </div>
                        <div v-if="isTeamServing(1)" class="srv"><span>ĐANG GIAO BÓNG</span></div>
                    </div>

                    <!-- Score -->
                    <div class="mid">
                        <div class="sc">
                            <span class="scn scn--A">{{ currentSetScore.team1 }}</span>
                            <span class="scd">–</span>
                            <span class="scn scn--B">{{ currentSetScore.team2 }}</span>
                        </div>

                        <div class="set-row">
                            <span class="set-lbl">SÉC {{ currentSetNumber }}</span>
                            <span v-if="isConfirmed" class="verified"><span>Đã xác nhận</span></span>
                        </div>

                        <div class="tabs">
                            <template v-for="n in 3" :key="n">
                                <div class="tab" :class="n === currentSetNumber ? 'tab--on' : ''">
                                    S{{ n }}: {{ getSetTabLabel(n) }}
                                    <span v-if="isSetFinished(n)" class="tab-ok">✓</span>
                                </div>
                                <span v-if="n < 3" class="tab-dot">•</span>
                            </template>
                        </div>

                        <p v-if="ruleLine" class="fmt">{{ ruleLine }}</p>
                    </div>

                    <!-- Team B -->
                    <div class="side side--B">
                        <p class="tnm">{{ team2Name }}</p>
                        <div v-if="team2TimeoutUsed" class="timeout"><span>Đã timeout</span></div>
                        <div class="avs">
                            <div v-for="member in team2Members" :key="member.id" class="member">
                                <div class="av">
                                    <img v-if="member.avatar" :src="member.avatar" alt="" class="w-full h-full object-cover" />
                                    <span v-else>{{ getInitials(member.name) }}</span>
                                </div>
                                <p class="pn">{{ member.name }}</p>
                                <div class="dupr"><span>{{ getMemberVndupr(member) }}</span></div>
                            </div>
                        </div>
                        <div v-if="isTeamServing(2)" class="srv"><span>ĐANG GIAO BÓNG</span></div>
                    </div>
                </div>

                <!-- Footer -->
                <div v-if="!isLoading && !isError" class="lf">
                    <div class="lf-left">
                        <span v-if="matchRules?.sets_per_match">Best of {{ matchRules.sets_per_match }}</span>
                        <span v-if="matchRules?.points_to_win_set">{{ matchRules.points_to_win_set }} điểm</span>
                        <span v-if="winningRuleText">{{ winningRuleText }}</span>
                    </div>
                    <div class="lf-clock">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" />
                        </svg>
                        <span>{{ elapsedTimeText }}</span>
                    </div>
                    <div class="lf-ref">
                        <div class="ref-av">
                            <span v-if="!refereeName">—</span>
                            <span v-else>{{ getInitials(refereeName) }}</span>
                        </div>
                        <div class="ref-name">
                            <span class="ref-lbl">TRỌNG TÀI</span>
                            <span class="ref-val">{{ refereeName || '—' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Timeout overlay -->
                <Transition name="timeout-fade">
                    <div v-if="showTimeoutOverlay" class="to-ov">
                        <p class="to-title">TIMEOUT</p>
                        <div class="to-ring">
                            <svg class="to-svg" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="45" class="to-track" />
                                <circle cx="50" cy="50" r="45" class="to-prog"
                                    :stroke-dasharray="283" :stroke-dashoffset="283 * (1 - timeoutSecondsDisplay / 60)" />
                            </svg>
                            <span class="to-num">{{ timeoutSecondsDisplay }}</span>
                        </div>
                        <p v-if="timeoutTeam" class="to-team">
                            {{ timeoutTeam === 'team1' ? team1Name : team2Name }} timeout
                        </p>
                        <button type="button" class="to-close" @click="closeTimeout">Đóng Timeout</button>
                    </div>
                </Transition>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getPublicLiveScore } from '@/service/publicLiveScore.js'
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
const showTimeoutOverlay = ref(false)

let echoChannel = null
let nowTimer = null

// ============ THEME (page-local, no API: this page is public) ============
const THEME_KEY = 'vpick_livescore_theme'
const THEMES = {
    dark: {
        '--bg': '#0B0D12',
        '--A': '#4A9BFF', '--B': '#FF5A63', '--gold': '#F5C542', '--gold-ink': '#1A1508',
        '--txt': '#F2F4F8', '--t2': '#9AA4B4', '--t3': '#6B7585',
        '--surf': '#1E2430', '--line': '#2A3240', '--div': 'rgba(255,255,255,.08)',
        '--Aw': 'rgba(74,155,255,.18)', '--Bw': 'rgba(255,90,99,.15)',
        '--ok': '#34D399',
    },
    light: {
        '--bg': '#FFFFFF',
        '--A': '#1D6FE0', '--B': '#D32030', '--gold': '#D99400', '--gold-ink': '#1A1508',
        '--txt': '#14161A', '--t2': '#5A6069', '--t3': '#8C929B',
        '--surf': '#F1F3F6', '--line': '#DDE1E7', '--div': '#E7EAEF',
        '--Aw': '#E7F0FE', '--Bw': '#FDEAEC',
        '--ok': '#16A34A',
    },
    photo: {
        '--bg': '#14402E',
        '--A': '#6FB4FF', '--B': '#FF7A82', '--gold': '#FFD24A', '--gold-ink': '#1A1508',
        '--txt': '#FFFFFF', '--t2': '#D4DCE8', '--t3': '#A8B4C4',
        '--surf': 'rgba(255,255,255,.12)', '--line': 'rgba(255,255,255,.28)', '--div': 'rgba(255,255,255,.18)',
        '--Aw': 'rgba(111,180,255,.22)', '--Bw': 'rgba(255,122,130,.2)',
        '--ok': '#6EE7B7',
    },
}

const themeMode = ref(localStorage.getItem(THEME_KEY) || 'dark')
const showSettings = ref(false)

// Poster may load after settings open, so photo option is disabled reactively.
const THEME_OPTIONS = computed(() => [
    { value: 'light', label: 'Sáng', hint: 'Sân ngoài trời, máy chiếu yếu' },
    { value: 'dark', label: 'Tối', hint: 'Trong nhà, ánh sáng yếu' },
    { value: 'photo', label: 'Ảnh nền', hint: tournamentPoster.value ? 'Poster giải đấu' : 'Giải chưa có poster', disabled: !tournamentPoster.value },
])

// Fall back to dark when photo mode is remembered but the poster is gone.
const effectiveMode = computed(() => {
    if (themeMode.value === 'photo' && !tournamentPoster.value) return 'dark'
    return themeMode.value
})

const themeClass = computed(() => `theme-${effectiveMode.value}`)
const themeVars = computed(() => THEMES[effectiveMode.value] || THEMES.dark)
const isPhotoMode = computed(() => effectiveMode.value === 'photo')

const setTheme = (mode) => {
    if (mode === 'photo' && !tournamentPoster.value) return
    themeMode.value = mode
    localStorage.setItem(THEME_KEY, mode)
    showSettings.value = false
}

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

// Winning rule text (e.g. "Cách 1" / "Cách 2")
const winningRuleText = computed(() => {
    const rule = matchRules.value?.winning_rule
    if (rule == null) return null
    return `Cách ${rule}`
})

// Elapsed time MM:SS. Source of truth is BE `elapsed_seconds`; local 1s tick keeps it moving.
const elapsedTimeText = computed(() => {
    const baseline = matchData.value?.elapsed_seconds
    if (baseline == null) return '00:00'
    const elapsed = Math.max(0, Number(baseline) + Math.floor(elapsedTick.value / 1000))
    const minutes = Math.floor(elapsed / 60)
    const seconds = elapsed % 60
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
})

// Timeout
const isTimeoutActive = computed(() => matchData.value?.live_status === 'timeout')
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

const isSetFinished = (n) => {
    const s = sets.value.find(x => x.set_number === n)
    if (!s) return false
    if (s.team1_score == null && s.team2_score == null) return false
    return n < currentSetNumber.value || matchData.value?.live_status === 'finish'
}

const isTeamServing = (teamIndex) => {
    const servingTeamId = matchData.value?.serving_team_id
    if (!servingTeamId) return false
    if (teamIndex === 1) return servingTeamId === matchData.value?.team1?.id
    if (teamIndex === 2) return servingTeamId === matchData.value?.team2?.id
    return false
}

const status = computed(() => {
    const d = matchData.value
    if (!d) return 'pending'
    if (d.live_status === 'playing' || d.live_status === 'live') return 'going_on'
    if (d.live_status === 'completed' || d.live_status === 'done' || d.live_status === 'finished' || d.live_status === 'finish') return 'completed'
    return 'pending'
})
const isConfirmed = computed(() => {
    const d = matchData.value
    if (!d) return false
    return (d.home_team_confirm === 1 && d.away_team_confirm === 1) || d.status === 'completed'
})

// match_rules may arrive as an object or as an array holding one object; normalize.
const matchRules = computed(() => {
    const raw = matchData.value?.match_rules
    if (!raw) return null
    if (Array.isArray(raw)) return raw[0] ?? null
    if (typeof raw === 'object') return raw
    return null
})

const tournamentPoster = computed(() => {
    const d = matchData.value
    if (!d) return null
    return d.poster_url || d.tournament?.poster_url || null
})

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

const onDocumentClick = (e) => {
    if (!showSettings.value) return
    if (!e.target.closest?.('.ls-settings')) showSettings.value = false
}

onMounted(async () => {
    document.addEventListener('click', onDocumentClick)

    const { matchId } = route.params
    if (!matchId) {
        isLoading.value = false
        isError.value = true
        errorMessage.value = 'ID trận đấu không hợp lệ'
        return
    }
    await fetchLiveScore()

    // 1s tick so the clock advances without re-fetching.
    nowTimer = setInterval(() => {
        const nowMs = Date.now()
        now.value = nowMs
        elapsedTick.value = nowMs - tickStart.value
    }, 1000)

    tickStart.value = Date.now()

    // Echo public channel (no auth). Init without Bearer if the user is not logged in.
    if (!window.Echo && typeof window.initEcho === 'function') {
        window.initEcho(null)
    }
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
                version: data.version,
                sets: data.sets || [],
                elapsed_seconds: typeof data.elapsed_seconds === 'number'
                    ? data.elapsed_seconds
                    : matchData.value.elapsed_seconds,
            }
            if (typeof data.elapsed_seconds === 'number') {
                tickStart.value = Date.now()
                elapsedTick.value = 0
            }
            lastUpdated.value = new Date()
        })
    }
})

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick)
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
/* ============================================================
   LAYOUT SHELL — .ls-shell is the query container; all sizing
   below scales off --u which derives from the container width.
   ============================================================ */
.ls-shell {
    container-type: inline-size;
}

.ls-root {
    position: relative;
    overflow: hidden;
    min-height: 100vh;
    color: var(--txt);
    background: var(--bg);
    /* ~1.76cqi tracks the mockup: 854px -> 15px, 490px -> 8.6px */
    --u: clamp(8px, 1.76cqi, 15px);
}

.ls-bg, .ls-photo, .ls-ov {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.ls-bg, .ls-photo { z-index: 0; }
.ls-photo { object-fit: cover; }
.ls-ov { z-index: 1; }

.lsc {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ============ THEMES ============ */
.theme-dark .ls-bg {
    background: linear-gradient(135deg, #1B2838, #0B0D12 55%, #231A1C);
}
.theme-dark .ls-ov {
    background: radial-gradient(ellipse at 50% 42%, rgba(0, 0, 0, 0) 0%, rgba(7, 8, 11, .55) 75%);
}

.theme-light .ls-bg {
    background: linear-gradient(135deg, #FFFFFF, #F5F7FA 55%, #FFFFFF);
}
.theme-light .ls-ov {
    background: none;
}

.theme-photo .ls-bg {
    background: linear-gradient(170deg, #2E5C8A 0%, #1E6B4A 42%, #3A7A52 68%, #1A4030 100%);
}
/* Overlay is mandatory — poster is arbitrary user content, text must stay readable. */
.theme-photo .ls-ov {
    background: linear-gradient(180deg, rgba(5, 8, 14, .88) 0%, rgba(5, 8, 14, .52) 22%, rgba(5, 8, 14, .52) 74%, rgba(5, 8, 14, .9) 100%),
        radial-gradient(ellipse at 50% 46%, rgba(5, 8, 14, .74) 0%, rgba(5, 8, 14, .2) 70%);
}

/* ============ HEADER ============ */
.lt {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * 1.2);
    padding: calc(var(--u) * 1.1) calc(var(--u) * 1.5);
    border-bottom: 1px solid var(--div);
    flex-shrink: 0;
}

.ls-logo {
    width: calc(var(--u) * 2.4);
    height: calc(var(--u) * 2.4);
    flex-shrink: 0;
}

.live {
    display: flex;
    align-items: center;
    font-size: calc(var(--u) * .82);
    font-weight: 900;
    color: #fff;
    background: #E8192C;
    padding: calc(var(--u) * .32) calc(var(--u) * .75);
    border-radius: calc(var(--u) * .35);
    letter-spacing: 1px;
    flex-shrink: 0;
}
.live i {
    width: calc(var(--u) * .42);
    height: calc(var(--u) * .42);
    border-radius: 50%;
    background: #fff;
    margin-right: calc(var(--u) * .42);
    display: block;
}

.lt-center {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.ltn {
    font-size: calc(var(--u) * 1.5);
    font-weight: 800;
    letter-spacing: .4px;
    color: var(--txt);
    text-align: center;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1.25;
}

.lt-meta {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * .4);
    margin-top: calc(var(--u) * .25);
    font-size: calc(var(--u) * .92);
    font-weight: 600;
    color: var(--t2);
}
.lt-meta span { max-width: 100%; }

.ls-sponsor {
    width: calc(var(--u) * 4);
    height: calc(var(--u) * 2.5);
    flex-shrink: 0;
    border-radius: calc(var(--u) * .3);
    overflow: hidden;
    border: 1px solid var(--line);
}
.ls-sponsor--ph {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--surf);
    font-size: calc(var(--u) * .58);
    font-weight: 800;
    color: var(--t3);
    text-align: center;
    line-height: 1.2;
    padding: 0 calc(var(--u) * .2);
}

/* ============ SETTINGS ============ */
.ls-settings {
    position: relative;
    flex-shrink: 0;
}

.gear {
    display: flex;
    align-items: center;
    justify-content: center;
    width: calc(var(--u) * 2.5);
    height: calc(var(--u) * 2.5);
    border-radius: calc(var(--u) * .35);
    background: var(--surf);
    border: 1px solid var(--line);
    color: var(--t2);
    cursor: pointer;
    transition: color .15s, background .15s;
}
.gear:hover { color: var(--txt); }
.gear svg { width: calc(var(--u) * 1.25); height: calc(var(--u) * 1.25); }

.panel {
    position: absolute;
    top: calc(100% + calc(var(--u) * .5));
    right: 0;
    z-index: 30;
    width: max(200px, calc(var(--u) * 16));
    padding: calc(var(--u) * .7);
    border-radius: calc(var(--u) * .5);
    background: var(--surf);
    border: 1px solid var(--line);
    box-shadow: 0 calc(var(--u) * .5) calc(var(--u) * 2) rgba(0, 0, 0, .35);
}

.panel-cap {
    font-size: calc(var(--u) * .62);
    font-weight: 900;
    letter-spacing: 1.2px;
    color: var(--t3);
    padding: 0 calc(var(--u) * .4) calc(var(--u) * .45);
}

.opt {
    display: flex;
    flex-direction: column;
    gap: calc(var(--u) * .12);
    width: 100%;
    text-align: left;
    padding: calc(var(--u) * .5) calc(var(--u) * .55);
    border-radius: calc(var(--u) * .35);
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
    transition: background .15s, border-color .15s;
}
.opt:hover:not(.opt--off) { background: var(--div); }
.opt--on {
    background: var(--Aw);
    border-color: var(--A);
}
.opt--off {
    opacity: .42;
    cursor: not-allowed;
}

.opt-name {
    font-size: calc(var(--u) * .88);
    font-weight: 800;
    color: var(--txt);
}
.opt--on .opt-name { color: var(--A); }

.opt-hint {
    font-size: calc(var(--u) * .68);
    color: var(--t3);
    line-height: 1.3;
}

/* ============ LOADING / ERROR ============ */
.state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: calc(var(--u) * .8);
    padding: calc(var(--u) * 2);
}
.spinner {
    width: calc(var(--u) * 3.4);
    height: calc(var(--u) * 3.4);
    border-radius: 50%;
    border: calc(var(--u) * .22) solid var(--line);
    border-top-color: var(--B);
    animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.err-icon {
    width: calc(var(--u) * 4);
    height: calc(var(--u) * 4);
    border-radius: 50%;
    background: var(--surf);
    display: flex;
    align-items: center;
    justify-content: center;
}
.err-icon svg { width: calc(var(--u) * 2); height: calc(var(--u) * 2); color: var(--t3); }

.state-title {
    font-size: calc(var(--u) * 1.15);
    font-weight: 700;
    color: var(--txt);
    text-align: center;
}
.state-txt {
    font-size: calc(var(--u) * .9);
    color: var(--t3);
    text-align: center;
}

/* ============ BODY ============ */
.lb {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: calc(var(--u) * 1.5);
    padding: calc(var(--u) * 1.2) calc(var(--u) * 1.6);
    min-height: 0;
}

.side {
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: calc(var(--u) * .7);
    text-align: center;
}

.tnm {
    font-size: calc(var(--u) * .85);
    font-weight: 900;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    color: var(--t3);
}
.side--A .tnm { color: var(--A); }
.side--B .tnm { color: var(--B); }

.timeout {
    padding: calc(var(--u) * .3) calc(var(--u) * .7);
    border-radius: calc(var(--u) * .35);
    background: var(--Bw);
    border: 1px solid var(--B);
    font-size: calc(var(--u) * .72);
    font-weight: 800;
    color: var(--B);
    letter-spacing: .5px;
}

.avs {
    display: flex;
    justify-content: center;
    gap: calc(var(--u) * 1.4);
    flex-wrap: wrap;
}

.member {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: calc(var(--u) * .35);
}

.av {
    display: flex;
    align-items: center;
    justify-content: center;
    width: calc(var(--u) * 4.2);
    height: calc(var(--u) * 4.2);
    border-radius: 50%;
    overflow: hidden;
    background: var(--surf);
    border: calc(var(--u) * .16) solid var(--t3);
    font-size: calc(var(--u) * 1.5);
    font-weight: 800;
    color: var(--t3);
}
.side--A .av { border-color: var(--A); color: var(--A); }
.side--B .av { border-color: var(--B); color: var(--B); }

.pn {
    font-size: calc(var(--u) * 1);
    font-weight: 800;
    line-height: 1.3;
    color: var(--txt);
    max-width: calc(var(--u) * 8);
    overflow-wrap: anywhere;
}

.dupr {
    padding: calc(var(--u) * .22) calc(var(--u) * .6);
    border-radius: calc(var(--u) * .3);
    background: var(--surf);
    border: 1px solid var(--line);
    font-size: calc(var(--u) * .7);
    font-weight: 700;
    color: var(--t3);
    font-variant-numeric: tabular-nums;
}

.srv {
    display: inline-flex;
    align-items: center;
    padding: calc(var(--u) * .3) calc(var(--u) * .7);
    border-radius: calc(var(--u) * .35);
    background: var(--gold);
    font-size: calc(var(--u) * .7);
    font-weight: 900;
    letter-spacing: .5px;
    color: var(--gold-ink);
}

/* ============ SCORE ============ */
.mid {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.sc {
    display: flex;
    align-items: baseline;
    justify-content: center;
    line-height: .9;
}
.scn {
    font-size: calc(var(--u) * 4.8);
    font-weight: 900;
    letter-spacing: calc(var(--u) * -.12);
    font-variant-numeric: tabular-nums;
}
.scn--A { color: var(--A); }
.scn--B { color: var(--B); }
.scd {
    font-size: calc(var(--u) * 2.6);
    font-weight: 300;
    color: var(--t3);
    margin: 0 calc(var(--u) * .55);
}

.set-row {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * .6);
    margin-top: calc(var(--u) * .5);
}
.set-lbl {
    font-size: calc(var(--u) * .9);
    font-weight: 900;
    letter-spacing: 1.6px;
    color: var(--t2);
}

.verified {
    display: inline-flex;
    align-items: center;
    padding: calc(var(--u) * .18) calc(var(--u) * .45);
    border-radius: calc(var(--u) * .3);
    background: color-mix(in srgb, var(--ok) 18%, transparent);
    border: 1px solid color-mix(in srgb, var(--ok) 45%, transparent);
    font-size: calc(var(--u) * .68);
    font-weight: 700;
    color: var(--ok);
}

.tabs {
    display: flex;
    align-items: center;
    margin-top: calc(var(--u) * .6);
}
.tab {
    padding: calc(var(--u) * .28) calc(var(--u) * .6);
    border-radius: calc(var(--u) * .3);
    background: var(--surf);
    border: 1px solid var(--line);
    font-size: calc(var(--u) * .78);
    font-weight: 800;
    color: var(--t2);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.tab--on {
    background: var(--Aw);
    border-color: var(--A);
    color: var(--A);
}
.tab-ok { margin-left: calc(var(--u) * .25); color: var(--ok); }
.tab-dot { margin: 0 calc(var(--u) * .35); color: var(--t3); font-size: calc(var(--u) * .8); }

.fmt {
    font-size: calc(var(--u) * .76);
    font-weight: 600;
    color: var(--t3);
    margin-top: calc(var(--u) * .5);
}

/* ============ FOOTER ============ */
.lf {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * 1.2);
    padding: calc(var(--u) * .9) calc(var(--u) * 1.5);
    border-top: 1px solid var(--div);
    flex-shrink: 0;
    font-size: calc(var(--u) * .8);
    font-weight: 700;
    color: var(--t3);
}

.lf-left {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * .9);
    min-width: 0;
    flex-wrap: wrap;
    letter-spacing: .5px;
}

.lf-clock {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * .4);
    margin: 0 auto;
    padding: calc(var(--u) * .3) calc(var(--u) * .8);
    border-radius: calc(var(--u) * .35);
    background: var(--surf);
    border: 1px solid var(--line);
    color: var(--txt);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.lf-clock svg { width: calc(var(--u) * 1.1); height: calc(var(--u) * 1.1); }

.lf-ref {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * .6);
}
.ref-av {
    display: flex;
    align-items: center;
    justify-content: center;
    width: calc(var(--u) * 1.9);
    height: calc(var(--u) * 1.9);
    border-radius: 50%;
    overflow: hidden;
    background: var(--surf);
    font-size: calc(var(--u) * .68);
    font-weight: 800;
    color: var(--t3);
}
.ref-name { display: flex; flex-direction: column; line-height: 1.2; }
.ref-lbl { font-size: calc(var(--u) * .6); letter-spacing: .8px; }
.ref-val { font-size: calc(var(--u) * .82); font-weight: 800; color: var(--txt); }

/* ============ TIMEOUT OVERLAY ============ */
.to-ov {
    position: fixed;
    inset: 0;
    z-index: 70;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, .72);
    backdrop-filter: blur(4px);
    gap: calc(var(--u) * 1.4);
}
.to-title {
    font-size: calc(var(--u) * 3.2);
    font-weight: 900;
    letter-spacing: .2em;
    text-transform: uppercase;
    color: #fff;
    text-shadow: 0 calc(var(--u) * .2) calc(var(--u) * .8) rgba(0, 0, 0, .6);
}
.to-ring {
    position: relative;
    width: calc(var(--u) * 11);
    height: calc(var(--u) * 11);
    display: flex;
    align-items: center;
    justify-content: center;
}
.to-svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.to-track, .to-prog { fill: transparent; stroke-width: 6; }
.to-track { stroke: rgba(255, 255, 255, .15); }
.to-prog {
    stroke: var(--B);
    stroke-linecap: round;
    transition: stroke-dashoffset 1s linear;
}
.to-num {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: calc(var(--u) * 4.6);
    font-weight: 900;
    color: #fff;
}
.to-team {
    font-size: calc(var(--u) * 1.3);
    font-weight: 700;
    color: rgba(255, 255, 255, .72);
}
.to-close {
    padding: calc(var(--u) * .7) calc(var(--u) * 1.6);
    border-radius: calc(var(--u) * .45);
    background: var(--A);
    border: none;
    color: #fff;
    font-size: calc(var(--u) * 1);
    font-weight: 800;
    letter-spacing: .5px;
    cursor: pointer;
}

/* ============ TRANSITIONS ============ */
.timeout-fade-enter-active,
.timeout-fade-leave-active { transition: opacity .3s ease; }
.timeout-fade-enter-from,
.timeout-fade-leave-to { opacity: 0; }

.pop-enter-active, .pop-leave-active { transition: opacity .15s ease, transform .15s ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; transform: translateY(calc(var(--u) * -.4)); }

/* ============================================================
   RESPONSIVE — container queries, not window width: this board
   can be embedded in a narrow frame on a projector.
   ============================================================ */

/* Vertical: score moves to a right-hand column spanning both
   teams, each team becomes a horizontal row. */
@container (max-width: 640px) {
    .ls-root {
        /* Slightly larger base than the width formula: layout is stacked,
           so there is room to breathe (matches mockup's --u:11px). */
        --u: clamp(10px, 3.1cqi, 13px);
    }

    .lb {
        grid-template-columns: 1fr auto;
        grid-template-rows: auto auto auto;
        align-content: center;
        gap: calc(var(--u) * .9);
        padding: calc(var(--u) * 1);
    }

    .side--A { grid-column: 1; grid-row: 1; }
    .side--B { grid-column: 1; grid-row: 3; }
    .mid {
        grid-column: 2;
        grid-row: 1 / -1;
        padding-left: calc(var(--u) * .8);
    }

    /* Team rows: avatars on the left, names beside them. */
    .side {
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        gap: calc(var(--u) * .8);
        text-align: left;
    }
    .side > * { margin: 0; }
    .tnm { display: none; }
    .avs { flex-shrink: 0; gap: calc(var(--u) * .5); }
    .av { width: calc(var(--u) * 3.2); height: calc(var(--u) * 3.2); font-size: calc(var(--u) * 1.2); }
    .member { flex-direction: column; align-items: center; gap: calc(var(--u) * .25); }
    .pn { max-width: calc(var(--u) * 11); font-size: calc(var(--u) * .92); }
    .dupr { padding: calc(var(--u) * .14) calc(var(--u) * .4); font-size: calc(var(--u) * .64); }
    .srv { margin-left: auto; }
    .timeout { flex-shrink: 0; }

    .scn { font-size: calc(var(--u) * 3.4); }
    .scd { font-size: calc(var(--u) * 2); }
    .set-lbl { font-size: calc(var(--u) * .78); }
    .tab { font-size: calc(var(--u) * .68); padding: calc(var(--u) * .22) calc(var(--u) * .45); }
    .fmt { font-size: calc(var(--u) * .68); }

    /* Footer: rules hidden, clock + referee stay. */
    .lf { justify-content: space-between; padding: calc(var(--u) * .7) calc(var(--u) * 1); }
    .lf-left { display: none; }

    .ltn { font-size: calc(var(--u) * 1.1); }
    .lt-meta { font-size: calc(var(--u) * .72); }
    .ls-logo { width: calc(var(--u) * 2); height: calc(var(--u) * 2); }
    .ls-sponsor { display: none; }
}

@container (max-width: 420px) {
    .avs { gap: calc(var(--u) * .35); }
    .pn { max-width: calc(var(--u) * 9); font-size: calc(var(--u) * .82); }
    .dupr, .ref-lbl { display: none; }
    .ref-av { width: calc(var(--u) * 1.6); height: calc(var(--u) * 1.6); }
}
</style>