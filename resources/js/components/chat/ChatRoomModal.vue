<template>
  <!-- Messenger-Style Floating Chat Box Docked at Bottom-Right Corner -->
  <div
    v-if="chatStore.isChatOpen && conversation"
    id="messenger-chat-box"
    class="fixed bottom-0 right-3 sm:right-6 md:right-20 z-50 w-[350px] sm:w-[360px] max-w-[calc(100vw-20px)] bg-white dark:bg-[#0F172A] border-t border-x border-gray-200 dark:border-slate-700/80 rounded-t-2xl shadow-2xl flex flex-col overflow-hidden transition-all duration-300 ease-in-out font-sans"
    :class="isMinimized ? 'h-[52px] shadow-lg ring-1 ring-gray-200 dark:ring-slate-700' : 'h-[500px] sm:h-[520px] max-h-[82vh] ring-1 ring-black/5'"
  >
    <!-- Chat Header (Facebook Messenger style) -->
    <div
      class="px-3 py-2.5 bg-white dark:bg-slate-900 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between shadow-sm cursor-pointer select-none hover:bg-gray-50/80 dark:hover:bg-slate-800/60 transition flex-shrink-0"
      @click="expandChat"
    >
      <div class="flex items-center gap-2.5 min-w-0">
        <div class="relative flex-shrink-0">
          <img
            :src="conversation.avatar_url || '/images/default-avatar.png'"
            class="w-8 h-8 rounded-full object-cover border border-gray-200 dark:border-slate-700"
            @error="$event.target.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(conversation.title || 'Chat')"
          />
          <!-- Online status dot -->
          <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-500 border-2 border-white dark:border-slate-900"></span>
        </div>

        <div class="min-w-0">
          <div class="flex items-center gap-1.5">
            <h3 class="font-bold text-gray-900 dark:text-white text-xs sm:text-sm truncate max-w-[170px] sm:max-w-[190px]">
              {{ conversation.title }}
            </h3>
            <!-- Unread badge when minimized -->
            <span
              v-if="isMinimized && hasUnreadWhileMinimized"
              class="w-2 h-2 rounded-full bg-[#D72D36] animate-ping"
              title="Có tin nhắn mới"
            ></span>
            <span
              v-if="conversation.is_archived"
              class="bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 text-[9px] font-semibold px-1 py-0.5 rounded"
            >
              Lưu trữ
            </span>
          </div>

          <p v-if="conversation.context_tag" class="text-[10px] text-[#D72D36] dark:text-red-400 font-medium truncate">
            {{ conversation.context_tag }}
          </p>
          <p v-else class="text-[10px] text-gray-400 dark:text-slate-400 truncate">
            Đang hoạt động
          </p>
        </div>
      </div>

      <!-- Header Actions -->
      <div class="flex items-center gap-0.5" @click.stop>
        <!-- Minimize / Expand Button -->
        <button
          @click="toggleMinimize"
          class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-full hover:bg-gray-100 dark:hover:bg-slate-800 transition"
          :title="isMinimized ? 'Mở rộng' : 'Thu nhỏ'"
        >
          <MinusIcon v-if="!isMinimized" class="w-4 h-4" />
          <ChevronUpIcon v-else class="w-4 h-4" />
        </button>

        <!-- Dropdown Options -->
        <div class="relative" ref="menuRef">
          <button
            @click="isMenuOpen = !isMenuOpen"
            class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-full hover:bg-gray-100 dark:hover:bg-slate-800 transition"
            title="Tùy chọn"
          >
            <EllipsisVerticalIcon class="w-4 h-4" />
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="isMenuOpen"
            class="absolute right-0 top-full mt-1 w-44 bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-xl shadow-xl py-1 z-50 text-xs"
          >
            <button
              v-if="otherUserId && !conversation.is_blocked_by_me"
              @click="blockUser"
              class="w-full text-left px-3 py-2 text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700 flex items-center gap-2"
            >
              <NoSymbolIcon class="w-4 h-4 text-gray-500" />
              <span>Chặn người dùng</span>
            </button>
            <button
              v-else-if="otherUserId && conversation.is_blocked_by_me"
              @click="unblockUser"
              class="w-full text-left px-3 py-2 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 flex items-center gap-2 font-medium"
            >
              <CheckCircleIcon class="w-4 h-4 text-emerald-500" />
              <span>Bỏ chặn người dùng</span>
            </button>
            <button
              @click="openReportModal"
              class="w-full text-left px-3 py-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 flex items-center gap-2 font-medium"
            >
              <FlagIcon class="w-4 h-4 text-red-500" />
              <span>Báo cáo quấy rối</span>
            </button>
          </div>
        </div>

        <!-- Close Button -->
        <button
          @click="chatStore.closeChat"
          class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-full hover:bg-gray-100 dark:hover:bg-slate-800 transition"
          title="Đóng chat"
        >
          <XMarkIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Stranger Warning Banner -->
    <div
      v-if="!isMinimized && conversation.is_stranger"
      class="bg-amber-50 dark:bg-amber-950/30 px-3 py-1.5 border-b border-amber-100 dark:border-amber-900/30 text-[11px] text-amber-800 dark:text-amber-300 flex items-center justify-between flex-shrink-0"
    >
      <div class="flex items-center gap-1.5 min-w-0">
        <ExclamationTriangleIcon class="w-3.5 h-3.5 text-amber-600 flex-shrink-0" />
        <span class="truncate">Tin nhắn từ người lạ</span>
      </div>
      <button
        @click="blockUser"
        class="text-[11px] font-semibold text-red-600 dark:text-red-400 hover:underline ml-2 flex-shrink-0"
      >
        Chặn
      </button>
    </div>

    <!-- Messages Feed -->
    <div
      v-show="!isMinimized"
      ref="feedRef"
      class="flex-1 overflow-y-auto p-3 space-y-2.5 bg-gray-50/60 dark:bg-[#0B1120]"
    >
      <div v-if="chatStore.loadingMessages" class="text-center py-10 text-xs text-gray-400">
        Đang tải tin nhắn...
      </div>

      <div v-else-if="chatStore.messages.length === 0" class="text-center py-12 text-gray-400 space-y-2">
        <div class="flex justify-center text-gray-300 dark:text-slate-600">
          <ChatBubbleOvalLeftEllipsisIcon class="w-10 h-10 stroke-[1.5]" />
        </div>
        <p class="text-xs">Hãy gửi tin nhắn đầu tiên để bắt đầu trò chuyện!</p>
      </div>

      <div
        v-for="msg in chatStore.messages"
        :key="msg.id"
        class="flex flex-col"
        :class="isMyMessage(msg) ? 'items-end' : 'items-start'"
      >
        <!-- Sender name in group chat -->
        <span
          v-if="!isMyMessage(msg) && !conversation.isDm && msg.sender"
          class="text-[10px] text-gray-500 dark:text-slate-400 ml-1 mb-0.5"
        >
          {{ msg.sender.full_name }}
        </span>

        <!-- Message Content / Card -->
        <div
          v-if="msg.type === 'card_mini_tournament' || msg.type === 'card_match'"
          class="w-full max-w-[280px]"
        >
          <ChatCardMiniTournament :card="msg.card_data || {}" />
        </div>

        <div
          v-else-if="msg.type === 'card_tournament'"
          class="w-full max-w-[280px]"
        >
          <ChatCardTournament :card="msg.card_data || {}" />
        </div>

        <div
          v-else-if="msg.type === 'card_player'"
          class="w-full max-w-[280px]"
        >
          <ChatCardPlayer :card="msg.card_data || {}" />
        </div>

        <div
          v-else-if="msg.type === 'card_club'"
          class="w-full max-w-[280px]"
        >
          <ChatCardClub :card="msg.card_data || {}" />
        </div>

        <!-- Standard Text Bubble -->
        <div
          v-else
          :class="[
            'px-3.5 py-2 rounded-2xl text-xs sm:text-[13px] max-w-[85%] break-words leading-relaxed shadow-sm',
            isMyMessage(msg)
              ? 'bg-[#D72D36] text-white rounded-br-none'
              : 'bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-bl-none border border-gray-100 dark:border-slate-700/60'
          ]"
        >
          {{ msg.content }}
        </div>

        <!-- Timestamp & Delivery/Read Status -->
        <div
          class="text-[10px] text-gray-400 mt-0.5 px-1 flex items-center gap-1"
          :class="isMyMessage(msg) ? 'justify-end' : 'justify-start'"
        >
          <span>{{ formatTime(msg.created_at) }}</span>
          <template v-if="isMyMessage(msg)">
            <span>·</span>
            <span
              :class="[
                'font-medium inline-flex items-center gap-0.5',
                msg.status === 'seen'
                  ? 'text-blue-500 dark:text-blue-400'
                  : 'text-gray-400 dark:text-slate-400'
              ]"
            >
              <CheckBadgeIcon v-if="msg.status === 'seen'" class="w-3 h-3" />
              <CheckIcon v-else class="w-3 h-3" />
              {{ msg.status === 'seen' ? 'Đã xem' : 'Đã gửi' }}
            </span>
          </template>
        </div>
      </div>
    </div>

    <!-- Blocked by me banner -->
    <div
      v-if="!isMinimized && conversation.is_blocked_by_me"
      class="p-3 bg-gray-50 dark:bg-slate-900 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between gap-2 text-xs flex-shrink-0"
    >
      <div class="flex items-center gap-1.5 text-gray-600 dark:text-slate-300 min-w-0">
        <NoSymbolIcon class="w-4 h-4 text-red-500 flex-shrink-0" />
        <span class="truncate text-[11px]">Bạn đã chặn người dùng này.</span>
      </div>
      <button
        @click="unblockUser"
        class="bg-[#D72D36] hover:bg-red-600 text-white font-semibold text-xs px-3 py-1.5 rounded-lg flex-shrink-0 transition shadow-sm"
      >
        Bỏ chặn
      </button>
    </div>

    <!-- Blocked by other banner -->
    <div
      v-else-if="!isMinimized && conversation.is_blocked_by_other"
      class="p-3 bg-gray-50 dark:bg-slate-900 border-t border-gray-100 dark:border-slate-800 flex items-center justify-center gap-1.5 text-xs text-gray-400 dark:text-slate-500 flex-shrink-0"
    >
      <NoSymbolIcon class="w-3.5 h-3.5 flex-shrink-0" />
      <span class="text-[11px]">Không thể gửi tin nhắn cho người dùng này.</span>
    </div>

    <!-- Chat Input Bar -->
    <div
      v-else-if="!isMinimized"
      class="p-2.5 bg-white dark:bg-slate-900 border-t border-gray-100 dark:border-slate-800 flex items-center gap-1.5 flex-shrink-0"
    >
      <!-- Attachment / Shortcut (+) button -->
      <button
        @click="isShortcutModalOpen = true"
        class="w-8 h-8 rounded-full bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-600 dark:text-slate-300 flex items-center justify-center flex-shrink-0 transition"
        title="Đính kèm thẻ Kèo / Giải / Người chơi / CLB"
      >
        <PlusIcon class="w-4 h-4" />
      </button>

      <!-- Message Input -->
      <input
        ref="inputRef"
        v-model="textInput"
        @keyup.enter="handleSendText"
        type="text"
        placeholder="Nhập tin nhắn..."
        class="flex-1 bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white text-xs px-3.5 py-2 rounded-full border-none focus:ring-2 focus:ring-[#D72D36] outline-none"
      />

      <!-- Send Button -->
      <button
        @click="handleSendText"
        :disabled="sending || !textInput.trim()"
        class="w-8 h-8 rounded-full bg-[#D72D36] hover:bg-red-600 text-white flex items-center justify-center flex-shrink-0 transition disabled:opacity-40 disabled:cursor-not-allowed shadow"
      >
        <PaperAirplaneIcon class="w-3.5 h-3.5 transform -rotate-45 -translate-y-0.5 translate-x-0.5" />
      </button>
    </div>
  </div>

  <!-- Teleport Dialog Modals to Body -->
  <teleport to="body">
    <ChatShortcutPickerModal
      :isOpen="isShortcutModalOpen"
      @close="isShortcutModalOpen = false"
      @select="handleSendCard"
    />

    <ChatReportModal
      :isOpen="isReportModalOpen"
      :conversationId="conversation?.id"
      :reportedUserId="otherUserId"
      @close="isReportModalOpen = false"
    />

    <DeleteConfirmationModal
      v-model="isBlockConfirmOpen"
      title="Chặn người dùng"
      message="Bạn có chắc chắn muốn chặn người dùng này? Bạn sẽ không nhận được tin nhắn từ họ nữa."
      confirmButtonText="Chặn"
      confirmButtonClass="!bg-red-600 hover:!bg-red-700"
      @confirm="confirmBlockUser"
    />
  </teleport>
</template>

<script setup>
import { ref, computed, nextTick, watch } from "vue";
import {
  EllipsisVerticalIcon,
  XMarkIcon,
  MinusIcon,
  ChevronUpIcon,
  NoSymbolIcon,
  FlagIcon,
  ExclamationTriangleIcon,
  PlusIcon,
  PaperAirplaneIcon,
  CheckIcon,
  CheckCircleIcon,
  CheckBadgeIcon,
} from "@heroicons/vue/24/solid";
import { ChatBubbleOvalLeftEllipsisIcon } from "@heroicons/vue/24/outline";
import { useChatStore } from "@/store/chat.js";
import { useUserStore } from "@/store/auth.js";
import { chatService } from "@/service/chat.js";
import { toast } from "vue3-toastify";
import DeleteConfirmationModal from "@/components/molecules/DeleteConfirmationModal.vue";
import ChatCardMiniTournament from "./ChatCardMiniTournament.vue";
import ChatCardTournament from "./ChatCardTournament.vue";
import ChatCardPlayer from "./ChatCardPlayer.vue";
import ChatCardClub from "./ChatCardClub.vue";
import ChatShortcutPickerModal from "./ChatShortcutPickerModal.vue";
import ChatReportModal from "./ChatReportModal.vue";

const chatStore = useChatStore();
const userStore = useUserStore();

const feedRef = ref(null);
const inputRef = ref(null);
const textInput = ref("");
const sending = ref(false);
const isMenuOpen = ref(false);
const isMinimized = ref(false);
const hasUnreadWhileMinimized = ref(false);
const isShortcutModalOpen = ref(false);
const isReportModalOpen = ref(false);
const isBlockConfirmOpen = ref(false);

const conversation = computed(() => chatStore.activeConversation);

const currentUserId = computed(() => userStore.getUser?.id);

const otherUserId = computed(() => {
  if (conversation.value?.other_user?.id) {
    return conversation.value.other_user.id;
  }
  return null;
});

const isMyMessage = (msg) => {
  return msg.user_id === currentUserId.value;
};

const scrollToBottom = () => {
  nextTick(() => {
    if (feedRef.value) {
      feedRef.value.scrollTop = feedRef.value.scrollHeight;
    }
  });
};

const toggleMinimize = () => {
  isMinimized.value = !isMinimized.value;
  if (!isMinimized.value) {
    hasUnreadWhileMinimized.value = false;
    scrollToBottom();
  }
};

const expandChat = () => {
  if (isMinimized.value) {
    isMinimized.value = false;
    hasUnreadWhileMinimized.value = false;
    scrollToBottom();
  }
};

watch(
  () => chatStore.messages.length,
  () => {
    if (isMinimized.value) {
      hasUnreadWhileMinimized.value = true;
    } else {
      scrollToBottom();
    }
  }
);

watch(
  () => conversation.value?.id,
  () => {
    isMinimized.value = false;
    hasUnreadWhileMinimized.value = false;
  }
);

watch(
  () => chatStore.isChatOpen,
  (val) => {
    if (val) {
      isMinimized.value = false;
      hasUnreadWhileMinimized.value = false;
      scrollToBottom();
      nextTick(() => {
        inputRef.value?.focus();
      });
    }
  }
);

const handleSendText = async () => {
  const content = textInput.value.trim();
  if (!content || sending.value) return;

  try {
    sending.value = true;
    textInput.value = "";
    nextTick(() => {
      inputRef.value?.focus();
    });
    await chatStore.sendMessage({
      type: "text",
      content: content,
    });
    scrollToBottom();
  } catch (err) {
    console.error("send message error:", err);
    toast.error(err?.response?.data?.message || "Không thể gửi tin nhắn");
  } finally {
    sending.value = false;
    nextTick(() => {
      inputRef.value?.focus();
    });
  }
};

const handleSendCard = async ({ type, target_id, title }) => {
  try {
    await chatStore.sendMessage({
      type: type,
      content: `Đã chia sẻ: ${title}`,
      metadata: { target_id: target_id },
    });
    scrollToBottom();
    nextTick(() => {
      inputRef.value?.focus();
    });
  } catch (err) {
    console.error("send card error:", err);
    toast.error(err?.response?.data?.message || "Không thể gửi thẻ");
  }
};

const blockUser = () => {
  if (!otherUserId.value) return;
  isMenuOpen.value = false;
  isBlockConfirmOpen.value = true;
};

const confirmBlockUser = async () => {
  try {
    await chatService.blockUser(otherUserId.value);
    toast.success("Đã chặn người dùng thành công");
    if (conversation.value) {
      conversation.value.is_blocked_by_me = true;
      conversation.value.is_blocked = true;
    }
  } catch (err) {
    toast.error(err?.response?.data?.message || "Lỗi khi chặn người dùng");
  }
};

const unblockUser = async () => {
  if (!otherUserId.value) return;
  try {
    await chatService.unblockUser(otherUserId.value);
    toast.success("Đã bỏ chặn người dùng thành công");
    if (conversation.value) {
      conversation.value.is_blocked_by_me = false;
      conversation.value.is_blocked = !!conversation.value.is_blocked_by_other;
    }
    isMenuOpen.value = false;
  } catch (err) {
    toast.error(err?.response?.data?.message || "Lỗi khi bỏ chặn người dùng");
  }
};

const openReportModal = () => {
  isMenuOpen.value = false;
  isReportModalOpen.value = true;
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
</script>
