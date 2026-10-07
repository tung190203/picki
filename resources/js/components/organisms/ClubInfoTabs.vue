<template>
    <div class="mt-8 bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-2xl shadow-sm flex flex-col overflow-hidden">
        <!-- Horizontal Scrollable Tab Bar -->
        <div class="flex border-b border-gray-200 dark:border-slate-800 overflow-x-auto custom-scrollbar whitespace-nowrap bg-gray-50/50 dark:bg-[#1E293B] px-2">
            <button v-for="tab in tabs" :key="tab.id" :ref="(el) => setTabRef(el, tab.id)"
                @click="handleTabClick(tab)"
                class="flex-shrink-0 md:flex-1 px-4 sm:px-6 py-4 text-center font-semibold text-sm transition-all relative"
                :class="[
                    activeTab === tab.id ? 'text-[#D72D36] dark:text-red-400 bg-white dark:bg-[#161F33] font-bold' : 'text-[#838799] dark:text-slate-400 hover:text-gray-800 dark:hover:text-white',
                    !props.isJoined && tab.id !== 'intro' && 'opacity-50 cursor-not-allowed pointer-events-none'
                ]">
                {{ tab.name }}
                <div v-if="activeTab === tab.id" class="absolute bottom-0 left-0 w-full h-1 bg-[#D72D36] dark:bg-red-500"></div>
            </button>
        </div>

        <div class="relative">
            <div class="p-4 sm:p-8 transition-all duration-500 ease-in-out" ref="contentWrapper">
                <!-- 1. Tab Giới thiệu -->
                <div v-show="activeTab === 'intro'" ref="introContent" class="relative space-y-6">

                    <!-- Giới thiệu -->
                    <div>
                        <h3 class="text-sm font-bold text-[#1F2937] dark:text-slate-100 mb-2 uppercase tracking-wide">Giới thiệu</h3>
                        <Transition name="fade-slide" mode="out-in">
                            <div v-if="isEditingIntro" :key="'edit'" class="space-y-3">
                                <textarea v-model="editDescription" rows="4" maxlength="300"
                                    class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-slate-100 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors placeholder:text-gray-400 dark:placeholder:text-slate-500 resize-none"
                                    placeholder="Nhập giới thiệu về CLB..."></textarea>
                                <div class="flex items-center gap-3">
                                    <button @click="cancelEditIntro"
                                        class="flex-1 px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-700 text-[#3E414C] dark:text-slate-200 font-semibold hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                                        Hủy
                                    </button>
                                    <button @click="saveIntro" :disabled="isSaving"
                                        class="flex-1 px-4 py-2 rounded-lg bg-[#D72D36] text-white font-semibold hover:bg-[#c9252e] transition-colors disabled:opacity-50">
                                        {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
                                    </button>
                                </div>
                            </div>

                            <div v-else-if="club?.profile?.description" :key="'view'" class="relative group">
                                <p class="whitespace-pre-wrap text-sm text-[#3E414C] dark:text-slate-200 leading-relaxed">
                                    {{ club.profile.description }}
                                </p>
                                <button v-if="canManageIntro"
                                    @click="startEditIntro"
                                    title="Chỉnh sửa giới thiệu"
                                    class="absolute top-0 right-0 p-1.5 bg-white dark:bg-slate-800 shadow-sm rounded-full text-[#D72D36] dark:text-red-400 border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                                    <PencilSquareIcon class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            <div v-else :key="'empty'" class="text-center py-3">
                                <span v-if="canManageIntro" class="text-xs italic text-[#838799]">Chưa có mô tả</span>
                                <span v-else class="text-xs italic text-[#838799]">CLB chưa cập nhật mô tả</span>
                            </div>
                        </Transition>
                    </div>

                    <!-- Nội quy -->
                    <div>
                        <h3 class="text-sm font-bold text-[#1F2937] dark:text-slate-100 mb-2 uppercase tracking-wide">Nội quy</h3>
                        <Transition name="fade-slide" mode="out-in">
                            <div v-if="isEditingRules" :key="'edit'" class="space-y-3">
                                <textarea v-model="editRules" rows="5" maxlength="5000"
                                    class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-slate-100 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors placeholder:text-gray-400 dark:placeholder:text-slate-500 resize-y"
                                    placeholder="Nhập nội quy CLB (mỗi dòng 1 điều, vd: 1. Tôn trọng thành viên khác)..."></textarea>
                                <div class="flex items-center gap-3">
                                    <button @click="cancelEditRules"
                                        class="flex-1 px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-700 text-[#3E414C] dark:text-slate-200 font-semibold hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                                        Hủy
                                    </button>
                                    <button @click="saveRules" :disabled="isSaving"
                                        class="flex-1 px-4 py-2 rounded-lg bg-[#D72D36] text-white font-semibold hover:bg-[#c9252e] transition-colors disabled:opacity-50">
                                        {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
                                    </button>
                                </div>
                            </div>

                            <div v-else-if="club?.rules" :key="'view'" class="relative group">
                                <div class="whitespace-pre-wrap text-sm text-[#3E414C] dark:text-slate-200 leading-relaxed bg-gray-50 dark:bg-slate-800 rounded-xl p-3 border border-gray-100 dark:border-slate-700">
                                    {{ club.rules }}
                                </div>
                                <button v-if="canManageIntro"
                                    @click="startEditRules"
                                    title="Chỉnh sửa nội quy"
                                    class="absolute top-2 right-2 p-1.5 bg-white dark:bg-slate-800 shadow-sm rounded-full text-[#D72D36] dark:text-red-400 border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                                    <PencilSquareIcon class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            <div v-else :key="'empty'" class="text-center py-3">
                                <span v-if="canManageIntro" class="text-xs italic text-[#838799]">Chưa có nội quy</span>
                                <span v-else class="text-xs italic text-[#838799]">CLB chưa công bố nội quy</span>
                            </div>
                        </Transition>
                    </div>

                    <!-- Lịch sinh hoạt -->
                    <div>
                        <h3 class="text-sm font-bold text-[#1F2937] dark:text-slate-100 mb-2 uppercase tracking-wide">Lịch sinh hoạt</h3>
                        <Transition name="fade-slide" mode="out-in">
                            <div v-if="isEditingSchedule" :key="'edit'" class="space-y-3">
                                <textarea v-model="editScheduleText" rows="5" maxlength="5000"
                                    class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-slate-100 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors placeholder:text-gray-400 dark:placeholder:text-slate-500 resize-y"
                                    placeholder="Nhập lịch sinh hoạt định kỳ (vd: Thứ 2: 18h-21h tập cơ bản&#10;Thứ 4: 19h-22h tập nâng cao)..."></textarea>
                                <div class="flex items-center gap-3">
                                    <button @click="cancelEditSchedule"
                                        class="flex-1 px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-700 text-[#3E414C] dark:text-slate-200 font-semibold hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                                        Hủy
                                    </button>
                                    <button @click="saveSchedule" :disabled="isSaving"
                                        class="flex-1 px-4 py-2 rounded-lg bg-[#D72D36] text-white font-semibold hover:bg-[#c9252e] transition-colors disabled:opacity-50">
                                        {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
                                    </button>
                                </div>
                            </div>

                            <div v-else-if="club?.recurring_schedule_text" :key="'view'" class="relative group">
                                <div class="whitespace-pre-wrap text-sm text-[#3E414C] dark:text-slate-200 leading-relaxed bg-gray-50 dark:bg-slate-800 rounded-xl p-3 border border-gray-100 dark:border-slate-700">
                                    {{ club.recurring_schedule_text }}
                                </div>
                                <button v-if="canManageIntro"
                                    @click="startEditSchedule"
                                    title="Chỉnh sửa lịch sinh hoạt"
                                    class="absolute top-2 right-2 p-1.5 bg-white dark:bg-slate-800 shadow-sm rounded-full text-[#D72D36] dark:text-red-400 border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                                    <PencilSquareIcon class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            <div v-else :key="'empty'" class="text-center py-3">
                                <span v-if="canManageIntro" class="text-xs italic text-[#838799]">Chưa có lịch sinh hoạt</span>
                                <span v-else class="text-xs italic text-[#838799]">CLB chưa công bố lịch sinh hoạt</span>
                            </div>
                        </Transition>
                    </div>

                    <!-- Sân nhà (Home Courts) -->
                    <ClubHomeCourtsSection
                        v-if="club?.id"
                        :club-id="club.id"
                        :can-manage="canManageIntro"
                        :initial-courts="club.home_courts || []"
                        class="mt-2"
                    />
                </div>

                <!-- 2. Tab Thành viên -->
                <div v-show="activeTab === 'members'" class="text-gray-400">
                    <template v-if="hasMembersTabBeenActive">
                        <ClubMember v-if="club?.id" :club-id="club.id" :isJoined="isJoined" :currentUserRole="currentUserRole" @refresh-club="$emit('refresh-club')" />
                        <div v-else class="text-center py-12">
                            <p class="text-gray-400">Đang tải...</p>
                        </div>
                    </template>
                </div>

                <!-- 3. Tab BXH -->
                <div v-show="activeTab === 'ranking'">
                    <ClubAchievementRanking
                        v-if="club?.id"
                        :club-id="club.id"
                        :top-three="topThree"
                        :leaderboard="leaderboard"
                        :meta="leaderboardMeta"
                        :loading="leaderboardLoading"
                        @page-change="$emit('leaderboard-page-change', $event)"
                    />
                </div>

                <!-- 4. Tab Nhóm chat -->
                <div v-show="activeTab === 'chat'">
                    <ClubChatTab v-if="club?.id" :club-id="club.id" :club-name="club?.name || 'CLB'" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { PencilSquareIcon } from '@heroicons/vue/24/outline'
import ClubMember from '@/components/molecules/ClubMember.vue'
import ClubAchievementRanking from '@/components/molecules/ClubAchievementRanking.vue'
import ClubChatTab from '@/components/molecules/ClubChatTab.vue'
import ClubHomeCourtsSection from '@/components/pages/club/partials/ClubHomeCourtsSection.vue'

const activeTab = ref('intro')
const isExpanded = ref(false)
const isAnimating = ref(false)
const contentHeight = ref(0)
const collapsedHeight = ref(78)
const contentWrapper = ref(null)
const introContent = ref(null)
const hasMembersTabBeenActive = ref(false)
const tabRefs = ref({})

const props = defineProps({
    club: { type: Object, default: () => ({}) },
    isJoined: { type: Boolean, default: false },
    currentUserRole: { type: String, default: null },
    topThree: { type: Array, default: () => [] },
    leaderboard: { type: Array, default: () => [] },
    leaderboardMeta: { type: Object, default: () => ({}) },
    leaderboardLoading: { type: Boolean, default: false },
    isSaving: { type: Boolean, default: false }
})

const emit = defineEmits(['leaderboard-page-change', 'tab-change', 'refresh-club', 'update-intro', 'update-rules', 'update-schedule'])

// Cho phép admin/manager/secretary chỉnh sửa
const canManageIntro = computed(() => {
    return ['admin', 'manager', 'secretary'].includes(props.currentUserRole)
})

const tabs = computed(() => [
    { id: 'intro', name: 'Giới thiệu' },
    { id: 'members', name: `Thành viên (${props.club?.quantity_members || 0})` },
    { id: 'ranking', name: 'BXH' },
    { id: 'chat', name: 'Nhóm chat' }
])

const setTabRef = (el, id) => {
    if (el) tabRefs.value[id] = el
}

// --- Description ---
const isEditingIntro = ref(false)
const editDescription = ref('')

const startEditIntro = () => {
    editDescription.value = props.club?.profile?.description || ''
    isEditingIntro.value = true
}
const cancelEditIntro = () => {
    isEditingIntro.value = false
    editDescription.value = ''
}
const saveIntro = () => {
    emit('update-intro', editDescription.value)
}

// --- Rules ---
const isEditingRules = ref(false)
const editRules = ref('')

const startEditRules = () => {
    editRules.value = props.club?.rules || ''
    isEditingRules.value = true
}
const cancelEditRules = () => {
    isEditingRules.value = false
    editRules.value = ''
}
const saveRules = () => {
    emit('update-rules', editRules.value)
}

// --- Recurring Schedule ---
const isEditingSchedule = ref(false)
const editScheduleText = ref('')

const startEditSchedule = () => {
    editScheduleText.value = props.club?.recurring_schedule_text || ''
    isEditingSchedule.value = true
}
const cancelEditSchedule = () => {
    isEditingSchedule.value = false
    editScheduleText.value = ''
}
const saveSchedule = () => {
    emit('update-schedule', editScheduleText.value)
}

// Reset editing states when parent signals save done
watch(() => props.isSaving, (newVal, oldVal) => {
    if (oldVal && !newVal) {
        if (isEditingIntro.value) isEditingIntro.value = false
        if (isEditingRules.value) isEditingRules.value = false
        if (isEditingSchedule.value) isEditingSchedule.value = false
    }
})

const handleTabClick = (tab) => {
    if (!props.isJoined && tab.id !== 'intro') return
    activeTab.value = tab.id
    if (tab.id === 'members') hasMembersTabBeenActive.value = true
    if (tabRefs.value[tab.id]) {
        tabRefs.value[tab.id].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' })
    }
    emit('tab-change', tab.id)
}

watch(activeTab, () => {
    isExpanded.value = false
})

onMounted(() => {
    window.addEventListener('resize', () => {})
})
</script>

<style scoped>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.fade-slide-enter-active, .fade-slide-leave-active { transition: all 0.3s ease; }
.fade-slide-enter-from { opacity: 0; transform: translateY(8px); }
.fade-slide-leave-to { opacity: 0; transform: translateY(-8px); }
.custom-scrollbar::-webkit-scrollbar { height: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 4px; }
</style>
