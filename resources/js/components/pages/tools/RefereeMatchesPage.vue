<template>
  <div class="min-h-screen bg-[#F7F8FA] text-[#3E414C] pb-24">
    <!-- Header -->
    <div class="sticky top-0 z-20 bg-white border-b border-[#DCDEE6]">
      <div class="max-w-[960px] mx-auto px-4 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <button
            type="button"
            class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-gray-100 shrink-0"
            @click="router.push('/tools')"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <div class="min-w-0">
            <h1 class="text-[18px] font-bold">Trận của tôi</h1>
            <p class="text-[12px] text-[#6B7280] truncate">
              {{ mergedRolesLabel }} · {{ data.tournaments.length }} giải đã chọn ·
              <button type="button" class="text-[#1D5FD1] font-semibold underline" @click="changeTournaments">
                Đổi
              </button>
            </p>
          </div>
        </div>

        <button
          type="button"
          class="px-3 py-1.5 rounded-lg text-[12.5px] font-semibold bg-[#F1F3F6] hover:bg-gray-200 shrink-0"
          @click="fetchMatches"
        >
          Làm mới
        </button>
      </div>

      <!-- Court Chips -->
      <div class="max-w-[960px] mx-auto px-4 py-2 border-t border-[#EDEFF3] flex items-center gap-2 overflow-x-auto">
        <button
          type="button"
          @click="selectedCourtName = '__ALL__'"
          class="px-3 py-1.5 rounded-full text-[12.5px] font-semibold whitespace-nowrap border transition-colors"
          :class="selectedCourtName === '__ALL__'
            ? 'bg-[#1F2937] text-white border-[#1F2937]'
            : 'bg-white text-[#3E414C] border-[#DCDEE6]'"
        >
          Tất cả sân ({{ countForCourt('__ALL__') }})
        </button>

        <button
          v-for="court in data.courts"
          :key="court.name"
          type="button"
          @click="selectedCourtName = court.name"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12.5px] font-semibold whitespace-nowrap border transition-colors"
          :class="selectedCourtName === court.name
            ? 'bg-[#0B7A4B] text-white border-[#0B7A4B]'
            : 'bg-white text-[#3E414C] border-[#DCDEE6]'"
        >
          <span>{{ court.name }}</span>
          <span
            v-if="court.mine"
            class="px-1.5 py-px rounded text-[10px] font-bold"
            :class="selectedCourtName === court.name ? 'bg-white/20 text-white' : 'bg-[#E7F6EE] text-[#0B7A4B]'"
          >
            Của bạn
          </span>
          <span class="opacity-80">({{ countForCourt(court.name) }})</span>
        </button>

        <button
          v-if="hasAnyCanMove && unassignedCount > 0"
          type="button"
          @click="selectedCourtName = '__UNASSIGNED__'"
          class="px-3 py-1.5 rounded-full text-[12.5px] font-semibold whitespace-nowrap border transition-colors"
          :class="selectedCourtName === '__UNASSIGNED__'
            ? 'bg-[#D97706] text-white border-[#D97706]'
            : 'bg-[#FFFBEB] text-[#B45309] border-[#FCD34D]'"
        >
          Chưa xếp sân ({{ unassignedCount }})
        </button>
      </div>

      <!-- Tournament Chips (when >= 2 tournaments) -->
      <div
        v-if="data.tournaments.length >= 2"
        class="max-w-[960px] mx-auto px-4 py-2 border-t border-[#EDEFF3] flex items-center gap-2 overflow-x-auto"
      >
        <button
          type="button"
          @click="selectedTournamentId = 'ALL'"
          class="px-3 py-1 rounded-full text-[12px] font-semibold whitespace-nowrap border"
          :class="selectedTournamentId === 'ALL'
            ? 'bg-[#1D5FD1] text-white border-[#1D5FD1]'
            : 'bg-white text-[#6B7280] border-[#DCDEE6]'"
        >
          Tất cả giải ({{ countForTournament('ALL') }})
        </button>
        <button
          v-for="t in data.tournaments"
          :key="t.id"
          type="button"
          @click="selectedTournamentId = t.id"
          class="px-3 py-1 rounded-full text-[12px] font-semibold whitespace-nowrap border"
          :class="selectedTournamentId === t.id
            ? 'bg-[#1D5FD1] text-white border-[#1D5FD1]'
            : 'bg-white text-[#3E414C] border-[#DCDEE6]'"
        >
          {{ t.short }} ({{ countForTournament(t.id) }})
        </button>
      </div>
    </div>

    <!-- Content -->
    <div class="max-w-[960px] mx-auto px-4 pt-4 space-y-5">
      <div v-if="loading" class="py-16 text-center text-sm text-[#8C929B]">
        Đang tải danh sách trận...
      </div>

      <template v-else>
        <!-- LIVE -->
        <section v-if="liveMatches.length > 0" class="space-y-2.5">
          <h2 class="text-[12px] font-bold uppercase tracking-wider text-[#D72D36] px-1">
            ● Đang đấu ({{ liveMatches.length }})
          </h2>
          <div
            v-for="m in liveMatches"
            :key="m.id"
            class="bg-white rounded-2xl border border-[#DCDEE6] p-4 space-y-3 shadow-sm"
          >
            <div class="flex items-center justify-between gap-2 text-[12px]">
              <div class="flex items-center gap-2 min-w-0">
                <span class="px-2 py-0.5 rounded font-bold text-[11px]" :class="tourBadgeClass(m.tournament_id)">
                  {{ tournamentShort(m.tournament_id) }}
                </span>
                <span class="font-semibold text-[#3E414C]">{{ m.court_name || 'Chưa xếp sân' }}</span>
                <span class="text-[#8C929B]">· {{ m.round }}</span>
              </div>
              <span class="px-2 py-0.5 rounded-full bg-red-50 text-[#D72D36] font-bold text-[11px]">● Đang đấu</span>
            </div>

            <div class="flex items-center justify-between gap-4">
              <div class="space-y-1 min-w-0 flex-1">
                <p class="text-[14.5px] font-bold truncate">{{ m.team_a }}</p>
                <p class="text-[14.5px] font-bold truncate">{{ m.team_b }}</p>
              </div>
              <div class="text-right font-extrabold text-[18px] tabular-nums space-y-0.5">
                <p>{{ m.score?.[0] ?? 0 }}</p>
                <p>{{ m.score?.[1] ?? 0 }}</p>
              </div>
            </div>

            <div class="pt-2.5 border-t border-[#EDEFF3] flex items-center justify-between gap-2 text-[12.5px]">
              <span class="text-[#6B7280]">
                Trọng tài: <strong>{{ m.referee?.name || '—' }}</strong>
              </span>
              <div class="flex items-center gap-2">
                <button
                  v-if="isAdminInTournament(m.tournament_id) && m.referee?.user_id !== currentUserId"
                  type="button"
                  class="px-3 py-1.5 rounded-xl bg-[#F1F3F6] text-[#3E414C] font-semibold"
                  @click="openReassignModal(m)"
                >
                  Đổi trọng tài
                </button>
                <button
                  v-if="m.referee?.user_id === currentUserId"
                  type="button"
                  class="px-3.5 py-1.5 rounded-xl bg-[#D72D36] text-white font-semibold"
                  @click="openMatchDetailScoring(m)"
                >
                  Tiếp tục ›
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- WAITING -->
        <section class="space-y-2.5">
          <h2 class="text-[12px] font-bold uppercase tracking-wider text-[#6B7280] px-1">
            Chờ đấu ({{ waitingMatches.length }})
          </h2>

          <div v-if="waitingMatches.length === 0" class="bg-white rounded-2xl border border-[#DCDEE6] p-6 text-center text-[13px] text-[#8C929B]">
            Không có trận chờ đấu.
          </div>

          <div
            v-for="m in waitingMatches"
            :key="m.id"
            class="bg-white rounded-2xl border border-[#DCDEE6] p-4 space-y-3 transition-opacity"
            :class="{ 'opacity-60': !!m.busy }"
          >
            <div class="flex items-center justify-between gap-2 text-[12px]">
              <div class="flex items-center gap-2 min-w-0">
                <span class="px-2 py-0.5 rounded font-bold text-[11px]" :class="tourBadgeClass(m.tournament_id)">
                  {{ tournamentShort(m.tournament_id) }}
                </span>
                <span class="font-semibold">{{ m.court_name || 'Chưa xếp sân' }}</span>
                <span v-if="m.scheduled_at" class="text-[#8C929B]">· {{ m.scheduled_at }}</span>
                <span class="text-[#8C929B]">· {{ m.round }}</span>
              </div>
              <span
                v-if="m.is_next && selectedCourtName !== '__ALL__' && selectedCourtName !== '__UNASSIGNED__'"
                class="px-2 py-0.5 rounded-full bg-[#E7F6EE] text-[#0B7A4B] font-bold text-[11px]"
              >
                Kế tiếp
              </span>
            </div>

            <div class="space-y-1">
              <p class="text-[14.5px] font-bold">{{ m.team_a }}</p>
              <p class="text-[14.5px] font-bold">{{ m.team_b }}</p>
            </div>

            <div v-if="m.busy" class="text-[12px] font-semibold text-[#B45309] bg-[#FFFBEB] px-2.5 py-1.5 rounded-lg">
              {{ m.busy.player }} đang đấu ở {{ m.busy.court_name }}
            </div>

            <div class="pt-2.5 border-t border-[#EDEFF3] flex items-center justify-between gap-2 text-[12.5px]">
              <span class="text-[#6B7280]">
                Phụ trách:
                <strong :class="m.court_referee ? '' : 'text-[#B45309]'">
                  {{ m.court_referee || 'Chưa gán TT' }}
                </strong>
              </span>

              <div class="flex items-center gap-2">
                <button
                  v-if="m.can_move"
                  type="button"
                  class="px-3 py-1.5 rounded-xl bg-[#F1F3F6] text-[#3E414C] font-semibold"
                  @click="openMoveModal(m)"
                >
                  ⇄ Chuyển
                </button>
                <button
                  v-if="m.can_claim"
                  type="button"
                  class="px-3.5 py-1.5 rounded-xl bg-[#D72D36] text-white font-semibold"
                  @click="handleClaim(m)"
                >
                  Bắt trận này ›
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- DONE TODAY -->
        <section v-if="doneMatches.length > 0" class="space-y-2.5">
          <h2 class="text-[12px] font-bold uppercase tracking-wider text-[#0B7A4B] px-1">
            Đã xong hôm nay ({{ doneMatches.length }})
          </h2>
          <div
            v-for="m in doneMatches"
            :key="m.id"
            class="bg-white rounded-2xl border border-[#DCDEE6] p-4 space-y-2"
          >
            <div class="flex items-center justify-between text-[12px]">
              <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded font-bold text-[11px]" :class="tourBadgeClass(m.tournament_id)">
                  {{ tournamentShort(m.tournament_id) }}
                </span>
                <span>{{ m.court_name || '—' }} · {{ m.round }}</span>
              </div>
              <span class="text-[#0B7A4B] font-bold">Đã xong</span>
            </div>
            <div class="flex items-center justify-between text-[14px] font-semibold">
              <div>
                <p>{{ m.team_a }}</p>
                <p>{{ m.team_b }}</p>
              </div>
              <div class="text-right font-bold tabular-nums">
                <p>{{ m.score?.[0] ?? 0 }}</p>
                <p>{{ m.score?.[1] ?? 0 }}</p>
              </div>
            </div>
            <p class="text-[12px] text-[#6B7280]">Trọng tài: {{ m.referee?.name || '—' }}</p>
          </div>
        </section>

        <!-- Bottom: + Thêm trận vào Sân X -->
        <button
          v-if="canAddToSelectedCourt"
          type="button"
          class="w-full py-3 rounded-2xl border border-dashed border-[#0B7A4B] text-[#0B7A4B] bg-[#E7F6EE]/50 hover:bg-[#E7F6EE] font-semibold text-[13.5px]"
          @click="openPullModal"
        >
          + Thêm trận vào {{ selectedCourtName }}
        </button>
      </template>
    </div>

    <!-- Modal: ⇄ Chuyển trận -->
    <div v-if="moveModal.open && moveModal.match" class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center p-4">
      <div class="bg-white rounded-2xl max-w-md w-full p-5 space-y-4 shadow-xl max-h-[85vh] overflow-y-auto">
        <div>
          <h3 class="text-[16px] font-bold">Chuyển trận</h3>
          <p class="text-[12.5px] text-[#6B7280]">
            {{ moveModal.match.team_a }} vs {{ moveModal.match.team_b }}
          </p>
        </div>

        <div class="space-y-1.5">
          <button
            v-if="moveModal.match.court_id"
            type="button"
            class="w-full py-2.5 px-3 rounded-xl bg-[#EAF1FE] text-[#1D5FD1] text-[13px] font-semibold text-left"
            @click="submitMove(moveModal.match, moveModal.match.court_id, true)"
          >
            ↑ Đưa lên đầu hàng ({{ moveModal.match.court_name }})
          </button>

          <button
            v-for="c in moveModal.courts"
            :key="c.id"
            type="button"
            :disabled="c.id === moveModal.match.court_id"
            class="w-full py-2.5 px-3 rounded-xl border text-left flex items-center justify-between text-[13px]"
            :class="c.id === moveModal.match.court_id
              ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'
              : 'border-[#DCDEE6] hover:bg-gray-50'"
            @click="submitMove(moveModal.match, c.id, false)"
          >
            <span>
              <strong>{{ c.name }}</strong>
              <span class="text-[#6B7280]"> · {{ c.referee?.name || 'Chưa có TT' }}</span>
            </span>
            <span v-if="c.id === moveModal.match.court_id" class="text-[11px] font-semibold">Hiện tại</span>
          </button>

          <button
            v-if="moveModal.match.court_id"
            type="button"
            class="w-full py-2.5 px-3 rounded-xl bg-[#FFFBEB] text-[#B45309] text-[13px] font-semibold text-left"
            @click="submitMove(moveModal.match, null, false)"
          >
            Bỏ xếp sân (đưa về Chưa xếp sân)
          </button>
        </div>

        <div class="flex justify-end">
          <button type="button" class="px-4 py-2 rounded-xl text-[13px] text-[#6B7280]" @click="moveModal.open = false">Đóng</button>
        </div>
      </div>
    </div>

    <!-- Modal: Đổi trọng tài -->
    <div v-if="reassignModal.open && reassignModal.match" class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center p-4">
      <div class="bg-white rounded-2xl max-w-md w-full p-5 space-y-4 shadow-xl">
        <h3 class="text-[16px] font-bold">Đổi trọng tài trận đang đấu</h3>
        <div v-if="reassignModal.loading" class="text-[13px] text-[#8C929B] py-4 text-center">Đang tải...</div>
        <div v-else-if="reassignModal.referees.length === 0" class="text-[13px] text-[#8C929B] py-4 text-center">
          Không có trọng tài khác trong giải.
        </div>
        <div v-else class="space-y-1.5 max-h-64 overflow-y-auto">
          <div
            v-for="r in reassignModal.referees"
            :key="r.user_id"
            class="flex items-center justify-between py-2 px-3 rounded-xl border border-[#DCDEE6]"
          >
            <span class="text-[13.5px] font-semibold">{{ r.name }}</span>
            <button
              type="button"
              class="px-3 py-1 rounded-lg bg-[#D72D36] text-white text-[12px] font-semibold"
              @click="submitReassign(r.user_id)"
            >
              Giao
            </button>
          </div>
        </div>
        <div class="flex justify-end">
          <button type="button" class="px-4 py-2 rounded-xl text-[13px] text-[#6B7280]" @click="reassignModal.open = false">Đóng</button>
        </div>
      </div>
    </div>

    <!-- Modal: + Thêm trận vào Sân X -->
    <div v-if="pullModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center p-4">
      <div class="bg-white rounded-2xl max-w-md w-full p-5 space-y-4 shadow-xl max-h-[85vh] overflow-y-auto">
        <h3 class="text-[16px] font-bold">Thêm trận vào {{ selectedCourtName }}</h3>
        <div v-if="pullableMatches.length === 0" class="text-[13px] text-[#8C929B] py-6 text-center">
          Không còn trận chờ nào ở sân khác hoặc chưa xếp sân.
        </div>
        <div v-else class="space-y-2">
          <div
            v-for="m in pullableMatches"
            :key="m.id"
            class="p-3 rounded-xl border border-[#DCDEE6] flex items-center justify-between gap-3"
          >
            <div class="min-w-0">
              <p class="text-[11.5px] text-[#6B7280]">
                {{ m.court_name || 'Chưa xếp sân' }} · {{ tournamentShort(m.tournament_id) }}
              </p>
              <p class="text-[13px] font-bold truncate">{{ m.team_a }} vs {{ m.team_b }}</p>
            </div>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg bg-[#0B7A4B] text-white text-[12px] font-semibold shrink-0"
              @click="pullMatchToCurrentCourt(m)"
            >
              Về {{ selectedCourtName }}
            </button>
          </div>
        </div>
        <div class="flex justify-end">
          <button type="button" class="px-4 py-2 rounded-xl text-[13px] text-[#6B7280]" @click="pullModalOpen = false">Đóng</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { toast } from 'vue3-toastify';
import { useUserStore } from '@/store/auth';
import {
  getRefereeMatches,
  getTournamentCourts,
  claimMatch,
  moveMatch,
  reassignMatch,
} from '@/service/tournamentCourt.js';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const { getUser } = storeToRefs(userStore);
const currentUserId = computed(() => Number(getUser.value?.id || 0));

const loading = ref(true);
const data = ref({ tournaments: [], courts: [], matches: [] });
const selectedCourtName = ref('__ALL__');
const selectedTournamentId = ref('ALL');

const moveModal = ref({ open: false, match: null, courts: [] });
const reassignModal = ref({ open: false, match: null, loading: false, referees: [] });
const pullModalOpen = ref(false);

let pollTimer = null;

const tournamentIds = computed(() =>
  String(route.query.tournament_ids || '')
    .split(',')
    .map((x) => Number(x.trim()))
    .filter(Boolean)
);

const mergedRolesLabel = computed(() => {
  const set = new Set();
  data.value.tournaments.forEach((t) => (t.roles || []).forEach((r) => set.add(r)));
  return Array.from(set).join(' · ') || 'Trọng tài';
});

const hasAnyCanMove = computed(() => data.value.tournaments.some((t) => t.can_move));

const isAdminInTournament = (tId) =>
  Boolean(data.value.tournaments.find((t) => t.id === tId)?.can_move);

const tournamentShort = (tId) =>
  data.value.tournaments.find((t) => t.id === tId)?.short || '';

const tourBadgeClass = (tId) => {
  const color = data.value.tournaments.find((t) => t.id === tId)?.color || 'c1';
  const map = {
    c1: 'bg-[#EAF1FE] text-[#1D5FD1]',
    c2: 'bg-[#E7F6EE] text-[#0B7A4B]',
    c3: 'bg-[#FDECEE] text-[#C8102E]',
    c4: 'bg-[#FFFBEB] text-[#B45309]',
    c5: 'bg-purple-50 text-purple-700',
    c6: 'bg-cyan-50 text-cyan-700',
  };
  return map[color] || map.c1;
};

const remainingMatches = computed(() =>
  data.value.matches.filter((m) => m.status !== 'done')
);

const unassignedCount = computed(() =>
  remainingMatches.value.filter((m) => !m.court_id).length
);

const countForCourt = (courtName) => {
  return remainingMatches.value.filter((m) => {
    if (selectedTournamentId.value !== 'ALL' && m.tournament_id !== selectedTournamentId.value) return false;
    if (courtName === '__ALL__') return true;
    return m.court_name === courtName;
  }).length;
};

const countForTournament = (tId) => {
  return remainingMatches.value.filter((m) => {
    if (selectedCourtName.value === '__UNASSIGNED__' && m.court_id) return false;
    if (selectedCourtName.value !== '__ALL__' && selectedCourtName.value !== '__UNASSIGNED__' && m.court_name !== selectedCourtName.value) return false;
    if (tId === 'ALL') return true;
    return m.tournament_id === tId;
  }).length;
};

const filteredMatches = computed(() =>
  data.value.matches.filter((m) => {
    if (selectedTournamentId.value !== 'ALL' && m.tournament_id !== selectedTournamentId.value) return false;
    if (selectedCourtName.value === '__UNASSIGNED__') return !m.court_id;
    if (selectedCourtName.value !== '__ALL__') return m.court_name === selectedCourtName.value;
    return true;
  })
);

const liveMatches = computed(() => filteredMatches.value.filter((m) => m.status === 'live'));
const waitingMatches = computed(() => filteredMatches.value.filter((m) => m.status === 'waiting'));
const doneMatches = computed(() => filteredMatches.value.filter((m) => m.status === 'done'));

const selectedCourtObj = computed(() =>
  data.value.courts.find((c) => c.name === selectedCourtName.value) || null
);

const canAddToSelectedCourt = computed(() => {
  if (!selectedCourtObj.value) return false;
  if (selectedCourtObj.value.mine) return true;
  return Object.keys(selectedCourtObj.value.ids || {}).some((tId) => isAdminInTournament(Number(tId)));
});

const pullableMatches = computed(() => {
  if (!selectedCourtObj.value) return [];
  const courtIdsByTour = selectedCourtObj.value.ids || {};
  return data.value.matches.filter((m) => {
    if (m.status !== 'waiting') return false;
    if (m.court_name === selectedCourtObj.value.name) return false;
    return Boolean(courtIdsByTour[String(m.tournament_id)]);
  });
});

const fetchMatches = async (silent = false) => {
  if (tournamentIds.value.length === 0) {
    router.replace('/tools/referee?change=1');
    return;
  }
  if (!silent) loading.value = true;
  try {
    const res = await getRefereeMatches(tournamentIds.value);
    data.value = res;
    if (!silent && selectedCourtName.value === '__ALL__') {
      const firstMine = res.courts?.find((c) => c.mine);
      if (firstMine) selectedCourtName.value = firstMine.name;
    }
  } catch (e) {
    if (!silent) toast.error(e.response?.data?.message || 'Không tải được danh sách trận.');
  } finally {
    loading.value = false;
  }
};

const changeTournaments = () => {
  router.push('/tools/referee?change=1');
};

const handleClaim = async (m) => {
  try {
    await claimMatch(m.id);
    toast.success('Đã nhận bắt trận.');
    await fetchMatches(true);
    openMatchDetailScoring(m);
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể bắt trận.');
    await fetchMatches(true);
  }
};

const openMatchDetailScoring = (m) => {
  router.push(`/tournament-detail/${m.tournament_id}/bracket`);
};

const openMoveModal = async (m) => {
  try {
    const res = await getTournamentCourts(m.tournament_id);
    moveModal.value = { open: true, match: m, courts: res.courts || [] };
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không tải được danh sách sân.');
  }
};

const submitMove = async (m, courtId, toTop) => {
  try {
    await moveMatch(m.id, { court_id: courtId, to_top: toTop });
    moveModal.value.open = false;
    toast.success('Đã chuyển trận.');
    await fetchMatches(true);
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể chuyển trận.');
  }
};

const openReassignModal = async (m) => {
  reassignModal.value = { open: true, match: m, loading: true, referees: [] };
  try {
    const res = await getTournamentCourts(m.tournament_id);
    reassignModal.value.referees = (res.referees || []).filter(
      (r) => r.user_id !== m.referee?.user_id
    );
  } finally {
    reassignModal.value.loading = false;
  }
};

const submitReassign = async (refereeUserId) => {
  try {
    await reassignMatch(reassignModal.value.match.id, refereeUserId);
    reassignModal.value.open = false;
    toast.success('Đã đổi trọng tài.');
    await fetchMatches(true);
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể đổi trọng tài.');
  }
};

const openPullModal = () => {
  pullModalOpen.value = true;
};

const pullMatchToCurrentCourt = async (m) => {
  const destCourtId = selectedCourtObj.value?.ids?.[String(m.tournament_id)];
  if (!destCourtId) return;
  try {
    await moveMatch(m.id, { court_id: destCourtId, to_top: true });
    pullModalOpen.value = false;
    toast.success(`Đã đưa trận về ${selectedCourtObj.value.name}.`);
    await fetchMatches(true);
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể đưa trận về sân.');
  }
};

onMounted(async () => {
  await fetchMatches(false);
  pollTimer = setInterval(() => fetchMatches(true), 15000);
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>
