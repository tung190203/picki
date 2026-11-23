<template>
  <div class="min-h-screen bg-[#F7F8FA] text-[#3E414C] pb-16">
    <!-- Header -->
    <div class="sticky top-0 z-20 bg-white border-b border-[#DCDEE6]">
      <div class="max-w-[1100px] mx-auto px-4 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <button
            type="button"
            class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-gray-100 transition-colors shrink-0"
            @click="goBack"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <div class="min-w-0">
            <h1 class="text-[17px] font-bold truncate">Sân &amp; trọng tài</h1>
            <p class="text-[12px] text-[#8C929B]">
              {{ board.courts.length }} sân · {{ totalUnitMatches }} trận
              <span v-if="unassignedCount > 0" class="text-[#C77800] font-semibold">
                · {{ unassignedCount }} chưa xếp
              </span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg text-[13px] font-semibold text-[#1D5FD1] bg-[#EAF1FE] hover:bg-[#dbe7fd] transition-colors"
            @click="handleAutoAssign"
          >
            Chia tự động
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg text-[13px] font-semibold text-[#3E414C] bg-[#F1F3F6] hover:bg-[#e5e8ed] transition-colors"
            @click="openCopyModal"
          >
            Sao chép
          </button>
        </div>
      </div>

      <!-- Instruction bar -->
      <div
        class="max-w-[1100px] mx-auto px-4 py-2 text-[12.5px] border-t border-[#EDEFF3]"
        :class="selectedChip ? 'bg-[#FFF9E6] text-[#9A5B00] font-medium' : 'bg-[#F9FAFB] text-[#6B7280]'"
      >
        <template v-if="selectedChip">
          Đang chọn <strong>{{ selectedChipLabel }}</strong>. Chạm vào thẻ sân để thả, hoặc chạm lại chip để huỷ.
        </template>
        <template v-else>
          Kéo chip thả vào thẻ sân. Hoặc chạm chip để chọn, rồi chạm thẻ sân.
        </template>
      </div>
    </div>

    <div class="max-w-[1100px] mx-auto px-4 pt-4 space-y-5">
      <div v-if="loading" class="py-16 text-center text-sm text-[#8C929B]">
        Đang tải bảng sân...
      </div>

      <template v-else>
        <!-- Chip Bank -->
        <div
          class="bg-white rounded-2xl border border-[#DCDEE6] p-4 space-y-4 shadow-sm"
          @dragover.prevent="dragOverBank = true"
          @dragleave="dragOverBank = false"
          @drop.prevent="onDropToBank"
          :class="{ 'ring-2 ring-amber-400 bg-amber-50/30': dragOverBank }"
        >
          <!-- Referees group -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#0B7A4B]"></span>
                <span class="text-[12.5px] font-bold uppercase tracking-wide text-[#3E414C]">Trọng tài</span>
                <span class="text-[11.5px] text-[#8C929B]">— kéo vào thẻ sân</span>
              </div>
              <span class="text-[12px] font-semibold text-[#6B7280]">{{ board.referees.length }}</span>
            </div>

            <div v-if="board.referees.length === 0" class="text-[12.5px] text-[#8C929B] py-1">
              Chưa có trọng tài trong BTC giải.
            </div>
            <div v-else class="flex flex-wrap gap-2">
              <button
                v-for="referee in board.referees"
                :key="referee.user_id"
                type="button"
                draggable="true"
                @dragstart="onDragStart($event, { kind: 'referee', referee, fromCourtId: null })"
                @dragend="onDragEnd"
                @click="toggleSelectChip({ kind: 'referee', referee, fromCourtId: null })"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12.5px] font-semibold border transition-all select-none cursor-grab active:cursor-grabbing"
                :class="[
                  referee.court_ids.length === 0
                    ? 'bg-[#E7F6EE] text-[#0B7A4B] border-dashed border-[#D97706]'
                    : 'bg-[#E7F6EE] text-[#0B7A4B] border-[#A7DFC0]',
                  isChipSelected('referee', referee.user_id) ? 'ring-2 ring-amber-400 shadow-sm' : '',
                ]"
              >
                <span class="w-5 h-5 rounded-full bg-[#0B7A4B] text-white text-[10px] font-bold flex items-center justify-center">
                  {{ referee.initials || '?' }}
                </span>
                <span>{{ referee.name }}</span>
                <span v-if="referee.is_me" class="opacity-75">(bạn)</span>
                <span v-if="referee.court_ids.length === 0" class="text-[11px] text-[#B45309] font-medium">· chưa gán</span>
                <span v-else class="text-[11px] opacity-80">· {{ formatRefereeCourts(referee.court_ids) }}</span>
              </button>
            </div>
          </div>

          <div class="border-t border-[#EDEFF3]"></div>

          <!-- Unassigned matches group -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#D97706]"></span>
                <span class="text-[12.5px] font-bold uppercase tracking-wide text-[#3E414C]">Trận chưa xếp sân</span>
              </div>
              <span class="text-[12px] font-semibold text-[#D97706]">{{ unassignedCount }} trận</span>
            </div>

            <div v-if="unassignedGroups.length === 0 && unassignedSlots.length === 0" class="text-[13px] font-medium text-[#0B7A4B] py-1">
              ✓ Đã xếp hết vào sân
            </div>
            <div v-else class="flex flex-wrap gap-2">
              <!-- Unassigned Groups -->
              <button
                v-for="g in unassignedGroups"
                :key="'ug-' + g.group_id"
                type="button"
                draggable="true"
                @dragstart="onDragStart($event, { kind: 'group', unit: g, fromCourtId: null })"
                @dragend="onDragEnd"
                @click="toggleSelectChip({ kind: 'group', unit: g, fromCourtId: null })"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12.5px] font-semibold bg-[#EAF1FE] text-[#1D5FD1] border border-[#B8D1FA] transition-all select-none cursor-grab active:cursor-grabbing"
                :class="isChipSelected('group', g.group_id) ? 'ring-2 ring-amber-400 shadow-sm' : ''"
              >
                <span>{{ g.label }}</span>
                <span class="px-1.5 py-px rounded bg-white/80 text-[11px]">{{ g.match_count }}</span>
              </button>

              <!-- Unassigned Slots -->
              <button
                v-for="s in unassignedSlots"
                :key="'us-' + s.round + '-' + s.no"
                type="button"
                draggable="true"
                @dragstart="onDragStart($event, { kind: 'slot', unit: s, fromCourtId: null })"
                @dragend="onDragEnd"
                @click="toggleSelectChip({ kind: 'slot', unit: s, fromCourtId: null })"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12.5px] font-semibold bg-[#FDECEE] text-[#C8102E] border border-[#F6BCC4] transition-all select-none cursor-grab active:cursor-grabbing"
                :class="isChipSelected('slot', `${s.round}:${s.no}`) ? 'ring-2 ring-amber-400 shadow-sm' : ''"
              >
                <span>{{ s.label }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Courts Grid -->
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#6B7280] mb-2.5 px-1">SÂN</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            <div
              v-for="court in board.courts"
              :key="court.id"
              @dragover.prevent="dragOverCourtId = court.id"
              @dragleave="dragOverCourtId === court.id && (dragOverCourtId = null)"
              @drop.prevent="onDropToCourt(court)"
              @click="onTapCourt(court)"
              class="bg-white rounded-2xl border p-4 flex flex-col justify-between transition-all relative"
              :class="[
                dragOverCourtId === court.id
                  ? 'border-amber-400 bg-amber-50/40 ring-2 ring-amber-300'
                  : selectedChip
                    ? 'border-dashed border-amber-400 hover:bg-amber-50/20 cursor-pointer'
                    : 'border-[#DCDEE6]',
              ]"
            >
              <!-- Card Header -->
              <div>
                <div class="flex items-center justify-between gap-2 mb-2.5">
                  <h3 class="text-[15px] font-bold text-[#1F2937]">{{ court.name }}</h3>
                  <div class="relative" @click.stop>
                    <button
                      type="button"
                      class="w-7 h-7 rounded-full flex items-center justify-center hover:bg-gray-100 text-[#6B7280]"
                      @click="activeMenuCourtId = activeMenuCourtId === court.id ? null : court.id"
                    >
                      •••
                    </button>
                    <div
                      v-if="activeMenuCourtId === court.id"
                      class="absolute right-0 mt-1 w-44 bg-white rounded-xl border border-[#DCDEE6] shadow-lg py-1 z-30 text-[13px]"
                    >
                      <button
                        type="button"
                        class="w-full text-left px-3 py-2 hover:bg-gray-50"
                        @click="openRenameModal(court)"
                      >
                        Đổi tên sân
                      </button>
                      <button
                        v-if="court.referee"
                        type="button"
                        class="w-full text-left px-3 py-2 hover:bg-gray-50 text-[#B45309]"
                        @click="unassignCourtReferee(court)"
                      >
                        Bỏ gán trọng tài
                      </button>
                      <button
                        type="button"
                        class="w-full text-left px-3 py-2 hover:bg-red-50 text-[#C8102E]"
                        @click="handleDeleteCourt(court)"
                      >
                        Xoá sân
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Referee slot -->
                <div class="mb-3" @click.stop>
                  <button
                    v-if="court.referee"
                    type="button"
                    draggable="true"
                    @dragstart="onDragStart($event, { kind: 'referee', referee: court.referee, fromCourtId: court.id })"
                    @dragend="onDragEnd"
                    @click="toggleSelectChip({ kind: 'referee', referee: court.referee, fromCourtId: court.id })"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-semibold bg-[#E7F6EE] text-[#0B7A4B] border border-[#A7DFC0] cursor-grab"
                  >
                    <span class="w-4 h-4 rounded-full bg-[#0B7A4B] text-white text-[9px] font-bold flex items-center justify-center">
                      {{ court.referee.initials || '?' }}
                    </span>
                    <span>{{ court.referee.name }}</span>
                  </button>
                  <div
                    v-else
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-[12px] font-medium border border-dashed border-[#D97706] text-[#B45309] bg-[#FFFBEB]"
                  >
                    + Thả trọng tài
                  </div>
                </div>

                <!-- Assigned Units on this court -->
                <div class="min-h-[42px] flex flex-wrap gap-1.5 items-center" @click.stop>
                  <template v-if="unitsOnCourt(court.id).length === 0">
                    <span class="text-[12px] text-[#9CA3AF]">Thả bảng hoặc trận vào đây</span>
                  </template>
                  <template v-else>
                    <button
                      v-for="item in unitsOnCourt(court.id)"
                      :key="item.key"
                      type="button"
                      draggable="true"
                      @dragstart="onDragStart($event, item.dragPayload)"
                      @dragend="onDragEnd"
                      @click="toggleSelectChip(item.dragPayload)"
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[12px] font-semibold border cursor-grab"
                      :class="item.kind === 'group'
                        ? 'bg-[#EAF1FE] text-[#1D5FD1] border-[#B8D1FA]'
                        : 'bg-[#FDECEE] text-[#C8102E] border-[#F6BCC4]'"
                    >
                      <span>{{ item.label }}</span>
                      <span v-if="item.sub" class="text-[11px] opacity-80">{{ item.sub }}</span>
                    </button>
                  </template>
                </div>
              </div>

              <!-- Footer -->
              <div class="mt-3 pt-2.5 border-t border-[#EDEFF3] flex items-center justify-between text-[12px] text-[#6B7280]">
                <span>{{ courtPendingCount(court.id) > 0 ? `${courtPendingCount(court.id)} trận` : 'Chưa có trận' }}</span>
              </div>
            </div>

            <!-- Add court button -->
            <button
              type="button"
              class="md:col-span-2 py-3.5 rounded-2xl border border-dashed border-[#CBD5E1] bg-white/70 hover:bg-white text-[13.5px] font-semibold text-[#475569] transition-colors"
              @click="handleAddCourt"
            >
              + Thêm sân
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- Modal: Group Move vs Split -->
    <div v-if="groupMovePrompt" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl max-w-sm w-full p-5 space-y-4 shadow-xl">
        <h3 class="text-[16px] font-bold">Chia {{ groupMovePrompt.unit.label }}</h3>
        <p class="text-[13px] text-[#6B7280]">
          {{ groupMovePrompt.unit.label }} đang được xếp ở sân khác. Bạn muốn chuyển hẳn sang
          <strong>{{ groupMovePrompt.targetCourt.name }}</strong> hay chia thêm sân này?
        </p>
        <div class="flex flex-col gap-2">
          <button
            type="button"
            class="w-full py-2.5 rounded-xl bg-[#1D5FD1] text-white text-[13.5px] font-semibold"
            @click="confirmGroupMove('move')"
          >
            Chuyển sang {{ groupMovePrompt.targetCourt.name }}
          </button>
          <button
            type="button"
            class="w-full py-2.5 rounded-xl bg-[#EAF1FE] text-[#1D5FD1] text-[13.5px] font-semibold"
            @click="confirmGroupMove('split')"
          >
            Chia thêm {{ groupMovePrompt.targetCourt.name }}
          </button>
          <button
            type="button"
            class="w-full py-2 rounded-xl text-[#6B7280] text-[13px] hover:bg-gray-100"
            @click="groupMovePrompt = null"
          >
            Huỷ
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Rename Court -->
    <div v-if="renameModal.open" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl max-w-sm w-full p-5 space-y-4 shadow-xl">
        <h3 class="text-[16px] font-bold">Đổi tên sân</h3>
        <input
          v-model="renameModal.name"
          type="text"
          maxlength="40"
          class="w-full px-3 py-2 border border-[#DCDEE6] rounded-xl text-[14px] focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Tên sân..."
        />
        <div class="flex justify-end gap-2">
          <button type="button" class="px-4 py-2 rounded-xl text-[13px] text-[#6B7280]" @click="renameModal.open = false">Huỷ</button>
          <button type="button" class="px-4 py-2 rounded-xl bg-[#D72D36] text-white text-[13px] font-semibold" @click="submitRename">Lưu</button>
        </div>
      </div>
    </div>

    <!-- Modal: Copy from another tournament -->
    <div v-if="copyModal.open" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl max-w-md w-full p-5 space-y-4 shadow-xl">
        <h3 class="text-[16px] font-bold">Sao chép sân &amp; trọng tài từ giải khác</h3>
        <div v-if="copyModal.loading" class="text-[13px] text-[#8C929B] py-4 text-center">Đang tải danh sách giải...</div>
        <div v-else-if="copyModal.tournaments.length === 0" class="text-[13px] text-[#8C929B] py-4 text-center">
          Không có giải đấu khác để sao chép.
        </div>
        <div v-else class="max-h-64 overflow-y-auto divide-y divide-[#EDEFF3]">
          <button
            v-for="t in copyModal.tournaments"
            :key="t.id"
            type="button"
            class="w-full text-left py-2.5 px-2 hover:bg-gray-50 rounded-lg flex items-center justify-between"
            @click="submitCopy(t.id)"
          >
            <span class="text-[13.5px] font-semibold">{{ t.name }}</span>
            <span class="text-[12px] text-[#1D5FD1]">Chọn</span>
          </button>
        </div>
        <div class="flex justify-end">
          <button type="button" class="px-4 py-2 rounded-xl text-[13px] text-[#6B7280]" @click="copyModal.open = false">Đóng</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue3-toastify';
import {
  getTournamentCourts,
  getCourtBoard,
  createTournamentCourt,
  updateCourt,
  deleteCourt,
  copyTournamentCourts,
  saveCourtBoardUnit,
  autoAssignCourtBoard,
  getRefereeTournaments,
} from '@/service/tournamentCourt.js';

const route = useRoute();
const router = useRouter();
const tournamentId = Number(route.params.id);

const loading = ref(true);
const board = ref({
  courts: [],
  referees: [],
  matches: [],
});

const selectedChip = ref(null);
const draggingChip = ref(null);
const dragOverCourtId = ref(null);
const dragOverBank = ref(false);
const activeMenuCourtId = ref(null);
const groupMovePrompt = ref(null);

const renameModal = ref({ open: false, courtId: null, name: '' });
const copyModal = ref({ open: false, loading: false, tournaments: [] });

const totalUnitMatches = computed(() => {
  const groupSum = board.value.matches
    .filter((u) => u.type === 'group')
    .reduce((acc, u) => acc + (u.match_count || 0), 0);
  const slotSum = board.value.matches.filter((u) => u.type === 'slot').length;
  return groupSum + slotSum;
});

const unassignedCount = computed(() => {
  let count = 0;
  for (const u of board.value.matches) {
    if (u.type === 'group') {
      const assigned = (u.courts || []).reduce((acc, c) => acc + (c.count || 0), 0);
      if (assigned === 0) count += (u.match_count || 0);
    } else if (u.type === 'slot' && !u.court_id) {
      count += 1;
    }
  }
  return count;
});

const courtPendingCount = (courtId) => {
  let count = 0;
  for (const u of board.value.matches) {
    if (u.type === 'group') {
      const entry = (u.courts || []).find((c) => c.court_id === courtId);
      if (entry) count += entry.count || 0;
    } else if (u.type === 'slot' && u.court_id === courtId) {
      count += 1;
    }
  }
  return count;
};

const unassignedGroups = computed(() =>
  board.value.matches.filter((u) => u.type === 'group' && (!u.courts || u.courts.length === 0))
);

const unassignedSlots = computed(() =>
  board.value.matches.filter((u) => u.type === 'slot' && !u.court_id)
);

const selectedChipLabel = computed(() => {
  if (!selectedChip.value) return '';
  if (selectedChip.value.kind === 'referee') return selectedChip.value.referee.name;
  return selectedChip.value.unit.label;
});

const isChipSelected = (kind, id) => {
  if (!selectedChip.value || selectedChip.value.kind !== kind) return false;
  if (kind === 'referee') return selectedChip.value.referee.user_id === id;
  if (kind === 'group') return selectedChip.value.unit.group_id === id;
  return `${selectedChip.value.unit.round}:${selectedChip.value.unit.no}` === id;
};

const formatRefereeCourts = (courtIds) => {
  const map = new Map(board.value.courts.map((c, idx) => [c.id, `S${idx + 1}`]));
  return courtIds.map((id) => map.get(id) || `#${id}`).join(', ');
};

const unitsOnCourt = (courtId) => {
  const items = [];
  for (const u of board.value.matches) {
    if (u.type === 'group' && Array.isArray(u.courts)) {
      const idx = u.courts.findIndex((c) => c.court_id === courtId);
      if (idx !== -1) {
        const entry = u.courts[idx];
        const part = u.courts.length > 1 ? ` ${idx + 1}/${u.courts.length}` : '';
        items.push({
          key: `g-${u.group_id}-${courtId}`,
          kind: 'group',
          label: `${u.label}${part}`,
          sub: `${entry.count}`,
          dragPayload: { kind: 'group', unit: u, fromCourtId: courtId },
        });
      }
    } else if (u.type === 'slot' && u.court_id === courtId) {
      items.push({
        key: `s-${u.round}-${u.no}`,
        kind: 'slot',
        label: u.label,
        sub: null,
        dragPayload: { kind: 'slot', unit: u, fromCourtId: courtId },
      });
    }
  }
  return items;
};

const fetchBoard = async () => {
  loading.value = true;
  try {
    const [courtsData, matchesList] = await Promise.all([
      getTournamentCourts(tournamentId),
      getCourtBoard(tournamentId),
    ]);
    board.value = {
      courts: courtsData?.courts || [],
      referees: courtsData?.referees || [],
      matches: Array.isArray(matchesList) ? matchesList : [],
    };
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không tải được bảng sân.');
  } finally {
    loading.value = false;
  }
};

const refreshMatchesOnly = (matchesList) => {
  board.value.matches = Array.isArray(matchesList) ? matchesList : [];
};

const goBack = () => {
  if (window.history.length > 1) router.back();
  else router.push(`/tournament-detail/${tournamentId}`);
};

const toggleSelectChip = (payload) => {
  activeMenuCourtId.value = null;
  if (
    selectedChip.value &&
    selectedChip.value.kind === payload.kind &&
    ((payload.kind === 'referee' && selectedChip.value.referee.user_id === payload.referee.user_id) ||
      (payload.kind === 'group' && selectedChip.value.unit.group_id === payload.unit.group_id) ||
      (payload.kind === 'slot' &&
        selectedChip.value.unit.round === payload.unit.round &&
        selectedChip.value.unit.no === payload.unit.no))
  ) {
    selectedChip.value = null;
    return;
  }
  selectedChip.value = payload;
};

const onDragStart = (event, payload) => {
  draggingChip.value = payload;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', payload.kind);
  }
};

const onDragEnd = () => {
  draggingChip.value = null;
  dragOverCourtId.value = null;
  dragOverBank.value = false;
};

const onDropToCourt = async (court) => {
  dragOverCourtId.value = null;
  if (!draggingChip.value) return;
  const payload = draggingChip.value;
  draggingChip.value = null;
  await applyChipToCourt(payload, court);
};

const onTapCourt = async (court) => {
  activeMenuCourtId.value = null;
  if (!selectedChip.value) return;
  const payload = selectedChip.value;
  selectedChip.value = null;
  await applyChipToCourt(payload, court);
};

const onDropToBank = async () => {
  dragOverBank.value = false;
  if (!draggingChip.value) return;
  const payload = draggingChip.value;
  draggingChip.value = null;

  if (!payload.fromCourtId) return;

  try {
    if (payload.kind === 'referee') {
      await updateCourt(payload.fromCourtId, { referee_user_id: null });
      await fetchBoard();
      toast.success('Đã bỏ gán trọng tài.');
    } else if (payload.kind === 'group') {
      const remaining = (payload.unit.courts || [])
        .map((c) => c.court_id)
        .filter((id) => id !== payload.fromCourtId);
      refreshMatchesOnly(await saveCourtBoardUnit(tournamentId, {
        unit_type: 'group',
        group_id: payload.unit.group_id,
        court_ids: remaining,
      }));
      toast.success('Đã bỏ sân khỏi bảng.');
    } else if (payload.kind === 'slot') {
      refreshMatchesOnly(await saveCourtBoardUnit(tournamentId, {
        unit_type: 'slot',
        slot_round: payload.unit.round,
        slot_no: payload.unit.no,
        court_ids: [],
      }));
      toast.success('Đã đưa trận về chưa xếp sân.');
    }
  } catch (e) {
    toast.error(e.response?.data?.message || 'Thao tác thất bại.');
  }
};

const applyChipToCourt = async (payload, targetCourt) => {
  try {
    if (payload.kind === 'referee') {
      if (payload.fromCourtId && payload.fromCourtId !== targetCourt.id) {
        await updateCourt(payload.fromCourtId, { referee_user_id: null });
      }
      await updateCourt(targetCourt.id, { referee_user_id: payload.referee.user_id });
      await fetchBoard();
      toast.success(`Đã gán ${payload.referee.name} cho ${targetCourt.name}.`);
      return;
    }

    if (payload.kind === 'group') {
      const existingIds = (payload.unit.courts || []).map((c) => c.court_id);
      if (existingIds.includes(targetCourt.id)) return;
      if (existingIds.length > 0) {
        groupMovePrompt.value = { unit: payload.unit, targetCourt };
        return;
      }
      refreshMatchesOnly(await saveCourtBoardUnit(tournamentId, {
        unit_type: 'group',
        group_id: payload.unit.group_id,
        court_ids: [targetCourt.id],
      }));
      toast.success(`Đã xếp ${payload.unit.label} vào ${targetCourt.name}.`);
      return;
    }

    if (payload.kind === 'slot') {
      refreshMatchesOnly(await saveCourtBoardUnit(tournamentId, {
        unit_type: 'slot',
        slot_round: payload.unit.round,
        slot_no: payload.unit.no,
        court_ids: [targetCourt.id],
      }));
      toast.success(`Đã xếp ${payload.unit.label} vào ${targetCourt.name}.`);
    }
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể lưu phân sân.');
  }
};

const confirmGroupMove = async (mode) => {
  const { unit, targetCourt } = groupMovePrompt.value;
  groupMovePrompt.value = null;

  const existingIds = (unit.courts || []).map((c) => c.court_id);
  const courtIds = mode === 'split'
    ? [...existingIds, targetCourt.id]
    : [targetCourt.id];

  try {
    refreshMatchesOnly(await saveCourtBoardUnit(tournamentId, {
      unit_type: 'group',
      group_id: unit.group_id,
      court_ids: courtIds,
    }));
    toast.success(mode === 'split' ? `Đã chia thêm ${targetCourt.name}.` : `Đã chuyển sang ${targetCourt.name}.`);
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể cập nhật bảng.');
  }
};

const handleAddCourt = async () => {
  try {
    await createTournamentCourt(tournamentId);
    await fetchBoard();
    toast.success('Đã thêm sân mới.');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể thêm sân.');
  }
};

const openRenameModal = (court) => {
  activeMenuCourtId.value = null;
  renameModal.value = { open: true, courtId: court.id, name: court.name };
};

const submitRename = async () => {
  if (!renameModal.value.name.trim()) return;
  try {
    await updateCourt(renameModal.value.courtId, { name: renameModal.value.name.trim() });
    renameModal.value.open = false;
    await fetchBoard();
    toast.success('Đã đổi tên sân.');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể đổi tên sân.');
  }
};

const unassignCourtReferee = async (court) => {
  activeMenuCourtId.value = null;
  try {
    await updateCourt(court.id, { referee_user_id: null });
    await fetchBoard();
    toast.success('Đã bỏ gán trọng tài.');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Thao tác thất bại.');
  }
};

const handleDeleteCourt = async (court) => {
  activeMenuCourtId.value = null;
  if (!window.confirm(`Xoá ${court.name}? Các trận chờ sẽ chuyển về Chưa xếp sân.`)) return;
  try {
    await deleteCourt(court.id);
    await fetchBoard();
    toast.success(`Đã xoá ${court.name}.`);
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể xoá sân.');
  }
};

const handleAutoAssign = async () => {
  if (board.value.courts.length === 0) {
    toast.warning('Vui lòng thêm ít nhất 1 sân trước.');
    return;
  }
  const hasAssigned = board.value.matches.some((u) => (u.courts?.length > 0) || u.court_id);
  if (hasAssigned && !window.confirm('Chia tự động sẽ xếp lại toàn bộ trận chưa đấu. Tiếp tục?')) {
    return;
  }
  try {
    refreshMatchesOnly(await autoAssignCourtBoard(tournamentId));
    toast.success('Đã chia sân tự động.');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể chia tự động.');
  }
};

const openCopyModal = async () => {
  copyModal.value = { open: true, loading: true, tournaments: [] };
  try {
    const list = await getRefereeTournaments();
    copyModal.value.tournaments = (list || []).filter(
      (t) => t.id !== tournamentId && (t.roles?.includes('Admin') || t.roles?.includes('BTC'))
    );
  } catch {
    copyModal.value.tournaments = [];
  } finally {
    copyModal.value.loading = false;
  }
};

const submitCopy = async (sourceId) => {
  if (board.value.courts.length > 0 && !window.confirm('Thay toàn bộ sân & trọng tài hiện tại bằng của giải đã chọn?')) {
    return;
  }
  try {
    await copyTournamentCourts(tournamentId, sourceId);
    copyModal.value.open = false;
    await fetchBoard();
    toast.success('Đã sao chép sân & trọng tài.');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể sao chép.');
  }
};

onMounted(fetchBoard);
</script>
