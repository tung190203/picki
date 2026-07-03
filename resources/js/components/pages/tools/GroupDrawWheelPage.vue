<template>
  <div class="wheel-page">
    <div class="container">
      <div class="status">
        <div class="hdr">
          <router-link to="/" class="back">‹</router-link>
          <h1>Bốc thăm chia bảng</h1>
        </div>
      </div>

      <div class="body-content">
        <!-- SETUP SCREEN -->
        <div class="setup" v-if="!isDrawScreen">
          <div class="cfg">
            <div class="mini">
              <div class="mlabel">Số bảng</div>
              <select v-model="numGroups">
                <option :value="2">2</option>
                <option :value="3">3</option>
                <option :value="4">4</option>
              </select>
            </div>
            <div class="mini">
              <div class="mlabel">Tổng số đội</div>
              <div class="team-count">{{ teams.length }} đội</div>
            </div>
          </div>
          
          <label class="lbl">Thêm đội</label>
          <div class="addrow">
            <input 
              type="text" 
              v-model="teamInput" 
              placeholder="Tên đội..." 
              @keydown.enter="addTeam"
            >
            <button @click="addTeam">+</button>
          </div>
          <div class="seedhint">
            💡 Bấm ⭐ để đặt hạt giống. Hạt giống cũng được quay ngẫu nhiên, nhưng mỗi bảng chỉ nhận 1 hạt giống — không đội mạnh nào cùng bảng. Tối đa số hạt giống = số bảng. Không có hạt giống cũng được.
          </div>
          
          <div class="tlist">
            <div v-for="t in teams" :key="t.name" class="titem">
              <button 
                :class="['seed-btn', { on: t.seed }]" 
                @click="toggleSeed(t.name)"
              >
                {{ t.seed ? '⭐ Hạt giống' : '☆ Hạt giống' }}
              </button>
              <div class="tname">{{ t.name }}</div>
              <button class="x" @click="removeTeam(t.name)">×</button>
            </div>
          </div>
          
          <div class="quickadd">
            <button @click="quickFill">Điền mẫu 8 đội</button>
            <button @click="clearAll">Xóa hết</button>
          </div>
          
          <div :class="['info', infoStatus.class]">
            {{ infoStatus.text }}
          </div>
          
          <button 
            class="start-btn" 
            :disabled="!canStart" 
            @click="startDraw"
          >
            Bắt đầu bốc thăm
          </button>
        </div>

        <!-- DRAW SCREEN -->
        <div class="drawwrap" v-else>
          <div :class="['phase-pill', drawPhase === 'seed' ? 'seed' : 'normal']" v-if="!(seedPool.length === 0 && remaining.length === 0)">
            {{ drawPhase === 'seed' ? '⭐ ĐANG QUAY HẠT GIỐNG' : '🎯 ĐANG QUAY CÁC ĐỘI' }}
          </div>
          <div class="progress">{{ progressText }}</div>
          
          <div class="drawing-name" v-html="drawingNameHtml"></div>
          
          <div class="wheel-container" v-show="activePool.length > 0">
            <div class="pointer"></div>
            <div 
              class="wheel" 
              :style="{ transform: `rotate(${rot}deg)`, transition: wheelTransition }"
            >
              <svg viewBox="0 0 100 100">
                <template v-if="activePool.length === 1">
                  <circle cx="50" cy="50" r="50" :fill="activeColors[0]" />
                  <text x="50" y="20" fill="#fff" font-size="6" font-weight="700" text-anchor="middle" dominant-baseline="middle">
                    {{ truncate(activePool[0], 9) }}
                  </text>
                </template>
                <template v-else-if="activePool.length > 1">
                  <g v-for="(p, i) in activePool" :key="p">
                    <path :d="getPathDef(i, activePool.length)" :fill="activeColors[i % activeColors.length]" />
                    <text 
                      :x="getTextX(i, activePool.length)" 
                      :y="getTextY(i, activePool.length)" 
                      fill="#fff" 
                      :font-size="activePool.length > 8 ? 3 : 3.8" 
                      font-weight="700" 
                      text-anchor="middle" 
                      dominant-baseline="middle" 
                      :transform="getTextTransform(i, activePool.length)"
                    >
                      {{ truncate(p, 7) }}
                    </text>
                  </g>
                </template>
              </svg>
            </div>
            <div class="wheel-center">🏓</div>
          </div>
          
          <div 
            :class="['into', { seed: drawPhase === 'seed' }]" 
            v-html="intoHtml" 
            v-if="!(seedPool.length === 0 && remaining.length === 0)"
          ></div>
          
          <button 
            v-if="!(seedPool.length === 0 && remaining.length === 0)"
            :class="['spin-btn', drawPhase === 'seed' ? 'seed' : 'normal']" 
            :disabled="spinning || activePool.length === 0" 
            @click="spin"
          >
            {{ drawPhase === 'seed' ? 'QUAY HẠT GIỐNG ⭐' : 'BỐC THĂM 🎯' }}
          </button>
          
          <div class="groups">
            <h3>
              Kết quả chia bảng 
              <button class="reset" @click="startDraw" v-if="groups.length > 0">↻ Bốc lại</button>
            </h3>
            
            <div class="ggrid" :style="{ gridTemplateColumns: groups.length > 2 ? 'repeat(2, 1fr)' : `repeat(${groups.length}, 1fr)` }">
              <div v-for="(g, gi) in groups" :key="gi" class="gcard">
                <div class="gtitle">🏆 Bảng {{ g.letter }}</div>
                
                <div 
                  v-for="i in slotsPerGroup" 
                  :key="i" 
                  :class="['gslot', g.teams[i-1] ? 'filled' : 'empty', g.teams[i-1]?.seed ? 'seed' : '']"
                >
                  <div class="gn">{{ i }}</div>
                  <template v-if="g.teams[i-1]">
                    <div class="gname">{{ g.teams[i-1].name }}</div>
                    <span v-if="g.teams[i-1].seed" class="seed-badge">HG</span>
                  </template>
                  <template v-else>
                    <div class="gname">— trống —</div>
                  </template>
                </div>
              </div>
            </div>
            
            <div class="done-banner" v-if="seedPool.length === 0 && remaining.length === 0 && isDrawScreen">
              ✓ Đã chia xong tất cả các bảng!
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const COLORS = ['#E8192C','#F2637A','#2563EB','#0E9F6E','#D97706','#8B5CF6','#EC4899','#0891B2','#EA580C','#65A30D','#DB2777','#4F46E5','#0D9488','#CA8A04','#7C3AED','#DC2626'];
const SEED_COLORS = ['#D97706','#F59E0B','#EAB308','#CA8A04'];
const GLETTER = ['A','B','C','D'];

const numGroups = ref(2);
const teamInput = ref('');
const teams = ref([]); // {name, seed}

const isDrawScreen = ref(false);
const groups = ref([]); 
const seedPool = ref([]);
const remaining = ref([]);
const drawPhase = ref('normal');
const spinning = ref(false);
const rot = ref(0);
const wheelTransition = ref('none');
const drawingNameHtml = ref('');

const activePool = computed(() => drawPhase.value === 'seed' ? seedPool.value : remaining.value);
const activeColors = computed(() => drawPhase.value === 'seed' ? SEED_COLORS : COLORS);

const canStart = computed(() => teams.value.length >= numGroups.value * 2);

const infoStatus = computed(() => {
  const ng = numGroups.value;
  const len = teams.value.length;
  const seedsCount = teams.value.filter(t => t.seed).length;
  
  if (len < ng * 2) {
    return { class: '', text: `Cần tối thiểu ${ng * 2} đội cho ${ng} bảng.` };
  }
  
  const per = len / ng;
  let msg = len % ng !== 0
    ? `${len} đội không chia đều ${ng} bảng — các bảng lệch ${Math.ceil(per)}/${Math.floor(per)}.`
    : `✓ ${len} đội → ${ng} bảng, mỗi bảng ${per} đội.`;
    
  if (seedsCount > 0) {
    msg += ` ${seedsCount} hạt giống sẽ quay vào ${seedsCount} bảng khác nhau.`;
  }
  
  return { 
    class: len % ng !== 0 ? 'warn' : 'ok', 
    text: msg 
  };
});

const slotsPerGroup = computed(() => {
  if (teams.value.length === 0) return 0;
  return Math.ceil(teams.value.length / groups.value.length);
});

const progressText = computed(() => {
  if (!isDrawScreen.value) return '';
  const placed = groups.value.reduce((s, g) => s + g.teams.length, 0);
  if (drawPhase.value === 'seed') {
    return `Hạt giống: còn ${seedPool.value.length} chưa quay`;
  } else {
    return `Đã xếp ${placed}/${teams.value.length} đội`;
  }
});

const intoHtml = computed(() => {
  if (!isDrawScreen.value) return '';
  if (activePool.value.length > 0) {
    const gi = getNextGroupIndex();
    if (drawPhase.value === 'seed') {
      return `Hạt giống bốc được sẽ vào <b>Bảng ${groups.value[gi].letter}</b>`;
    } else {
      return `Đội bốc được sẽ vào <b>Bảng ${groups.value[gi].letter}</b>`;
    }
  }
  return '';
});

function addTeam() {
  const v = teamInput.value.trim();
  if (!v) return;
  if (!teams.value.find(t => t.name === v)) {
    teams.value.push({ name: v, seed: false });
  }
  teamInput.value = '';
}

function removeTeam(name) {
  teams.value = teams.value.filter(t => t.name !== name);
}

function toggleSeed(name) {
  const t = teams.value.find(x => x.name === name);
  const ng = numGroups.value;
  const seedsCount = teams.value.filter(x => x.seed).length;
  
  if (!t.seed && seedsCount >= ng) {
    alert(`Tối đa ${ng} hạt giống (bằng số bảng).`);
    return;
  }
  t.seed = !t.seed;
}

function quickFill() {
  teams.value = ['Team Rồng','Team Hổ','Team Báo','Team Sói','Team Ưng','Team Gấu','Team Cáo','Team Nai']
    .map((n, i) => ({ name: n, seed: i < 2 }));
}

function clearAll() {
  teams.value = [];
}

function startDraw() {
  const ng = numGroups.value;
  groups.value = Array.from({ length: ng }, (_, i) => ({
    letter: GLETTER[i],
    teams: []
  }));
  
  seedPool.value = teams.value.filter(t => t.seed).map(t => t.name);
  remaining.value = teams.value.filter(t => !t.seed).map(t => t.name);
  drawPhase.value = seedPool.value.length > 0 ? 'seed' : 'normal';
  rot.value = 0;
  wheelTransition.value = 'none';
  drawingNameHtml.value = '';
  isDrawScreen.value = true;
}

function getNextGroupIndex() {
  if (drawPhase.value === 'seed') {
    // Group without a seed
    for (let i = 0; i < groups.value.length; i++) {
      if (!groups.value[i].teams.some(t => t.seed)) return i;
    }
  }
  
  // Group with the fewest teams
  let min = Infinity;
  let gi = 0;
  for (let i = 0; i < groups.value.length; i++) {
    if (groups.value[i].teams.length < min) {
      min = groups.value[i].teams.length;
      gi = i;
    }
  }
  return gi;
}

// SVG helpers
function getPathDef(i, n) {
  const cx = 50, cy = 50, r = 50;
  const a0 = (i / n) * 2 * Math.PI - Math.PI / 2;
  const a1 = ((i + 1) / n) * 2 * Math.PI - Math.PI / 2;
  const x0 = cx + r * Math.cos(a0);
  const y0 = cy + r * Math.sin(a0);
  const x1 = cx + r * Math.cos(a1);
  const y1 = cy + r * Math.sin(a1);
  const large = (a1 - a0) > Math.PI ? 1 : 0;
  return `M${cx},${cy} L${x0},${y0} A${r},${r} 0 ${large},1 ${x1},${y1} Z`;
}

function getMidAngle(i, n) {
  const a0 = (i / n) * 2 * Math.PI - Math.PI / 2;
  const a1 = ((i + 1) / n) * 2 * Math.PI - Math.PI / 2;
  return (a0 + a1) / 2;
}

function getTextX(i, n) {
  const am = getMidAngle(i, n);
  return 50 + 50 * 0.62 * Math.cos(am);
}

function getTextY(i, n) {
  const am = getMidAngle(i, n);
  return 50 + 50 * 0.62 * Math.sin(am);
}

function getTextTransform(i, n) {
  const am = getMidAngle(i, n);
  const tx = getTextX(i, n);
  const ty = getTextY(i, n);
  return `rotate(${am * 180 / Math.PI + 90} ${tx} ${ty})`;
}

function truncate(str, len) {
  return str.length > len + 1 ? str.slice(0, len) + '…' : str;
}

function spin() {
  if (spinning.value) return;
  const pool = activePool.value;
  if (pool.length === 0) return;
  
  spinning.value = true;
  
  const n = pool.length;
  const pick = Math.floor(Math.random() * n);
  const seg = 360 / n;
  const targetMid = pick * seg + seg / 2;
  const spins = 5;
  
  const add = 360 * spins - targetMid + (360 - (rot.value % 360));
  rot.value += add;
  
  wheelTransition.value = 'transform 4s cubic-bezier(.17,.67,.24,1)';
  
  setTimeout(() => {
    const isSeed = drawPhase.value === 'seed';
    // Modify array, trigger reactivity
    const name = pool.splice(pick, 1)[0];
    const gi = getNextGroupIndex();
    
    groups.value[gi].teams.push({ name, seed: isSeed });
    drawingNameHtml.value = `<b style="color:${isSeed ? '#D97706' : '#E8192C'}">${name}</b> → Bảng ${groups.value[gi].letter}`;
    
    if (drawPhase.value === 'seed' && seedPool.value.length === 0) {
      drawPhase.value = 'normal';
    }
    
    spinning.value = false;
  }, 4100);
}
</script>

<style scoped>
:root {
  --red: #E8192C; --red-tint: #FDECEE;
  --ink: #1A1A1A; --gray: #6B7280; --gray-light: #9CA3AF;
  --field: #F3F4F6; --field-border: #E5E7EB;
  --white: #fff; --ok: #0E9F6E; --ok-tint: #E7F6EF;
  --gold: #D97706; --gold-tint: #FEF6E7;
}

.wheel-page {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  background: #E9EAEC;
  color: #1A1A1A;
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
}

.container {
  width: 100%;
  max-width: 800px; /* Web layout */
  background: #fff;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,.18);
  display: flex;
  flex-direction: column;
  min-height: 700px;
}

.hdr {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 24px;
  border-bottom: 1px solid #E5E7EB;
}

.back {
  width: 38px;
  height: 38px;
  border-radius: 11px;
  background: #F3F4F6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 19px;
  cursor: pointer;
  text-decoration: none;
  color: #1A1A1A;
}

.hdr h1 {
  font-size: 20px;
  font-weight: 800;
  margin: 0;
}

.body-content {
  flex: 1;
  padding: 24px;
  overflow-y: auto;
}

.lbl { font-size: 15px; font-weight: 700; margin: 24px 0 12px; display: block; }
.cfg { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
@media (min-width: 600px) {
  .cfg { grid-template-columns: 200px 200px; }
}

.mini { background: #F3F4F6; border-radius: 12px; padding: 12px 16px; }
.mini .mlabel { font-size: 13px; color: #6B7280; font-weight: 600; margin-bottom: 8px; }
.mini select { width: 100%; border: none; background: #fff; border-radius: 8px; padding: 10px; font-size: 15px; font-weight: 700; font-family: inherit; color: #1A1A1A; outline: none; }
.team-count { padding: 8px; font-size: 16px; font-weight: 700; }

.addrow { display: flex; gap: 8px; }
.addrow input { flex: 1; background: #F3F4F6; border: 1.5px solid transparent; border-radius: 12px; padding: 14px 16px; font-size: 15px; font-family: inherit; outline: none; }
.addrow input:focus { border-color: #E8192C; background: #fff; }
.addrow button { width: 52px; border: none; border-radius: 12px; background: #E8192C; color: #fff; font-size: 24px; cursor: pointer; }

.tlist { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
.titem { display: flex; align-items: center; gap: 12px; background: #F3F4F6; border-radius: 12px; padding: 12px 16px; }
.titem .tname { flex: 1; font-size: 15px; font-weight: 600; }
.seed-btn { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; border: 1.5px solid #E5E7EB; background: #fff; color: #9CA3AF; border-radius: 9px; padding: 8px 12px; cursor: pointer; font-family: inherit; transition: all 0.2s; }
.seed-btn.on { border-color: #D97706; background: #FEF6E7; color: #D97706; }
.titem .x { width: 28px; height: 28px; border-radius: 50%; background: #fff; border: none; color: #9CA3AF; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; }

.quickadd { display: flex; gap: 10px; margin-top: 24px; }
.quickadd button { font-size: 13px; font-weight: 600; color: #6B7280; background: #F3F4F6; border: none; border-radius: 9px; padding: 10px 16px; cursor: pointer; }

.info { font-size: 14px; margin-top: 24px; padding: 14px 16px; border-radius: 11px; line-height: 1.5; background: #F3F4F6; color: #6B7280; }
.info.warn { background: #FEF6E7; color: #D97706; font-weight: 600; }
.info.ok { background: #E7F6EF; color: #0E9F6E; font-weight: 600; }

.seedhint { font-size: 13px; color: #9CA3AF; margin-top: 12px; line-height: 1.5; }

.start-btn { width: 100%; margin-top: 24px; background: #E8192C; color: #fff; border: none; border-radius: 15px; padding: 18px; font-size: 16px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 20px rgba(232,25,44,.3); transition: transform 0.1s; }
.start-btn:active:not(:disabled) { transform: scale(0.98); }
.start-btn:disabled { background: #E5B5BA; box-shadow: none; cursor: not-allowed; }

.drawwrap { display: flex; flex-direction: column; align-items: center; }
.phase-pill { font-size: 13px; font-weight: 800; padding: 6px 16px; border-radius: 20px; margin-bottom: 12px; color: #fff; }
.phase-pill.seed { background: #D97706; }
.phase-pill.normal { background: #E8192C; }

.progress { font-size: 15px; color: #6B7280; font-weight: 700; margin-bottom: 8px; }
.drawing-name { font-size: 14px; color: #9CA3AF; margin-bottom: 16px; min-height: 22px; text-align: center; }
.drawing-name :deep(b) { color: #E8192C; font-weight: 800; font-size: 16px; }

.wheel-container { position: relative; width: 280px; height: 280px; margin-bottom: 16px; }
@media (max-width: 600px) {
  .wheel-container { width: 210px; height: 210px; }
}
.pointer { position: absolute; top: -8px; left: 50%; transform: translateX(-50%); z-index: 10; width: 0; height: 0; border-left: 14px solid transparent; border-right: 14px solid transparent; border-top: 24px solid #E8192C; filter: drop-shadow(0 2px 4px rgba(0,0,0,.3)); }
.wheel { width: 100%; height: 100%; border-radius: 50%; position: relative; box-shadow: 0 8px 30px rgba(0,0,0,.18); border: 8px solid #fff; }
.wheel svg { width: 100%; height: 100%; display: block; border-radius: 50%; }
.wheel-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 56px; height: 56px; background: #fff; border-radius: 50%; box-shadow: 0 2px 12px rgba(0,0,0,.2); display: flex; align-items: center; justify-content: center; font-size: 24px; z-index: 5; }

.into { font-size: 15px; color: #6B7280; margin-top: 16px; font-weight: 600; min-height: 24px; text-align: center; }
.into :deep(b) { color: #E8192C; font-weight: 800; font-size: 17px; }
.into.seed :deep(b) { color: #D97706; }

.spin-btn { width: 100%; max-width: 340px; margin-top: 20px; color: #fff; border: none; border-radius: 15px; padding: 18px; font-size: 16px; font-weight: 800; cursor: pointer; transition: transform 0.1s; }
.spin-btn:active:not(:disabled) { transform: scale(0.98); }
.spin-btn.seed { background: #D97706; box-shadow: 0 8px 20px rgba(217,119,6,.3); }
.spin-btn.normal { background: #E8192C; box-shadow: 0 8px 20px rgba(232,25,44,.3); }
.spin-btn:disabled { background: #E5B5BA; box-shadow: none; cursor: default; }

.groups { width: 100%; margin-top: 32px; border-top: 1px solid #E5E7EB; padding-top: 24px; }
.groups h3 { font-size: 16px; font-weight: 700; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
.groups h3 .reset { font-size: 14px; font-weight: 700; color: #E8192C; background: none; border: none; cursor: pointer; padding: 6px 12px; border-radius: 8px; }
.groups h3 .reset:hover { background: #FDECEE; }

.ggrid { display: grid; gap: 16px; grid-template-columns: 1fr; }
@media (min-width: 600px) {
  .ggrid { grid-template-columns: 1fr 1fr; }
}

.gcard { background: #F3F4F6; border-radius: 16px; padding: 16px; }
.gcard .gtitle { font-size: 15px; font-weight: 800; color: #E8192C; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.gslot { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #E5E7EB; }
.gslot:last-child { border-bottom: none; }
.gslot .gn { width: 28px; height: 28px; border-radius: 8px; background: #fff; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; color: #6B7280; flex-shrink: 0; }
.gslot .gname { flex: 1; font-size: 15px; font-weight: 600; }
.gslot.filled { animation: pop .4s ease; }
.gslot.seed .gname { color: #D97706; font-weight: 800; }
.gslot.seed .gn { background: #D97706; color: #fff; }
.gslot .seed-badge { font-size: 10px; font-weight: 800; color: #fff; background: #D97706; padding: 3px 8px; border-radius: 6px; }
.gslot.empty .gname { color: #9CA3AF; font-style: italic; font-weight: 400; }

@keyframes pop { from{opacity:0; transform:scale(.95)} to{opacity:1; transform:scale(1)} }
.done-banner { background: #E7F6EF; border-radius: 16px; padding: 20px; text-align: center; font-size: 16px; font-weight: 700; color: #0E9F6E; margin-top: 24px; }
</style>
