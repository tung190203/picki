<template>
  <div class="w-full max-w-sm rounded-xl overflow-hidden border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm hover:shadow-md transition p-3.5 flex items-center gap-3">
    <div class="relative flex-shrink-0">
      <img
        :src="card.avatar_url || '/images/default-club.png'"
        :alt="card.name"
        class="w-14 h-14 rounded-xl object-cover border border-gray-200 dark:border-slate-700 shadow-sm"
        @error="$event.target.src = 'https://ui-avatars.com/api/?background=3b82f6&color=fff&name=' + encodeURIComponent(card.name || 'Club')"
      />
    </div>

    <div class="flex-1 min-w-0">
      <h4 class="font-bold text-gray-900 dark:text-white text-sm truncate">
        {{ card.name }}
      </h4>
      <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">
        {{ card.member_count_text || 'Câu lạc bộ Pickleball' }}
      </p>

      <div class="mt-2">
        <router-link
          v-if="card.id && !card.is_deleted"
          :to="{ name: 'club-detail', params: { id: card.id } }"
          @click="chatStore.closeChat()"
          class="inline-block text-[11px] font-semibold bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 text-blue-700 dark:text-blue-300 px-2.5 py-1 rounded-md transition"
        >
          Xem CLB →
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useChatStore } from "@/store/chat.js";

const chatStore = useChatStore();

defineProps({
  card: {
    type: Object,
    required: true,
  },
});
</script>
