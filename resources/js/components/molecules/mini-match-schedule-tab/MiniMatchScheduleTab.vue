<template src="./MiniMatchScheduleTab.html"></template>

<script>
import { ref, watch, computed } from 'vue'
import MiniMatchCard from '@/components/molecules/MiniMatchCard.vue';
import { TABS, SUB_TABS } from '@/data/mini/index.js';
import CreateMiniMatch from '@/components/molecules/create-mini-match/CreateMiniMatch.vue';
import UpdateMiniMatch from '@/components/molecules/update-mini-match/UpdateMiniMatch.vue';
import {toast} from "vue3-toastify";
import * as MiniMatchService from '@/service/miniMatch.js';
import * as SessionService from '@/service/miniTournamentSession.js';
import * as MiniTournamentService from '@/service/miniTournament.js';
import { updateMiniTournamentByClub } from '@/service/miniTournament.js';
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/vue/24/solid/index.js";
import DeleteConfirmationModal from '@/components/molecules/DeleteConfirmationModal.vue'
import { SESSION_STATUS, MATCH_FORMAT } from '@/constants/index.js';
import MiniTournamentLeaderboard from '@/components/molecules/MiniTournamentLeaderboard.vue';
import SessionReadyPreview from '@/components/molecules/session-ready-preview/SessionReadyPreview.vue';
import SessionScheduleRound from '@/components/molecules/session-schedule-round/SessionScheduleRound.vue';

const SESSION_SUBTABS = [
    { id: 'format', label: 'Thể thức' },
    { id: 'group', label: 'Phân nhóm' },
    { id: 'schedule', label: 'Lịch thi đấu' },
    { id: 'leaderboard', label: 'BXH' },
];


export default {
    name: 'MiniTournamentDetail',
    components: {
        ChevronLeftIcon,
        ChevronRightIcon,
        MiniMatchCard,
        CreateMiniMatch,
        UpdateMiniMatch,
        DeleteConfirmationModal,
        MiniTournamentLeaderboard,
        SessionReadyPreview,
        SessionScheduleRound,
    },
    props: {
        isCreator: {
            type: Boolean,
            default: false
        },
        data: {
            type: Object,
            required: true
        },
        sportId: {
            type: Number,
            required: false
        },
        clubId: {
            type: Number,
            required: false
        }
    },

    emits: ['select-format', 'refresh-data'],
    setup(props, { emit }) {
        const tabs = TABS
        const subtabs = SUB_TABS
        const activeTab = ref('matches')
        const subActiveTab = ref('match')
        const showCreateMiniMatchModal = ref(false)
        const showUpdateMiniMatchModal = ref(false)
        const miniMatches = ref([])
        const scheduledMyMiniMatches = ref([])
        const countMiniMatches = ref(0)
        const countMyMiniMatches = ref(0)
        const selectedMiniMatches = ref([])
        const showDeleteModal = ref(false)
        const detailData = ref({});

        // Session state (Round Robin)
        const sessionSchedule = ref([])
        const sessionLeaderboardData = ref({})
        const currentRound = ref(1)
        const isLoadingSchedule = ref(false)
        const playerGroups = ref({}) // participantId -> group
        const isLoadingGroup = ref(false)
        const sessionSubTab = ref('format')
        const showReadyPreview = ref(false)
        const readyPreviewData = ref({})
        const showFormatConfirm = ref(false)
        const selectedFormat = ref('')
        const isConfirmingFormat = ref(false)

        const confirmRemoval = () => {
            showDeleteModal.value = true;
        };

        const pagination = ref({
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0
        })

        const visiblePages = computed(() => {
            const total = pagination.value.last_page
            const current = pagination.value.current_page
            const delta = 2

            if (total <= 7) {
                return Array.from({ length: total }, (_, i) => i + 1)
            }

            const pages = []
            const left = Math.max(2, current - delta)
            const right = Math.min(total - 1, current + delta)

            pages.push(1)
            if (left > 2) pages.push('...')

            for (let i = left; i <= right; i++) {
                pages.push(i)
            }

            if (right < total - 1) pages.push('...')
            pages.push(total)

            return pages
        })

        const getMiniMatches = async (miniTournamentId, page = 1) => {
            try {
                const res = await MiniMatchService.getListMiniMatches(
                    miniTournamentId,
                    { page }
                )

                miniMatches.value = res.data.matches
                pagination.value = res.meta
                countMiniMatches.value = res.meta.total
                selectedMiniMatches.value = []
            } catch (error) {
                toast.error(error.response?.data?.message || 'Lấy trận thi đấu thất bại');
            }
        }

        const getUserRatingBySport = (member, sportId) => {
            if (!sportId) return 0
            const user = member?.user ?? member
            if (!user?.sports || !Array.isArray(user.sports)) return 0
            const sport = user.sports.find(s => Number(s.sport_id) === Number(sportId))
            if (!sport?.scores) return 0

            let scoreValue = 0
            if (Array.isArray(sport.scores)) {
                const scoreRecord = sport.scores.find(sc => sc?.score_type === 'vndupr_score')
                scoreValue = scoreRecord?.score_value ?? 0
            } else if (typeof sport.scores === 'object') {
                scoreValue = sport.scores.vndupr_score ?? sport.scores.personal_score ?? 0
            }

            return Number(scoreValue || 0).toFixed(1)
        }

        const getMyMiniMatches = async (miniTournamentId, page = 1) => {
            try {
                const res = await MiniMatchService.getListMiniMatches(
                    miniTournamentId,
                    { page, filter: 'my_matches' }
                )
                scheduledMyMiniMatches.value = res.data.matches
                pagination.value = res.meta
                countMyMiniMatches.value = res.meta.total
                selectedMiniMatches.value = []
            } catch (error) {
                toast.error(error.response?.data?.message || 'Lấy trận thi đấu thất bại');
            }
        }

        const changePage = (page) => {
            if (page < 1 || page > pagination.value.last_page) return

            pagination.value.current_page = page

            if (subActiveTab.value === 'match') {
                getMiniMatches(props.data.id, page)
            }

            if (subActiveTab.value === 'your-match') {
                getMyMiniMatches(props.data.id, page)
            }
        }

        const totalDuration = computed(() => {
            if (!Array.isArray(miniMatches.value)) return 0

            return miniMatches.value.reduce((sum, match) => {
                if (!match.started_at || !match.finished_at) return sum

                const start = new Date(match.started_at)
                const end = new Date(match.finished_at)

                const diffHours = (end - start) / (1000 * 60 * 60)

                return sum + diffHours
            }, 0)
        })

        const formatDate = (dateString) => {
            if (!dateString) return 'Chưa xác định'
            const date = new Date(dateString)
            const year = date.getFullYear() % 100
            const day = date.getDate()
            const month = date.getMonth() + 1
            const hours = date.getHours().toString().padStart(2, '0')
            const minutes = date.getMinutes().toString().padStart(2, '0')
            return `${hours}:${minutes} - ${day}/${month}/${year}`
        }

        const buildSets = (match) => {
            const r = match?.results_by_sets
            if (!r) return []

            const team1Id = match.team1?.id
            const team2Id = match.team2?.id

            const sets = []

            Object.keys(r).forEach((key) => {
                const arr = r[key]

                if (!Array.isArray(arr)) return

                let team1Score = '0'
                let team2Score = '0'

                arr.forEach(item => {
                    if (item.team?.id === team1Id) {
                        team1Score = String(item.score)
                    }
                    if (item.team?.id === team2Id) {
                        team2Score = String(item.score)
                    }
                })

                sets.push({
                    team1: team1Score,
                    team2: team2Score
                })
            })

            return sets
        }

        const toggleSelectMiniMatch = (miniMatchId, value) => {
            if (value) {
                if (selectedMiniMatches.value.length >= countMiniMatches) return
                if (!selectedMiniMatches.value.includes(miniMatchId)) {
                    selectedMiniMatches.value.push(miniMatchId)
                }
            } else {
                selectedMiniMatches.value = selectedMiniMatches.value.filter(id => id !== miniMatchId)
            }
        }

        const totalTimeMiniMatches = (countMatches) => {
            return Number.isInteger((countMatches * 15) / 60)
                ? (countMatches * 15) / 60
                : ((countMatches * 15) / 60).toFixed(2)
        }

        const cancelSelectedMiniMatches = async () => {
            if (selectedMiniMatches.value.length === 0) return

            try {
                const data = {
                    ids: selectedMiniMatches.value,
                }

                await MiniMatchService.deleteMiniMatches(data)
                selectedMiniMatches.value = []

                if (!props.data?.id) return
                try {
                    await getMiniMatches(props.data.id)
                    await getMyMiniMatches(props.data.id)
                } catch (e) {
                    console.error(e)
                }
                toast.success('Đã huỷ kèo đấu thành công');
            } catch (error) {
                toast.error(error.response?.data?.message || 'Huỷ kèo đấu thất bại');
            }
        }

        const showMiniMatchDetail = async (id) => {
            try {
                const res = await MiniMatchService.detailMiniMatches(id);
                if(res) {
                    detailData.value = res
                    showUpdateMiniMatchModal.value = true;
                }
            } catch (error) {
                toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi thực hiện thao tác này');
            }
        }

        const onMiniMatchCreated = (newMatch) => {
            showUpdateMiniMatchModal.value = false
            showCreateMiniMatchModal.value = false

            if (!props.data?.id) return
            try {
                getMiniMatches(props.data.id)
                getMyMiniMatches(props.data.id)
            } catch (e) {
                console.error(e)
            }
        }

        watch(subActiveTab, () => {
            if (props.data?.match_format && props.data.match_format !== MATCH_FORMAT.STANDARD) return
            pagination.value.current_page = 1
            selectedMiniMatches.value = []

            if (!props.data?.id) return

            if (subActiveTab.value === 'match') {
                getMiniMatches(props.data.id, 1)
            } else if (subActiveTab.value === 'your-match') {
                getMyMiniMatches(props.data.id, 1)
            }
        })

        watch(
            () => props.data?.id,
            (miniTournamentId) => {
                if (!miniTournamentId) return

                pagination.value.current_page = 1

                if (subActiveTab.value === 'your-match') {
                    getMyMiniMatches(miniTournamentId, 1)
                } else {
                    getMiniMatches(miniTournamentId, 1)
                }
            },
            { immediate: true }
        )

        // Watch data changes to detect session-related fields
        watch(() => props.data, async (newData) => {
            if (newData) {
                if (newData.match_format && newData.match_format !== MATCH_FORMAT.STANDARD) {
                    await loadSessionSchedule(newData.id)
                    await loadSessionLeaderboard(newData.id)
                    // Init session sub-tab based on status (null session_status = treat as PENDING_GROUP)
                    const effectiveStatus = newData.session_status || SESSION_STATUS.PENDING_GROUP
                    if (effectiveStatus === SESSION_STATUS.PENDING_GROUP) {
                        sessionSubTab.value = 'group'
                    } else if (effectiveStatus === SESSION_STATUS.READY) {
                        sessionSubTab.value = 'ready'
                    } else if (newData.session_status === SESSION_STATUS.ONGOING) {
                        sessionSubTab.value = 'schedule'
                    } else if (newData.session_status === SESSION_STATUS.FINISHED) {
                        sessionSubTab.value = 'leaderboard'
                    }
                }
                if (newData.match_format === MATCH_FORMAT.MIXED_GENDER || newData.match_format === MATCH_FORMAT.RANK_PAIRING) {
                    loadPlayerGroups(newData)
                }
            }
        }, { immediate: true })

        const loadSessionSchedule = async (id) => {
            try {
                isLoadingSchedule.value = true
                const res = await SessionService.getSchedule(id)
                if (res.data?.rounds) {
                    sessionSchedule.value = res.data.rounds
                    const activeRound = res.data.rounds.find(r => r.status === 'active')
                    const upcomingRound = res.data.rounds.find(r => r.status === 'upcoming')
                    const firstRound = res.data.rounds[0]?.round_number ?? 1
                    currentRound.value = activeRound
                        ? activeRound.round_number
                        : (upcomingRound ? upcomingRound.round_number : firstRound)
                }
            } catch (_e) {
                // No schedule yet or error — silently ignore
            } finally {
                isLoadingSchedule.value = false
            }
        }

        const loadSessionLeaderboard = async (id) => {
            try {
                const res = await SessionService.getLeaderboard(id)
                if (res.data) {
                    sessionLeaderboardData.value = res.data
                }
            } catch (_e) {
                // Silently ignore leaderboard load errors
            }
        }

        const loadPlayerGroups = (data) => {
            if (data.participants) {
                const groups = {}
                data.participants.forEach(p => {
                    if (p.player_group) {
                        groups[p.id] = p.player_group
                    }
                })
                playerGroups.value = groups
            }
        }

        const confirmPlayerGroups = async () => {
            try {
                isLoadingGroup.value = true
                await SessionService.updatePlayerGroup(props.data.id, playerGroups.value)
                toast.success('Đã cập nhật phân nhóm thành công')

                // Refresh data to get updated session_status
                emit('refresh-data')
            } catch (e) {
                toast.error(e.response?.data?.message || 'Cập nhật phân nhóm thất bại')
            } finally {
                isLoadingGroup.value = false
            }
        }

        const markReady = async () => {
            try {
                await SessionService.markReady(props.data.id)
                toast.success('Đã chuyển sang trạng thái sẵn sàng')
                emit('refresh-data')
            } catch (e) {
                toast.error(e.response?.data?.message || 'Không thể chuyển trạng thái')
            }
        }

        const openReadyPreview = () => {
            const groups = sessionParticipantGroups.value
            const total = confirmedParticipantsCount.value
            const format = props.data?.match_format

            let preview = {}
            if (format === MATCH_FORMAT.PARTNER_ROTATION) {
                preview = SessionService.getMatchPreview(format, 2, {}, total)
            } else {
                preview = SessionService.getMatchPreview(format, 2, groups)
            }

            readyPreviewData.value = preview
            showReadyPreview.value = true
        }

        const onStartFromPreview = async (courtCount) => {
            showReadyPreview.value = false
            try {
                const res = await SessionService.startSession(props.data.id, courtCount)
                toast.success(res.message || 'Đã bắt đầu session')
                await loadSessionSchedule(props.data.id)
                await loadSessionLeaderboard(props.data.id)
                sessionSubTab.value = 'schedule'
                emit('refresh-data')
            } catch (e) {
                toast.error(e.response?.data?.message || 'Không thể bắt đầu session')
            }
        }

        const startSession = async (courtCount = 2) => {
            try {
                const res = await SessionService.startSession(props.data.id, courtCount)
                toast.success(res.message || 'Đã bắt đầu session')
                sessionSchedule.value = []
                await loadSessionSchedule(props.data.id)
                await loadSessionLeaderboard(props.data.id)
                sessionSubTab.value = 'schedule'
                emit('refresh-data')
            } catch (e) {
                toast.error(e.response?.data?.message || 'Không thể bắt đầu session')
            }
        }

        const finishSession = async () => {
            try {
                const res = await SessionService.finishSession(props.data.id)
                toast.success(res.message || 'Đã kết thúc session')
                await loadSessionLeaderboard(props.data.id)
                sessionSubTab.value = 'leaderboard'
            } catch (e) {
                toast.error(e.response?.data?.message || 'Không thể kết thúc session')
            }
        }

        const onSelectFormat = async (format) => {
            emit('select-format', format)
        }

        const openFormatConfirm = (format) => {
            selectedFormat.value = format
            showFormatConfirm.value = true
        }

        const formatConfirmLabel = computed(() => {
            const labels = {
                standard: 'Tiêu chuẩn',
                partner_rotation: 'Xoay vòng partner',
                mixed_gender: 'Mix nam nữ',
                rank_pairing: 'Ghép hạng A/B',
            }
            return labels[selectedFormat.value] || selectedFormat.value
        })

        const confirmFormatSelection = async () => {
            if (!selectedFormat.value) {
                toast.error('Vui lòng chọn thể thức thi đấu.')
                return
            }
            isConfirmingFormat.value = true
            try {
                await updateMiniTournamentByClub(props.clubId, props.data.id, { match_format: selectedFormat.value })
                emit('refresh-data')
                showFormatConfirm.value = false
                toast.success('Đã chốt thể thức thi đấu!')
            } catch (error) {
                console.error('[confirmFormatSelection] error:', error)
                toast.error(error.response?.data?.message || 'Không thể chốt thể thức.')
            } finally {
                isConfirmingFormat.value = false
            }
        }

        const isSessionFormat = computed(() => {
            return props.data?.match_format && props.data.match_format !== MATCH_FORMAT.STANDARD
        })

        const sessionStatus = computed(() => props.data?.session_status)

        const effectiveSessionStatus = computed(() => {
            if (sessionStatus.value) return sessionStatus.value
            // Nếu chưa set session_status nhưng đã có match_format → coi như PENDING_GROUP
            if (props.data?.match_format) return SESSION_STATUS.PENDING_GROUP
            return null
        })

        const sessionStatusLabel = computed(() => {
            const s = sessionStatus.value
            if (!s) return null
            const labels = {
                pending_group: 'Chờ phân nhóm',
                ready: 'Sẵn sàng bắt đầu',
                ongoing: 'Đang đấu',
                finished: 'Đã kết thúc',
            }
            return labels[s] || s
        })

        const confirmedParticipantsCount = computed(() => {
            const participants = props.data?.participants || []
            return participants.filter(p => p.is_confirmed && !p.is_absent).length
        })

        const sessionParticipantGroups = computed(() => {
            const participants = props.data?.participants || []
            const male = participants
                .filter(p => p.is_confirmed && !p.is_absent && p.player_group === 'male')
                .map(p => p.id)
            const female = participants
                .filter(p => p.is_confirmed && !p.is_absent && p.player_group === 'female')
                .map(p => p.id)
            const a = participants
                .filter(p => p.is_confirmed && !p.is_absent && p.player_group === 'a')
                .map(p => p.id)
            const b = participants
                .filter(p => p.is_confirmed && !p.is_absent && p.player_group === 'b')
                .map(p => p.id)
            return { male, female, a, b }
        })

        const confirmedParticipants = computed(() => {
            return (props.data?.participants || []).filter(p => p.is_confirmed && !p.is_absent)
        })

        const groupCounts = computed(() => {
            const male = confirmedParticipants.value.filter(p => playerGroups.value[p.id] === 'male').length
            const female = confirmedParticipants.value.filter(p => playerGroups.value[p.id] === 'female').length
            const a = confirmedParticipants.value.filter(p => playerGroups.value[p.id] === 'a').length
            const b = confirmedParticipants.value.filter(p => playerGroups.value[p.id] === 'b').length
            const total = male + female + a + b
            return { male, female, a, b, totalSelected: total }
        })

        const groupValidationError = computed(() => {
            if (props.data?.match_format === MATCH_FORMAT.MIXED_GENDER) {
                if (groupCounts.value.male > 0 && groupCounts.value.female > 0 && groupCounts.value.male < 3) {
                    return 'Cần ít nhất 3 nam đã phân nhóm để bắt đầu.'
                }
                if (groupCounts.value.female > 0 && groupCounts.value.male > 0 && groupCounts.value.female < 3) {
                    return 'Cần ít nhất 3 nữ đã phân nhóm để bắt đầu.'
                }
            }
            if (props.data?.match_format === MATCH_FORMAT.RANK_PAIRING) {
                if (groupCounts.value.a > 0 && groupCounts.value.b > 0 && groupCounts.value.a < 3) {
                    return 'Cần ít nhất 3 hạng A đã phân nhóm để bắt đầu.'
                }
                if (groupCounts.value.b > 0 && groupCounts.value.a > 0 && groupCounts.value.b < 3) {
                    return 'Cần ít nhất 3 hạng B đã phân nhóm để bắt đầu.'
                }
            }
            return null
        })

        const canSaveGroups = computed(() => {
            if (props.data?.match_format === MATCH_FORMAT.MIXED_GENDER) {
                return groupCounts.value.male >= 3 && groupCounts.value.female >= 3
            }
            if (props.data?.match_format === MATCH_FORMAT.RANK_PAIRING) {
                return groupCounts.value.a >= 3 && groupCounts.value.b >= 3
            }
            return false
        })

        const assignGroup = (participantId, group) => {
            if (playerGroups.value[participantId] === group) {
                // Toggle off
                playerGroups.value = { ...playerGroups.value, [participantId]: '' }
            } else {
                playerGroups.value = { ...playerGroups.value, [participantId]: group }
            }
        }

        const readyPreviewStats = computed(() => {
            const total = confirmedParticipantsCount.value
            const groups = sessionParticipantGroups.value
            const format = props.data?.match_format
            let preview = {}
            if (format === MATCH_FORMAT.PARTNER_ROTATION) {
                preview = SessionService.getMatchPreview(format, 2, {}, total)
            } else {
                preview = SessionService.getMatchPreview(format, 2, groups)
            }
            return {
                participants: total,
                matches: preview.total_matches || 0,
                rounds: preview.total_rounds || 0,
            }
        })

        return {
            MiniMatchCard,
            activeTab,
            subActiveTab,
            tabs,
            subtabs,
            showUpdateMiniMatchModal,
            showCreateMiniMatchModal,
            CreateMiniMatch,
            miniMatches,
            countMiniMatches,
            scheduledMyMiniMatches,
            countMyMiniMatches,
            totalDuration,
            buildSets,
            formatDate,
            selectedMiniMatches,
            toggleSelectMiniMatch,
            cancelSelectedMiniMatches,
            totalTimeMiniMatches,
            DeleteConfirmationModal,
            showDeleteModal,
            confirmRemoval,
            showMiniMatchDetail,
            detailData,
            props,
            onMiniMatchCreated,
            getUserRatingBySport,
            pagination,
            visiblePages,
            changePage,
            // Session (Round Robin)
            sessionSchedule,
            sessionLeaderboardData,
            currentRound,
            isLoadingSchedule,
            playerGroups,
            isLoadingGroup,
            isSessionFormat,
            sessionStatus,
            sessionStatusLabel,
            confirmPlayerGroups,
            markReady,
            openReadyPreview,
            onStartFromPreview,
            startSession,
            finishSession,
            sessionSubTab,
            sessionSubtabs: SESSION_SUBTABS,
            onSelectFormat,
            MATCH_FORMAT,
            SESSION_STATUS,
            MiniTournamentLeaderboard,
            SessionReadyPreview,
            SessionScheduleRound,
            showReadyPreview,
            readyPreviewData,
            confirmedParticipantsCount,
            sessionParticipantGroups,
            readyPreviewStats,
            showFormatConfirm,
            selectedFormat,
            isConfirmingFormat,
            openFormatConfirm,
            formatConfirmLabel,
            confirmFormatSelection,
            confirmedParticipants,
            groupCounts,
            effectiveSessionStatus,
            groupValidationError,
            canSaveGroups,
            assignGroup,
        }
    }
}

</script>
