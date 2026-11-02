<template>
    <div class="ls-shell">
        <div class="ls-root flex flex-col select-none" :class="themeClass" :style="themeVars">

            <!-- Background layers: base gradient -> photo -> overlay (mandatory for legibility) -->
            <div class="ls-bg"></div>
            <img v-if="isPhotoMode && backgroundImage" :src="backgroundImage" class="ls-photo" alt="" />
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
                    <div v-if="sponsorLogo" class="ls-sponsor">
                        <img :src="sponsorLogo" alt="poster" class="w-full h-full object-cover" />
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
                                <p class="panel-cap">NỀN</p>
                                <button v-for="opt in THEME_OPTIONS" :key="opt.value" type="button" class="opt"
                                    :class="{ 'opt--on': effectiveMode === opt.value, 'opt--off': opt.disabled }"
                                    :disabled="opt.disabled" @click="setTheme(opt.value)">
                                    <span class="opt-name">{{ opt.label }}</span>
                                    <span class="opt-hint">{{ opt.hint }}</span>
                                </button>

                                <!-- Độ phủ: chỉ hiện khi đang dùng ảnh nền -->
                                <template v-if="isPhotoMode && backgroundImage">
                                    <p class="panel-cap panel-cap--sp">ĐỘ PHỦ</p>
                                    <div class="ovl">
                                        <button v-for="lvl in [40, 60, 80]" :key="lvl" type="button" class="ovl-btn"
                                            :class="{ 'ovl-btn--on': prefs.overlayLevel === lvl }"
                                            @click="setOverlayLevel(lvl)">
                                            {{ lvl }}%
                                        </button>
                                    </div>
                                    <p class="ovl-note">
                                        {{ prefs.overlayLevel >= 80 ? 'Chữ rõ nhất, ảnh mờ nhất'
                                            : prefs.overlayLevel <= 40 ? 'Ảnh rõ nhất, chữ khó đọc hơn'
                                            : 'Cân bằng' }}
                                    </p>
                                </template>

                                <p class="panel-cap panel-cap--sp">MÀU ĐỘI</p>
                                <div class="color-row">
                                    <label class="color-item">
                                        <span class="color-dot" style="background: var(--A)"></span>
                                        <span class="color-lbl">Đội A</span>
                                        <input type="color" class="color-in" :value="prefs.colorA || '#4A9BFF'"
                                            @input="onColorInput('A', $event.target.value)" />
                                    </label>
                                    <label class="color-item">
                                        <span class="color-dot" style="background: var(--B)"></span>
                                        <span class="color-lbl">Đội B</span>
                                        <input type="color" class="color-in" :value="prefs.colorB || '#FF5A63'"
                                            @input="onColorInput('B', $event.target.value)" />
                                    </label>
                                </div>
                                <p v-if="colorError" class="ovl-note" style="color: var(--B)">{{ colorError }}</p>

                                <p class="panel-cap panel-cap--sp">HIỂN THỊ</p>
                                <label v-for="t in TOGGLE_OPTIONS" :key="t.key" class="tgl">
                                    <span class="tgl-lbl">{{ t.label }}</span>
                                    <input type="checkbox" class="tgl-box" :checked="prefs[t.key]"
                                        @change="togglePref(t.key)" />
                                </label>

                                <button type="button" class="reset" @click="resetPrefs">Khôi phục mặc định</button>
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
                                <div v-if="prefs.showRating" class="dupr"><span>{{ getMemberVndupr(member) }}</span></div>
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

                        <div v-if="prefs.showSetHistory" class="tabs">
                            <template v-for="n in setsInMatch" :key="n">
                                <div class="tab" :class="n === currentSetNumber ? 'tab--on' : ''">
                                    S{{ n }}: {{ getSetTabLabel(n) }}
                                    <span v-if="isSetFinished(n)" class="tab-ok">✓</span>
                                </div>
                                <span v-if="n < setsInMatch" class="tab-dot">•</span>
                            </template>
                        </div>

                        <p v-if="ruleLine && prefs.showFormat" class="fmt">{{ ruleLine }}</p>
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
                                <div v-if="prefs.showRating" class="dupr"><span>{{ getMemberVndupr(member) }}</span></div>
                            </div>
                        </div>
                        <div v-if="isTeamServing(2)" class="srv"><span>ĐANG GIAO BÓNG</span></div>
                    </div>
                </div>

                <!-- Footer -->
                <div v-if="!isLoading && !isError" class="lf">
                    <div v-if="prefs.showFormat" class="lf-left">
                        <span v-if="matchRules?.sets_per_match">Best of {{ matchRules.sets_per_match }}</span>
                        <span v-if="matchRules?.points_to_win_set">{{ matchRules.points_to_win_set }} điểm</span>
                        <span v-if="winningRuleText">{{ winningRuleText }}</span>
                        <span v-if="isMatchOver" class="lf-hold">
                            Giữ tỉ số {{ holdCountdown }}s
                        </span>
                    </div>
                    <div v-if="prefs.showClock" class="lf-clock">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" />
                        </svg>
                        <span>{{ elapsedTimeText }}</span>
                    </div>
                    <div v-if="prefs.showReferee" class="lf-ref">
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

// ============ PREFS (spec D.4) ============
// Cấu hình hiển thị KHÔNG lưu theo giải, KHÔNG gọi API.
// Mỗi lần phát là phiên độc lập; lưu cục bộ trên máy đang chiếu.
// Mở lại trên cùng máy -> giữ; mở máy khác -> mặc định.
const PREFS_KEY = 'picki_livescore_prefs'
const LEGACY_THEME_KEY = 'vpick_livescore_theme' // bài trước, chuyển sang key mới

const DEFAULT_PREFS = {
    theme: 'dark',
    overlayLevel: 60,
    colorA: null,
    colorB: null,
    sponsorLogoUrl: null,
    bgImageUrl: null,
    showRating: true,
    showSetHistory: true,
    showClock: true,
    showReferee: true,
    showFormat: true,
}

// Đọc lúc init. Dùng try/catch vì localStorage có thể bị chặn (private mode).
const loadPrefs = () => {
    try {
        const raw = localStorage.getItem(PREFS_KEY)
        if (raw) return { ...DEFAULT_PREFS, ...JSON.parse(raw) }
        // Migrate 1 lần từ bản trước.
        const legacy = localStorage.getItem(LEGACY_THEME_KEY)
        if (legacy) {
            localStorage.setItem(PREFS_KEY, JSON.stringify({ ...DEFAULT_PREFS, theme: legacy }))
            localStorage.removeItem(LEGACY_THEME_KEY)
            return { ...DEFAULT_PREFS, theme: legacy }
        }
    } catch {
        /* storage bị chặn -> dùng mặc định, màn chiếu vẫn chạy */
    }
    return { ...DEFAULT_PREFS }
}

const prefs = ref(loadPrefs())

const savePrefs = () => {
    try {
        localStorage.setItem(PREFS_KEY, JSON.stringify(prefs.value))
    } catch {
        /* bỏ qua: mất lưu cục bộ vẫn dùng được */
    }
}

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

const themeMode = ref(prefs.value.theme)
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
const themeVars = computed(() => {
    const base = THEMES[effectiveMode.value] || THEMES.dark
    return {
        ...base,
        '--overlay': overlayAlpha.value,
        // Màu đội tuỳ chỉnh đè lên bộ màu gốc (null = dùng mặc định của theme).
        ...(prefs.value.colorA ? { '--A': prefs.value.colorA } : {}),
        ...(prefs.value.colorB ? { '--B': prefs.value.colorB } : {}),
    }
})
const isPhotoMode = computed(() => effectiveMode.value === 'photo')

/** Độ phủ lớp phủ ảnh nền: 40 | 60 | 80 (spec B). */
const OVERLAY_ALPHA = { 40: 0.62, 60: 0.82, 80: 0.95 }

const setTheme = (mode) => {
    if (mode === 'photo' && !tournamentPoster.value) return
    themeMode.value = mode
    prefs.value.theme = mode
    savePrefs()
    showSettings.value = false
}

/**
 * Đổi màu đội — chặn trùng nhau (spec kiểm thử: "Đổi màu đội trùng nhau -> BỊ CHẶN").
 * Trả về false để UI báo lý do.
 */
const setTeamColor = (side, color) => {
    const other = side === 'A' ? 'colorB' : 'colorA'
    if (prefs.value[other] && prefs.value[other].toLowerCase() === color.toLowerCase()) return false
    prefs.value[side === 'A' ? 'colorA' : 'colorB'] = color
    savePrefs()
    return true
}

const setOverlayLevel = (level) => {
    prefs.value.overlayLevel = level
    savePrefs()
}

const resetPrefs = () => {
    prefs.value = { ...DEFAULT_PREFS }
    themeMode.value = DEFAULT_PREFS.theme
    try {
        localStorage.removeItem(PREFS_KEY)
    } catch { /* bỏ qua */ }
}

// Chặn chọn trùng màu: báo lý do thay vì im lặng (spec kiểm thử).
const colorError = ref('')
const onColorInput = (side, color) => {
    if (!setTeamColor(side, color)) {
        colorError.value = 'Hai đội không được dùng cùng một màu'
        return
    }
    colorError.value = ''
}

const TOGGLE_OPTIONS = [
    { key: 'showRating', label: 'Điểm trình' },
    { key: 'showSetHistory', label: 'Lịch sử séc' },
    { key: 'showClock', label: 'Đồng hồ' },
    { key: 'showReferee', label: 'Tên trọng tài' },
    { key: 'showFormat', label: 'Thể thức' },
]

const togglePref = (key) => {
    prefs.value[key] = !prefs.value[key]
    savePrefs()
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
const currentSetNumber = computed(() => {
    const raw = Number(matchData.value?.current_set) || (sets.value.length > 0 ? sets.value.length : 1)
    // Không vượt quá số set của thể thức (best-of 1 chỉ có S1).
    return Math.min(Math.max(1, raw), setsInMatch.value)
})

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

// ============ TỰ LÀM MỚI & GIỮ TỈ SỐ ============
// Máy chiếu chạy cả ngày. Ba chế độ:
//   - trận đang đá  -> tự fetch mỗi AUTO_RELOAD_MS (dự phòng cho lúc Pusher rớt)
//   - trận vừa xong -> giữ nguyên tỉ số HOLD_AFTER_END_MS để khán giả kịp nhìn
//   - hết giờ       -> reload trang để dọn trạng thái cũ
const AUTO_RELOAD_MS = 5 * 60 * 1000      // 5 phút khi đang đá
const HOLD_AFTER_END_MS = 60 * 1000       // giữ tỉ số 60s sau khi trận kết thúc
const HARD_RELOAD_MS = 2 * 60 * 60 * 1000 // cứng lại sau 2h để tránh rò bộ nhớ

// Số giây còn lại của giai đoạn giữ tỉ số sau khi trận xong.
const holdRemaining = ref(0)

// Đồng hồ giữ tỉ số, dạng mm:ss cho footer.
const holdCountdown = computed(() => {
    const s = holdRemaining.value
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
})

let autoReloadTimer = null
let countdownTimer = null
let pageOpenedAt = Date.now()

// Trận đã kết thúc chưa?
const isMatchOver = computed(() => status.value === 'completed' || isConfirmed.value)

const currentInterval = computed(() => (isMatchOver.value ? HOLD_AFTER_END_MS : AUTO_RELOAD_MS))

const stopAutoReload = () => {
    if (autoReloadTimer) {
        clearTimeout(autoReloadTimer)
        autoReloadTimer = null
    }
    if (countdownTimer) {
        clearInterval(countdownTimer)
        countdownTimer = null
    }
}

const hardReload = () => {
    window.location.reload()
}

const scheduleAutoReload = () => {
    stopAutoReload()

    // Hết 2h: nạp lại cứng, giải phóng bộ nhớ cho phiên chiếu kéo dài.
    if (Date.now() - pageOpenedAt >= HARD_RELOAD_MS) {
        hardReload()
        return
    }

    let remaining = currentInterval.value

    // Sau khi trận kết thúc, chỉ giữ 60s rồi reload — không chạy vô hạn.
    const isHold = isMatchOver.value

    const deadline = Date.now() + remaining

    countdownTimer = setInterval(() => {
        const secs = Math.max(0, Math.ceil((deadline - Date.now()) / 1000))
        // Chỉ hiện đồng hồ giữ khi trận đã xong; lúc đang đá thì để kín.
        holdRemaining.value = isHold ? secs : 0
    }, 1000)

    autoReloadTimer = setTimeout(async () => {
        if (isHold) {
            // Hết giờ giữ -> trở về trạng thái chờ, sẵn sàng trận tiếp theo.
            hardReload()
            return
        }
        // Đang đá -> nạp lại nhẹ, giữ nguyên giao diện chiếu.
        try {
            await fetchLiveScore()
        } finally {
            scheduleAutoReload()
        }
    }, remaining)
}

// match_rules may arrive as an object or as an array holding one object; normalize.
const matchRules = computed(() => {
    const raw = matchData.value?.match_rules
    if (!raw) return null
    if (Array.isArray(raw)) return raw[0] ?? null
    if (typeof raw === 'object') return raw
    return null
})

// Số set thực tế của thể thức (best-of N -> N set). Mặc định 3 khi không có luật.
// Dùng để chỉ hiện đúng số section S1..SN thay vì luôn hardcode 3.
const setsInMatch = computed(() => {
    const n = Number(matchRules.value?.sets_per_match)
    return Number.isFinite(n) && n >= 1 && n <= 7 ? n : 3
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

const tournamentPoster = computed(() => {
    const d = matchData.value
    if (!d) return null
    return d.poster_url || d.tournament?.poster_url || null
})

// Ảnh nền: ưu tiên ảnh BTC tải lên, không có thì dùng poster giải.
const backgroundImage = computed(() => prefs.value.bgImageUrl || tournamentPoster.value || null)
const sponsorLogo = computed(() => prefs.value.sponsorLogoUrl || tournamentPoster.value || null)
const overlayAlpha = computed(() => OVERLAY_ALPHA[prefs.value.overlayLevel] ?? OVERLAY_ALPHA[60])

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

// Trận vừa kết thúc -> chuyển sang chế độ giữ 60s rồi nạp lại trang;
// trận được đấu lại -> quay lại nhịp nạp 5 phút. currentInterval tự tính lại.
// Đặt ở đây để cả nhánh kênh match.* và tournament.* đều được hưởng.
watch(isMatchOver, () => {
    scheduleAutoReload()
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

    // Bắt đầu chu kỳ tự nạp lại (dự phòng + giữ tỉ số sau khi trận xong).
    scheduleAutoReload()

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

    // Vào từ picker => nghe kênh cấp giải, lọc đúng trận đang xem.
    // Vào bằng link trực tiếp => nghe kênh cấp trận như cũ.
    const tournamentId = route.query.tournament
    if (tournamentId && window.Echo) {
        echoChannel = window.Echo.channel(`tournament.${tournamentId}`)
        echoChannel.listen('.tournament.score_updated', (payload) => {
            const row = (payload?.matches || []).find((m) => String(m.match_id) === String(matchId))
            if (!row || !matchData.value) return
            matchData.value = {
                ...matchData.value,
                live_status: row.live_status,
                current_set: row.current_set,
                version: row.version,
                // Trận đã chốt -> màn chiếu biết ngay, không cần chờ fetch lại.
                status: row.status ?? matchData.value.status,
            }
            // Snapshot cấp giải không mang chi tiết set — lấy lại khi điểm đổi.
            if (row.team1_score !== undefined) {
                const sets = Array.isArray(matchData.value.sets) ? [...matchData.value.sets] : []
                const idx = sets.findIndex((s) => s.set_number === row.current_set)
                const next = {
                    set_number: row.current_set,
                    team1_score: row.team1_score,
                    team2_score: row.team2_score,
                    serving_position: sets[idx]?.serving_position ?? 0,
                }
                if (idx >= 0) sets[idx] = next
                else sets.push(next)
                matchData.value.sets = sets
                tickStart.value = Date.now()
                elapsedTick.value = 0
            }
            lastUpdated.value = new Date()
        })
    } else if (matchId && window.Echo) {
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
        const { matchId } = route.params
        const tournamentId = route.query.tournament
        const chName = tournamentId ? `tournament.${tournamentId}` : `match.${matchId}`
        const event = tournamentId ? '.tournament.score_updated' : '.match.score_updated'
        echoChannel.stopListening(event)
        window.Echo.leave(chName)
        echoChannel = null
    }
    if (nowTimer) {
        clearInterval(nowTimer)
        nowTimer = null
    }
    stopAutoReload()
})
</script>

<style scoped>
/* ============================================================
   LAYOUT SHELL — .ls-shell is the query container; all sizing
   below scales off --u which derives from the container width.
   ============================================================ */
.ls-shell {
    container-type: inline-size;
    /* Máy chiếu: khóa trang, không cuộn ngang/dọc. */
    width: 100%;
    max-width: 100%;
    height: 100dvh;
    overflow: hidden;
    overscroll-behavior: none;
}

.ls-root {
    position: relative;
    overflow: hidden;
    height: 100dvh;
    max-width: 100%;
    color: var(--txt);
    background: var(--bg);
    /* ~1.76cqi tracks the mockup: 854px -> 15px, 490px -> 8.6px */
    --u: clamp(8px, 1.76cqi, 15px);
    /* Độ phủ do BTC chỉnh (40/60/80) — nhân lên độ đậm gốc của lớp phủ. */
    --ov: var(--overlay, 0.82);
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
    height: 100%;
    max-width: 100%;
    overflow: hidden;
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
    background: linear-gradient(180deg,
            rgba(5, 8, 14, var(--ov)) 0%,
            rgba(5, 8, 14, calc(var(--ov) * .6)) 22%,
            rgba(5, 8, 14, calc(var(--ov) * .6)) 74%,
            rgba(5, 8, 14, var(--ov)) 100%),
        radial-gradient(ellipse at 50% 46%,
            rgba(5, 8, 14, var(--ov)) 0%,
            rgba(5, 8, 14, calc(var(--ov) * .25)) 70%);
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
.panel-cap--sp {
    padding-top: calc(var(--u) * .8);
    border-top: 1px solid var(--div);
    margin-top: calc(var(--u) * .35);
}

/* Độ phủ 40/60/80 */
.ovl {
    display: flex;
    gap: calc(var(--u) * .35);
}
.ovl-btn {
    flex: 1;
    padding: calc(var(--u) * .35) 0;
    border-radius: calc(var(--u) * .3);
    background: var(--div);
    border: 1px solid var(--line);
    color: var(--t2);
    font-size: calc(var(--u) * .72);
    font-weight: 800;
    cursor: pointer;
    transition: background .15s, color .15s, border-color .15s;
}
.ovl-btn--on {
    background: var(--Aw);
    border-color: var(--A);
    color: var(--A);
}
.ovl-note {
    font-size: calc(var(--u) * .64);
    color: var(--t3);
    padding: calc(var(--u) * .3) calc(var(--u) * .2) 0;
    line-height: 1.35;
}

/* Màu đội */
.color-row {
    display: flex;
    flex-direction: column;
    gap: calc(var(--u) * .3);
}
.color-item {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * .5);
    padding: calc(var(--u) * .35) calc(var(--u) * .4);
    border-radius: calc(var(--u) * .3);
    background: var(--div);
    cursor: pointer;
}
.color-dot {
    width: calc(var(--u) * .95);
    height: calc(var(--u) * .95);
    border-radius: 50%;
    flex-shrink: 0;
    border: 1px solid var(--line);
}
.color-lbl {
    flex: 1;
    font-size: calc(var(--u) * .76);
    font-weight: 700;
    color: var(--t2);
}
.color-in {
    width: calc(var(--u) * 2.2);
    height: calc(var(--u) * 1.3);
    padding: 0;
    border: 1px solid var(--line);
    border-radius: calc(var(--u) * .25);
    background: transparent;
    cursor: pointer;
}

/* Bật/tắt hiển thị */
.tgl {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * .5);
    padding: calc(var(--u) * .32) calc(var(--u) * .4);
    border-radius: calc(var(--u) * .3);
    cursor: pointer;
}
.tgl:hover {
    background: var(--div);
}
.tgl-lbl {
    flex: 1;
    font-size: calc(var(--u) * .78);
    font-weight: 700;
    color: var(--t2);
}
.tgl-box {
    width: calc(var(--u) * 1.15);
    height: calc(var(--u) * 1.15);
    accent-color: var(--A);
    cursor: pointer;
}

.reset {
    width: 100%;
    margin-top: calc(var(--u) * .8);
    padding: calc(var(--u) * .5);
    border-radius: calc(var(--u) * .35);
    border: 1px solid var(--line);
    background: transparent;
    color: var(--t2);
    font-size: calc(var(--u) * .75);
    font-weight: 800;
    cursor: pointer;
    transition: background .15s, color .15s;
}
.reset:hover {
    background: var(--Bw);
    color: var(--B);
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
    /* Điểm to, 2 đội to: cho đội nhiều chỗ hơn nhưng vẫn chừa cột giữa cho số. */
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: calc(var(--u) * 1.8);
    padding: calc(var(--u) * 1.2) calc(var(--u) * 1.6);
    min-height: 0;
    max-width: 100%;
    overflow: hidden;
}

.side {
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: calc(var(--u) * .9);
    text-align: center;
}

.tnm {
    /* To: tên đội phải nổi bật ngang với điểm. */
    font-size: calc(var(--u) * 2.4);
    font-weight: 900;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    color: var(--t3);
}
.side--A .tnm { color: var(--A); }
.side--B .tnm { color: var(--B); }

.timeout {
    padding: calc(var(--u) * .36) calc(var(--u) * .85);
    border-radius: calc(var(--u) * .4);
    background: var(--Bw);
    border: 1px solid var(--B);
    font-size: calc(var(--u) * .85);
    font-weight: 800;
    color: var(--B);
    letter-spacing: .5px;
}

.avs {
    display: flex;
    justify-content: center;
    gap: calc(var(--u) * 1.8);
    flex-wrap: wrap;
}

.member {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: calc(var(--u) * .45);
}

.av {
    display: flex;
    align-items: center;
    justify-content: center;
    width: calc(var(--u) * 7.5);
    height: calc(var(--u) * 7.5);
    border-radius: 50%;
    overflow: hidden;
    background: var(--surf);
    border: calc(var(--u) * .2) solid var(--t3);
    font-size: calc(var(--u) * 2.6);
    font-weight: 800;
    color: var(--t3);
}
.side--A .av { border-color: var(--A); color: var(--A); }
.side--B .av { border-color: var(--B); color: var(--B); }

.pn {
    font-size: calc(var(--u) * 1.9);
    font-weight: 800;
    line-height: 1.3;
    color: var(--txt);
    max-width: calc(var(--u) * 15);
    overflow-wrap: anywhere;
}

.dupr {
    padding: calc(var(--u) * .3) calc(var(--u) * .9);
    border-radius: calc(var(--u) * .4);
    background: var(--surf);
    border: 1px solid var(--line);
    font-size: calc(var(--u) * 1.1);
    font-weight: 700;
    color: var(--t3);
    font-variant-numeric: tabular-nums;
}

.srv {
    display: inline-flex;
    align-items: center;
    padding: calc(var(--u) * .36) calc(var(--u) * .85);
    border-radius: calc(var(--u) * .4);
    background: var(--gold);
    font-size: calc(var(--u) * .85);
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
    /* To: màn chiếu lớn, phải đọc được từ hàng ghế cuối. */
    font-size: calc(var(--u) * 13);
    font-weight: 900;
    letter-spacing: calc(var(--u) * -.2);
    font-variant-numeric: tabular-nums;
}
.scn--A { color: var(--A); }
.scn--B { color: var(--B); }
.scd {
    font-size: calc(var(--u) * 6.4);
    font-weight: 300;
    color: var(--t3);
    margin: 0 calc(var(--u) * 1);
}

.set-row {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * .6);
    margin-top: calc(var(--u) * .5);
}
.set-lbl {
    font-size: calc(var(--u) * 1.15);
    font-weight: 900;
    letter-spacing: 1.6px;
    color: var(--t2);
}

.verified {
    display: inline-flex;
    align-items: center;
    padding: calc(var(--u) * .22) calc(var(--u) * .55);
    border-radius: calc(var(--u) * .35);
    background: color-mix(in srgb, var(--ok) 18%, transparent);
    border: 1px solid color-mix(in srgb, var(--ok) 45%, transparent);
    font-size: calc(var(--u) * .8);
    font-weight: 700;
    color: var(--ok);
}

.tabs {
    display: flex;
    align-items: center;
    margin-top: calc(var(--u) * .7);
}
.tab {
    padding: calc(var(--u) * .32) calc(var(--u) * .7);
    border-radius: calc(var(--u) * .35);
    background: var(--surf);
    border: 1px solid var(--line);
    font-size: calc(var(--u) * .92);
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
.tab-dot { margin: 0 calc(var(--u) * .4); color: var(--t3); font-size: calc(var(--u) * .95); }

.fmt {
    font-size: calc(var(--u) * .9);
    font-weight: 600;
    color: var(--t3);
    margin-top: calc(var(--u) * .6);
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

/* Đồng hồ giữ tỉ số sau khi trận xong — BTC/khách biết sẽ chuyển cảnh sau bao lâu. */
.lf-hold {
    display: inline-flex;
    align-items: center;
    padding: calc(var(--u) * .16) calc(var(--u) * .5);
    border-radius: calc(var(--u) * .3);
    background: color-mix(in srgb, var(--ok) 16%, transparent);
    border: 1px solid color-mix(in srgb, var(--ok) 42%, transparent);
    font-size: calc(var(--u) * .68);
    font-weight: 800;
    color: var(--ok);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
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
        /* Mobile: xếp dọc — Đội A trên, điểm ở giữa, Đội B dưới. */
        flex-direction: column;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: calc(var(--u) * 1.2);
        padding: calc(var(--u) * 1) calc(var(--u) * .8);
        min-height: 0;
        overflow: hidden;
    }

    /* Đội A trên cùng, đội B dưới cùng, điểm nằm giữa hai đội. */
    .side--A { order: 1; }
    .mid { order: 2; }
    .side--B { order: 3; }

    /* Mỗi đội là một hàng ngang: avatar trái, tên + thông tin phải. */
    .side {
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        gap: calc(var(--u) * .8);
        text-align: left;
        width: 100%;
        max-width: 100%;
    }
    .side > * { margin: 0; }
    .tnm {
        display: block;
        font-size: calc(var(--u) * 1.35);
        flex-shrink: 0;
        max-width: 42%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .avs { flex-shrink: 1; gap: calc(var(--u) * .5); min-width: 0; }
    .av { width: calc(var(--u) * 3.2); height: calc(var(--u) * 3.2); font-size: calc(var(--u) * 1.2); }
    .member { flex-direction: column; align-items: center; gap: calc(var(--u) * .25); }
    .pn { max-width: calc(var(--u) * 9); font-size: calc(var(--u) * .85); }
    .dupr { padding: calc(var(--u) * .14) calc(var(--u) * .4); font-size: calc(var(--u) * .62); }
    .srv { margin-left: auto; flex-shrink: 0; }
    .timeout { flex-shrink: 0; }

    /* Điểm: lớn nhất trên mobile vì đây là thứ khách nhìn. */
    .mid {
        padding: 0;
        width: 100%;
        gap: calc(var(--u) * .4);
    }

    .scn { font-size: calc(var(--u) * 5.2); line-height: 1; }
    .scd { font-size: calc(var(--u) * 3); line-height: 1; }
    .set-row { margin-top: calc(var(--u) * .4); }
    .set-lbl { font-size: calc(var(--u) * .95); }
    .tabs { margin-top: calc(var(--u) * .5); flex-wrap: wrap; justify-content: center; }
    .tab { font-size: calc(var(--u) * .78); padding: calc(var(--u) * .22) calc(var(--u) * .45); }
    .fmt { font-size: calc(var(--u) * .72); margin-top: calc(var(--u) * .3); }

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

/* ================================================================
   DESKTOP FULL-SCREEN OVERRIDE — scale everything by viewport width.
   All font sizes and spacing use vw so they grow with the screen,
   independent of the cqi-based --u unit used on smaller layouts.
   ================================================================ */
@media (min-width: 1024px) {
    /* Chuỗi layout thật: .ls-shell > .ls-root > .lsc > .lb
       => .lsc mới là lớp flex chứa header/body/footer. */
    .ls-shell {
        width: 100%;
        height: 100vh;
        overflow: hidden;
    }

    .ls-root {
        height: 100vh;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .lsc {
        flex: 1;
        min-height: 0;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    /* Header gọn lại để nhường chỗ cho phần điểm. */
    .lt {
        padding: 1vh 2vw;
        gap: 1vw;
    }

    .ls-logo {
        width: 2.4vw;
        height: 2.4vw;
    }

    .ls-sponsor {
        width: 6vw;
        height: 3.2vw;
    }

    .lb {
        flex: 1;
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: stretch;
        gap: 0;
        padding: 0;
        min-height: 0;
    }

    .side {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 1.5vh;
        padding: 3vh 3vw;
    }

    .tnm {
        font-size: min(3vw, 6.4vh);
        font-weight: 900;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .avs {
        display: flex;
        justify-content: center;
        gap: 2vw;
        flex-wrap: wrap;
    }

    .member {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .6vh;
    }

    .av {
        width: min(5vw, 10.5vh);
        height: min(5vw, 10.5vh);
        min-width: 60px;
        min-height: 60px;
        border-radius: 50%;
        border-width: 2px;
        font-size: min(1.5vw, 3.2vh);
        font-weight: 800;
    }

    .pn {
        font-size: 1.5vw;
        font-weight: 800;
        max-width: 10vw;
        text-align: center;
        overflow-wrap: break-word;
    }

    .dupr {
        font-size: .9vw;
        padding: .2vh .5vw;
        font-weight: 700;
    }

    .srv {
        font-size: .7vw;
        padding: .3vh .7vw;
        border-radius: .3vw;
        font-weight: 900;
    }

    .timeout {
        font-size: .75vw;
        padding: .3vh .7vw;
        border-radius: .3vw;
    }

    /* Score: chiếm trung tâm — phần quan trọng nhất. */
    .mid {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 4vh 2vw;
        min-width: 28vw;
    }

    .sc {
        display: flex;
        align-items: baseline;
        justify-content: center;
    }

    /* Chặn trên bằng vh để không tràn chiều cao trên màn siêu rộng/thấp. */
    .scn {
        font-size: min(10vw, 21vh);
        font-weight: 900;
        letter-spacing: -.3vw;
        font-variant-numeric: tabular-nums;
        line-height: 1;
    }

    .scd {
        font-size: min(5vw, 11vh);
        font-weight: 200;
        color: var(--t3);
        margin: 0 1vw;
        line-height: 1;
    }

    .set-row {
        margin-top: 1.5vh;
        gap: .8vw;
    }

    .set-lbl {
        font-size: 1.2vw;
        letter-spacing: .1em;
    }

    .verified {
        font-size: .8vw;
        padding: .2vh .5vw;
    }

    .tabs {
        margin-top: 1.2vh;
        gap: .5vw;
    }

    .tab {
        font-size: .95vw;
        padding: .3vh .6vw;
    }

    .tab-dot {
        font-size: .9vw;
        margin: 0 .4vw;
    }

    .fmt {
        font-size: .85vw;
        margin-top: 1vh;
    }

    /* Footer nhỏ lại — chỉ BTC cần thông tin. */
    .lf {
        padding: 1vh 2vw;
        gap: 2vw;
        min-height: auto;
    }

    .lf-left {
        font-size: .85vw;
    }

    .lf-hold {
        font-size: .65vw;
    }

    .lf-clock {
        font-size: .8vw;
    }

    .lf-clock svg {
        width: 1vw;
        height: 1vw;
    }

    .lf-ref {
        gap: .5vw;
    }

    .ref-av {
        width: 2.5vw;
        height: 2.5vw;
        min-width: 28px;
        min-height: 28px;
        font-size: .9vw;
    }

    .ref-lbl {
        font-size: .55vw;
    }

    .ref-val {
        font-size: .75vw;
    }
}
</style>