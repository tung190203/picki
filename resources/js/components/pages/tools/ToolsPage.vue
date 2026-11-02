<template>
  <div class="ls-tools min-h-screen">
    <div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6">

      <!-- Header -->
      <div class="flex items-center gap-3 mb-6">
        <button
          @click="goBack"
          class="w-8 h-8 flex items-center justify-center rounded-full transition-colors shrink-0 ls-icon-btn"
          aria-label="Quay lại"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 class="text-[20px] font-bold tracking-[-0.5px] ls-text-primary">Công cụ</h1>
          <p class="hidden sm:block text-[12.5px] ls-text-secondary">Các công cụ hỗ trợ chấm điểm và vận hành giải</p>
        </div>
      </div>

      <!-- Group: TẠO & GHI ĐIỂM -->
      <p class="text-[11px] font-bold ls-text-secondary tracking-[1px] mb-3 px-1">TẠO & GHI ĐIỂM</p>

      <div class="ls-card rounded-[14px] border overflow-hidden shadow-sm divide-y ls-divider">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
          <button
            v-for="tool in tools"
            :key="tool.route"
            type="button"
            class="w-full flex items-center gap-3 px-4 py-4 text-left transition-colors ls-tool-btn"
            @click="open(tool.route)"
          >
            <span class="w-10 h-10 flex items-center justify-center rounded-[10px] flex-shrink-0" :class="tool.iconWrap">
              <span class="w-[20px] h-[20px]" v-html="tool.icon"></span>
            </span>
            <span class="flex-1 min-w-0">
              <span class="flex items-center gap-2">
                <span class="text-[15px] font-semibold ls-text-primary">{{ tool.label }}</span>
                <span v-if="tool.badge" class="px-1.5 py-px bg-[#1D6FE0] text-white text-[10px] font-bold rounded">{{ tool.badge }}</span>
              </span>
              <span class="block text-[13px] ls-text-secondary truncate">{{ tool.desc }}</span>
            </span>
            <svg class="w-4 h-4 ls-text-muted flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();

const ICON_LIVESCORE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="13" rx="2" /><path stroke-linecap="round" d="M8 21h8M12 17v4" /></svg>';
const ICON_SCORECARD = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h3m4 0h1M9 8h1m4 0h1M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" /></svg>';
const ICON_PAIRING = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 3v18M3.6 9h16.8M3.6 15h16.8" /></svg>';
const ICON_DRAW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></svg>';

const tools = [
    {
        route: '/tools/livescore',
        label: 'Livescore',
        desc: 'Chiếu tỉ số trực tiếp lên màn hình lớn tại sân',
        badge: 'MỚI',
        icon: ICON_LIVESCORE,
        iconWrap: 'bg-[#E7F0FE] dark:bg-blue-500/15 text-[#1D6FE0] dark:text-blue-400',
    },
    {
        route: '/scorecard',
        label: 'Scorecard realtime',
        desc: 'Chấm điểm trực tiếp theo trận',
        icon: ICON_SCORECARD,
        iconWrap: 'bg-[#F1F3F6] dark:bg-slate-700/50 text-[#5A6069] dark:text-slate-300',
    },
    {
        route: '/tools/pairing-wheel',
        label: 'Vòng quay cặp đấu',
        desc: 'Bốc cặp đấu ngẫu nhiên',
        icon: ICON_PAIRING,
        iconWrap: 'bg-[#F1F3F6] dark:bg-slate-700/50 text-[#5A6069] dark:text-slate-300',
    },
    {
        route: '/tools/group-draw-wheel',
        label: 'Bốc thăm bảng đấu',
        desc: 'Chia bảng đấu tự động',
        icon: ICON_DRAW,
        iconWrap: 'bg-[#F1F3F6] dark:bg-slate-700/50 text-[#5A6069] dark:text-slate-300',
    },
];

const goBack = () => {
  if (window.history.length > 1) router.back();
  else router.push('/');
};

const open = (path) => {
  router.push(path);
};
</script>

<style scoped>
.ls-tools {
    background: #F7F8FA;
    color: #3E414C;
}

.ls-card {
    background: #FFFFFF;
    border-color: #DCDEE6;
}

.ls-divider { border-color: #EDEFF3; }

.ls-text-primary { color: #3E414C; }
.ls-text-secondary { color: #8C929B; }
.ls-text-muted { color: #C2C6D0; }

.ls-tool-btn:hover { background: #FAFAFB; }

.ls-icon-btn { color: #5A6069; }
.ls-icon-btn:hover {
    background: #EDEFF3;
    color: #3E414C;
}

/* ---------------- DARK MODE ---------------- */
html.dark .ls-tools {
    background: #0B0F19;
    color: #F8FAFC;
}

html.dark .ls-card {
    background: #161F33;
    border-color: #233148;
}

html.dark .ls-divider { border-color: #233148; }

html.dark .ls-text-primary { color: #F8FAFC; }
html.dark .ls-text-secondary { color: #94A3B8; }
html.dark .ls-text-muted { color: #64748B; }

html.dark .ls-tool-btn:hover { background: rgba(30, 41, 59, 0.5); }

html.dark .ls-icon-btn { color: #94A3B8; }
html.dark .ls-icon-btn:hover {
    background: #1E293B;
    color: #F8FAFC;
}
</style>
