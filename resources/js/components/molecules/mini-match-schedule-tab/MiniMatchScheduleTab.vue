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
import { updateMiniTournamentByClub } from '@/service/miniTournament.js';
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/vue/24/solid/index.js";
import DeleteConfirmationModal from '@/components/molecules/DeleteConfirmationModal.vue'
import { SESSION_STATUS, MATCH_FORMAT } from '@/constants/index.js';
import MiniTournamentLeaderboard from '@/components/molecules/MiniTournamentLeaderboard.vue';
import SessionScheduleRound from '@/components/molecules/session-schedule-round/SessionScheduleRound.vue';
import MatchScoreInput from '@/components/molecules/MatchScoreInput.vue';

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
            SessionScheduleRound,
            MatchScoreInput,
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
        const isCreatingSchedule = ref(false)
        const playerGroups = ref({}) // participantId -> group
        const sessionSubTab = ref('format')
        const showFormatConfirm = ref(false)
        const selectedFormat = ref('')
        const isConfirmingFormat = ref(false)
        const isLoadingPartnerMatches = ref(false)
        const partnerMatches = ref([])
        const isStartingSession = ref(false)

        const selectedRoundData = computed(() => {
            return sessionSchedule.value.find(r => r.round_number === currentRound.value) || null
        })

        const selectedSessionMatch = ref(null)
        const sessionRRMatch = ref(null)
        const showRRMatchModal = ref(false)
        const showSessionScoreModal = ref(false)
        const sessionScores = ref([{ team1: 0, team2: 0 }])
        const isSavingSessionScore = ref(false)

        const onMatchClick = async (match) => {
            if (props.isCreator) {
                sessionRRMatch.value = match
                showRRMatchModal.value = true
                return
            }
            selectedSessionMatch.value = match
            // Initialize scores from existing results
            if (match.results_by_sets && Object.keys(match.results_by_sets).length > 0) {
                const r = match.results_by_sets
                const team1Id = match.team1?.id
                const team2Id = match.team2?.id
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
                sessionScores.value = sets.length > 0 ? sets : [{ team1: 0, team2: 0 }]
            } else {
                sessionScores.value = [{ team1: 0, team2: 0 }]
            }
            showSessionScoreModal.value = true
        }

        const onSessionMatchUpdated = async () => {
            showSessionScoreModal.value = false
            selectedSessionMatch.value = null
            if (props.data?.id) {
                await loadSessionSchedule(props.data.id)
                await loadSessionLeaderboard(props.data.id)
            }
            emit('refresh-data')
        }

        const onSaveSessionMatchScore = async () => {
            if (!selectedSessionMatch.value || isSavingSessionScore.value) return
            isSavingSessionScore.value = true
            try {
                const match = selectedSessionMatch.value
                const sets = sessionScores.value
                    .filter(s => s.team1 > 0 || s.team2 > 0)
                    .map((score, idx) => ({
                        set_number: idx + 1,
                        results: [
                            { team: 'team1', score: Number(score.team1) },
                            { team: 'team2', score: Number(score.team2) }
                        ]
                    }))

                const payload = {
                    match_id: match.id,
                    team1: match.team1?.members?.map(m => m.id) || [],
                    team2: match.team2?.members?.map(m => m.id) || [],
                    sets: sets.length > 0 ? sets : [{ set_number: 1, results: [{ team: 'team1', score: 0 }, { team: 'team2', score: 0 }] }]
                }

                await MiniMatchService.saveMiniMatch(match.mini_tournament_id, payload)
                toast.success('Đã lưu kết quả!')
                showSessionScoreModal.value = false
                selectedSessionMatch.value = null
                await loadSessionSchedule(props.data.id)
                await loadSessionLeaderboard(props.data.id)
                emit('refresh-data')
            } catch (err) {
                toast.error(err.response?.data?.message || 'Lỗi khi lưu kết quả')
            } finally {
                isSavingSessionScore.value = false
            }
        }

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
            if (!pagination.value) return
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

        watch(sessionSubTab, () => {
            if (pagination.value) {
                pagination.value.current_page = 1
            }
            selectedMiniMatches.value = []

            if (!props.data?.id) return

            if (sessionSubTab.value === 'schedule') {
                loadSessionSchedule(props.data.id)
            } else if (sessionSubTab.value === 'leaderboard') {
                loadSessionLeaderboard(props.data.id)
            }
        })

        // Auto-activate round matches when switching to a new round
        watch(currentRound, () => {
            if (sessionSubTab.value === 'schedule') {
                onActivateRound()
            }
        })

        const loadSessionSchedule = async (id) => {
            try {
                isLoadingSchedule.value = true
                const res = await MiniMatchService.getListMiniMatches(id, {})
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

        // Watch data changes to detect session-related fields
        watch(() => props.data, async (newData) => {
            if (newData) {
                if (newData.match_format && newData.match_format !== MATCH_FORMAT.STANDARD) {
                    await loadSessionSchedule(newData.id)
                    await loadSessionLeaderboard(newData.id)
                    // Init session sub-tab based on status
                    if (newData.session_status === SESSION_STATUS.ONGOING) {
                        sessionSubTab.value = 'schedule'
                    } else if (newData.session_status === SESSION_STATUS.FINISHED) {
                        sessionSubTab.value = 'leaderboard'
                    } else {
                        // partner_rotation → schedule tab (no grouping needed)
                        // mixed_gender / rank_pairing → group tab (needs grouping)
                        if (newData.match_format === MATCH_FORMAT.PARTNER_ROTATION) {
                            sessionSubTab.value = 'schedule'
                        } else {
                            sessionSubTab.value = 'group'
                        }
                    }
                }
                if (newData.match_format === MATCH_FORMAT.MIXED_GENDER || newData.match_format === MATCH_FORMAT.RANK_PAIRING) {
                    loadPlayerGroups(newData)
                }
            }
        }, { immediate: true })

        // Watch data.id for standard format match loading
        watch(
            () => props.data?.id,
            (miniTournamentId) => {
                if (!miniTournamentId) return

                if (pagination.value) {
                    pagination.value.current_page = 1
                }

                const isSessionFormat = props.data?.match_format &&
                    props.data.match_format !== 'standard'

                if (isSessionFormat) return

                if (subActiveTab.value === 'match') {
                    getMiniMatches(miniTournamentId, 1)
                } else if (subActiveTab.value === 'your-match') {
                    getMyMiniMatches(miniTournamentId, 1)
                }
            },
            { immediate: true }
        )

        const allParticipantsGrouped = computed(() => {
            const confirmed = confirmedParticipants.value
            if (!confirmed || confirmed.length === 0) return false
            return confirmed.every(p => playerGroups.value[p.id])
        })

        const onStartWithGroups = async () => {
            isStartingSession.value = true
            try {
                const res = await SessionService.startSession(props.data.id, 2, playerGroups.value)
                toast.success('Đã bắt đầu session!')
                sessionSchedule.value = res.data?.rounds || []
                await loadSessionLeaderboard(props.data.id)
                sessionSubTab.value = 'schedule'
                emit('refresh-data')
            } catch (e) {
                toast.error(e.response?.data?.message || 'Không thể bắt đầu session')
            } finally {
                isStartingSession.value = false
            }
        }

        const onStartPartnerRotation = async () => {
            isStartingSession.value = true
            try {
                const matchType = props.data.format === 'double' ? 'double' : 'single'
                const res = await SessionService.startSession(props.data.id, 2, {}, matchType)
                toast.success('Đã bắt đầu session!')
                sessionSchedule.value = res.data?.rounds || []
                await loadSessionLeaderboard(props.data.id)
                sessionSubTab.value = 'schedule'
                emit('refresh-data')
            } catch (e) {
                toast.error(e.response?.data?.message || 'Không thể bắt đầu session')
            } finally {
                isStartingSession.value = false
            }
        }

        // Session ends automatically when all matches complete (checkSessionCompletion).
        // The button only refreshes leaderboard and switches tab.
        const finishSession = async () => {
            try {
                await loadSessionLeaderboard(props.data.id)
                sessionSubTab.value = 'leaderboard'
            } catch (e) {
                toast.error(e.response?.data?.message || 'Không thể tải bảng xếp hạng')
            }
        }

        // Rounds activate automatically when the previous round completes (checkSessionCompletion).
        // Manual activation is no longer needed.
        const onActivateRound = async () => {
            // intentionally no-op
        }

        const onCreateSchedule = async () => {
            isCreatingSchedule.value = true
            try {
                const res = await SessionService.startSession(props.data.id, 2, playerGroups.value)
                toast.success('Đã tạo lịch thi đấu!')
                sessionSchedule.value = res.data?.rounds || []
                await loadSessionLeaderboard(props.data.id)
                emit('refresh-data')
            } catch (e) {
                toast.error(e.response?.data?.message || 'Không thể tạo lịch đấu')
            } finally {
                isCreatingSchedule.value = false
            }
        }

        const onSelectFormat = async (format) => {
            emit('select-format', format)
        }

        const openFormatConfirm = (format) => {
            if (format === MATCH_FORMAT.PARTNER_ROTATION) {
                if (props.data.format !== 'double') {
                    toast.error('Xoay vòng partner chỉ áp dụng cho kèo đấu đôi.')
                    return
                }
                const cnt = confirmedParticipantsCount.value
                if (cnt < 6 || cnt > 8) {
                    toast.error('Xoay vòng partner cần từ 6 đến 8 người đã xác nhận.')
                    return
                }
            }
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

                // Switch tab based on format after successful update
                if (selectedFormat.value === MATCH_FORMAT.MIXED_GENDER || selectedFormat.value === MATCH_FORMAT.RANK_PAIRING) {
                    sessionSubTab.value = 'group'
                } else {
                    // standard & partner_rotation → schedule tab
                    sessionSubTab.value = 'schedule'
                }

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
            isCreatingSchedule,
            selectedRoundData,
            playerGroups,
            isSessionFormat,
            sessionStatus,
            sessionStatusLabel,
            finishSession,
            onCreateSchedule,
            sessionSubTab,
            sessionSubtabs: SESSION_SUBTABS,
            onSelectFormat,
            MATCH_FORMAT,
            SESSION_STATUS,
            MiniTournamentLeaderboard,
            SessionScheduleRound,
            confirmedParticipantsCount,
            sessionParticipantGroups,
            showFormatConfirm,
            selectedFormat,
            isConfirmingFormat,
            isLoadingPartnerMatches,
            partnerMatches,
            openFormatConfirm,
            formatConfirmLabel,
            confirmFormatSelection,
            confirmedParticipants,
            groupCounts,
            effectiveSessionStatus,
            groupValidationError,
            allParticipantsGrouped,
            assignGroup,
            isStartingSession,
            onStartWithGroups,
            onStartPartnerRotation,
            selectedSessionMatch,
            sessionRRMatch,
            showRRMatchModal,
            showSessionScoreModal,
            onMatchClick,
            onSessionMatchUpdated,
            onActivateRound,
            sessionScores,
            isSavingSessionScore,
            onSaveSessionMatchScore,
        }
    }
}

</script>
