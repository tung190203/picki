<template src="./CreateMiniMatch.html"></template>

<script>
import {ref, computed, watch} from 'vue'
import {MinusIcon, PlusIcon, XMarkIcon, CheckBadgeIcon} from '@heroicons/vue/24/solid'
import {ClipboardIcon, MapPinIcon} from '@heroicons/vue/24/outline'
import QrcodeVue from 'qrcode.vue'
import {toast} from 'vue3-toastify'
import * as MiniMatchService from '@/service/miniMatch.js';
import UserCard from '@/components/molecules/UserCard.vue'
import InviteUserParticipant from '@/components/molecules/InviteUserParticipant.vue'
import RefereeScoringScreen from '@/components/molecules/referee-scoring/RefereeScoringScreen.vue'

export default {
    name: 'CreateMiniMatch',
    components: {
        MinusIcon,
        PlusIcon,
        XMarkIcon,
        CheckBadgeIcon,
        ClipboardIcon,
        MapPinIcon,
        QrcodeVue,
        UserCard,
        InviteUserParticipant,
        RefereeScoringScreen
    },
    props: {
        modelValue: Boolean,
        miniTournament: { type: Object, required: true },
        isCreator: Boolean,
        editMatch: { type: Object, default: null },
        matchCount: { type: Number, default: 0 }
    },

    setup(props, {emit}) {
        const isSaving = ref(false)
        const miniMatchName = ref('')
        const text_team1 = ref('Team 1')
        const text_team2 = ref('Team 2')
        const team1Users = ref([])
        const team2Users = ref([])
        const showUserModal = ref(false)
        const selectingTeam = ref(null)
        const MATCH_TYPE_SINGLE = 2

        const scores = ref([{ team1: 0, team2: 0 }])
        const showRefereeScreen = ref(false)
        const currentMatchId = ref(null)

        const isOpen = computed({
            get: () => props.modelValue,
            set: val => emit('update:modelValue', val)
        })

        const closeModal = () => {
            if (!isSaving.value) isOpen.value = false
        }

        const isSingleMode = computed(() => {
            // Ưu tiên `format` từ API mini-tournaments/{id}
            const format = props.miniTournament?.format
            if (typeof format === 'string') return format === 'single'
            // fallback legacy nếu FE vẫn đang nhận match_type
            return props.miniTournament?.match_type === MATCH_TYPE_SINGLE
        })

        const playersPerTeam = computed(() => (isSingleMode.value ? 1 : 2))

        const confirmedUsers = computed(() =>
            props.miniTournament?.participants
                ?.filter(p => p.is_confirmed)
                .map(p => p.user) || []
        )

        const selectableUsers = computed(() => {
            const selectedIds = new Set([
                ...team1Users.value.map(u => u.id),
                ...team2Users.value.map(u => u.id),
            ])

            return confirmedUsers.value.filter(u => !selectedIds.has(u.id))
        })

        const emptySlots = (team) => {
            const totalSlots = playersPerTeam.value
            const members = team === 'team1'
                ? team1Users.value.length
                : team2Users.value.length
            const slots = totalSlots - members
            return slots > 0
                ? Array.from({ length: slots }, (_, i) => i + 1)
                : []
        }

        const openInviteModalDefault = (team) => {
            selectingTeam.value = team
            showUserModal.value = true
        }

        const selectUserToTeam = (user) => {
            if (selectingTeam.value === 'team1') {
                if (team1Users.value.length >= playersPerTeam.value) {
                    toast.error(`Mỗi đội chỉ được chọn ${playersPerTeam.value} người`)
                    return
                }
                team1Users.value.push(user)
            } else if (selectingTeam.value === 'team2') {
                if (team2Users.value.length >= playersPerTeam.value) {
                    toast.error(`Mỗi đội chỉ được chọn ${playersPerTeam.value} người`)
                    return
                }
                team2Users.value.push(user)
            }
            showUserModal.value = false
        }

        const defaultMatchName = computed(() => {
            const count = props.matchCount + 1
            const name = props.miniTournament?.name || ''
            return `Trận ${count} kèo ${name}`
        })

        const resetForm = () => {
            miniMatchName.value = defaultMatchName.value
            team1Users.value = []
            team2Users.value = []
            text_team1.value = 'Team 1'
            text_team2.value = 'Team 2'
            scores.value = [{ team1: 0, team2: 0 }]
            currentMatchId.value = null
        }

        /** Giới hạn nút +/- trên UI (không phải validate luật — BE mới quyết định hợp lệ) */
        const SCORE_UI_MAX = 999

        const incrementScore = (idx, team) => {
            if (team === '1' && scores.value[idx].team1 < SCORE_UI_MAX) scores.value[idx].team1++
            if (team === '2' && scores.value[idx].team2 < SCORE_UI_MAX) scores.value[idx].team2++
        }

        const decrementScore = (idx, team) => {
            if (team === '1' && scores.value[idx].team1 > 0) scores.value[idx].team1--
            if (team === '2' && scores.value[idx].team2 > 0) scores.value[idx].team2--
        }

        const addSet = () => {
            scores.value.push({ team1: 0, team2: 0 })
        }

        const removeSet = (idx) => {
            if (scores.value.length > 1) scores.value.splice(idx, 1)
        }

        const hasScores = computed(() => {
            return scores.value.some(s => s.team1 > 0 || s.team2 > 0)
        })

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

        const buildPayload = () => {
            const payload = {
                name: miniMatchName.value,
                team1_name: text_team1.value,
                team2_name: text_team2.value,
                team1: team1Users.value.map(u => u.id),
                team2: team2Users.value.map(u => u.id),
            }

            if (currentMatchId.value) {
                payload.match_id = currentMatchId.value
            }

            const sets = formatSetsForAPI()
            if (sets.length > 0) {
                payload.sets = sets
            }

            return payload
        }

        const saveMiniMatch = async () => {
            if (isSaving.value) return

            if (team1Users.value.length < playersPerTeam.value || team2Users.value.length < playersPerTeam.value) {
                toast.error(`Mỗi đội phải có đủ ${playersPerTeam.value} người chơi`)
                return
            }

            isSaving.value = true

            try {
                const payload = buildPayload()
                const res = await MiniMatchService.saveMiniMatch(props.miniTournament.id, payload)
                toast.success(currentMatchId.value ? 'Cập nhật trận đấu thành công!' : 'Tạo trận đấu thành công!')

                if (!currentMatchId.value && res?.id) {
                    currentMatchId.value = res.id
                }

                emit('created', res)
            } catch (error) {
                toast.error(error.response?.data?.message || 'Lưu trận đấu thất bại')
            } finally {
                isSaving.value = false
            }
        }

        const confirmMiniMatch = async () => {
            if (isSaving.value) return

            isSaving.value = true

            try {
                const payload = buildPayload()
                const res = await MiniMatchService.saveMiniMatch(props.miniTournament.id, payload)

                const matchId = currentMatchId.value || res?.id
                if (matchId) {
                    await MiniMatchService.confirmResults(matchId)
                    toast.success('Xác nhận kết quả thành công!')
                    isOpen.value = false
                    emit('created', res)
                    resetForm()
                }
            } catch (error) {
                toast.error(error.response?.data?.message || 'Xác nhận thất bại')
            } finally {
                isSaving.value = false
            }
        }

        const openRefereeScreen = () => {
            if (team1Users.value.length < playersPerTeam.value || team2Users.value.length < playersPerTeam.value) {
                toast.error(`Vui lòng chọn đủ ${playersPerTeam.value} người cho mỗi đội trước khi nhập điểm trọng tài`)
                return
            }
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
            name: text_team1.value,
            members: team1Users.value.map(u => ({ user: u }))
        }))

        const team2ForReferee = computed(() => ({
            name: text_team2.value,
            members: team2Users.value.map(u => ({ user: u }))
        }))

        watch(
            () => props.modelValue,
            (val) => {
                if (val && !props.editMatch) {
                    miniMatchName.value = defaultMatchName.value
                }
            }
        )

        watch(
            () => isSingleMode.value,
            (val) => {
                if (!val) return
                // Trận đơn: cắt đội về 1 người/đội để khớp format
                if (team1Users.value.length > 1) team1Users.value = team1Users.value.slice(0, 1)
                if (team2Users.value.length > 1) team2Users.value = team2Users.value.slice(0, 1)
            }
        )

        watch(
            () => props.editMatch,
            (match) => {
                if (!match) return
                currentMatchId.value = match.id
                miniMatchName.value = match.name || ''
                text_team1.value = match.team1?.name || 'Team 1'
                text_team2.value = match.team2?.name || 'Team 2'
                team1Users.value = match.team1?.members?.map(m => m.user) || []
                team2Users.value = match.team2?.members?.map(m => m.user) || []

                if (match.results_by_sets) {
                    const r = match.results_by_sets
                    const sets = []
                    Object.keys(r).forEach((key) => {
                        const arr = r[key]
                        if (!Array.isArray(arr)) return
                        let t1Score = 0, t2Score = 0
                        arr.forEach(item => {
                            if (item.team?.id === match.team1?.id) t1Score = Number(item.score)
                            if (item.team?.id === match.team2?.id) t2Score = Number(item.score)
                        })
                        sets.push({ team1: t1Score, team2: t2Score })
                    })
                    if (sets.length > 0) scores.value = sets
                }
            },
            { deep: true }
        )

        return {
            isOpen,
            closeModal,
            props,
            emptySlots,
            isSaving,
            isSingleMode,
            openInviteModalDefault,
            confirmedUsers,
            selectUserToTeam,
            text_team1,
            text_team2,
            team1Users,
            team2Users,
            InviteUserParticipant,
            selectableUsers,
            showUserModal,
            saveMiniMatch,
            confirmMiniMatch,
            miniMatchName,
            scores,
            incrementScore,
            decrementScore,
            addSet,
            removeSet,
            hasScores,
            showRefereeScreen,
            openRefereeScreen,
            onRefereeDone,
            onRefereeBack,
            team1ForReferee,
            team2ForReferee,
            currentMatchId
        }
    }
}
</script>
