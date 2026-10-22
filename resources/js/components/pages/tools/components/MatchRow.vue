<template>
  <!--
    Trạng thái (spec C.3):
      Đang đá  -> nền đỏ nhạt + badge LIVE nhấp nháy
      Chưa bắt đầu -> nền trung tính, badge "Chưa bắt đầu"
      Đã xong  -> mờ đi, không còn nút phát
    Màu dùng token class riêng (ls-*) để không bị app.css ghi đè
    dark mode bằng !important.
  -->
  <div
    class="px-3 sm:px-5 py-3 transition-colors border-b last:border-b-0"
    :class="[rowClass, 'ls-row-divider']"
  >
    <div class="flex items-center gap-3 sm:gap-4">

      <!-- Vòng đấu -->
      <div class="w-[92px] sm:w-[124px] shrink-0 hidden sm:block">
        <p class="text-[12px] font-semibold ls-text-secondary truncate" :title="match.round">
          {{ match.round || 'Trận đấu' }}
        </p>
        <p v-if="match.scheduled_at" class="text-[11px] ls-text-muted tabular-nums">
          {{ formatTime(match.scheduled_at) }}
        </p>
      </div>

      <!-- Đội 1 -->
      <div class="flex-1 min-w-0 flex items-center gap-2 sm:gap-3">
        <span class="hidden sm:flex w-6 h-6 rounded-md ls-team-badge-a text-[10.5px] font-bold items-center justify-center shrink-0">A</span>
        <span
          class="text-[14px] sm:text-[15px] font-bold truncate"
          :class="teamNameClass(1)"
          :title="match.team1?.name"
        >
          {{ match.team1?.name || 'Đội A' }}
        </span>
      </div>

      <!-- Tỉ số -->
      <div class="shrink-0 flex items-center gap-1.5 sm:gap-2">
        <span class="text-[20px] sm:text-[22px] font-black tabular-nums tracking-tight" :class="scoreClass">
          {{ match.team1_score ?? 0 }}
        </span>
        <span class="text-[15px] font-light ls-text-muted">–</span>
        <span class="text-[20px] sm:text-[22px] font-black tabular-nums tracking-tight" :class="scoreClass">
          {{ match.team2_score ?? 0 }}
        </span>
      </div>

      <!-- Đội 2 -->
      <div class="flex-1 min-w-0 flex items-center gap-2 sm:gap-3 justify-end">
        <span
          class="text-[14px] sm:text-[15px] font-bold truncate"
          :class="teamNameClass(2)"
          :title="match.team2?.name"
        >
          {{ match.team2?.name || 'Đội B' }}
        </span>
        <span class="hidden sm:flex w-6 h-6 rounded-md ls-team-badge-b text-[10.5px] font-bold items-center justify-center shrink-0">B</span>
      </div>

      <!-- Trạng thái + nút phát -->
      <div class="shrink-0 flex items-center gap-2 sm:gap-3 w-[104px] sm:w-[152px] justify-end">
        <span
          class="px-2 py-0.5 rounded-full text-[10.5px] sm:text-[11px] font-bold flex items-center gap-1 whitespace-nowrap"
          :class="chipClass"
        >
          <i v-if="match.is_going_on" class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></i>
          {{ chipText }}
        </span>

        <button
          v-if="!match.is_finished"
          type="button"
          class="px-3 py-1.5 rounded-lg text-[12px] sm:text-[12.5px] font-bold transition-colors whitespace-nowrap shrink-0"
          :class="match.is_going_on ? 'ls-play-live' : 'ls-play-idle'"
          @click="$emit('play')"
        >
          {{ match.is_going_on ? '▶ Phát' : 'Phát' }}
        </button>
      </div>
    </div>

    <!-- Mobile: vòng đấu + giờ ở dòng phụ -->
    <div class="sm:hidden flex items-center gap-1.5 mt-1.5">
      <span class="text-[11px] ls-text-secondary">{{ match.round || 'Trận đấu' }}</span>
      <span v-if="match.scheduled_at" class="text-[11px] ls-text-muted tabular-nums">
        · {{ formatTime(match.scheduled_at) }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    match: { type: Object, required: true },
});

defineEmits(['play']);

const formatTime = (value) => {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

const rowClass = computed(() => {
    const m = props.match;
    if (m.is_going_on) return 'ls-row-live';
    if (m.is_finished) return 'ls-row-finished';
    return 'ls-row-idle';
});

const teamNameClass = (side) => {
    const m = props.match;
    if (m.is_finished) return 'ls-text-muted';
    if (m.is_going_on) {
        return side === 1 ? 'ls-team-a' : 'ls-team-b';
    }
    return 'ls-text-primary';
};

const chipText = computed(() => {
    const m = props.match;
    if (m.is_finished) return 'Đã xong';
    if (m.is_going_on) return 'LIVE';
    return 'Chưa bắt đầu';
});

const chipClass = computed(() => {
    const m = props.match;
    if (m.is_finished) return 'ls-chip-muted';
    if (m.is_going_on) return 'ls-chip-live';
    return 'ls-chip-idle';
});

const scoreClass = computed(() => {
    const m = props.match;
    if (m.is_finished) return 'ls-text-muted';
    if (m.is_going_on) return 'ls-live';
    return 'ls-text-primary';
});
</script>

<style scoped>
/* Token màu cục bộ — tránh class global của app.css ghi đè dark mode. */
.ls-row-divider { border-color: #F2F4F7; }

.ls-text-primary { color: #3E414C; }
.ls-text-secondary { color: #5A6069; }
.ls-text-muted { color: #A8AEBA; }
.ls-live { color: #E8192C; }
.ls-team-a { color: #1D6FE0; }
.ls-team-b { color: #D32030; }

.ls-row-idle { background: #FFFFFF; }
.ls-row-idle:hover { background: #FAFAFB; }

.ls-row-live { background: #FDECEC; }
.ls-row-live:hover { background: #FBE3E3; }

.ls-row-finished { background: #FAFBFC; }

.ls-team-badge-a {
    background: #E7F0FE;
    color: #1D6FE0;
}
.ls-team-badge-b {
    background: #FDEAEC;
    color: #D32030;
}

.ls-chip-idle {
    background: #EDEFF3;
    color: #5A6069;
}
.ls-chip-muted {
    background: #EDEFF3;
    color: #8C929B;
}
.ls-chip-live {
    background: #E8192C;
    color: #FFFFFF;
}

.ls-play-live {
    background: #E8192C;
    color: #FFFFFF;
    box-shadow: 0 1px 2px rgba(232, 25, 44, 0.25);
}
.ls-play-live:hover { background: #C81426; }

.ls-play-idle {
    background: #FFFFFF;
    color: #E8192C;
    border: 1px solid #E8192C;
}
.ls-play-idle:hover { background: #FDECEC; }

/* ---------------- DARK MODE ---------------- */
html.dark .ls-row-divider { border-color: rgba(35, 49, 72, 0.7); }

html.dark .ls-text-primary { color: #F8FAFC; }
html.dark .ls-text-secondary { color: #94A3B8; }
html.dark .ls-text-muted { color: #64748B; }
html.dark .ls-live { color: #F87171; }
html.dark .ls-team-a { color: #60A5FA; }
html.dark .ls-team-b { color: #F87171; }

html.dark .ls-row-idle { background: #161F33; }
html.dark .ls-row-idle:hover { background: rgba(30, 41, 59, 0.5); }

html.dark .ls-row-live { background: rgba(239, 68, 68, 0.12); }
html.dark .ls-row-live:hover { background: rgba(239, 68, 68, 0.18); }

html.dark .ls-row-finished { background: rgba(30, 41, 59, 0.35); }

html.dark .ls-team-badge-a {
    background: rgba(59, 130, 246, 0.16);
    color: #60A5FA;
}
html.dark .ls-team-badge-b {
    background: rgba(239, 68, 68, 0.16);
    color: #F87171;
}

html.dark .ls-chip-idle {
    background: #1E293B;
    color: #CBD5E1;
}
html.dark .ls-chip-muted {
    background: #1E293B;
    color: #64748B;
}

html.dark .ls-play-idle {
    background: #1E293B;
    color: #F87171;
    border-color: rgba(248, 113, 113, 0.5);
}
html.dark .ls-play-idle:hover { background: rgba(239, 68, 68, 0.14); }
</style>
