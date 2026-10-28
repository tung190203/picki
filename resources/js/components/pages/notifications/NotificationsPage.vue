<template>
  <div class="min-h-screen bg-gray-50 dark:bg-[#0B1120] text-gray-900 dark:text-slate-100 py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <!-- Header & Search -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2.5">
            <span>Inbox</span>
            <span
              v-if="unreadCounts.total_unread > 0"
              class="bg-[#D72D36] text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-sm"
            >
              {{ unreadCounts.total_unread }} mới
            </span>
          </h1>
          <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
            Hộp thư hợp nhất tin nhắn cá nhân, nhóm và thông báo hệ thống
          </p>
        </div>

        <div class="flex items-center gap-2">
          <!-- Search Bar -->
          <div class="relative flex-1 sm:w-64">
            <input
              v-model="searchQuery"
              @input="onSearchInput"
              type="text"
              placeholder="Tìm kiếm tin nhắn, thông báo..."
              class="w-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-3.5 py-2 pl-9 pr-8 text-xs focus:ring-2 focus:ring-[#D72D36] outline-none shadow-sm transition"
            />
            <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" />
            <button
              v-if="searchQuery"
              @click="clearSearch"
              class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
              title="Xóa tìm kiếm"
            >
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Mark all as read button -->
          <button
            @click="markAllAsRead"
            class="flex items-center gap-1.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 px-3.5 py-2 rounded-xl text-xs font-semibold shadow-sm transition"
            title="Đánh dấu đã đọc tất cả"
          >
            <CheckIcon class="w-4 h-4 text-emerald-500" />
            <span class="hidden md:inline">Đã đọc tất cả</span>
          </button>
        </div>
      </div>

      <!-- Main Navigation Tabs -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-200 dark:border-slate-800 pb-3">
        <!-- 3 Main Tabs -->
        <div class="flex items-center gap-1.5 p-1 bg-gray-100 dark:bg-slate-800/80 rounded-xl w-fit">
          <button
            v-for="t in mainTabs"
            :key="t.key"
            @click="changeTab(t.key)"
            :class="[
              'px-4 py-2 rounded-lg text-xs font-bold transition flex items-center gap-1.5 relative',
              currentTab === t.key
                ? 'bg-white dark:bg-slate-900 text-gray-900 dark:text-white shadow-sm'
                : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'
            ]"
          >
            <span>{{ t.label }}</span>
            <span
              v-if="t.badge > 0"
              class="bg-[#D72D36] text-white text-[10px] font-extrabold px-1.5 py-0.2 rounded-full"
            >
              {{ t.badge }}
            </span>
          </button>
        </div>

        <!-- Filter Pill: Chưa đọc & Danh sách đã chặn -->
        <div class="flex items-center gap-2">
          <button
            v-if="currentTab === 'messages'"
            @click="isBlockedModalOpen = true"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition flex items-center gap-1.5"
            title="Quản lý danh sách người dùng đã chặn"
          >
            <NoSymbolIcon class="w-3.5 h-3.5 text-gray-500" />
            <span>Đã chặn</span>
          </button>

          <button
            @click="toggleUnreadFilter"
            :class="[
              'px-3 py-1.5 rounded-lg text-xs font-semibold border transition flex items-center gap-1.5',
              isUnreadOnly
                ? 'bg-red-50 dark:bg-red-950/40 border-[#D72D36] text-[#D72D36] dark:text-red-400'
                : 'bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50'
            ]"
          >
            <span class="w-2 h-2 rounded-full" :class="isUnreadOnly ? 'bg-[#D72D36]' : 'bg-gray-400'"></span>
            <span>Chưa đọc</span>
          </button>
        </div>
      </div>

      <!-- ================= TAB 2: TIN NHẮN (MESSAGES) ================= -->
      <div v-if="currentTab === 'messages'" class="space-y-4">
        <!-- Pinned Card: Tin nhắn từ người lạ -->
        <div
          v-if="strangersSummary && strangersSummary.total_conversations > 0"
          @click="isStrangersModalOpen = true"
          class="p-4 rounded-2xl bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-950/30 dark:to-orange-950/20 border border-amber-200/80 dark:border-amber-800/40 hover:border-amber-400 cursor-pointer shadow-sm hover:shadow transition flex items-center justify-between"
        >
          <div class="flex items-center gap-3.5 min-w-0 pr-3">
            <div class="relative flex-shrink-0">
              <img
                :src="strangersSummary.latest_sender_avatar || '/images/default-avatar.png'"
                class="w-12 h-12 rounded-full object-cover border-2 border-amber-400 shadow-sm"
                @error="$event.target.src = 'https://ui-avatars.com/api/?background=f59e0b&color=fff&name=' + encodeURIComponent(strangersSummary.latest_sender_name || 'Stranger')"
              />
              <span
                v-if="strangersSummary.unread_count > 0"
                class="absolute -top-1 -right-1 bg-amber-500 text-white font-extrabold text-[10px] w-5 h-5 rounded-full flex items-center justify-center shadow"
              >
                {{ strangersSummary.unread_count }}
              </span>
            </div>

            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <h4 class="font-extrabold text-sm text-gray-900 dark:text-white">
                  Tin nhắn từ người lạ
                </h4>
                <span class="bg-amber-200 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200 text-[10px] font-bold px-2 py-0.5 rounded-full">
                  {{ strangersSummary.total_conversations }} hội thoại
                </span>
              </div>
              <p class="text-xs text-gray-600 dark:text-slate-300 truncate mt-0.5">
                <span class="font-semibold">{{ strangersSummary.latest_sender_name }}:</span>
                {{ strangersSummary.latest_message }}
              </p>
            </div>
          </div>

          <button class="bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs px-3.5 py-2 rounded-xl flex-shrink-0 shadow transition">
            Xem ngay →
          </button>
        </div>

        <!-- Conversations List -->
        <div v-if="loadingFeed && page === 1 && conversationItems.length === 0" class="text-center py-12 text-xs text-gray-400">
          Đang tải danh sách tin nhắn...
        </div>

        <div v-else-if="conversationItems.length === 0" class="flex flex-col items-center justify-center py-16 text-gray-400 space-y-2">
          <p class="text-4xl text-gray-300 dark:text-slate-600">
            <ChatBubbleOvalLeftEllipsisIcon class="w-12 h-12 stroke-[1.5]" />
          </p>
          <p class="text-sm font-medium">Chưa có cuộc trò chuyện nào</p>
          <p class="text-xs text-gray-500">Tin nhắn từ bạn bè, CLB và Kèo đấu sẽ hiển thị tại đây.</p>
        </div>

        <div v-else class="space-y-2.5">
          <div
            v-for="conv in conversationItems"
            :key="conv.id"
            @click="openConversation(conv.id)"
            class="flex items-center justify-between p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800/80 hover:border-gray-300 dark:hover:border-slate-700 cursor-pointer shadow-sm hover:shadow transition"
          >
            <div class="flex items-center gap-3.5 min-w-0 pr-3">
              <div class="relative flex-shrink-0">
                <img
                  :src="conv.avatar_url || '/images/default-avatar.png'"
                  class="w-12 h-12 rounded-full object-cover border border-gray-200 dark:border-slate-700"
                  @error="$event.target.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(conv.title || 'Chat')"
                />
                <span
                  v-if="conv.unread_count > 0"
                  class="absolute -top-1 -right-1 bg-[#D72D36] text-white font-extrabold text-[10px] w-5 h-5 rounded-full flex items-center justify-center shadow"
                >
                  {{ conv.unread_count }}
                </span>
              </div>

              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <h4 class="font-bold text-sm text-gray-900 dark:text-white truncate">
                    {{ conv.title }}
                  </h4>
                  <BellSlashIcon
                    v-if="conv.is_muted"
                    class="w-3.5 h-3.5 text-gray-400 dark:text-slate-500 flex-shrink-0"
                    title="Đã tắt thông báo"
                  />
                  <span
                    v-if="conv.context_tag"
                    class="bg-gray-100 dark:bg-slate-800 text-[#D72D36] dark:text-red-400 text-[10px] font-semibold px-2 py-0.5 rounded-full truncate"
                  >
                    {{ conv.context_tag }}
                  </span>
                  <span
                    v-if="conv.is_blocked_by_me"
                    class="bg-gray-100 dark:bg-slate-800 text-gray-500 text-[10px] font-semibold px-2 py-0.5 rounded-full"
                  >
                    Đã chặn
                  </span>
                  <span
                    v-if="conv.is_archived"
                    class="bg-amber-100 dark:bg-amber-950/60 text-amber-700 text-[10px] font-semibold px-1.5 py-0.5 rounded"
                  >
                    Lưu trữ
                  </span>
                </div>

                <p class="text-xs text-gray-500 dark:text-slate-400 truncate mt-1">
                  {{ conv.last_message?.content || 'Đã gửi một tin nhắn' }}
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0" @click.stop>
              <button
                @click.stop="toggleMuteConv(conv.id)"
                class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800 transition"
                :title="conv.is_muted ? 'Bật thông báo' : 'Tắt thông báo'"
              >
                <BellSlashIcon v-if="conv.is_muted" class="w-4 h-4 text-amber-500" />
                <BellOutlineIcon v-else class="w-4 h-4" />
              </button>
              <span class="text-[11px] text-gray-400 whitespace-nowrap">
                {{ formatRelativeTime(conv.last_message_at || conv.updated_at) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- ================= TAB 3: THÔNG BÁO (ALERTS) ================= -->
      <div v-else-if="currentTab === 'alerts'" class="space-y-4">
        <div v-if="loadingFeed && page === 1 && alertItems.length === 0" class="text-center py-12 text-xs text-gray-400">
          Đang tải thông báo...
        </div>

        <div v-else-if="alertItems.length === 0" class="flex flex-col items-center justify-center py-16 text-gray-400 space-y-2">
          <p class="text-4xl text-gray-300 dark:text-slate-600">
            <BellOutlineIcon class="w-12 h-12 stroke-[1.5]" />
          </p>
          <p class="text-sm font-medium">Không có thông báo nào</p>
          <p class="text-xs text-gray-500">Thông báo về kèo đấu, giải đấu và CLB sẽ hiển thị tại đây.</p>
        </div>

        <div v-else class="space-y-2.5">
          <div
            v-for="alert in alertItems"
            :key="alert.id"
            @click="handleAlertClick(alert)"
            :class="[
              'p-4 rounded-2xl border transition cursor-pointer shadow-sm hover:shadow',
              alert.read_at
                ? 'bg-white dark:bg-slate-900 border-gray-100 dark:border-slate-800 text-gray-600 dark:text-slate-300'
                : 'bg-red-50/40 dark:bg-slate-800/80 border-red-200 dark:border-red-950/60 text-gray-900 dark:text-white font-medium'
            ]"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-start gap-3.5 min-w-0">
                <!-- Heroicon Badge Container -->
                <div :class="['w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5 shadow-xs', getAlertBadgeClass(alert)]">
                  <component :is="getAlertIcon(alert)" class="w-5 h-5" />
                </div>

                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <h4 class="font-bold text-sm">{{ parseAlert(alert).title }}</h4>
                    <span
                      v-if="!alert.read_at"
                      class="w-2 h-2 rounded-full bg-[#D72D36] flex-shrink-0"
                    />
                  </div>
                  <p class="text-xs text-gray-600 dark:text-slate-300 mt-1 leading-relaxed">
                    {{ parseAlert(alert).message }}
                  </p>
                </div>
              </div>

              <div class="flex flex-col items-end gap-2.5 flex-shrink-0">
                <span class="text-[11px] text-gray-400 whitespace-nowrap">
                  {{ formatRelativeTime(alert.created_at) }}
                </span>

                <!-- Quick actions -->
                <div class="flex items-center gap-1.5" @click.stop>
                  <button
                    v-if="!alert.read_at"
                    @click.stop="markOneAsRead(alert.id)"
                    class="flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold bg-white dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-200 border border-gray-200 dark:border-slate-700 rounded-lg shadow-xs transition"
                    title="Đánh dấu đã đọc"
                  >
                    <CheckIcon class="w-3.5 h-3.5 text-emerald-500" />
                    <span>Đã đọc</span>
                  </button>
                  <button
                    @click.stop="deleteOneAlert(alert.id)"
                    class="flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-900/60 text-red-600 dark:text-red-400 border border-transparent rounded-lg transition"
                    title="Xóa thông báo"
                  >
                    <TrashIcon class="w-3.5 h-3.5" />
                    <span>Xóa</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ================= TAB 1: TẤT CẢ (ALL) ================= -->
      <div v-else class="space-y-4">
        <!-- Pinned Card for Strangers in Tab All as well -->
        <div
          v-if="strangersSummary && strangersSummary.unread_count > 0"
          @click="isStrangersModalOpen = true"
          class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40 hover:border-amber-400 cursor-pointer shadow-sm transition flex items-center justify-between"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-300 flex items-center justify-center flex-shrink-0 shadow-xs">
              <EnvelopeIcon class="w-5 h-5" />
            </div>
            <div>
              <h4 class="font-bold text-xs text-amber-900 dark:text-amber-200">
                Bạn có {{ strangersSummary.unread_count }} tin nhắn từ người lạ
              </h4>
              <p class="text-[11px] text-amber-700 dark:text-amber-300">
                {{ strangersSummary.latest_message }}
              </p>
            </div>
          </div>
          <span class="text-xs font-bold text-amber-600">Xem ngay →</span>
        </div>

        <!-- Merged Feed -->
        <div v-if="loadingFeed && page === 1 && allItems.length === 0" class="text-center py-12 text-xs text-gray-400">
          Đang tải dữ liệu...
        </div>

        <div v-else-if="allItems.length === 0" class="flex flex-col items-center py-16 text-gray-400 space-y-2">
          <p class="flex justify-center text-gray-300 dark:text-slate-600">
            <InboxIcon class="w-12 h-12 stroke-[1.5]" />
          </p>
          <p class="text-sm font-medium">Hộp thư trống</p>
        </div>

        <div v-else class="space-y-2.5">
          <div
            v-for="item in allItems"
            :key="item.id"
            @click="handleUnifiedItemClick(item)"
            class="flex items-center justify-between p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 hover:border-gray-300 dark:hover:border-slate-700 cursor-pointer shadow-sm hover:shadow transition"
          >
            <!-- Conversation Item -->
            <template v-if="item.item_type === 'conversation'">
              <div class="flex items-center gap-3.5 min-w-0 pr-3">
                <div class="relative flex-shrink-0">
                  <img
                    :src="item.data?.avatar_url || '/images/default-avatar.png'"
                    class="w-12 h-12 rounded-full object-cover border border-gray-200 dark:border-slate-700"
                    @error="$event.target.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(item.data?.title || 'Chat')"
                  />
                  <span
                    v-if="item.data?.unread_count > 0"
                    class="absolute -top-1 -right-1 bg-[#D72D36] text-white font-extrabold text-[10px] w-5 h-5 rounded-full flex items-center justify-center shadow"
                  >
                    {{ item.data.unread_count }}
                  </span>
                </div>

                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <h4 class="font-bold text-sm truncate">{{ item.data?.title }}</h4>
                    <BellSlashIcon
                      v-if="item.data?.is_muted"
                      class="w-3.5 h-3.5 text-gray-400 dark:text-slate-500 flex-shrink-0"
                      title="Đã tắt thông báo"
                    />
                    <span
                      v-if="item.data?.context_tag"
                      class="bg-gray-100 dark:bg-slate-800 text-[#D72D36] text-[10px] font-semibold px-2 py-0.5 rounded-full truncate"
                    >
                      {{ item.data.context_tag }}
                    </span>
                    <span
                      v-if="item.data?.is_blocked_by_me"
                      class="bg-gray-100 dark:bg-slate-800 text-gray-500 text-[10px] font-semibold px-2 py-0.5 rounded-full"
                    >
                      Đã chặn
                    </span>
                  </div>
                  <p class="text-xs text-gray-500 dark:text-slate-400 truncate mt-1">
                    {{ item.data?.last_message?.content || 'Đã gửi một tin nhắn' }}
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-2 flex-shrink-0" @click.stop>
                <button
                  @click.stop="toggleMuteConv(item.data?.id)"
                  class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800 transition"
                  :title="item.data?.is_muted ? 'Bật thông báo' : 'Tắt thông báo'"
                >
                  <BellSlashIcon v-if="item.data?.is_muted" class="w-4 h-4 text-amber-500" />
                  <BellOutlineIcon v-else class="w-4 h-4" />
                </button>
                <span class="text-[11px] text-gray-400 whitespace-nowrap">
                  {{ formatRelativeTime(item.timestamp) }}
                </span>
              </div>
            </template>

            <!-- Alert Item -->
            <template v-else>
              <div class="flex items-start gap-3.5 min-w-0 pr-3">
                <!-- Heroicon Badge Container -->
                <div :class="['w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5 shadow-xs', getAlertBadgeClass(item.data)]">
                  <component :is="getAlertIcon(item.data)" class="w-5 h-5" />
                </div>

                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <h4 class="font-bold text-sm">{{ parseAlert(item.data).title }}</h4>
                    <span
                      v-if="!item.data?.read_at"
                      class="w-2 h-2 rounded-full bg-[#D72D36] flex-shrink-0"
                    />
                  </div>
                  <p class="text-xs text-gray-500 dark:text-slate-400 truncate mt-1">
                    {{ parseAlert(item.data).message }}
                  </p>
                </div>
              </div>

              <span class="text-[11px] text-gray-400 whitespace-nowrap flex-shrink-0">
                {{ formatRelativeTime(item.timestamp) }}
              </span>
            </template>
          </div>
        </div>
      </div>

      <!-- Load More Button -->
      <div v-if="hasMore" class="flex justify-center pt-2 pb-6">
        <button
          @click="loadMore"
          :disabled="loadingMore"
          class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 shadow-sm transition disabled:opacity-50"
        >
          <span v-if="loadingMore">Đang tải thêm...</span>
          <span v-else>Xem thêm thông báo & tin nhắn</span>
        </button>
      </div>
    </div>

    <!-- Modals -->
    <StrangersQueueModal
      :isOpen="isStrangersModalOpen"
      @close="isStrangersModalOpen = false"
    />

    <ChatBlockedUsersModal
      :isOpen="isBlockedModalOpen"
      @close="isBlockedModalOpen = false"
      @unblocked="handleUserUnblocked"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { useRouter } from "vue-router";
import {
  CheckIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  TrophyIcon,
  UserGroupIcon,
  UserIcon,
  EnvelopeIcon,
  BellIcon,
  BellSlashIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
  NoSymbolIcon,
} from "@heroicons/vue/24/solid";
import {
  BellIcon as BellOutlineIcon,
  ChatBubbleOvalLeftEllipsisIcon,
  InboxIcon,
  TrashIcon,
} from "@heroicons/vue/24/outline";
import { toast } from "vue3-toastify";
import { inboxService } from "@/service/chat.js";
import * as NotificationService from "@/service/notifications.js";
import { useChatStore } from "@/store/chat.js";
import { useUserStore } from "@/store/auth.js";
import StrangersQueueModal from "@/components/chat/StrangersQueueModal.vue";
import ChatBlockedUsersModal from "@/components/chat/ChatBlockedUsersModal.vue";

const router = useRouter();
const chatStore = useChatStore();
const userStore = useUserStore();

const currentTab = ref("all"); // 'all', 'messages', 'alerts'
const isUnreadOnly = ref(false);
const loadingFeed = ref(false);
const loadingMore = ref(false);
const page = ref(1);
const hasMore = ref(false);

const feedData = ref({
  items: [],
  strangers_summary: null,
});

const isStrangersModalOpen = ref(false);
const isBlockedModalOpen = ref(false);

const handleUserUnblocked = (unblockedUserId) => {
  feedData.value.items.forEach((item) => {
    if (item.other_user?.id === unblockedUserId) {
      item.is_blocked_by_me = false;
      item.is_blocked = false;
    }
    if (item.data?.other_user?.id === unblockedUserId) {
      item.data.is_blocked_by_me = false;
      item.data.is_blocked = false;
    }
  });
};

// Search state
const searchQuery = ref("");
const searching = ref(false);
const searchResults = ref({
  conversations: [],
  alerts: [],
});

const unreadCounts = computed(() => chatStore.unreadCounts);
const strangersSummary = computed(() => feedData.value.strangers_summary);

const mainTabs = computed(() => [
  { key: "all", label: "Tất cả", badge: unreadCounts.value.total_unread },
  { key: "messages", label: "Tin nhắn", badge: unreadCounts.value.messages_unread },
  { key: "alerts", label: "Thông báo", badge: unreadCounts.value.alerts_unread },
]);

const conversationItems = computed(() => {
  return feedData.value.items || [];
});

const alertItems = computed(() => {
  return feedData.value.items || [];
});

const allItems = computed(() => {
  return feedData.value.items || [];
});

/**
 * Chọn Heroicon phù hợp theo từng loại thông báo
 */
const getAlertIcon = (alert) => {
  const type = alert?.type || alert?.data?.type || "";

  if (
    type.includes("cleanup") ||
    type.includes("Removed") ||
    type.includes("Cancelled") ||
    type.includes("Rejected")
  ) {
    return ExclamationTriangleIcon;
  }
  if (
    type.includes("Confirmed") ||
    type.includes("Approved") ||
    type.includes("success")
  ) {
    return CheckCircleIcon;
  }
  if (
    type.includes("Tournament") ||
    type.includes("tournament") ||
    type.includes("Match") ||
    type.includes("match")
  ) {
    return TrophyIcon;
  }
  if (type.includes("Club") || type.includes("CLUB")) {
    return UserGroupIcon;
  }
  if (type.includes("Follow") || type.includes("PrivateMessage")) {
    return UserIcon;
  }

  return BellIcon;
};

/**
 * Chọn màu nền badge phù hợp theo từng loại thông báo
 */
const getAlertBadgeClass = (alert) => {
  const type = alert?.type || alert?.data?.type || "";

  if (
    type.includes("cleanup") ||
    type.includes("Removed") ||
    type.includes("Cancelled") ||
    type.includes("Rejected")
  ) {
    return "bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400";
  }
  if (
    type.includes("Confirmed") ||
    type.includes("Approved") ||
    type.includes("success")
  ) {
    return "bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400";
  }
  if (type.includes("Club") || type.includes("CLUB")) {
    return "bg-purple-100 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400";
  }
  if (
    type.includes("Tournament") ||
    type.includes("tournament") ||
    type.includes("Match") ||
    type.includes("match")
  ) {
    return "bg-blue-100 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400";
  }

  return "bg-red-100 text-[#D72D36] dark:bg-red-950/60 dark:text-red-400";
};

/**
 * Chuẩn hóa title, message và icon cho từng loại thông báo
 */
const parseAlert = (raw) => {
  if (!raw) return { title: "Thông báo", message: "" };
  const d = raw.data || {};
  const type = raw.type || "";

  let title = d.title || "Thông báo";
  let message = d.message || d.body || "";

  switch (type) {
    case "MiniTournamentMessageNotification":
    case "PrivateMessageNotification":
    case "TournamentMessageNotification":
      title = d.sender_name ? `${d.sender_name} đã gửi tin nhắn` : "Tin nhắn mới";
      message = d.type === "image" ? "Đã gửi một hình ảnh" : d.content || message;
      break;

    case "MiniMatchCreatedNotification":
    case "MiniMatchUpdatedNotification":
    case "MiniMatchResultConfirmedNotification":
      title = d.title || "Cập nhật trận đấu";
      message = d.message || "Có cập nhật mới về trận đấu";
      break;

    case "MiniTournamentInvitationNotification":
    case "MiniTournamentCreatorInvitationNotification":
    case "TournamentInvitationNotification":
      title = d.title || "Lời mời tham gia";
      message = d.message || "Bạn được mời tham gia";
      break;

    case "MiniTournamentJoinRequestNotification":
    case "TournamentJoinRequestNotification":
      title = d.title || "Yêu cầu tham gia";
      message = d.message || "Có người gửi yêu cầu tham gia";
      break;

    case "MiniTournamentJoinConfirmedNotification":
    case "TournamentJoinConfirmedNotification":
      title = d.title || "Tham gia thành công";
      message = d.message || "Yêu cầu tham gia của bạn đã được chấp nhận";
      break;

    case "MiniTournamentRemovedNotification":
    case "TournamentRemovedNotification":
      title = d.title || "Giải đấu đã bị hủy";
      message = d.message || "Giải đấu không còn tồn tại";
      break;

    case "MiniTournamentReminder":
      title = d.title || "Nhắc nhở giải đấu";
      message = d.message || "Giải đấu sắp diễn ra";
      break;

    case "FollowNotification":
      title = "Theo dõi mới";
      message = d.message || (d.follower_name ? `${d.follower_name} vừa theo dõi bạn` : "Có người vừa theo dõi bạn");
      break;

    case "CLUB_ACTIVITY_PROMOTION":
      title = d.title || "Lời mời tham gia sự kiện";
      message = d.message || "Bạn được mời tham gia sự kiện";
      break;

    case "CLUB_PROMOTION":
      title = d.title || "Lời mời tham gia CLB";
      message = d.message || "Bạn được mời tham gia CLB";
      break;

    case "MINI_TOURNAMENT_PROMOTION":
      title = d.title || "Lời mời tham gia kèo đấu";
      message = d.message || "Bạn được mời tham gia kèo đấu";
      break;

    case "VerifyEmailNotification":
      title = "Xác thực email";
      message = "Vui lòng xác thực email của bạn";
      break;

    case "tournament_cleanup":
      title = d.title || "Kèo đấu đã được xóa tự động";
      message = d.message || "";
      break;

    case "tournament_guest_added":
      title = d.title || "Khách mời được thêm vào giải";
      message = d.message || "";
      break;
  }

  return {
    id: raw.id,
    type,
    title,
    message,
    read: !!raw.read_at,
    read_at: raw.read_at,
    created_at: raw.created_at,
    rawData: d,
  };
};

const loadFeed = async (isFirstPage = true, isSilent = false) => {
  try {
    if (isFirstPage) {
      page.value = 1;
      if (!isSilent && (!feedData.value.items || feedData.value.items.length === 0)) {
        loadingFeed.value = true;
      }
    } else {
      loadingMore.value = true;
    }

    const keyword = searchQuery.value.trim();
    const res = await inboxService.getInbox({
      tab: currentTab.value,
      is_unread: isUnreadOnly.value ? 1 : 0,
      ...(keyword ? { q: keyword } : {}),
      page: page.value,
      per_page: 25,
    });

    const data = res.data || { items: [], strangers_summary: null, pagination: {} };

    if (isFirstPage) {
      feedData.value = {
        items: data.items || [],
        strangers_summary: data.strangers_summary || null,
      };
    } else {
      feedData.value.items.push(...(data.items || []));
      if (data.strangers_summary) {
        feedData.value.strangers_summary = data.strangers_summary;
      }
    }

    const p = data.pagination || {};
    hasMore.value = p.current_page && p.last_page ? p.current_page < p.last_page : false;
  } catch (err) {
    console.error("loadFeed error:", err);
  } finally {
    loadingFeed.value = false;
    loadingMore.value = false;
  }
};

const loadMore = () => {
  if (loadingMore.value || !hasMore.value) return;
  page.value++;
  loadFeed(false);
};

const changeTab = (tabKey) => {
  currentTab.value = tabKey;
  feedData.value.items = [];
  loadFeed(true, false);
};

const toggleUnreadFilter = () => {
  isUnreadOnly.value = !isUnreadOnly.value;
  loadFeed(true);
};

const markAllAsRead = async () => {
  try {
    await inboxService.markAllAsRead(currentTab.value);
    toast.success("Đã đánh dấu tất cả là đã đọc");
    await chatStore.fetchUnreadCounts();
    await loadFeed(true);
  } catch (err) {
    console.error("markAllAsRead error:", err);
  }
};

const markOneAsRead = async (alertId) => {
  try {
    await NotificationService.markAsRead(alertId);
    // Cập nhật trạng thái item hiện tại
    const target = feedData.value.items.find((item) => {
      const id = item.item_type === "alert" ? item.data?.id : item.id;
      return id === alertId;
    });
    if (target) {
      if (target.item_type === "alert" && target.data) {
        target.data.read_at = new Date().toISOString();
      } else {
        target.read_at = new Date().toISOString();
      }
    }
    await chatStore.fetchUnreadCounts();
    toast.success("Đã đánh dấu đã đọc");
  } catch (err) {
    console.error("markOneAsRead error:", err);
  }
};

const deleteOneAlert = async (alertId) => {
  try {
    await NotificationService.deleteNotification(alertId);
    feedData.value.items = feedData.value.items.filter((item) => {
      const id = item.item_type === "alert" ? item.data?.id : item.id;
      return id !== alertId;
    });
    await chatStore.fetchUnreadCounts();
    toast.success("Đã xóa thông báo");
  } catch (err) {
    console.error("deleteOneAlert error:", err);
  }
};

const handleConversationReadLocally = (convId) => {
  if (!convId) return;
  const targetId = Number(convId);
  if (Array.isArray(feedData.value?.items)) {
    feedData.value.items.forEach((item) => {
      // Tab "Tất cả": item.item_type === 'conversation', item.data.id === targetId
      if (item.item_type === "conversation" && item.data && Number(item.data.id) === targetId) {
        item.data.unread_count = 0;
      }
      // Tab "Tin nhắn": item.id === targetId
      if (Number(item.id) === targetId) {
        item.unread_count = 0;
      }
    });
  }
};

const handleConversationReadEvent = (e) => {
  const convId = e?.detail?.conversationId;
  if (convId) {
    handleConversationReadLocally(convId);
  }
};

const applyConversationMutedLocally = (convId, isMuted) => {
  if (!convId || !Array.isArray(feedData.value?.items)) return;
  const targetId = Number(convId);
  feedData.value.items.forEach((item) => {
    if (item.item_type === "conversation" && item.data && Number(item.data.id) === targetId) {
      item.data.is_muted = Boolean(isMuted);
    }
    if (Number(item.id) === targetId) {
      item.is_muted = Boolean(isMuted);
    }
  });
};

const handleConversationMutedEvent = (e) => {
  const convId = e?.detail?.conversationId;
  if (convId) {
    applyConversationMutedLocally(convId, e?.detail?.is_muted);
  }
};

const toggleMuteConv = async (convId) => {
  if (!convId) return;
  try {
    const res = await chatStore.toggleMute(convId);
    const muted = Boolean(res?.data?.is_muted);
    applyConversationMutedLocally(convId, muted);
    toast.success(muted ? "Đã tắt thông báo cuộc trò chuyện" : "Đã bật thông báo cuộc trò chuyện");
  } catch (err) {
    toast.error(err?.response?.data?.message || "Không thể cập nhật cài đặt thông báo");
  }
};

const openConversation = (convId) => {
  handleConversationReadLocally(convId);
  chatStore.openChat(convId);
};

const redirectByType = (alertObj) => {
  const d = alertObj.rawData || alertObj.data || {};
  const type = alertObj.type || "";

  switch (type) {
    case "PrivateMessageNotification":
      if (!d.sender_id) return;
      router.push({ name: "profile", params: { id: d.sender_id } });
      break;

    case "TournamentMessageNotification":
      if (!d.tournament_id) return;
      router.push({
        name: "tournament-detail",
        params: { id: d.tournament_id },
        query: { tab: "discuss" },
      });
      break;

    case "MiniTournamentMessageNotification":
      if (!d.mini_tournament_id) return;
      router.push({
        name: "mini-tournament-detail",
        params: { id: d.mini_tournament_id },
        query: { tab: "chat" },
      });
      break;

    case "MiniMatchCreatedNotification":
    case "MiniMatchUpdatedNotification":
      if (!d.mini_tournament_id) return;
      router.push({
        name: "mini-tournament-detail",
        params: { id: d.mini_tournament_id },
        query: { tab: "matches" },
      });
      break;

    case "MiniMatchResultConfirmedNotification":
      if (!d.match_id) return;
      router.push({
        name: "mini-match-verify",
        params: { id: d.match_id },
      });
      break;

    case "MiniTournamentInvitationNotification":
    case "MiniTournamentJoinConfirmedNotification":
    case "MiniTournamentCreatorInvitationNotification":
      if (!d.tournament_id && !d.mini_tournament_id) return;
      router.push({
        name: "mini-tournament-detail",
        params: { id: d.tournament_id || d.mini_tournament_id },
      });
      break;

    case "TournamentInvitationNotification":
    case "TournamentJoinConfirmedNotification":
      if (!d.tournament_id) return;
      router.push({
        name: "tournament-detail",
        params: { id: d.tournament_id },
      });
      break;

    case "ClubRoleChangeNotification":
    case "ClubInvitationNotification":
    case "ClubInvitationCancelledNotification":
    case "ClubInvitationDeclinedNotification":
    case "ClubJoinRequestApprovedNotification":
    case "ClubJoinRequestReceivedNotification":
    case "ClubJoinRequestRejectedNotification":
    case "ClubMemberKickedNotification":
    case "ClubNotificationSentNotification":
    case "ClubRenamedNotification":
      if (!d.club_id) return;
      router.push({
        name: "club-detail",
        params: { id: d.club_id },
        query: { showNotifications: true },
      });
      break;

    case "ClubActivityCancelledNotification":
    case "ClubActivityInvitationNotification":
    case "ClubActivityJoinApprovedNotification":
    case "ClubActivityJoinRejectedNotification":
    case "ClubActivityJoinRequestNotification":
      if (!d.club_id || !d.club_activity_id) return;
      router.push({
        name: "club-detail-activity",
        params: { id: d.club_id, activityId: d.club_activity_id },
      });
      break;

    case "ClubFundCollectionCreatedNotification":
    case "ClubFundCollectionReminderNotification":
    case "ClubFundContributionApprovedNotification":
    case "ClubFundContributionRejectedNotification":
    case "ClubFundContributionSubmittedNotification":
      if (!d.club_id || !d.club_fund_collection_id) return;
      router.push({
        name: "club-fund",
        params: { id: d.club_id },
        query: {
          collectionId: d.club_fund_collection_id,
          contributionId: d.club_fund_contribution_id,
        },
      });
      break;

    case "CLUB_ACTIVITY_PROMOTION":
      if (!d.club_id || !d.club_activity_id) return;
      router.push({
        name: "club-detail-activity",
        params: { id: d.club_id, activityId: d.club_activity_id },
      });
      break;

    case "CLUB_PROMOTION":
      if (!d.club_id) return;
      router.push({
        name: "club-detail",
        params: { id: d.club_id },
      });
      break;

    case "CLUB_TOURNAMENT_CREATED":
      if (!d.tournament_id) return;
      if (d.tournament_type === "mini_tournament") {
        router.push({
          name: "mini-tournament-detail",
          params: { id: d.tournament_id },
        });
      } else {
        router.push({
          name: "tournament-detail",
          params: { id: d.tournament_id },
        });
      }
      break;

    case "MINI_TOURNAMENT_PROMOTION":
      if (!d.mini_tournament_id) return;
      router.push({
        name: "mini-tournament-detail",
        params: { id: d.mini_tournament_id },
      });
      break;

    default:
      if (d.tournament_id) {
        router.push(`/tournament/${d.tournament_id}`);
      } else if (d.mini_tournament_id) {
        router.push(`/mini-tournament/${d.mini_tournament_id}`);
      } else if (d.club_id) {
        router.push(`/club/${d.club_id}`);
      } else if (d.sender_id || d.user_id) {
        router.push(`/profile/${d.sender_id || d.user_id}`);
      }
  }
};

const handleAlertClick = async (alert) => {
  const parsed = parseAlert(alert);
  if (!parsed.read && parsed.id) {
    try {
      await NotificationService.markAsRead(parsed.id);
      alert.read_at = new Date().toISOString();
      chatStore.fetchUnreadCounts();
    } catch {}
  }
  redirectByType(parsed);
};

const handleUnifiedItemClick = (item) => {
  if (item.item_type === "conversation") {
    openConversation(item.data.id);
  } else {
    handleAlertClick(item.data);
  }
};

let searchDebounce = null;
const onSearchInput = () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    loadFeed(true);
  }, 300);
};

const clearSearch = () => {
  searchQuery.value = "";
  loadFeed(true);
};

const formatRelativeTime = (timeStr) => {
  if (!timeStr) return "";
  try {
    const d = new Date(timeStr);
    const now = new Date();
    const diffMs = now - d;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return "Vừa xong";
    if (diffMins < 60) return `${diffMins} phút trước`;
    if (diffHours < 24) return `${diffHours} giờ trước`;
    if (diffDays < 7) return `${diffDays} ngày trước`;

    return d.toLocaleDateString("vi-VN", { day: "2-digit", month: "2-digit" });
  } catch {
    return timeStr;
  }
};

const handleRealtimeConversationUpdated = (e) => {
  chatStore.fetchUnreadCounts();
  const conv = e?.detail?.conversation;
  const unreadCount = e?.detail?.unread_count ?? 0;
  if (!conv || !Array.isArray(feedData.value?.items)) {
    loadFeed(true, true);
    return;
  }

  // Nếu cuộc trò chuyện này đang được mở bởi chính người dùng, unread_count hiển thị trên list sẽ là 0
  const isThisChatCurrentlyOpen = chatStore.isChatOpen && Number(chatStore.activeConversation?.id) === Number(conv.id);
  const effectiveUnreadCount = isThisChatCurrentlyOpen ? 0 : unreadCount;

  // 1. Cập nhật trong tab "Tin nhắn" (items chứa conversation objects)
  const idxInMessages = feedData.value.items.findIndex(
    (item) => Number(item.id) === Number(conv.id)
  );

  // 2. Cập nhật trong tab "Tất cả" (items chứa { item_type: 'conversation', data: conv })
  const idxInAll = feedData.value.items.findIndex(
    (item) => item.item_type === "conversation" && Number(item.data?.id) === Number(conv.id)
  );

  if (idxInMessages !== -1) {
    const existing = feedData.value.items[idxInMessages];
    feedData.value.items.splice(idxInMessages, 1);
    const updated = {
      ...existing,
      ...conv,
      unread_count: effectiveUnreadCount,
      last_message: conv.last_message || existing.last_message,
      last_message_at: conv.last_message_at || new Date().toISOString(),
    };
    feedData.value.items.unshift(updated);
  } else if (idxInAll !== -1) {
    const existingWrapper = feedData.value.items[idxInAll];
    feedData.value.items.splice(idxInAll, 1);
    const updatedWrapper = {
      ...existingWrapper,
      sort_time: Date.now(),
      timestamp: new Date().toISOString(),
      data: {
        ...existingWrapper.data,
        ...conv,
        unread_count: effectiveUnreadCount,
        last_message: conv.last_message || existingWrapper.data?.last_message,
        last_message_at: conv.last_message_at || new Date().toISOString(),
      },
    };
    feedData.value.items.unshift(updatedWrapper);
  } else {
    // Hội thoại chưa có trong trang 1 -> Tải ngầm không làm gián đoạn UI
    loadFeed(true, true);
  }
};

const handleRepliedStranger = () => {
  chatStore.fetchUnreadCounts();
  loadFeed(true, true);
};

onMounted(() => {
  chatStore.fetchUnreadCounts();
  loadFeed(true);

  // Listen to custom window events from chatStore
  window.addEventListener("chat:replied-stranger", handleRepliedStranger);
  window.addEventListener("chat:conversation-updated", handleRealtimeConversationUpdated);
  window.addEventListener("chat:conversation-read", handleConversationReadEvent);
  window.addEventListener("chat:conversation-muted", handleConversationMutedEvent);

  // Listen to user socket channel for real-time conversation updates
  const userId = userStore.getUser?.id;
  if (userId) {
    chatStore.listenUserChannel(userId);
  }
});

onBeforeUnmount(() => {
  window.removeEventListener("chat:replied-stranger", handleRepliedStranger);
  window.removeEventListener("chat:conversation-updated", handleRealtimeConversationUpdated);
  window.removeEventListener("chat:conversation-read", handleConversationReadEvent);
  window.removeEventListener("chat:conversation-muted", handleConversationMutedEvent);
});
</script>