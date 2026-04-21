<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                class="fixed inset-0 bg-black backdrop-blur-sm bg-opacity-60 flex items-center justify-center z-[60] p-0 sm:p-4"
                @click.self="goBack"
            >
                <div class="bg-white w-full sm:max-w-md h-full sm:h-[95vh] sm:max-h-[850px] flex flex-col sm:rounded-xl shadow-2xl overflow-hidden relative">
                    <!-- Header -->
                    <div class="relative flex items-center justify-center px-4 pt-3 pb-1 flex-shrink-0 bg-white">
                        <button @click="goBack" class="absolute left-4 text-gray-600 hover:text-gray-900 transition-colors p-2 rounded-lg hover:bg-gray-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <div class="text-center px-4 max-w-[70%]">
                            <h2 class="text-lg font-bold text-gray-900 leading-7">Nhập điểm trọng tài</h2>
                            <p class="text-xs text-gray-500 truncate uppercase font-medium tracking-wide">TỨ KẾT: {{ tournamentLabel }}</p>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto bg-gray-50">
                        <!-- Rules / info -->
                        <div class="px-3 py-2 bg-white flex-shrink-0">
                            <div class="flex items-center justify-center gap-4 text-xs xl:text-sm text-gray-500 font-medium">
                                <div class="flex items-center gap-1.5 whitespace-nowrap">
                                    <ChevronUpDownIcon class="w-5 h-5 text-[#5493E3] rotate-90" />
                                    <span class="font-medium">{{ basePoints }} Điểm</span>
                                </div>
                                <div class="w-px h-4 bg-gray-200"></div>
                                <div class="flex items-center gap-1.5 whitespace-nowrap">
                                    <ChevronUpDownIcon class="w-5 h-5 text-[#5493E3] rotate-90" />
                                    <span class="font-medium">Cách {{ pointsDifference }}</span>
                                </div>
                                <div class="w-px h-4 bg-gray-200"></div>
                                <div class="flex items-center gap-1.5 whitespace-nowrap">
                                    <Square3Stack3DIcon class="w-4 h-4 text-[#5493E3]" />
                                    <span class="font-medium">{{ setNumber }} Sets</span>
                                </div>
                            </div>
                        </div>

                        <!-- Set Tabs -->
                        <div class="flex items-center gap-1.5 px-4 bg-white">
                            <!-- Scrollable Sets Area (Swiper-like) -->
                            <div class="flex-1 flex items-center gap-2 overflow-x-auto no-scrollbar scroll-smooth scroll-snap-x">
                                <button
                                    v-for="(set, idx) in completedSets"
                                    :key="'completed-' + idx"
                                    type="button"
                                    @click="selectSet(idx)"
                                    class="px-3 py-1 rounded-md text-[11px] font-bold whitespace-nowrap transition-all flex-shrink-0 scroll-snap-align-start border"
                                    :class="activeSetIndex === idx
                                        ? 'bg-[#5493E3] text-white border-[#5493E3] shadow-md scale-105'
                                        : 'bg-white text-gray-500 border-gray-100 hover:bg-gray-50'"
                                >
                                    SET {{ idx + 1 }}: {{ allSets[idx].team1 }}-{{ allSets[idx].team2 }}
                                </button>
                                <button
                                    type="button"
                                    @click="selectSet(currentSetIndex)"
                                    class="px-3 py-1 rounded-md text-[11px] font-bold whitespace-nowrap transition-all flex-shrink-0 scroll-snap-align-start border"
                                    :class="activeSetIndex === currentSetIndex
                                        ? 'bg-[#5493E3] text-white border-[#5493E3] shadow-md scale-105'
                                        : 'bg-white text-gray-800 border-gray-100 hover:bg-gray-50'"
                                >
                                    SET {{ currentSetIndex + 1 }}: {{ allSets[currentSetIndex].team1 }}-{{ allSets[currentSetIndex].team2 }}
                                </button>
                            </div>
                            <div class="pl-1 border-gray-100">
                                <button
                                    type="button"
                                    class="w-6 h-6 rounded-sm bg-red-500 text-white font-bold shadow-sm hover:bg-red-600 active:scale-95 transition-all flex items-center justify-center text-xl"
                                    title="Thêm set"
                                    @click="handleAddSet"
                                >
                                    +
                                </button>
                            </div>
                        </div>

                        <!-- Scoreboard -->
                        <div class="px-4 py-2 bg-white">
                            <div class="flex items-center justify-center gap-3">
                                <div class="flex-1 rounded-md bg-blue-50 border border-blue-100 px-3 py-2 text-center">
                                    <div class="text-4xl font-semibold text-[#5493E3] leading-none tracking-tight">
                                        {{ team1Score }}
                                    </div>
                                    <div class="text-xs font-semibold text-[#5493E3] mt-2 uppercase tracking-wide">
                                        {{ team1.name || 'TEAM A' }}
                                    </div>
                                </div>
                                <div class="text-sm font-bold text-gray-400 px-1">VS</div>
                                <div class="flex-1 rounded-md bg-red-50 border border-red-100 px-3 py-2 text-center">
                                    <div class="text-4xl font-semibold text-red-600 leading-none tracking-tight">
                                        {{ team2Score }}
                                    </div>
                                    <div class="text-xs font-semibold text-red-600 mt-2 uppercase tracking-wide">
                                        {{ team2.name || 'TEAM B' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Court -->
                        <div class="px-4 py-2 flex flex-col items-center justify-center flex-1 min-h-0">
                            <!-- Giảm max-w từ 420px xuống 320-360px để thu nhỏ sân, giúp vừa vặn màn hình hơn -->
                            <div class="w-full max-w-[420px] sm:max-w-[420px]">
                                <div class="relative w-full max-h-[440px] rounded-xl bg-white shadow-md border border-gray-200 overflow-hidden" style="aspect-ratio: 9/11;">
                                    <!-- court lines -->
                                    <div class="absolute inset-0 pointer-events-none">
                                        <!-- Horizontal Center line -->
                                        <div class="absolute top-1/2 left-0 right-0 h-px bg-gray-300"></div>
                                        <!-- Vertical Net (Two parallel lines) -->
                                        <div class="absolute left-[calc(50%-25px)] top-0 bottom-0 w-px bg-gray-300"></div>
                                        <div class="absolute left-1/2 -translate-x-1/2 top-0 bottom-0 w-px bg-gray-300"></div>
                                        <div class="absolute right-[calc(50%-25px)] top-0 bottom-0 w-px bg-gray-300"></div>
                                    </div>

                                    <!-- players grid (Unified for Singles & Doubles) -->
                                    <div class="absolute inset-0 grid grid-cols-2 grid-rows-2">
                                        <!-- LEFT TOP (Team at Left Pos 2 / Singles Pos 2) -->
                                        <div class="relative flex flex-col items-center justify-center p-2">
                                            <template v-if="isDoubles ? leftTeamInfo.players[1] : (activeSinglesPos === 1 && leftTeamInfo.players[0])">
                                                <span 
                                                    class="absolute top-2 left-2 w-6 h-6 rounded-full bg-white border text-sm font-semibold flex items-center justify-center z-10 shadow-[0_1px_2px_rgba(0,0,0,0.05)]"
                                                    :class="leftTeamInfo.badgeClass"
                                                >
                                                    {{ isDoubles ? '2' : '1' }}
                                                </span>
                                                <div class="relative flex flex-col items-center">
                                                    <img
                                                        :src="(isDoubles ? leftTeamInfo.players[1] : leftTeamInfo.players[0]).avatar_url || '/images/default-avatar.png'"
                                                        class="w-16 h-16 rounded-full border-2 object-cover"
                                                        :class="isServingPosition(leftTeamInfo.key, isDoubles ? 1 : 0) ? `ring-2 ring-opacity-30` : 'border-transparent'"
                                                        :style="isServingPosition(leftTeamInfo.key, isDoubles ? 1 : 0) ? { borderColor: leftTeamInfo.ballColor, ringColor: leftTeamInfo.ballColor } : {}"
                                                    />
                                                </div>
                                                <div class="mt-3 text-sm md:text-base font-bold text-gray-800 truncate max-w-[120px] md:max-w-[150px] text-center leading-tight">
                                                    {{ (isDoubles ? leftTeamInfo.players[1] : leftTeamInfo.players[0]).full_name }}
                                                </div>
                                                <div class="h-6 mt-1.5 flex justify-center w-full relative z-10">
                                                    <Ball v-if="isServingPosition(leftTeamInfo.key, isDoubles ? 1 : 0)" :style="{ color: leftTeamInfo.ballColor }" class="w-7 h-7 drop-shadow-sm" />
                                                </div>
                                            </template>
                                        </div>

                                        <!-- RIGHT TOP (Team at Right Pos 1 / Singles Pos 1) -->
                                        <div class="relative flex flex-col items-center justify-center p-2">
                                            <template v-if="isDoubles ? rightTeamInfo.players[0] : (activeSinglesPos === 0 && rightTeamInfo.players[0])">
                                                <span 
                                                    class="absolute top-2 right-2 w-6 h-6 rounded-full bg-white border text-sm font-semibold flex items-center justify-center z-10 shadow-[0_1px_2px_rgba(0,0,0,0.05)]"
                                                    :class="rightTeamInfo.badgeClass"
                                                >
                                                    1
                                                </span>
                                                <div class="relative flex flex-col items-center">
                                                    <img
                                                        :src="rightTeamInfo.players[0].avatar_url || '/images/default-avatar.png'"
                                                        class="w-16 h-16 rounded-full border-2 object-cover"
                                                        :class="isServingPosition(rightTeamInfo.key, 0) ? `ring-2 ring-opacity-30` : 'border-transparent'"
                                                        :style="isServingPosition(rightTeamInfo.key, 0) ? { borderColor: rightTeamInfo.ballColor, ringColor: rightTeamInfo.ballColor } : {}"
                                                    />
                                                </div>
                                                <div class="mt-3 text-sm md:text-base font-bold text-gray-800 truncate max-w-[120px] md:max-w-[150px] text-center leading-tight">
                                                    {{ rightTeamInfo.players[0].full_name }}
                                                </div>
                                                <div class="h-6 mt-1.5 flex justify-center w-full relative z-10">
                                                    <Ball v-if="isServingPosition(rightTeamInfo.key, 0)" :style="{ color: rightTeamInfo.ballColor }" class="w-7 h-7 drop-shadow-sm" />
                                                </div>
                                            </template>
                                        </div>

                                        <!-- LEFT BOTTOM (Team at Left Pos 1 / Singles Pos 1) -->
                                        <div class="relative flex flex-col items-center justify-center p-2">
                                            <template v-if="isDoubles ? leftTeamInfo.players[0] : (activeSinglesPos === 0 && leftTeamInfo.players[0])">
                                                <span 
                                                    class="absolute bottom-2 left-2 w-6 h-6 rounded-full bg-white border text-sm font-semibold flex items-center justify-center z-10 shadow-[0_1px_2px_rgba(0,0,0,0.05)]"
                                                    :class="leftTeamInfo.badgeClass"
                                                >
                                                    1
                                                </span>
                                                <div class="relative flex flex-col items-center">
                                                    <img
                                                        :src="leftTeamInfo.players[0].avatar_url || '/images/default-avatar.png'"
                                                        class="w-16 h-16 rounded-full border-2 object-cover"
                                                        :class="isServingPosition(leftTeamInfo.key, 0) ? `ring-2 ring-opacity-30` : 'border-transparent'"
                                                        :style="isServingPosition(leftTeamInfo.key, 0) ? { borderColor: leftTeamInfo.ballColor, ringColor: leftTeamInfo.ballColor } : {}"
                                                    />
                                                </div>
                                                <div class="mt-3 text-sm md:text-base font-bold text-gray-800 truncate max-w-[120px] md:max-w-[150px] text-center leading-tight">
                                                    {{ leftTeamInfo.players[0].full_name }}
                                                </div>
                                                <div class="h-6 mt-1.5 flex justify-center w-full relative z-10">
                                                    <Ball v-if="isServingPosition(leftTeamInfo.key, 0)" :style="{ color: leftTeamInfo.ballColor }" class="w-7 h-7 drop-shadow-sm" />
                                                </div>
                                            </template>
                                        </div>

                                        <!-- RIGHT BOTTOM (Team at Right Pos 2 / Singles Pos 2) -->
                                        <div class="relative flex flex-col items-center justify-center p-2">
                                            <template v-if="isDoubles ? rightTeamInfo.players[1] : (activeSinglesPos === 1 && rightTeamInfo.players[0])">
                                                <span 
                                                    class="absolute bottom-2 right-2 w-6 h-6 rounded-full bg-white border text-sm font-semibold flex items-center justify-center z-10 shadow-[0_1px_2px_rgba(0,0,0,0.05)]"
                                                    :class="rightTeamInfo.badgeClass"
                                                >
                                                    {{ isDoubles ? '2' : '1' }}
                                                </span>
                                                <div class="relative flex flex-col items-center">
                                                    <img
                                                        :src="(isDoubles ? rightTeamInfo.players[1] : rightTeamInfo.players[0]).avatar_url || '/images/default-avatar.png'"
                                                        class="w-16 h-16 rounded-full border-2 object-cover"
                                                        :class="isServingPosition(rightTeamInfo.key, isDoubles ? 1 : 0) ? `ring-2 ring-opacity-30` : 'border-transparent'"
                                                        :style="isServingPosition(rightTeamInfo.key, isDoubles ? 1 : 0) ? { borderColor: rightTeamInfo.ballColor, ringColor: rightTeamInfo.ballColor } : {}"
                                                    />
                                                </div>
                                                <div class="mt-3 text-sm md:text-base font-bold text-gray-800 truncate max-w-[120px] md:max-w-[150px] text-center leading-tight">
                                                    {{ (isDoubles ? rightTeamInfo.players[1] : rightTeamInfo.players[0]).full_name }}
                                                </div>
                                                <div class="h-6 mt-1.5 flex justify-center w-full relative z-10">
                                                    <Ball v-if="isServingPosition(rightTeamInfo.key, isDoubles ? 1 : 0)" :style="{ color: rightTeamInfo.ballColor }" class="w-7 h-7 drop-shadow-sm" />
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Center vertical dividers have Ball & Swap buttons -->
                                    <button
                                        v-if="!matchStarted"
                                        type="button"
                                        class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-[#f1f3f5] border border-gray-200 text-gray-800 shadow-sm flex items-center justify-center hover:bg-gray-200 z-20 transition-colors"
                                        title="Đổi đội giao bóng"
                                        @click="chooseBall"
                                    >
                                        <Ball class="w-5 h-5 text-gray-800" />
                                    </button>

                                    <button
                                        type="button"
                                        @click="swapTeams"
                                        class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-[#f1f3f5] border border-gray-200 text-gray-800 font-bold text-lg shadow-sm flex items-center justify-center hover:bg-gray-200 z-20 transition-all gap-1"
                                        title="Đổi bên hai đội"
                                    >
                                    <ArrowsRightLeftIcon class="w-5 h-5 text-gray-800" />
                                    </button>

                                    <!-- Left side horizontal gap buttons -->
                                    <div class="absolute top-1/2 left-[22%] -translate-x-1/2 -translate-y-1/2 flex items-center gap-[6px] z-20 p-1">
                                        <button type="button" @click="startTimeout(leftTeamInfo.key)" :disabled="leftTeamInfo.key === 'team1' ? team1TimeoutUsed : team2TimeoutUsed" class="w-10 h-10 rounded-full flex items-center justify-center transition-colors disabled:opacity-50 disabled:cursor-not-allowed" :class="(leftTeamInfo.key === 'team1' ? team1TimeoutUsed : team2TimeoutUsed) ? 'text-gray-400 bg-gray-50' : 'bg-[#f1f3f5] text-gray-700 hover:bg-gray-200'" title="Timeout">
                                            <Hourglass class="w-5 h-5 text-gray-800" />
                                        </button>
                                        <button v-if="isDoubles" type="button" @click="swapPositions(leftTeamInfo.key)" :disabled="matchStarted" class="w-10 h-10 rounded-full flex items-center justify-center transition-colors disabled:opacity-50 disabled:cursor-not-allowed" :class="matchStarted ? 'text-gray-400 bg-gray-50' : 'bg-[#f1f3f5] text-gray-700 hover:bg-gray-200'" title="Đổi vị trí">
                                            <ArrowsRightLeftIcon class="w-5 h-5 text-gray-800 rotate-90" />
                                        </button>
                                    </div>

                                    <!-- Right side horizontal gap buttons -->
                                    <div class="absolute top-1/2 right-[22%] translate-x-1/2 -translate-y-1/2 flex items-center gap-[6px] z-20 p-1">
                                        <button v-if="isDoubles" type="button" @click="swapPositions(rightTeamInfo.key)" :disabled="matchStarted" class="w-10 h-10 rounded-full flex items-center justify-center transition-colors disabled:opacity-50 disabled:cursor-not-allowed" :class="matchStarted ? 'text-gray-400 bg-gray-50' : 'bg-[#f1f3f5] text-gray-700 hover:bg-gray-200'" title="Đổi vị trí">
                                            <ArrowsRightLeftIcon class="w-5 h-5 text-gray-800 rotate-90" />
                                        </button>
                                        <button type="button" @click="startTimeout(rightTeamInfo.key)" :disabled="rightTeamInfo.key === 'team1' ? team1TimeoutUsed : team2TimeoutUsed" class="w-10 h-10 rounded-full flex items-center justify-center transition-colors disabled:opacity-50 disabled:cursor-not-allowed" :class="(rightTeamInfo.key === 'team1' ? team1TimeoutUsed : team2TimeoutUsed) ? 'text-gray-400 bg-gray-50' : 'bg-[#f1f3f5] text-gray-700 hover:bg-gray-200'" title="Timeout">
                                            <Hourglass class="w-5 h-5 text-gray-800" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-white px-4 py-2 border-t border-gray-100 flex-shrink-0 space-y-2 relative z-10 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="handleSideOut"
                                :disabled="!canStartMatch"
                                class="py-2 rounded-md font-semibold text-sm transition-all disabled:opacity-40 shadow-sm"
                                :class="canStartMatch ? 'bg-red-600 hover:bg-red-700 active:bg-red-800 text-white' : 'bg-red-100 text-red-300'"
                            >
                                SIDE OUT
                                <span class="block text-[9px] font-normal opacity-80">ĐỔI GIAO BÓNG</span>
                            </button>
                            <button
                                type="button"
                                @click="handlePoint"
                                :disabled="!canStartMatch"
                                class="py-2 rounded-md font-semibold text-sm transition-all disabled:opacity-40 shadow-sm"
                                :class="canStartMatch ? 'bg-green-600 hover:bg-green-700 active:bg-green-800 text-white' : 'bg-green-100 text-green-300'"
                            >
                                POINT +1
                                <span class="block text-[9px] font-normal opacity-80">GHI ĐIỂM</span>
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="handleUndo"
                                :disabled="actionHistory.length === 0"
                                class="py-2 rounded-md font-medium text-sm transition-all disabled:opacity-50 border flex items-center justify-center gap-1.5"
                                :class="actionHistory.length > 0 ? 'bg-gray-50 hover:bg-gray-100 text-gray-800 border-gray-200 hover:border-gray-300' : 'bg-gray-50 text-gray-400 border-gray-100'"
                            >
                                <ArrowUturnLeftIcon class="w-4 h-4" />
                                Hoàn tác
                            </button>
                            <button
                                type="button"
                                @click="handleFinishSet"
                                class="py-2 bg-[#5493E3] hover:bg-[white] hover:text-[#5493E3] text-white rounded-md font-semibold text-sm transition-all disabled:opacity-50 border border-[#5493E3] hover:border-[#5493E3] flex items-center justify-center gap-1.5"
                            >
                                <CheckIcon class="w-4 h-4" />
                                Xong Set
                            </button>
                        </div>
                    </div>

                    <!-- Timeout Overlay -->
                    <div v-if="isTimeoutActive" class="absolute inset-0 bg-gray-900/65 backdrop-blur-sm z-50 flex flex-col items-center justify-center p-4">
                        <h3 class="text-white text-3xl font-bold tracking-[0.1em] mb-[60px] pb-6 uppercase drop-shadow-md">TIMEOUT</h3>
                        
                        <!-- Circular Progress -->
                        <div class="relative w-44 h-44 flex items-center justify-center mb-12">
                            <svg class="absolute inset-0 w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="45" stroke="currentColor" stroke-width="6" fill="transparent" class="text-gray-400 opacity-20" />
                                <circle cx="50" cy="50" r="45" stroke="currentColor" stroke-width="6" stroke-linecap="round" fill="transparent" class="text-[#5493E3] transition-all duration-1000 ease-linear" :stroke-dasharray="282.7" :stroke-dashoffset="282.7 * (1 - timeoutSeconds / 60)" />
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-white text-[52px] pr-2 font-bold italic tracking-tighter drop-shadow-md" style="font-family: Impact, sans-serif; line-height: 1">{{ timeoutSeconds }}</span>
                            </div>
                        </div>

                        <button type="button" @click="stopTimeout" class="px-6 py-2 bg-[#ff0036] hover:bg-red-600 text-white font-bold text-lg rounded-md shadow-lg transition-transform active:scale-95 w-[200px]">
                            Dừng đếm
                        </button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { CheckIcon, ChevronUpDownIcon, Square3Stack3DIcon, ArrowUturnLeftIcon, ArrowsRightLeftIcon } from '@heroicons/vue/24/outline';
import { ref, computed, onMounted } from 'vue'
import { toast } from 'vue3-toastify'
import Ball from '@/assets/images/ball.svg'
import Hourglass from '@/assets/images/hourglass.svg'

const props = defineProps({
    team1: { type: Object, required: true },
    team2: { type: Object, required: true },
    miniTournament: { type: Object, required: true },
    initialScores: { type: Array, default: () => [] }
})

const emit = defineEmits(['done', 'back'])

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

// Tất cả set được lưu trong một mảng thống nhất
const allSets = ref([{ team1: 0, team2: 0 }])
const currentSetIndex = ref(0)   // index của set đang thi đấu (live)
const activeSetIndex = ref(0)    // index của set đang được xem/chỉnh sửa

// Computed từ allSets
const completedSets = computed(() => allSets.value.slice(0, currentSetIndex.value))
const team1Score = computed(() => allSets.value[activeSetIndex.value]?.team1 ?? 0)
const team2Score = computed(() => allSets.value[activeSetIndex.value]?.team2 ?? 0)

const activeSinglesPos = computed(() => {
    if (isDoubles.value) return null
    const serverScore = servingTeam.value === 'team1' ? team1Score.value : team2Score.value
    return serverScore % 2 === 0 ? 0 : 1
})

const servingTeam = ref('team1')
const serverNumber = ref(1)
const isFirstServe = ref(true)
const matchStarted = ref(false)
const isSwapped = ref(false)

const team1TimeoutUsed = ref(false)
const team2TimeoutUsed = ref(false)
const isTimeoutActive = ref(false)
const timeoutSeconds = ref(60)
let timeoutInterval = null

// handIndex: người đang cầm giao bóng trong đội (tay 1 / tay 2)
const serverHandIndex = ref(0)
// serveBoxIndex: ô phát bóng (đổi ô khi ghi điểm), KHÔNG đổi người
const serveBoxIndex = ref(0)

const courtPositions = ref({
    team1: [],
    team2: []
})

const actionHistory = ref([])

const canStartMatch = computed(() => {
    const team = servingTeam.value
    const pos = serverHandIndex.value
    return Boolean(courtPositions.value?.[team]?.[pos])
})

const canScore = computed(() => {
    if (!matchStarted.value) return false
    return canStartMatch.value
})

const initCourt = () => {
    const normalize = (members) => {
        return (members || []).map(m => {
            if (!m) return null
            const user = m?.user ?? m
            return {
                id: user?.id ?? m?.id,
                full_name: user?.full_name ?? m?.full_name ?? 'N/A',
                avatar_url: user?.avatar_url ?? m?.avatar_url ?? '',
            }
        }).filter(Boolean)
    }
    courtPositions.value = {
        team1: normalize(props.team1?.members),
        team2: normalize(props.team2?.members)
    }
}

const safeClone = (obj) => {
    // structuredClone có thể tồn tại nhưng vẫn throw (DataCloneError) nếu object chứa ref không clone được (vd: window)
    if (typeof structuredClone === 'function') {
        try {
            return structuredClone(obj)
        } catch (e) {
            // eslint-disable-next-line no-unused-vars
            const _ignored = e
            // fallback bên dưới
        }
    }

    const seen = new WeakSet()
    const json = JSON.stringify(obj, (key, value) => {
        if (typeof value === 'function') return undefined
        if (typeof value === 'symbol') return undefined
        if (value && typeof value === 'object') {
            // loại bỏ các object không serialize được / gây vòng lặp
            // eslint-disable-next-line no-undef
            if (typeof globalThis !== 'undefined' && value === globalThis) return undefined
            // eslint-disable-next-line no-undef
            if (typeof document !== 'undefined' && value === document) return undefined
            if (seen.has(value)) return undefined
            seen.add(value)
        }
        return value
    })

    return JSON.parse(json)
}

const getSnapshot = () => safeClone({
    allSets: allSets.value,
    currentSetIndex: currentSetIndex.value,
    activeSetIndex: activeSetIndex.value,
    servingTeam: servingTeam.value,
    serverNumber: serverNumber.value,
    isFirstServe: isFirstServe.value,
    serverHandIndex: serverHandIndex.value,
    serveBoxIndex: serveBoxIndex.value,
    courtPositions: courtPositions.value,
    matchStarted: matchStarted.value,
    isSwapped: isSwapped.value,
    team1TimeoutUsed: team1TimeoutUsed.value,
    team2TimeoutUsed: team2TimeoutUsed.value,
})

const restoreSnapshot = (snap) => {
    allSets.value = snap.allSets
    currentSetIndex.value = snap.currentSetIndex
    activeSetIndex.value = snap.activeSetIndex ?? snap.currentSetIndex
    servingTeam.value = snap.servingTeam
    serverNumber.value = snap.serverNumber
    isFirstServe.value = snap.isFirstServe
    serverHandIndex.value = snap.serverHandIndex ?? 0
    serveBoxIndex.value = snap.serveBoxIndex ?? 0
    courtPositions.value = snap.courtPositions
    matchStarted.value = Boolean(snap.matchStarted)
    isSwapped.value = Boolean(snap.isSwapped)
    team1TimeoutUsed.value = Boolean(snap.team1TimeoutUsed)
    team2TimeoutUsed.value = Boolean(snap.team2TimeoutUsed)
}

const pushHistory = () => {
    actionHistory.value.push(getSnapshot())
}

const isServingPosition = (team, posIdx) => {
    if (team !== servingTeam.value) return false
    return posIdx === serverHandIndex.value
}

const getPositionLabel = (team, posIdx) => {
    return `Vị trí ${posIdx + 1}`
}

const currentServerName = computed(() => {
    const team = servingTeam.value
    const positions = courtPositions.value[team]
    const player = positions[serverHandIndex.value]
    return player?.full_name || 'N/A'
})

const leftTeamInfo = computed(() => {
    const logicalKey = !isSwapped.value ? 'team1' : 'team2'
    return {
        key: logicalKey,
        players: courtPositions.value[logicalKey],
        colorClass: logicalKey === 'team1' ? 'text-[#5493E3]' : 'text-red-600',
        badgeClass: logicalKey === 'team1' ? 'border-blue-200 text-[#5493E3]' : 'border-red-200 text-red-500',
        ballColor: logicalKey === 'team1' ? '#5493E3' : '#dc2626'
    }
})

const rightTeamInfo = computed(() => {
    const logicalKey = !isSwapped.value ? 'team2' : 'team1'
    return {
        key: logicalKey,
        players: courtPositions.value[logicalKey],
        colorClass: logicalKey === 'team1' ? 'text-[#5493E3]' : 'text-red-600',
        badgeClass: logicalKey === 'team1' ? 'border-blue-200 text-[#5493E3]' : 'border-red-200 text-red-500',
        ballColor: logicalKey === 'team1' ? '#5493E3' : '#dc2626'
    }
})

const chooseBall = () => {
    // Trước khi bắt đầu: dùng để chọn đội cầm bóng đầu tiên
    if (!matchStarted.value) {
        servingTeam.value = servingTeam.value === 'team1' ? 'team2' : 'team1'
        serverNumber.value = 1
        serverHandIndex.value = 0
        serveBoxIndex.value = 0
        isFirstServe.value = true
    }
}

const swapPositions = (team) => {
    if (matchStarted.value) return
    if (!isDoubles.value) return
    const arr = courtPositions.value[team]
    if (arr.length >= 2) {
        courtPositions.value[team] = [arr[1], arr[0]]
    }
}

const swapTeams = () => {
    isSwapped.value = !isSwapped.value
}

const handlePoint = () => {
    if (!matchStarted.value) {
        if (!canStartMatch.value) return
        pushHistory()
        matchStarted.value = true
    } else {
        if (!canScore.value) return
        pushHistory()
    }

    if (servingTeam.value === 'team1') {
        allSets.value[activeSetIndex.value].team1++
    } else {
        allSets.value[activeSetIndex.value].team2++
    }

    if (isDoubles.value) {
        serveBoxIndex.value = serveBoxIndex.value === 0 ? 1 : 0

        const team = servingTeam.value
        const arr = courtPositions.value[team]
        if (arr.length >= 2) {
            courtPositions.value[team] = [arr[1], arr[0]]
            serverHandIndex.value = serverHandIndex.value === 0 ? 1 : 0
        }
    }

    if (isFirstServe.value) {
        isFirstServe.value = false
    }
}

const switchToOpponentFirstServer = () => {
    servingTeam.value = servingTeam.value === 'team1' ? 'team2' : 'team1'
    serverNumber.value = 1
    serverHandIndex.value = 0
    serveBoxIndex.value = 0
}

const handleSideOut = () => {
    if (!matchStarted.value) {
        if (!canStartMatch.value) return
        pushHistory()
        matchStarted.value = true
    } else {
        if (!canScore.value) return
        pushHistory()
    }

    if (isFirstServe.value) {
        switchToOpponentFirstServer()
        isFirstServe.value = false
        return
    }

    if (!isDoubles.value) {
        switchToOpponentFirstServer()
        return
    }

    if (serverNumber.value === 1) {
        serverNumber.value = 2
        serverHandIndex.value = serverHandIndex.value === 0 ? 1 : 0
        return
    }

    switchToOpponentFirstServer()
}

const handleUndo = () => {
    if (actionHistory.value.length === 0) return
    const snap = actionHistory.value.pop()
    restoreSnapshot(snap)
}

const finishCurrentSet = () => {
    // Lưu set hiện tại, thêm set mới vào allSets
    const prevSet = allSets.value[currentSetIndex.value]
    currentSetIndex.value++
    activeSetIndex.value = currentSetIndex.value
    allSets.value.push({ team1: 0, team2: 0 })

    const loser = prevSet.team1 < prevSet.team2 ? 'team1' : 'team2'
    servingTeam.value = loser
    serverNumber.value = 1
    serverHandIndex.value = 0
    serveBoxIndex.value = 0
    isFirstServe.value = true
    matchStarted.value = false
    actionHistory.value = []
    team1TimeoutUsed.value = false
    team2TimeoutUsed.value = false

    initCourt()
}

const selectSet = (idx) => {
    activeSetIndex.value = idx
}

const handleAddSet = () => {
    finishCurrentSet()
}

const handleFinishSet = () => {
    const activeSet = allSets.value[activeSetIndex.value]
    if (!matchStarted.value || (activeSet.team1 === 0 && activeSet.team2 === 0)) {
        toast.warning('Trận đấu chưa bắt đầu hoặc chưa có điểm nào được ghi!')
        return
    }

    if (activeSet.team1 < basePoints.value && activeSet.team2 < basePoints.value) {
        toast.warning(`Chưa đạt điểm chạm ${basePoints.value}`)
        return
    }

    // Lưu tất cả điểm và đóng màn hình
    goBack()
}

const startTimeout = (team) => {
    if (team === 'team1' && team1TimeoutUsed.value) return
    if (team === 'team2' && team2TimeoutUsed.value) return

    pushHistory() // Lưu snapshot để có thể hoàn tác
    if (team === 'team1') team1TimeoutUsed.value = true
    if (team === 'team2') team2TimeoutUsed.value = true

    isTimeoutActive.value = true
    timeoutSeconds.value = 60

    if (timeoutInterval) clearInterval(timeoutInterval)
    timeoutInterval = setInterval(() => {
        timeoutSeconds.value--
        if (timeoutSeconds.value <= 0) {
            stopTimeout()
        }
    }, 1000)
}

const stopTimeout = () => {
    isTimeoutActive.value = false
    if (timeoutInterval) clearInterval(timeoutInterval)
    timeoutInterval = null
}

const handleTimeout = () => {
    // Placeholder for other global actions
}


const goBack = () => {
    const nonEmpty = allSets.value.filter(s => s.team1 > 0 || s.team2 > 0)
    if (nonEmpty.length > 0) {
        emit('done', nonEmpty)
    } else {
        emit('back')
    }
}

onMounted(() => {
    initCourt()

    if (props.initialScores && props.initialScores.length > 0) {
        const nonEmpty = props.initialScores.filter(s => s.team1 > 0 || s.team2 > 0)
        if (nonEmpty.length > 0) {
            allSets.value = nonEmpty.map(s => ({ team1: Number(s.team1), team2: Number(s.team2) }))
            // Giữ set cuối cùng làm set live, không tạo thêm set trống
            currentSetIndex.value = nonEmpty.length - 1
            activeSetIndex.value = currentSetIndex.value
            matchStarted.value = true
        }
    }
})
</script>
