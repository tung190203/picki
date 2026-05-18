<template>
  <div>
    <!-- Tab Navigation -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
      <div class="flex">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="switchTab(tab.key)"
          class="flex-1 flex items-center justify-center gap-1.5 py-3 text-sm font-medium transition-colors relative"
          :class="activeTab === tab.key ? 'text-[#4392E0]' : 'text-gray-400 hover:text-gray-600'"
        >
          <component :is="tab.icon" class="w-4 h-4" />
          <span>{{ tab.label }}</span>
          <span
            v-if="tabCounts[tab.key] > 0"
            class="text-xs px-1.5 py-0.5 rounded-full min-w-[20px] text-center"
            :class="activeTab === tab.key ? 'bg-[#4392E0]/10 text-[#4392E0]' : 'bg-gray-100 text-gray-500'"
          >
            {{ tabCounts[tab.key] }}
          </span>
          <div
            v-if="activeTab === tab.key"
            class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#4392E0]"
          />
        </button>
      </div>
    </div>

    <!-- ================================================ -->
    <!-- MINI TOURNAMENTS TAB (Kèo đấu) -->
    <!-- ================================================ -->
    <template v-if="activeTab === 'mini_tournament'">
      <!-- Loading -->
      <div v-if="miniLoading" class="space-y-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 animate-pulse">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-full h-10 bg-gray-200 rounded"></div>
          </div>
          <div class="flex items-center gap-2">
            <div class="flex -space-x-2">
              <div v-for="i in 3" :key="i" class="w-7 h-7 rounded-full bg-gray-200 ring-2 ring-white"></div>
            </div>
            <div class="h-3 w-16 bg-gray-200 rounded"></div>
          </div>
        </div>
        <div v-for="i in 2" :key="i" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 animate-pulse">
          <div class="flex items-start justify-between gap-3 mb-2">
            <div><div class="h-3 w-40 bg-gray-200 rounded mb-1"></div><div class="h-2 w-28 bg-gray-200 rounded"></div></div>
            <div class="h-5 w-16 bg-gray-200 rounded"></div>
          </div>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="miniError" class="flex flex-col items-center justify-center py-12 text-center">
        <ExclamationCircleIcon class="w-12 h-12 text-red-400 mb-3" />
        <p class="text-sm text-gray-500 mb-3">{{ miniError }}</p>
        <button @click="fetchMiniTournaments" class="text-sm text-[#4392E0] hover:underline font-medium">Thử lại</button>
      </div>

      <!-- Content -->
      <template v-else>
        <!-- Overview stats -->
        <div v-if="miniOverview" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">Tổng kết kèo đấu</h3>
          <div class="grid grid-cols-4 gap-3">
            <div class="text-center">
              <p class="text-lg font-bold text-gray-800">{{ miniOverview.total_joined ?? 0 }}</p>
              <p class="text-xs text-gray-500">Tham gia</p>
            </div>
            <div class="text-center">
              <p class="text-lg font-bold text-amber-600">{{ miniOverview.total_created ?? 0 }}</p>
              <p class="text-xs text-gray-500">Tôi tạo</p>
            </div>
            <div class="text-center">
              <p class="text-lg font-bold text-green-600">{{ miniOverview.total_win ?? 0 }}</p>
              <p class="text-xs text-gray-500">Thắng</p>
            </div>
            <div class="text-center">
              <p class="text-lg font-bold text-red-500">{{ miniOverview.total_lose ?? 0 }}</p>
              <p class="text-xs text-gray-500">Thua</p>
            </div>
          </div>
        </div>

        <!-- Ongoing mini tournament -->
        <div v-if="miniCurrent" class="mb-4">
          <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2 px-1">ĐANG THAM GIA</h3>
          <MiniTournamentHistoryCard
            :mini-tournament="miniCurrent"
            @click="handleMiniTournamentClick"
          />
        </div>

        <!-- Empty -->
        <div v-if="!miniCurrent && miniCompleted.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
          <BoltIcon class="w-12 h-12 text-gray-300 mb-3" />
          <p class="text-sm text-gray-500">Chưa có kèo đấu nào</p>
        </div>

        <!-- Completed section -->
        <div v-if="miniCompleted.length > 0">
          <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2 px-1">ĐÃ HOÀN THÀNH</h3>
          <div class="space-y-3">
            <MiniTournamentHistoryCard
              v-for="mt in miniCompleted"
              :key="mt.id"
              :mini-tournament="mt"
              @click="handleMiniTournamentClick"
            />
          </div>
        </div>
      </template>
    </template>

    <!-- ================================================ -->
    <!-- MATCHES TAB (Trận đấu) -->
    <!-- ================================================ -->
    <template v-else-if="activeTab === 'match'">
      <!-- Loading -->
      <div v-if="matchesLoading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 animate-pulse">
          <div class="flex items-start justify-between gap-3 mb-3">
            <div class="flex items-center gap-2">
              <div class="w-9 h-9 rounded-full bg-gray-200"></div>
              <div><div class="h-3 w-28 bg-gray-200 rounded mb-1"></div><div class="h-2 w-20 bg-gray-200 rounded"></div></div>
            </div>
            <div class="h-5 w-12 bg-gray-200 rounded"></div>
          </div>
          <div class="flex items-center justify-between gap-3">
            <div><div class="h-2 w-16 bg-gray-200 rounded mb-1"></div><div class="h-4 w-20 bg-gray-200 rounded"></div></div>
            <div class="h-8 w-16 bg-gray-200 rounded"></div>
            <div class="flex-1 flex justify-end"><div class="h-4 w-20 bg-gray-200 rounded"></div></div>
          </div>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="matchesError" class="flex flex-col items-center justify-center py-12 text-center">
        <ExclamationCircleIcon class="w-12 h-12 text-red-400 mb-3" />
        <p class="text-sm text-gray-500 mb-3">{{ matchesError }}</p>
        <button @click="fetchMatches" class="text-sm text-[#4392E0] hover:underline font-medium">Thử lại</button>
      </div>

      <!-- Empty -->
      <div v-else-if="matches.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
        <CommandLineIcon class="w-12 h-12 text-gray-300 mb-3" />
        <p class="text-sm text-gray-500">Chưa có trận đấu nào</p>
      </div>

      <!-- List -->
      <div v-else class="space-y-3">
        <MatchHistoryCard
          v-for="match in matches"
          :key="`${match.type}-${match.id}`"
          :match="match"
          @click="handleMatchClick"
        />
        <div v-if="matchesHasMore" class="flex justify-center pt-2">
          <button @click="loadMoreMatches" :disabled="matchesLoadingMore"
            class="px-6 py-2 text-sm text-[#4392E0] border border-[#4392E0] rounded-full hover:bg-blue-50 transition-colors disabled:opacity-50">
            {{ matchesLoadingMore ? 'Đang tải...' : 'Xem thêm' }}
          </button>
        </div>
      </div>
    </template>

    <!-- ================================================ -->
    <!-- TOURNAMENTS TAB (Giải đấu) -->
    <!-- ================================================ -->
    <template v-else>
      <!-- Overview stats -->
      <div v-if="tournamentOverview && !tournamentLoading" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Tổng kết giải đấu</h3>
        <div class="grid grid-cols-4 gap-3">
          <div class="text-center">
            <p class="text-lg font-bold text-gray-800">{{ tournamentOverview.total_tournaments ?? 0 }}</p>
            <p class="text-xs text-gray-500">Giải</p>
          </div>
          <div class="text-center">
            <p class="text-lg font-bold text-green-600">{{ tournamentOverview.total_win ?? 0 }}</p>
            <p class="text-xs text-gray-500">Thắng</p>
          </div>
          <div class="text-center">
            <p class="text-lg font-bold text-red-500">{{ tournamentOverview.total_lose ?? 0 }}</p>
            <p class="text-xs text-gray-500">Thua</p>
          </div>
          <div class="text-center">
            <p class="text-lg font-bold text-blue-600">{{ tournamentOverview.total_matches ?? 0 }}</p>
            <p class="text-xs text-gray-500">Trận</p>
          </div>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="tournamentLoading" class="space-y-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 animate-pulse">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-full h-10 bg-gray-200 rounded"></div>
          </div>
        </div>
        <div v-for="i in 2" :key="i" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 animate-pulse">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gray-200"></div>
            <div class="flex-1"><div class="h-3 w-32 bg-gray-200 rounded mb-1"></div><div class="h-2 w-20 bg-gray-200 rounded"></div></div>
            <div class="h-5 w-16 bg-gray-200 rounded"></div>
          </div>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="tournamentError" class="flex flex-col items-center justify-center py-12 text-center">
        <ExclamationCircleIcon class="w-12 h-12 text-red-400 mb-3" />
        <p class="text-sm text-gray-500 mb-3">{{ tournamentError }}</p>
        <button @click="fetchTournaments" class="text-sm text-[#4392E0] hover:underline font-medium">Thử lại</button>
      </div>

      <!-- Empty -->
      <div v-else-if="tournaments.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
        <TrophyIcon class="w-12 h-12 text-gray-300 mb-3" />
        <p class="text-sm text-gray-500">Chưa có giải đấu nào</p>
      </div>

      <!-- List -->
      <div v-else class="space-y-3">
        <TournamentHistoryCard
          v-for="t in tournaments"
          :key="t.id"
          :tournament="t"
          @click="handleTournamentClick"
        />
        <div v-if="tournamentHasMore" class="flex justify-center pt-2">
          <button @click="loadMoreTournaments" :disabled="tournamentLoadingMore"
            class="px-6 py-2 text-sm text-[#4392E0] border border-[#4392E0] rounded-full hover:bg-blue-50 transition-colors disabled:opacity-50">
            {{ tournamentLoadingMore ? 'Đang tải...' : 'Xem thêm' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import {
  ExclamationCircleIcon,
  TrophyIcon,
  BoltIcon,
  CommandLineIcon
} from '@heroicons/vue/24/outline';
import { toast } from 'vue3-toastify';
import { userService } from '@/service/user.js';
import MatchHistoryCard from '@/components/molecules/MatchHistoryCard.vue';
import TournamentHistoryCard from '@/components/molecules/TournamentHistoryCard.vue';
import MiniTournamentHistoryCard from '@/components/molecules/MiniTournamentHistoryCard.vue';

const props = defineProps({
  userId: {
    type: [Number, String],
    required: true,
  },
});

const tabs = [
  { key: 'mini_tournament', label: 'Kèo đấu', icon: BoltIcon },
  { key: 'match',          label: 'Trận đấu',  icon: CommandLineIcon },
  { key: 'tournament',     label: 'Giải đấu',  icon: TrophyIcon },
];

const activeTab = ref('mini_tournament');

// Mini Tournament state
const miniTournaments = ref([]);
const miniLoading = ref(false);
const miniError = ref('');
const miniOverview = ref(null);

// Tournament state
const tournaments = ref([]);
const tournamentLoading = ref(false);
const tournamentLoadingMore = ref(false);
const tournamentError = ref('');
const tournamentCurrentPage = ref(1);
const tournamentLastPage = ref(1);
const tournamentPerPage = 10;
const tournamentHasMore = computed(() => tournamentCurrentPage.value < tournamentLastPage.value);
const tournamentOverview = ref(null);

// Matches state
const matches = ref([]);
const matchesLoading = ref(false);
const matchesLoadingMore = ref(false);
const matchesError = ref('');
const matchesCurrentPage = ref(1);
const matchesLastPage = ref(1);
const matchesPerPage = 20;
const matchesHasMore = computed(() => matchesCurrentPage.value < matchesLastPage.value);

// Mini tournament grouped
const miniCurrent = computed(() =>
  miniTournaments.value.find(m => !m.is_completed) || null
);

const miniCompleted = computed(() =>
  miniTournaments.value.filter(m => m.is_completed)
);

// Tab counts
const tabCounts = computed(() => ({
  mini_tournament: miniTournaments.value.length,
  match: matches.value.length,
  tournament: tournaments.value.length,
}));

// --- Mini Tournament API ---
const fetchMiniTournaments = async () => {
  miniLoading.value = true;
  miniError.value = '';

  try {
    const res = await userService.getMiniTournaments({
      user_id: props.userId,
      per_page: 100,
    });

    const data = res.data?.data || res.data || {};
    const list = Array.isArray(data.mini_tournaments) ? data.mini_tournaments : [];

    // Prepend current mini tournament if any
    const current = data.current_mini_tournament || null;
    if (current) {
      miniTournaments.value = [current, ...list];
    } else {
      miniTournaments.value = list;
    }
    miniOverview.value = data.overview || null;
  } catch (e) {
    const status = e.response?.status;
    if (status === 401) {
      miniError.value = 'Phiên đăng nhập hết hạn, vui lòng đăng nhập lại.';
    } else {
      miniError.value = e.response?.data?.message || e.response?.data?.error || 'Không thể tải kèo đấu';
    }
    toast.error(miniError.value);
  } finally {
    miniLoading.value = false;
  }
};

// --- Tournament API ---
const fetchTournaments = async (page = 1) => {
  if (page === 1) {
    tournamentLoading.value = true;
    tournamentError.value = '';
  } else {
    tournamentLoadingMore.value = true;
  }

  try {
    const res = await userService.getTournaments({
      user_id: props.userId,
      per_page: tournamentPerPage,
      page,
    });

    const data = res.data?.data || res.data || {};
    const list = Array.isArray(data.tournaments) ? data.tournaments : [];
    const meta = data.meta || {};

    if (page === 1) {
      tournaments.value = list;
      tournamentOverview.value = data.overview || null;
    } else {
      tournaments.value.push(...list);
    }

    tournamentLastPage.value = meta.last_page || 1;
    tournamentCurrentPage.value = meta.current_page || 1;
  } catch (e) {
    const status = e.response?.status;
    if (status === 401) {
      tournamentError.value = 'Phiên đăng nhập hết hạn, vui lòng đăng nhập lại.';
    } else {
      tournamentError.value = e.response?.data?.message || e.response?.data?.error || 'Không thể tải giải đấu';
    }
    toast.error(tournamentError.value);
  } finally {
    tournamentLoading.value = false;
    tournamentLoadingMore.value = false;
  }
};

const loadMoreTournaments = () => {
  if (!tournamentHasMore.value || tournamentLoadingMore.value) return;
  fetchTournaments(tournamentCurrentPage.value + 1);
};

// --- Matches API ---
const fetchMatches = async (page = 1) => {
  if (page === 1) {
    matchesLoading.value = true;
    matchesError.value = '';
  } else {
    matchesLoadingMore.value = true;
  }

  try {
    const res = await userService.getMatches({
      user_id: props.userId,
      per_page: matchesPerPage,
      page,
    });

    const data = res.data?.data?.matches
      ? res.data.data
      : res.data?.matches
      ? res.data
      : res.data?.data || {};
    const list = Array.isArray(data.matches) ? data.matches : [];
    const meta = data.meta || {};

    if (page === 1) {
      matches.value = list;
    } else {
      matches.value.push(...list);
    }

    matchesLastPage.value = meta.last_page || 1;
    matchesCurrentPage.value = meta.current_page || 1;
  } catch (e) {
    const status = e.response?.status;
    if (status === 401) {
      matchesError.value = 'Phiên đăng nhập hết hạn, vui lòng đăng nhập lại.';
    } else {
      matchesError.value = e.response?.data?.message || e.response?.data?.error || 'Không thể tải trận đấu';
    }
    toast.error(matchesError.value);
  } finally {
    matchesLoading.value = false;
    matchesLoadingMore.value = false;
  }
};

const loadMoreMatches = () => {
  if (!matchesHasMore.value || matchesLoadingMore.value) return;
  fetchMatches(matchesCurrentPage.value + 1);
};

// --- Tab switching ---
const switchTab = (tabKey) => {
  if (activeTab.value === tabKey) return;
  activeTab.value = tabKey;

  if (tabKey === 'mini_tournament' && miniTournaments.value.length === 0) {
    fetchMiniTournaments();
  } else if (tabKey === 'tournament' && tournaments.value.length === 0) {
    fetchTournaments(1);
  } else if (tabKey === 'match' && matches.value.length === 0) {
    fetchMatches(1);
  }
};

// --- Click handlers ---
const handleMiniTournamentClick = (miniTournament) => {};
const handleTournamentClick = (tournament) => {};
const handleMatchClick = (match) => {};

// --- Watch userId ---
watch(
  () => props.userId,
  (newId) => {
    if (!newId) return;
    miniTournaments.value = [];
    tournaments.value = [];
    matches.value = [];
    miniOverview.value = null;
    tournamentOverview.value = null;
    tournamentCurrentPage.value = 1;
    matchesCurrentPage.value = 1;

    if (activeTab.value === 'mini_tournament') {
      fetchMiniTournaments();
    } else if (activeTab.value === 'tournament') {
      fetchTournaments(1);
    } else {
      fetchMatches(1);
    }
  },
  { immediate: true }
);
</script>
