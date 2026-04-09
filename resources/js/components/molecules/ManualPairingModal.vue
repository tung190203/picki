<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div v-if="modelValue" class="fixed inset-0 z-[10010] flex items-start justify-center p-4 bg-gray-900 bg-opacity-50 backdrop-blur-sm overflow-y-auto" @click.self="closeModal">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl transform transition-all duration-300 my-8">
                    <!-- Header -->
                    <div class="p-5 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white rounded-t-xl z-10">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Ghép cặp thủ công</h3>
                            <p class="text-sm text-gray-500 mt-1">Kéo thả hoặc chọn đội để tạo cặp đấu</p>
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
                                <p>Mỗi trận gồm <strong>2 đội</strong>: một đội nhất bảng (1) và một đội nhì bảng (2).</p>
                                <p class="mt-1">Nhấn <strong>"Mặc định"</strong> để tự động sắp xếp theo tuần tự.</p>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="flex gap-2 mb-5">
                            <button @click="resetToSequential" class="px-3 py-1.5 text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                                Mặc định (Tuần tự)
                            </button>
                            <button @click="resetToSymmetric" class="px-3 py-1.5 text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                                Mặc định (Đối xứng)
                            </button>
                            <button @click="resetToEmpty" class="px-3 py-1.5 text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                                Xóa tất cả
                            </button>
                        </div>

                        <!-- Group Teams Grid -->
                        <div class="mb-5">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Đội từ mỗi bảng (kéo vào ô bên dưới)</h4>
                            <div class="grid grid-cols-4 gap-2">
                                <div v-for="group in groupList" :key="group.groupId"
                                    class="border border-gray-200 rounded-lg p-2 bg-gray-50 text-center">
                                    <div class="text-xs text-gray-500 mb-1 font-medium">Bảng {{ group.groupName }}</div>
                                    <div class="flex flex-col gap-1">
                                        <button @click="addTeamToSlot(group.groupId, 1)"
                                            class="text-xs px-2 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded border border-blue-200 transition-colors font-medium"
                                            :disabled="isTeamUsed(group.groupId, 1)">
                                            1. {{ group.firstTeam || 'Chưa có' }}
                                        </button>
                                        <button @click="addTeamToSlot(group.groupId, 2)"
                                            class="text-xs px-2 py-1 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded border border-orange-200 transition-colors font-medium"
                                            :disabled="isTeamUsed(group.groupId, 2)">
                                            2. {{ group.secondTeam || 'Chưa có' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pairing Slots -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Các cặp đấu (nhấn ô để xóa)</h4>
                            <div class="space-y-2">
                                <div v-for="(slot, idx) in pairingSlots" :key="idx"
                                    class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="w-8 h-8 flex items-center justify-center bg-gray-200 rounded-full text-sm font-bold text-gray-600 flex-shrink-0">
                                        {{ idx + 1 }}
                                    </div>

                                    <!-- Slot 1 (even position) -->
                                    <div class="flex-1">
                                        <div v-if="slot[0]"
                                            @click="removeFromSlot(idx, 0)"
                                            class="flex items-center justify-between px-3 py-2 bg-blue-50 border border-blue-300 rounded-lg cursor-pointer hover:bg-blue-100 transition-colors">
                                            <div>
                                                <span class="text-xs text-blue-500 font-medium">Bảng {{ slot[0].groupName }} - Nhất</span>
                                                <div class="font-medium text-sm text-blue-800">{{ slot[0].teamName }}</div>
                                            </div>
                                            <XMarkIcon class="w-4 h-4 text-blue-400 hover:text-blue-600" />
                                        </div>
                                        <div v-else
                                            class="flex items-center justify-center h-[44px] border-2 border-dashed border-blue-200 rounded-lg text-xs text-blue-300">
                                            Chọn đội nhất bảng...
                                        </div>
                                    </div>

                                    <span class="text-gray-400 font-bold">VS</span>

                                    <!-- Slot 2 (odd position) -->
                                    <div class="flex-1">
                                        <div v-if="slot[1]"
                                            @click="removeFromSlot(idx, 1)"
                                            class="flex items-center justify-between px-3 py-2 bg-orange-50 border border-orange-300 rounded-lg cursor-pointer hover:bg-orange-100 transition-colors">
                                            <div>
                                                <span class="text-xs text-orange-500 font-medium">Bảng {{ slot[1].groupName }} - Nhì</span>
                                                <div class="font-medium text-sm text-orange-800">{{ slot[1].teamName }}</div>
                                            </div>
                                            <XMarkIcon class="w-4 h-4 text-orange-400 hover:text-orange-600" />
                                        </div>
                                        <div v-else
                                            class="flex items-center justify-center h-[44px] border-2 border-dashed border-orange-200 rounded-lg text-xs text-orange-300">
                                            Chọn đội nhì bảng...
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
import { XMarkIcon, InformationCircleIcon, ExclamationTriangleIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: Boolean,
    numGroups: {
        type: Number,
        default: 8
    },
    existingPairings: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['update:modelValue', 'apply']);

const groupList = ref([]);

const pairingSlots = ref([]); // Array of [team1, team2] pairs

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
});

const closeModal = () => {
    isOpen.value = false;
};

// Initialize groups and slots
const initializeData = () => {
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
    const numGroups = props.numGroups;

    groupList.value = [];
    for (let i = 0; i < numGroups; i++) {
        groupList.value.push({
            groupId: i + 1,
            groupName: groupNames[i] || (i + 1),
            firstTeam: `Nhất ${groupNames[i]}`,
            secondTeam: `Nhì ${groupNames[i]}`
        });
    }

    // Calculate number of slots needed (each slot = 2 teams: 1 from first place, 1 from second place)
    const numSlots = numGroups; // N teams from first place + N teams from second place = N pairs
    pairingSlots.value = [];

    for (let i = 0; i < numSlots; i++) {
        pairingSlots.value.push([null, null]);
    }

    // Load existing pairings if any
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
        teamMap[`${g.groupId}_1`] = { groupId: g.groupId, groupName: g.groupName, teamName: g.firstTeam, rank: 1, position: -1 };
        teamMap[`${g.groupId}_2`] = { groupId: g.groupId, groupName: g.groupName, teamName: g.secondTeam, rank: 2, position: -1 };
    });

    // Fill slots based on pairings
    pairings.forEach(pairing => {
        const position = pairing.position ?? pairing.rank;
        const team = teamMap[`${pairing.group_id}_${pairing.rank}`];
        if (team && position >= 0 && position < pairingSlots.value.length) {
            const slotIndex = Math.floor(position / 2);
            const isFirstSlot = position % 2 === 0;
            if (isFirstSlot) {
                pairingSlots.value[slotIndex][0] = { ...team, rank: 1 };
            } else {
                pairingSlots.value[slotIndex][1] = { ...team, rank: 2 };
            }
        }
    });
};

// Watch for modal open to initialize
watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        initializeData();
    }
}, { immediate: true });

// Add team to next empty slot
const addTeamToSlot = (groupId, rank) => {
    // rank: 1 = first place (Nhất), 2 = second place (Nhì)
    const teamInfo = groupList.value.find(g => g.groupId === groupId);
    if (!teamInfo) return;

    const team = {
        groupId,
        groupName: teamInfo.groupName,
        teamName: rank === 1 ? teamInfo.firstTeam : teamInfo.secondTeam,
        rank
    };

    // Find next empty slot for this rank type
    // Even positions (0, 2, 4...) = first place teams
    // Odd positions (1, 3, 5...) = second place teams
    for (let i = 0; i < pairingSlots.value.length; i++) {
        const slot = pairingSlots.value[i];
        if (rank === 1 && slot[0] === null) {
            slot[0] = team;
            return;
        }
        if (rank === 2 && slot[1] === null) {
            slot[1] = team;
            return;
        }
    }
};

// Remove team from slot
const removeFromSlot = (slotIndex, subIndex) => {
    pairingSlots.value[slotIndex][subIndex] = null;
};

// Check if team is already used
const isTeamUsed = (groupId, rank) => {
    for (const slot of pairingSlots.value) {
        if (slot[0] && slot[0].groupId === groupId && slot[0].rank === rank) return true;
        if (slot[1] && slot[1].groupId === groupId && slot[1].rank === rank) return true;
    }
    return false;
};

// Validation
const validationError = computed(() => {
    for (let i = 0; i < pairingSlots.value.length; i++) {
        const slot = pairingSlots.value[i];
        if (!slot[0] || !slot[1]) {
            return `Cặp đấu ${i + 1} chưa hoàn tất.`;
        }
    }
    return null;
});

const isValid = computed(() => {
    return !validationError.value && pairingSlots.value.length > 0;
});

// Reset to sequential
const resetToSequential = () => {
    // Sequential: Nhất[i] vs Nhì[i+1], Nhất[i+1] vs Nhì[i]
    for (let i = 0; i < pairingSlots.value.length; i++) {
        const firstPlaceIndex = i;
        const secondPlaceIndex = (i + 1) % groupList.value.length;

        pairingSlots.value[i][0] = {
            groupId: groupList.value[firstPlaceIndex].groupId,
            groupName: groupList.value[firstPlaceIndex].groupName,
            teamName: groupList.value[firstPlaceIndex].firstTeam,
            rank: 1
        };
        pairingSlots.value[i][1] = {
            groupId: groupList.value[secondPlaceIndex].groupId,
            groupName: groupList.value[secondPlaceIndex].groupName,
            teamName: groupList.value[secondPlaceIndex].secondTeam,
            rank: 2
        };
    }
};

// Reset to symmetric
const resetToSymmetric = () => {
    // Symmetric: Nhất[i] vs Nhì[cuối-i]
    const len = groupList.value.length;
    for (let i = 0; i < pairingSlots.value.length; i++) {
        const firstPlaceIndex = i;
        const secondPlaceIndex = len - 1 - i;

        pairingSlots.value[i][0] = {
            groupId: groupList.value[firstPlaceIndex].groupId,
            groupName: groupList.value[firstPlaceIndex].groupName,
            teamName: groupList.value[firstPlaceIndex].firstTeam,
            rank: 1
        };
        pairingSlots.value[i][1] = {
            groupId: groupList.value[secondPlaceIndex].groupId,
            groupName: groupList.value[secondPlaceIndex].groupName,
            teamName: groupList.value[secondPlaceIndex].secondTeam,
            rank: 2
        };
    }
};

// Reset to empty
const resetToEmpty = () => {
    for (let i = 0; i < pairingSlots.value.length; i++) {
        pairingSlots.value[i] = [null, null];
    }
};

// Apply pairing and emit
const applyPairing = () => {
    if (!isValid.value) return;

    const manualPairings = [];
    pairingSlots.value.forEach((slot, slotIndex) => {
        // Position: slotIndex*2 (first team), slotIndex*2+1 (second team)
        manualPairings.push({
            group_id: slot[0].groupId,
            rank: slot[0].rank,
            position: slotIndex * 2
        });
        manualPairings.push({
            group_id: slot[1].groupId,
            rank: slot[1].rank,
            position: slotIndex * 2 + 1
        });
    });

    emit('apply', manualPairings);
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
