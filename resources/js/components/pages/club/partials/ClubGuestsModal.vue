<template>
    <Transition name="fade">
        <div v-if="isOpen"
            class="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-sm"
            @click.self="close">
            <Transition name="scale">
                <div v-if="isOpen"
                    class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-[24px] w-full max-w-[640px] max-h-[95vh] sm:max-h-[90vh] transition-all duration-300 flex flex-col p-4 sm:p-6 relative shadow-2xl overflow-hidden">
                    <!-- Close button -->
                    <button @click="close"
                        class="absolute right-5 top-5 text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-white transition-colors z-10">
                        <XMarkIcon class="w-6 h-6" />
                    </button>

                    <!-- Header -->
                    <div class="mb-4 flex-shrink-0 pr-8">
                        <h2 class="text-[20px] font-bold text-[#1F2937] dark:text-slate-100">Khách của CLB</h2>
                        <p class="text-sm text-[#838799] dark:text-slate-400 mt-1">
                            Người chơi kèo/giải nhưng chưa là thành viên
                        </p>
                    </div>

                    <!-- Tabs -->
                    <div class="flex items-center space-x-1 bg-gray-100 dark:bg-slate-800/60 rounded-xl p-1 mb-4 flex-shrink-0">
                        <button @click="activeTab = 'potential'"
                            :class="[
                                'flex-1 flex items-center justify-center space-x-2 py-2 px-3 rounded-lg text-sm font-semibold transition-all',
                                activeTab === 'potential'
                                    ? 'bg-white dark:bg-slate-700 text-[#D72D36] shadow-sm'
                                    : 'text-gray-500 dark:text-slate-400'
                            ]">
                            <span>Tiềm năng</span>
                            <span :class="[
                                'text-[11px] font-bold rounded-full px-2 py-0.5',
                                activeTab === 'potential' ? 'bg-[#D72D36]/10 text-[#D72D36]' : 'bg-gray-200 dark:bg-slate-700 text-gray-500'
                            ]">{{ guests?.counts?.potential ?? 0 }}</span>
                        </button>
                        <button @click="activeTab = 'normal'"
                            :class="[
                                'flex-1 flex items-center justify-center space-x-2 py-2 px-3 rounded-lg text-sm font-semibold transition-all',
                                activeTab === 'normal'
                                    ? 'bg-white dark:bg-slate-700 text-[#3E414C] dark:text-slate-100 shadow-sm'
                                    : 'text-gray-500 dark:text-slate-400'
                            ]">
                            <span>Bình thường</span>
                            <span :class="[
                                'text-[11px] font-bold rounded-full px-2 py-0.5',
                                activeTab === 'normal' ? 'bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-200' : 'bg-gray-200 dark:bg-slate-700 text-gray-500'
                            ]">{{ guests?.counts?.normal ?? 0 }}</span>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar -mx-2 px-2">
                        <div v-if="isLoading" class="flex flex-col items-center justify-center py-12 space-y-3">
                            <div class="w-10 h-10 border-2 border-[#D72D36] border-t-transparent rounded-full animate-spin"></div>
                            <p class="text-sm text-gray-500 dark:text-slate-400">Đang tải...</p>
                        </div>

                        <div v-else-if="!currentList || currentList.length === 0"
                            class="flex flex-col items-center justify-center py-12 text-center">
                            <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center mb-3">
                                <UserGroupIcon class="w-8 h-8 text-gray-400 dark:text-slate-500" />
                            </div>
                            <p class="text-sm font-semibold text-[#3E414C] dark:text-slate-200">
                                {{ activeTab === 'potential' ? 'Chưa có khách tiềm năng' : 'Chưa có khách' }}
                            </p>
                            <p class="text-xs text-[#838799] dark:text-slate-400 mt-1">
                                {{ activeTab === 'potential'
                                    ? 'Khách quay lại trong 30 ngày gần nhất sẽ hiển thị ở đây'
                                    : 'Khách đã chơi nhưng chưa tham gia gần đây' }}
                            </p>
                        </div>

                        <div v-else class="space-y-2">
                            <div v-for="guest in currentList" :key="guest.user_id"
                                class="flex items-center justify-between p-3 bg-white dark:bg-slate-800/40 border border-gray-100 dark:border-slate-700/60 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-800/80 transition-colors">
                                <!-- User info -->
                                <div class="flex items-center space-x-3 min-w-0 flex-1">
                                    <div class="relative flex-shrink-0">
                                        <img :src="guest.user?.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(guest.user?.full_name || 'U')}&background=random`"
                                            class="w-11 h-11 rounded-full object-cover border border-gray-100 dark:border-slate-700"
                                            @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(guest.user?.full_name || 'U')}&background=random`" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-[#1F2937] dark:text-slate-100 text-sm truncate">
                                            {{ guest.user?.full_name || 'Người dùng' }}
                                        </p>
                                        <p class="text-xs text-[#838799] dark:text-slate-400 mt-0.5 flex items-center space-x-1">
                                            <span>Chơi {{ guest.play_count }} lần</span>
                                            <span>•</span>
                                            <span>{{ formatLastPlayed(guest) }}</span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center space-x-2 flex-shrink-0">
                                    <span v-if="guest.is_invited"
                                        class="text-[10px] font-bold uppercase tracking-wider bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 px-2 py-1 rounded-full">
                                        Đã mời
                                    </span>
                                    <button v-else @click="handleInvite(guest)" :disabled="invitingId === guest.user_id"
                                        class="text-xs font-semibold bg-[#D72D36] hover:bg-red-700 text-white px-3 py-1.5 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span v-if="invitingId === guest.user_id">...</span>
                                        <span v-else>Mời</span>
                                    </button>
                                    <button @click="handleDelete(guest)" :disabled="deletingId === guest.user_id"
                                        class="p-1.5 text-gray-400 hover:text-[#D72D36] hover:bg-red-50 dark:hover:bg-red-950/30 rounded-lg transition-colors disabled:opacity-50">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import {
    XMarkIcon,
    TrashIcon,
    UserGroupIcon
} from '@heroicons/vue/24/outline'
import { getClubGuests, inviteClubGuest, deleteClubGuest } from '@/service/club'

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    clubId: {
        type: [String, Number],
        required: true
    }
})

const emit = defineEmits(['update:isOpen', 'refresh'])

const guests = ref({ normal: [], potential: [], counts: { normal: 0, potential: 0, total: 0 } })
const isLoading = ref(false)
const activeTab = ref('potential')
const invitingId = ref(null)
const deletingId = ref(null)

const currentList = computed(() => guests.value?.[activeTab.value] || [])

const close = () => {
    emit('update:isOpen', false)
}

const formatLastPlayed = (guest) => {
    if (guest.days_since_last_play == null) return 'chưa rõ'
    if (guest.days_since_last_play === 0) return 'hôm nay'
    if (guest.days_since_last_play === 1) return '1 ngày trước'
    return `${guest.days_since_last_play} ngày trước`
}

const fetchGuests = async () => {
    if (!props.clubId) return
    isLoading.value = true
    try {
        const data = await getClubGuests(props.clubId)
        guests.value = data || { normal: [], potential: [], counts: { normal: 0, potential: 0, total: 0 } }
    } catch (e) {
        guests.value = { normal: [], potential: [], counts: { normal: 0, potential: 0, total: 0 } }
    } finally {
        isLoading.value = false
    }
}

const handleInvite = async (guest) => {
    if (guest.is_invited || invitingId.value) return
    invitingId.value = guest.user_id
    try {
        await inviteClubGuest(props.clubId, guest.user_id)
        guest.is_invited = true
        emit('refresh')
    } catch (e) {
        const msg = e?.response?.data?.message || e?.message || 'Lỗi khi mời khách'
        alert(msg)
    } finally {
        invitingId.value = null
    }
}

const handleDelete = async (guest) => {
    if (deletingId.value) return
    const name = guest.user?.full_name || 'khách này'
    if (!confirm(`Xoá ${name} khỏi danh sách khách?`)) return
    deletingId.value = guest.user_id
    try {
        await deleteClubGuest(props.clubId, guest.user_id)
        guests.value.normal = guests.value.normal.filter(g => g.user_id !== guest.user_id)
        guests.value.potential = guests.value.potential.filter(g => g.user_id !== guest.user_id)
        if (guests.value.counts) {
            guests.value.counts.total = Math.max(0, (guests.value.counts.total || 1) - 1)
        }
        emit('refresh')
    } catch (e) {
        const msg = e?.response?.data?.message || e?.message || 'Lỗi khi xoá khách'
        alert(msg)
    } finally {
        deletingId.value = null
    }
}

watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        activeTab.value = 'potential'
        fetchGuests()
    }
}, { immediate: false })
</script>

<style scoped>
::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #e5e7eb;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #d1d5db;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.scale-enter-active,
.scale-leave-active {
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.scale-enter-from,
.scale-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(20px);
}
</style>
