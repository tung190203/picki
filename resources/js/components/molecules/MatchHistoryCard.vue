<template>
  <div
    class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow cursor-pointer"
    @click="$emit('click', match)"
  >
    <!-- Header: type badge + match name + date + win/loss badge -->
    <div class="flex items-start justify-between gap-3 mb-3">
      <div class="flex items-center gap-2 flex-1 min-w-0">
        <!-- Type icon -->
        <div
          class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"
          :class="typeIconBg"
        >
          <TrophyIcon v-if="match.type === 'match'" class="w-4 h-4 text-white" />
          <BoltIcon v-else-if="match.type === 'mini_match'" class="w-4 h-4 text-white" />
          <BoltIcon v-else-if="match.type === 'quick_match'" class="w-4 h-4 text-white" />
          <CommandLineIcon v-else class="w-4 h-4 text-white" />
        </div>

        <!-- Match name + tournament name -->
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <p class="text-sm font-semibold text-gray-800 truncate leading-tight">
              {{ match.match_name || 'Trận đấu' }}
            </p>
            <!-- Format badge -->
            <span
              v-if="match.format"
              class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase flex-shrink-0"
              :class="formatBadgeClass"
            >
              {{ match.format }}
            </span>
          </div>
          <p class="text-xs text-gray-500 truncate mt-0.5">
            {{ tournamentLabel }}
          </p>
        </div>
      </div>

      <div class="flex flex-col items-end gap-1 flex-shrink-0">
        <!-- Win / Loss badge -->
        <span
          class="px-2 py-0.5 rounded text-xs font-semibold"
          :class="match.is_win ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'"
        >
          {{ match.is_win ? 'Thắng' : 'Thua' }}
        </span>
        <!-- Date -->
        <span class="text-xs text-gray-500">{{ formatDate(match.match_date) }}</span>
      </div>
    </div>

    <!-- Teams + Score -->
    <div class="flex items-center justify-between gap-3">
      <!-- Team A / Home -->
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-1.5">
          <div class="flex -space-x-2">
            <img
              v-for="(member, idx) in match.my_team?.members?.slice(0, 2)"
              :key="idx"
              :src="member?.avatar_url || defaultAvatar"
              @error="e => e.target.src = defaultAvatar"
              class="w-8 h-8 rounded-full ring-2 ring-white object-cover"
            />
          </div>
          <span class="text-sm text-gray-800 font-semibold truncate">
            {{ myTeamNames }}
          </span>
        </div>
      </div>

      <!-- Score -->
      <div class="flex flex-col items-center flex-shrink-0 px-2">
        <div class="flex items-center gap-1">
          <span class="text-xl font-bold" :class="match.is_win ? 'text-green-600' : 'text-red-500'">
            {{ totalMyScore }}
          </span>
          <span class="text-gray-400 text-base font-bold">-</span>
          <span class="text-xl font-bold text-gray-600">
            {{ totalOpponentScore }}
          </span>
        </div>
      </div>

      <!-- Team B / Away -->
      <div class="flex-1 min-w-0 text-right">
        <div class="flex items-center justify-end gap-1.5">
          <span class="text-sm text-gray-800 font-semibold truncate">
            {{ opponentTeamNames }}
          </span>
          <div class="flex -space-x-2 space-x-reverse">
            <img
              v-for="(member, idx) in match.opponent_team?.members?.slice(0, 2)"
              :key="idx"
              :src="member?.avatar_url || defaultAvatar"
              @error="e => e.target.src = defaultAvatar"
              class="w-8 h-8 rounded-full ring-2 ring-white object-cover"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Score breakdown (optional, shown when sets exist) -->
    <div v-if="match.scores && match.scores.length > 0" class="mt-3 pt-3 border-t border-gray-100">
      <div class="flex items-center justify-center gap-2">
        <span
          v-for="(score, idx) in match.scores"
          :key="idx"
          class="px-2 py-1 bg-gray-50 rounded text-xs font-medium"
        >
          {{ score.my_score }} - {{ score.opponent_score }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import {
  TrophyIcon,
  BoltIcon,
  CommandLineIcon
} from '@heroicons/vue/24/outline';
import dayjs from 'dayjs';
import 'dayjs/locale/vi';

dayjs.locale('vi');

const props = defineProps({
  match: {
    type: Object,
    required: true,
  },
});

defineEmits(['click']);

const defaultAvatar = '/images/default-avatar.png';

const typeIconBg = computed(() => {
  switch (props.match.type) {
    case 'match':        return 'bg-blue-500';
    case 'mini_match':   return 'bg-purple-500';
    case 'quick_match':  return 'bg-orange-500';
    default:             return 'bg-gray-500';
  }
});

const formatBadgeClass = computed(() => {
  switch (props.match.format) {
    case 'quick':   return 'bg-orange-100 text-orange-700';
    case 'team':    return 'bg-purple-100 text-purple-700';
    case 'single':  return 'bg-green-100 text-green-700';
    default:        return 'bg-gray-100 text-gray-600';
  }
});

const tournamentLabel = computed(() => {
  if (props.match.type === 'match' && props.match.tournament_name) {
    return props.match.tournament_name;
  }
  if (props.match.type === 'mini_match' && props.match.mini_tournament_name) {
    return props.match.mini_tournament_name;
  }
  if (props.match.type === 'quick_match') {
    return 'Trận nhanh';
  }
  return '';
});

const myTeamNames = computed(() => {
  const team = props.match.my_team;
  if (team?.name && team.name !== 'Team A' && team.name !== 'Team B') {
    return team.name;
  }
  const members = team?.members || [];
  return members.map(m => m.full_name).join(', ') || 'Đội của bạn';
});

const opponentTeamNames = computed(() => {
  const team = props.match.opponent_team;
  if (team?.name && team.name !== 'Team A' && team.name !== 'Team B') {
    return team.name;
  }
  const members = team?.members || [];
  return members.map(m => m.full_name).join(', ') || 'Đối thủ';
});

const totalMyScore = computed(() => {
  const scores = props.match.scores || [];
  return scores.reduce((sum, s) => sum + (s.my_score || 0), 0);
});

const totalOpponentScore = computed(() => {
  const scores = props.match.scores || [];
  return scores.reduce((sum, s) => sum + (s.opponent_score || 0), 0);
});

const totalSets = computed(() => {
  return (props.match.scores || []).length;
});

const formatDate = (date) => {
  if (!date) return '';
  return dayjs(date).format('DD/MM/YYYY');
};
</script>
