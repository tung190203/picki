<template>
  <div>
    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div
        v-for="i in 3"
        :key="i"
        class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 animate-pulse"
      >
        <div class="flex items-start justify-between gap-3 mb-3">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-gray-200"></div>
            <div>
              <div class="h-3 w-32 bg-gray-200 rounded mb-1"></div>
              <div class="h-2 w-20 bg-gray-200 rounded"></div>
            </div>
          </div>
          <div class="h-5 w-10 bg-gray-200 rounded"></div>
        </div>
        <div class="flex items-center justify-between gap-3">
          <div class="flex-1">
            <div class="h-2 w-16 bg-gray-200 rounded mb-1"></div>
            <div class="h-4 w-24 bg-gray-200 rounded"></div>
          </div>
          <div class="h-8 w-16 bg-gray-200 rounded"></div>
          <div class="flex-1 flex justify-end">
            <div class="h-4 w-24 bg-gray-200 rounded"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div
      v-else-if="error"
      class="flex flex-col items-center justify-center py-8 text-center"
    >
      <ExclamationCircleIcon class="w-10 h-10 text-red-400 mb-2" />
      <p class="text-sm text-gray-500 mb-3">{{ error }}</p>
      <button
        @click="fetchHistory"
        class="text-sm text-[#4392E0] hover:underline"
      >
        Thử lại
      </button>
    </div>

    <!-- Empty -->
    <div
      v-else-if="matches.length === 0"
      class="flex flex-col items-center justify-center py-8 text-center"
    >
      <TrophyIcon class="w-10 h-10 text-gray-300 mb-2" />
      <p class="text-sm text-gray-500">Chưa có trận đấu nào</p>
    </div>

    <!-- Match list -->
    <div v-else class="space-y-3">
      <MatchHistoryCard
        v-for="match in matches"
        :key="`${match.type}-${match.id}`"
        :match="match"
        @click="handleMatchClick(match)"
      />

      <!-- Load more -->
      <div v-if="hasMore" class="flex justify-center pt-2">
        <button
          @click="loadMore"
          :disabled="loadingMore"
          class="px-6 py-2 text-sm text-[#4392E0] border border-[#4392E0] rounded-full hover:bg-blue-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loadingMore ? 'Đang tải...' : 'Xem thêm' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { ExclamationCircleIcon, TrophyIcon } from '@heroicons/vue/24/outline';
import { toast } from 'vue3-toastify';
import { matchHistoryService } from '@/service/matchHistory.js';
import MatchHistoryCard from '@/components/molecules/MatchHistoryCard.vue';

const props = defineProps({
  userId: {
    type: [Number, String],
    required: true,
  },
});

const matches = ref([]);
const loading = ref(false);
const loadingMore = ref(false);
const error = ref('');
const currentPage = ref(1);
const lastPage = ref(1);
const perPage = 10;

const hasMore = ref(false);

const fetchHistory = async (page = 1) => {
  if (page === 1) {
    loading.value = true;
    error.value = '';
  } else {
    loadingMore.value = true;
  }

  try {
    const res = await matchHistoryService.getList({
      user_id: props.userId,
      per_page: perPage,
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

    lastPage.value = meta.last_page || 1;
    currentPage.value = meta.current_page || 1;
    hasMore.value = currentPage.value < lastPage.value;
  } catch (e) {
    const status = e.response?.status;
    if (status === 401) {
      error.value = 'Phiên đăng nhập hết hạn, vui lòng đăng nhập lại.';
    } else {
      error.value = e.response?.data?.message || e.response?.data?.error || 'Không thể tải lịch sử đấu';
    }
    toast.error(error.value);
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
};

const loadMore = () => {
  if (!hasMore.value || loadingMore.value) return;
  fetchHistory(currentPage.value + 1);
};

const handleMatchClick = (match) => {
  // Navigate based on match type
  // Future: implement navigation to match detail page
};

watch(
  () => props.userId,
  (newId) => {
    if (newId) {
      currentPage.value = 1;
      matches.value = [];
      fetchHistory(1);
    }
  },
  { immediate: true }
);
</script>
