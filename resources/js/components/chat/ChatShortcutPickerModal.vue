<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in"
      @click.self="$emit('close')"
    >
      <div class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl w-full max-w-lg h-[580px] max-h-[85vh] shadow-2xl overflow-hidden flex flex-col">
        <!-- Header -->
        <div class="p-4 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between flex-shrink-0">
          <h3 class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2">
            <PaperClipIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
            <span>Đính kèm thẻ tương tác</span>
          </h3>
          <button
            @click="$emit('close')"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition"
            title="Đóng"
          >
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Type Tabs -->
        <div class="px-4 py-3 bg-gray-50/80 dark:bg-slate-800/40 flex items-center gap-2.5 border-b border-gray-100 dark:border-slate-800 overflow-x-auto scrollbar-none flex-shrink-0">
          <button
            v-for="t in tabs"
            :key="t.key"
            @click="changeTab(t.key)"
            :class="[
              'px-4 py-2.5 rounded-full text-xs sm:text-sm font-bold whitespace-nowrap transition-all duration-200 flex items-center gap-2 select-none',
              activeTab === t.key
                ? 'bg-[#D72D36] text-white shadow-md shadow-red-500/25 ring-2 ring-red-500/20'
                : 'bg-white dark:bg-slate-800 text-gray-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-700 border border-gray-200/90 dark:border-slate-700 shadow-sm'
            ]"
          >
            <component :is="t.icon" class="w-4 h-4 flex-shrink-0" />
            <span>{{ t.label }}</span>
          </button>
        </div>

        <!-- Search Input -->
        <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-800 flex-shrink-0">
          <div class="relative">
            <input
              v-model="searchQuery"
              @input="onSearchInput"
              type="text"
              placeholder="Tìm kiếm..."
              class="w-full bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white text-xs sm:text-sm pl-9 pr-4 py-2.5 rounded-xl border border-transparent focus:border-red-300 dark:focus:border-red-900 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-[#D72D36]/20 outline-none transition"
            />
            <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 absolute left-3 top-3" />
          </div>
        </div>

        <!-- List Items -->
        <div class="flex-1 overflow-y-auto p-4 space-y-2.5 min-h-0">
          <div v-if="loading" class="text-center py-16 text-gray-400 text-xs">
            Đang tải dữ liệu...
          </div>

          <div v-else-if="items.length === 0" class="text-center py-16 text-gray-400 text-xs">
            Không tìm thấy mục nào phù hợp
          </div>

          <div
            v-for="item in items"
            :key="item.id"
            class="flex items-center justify-between p-3 rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-800/60 hover:bg-gray-50/80 dark:hover:bg-slate-800 hover:shadow-sm transition"
          >
            <div class="flex items-center gap-3.5 min-w-0 pr-3">
              <img
                v-if="item.poster_url || item.avatar_url"
                :src="formatImageUrl(item.poster_url || item.avatar_url)"
                class="w-12 h-12 rounded-xl object-cover flex-shrink-0 shadow-sm border border-gray-100 dark:border-slate-700"
                @error="$event.target.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(item.title || item.full_name || item.name || 'Item')"
              />
              <div
                v-else
                class="w-12 h-12 rounded-xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0 text-gray-500 shadow-sm border border-gray-100 dark:border-slate-700"
              >
                <component :is="currentTabIcon" class="w-5 h-5" />
              </div>

              <div class="min-w-0">
                <h5 class="font-bold text-gray-900 dark:text-white text-xs sm:text-sm truncate">
                  {{ item.title || item.full_name || item.name }}
                </h5>
                <p class="text-[11px] sm:text-xs text-gray-500 dark:text-slate-400 truncate mt-0.5">
                  {{ item.start_time || item.start_date || (item.rating ? `Điểm: ${item.rating}` : 'Đang hoạt động') }}
                </p>
              </div>
            </div>

            <button
              @click="selectItem(item)"
              class="bg-[#D72D36] hover:bg-red-600 text-white font-semibold text-xs sm:text-sm px-3.5 py-2 rounded-xl flex-shrink-0 transition shadow-sm hover:shadow"
            >
              Gửi thẻ
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import {
  PaperClipIcon,
  XMarkIcon,
  MagnifyingGlassIcon,
  CalendarDaysIcon,
  TrophyIcon,
  UserIcon,
  UserGroupIcon,
} from "@heroicons/vue/24/solid";
import { chatService } from "@/service/chat.js";

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["close", "select"]);

const tabs = [
  { key: "matches", label: "Kèo đấu", icon: CalendarDaysIcon, cardType: "card_mini_tournament" },
  { key: "tournaments", label: "Giải đấu", icon: TrophyIcon, cardType: "card_tournament" },
  { key: "players", label: "Người chơi", icon: UserIcon, cardType: "card_player" },
  { key: "clubs", label: "CLB", icon: UserGroupIcon, cardType: "card_club" },
];

const activeTab = ref("matches");
const searchQuery = ref("");
const items = ref([]);
const loading = ref(false);

const currentTabIcon = computed(() => {
  return tabs.find((t) => t.key === activeTab.value)?.icon || PaperClipIcon;
});

const loadShortcuts = async () => {
  try {
    loading.value = true;
    const res = await chatService.getShortcuts(activeTab.value, searchQuery.value);
    items.value = res.data || [];
  } catch (err) {
    console.error("loadShortcuts error:", err);
    items.value = [];
  } finally {
    loading.value = false;
  }
};

let searchTimeout = null;
const onSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadShortcuts();
  }, 300);
};

const changeTab = (tabKey) => {
  activeTab.value = tabKey;
  searchQuery.value = "";
  loadShortcuts();
};

const selectItem = (item) => {
  const currentTabObj = tabs.find((t) => t.key === activeTab.value);
  emit("select", {
    type: currentTabObj.cardType,
    target_id: item.id,
    title: item.title || item.full_name || item.name,
  });
  emit("close");
};

const formatImageUrl = (url) => {
  if (!url) return "";
  if (url.startsWith("http://") || url.startsWith("https://") || url.startsWith("/storage/")) {
    return url;
  }
  return `/storage/${url.replace(/^\/+/, "")}`;
};

watch(
  () => props.isOpen,
  (val) => {
    if (val) {
      loadShortcuts();
    }
  },
  { immediate: true }
);
</script>
