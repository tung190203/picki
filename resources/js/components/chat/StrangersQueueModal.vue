<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in"
    @click.self="$emit('close')"
  >
    <div class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">
      <!-- Header -->
      <div class="p-4 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-600 flex items-center justify-center shadow-xs">
            <UserIcon class="w-4 h-4" />
          </div>
          <div>
            <h3 class="font-bold text-gray-900 dark:text-white text-base">
              Tin nhắn từ người lạ
            </h3>
            <p class="text-[11px] text-gray-500 dark:text-slate-400">
              Chưa từng cùng CLB hoặc đấu chung kèo
            </p>
          </div>
        </div>
        <button
          @click="$emit('close')"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded-lg transition"
          title="Đóng"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>

      <!-- Banner -->
      <div class="p-3 bg-amber-50/70 dark:bg-amber-950/30 border-b border-amber-100 dark:border-amber-900/40 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
        <LightBulbIcon class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
        <span class="leading-relaxed">
          Người lạ chỉ được gửi tối đa 10 tin nhắn/ngày. Khi bạn trả lời, cuộc trò chuyện sẽ tự động chuyển vĩnh viễn sang hộp thư chính.
        </span>
      </div>

      <!-- List -->
      <div class="flex-1 overflow-y-auto p-3 space-y-2">
        <div v-if="loading" class="text-center py-10 text-gray-400 text-xs">
          Đang tải danh sách người lạ...
        </div>

        <div v-else-if="strangers.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-400 space-y-2">
          <ChatBubbleOvalLeftEllipsisIcon class="w-12 h-12 stroke-[1.5] text-gray-300 dark:text-slate-600" />
          <p class="text-xs">Không có tin nhắn nào từ người lạ</p>
        </div>

        <div
          v-for="item in strangers"
          :key="item.id"
          @click="openConversation(item.id)"
          class="flex items-center justify-between p-3 rounded-xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-800/60 hover:bg-gray-50 dark:hover:bg-slate-800 cursor-pointer transition shadow-sm"
        >
          <div class="flex items-center gap-3 min-w-0 pr-2">
            <div class="relative flex-shrink-0">
              <img
                :src="item.avatar_url || '/images/default-avatar.png'"
                class="w-11 h-11 rounded-full object-cover border border-gray-200 dark:border-slate-700"
                @error="$event.target.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(item.title || 'User')"
              />
              <span
                v-if="item.unread_count > 0"
                class="absolute -top-1 -right-1 bg-amber-500 text-white font-extrabold text-[10px] w-4 h-4 rounded-full flex items-center justify-center shadow"
              >
                {{ item.unread_count }}
              </span>
            </div>

            <div class="min-w-0">
              <div class="flex items-center gap-1.5">
                <h5 class="font-bold text-gray-900 dark:text-white text-xs truncate">
                  {{ item.title }}
                </h5>
                <span class="bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 text-[10px] font-medium px-1.5 py-0.2 rounded">
                  {{ item.context_tag || 'Người lạ' }}
                </span>
              </div>
              <p class="text-[11px] text-gray-500 dark:text-slate-400 truncate mt-0.5">
                {{ item.last_message?.content || 'Đã gửi một tin nhắn' }}
              </p>
            </div>
          </div>

          <span class="text-[10px] text-gray-400 whitespace-nowrap flex-shrink-0">
            {{ formatTime(item.last_message_at || item.updated_at) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from "vue";
import { UserIcon, LightBulbIcon, XMarkIcon } from "@heroicons/vue/24/solid";
import { ChatBubbleOvalLeftEllipsisIcon } from "@heroicons/vue/24/outline";
import { inboxService } from "@/service/chat.js";
import { useChatStore } from "@/store/chat.js";

const props = defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(["close"]);

const chatStore = useChatStore();
const strangers = ref([]);
const loading = ref(false);

const loadStrangers = async () => {
  try {
    loading.value = true;
    const res = await inboxService.getStrangers({ per_page: 50 });
    strangers.value = res.data?.items || [];
  } catch (err) {
    console.error("loadStrangers error:", err);
  } finally {
    loading.value = false;
  }
};

const openConversation = (convId) => {
  emit("close");
  chatStore.openChat(convId);
};

const formatTime = (timeStr) => {
  if (!timeStr) return "";
  try {
    const d = new Date(timeStr);
    return d.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" });
  } catch {
    return "";
  }
};

const handleStrangerReplied = (e) => {
  const convId = e.detail?.conversationId;
  if (convId) {
    strangers.value = strangers.value.filter((s) => s.id !== convId);
  }
};

onMounted(() => {
  window.addEventListener("chat:replied-stranger", handleStrangerReplied);
});

onBeforeUnmount(() => {
  window.removeEventListener("chat:replied-stranger", handleStrangerReplied);
});

watch(
  () => props.isOpen,
  (val) => {
    if (val) {
      loadStrangers();
    }
  }
);
</script>
