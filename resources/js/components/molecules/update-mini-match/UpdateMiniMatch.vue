<template src="./UpdateMiniMatch.html"></template>

<script>
import { ref, computed, watch } from 'vue'
import { MinusIcon, PlusIcon, XMarkIcon, CheckBadgeIcon } from '@heroicons/vue/24/solid'
import { ClipboardIcon, CalendarDaysIcon, MapPinIcon } from '@heroicons/vue/24/outline'
import { formatEventDate } from '@/composables/formatDatetime.js'
import QrcodeVue from 'qrcode.vue'
import { toast } from 'vue3-toastify'
import * as MiniMatchService from '@/service/miniMatch.js';
import UserCard from '@/components/molecules/UserCard.vue'
import RefereeScoringScreen from '@/components/molecules/referee-scoring/RefereeScoringScreen.vue'

export default {
    name: 'UpdateMiniMatch',
    components: {
        MinusIcon,
        PlusIcon,
        XMarkIcon,
        CheckBadgeIcon,
        ClipboardIcon,
        CalendarDaysIcon,
        MapPinIcon,
        QrcodeVue,
        UserCard,
        RefereeScoringScreen
    },
    props: {
        modelValue: {
            type: Boolean,
            default: false
        },
        data: {
            type: Object,
            default: () => ({})
        },
        miniTournament: {
            type: Object,
            required: true,
            default: () => ({ player_per_team: 0 })
        },
        isCreator: {
            type: Boolean,
            default: false
        },
    },

    emit: ['update:modelValue', 'updated'],

    setup(props, { emit }) {
        const scores = ref([])
        const isSaving = ref(false)
        const showRefereeScreen = ref(false)
        const team1Users = ref([])
        const team2Users = ref([])

        const basePoints = computed(() => props.miniTournament?.base_points || 11)
        const pointsDifference = computed(() => props.miniTournament?.points_difference || 2)
        const maxPoints = computed(() => props.miniTournament?.max_points || 15)

        const isValidSetScore = (s1, s2) => {
            const a = Number(s1 || 0)
            const b = Number(s2 || 0)

            if (a < 0 || b < 0) return false
            if (a > maxPoints.value || b > maxPoints.value) return false

            if (a === 0 && b === 0) return true

            if (a >= basePoints.value && (a - b) >= pointsDifference.value) return true
            if (b >= basePoints.value && (b - a) >= pointsDifference.value) return true

            if (a === maxPoints.value || b === maxPoints.value) return true

            return false
        }

        const validateScoresByRules = () => {
            const nonEmpty = scores.value.filter(s => Number(s.team1 || 0) > 0 || Number(s.team2 || 0) > 0)
            if (nonEmpty.length === 0) return { ok: false, message: 'Vui lòng nhập kết quả trước khi xác nhận' }

            for (let i = 0; i < scores.value.length; i++) {
                const s = scores.value[i]
                const a = Number(s.team1 || 0)
                const b = Number(s.team2 || 0)
                if (a === 0 && b === 0) continue
                if (!isValidSetScore(a, b)) {
                    return {
                        ok: false,
                        message: `Set ${i + 1} chưa đúng luật (${basePoints.value} điểm, cách ${pointsDifference.value}, tối đa ${maxPoints.value}).`
                    }
                }
            }

            return { ok: true }
        }

        const incrementScore = (idx, team) => {
            if (team === '1' && scores.value[idx].team1 < maxPoints.value) scores.value[idx].team1++
            if (team === '2' && scores.value[idx].team2 < maxPoints.value) scores.value[idx].team2++
        }

        const decrementScore = (idx, team) => {
            if (team === '1' && scores.value[idx].team1 > 0) scores.value[idx].team1--
            if (team === '2' && scores.value[idx].team2 > 0) scores.value[idx].team2--
        }

        const isOpen = computed({
            get: () => props.modelValue,
            set: val => emit('update:modelValue', val)
        })

        const currentMiniMatch = computed(() => {
            return props.data
        })

        const qrCodeUrl = computed(() => {
            if (!currentMiniMatch.value?.id) return ''
            return `${globalThis.location.origin}/mini-match/${currentMiniMatch.value.id}/verify`
        })

        const addSet = () => {
            scores.value.push({ team1: 0, team2: 0 })
        }

        const removeSet = (idx) => {
            if (scores.value.length > 1) scores.value.splice(idx, 1)
        }

        const formatSetsForAPI = () => {
            return scores.value
                .filter(s => s.team1 > 0 || s.team2 > 0)
                .map((score, idx) => ({
                    set_number: idx + 1,
                    results: [
                        { team: 'team1', score: Number(score.team1) },
                        { team: 'team2', score: Number(score.team2) }
                    ]
                }))
        }

        const initializeScores = () => {
            if (currentMiniMatch.value?.results_by_sets) {
                const r = currentMiniMatch.value?.results_by_sets
                if (!r) return []

                const team1Id = props.data.team1?.id
                const team2Id = props.data.team2?.id
                const sets = []

                Object.keys(r).forEach((key) => {
                    const arr = r[key]
                    if (!Array.isArray(arr)) return

                    let team1Score = 0
                    let team2Score = 0

                    arr.forEach(item => {
                        if (item.team?.id === team1Id) team1Score = Number(item.score)
                        if (item.team?.id === team2Id) team2Score = Number(item.score)
                    })

                    sets.push({ team1: team1Score, team2: team2Score })
                })

                return sets;
            }
            return [{ team1: 0, team2: 0 }]
        }

        const resetFormState = () => {
            scores.value = initializeScores()
            team1Users.value = (props.data?.team1?.members || []).map(m => m?.user ?? m).filter(Boolean)
            team2Users.value = (props.data?.team2?.members || []).map(m => m?.user ?? m).filter(Boolean)
            showRefereeScreen.value = false
        }

        const saveMiniMatch = async () => {
            if (isSaving.value) return
            try {
                isSaving.value = true

                const sets = formatSetsForAPI()
                const payload = {
                    match_id: currentMiniMatch.value.id,
                    team1: team1Users.value.map(u => u.id),
                    team2: team2Users.value.map(u => u.id),
                    team1_name: props.data.team1?.name,
                    team2_name: props.data.team2?.name,
                }

                if (sets.length > 0) {
                    payload.sets = sets
                }

                const tournamentId = props.data.mini_tournament_id || props.miniTournament?.id
                const res = await MiniMatchService.saveMiniMatch(tournamentId, payload)
                toast.success('Cập nhật kết quả thành công!')
                emit('updated', res)
                isOpen.value = false
                resetFormState()
            } catch (err) {
                toast.error(err.response?.data?.message || 'Lỗi khi cập nhật')
            } finally {
                isSaving.value = false
            }
        }

        const canConfirmMiniMatch = computed(() => validateScoresByRules().ok)

        const confirmMiniMatchResult = async () => {
            if (isSaving.value || !canConfirmMiniMatch.value) return
            try {
                isSaving.value = true

                const sets = formatSetsForAPI()
                if (sets.length > 0) {
                    const payload = {
                        match_id: currentMiniMatch.value.id,
                        team1: team1Users.value.map(u => u.id),
                        team2: team2Users.value.map(u => u.id),
                        team1_name: props.data.team1?.name,
                        team2_name: props.data.team2?.name,
                        sets: sets,
                    }
                    const tournamentId = props.data.mini_tournament_id || props.miniTournament?.id
                    await MiniMatchService.saveMiniMatch(tournamentId, payload)
                }

                const res = await MiniMatchService.confirmResults(currentMiniMatch.value.id)
                toast.success('Xác nhận kết quả thành công!')
                emit('updated', res)
                isOpen.value = false
                resetFormState()
            } catch (err) {
                toast.error(err.response?.data?.message || 'Lỗi xác nhận')
            } finally {
                isSaving.value = false
            }
        }

        const closeModal = () => {
            if (isSaving.value) return
            isOpen.value = false
            resetFormState()
        }

        const emptySlots = (team) => {
            const members = team === 'team1' ? team1Users.value.length : team2Users.value.length

            const slots = props.miniTournament.player_per_team - members
            return slots > 0 ? Array.from({ length: slots }, (_, i) => i + 1) : []
        }

        const openRefereeScreen = () => {
            showRefereeScreen.value = true
        }

        const onRefereeDone = (refereeScores) => {
            scores.value = refereeScores.map(s => ({
                team1: s.team1,
                team2: s.team2
            }))
            showRefereeScreen.value = false
        }

        const onRefereeBack = () => {
            showRefereeScreen.value = false
        }

        const team1ForReferee = computed(() => ({
            name: props.data.team1?.name || 'Team 1',
            members: team1Users.value.map(u => ({ user: u }))
        }))
        const team2ForReferee = computed(() => ({
            name: props.data.team2?.name || 'Team 2',
            members: team2Users.value.map(u => ({ user: u }))
        }))

        const removeMemberFromTeam = (team, userId) => {
            if (!props.isCreator) return
            if (team === 'team1') {
                team1Users.value = team1Users.value.filter(u => u.id !== userId)
                return
            }
            team2Users.value = team2Users.value.filter(u => u.id !== userId)
        }

        watch(
            () => props.data,
            () => {
                resetFormState()
            },
            { deep: true }
        )

        watch(
            () => isOpen.value,
            (val) => {
                if (val) resetFormState()
            }
        )

        return {
            MinusIcon,
            PlusIcon,
            XMarkIcon,
            CheckBadgeIcon,
            ClipboardIcon,
            CalendarDaysIcon,
            MapPinIcon,
            QrcodeVue,
            UserCard,
            formatEventDate,
            isOpen,
            isSaving,
            currentMiniMatch,
            scores,
            initializeScores,
            qrCodeUrl,
            incrementScore,
            decrementScore,
            addSet,
            removeSet,
            saveMiniMatch,
            canConfirmMiniMatch,
            confirmMiniMatchResult,
            closeModal,
            emptySlots,
            showRefereeScreen,
            openRefereeScreen,
            onRefereeDone,
            onRefereeBack,
            team1ForReferee,
            team2ForReferee,
            team1Users,
            team2Users,
            removeMemberFromTeam,
            maxPoints
        }
    }
}
</script>
