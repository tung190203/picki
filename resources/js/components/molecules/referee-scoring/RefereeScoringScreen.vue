<template>
    <Teleport to="body">
        <div class="fixed inset-0 bg-gray-900 z-[60] flex flex-col text-white">
            <!-- Header -->
            <div class="bg-gray-800 px-4 py-3">
                <div class="flex items-center justify-between mb-2">
                    <button @click="goBack" class="text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <h2 class="text-lg font-bold text-center flex-1">Nhập điểm trọng tài</h2>
                    <button @click="handleTimeout" class="text-white p-1" title="Timeout">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </button>
                </div>
                <p class="text-center text-sm text-gray-300">
                    {{ tournamentLabel }}
                </p>
                <p class="text-center text-xs text-gray-400 mt-1">
                    {{ rulesLabel }}
                </p>
            </div>

            <!-- Set Tabs -->
            <div class="flex gap-2 px-4 py-2 bg-gray-800 overflow-x-auto">
                <div v-for="(set, idx) in completedSets" :key="'completed-' + idx"
                     class="px-3 py-1 rounded-full text-xs font-medium bg-gray-600 text-gray-200 whitespace-nowrap">
                    SET {{ idx + 1 }}: {{ set.team1 }}-{{ set.team2 }}
                </div>
                <div class="px-3 py-1 rounded-full text-xs font-medium bg-red-500 text-white flex items-center gap-1 whitespace-nowrap">
                    SET {{ currentSetIndex + 1 }}
                    <span class="inline-block w-2 h-2 bg-white rounded-full animate-pulse"></span>
                    LIVE
                </div>
            </div>

            <!-- Scoreboard -->
            <div class="flex items-center justify-center gap-8 py-4 bg-gray-800">
                <div class="text-center">
                    <div class="text-5xl font-bold" :class="servingTeam === 'team1' ? 'text-red-400' : 'text-white'">
                        {{ team1Score }}
                    </div>
                    <div class="text-sm text-gray-300 mt-1">{{ team1.name || 'TEAM A' }}</div>
                </div>
                <div class="text-2xl text-gray-500 font-bold">VS</div>
                <div class="text-center">
                    <div class="text-5xl font-bold" :class="servingTeam === 'team2' ? 'text-red-400' : 'text-white'">
                        {{ team2Score }}
                    </div>
                    <div class="text-sm text-gray-300 mt-1">{{ team2.name || 'TEAM B' }}</div>
                </div>
            </div>

            <!-- Court -->
            <div class="flex-1 flex flex-col items-center justify-center px-4 py-2">
                <div class="relative w-full max-w-md">
                    <!-- Court grid -->
                    <div class="grid grid-cols-2 gap-1 bg-gray-700 p-1 rounded-lg">
                        <!-- Top-left (Team1 position 0) -->
                        <div class="bg-gray-600 rounded-lg p-4 flex flex-col items-center justify-center min-h-[100px] relative cursor-pointer"
                             :class="{ 'ring-2 ring-yellow-400': isServingPosition('team1', 0) }"
                             @click="!matchStarted && swapPositions('team1')">
                            <div v-if="courtPositions.team1[0]" class="text-center">
                                <img :src="courtPositions.team1[0].avatar_url || '/images/default-avatar.png'"
                                     :alt="courtPositions.team1[0].full_name"
                                     class="w-12 h-12 rounded-full mx-auto border-2"
                                     :class="isServingPosition('team1', 0) ? 'border-yellow-400' : 'border-gray-400'" />
                                <p class="text-xs mt-1 truncate max-w-[80px]">{{ courtPositions.team1[0].full_name }}</p>
                            </div>
                            <div v-if="isServingPosition('team1', 0)" class="absolute top-1 right-1">
                                <span class="text-yellow-400 text-lg">🏐</span>
                            </div>
                            <div v-if="!matchStarted" class="absolute bottom-1 left-1/2 -translate-x-1/2">
                                <span class="text-[10px] text-gray-400">{{ getPositionLabel('team1', 0) }}</span>
                            </div>
                        </div>

                        <!-- Top-right (Team2 position 0) -->
                        <div class="bg-gray-600 rounded-lg p-4 flex flex-col items-center justify-center min-h-[100px] relative cursor-pointer"
                             :class="{ 'ring-2 ring-yellow-400': isServingPosition('team2', 0) }"
                             @click="!matchStarted && swapPositions('team2')">
                            <div v-if="courtPositions.team2[0]" class="text-center">
                                <img :src="courtPositions.team2[0].avatar_url || '/images/default-avatar.png'"
                                     :alt="courtPositions.team2[0].full_name"
                                     class="w-12 h-12 rounded-full mx-auto border-2"
                                     :class="isServingPosition('team2', 0) ? 'border-yellow-400' : 'border-gray-400'" />
                                <p class="text-xs mt-1 truncate max-w-[80px]">{{ courtPositions.team2[0].full_name }}</p>
                            </div>
                            <div v-if="isServingPosition('team2', 0)" class="absolute top-1 right-1">
                                <span class="text-yellow-400 text-lg">🏐</span>
                            </div>
                            <div v-if="!matchStarted" class="absolute bottom-1 left-1/2 -translate-x-1/2">
                                <span class="text-[10px] text-gray-400">{{ getPositionLabel('team2', 0) }}</span>
                            </div>
                        </div>

                        <!-- Middle: swap & choose ball buttons -->
                        <div class="col-span-2 flex items-center justify-center gap-4 py-2" v-if="!matchStarted && isDoubles">
                            <button @click="swapPositions('team1')"
                                    class="px-3 py-1 bg-gray-500 text-white rounded text-xs hover:bg-gray-400 transition-colors">
                                ⇅ Đổi chỗ T1
                            </button>
                            <button @click="chooseBall"
                                    class="px-3 py-1 rounded text-xs transition-colors"
                                    :class="servingTeam === 'team1' ? 'bg-red-500 text-white' : 'bg-blue-500 text-white'">
                                🏐 {{ servingTeam === 'team1' ? team1.name : team2.name }}
                            </button>
                            <button @click="swapPositions('team2')"
                                    class="px-3 py-1 bg-gray-500 text-white rounded text-xs hover:bg-gray-400 transition-colors">
                                ⇅ Đổi chỗ T2
                            </button>
                        </div>

                        <!-- Bottom-left (Team1 position 1) -->
                        <div v-if="isDoubles"
                             class="bg-gray-600 rounded-lg p-4 flex flex-col items-center justify-center min-h-[100px] relative cursor-pointer"
                             :class="{ 'ring-2 ring-yellow-400': isServingPosition('team1', 1) }"
                             @click="!matchStarted && swapPositions('team1')">
                            <div v-if="courtPositions.team1[1]" class="text-center">
                                <img :src="courtPositions.team1[1].avatar_url || '/images/default-avatar.png'"
                                     :alt="courtPositions.team1[1].full_name"
                                     class="w-12 h-12 rounded-full mx-auto border-2"
                                     :class="isServingPosition('team1', 1) ? 'border-yellow-400' : 'border-gray-400'" />
                                <p class="text-xs mt-1 truncate max-w-[80px]">{{ courtPositions.team1[1].full_name }}</p>
                            </div>
                            <div v-if="isServingPosition('team1', 1)" class="absolute top-1 right-1">
                                <span class="text-yellow-400 text-lg">🏐</span>
                            </div>
                            <div v-if="!matchStarted" class="absolute bottom-1 left-1/2 -translate-x-1/2">
                                <span class="text-[10px] text-gray-400">{{ getPositionLabel('team1', 1) }}</span>
                            </div>
                        </div>

                        <!-- Bottom-right (Team2 position 1) -->
                        <div v-if="isDoubles"
                             class="bg-gray-600 rounded-lg p-4 flex flex-col items-center justify-center min-h-[100px] relative cursor-pointer"
                             :class="{ 'ring-2 ring-yellow-400': isServingPosition('team2', 1) }"
                             @click="!matchStarted && swapPositions('team2')">
                            <div v-if="courtPositions.team2[1]" class="text-center">
                                <img :src="courtPositions.team2[1].avatar_url || '/images/default-avatar.png'"
                                     :alt="courtPositions.team2[1].full_name"
                                     class="w-12 h-12 rounded-full mx-auto border-2"
                                     :class="isServingPosition('team2', 1) ? 'border-yellow-400' : 'border-gray-400'" />
                                <p class="text-xs mt-1 truncate max-w-[80px]">{{ courtPositions.team2[1].full_name }}</p>
                            </div>
                            <div v-if="isServingPosition('team2', 1)" class="absolute top-1 right-1">
                                <span class="text-yellow-400 text-lg">🏐</span>
                            </div>
                            <div v-if="!matchStarted" class="absolute bottom-1 left-1/2 -translate-x-1/2">
                                <span class="text-[10px] text-gray-400">{{ getPositionLabel('team2', 1) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Service indicator -->
                    <div class="text-center mt-3 text-sm text-gray-400">
                        <template v-if="matchStarted">
                            Giao bóng: <span class="text-yellow-400 font-semibold">{{ currentServerName }}</span>
                            <span class="ml-2 text-gray-500">(Tay {{ isFirstServe ? '1*' : serverNumber }})</span>
                        </template>
                        <template v-else>
                            <span class="text-yellow-400">Chọn đội giao bóng và vị trí để bắt đầu</span>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-gray-800 px-4 py-4 space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <button @click="handleSideOut" :disabled="!matchStarted"
                            class="py-4 rounded-lg font-bold text-lg transition-colors disabled:opacity-40"
                            :class="matchStarted ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-gray-600 text-gray-400'">
                        SIDE OUT
                        <span class="block text-xs font-normal opacity-75">ĐỔI GIAO BÓNG</span>
                    </button>
                    <button @click="handlePoint" :disabled="!matchStarted"
                            class="py-4 rounded-lg font-bold text-lg transition-colors disabled:opacity-40"
                            :class="matchStarted ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-600 text-gray-400'">
                        POINT +1
                        <span class="block text-xs font-normal opacity-75">GHI ĐIỂM</span>
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button @click="handleUndo" :disabled="actionHistory.length === 0"
                            class="py-3 rounded-lg font-medium text-sm transition-colors"
                            :class="actionHistory.length > 0 ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-700 text-gray-500'">
                        ↩ Hoàn tác
                    </button>
                    <button @click="handleFinishSet"
                            class="py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium text-sm transition-colors"
                            :disabled="!matchStarted">
                        ✓ Xong Set
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script>
import { ref, computed, onMounted } from 'vue'

export default {
    name: 'RefereeScoringScreen',
    props: {
        team1: { type: Object, required: true },
        team2: { type: Object, required: true },
        miniTournament: { type: Object, required: true },
        initialScores: { type: Array, default: () => [] }
    },
    emits: ['done', 'back'],

    setup(props, { emit }) {
        const basePoints = computed(() => props.miniTournament?.base_points || 11)
        const pointsDifference = computed(() => props.miniTournament?.points_difference || 2)
        const maxPoints = computed(() => props.miniTournament?.max_points || 15)
        const setNumber = computed(() => props.miniTournament?.set_number || 3)

        const isDoubles = computed(() => {
            const t1 = props.team1?.members?.length || 0
            const t2 = props.team2?.members?.length || 0
            return t1 >= 2 && t2 >= 2
        })

        const tournamentLabel = computed(() => props.miniTournament?.name || '')
        const rulesLabel = computed(() =>
            `${basePoints.value} Điểm · Cách ${pointsDifference.value} · ${setNumber.value} Sets`
        )

        const currentSetIndex = ref(0)
        const completedSets = ref([])
        const team1Score = ref(0)
        const team2Score = ref(0)

        const servingTeam = ref('team1')
        const serverNumber = ref(1)
        const isFirstServe = ref(true)
        const matchStarted = ref(false)

        const serverPositionIndex = ref(0)

        const courtPositions = ref({
            team1: [],
            team2: []
        })

        const actionHistory = ref([])

        const initCourt = () => {
            const t1Members = (props.team1?.members || []).map(m => m.user)
            const t2Members = (props.team2?.members || []).map(m => m.user)
            courtPositions.value = {
                team1: [...t1Members],
                team2: [...t2Members]
            }
        }

        const getSnapshot = () => structuredClone({
            team1Score: team1Score.value,
            team2Score: team2Score.value,
            servingTeam: servingTeam.value,
            serverNumber: serverNumber.value,
            isFirstServe: isFirstServe.value,
            serverPositionIndex: serverPositionIndex.value,
            courtPositions: courtPositions.value,
            completedSets: completedSets.value,
            currentSetIndex: currentSetIndex.value,
        })

        const restoreSnapshot = (snap) => {
            team1Score.value = snap.team1Score
            team2Score.value = snap.team2Score
            servingTeam.value = snap.servingTeam
            serverNumber.value = snap.serverNumber
            isFirstServe.value = snap.isFirstServe
            serverPositionIndex.value = snap.serverPositionIndex
            courtPositions.value = snap.courtPositions
            completedSets.value = snap.completedSets
            currentSetIndex.value = snap.currentSetIndex
        }

        const pushHistory = () => {
            actionHistory.value.push(getSnapshot())
        }

        const isServingPosition = (team, posIdx) => {
            if (!matchStarted.value) return false
            if (team !== servingTeam.value) return false
            return posIdx === serverPositionIndex.value
        }

        const getPositionLabel = (team, posIdx) => {
            return `Vị trí ${posIdx + 1}`
        }

        const currentServerName = computed(() => {
            const team = servingTeam.value
            const positions = courtPositions.value[team]
            const player = positions[serverPositionIndex.value]
            return player?.full_name || 'N/A'
        })

        const chooseBall = () => {
            servingTeam.value = servingTeam.value === 'team1' ? 'team2' : 'team1'
        }

        const swapPositions = (team) => {
            if (matchStarted.value) return
            const arr = courtPositions.value[team]
            if (arr.length >= 2) {
                courtPositions.value[team] = [arr[1], arr[0]]
            }
        }

        const checkSetWin = () => {
            const s1 = team1Score.value
            const s2 = team2Score.value
            const bp = basePoints.value
            const pd = pointsDifference.value
            const mp = maxPoints.value

            if (s1 >= bp && (s1 - s2) >= pd) return true
            if (s2 >= bp && (s2 - s1) >= pd) return true
            if (s1 >= mp || s2 >= mp) return true

            return false
        }

        const handlePoint = () => {
            if (!matchStarted.value) return
            pushHistory()

            if (servingTeam.value === 'team1') {
                team1Score.value++
            } else {
                team2Score.value++
            }

            if (isDoubles.value) {
                const team = servingTeam.value
                const arr = courtPositions.value[team]
                if (arr.length >= 2) {
                    courtPositions.value[team] = [arr[1], arr[0]]
                }
            }

            if (isFirstServe.value) {
                isFirstServe.value = false
            }

            if (checkSetWin()) {
                finishCurrentSet()
            }
        }

        const handleSideOut = () => {
            if (!matchStarted.value) {
                matchStarted.value = true
                serverPositionIndex.value = 0
                return
            }

            pushHistory()

            if (isFirstServe.value) {
                const opponent = servingTeam.value === 'team1' ? 'team2' : 'team1'
                servingTeam.value = opponent
                serverNumber.value = 1
                serverPositionIndex.value = 0
                isFirstServe.value = false
                return
            }

            if (!isDoubles.value) {
                const opponent = servingTeam.value === 'team1' ? 'team2' : 'team1'
                servingTeam.value = opponent
                serverNumber.value = 1
                serverPositionIndex.value = 0
                return
            }

            if (serverNumber.value === 1) {
                serverNumber.value = 2
                serverPositionIndex.value = 1
            } else {
                const opponent = servingTeam.value === 'team1' ? 'team2' : 'team1'
                servingTeam.value = opponent
                serverNumber.value = 1
                serverPositionIndex.value = 0
            }
        }

        const handleUndo = () => {
            if (actionHistory.value.length === 0) return
            const snap = actionHistory.value.pop()
            restoreSnapshot(snap)
        }

        const finishCurrentSet = () => {
            completedSets.value.push({
                team1: team1Score.value,
                team2: team2Score.value
            })

            team1Score.value = 0
            team2Score.value = 0
            currentSetIndex.value++

            const loser = completedSets.value[completedSets.value.length - 1].team1 <
                          completedSets.value[completedSets.value.length - 1].team2
                          ? 'team1' : 'team2'
            servingTeam.value = loser
            serverNumber.value = 1
            serverPositionIndex.value = 0
            isFirstServe.value = true
            actionHistory.value = []

            initCourt()
        }

        const handleFinishSet = () => {
            if (!matchStarted.value) return

            if (team1Score.value === 0 && team2Score.value === 0) return

            finishCurrentSet()
        }

        const handleTimeout = () => {
            // Placeholder for timeout functionality
        }

        const goBack = () => {
            const allScores = [
                ...completedSets.value,
            ]
            if (team1Score.value > 0 || team2Score.value > 0) {
                allScores.push({
                    team1: team1Score.value,
                    team2: team2Score.value
                })
            }

            if (allScores.length > 0) {
                emit('done', allScores)
            } else {
                emit('back')
            }
        }

        onMounted(() => {
            initCourt()

            if (props.initialScores && props.initialScores.length > 0) {
                const nonEmpty = props.initialScores.filter(s => s.team1 > 0 || s.team2 > 0)
                if (nonEmpty.length > 0) {
                    completedSets.value = nonEmpty.map(s => ({
                        team1: Number(s.team1),
                        team2: Number(s.team2)
                    }))
                    currentSetIndex.value = completedSets.value.length
                }
            }
        })

        return {
            basePoints,
            pointsDifference,
            maxPoints,
            setNumber,
            isDoubles,
            tournamentLabel,
            rulesLabel,
            currentSetIndex,
            completedSets,
            team1Score,
            team2Score,
            servingTeam,
            serverNumber,
            isFirstServe,
            matchStarted,
            serverPositionIndex,
            courtPositions,
            actionHistory,
            currentServerName,
            isServingPosition,
            getPositionLabel,
            chooseBall,
            swapPositions,
            handlePoint,
            handleSideOut,
            handleUndo,
            handleFinishSet,
            handleTimeout,
            goBack,
        }
    }
}
</script>
