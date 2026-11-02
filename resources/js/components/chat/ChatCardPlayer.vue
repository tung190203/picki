<template>
  <div class="w-full max-w-sm rounded-xl overflow-hidden border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm hover:shadow-md transition p-3.5 flex items-center gap-3">
    <div class="relative flex-shrink-0">
      <img
        :src="card.avatar_url || '/images/default-avatar.png'"
        :alt="card.full_name"
        class="w-14 h-14 rounded-full object-cover border-2 border-indigo-500 shadow-sm"
        @error="$event.target.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(card.full_name || 'User')"
      />
      <div
        v-if="card.rating"
        class="absolute -bottom-1 -right-1 bg-indigo-600 text-white font-extrabold text-[10px] px-1.5 py-0.5 rounded-full shadow"
      >
        {{ card.rating }}
      </div>
    </div>

    <div class="flex-1 min-w-0">
      <h4 class="font-bold text-gray-900 dark:text-white text-sm truncate">
        {{ card.full_name }}
      </h4>
      <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">
        {{ card.rating_text || 'Người chơi Picki' }}
      </p>

      <div class="mt-2 flex gap-2">
        <router-link
          v-if="card.id"
          :to="{ name: 'profile', params: { id: card.id } }"
          @click="chatStore.closeChat()"
          class="text-[11px] font-semibold bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 text-gray-700 dark:text-slate-200 px-2.5 py-1 rounded-md transition"
        >
          Hồ sơ
        </router-link>
        <button
          @click="startDmWithPlayer(card.id)"
          class="text-[11px] font-semibold bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 text-indigo-700 dark:text-indigo-300 px-2.5 py-1 rounded-md transition"
        >
          Nhắn tin
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useChatStore } from "@/store/chat.js";
import { chatService } from "@/service/chat.js";

defineProps({
  card: {
    type: Object,
    required: true,
  },
});

const chatStore = useChatStore();

const startDmWithPlayer = async (userId) => {
  if (!userId) return;
  try {
    const res = await chatService.startDm(userId);
    if (res?.data?.id) {
      chatStore.openChat(res.data.id);
    }
  } catch (err) {
    console.error("startDmWithPlayer error:", err);
  }
};
</script>
