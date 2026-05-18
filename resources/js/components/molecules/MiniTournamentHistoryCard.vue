<template>
  <div
    class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow cursor-pointer"
    @click="$emit('click', miniTournament)"
  >
    <!-- Card Header: name + status -->
    <div class="p-4">
      <div class="flex items-start justify-between gap-3 mb-2">
        <!-- Name + meta -->
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-gray-800 leading-tight">
            {{ miniTournament.name || 'Kèo đấu' }}
          </p>
          <div class="flex items-center gap-2 mt-1">
            <span class="text-xs text-gray-400">{{ formatDateTime(miniTournament.start_time) }}</span>
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

      <!-- Location -->
      <div v-if="locationName" class="flex items-center gap-1 mb-3">
        <MapPinIcon class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" />
        <span class="text-xs text-gray-500 truncate">{{ locationName }}</span>
      </div>

      <!-- Type + Rating badge row -->
      <div class="flex items-center gap-2">
        <!-- Type -->
        <span
          v-if="miniTournament.format || miniTournament.play_mode"
          class="px-2 py-0.5 rounded text-[10px] font-semibold"
          :class="typeBadgeClass"
        >
          {{ formatLabel }}
        </span>
        <!-- Rating indicator -->
        <span
          v-if="miniTournament.sport?.id"
          class="px-2 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-600"
        >
          Thi đấu có tính rating
        </span>
        <!-- Creator badge -->
        <span
          v-if="miniTournament.is_creator"
          class="px-2 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-600"
        >
          Người tạo
        </span>
      </div>
    </div>

    <!-- Bottom: participants + stats -->
    <div class="border-t border-gray-100 px-4 py-3">
      <!-- Participant avatars + score -->
      <div class="flex items-center justify-between">
        <!-- Left: participants -->
        <div class="flex items-center gap-2">
          <!-- Avatar stack -->
          <div class="flex -space-x-2">
            <img
              v-for="(p, idx) in displayParticipants.slice(0, 3)"
              :key="idx"
              :src="p?.user?.avatar_url || defaultAvatar"
              @error="e => e.target.src = defaultAvatar"
              class="w-7 h-7 rounded-full ring-2 ring-white object-cover"
            />
            <div
              v-if="extraParticipantCount > 0"
              class="w-7 h-7 rounded-full ring-2 ring-white bg-gray-200 flex items-center justify-center text-[10px] font-semibold text-gray-600"
            >
              +{{ extraParticipantCount }}
            </div>
          </div>
          <span class="text-xs text-gray-500">
            {{ miniTournament.participants?.length || 0 }} người
          </span>
        </div>

        <!-- Right: result -->
        <div v-if="miniTournament.stats" class="flex items-center gap-3">
          <!-- Score if completed -->
          <template v-if="miniTournament.is_completed">
            <div class="flex items-center gap-1">
              <span class="text-xs text-gray-500">Kết quả</span>
              <span class="text-green-600 font-bold text-sm">
                {{ miniTournament.stats.total_win ?? 0 }}
              </span>
              <span class="text-gray-400 text-xs">W</span>
              <span class="text-gray-300">|</span>
              <span class="text-red-500 font-bold text-sm">
                {{ miniTournament.stats.total_lose ?? 0 }}
              </span>
              <span class="text-gray-400 text-xs">L</span>
            </div>
          </template>

          <!-- Rating change if has stats -->
          <div v-if="ratingChange !== null" class="flex items-center gap-1 text-xs">
            <span class="text-gray-400">
              <PlusCircleIcon v-if="ratingChange > 0" class="w-3.5 h-3.5 inline text-green-500" />
              <MinusCircleIcon v-else-if="ratingChange < 0" class="w-3.5 h-3.5 inline text-red-500" />
              <span :class="ratingChange > 0 ? 'text-green-500 font-semibold' : ratingChange < 0 ? 'text-red-500 font-semibold' : 'text-gray-400'">
                {{ ratingChange > 0 ? '+' : '' }}{{ ratingChange }}
              </span>
              <span class="text-gray-400 ml-0.5">điểm</span>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { MapPinIcon, PlusCircleIcon, MinusCircleIcon } from '@heroicons/vue/24/outline';
import dayjs from 'dayjs';
import 'dayjs/locale/vi';
import relativeTime from 'dayjs/plugin/relativeTime.js';

dayjs.locale('vi');
dayjs.extend(relativeTime);

const props = defineProps({
  miniTournament: {
    type: Object,
    required: true,
  },
});

defineEmits(['click']);

const defaultAvatar = '/images/default-avatar.png';

const locationName = computed(() => {
  return props.miniTournament.competition_location?.name
    || props.miniTournament.club?.name
    || '';
});

const displayParticipants = computed(() => {
  const participants = props.miniTournament.participants || [];
  return participants.filter(p => p.is_confirmed);
});

const extraParticipantCount = computed(() => {
  const total = displayParticipants.value.length;
  return total > 3 ? total - 3 : 0;
});

const formatLabel = computed(() => {
  const fmt = props.miniTournament.format;
  const mode = props.miniTournament.play_mode;
  if (fmt === 'single' || mode === 'single') return 'Đấu đơn';
  if (fmt === 'double' || mode === 'double') return 'Đấu đôi';
  if (fmt === 'mixed') return 'Mixed';
  return '';
});

const typeBadgeClass = computed(() => {
  const fmt = props.miniTournament.format;
  if (fmt === 'single') return 'bg-green-100 text-green-700';
  if (fmt === 'double') return 'bg-purple-100 text-purple-700';
  if (fmt === 'mixed') return 'bg-blue-100 text-blue-700';
  return 'bg-gray-100 text-gray-600';
});

const statusText = computed(() => {
  const s = props.miniTournament.status;
  if (props.miniTournament.is_completed) return 'Hoàn thành';
  if (s === 0) return 'Chưa bắt đầu';
  if (s === 1) return 'Đang đăng ký';
  if (s === 2) return 'Đang diễn ra';
  if (s === 3) return 'Hoàn thành';
  if (s === 4) return 'Đã hủy';
  return props.miniTournament.status_text || '';
});

const statusBadgeClass = computed(() => {
  const s = props.miniTournament.status;
  if (props.miniTournament.is_completed) return 'bg-green-100 text-green-700';
  if (s === 0) return 'bg-gray-100 text-gray-500';
  if (s === 1) return 'bg-blue-100 text-blue-700';
  if (s === 2) return 'bg-orange-100 text-orange-700';
  if (s === 4) return 'bg-red-100 text-red-600';
  return 'bg-gray-100 text-gray-500';
});

const ratingChange = computed(() => {
  const stats = props.miniTournament.stats;
  if (!stats) return null;
  if (stats.rating_after !== null && stats.rating_before !== null) {
    return stats.rating_after - stats.rating_before;
  }
  return null;
});

const formatDateTime = (date) => {
  if (!date) return '';
  return dayjs(date).format('DD/MM/YYYY - HH:mm');
};
</script>
