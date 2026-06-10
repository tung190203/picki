<template>
    <div v-if="modelValue" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-md p-6 shadow-xl">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <h3 class="font-bold text-lg text-gray-900">Sẵn sàng bắt đầu</h3>
                <button @click="$emit('update:modelValue', false)" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Format badge -->
            <div class="mb-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold"
                    :class="formatBadgeClass">
                    {{ formatLabel }}
                </span>
            </div>

            <!-- Summary stats -->
            <div class="grid grid-cols-3 gap-3 mb-5">
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ preview.total_matches || 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Trận đấu</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ preview.total_rounds || 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Vòng đấu</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ preview.matches_per_round || 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Trận/vòng</p>
                </div>
            </div>

            <!-- Group summary (mixed_gender / rank_pairing) -->
            <div v-if="preview.group_summary && (preview.group_summary.male !== undefined || preview.group_summary.a !== undefined)"
                class="mb-4 space-y-2">
                <div v-if="preview.group_summary.male !== undefined"
                    class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                        Nam
                    </span>
                    <span class="font-semibold text-gray-900">{{ preview.group_summary.male }} người</span>
                </div>
                <div v-if="preview.group_summary.female !== undefined"
                    class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-pink-500"></span>
                        Nữ
                    </span>
                    <span class="font-semibold text-gray-900">{{ preview.group_summary.female }} người</span>
                </div>
                <div v-if="preview.group_summary.a !== undefined"
                    class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-indigo-500"></span>
                        Hạng A
                    </span>
                    <span class="font-semibold text-gray-900">{{ preview.group_summary.a }} người</span>
                </div>
                <div v-if="preview.group_summary.b !== undefined"
                    class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-orange-500"></span>
                        Hạng B
                    </span>
                    <span class="font-semibold text-gray-900">{{ preview.group_summary.b }} người</span>
                </div>
            </div>

            <!-- Unbalanced warning -->
            <div v-if="preview.unbalanced_notice"
                class="mb-5 bg-amber-50 border border-amber-200 rounded-lg p-3">
                <p class="text-sm text-amber-800">
                    <span class="font-semibold">Lưu ý:</span> {{ preview.unbalanced_notice }}
                </p>
            </div>

            <!-- Court count selector -->
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">Số sân thi đấu</label>
                <div class="flex gap-2">
                    <button v-for="count in [1, 2, 3, 4]" :key="count"
                        @click="selectedCourtCount = count"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors border"
                        :class="selectedCourtCount === count
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white text-gray-700 border-gray-300 hover:border-blue-400'">
                        {{ count }} sân
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3">
                <button @click="$emit('update:modelValue', false)"
                    class="flex-1 py-2.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                    Hủy
                </button>
                <button @click="handleStart"
                    :disabled="isStarting"
                    class="flex-1 py-2.5 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700 disabled:opacity-60 disabled:cursor-not-allowed transition flex items-center justify-center gap-2">
                    <svg v-if="isStarting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    {{ isStarting ? 'Đang bắt đầu...' : 'Bắt đầu Session' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import { MATCH_FORMAT } from '@/constants/index.js';

export default {
    name: 'SessionReadyPreview',
    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        format: {
            type: String,
            default: '',
        },
        preview: {
            type: Object,
            default: () => ({}),
        },
    },
    emits: ['update:modelValue', 'start'],
    setup(props, { emit }) {
        const selectedCourtCount = ref(2);
        const isStarting = ref(false);

        const formatLabel = computed(() => {
            const labels = {
                [MATCH_FORMAT.PARTNER_ROTATION]: 'Xoay vòng partner',
                [MATCH_FORMAT.MIXED_GENDER]: 'Mix nam nữ',
                [MATCH_FORMAT.RANK_PAIRING]: 'Ghép hạng A/B',
            };
            return labels[props.format] || props.format;
        });

        const formatBadgeClass = computed(() => {
            const classes = {
                [MATCH_FORMAT.PARTNER_ROTATION]: 'bg-amber-100 text-amber-800',
                [MATCH_FORMAT.MIXED_GENDER]: 'bg-blue-100 text-blue-800',
                [MATCH_FORMAT.RANK_PAIRING]: 'bg-indigo-100 text-indigo-800',
            };
            return classes[props.format] || 'bg-gray-100 text-gray-800';
        });

        // Reset state when modal opens
        watch(() => props.modelValue, (val) => {
            if (val) {
                selectedCourtCount.value = 2;
                isStarting.value = false;
            }
        });

        const handleStart = async () => {
            isStarting.value = true;
            emit('start', selectedCourtCount.value);
        };

        return {
            selectedCourtCount,
            isStarting,
            formatLabel,
            formatBadgeClass,
            handleStart,
            MATCH_FORMAT,
        };
    },
};
</script>
