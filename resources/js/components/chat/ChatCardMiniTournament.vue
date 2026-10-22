<template>
  <div class="w-full max-w-sm rounded-xl overflow-hidden border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm hover:shadow-md transition">
    <div class="relative h-28 bg-gradient-to-r from-emerald-500 to-teal-600 flex items-center justify-center overflow-hidden">
      <img
        v-if="card.poster_url"
        :src="formatImageUrl(card.poster_url)"
        :alt="card.title"
        class="w-full h-full object-cover"
      />
      <div v-else class="text-white text-center px-4 flex flex-col items-center justify-center">
        <TrophyIcon class="w-8 h-8 opacity-90 mb-1" />
        <div class="text-xs font-semibold tracking-wide uppercase opacity-90">Kèo Pickleball</div>
      </div>
      <div class="absolute top-2 right-2 bg-black/60 backdrop-blur-sm text-white text-[11px] font-medium px-2 py-0.5 rounded-full">
        {{ card.status_text || 'Kèo đấu' }}
      </div>
    </div>

    <div class="p-3.5 space-y-2">
      <h4 class="font-bold text-gray-900 dark:text-white text-sm line-clamp-1">
        {{ card.title }}
      </h4>

      <div class="space-y-1 text-xs text-gray-600 dark:text-slate-300">
        <div class="flex items-center gap-1.5">
          <CalendarDaysIcon class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" />
          <span class="truncate">{{ formatTime(card.start_time) }}</span>
        </div>

        <div class="flex items-center gap-1.5">
          <MapPinIcon class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" />
          <span class="truncate">{{ card.location_name || 'Chưa xác định sân' }}</span>
        </div>

        <div class="flex items-center gap-1.5 font-medium text-emerald-600 dark:text-emerald-400">
          <UserGroupIcon class="w-3.5 h-3.5 flex-shrink-0" />
          <span>{{ card.slots_text || `${card.confirmed_count || 0} người tham gia` }}</span>
        </div>
      </div>

      <router-link
        v-if="card.id && !card.is_deleted"
        :to="{ name: 'mini-tournament-detail', params: { id: card.id } }"
        @click="chatStore.closeChat()"
        class="block w-full text-center bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 font-semibold text-xs py-2 rounded-lg transition"
      >
        Xem chi tiết kèo →
      </router-link>
      <div v-else class="text-xs text-red-500 italic text-center py-1">
        Kèo đấu đã bị xóa hoặc không còn khả dụng
      </div>
    </div>
  </div>
</template>

<script setup>
import { TrophyIcon, CalendarDaysIcon, MapPinIcon, UserGroupIcon } from "@heroicons/vue/24/solid";
import { useChatStore } from "@/store/chat.js";

const chatStore = useChatStore();

defineProps({
  card: {
    type: Object,
    required: true,
  },
});

const formatTime = (timeStr) => {
  if (!timeStr) return "Chưa xếp lịch";
  try {
    const d = new Date(timeStr);
    return d.toLocaleString("vi-VN", {
      hour: "2-digit",
      minute: "2-digit",
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  } catch {
    return timeStr;
  }
};

const formatImageUrl = (url) => {
  if (!url) return "";
  if (url.startsWith("http://") || url.startsWith("https://") || url.startsWith("/storage/")) {
    return url;
  }
  return `/storage/${url.replace(/^\/+/, "")}`;
};
</script>
