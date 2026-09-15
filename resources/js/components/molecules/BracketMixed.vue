<template>
    <div class="grid grid-cols-[450px_1fr] gap-4">
        <CreateMatch
            v-model="showCreateMatchModal"
            :data="detailData"
            :tournament="tournament"
            @updated="handleMatchUpdated"
        />

        <!-- Ranking Modal - Full Screen -->
        <Teleport to="body">
                <Transition name="modal">
                <div
                    v-if="showRankingModal"
                    class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"
                    @click.self="showRankingModal = false"
                >
                    <div
                        class="bg-white dark:bg-[#161F33] rounded-lg w-full h-full overflow-auto p-8"
                    >
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-slate-100">
                                Bảng xếp hạng chi tiết
                            </h2>
                            <button
                                @click="showRankingModal = false"
                                class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                            >
                                <svg
                                    class="w-6 h-6 text-gray-600 dark:text-slate-300"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>

                        <div
                            v-if="!hasAnyRanking"
                            class="py-12 text-center text-gray-500 dark:text-slate-400 text-lg"
                        >
                            Chưa có dữ liệu bảng xếp hạng
                        </div>

                        <div
                            v-else
                            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-6"
                        >
                            <div
                                v-for="group in rank.group_rankings"
                                :key="group.group_id"
                                class="bg-gray-100 dark:bg-[#161F33] rounded-lg shadow-lg overflow-hidden transition-all duration-300"
                                :class="{
                                    'border-2 border-yellow-400 dark:border-yellow-500 ring-2 ring-yellow-300/50 dark:ring-yellow-600/30': group.need_draw_lots === true,
                                    'border border-gray-200 dark:border-slate-700': group.need_draw_lots !== true,
                                }"
                            >
                                <template
                                    v-if="
                                        group.rankings && group.rankings.length
                                    "
                                >
                                    <!-- Table Header -->
                                    <div
                                        class="grid grid-cols-[40px_1fr_70px_70px] bg-gray-200 dark:bg-slate-700 px-4 py-2 text-gray-600 dark:text-slate-200 font-semibold text-sm"
                                        :class="{
                                            'bg-yellow-100 dark:bg-yellow-900/40': group.need_draw_lots === true,
                                        }"
                                    >
                                        <span>#</span>
                                        <span class="flex items-center gap-2">
                                            {{ group.group_name }}
                                            <!-- ✅ Badge: cần bốc thăm -->
                                            <span
                                                v-if="group.need_draw_lots === true"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300 text-[10px] font-bold uppercase animate-pulse"
                                            >
                                                ⚡ Cần bốc thăm
                                            </span>
                                            <!-- ✅ Badge: đang thi đấu -->
                                            <span
                                                v-else-if="group.need_draw_lots === null"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400 text-[10px] font-medium uppercase"
                                            >
                                                Đang thi đấu
                                            </span>
                                        </span>
                                        <span class="text-center">Điểm</span>
                                        <span class="text-center">Hiệu số</span>
                                    </div>

                                    <!-- ✅ Banner: bốc thăm -->
                                    <div
                                        v-if="group.need_draw_lots === true"
                                        class="bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/30 dark:to-orange-900/20 px-3 py-2 text-center"
                                    >
                                        <button
                                            @click="openManualTiebreaker(group)"
                                            class="w-full px-3 py-1.5 text-xs font-semibold rounded bg-[#D72D36] hover:bg-red-700 text-white transition-colors shadow-sm"
                                        >
                                            🎲 Bốc thăm cho bảng này
                                        </button>
                                    </div>

                                    <!-- Teams -->
                                    <div class="divide-y divide-gray-200 dark:divide-slate-700">
                                        <div
                                            v-for="(
                                                team, index
                                            ) in group.rankings"
                                            :key="team.team_id"
                                            class="grid grid-cols-[40px_1fr_70px_70px] items-center px-4 py-3 bg-white dark:bg-[#161F33] hover:bg-blue-50 dark:hover:bg-slate-800 transition-colors duration-200"
                                            :class="{
                                                'bg-yellow-50/60 dark:bg-yellow-900/15': team.pending_tie,
                                            }"
                                        >
                                            <span
                                                class="font-bold text-lg"
                                                :class="{
                                                    'text-yellow-500':
                                                        index === 0,
                                                    'text-gray-400 dark:text-slate-500':
                                                        index === 1,
                                                    'text-orange-500':
                                                        index === 2,
                                                }"
                                                >{{ index + 1 }}</span
                                            >

                                            <div
                                                class="flex items-center gap-2 min-w-0"
                                            >
                                                <img
                                                    :src="
                                                        team.team_avatar ||
                                                        `https://placehold.co/40x40/BBBFCC/3E414C?text=${getTeamInitials(team.team_name)}`
                                                    "
                                                    class="w-10 h-10 rounded-full border-2 border-gray-300 dark:border-slate-600 flex-shrink-0"
                                                />
                                                <p
                                                    class="text-sm font-medium text-gray-800 dark:text-slate-100 truncate"
                                                >
                                                    {{ team.team_name }}
                                                </p>
                                                <!-- ✅ Badge: đồng hạng -->
                                                <span
                                                    v-if="team.pending_tie"
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300 text-[10px] font-semibold flex-shrink-0"
                                                >
                                                    Đồng hạng
                                                </span>
                                            </div>

                                            <span
                                                class="text-center font-bold text-lg text-blue-600 dark:text-blue-400"
                                                >{{ team.points }}</span
                                            >
                                            <span
                                                class="text-center font-semibold"
                                                :class="{
                                                    'text-green-600 dark:text-green-400':
                                                        team.point_diff > 0,
                                                    'text-red-600 dark:text-red-400':
                                                        team.point_diff < 0,
                                                    'text-gray-600 dark:text-slate-400':
                                                        team.point_diff === 0,
                                                }"
                                            >
                                                {{
                                                    team.point_diff > 0
                                                        ? "+"
                                                        : ""
                                                }}{{ team.point_diff }}
                                            </span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Cột bảng xếp hạng - Fixed 450px -->
        <div class="w-[450px]">
            <div class="p-4 space-y-4">
                <!-- Header -->
                <div
                    class="flex justify-between items-center p-4 bg-gray-100 dark:bg-[#161F33] border border-gray-200 dark:border-slate-700 rounded-md"
                >
                    <h2 class="text-lg font-bold text-gray-700 dark:text-slate-100">
                        Bảng xếp hạng
                    </h2>
                    <div class="flex gap-4">
                        <button
                            @click="showRankingModal = true"
                            class="w-9 h-9 rounded-full shadow-lg flex items-center justify-center border border-gray-300 dark:border-slate-600 transition-colors duration-200 hover:bg-gray-200 dark:hover:bg-slate-700 hover:border-gray-400 dark:hover:border-slate-500"
                        >
                            <ArrowsPointingOutIcon
                                class="w-5 h-5 text-gray-500 dark:text-slate-400 transition-colors duration-200 hover:text-gray-700 dark:hover:text-slate-200"
                            />
                        </button>
                        <button
                            class="w-9 h-9 rounded-full shadow-lg flex items-center justify-center border border-gray-300 dark:border-slate-600 transition-colors duration-200 hover:bg-gray-200 dark:hover:bg-slate-700 hover:border-gray-400 dark:hover:border-slate-500"
                        >
                            <PencilIcon
                                class="w-5 h-5 text-gray-500 dark:text-slate-400 transition-colors duration-200 hover:text-gray-700 dark:hover:text-slate-200"
                            />
                        </button>
                    </div>
                </div>

                <!-- Groups -->
                <div
                    v-if="!hasAnyRanking"
                    class="py-2 text-center text-gray-500 dark:text-slate-400"
                >
                    Chưa có dữ liệu bảng xếp hạng
                </div>

                <div v-else>
                    <div
                        v-for="group in rank.group_rankings"
                        :key="group.group_id"
                        class="bg-gray-100 dark:bg-[#161F33] rounded-lg shadow overflow-hidden mb-4 transition-all duration-300"
                        :class="{
                            'border-2 border-yellow-400 dark:border-yellow-500 shadow-yellow-200/50 dark:shadow-yellow-900/30 shadow-lg': group.need_draw_lots === true,
                            'border border-gray-200 dark:border-slate-700': group.need_draw_lots !== true,
                        }"
                    >
                        <template v-if="group.rankings && group.rankings.length">
                            <!-- Group Header -->
                            <div
                                class="grid grid-cols-[20px_1fr_60px_60px] bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-100 px-4 py-2 font-semibold text-sm border-b border-gray-300 dark:border-slate-500"
                                :class="{
                                    'border-b-yellow-300 dark:border-b-yellow-600': group.need_draw_lots === true,
                                }"
                            >
                                <span>#</span>
                                <span class="flex items-center gap-2">
                                    {{ group.group_name }}
                                    <!-- ✅ Badge: group cần bốc thăm (chỉ khi vòng bảng đã kết thúc + need_draw_lots === true) -->
                                    <span
                                        v-if="group.need_draw_lots === true"
                                        class="inline-flex items-center px-1.5 py-0.5 rounded bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300 text-[10px] font-semibold uppercase animate-pulse"
                                    >
                                        ⚡ Cần bốc thăm
                                    </span>
                                    <!-- ✅ Badge: chưa kết thúc vòng bảng -->
                                    <span
                                        v-else-if="group.need_draw_lots === null"
                                        class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400 text-[10px] font-medium uppercase"
                                    >
                                        Đang thi đấu
                                    </span>
                                </span>
                                <span class="text-center">Điểm</span>
                                <span class="text-center">
                                    <span>Hiệu số</span>
                                </span>
                            </div>

                            <!-- ✅ Banner: Bốc thăm (chỉ hiện khi need_draw_lots === true) -->
                            <div
                                v-if="group.need_draw_lots === true"
                                class="bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/30 dark:to-orange-900/20 border-b border-yellow-200 dark:border-yellow-800/50 px-4 py-3"
                            >
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg">🎲</span>
                                        <div>
                                            <p class="text-xs font-semibold text-yellow-800 dark:text-yellow-200">
                                                Bảng "{{ group.group_name }}" cần bốc thăm để chọn đội vào vòng tiếp theo
                                            </p>
                                            <p class="text-[10px] text-yellow-600 dark:text-yellow-400 mt-0.5">
                                                Có {{ group.rankings.filter(t => t.pending_tie).length }} đội đang đồng hạng tại ranh giới đi tiếp
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        @click="openManualTiebreaker(group)"
                                        class="px-3 py-1.5 text-xs font-semibold rounded bg-[#D72D36] hover:bg-red-700 text-white transition-colors shadow-sm"
                                    >
                                        🎲 Mở bốc thăm
                                    </button>
                                </div>
                            </div>

                            <!-- ✅ Toolbar: Đồng hạng không ở ranh giới (pending_tie nhưng need_draw_lots === false) -->
                            <div
                                v-else-if="group.rankings.some(t => t.pending_tie) && group.need_draw_lots === false"
                                class="bg-blue-50 dark:bg-blue-900/20 border-b border-blue-200 dark:border-blue-800/50 px-4 py-2"
                            >
                                <span class="text-xs text-blue-700 dark:text-blue-300">
                                    💡 Có {{ group.rankings.filter(t => t.pending_tie).length }} đội đồng hạng (không ảnh hưởng ranh giới đi tiếp)
                                </span>
                            </div>

                            <!-- Teams -->
                            <div class="divide-y divide-gray-200 dark:divide-slate-700">
                                <div
                                    v-for="(team, index) in group.rankings"
                                    :key="team.team_id"
                                    class="grid grid-cols-[20px_1fr_60px_60px] items-center px-4 py-3 bg-white dark:bg-[#161F33] hover:bg-blue-50 dark:hover:bg-slate-800 transition-colors duration-200"
                                    :class="{
                                        'bg-yellow-50/60 dark:bg-yellow-900/15': team.pending_tie,
                                    }"
                                >
                                    <span
                                        class="font-bold text-lg"
                                        :class="{
                                            'text-yellow-500': index === 0,
                                            'text-gray-400 dark:text-slate-500': index === 1,
                                            'text-orange-500': index === 2,
                                        }"
                                        >{{ index + 1 }}</span>

                                    <div class="flex items-center gap-2">
                                        <img
                                            :src="
                                                team.team_avatar ||
                                                `https://placehold.co/40x40/BBBFCC/3E414C?text=${getTeamInitials(team.team_name)}`
                                            "
                                            class="w-8 h-8 rounded-full border border-gray-300 dark:border-slate-600"
                                        />
                                        <p
                                            class="text-sm font-medium text-gray-800 dark:text-slate-100 max-w-[180px] whitespace-normal break-all"
                                        >
                                            {{ team.team_name }}
                                        </p>
                                        <!-- ✅ Badge: team này đang đồng hạng -->
                                        <span
                                            v-if="team.pending_tie"
                                            class="inline-flex items-center px-1.5 py-0.5 rounded bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300 text-[10px] font-semibold"
                                            title="Đội này đang đồng hạng — cần bốc thăm/kéo-thả"
                                        >
                                            Đồng hạng
                                        </span>
                                    </div>

                                    <span
                                        class="text-center font-bold text-lg text-blue-600 dark:text-blue-400"
                                        >{{ team.points }}</span
                                    >
                                    <span
                                        class="text-center font-semibold"
                                        :class="{
                                            'text-green-600 dark:text-green-400':
                                                team.point_diff > 0,
                                            'text-red-600 dark:text-red-400': team.point_diff < 0,
                                            'text-gray-600 dark:text-slate-400':
                                                team.point_diff === 0,
                                        }"
                                    >
                                        {{ team.point_diff > 0 ? "+" : ""
                                        }}{{ team.point_diff }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cột bracket - Chiếm phần còn lại -->
        <div class="p-4 pt-0">
            <!-- Branch Switcher cho Vòng Tái sinh -->
            <div v-if="hasResurrectionBracket" class="flex gap-2 mb-4 bg-gray-100 dark:bg-[#1E293B] p-1.5 rounded-xl border border-gray-200 dark:border-slate-700 w-fit">
                <button @click="activeBranch = 'main'"
                    :class="['px-4 py-2 rounded-lg font-bold text-xs transition-all cursor-pointer', activeBranch === 'main' ? 'bg-white dark:bg-[#161F33] text-[#D72D36] shadow-sm' : 'text-gray-600 dark:text-slate-300 hover:text-gray-900 dark:hover:text-white']">
                    {{ mainBracketName }}
                </button>
                <button @click="activeBranch = 'sub'"
                    :class="['px-4 py-2 rounded-lg font-bold text-xs transition-all cursor-pointer', activeBranch === 'sub' ? 'bg-white dark:bg-[#161F33] text-[#D72D36] shadow-sm' : 'text-gray-600 dark:text-slate-300 hover:text-gray-900 dark:hover:text-white']">
                    {{ subBracketName }}
                </button>
            </div>

            <div class="overflow-x-auto h-full custom-scrollbar-hide">
                <div class="flex w-max min-h-full pb-4">
                    <!-- POOL STAGE (Chỉ hiển thị ở Nhánh chính) -->
                    <template v-if="activeBranch === 'main'">
                        <div
                            v-for="group in bracket.pool_stage"
                            :key="group.group_id"
                            class="round-column flex flex-col items-center pt-4 min-w-[280px]"
                        >
                            <div
                                :class="roundHeaderClass(group.group_name, true)"
                                class="flex justify-between items-center w-full mb-4 bg-gray-100 dark:bg-[#161F33] border border-gray-200 dark:border-slate-700 p-4"
                            >
                                <h2
                                    class="font-bold text-gray-700 dark:text-slate-100 whitespace-nowrap"
                                >
                                    {{ group.group_name }}
                                </h2>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500 dark:text-slate-400"
                                        >Chưa xác định</span
                                    >
                                    <button
                                        class="w-9 h-9 rounded-full flex items-center justify-center border border-gray-300 dark:border-slate-600 transition-colors duration-200 hover:bg-gray-200 dark:hover:bg-slate-700 hover:border-gray-400 dark:hover:border-slate-500"
                                    >
                                        <PencilIcon
                                            class="w-5 h-5 text-gray-500 dark:text-slate-400 transition-colors duration-200 hover:text-gray-700 dark:hover:text-slate-200"
                                        />
                                    </button>
                                </div>
                            </div>

                            <!-- GỘP LEGS THÀNH 1 CARD -->
                            <div class="flex flex-col w-full items-center">
                                <PoolStageMatchCard
                                    v-for="match in group.matches"
                                    :key="match.match_id"
                                    :match="match"
                                    :is-dragging="isDragging"
                                    :dragged-team="draggedTeam"
                                    :drop-target-match="dropTargetMatch"
                                    :drop-target-position="dropTargetPosition"
                                    @match-click="handleMatchClick"
                                    @drag-start="handleDragStart"
                                    @drag-end="handleDragEnd"
                                    @drag-over="handleDragOver"
                                    @drag-leave="handleDragLeave"
                                    @drop="handleDrop"
                                />
                            </div>
                        </div>
                    </template>

                    <!-- KNOCKOUT STAGE -->
                    <div
                        v-for="roundData in filteredKnockoutStage"
                        :key="roundData.round"
                        class="round-column flex flex-col items-center pt-4 min-w-[280px]"
                    >
                        <div
                            :class="
                                roundHeaderClass(roundData.round_name, false)
                            "
                            class="flex justify-between items-center w-full mb-4 bg-gray-100 dark:bg-[#161F33] border border-gray-200 dark:border-slate-700 p-4"
                        >
                            <h2
                                class="font-bold text-gray-700 dark:text-slate-100 whitespace-nowrap"
                            >
                                {{
                                    roundData.matches.some(
                                        (m) => m.is_third_place == 1,
                                    )
                                        ? "Tranh hạng 3"
                                        : roundData.round_name
                                }}
                            </h2>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-500 dark:text-slate-400"
                                    >Chưa xác định</span
                                >
                                <button
                                    class="w-9 h-9 rounded-full flex items-center justify-center border border-gray-300 dark:border-slate-600 transition-colors duration-200 hover:bg-gray-200 dark:hover:bg-slate-700 hover:border-gray-400 dark:hover:border-slate-500"
                                >
                                    <PencilIcon
                                        class="w-5 h-5 text-gray-500 dark:text-slate-400 transition-colors duration-200 hover:text-gray-700 dark:hover:text-slate-200"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- GỘP LEGS THÀNH 1 CARD -->
                        <div class="flex flex-col w-full">
                            <PoolStageMatchCard
                                v-for="match in roundData.matches"
                                :key="match.match_id"
                                :match="match"
                                :is-dragging="isDragging"
                                :dragged-team="draggedTeam"
                                :drop-target-match="dropTargetMatch"
                                :drop-target-position="dropTargetPosition"
                                :enable-drag-drop="false"
                                @match-click="handleMatchClick"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ✅ Manual Tiebreaker Modal (Bốc thăm thủ công) -->
        <ManualTiebreakerModal
            v-if="tiebreakerContext.groupId && currentTournamentTypeId"
            v-model="showManualTiebreakerModal"
            :tournament-type-id="currentTournamentTypeId"
            :group-id="tiebreakerContext.groupId"
            :group-name="tiebreakerContext.groupName"
            @saved="onTiebreakerSaved"
        />
    </div>
</template>

<script setup>
import { computed, ref, onMounted, nextTick, watch } from "vue";
import {
    ArrowsPointingOutIcon,
    PencilIcon,
} from "@heroicons/vue/24/solid";
import CreateMatch from "@/components/molecules/CreateMatch.vue";
import PoolStageMatchCard from "@/components/molecules/PoolStageMatchCard.vue";
import ManualTiebreakerModal from "@/components/molecules/ManualTiebreakerModal.vue";
import * as MatchesService from "@/service/match.js";
import { toast } from "vue3-toastify";

const props = defineProps({
    bracket: {
        type: Object,
        required: true,
    },
    rank: {
        type: Object,
        required: true,
    },
    tournament: {
        type: Object,
        required: true,
    },
});
const emit = defineEmits(["refresh"]);

const showCreateMatchModal = ref(false);
const detailData = ref({});
const isDragging = ref(false);
const draggedTeam = ref(null);
const dropTargetMatch = ref(null);
const dropTargetPosition = ref(null);
const showRankingModal = ref(false);

// ✅ Manual tiebreaker state
const showManualTiebreakerModal = ref(false);
const tiebreakerContext = ref({ groupId: null, groupName: '', tournamentTypeId: null });

const activeBranch = ref('main');

const hasResurrectionBracket = computed(() => {
    const isTruthy = (val) => val === true || val === 'true' || val === 1 || val === '1';
    const isFalsy = (val) => val === false || val === 'false' || val === 0 || val === '0';

    const tt = props.tournament?.tournament_types?.[0];
    if (tt) {
        const val1 = tt.has_resurrection_bracket;
        const val2 = tt.format_specific_config?.[0]?.has_resurrection_bracket;
        if (isTruthy(val1) || isTruthy(val2)) return true;
        if (isFalsy(val1) || isFalsy(val2)) return false;
    }
    const bVal = props.bracket?.has_resurrection_bracket;
    return isTruthy(bVal);
});

const mainBracketName = computed(() => {
    const tt = props.tournament?.tournament_types?.[0];
    return tt?.main_bracket_name || tt?.format_specific_config?.[0]?.main_bracket_name || props.bracket?.main_bracket_name || 'Giải chính';
});

const subBracketName = computed(() => {
    const tt = props.tournament?.tournament_types?.[0];
    return tt?.sub_bracket_name || tt?.format_specific_config?.[0]?.sub_bracket_name || props.bracket?.sub_bracket_name || 'Giải Tái sinh';
});

const filteredKnockoutStage = computed(() => {
    if (!props.bracket?.knockout_stage) return [];
    if (!hasResurrectionBracket.value) return props.bracket.knockout_stage;

    return props.bracket.knockout_stage.map(roundData => {
        const filteredMatches = (roundData.matches || []).filter(m => {
            const bType = m.bracket_type || 'main';
            return bType === activeBranch.value;
        });
        return {
            ...roundData,
            matches: filteredMatches
        };
    }).filter(roundData => roundData.matches.length > 0);
});

/* ===========================
   DRAG & DROP HANDLERS
=========================== */
const canDragPoolStage = (match) => {
    // Chỉ cho drag khi tất cả legs đều pending (chưa bắt đầu)
    return match.legs?.every((leg) => leg.status === "pending");
};

const handleDragStart = ({ event, match, position }) => {
    if (!canDragPoolStage(match)) {
        event.preventDefault();
        return;
    }

    isDragging.value = true;
    const teamData = position === "home" ? match.home_team : match.away_team;

    draggedTeam.value = {
        matchId: match.match_id,
        position: position,
        teamId: teamData.id,
        teamName: teamData.name,
    };

    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", JSON.stringify(draggedTeam.value));
};

const handleDragEnd = () => {
    isDragging.value = false;
    draggedTeam.value = null;
    dropTargetMatch.value = null;
    dropTargetPosition.value = null;
};

const handleDragOver = ({ event, matchId, position }) => {
    if (!draggedTeam.value) return;

    // Không cho drop vào chính vị trí đang drag
    if (
        draggedTeam.value.matchId === matchId &&
        draggedTeam.value.position === position
    ) {
        event.dataTransfer.dropEffect = "none";
        return;
    }

    event.dataTransfer.dropEffect = "move";
    dropTargetMatch.value = matchId;
    dropTargetPosition.value = position;
};

const hasAnyRanking = computed(() => {
    return props.rank?.group_rankings?.some(
        (g) => g.rankings && g.rankings.length > 0,
    );
});

// ✅ Manual tiebreaker — tổng số team đang đồng hạng để hiển thị badge
const hasPendingTies = computed(() => {
    return props.rank?.group_rankings?.some((g) =>
        g.rankings?.some((t) => t.pending_tie),
    );
});

// ✅ Lấy tournament_type_id từ props.tournament
const currentTournamentTypeId = computed(() => {
    return props.tournament?.tournament_types?.[0]?.id || null;
});

// ✅ Mở modal bốc thăm cho 1 group cụ thể
const openManualTiebreaker = (group) => {
    tiebreakerContext.value = {
        groupId: group.group_id,
        groupName: group.group_name,
        tournamentTypeId: currentTournamentTypeId.value,
    };
    showManualTiebreakerModal.value = true;
};

// ✅ Callback khi lưu xong → refresh rank
const onTiebreakerSaved = () => {
    emit('refresh');
};

const handleDragLeave = ({ event }) => {
    const rect = event.currentTarget.getBoundingClientRect();
    const x = event.clientX;
    const y = event.clientY;

    if (x < rect.left || x >= rect.right || y < rect.top || y >= rect.bottom) {
        dropTargetMatch.value = null;
        dropTargetPosition.value = null;
    }
};

const handleDrop = async ({ event, matchId: targetMatchId, position: targetPosition }) => {
    event.preventDefault();
    event.stopPropagation();

    if (!draggedTeam.value) return;

    if (
        draggedTeam.value.matchId === targetMatchId &&
        draggedTeam.value.position === targetPosition
    ) {
        handleDragEnd();
        return;
    }

    // Tìm trận đích
    const targetMatch = findMatchById(targetMatchId);
    if (!targetMatch) {
        toast.error("Không tìm thấy trận đấu đích");
        handleDragEnd();
        return;
    }

    // Lấy team bị thay thế (to_team)
    const targetTeam =
        targetPosition === "home"
            ? targetMatch.home_team
            : targetMatch.away_team;

    try {
        const payload = {
            from_team_id: draggedTeam.value.teamId,
            to_team_id: targetTeam.id,
        };

        await MatchesService.swapTeams(targetMatchId, payload);
        toast.success("Hoán đổi đội thành công!");
        emit("refresh");
    } catch (error) {
        const errorMsg =
            error.response?.data?.message || "Có lỗi xảy ra khi hoán đổi đội";
        toast.error(errorMsg);
    } finally {
        handleDragEnd();
    }
};

// Helper function
const findMatchById = (matchId) => {
    for (const group of props.bracket.pool_stage) {
        const match = group.matches.find((m) => m.match_id === matchId);
        if (match) return match;
    }
    return null;
};
/* ===========================
   GET DETAIL MATCH
=========================== */
const handleMatchClick = async (matchId) => {
    if (!matchId || isDragging.value) return;

    try {
        const res = await MatchesService.detailMatches(matchId);
        if (res) {
            detailData.value = res;
            showCreateMatchModal.value = true;
        }
    } catch (error) {
        toast.error(
            error.response?.data?.message ||
                "Có lỗi xảy ra khi lấy chi tiết trận đấu",
        );
    }
};

const handleMatchUpdated = () => {
    showCreateMatchModal.value = false;
    emit("refresh");
};

/* ===========================
   COMPUTED PROPERTIES
=========================== */
const poolStages = computed(() => props.bracket.pool_stage || []);
const knockoutStages = computed(() => props.bracket.knockout_stage || []);

const allRoundNames = computed(() => {
    const poolNames = poolStages.value.map((g) => g.group_name);
    const knockoutNames = knockoutStages.value.map((r) => r.round_name);
    return [...poolNames, ...knockoutNames];
});

/* ===========================
   STYLING HELPERS
=========================== */
const roundHeaderClass = (roundName, isPoolStage) => {
    const keys = allRoundNames.value;
    const index = keys.indexOf(roundName);

    if (keys.length === 1) {
        return "rounded-md";
    } else if (index === 0) {
        return "rounded-tl-md rounded-bl-md";
    } else if (index === keys.length - 1) {
        return "rounded-tr-md rounded-br-md border-l border-white dark:border-slate-700";
    }

    return "border-l border-white dark:border-slate-700";
};

/* ===========================
   UTILITY
=========================== */
const getTeamInitials = (name) => {
    if (!name) return "??";
    const parts = name.split(" ");
    if (parts.length > 1) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
};

/* ===========================
   SYNC CARD HEIGHTS
=========================== */
const syncCardHeights = () => {
    nextTick(() => {
        setTimeout(() => {
            const columns = document.querySelectorAll('.round-column');
            if (columns.length === 0) return;

            // Tìm số lượng thẻ tối đa trong các cột
            const maxMatches = Math.max(
                ...Array.from(columns).map(col =>
                    col.querySelectorAll('.match-card').length
                )
            );

            // Với mỗi hàng (index), đồng bộ chiều cao
            for (let i = 0; i < maxMatches; i++) {
                const cardsInRow = Array.from(columns)
                    .map(col => {
                        const cards = col.querySelectorAll('.match-card');
                        return cards[i] || null;
                    })
                    .filter(Boolean);

                if (cardsInRow.length === 0) continue;

                // Tìm chiều cao lớn nhất trong hàng (thẻ có tên dài nhất)
                const maxHeight = Math.max(
                    ...cardsInRow.map(card => card.offsetHeight)
                );

                // Set chiều cao cho tất cả các thẻ trong hàng
                cardsInRow.forEach(card => {
                    card.style.height = `${maxHeight}px`;
                });
            }
        }, 100);
    });
};

// Đồng bộ chiều cao khi component mount
onMounted(() => {
    syncCardHeights();
});

// Đồng bộ chiều cao khi bracket thay đổi
watch(() => props.bracket, () => {
    syncCardHeights();
}, { deep: true, immediate: false });
</script>

<style scoped>
.custom-scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.custom-scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .bg-white,
.modal-leave-active .bg-white {
    transition: transform 0.3s ease;
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white {
    transform: scale(0.9);
}
</style>
