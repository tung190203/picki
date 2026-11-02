<template>
    <div class="m-4 p-4 max-w-8xl h-[calc(100vh-7rem)] rounded-md bg-white shadow-sm flex flex-col">
        <div class="flex justify-between mb-4">
            <div class="flex items-center gap-4 mb-4">
                <ArrowLeftIcon class="w-6 h-6 text-gray-600 hover:text-[#D72D36] cursor-pointer" @click="handleGoBack" />
                <h1 class="text-lg font-semibold">Sắp xếp bảng đấu</h1>
            </div>
            <div class="flex gap-2" v-if="isCreator">
                <button
                    class="w-full bg-[#FBBF24] hover:bg-white border text-white px-4 py-2 rounded-md hover:text-[#FBBF24] hover:border-[#FBBF24] font-medium transition whitespace-nowrap"
                    @click="confirmAssignments(true)" :disabled="loading">
                    {{ loading ? 'Đang lưu...' : 'Lưu nháp' }}
                </button>
                <button
                    class="w-full bg-secondary hover:bg-white border text-white px-4 py-2 rounded-md hover:text-[#D72D36] hover:border-[#D72D36] font-medium transition whitespace-nowrap"
                    @click="confirmAssignments(false)" :disabled="loading">
                    {{ loading ? 'Đang lưu...' : 'Lưu & tạo nhánh đấu' }}
                </button>
            </div>
        </div>

        <div class="grid gap-4 flex-1 overflow-hidden" :class="isCreator ? 'grid-cols-10' : 'grid-cols-1'">
            <!-- Left Sidebar - Unassigned Teams -->
            <div class="col-span-3 bg-gray-100 rounded-lg p-4 flex flex-col overflow-hidden" v-if="isCreator">
                <div class="flex flex-col h-full">
                    <!-- Header -->
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-semibold text-gray-700">ĐỘI CHƯA XẾP</h2>
                            <span class="text-gray-500 text-sm">• {{ unassignedTeams.length }}</span>
                        </div>
                        <button @click="autoAssignTeams" v-if="isCreator"
                            class="text-red-500 text-xs font-medium hover:text-red-600 transition-colors">
                            Tự động xếp bảng
                        </button>
                    </div>

                    <!-- Unassigned Teams List -->
                    <div class="flex-1 space-y-2 overflow-y-auto custom-scrollbar-hide"
                        @drop="isCreator && onDrop($event, 'unassigned')" 
                        @dragover.prevent 
                        @dragenter.prevent>
                        <div v-for="team in unassignedTeams" :key="team.team_id" 
                            :draggable="isCreator"
                            @dragstart="isCreator && onDragStart($event, team, 'unassigned')" 
                            @dragend="isCreator && onDragEnd"
                            class="flex items-center gap-3 p-3 bg-white rounded-lg hover:bg-gray-50 transition-colors"
                            :class="{ 
                                'opacity-50': draggingTeam?.team_id === team.team_id,
                                'cursor-move': isCreator,
                                'cursor-default': !isCreator
                            }">
                            <DotMenu v-if="isCreator" />

                            <img :src="team.team_avatar || `https://api.dicebear.com/7.x/avataaars/svg?seed=${team.team_id}`"
                                :alt="team.team_name" class="w-10 h-10 rounded-full flex-shrink-0">

                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-900 text-sm truncate">{{ team.team_name }}</div>
                                <div class="text-xs text-gray-500 truncate">
                                    {{team.members.map(m => m.full_name).join(' & ')}}
                                </div>
                            </div>

                            <span
                                class="px-2.5 py-1 bg-blue-500 text-white text-xs font-medium rounded-full whitespace-nowrap flex-shrink-0">
                                {{ team.vndupr_avg?.toFixed(2) ?? '—' }} PICKI
                            </span>
                        </div>

                        <div v-if="unassignedTeams.length === 0" class="w-full p-8 text-gray-400 text-center text-sm">
                            Tất cả đội đã được phân chia vào các bảng
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Tournament Brackets Grid -->
            <div class="overflow-y-auto custom-scrollbar-hide" :class="isCreator ? 'col-span-7' : 'col-span-1'">
                <div class="grid gap-4 h-fit" :class="groupGridClass">
                    <!-- Dynamic Groups -->
                    <div v-for="group in groups" :key="group.group_id"
                        class="bg-white rounded-lg border border-gray-200 p-4 flex flex-col h-fit">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-base font-semibold text-gray-900">{{ group.group_name }}</h2>
                            <span class="px-2.5 py-1 text-white text-xs font-semibold rounded-full"
                                :class="getGroupBadgeClass(group)">
                                {{ group.teams.length }}/{{ teamsPerGroup }} Đội
                            </span>
                        </div>

                        <div class="flex-1 space-y-2 overflow-y-auto custom-scrollbar-hide transition-all duration-200"
                            @drop="isCreator && onDrop($event, group.group_id)" 
                            @dragover.prevent
                            @dragenter.prevent="isCreator && handleDragEnter(group.group_id)"
                            @dragleave.prevent="isCreator && handleDragLeave($event, group.group_id)"
                            :class="{ 'bg-blue-50 border-2 border-dashed border-blue-300 rounded-lg p-2': isCreator && isDragging && dragOverBang === group.group_id }">
                            <div v-for="team in group.teams" :key="team.team_id" 
                                :draggable="isCreator"
                                @dragstart="isCreator && onDragStart($event, team, group.group_id)" 
                                @dragend="isCreator && onDragEnd"
                                @drop="isCreator && onDrop($event, group.group_id, team)" 
                                @dragover.prevent="isCreator && (dropTargetTeam = team)"
                                @dragleave="isCreator && (dropTargetTeam = null)" 
                                @dragenter.prevent
                                class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg group hover:bg-gray-100 transition-colors" v-tooltip="team.team_name"
                                :class="{
                                    'opacity-50': draggingTeam?.team_id === team.team_id,
                                    'border-2 border-blue-400 bg-blue-50': isCreator && isDragging && dropTargetTeam?.team_id === team.team_id,
                                    'cursor-move': isCreator,
                                    'cursor-default': !isCreator
                                }">
                                <DotMenu v-if="isCreator" />

                                <img :src="team.team_avatar || `https://api.dicebear.com/7.x/avataaars/svg?seed=${team.team_id}`"
                                    :alt="team.team_name" class="w-10 h-10 rounded-full flex-shrink-0">

                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-900 text-sm truncate">{{ team.team_name }}</div>
                                    <div class="text-xs text-gray-500 truncate">
                                        {{team.members.map(m => m.full_name).join(' & ')}}
                                    </div>
                                </div>

                                <span
                                    class="px-2.5 py-1 bg-blue-500 text-white text-xs font-medium rounded-full whitespace-nowrap flex-shrink-0">
                                    {{ team.vndupr_avg?.toFixed(2) ?? '—' }} PICKI
                                </span>

                                <button v-if="isCreator" @click="removeTeam(group.group_id, team.team_id)"
                                    class="opacity-0 group-hover:opacity-100 transition-opacity text-red-500 hover:text-red-600 flex-shrink-0">
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>

                            <div v-if="group.teams.length === 0"
                                class="w-full py-12 border-2 border-dashed border-gray-300 rounded-lg text-gray-400 text-center text-xs">
                                {{ isCreator ? 'Kéo thả đội vào đây' : 'Chưa có đội nào' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Confirmation Modal -->
        <Transition name="modal">
            <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black bg-opacity-50 transition-opacity" @click="handleModalCancel"></div>
                
                <!-- Modal -->
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
                    <!-- Header -->
                    <div class="px-6 pt-5 pb-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ confirmModal.title }}</h3>
                    </div>
                    
                    <!-- Body -->
                    <div class="px-6 pb-4">
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ confirmModal.message }}</p>
                    </div>
                    
                    <!-- Footer -->
                    <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex gap-3 justify-end">
                        <button
                            @click="handleModalCancel"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                            {{ confirmModal.cancelText }}
                        </button>
                        <button
                            @click="handleModalConfirm"
                            :class="confirmModal.confirmClass"
                            class="px-4 py-2 text-sm font-medium text-white rounded-md hover:opacity-90 transition-opacity">
                            {{ confirmModal.confirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, reactive, computed } from "vue";
import { onBeforeRouteLeave } from 'vue-router';
import { ArrowLeftIcon, XMarkIcon } from "@heroicons/vue/24/solid";
import { toast } from 'vue3-toastify';
import { useRouter, useRoute } from 'vue-router';
import DotMenu from '@/assets/images/dot-menu.svg';
import * as TournamentTypeService from '@/service/tournamentType.js'
import * as TournamentService from '@/service/tournament.js'
import { useUserStore } from '@/store/auth'
import { storeToRefs } from 'pinia'

const router = useRouter();
const route = useRoute();
const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)

/* ================= MODAL STATE ================= */
const showConfirmModal = ref(false);
const confirmModal = reactive({
    title: '',
    message: '',
    confirmText: 'OK',
    cancelText: 'Hủy',
    confirmClass: 'bg-[#D72D36]',
    onConfirm: null,
    onCancel: null
});

const groupGridClass = computed(() => {
    if (isCreator.value) {
        return 'grid-cols-1 md:grid-cols-2';
    }

    return `
        grid-cols-1
        sm:grid-cols-2
        lg:grid-cols-3
        2xl:grid-cols-4
    `;
});

const showModal = (config) => {
    return new Promise((resolve) => {
        confirmModal.title = config.title || 'Xác nhận';
        confirmModal.message = config.message || '';
        confirmModal.confirmText = config.confirmText || 'OK';
        confirmModal.cancelText = config.cancelText || 'Hủy';
        confirmModal.confirmClass = config.confirmClass || 'bg-[#D72D36]';
        
        confirmModal.onConfirm = () => resolve(true);
        confirmModal.onCancel = () => resolve(false);
        
        showConfirmModal.value = true;
    });
};

const handleModalConfirm = () => {
    showConfirmModal.value = false;
    if (confirmModal.onConfirm) {
        confirmModal.onConfirm();
    }
};

const handleModalCancel = () => {
    showConfirmModal.value = false;
    if (confirmModal.onCancel) {
        confirmModal.onCancel();
    }
};

/* ================= NAVIGATION ================= */
const handleGoBack = async () => {
    if (!isCreator.value || !hasUnsavedChanges.value) {
        navigateBack();
        return;
    }

    const confirmed = await showModal({
        title: 'Thay đổi chưa được lưu',
        message: 'Bạn có thay đổi chưa được lưu. Bạn có muốn lưu nháp trước khi rời đi?',
        confirmText: 'Lưu nháp',
        cancelText: 'Không lưu',
        confirmClass: 'bg-[#FBBF24]'
    });

    if (confirmed) {
        await confirmAssignments(true);
        // Đợi 1.5s để toast hiển thị xong
        await new Promise(resolve => setTimeout(resolve, 1500));
    }
    
    navigateBack();
};

const navigateBack = () => {
    router.push({
        name: 'tournament-detail',
        params: { id: tournamentId },
        query: { tab: route.query.tab }
    });
};

/* ================= STATE ================= */
const unassignedTeams = ref([]);
const groups = ref([]);
const teamsPerGroup = ref(3);

const draggingTeam = ref(null);
const sourceBang = ref(null);
const isDragging = ref(false);
const dragOverBang = ref(null);
const dropTargetTeam = ref(null);
const dragCounter = ref({});

const loading = ref(false);
const tournamentId = route.params.id;
const tournament = ref([]);
const tournamentTypeId = ref(null);
const hasUnsavedChanges = ref(false);

/* ================= HELPERS ================= */
const findGroupById = (id) =>
    groups.value.find(g => g.group_id === id);

const resetDrag = () => {
    draggingTeam.value = null;
    sourceBang.value = null;
    dragOverBang.value = null;
    dropTargetTeam.value = null;
    dragCounter.value = {};
    isDragging.value = false;
};

const removeTeamFromSource = (team, source) => {
    if (source === 'unassigned') {
        unassignedTeams.value =
            unassignedTeams.value.filter(t => t.team_id !== team.team_id);
    } else {
        const g = findGroupById(source);
        if (!g) return;
        g.teams = g.teams.filter(t => t.team_id !== team.team_id);
    }
};

const insertTeamToGroup = (groupId, team, index = null) => {
    const g = findGroupById(groupId);
    if (!g) return;
    index === null
        ? g.teams.push(team)
        : g.teams.splice(index, 0, team);
};

/* ================= UI ================= */
const getGroupBadgeClass = (group) => {
    if (group.teams.length === teamsPerGroup.value) return 'bg-green-500';
    if (group.teams.length === 0) return 'bg-red-500';
    return 'bg-gray-400';
};

/* ================= API ================= */
const getTournamentTypeId = async (id) => {
    const res = await TournamentService.getTournamentById(id);
    tournamentTypeId.value = res?.tournament_types?.[0]?.id;
    tournament.value = res
};

const isCreator = computed(() => {
    return tournament.value?.tournament_staff?.some(
        staff => staff.role === 1 && staff.staff?.id === getUser.value.id
    )
})

/**
 * Số người mỗi đội - được cấu hình từ tournament.
 * Phase 2: Không có validation giới tính, bất kỳ user hợp lệ nào đều có thể tham gia.
 */
const playerPerTeam = computed(() => {
    return tournament.value?.player_per_team ?? 2;
});

const fetchTeams = async () => {
    try {
        const res = await TournamentTypeService.groupWithTeamsForSort(
            tournamentTypeId.value
        );

        groups.value = res.groups || [];
        unassignedTeams.value = res.available_teams || [];

        if (res.config) {
            const total =
                unassignedTeams.value.length +
                groups.value.reduce((s, g) => s + g.teams.length, 0);

            teamsPerGroup.value =
                Math.ceil(total / (res.config.num_groups || groups.value.length));
        }
    } catch(error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi thực hiện thao tác');
    }
};

const confirmAssignments = async (isDraft = false) => {
    if (!isCreator.value) return;
    
    loading.value = true;
    try {
        await TournamentTypeService.assignTeamsAndGenerate(
            tournamentTypeId.value,
            {
                groups: groups.value.map(g => ({
                    group_id: g.group_id,
                    team_ids: g.teams.map(t => t.team_id)
                })),
                is_draft: isDraft
            }
        );

        toast.success(
            isDraft
                ? 'Đã lưu nháp sắp xếp bảng đấu!'
                : 'Đã lưu sắp xếp bảng đấu!'
        );

        await fetchTeams();
        hasUnsavedChanges.value = false;
    } catch (error) {
        toast.error(
            error.response?.data?.message ||
            (isDraft
                ? 'Không thể lưu nháp sắp xếp bảng đấu'
                : 'Không thể lưu sắp xếp bảng đấu')
        );
    } finally {
        loading.value = false;
    }
};

/* ================= DRAG ================= */
const onDragStart = (e, team, source) => {
    if (!isCreator.value) return;
    
    draggingTeam.value = team;
    sourceBang.value = source;
    isDragging.value = true;
    e.dataTransfer.effectAllowed = 'move';
};

const onDragEnd = () => {
    if (!isCreator.value) return;
    resetDrag();
};

const handleDragEnter = (groupId) => {
    if (!isCreator.value) return;
    
    dragCounter.value[groupId] = (dragCounter.value[groupId] || 0) + 1;
    dragOverBang.value = groupId;
};

const handleDragLeave = (e, groupId) => {
    if (!isCreator.value) return;
    
    dragCounter.value[groupId]--;
    if (dragCounter.value[groupId] === 0) dragOverBang.value = null;
};

/* ================= DROP ================= */
const onDrop = (e, targetGroupId, targetTeam = null) => {
    if (!isCreator.value) return;
    
    e.preventDefault();
    if (!draggingTeam.value) return resetDrag();
    hasUnsavedChanges.value = true;

    const team = draggingTeam.value;
    const source = sourceBang.value;

    /* A. DROP TO UNASSIGNED */
    if (targetGroupId === 'unassigned') {
        removeTeamFromSource(team, source);
        unassignedTeams.value.push(team);
        return resetDrag();
    }

    const targetGroup = findGroupById(targetGroupId);
    if (!targetGroup) return resetDrag();

    /* B. REORDER SAME GROUP */
    if (source === targetGroupId && targetTeam) {
        const from = targetGroup.teams.findIndex(t => t.team_id === team.team_id);
        const to = targetGroup.teams.findIndex(t => t.team_id === targetTeam.team_id);

        targetGroup.teams.splice(from, 1);
        targetGroup.teams.splice(to, 0, team);
        return resetDrag();
    }

    /* C. MOVE (TARGET HAS SLOT) */
    if (source !== targetGroupId &&
        targetGroup.teams.length < teamsPerGroup.value) {

        removeTeamFromSource(team, source);

        const index = targetTeam
            ? targetGroup.teams.findIndex(t => t.team_id === targetTeam.team_id)
            : null;

        insertTeamToGroup(targetGroupId, team, index);
        return resetDrag();
    }

    /* D. SWAP (TARGET FULL – GIỮ POSITION) */
    if (
        source !== targetGroupId &&
        targetGroup.teams.length >= teamsPerGroup.value &&
        targetTeam
    ) {
        const targetIndex =
            targetGroup.teams.findIndex(t => t.team_id === targetTeam.team_id);

        let sourceIndex = null;
        let sourceGroup = null;

        if (source !== 'unassigned') {
            sourceGroup = findGroupById(source);
            sourceIndex = sourceGroup.teams.findIndex(
                t => t.team_id === team.team_id
            );
        }

        removeTeamFromSource(team, source);

        targetGroup.teams.splice(targetIndex, 1);
        targetGroup.teams.splice(targetIndex, 0, team);

        if (source === 'unassigned') {
            unassignedTeams.value.push(targetTeam);
        } else {
            sourceGroup.teams.splice(sourceIndex, 0, targetTeam);
        }

        return resetDrag();
    }

    resetDrag();
};

/* ================= ACTION ================= */
const removeTeam = (groupId, teamId) => {
    if (!isCreator.value) return;
    
    const g = findGroupById(groupId);
    if (!g) return;

    const idx = g.teams.findIndex(t => t.team_id === teamId);
    if (idx !== -1) {
        unassignedTeams.value.push(g.teams[idx]);
        g.teams.splice(idx, 1);
        hasUnsavedChanges.value = true;
    }
};

const autoAssignTeams = async() => {
    if (!isCreator.value) return;
    
    try {
        await TournamentTypeService.autoGenerateTeamAndMatches(tournamentTypeId.value);
        toast.success('Chia bảng thành công');
        await fetchTeams();
        hasUnsavedChanges.value = true;
    } catch(error) {
        toast.error(error.response?.data?.message || 'Không thể lưu sắp xếp bảng đấu');
    }
}

const handleBeforeUnload = (event) => {
    if (!isCreator.value || !hasUnsavedChanges.value) return;
    event.preventDefault();
    event.returnValue = '';
};

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
});

onBeforeRouteLeave(async (to, from, next) => {
    if (!isCreator.value || !hasUnsavedChanges.value) {
        next();
        return;
    }

    // Chặn navigation trước
    next(false);
    
    // Hiển thị modal và đợi kết quả
    const confirmed = await showModal({
        title: 'Thay đổi chưa được lưu',
        message: 'Bạn có thay đổi chưa được lưu. Bạn có muốn lưu nháp trước khi rời đi?',
        confirmText: 'Lưu nháp & rời đi',
        cancelText: 'Rời đi không lưu',
        confirmClass: 'bg-[#FBBF24]'
    });

    if (confirmed) {
        try {
            await confirmAssignments(true);
            hasUnsavedChanges.value = false;
            // Đợi 1.5s để toast hiển thị xong
            await new Promise(resolve => setTimeout(resolve, 1500));
        } catch {
            // Nếu lưu lỗi, không cho rời đi
            return;
        }
    }
    
    // Sau khi xử lý xong, cho phép navigation
    next();
});

/* ================= INIT ================= */
onMounted(async () => {
    await getTournamentTypeId(tournamentId);
    await fetchTeams();
});
</script>

<style scoped>
.custom-scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.custom-scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .relative,
.modal-leave-active .relative {
    transition: transform 0.2s ease, opacity 0.2s ease;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
    transform: scale(0.95);
    opacity: 0;
}
</style>