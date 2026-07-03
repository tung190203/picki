<template>
  <div class="wheel-page">
    <div class="container">
      <div class="status">
        <div class="hdr">
          <router-link to="/" class="back">‹</router-link>
          <h1>Vòng quay ghép cặp A/B</h1>
        </div>
      </div>

      <div class="body-content">
        <!-- SETUP SCREEN -->
        <div class="setup" v-if="!isWheelScreen">
          <div class="setup-grid">
            <div class="col-group">
              <div class="grouphdr">
                <span class="dot a"></span>
                <span class="lbl">Nhóm A</span>
                <span class="sub">— thường là người mạnh</span>
              </div>
              <div class="addrow">
                <input 
                  type="text" 
                  v-model="inputA" 
                  placeholder="Tên người nhóm A..." 
                  @keydown.enter="addP('A')"
                >
                <button class="a" @click="addP('A')">+</button>
              </div>
              <div class="plist">
                <div v-for="p in A" :key="p" class="ptag a">
                  {{ p }}
                  <button class="x" @click="removeP('A', p)">×</button>
                </div>
              </div>
            </div>

            <div class="col-group">
              <div class="grouphdr">
                <span class="dot b"></span>
                <span class="lbl">Nhóm B</span>
                <span class="sub">— thường là người yếu hơn</span>
              </div>
              <div class="addrow">
                <input 
                  type="text" 
                  v-model="inputB" 
                  placeholder="Tên người nhóm B..." 
                  @keydown.enter="addP('B')"
                >
                <button class="b" @click="addP('B')">+</button>
              </div>
              <div class="plist">
                <div v-for="p in B" :key="p" class="ptag b">
                  {{ p }}
                  <button class="x" @click="removeP('B', p)">×</button>
                </div>
              </div>
            </div>
          </div>

          <div class="quickadd">
            <button @click="quickFill">Điền mẫu 4+4</button>
            <button @click="clearAll">Xóa hết</button>
          </div>
          
          <div :class="['balance', balanceStatus.class]">
            {{ balanceStatus.text }}
          </div>
          
          <button 
            class="start-btn" 
            :disabled="!canStart" 
            @click="startWheel"
          >
            Bắt đầu quay
          </button>
        </div>

        <!-- WHEELS SCREEN -->
        <div class="wheelwrap" v-else>
          <div class="progress">
            Đã ghép {{ pairs.length }} cặp · còn {{ remA.length }} cặp chưa quay
          </div>
          
          <div class="twowheels">
            <!-- WHEEL A -->
            <div class="wcol">
              <div class="wlabel a">NHÓM A</div>
              <div class="wheel-container">
                <div class="pointer"></div>
                <div 
                  class="wheel" 
                  :style="{ transform: `rotate(${rotA}deg)`, transition: wheelTransitionA }"
                >
                  <svg viewBox="0 0 100 100" v-if="remA.length > 0">
                    <template v-if="remA.length === 1">
                      <circle cx="50" cy="50" r="50" :fill="CA[0]" />
                      <text x="50" y="20" fill="#fff" font-size="7" font-weight="700" text-anchor="middle" dominant-baseline="middle">
                        {{ truncate(remA[0], 8) }}
                      </text>
                    </template>
                    <template v-else>
                      <g v-for="(p, i) in remA" :key="p">
                        <path :d="getPathDef(i, remA.length)" :fill="CA[i % CA.length]" />
                        <text 
                          :x="getTextX(i, remA.length)" 
                          :y="getTextY(i, remA.length)" 
                          fill="#fff" 
                          :font-size="remA.length > 6 ? 4 : 5" 
                          font-weight="700" 
                          text-anchor="middle" 
                          dominant-baseline="middle" 
                          :transform="getTextTransform(i, remA.length)"
                        >
                          {{ truncate(p, 6) }}
                        </text>
                      </g>
                    </template>
                  </svg>
                </div>
                <div class="wheel-center" v-if="remA.length > 0">🏓</div>
              </div>
            </div>

            <!-- WHEEL B -->
            <div class="wcol">
              <div class="wlabel b">NHÓM B</div>
              <div class="wheel-container">
                <div class="pointer"></div>
                <div 
                  class="wheel" 
                  :style="{ transform: `rotate(${rotB}deg)`, transition: wheelTransitionB }"
                >
                  <svg viewBox="0 0 100 100" v-if="remB.length > 0">
                    <template v-if="remB.length === 1">
                      <circle cx="50" cy="50" r="50" :fill="CB[0]" />
                      <text x="50" y="20" fill="#fff" font-size="7" font-weight="700" text-anchor="middle" dominant-baseline="middle">
                        {{ truncate(remB[0], 8) }}
                      </text>
                    </template>
                    <template v-else>
                      <g v-for="(p, i) in remB" :key="p">
                        <path :d="getPathDef(i, remB.length)" :fill="CB[i % CB.length]" />
                        <text 
                          :x="getTextX(i, remB.length)" 
                          :y="getTextY(i, remB.length)" 
                          fill="#fff" 
                          :font-size="remB.length > 6 ? 4 : 5" 
                          font-weight="700" 
                          text-anchor="middle" 
                          dominant-baseline="middle" 
                          :transform="getTextTransform(i, remB.length)"
                        >
                          {{ truncate(p, 6) }}
                        </text>
                      </g>
                    </template>
                  </svg>
                </div>
                <div class="wheel-center" v-if="remB.length > 0">🏓</div>
              </div>
            </div>
          </div>

          <div class="picked-row">
            <div :class="['picked', pickedA ? 'a' : 'empty a']">{{ pickedA || '?' }}</div>
            <div :class="['picked', pickedB ? 'b' : 'empty b']">{{ pickedB || '?' }}</div>
          </div>
          
          <button 
            class="spin-btn" 
            :disabled="spinning || remA.length === 0" 
            @click="spin"
          >
            QUAY CẢ HAI 🎯
          </button>
          
          <div class="pairs" v-if="pairs.length > 0">
            <h3>Các cặp đã ghép <button class="reset" @click="startWheel">↻ Quay lại</button></h3>
            <div class="pairs-grid">
              <div v-for="(p, i) in pairs" :key="i" class="pair">
                <div class="pnum">{{ i + 1 }}</div>
                <div class="names">
                  <span class="mini-tag a">A</span>{{ p.a }}
                  <span class="amp">&</span>
                  <span class="mini-tag b">B</span>{{ p.b }}
                </div>
              </div>
            </div>
            
            <div class="done-banner" v-if="remA.length === 0">
              ✓ Đã ghép xong {{ pairs.length }} cặp (mỗi cặp 1 A + 1 B)!
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const CA = ['#2563EB','#3B82F6','#60A5FA','#1D4ED8','#1E40AF','#38BDF8','#0EA5E9','#818CF8'];
const CB = ['#B45309','#D97706','#F59E0B','#92400E','#EA580C','#FB923C','#EAB308','#CA8A04'];

const inputA = ref('');
const inputB = ref('');
const A = ref([]);
const B = ref([]);
const remA = ref([]);
const remB = ref([]);
const pairs = ref([]);

const isWheelScreen = ref(false);
const spinning = ref(false);

const rotA = ref(0);
const rotB = ref(0);
const wheelTransitionA = ref('none');
const wheelTransitionB = ref('none');

const pickedA = ref(null);
const pickedB = ref(null);

const canStart = computed(() => {
  return A.value.length > 0 && B.value.length > 0 && A.value.length === B.value.length;
});

const balanceStatus = computed(() => {
  if (A.value.length === 0 || B.value.length === 0) {
    return { class: '', text: 'Thêm người vào cả 2 nhóm. Số lượng A và B phải bằng nhau.' };
  } else if (A.value.length !== B.value.length) {
    return { class: 'warn', text: `Chưa cân: Nhóm A có ${A.value.length}, Nhóm B có ${B.value.length}. Cần bằng nhau để ghép cặp.` };
  } else {
    return { class: 'ok', text: `✓ Cân bằng: ${A.value.length} người mỗi nhóm → sẽ ghép ${A.value.length} cặp.` };
  }
});

function addP(group) {
  const v = group === 'A' ? inputA.value.trim() : inputB.value.trim();
  if (!v) return;
  const arr = group === 'A' ? A.value : B.value;
  if (!arr.includes(v)) {
    arr.push(v);
  }
  if (group === 'A') inputA.value = '';
  else inputB.value = '';
}

function removeP(group, name) {
  if (group === 'A') {
    A.value = A.value.filter(x => x !== name);
  } else {
    B.value = B.value.filter(x => x !== name);
  }
}

function quickFill() {
  A.value = ['Phong', 'Thắng', 'Việt Anh', 'Tùng'];
  B.value = ['Nghĩa', 'Trang', 'Khải', 'Hương'];
}

function clearAll() {
  A.value = [];
  B.value = [];
}

function startWheel() {
  remA.value = [...A.value];
  remB.value = [...B.value];
  pairs.value = [];
  rotA.value = 0;
  rotB.value = 0;
  wheelTransitionA.value = 'none';
  wheelTransitionB.value = 'none';
  pickedA.value = null;
  pickedB.value = null;
  isWheelScreen.value = true;
}

// Wheel SVG helpers
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
  return 50 + 50 * 0.6 * Math.cos(am);
}

function getTextY(i, n) {
  const am = getMidAngle(i, n);
  return 50 + 50 * 0.6 * Math.sin(am);
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

function spinOne(group) {
  const rem = group === 'A' ? remA.value : remB.value;
  const n = rem.length;
  const pick = Math.floor(Math.random() * n);
  const seg = 360 / n;
  const targetMid = pick * seg + seg / 2;
  const spins = 5;
  
  const cur = group === 'A' ? rotA.value : rotB.value;
  const add = 360 * spins - targetMid + (360 - (cur % 360));
  const newRot = cur + add;
  
  if (group === 'A') {
    rotA.value = newRot;
    wheelTransitionA.value = 'transform 4s cubic-bezier(.17,.67,.24,1)';
  } else {
    rotB.value = newRot;
    wheelTransitionB.value = 'transform 4s cubic-bezier(.17,.67,.24,1)';
  }
  
  return pick;
}

function spin() {
  if (spinning.value || remA.value.length === 0) return;
  spinning.value = true;
  pickedA.value = null;
  pickedB.value = null;
  
  const pickAIdx = spinOne('A');
  const pickBIdx = spinOne('B');
  
  setTimeout(() => {
    const pA = remA.value.splice(pickAIdx, 1)[0];
    const pB = remB.value.splice(pickBIdx, 1)[0];
    
    pickedA.value = pA;
    pickedB.value = pB;
    
    pairs.value.push({ a: pA, b: pB });
    spinning.value = false;
  }, 4100);
}
</script>

<style scoped>
/* Scoped styles specifically adapted for web UI */
:root {
  --red: #E8192C; --red-tint: #FDECEE;
  --ink: #1A1A1A; --gray: #6B7280; --gray-light: #9CA3AF;
  --field: #F3F4F6; --field-border: #E5E7EB;
  --white: #fff; --ok: #0E9F6E; --ok-tint: #E7F6EF;
  --blue: #2563EB; --blue-tint: #EFF4FF;
  --amber: #B45309; --amber-tint: #FEF6E7;
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
  max-width: 800px; /* Widened for web */
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

/* ==== SETUP ==== */
.setup-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}
@media (min-width: 600px) {
  .setup-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.grouphdr { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.grouphdr .dot { width: 12px; height: 12px; border-radius: 50%; }
.grouphdr .dot.a { background: #2563EB; }
.grouphdr .dot.b { background: #B45309; }
.grouphdr .lbl { font-size: 15px; font-weight: 800; }
.grouphdr .sub { font-size: 13px; color: #9CA3AF; font-weight: 500; }

.addrow { display: flex; gap: 8px; }
.addrow input { flex: 1; background: #F3F4F6; border: 1.5px solid transparent; border-radius: 12px; padding: 12px 14px; font-size: 15px; font-family: inherit; outline: none; }
.addrow input:focus { border-color: #E8192C; background: #fff; }
.addrow button { width: 48px; border: none; border-radius: 12px; color: #fff; font-size: 24px; cursor: pointer; }
.addrow button.a { background: #2563EB; }
.addrow button.b { background: #B45309; }

.plist { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 12px; min-height: 8px; }
.ptag { display: flex; align-items: center; gap: 6px; border-radius: 20px; padding: 7px 7px 7px 13px; font-size: 14px; font-weight: 600; }
.ptag.a { background: #EFF4FF; color: #2563EB; }
.ptag.b { background: #FEF6E7; color: #B45309; }
.ptag .x { width: 22px; height: 22px; border-radius: 50%; background: #fff; border: none; color: #6B7280; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; }

.quickadd { display: flex; gap: 10px; margin-top: 24px; }
.quickadd button { font-size: 13px; font-weight: 600; color: #6B7280; background: #F3F4F6; border: none; border-radius: 9px; padding: 10px 16px; cursor: pointer; }
.quickadd button:hover { background: #E5E7EB; }

.balance { font-size: 14px; margin-top: 20px; padding: 14px 16px; border-radius: 11px; line-height: 1.4; background: #F3F4F6; color: #6B7280; }
.balance.warn { background: #FEF6E7; color: #B45309; font-weight: 600; }
.balance.ok { background: #E7F6EF; color: #0E9F6E; font-weight: 600; }

.start-btn { width: 100%; margin-top: 24px; background: #E8192C; color: #fff; border: none; border-radius: 15px; padding: 18px; font-size: 16px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 20px rgba(232,25,44,.3); transition: transform 0.1s; }
.start-btn:active:not(:disabled) { transform: scale(0.98); }
.start-btn:disabled { background: #E5B5BA; box-shadow: none; cursor: not-allowed; }

/* ==== WHEELS ==== */
.wheelwrap { display: flex; flex-direction: column; align-items: center; }
.progress { font-size: 15px; color: #6B7280; font-weight: 700; margin-bottom: 24px; }

.twowheels { display: flex; gap: 40px; width: 100%; justify-content: center; margin-bottom: 20px; }
@media (max-width: 600px) {
  .twowheels { gap: 16px; }
}

.wcol { display: flex; flex-direction: column; align-items: center; }
.wlabel { font-size: 13px; font-weight: 800; padding: 4px 16px; border-radius: 8px; color: #fff; margin-bottom: 12px; }
.wlabel.a { background: #2563EB; }
.wlabel.b { background: #B45309; }

.wheel-container { position: relative; width: 200px; height: 200px; }
@media (max-width: 600px) {
  .wheel-container { width: 150px; height: 150px; }
}

.pointer { position: absolute; top: -6px; left: 50%; transform: translateX(-50%); z-index: 10; width: 0; height: 0; border-left: 12px solid transparent; border-right: 12px solid transparent; border-top: 20px solid #E8192C; filter: drop-shadow(0 2px 4px rgba(0,0,0,.3)); }
.wheel { width: 100%; height: 100%; border-radius: 50%; position: relative; box-shadow: 0 8px 24px rgba(0,0,0,.15); border: 6px solid #fff; }
.wheel svg { width: 100%; height: 100%; display: block; border-radius: 50%; }
.wheel-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 44px; height: 44px; background: #fff; border-radius: 50%; box-shadow: 0 2px 10px rgba(0,0,0,.2); display: flex; align-items: center; justify-content: center; font-size: 18px; z-index: 5; }

.spin-btn { width: 100%; max-width: 320px; margin-top: 24px; background: #E8192C; color: #fff; border: none; border-radius: 15px; padding: 18px; font-size: 16px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 20px rgba(232,25,44,.3); transition: transform 0.1s; }
.spin-btn:active:not(:disabled) { transform: scale(0.98); }
.spin-btn:disabled { background: #E5B5BA; box-shadow: none; cursor: default; }

.picked-row { display: flex; gap: 16px; margin-top: 20px; width: 100%; justify-content: center; }
.picked { width: 200px; text-align: center; padding: 12px; border-radius: 12px; font-size: 16px; font-weight: 700; min-height: 46px; display: flex; align-items: center; justify-content: center; }
@media (max-width: 600px) {
  .picked-row { gap: 8px; }
  .picked { width: 150px; font-size: 14px; }
}

.picked.a { background: #EFF4FF; color: #2563EB; }
.picked.b { background: #FEF6E7; color: #B45309; }
.picked.empty { background: #F3F4F6; color: #9CA3AF; font-weight: 400; }

/* pairs result */
.pairs { width: 100%; margin-top: 32px; border-top: 1px solid #E5E7EB; padding-top: 24px; }
.pairs h3 { font-size: 16px; font-weight: 700; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
.pairs h3 .reset { font-size: 14px; font-weight: 700; color: #E8192C; background: none; border: none; cursor: pointer; padding: 6px 12px; border-radius: 8px; }
.pairs h3 .reset:hover { background: #FDECEE; }

.pairs-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
@media (min-width: 600px) {
  .pairs-grid { grid-template-columns: 1fr 1fr; }
}

.pair { display: flex; align-items: center; gap: 12px; background: #F3F4F6; border-radius: 14px; padding: 12px 16px; animation: pop .4s ease; }
@keyframes pop { from{opacity:0; transform:scale(.95)} to{opacity:1; transform:scale(1)} }

.pair .pnum { width: 30px; height: 30px; border-radius: 8px; background: #E8192C; color: #fff; font-size: 14px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.pair .names { flex: 1; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.mini-tag { font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 5px; color: #fff; }
.mini-tag.a { background: #2563EB; }
.mini-tag.b { background: #B45309; }
.amp { color: #9CA3AF; }
.done-banner { background: #E7F6EF; border-radius: 14px; padding: 16px; text-align: center; font-size: 15px; font-weight: 700; color: #0E9F6E; margin-top: 16px; }
</style>
