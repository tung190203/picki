<template>
    <div class="border rounded-lg p-4 transition-all"
        :class="roundBorderClass">
        <!-- Round header -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <p class="font-semibold text-sm" :class="roundTitleClass">
                    Vòng {{ round.round_number }}
                </p>
                <!-- Status badge -->
                <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold"
                    :class="statusBadgeClass">
                    {{ statusLabel }}
                </span>
            </div>
            <span class="text-xs text-gray-500">
                {{ round.completed_count }}/{{ round.total_count }} trận
            </span>
        </div>

        <!-- Progress bar -->
        <div class="w-full bg-gray-200 rounded-full h-1.5 mb-3">
            <div class="h-1.5 rounded-full transition-all duration-300"
                :class="progressBarClass"
                :style="{ width: progressPercent + '%' }">
            </div>
        </div>

        <!-- Matches list -->
        <div class="space-y-2">
            <div v-for="match in round.matches" :key="match.id"
                class="flex items-center justify-between bg-white rounded px-3 py-2 text-xs border"
                :class="match.is_bye ? 'border-gray-100 bg-gray-50' : 'border-gray-200'">
                <span class="font-medium text-[#3E414C]">
                    {{ match.team_1?.name || 'TBD' }} vs {{ match.team_2?.name || 'TBD' }}
                </span>
                <span v-if="match.is_bye" class="text-gray-400 italic">Bye</span>
                <span v-else-if="match.status === 'completed'"
                    :class="match.score_1 > match.score_2 ? 'text-green-600' : 'text-red-600'">
                    {{ match.score_1 }} - {{ match.score_2 }}
                </span>
                <span v-else-if="match.status === 'disputed'" class="text-amber-500">Tranh chấp</span>
                <span v-else class="text-gray-400">Chờ đấu</span>
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

        const roundBorderClass = computed(() => {
            if (status.value === 'active') {
                return 'border-blue-400 bg-blue-50/30';
            }
            if (status.value === 'done') {
                return 'border-gray-200 bg-gray-50 opacity-75';
            }
            return 'border-gray-200 bg-gray-50 opacity-60';
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

        return {
            status,
            statusLabel,
            statusBadgeClass,
            roundBorderClass,
            roundTitleClass,
            progressBarClass,
            progressPercent,
        };
    },
};
</script>
