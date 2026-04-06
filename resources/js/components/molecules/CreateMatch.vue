<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="isOpen"
                class="fixed inset-0 bg-black backdrop-blur-[1px] bg-opacity-50 flex items-center justify-center z-50 p-4"
                @click.self="closeModal">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl h-[85vh] max-h-[95vh] flex flex-col">
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <h2 class="text-2xl font-semibold text-gray-800">{{ data.round_name || 'Trận đấu' }}</h2>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>
                    <div v-if="data.legs?.length > 1" class="px-6 pt-3 ">
                        <div class="flex gap-2">
                            <button v-for="(leg, index) in data.legs" :key="leg.id" @click="selectedLegIndex = index"
                                class="px-4 py-2 rounded-md text-sm font-semibold transition" :class="selectedLegIndex === index
                                    ? 'bg-red-500 text-white'
                                    : 'bg-white border text-gray-600 hover:bg-gray-100'">
                                Lượt {{ leg.leg }}
                            </button>
                        </div>
                    </div>

                    <div class="p-6 overflow-y-auto flex-1">
                        <div class="grid grid-cols-[2fr_3fr] gap-6">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Chọn sân</label>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            <ClipboardIcon class="w-6 h-6" />
                                            <span class="text-gray-700">Sân số</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button @click="decrementCourt"
                                                class="w-8 h-8 flex items-center justify-center bg-gray-800 text-white rounded hover:bg-gray-700 transition-colors">
                                                <span class="text-lg">−</span>
                                            </button>
                                            <input v-model.number="courtNumber" type="text"
                                                class="w-16 text-center border border-gray-300 rounded px-3 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                            <button @click="incrementCourt"
                                                class="w-8 h-8 flex items-center justify-center bg-gray-800 text-white rounded hover:bg-gray-700 transition-colors">
                                                <span class="text-lg">+</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 text-gray-700 mb-1">
                                    <CalendarDaysIcon class="w-5 h-5" />
                                    <span>{{ formatEventDate(tournament.start_date) }}</span>
                                </div>

                                <div class="flex items-center gap-2 text-gray-700">
                                    <MapPinIcon class="w-5 h-5" />
                                    <span class="truncate w-64">{{ tournament.competition_location?.name }}</span>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-600 mb-3">
                                        Kết quả kèo đấu được ghi nhận khi tất cả người chơi đã quét mã QR
                                    </p>
                                    <div class="w-full h-auto p-3 rounded-lg flex items-center justify-center">
                                        <qrcode-vue :value="qrCodeUrl" :size="250" level="H" />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Chọn đội</label>
                                <div class="grid grid-cols-[2fr_1fr_2fr] gap-4 items-stretch mb-6">
                                    <div
                                        class="border border-1 border-[#DCDEE6] bg-[#F2F7FC] rounded-lg p-3 flex flex-col">
                                        <p class="text-center mb-4">{{ data.home_team?.name || 'Team A' }}</p>
                                        <div class="flex gap-2 justify-around items-stretch">
                                            <UserCard v-for="member in data.home_team?.members || []" :key="member.id"
                                                :showHoverDelete="false" :name="member.name" :avatar="member.avatar"
                                                :size="12" :badgeSize="5" class="cursor-pointer" />
                                            <UserCard v-for="n in emptySlots('home')" :key="'empty-home-' + n" empty
                                                :size="12" :badgeSize="5" class="cursor-pointer" />
                                        </div>
                                    </div>

                                    <div class="flex justify-center items-center">
                                        <span class="text-sm font-bold">VS</span>
                                    </div>

                                    <div
                                        class="border border-1 border-[#DCDEE6] bg-[#F2F7FC] rounded-lg p-3 flex flex-col">
                                        <p class="text-center mb-4">{{ data.away_team?.name || 'Team B' }}</p>
                                        <div class="flex gap-2 justify-around items-stretch">
                                            <UserCard v-for="member in data.away_team?.members || []" :key="member.id"
                                                :showHoverDelete="false" :name="member.name" :avatar="member.avatar"
                                                :size="12" :badgeSize="5" class="cursor-pointer" />
                                            <UserCard v-for="n in emptySlots('away')" :key="'empty-away-' + n" empty
                                                :size="12" :badgeSize="5" class="cursor-pointer" />
                                        </div>
                                    </div>
                                </div>

                                <label class="block text-sm font-semibold text-gray-700 mb-3">Kết quả</label>

                                <div v-for="(score, index) in scores" :key="index" class="mb-4">
                                    <div class="grid grid-cols-[2fr_1fr_2fr] gap-4 items-center">
                                        <div class="border border-1 border-[#DCDEE6] rounded-lg p-3">
                                            <button @click="incrementScore(index, 'A')"
                                                class="w-full bg-[#EDEEF2] rounded px-3 py-2 text-gray-600 hover:bg-gray-300 transition-colors mb-2 flex items-center justify-center">
                                                <PlusIcon class="w-5 h-5" />
                                            </button>
                                            <div class="text-center text-2xl font-bold mb-2">{{ score.teamA }}</div>
                                            <button @click="decrementScore(index, 'A')"
                                                class="w-full bg-[#EDEEF2] rounded px-3 py-2 text-gray-600 hover:bg-gray-300 transition-colors flex items-center justify-center">
                                                <MinusIcon class="w-5 h-5" />
                                            </button>
                                        </div>

                                        <div class="flex flex-col items-center gap-2">
                                            <span class="text-sm font-semibold">Set {{ index + 1 }}</span>
                                            <button v-if="scores.length > 1" @click="removeSet(index)"
                                                class="text-red-500 hover:text-red-700 transition-colors">
                                                <XMarkIcon class="w-5 h-5" />
                                            </button>
                                        </div>

                                        <div class="border border-1 border-[#DCDEE6] rounded-lg p-3">
                                            <button @click="incrementScore(index, 'B')"
                                                class="w-full bg-[#EDEEF2] rounded px-3 py-2 text-gray-600 hover:bg-gray-300 transition-colors mb-2 flex items-center justify-center">
                                                <PlusIcon class="w-5 h-5" />
                                            </button>
                                            <div class="text-center text-2xl font-bold mb-2">{{ score.teamB }}</div>
                                            <button @click="decrementScore(index, 'B')"
                                                class="w-full bg-[#EDEEF2] rounded px-3 py-2 text-gray-600 hover:bg-gray-300 transition-colors flex items-center justify-center">
                                                <MinusIcon class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <button @click="addSet"
                                    class="w-full flex justify-center items-center gap-2 border p-3 rounded-lg text-[#838799] hover:bg-gray-100 transition-colors mb-6">
                                    <PlusIcon class="w-5 h-5" />
                                    <span class="text-sm font-semibold">Thêm set</span>
                                </button>

                                <!-- Nút tiến vào vòng trong -->
                                <div v-if="shouldShowAdvanceButtons" class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <p class="text-sm text-gray-700 mb-3 text-center font-medium">
                                        Trận đấu hòa! Chọn đội tiến vào vòng trong:
                                    </p>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button @click="handleAdvanceTeam(data.home_team.id)" :disabled="isAdvancing"
                                            class="py-2 px-4 bg-blue-600 text-white rounded font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                            {{ data.home_team?.name || 'Team A' }}
                                        </button>
                                        <button @click="handleAdvanceTeam(data.away_team.id)" :disabled="isAdvancing"
                                            class="py-2 px-4 bg-blue-600 text-white rounded font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                            {{ data.away_team?.name || 'Team B' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 py-4 bg-white rounded-b-lg border-t">
                        <div class="flex gap-3">
                            <button @click="saveMatch" :disabled="isSaving"
                                class="px-12 py-3 bg-red-500 text-white rounded font-medium hover:bg-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ isSaving && !canConfirmMatch ? 'Đang lưu...' : 'Lưu' }}
                            </button>
                            <button v-if="isCreator || isReferee" @click="confirmMatchResult"
                                :disabled="isSaving || !canConfirmMatch || currentLeg.status === 'completed'"
                                class="flex items-center justify-center gap-2 px-12 py-3 bg-green-500 text-white rounded font-medium hover:bg-green-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                <template v-if="currentLeg.status === 'completed'">
                                    <CheckBadgeIcon class="w-6 h-6 text-white" />
                                    <span>Đã xác nhận</span>
                                </template>
                                <template v-else>
                                    {{ isSaving ? 'Đang xác nhận...' : 'Xác nhận' }}
                                </template>
                            </button>
                            <button @click="closeModal" :disabled="isSaving"
                                class="px-12 py-3 bg-gray-200 text-gray-700 rounded font-medium hover:bg-gray-300 transition-colors disabled:opacity-50">
                                Hủy
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Confirmation Modal -->
                <div v-if="showConfirmClose" 
                    class="absolute inset-0 z-[60] flex items-center justify-center bg-black bg-opacity-50"
                    @click.self="handleCancelClose">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6 transform transition-all">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-2">
                            Lưu thay đổi?
                        </h3>
                        <p class="text-sm text-gray-500 mb-6">
                            Bạn có thay đổi chưa được lưu. Bạn có muốn lưu lại trước khi đóng không?
                        </p>
                        <div class="flex flex-col gap-3">
                            <button @click="handleConfirmSave"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:text-sm">
                                Lưu và Đóng
                            </button>
                            <button @click="handleDiscard"
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:text-sm">
                                Không lưu
                            </button>
                            <button @click="handleCancelClose"
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-gray-100 text-base font-medium text-gray-700 hover:bg-gray-200 focus:outline-none sm:text-sm">
                                Hủy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { MinusIcon, PlusIcon, XMarkIcon, CheckBadgeIcon } from '@heroicons/vue/24/solid'
import { ClipboardIcon, CalendarDaysIcon, MapPinIcon } from '@heroicons/vue/24/outline'
import UserCard from './UserCard.vue'
import { formatEventDate } from '@/composables/formatDatetime.js'
import QrcodeVue from 'qrcode.vue'
import { toast } from 'vue3-toastify'
import * as MatchesServices from '@/service/match.js'
import { useUserStore } from '@/store/auth'
import { storeToRefs } from 'pinia'

/* ===================== PROPS ===================== */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    data: { type: Object, default: () => ({}) },
    tournament: { type: Object, default: () => ({ player_per_team: 0 }) }
})

/* ===================== AUTH ===================== */
const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)
const isCreator = computed(() => {
    return props.tournament?.tournament_staff?.some(
        staff => staff.role === 1 && staff.staff?.id === getUser.value.id
    )
})

const isReferee = computed(() => {
    return props.tournament?.tournament_staff?.some(
        staff => staff.role === 3 && staff.staff?.id === getUser.value.id
    )
})

/* ===================== MODAL ===================== */
const emit = defineEmits(['update:modelValue', 'updated'])
const isOpen = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
})

const isSaving = ref(false)
const isAdvancing = ref(false)

/* ===================== LEG LOGIC ===================== */
const selectedLegIndex = ref(0)

const currentLeg = computed(() => {
    return props.data.legs?.[selectedLegIndex.value] || props.data
})

/* ===================== COURT ===================== */
const courtNumber = ref(1)

const incrementCourt = () => courtNumber.value++
const decrementCourt = () => {
    if (courtNumber.value > 1) courtNumber.value--
}

/* ===================== SCORES ===================== */
const scores = ref([])

/* ===================== INIT SCORES ===================== */
const initializeScores = () => {
    if (currentLeg.value?.sets) {
        return Object.values(currentLeg.value.sets).map(setArray => ({
            teamA: setArray.find(s => s.team_id === props.data.home_team?.id)?.score || 0,
            teamB: setArray.find(s => s.team_id === props.data.away_team?.id)?.score || 0
        }))
    }
    return [{ teamA: 0, teamB: 0 }]
}

/* ===================== WATCH LEG ===================== */
watch(
    currentLeg,
    (leg) => {
        courtNumber.value = leg?.court || 1
        scores.value = initializeScores()
    },
    { immediate: true }
)

/* ===================== WATCH DATA ===================== */
watch(
    () => props.data,
    () => {
        scores.value = initializeScores()
        courtNumber.value = currentLeg.value?.court || 1
    },
    { deep: true }
)

/* ===================== ADVANCE TEAM LOGIC ===================== */
const calculateMatchStats = () => {
    if (!props.data.legs || props.data.legs.length === 0) {
        return { homeLegsWon: 0, awayLegsWon: 0, homeSetsWon: 0, awaySetsWon: 0, homePoints: 0, awayPoints: 0 }
    }

    let homeLegsWon = 0
    let awayLegsWon = 0
    let homeSetsWon = 0
    let awaySetsWon = 0
    let homePoints = 0
    let awayPoints = 0

    props.data.legs.forEach(leg => {
        if (!leg.sets) return

        const sets = Object.values(leg.sets)
        let legHomeWins = 0
        let legAwayWins = 0

        sets.forEach(setArray => {
            const homeSet = setArray.find(s => s.team_id === props.data.home_team?.id)
            const awaySet = setArray.find(s => s.team_id === props.data.away_team?.id)

            if (homeSet && awaySet) {
                homePoints += homeSet.score || 0
                awayPoints += awaySet.score || 0

                if (homeSet.won_match === 1) {
                    homeSetsWon++
                    legHomeWins++
                } else if (awaySet.won_match === 1) {
                    awaySetsWon++
                    legAwayWins++
                }
            }
        })

        // Xác định leg winner
        if (legHomeWins > legAwayWins) {
            homeLegsWon++
        } else if (legAwayWins > legHomeWins) {
            awayLegsWon++
        }
    })

    return { homeLegsWon, awayLegsWon, homeSetsWon, awaySetsWon, homePoints, awayPoints }
}

const shouldShowAdvanceButtons = computed(() => {
    // 1. Phải có leg
    if (!props.data.legs || props.data.legs.length === 0) return false

    // 2. Tất cả leg phải completed
    const allLegsCompleted = props.data.legs.every(
        leg => leg.status === 'completed'
    )
    if (!allLegsCompleted) return false

    // 3. Nếu đã có winner thì không hiển thị
    if (props.data.winner_id) return false

    const stats = calculateMatchStats()

    // 4. Phải có ít nhất 1 set hoặc 1 điểm được ghi nhận
    const hasAnyResult =
        stats.homeSetsWon > 0 ||
        stats.awaySetsWon > 0 ||
        stats.homePoints > 0 ||
        stats.awayPoints > 0

    if (!hasAnyResult) return false

    // 5. Hòa hoàn toàn
    return (
        stats.homeLegsWon === stats.awayLegsWon &&
        stats.homeSetsWon === stats.awaySetsWon &&
        stats.homePoints === stats.awayPoints
    )
})


const handleAdvanceTeam = async (teamId) => {
    if (isAdvancing.value) return
    
    try {
        isAdvancing.value = true
        await MatchesServices.advanceTeamManual(props.data.id, { winner_team_id: teamId })
        toast.success('Đội đã được chọn tiến vào vòng trong!')
        emit('updated')
        isOpen.value = false
    } catch (error) {
        const errorMsg = error.response?.data?.message || 'Có lỗi xảy ra khi chọn đội tiến vòng'
        toast.error(errorMsg)
    } finally {
        isAdvancing.value = false
    }
}

/* ===================== QR CODE ===================== */
const qrCodeUrl = computed(() => {
    if (!currentLeg.value?.id) return ''
    return `${window.location.origin}/match/${currentLeg.value.id}/verify`
})

/* ===================== SCORE ACTIONS ===================== */
const incrementScore = (idx, team) => {
    // const maxPoints = props.tournament?.tournament_types?.[0]?.match_rules?.[0]?.max_points || 11
    // if (team === 'A' && scores.value[idx].teamA < maxPoints) scores.value[idx].teamA++
    // if (team === 'B' && scores.value[idx].teamB < maxPoints) scores.value[idx].teamB++
    if (team === 'A') scores.value[idx].teamA++
    if (team === 'B') scores.value[idx].teamB++
}

const decrementScore = (idx, team) => {
    if (team === 'A' && scores.value[idx].teamA > 0) scores.value[idx].teamA--
    if (team === 'B' && scores.value[idx].teamB > 0) scores.value[idx].teamB--
}

const addSet = () => {
    scores.value.push({ teamA: 0, teamB: 0 })
}

const removeSet = (idx) => {
    if (scores.value.length > 1) scores.value.splice(idx, 1)
}

/* ===================== FORMAT API ===================== */
const formatResultsForAPI = () => {
    return scores.value.flatMap((score, idx) => [
        {
            set_number: idx + 1,
            team_id: props.data.home_team.id,
            score: score.teamA
        },
        {
            set_number: idx + 1,
            team_id: props.data.away_team.id,
            score: score.teamB
        }
    ])
}

/* ===================== SAVE MATCH ===================== */
const saveMatch = async () => {
    if (isSaving.value) return
    try {
        isSaving.value = true
        const payload = {
            court: courtNumber.value,
            results: formatResultsForAPI()
        }
        const res = await MatchesServices.updateMatches(currentLeg.value.id, payload)
        toast.success('Cập nhật kết quả thành công!')
        emit('updated', res.data)
        isOpen.value = false
    } catch (err) {
        toast.error(err.response?.data?.message || 'Lỗi khi cập nhật')
    } finally {
        isSaving.value = false
    }
}

/* ===================== CONFIRM MATCH ===================== */
const canConfirmMatch = computed(() =>
    scores.value.some(s => s.teamA > 0 || s.teamB > 0)
)

const confirmMatchResult = async () => {
    if (isSaving.value || !canConfirmMatch.value) return
    try {
        isSaving.value = true
        const res = await MatchesServices.confirmResults(currentLeg.value.id)
        toast.success('Xác nhận kết quả thành công!')
        emit('updated', res)
        isOpen.value = false
    } catch (err) {
        toast.error(err.response?.data?.message || 'Lỗi xác nhận')
    } finally {
        isSaving.value = false
    }
}

/* ===================== UI HELPERS ===================== */


const emptySlots = (team) => {
    const members = team === 'home'
        ? props.data.home_team?.members?.length || 0
        : props.data.away_team?.members?.length || 0

    const slots = props.tournament.player_per_team - members
    return slots > 0 ? Array.from({ length: slots }, (_, i) => i + 1) : []
}

/* ===================== CLOSE CONFIRMATION ===================== */
const showConfirmClose = ref(false)
const initialCourtNumber = ref(1)
const initialScores = ref([])

// Cập nhật state ban đầu khi dữ liệu thay đổi
const updateInitialState = () => {
    initialCourtNumber.value = courtNumber.value
    initialScores.value = JSON.parse(JSON.stringify(scores.value))
}

watch(
    [currentLeg, () => props.data],
    () => {
        // Đợi 1 tick để scores được init xong trong watch ở trên
        setTimeout(() => {
            updateInitialState()
        }, 0)
    },
    { immediate: true, deep: true }
)

const hasUnsavedChanges = computed(() => {
    const isCourtChanged = courtNumber.value !== initialCourtNumber.value
    const isScoresChanged = JSON.stringify(scores.value) !== JSON.stringify(initialScores.value)
    return isCourtChanged || isScoresChanged
})

// Override closeModal
const closeModal = () => {
    if (isSaving.value || isAdvancing.value) return

    // Nếu trận đấu đã hoàn thành thì không cần check unsaved changes (vì không lưu được)
    if (currentLeg.value?.status === 'completed') {
        isOpen.value = false
        return
    }

    if (hasUnsavedChanges.value) {
        showConfirmClose.value = true
    } else {
        isOpen.value = false
    }
}

const handleConfirmSave = async () => {
    await saveMatch()
    showConfirmClose.value = false
    isOpen.value = false
}

const handleDiscard = () => {
    showConfirmClose.value = false
    isOpen.value = false
}

const handleCancelClose = () => {
    showConfirmClose.value = false
}
</script>