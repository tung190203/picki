<template>
  <div class="min-h-screen bg-gray-50 px-4 py-8">
    <div class="max-w-7xl mx-auto">
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <!-- Header -->
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-gray-900">Bảng xếp hạng</h1>
          <p class="text-sm text-gray-500 mt-1">Xếp hạng người chơi và CLB theo điểm VNDUPR</p>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-1">
          <button
            v-for="tab in tabs"
            :key="tab.value"
            @click="switchTab(tab.value)"
            :class="[
              'flex-shrink-0 px-4 py-2 rounded-xl text-sm font-medium transition-all',
              activeTab === tab.value
                ? 'bg-red-600 text-white shadow-md'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            ]">
            {{ tab.label }}
          </button>
        </div>

        <!-- Club selector for clubMembers tab -->
        <div v-if="activeTab === 'clubMembers' && !clubSelectorLoaded" class="mb-6">
          <div class="h-12 rounded-xl bg-gray-100 animate-pulse"></div>
        </div>
        <div v-if="activeTab === 'clubMembers' && clubSelectorLoaded" class="mb-6">
          <select
            v-model="selectedClubId"
            class="w-full px-4 py-3 rounded-xl border border-gray-300 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
            <option value="">-- Chọn CLB --</option>
            <option v-for="club in myClubs" :key="club.id" :value="club.id">
              {{ club.name }}
            </option>
          </select>
        </div>

        <!-- Loading skeleton -->
        <template v-if="loading">
          <div class="space-y-3">
            <div v-for="i in 10" :key="'s'+i" class="h-16 rounded-xl bg-gray-100 animate-pulse"></div>
          </div>
        </template>

        <!-- Empty state -->
        <template v-else-if="!items.length">
          <div class="min-h-[400px] flex flex-col items-center justify-center text-gray-400 text-sm gap-3">
            <svg class="w-16 h-16 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <span class="text-center px-4">{{ emptyMessage }}</span>
          </div>
        </template>

        <!-- Content -->
        <template v-else>
          <!-- Table -->
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Rank</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    {{ activeTab === 'allClubs' ? 'Tên CLB' : 'Người chơi' }}
                  </th>
                  <th v-if="activeTab !== 'allClubs'" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">CLB</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    {{ activeTab === 'allClubs' ? 'Thành viên' : 'VNDUPR' }}
                  </th>
                  <th v-if="activeTab !== 'allClubs'" class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Tuần</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <tr
                  v-for="item in items"
                  :key="item.id"
                  @click="goToItem(item)"
                  :class="[
                    'cursor-pointer transition-all hover:bg-gray-50',
                    item.is_current_user ? 'bg-blue-50' : ''
                  ]">
                  <!-- Rank -->
                  <td class="px-4 py-4 whitespace-nowrap">
                    <span class="text-sm font-bold" :class="getRankClass(item.rank)">
                      {{ item.rank }}
                    </span>
                  </td>

                  <!-- Avatar & Name -->
                  <td class="px-4 py-4">
                    <div class="flex items-center gap-3">
                      <template v-if="activeTab === 'allClubs'">
                        <img
                          v-if="!avatarFailed[item.id]"
                          :src="getClubLogo(item)"
                          :alt="item.name"
                          class="w-12 h-12 rounded-xl object-cover bg-gray-100"
                          @error="avatarFailed[item.id] = true" />
                        <div
                          v-if="avatarFailed[item.id]"
                          class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-100 to-red-200 text-red-600 font-bold text-lg flex items-center justify-center">
                          {{ item.name?.charAt(0).toUpperCase() }}
                        </div>
                      </template>
                      <template v-else>
                        <img
                          v-if="!avatarFailed[item.id]"
                          :src="getAvatar(item)"
                          :alt="item.full_name"
                          class="w-12 h-12 rounded-full object-cover bg-gray-100"
                          @error="avatarFailed[item.id] = true" />
                        <div
                          v-if="avatarFailed[item.id]"
                          class="w-12 h-12 rounded-full bg-gradient-to-br from-red-100 to-red-200 text-red-600 font-bold text-lg flex items-center justify-center">
                          {{ getInitial(item) }}
                        </div>
                      </template>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                          <span class="text-sm font-semibold text-gray-900 truncate">
                            {{ item.full_name || item.name }}
                          </span>
                          <template v-if="item.is_current_user">
                            <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-600">
                              Bạn
                            </span>
                          </template>
                          <template v-else-if="item.primary_badge || (item.badges && item.badges.length)">
                            <BadgeIcon :badge="item.primary_badge || item.badges[0]" size="sm" />
                          </template>
                        </div>
                        <div v-if="item.is_verified && activeTab === 'allClubs'" class="flex items-center gap-1 mt-0.5">
                          <svg class="w-3 h-3 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                          </svg>
                          <span class="text-xs text-gray-500">Đã xác minh</span>
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Clubs (for players only) -->
                  <td v-if="activeTab !== 'allClubs'" class="px-4 py-4">
                    <div v-if="item.clubs && item.clubs.length" class="flex flex-wrap gap-1">
                      <span
                        v-for="club in item.clubs.slice(0, 2)"
                        :key="club.id"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">
                        {{ club.name }}
                      </span>
                      <span v-if="item.clubs.length > 2" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">
                        +{{ item.clubs.length - 2 }}
                      </span>
                    </div>
                    <span v-else class="text-xs text-gray-400">-</span>
                  </td>

                  <!-- Score / Members count -->
                  <td class="px-4 py-4 text-center">
                    <template v-if="activeTab === 'allClubs'">
                      <div class="text-sm font-semibold text-gray-600">{{ item.quantity_members }} người</div>
                      <div class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-yellow-50 text-yellow-700 mt-1">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                        </svg>
                        {{ getScore(item) }}
                      </div>
                    </template>
                    <template v-else>
                      <div class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-semibold bg-gradient-to-r from-yellow-50 to-orange-50 text-yellow-700">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                        </svg>
                        {{ getScore(item) }}
                      </div>
                    </template>
                  </td>

                  <!-- Weekly change (for players only) -->
                  <td v-if="activeTab !== 'allClubs'" class="px-4 py-4 text-center">
                    <div v-if="item.weekly_change !== null && item.weekly_change !== undefined"
                      class="inline-flex items-center gap-0.5 px-2 py-1 rounded-full text-xs font-semibold"
                      :class="getWeeklyChangeClass(item.weekly_change)">
                      <ArrowTrendingUpIcon v-if="item.weekly_change > 0" class="w-3 h-3" />
                      <ArrowTrendingDownIcon v-else-if="item.weekly_change < 0" class="w-3 h-3" />
                      <MinusIcon v-else class="w-3 h-3" />
                      {{ Math.abs(item.weekly_change) }}
                    </div>
                    <span v-else class="text-xs text-gray-400">-</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="meta.last_page > 1" class="mt-6 flex items-center justify-center gap-3">
            <button
              @click="changePage(Number(meta.page) - 1)"
              :disabled="Number(meta.page) <= 1"
              class="px-4 py-2 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed transition-all text-sm font-medium">
              <ChevronLeftIcon class="w-4 h-4" />
            </button>
            <span class="text-sm text-gray-600 px-3">Trang {{ meta.page }} / {{ meta.last_page }}</span>
            <button
              @click="changePage(Number(meta.page) + 1)"
              :disabled="Number(meta.page) >= meta.last_page"
              class="px-4 py-2 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed transition-all text-sm font-medium">
              <ChevronRightIcon class="w-4 h-4" />
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, inject, onMounted } from "vue";
import { useRouter } from "vue-router";
import { ChevronLeftIcon, ChevronRightIcon, ArrowTrendingUpIcon, ArrowTrendingDownIcon, MinusIcon } from "@heroicons/vue/24/outline";
import BadgeIcon from "@/components/atoms/BadgeIcon.vue";
import * as LeaderboardService from "@/service/leaderboard";
import { useUserStore } from "@/store/auth";
import { storeToRefs } from "pinia";
import { toast } from 'vue3-toastify';

const router = useRouter();
const userStore = useUserStore();
const { getUser } = storeToRefs(userStore);
const BASE_STORAGE_URL = "http://localhost:8000/storage/";

const tabs = [
  { label: "Top 50", value: "top50" },
  { label: "Top 100", value: "all" },
  { label: "BXH CLB", value: "allClubs" },
  { label: "Thành viên CLB", value: "clubMembers" },
  { label: "BXH Bạn bè", value: "friend" },
];

const scopeMap = {
  top50: "top50",
  all: "all",
  allClubs: "allClubs",
  clubMembers: "club",
  friend: "friend",
};

// Reverse map để chuyển scope về tab
const tabFromScope = {
  top50: "top50",
  all: "all",
  allClubs: "allClubs",
  club: "clubMembers",
  friend: "friend",
};

const activeTab = ref("all");
const items = ref([]);
const meta = ref({ page: 1, last_page: 1, per_page: 20, total: 0 });
const loading = ref(false);
const myClubs = inject('myClubs', ref([]));
const clubSelectorLoaded = ref(false);
const selectedClubId = ref("");
const avatarFailed = ref({});
const isInitialLoad = ref(true); // Flag to detect first load vs user interaction

const emptyMessage = computed(() => {
  if (activeTab.value === "friend") return "Bạn chưa có bạn bè nào để hiển thị bảng xếp hạng.";
  if (activeTab.value === "clubMembers") return "Chưa có dữ liệu xếp hạng thành viên.";
  if (activeTab.value === "allClubs") return "Chưa có CLB nào trong bảng xếp hạng.";
  return "Chưa có dữ liệu bảng xếp hạng.";
});

const fetchLeaderboard = async (page = 1) => {
  if (activeTab.value === "clubMembers" && !selectedClubId.value) {
    items.value = [];
    meta.value = { page: 1, last_page: 1 };
    return;
  }
  loading.value = true;
  const params = {
    scope: scopeMap[activeTab.value],
    per_page: activeTab.value === "allClubs" ? 20 : 50,
    page,
  };
  if (activeTab.value === "clubMembers" && selectedClubId.value) {
    params.club_id = selectedClubId.value;
  }
  try {
    const data = await LeaderboardService.getLeaderboard(params);
    const userId = getUser.value?.id;
    items.value = (data.leaderboard || []).map(item => ({
      ...item,
      is_current_user: item.id === userId,
    }));
    const m = data.meta || { page: 1, last_page: 1 };
    meta.value = {
      page: Number(m.page) || 1,
      last_page: Number(m.last_page) || 1,
      per_page: Number(m.per_page) || 20,
      total: Number(m.total) || 0,
    };
  } catch {
    items.value = [];
    meta.value = { page: 1, last_page: 1, per_page: 20, total: 0 };
  } finally {
    loading.value = false;
  }
};

const switchTab = (tab) => {
  isInitialLoad.value = false; // User manually switched, not restoration
  activeTab.value = tab;
};

const changePage = (page) => {
  if (page < 1 || page > meta.value.last_page) return;
  meta.value.page = page;
  fetchLeaderboard(page);
};

const getAvatar = (item) => {
  if (!item) return "";
  const url = item.avatar_url || item.logo_url;
  if (!url) return "";
  return url.startsWith("http") ? url : BASE_STORAGE_URL + url;
};

const getClubLogo = (item) => {
  if (!item.logo_url) return "";
  return item.logo_url.startsWith("http") ? item.logo_url : BASE_STORAGE_URL + item.logo_url;
};

const getName = (item) => item.full_name || item.name || "";

const getInitial = (item) => {
  const name = getName(item);
  return name.charAt(0).toUpperCase();
};

const getScore = (item) => {
  const score = item.vndupr_score || item.max_score;
  if (score == null) return "-";
  return Number(score).toFixed(2);
};

const goToItem = (item) => {
  if (!item) return;
  if (activeTab.value === "allClubs") {
    router.push(`/clubs/${item.id}`);
  } else if (item.is_virtual || !item.id) {
    toast.info('Thành viên ảo (khách vãng lai) không có hồ sơ cá nhân.');
  } else {
    router.push(`/profile/${item.id}`);
  }
};

const getRankClass = (rank) => {
  if (rank === 1) return 'text-yellow-600';
  if (rank === 2) return 'text-gray-500';
  if (rank === 3) return 'text-orange-600';
  return 'text-gray-600';
};

const getWeeklyChangeClass = (change) => {
  if (change < 0) return 'bg-green-100 text-green-700'; // Cải thiện rank
  if (change > 0) return 'bg-red-100 text-red-700';    // Tụt rank
  return 'bg-gray-100 text-gray-500';                  // Không đổi
};

watch(myClubs, (val) => {
  clubSelectorLoaded.value = true;
  // Auto-select first club when myClubs loads and tab is clubMembers
  if (val && val.length > 0 && activeTab.value === 'clubMembers' && !selectedClubId.value) {
    selectedClubId.value = val[0].id;
  }
});

watch(selectedClubId, () => {
  avatarFailed.value = {};
  if (selectedClubId.value) {
    fetchLeaderboard(1);
  } else {
    items.value = [];
    meta.value = { page: 1, last_page: 1 };
  }
});

watch(activeTab, (val) => {
  items.value = [];
  meta.value = { page: 1, last_page: 1 };
  avatarFailed.value = {};
  // Only fetch if not initial load (i.e., user manually switched tab)
  if (!isInitialLoad.value) {
    fetchLeaderboard(1);
  }
});

// Watch user data để khôi phục scope khi user data đã load
watch(() => getUser.value?.settings?.leaderboard_scope, (savedScope) => {
  console.log('User settings loaded, leaderboard_scope:', savedScope);
  if (savedScope && tabFromScope[savedScope]) {
    // Khôi phục tab đã lưu
    activeTab.value = tabFromScope[savedScope];
    console.log('Restored tab to:', activeTab.value);

    // Nếu khôi phục về clubMembers, khôi phục luôn club_id
    if (savedScope === 'club') {
      const savedClubId = getUser.value?.settings?.leaderboard_club_id;
      if (savedClubId) {
        selectedClubId.value = savedClubId;
        console.log('Restored club_id to:', savedClubId);
      }
    }
  }
}, { immediate: true });

onMounted(async () => {
  // Fetch user data first to get latest settings including leaderboard_scope
  await userStore.fetchMe();
  
  // Đợi một chút để watcher settings chạy trước
  setTimeout(() => {
    isInitialLoad.value = false;
    fetchLeaderboard(1);
  }, 100);
});
</script>
