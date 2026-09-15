<template>
    <div ref="bracketContainer"
        class="fixed inset-0 z-[9999] overflow-hidden flex items-center justify-center bracket-bg-container"
        :style="bracketContainerStyle">
        <!-- Control Buttons -->
        <div class="fixed top-6 right-6 z-[10000] flex gap-4">
            <!-- ✅ Change background button (chỉ hiện với creator) -->
            <button v-if="isCreator && !isFullscreen" @click="openBackgroundModal"
                class="p-3 bg-white/10 hover:bg-white/20 backdrop-blur-md rounded-full border border-white/20 transition-all group shadow-xl"
                title="Đổi ảnh nền">
                <svg xmlns="http://www.w3.org/2000/svg"
                    class="w-6 h-6 text-white group-hover:scale-110 transition-transform" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </button>

            <!-- Fullscreen Toggle -->
            <button @click="toggleFullscreen"
                class="p-3 bg-white/10 hover:bg-white/20 backdrop-blur-md rounded-full border border-white/20 transition-all group shadow-xl"
                :title="isFullscreen ? 'Thoát toàn màn hình' : 'Toàn màn hình'">
                <svg v-if="!isFullscreen" xmlns="http://www.w3.org/2000/svg"
                    class="w-6 h-6 text-white group-hover:scale-110 transition-transform" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg"
                    class="w-6 h-6 text-white group-hover:scale-110 transition-transform" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Close Button -->
            <button v-if="!isFullscreen" @click="emit('close')"
                class="p-3 bg-red-500/20 hover:bg-red-500/40 backdrop-blur-md rounded-full border border-red-500/20 transition-all group shadow-xl"
                title="Đóng">
                <svg xmlns="http://www.w3.org/2000/svg"
                    class="w-6 h-6 text-white group-hover:rotate-90 transition-transform" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <div ref="bracketContent"
            class="flex flex-nowrap justify-center items-stretch gap-20 lg:gap-5 min-w-max min-h-[950px] py-10 px-10 transition-transform duration-300 origin-center"
            :style="{
                transform: `scale(${scale})`,
            }">
            <!-- Left Side -->
            <div class="flex gap-12 lg:gap-20 min-w-[200px] items-stretch">
                <div v-for="(round, rIdx) in leftRounds" :key="`left-${rIdx}`"
                    class="flex flex-col h-full justify-around">
                    <div
                        class="flex flex-col items-center justify-center opacity-70 bg-white/90 backdrop-blur-sm rounded-xl p-4 border border-white/20 shadow-sm mb-8 w-full">
                        <span class="font-black text-gray-800 text-sm uppercase tracking-wider">{{ round.round_name
                            }}</span>
                        <span class="text-[10px] text-gray-500 font-medium mt-1">
                            {{
                                round.matches?.[0]?.scheduled_at
                                    ? formatDateTime(
                                        round.matches[0].scheduled_at,
                                    )
                                    : "Chưa xếp lịch"
                            }}
                        </span>
                    </div>

                    <div class="flex flex-col flex-1 justify-around w-full min-h-[200px]">
                        <div v-for="(pair, pIdx) in chunkMatches(round.matches)" :key="`left-pair-${pIdx}`"
                            class="flex flex-col justify-around relative" :class="pair.length === 2 ? 'flex-1' : ''">
                            <template v-if="pair.length === 2">
                                <div class="relative flex items-center z-10">
                                    <MatchCard :match="pair[0]" side="left" :round-type="getRoundType(round.round_name)
                                        " :match-index="pIdx * 2" :previous-matches="getPreviousMatches(
                                            round,
                                            leftRounds,
                                            rIdx,
                                        )
                                            " :round-name="round.round_name" :is-right-side="false" />
                                    <div class="connector-h-right"></div>
                                </div>

                                <div class="relative flex items-center z-10">
                                    <MatchCard :match="pair[1]" side="left" :round-type="getRoundType(round.round_name)
                                        " :match-index="pIdx * 2 + 1" :previous-matches="getPreviousMatches(
                                            round,
                                            leftRounds,
                                            rIdx,
                                        )
                                            " :round-name="round.round_name" :is-right-side="false" />
                                    <div class="connector-h-right"></div>
                                </div>

                                <div class="connector-vertical-right"></div>
                                <div class="connector-next-right"></div>
                            </template>

                            <template v-else>
                                <div class="relative flex items-center z-10">
                                    <MatchCard :match="pair[0]" side="left" :round-type="getRoundType(round.round_name)
                                        " :match-index="pIdx" :previous-matches="getPreviousMatches(
                                            round,
                                            leftRounds,
                                            rIdx,
                                        )
                                            " :round-name="round.round_name" :is-right-side="false" />
                                    <div class="connector-line-right-full"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Center (Finals) -->
            <div class="flex flex-col items-center px-4 relative self-stretch min-w-[320px] z-50">
                <div class="w-full h-[72px] mb-8"></div>

                <div class="flex-1 flex flex-col justify-around w-full relative">
                    <div class="flex flex-col items-center relative">
                        <div class="relative">
                            <div class="absolute -top-10 left-0 right-0 flex flex-col items-center whitespace-nowrap">
                                <span class="text-yellow-400 text-xl font-bold uppercase tracking-wider">Chung
                                    Kết</span>
                            </div>

                            <MatchCard :match="finalMatch" :is-final="true" round-type="final" :match-index="0"
                                :previous-matches="[
                                    ...(leftRounds[leftRounds.length - 1]
                                        ?.matches || []),
                                    ...(rightRounds[rightRounds.length - 1]
                                        ?.matches || []),
                                ]" round-name="Chung kết" :is-right-side="false" />
                        </div>
                    </div>
                </div>

                <div v-if="hasThirdPlaceMatch"
                    class="absolute bottom-28 left-0 right-0 flex flex-col items-center pb-4 opacity-90 scale-95">
                    <div class="text-gray-400 text-[10px] font-bold uppercase tracking-wider mb-1">
                        Tranh hạng 3
                    </div>
                    <MatchCard :match="thirdPlaceMatch" round-type="third_place" :match-index="0"
                        round-name="Tranh hạng 3" />
                </div>
            </div>

            <!-- Right Side -->
            <div class="flex gap-12 lg:gap-20 flex-row-reverse min-w-[200px] items-stretch">
                <div v-for="(round, rIdx) in rightRounds" :key="`right-${rIdx}`"
                    class="flex flex-col h-full justify-around">
                    <div
                        class="flex flex-col items-center justify-center opacity-70 bg-white/90 backdrop-blur-sm rounded-xl p-4 border border-white/20 shadow-sm mb-8 w-full">
                        <span class="font-black text-gray-800 text-sm uppercase tracking-wider">
                            {{ round.round_name || round.title }}
                        </span>
                        <span class="text-[10px] text-gray-500 font-medium mt-1">
                            {{
                                round.matches?.[0]?.scheduled_at
                                    ? formatDateTime(
                                        round.matches[0].scheduled_at,
                                    )
                                    : "Chưa xếp lịch"
                            }}
                        </span>
                    </div>

                    <div class="flex flex-col flex-1 justify-around w-full min-h-[200px]">
                        <div v-for="(pair, pIdx) in chunkMatches(round.matches)" :key="`right-pair-${pIdx}`"
                            class="flex flex-col justify-around relative" :class="pair.length === 2 ? 'flex-1' : ''">
                            <template v-if="pair.length === 2">
                                <div class="relative flex items-center flex-row-reverse z-10">
                                    <MatchCard :match="pair[0]" side="right" :round-type="getRoundType(round.round_name)
                                        " :match-index="pIdx * 2" :previous-matches="getPreviousMatches(
                                            round,
                                            rightRounds,
                                            rIdx,
                                        )
                                            " :round-name="round.round_name" :is-right-side="true" />
                                    <div class="connector-h-left"></div>
                                </div>

                                <div class="relative flex items-center flex-row-reverse z-10">
                                    <MatchCard :match="pair[1]" side="right" :round-type="getRoundType(round.round_name)
                                        " :match-index="pIdx * 2 + 1" :previous-matches="getPreviousMatches(
                                            round,
                                            rightRounds,
                                            rIdx,
                                        )
                                            " :round-name="round.round_name" :is-right-side="true" />
                                    <div class="connector-h-left"></div>
                                </div>

                                <div class="connector-vertical-left"></div>
                                <div class="connector-next-left"></div>
                            </template>

                            <template v-else>
                                <div class="relative flex items-center flex-row-reverse z-10">
                                    <MatchCard :match="pair[0]" side="right" :round-type="getRoundType(round.round_name)
                                        " :match-index="pIdx" :previous-matches="getPreviousMatches(
                                            round,
                                            rightRounds,
                                            rIdx,
                                        )
                                            " :round-name="round.round_name" :is-right-side="true" />
                                    <div class="connector-line-left-full"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ✅ Modal chọn ảnh background -->
        <Teleport to="body">
            <Transition name="bg-modal">
                <div v-if="showBackgroundModal"
                    class="fixed inset-0 bg-black/70 z-[11000] flex items-center justify-center p-4"
                    @click.self="closeBackgroundModal">
                    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
                        <div class="flex justify-between items-center p-5 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800">Đổi ảnh nền sơ đồ</h3>
                            <button @click="closeBackgroundModal"
                                class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-gray-100 transition-colors">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="p-5 space-y-4">
                            <!-- Preview -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Xem trước</label>
                                <div class="relative w-full h-40 rounded-xl overflow-hidden border border-gray-200 bg-gray-50">
                                    <img v-if="backgroundPreviewUrl || currentBackgroundUrl" :src="backgroundPreviewUrl || currentBackgroundUrl"
                                        alt="Preview" class="w-full h-full object-cover" />
                                    <div v-else
                                        class="w-full h-full flex items-center justify-center text-gray-400 text-sm">
                                        Chưa có ảnh - sẽ dùng ảnh mặc định của dự án
                                    </div>
                                </div>
                            </div>

                            <!-- File input -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Chọn ảnh mới</label>
                                <input ref="backgroundFileInput" type="file" accept="image/jpeg,image/jpg,image/png,image/webp"
                                    @change="handleBackgroundFileChange"
                                    class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-[#D72D36] hover:file:bg-red-100 cursor-pointer" />
                                <p class="text-xs text-gray-500 mt-2">Hỗ trợ: JPG, PNG, WEBP. Tối đa 5MB.</p>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-between gap-3 pt-2">
                                <button v-if="currentBackgroundUrl" @click="handleRemoveBackground" :disabled="isUploadingBackground"
                                    class="text-sm font-medium text-red-600 hover:text-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Xoá ảnh (về mặc định)
                                </button>
                                <div class="flex-1"></div>
                                <button @click="closeBackgroundModal" :disabled="isUploadingBackground"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg disabled:opacity-50">
                                    Huỷ
                                </button>
                                <button @click="handleSaveBackground" :disabled="!pendingBackgroundFile || isUploadingBackground"
                                    class="px-4 py-2 text-sm font-medium text-white bg-[#D72D36] hover:bg-red-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                                    <svg v-if="isUploadingBackground" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    {{ isUploadingBackground ? 'Đang lưu...' : 'Lưu' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import {
    computed,
    defineComponent,
    h,
    ref,
    onMounted,
    onUnmounted,
    defineEmits,
    watch,
} from "vue";
import { VideoCameraIcon } from "@heroicons/vue/24/solid";
import * as TournamentService from "@/service/tournament.js";
import * as TournamentTypeService from "@/service/tournamentType.js";
import { toast } from "vue3-toastify";
import {
    updateBracketBackground as apiUpdateBracketBackground,
    removeBracketBackground as apiRemoveBracketBackground,
    getBracketBackground as apiGetBracketBackground,
} from "@/service/tournament.js";
const emit = defineEmits(["close"]);

const props = defineProps({
    tournamentId: {
        type: [Number, String],
        default: null,
    },
    tournamentTypeId: {
        type: [Number, String],
        default: null,
    },
    bracketData: {
        type: Object,
        default: () => ({}),
    },
    rankData: {
        type: Object,
        default: () => ({}),
    },
    // ✅ Props mới cho background
    isCreator: {
        type: Boolean,
        default: false,
    },
    bracketBackgroundUrl: {
        type: String,
        default: null,
    },
});

const bracketContainer = ref(null);
const bracketContent = ref(null);
const isFullscreen = ref(false);
const scale = ref(1);

// ✅ State cho background modal
const showBackgroundModal = ref(false);
const currentBackgroundUrl = ref(null);
const backgroundPreviewUrl = ref(null);
const pendingBackgroundFile = ref(null);
const backgroundFileInput = ref(null);
const isUploadingBackground = ref(false);

// Khởi tạo giá trị ban đầu từ prop (sau khi props đã sẵn sàng)
currentBackgroundUrl.value = props.bracketBackgroundUrl || null;

// Watch prop thay đổi để cập nhật background
watch(
    () => props.bracketBackgroundUrl,
    (newUrl) => {
        if (newUrl !== currentBackgroundUrl.value) {
            currentBackgroundUrl.value = newUrl || null;
        }
    },
);

// ✅ Computed: style cho container với dynamic background
const bracketContainerStyle = computed(() => {
    const url = currentBackgroundUrl.value;
    if (url) {
        return {
            backgroundImage: `url("${url}")`,
            backgroundSize: '100% 100%',
            backgroundPosition: 'center',
            backgroundRepeat: 'no-repeat',
            backgroundAttachment: 'fixed',
        };
    }
    // Fallback về ảnh mặc định của dự án
    return {};
});

const updateScale = () => {
    if (!bracketContainer.value || !bracketContent.value) return;

    const padding = 20;
    const containerWidth = bracketContainer.value.clientWidth - padding;
    const containerHeight = bracketContainer.value.clientHeight - padding;

    const contentWidth = bracketContent.value.offsetWidth;
    const contentHeight = bracketContent.value.offsetHeight;

    const scaleX = containerWidth / contentWidth;
    const scaleY = containerHeight / contentHeight;
    scale.value = Math.min(scaleX, scaleY);
};

const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        bracketContainer.value?.requestFullscreen().catch((err) => {
            console.error(
                `Error attempting to enable full-screen mode: ${err.message}`,
            );
        });
    } else {
        document.exitFullscreen();
    }
};

const handleFullscreenChange = () => {
    isFullscreen.value = !!document.fullscreenElement;
    setTimeout(updateScale, 100);
};

let resizeObserver = null;

const handleKeyDown = (e) => {
    if (e.key === "Escape") {
        emit("close");
    }
};

onMounted(() => {
    document.addEventListener("fullscreenchange", handleFullscreenChange);
    window.addEventListener("resize", updateScale);
    window.addEventListener("keydown", handleKeyDown);

    resizeObserver = new ResizeObserver(() => {
        updateScale();
    });
    if (bracketContent.value) {
        resizeObserver.observe(bracketContent.value);
    }

    setTimeout(updateScale, 500);
    
    // Initialize bracket data
    updateBracketFromProps();
    
    // Tự động refresh mỗi 30 giây (30000ms) để cập nhật realtime
    refreshInterval.value = setInterval(() => {
        refreshBracketData();
    }, 30000); // 30 giây
});

onUnmounted(() => {
    document.removeEventListener("fullscreenchange", handleFullscreenChange);
    window.removeEventListener("resize", updateScale);
    window.removeEventListener("keydown", handleKeyDown);
    if (resizeObserver) {
        resizeObserver.disconnect();
    }
    
    // Clear interval khi component unmount
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value);
        refreshInterval.value = null;
    }
});

const getRound16Label = (matchIndex, isRightSide) => {
    const matchNum = Math.floor(matchIndex / 2);
    const isHome = matchIndex % 2 === 0;
    const allGroupLetters = ["A", "B", "C", "D", "E", "F", "G", "H"];

    if (isRightSide) {
        const rightMapping = [
            [1, 0],
            [3, 2],
            [5, 4],
            [7, 6],
        ];
        const [firstIdx, secondIdx] = rightMapping[matchNum] || [1, 0];
        return isHome ? `Nhất bảng ${allGroupLetters[firstIdx]}` : `Nhì bảng ${allGroupLetters[secondIdx]}`;
    } else {
        const leftMapping = [
            [0, 1],
            [2, 3],
            [4, 5],
            [6, 7],
        ];
        const [firstIdx, secondIdx] = leftMapping[matchNum] || [0, 1];
        return isHome ? `Nhất bảng ${allGroupLetters[firstIdx]}` : `Nhì bảng ${allGroupLetters[secondIdx]}`;
    }
};

const getQuarterLabel = (matchIndex, isRightSide) => {
    const matchNum = Math.floor(matchIndex / 2);
    const isHome = matchIndex % 2 === 0;

    if (groupCount.value === 4) {
        const groupLetters = isRightSide ? ["C", "D"] : ["A", "B"];
        const g1 = groupLetters[matchNum === 0 ? 0 : 1];
        const g2 = groupLetters[matchNum === 0 ? 1 : 0];
        return isHome ? `Nhất bảng ${g1}` : `Nhì bảng ${g2}`;
    }

    const baseMatch = isRightSide ? 5 : 1;
    const matchNumber = baseMatch + matchNum * 2;
    return isHome ? `Thắng Trận ${matchNumber}` : `Thắng Trận ${matchNumber + 1}`;
};

const getSemiLabel = (matchIndex, isRightSide) => {
    const isHome = matchIndex % 2 === 0;

    if (groupCount.value === 2) {
        if (isRightSide) {
            return isHome ? `Nhất bảng B` : `Nhì bảng A`;
        } else {
            return isHome ? `Nhất bảng A` : `Nhì bảng B`;
        }
    }

    const baseQF = isRightSide ? 3 : 1;
    return isHome ? `Thắng Tứ kết ${baseQF}` : `Thắng Tứ kết ${baseQF + 1}`;
};

const getFinalLabel = (matchIndex) => {
    return matchIndex % 2 === 0 ? "Thắng Bán kết 1" : "Thắng Bán kết 2";
};

const getThirdPlaceLabel = (matchIndex) => {
    return matchIndex === 0 ? "Thua Bán kết 1" : "Thua Bán kết 2";
};

const getMatchLabel = (
    team,
    roundType,
    matchIndex,
    isRightSide,
    previousMatches,
    roundName,
) => {
    if (
        team &&
        (team.name || team.team_name) &&
        team.name !== "TBD" &&
        team.team_name !== "TBD"
    ) {
        return team.name || team.team_name;
    }

    const name = roundName?.toLowerCase() || "";
    if (
        roundType === "round16" ||
        name.includes("16") ||
        name.includes("1/8")
    ) {
        return getRound16Label(matchIndex, isRightSide);
    }

    if (roundType === "quarter" || name.includes("tứ kết")) {
        return getQuarterLabel(matchIndex, isRightSide);
    }

    if (roundType === "semi" || name.includes("bán kết")) {
        return getSemiLabel(matchIndex, isRightSide);
    }

    if (roundType === "final" || name.includes("chung kết")) {
        return getFinalLabel(matchIndex);
    }

    if (roundType === "third_place" || name.includes("tranh hạng 3")) {
        return getThirdPlaceLabel(matchIndex);
    }

    return "Chưa xác định";
};

const MatchCard = defineComponent({
    props: [
        "match",
        "side",
        "isFinal",
        "roundType",
        "matchIndex",
        "previousMatches",
        "roundName",
        "isRightSide",
    ],
    setup(props, { emit }) {
        // Computed để tính điểm số từ match (từ nhiều nguồn)
        const getMatchScores = () => {
            const match = props.match;
            let homeScore = 0;
            let awayScore = 0;
            
            // Ưu tiên 1: aggregate_score
            if (match.aggregate_score) {
                homeScore = Number(match.aggregate_score.home) || 0;
                awayScore = Number(match.aggregate_score.away) || 0;
            }
            
            // Ưu tiên 2: home_score/away_score trực tiếp
            if (homeScore === 0 && awayScore === 0) {
                homeScore = Number(match.home_score) || 0;
                awayScore = Number(match.away_score) || 0;
            }
            
            // Ưu tiên 3: Tính từ legs nếu có
            if (homeScore === 0 && awayScore === 0 && match?.legs && Array.isArray(match.legs)) {
                match.legs.forEach(leg => {
                    if (leg?.sets && typeof leg.sets === 'object' && !Array.isArray(leg.sets)) {
                        Object.values(leg.sets).forEach(setArray => {
                            if (Array.isArray(setArray)) {
                                setArray.forEach(teamScore => {
                                    if (typeof teamScore === 'object' && teamScore !== null) {
                                        const score = Number(teamScore.score) || 0;
                                        if (teamScore.team_id === match.home_team?.id) {
                                            homeScore += score;
                                        } else if (teamScore.team_id === match.away_team?.id) {
                                            awayScore += score;
                                        }
                                    }
                                });
                            }
                        });
                    }
                });
            }
            
            return { homeScore, awayScore };
        };
        
        // Helper function để kiểm tra match đã có điểm nhưng chưa completed
        // Đơn giản: nếu có bất kỳ điểm số nào > 0 và status chưa completed → màu vàng
        const hasAnyLegStarted = () => {
            const match = props.match;
            
            // Nếu match đã completed → màu xanh (không phải vàng)
            if (match.status === 'completed') return false;
            
            // Kiểm tra 1: Status in_progress → màu vàng
            if (match.status === 'in_progress') return true;
            
            // Kiểm tra 2: Tính điểm số từ match
            const { homeScore, awayScore } = getMatchScores();
            if (homeScore > 0 || awayScore > 0) {
                return true;
            }
            
            // Kiểm tra 3: Có legs với status in_progress hoặc completed
            if (match?.legs && Array.isArray(match.legs) && match.legs.length > 0) {
                const hasActiveLeg = match.legs.some(leg =>
                    leg && ['in_progress', 'completed'].includes(leg.status)
                );
                if (hasActiveLeg) return true;
            }
            
            return false;
        };

        // Computed để xác định màu card
        const getCardClasses = () => {
            const match = props.match;
            const baseClasses = [
                "bg-white rounded-lg shadow-sm border overflow-hidden z-10",
                props.isFinal ? "w-80 py-2" : "w-64",
            ];

            const hasStarted = hasAnyLegStarted();
            const isInProgress = match.status === 'in_progress';
            const isCompleted = match.status === 'completed';
            
            // Debug: Bật để xem match data thực tế
            const { homeScore, awayScore } = getMatchScores();
            if (homeScore > 0 || awayScore > 0) {
                console.log('Match Card Debug:', {
                    matchId: match.match_id || match.id,
                    status: match.status,
                    home_score: match.home_score,
                    away_score: match.away_score,
                    aggregate_score: match.aggregate_score,
                    calculatedHomeScore: homeScore,
                    calculatedAwayScore: awayScore,
                    hasLegs: !!match.legs,
                    legsCount: match.legs?.length || 0,
                    hasStarted,
                    isInProgress,
                    isCompleted,
                    willShowYellow: hasStarted || isInProgress
                });
            }

            if (isCompleted) {
                return [
                    ...baseClasses,
                    "border-green-500 shadow-md bg-green-500",
                ];
            } else if (hasStarted || isInProgress) {
                return [
                    ...baseClasses,
                    "border-[#FBBF24] shadow-md !bg-[#FBBF24]",
                ];
            }

            return [
                ...baseClasses,
                "border-gray-200",
            ];
        };

        // Computed để xác định màu header
        const getHeaderClasses = () => {
            const match = props.match;
            const baseClasses = "flex justify-between items-center px-3 py-1.5 border-b text-[10px] uppercase font-bold";

            if (match.status === 'completed') {
                return [
                    baseClasses,
                    "text-white bg-green-500 border-green-600",
                ];
            } else if (hasAnyLegStarted() || match.status === 'in_progress') {
                return [
                    baseClasses,
                    "text-white !bg-[#FBBF24] border-[#F59E0B]",
                ];
            }

            return [
                baseClasses,
                "bg-gray-50 border-gray-100 text-gray-500",
            ];
        };

        const getMatchLabelLocal = (
            team,
            roundType,
            matchIndex,
            isRightSide,
            previousMatches,
            roundName,
        ) => {
            if (
                team &&
                team.name &&
                team.name !== "TBD" &&
                team.team_name &&
                team.team_name !== "TBD"
            ) {
                return team.name || team.team_name;
            }
            return getMatchLabel(
                team,
                roundType,
                matchIndex,
                isRightSide,
                previousMatches,
                roundName,
            );
        };

        const getTeamName = (team, position) => {
            const hasValidTeamName =
                team &&
                (team.name || team.team_name) &&
                team.name !== "TBD" &&
                team.team_name !== "TBD" &&
                team.placeholder !== "TBD";

            if (hasValidTeamName) {
                return team.name || team.team_name;
            }

            const roundType = props.roundType || (props.isFinal ? "final" : "");
            const matchIdx = props.matchIndex || 0;
            const isRight = props.isRightSide || false;
            const prevMatches = props.previousMatches || null;
            const rName = props.roundName || "";

            const finalMatchIndex =
                matchIdx * 2 + (position === "home" ? 0 : 1);

            return getMatchLabelLocal(
                null,
                roundType,
                finalMatchIndex,
                isRight,
                prevMatches,
                rName,
            );
        };
        const getTeamAvatar = (team) => {
            if (team?.team_avatar) return team.team_avatar;
            const name = team?.name || team?.placeholder || "T";
            return `https://placehold.co/40x40/BBBFCC/3E414C?text=${name.charAt(0)}`;
        };
        const isWinner = (teamId) =>
            props.match.status === "completed" &&
            props.match.winner_team_id === teamId;

        const formatTime = (scheduledAt) => {
            if (!scheduledAt) return "Chưa xếp lịch";
            try {
                const date = new Date(scheduledAt);
                return date.toLocaleTimeString([], {
                    hour: "2-digit",
                    minute: "2-digit",
                });
            } catch (e) {
                console.error("Error formatting time:", e);
                return "Chưa xếp lịch";
            }
        };

        return () =>
            h(
                "div",
                {
                    class: [
                        ...getCardClasses(),
                        props.isFinal ? "ring-4 ring-yellow-100 shadow-xl" : "",
                    ],
                },
                [
                    h(
                        "div",
                        {
                            class: getHeaderClasses(),
                        },
                        [
                            h(
                                "span",
                                { class: props.isFinal ? "text-xs" : "" },
                                `Sân ${props.match.court || 1}`,
                            ),
                            props.match.status === "in_progress"
                                ? h(
                                    "span",
                                    {
                                        class: "text-red-500 flex items-center gap-1",
                                    },
                                    [
                                        h(VideoCameraIcon, {
                                            class: "w-3 h-3",
                                        }),
                                        "Live",
                                    ],
                                )
                                : h(
                                    "span",
                                    { class: props.isFinal ? "text-xs" : "" },
                                    formatTime(props.match.scheduled_at),
                                ),
                        ],
                    ),
                    h("div", { class: "flex flex-col" }, [
                        h(
                            "div",
                            {
                                class: [
                                    "flex justify-between items-center px-3 py-2 border-b border-gray-50",
                                    isWinner(props.match.home_team?.id)
                                        ? "bg-green-50"
                                        : "",
                                ],
                            },
                            [
                                h(
                                    "div",
                                    {
                                        class: "flex items-center gap-2 overflow-hidden",
                                    },
                                    [
                                        h("img", {
                                            src: getTeamAvatar(
                                                props.match.home_team,
                                            ),
                                            class: [
                                                "rounded-full object-cover bg-gray-200 flex-shrink-0",
                                                props.isFinal
                                                    ? "w-10 h-10"
                                                    : "w-6 h-6",
                                            ],
                                        }),
                                        h(
                                            "span",
                                            {
                                                class: [
                                                    "font-medium truncate",
                                                    props.isFinal
                                                        ? "text-sm max-w-[180px]"
                                                        : "text-xs max-w-[140px]",
                                                    isWinner(
                                                        props.match.home_team
                                                            ?.id,
                                                    )
                                                        ? "text-gray-900 font-bold"
                                                        : "text-gray-600",
                                                ],
                                            },
                                            getTeamName(
                                                props.match.home_team,
                                                "home",
                                            ),
                                        ),
                                    ],
                                ),
                                h(
                                    "span",
                                    {
                                        class: [
                                            "font-bold",
                                            props.isFinal
                                                ? "text-lg"
                                                : "text-sm",
                                            isWinner(props.match.home_team?.id)
                                                ? "text-green-600"
                                                : "text-gray-400",
                                        ],
                                    },
                                    props.match.home_score ?? props.match.aggregate_score?.home ?? "-",
                                ),
                            ],
                        ),
                        h(
                            "div",
                            {
                                class: [
                                    "flex justify-between items-center px-3 py-2",
                                    isWinner(props.match.away_team?.id)
                                        ? "bg-green-50"
                                        : "",
                                ],
                            },
                            [
                                h(
                                    "div",
                                    {
                                        class: "flex items-center gap-2 overflow-hidden",
                                    },
                                    [
                                        h("img", {
                                            src: getTeamAvatar(
                                                props.match.away_team,
                                            ),
                                            class: [
                                                "rounded-full object-cover bg-gray-200 flex-shrink-0",
                                                props.isFinal
                                                    ? "w-10 h-10"
                                                    : "w-6 h-6",
                                            ],
                                        }),
                                        h(
                                            "span",
                                            {
                                                class: [
                                                    "font-medium truncate",
                                                    props.isFinal
                                                        ? "text-sm max-w-[180px]"
                                                        : "text-xs max-w-[140px]",
                                                    isWinner(
                                                        props.match.away_team
                                                            ?.id,
                                                    )
                                                        ? "text-gray-900 font-bold"
                                                        : "text-gray-600",
                                                ],
                                            },
                                            getTeamName(
                                                props.match.away_team,
                                                "away",
                                            ),
                                        ),
                                    ],
                                ),
                                h(
                                    "span",
                                    {
                                        class: [
                                            "font-bold",
                                            props.isFinal
                                                ? "text-lg"
                                                : "text-sm",
                                            isWinner(props.match.away_team?.id)
                                                ? "text-green-600"
                                                : "text-gray-400",
                                        ],
                                    },
                                    props.match.away_score ?? props.match.aggregate_score?.away ?? "-",
                                ),
                            ],
                        ),
                    ]),
                ],
            );
    },
});

const bracket = ref({});
const refreshInterval = ref(null); // Lưu interval ID để clear khi unmount

const getTeamName = (team) => team?.name || "Chưa xác định";
const getTeamAvatar = (team) =>
    team?.team_avatar ||
    `https://placehold.co/40x40/BBBFCC/3E414C?text=${(team?.name || "T").charAt(0)}`;

const groupCount = computed(() => {
    if (!props.rankData) return 0;

    if (
        props.rankData.group_rankings &&
        Array.isArray(props.rankData.group_rankings)
    ) {
        return props.rankData.group_rankings.length;
    }

    if (props.rankData.rankings && Array.isArray(props.rankData.rankings)) {
        return 1;
    }

    return 0;
});

const createPlaceholderRounds = (isRightSide) => {
    const count = groupCount.value;
    const rounds = [];
    const baseId = isRightSide ? 100 : 1;

    if (count > 4) {
        rounds.push(createPlaceholderRound("Vòng 1/8", 4, baseId));
        rounds.push(createPlaceholderRound("Tứ kết", 2, baseId + 10));
        rounds.push(createPlaceholderRound("Bán kết", 1, baseId + 20));
    } else if (count > 2) {
        rounds.push(createPlaceholderRound("Tứ kết", 2, baseId));
        rounds.push(createPlaceholderRound("Bán kết", 1, baseId + 10));
    } else {
        rounds.push(createPlaceholderRound("Bán kết", 1, baseId));
    }

    return rounds;
};

const createPlaceholderMatch = (id) => ({
    match_id: `placeholder-${id}`,
    status: "pending",
    scheduled_at: null,
    home_team: null,
    away_team: null,
    home_score: 0,
    away_score: 0,
});

const createPlaceholderRound = (name, matchCount, startId) => ({
    round_name: name,
    matches: Array.from({ length: matchCount }, (_, i) =>
        createPlaceholderMatch(startId + i),
    ),
});

const formatDateTime = (scheduledAt) => {
    if (!scheduledAt) return "Chưa xếp lịch";
    try {
        const date = new Date(scheduledAt);
        if (Number.isNaN(date.getTime())) {
            return "Chưa xếp lịch";
        }
        const day = date.getDate();
        const monthNames = [
            "Th1",
            "Th2",
            "Th3",
            "Th4",
            "Th5",
            "Th6",
            "Th7",
            "Th8",
            "Th9",
            "Th10",
            "Th11",
            "Th12",
        ];
        const month = monthNames[date.getMonth()];
        const hours = date.getHours().toString().padStart(2, "0");
        const minutes = date.getMinutes().toString().padStart(2, "0");
        return `${day} ${month} - ${hours}:${minutes}`;
    } catch (e) {
        console.error("Error formatting date time:", e);
        return "Chưa xếp lịch";
    }
};

const chunkMatches = (matches) => {
    const pairs = [];
    if (!matches) return pairs;
    for (let i = 0; i < matches.length; i += 2) {
        pairs.push(matches.slice(i, i + 2));
    }
    return pairs;
};

const getRoundType = (roundName) => {
    if (!roundName) return "";
    const name = roundName.toLowerCase();
    if (name.includes("16") || name.includes("1/8")) return "round16";
    if (name.includes("tứ kết") || name.includes("quarter")) return "quarter";
    if (name.includes("bán kết") || name.includes("semi")) return "semi";
    if (name.includes("chung kết") || name.includes("final")) return "final";
    return "";
};

const getPreviousMatches = (currentRound, allRounds, currentRoundIndex) => {
    if (currentRoundIndex === 0) return null;
    const previousRound = allRounds[currentRoundIndex - 1];
    return previousRound?.matches || null;
};

const fetchBracketData = async () => {
    try {
        if (!props.tournamentId) {
            // Nếu không có tournamentId, không fetch
            return;
        }

        // Dùng API getBracketByTournamentId để đảm bảo tương thích với BracketMixedPreview
        // (BracketMixedPreview cần leftSide/rightSide, không phải knockout_stage)
        const response = await TournamentService.getBracketByTournamentId(
            props.tournamentId,
        );

        bracket.value = {
            poolStage: response.poolStage || response.pool_stage || [],
            leftSide: response.leftSide || response.left_side || [],
            rightSide: response.rightSide || response.right_side || [],
            finalMatch: response.finalMatch || response.final_match || null,
            thirdPlaceMatch: response.thirdPlaceMatch || response.third_place_match || null,
            has_third_place_match: response.has_third_place_match,
            // ✅ Giữ lại knockout_stage để có thể tự tính lại left/right nếu cần
            knockout_stage: response.knockout_stage || [],
        };
    } catch (error) {
        // Không hiển thị error khi auto-refresh để tránh spam
        console.error("Lỗi khi fetch bracket data:", error);
    }
};

const updateBracketFromProps = () => {
    if (props.bracketData && Object.keys(props.bracketData).length > 0) {
        // Giữ nguyên logic cũ - dùng leftSide/rightSide nếu có
        bracket.value = {
            poolStage: props.bracketData.poolStage || props.bracketData.pool_stage || [],
            leftSide: props.bracketData.leftSide || props.bracketData.left_side || [],
            rightSide: props.bracketData.rightSide || props.bracketData.right_side || [],
            finalMatch: props.bracketData.finalMatch || props.bracketData.final_match || null,
            thirdPlaceMatch: props.bracketData.thirdPlaceMatch || props.bracketData.third_place_match || null,
            has_third_place_match: props.bracketData.has_third_place_match,
            knockout_stage: props.bracketData.knockout_stage || [],
        };
    } else if (props.tournamentId) {
        fetchBracketData();
    }
};

// Function để refresh bracket data (dùng cho auto-refresh)
const refreshBracketData = async () => {
    // Luôn fetch từ API để có dữ liệu mới nhất (realtime)
    if (props.tournamentId) {
        await fetchBracketData();
    } else if (props.bracketData && Object.keys(props.bracketData).length > 0) {
        // Nếu không có tournamentId nhưng có bracketData, update từ props
        // (Trường hợp này ít xảy ra vì thường có tournamentId)
        updateBracketFromProps();
    }
};


// Watch bracketData để refresh khi có update điểm
watch(
    () => props.bracketData,
    (newData, oldData) => {
        if (newData && Object.keys(newData).length > 0) {
            updateBracketFromProps();
        }
    },
    { deep: true, immediate: false }
);

// Watch bracket.value để trigger re-render khi có thay đổi
watch(
    () => bracket.value,
    () => {
        // Force re-render khi bracket thay đổi
    },
    { deep: true }
);

// Helper function để normalize match data - đảm bảo có đủ legs và sets
const normalizeMatch = (match) => {
    if (!match) return match;
    
    // Nếu match đã có legs và đúng format, normalize sets
    if (match.legs && Array.isArray(match.legs) && match.legs.length > 0) {
        const normalizedLegs = match.legs.map(leg => {
            // Nếu sets đã đúng format (object với key set_1, set_2, ...), giữ nguyên
            if (leg.sets && typeof leg.sets === 'object' && !Array.isArray(leg.sets)) {
                const hasValidSets = Object.keys(leg.sets).some(key => key.startsWith('set_'));
                if (hasValidSets) {
                    // Đảm bảo sets có đúng format với team_id và score
                    const normalizedSets = {};
                    Object.keys(leg.sets).forEach(key => {
                        if (key.startsWith('set_')) {
                            const setArray = leg.sets[key];
                            if (Array.isArray(setArray)) {
                                normalizedSets[key] = setArray.map(item => {
                                    if (typeof item === 'object' && item !== null) {
                                        return {
                                            team_id: item.team_id,
                                            score: item.score || 0,
                                        };
                                    }
                                    return item;
                                });
                            } else {
                                normalizedSets[key] = setArray;
                            }
                        }
                    });
                    return {
                        ...leg,
                        sets: normalizedSets,
                    };
                }
            }
            
            // Nếu sets không đúng format, cần convert
            let sets = {};
            if (leg.sets) {
                if (Array.isArray(leg.sets)) {
                    leg.sets.forEach((set, index) => {
                        const key = `set_${index + 1}`;
                        sets[key] = Array.isArray(set) ? set : [set];
                    });
                } else if (typeof leg.sets === 'object') {
                    sets = leg.sets;
                }
            }
            
            return {
                ...leg,
                sets: sets,
            };
        });
        
        // Tính toán điểm số từ legs nếu chưa có
        let homeScore = match.home_score || match.aggregate_score?.home || 0;
        let awayScore = match.away_score || match.aggregate_score?.away || 0;
        
        // Nếu chưa có điểm số, tính từ legs
        if (homeScore === 0 && awayScore === 0 && normalizedLegs.length > 0) {
            normalizedLegs.forEach(leg => {
                if (leg.sets && typeof leg.sets === 'object') {
                    Object.values(leg.sets).forEach(setArray => {
                        if (Array.isArray(setArray)) {
                            setArray.forEach(teamScore => {
                                if (teamScore.team_id === match.home_team?.id) {
                                    homeScore += Number(teamScore.score) || 0;
                                } else if (teamScore.team_id === match.away_team?.id) {
                                    awayScore += Number(teamScore.score) || 0;
                                }
                            });
                        }
                    });
                }
            });
        }
        
        return {
            ...match,
            legs: normalizedLegs,
            // Đảm bảo có aggregate_score và home_score/away_score
            aggregate_score: match.aggregate_score || {
                home: homeScore,
                away: awayScore,
            },
            home_score: homeScore,
            away_score: awayScore,
        };
    }
    
    // Nếu không có legs, tạo từ match data (fallback)
    const homeTeamId = match.home_team?.id;
    const awayTeamId = match.away_team?.id;
    
    let sets = {};
    if (match.results && Array.isArray(match.results)) {
        match.results.forEach(result => {
            const setNum = result.set_number || 1;
            const key = `set_${setNum}`;
            if (!sets[key]) sets[key] = [];
            sets[key].push({
                team_id: result.team_id,
                score: result.score || 0,
            });
        });
    } else if (match.home_score !== undefined && match.away_score !== undefined) {
        sets = {
            set_1: [
                { team_id: homeTeamId, score: match.home_score || 0 },
                { team_id: awayTeamId, score: match.away_score || 0 },
            ],
        };
    }
    
    const legs = [
        {
            id: match.match_id || match.id,
            leg: 1,
            court: match.court || 1,
            status: match.status || 'pending',
            scheduled_at: match.scheduled_at,
            is_completed: match.status === 'completed',
            sets: sets,
        },
    ];
    
    // Tính điểm số từ sets nếu có
    let homeScore = match.home_score || match.aggregate_score?.home || 0;
    let awayScore = match.away_score || match.aggregate_score?.away || 0;
    
    if (homeScore === 0 && awayScore === 0 && Object.keys(sets).length > 0) {
        Object.values(sets).forEach(setArray => {
            if (Array.isArray(setArray)) {
                setArray.forEach(teamScore => {
                    if (teamScore.team_id === match.home_team?.id) {
                        homeScore += Number(teamScore.score) || 0;
                    } else if (teamScore.team_id === match.away_team?.id) {
                        awayScore += Number(teamScore.score) || 0;
                    }
                });
            }
        });
    }
    
    return {
        ...match,
        legs: legs,
        // Đảm bảo có aggregate_score và home_score/away_score
        aggregate_score: match.aggregate_score || {
            home: homeScore,
            away: awayScore,
        },
        home_score: homeScore,
        away_score: awayScore,
    };
};


/**
 * ✅ Helper: Tự tính lại leftSide/rightSide từ knockout_stage
 * vì backend chia left/right theo next_position có thể sai bố cục
 * Logic: với mỗi round, một nửa đầu matches là left, một nửa sau là right
 */
const computeLeftRightFromKnockoutStage = (knockoutStage) => {
    if (!knockoutStage || knockoutStage.length === 0) {
        return { leftSide: [], rightSide: [], finalMatch: null, thirdPlaceMatch: null };
    }

    const leftSide = [];
    const rightSide = [];
    let finalMatch = null;
    let thirdPlaceMatch = null;

    const maxRound = Math.max(...knockoutStage.map((r) => r.round || 0));

    knockoutStage.forEach((roundData) => {
        const round = roundData.round || 0;
        const roundName = roundData.round_name;
        const matches = roundData.matches || [];

        // Tách final match (round cuối, không phải tranh hạng 3)
        if (round === maxRound) {
            const finalMatchData = matches.find(
                (m) => m.is_third_place !== true && m.is_third_place !== 1,
            );
            if (finalMatchData && !finalMatch) {
                finalMatch = finalMatchData;
                return; // final match không thuộc left/right
            }
        }

        // Tách third place match
        const thirdPlaceData = matches.find(
            (m) => m.is_third_place === true || m.is_third_place === 1,
        );
        if (thirdPlaceData && !thirdPlaceMatch) {
            thirdPlaceMatch = thirdPlaceData;
            return; // third place không thuộc left/right
        }

        // Nếu round chỉ có 1 match thì đó là final (đã xử lý ở trên)
        // Chia đều: nửa đầu = left, nửa sau = right
        const mid = Math.ceil(matches.length / 2);
        const leftMatches = matches.slice(0, mid);
        const rightMatches = matches.slice(mid);

        if (leftMatches.length > 0) {
            leftSide.push({
                round: round,
                round_name: roundName,
                matches: leftMatches,
            });
        }

        if (rightMatches.length > 0) {
            rightSide.push({
                round: round,
                round_name: roundName,
                matches: rightMatches,
            });
        }
    });

    return { leftSide, rightSide, finalMatch, thirdPlaceMatch };
};

const leftRounds = computed(() => {
    // ✅ Ưu tiên dùng leftSide từ bracket
    // Nếu leftSide rỗng nhưng có knockout_stage, tự tính lại từ knockout_stage
    let data = bracket.value.leftSide || [];

    if (data.length === 0 && bracket.value.knockout_stage && bracket.value.knockout_stage.length > 0) {
        const computed = computeLeftRightFromKnockoutStage(bracket.value.knockout_stage);
        data = computed.leftSide;
    }

    data = [...data].sort((a, b) => a.round - b.round);

    if (data.length > 0) {
        // Normalize matches trong mỗi round
        return data.map(round => ({
            ...round,
            matches: (round.matches || []).map(match => normalizeMatch(match)),
        }));
    }
    return createPlaceholderRounds(false);
});

const rightRounds = computed(() => {
    // ✅ Ưu tiên dùng rightSide từ bracket
    // Nếu rightSide rỗng nhưng có knockout_stage, tự tính lại từ knockout_stage
    let data = bracket.value.rightSide || [];

    if (data.length === 0 && bracket.value.knockout_stage && bracket.value.knockout_stage.length > 0) {
        const computed = computeLeftRightFromKnockoutStage(bracket.value.knockout_stage);
        data = computed.rightSide;
    }

    data = [...data]
        .filter(
            (r) =>
                !r.matches.some(
                    (m) => m.is_third_place === true || m.is_third_place === 1,
                ),
        )
        .sort((a, b) => a.round - b.round);

    if (data.length > 0) {
        // Normalize matches trong mỗi round
        return data.map(round => ({
            ...round,
            matches: (round.matches || []).map(match => normalizeMatch(match)),
        }));
    }
    return createPlaceholderRounds(true);
});

const finalMatch = computed(() => {
    // ✅ Nếu finalMatch rỗng, thử lấy từ knockout_stage
    if (!bracket.value.finalMatch && bracket.value.knockout_stage && bracket.value.knockout_stage.length > 0) {
        const computed = computeLeftRightFromKnockoutStage(bracket.value.knockout_stage);
        if (computed.finalMatch) return normalizeMatch(computed.finalMatch);
    }
    if (bracket.value.finalMatch) return normalizeMatch(bracket.value.finalMatch);
    return createPlaceholderMatch(999);
});

const thirdPlaceMatch = computed(() => {
    // ✅ Nếu thirdPlaceMatch rỗng, thử lấy từ knockout_stage
    const rightSide = bracket.value.rightSide || [];
    for (const round of rightSide) {
        const match = round.matches.find(
            (m) => m.is_third_place === true || m.is_third_place === 1,
        );
        if (match) return normalizeMatch(match);
    }
    if (bracket.value.thirdPlaceMatch) return normalizeMatch(bracket.value.thirdPlaceMatch);

    // Fallback: lấy từ knockout_stage
    if (bracket.value.knockout_stage && bracket.value.knockout_stage.length > 0) {
        const computed = computeLeftRightFromKnockoutStage(bracket.value.knockout_stage);
        if (computed.thirdPlaceMatch) return normalizeMatch(computed.thirdPlaceMatch);
    }
    return createPlaceholderMatch("3rd");
});

const hasThirdPlaceMatch = computed(() => {
    const hasThirdPlace = props.bracketData?.has_third_place_match ||
        bracket.value?.has_third_place_match;

    if (hasThirdPlace === "false" || hasThirdPlace === false) {
        return false;
    }

    if (hasThirdPlace === "true" || hasThirdPlace === true) {
        return true;
    }

    const match = thirdPlaceMatch.value;
    return match && match.match_id && !match.match_id.includes('placeholder');
});

// ============================================================
// ✅ Background management (chỉ dành cho creator)
// ============================================================

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

const openBackgroundModal = async () => {
    showBackgroundModal.value = true;
    backgroundPreviewUrl.value = null;
    pendingBackgroundFile.value = null;

    // Refresh URL hiện tại từ API để đảm bảo cập nhật mới nhất
    if (props.tournamentId) {
        try {
            const res = await apiGetBracketBackground(props.tournamentId);
            if (res?.bracket_background_url !== undefined) {
                currentBackgroundUrl.value = res.bracket_background_url || null;
            }
        } catch (err) {
            console.error('Lỗi khi load bracket background:', err);
            // Fallback: giữ giá trị hiện tại
        }
    }
};

const closeBackgroundModal = () => {
    if (isUploadingBackground.value) return;
    showBackgroundModal.value = false;
    backgroundPreviewUrl.value = null;
    pendingBackgroundFile.value = null;
    if (backgroundFileInput.value) {
        backgroundFileInput.value.value = '';
    }
};

const handleBackgroundFileChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) {
        pendingBackgroundFile.value = null;
        backgroundPreviewUrl.value = null;
        return;
    }

    // Validate size
    if (file.size > MAX_FILE_SIZE) {
        toast.error('Ảnh không được vượt quá 5MB');
        event.target.value = '';
        pendingBackgroundFile.value = null;
        backgroundPreviewUrl.value = null;
        return;
    }

    // Validate type
    if (!ALLOWED_TYPES.includes(file.type)) {
        toast.error('Chỉ chấp nhận ảnh JPG, PNG, WEBP');
        event.target.value = '';
        pendingBackgroundFile.value = null;
        backgroundPreviewUrl.value = null;
        return;
    }

    pendingBackgroundFile.value = file;

    // Tạo preview local
    const reader = new FileReader();
    reader.onload = (e) => {
        backgroundPreviewUrl.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

const handleSaveBackground = async () => {
    if (!pendingBackgroundFile.value) {
        toast.warning('Vui lòng chọn ảnh');
        return;
    }
    if (!props.tournamentId) {
        toast.error('Không tìm thấy tournament ID');
        return;
    }

    isUploadingBackground.value = true;
    try {
        const res = await apiUpdateBracketBackground(
            props.tournamentId,
            pendingBackgroundFile.value,
        );
        if (res?.bracket_background_url !== undefined) {
            currentBackgroundUrl.value = res.bracket_background_url || null;
        }
        toast.success(res?.message || 'Cập nhật ảnh nền thành công');
        closeBackgroundModal();
    } catch (error) {
        const errorMsg =
            error.response?.data?.message ||
            error.message ||
            'Có lỗi xảy ra khi cập nhật ảnh nền';
        toast.error(errorMsg);
    } finally {
        isUploadingBackground.value = false;
    }
};

const handleRemoveBackground = async () => {
    if (!props.tournamentId) return;
    if (!window.confirm('Bạn có chắc muốn xoá ảnh nền và quay về ảnh mặc định?')) {
        return;
    }

    isUploadingBackground.value = true;
    try {
        const res = await apiRemoveBracketBackground(props.tournamentId);
        currentBackgroundUrl.value = res?.bracket_background_url || null;
        toast.success(res?.message || 'Đã xoá ảnh nền');
        closeBackgroundModal();
    } catch (error) {
        const errorMsg =
            error.response?.data?.message ||
            error.message ||
            'Có lỗi xảy ra khi xoá ảnh nền';
        toast.error(errorMsg);
    } finally {
        isUploadingBackground.value = false;
    }
};
</script>

<style scoped>
.bracket-bg-container {
    /* ✅ Mặc định: dùng ảnh bracket-bg.png của dự án
       Khi user chọn ảnh riêng, style này sẽ bị override bằng inline style */
    background-image: url("@/assets/images/bracket-bg.png");
    background-size: 100% 100%;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
}

/* Backward compat: giữ class cũ để không vỡ những chỗ dùng class này */
.bg-image-container {
    background-image: url("@/assets/images/bracket-bg.png");
    background-size: 100% 100%;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
}

/* ✅ Transition cho modal background */
.bg-modal-enter-active,
.bg-modal-leave-active {
    transition: opacity 0.25s ease;
}

.bg-modal-enter-from,
.bg-modal-leave-to {
    opacity: 0;
}

.bg-modal-enter-active .bg-white,
.bg-modal-leave-active .bg-white {
    transition: transform 0.3s ease;
}

.bg-modal-enter-from .bg-white,
.bg-modal-leave-to .bg-white {
    transform: scale(0.95);
}

.bracket-scroll-container {
    overflow-x: scroll !important;
    overflow-y: auto !important;
    scrollbar-width: thin;
    scrollbar-color: #c1c1c1 #f1f1f1;
}

.bracket-scroll-container::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.bracket-scroll-container::-webkit-scrollbar:horizontal {
    height: 10px;
}

.bracket-scroll-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.bracket-scroll-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 5px;
}

.bracket-scroll-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.bracket-scroll-container::-webkit-scrollbar-corner {
    background: transparent;
}

.connector-h-right,
.connector-h-left,
.connector-vertical-right,
.connector-vertical-left,
.connector-next-right,
.connector-next-left,
.connector-line-right-full,
.connector-line-left-full,
.connector-line-in-left,
.connector-line-in-right {
    background-color: rgba(255, 255, 255, 0.6);
    position: absolute;
    z-index: 0;
}

.connector-h-right {
    right: -40px;
    top: 50%;
    width: 40px;
    height: 2px;
}

.connector-vertical-right {
    right: -40px;
    top: 25%;
    bottom: 25%;
    width: 2px;
}

.connector-next-right {
    right: -80px;
    top: 50%;
    width: 40px;
    height: 2px;
}

.connector-line-right-full {
    right: -80px;
    top: 50%;
    width: 80px;
    height: 2px;
}

.connector-h-left {
    left: -40px;
    top: 50%;
    width: 40px;
    height: 2px;
}

.connector-vertical-left {
    left: -40px;
    top: 25%;
    bottom: 25%;
    width: 2px;
}

.connector-next-left {
    left: -80px;
    top: 50%;
    width: 40px;
    height: 2px;
}

.connector-line-left-full {
    left: -80px;
    top: 50%;
    width: 80px;
    height: 2px;
}

.connector-line-in-left {
    left: -40px;
    top: 50%;
    width: 40px;
    height: 2px;
}

.connector-line-in-right {
    right: -40px;
    top: 50%;
    width: 40px;
    height: 2px;
}
</style>
