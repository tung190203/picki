<template>
    <div v-if="isOpen" class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
        <!-- Backdrop with blur -->
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="$emit('close')"></div>

        <div
            class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl relative z-[10000] overflow-hidden animate-in fade-in zoom-in duration-300 h-[calc(100vh-7rem)] flex flex-col">

            <!-- Header -->
            <div class="p-6 pb-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-[28px] font-bold text-[#3E414C]">Lịch đấu</h3>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <XMarkIcon class="w-8 h-8" stroke-width="2.5" />
                    </button>
                </div>
            </div>

            <!-- Featured Card (Dynamic) -->
            <div class="px-6 pb-4" v-if="nextMatch">
                <div class="relative w-full rounded-2xl overflow-hidden shadow-md cursor-pointer hover:opacity-95 transition-opacity" @click="$emit('click-card', nextMatch, 'next')">
                    <div class="absolute inset-0 bg-[#D72D36]" :style="{ backgroundImage: `url(${thumbnail})` }">
                    </div>

                    <div class="relative p-6 text-white">
                        <div class="flex justify-between items-start mb-4">
                            <span
                                class="bg-white/20 backdrop-blur-sm px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide border border-white/30">Trận
                                tiếp theo</span>
                            <div v-if="countdown" class="flex items-center gap-1 text-sm font-medium">
                                <ClockIcon class="w-4 h-4" />
                                <span>{{ countdown === 'Đang diễn ra' ? countdown : 'Bắt đầu sau: ' + countdown }}</span>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h2 class="text-xl font-semibold mb-1">{{ nextMatch.title }}</h2>
                            <div class="flex items-center gap-2 opacity-90" v-if="nextMatch.address">
                                <MapPinIcon class="w-4 h-4" />
                                <span class="text-sm font-normal">{{ nextMatch.address }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex -space-x-3">
                                <template v-for="(participant, pIdx) in nextMatch.participants_list?.slice(0, 3)" :key="pIdx">
                                    <div
                                        class="w-10 h-10 rounded-full border-2 border-[#D72D36] bg-gray-200 flex items-center justify-center text-gray-500 text-xs font-bold overflow-hidden">
                                        <img :src="participant.user.avatar_url || 'https://i.pravatar.cc/150'" alt="avatar"
                                            class="w-full h-full object-cover">
                                    </div>
                                </template>
                                <div v-if="nextMatch.participants_count > 3"
                                    class="w-10 h-10 rounded-full border-2 border-[#D72D36] bg-white flex items-center justify-center text-[#D72D36] text-xs font-bold">
                                    +{{ nextMatch.participants_count - 3 }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="nextMatch.isCreator"
                                    class="bg-white text-[#D72D36] p-2 rounded-lg font-semibold shadow-sm transition-colors hover:bg-gray-50 flex items-center justify-center h-10 w-10"
                                    @click.stop="$emit('edit', nextMatch)"
                                >
                                    <PencilIcon class="w-5 h-5" />
                                </button>
                                
                                <!-- New Split Button Logic -->
                                <template v-if="nextMatch.registrationStatus === 'pending'">
                                    <button
                                        class="bg-white text-[#D72D36] text-sm hover:bg-gray-50 px-6 py-2.5 rounded-lg font-semibold shadow-sm transition-colors h-10 flex items-center justify-center"
                                        @click.stop="$emit('cancel-join', nextMatch)"
                                    >
                                        Hủy tham gia
                                    </button>
                                    <button
                                        class="bg-white text-[#D72D36] text-sm px-6 py-2.5 rounded-lg font-semibold shadow-sm h-10 flex items-center justify-center opacity-70 cursor-not-allowed"
                                        disabled
                                    >
                                        <div class="flex items-center gap-2">
                                            <ClockIcon class="w-4 h-4" />
                                            <span>{{ nextMatch.buttonText }}</span>
                                        </div>
                                    </button>
                                </template>
                                <template v-else-if="nextMatch.registrationStatus === 'accepted'">
                                    <button
                                        class="bg-white text-[#D72D36] text-sm hover:bg-gray-50 px-6 py-2.5 rounded-lg font-semibold shadow-sm transition-colors h-10 flex items-center justify-center"
                                        @click.stop="$emit('self-absent', nextMatch)"
                                    >
                                        Báo vắng
                                    </button>
                                    <button
                                        class="bg-white text-[#D72D36] text-sm hover:bg-gray-50 px-6 py-2.5 rounded-lg font-semibold shadow-sm transition-colors h-10 flex items-center justify-center"
                                        @click.stop="$emit('check-in', nextMatch)"
                                    >
                                        Check-in
                                    </button>
                                </template>
                                <button
                                    v-else
                                    class="bg-white text-[#D72D36] text-sm hover:bg-gray-50 px-6 py-2.5 rounded-lg font-semibold shadow-sm transition-colors h-10 flex items-center justify-center"
                                    :class="{ 'cursor-not-allowed': nextMatch.registrationStatus === 'attended' || nextMatch.status === 'completed' }"
                                    :disabled="nextMatch.registrationStatus === 'attended' || nextMatch.status === 'completed'"
                                    @click.stop="$emit('register', nextMatch)"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>{{ nextMatch.registrationStatus === 'attended' ? 'Đã check in' : nextMatch.buttonText }}</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Container -->
            <div class="px-6 pb-6 flex-1 min-h-0">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 h-full">
                    <!-- Upcoming Activities -->
                    <div class="flex flex-col h-full overflow-hidden">
                        <h4 class="text-sm font-bold text-[#838799] uppercase tracking-wider mb-4 flex-shrink-0">Đang diễn ra</h4>
                        <div class="space-y-4 flex-1 overflow-y-auto custom-scrollbar pr-2" @scroll="handleUpcomingScroll">
                            <ActivitySmallCard v-for="(activity, index) in upcomingActivities" :key="index"
                                v-bind="activity" @edit="$emit('edit', activity)" @click-card="$emit('click-card', activity)" 
                                @register="$emit('register', activity)" @cancel-join="$emit('cancel-join', activity)" @self-absent="$emit('self-absent', activity)" @check-in="$emit('check-in', activity)" />
                                
                            <!-- Loading Indicator -->
                            <div v-if="isLoadingUpcoming" class="flex justify-center py-4">
                                <div class="w-6 h-6 border-2 border-[#D72D36] border-t-transparent rounded-full animate-spin"></div>
                            </div>

                            <!-- Additional mocked items to match image if needed -->
                            <div v-if="upcomingActivities.length === 0 && !isLoadingUpcoming"
                                class="flex flex-col items-center justify-center py-8 text-[#838799]">
                                <CalendarIcon class="w-10 h-10 mb-2 opacity-50" />
                                <span>Chưa có lịch thi đấu sắp tới</span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent History -->
                    <div class="flex flex-col h-full overflow-hidden">
                        <h4 class="text-sm font-bold text-[#838799] uppercase tracking-wider mb-4 flex-shrink-0">Đã kết thúc</h4>
                        <div class="space-y-4 flex-1 overflow-y-auto custom-scrollbar pr-2" @scroll="handleHistoryScroll">
                            <div v-for="(history, index) in historyActivities" :key="index"
                                class="flex items-start justify-between p-4 bg-white rounded-lg shadow-sm border border-[#E5E7EB] transition hover:border-gray-300 gap-3 cursor-pointer" @click="$emit('click-card', history)">
                                <div class="flex items-center space-x-4 min-w-0">
                                    <!-- Date Section -->
                                    <div
                                        class="flex flex-col items-center justify-center w-16 h-16 rounded-md border bg-gray-50 border-[#E5E7EB] text-[#838799] flex-shrink-0">
                                        <span class="text-xs font-bold uppercase">{{ history.day }}</span>
                                        <span class="text-2xl font-bold">{{ history.date }}</span>
                                    </div>

                                    <!-- Info Section -->
                                    <div class="flex flex-col space-y-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-lg font-semibold text-[#3E414C] truncate">{{ history.title }}
                                            </h3>
                                        </div>

                                        <div class="flex items-center space-x-4 text-sm text-[#838799]">
                                            <div class="flex items-center space-x-1">
                                                <ClockIcon class="w-4 h-4 flex-shrink-0" />
                                                <span>{{ history.time }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-start space-x-1 text-sm text-[#838799] min-w-0">
                                            <MapPinIcon class="w-4 h-4 flex-shrink-0 mt-0.5" />
                                            <span class="truncate" :title="history.address">{{ history.address }}</span>
                                        </div>
                                    </div>
                                </div>
                                <span 
                                    class="text-[10px] font-bold px-2 py-0.5 rounded uppercase whitespace-nowrap h-fit"
                                    :class="{
                                        'bg-[#E3F7EF] text-[#2D9B71]': history.status === 'open',
                                        'bg-[#F2F7FC] text-[#4392E0]': history.status === 'private',
                                        'bg-[#FBEAEA] text-[#D72D36]': history.status === 'cancelled',
                                        'bg-[#EDEEF2] text-[#838799]': history.status === 'completed' || !['open', 'private', 'cancelled', 'completed'].includes(history.status),
                                    }"
                                >
                                    {{ history.statusText }}
                                </span>
                            </div>

                            <!-- Loading Indicator -->
                            <div v-if="isLoadingHistory" class="flex justify-center py-4">
                                <div class="w-6 h-6 border-2 border-[#D72D36] border-t-transparent rounded-full animate-spin"></div>
                            </div>

                            <div v-if="historyActivities.length === 0 && !isLoadingHistory"
                                class="flex flex-col items-center justify-center py-8 text-[#838799]">
                                <ClockIcon class="w-10 h-10 mb-2 opacity-50" />
                                <span>Chưa có lịch sử thi đấu</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import {
    XMarkIcon,
    ClockIcon,
    MapPinIcon,
    CalendarIcon,
    CalendarDaysIcon,
    PencilIcon,
} from '@heroicons/vue/24/outline'
import ActivitySmallCard from '@/components/molecules/ActivitySmallCard.vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    thumbnail: {
        type: String,
        default: ''
    },
    upcomingActivities: {
        type: Array,
        default: () => []
    },
    historyActivities: {
        type: Array,
        default: () => []
    },
    nextMatch: {
        type: Object,
        default: null
    },
    countdown: {
        type: String,
        default: ''
    },
    isLoadingUpcoming: {
        type: Boolean,
        default: false
    },
    isLoadingHistory: {
        type: Boolean,
        default: false
    },
    hasMoreUpcoming: {
        type: Boolean,
        default: true
    },
    hasMoreHistory: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['close', 'edit', 'click-card', 'register', 'cancel-join', 'self-absent', 'check-in', 'load-more-upcoming', 'load-more-history'])

const handleUpcomingScroll = (e) => {
    if (props.isLoadingUpcoming || !props.hasMoreUpcoming) return
    const { scrollTop, scrollHeight, clientHeight } = e.target
    if (scrollTop + clientHeight >= scrollHeight - 20) {
        emit('load-more-upcoming')
    }
}

const handleHistoryScroll = (e) => {
    if (props.isLoadingHistory || !props.hasMoreHistory) return
    const { scrollTop, scrollHeight, clientHeight } = e.target
    if (scrollTop + clientHeight >= scrollHeight - 20) {
        emit('load-more-history')
    }
}
</script>
