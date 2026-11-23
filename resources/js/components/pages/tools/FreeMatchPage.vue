<template>
  <div class="min-h-screen bg-[#F7F8FA] text-[#3E414C] pb-20">
    <!-- Header -->
    <div class="sticky top-0 z-20 bg-white border-b border-[#DCDEE6]">
      <div class="max-w-[720px] mx-auto px-4 py-3 flex items-center gap-3">
        <button
          type="button"
          class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-gray-100"
          @click="router.back()"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 class="text-[17px] font-bold">Tạo trận mới (ngoài giải)</h1>
          <p class="text-[12px] text-[#8C929B]">Không gắn giải · không tính điểm trình</p>
        </div>
      </div>
    </div>

    <div class="max-w-[720px] mx-auto px-4 pt-5 space-y-4">
      <div class="bg-white rounded-2xl border border-[#DCDEE6] p-4 space-y-4">
        <div>
          <label class="block text-[12.5px] font-semibold mb-1">Tên trận (không bắt buộc)</label>
          <input
            v-model="form.title"
            type="text"
            class="w-full px-3 py-2 border border-[#DCDEE6] rounded-xl text-[14px]"
            placeholder="VD: Kèo giao hữu tối thứ 5"
          />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-2">
            <p class="text-[12.5px] font-bold text-[#1D5FD1]">Đội A</p>
            <input
              v-model="form.teamA1"
              type="text"
              class="w-full px-3 py-2 border border-[#DCDEE6] rounded-xl text-[13.5px]"
              placeholder="VĐV 1 (Đội A)"
            />
            <input
              v-model="form.teamA2"
              type="text"
              class="w-full px-3 py-2 border border-[#DCDEE6] rounded-xl text-[13.5px]"
              placeholder="VĐV 2 (tuỳ chọn)"
            />
          </div>
          <div class="space-y-2">
            <p class="text-[12.5px] font-bold text-[#C8102E]">Đội B</p>
            <input
              v-model="form.teamB1"
              type="text"
              class="w-full px-3 py-2 border border-[#DCDEE6] rounded-xl text-[13.5px]"
              placeholder="VĐV 1 (Đội B)"
            />
            <input
              v-model="form.teamB2"
              type="text"
              class="w-full px-3 py-2 border border-[#DCDEE6] rounded-xl text-[13.5px]"
              placeholder="VĐV 2 (tuỳ chọn)"
            />
          </div>
        </div>

        <!-- Rules -->
        <div class="pt-2 border-t border-[#EDEFF3] flex items-center justify-between">
          <div>
            <p class="text-[12px] text-[#8C929B]">Luật thi đấu</p>
            <p class="text-[13.5px] font-semibold">
              {{ form.pointsToWin }} điểm · cách {{ form.winBy }} · {{ form.sets }} set
            </p>
          </div>
          <button
            type="button"
            class="text-[13px] font-semibold text-[#1D5FD1]"
            @click="showRules = !showRules"
          >
            {{ showRules ? 'Thu gọn' : 'Đổi' }}
          </button>
        </div>

        <div v-if="showRules" class="grid grid-cols-2 gap-3 pt-2">
          <div>
            <label class="block text-[12px] text-[#6B7280] mb-1">Điểm thắng set</label>
            <select v-model.number="form.pointsToWin" class="w-full px-3 py-2 border border-[#DCDEE6] rounded-xl text-[13.5px]">
              <option :value="11">11 điểm</option>
              <option :value="15">15 điểm</option>
              <option :value="21">21 điểm</option>
            </select>
          </div>
          <div>
            <label class="block text-[12px] text-[#6B7280] mb-1">Số set</label>
            <select v-model.number="form.sets" class="w-full px-3 py-2 border border-[#DCDEE6] rounded-xl text-[13.5px]">
              <option :value="1">1 set</option>
              <option :value="3">3 set</option>
            </select>
          </div>
        </div>
      </div>

      <button
        type="button"
        :disabled="submitting"
        class="w-full py-3 rounded-2xl bg-[#D72D36] text-white font-bold text-[14.5px] hover:bg-[#b91c1c] disabled:opacity-50"
        @click="startFreeMatch"
      >
        {{ submitting ? 'Đang tạo...' : 'Bắt đầu bắt trận' }}
      </button>
    </div>

    <!-- Referee Scoring Modal -->
    <RefereeScoringScreen
      v-if="showScoring && createdMatch"
      :is-open="showScoring"
      :team-a="scoringTeamA"
      :team-b="scoringTeamB"
      :rules="{ points_to_win: form.pointsToWin, sets_per_match: form.sets, winning_rule: form.winBy }"
      @close="showScoring = false"
      @confirm="handleMatchFinished"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from 'vue3-toastify';
import { createFreeMatch, updateFreeMatch } from '@/service/tournamentCourt.js';
import RefereeScoringScreen from '@/components/molecules/referee-scoring/RefereeScoringScreen.vue';

const router = useRouter();
const showRules = ref(false);
const submitting = ref(false);
const showScoring = ref(false);
const createdMatch = ref(null);

const form = ref({
  title: '',
  teamA1: '',
  teamA2: '',
  teamB1: '',
  teamB2: '',
  pointsToWin: 11,
  winBy: 2,
  sets: 1,
});

const scoringTeamA = computed(() => {
  const members = [form.value.teamA1, form.value.teamA2]
    .filter((n) => n.trim())
    .map((name, idx) => ({ id: idx + 1, user: { id: idx + 1, full_name: name.trim() } }));
  return { id: 1, name: members.map((m) => m.user.full_name).join(' - ') || 'Đội A', members };
});

const scoringTeamB = computed(() => {
  const members = [form.value.teamB1, form.value.teamB2]
    .filter((n) => n.trim())
    .map((name, idx) => ({ id: idx + 10, user: { id: idx + 10, full_name: name.trim() } }));
  return { id: 2, name: members.map((m) => m.user.full_name).join(' - ') || 'Đội B', members };
});

const startFreeMatch = async () => {
  const teamA = [form.value.teamA1, form.value.teamA2]
    .map((n) => n.trim())
    .filter(Boolean)
    .map((name) => ({ name }));
  const teamB = [form.value.teamB1, form.value.teamB2]
    .map((n) => n.trim())
    .filter(Boolean)
    .map((name) => ({ name }));

  if (teamA.length === 0 || teamB.length === 0) {
    toast.warning('Vui lòng nhập ít nhất 1 VĐV cho mỗi đội.');
    return;
  }

  submitting.value = true;
  try {
    createdMatch.value = await createFreeMatch({
      title: form.value.title.trim() || null,
      rules: {
        points_to_win: form.value.pointsToWin,
        win_by: form.value.winBy,
        sets: form.value.sets,
      },
      team_a: teamA,
      team_b: teamB,
      status: 'playing',
    });
    showScoring.value = true;
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không thể tạo trận.');
  } finally {
    submitting.value = false;
  }
};

const handleMatchFinished = async (scorePayload) => {
  if (!createdMatch.value) return;
  try {
    await updateFreeMatch(createdMatch.value.id, {
      score: scorePayload || {},
      status: 'done',
    });
    showScoring.value = false;
    toast.success('Đã lưu kết quả trận ngoài giải.');
    router.push('/tools/referee');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không lưu được kết quả.');
  }
};
</script>
