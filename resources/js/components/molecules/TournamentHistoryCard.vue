<template>
  <div
    class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow cursor-pointer"
    @click="$emit('click', tournament)"
  >
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 mb-3">
      <div class="flex items-center gap-2 flex-1 min-w-0">
        <!-- Icon -->
        <div class="w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center flex-shrink-0">
          <TrophyIcon class="w-4 h-4 text-white" />
        </div>

        <!-- Name + Date -->
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-gray-800 truncate leading-tight">
            {{ tournament.name || 'Giải đấu' }}
          </p>
          <p class="text-xs text-gray-500 mt-0.5">
            {{ formatDate(tournament.start_date) }}
            <template v-if="tournament.end_date"> - {{ formatDate(tournament.end_date) }}</template>
          </p>
        </div>
      </div>

      <!-- Status badge -->
      <span
        class="px-2 py-0.5 rounded text-xs font-semibold flex-shrink-0"
        :class="statusBadgeClass"
      >
        {{ statusText }}
      </span>
    </div>

    <!-- Stats row -->
    <div class="flex items-center gap-3">
      <!-- Win/Loss -->
      <div class="flex-1 flex items-center gap-3">
        <div v-if="tournament.stats" class="flex items-center gap-1">
          <span class="text-green-600 font-bold text-base">{{ tournament.stats.total_win ?? 0 }}</span>
          <span class="text-gray-400 text-sm">W</span>
        </div>
        <div v-if="tournament.stats" class="flex items-center gap-1">
          <span class="text-red-500 font-bold text-base">{{ tournament.stats.total_lose ?? 0 }}</span>
          <span class="text-gray-400 text-sm">L</span>
        </div>
      </div>

      <!-- Rank / Round -->
      <div class="text-right">
        <div v-if="tournament.is_completed && tournament.stats?.tournament_rank" class="text-xs text-gray-500">
          Hạng #{{ tournament.stats.tournament_rank }}
        </div>
        <div v-else-if="!tournament.is_completed && tournament.stats?.current_round" class="text-xs text-gray-500">
          {{ tournament.stats.current_round }}
        </div>
        <div v-else-if="!tournament.is_completed && tournament.stats?.final_round" class="text-xs text-gray-500">
          {{ tournament.stats.final_round }}
        </div>
      </div>
    </div>

    <!-- Rating -->
    <div v-if="tournament.stats?.current_rating" class="mt-2 pt-2 border-t border-gray-100 flex items-center gap-3">
      <div class="flex items-center gap-1 text-xs">
        <span class="text-gray-500">Rating:</span>
        <span class="font-semibold text-gray-800">{{ tournament.stats.current_rating }}</span>
      </div>
      <div v-if="tournament.stats?.current_rank" class="flex items-center gap-1 text-xs">
        <span class="text-gray-500">Rank:</span>
        <span class="font-semibold text-gray-800">#{{ tournament.stats.current_rank }}</span>
      </div>
      <div v-if="tournament.stats?.rank_change !== null && tournament.stats?.rank_change !== undefined" class="flex items-center gap-1 text-xs">
        <span class="text-gray-400">
          <ArrowUpIcon v-if="tournament.stats.rank_change > 0" class="w-3 h-3 inline text-green-500" />
          <ArrowDownIcon v-else-if="tournament.stats.rank_change < 0" class="w-3 h-3 inline text-red-500" />
          <span :class="tournament.stats.rank_change > 0 ? 'text-green-500' : tournament.stats.rank_change < 0 ? 'text-red-500' : 'text-gray-400'">
            {{ tournament.stats.rank_change > 0 ? '+' : '' }}{{ tournament.stats.rank_change }}
          </span>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { TrophyIcon, ArrowUpIcon, ArrowDownIcon } from '@heroicons/vue/24/outline';
import dayjs from 'dayjs';
import 'dayjs/locale/vi';

dayjs.locale('vi');

const props = defineProps({
  tournament: {
    type: Object,
    required: true,
  },
});

defineEmits(['click']);

const statusText = computed(() => {
  const s = props.tournament.status;
  if (props.tournament.is_completed) return 'Hoàn thành';
  if (s === 0 || s === 'upcoming') return 'Sắp tới';
  if (s === 2 || s === 'ongoing') return 'Đang diễn ra';
  if (s === 4 || s === 'canceled') return 'Đã hủy';
  return 'Đang diễn ra';
});

const statusBadgeClass = computed(() => {
  const s = props.tournament.status;
  if (props.tournament.is_completed) return 'bg-green-100 text-green-700';
  if (s === 0 || s === 'upcoming') return 'bg-blue-100 text-blue-700';
  if (s === 2 || s === 'ongoing') return 'bg-orange-100 text-orange-700';
  if (s === 4 || s === 'canceled') return 'bg-gray-100 text-gray-500';
  return 'bg-orange-100 text-orange-700';
});

const formatDate = (date) => {
  if (!date) return '';
  return dayjs(date).format('DD/MM/YYYY');
};
</script>
