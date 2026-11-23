<template>
  <div class="min-h-screen bg-[#F7F8FA] text-[#3E414C] pb-24">
    <!-- Header -->
    <div class="sticky top-0 z-20 bg-white border-b border-[#DCDEE6]">
      <div class="max-w-[900px] mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-gray-100"
            @click="router.push('/tools')"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <div>
            <h1 class="text-[18px] font-bold">Trọng tài</h1>
            <p class="text-[12px] text-[#8C929B]">Chọn giải đấu đang diễn ra để xem hàng đợi trận</p>
          </div>
        </div>
        <button
          v-if="tournaments.length > 0"
          type="button"
          class="text-[13px] font-semibold text-[#1D5FD1]"
          @click="toggleSelectAll"
        >
          {{ allSelected ? 'Bỏ chọn hết' : 'Chọn tất cả' }}
        </button>
      </div>
    </div>

    <div class="max-w-[900px] mx-auto px-4 pt-5 space-y-6">
      <div v-if="loading" class="py-12 text-center text-sm text-[#8C929B]">
        Đang tải danh sách giải đấu...
      </div>

      <template v-else>
        <div v-if="tournaments.length > 0" class="space-y-3">
          <div
            v-for="t in tournaments"
            :key="t.id"
            @click="toggleTournament(t.id)"
            class="bg-white rounded-2xl border p-4 flex items-center gap-3.5 cursor-pointer transition-all"
            :class="selectedIds.includes(t.id) ? 'border-[#0B7A4B] ring-1 ring-[#0B7A4B]/30' : 'border-[#DCDEE6]'"
          >
            <input
              type="checkbox"
              :checked="selectedIds.includes(t.id)"
              class="w-5 h-5 accent-[#0B7A4B] rounded shrink-0"
              @click.stop="toggleTournament(t.id)"
            />
            <img
              v-if="t.poster_url"
              :src="t.poster_url"
              alt="poster"
              class="w-12 h-12 rounded-xl object-cover shrink-0 border border-[#EDEFF3]"
            />
            <div v-else class="w-12 h-12 rounded-xl bg-[#EAF1FE] text-[#1D5FD1] font-bold flex items-center justify-center shrink-0">
              {{ t.name.slice(0, 2).toUpperCase() }}
            </div>

            <div class="flex-1 min-w-0">
              <p class="text-[15px] font-bold text-[#1F2937] truncate">{{ t.name }}</p>
              <p class="text-[12.5px] text-[#6B7280] truncate">
                {{ t.roles.join(' · ') }}
                <span v-if="t.location"> · {{ t.location }}</span>
              </p>
              <p v-if="t.my_courts.length > 0" class="text-[12.5px] font-semibold text-[#0B7A4B] mt-0.5">
                Bạn phụ trách {{ t.my_courts.map(c => c.name).join(', ') }} · {{ t.waiting_count }} trận chờ
              </p>
              <p v-else class="text-[12.5px] font-medium text-[#B45309] mt-0.5">
                Chưa được gán sân
              </p>
            </div>
          </div>

          <button
            type="button"
            :disabled="selectedIds.length === 0"
            class="w-full py-3 rounded-2xl font-bold text-[14.5px] transition-colors"
            :class="selectedIds.length > 0
              ? 'bg-[#D72D36] text-white hover:bg-[#b91c1c]'
              : 'bg-gray-200 text-gray-500 cursor-not-allowed'"
            @click="goToMatches"
          >
            {{ selectedIds.length > 0 ? `Xem trận (${selectedIds.length} giải)` : 'Chọn ít nhất 1 giải' }}
          </button>
        </div>

        <div v-else class="bg-white rounded-2xl border border-[#DCDEE6] p-5 text-center space-y-2">
          <p class="text-[14px] font-semibold">Bạn chưa thuộc BTC giải đấu nào đang diễn ra</p>
          <p class="text-[12.5px] text-[#8C929B]">Bạn vẫn có thể tạo trận ngoài giải để bắt điểm ngay bên dưới.</p>
        </div>

        <!-- Free match block -->
        <div class="bg-white rounded-2xl border border-[#DCDEE6] p-4 flex items-center justify-between gap-3">
          <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-[#8C929B]">Trận ngoài giải</p>
            <p class="text-[15px] font-bold text-[#1F2937] mt-0.5">Tạo trận mới</p>
            <p class="text-[12.5px] text-[#6B7280]">Bắt một trận bất kỳ, không gắn giải, không tính điểm trình</p>
          </div>
          <button
            type="button"
            class="px-4 py-2 rounded-xl bg-[#1D5FD1] text-white text-[13px] font-semibold shrink-0 hover:bg-[#174eb0]"
            @click="router.push('/tools/free-matches/create')"
          >
            Tạo trận mới
          </button>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getRefereeTournaments } from '@/service/tournamentCourt.js';

const STORAGE_KEY = 'picki_referee_selected_tournaments';
const route = useRoute();
const router = useRouter();

const loading = ref(true);
const tournaments = ref([]);
const selectedIds = ref([]);

const todayKey = () => new Date().toISOString().slice(0, 10);

const allSelected = computed(
  () => tournaments.value.length > 0 && selectedIds.value.length === tournaments.value.length
);

const toggleTournament = (id) => {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((x) => x !== id);
  } else {
    selectedIds.value = [...selectedIds.value, id];
  }
};

const toggleSelectAll = () => {
  selectedIds.value = allSelected.value ? [] : tournaments.value.map((t) => t.id);
};

const goToMatches = () => {
  if (selectedIds.value.length === 0) return;
  localStorage.setItem(STORAGE_KEY, JSON.stringify({
    date: todayKey(),
    ids: selectedIds.value,
  }));
  router.push({
    path: '/tools/referee/matches',
    query: { tournament_ids: selectedIds.value.join(',') },
  });
};

onMounted(async () => {
  try {
    const savedRaw = localStorage.getItem(STORAGE_KEY);
    const saved = savedRaw ? JSON.parse(savedRaw) : null;

    // Auto-forward if same day and not explicitly changing
    if (!route.query.change && saved && saved.date === todayKey() && Array.isArray(saved.ids) && saved.ids.length > 0) {
      router.replace({
        path: '/tools/referee/matches',
        query: { tournament_ids: saved.ids.join(',') },
      });
      return;
    }

    const list = await getRefereeTournaments();
    tournaments.value = Array.isArray(list) ? list : [];

    if (saved && saved.date === todayKey() && Array.isArray(saved.ids)) {
      const validSet = new Set(tournaments.value.map((t) => t.id));
      selectedIds.value = saved.ids.filter((id) => validSet.has(id));
    } else {
      // First time today: pre-select tournaments where user has assigned courts
      selectedIds.value = tournaments.value
        .filter((t) => t.my_courts && t.my_courts.length > 0)
        .map((t) => t.id);
    }
  } finally {
    loading.value = false;
  }
});
</script>
