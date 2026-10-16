<template>
    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-3">Kết quả Rally</label>

        <!-- Stage points config badge -->
        <div v-if="stagePoints.length" class="mb-3 flex items-center gap-2 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <span class="font-medium">Rally {{ lastTarget }} · {{ stagePoints.length }} chặng</span>
            <span class="text-blue-500">· mốc {{ stagePoints.join('/') }}</span>
        </div>

        <!-- Total cumulative result -->
        <div v-if="hasAnyData" class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-center">
            <div class="text-xs font-bold text-green-700 uppercase tracking-wide mb-1">Kết quả chung cuộc</div>
            <div class="flex items-center justify-center gap-3">
                <span class="text-3xl font-black" :class="homeWins ? 'text-green-600' : 'text-blue-600'">{{ homeTotal }}</span>
                <span class="text-2xl font-light text-gray-400">–</span>
                <span class="text-3xl font-black" :class="awayWins ? 'text-green-600' : 'text-red-500'">{{ awayTotal }}</span>
            </div>
            <div class="mt-2 text-sm font-semibold" :class="matchWinner ? 'text-green-700' : 'text-gray-500'">
                {{ matchWinnerText }}
            </div>
        </div>

        <!-- Stage cards -->
        <div class="space-y-3">
            <div v-for="(stage, idx) in localStages" :key="stage.stage_no || idx"
                class="border rounded-xl overflow-hidden transition-all"
                :class="isStageComplete(stage) ? 'border-green-300 bg-white' : isStageEditable(idx) ? 'border-blue-300 bg-white' : 'border-gray-200 bg-gray-50 opacity-75'">

                <!-- Stage header -->
                <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm" :class="isStageComplete(stage) ? 'text-green-700' : 'text-blue-700'">
                            Chặng {{ stage.stage_no }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500 font-medium">mốc {{ stage.target_point || stagePoints[idx] }}</span>
                        <span v-if="isStageComplete(stage)" class="text-xs font-bold px-2 py-0.5 rounded-full text-white" :class="getStageWinnerClass(stage)">
                            THẮNG CHẶNG
                        </span>
                    </div>
                </div>

                <!-- Stage body -->
                <div class="p-4">
                    <!-- Member selection per team -->
                    <div class="grid grid-cols-[1fr_auto_1fr] gap-3 items-center mb-4">
                        <!-- Home team members -->
                        <div class="space-y-1.5">
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Đội nhà</div>
                            <div v-for="n in membersPerStage" :key="'home-' + n"
                                @click="canSelectMembers && isStageEditable(idx) && selectMember('home', idx, n - 1)"
                                class="border rounded-lg p-2 flex items-center gap-2 transition-all cursor-pointer"
                                :class="getMemberCardClass('home', stage, n - 1)">
                                <template v-if="getSelectedMember('home', stage, n - 1)">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0" :style="{ background: getMemberBg(getSelectedMember('home', stage, n - 1)) }">
                                        {{ getMemberInitials(getSelectedMember('home', stage, n - 1)) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-bold truncate">{{ getSelectedMember('home', stage, n - 1).name }}</div>
                                        <div class="text-[10px] text-gray-400">{{ getSelectedMember('home', stage, n - 1).gender_label || '' }}</div>
                                    </div>
                                </template>
                                <div v-else class="flex items-center gap-2 flex-1 text-gray-400">
                                    <div class="w-8 h-8 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center text-sm">+</div>
                                    <span class="text-xs">Chọn</span>
                                </div>
                            </div>
                        </div>

                        <!-- VS -->
                        <div class="text-xs font-bold text-gray-400 self-start mt-5">VS</div>

                        <!-- Away team members -->
                        <div class="space-y-1.5">
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 text-right">Đội khách</div>
                            <div v-for="n in membersPerStage" :key="'away-' + n"
                                @click="canSelectMembers && isStageEditable(idx) && selectMember('away', idx, n - 1)"
                                class="border rounded-lg p-2 flex items-center gap-2 transition-all cursor-pointer"
                                :class="getMemberCardClass('away', stage, n - 1)">
                                <template v-if="getSelectedMember('away', stage, n - 1)">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0" :style="{ background: getMemberBg(getSelectedMember('away', stage, n - 1)) }">
                                        {{ getMemberInitials(getSelectedMember('away', stage, n - 1)) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-bold truncate">{{ getSelectedMember('away', stage, n - 1).name }}</div>
                                        <div class="text-[10px] text-gray-400">{{ getSelectedMember('away', stage, n - 1).gender_label || '' }}</div>
                                    </div>
                                </template>
                                <div v-else class="flex items-center gap-2 flex-1 text-gray-400 flex-row-reverse">
                                    <div class="w-8 h-8 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center text-sm">+</div>
                                    <span class="text-xs">Chọn</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Score input -->
                    <div class="flex items-center justify-center gap-3">
                        <!-- Home score -->
                        <div class="flex flex-col items-center">
                            <button v-if="isStageEditable(idx)" @click="decrementStageScore(idx, 'home')"
                                class="w-12 h-10 bg-gray-100 hover:bg-gray-200 rounded-t-lg flex items-center justify-center text-gray-600 transition-colors">
                                <MinusIcon class="w-5 h-5" />
                            </button>
                            <div class="w-20 h-14 rounded-lg border-2 flex items-center justify-center font-black text-2xl transition-colors"
                                :class="isStageEditable(idx)
                                    ? 'border-blue-300 bg-white text-blue-600'
                                    : getStageScore(stage, 'home') > getStageScore(stage, 'away')
                                        ? 'border-green-400 bg-green-50 text-green-600'
                                        : 'border-gray-200 bg-gray-50 text-gray-400'">
                                {{ getDisplayCumulative(stage, 'home') }}
                            </div>
                            <button v-if="isStageEditable(idx)" @click="incrementStageScore(idx, 'home')"
                                class="w-12 h-10 bg-gray-100 hover:bg-gray-200 rounded-b-lg flex items-center justify-center text-gray-600 transition-colors">
                                <PlusIcon class="w-5 h-5" />
                            </button>
                        </div>

                        <span class="text-xl font-bold text-gray-400 mt-2">–</span>

                        <!-- Away score -->
                        <div class="flex flex-col items-center">
                            <button v-if="isStageEditable(idx)" @click="incrementStageScore(idx, 'away')"
                                class="w-12 h-10 bg-gray-100 hover:bg-gray-200 rounded-t-lg flex items-center justify-center text-gray-600 transition-colors">
                                <PlusIcon class="w-5 h-5" />
                            </button>
                            <div class="w-20 h-14 rounded-lg border-2 flex items-center justify-center font-black text-2xl transition-colors"
                                :class="isStageEditable(idx)
                                    ? 'border-red-300 bg-white text-red-500'
                                    : getStageScore(stage, 'away') > getStageScore(stage, 'home')
                                        ? 'border-green-400 bg-green-50 text-green-600'
                                        : 'border-gray-200 bg-gray-50 text-gray-400'">
                                {{ getDisplayCumulative(stage, 'away') }}
                            </div>
                            <button v-if="isStageEditable(idx)" @click="decrementStageScore(idx, 'away')"
                                class="w-12 h-10 bg-gray-100 hover:bg-gray-200 rounded-b-lg flex items-center justify-center text-gray-600 transition-colors">
                                <MinusIcon class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <div class="text-center mt-2">
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">
                            Điểm luỹ kế tại mốc {{ stage.target_point || stagePoints[idx] }}
                        </div>
                    </div>

                    <!-- Per-stage score breakdown -->
                    <div v-if="isStageComplete(stage) || hasStageScore(stage)" class="mt-3 text-center">
                        <div class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 rounded-full text-xs font-semibold" :class="getStageScore(stage, 'home') > getStageScore(stage, 'away') ? 'text-green-700' : 'text-gray-600'">
                            Chặng {{ stage.stage_no }} · {{ getStageScore(stage, 'home') }} – {{ getStageScore(stage, 'away') }}
                            <span v-if="hasAnyData" class="text-gray-400 ml-1">(riêng)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hint -->
        <p class="mt-3 text-xs text-gray-400 leading-relaxed">
            Nhập điểm luỹ kế tại mỗi mốc. Hệ thống tự tính điểm riêng của mỗi chặng bằng hiệu số hai mốc liền nhau.
        </p>

        <!-- Inline member picker -->
        <div v-if="pickerState.open" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40"
            @click.self="closePicker">
            <div class="bg-white w-full sm:max-w-md sm:rounded-2xl rounded-t-2xl p-4 shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-bold text-gray-800">
                        Chọn {{ pickerState.side === 'home' ? 'Đội nhà' : 'Đội khách' }} · Chặng {{ localStages[pickerState.stageIdx]?.stage_no }}
                    </div>
                    <button @click="closePicker" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
                </div>
                <div v-if="!pickerState.available.length" class="text-sm text-gray-400 text-center py-6">Không có thành viên</div>
                <div v-else class="max-h-80 overflow-y-auto space-y-1.5">
                    <button v-for="m in pickerState.available" :key="m.id"
                        type="button"
                        @click="pickMember(m)"
                        :disabled="usedMemberIdsInStage.has(m.id)"
                        class="w-full flex items-center gap-2 p-2 rounded-lg border transition-colors text-left"
                        :class="usedMemberIdsInStage.has(m.id)
                            ? 'border-gray-100 bg-gray-50 opacity-40 cursor-not-allowed'
                            : (pickerState.current?.id === m.id ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:bg-gray-50')">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0" :style="{ background: getMemberBg(m) }">
                            {{ getMemberInitials(m) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold truncate">{{ m.name }}</div>
                            <div class="text-[11px] text-gray-400">{{ m.gender_label || (m.gender === 1 ? 'Nam' : (m.gender === 0 ? 'Nữ' : (m.gender === 2 ? 'Nữ' : ''))) }}</div>
                        </div>
                        <span v-if="pickerState.current?.id === m.id" class="text-xs font-bold text-blue-600">✓</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { PlusIcon, MinusIcon } from '@heroicons/vue/24/solid'
import { toast } from 'vue3-toastify'

const MEMBER_COLORS = ['#8E4B3C','#3C4A63','#2F6B4A','#4A4A52','#C2546E','#7A4B5B','#B4762A','#5B4A7A']

const props = defineProps({
    /**
     * v-model: array of stages, each item shape (mirrors BE rally_stages):
     *   { stage_no, cumulative_score_a, cumulative_score_b, target_point,
     *     members: { team_a: [{user_id, name}], team_b: [{user_id, name}] } }
     */
    modelValue: {
        type: Array,
        default: () => []
    },
    /** Target points per stage, e.g. [10, 20, 30] (mirrors tournament_types.match_rules.stage_points). */
    stagePoints: {
        type: Array,
        default: () => [10, 20, 30]
    },
    membersPerStage: {
        type: Number,
        default: 2
    },
    /** Available home team members, used by the picker: [{ id, name, gender }]. */
    homeMembers: {
        type: Array,
        default: () => []
    },
    awayMembers: {
        type: Array,
        default: () => []
    },
    canEdit: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['update:modelValue'])

// Combined lookup: any user_id -> { id, name, gender, gender_label, avatar, ... }
const memberLookup = computed(() => {
    const map = new Map()
    for (const m of [...(props.homeMembers || []), ...(props.awayMembers || [])]) {
        if (m?.id == null) continue
        map.set(m.id, {
            id: m.id,
            name: m.name || m.full_name,
            gender: m.gender,
            gender_label: m.gender === 1 ? 'Nam' : (m.gender === 2 || m.gender === 0 ? 'Nữ' : null),
            avatar: m.avatar || m.avatar_url,
        })
    }
    return map
})

function createDefaultStage(stageNo, targetPoint) {
    return {
        stage_no: stageNo,
        cumulative_score_a: 0,
        cumulative_score_b: 0,
        target_point: targetPoint,
        members: { team_a: [], team_b: [] }
    }
}

const localStages = computed({
    get() {
        if (!props.modelValue || props.modelValue.length === 0) {
            return props.stagePoints.map((target, idx) => createDefaultStage(idx + 1, target))
        }
        return props.modelValue
    },
    set(val) {
        emit('update:modelValue', val)
    }
})

// Seed defaults only if parent has nothing yet. Once parent passes data, treat
// it as source of truth — rebuilding would wipe scores the user just entered.
watch(() => props.stagePoints, (newPoints) => {
    if (props.modelValue && props.modelValue.length > 0) return
    localStages.value = newPoints.map((t, i) => createDefaultStage(i + 1, t))
}, { immediate: true })

// Inline picker state
const pickerState = ref({ open: false, side: null, stageIdx: 0, slotIdx: 0, current: null, available: [] })

function closePicker() {
    pickerState.value = { open: false, side: null, stageIdx: 0, slotIdx: 0, current: null, available: [] }
}

function pickMember(member) {
    setMemberForSlot(pickerState.value.side, pickerState.value.stageIdx, pickerState.value.slotIdx, member.user_id ?? member.id)
    closePicker()
}

const usedMemberIdsInStage = computed(() => {
    if (!pickerState.value.open) return new Set()
    const stage = localStages.value[pickerState.value.stageIdx]
    const list = pickerState.value.side === 'home' ? stage?.members?.team_a : stage?.members?.team_b
    const cur = pickerState.value.current?.id
    const set = new Set((list || []).map(m => m?.user_id).filter(Boolean))
    if (cur) set.delete(cur)
    return set
})

const lastTarget = computed(() => props.stagePoints[props.stagePoints.length - 1] || 30)
const hasAnyData = computed(() =>
    localStages.value.some(s => (s.cumulative_score_a || 0) > 0 || (s.cumulative_score_b || 0) > 0)
)

// Per-stage score = difference between consecutive cumulative points.
// First stage = its own cumulative (the delta from 0).
function getStageScore(stage, team) {
    const idx = localStages.value.findIndex(s => s.stage_no === stage.stage_no)
    if (idx <= 0) {
        return team === 'home' ? (stage.cumulative_score_a || 0) : (stage.cumulative_score_b || 0)
    }
    const prev = localStages.value[idx - 1]
    const currentCum = team === 'home' ? (stage.cumulative_score_a || 0) : (stage.cumulative_score_b || 0)
    const prevCum = team === 'home' ? (prev?.cumulative_score_a || 0) : (prev?.cumulative_score_b || 0)
    return Math.max(0, currentCum - prevCum)
}

// Display value for a team's cumulative at a stage. If the stage has no
// recorded score yet, show the previous stage's cumulative (for a non-first
// stage) so the user sees where the running total currently sits — not a
// confusing 0.
function getDisplayCumulative(stage, team) {
    const idx = localStages.value.findIndex(s => s.stage_no === stage.stage_no)
    const own = team === 'home' ? (stage.cumulative_score_a || 0) : (stage.cumulative_score_b || 0)
    if (own > 0) return own
    if (idx <= 0) return 0
    const prev = localStages.value[idx - 1]
    return team === 'home' ? (prev?.cumulative_score_a || 0) : (prev?.cumulative_score_b || 0)
}

function hasStageScore(stage) {
    return getStageScore(stage, 'home') > 0 || getStageScore(stage, 'away') > 0
}

const homeTotal = computed(() => {
    const last = localStages.value[localStages.value.length - 1]
    return last?.cumulative_score_a || 0
})
const awayTotal = computed(() => {
    const last = localStages.value[localStages.value.length - 1]
    return last?.cumulative_score_b || 0
})

function isStageComplete(stage) {
    const target = stage.target_point || props.stagePoints[(stage.stage_no || 1) - 1] || 10
    return (stage.cumulative_score_a || 0) >= target || (stage.cumulative_score_b || 0) >= target
}

function isStageEditable(idx) {
    if (!props.canEdit) return false
    for (let i = 0; i < localStages.value.length; i++) {
        if (!isStageComplete(localStages.value[i])) return i === idx
    }
    return false
}

const canSelectMembers = computed(() => props.canEdit)

const homeWins = computed(() => hasAnyData.value && homeTotal.value > awayTotal.value)
const awayWins = computed(() => hasAnyData.value && awayTotal.value > homeTotal.value)
const matchWinner = computed(() => homeWins.value || awayWins.value)
const matchWinnerText = computed(() => {
    if (!hasAnyData.value) return ''
    if (homeWins.value && awayWins.value) return 'Hòa'
    const homeStages = localStages.value.filter(s => getStageScore(s, 'home') > getStageScore(s, 'away')).length
    if (homeWins.value) return `Thắng ${homeStages}/${localStages.value.length} chặng`
    if (awayWins.value) return `Thắng ${localStages.value.length - homeStages}/${localStages.value.length} chặng`
    return ''
})

function getStageWinnerClass(stage) {
    return getStageScore(stage, 'home') > getStageScore(stage, 'away') ? 'bg-blue-600' : 'bg-red-500'
}

// Increment/decrement a stage's delta; cumulative = prev_cum + delta (clamped).
function _setDelta(idx, team, newDelta) {
    const stages = [...localStages.value]
    const stage = { ...stages[idx] }
    const target = stage.target_point || props.stagePoints[idx] || 10
    const key = team === 'home' ? 'cumulative_score_a' : 'cumulative_score_b'
    const prev = idx > 0 ? (stages[idx - 1][key] || 0) : 0
    const clamped = Math.max(0, Math.min(newDelta, target - prev))
    stage[key] = prev + clamped
    stages[idx] = stage
    localStages.value = stages
}

function incrementStageScore(idx, team) {
    _setDelta(idx, team, getStageScore(localStages.value[idx], team) + 1)
}
function decrementStageScore(idx, team) {
    _setDelta(idx, team, getStageScore(localStages.value[idx], team) - 1)
}

// Resolve a saved member slot (just {user_id, name}) to a full member from the
// lookup. Used for display + duplicate-pick detection.
function getSelectedMember(side, stage, slotIdx) {
    if (!stage) return null
    const list = side === 'home' ? stage.members?.team_a : stage.members?.team_b
    const slot = list?.[slotIdx]
    if (!slot) return null
    if (slot.user_id != null && memberLookup.value.has(slot.user_id)) {
        return memberLookup.value.get(slot.user_id)
    }
    return { id: slot.user_id, name: slot.name || `User #${slot.user_id}`, gender_label: null }
}

function selectMember(side, stageIdx, slotIdx) {
    if (!props.canEdit || !isStageEditable(stageIdx)) return
    const available = side === 'home' ? props.homeMembers : props.awayMembers
    if (!available.length) {
        toast.error('Chưa có thành viên đội này')
        return
    }
    const current = getSelectedMember(side, localStages.value[stageIdx], slotIdx)
    pickerState.value = { open: true, side, stageIdx, slotIdx, current, available }
}

function getMemberCardClass(side, stage, slotIdx) {
    const selected = getSelectedMember(side, stage, slotIdx)
    if (!selected) return 'border-dashed border-gray-300 bg-gray-50 hover:bg-gray-100'
    return side === 'home' ? 'border-blue-200 bg-blue-50 hover:bg-blue-100' : 'border-red-200 bg-red-50 hover:bg-red-100'
}

function getMemberBg(member) {
    if (!member) return '#9AA0A8'
    const hash = (member.name || '').split('').reduce((acc, c) => acc + c.charCodeAt(0), 0)
    return MEMBER_COLORS[hash % MEMBER_COLORS.length]
}

function getMemberInitials(member) {
    if (!member?.name) return '?'
    const parts = member.name.trim().split(/\s+/)
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
    return member.name.substring(0, 2).toUpperCase()
}

// Set a slot to a user_id (saved as {user_id, name}).
function setMemberForSlot(side, stageIdx, slotIdx, userId) {
    const stages = [...localStages.value]
    const stage = { ...stages[stageIdx] }
    stage.members = { ...stage.members }
    const key = side === 'home' ? 'team_a' : 'team_b'
    const next = [...(stage.members[key] || [])]
    const lookup = memberLookup.value.get(userId)
    next[slotIdx] = { user_id: userId, name: lookup?.name || next[slotIdx]?.name || `User #${userId}` }
    stage.members[key] = next
    stages[stageIdx] = stage
    localStages.value = stages
}

defineExpose({ setMemberForSlot, localStages })
</script>
