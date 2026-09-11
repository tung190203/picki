<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div v-if="modelValue" class="fixed inset-0 z-[10010] flex items-start justify-center p-4 bg-gray-900 bg-opacity-50 backdrop-blur-sm overflow-y-auto" @click.self="closeModal">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl transform transition-all duration-300 my-8">
                    <!-- Header -->
                    <div class="p-5 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white rounded-t-xl z-10">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Ghép cặp thủ công</h3>
                            <p class="text-sm text-gray-500 mt-1">Kéo thả hoặc nhấn vào đội để đưa vào cặp đấu</p>
                        </div>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="p-5 max-h-[calc(100vh-200px)] overflow-y-auto">
                        <!-- Info Banner -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-5 flex items-start gap-2">
                            <InformationCircleIcon class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
                            <div class="text-sm text-blue-700">
                                <p v-if="isRebuildMode">Mỗi cặp cần <strong>2 đội</strong>. Kéo thả đội từ danh sách bên dưới vào <strong>bất kỳ ô trống nào</strong> của cặp đấu.</p>
                                <p v-else>Mỗi cặp cần <strong>2 đội</strong>. Kéo thả đội từ danh sách bên dưới vào <strong>bất kỳ ô trống nào</strong> của cặp đấu.</p>
                                <p class="mt-1">Nhấn <strong>"Mặc định"</strong> để tự động sắp xếp theo tuần tự.</p>
                                <p class="mt-1" v-if="hasVirtualGroups && crossGroupRankingEnabled && !isRebuildMode">
                                    Giải đấu đang bật <strong>cross_group_ranking</strong>: các suất ghép chéo bảng lấy từ <strong>"Nhì tốt nhất"</strong>. Hệ thống sẽ tự xác định đội cụ thể sau khi vòng bảng kết thúc.
                                </p>
                                <p class="mt-1" v-else-if="hasVirtualGroups && !isRebuildMode">
                                    Ngoài ra còn có <strong>các suất "Nhì tốt nhất"</strong> từ vòng bảng. Hệ thống sẽ tự động xác định đội cụ thể sau khi vòng bảng kết thúc.
                                </p>
                                <p class="mt-1" v-else-if="hasVirtualGroups && isRebuildMode">
                                    Giải đấu đang bật <strong>cross_group_ranking</strong>: các suất "Nhì tốt nhất" đã được xác định sau khi vòng bảng kết thúc.
                                </p>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="flex flex-wrap gap-2 mb-5">
                            <button @click="resetToSequential" class="px-3 py-1.5 text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                                Mặc định (Tuần tự)
                            </button>
                            <button @click="resetToSymmetric" class="px-3 py-1.5 text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                                Mặc định (Đối xứng)
                            </button>
                            <button @click="resetToEmpty" class="px-3 py-1.5 text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                                Xóa tất cả
                            </button>
                            <!-- ✅ Nút "Tải đội từ vòng bảng" — chỉ hiện khi pool đã hoàn thành và đang ở pairing_mode -->
                            <button
                                v-if="poolCompleted && !isRebuildMode"
                                @click="onLoadCandidatesClick"
                                :disabled="isLoadingCandidates"
                                class="ml-auto px-3 py-1.5 text-sm font-medium bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg transition-colors flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                                <ArrowPathIcon v-if="isLoadingCandidates" class="w-4 h-4 animate-spin" />
                                <ArrowPathIcon v-else class="w-4 h-4" />
                                {{ isLoadingCandidates ? 'Đang tải...' : 'Tải đội từ vòng bảng' }}
                            </button>
                            <!-- ✅ Badge hiển thị khi đang ở rebuild mode (đã load candidates thật) -->
                            <span v-if="isRebuildMode"
                                class="ml-auto px-3 py-1.5 text-xs font-semibold bg-orange-100 text-orange-700 rounded-lg border border-orange-200">
                                Đang dùng kết quả vòng bảng
                            </span>
                        </div>

                        <!-- Group Teams Grid (real groups) -->
                        <div class="mb-5">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Đội từ mỗi bảng (kéo vào ô bên dưới)</h4>
                            <div class="grid grid-cols-4 gap-2">
                                <div v-for="group in realGroupList" :key="group.groupId"
                                    class="border border-gray-200 rounded-lg p-2 bg-gray-50">
                                    <div class="text-xs text-gray-500 mb-1 font-medium text-center">Bảng {{ group.groupName }}</div>
                                    <div class="flex flex-col gap-1">
                                        <button @click="addTeamToSlot(group.groupId, 1)"
                                            :draggable="!isTeamUsed(group.groupId, 1)"
                                            @dragstart="onDragStart($event, group.groupId, 1)"
                                            class="text-xs px-2 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded border border-blue-200 transition-colors font-medium cursor-grab active:cursor-grabbing"
                                            :disabled="isTeamUsed(group.groupId, 1)">
                                            {{ group.firstTeam || 'Chưa có nhất' }}
                                        </button>
                                        <button v-if="canPickRealNhi" @click="addTeamToSlot(group.groupId, 2)"
                                            :draggable="!isTeamUsed(group.groupId, 2)"
                                            @dragstart="onDragStart($event, group.groupId, 2)"
                                            class="text-xs px-2 py-1 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded border border-orange-200 transition-colors font-medium cursor-grab active:cursor-grabbing"
                                            :disabled="isTeamUsed(group.groupId, 2)">
                                            {{ group.secondTeam || 'Chưa có nhì' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Virtual "Nhì tốt nhất" buttons (chỉ khi có bảng ảo) -->
                        <div v-if="hasVirtualGroups" class="mb-5 p-3 bg-orange-50/60 border border-orange-200 rounded-lg">
                            <h4 class="text-sm font-semibold text-orange-700 mb-2">
                                Các suất "Nhì tốt nhất" từ vòng bảng
                                <span class="text-xs font-normal text-orange-600 ml-1">(sẽ tự động điền sau khi vòng bảng kết thúc)</span>
                            </h4>
                            <div class="grid grid-cols-4 gap-2">
                                <button v-for="vg in virtualGroupList" :key="vg.groupId"
                                    @click="addTeamToSlot(vg.groupId, 2)"
                                    :draggable="!isTeamUsed(vg.groupId, 2)"
                                    @dragstart="onDragStart($event, vg.groupId, 2)"
                                    class="text-xs px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded border border-orange-200 transition-colors font-medium text-center cursor-grab active:cursor-grabbing"
                                    :disabled="isTeamUsed(vg.groupId, 2)">
                                    Nhì tốt nhất #{{ vg.virtualIndex }}
                                </button>
                            </div>
                        </div>

                        <!-- Pairing Slots -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Các cặp đấu (nhấn ô để xóa)</h4>
                            <div class="space-y-2">
                                <div v-for="(slot, idx) in pairingSlots" :key="idx"
                                    class="flex items-center gap-3 p-3 rounded-lg border bg-gray-50 border-gray-200">
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full text-sm font-bold flex-shrink-0 bg-gray-200 text-gray-600">
                                        {{ idx + 1 }}
                                    </div>

                                    <!-- Ô đội 1 (bất kỳ đội nào có thể rơi vào) -->
                                    <div class="flex-1">
                                        <div v-if="slot[0]"
                                            @click="removeFromSlot(idx, 0)"
                                            class="flex items-center justify-between px-3 py-2 bg-white border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors">
                                            <div>
                                                <span class="text-xs text-gray-500 font-medium">{{ getTeamLabel(slot[0]) }}</span>
                                                <div class="font-medium text-sm text-gray-800">{{ slot[0]?.teamName }}</div>
                                            </div>
                                            <XMarkIcon class="w-4 h-4 text-gray-400 hover:text-gray-600" />
                                        </div>
                                        <div v-else
                                            @dragover.prevent="onDragOver($event)"
                                            @dragenter.prevent="onDragEnter($event)"
                                            @dragleave="onDragLeave($event)"
                                            @drop="onDrop($event, idx, 0)"
                                            :data-drop-slot="idx"
                                            :data-drop-sub="0"
                                            class="flex items-center justify-center h-[52px] border-2 border-dashed border-gray-300 rounded-lg text-xs text-gray-400 transition-colors hover:border-blue-400 hover:bg-blue-50/40 hover:text-blue-500">
                                            Thả đội vào đây
                                        </div>
                                    </div>

                                    <span class="text-gray-400 font-bold">VS</span>

                                    <!-- Ô đội 2 (bất kỳ đội nào có thể rơi vào) -->
                                    <div class="flex-1">
                                        <div v-if="slot[1]"
                                            @click="removeFromSlot(idx, 1)"
                                            class="flex items-center justify-between px-3 py-2 bg-white border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors">
                                            <div>
                                                <span class="text-xs text-gray-500 font-medium">{{ getTeamLabel(slot[1]) }}</span>
                                                <div class="font-medium text-sm text-gray-800">{{ slot[1]?.teamName }}</div>
                                            </div>
                                            <XMarkIcon class="w-4 h-4 text-gray-400 hover:text-gray-600" />
                                        </div>
                                        <div v-else
                                            @dragover.prevent="onDragOver($event)"
                                            @dragenter.prevent="onDragEnter($event)"
                                            @dragleave="onDragLeave($event)"
                                            @drop="onDrop($event, idx, 1)"
                                            :data-drop-slot="idx"
                                            :data-drop-sub="1"
                                            class="flex items-center justify-center h-[52px] border-2 border-dashed border-gray-300 rounded-lg text-xs text-gray-400 transition-colors hover:border-blue-400 hover:bg-blue-50/40 hover:text-blue-500">
                                            Thả đội vào đây
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Validation Warning -->
                        <div v-if="validationError" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2">
                            <ExclamationTriangleIcon class="w-5 h-5 text-red-500 flex-shrink-0" />
                            <p class="text-sm text-red-700">{{ validationError }}</p>
                        </div>

                        <!-- Validation Success -->
                        <div v-if="isValid" class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center gap-2">
                            <CheckCircleIcon class="w-5 h-5 text-green-500 flex-shrink-0" />
                            <p class="text-sm text-green-700">Tất cả cặp đấu đã được ghép hoàn tất!</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3 rounded-b-xl">
                        <button @click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Hủy
                        </button>
                        <button @click="applyPairing"
                            :disabled="!isValid"
                            class="px-4 py-2 text-sm font-medium text-white bg-[#D72D36] rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            Áp dụng
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { XMarkIcon, InformationCircleIcon, ExclamationTriangleIcon, CheckCircleIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';
import * as TournamentTypeService from '@/service/tournamentType.js';

const props = defineProps({
    modelValue: Boolean,
    // ✅ Tournament type id — dùng để modal tự gọi API /knockout-candidates khi vòng bảng đã hoàn thành.
    tournamentTypeId: {
        type: [Number, String, null],
        default: null
    },
    // ✅ Force mode từ bên ngoài (optional). Nếu không truyền, modal sẽ tự detect khi pool stage xong.
    mode: {
        type: String,
        default: null,
        validator: (v) => v === null || ['pairing_mode', 'rebuild'].includes(v)
    },
    numGroups: {
        type: Number,
        default: 8
    },
    numAdvancingTeams: {
        type: Number,
        default: 2
    },
    existingPairings: {
        type: Array,
        default: () => []
    },
    poolGroups: {
        type: Array,
        default: () => []
    },
    crossGroupRankingEnabled: {
        type: Boolean,
        default: false
    },
    // ✅ Pool stage đã hoàn thành 100% chưa (parent detect từ matches).
    // Khi true, modal sẽ tự động fetch /knockout-candidates khi mở.
    poolCompleted: {
        type: Boolean,
        default: false
    },
    // ✅ Danh sách ứng viên từ parent (optional). Nếu parent đã load sẵn thì dùng,
    // nếu không thì modal sẽ tự fetch khi cần.
    candidates: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['update:modelValue', 'apply', 'apply-rebuild']);

const groupList = ref([]);

const pairingSlots = ref([]); // Array of [team1, team2] pairs

// ✅ Mode nội bộ: có thể thay đổi runtime khi user click "Tải đội từ vòng bảng".
// - 'pairing_mode': dùng placeholder (Nhất/Nhì theo bảng).
// - 'rebuild': dùng team thật + team_label từ /knockout-candidates.
const internalMode = ref(props.mode || 'pairing_mode');

// ✅ Mode hiệu lực: prop.mode nếu có, không thì internalMode
const effectiveMode = computed(() => props.mode || internalMode.value);

// ✅ Mode helper: kiểm tra mode hiện tại có phải 'rebuild' không
const isRebuildMode = computed(() => effectiveMode.value === 'rebuild');

// ✅ Internal state cho candidates
const internalCandidates = ref([]);  // candidates fetch nội bộ từ API
const isLoadingCandidates = ref(false);
const loadCandidatesError = ref(null);

// ✅ Effective candidates: ưu tiên prop.candidates, fallback internalCandidates
const effectiveCandidates = computed(() => {
    if (props.candidates && props.candidates.length > 0) return props.candidates;
    return internalCandidates.value || [];
});

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
});

// Computed: có bảng ảo hay không
const hasVirtualGroups = computed(() => {
    return groupList.value.some(g => g.isVirtual);
});

// Computed: có thể pick Nhì thật từ real group hay không
// Rule: CHỈ hiển thị nút "Nhì thật" khi numAdvancingTeams >= 2 (lấy ≥2 đội mỗi bảng).
// Khi numAdvancingTeams = 1, các suất Nhì phải đến từ "Nhì tốt nhất" (cross_group_ranking / virtual),
// KHÔNG pick Nhì thật từ từng bảng — để tránh admin ghép nhầm nguồn đội.
const canPickRealNhi = computed(() => {
    return (props.numAdvancingTeams ?? 1) >= 2;
});

// Computed: chỉ real groups (dùng để render grid "Đội từ mỗi bảng")
const realGroupList = computed(() => {
    return groupList.value.filter(g => !g.isVirtual);
});

// Computed: chỉ virtual groups (dùng để render row "Nhì tốt nhất")
// Mỗi virtual group có virtualIndex (1-based) cho label thân thiện
const virtualGroupList = computed(() => {
    var out = [];
    var counter = 0;
    for (var i = 0; i < groupList.value.length; i++) {
        var g = groupList.value[i];
        if (g.isVirtual) {
            counter = counter + 1;
            out.push({
                virtualIndex: counter,
                groupId: g.groupId,
                groupName: g.groupName,
                isVirtual: true
            });
        }
    }
    return out;
});

// Helper: lấy virtualIndex của group (1-based) — dùng cho slot picker label
const getVirtualIndex = (groupId) => {
    let idx = 0;
    for (const g of groupList.value) {
        if (g.isVirtual) {
            idx++;
            if (g.groupId === groupId) return idx;
        }
    }
    return 0;
};

// Helper: kiểm tra slot có phải là bảng ảo không
// Trong layout: slot[i] tương ứng với group i trong groupList
// Nếu groupList[i] là virtual thì slot[i] là virtual
const isVirtualSlot = (slotIndex) => {
    const group = groupList.value[slotIndex];
    return Boolean(group?.isVirtual);
};

const closeModal = () => {
    isOpen.value = false;
};

// Initialize groups and slots
const initializeData = () => {
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
    const numGroups = props.numGroups;

    groupList.value = [];

    // ✅ MODE='rebuild': dùng candidates từ API /knockout-candidates
    // - Mỗi candidate đã có team_id, team_name, team_label thật
    // - Tạo groupList theo group_id thật (real) hoặc is_virtual (Nhì/Ba tốt nhất)
    if (isRebuildMode.value) {
        const candidatesToUse = effectiveCandidates.value;
        if (!candidatesToUse || candidatesToUse.length === 0) {
            console.warn('[ManualPairingModal] rebuild mode: candidates is empty');
        }
        const groupedByGroupId = {};
        let virtualCounter = 0;
        (candidatesToUse || []).forEach((c) => {
            let key;
            if (c.is_virtual) {
                // ✅ FIX: Trong rebuild mode (pool đã hoàn thành), mỗi candidate virtual đã có
                // team_id thật. Dùng team_id làm key để phân biệt từng slot virtual
                // (2 Nhì tốt nhất có team_id khác nhau, không thể dùng candidate_type*100+position
                // vì cả 2 cùng type "runner_up" + position 2 → cùng key → chỉ hiển thị 1 virtual group).
                virtualCounter++;
                key = c.team_id
                    ? -10000 - Number(c.team_id)
                    : -1 - (virtualCounter * 100);
            } else {
                key = c.group_id;
            }
            if (!groupedByGroupId[key]) {
                groupedByGroupId[key] = {
                    groupId: key,
                    groupName: c.is_virtual ? 'Ảo' : (c.group_name ? c.group_name.replace(/^Bảng\s+/u, '') : '?'),
                    isVirtual: !!c.is_virtual,
                    candidates: []
                };
            }
            groupedByGroupId[key].candidates.push(c);
        });

        // Sort mỗi group theo group_position ascending
        Object.values(groupedByGroupId).forEach((g) => {
            g.candidates.sort((a, b) => (a.group_position ?? 1) - (b.group_position ?? 1));
            // Pick first 2 candidates làm firstTeam/secondTeam
            const c1 = g.candidates[0];
            const c2 = g.candidates[1];
            g.firstTeam = c1 ? (c1.team_label || c1.team_name) : '?';
            g.secondTeam = c2 ? (c2.team_label || c2.team_name) : '';
            g.virtualIndex = g.isVirtual ? (g.candidates[0]?.group_position ?? 1) : 0;
        });

        groupList.value = Object.values(groupedByGroupId);

        // Create slots
        const numSlots = numGroups;
        pairingSlots.value = [];
        for (let i = 0; i < numSlots; i++) {
            pairingSlots.value.push([null, null]);
        }

        // Rebuild mode không cần load existing pairings (round=2 main chưa có team thật,
        // user phải tự ghép lại sau khi pool xong).
        return;
    }

    // ===== MODE='pairing_mode' (luồng cũ) =====
    // BẮT BUỘC phải có poolGroups (database ID)
    if (!props.poolGroups || props.poolGroups.length === 0) {
        console.error('ManualPairingModal: poolGroups is required for manual pairing');
        return;
    }

    // Dùng database ID từ poolGroups
    // Có 2 loại group:
    //  - Real group: có id > 0 (database ID), label = "Bảng A/B/C..."
    //  - Virtual group: có id < 0 và isVirtual=true (suất "Nhì tốt nhất"), label dùng virtualIndex
    let realIndex = 0;
    let virtualIndex = 0;
    const nAdv = props.numAdvancingTeams ?? 1;
    const showRealNhi = nAdv >= 2; // Chỉ hiển thị "Nhì thật" khi lấy ≥2 đội mỗi bảng
    props.poolGroups.forEach((g) => {
        const isVirtual = g.isVirtual === true;
        if (isVirtual) {
            virtualIndex++;
            groupList.value.push({
                groupId: g.id,
                groupName: `${virtualIndex}`,
                firstTeam: '?',
                secondTeam: `Nhì tốt nhất #${virtualIndex}`,
                isVirtual: true,
                virtualIndex
            });
        } else {
            realIndex++;
            const letter = groupNames[realIndex - 1] || `Bảng ${realIndex}`;
            groupList.value.push({
                groupId: g.id,
                groupName: letter,
                firstTeam: `Nhất ${letter}`,
                // Khi numAdvancingTeams = 1, KHÔNG tạo Nhì thật (chỉ lấy Nhất mỗi bảng).
                // Các suất Nhì phải đến từ virtual "Nhì tốt nhất".
                secondTeam: showRealNhi ? `Nhì ${letter}` : '',
                isVirtual: false,
                virtualIndex: 0
            });
        }
    });

    // Create slots (pairingSlots[i] = [team1, team2] for each knockout pair)
    // Số cặp = numGroups (parent tính sẵn = totalAdvancing / 2)
    const numSlots = numGroups;
    pairingSlots.value = [];

    for (let i = 0; i < numSlots; i++) {
        pairingSlots.value.push([null, null]);
    }

    // Load existing pairings if any (only when prop is already available on mount)
    // For updates after modal is open, the existingPairings watcher handles it
    if (props.existingPairings && props.existingPairings.length > 0) {
        loadExistingPairings(props.existingPairings);
    }
};

// Load existing pairings into slots
const loadExistingPairings = (pairings) => {
    // Reset slots
    for (let i = 0; i < pairingSlots.value.length; i++) {
        pairingSlots.value[i] = [null, null];
    }

    // Build a lookup map: "groupId_rank" -> team info
    const teamMap = {};
    groupList.value.forEach(g => {
        const vIdx = g.isVirtual ? getVirtualIndex(g.groupId) : 0;
        teamMap[`${g.groupId}_1`] = {
            groupId: g.groupId,
            groupName: g.groupName,
            teamName: g.firstTeam,
            rank: 1,
            isVirtual: g.isVirtual,
            virtualIndex: vIdx
        };
        teamMap[`${g.groupId}_2`] = {
            groupId: g.groupId,
            groupName: g.groupName,
            teamName: g.secondTeam,
            rank: 2,
            isVirtual: g.isVirtual,
            virtualIndex: vIdx
        };
    });

    // Convention: position = slotIndex * 2 + subIndex (0 = ô trái, 1 = ô phải)
    // group_id = 0 + rank = 2 → virtual "Nhì tốt nhất" (lấy virtual đầu tiên)
    const sortedPairings = [...pairings].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));

    // ✅ Sanity: nếu có entry vượt quá số slot hiện có, bỏ qua + cảnh báo (data cũ từ DB)
    const expectedCount = pairingSlots.value.length * 2;
    const skipped = sortedPairings.length > expectedCount
        ? sortedPairings.length - expectedCount
        : 0;
    if (skipped > 0) {
        console.warn(
            `[ManualPairingModal] loadExistingPairings: có ${pairings.length} entries ` +
            `nhưng chỉ có ${expectedCount} slot. Bỏ qua ${skipped} entries thừa (data cũ từ DB).`
        );
    }

    let firstVirtualAssigned = false;

    sortedPairings.forEach(pairing => {
        const position = pairing.position ?? 0;
        const slotIndex = Math.floor(position / 2);
        const subIndex = position % 2;

        if (slotIndex < 0 || slotIndex >= pairingSlots.value.length) return;
        if (subIndex !== 0 && subIndex !== 1) return;

        const slot = pairingSlots.value[slotIndex];
        const rank = pairing.rank ?? 1;

        // Virtual "Nhì tốt nhất" (group_id = 0, rank >= 2) → slot vị trí tương ứng
        if (parseInt(pairing.group_id, 10) === 0 && rank >= 2) {
            // Tìm virtual group chưa được assign (theo position)
            const vGroups = groupList.value.filter(g => g.isVirtual);
            // Mapping: rank=2 → các virtual đầu tiên; rank=3 → các virtual tiếp theo.
            // Tạm thời: lấy virtual đầu tiên cho rank=2, sau đó tăng "firstVirtualAssigned".
            const idx = rank === 2 ? (firstVirtualAssigned ? 1 : 0) : 0;
            const vGroup = vGroups[idx];
            if (vGroup) {
                slot[subIndex] = {
                    groupId: vGroup.groupId,
                    groupName: vGroup.groupName,
                    teamName: vGroup.secondTeam,
                    rank,
                    isVirtual: true,
                    virtualIndex: getVirtualIndex(vGroup.groupId)
                };
                if (rank === 2) firstVirtualAssigned = true;
            }
            return;
        }

        // Real team → lookup theo group_id + rank
        const team = teamMap[`${pairing.group_id}_${rank}`];
        if (!team) return;
        slot[subIndex] = { ...team, rank };
    });
};
// Watch for prop changes
watch(() => props.modelValue, async (newVal) => {
    if (newVal) {
        // Mỗi lần mở modal: reset internal mode về mode prop (mặc định pairing_mode)
        internalMode.value = props.mode || 'pairing_mode';
        initializeData();
        // Nếu pool đã hoàn thành + có tournamentTypeId + đang ở pairing_mode → auto-load candidates
        await tryAutoLoadCandidates();
    }
}, { immediate: true });

/**
 * ✅ Auto-load candidates khi pool đã hoàn thành (và đang ở pairing_mode).
 * - Nếu parent đã truyền candidates → dùng luôn (không cần fetch).
 * - Nếu chưa có và có tournamentTypeId → gọi API /knockout-candidates.
 * - Nếu pool_completed=true và có candidates → tự chuyển sang rebuild mode.
 */
const tryAutoLoadCandidates = async () => {
    if (!props.poolCompleted) return;
    if (effectiveMode.value !== 'pairing_mode') return;
    if (props.candidates && props.candidates.length > 0) {
        // Parent đã có candidates → chuyển thẳng sang rebuild mode
        internalMode.value = 'rebuild';
        initializeData();
        return;
    }
    if (!props.tournamentTypeId) return;
    await loadCandidatesFromPool();
};

/**
 * ✅ Gọi API /knockout-candidates để fetch danh sách đội thật vào vòng sau.
 * - Nếu pool chưa xong → return null + không làm gì (parent sẽ hiển thị toast riêng).
 * - Nếu có candidates → tự động chuyển sang rebuild mode và re-init.
 */
const loadCandidatesFromPool = async () => {
    if (!props.tournamentTypeId) {
        console.warn('[ManualPairingModal] loadCandidatesFromPool: missing tournamentTypeId');
        return null;
    }
    if (isLoadingCandidates.value) return null;

    isLoadingCandidates.value = true;
    loadCandidatesError.value = null;
    try {
        const payload = await TournamentTypeService.getKnockoutCandidates(props.tournamentTypeId);
        const poolDone = Boolean(payload?.pool_completed);
        const candidates = payload?.candidates || [];

        if (!poolDone) {
            // Pool chưa hoàn thành → vẫn cache candidates (rỗng) để modal biết
            internalCandidates.value = candidates;
            return { pool_completed: false, candidates };
        }

        internalCandidates.value = candidates;
        // Tự động chuyển sang rebuild mode
        internalMode.value = 'rebuild';
        initializeData();
        return { pool_completed: true, candidates };
    } catch (error) {
        console.error('[ManualPairingModal] loadCandidatesFromPool error:', error);
        loadCandidatesError.value = error.response?.data?.message || error.message || 'Lỗi không xác định';
        return null;
    } finally {
        isLoadingCandidates.value = false;
    }
};

/**
 * ✅ Click handler cho button "Tải đội từ vòng bảng".
 * - Nếu pool chưa xong → emit 'pool-not-completed' để parent show toast.
 * - Nếu pool xong và có candidates → fetch + switch mode.
 */
const onLoadCandidatesClick = async () => {
    const result = await loadCandidatesFromPool();
    if (!result) {
        // Error đã được log, loadCandidatesError có thể hiển thị
        return;
    }
    if (!result.pool_completed) {
        emit('pool-not-completed');
    }
};

// ✅ Watch cho rebuild mode: re-init khi candidates thay đổi (sau khi pool stage xong)
watch(() => props.candidates, (newVal) => {
    if (props.modelValue && isRebuildMode.value) {
        initializeData();
    }
}, { deep: true });

// ✅ Watch internalCandidates (sau khi fetch nội bộ) → re-init nếu đang rebuild mode
watch(internalCandidates, (newVal) => {
    if (props.modelValue && isRebuildMode.value) {
        initializeData();
    }
}, { deep: true });

// Watch for existingPairings changes (after modal is open)
watch(() => props.existingPairings, (newVal) => {
    if (props.modelValue && newVal && newVal.length > 0) {
        loadExistingPairings(newVal);
    }
}, { deep: true });

// Build một team object sạch từ groupList entry.
// ✅ Luôn include teamId để FE validation có thể track actual team_id,
// ngăn việc cùng 1 đội xuất hiện nhiều lần trong round 2.
const buildTeamFromGroup = (groupInfo, rank) => {
    if (!groupInfo) return null;
    // Lấy team_id từ candidates array
    const candidate = (groupInfo.candidates || []).find(c => c.rank === rank);
    return {
        groupId: groupInfo.groupId,
        groupName: groupInfo.groupName,
        teamName: rank === 1 ? groupInfo.firstTeam : groupInfo.secondTeam,
        teamId: candidate?.team_id ?? null,  // ✅ Resolved team_id (null cho virtual placeholder)
        rank,
        isVirtual: groupInfo.isVirtual,
        virtualIndex: groupInfo.isVirtual ? getVirtualIndex(groupInfo.groupId) : 0
    };
};

// Add team vào ô trống đầu tiên tìm được (bất kỳ vị trí nào trong bất kỳ cặp nào).
// Không ép rank cứng vào vị trí: Nhất có thể vào ô phải, Nhì có thể vào ô trái.
const addTeamToSlot = (groupId, rank) => {
    const teamInfo = groupList.value.find(g => g.groupId === groupId);
    if (!teamInfo) return;

    // Bảng ảo chỉ có rank=2 (Nhì tốt nhất), không có Nhất
    if (teamInfo.isVirtual && rank === 1) return;

    // Defensive: khi numAdvancingTeams = 1, không pick Nhì thật từ real group
    if (!teamInfo.isVirtual && rank === 2 && (props.numAdvancingTeams ?? 1) < 2) return;

    const team = buildTeamFromGroup(teamInfo, rank);

    // Nếu đã ở trong slot nào đó thì không thêm nữa
    if (isTeamUsed(groupId, rank)) return;

    // Tìm ô trống đầu tiên trong pairingSlots (bất kỳ vị trí 0 hoặc 1)
    for (let i = 0; i < pairingSlots.value.length; i++) {
        if (pairingSlots.value[i][0] === null) {
            pairingSlots.value[i][0] = team;
            return;
        }
        if (pairingSlots.value[i][1] === null) {
            pairingSlots.value[i][1] = team;
            return;
        }
    }
};

// Remove team from slot
const removeFromSlot = (slotIndex, subIndex) => {
    pairingSlots.value[slotIndex][subIndex] = null;
};

// Check xem (groupId, rank) đã được đặt ở bất kỳ vị trí nào trong slots chưa.
// Vì 2 ô trong 1 cặp là bất kỳ (không ép Nhất trái/Nhì phải), chỉ cần check cả 2.
//
// ✅ Cải tiến: Cũng check theo actual team_id để ngăn việc cùng 1 đội xuất hiện
// nhiều lần (ví dụ: Đội số 7 vừa là Nhất C vừa là "Nhì tốt nhất" khi resolve).
const isTeamUsed = (groupId, rank) => {
    const teamInfo = groupList.value.find(g => g.groupId === groupId);
    // Bảng ảo không có Nhất → không pick được
    if (rank === 1 && teamInfo?.isVirtual) return true;

    // Defensive: khi numAdvancingTeams = 1, không pick Nhì thật từ real group
    // (canPickRealNhi đã ẩn nút, nhưng chặn cả drag/drop từ chỗ khác)
    if (!teamInfo?.isVirtual && rank === 2 && (props.numAdvancingTeams ?? 1) < 2) return true;

    // Collect team_ids đang nằm trong slots (bất kể groupId/rank gốc là gì)
    const usedTeamIds = new Set();
    for (const slot of pairingSlots.value) {
        if (slot[0]?.teamId) usedTeamIds.add(slot[0].teamId);
        if (slot[1]?.teamId) usedTeamIds.add(slot[1].teamId);
    }

    // Check (groupId, rank) combination
    for (const slot of pairingSlots.value) {
        if (slot[0] && slot[0].groupId === groupId && slot[0].rank === rank) return true;
        if (slot[1] && slot[1].groupId === groupId && slot[1].rank === rank) return true;
    }

    // ✅ Ngăn cùng 1 đội xuất hiện nhiều lần:
    // Với virtual group, check xem candidate team_id đã được dùng chưa.
    // Với real group + rank=2, check xem đội Nhì thật đã được dùng chưa (tránh dùng Nhì thật khi đang dùng "Nhì tốt nhất" ảo cùng team).
    if (teamInfo?.isVirtual) {
        // Lấy tất cả team_id từ candidates của group này
        const groupCandidates = teamInfo.candidates || [];
        for (const candidate of groupCandidates) {
            if (candidate.teamId && usedTeamIds.has(candidate.teamId)) {
                return true; // Team này đã nằm trong slot khác → disable
            }
        }
    } else if (rank === 2) {
        // Real Nhì: check xem team_id tương ứng đã được dùng chưa
        const realNhi = (teamInfo?.candidates || []).find(c => c.rank === 2);
        if (realNhi?.teamId && usedTeamIds.has(realNhi.teamId)) {
            return true;
        }
    }

    return false;
};

// Label động cho mỗi đội trong slot (vì không cố định Nhất/Nhì vị trí nữa)
const getTeamLabel = (team) => {
    if (!team) return '';
    if (team.isVirtual) {
        return `Nhì tốt nhất #${team.virtualIndex}`;
    }
    const rankLabel = team.rank === 1 ? 'Nhất' : 'Nhì';
    return `Bảng ${team.groupName} - ${rankLabel}`;
};

// Validation
const validationError = computed(() => {
    for (let i = 0; i < pairingSlots.value.length; i++) {
        const slot = pairingSlots.value[i];
        // Mỗi cặp cần đủ 2 đội (bất kỳ Nhất/Nhì/ảo)
        if (!slot[0] || !slot[1]) {
            return `Cặp đấu ${i + 1} chưa đủ 2 đội.`;
        }
    }
    return null;
});

const isValid = computed(() => {
    return !validationError.value && pairingSlots.value.length > 0;
});

// Helper: build entry từ groupList để gán vào slot
const buildEntry = (group, rank) => {
    if (!group) return null;
    return buildTeamFromGroup(group, rank);
};

// Reset to sequential: lần lượt lấy từng cặp gồm 2 đội liên tiếp trong groupList
// (không ép Nhất vào ô 0, Nhì vào ô 1 — chỉ cần 2 đội đầy đủ)
const resetToSequential = () => {
    for (let i = 0; i < pairingSlots.value.length; i++) {
        const g0 = groupList.value[(2 * i) % groupList.value.length];
        const g1 = groupList.value[(2 * i + 1) % groupList.value.length];

        // rank cho mỗi entry: nếu group ở vị trí đầu tiên của cặp thì ưu tiên rank=1 (Nhất)
        // nếu không (ví dụ 2 virtual liên tiếp) thì mặc định rank=2 cho cả 2
        pairingSlots.value[i][0] = buildEntry(g0, g0?.isVirtual ? 2 : 1);
        pairingSlots.value[i][1] = buildEntry(g1, g1?.isVirtual ? 2 : 2);
    }
};

// Reset to symmetric: cặp (i, len-1-i)
const resetToSymmetric = () => {
    const len = groupList.value.length;
    for (let i = 0; i < pairingSlots.value.length; i++) {
        const g0 = groupList.value[i % len];
        const g1 = groupList.value[(len - 1 - i) % len];
        pairingSlots.value[i][0] = buildEntry(g0, g0?.isVirtual ? 2 : 1);
        pairingSlots.value[i][1] = buildEntry(g1, g1?.isVirtual ? 2 : 2);
    }
};

// Reset to empty
const resetToEmpty = () => {
    for (let i = 0; i < pairingSlots.value.length; i++) {
        pairingSlots.value[i] = [null, null];
    }
};

// === Drag & Drop handlers (HTML5 native D&D) ===
const onDragStart = (event, groupId, rank) => {
    if (isTeamUsed(groupId, rank)) {
        event.preventDefault();
        return;
    }
    // Truyền groupId + rank qua dataTransfer
    event.dataTransfer.setData('application/x-team', JSON.stringify({ groupId, rank }));
    event.dataTransfer.effectAllowed = 'move';
};

const onDragOver = (event) => {
    event.dataTransfer.dropEffect = 'move';
};

const onDragEnter = (event) => {
    event.currentTarget.classList.add('border-blue-500', 'bg-blue-50');
};

const onDragLeave = (event) => {
    event.currentTarget.classList.remove('border-blue-500', 'bg-blue-50');
};

const onDrop = (event, slotIndex, subIndex) => {
    event.preventDefault();
    event.currentTarget.classList.remove('border-blue-500', 'bg-blue-50');
    const raw = event.dataTransfer.getData('application/x-team');
    if (!raw) return;
    try {
        const { groupId, rank } = JSON.parse(raw);
        // Nếu team đã đặt ở đâu đó thì gỡ trước
        for (let i = 0; i < pairingSlots.value.length; i++) {
            for (let k = 0; k < 2; k++) {
                const t = pairingSlots.value[i][k];
                if (t && t.groupId === groupId && t.rank === rank) {
                    pairingSlots.value[i][k] = null;
                }
            }
        }
        // Nếu ô đích đang có đội khác thì giữ nguyên (không ghi đè) → đẩy sang ô trống khác
        const targetEmpty = pairingSlots.value[slotIndex][subIndex] === null;
        if (targetEmpty) {
            const teamInfo = groupList.value.find(g => g.groupId === groupId);
            pairingSlots.value[slotIndex][subIndex] = buildEntry(teamInfo, rank);
            return;
        }
        // Tìm ô trống khác để đặt vào
        for (let i = 0; i < pairingSlots.value.length; i++) {
            for (let k = 0; k < 2; k++) {
                if (pairingSlots.value[i][k] === null) {
                    const teamInfo = groupList.value.find(g => g.groupId === groupId);
                    pairingSlots.value[i][k] = buildEntry(teamInfo, rank);
                    return;
                }
            }
        }
    } catch (err) {
        // ignore parse errors
    }
};

/**
 * Build manual_pairings payload từ pairingSlots theo 2 convention:
 * - 'rebuild': dùng khi đã có candidates từ /knockout-candidates (team thật + team_label).
 *   Mỗi entry: {group_id, rank, position} với group_id=0 cho virtual.
 * - 'pairing_mode': dùng khi tạo/đổi tournament type (chưa có kết quả vòng bảng).
 *   Mỗi entry: {group_id, rank, position} — group_id từ poolGroups (database ID).
 */
const buildManualPairings = () => {
    const useRebuildFormat = isRebuildMode.value || props.poolCompleted;
    const manualPairings = [];

    pairingSlots.value.forEach((slot, slotIndex) => {
        const basePos = slotIndex * 2;
        const entries = [];
        if (useRebuildFormat) {
            // Build 2 entries cho mỗi slot, bất kể ô có trống không (filter null)
            [0, 1].forEach((subIdx) => {
                const t = slot[subIdx];
                if (!t) return;
                entries.push({
                    group_id: t.isVirtual ? 0 : t.groupId,
                    rank: t.rank,
                    position: basePos + subIdx
                });
            });
        } else {
            // Luồng cũ: chỉ push nếu ô có đội
            if (slot[0]) {
                const t = slot[0];
                entries.push({
                    group_id: t.isVirtual ? 0 : t.groupId,
                    rank: t.rank,
                    position: basePos
                });
            }
            if (slot[1]) {
                const t = slot[1];
                entries.push({
                    group_id: t.isVirtual ? 0 : t.groupId,
                    rank: t.rank,
                    position: basePos + 1
                });
            }
        }
        entries.forEach((e) => manualPairings.push(e));
    });

    return manualPairings;
};

/**
 * ✅ Logic quyết định emit event nào dựa trên context hiện tại:
 * - Nếu pool đã hoàn thành (poolCompleted=true) HOẶC đang ở rebuild mode:
 *   → Emit 'apply-rebuild' (gọi API /knockout-rebuild-pairing).
 * - Ngược lại:
 *   → Emit 'apply' (gọi PUT /api/tournament-types/{id} cũ).
 *
 * Lý do: Nếu giải đấu đã có trận hoàn thành → PUT cũ sẽ fail với
 * "Không thể thay đổi pairing mode. Đã có trận đấu hoàn thành...".
 * Khi đó phải dùng /knockout-rebuild-pairing mới (chỉ reassign team).
 */
const applyPairing = () => {
    if (!isValid.value) return;

    const manualPairings = buildManualPairings();
    const useRebuildEndpoint = isRebuildMode.value || props.poolCompleted;

    console.log('[ManualPairingModal] applyPairing()', {
        numSlots: pairingSlots.value.length,
        numEntries: manualPairings.length,
        poolCompleted: props.poolCompleted,
        isRebuildMode: isRebuildMode.value,
        endpoint: useRebuildEndpoint ? '/knockout-rebuild-pairing' : 'PUT /tournament-types/{id}'
    });

    if (useRebuildEndpoint) {
        emit('apply-rebuild', manualPairings);
    } else {
        emit('apply', manualPairings);
    }
    closeModal();
};
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.25s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

.modal-fade-enter-active .bg-white,
.modal-fade-leave-active .bg-white {
    transition: transform 0.25s ease;
}

.modal-fade-enter-from .bg-white,
.modal-fade-leave-to .bg-white {
    transform: scale(0.95) translateY(10px);
}
</style>
