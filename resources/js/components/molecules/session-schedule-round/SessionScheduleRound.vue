<template>
    <div class="space-y-3">
        <!-- Matches list -->
        <div class="space-y-2">
            <div v-for="match in round.matches" :key="match.id"
                class="bg-white rounded-lg px-4 py-3 text-xs border border-gray-200"
                :class="{ 'opacity-50 bg-gray-50': match.status === 'pending' || match.is_bye }">
                <!-- Team row -->
                <div class="flex items-center justify-between gap-2 mb-1">
                    <!-- Team 1 -->
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-red-50 flex items-center justify-center shrink-0 overflow-hidden">
                            <img v-if="match.team1?.avatar_url" :src="match.team1.avatar_url" class="w-full h-full object-cover" alt="">
                            <span v-else class="text-xs font-semibold text-red-500">
                                {{ (match.team1?.full_name || 'TBD').charAt(0).toUpperCase() }}
                            </span>
                        </div>
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="text-sm font-medium text-[#3E414C] truncate"
                                :class="{ 'text-gray-400': match.is_bye }">
                                {{ match.team1?.full_name || 'TBD' }}
                            </span>
                            <span v-if="match.team1?.user?.sports?.length" class="text-[10px] text-gray-400">
                                VN DUP: {{ getVnduprScore(match.team1) }}
                            </span>
                        </div>
                    </div>

                    <!-- Score / VS -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span v-if="match.is_bye" class="text-[10px] text-gray-400 px-2">Bye</span>
                        <template v-else-if="match.status === 'completed'">
                            <span class="text-sm font-bold" :class="match.score_1 > match.score_2 ? 'text-green-600' : 'text-gray-500'">{{ match.score_1 ?? 0 }}</span>
                            <span class="text-gray-300">-</span>
                            <span class="text-sm font-bold" :class="match.score_2 > match.score_1 ? 'text-green-600' : 'text-gray-500'">{{ match.score_2 ?? 0 }}</span>
                        </template>
                        <template v-else-if="match.status === 'disputed'">
                            <span class="text-[10px] px-2 py-0.5 bg-amber-100 text-amber-600 rounded-full">Tranh chấp</span>
                        </template>
                        <template v-else>
                            <span class="text-[10px] text-gray-400 px-2">vs</span>
                        </template>
                    </div>

                    <!-- Team 2 -->
                    <div class="flex items-center gap-2 flex-1 min-w-0 justify-end">
                        <div class="flex flex-col min-w-0 flex-1 items-end">
                            <span class="text-sm font-medium text-[#3E414C] truncate text-right"
                                :class="{ 'text-gray-400': match.is_bye }">
                                {{ match.team2?.full_name || 'TBD' }}
                            </span>
                            <span v-if="match.team2?.user?.sports?.length" class="text-[10px] text-gray-400">
                                VN DUP: {{ getVnduprScore(match.team2) }}
                            </span>
                        </div>
                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center shrink-0 overflow-hidden">
                            <img v-if="match.team2?.avatar_url" :src="match.team2.avatar_url" class="w-full h-full object-cover" alt="">
                            <span v-else class="text-xs font-semibold text-blue-500">
                                {{ (match.team2?.full_name || 'TBD').charAt(0).toUpperCase() }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Court + time + status -->
                <div class="flex items-center justify-between mt-1.5 pt-1.5 border-t border-gray-100" v-if="!match.is_bye">
                    <div class="flex items-center gap-3">
                        <span v-if="match.yard_number" class="text-[10px] text-gray-400">
                            Sân {{ match.yard_number }}
                        </span>
                        <span v-if="match.scheduled_at" class="text-[10px] text-gray-400">
                            {{ formatTime(match.scheduled_at) }}
                        </span>
                    </div>
                    <span v-if="match.status && match.status !== 'completed' && match.status !== 'disputed'"
                        class="text-[10px] px-2 py-0.5 rounded-full font-medium"
                        :class="matchStatusBadge(match.status)">
                        {{ matchStatusLabel(match.status) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { computed } from 'vue';

export default {
    name: 'SessionScheduleRound',
    props: {
        round: {
            type: Object,
            required: true,
        },
    },
    setup(props) {
        const status = computed(() => props.round.status || 'upcoming');

        const statusLabel = computed(() => {
            const labels = {
                done: 'Hoàn thành',
                active: 'Đang diễn ra',
                upcoming: 'Sắp tới',
            };
            return labels[status.value] || status.value;
        });

        const statusBadgeClass = computed(() => {
            const classes = {
                done: 'bg-green-100 text-green-700',
                active: 'bg-blue-100 text-blue-700',
                upcoming: 'bg-gray-100 text-gray-500',
            };
            return classes[status.value] || classes.upcoming;
        });

        const roundTitleClass = computed(() => {
            if (status.value === 'active') return 'text-blue-700';
            if (status.value === 'done') return 'text-gray-600';
            return 'text-gray-500';
        });

        const progressBarClass = computed(() => {
            if (status.value === 'done') return 'bg-green-500';
            if (status.value === 'active') return 'bg-blue-500';
            return 'bg-gray-400';
        });

        const progressPercent = computed(() => {
            if (!props.round.total_count) return 0;
            return Math.round((props.round.completed_count / props.round.total_count) * 100);
        });

        const formatTime = (dateString) => {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
        };

        const getVnduprScore = (team) => {
            if (!team?.user?.sports?.length) return '-';
            const sport = team.user.sports[0];
            if (!sport?.scores) return '-';
            const score = sport.scores.vndupr_score;
            return score ?? '-';
        };

        const matchStatusLabel = (status) => {
            const labels = {
                pending: 'Chờ',
                going_on: 'Đang đấu',
                waiting_confirm: 'Chờ xác nhận',
                completed: 'Xong',
            };
            return labels[status] || status;
        };

        const matchStatusBadge = (status) => {
            const classes = {
                pending: 'bg-gray-100 text-gray-500',
                going_on: 'bg-blue-100 text-blue-600',
                waiting_confirm: 'bg-yellow-100 text-yellow-600',
                completed: 'bg-green-100 text-green-600',
            };
            return classes[status] || 'bg-gray-100 text-gray-500';
        };

        return {
            status,
            statusLabel,
            statusBadgeClass,
            roundTitleClass,
            progressBarClass,
            progressPercent,
            formatTime,
            getVnduprScore,
            matchStatusLabel,
            matchStatusBadge,
        };
    },
};
</script>
