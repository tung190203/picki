<template>
  <div class="p-4 pt-0 min-h-screen overflow-x-auto">
    <CreateMatch v-model="showCreateMatchModal" :data="detailData" :tournament="tournament"
      @updated="handleMatchUpdated" />

    <!-- HEADER CÁC ROUND -->
    <div class="flex w-max min-h-full pb-4">

      <div v-for="(round, roundIndex) in bracket.bracket" :key="roundIndex"
        class="flex flex-col min-w-[320px] relative">
        <div :class="[
          'flex justify-between items-center w-full mb-6 bg-gray-100 dark:bg-[#1E293B] p-4 sticky top-0 z-10 rounded-md',
          roundIndex === 0
            ? 'rounded-l-md'
            : roundIndex === totalRounds - 1
              ? 'rounded-r-md border-l border-white dark:border-slate-700'
              : 'border-l border-white dark:border-slate-700',
        ]">
          <h2 class="font-bold text-gray-700 dark:text-slate-100 whitespace-nowrap">
            {{ round.round_name }}
          </h2>

          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 dark:text-slate-400">
              {{ formatRoundDate(round.date) }}
            </span>

            <button
              class="w-9 h-9 rounded-full flex items-center justify-center border border-gray-300 dark:border-slate-600 hover:bg-gray-200 dark:hover:bg-slate-700 hover:border-gray-400 dark:hover:border-slate-500 transition-colors">
              <PencilIcon class="w-5 h-5 text-gray-500 dark:text-slate-400" />
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- BRACKET -->
    <Bracket :rounds="rounds">
      <template #player="{ player }">
        <div v-if="player.isPlayer1"
          class="w-64 rounded-lg shadow-md border bg-gray-100 dark:bg-[#1E293B] relative cursor-pointer hover:shadow-lg transition-all"
          :class="playerWrapperClass(player)" @click="!isDragging ? handleMatchClick(player.matchId) : null">      
          <div class="flex justify-between items-center text-xs font-medium rounded-t-[7px] rounded-b-[7px] px-4 py-2"
            :class="headerClass(player)">
            <span class="uppercase">{{ player.label || 'SÂN 1' }}</span>
            <div class="flex items-center gap-2">
              <span v-if="player.status === 'pending' || hasScoreInSets(player)"
                class="text-white font-bold text-xs flex items-center px-2 py-0.5">
                <VideoCameraIcon class="w-3 h-3 mr-1" />
                Trực tiếp
              </span>
              <span class="text-xs" v-else>
                {{ player.time ? formatTime(player.time) : "Chưa xác định" }}
              </span>
            </div>
          </div>

          <!-- TEAMS -->
          <div class="px-4 space-y-1 bg-gray-100 dark:bg-[#1E293B] rounded-br-[7px] rounded-bl-[7px] rounded-tl-[7px] rounded-tr-[7px]">

            <!-- HOME TEAM -->
            <div v-tooltip="player.name" class="space-y-1 rounded transition-all px-2 -mx-2" :class="{
              'bg-blue-100 dark:bg-blue-900/40 ring-2 ring-blue-400 dark:ring-blue-500': isDropTarget(player.matchId, 'home'),
              'cursor-move hover:bg-gray-200 dark:hover:bg-slate-700': canDrag(player),
              'cursor-not-allowed': !canDrag(player)
            }" 
            :draggable="canDrag(player) ? 'true' : 'false'"
            @dragstart="handleDragStart($event, player, 'home')" 
            @dragend="handleDragEnd"
            @dragover.prevent="handleDragOver($event, player.matchId, 'home')" 
            @dragleave="handleDragLeave($event)"
            @drop.prevent.stop="handleDrop($event, player.matchId, 'home')">
              <div class="flex justify-between items-center pointer-events-none">
                <div class="flex items-center gap-2">
                  <img :src="player.logo || placeholderFor(player.name)" class="w-8 h-8 rounded-full" />
                  <p class="text-sm font-semibold text-gray-700 dark:text-slate-100 truncate max-w-[150px]">{{ player.name }}</p>
                </div>

                <span class="font-bold text-lg" :class="player.winnerId
                    ? (player.id === player.winnerId
                      ? 'text-green-600 dark:text-green-400'
                      : 'text-red-600 dark:text-red-400')
                    : 'text-gray-700 dark:text-slate-100'
                  ">
                  {{ player.score ?? 0 }}
                </span>
              </div>
            </div>

            <!-- AWAY TEAM -->
            <div v-tooltip="player.opponent.name" class="space-y-1 rounded transition-all px-2 -mx-2" :class="{
              'bg-blue-100 dark:bg-blue-900/40 ring-2 ring-blue-400 dark:ring-blue-500': isDropTarget(player.matchId, 'away'),
              'cursor-move hover:bg-gray-200 dark:hover:bg-slate-700': canDrag(player),
              'cursor-not-allowed': !canDrag(player)
            }" 
            :draggable="canDrag(player) ? 'true' : 'false'"
            @dragstart="handleDragStart($event, player, 'away')" 
            @dragend="handleDragEnd"
            @dragover.prevent="handleDragOver($event, player.matchId, 'away')" 
            @dragleave="handleDragLeave($event)"
            @drop.prevent.stop="handleDrop($event, player.matchId, 'away')">
              
              <div class="flex justify-between items-center pointer-events-none">
                <div class="flex items-center gap-2">
                  <img :src="player.opponent.logo || placeholderFor(player.opponent.name)"
                    class="w-8 h-8 rounded-full" />
                  <p class="text-sm font-semibold text-gray-700 dark:text-slate-100 truncate max-w-[150px]">{{ player.opponent.name }}</p>
                </div>

                <span class="font-bold text-lg" :class="player.winnerId
                    ? (player.opponent.id === player.winnerId
                      ? 'text-green-600 dark:text-green-400'
                      : 'text-red-600 dark:text-red-400')
                    : 'text-gray-700 dark:text-slate-100'
                  ">
                  {{ player.opponent.score ?? 0 }}
                </span>

              </div>
            </div>

          </div>
        </div>
      </template>
    </Bracket>

  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import Bracket from "vue-tournament-bracket";
import { VideoCameraIcon, PencilIcon } from "@heroicons/vue/24/solid";
import CreateMatch from '@/components/molecules/CreateMatch.vue';
import * as MatchesService from '@/service/match.js';
import { toast } from 'vue3-toastify';

const props = defineProps({
  bracket: { type: Object, required: true },
  tournament: { type: Object, required: true },
});
const emit = defineEmits(['refresh']);
const showCreateMatchModal = ref(false);
const detailData = ref({});
const isDragging = ref(false);
const draggedTeam = ref(null);
const dropTargetMatch = ref(null);
const dropTargetPosition = ref(null);

const totalRounds = computed(() => props.bracket.bracket.length);

/* ===========================
   CHECK IF CAN DRAG
=========================== */
const canDrag = (player) => {
  // Chỉ cho phép kéo ở round 1, status = pending và chưa có kết quả (score)
  return player.roundNumber === 1 && 
         player.status === 'pending' && 
         !hasScoreInSets(player);
};

// Helper: Kiểm tra match có thể drag/drop không (dùng cho validation)
const canDragMatch = (match) => {
  const displayLeg = getDisplayLeg(match.legs);
  if (!displayLeg) return false;
  
  // Lấy sets từ displayLeg
  const homeSets = displayLeg.sets ? getTeamSets(displayLeg.sets, match.home_team.id) : [];
  const awaySets = displayLeg.sets ? getTeamSets(displayLeg.sets, match.away_team.id) : [];
  const allSets = [...homeSets, ...awaySets];
  
  // Kiểm tra có score nào khác 0 không
  const hasScore = allSets.some(score => score !== 0);
  
  return displayLeg.status === 'pending' && !hasScore;
};

// Helper: Tìm match theo ID trong rounds
const findMatchById = (matchId) => {
  for (const round of props.bracket.bracket) {
    const match = round.matches.find(m => m.match_id === matchId);
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
    toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy chi tiết trận đấu');
  }
};

/* ===========================
   DRAG & DROP HANDLERS
=========================== */
const handleDragStart = (event, player, position) => {
  // Kiểm tra lại lần nữa để đảm bảo
  if (!canDrag(player)) {
    event.preventDefault();
    return;
  }

  isDragging.value = true;
  draggedTeam.value = {
    matchId: player.matchId,
    position: position, // 'home' hoặc 'away'
    teamId: position === 'home' ? player.id : player.opponent.id,
    teamName: position === 'home' ? player.name : player.opponent.name,
  };

  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', JSON.stringify(draggedTeam.value));
};

const handleDragEnd = () => {
  isDragging.value = false;
  draggedTeam.value = null;
  dropTargetMatch.value = null;
  dropTargetPosition.value = null;
};

const handleDragOver = (event, matchId, position) => {
  if (!draggedTeam.value) return;

  // Không cho drop vào chính vị trí đang drag
  if (draggedTeam.value.matchId === matchId && draggedTeam.value.position === position) {
    event.dataTransfer.dropEffect = 'none';
    return;
  }

  // 🚫 Không cho swap trong cùng 1 trận (home ↔ away)
  if (draggedTeam.value.matchId === matchId) {
    event.dataTransfer.dropEffect = 'none';
    return;
  }

  // Kiểm tra trận đích có thể drop không
  const targetMatch = findMatchById(matchId);
  if (!targetMatch || !canDragMatch(targetMatch)) {
    event.dataTransfer.dropEffect = 'none';
    return;
  }

  event.dataTransfer.dropEffect = 'move';
  dropTargetMatch.value = matchId;
  dropTargetPosition.value = position;
};

const handleDragLeave = (event) => {
  const rect = event.currentTarget.getBoundingClientRect();
  const x = event.clientX;
  const y = event.clientY;

  if (x < rect.left || x >= rect.right || y < rect.top || y >= rect.bottom) {
    dropTargetMatch.value = null;
    dropTargetPosition.value = null;
  }
};

const hasScoreInSets = (player) => {
  if (!player.sets) return false;
  return player.sets.some(score => score !== 0);
};

const headerClass = (player) => {
  if (player.status === 'pending' && hasScoreInSets(player)) {
    return 'bg-amber-500 text-white';
  } else if (player.status === 'completed') {
    return 'bg-green-500 text-white';
  } else {
    return 'bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-100';
  }
};

const playerWrapperClass = (player) => {
  let classes = [];

  // Opacity khi drag
  if (isDragging.value && draggedTeam.value?.matchId === player.matchId) {
    classes.push('opacity-50');
  }

  // Trận tranh hạng 3
  if (player.isThirdPlace) {
    classes.push('bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-100');
  } else if (player.status === 'pending' && hasScoreInSets(player)) {
    // Pending + có score → vàng
    classes.push('border border-amber-500 bg-amber-500 text-white');
  } else if (player.status === 'completed') {
    // Completed → xanh
    classes.push('border border-green-500 bg-green-500 text-white');
  } else {
    // Pending chưa score / default
    classes.push('bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300');
  }

  return classes.join(' ');
};


const handleDrop = async (event, targetMatchId, targetPosition) => {
  event.preventDefault();
  event.stopPropagation();

  if (!draggedTeam.value) return;

  // Không cho drop vào chính vị trí đang drag
  if (draggedTeam.value.matchId === targetMatchId && draggedTeam.value.position === targetPosition) {
    handleDragEnd();
    return;
  }

  // 🚫 Không cho swap trong cùng 1 trận
  if (draggedTeam.value.matchId === targetMatchId) {
    toast.error('Không thể hoán đổi đội trong cùng 1 trận');
    handleDragEnd();
    return;
  }

  // Kiểm tra trận đích có thể drop không
  const targetMatch = findMatchById(targetMatchId);
  if (!targetMatch || !canDragMatch(targetMatch)) {
    toast.error('Không thể hoán đổi vào trận đã có kết quả');
    handleDragEnd();
    return;
  }

  try {
    // Gọi API swap teams
    const payload = {};
    if (targetPosition === 'home') {
      payload.home_team_id = draggedTeam.value.teamId;
    } else {
      payload.away_team_id = draggedTeam.value.teamId;
    }

    const res = await MatchesService.swapTeams(targetMatchId, payload);

    if (res) {
      toast.success('Hoán đổi đội thành công!');
      // Refresh bracket data
      emit('refresh');
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi hoán đổi đội');
  } finally {
    handleDragEnd();
  }
};

const handleMatchUpdated = () => {
  showCreateMatchModal.value = false;
  emit('refresh'); // Refresh bracket data
};

const isDropTarget = (matchId, position) => {
  return dropTargetMatch.value === matchId && dropTargetPosition.value === position;
};
const getDisplayLeg = (legs) => {
  if (!Array.isArray(legs) || !legs.length) return null;

  // Ưu tiên leg đang diễn ra
  const inProgress = legs.find(l => l.status === 'pending');
  return inProgress ?? legs[legs.length - 1];
};

/* ===========================
   FORMAT ROUNDS FOR BRACKET
=========================== */
const rounds = computed(() => {
  return props.bracket.bracket.map((round, roundIndex) => ({
    games: round.matches.map((match) => {
      const displayLeg = getDisplayLeg(match.legs);

      return {
        player1: {
          id: match.home_team.id,
          name: match.home_team.name,
          logo: match.home_team.team_avatar,
          score: match.aggregate_score?.home ?? 0,

          // ✅ SETS LẤY THEO LEG ĐƯỢC CHỌN
          sets: displayLeg?.sets
            ? getTeamSets(displayLeg.sets, match.home_team.id)
            : [],

          winnerId: match.winner_team_id,
          status: displayLeg?.status,
          isThirdPlace: !!match.is_third_place,
          label: match.match_label,
          time: displayLeg?.scheduled_at,

          isPlayer1: true,
          matchId: match.match_id,
          roundNumber: roundIndex + 1,

          opponent: {
            id: match.away_team.id,
            name: match.away_team.name,
            logo: match.away_team.team_avatar,
            score: match.aggregate_score?.away ?? 0,

            // ✅ SETS LẤY THEO LEG ĐƯỢC CHỌN
            sets: displayLeg?.sets
              ? getTeamSets(displayLeg.sets, match.away_team.id)
              : [],
          }
        },
        player2: { isPlayer1: false },
      };
    }),
  }));
});

/* ================================
   UTILS
================================ */
const getTeamSets = (sets, teamId) =>
  Object.values(sets).map((s) => s.find((r) => r.team_id === teamId)?.score ?? 0);

const formatTime = (t) => new Date(t).toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" });

const formatRoundDate = (d) => {
  if (!d) return "Chưa xác định";

  const date = new Date(d);
  if (isNaN(date.getTime())) return "Chưa xác định";

  const day = date.toLocaleDateString("vi-VN", {
    day: "2-digit",
    month: "short",
  });

  const time = date.toLocaleTimeString("vi-VN", {
    hour: "2-digit",
    minute: "2-digit",
  });

  return `${day} - ${time}`;
};

const placeholderFor = (name) => {
  const parts = name?.split(" ") || [];
  const initials =
    parts.length > 1 ? parts[0][0] + parts.pop()[0] : name?.slice(0, 2) || "??";
  return `https://placehold.co/40x40/BBBFCC/3E414C?text=${initials.toUpperCase()}`;
};
</script>

<style scoped>
.overflow-x-auto::-webkit-scrollbar {
  height: 0px;
}
</style>