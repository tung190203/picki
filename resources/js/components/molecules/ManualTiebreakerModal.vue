<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="modelValue"
                class="fixed inset-0 z-[10010] flex items-start justify-center p-4 bg-gray-900 bg-opacity-50 backdrop-blur-sm overflow-y-auto"
                @click.self="closeModal"
            >
                <div class="bg-white dark:bg-[#161F33] rounded-xl shadow-2xl w-full max-w-2xl transform transition-all duration-300 my-8">
                    <!-- Header -->
                    <div class="p-5 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between sticky top-0 bg-white dark:bg-[#161F33] rounded-t-xl z-10">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100">
                                Bốc thăm thủ công
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
                                {{ groupName }} — Kéo thả để sắp xếp thứ tự các đội đồng hạng
                            </p>
                        </div>
                        <button
                            @click="closeModal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 transition-colors p-1"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="p-5">
                        <!-- Info Banner -->
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 mb-5 flex items-start gap-2">
                            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <div class="text-sm text-yellow-700 dark:text-yellow-300">
                                <p>
                                    Các đội dưới đây hiện đang <strong>đồng hạng</strong> trên tất cả các chỉ số
                                    (điểm, hiệu số, bàn thắng, …). Hệ thống không thể tự xếp — bạn cần:
                                </p>
                                <ul class="list-disc list-inside mt-1.5 space-y-0.5">
                                    <li>Kéo thả để sắp xếp thứ tự thủ công, hoặc</li>
                                    <li>Nhấn <strong>"Bốc thăm ngẫu nhiên"</strong> để hệ thống xáo trộn.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Pending tie cluster select -->
                        <div v-if="clusters.length > 1" class="mb-4">
                            <label class="text-xs font-medium text-gray-600 dark:text-slate-400 mb-1.5 block">
                                Cụm đồng hạng
                            </label>
                            <select
                                v-model.number="activeClusterIdx"
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-[#1E293B] dark:text-slate-100"
                            >
                                <option
                                    v-for="(cluster, idx) in clusters"
                                    :key="idx"
                                    :value="idx"
                                >
                                    Cụm {{ idx + 1 }} ({{ cluster.teams.length }} đội
                                    — cùng {{ cluster.stats.points }}đ, HS: {{ cluster.stats.point_diff }})
                                </option>
                            </select>
                        </div>

                        <!-- Teams list (HTML5 drag-and-drop) -->
                        <div class="space-y-2 mb-5">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-slate-300">
                                    Sắp xếp thứ tự (kéo-thả)
                                </h4>
                                <button
                                    @click="randomShuffle"
                                    class="px-2.5 py-1 text-xs font-medium bg-orange-100 dark:bg-orange-900/30 hover:bg-orange-200 text-orange-700 dark:text-orange-300 rounded transition-colors"
                                >
                                    Bốc thăm ngẫu nhiên
                                </button>
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="(team, index) in orderedTeams"
                                    :key="team.team_id"
                                    :draggable="true"
                                    @dragstart="onDragStart($event, index)"
                                    @dragover.prevent
                                    @drop="onDrop($event, index)"
                                    class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg cursor-move hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                                    :class="{ 'opacity-50': dragSourceIdx === index }"
                                >
                                    <span class="text-gray-400 dark:text-slate-500 select-none cursor-grab">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M7 2a1 1 0 011 1v3a1 1 0 11-2 0V3a1 1 0 011-1zm0 10a1 1 0 011 1v3a1 1 0 11-2 0v-3a1 1 0 011-1zm6-10a1 1 0 011 1v3a1 1 0 11-2 0V3a1 1 0 011-1zm0 10a1 1 0 011 1v3a1 1 0 11-2 0v-3a1 1 0 011-1z" />
                                        </svg>
                                    </span>

                                    <span class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-bold text-sm">
                                        {{ index + 1 }}
                                    </span>

                                    <img
                                        v-if="team.team_avatar"
                                        :src="team.team_avatar"
                                        class="w-10 h-10 rounded-full border border-gray-300 dark:border-slate-600 flex-shrink-0"
                                        :alt="team.team_name"
                                    />
                                    <div
                                        v-else
                                        class="w-10 h-10 rounded-full border border-gray-300 dark:border-slate-600 flex-shrink-0 bg-gray-200 dark:bg-slate-700 flex items-center justify-center text-sm font-bold text-gray-600 dark:text-slate-300"
                                    >
                                        {{ (team.team_name || '?').charAt(0) }}
                                    </div>

                                    <p class="flex-1 text-sm font-medium text-gray-800 dark:text-slate-100 truncate">
                                        {{ team.team_name || `Team #${team.team_id}` }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Stats snapshot -->
                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg p-3 mb-5 text-xs text-gray-600 dark:text-slate-400">
                            <p class="font-semibold text-gray-700 dark:text-slate-300 mb-1">Chỉ số chung (cả cụm):</p>
                            <div class="flex gap-4 flex-wrap">
                                <span>Điểm: <strong>{{ activeCluster.stats.points }}</strong></span>
                                <span>HS hiệp: <strong>{{ activeCluster.stats.sets_diff }}</strong></span>
                                <span>HS điểm: <strong>{{ activeCluster.stats.point_diff }}</strong></span>
                                <span>Bàn thắng: <strong>{{ activeCluster.stats.points_for }}</strong></span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-2 pt-4 border-t border-gray-100 dark:border-slate-700">
                            <button
                                @click="onReset"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-slate-200 bg-white dark:bg-[#1E293B] border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                            >
                                Reset
                            </button>
                            <button
                                @click="closeModal"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-slate-200 bg-white dark:bg-[#1E293B] border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                            >
                                Hủy
                            </button>
                            <button
                                @click="onSave"
                                :disabled="isSaving || orderedTeams.length < 2"
                                class="px-4 py-2 text-sm font-medium text-white bg-[#D72D36] hover:bg-red-700 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ isSaving ? 'Đang lưu...' : 'Lưu thứ hạng' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { toast } from 'vue3-toastify'
import {
    getPendingTies,
    storeManualTiebreaker,
} from '@/service/tournamentType'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    tournamentTypeId: { type: [Number, String], required: true },
    groupId: { type: [Number, String], required: true },
    groupName: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const isSaving = ref(false)
const clusters = ref([])
const activeClusterIdx = ref(0)
const orderedTeams = ref([])

// Drag-and-drop state
const dragSourceIdx = ref(null)

const activeCluster = computed(() => {
    return clusters.value[activeClusterIdx.value] ?? { teams: [], stats: {} }
})

watch(
    () => props.modelValue,
    async (open) => {
        if (open) {
            await loadClusters()
        } else {
            clusters.value = []
            orderedTeams.value = []
            activeClusterIdx.value = 0
            dragSourceIdx.value = null
        }
    }
)

watch(activeClusterIdx, () => {
    syncOrderedTeams()
})

async function loadClusters() {
    try {
        const data = await getPendingTies(props.tournamentTypeId, props.groupId)
        clusters.value = data.clusters || []
        activeClusterIdx.value = 0
        syncOrderedTeams()
    } catch (err) {
        toast.error(err.message || 'Không tải được danh sách đồng hạng')
        emit('update:modelValue', false)
    }
}

function syncOrderedTeams() {
    const c = activeCluster.value
    orderedTeams.value = (c.teams || []).map((t) => ({ ...t }))
}

function onDragStart(event, idx) {
    dragSourceIdx.value = idx
    // Required for Firefox
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move'
    }
}

function onDrop(event, targetIdx) {
    event.preventDefault()
    const src = dragSourceIdx.value
    if (src === null || src === targetIdx) {
        dragSourceIdx.value = null
        return
    }
    const arr = [...orderedTeams.value]
    const [moved] = arr.splice(src, 1)
    arr.splice(targetIdx, 0, moved)
    orderedTeams.value = arr
    dragSourceIdx.value = null
}

function randomShuffle() {
    const arr = [...orderedTeams.value]
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1))
        ;[arr[i], arr[j]] = [arr[j], arr[i]]
    }
    orderedTeams.value = arr
}

async function onSave() {
    if (orderedTeams.value.length < 2) {
        toast.warning('Cần ít nhất 2 đội để bốc thăm')
        return
    }
    isSaving.value = true
    try {
        const rankings = orderedTeams.value.map((t, idx) => ({
            team_id: t.team_id,
            manual_rank: idx + 1,
        }))
        await storeManualTiebreaker(props.tournamentTypeId, props.groupId, rankings)
        toast.success('Đã lưu thứ hạng thủ công')
        emit('saved', rankings)
        closeModal()
    } catch (err) {
        const msg = err.response?.data?.message || err.message || 'Lỗi khi lưu'
        toast.error(msg)
    } finally {
        isSaving.value = false
    }
}

function onReset() {
    syncOrderedTeams()
    toast.info('Đã reset về thứ tự ban đầu')
}

function closeModal() {
    emit('update:modelValue', false)
}
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
</style>
