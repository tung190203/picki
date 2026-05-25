<template>
    <div class="mt-8 bg-white rounded-2xl shadow-sm flex flex-col">
        <div class="flex border-b border-gray-200 mx-2">
            <button v-for="tab in tabs" :key="tab.id" @click="handleTabClick(tab)"
                class="flex-1 py-4 text-center font-semibold transition-all relative"
                :class="[
                    activeTab === tab.id ? 'text-[#D72D36]' : 'text-[#838799]',
                    !props.isJoined && tab.id !== 'intro' && 'opacity-50 cursor-not-allowed pointer-events-none'
                ]">
                {{ tab.name }}
                <div v-if="activeTab === tab.id" class="absolute bottom-0 left-0 w-full h-1 bg-[#D72D36]"></div>
            </button>
        </div>

        <div class="relative">
            <div class="p-8 transition-all duration-500 ease-in-out"
                ref="contentWrapper">
                <!-- Tab Content -->
                <div v-show="activeTab === 'intro'" ref="introContent" class="relative">
                    <Transition name="fade-slide" mode="out-in">
                        <div v-if="isEditingIntro" :key="'edit'" class="space-y-4">
                            <textarea v-model="editDescription" rows="6" maxlength="300"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] transition-colors placeholder:text-gray-400"
                                placeholder="Nhập giới thiệu về CLB..."></textarea>
                            <div class="flex items-center gap-3">
                                <button @click="cancelEditIntro"
                                    class="flex-1 px-4 py-2 rounded-lg border border-gray-200 text-[#3E414C] font-semibold hover:bg-gray-50 transition-colors">
                                    Hủy
                                </button>
                                <button @click="saveIntro" :disabled="isSaving"
                                    class="flex-1 px-4 py-2 rounded-lg bg-[#D72D36] text-white font-semibold hover:bg-[#c9252e] transition-colors disabled:opacity-50">
                                    {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
                                </button>
                            </div>
                        </div>

                        <div v-else-if="club?.profile?.description" :key="'view'" class="flex flex-col relative group">
                            <div class="whitespace-pre-wrap text-[#3E414C] leading-relaxed description-text overflow-hidden transition-all duration-500 ease-in-out"
                                :class="{ 'line-clamp-3': !isExpanded && !isAnimating && activeTab === 'intro' }"
                                :style="{ maxHeight: isExpanded ? contentHeight + 'px' : collapsedHeight + 'px' }"
                                @transitionstart="isAnimating = true"
                                @transitionend="isAnimating = false">
                                {{ club.profile.description }}
                            </div>
                            <div v-if="needsExpand" class="mt-2 flex justify-start">
                                <button @click="toggleExpand"
                                    class="text-[#D72D36] text-sm font-semibold hover:underline transition-colors uppercase">
                                    {{ isExpanded ? '[Thu gọn]' : '[Đọc thêm]' }}
                                </button>
                            </div>
                            <!-- Edit button at bottom-right - persistent for better UX -->
                            <button v-if="['admin', 'secretary'].includes(currentUserRole)" 
                                @click="startEditIntro"
                                title="Chỉnh sửa giới thiệu"
                                class="absolute bottom-0 right-0 p-2 bg-white shadow-md rounded-full text-[#D72D36] border border-gray-100 hover:bg-gray-50 transition-all duration-200 z-10 scale-90 hover:scale-100">
                                <PencilSquareIcon class="w-4 h-4" />
                            </button>
                        </div>

                        <div v-else :key="'empty'" class="flex flex-col items-center justify-center min-h-[100px] text-gray-400">
                            <span class="text-sm italic mb-4">Chưa có mô tả</span>
                            <button v-if="['admin', 'secretary'].includes(currentUserRole)"
                                @click="startEditIntro"
                                class="flex items-center gap-2 px-6 py-2.5 bg-gray-50 hover:bg-gray-100 text-[#D72D36] rounded-full transition-colors font-medium border border-dashed border-[#D72D36]/30">
                                <PlusIcon class="w-4 h-4" />
                                Thêm giới thiệu
                            </button>
                        </div>
                    </Transition>
                </div>

                <div v-show="activeTab === 'members'" class="text-gray-400">
                    <template v-if="hasMembersTabBeenActive">
                        <ClubMember v-if="club?.id" :club-id="club.id" :isJoined="isJoined" :currentUserRole="currentUserRole" @refresh-club="$emit('refresh-club')" />
                        <div v-else class="text-center py-12">
                            <p class="text-gray-400">Đang tải...</p>
                        </div>
                    </template>
                </div>

                <div v-show="activeTab === 'ranking'">
                    <ClubRanking 
                        :top-three="topThree"
                        :leaderboard="leaderboard"
                        :meta="leaderboardMeta"
                        :loading="leaderboardLoading"
                        @page-change="$emit('leaderboard-page-change', $event)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { ChevronDownIcon, ChevronUpIcon, PencilSquareIcon, PlusIcon } from '@heroicons/vue/24/outline'
import ClubMember from '@/components/molecules/ClubMember.vue'
import ClubRanking from '@/components/molecules/ClubRanking.vue'

const activeTab = ref('intro')
const isExpanded = ref(false)
const isAnimating = ref(false)
const contentHeight = ref(0)
const collapsedHeight = ref(78)
const contentWrapper = ref(null)
const introContent = ref(null)
const hasMembersTabBeenActive = ref(false)

const props = defineProps({
    club: {
        type: Object,
        default: () => ({})
    },
    isJoined: {
        type: Boolean,
        default: false
    },
    currentUserRole: {
        type: String,
        default: null
    },
    topThree: {
        type: Array,
        default: () => []
    },
    leaderboard: {
        type: Array,
        default: () => []
    },
    leaderboardMeta: {
        type: Object,
        default: () => ({})
    },
    leaderboardLoading: {
        type: Boolean,
        default: false
    },
    isSaving: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['leaderboard-page-change', 'tab-change', 'refresh-club', 'update-intro'])

const tabs = computed(() => [
    { id: 'intro', name: 'Giới thiệu' },
    { id: 'members', name: `Thành viên (${props.club?.quantity_members || 0})` },
    { id: 'ranking', name: 'BXH' }
])

// Only apply height limit to intro tab
const shouldLimitHeight = computed(() => {
    return activeTab.value === 'intro'
})

const needsExpand = computed(() => {
    // 3 lines * 26px (approx line height for leading-relaxed) = 78px
    // Use 80px as a safe threshold
    return activeTab.value === 'intro' && contentHeight.value > 80
})

const updateContentHeight = async () => {
    await nextTick()
    if (activeTab.value === 'intro' && introContent.value) {
        const descDiv = introContent.value.querySelector('.description-text')
        if (descDiv) {
            // Measure actual height without controls
            const wasExpanded = isExpanded.value
            const wasAnimating = isAnimating.value
            
            // Temporary state for measurement
            descDiv.style.transition = 'none'
            descDiv.classList.remove('line-clamp-3')
            descDiv.style.maxHeight = 'none'
            
            // Full content height
            contentHeight.value = descDiv.scrollHeight
            
            // Measure collapsed height (exactly 3 lines)
            descDiv.classList.add('line-clamp-3')
            collapsedHeight.value = descDiv.offsetHeight
            
            // Restore actual state
            descDiv.classList.toggle('line-clamp-3', !wasExpanded && !wasAnimating)
            descDiv.style.maxHeight = wasExpanded ? `${contentHeight.value}px` : `${collapsedHeight.value}px`
            
            // Re-enable transition after next tick
            await nextTick()
            descDiv.style.transition = ''
        }
    }
}

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

watch(() => props.isSaving, (newVal, oldVal) => {
    if (oldVal && !newVal && isEditingIntro.value) {
        isEditingIntro.value = false
    }
})

const toggleExpand = () => {
    isExpanded.value = !isExpanded.value
}

const handleTabClick = (tab) => {
    if (!props.isJoined && tab.id !== 'intro') return
    activeTab.value = tab.id
    if (tab.id === 'members') {
        hasMembersTabBeenActive.value = true
    }
    emit('tab-change', tab.id)
}

watch(activeTab, () => {
    isExpanded.value = false
    updateContentHeight()
})

onMounted(() => {
    updateContentHeight()
    window.addEventListener('resize', updateContentHeight)
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

.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.3s ease;
}

.fade-slide-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
