<template>
  <!--
    Bố cục web-first:
      - Desktop: 2 cột — cột trái danh sách giải, cột phải bảng trận.
      - Mobile: 1 cột, giải cuộn dọc, trận dạng bảng thu gọn.
    Màu dùng token light/dark riêng (không dùng class `bg-white` vì
    app.css có override `!important` cho dark mode).
  -->
  <div class="ls-page min-h-screen">
    <!-- ============ HEADER ============ -->
    <div class="ls-header sticky top-0 z-30 border-b backdrop-blur-md">
      <div class="max-w-[1440px] mx-auto px-4 sm:px-6 h-16 flex items-center gap-3">
        <button
          @click="goBack"
          class="w-8 h-8 flex items-center justify-center rounded-full transition-colors shrink-0 ls-icon-btn"
          aria-label="Quay lại"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>

        <div class="min-w-0">
          <h1 class="text-[18px] font-bold tracking-[-0.4px] leading-tight ls-text-primary">Livescore</h1>
          <p class="hidden sm:block text-[12px] ls-text-secondary leading-tight">
            Chọn trận rồi bấm <strong class="font-semibold ls-text-accent">Phát</strong> để chiếu lên màn hình lớn
          </p>
        </div>

        <div class="ml-auto flex items-center gap-2">
          <!-- Trạng thái realtime -->
          <span
            class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border transition-colors"
            :class="realtimeBadgeClass"
          >
            <i class="w-1.5 h-1.5 rounded-full" :class="realtimeDotClass"></i>
            {{ realtimeLabel }}
          </span>

          <button
            @click="refreshAll"
            :disabled="isRefreshing"
            class="w-8 h-8 flex items-center justify-center rounded-full transition-colors disabled:opacity-50 ls-icon-btn"
            title="Làm mới"
          >
            <svg class="w-[18px] h-[18px]" :class="{ 'animate-spin': isRefreshing }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 py-5 sm:py-6">
      <!-- Loading -->
      <div v-if="isLoading" class="py-20 flex flex-col items-center gap-3">
        <div class="w-8 h-8 rounded-full border-[3px] ls-spinner animate-spin"></div>
        <p class="text-[13px] ls-text-secondary">Đang tải danh sách giải...</p>
      </div>

      <!-- Lỗi -->
      <div v-else-if="errorMessage" class="py-20 flex flex-col items-center gap-2 text-center">
        <div class="w-12 h-12 rounded-full ls-soft-red flex items-center justify-center">
          <svg class="w-6 h-6 ls-text-danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
          </svg>
        </div>
        <p class="text-[14px] font-semibold ls-text-primary">Không tải được danh sách giải</p>
        <p class="text-[13px] ls-text-secondary">{{ errorMessage }}</p>
        <button class="mt-2 px-4 py-2 ls-btn-primary text-white text-[13px] font-semibold rounded-lg transition-colors"
          @click="load">Thử lại</button>
      </div>

      <!-- Rỗng -->
      <div v-else-if="tournaments.length === 0" class="py-20 flex flex-col items-center gap-2 text-center">
        <div class="w-12 h-12 rounded-full ls-soft-gray flex items-center justify-center">
          <svg class="w-6 h-6 ls-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
          </svg>
        </div>
        <p class="text-[14px] font-semibold ls-text-primary">Chưa có giải nào đang diễn ra</p>
        <p class="text-[13px] ls-text-secondary max-w-sm">
          Chỉ hiện các giải bạn đang tham gia hoặc phụ trách (BTC / trọng tài) và đang diễn ra.
        </p>
      </div>

      <!-- ============ NỘI DUNG ============ -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-[304px_minmax(0,1fr)] xl:grid-cols-[344px_minmax(0,1fr)] gap-4 sm:gap-5 items-start">

        <!-- ===== Cột trái: danh sách giải ===== -->
        <aside class="lg:sticky lg:top-[84px]">
          <div class="ls-card rounded-[14px] overflow-hidden shadow-sm">
            <div class="px-4 py-3 border-b ls-divider flex items-center justify-between">
              <h2 class="text-[12px] font-bold uppercase tracking-[0.8px] ls-text-secondary">
                Giải đang diễn ra
              </h2>
              <span class="text-[11px] font-bold ls-text-accent ls-soft-blue px-2 py-0.5 rounded-full">
                {{ tournaments.length }}
              </span>
            </div>

            <div class="max-h-[calc(100vh-190px)] overflow-y-auto">
              <button
                v-for="t in tournaments"
                :key="t.id"
                type="button"
                @click="selectTournament(t.id)"
                class="w-full text-left px-3.5 py-3 border-b last:border-b-0 transition-colors flex items-start gap-3"
                :class="selectedId === t.id ? 'ls-tournament-active' : 'ls-tournament-idle'"
              >
                <span class="relative shrink-0">
                  <img v-if="t.poster_url" :src="t.poster_url" alt=""
                    class="w-11 h-11 rounded-[9px] object-cover border ls-divider" />
                  <span v-else class="w-11 h-11 rounded-[9px] ls-soft-gray flex items-center justify-center">
                    <svg class="w-5 h-5 ls-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4 21V9l8-6 8 6v12M9 21v-6h6v6" />
                    </svg>
                  </span>
                  <span v-if="liveCount(t.id) > 0"
                    class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-[#E8192C] text-white text-[10px] font-bold flex items-center justify-center shadow">
                    {{ liveCount(t.id) }}
                  </span>
                </span>

                <span class="flex-1 min-w-0">
                  <span
                    class="block text-[13.5px] font-bold truncate"
                    :class="selectedId === t.id ? 'ls-text-accent' : 'ls-text-primary'"
                  >
                    {{ t.name }}
                  </span>
                  <span class="block text-[11.5px] ls-text-secondary truncate">
                    {{ t.location_name || 'Chưa cập nhật địa điểm' }}
                  </span>
                  <span class="block text-[11.5px] ls-text-muted truncate">
                    {{ dateRangeLabel(t) }}
                  </span>
                </span>
              </button>
            </div>
          </div>
        </aside>

        <!-- ===== Cột phải: trận của giải đang chọn ===== -->
        <section class="min-w-0">
          <!-- Thông tin giải -->
          <div v-if="selectedTournament" class="ls-card rounded-[14px] overflow-hidden shadow-sm mb-4">
            <div class="flex flex-col sm:flex-row">
              <img v-if="selectedTournament.poster_url" :src="selectedTournament.poster_url" alt=""
                class="w-full sm:w-[176px] h-[128px] sm:h-auto object-cover shrink-0" />
              <div class="flex-1 min-w-0 p-4 sm:p-5">
                <div class="flex items-start gap-3">
                  <div class="flex-1 min-w-0">
                    <h3 class="text-[17px] sm:text-[19px] font-bold tracking-[-0.3px] leading-snug ls-text-primary">
                      {{ selectedTournament.name }}
                    </h3>
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[12.5px] ls-text-secondary">
                      <span v-if="selectedTournament.location_name" class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                        </svg>
                        {{ selectedTournament.location_name }}
                      </span>
                      <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        {{ dateRangeLabel(selectedTournament) }}
                      </span>
                    </div>
                  </div>

                  <!-- Thống kê nhanh -->
                  <div class="flex shrink-0 gap-2">
                    <div class="text-center px-3 py-2 rounded-xl ls-soft-gray min-w-[64px]">
                      <p class="text-[18px] font-bold tabular-nums leading-none ls-live">{{ liveCount(selectedTournament.id) }}</p>
                      <p class="text-[10.5px] ls-text-secondary mt-1">đang đá</p>
                    </div>
                    <div class="text-center px-3 py-2 rounded-xl ls-soft-gray min-w-[64px]">
                      <p class="text-[18px] font-bold tabular-nums leading-none ls-text-primary">{{ getMatches(selectedTournament.id).length }}</p>
                      <p class="text-[10.5px] ls-text-secondary mt-1">trận</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Bộ lọc -->
          <div v-if="selectedTournament" class="ls-card rounded-[14px] shadow-sm p-3 mb-4 flex flex-col sm:flex-row gap-3 sm:items-center">
            <div class="relative flex-1">
              <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 ls-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <input
                v-model="keyword"
                type="text"
                placeholder="Tìm theo tên đội, tên người chơi hoặc vòng đấu..."
                class="ls-input w-full h-9 pl-9 pr-3 text-[13px] rounded-lg"
              />
            </div>

            <div class="flex items-center gap-1.5 overflow-x-auto">
              <button
                v-for="f in FILTERS"
                :key="f.key"
                type="button"
                @click="filter = f.key"
                class="px-3 h-9 rounded-lg text-[12.5px] font-semibold whitespace-nowrap transition-colors border"
                :class="filter === f.key ? 'ls-filter-on' : 'ls-filter-off'"
              >
                {{ f.label }}
                <span class="opacity-70 tabular-nums">{{ filterCount(f.key) }}</span>
              </button>
            </div>
          </div>

          <!-- Danh sách trận -->
          <div v-if="selectedTournament" class="ls-card rounded-[14px] shadow-sm overflow-hidden">
            <!-- Loading trận -->
            <div v-if="matchesLoading[selectedTournament.id]" class="py-16 flex flex-col items-center gap-3">
              <div class="w-7 h-7 rounded-full border-[3px] ls-spinner animate-spin"></div>
              <p class="text-[13px] ls-text-secondary">Đang tải danh sách trận...</p>
            </div>

            <!-- Lỗi trận -->
            <div v-else-if="matchesError[selectedTournament.id]" class="py-14 flex flex-col items-center gap-2 text-center px-4">
              <p class="text-[13.5px] font-semibold ls-text-danger">{{ matchesError[selectedTournament.id] }}</p>
              <button class="px-3.5 py-1.5 ls-btn-primary text-white text-[12.5px] font-semibold rounded-lg transition-colors"
                @click="loadMatches(selectedTournament.id)">Thử lại</button>
            </div>

            <!-- Rỗng -->
            <div v-else-if="filteredMatches.length === 0" class="py-16 flex flex-col items-center gap-2 text-center px-6">
              <p class="text-[13.5px] font-semibold ls-text-primary">
                {{ getMatches(selectedTournament.id).length ? 'Không có trận nào khớp bộ lọc' : 'Giải này chưa có trận nào' }}
              </p>
              <p class="text-[12.5px] ls-text-secondary">
                {{ getMatches(selectedTournament.id).length ? 'Thử xoá từ khoá hoặc đổi bộ lọc.' : 'Các trận sẽ hiện khi BTC tạo bản đấu.' }}
              </p>
            </div>

            <!-- Nhóm theo vòng -->
            <div v-else>
              <div v-for="group in groupedMatches" :key="group.round">
                <MatchRow
                  v-for="m in group.items"
                  :key="m.match_id"
                  :match="m"
                  @play="play(m, selectedTournament.id)"
                />
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { getLivescoreTournaments, getLivescoreTournamentMatches } from '@/service/livescore.js';
import MatchRow from './components/MatchRow.vue';

const router = useRouter();

const tournaments = ref([]);
const isLoading = ref(true);
const isRefreshing = ref(false);
const errorMessage = ref('');
const selectedId = ref(null);

// id giải -> list trận (load lazily khi chọn giải).
const matchesByTournament = reactive({});
const matchesLoading = reactive({});
const matchesError = reactive({});

const keyword = ref('');
const filter = ref('all');

const FILTERS = [
    { key: 'all', label: 'Tất cả' },
    { key: 'live', label: 'Đang đá' },
    { key: 'upcoming', label: 'Sắp diễn ra' },
    { key: 'finished', label: 'Đã xong' },
];

const channels = new Map();
const refreshTimers = new Map();

/** Trạng thái kết nối realtime để BTC biết tỉ số có đang cập nhật hay không. */
const realtimeState = ref('connecting');

const goBack = () => {
    if (window.history.length > 1) router.back();
    else router.push('/tools');
};

const selectedTournament = computed(
    () => tournaments.value.find((t) => t.id === selectedId.value) || null
);

const getMatches = (tournamentId) => matchesByTournament[tournamentId] || [];

const liveCount = (tournamentId) =>
    getMatches(tournamentId).filter((m) => m.is_going_on).length;

const dateRangeLabel = (t) => {
    if (!t?.start_date) return 'Chưa có thời gian';
    const start = new Date(t.start_date);
    if (Number.isNaN(start.getTime())) return '';

    const fmt = (d, withYear) =>
        d.toLocaleDateString('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            ...(withYear ? { year: 'numeric' } : {}),
        });

    if (!t.end_date) return `Từ ${fmt(start, true)}`;

    const end = new Date(t.end_date);
    if (Number.isNaN(end.getTime())) return `Từ ${fmt(start, true)}`;

    const sameDay = start.toDateString() === end.toDateString();
    return sameDay
        ? fmt(start, true)
        : `${fmt(start, false)} — ${fmt(end, true)}`;
};

const isUpcoming = (m) => !m.is_finished && !m.is_going_on;

const filterCount = (key) => {
    const list = getMatches(selectedId.value);
    if (key === 'live') return list.filter((m) => m.is_going_on).length;
    if (key === 'upcoming') return list.filter(isUpcoming).length;
    if (key === 'finished') return list.filter((m) => m.is_finished).length;
    return list.length;
};

const filteredMatches = computed(() => {
    const list = getMatches(selectedId.value);
    const kw = keyword.value.trim().toLowerCase();

    return list.filter((m) => {
        if (filter.value === 'live' && !m.is_going_on) return false;
        if (filter.value === 'upcoming' && !isUpcoming(m)) return false;
        if (filter.value === 'finished' && !m.is_finished) return false;

        if (kw) {
            const haystack = [
                m.team1?.name || '',
                m.team2?.name || '',
                m.round || '',
                ...(m.team1?.members || []).map((x) => x.full_name || x.name || ''),
                ...(m.team2?.members || []).map((x) => x.full_name || x.name || ''),
            ]
                .join(' ')
                .toLowerCase();
            if (!haystack.includes(kw)) return false;
        }
        return true;
    });
});

/** Gom trận theo vòng đấu, giữ thứ tự thời gian. */
const groupedMatches = computed(() => {
    const groups = new Map();
    for (const m of filteredMatches.value) {
        const key = m.round || 'Trận đấu';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(m);
    }
    return Array.from(groups.entries()).map(([round, items]) => ({ round, items }));
});

const realtimeLabel = computed(() => {
    if (realtimeState.value === 'connected') return 'Realtime';
    if (realtimeState.value === 'disconnected') return 'Mất kết nối';
    return 'Đang kết nối';
});

const realtimeBadgeClass = computed(() => {
    if (realtimeState.value === 'connected') {
        return 'bg-[#E8F8EF] dark:bg-[#12372A] text-[#0F7A46] dark:text-[#4ADE80] border-[#BFEBD3] dark:border-[#1D5C41]';
    }
    if (realtimeState.value === 'disconnected') {
        return 'bg-[#FFF6E5] dark:bg-[#3A2A0C] text-[#B45309] dark:text-[#FBBF24] border-[#FDE3B4] dark:border-[#63430F]';
    }
    return 'bg-[#F1F3F6] dark:bg-[#1E293B] text-[#5A6069] dark:text-[#94A3B8] border-[#E7EAEF] dark:border-[#2A3240]';
});

const realtimeDotClass = computed(() => {
    if (realtimeState.value === 'connected') return 'bg-[#16A34A]';
    if (realtimeState.value === 'disconnected') return 'bg-[#D97706]';
    return 'bg-[#A8AEBA] animate-pulse';
});

/**
 * Phát = mở màn chiếu ngay, KHÔNG qua bước cài đặt.
 * Mở tab mới vì máy chiếu chạy song song với app.
 */
const play = (match, tournamentId) => {
    window.open(
        `/live-score/tournament/${match.match_id}?tournament=${tournamentId}`,
        '_blank'
    );
};

/**
 * Nghe realtime theo từng giải: chỉ subscribe `tournament.{id}` của giải
 * đã tải trận. Đóng giải thì bỏ nghe giải đó — không nhận nhiễm giải khác.
 */
const syncChannel = (tournamentId) => {
    if (!window.Echo) return;
    if (channels.has(tournamentId)) return;

    const channel = window.Echo.channel(`tournament.${tournamentId}`);
    channel.listen('.tournament.score_updated', (payload) => {
        applySnapshot(payload);
    });
    channels.set(tournamentId, channel);
};

const applySnapshot = (payload) => {
    const tournamentId = payload?.tournament_id;
    const rows = payload?.matches || [];
    if (!rows.length) return;

    const list = matchesByTournament[tournamentId];
    if (!list?.length) return;

    const byId = new Map(rows.map((r) => [r.match_id, r]));

    let touched = false;
    for (const m of list) {
        const row = byId.get(m.match_id);
        if (!row) continue;

        // Bỏ qua event đến lệch thứ tự (version nhỏ hơn version đang có).
        if (row.version != null && m.version != null && row.version < m.version) continue;

        m.team1_score = row.team1_score;
        m.team2_score = row.team2_score;
        m.live_status = row.live_status;
        m.is_going_on = row.is_going_on;

        // Snapshot đã mang `status`/`is_finished` => không cần gọi lại API để
        // đồng bộ 2 field này sau khi confirm.
        if (row.status !== undefined) m.status = row.status;
        if (row.is_finished !== undefined) {
            m.is_finished = row.is_finished;
            if (row.is_finished) m.is_going_on = false;
        }

        // Chốt an toàn: live_status = finish thì chắc chắn trận đã xong.
        if (row.live_status === 'finish') {
            m.is_finished = true;
            m.is_going_on = false;
        }

        touched = true;
    }

    // Vue 3 tự bắt thay đổi trên object lồng nhau (deep reactivity).
    if (touched) matchesByTournament[tournamentId] = [...list];

    // Snapshot không mang `status` / `is_finished` -> gọi lại API sau nhịp ngắn.
    scheduleMatchRefresh(tournamentId);
};

/** Gọi lại API lấy trận (debounce) để đồng bộ status / is_finished. */
const scheduleMatchRefresh = (tournamentId) => {
    if (refreshTimers.has(tournamentId)) clearTimeout(refreshTimers.get(tournamentId));
    refreshTimers.set(tournamentId, setTimeout(() => {
        refreshTimers.delete(tournamentId);
        loadMatches(tournamentId, true);
    }, 1500));
};

const loadMatches = async (tournamentId, silent = false) => {
    if (!silent) {
        matchesLoading[tournamentId] = true;
    }
    matchesError[tournamentId] = '';

    try {
        const list = await getLivescoreTournamentMatches(tournamentId);
        matchesByTournament[tournamentId] = list || [];
        syncChannel(tournamentId);
    } catch (err) {
        matchesError[tournamentId] =
            err?.response?.data?.message || 'Không thể tải danh sách trận của giải.';
    } finally {
        matchesLoading[tournamentId] = false;
    }
};

const selectTournament = (tournamentId) => {
    if (selectedId.value === tournamentId) return;

    selectedId.value = tournamentId;
    keyword.value = '';
    filter.value = 'all';

    if (!matchesByTournament[tournamentId]) {
        loadMatches(tournamentId);
    } else {
        syncChannel(tournamentId);
    }
};

const refreshAll = async () => {
    if (isRefreshing.value) return;
    isRefreshing.value = true;

    try {
        const list = await getLivescoreTournaments();
        tournaments.value = list || [];

        // Giữ đúng giải đang xem nếu vẫn tồn tại, không thì chọn giải đầu tiên.
        if (!tournaments.value.some((t) => t.id === selectedId.value)) {
            selectedId.value = tournaments.value[0]?.id ?? null;
        }

        const targets = selectedId.value ? [selectedId.value] : [];
        await Promise.all(targets.map((id) => loadMatches(id, true)));
    } catch (err) {
        errorMessage.value =
            err?.response?.data?.message || 'Không thể tải danh sách giải.';
    } finally {
        isRefreshing.value = false;
    }
};

const load = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const list = await getLivescoreTournaments();
        tournaments.value = list || [];

        // Mở sẵn giải đầu tiên để BTC thấy ngay trận nào đáng phát.
        if (tournaments.value.length) {
            selectedId.value = tournaments.value[0].id;
            await loadMatches(tournaments.value[0].id);
        }
    } catch (err) {
        errorMessage.value =
            err?.response?.data?.message || 'Không thể tải danh sách giải.';
    } finally {
        isLoading.value = false;
    }
};

/** Lắng nghe trạng thái kết nối Pusher để báo người dùng. */
const onPusherStateChange = ({ current }) => {
    if (current === 'connected') realtimeState.value = 'connected';
    else if (current === 'connecting' || current === 'initialized') realtimeState.value = 'connecting';
    else if (current === 'unavailable' || current === 'failed' || current === 'disconnected') {
        realtimeState.value = 'disconnected';
    }
};

/**
 * Lấy đúng instance Pusher từ Echo.
 * Echo v2 expose `Echo.connector.pusher`; bản cũ hơn để ở `Echo.pusher`.
 */
const getPusher = () => {
    const echo = window.Echo;
    if (!echo) return null;
    return echo.connector?.pusher || echo.pusher || null;
};

const bindRealtimeState = () => {
    const pusher = getPusher();
    if (!pusher?.connection) return;

    // Đã kết nối trước khi vào màn -> hiện luôn trạng thái thật.
    if (pusher.connection.state === 'connected') realtimeState.value = 'connected';

    pusher.connection.bind('state_change', onPusherStateChange);
};

onMounted(() => {
    load();

    // Echo chỉ được init khi có token; trang này yêu cầu đăng nhập nên có token.
    if (!window.Echo && typeof window.initEcho === 'function') {
        window.initEcho(localStorage.getItem('access_token'));
    }

    bindRealtimeState();
});

onUnmounted(() => {
    for (const channel of channels.values()) {
        channel.stopListening('.tournament.score_updated');
    }
    channels.clear();

    for (const timer of refreshTimers.values()) {
        clearTimeout(timer);
    }
    refreshTimers.clear();

    const pusher = getPusher();
    if (pusher?.connection) {
        pusher.connection.unbind('state_change', onPusherStateChange);
    }
});
</script>

<style scoped>
/* ============================================================
   TOKEN MÀU — dùng class riêng thay vì `bg-white` / `text-[#...]`.
   Lý do: resources/css/app.css có override dark mode bằng `!important`
   cho các class phổ biến (bg-white, bg-gray-50, ...) — dùng chúng sẽ bị
   ghi đè màu và giao diện sáng/tối hiển thị sai.
   ============================================================ */
.ls-page {
    background: #F7F8FA;
    color: #3E414C;
}

.ls-header {
    background: rgba(255, 255, 255, 0.88);
    border-color: #DCDEE6;
}

.ls-card {
    background: #FFFFFF;
    border: 1px solid #DCDEE6;
    color: #3E414C;
}

.ls-divider {
    border-color: #EDEFF3;
}

.ls-text-primary { color: #3E414C; }
.ls-text-secondary { color: #8C929B; }
.ls-text-muted { color: #A8AEBA; }
.ls-text-accent { color: #1D6FE0; }
.ls-text-danger { color: #D32030; }
.ls-live { color: #E8192C; }

.ls-soft-gray { background: #F7F8FA; }
.ls-soft-blue { background: #E7F0FE; }
.ls-soft-red { background: #FDECEC; }

.ls-group-head { background: rgba(250, 251, 252, 0.92); }

.ls-icon-btn {
    color: #5A6069;
}
.ls-icon-btn:hover {
    background: #EDEFF3;
    color: #3E414C;
}

.ls-btn-primary {
    background: #1D6FE0;
}
.ls-btn-primary:hover { background: #1858B8; }

.ls-input {
    background: #F7F8FA;
    border: 1px solid #E7EAEF;
    color: #3E414C;
}
.ls-input::placeholder { color: #A8AEBA; }
.ls-input:focus {
    outline: none;
    border-color: #1D6FE0;
    box-shadow: 0 0 0 3px rgba(29, 111, 224, 0.15);
}

.ls-filter-on {
    background: #1D6FE0;
    border-color: #1D6FE0;
    color: #FFFFFF;
}
.ls-filter-off {
    background: #FFFFFF;
    border-color: #E7EAEF;
    color: #5A6069;
}
.ls-filter-off:hover { border-color: #C7CFD9; }

.ls-spinner {
    border-color: #DCDEE6;
    border-top-color: #1D6FE0;
}

/* Giải trong danh sách bên trái */
.ls-tournament-idle {
    border-color: #F2F4F7;
    border-left: 3px solid transparent;
    padding-left: 11px;
}
.ls-tournament-idle:hover { background: #FAFAFB; }

.ls-tournament-active {
    background: #F0F6FF;
    border-color: #EDEFF3;
    border-left: 3px solid #1D6FE0;
    padding-left: 11px;
}

/* ============================================================
   DARK MODE
   ============================================================ */
html.dark .ls-page {
    background: #0B0F19;
    color: #F8FAFC;
}

html.dark .ls-header {
    background: rgba(22, 31, 51, 0.88);
    border-color: #233148;
}

html.dark .ls-card {
    background: #161F33;
    border-color: #233148;
    color: #F8FAFC;
}

html.dark .ls-divider { border-color: #233148; }

html.dark .ls-text-primary { color: #F8FAFC; }
html.dark .ls-text-secondary { color: #94A3B8; }
html.dark .ls-text-muted { color: #64748B; }
html.dark .ls-text-accent { color: #60A5FA; }
html.dark .ls-text-danger { color: #F87171; }
html.dark .ls-live { color: #F87171; }

html.dark .ls-soft-gray { background: #1E293B; }
html.dark .ls-soft-blue { background: rgba(59, 130, 246, 0.16); }
html.dark .ls-soft-red { background: rgba(239, 68, 68, 0.14); }

html.dark .ls-group-head { background: rgba(30, 41, 59, 0.9); }

html.dark .ls-icon-btn { color: #94A3B8; }
html.dark .ls-icon-btn:hover {
    background: #1E293B;
    color: #F8FAFC;
}

html.dark .ls-btn-primary { background: #2563EB; }
html.dark .ls-btn-primary:hover { background: #1D4ED8; }

html.dark .ls-input {
    background: #0F172A;
    border-color: #334155;
    color: #F8FAFC;
}
html.dark .ls-input::placeholder { color: #64748B; }
html.dark .ls-input:focus {
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

html.dark .ls-filter-on {
    background: #2563EB;
    border-color: #2563EB;
    color: #FFFFFF;
}
html.dark .ls-filter-off {
    background: #1E293B;
    border-color: #334155;
    color: #CBD5E1;
}
html.dark .ls-filter-off:hover { border-color: #475569; }

html.dark .ls-spinner {
    border-color: #334155;
    border-top-color: #60A5FA;
}

html.dark .ls-tournament-idle {
    border-color: rgba(35, 49, 72, 0.7);
    border-left: 3px solid transparent;
}
html.dark .ls-tournament-idle:hover { background: rgba(30, 41, 59, 0.5); }

html.dark .ls-tournament-active {
    background: rgba(37, 99, 235, 0.12);
    border-color: rgba(35, 49, 72, 0.7);
    border-left: 3px solid #3B82F6;
}
</style>
