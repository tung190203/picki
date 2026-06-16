<template>
  <div class="bg-white rounded-2xl p-5" style="box-shadow: 0 2px 12px rgba(0,0,0,0.06)">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-gray-900 text-base">Bảng xếp hạng</h3>
      <button
        class="flex items-center text-sm text-[#D72D36] font-medium hover:text-red-700 transition-colors"
        @click="navigateTo('/leaderboard')">
        Xem chi tiết
        <ArrowUpRightIcon class="w-4 h-4 ml-1" />
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-2 mb-5 overflow-x-auto pb-1">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        @click="switchTab(tab.value)"
        :class="[
          'flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition-all',
          activeTab === tab.value
            ? 'bg-red-600 text-white shadow-sm'
            : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
        ]">
        {{ tab.label }}
      </button>
    </div>

    <!-- Club selector -->
    <div v-if="activeTab === 'clubMembers' && !clubSelectorLoaded" class="mb-4">
      <div class="h-10 rounded-xl bg-gray-100 animate-pulse"></div>
    </div>
    <div v-if="activeTab === 'clubMembers' && clubSelectorLoaded" class="mb-4">
      <select
        v-model="selectedClubId"
        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
        <option value="">-- Chọn CLB --</option>
        <option v-for="club in myClubs" :key="club.id" :value="club.id">
          {{ club.name }}
        </option>
      </select>
    </div>

    <!-- Loading skeleton -->
    <template v-if="loading">
      <div class="space-y-3">
        <div class="flex justify-center gap-4 mb-4">
          <div v-for="i in 3" :key="i" class="w-20 h-24 rounded-2xl bg-gray-100 animate-pulse"></div>
        </div>
        <div v-for="i in 5" :key="'s'+i" class="h-12 rounded-xl bg-gray-100 animate-pulse"></div>
      </div>
    </template>

    <!-- Empty state -->
    <template v-else-if="!items.length">
      <div class="min-h-[200px] flex flex-col items-center justify-center text-gray-400 text-sm gap-3">
        <svg class="w-12 h-12 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <span class="text-center px-4">{{ emptyMessage }}</span>
      </div>
    </template>

    <!-- Content -->
    <template v-else>
      <!-- Top 3 podium -->
      <div v-if="topThree.length" class="flex items-end justify-center gap-3 mb-6">
        <!-- #2 -->
        <div v-if="topThree[1]" class="flex flex-col items-center flex-1 max-w-[120px]">
          <div class="relative mb-2">
            <img
              v-if="!avatarFailed[topThree[1]?.id]"
              :src="getAvatar(topThree[1])"
              :alt="getName(topThree[1])"
              class="w-14 h-14 rounded-2xl object-cover bg-gray-100 ring-4 ring-gray-100"
              @error="topThree[1] && (avatarFailed[topThree[1].id] = true)" />
            <div
              v-if="avatarFailed[topThree[1]?.id]"
              class="w-14 h-14 rounded-2xl bg-gradient-to-br from-gray-200 to-gray-300 text-gray-600 font-bold text-xl flex items-center justify-center ring-4 ring-gray-100">
              {{ getInitial(topThree[1]) }}
            </div>
            <svg class="absolute -bottom-2 left-1/2 -translate-x-1/2 w-6 h-6" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="11" fill="#C0C0C0"/>
              <text x="12" y="16" text-anchor="middle" fill="#fff" font-size="10" font-weight="bold">2</text>
            </svg>
          </div>
          <span class="text-xs font-semibold text-gray-700 text-center line-clamp-1 w-full">{{ getName(topThree[1]) }}</span>
          <span class="text-xs text-[#4392E0] font-medium">{{ getScore(topThree[1]) }}</span>
        </div>

        <!-- #1 -->
        <div v-if="topThree[0]" class="flex flex-col items-center flex-1 max-w-[140px]">
          <div class="relative mb-2">
            <img
              v-if="!avatarFailed[topThree[0]?.id]"
              :src="getAvatar(topThree[0])"
              :alt="getName(topThree[0])"
              class="w-20 h-20 rounded-2xl object-cover bg-gray-100 ring-4 ring-red-100"
              @error="topThree[0] && (avatarFailed[topThree[0].id] = true)" />
            <div
              v-if="avatarFailed[topThree[0]?.id]"
              class="w-20 h-20 rounded-2xl bg-gradient-to-br from-red-200 to-red-300 text-red-700 font-bold text-2xl flex items-center justify-center ring-4 ring-red-100">
              {{ getInitial(topThree[0]) }}
            </div>
            <svg class="absolute -bottom-2 left-1/2 -translate-x-1/2 w-7 h-7" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="11" fill="#FFD700"/>
              <text x="12" y="16" text-anchor="middle" fill="#B8860B" font-size="10" font-weight="bold">1</text>
            </svg>
          </div>
          <span class="text-sm font-bold text-gray-900 text-center line-clamp-1 w-full">{{ getName(topThree[0]) }}</span>
          <span class="text-sm text-[#D72D36] font-bold">{{ getScore(topThree[0]) }}</span>
        </div>

        <!-- #3 -->
        <div v-if="topThree[2]" class="flex flex-col items-center flex-1 max-w-[120px]">
          <div class="relative mb-2">
            <img
              v-if="!avatarFailed[topThree[2]?.id]"
              :src="getAvatar(topThree[2])"
              :alt="getName(topThree[2])"
              class="w-14 h-14 rounded-2xl object-cover bg-gray-100 ring-4 ring-gray-100"
              @error="topThree[2] && (avatarFailed[topThree[2].id] = true)" />
            <div
              v-if="avatarFailed[topThree[2]?.id]"
              class="w-14 h-14 rounded-2xl bg-gradient-to-br from-orange-200 to-orange-300 text-orange-700 font-bold text-xl flex items-center justify-center ring-4 ring-gray-100">
              {{ getInitial(topThree[2]) }}
            </div>
            <svg class="absolute -bottom-2 left-1/2 -translate-x-1/2 w-6 h-6" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="11" fill="#CD7F32"/>
              <text x="12" y="16" text-anchor="middle" fill="#fff" font-size="10" font-weight="bold">3</text>
            </svg>
          </div>
          <span class="text-xs font-semibold text-gray-700 text-center line-clamp-1 w-full">{{ getName(topThree[2]) }}</span>
          <span class="text-xs text-[#4392E0] font-medium">{{ getScore(topThree[2]) }}</span>
        </div>
      </div>

      <!-- List items -->
      <div class="space-y-2">
        <div
          v-for="item in restItems"
          :key="item.id"
          @click="goToItem(item)"
          :class="[
            'flex items-center gap-3 p-3 rounded-2xl cursor-pointer transition-all',
            item.is_current_user
              ? 'bg-blue-50 ring-1 ring-blue-200 hover:bg-blue-100'
              : 'hover:bg-gray-50'
          ]">
          <!-- Rank -->
          <div class="w-8 text-center flex-shrink-0">
            <span class="text-sm font-semibold text-gray-400">{{ item.rank }}</span>
          </div>

          <!-- Avatar / Logo -->
          <template v-if="activeTab === 'allClubs'">
            <img
              v-if="!avatarFailed[item.id]"
              :src="getClubLogo(item)"
              :alt="item.name"
              class="w-10 h-10 rounded-xl object-cover bg-gray-100 flex-shrink-0"
              @error="avatarFailed[item.id] = true" />
            <div
              v-if="avatarFailed[item.id]"
              class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-100 to-red-200 text-red-600 font-bold text-sm flex items-center justify-center flex-shrink-0">
              {{ item.name?.charAt(0).toUpperCase() }}
            </div>
          </template>
          <template v-else>
            <img
              v-if="!avatarFailed[item.id]"
              :src="getAvatar(item)"
              :alt="item.full_name || item.name"
              class="w-10 h-10 rounded-full object-cover bg-gray-100 flex-shrink-0"
              @error="avatarFailed[item.id] = true" />
            <div
              v-if="avatarFailed[item.id]"
              class="w-10 h-10 rounded-full bg-gradient-to-br from-red-100 to-red-200 text-red-600 font-bold text-sm flex items-center justify-center flex-shrink-0">
              {{ getInitial(item) }}
            </div>
          </template>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5">
              <span class="text-sm font-semibold text-gray-900 line-clamp-1">
                {{ item.full_name || item.name }}
              </span>
              <template v-if="item.is_current_user">
                <span class="flex-shrink-0 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-600">
                  Bạn
                </span>
              </template>
              <template v-else-if="item.is_anchor">
                <svg class="w-4 h-4 text-[#4392E0] flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" />
                </svg>
              </template>
              <template v-else-if="item.is_verify">
                <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </template>
            </div>
            <div v-if="activeTab === 'allClubs'" class="flex items-center gap-2 mt-0.5">
              <span class="text-xs text-gray-400">{{ item.quantity_members }} thành viên</span>
            </div>
          </div>

          <!-- Score badge -->
          <div class="flex-shrink-0">
            <div class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold"
              :class="item.is_current_user ? 'bg-blue-100 text-blue-700' : 'bg-gray-50 text-gray-600'">
              <svg class="w-3 h-3 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
              {{ getScore(item) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-center gap-2">
        <button
          @click="changePage(Number(meta.page) - 1)"
          :disabled="Number(meta.page) <= 1"
          class="p-1.5 rounded-full hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
          <ChevronLeftIcon class="w-4 h-4 text-gray-500" />
        </button>
        <span class="text-xs text-gray-500 px-2">{{ meta.page }} / {{ meta.last_page }}</span>
        <button
          @click="changePage(Number(meta.page) + 1)"
          :disabled="Number(meta.page) >= meta.last_page"
          class="p-1.5 rounded-full hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
          <ChevronRightIcon class="w-4 h-4 text-gray-500" />
        </button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, inject, onMounted } from "vue";
import { useRouter } from "vue-router";
import { ArrowUpRightIcon, ChevronLeftIcon, ChevronRightIcon } from "@heroicons/vue/24/outline";
import * as LeaderboardService from "@/service/leaderboard";
import { useUserStore } from "@/store/auth";
import { storeToRefs } from "pinia";

const router = useRouter();
const userStore = useUserStore();
const { getUser } = storeToRefs(userStore);
const BASE_STORAGE_URL = "http://localhost:8000/storage/";

const tabs = [
  { label: "Top 50", value: "all" },
  { label: "BXH CLB", value: "allClubs" },
  { label: "Thành viên CLB", value: "clubMembers" },
  { label: "BXH Bạn bè", value: "friend" },
];

const scopeMap = {
  all: "all",
  allClubs: "allClubs",
  clubMembers: "club",
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

const emptyMessage = computed(() => {
  if (activeTab.value === "friend") return "Bạn chưa có bạn bè nào để hiển thị bảng xếp hạng.";
  if (activeTab.value === "clubMembers") return "Chưa có dữ liệu xếp hạng thành viên.";
  if (activeTab.value === "allClubs") return "Chưa có CLB nào trong bảng xếp hạng.";
  return "Chưa có dữ liệu bảng xếp hạng.";
});

const topThree = computed(() => items.value.slice(0, 3));
const restItems = computed(() => {
  const userId = getUser.value?.id;
  return items.value.slice(3).map(item => ({
    ...item,
    is_current_user: item.id === userId,
  }));
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
    per_page: activeTab.value === "allClubs" ? 12 : 20,
    page,
  };
  if (activeTab.value === "clubMembers" && selectedClubId.value) {
    params.club_id = selectedClubId.value;
  }
  try {
    const data = await LeaderboardService.getLeaderboard(params);
    items.value = data.leaderboard || [];
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
  if (activeTab.value === "allClubs") {
    router.push(`/club/${item.id}`);
  } else {
    router.push(`/profile/${item.id}`);
  }
};

const navigateTo = (route) => {
  router.push(route);
};

watch(myClubs, (val) => {
  clubSelectorLoaded.value = true;
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
  if (val === "friend") {
    fetchLeaderboard(1);
  } else {
    fetchLeaderboard(1);
  }
});

onMounted(() => {
  fetchLeaderboard(1);
});
</script>
