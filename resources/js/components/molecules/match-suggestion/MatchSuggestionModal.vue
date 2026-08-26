<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="close">
                <div class="bg-white dark:bg-[#161F33] rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden mx-4">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Gợi ý trận đấu tiếp theo
                        </h2>
                        <div class="flex items-center gap-3">
                            <button @click="openSettings" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </button>
                            <button @click="close" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
                        <!-- Loading State -->
                        <div v-if="isLoading" class="flex flex-col items-center justify-center py-12">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
                            <p class="text-gray-500">Đang tạo gợi ý...</p>
                        </div>

                        <!-- Error State -->
                        <div v-else-if="error" class="text-center py-12">
                            <p class="text-red-500 mb-4">{{ error }}</p>
                            <button @click="regenerate" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Thử lại
                            </button>
                        </div>

                        <!-- Suggestion Result -->
                        <div v-else-if="currentSuggestion" class="space-y-6">
                            <!-- Suggested Match Card -->
                            <SuggestedMatchCard 
                                :match="currentSuggestion.match" 
                                :court-number="courtNumber"
                            />

                            <!-- Statistics -->
                            <div v-if="currentSuggestion.statistics" class="grid grid-cols-3 gap-4 text-center">
                                <div class="p-3 bg-gray-50 dark:bg-slate-800 rounded-lg">
                                    <div class="text-2xl font-bold text-blue-600">
                                        {{ currentSuggestion.statistics.team1_strength || 0 }}
                                    </div>
                                    <div class="text-xs text-gray-500">Sức mạnh Đội 1</div>
                                </div>
                                <div class="p-3 bg-gray-50 dark:bg-slate-800 rounded-lg">
                                    <div class="text-2xl font-bold text-green-600">
                                        {{ currentSuggestion.statistics.balance_score || 0 }}
                                    </div>
                                    <div class="text-xs text-gray-500">Điểm cân bằng</div>
                                </div>
                                <div class="p-3 bg-gray-50 dark:bg-slate-800 rounded-lg">
                                    <div class="text-2xl font-bold text-purple-600">
                                        {{ currentSuggestion.statistics.team2_strength || 0 }}
                                    </div>
                                    <div class="text-xs text-gray-500">Sức mạnh Đội 2</div>
                                </div>
                            </div>

                            <!-- Participants List with Skip Toggle -->
                            <ParticipantsList
                                v-model:participants="localParticipants"
                                :collapsed="isParticipantsCollapsed"
                                @toggle-collapse="isParticipantsCollapsed = !isParticipantsCollapsed"
                            />

                            <!-- Messages -->
                            <div v-if="currentSuggestion.messages?.length" class="text-sm text-gray-600 dark:text-gray-400">
                                <div v-for="msg in currentSuggestion.messages" :key="msg" class="flex items-center gap-2">
                                    <span class="w-2 h-2 bg-yellow-500 rounded-full"></span>
                                    {{ msg }}
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="text-center py-12">
                            <p class="text-gray-500">Không có gợi ý nào</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800">
                        <button @click="regenerate" :disabled="isLoading" class="px-4 py-2 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-700 rounded-lg disabled:opacity-50">
                            Thêm gợi ý khác
                        </button>
                        <div class="flex gap-3">
                            <button @click="close" class="px-6 py-2 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700">
                                Đóng
                            </button>
                            <button @click="accept" :disabled="!currentSuggestion || isLoading" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                Đồng ý tạo trận
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Settings Modal -->
    <MatchSuggestionSettingsModal
        v-model="showSettings"
        :settings="settings"
        @update:settings="updateSettings"
    />
</template>

<script>
import { ref, watch, computed } from 'vue';
import SuggestedMatchCard from './SuggestedMatchCard.vue';
import ParticipantsList from './ParticipantsList.vue';
import MatchSuggestionSettingsModal from './MatchSuggestionSettingsModal.vue';
import * as MatchSuggestionService from '@/service/matchSuggestion.js';

export default {
    name: 'MatchSuggestionModal',
    components: {
        SuggestedMatchCard,
        ParticipantsList,
        MatchSuggestionSettingsModal,
    },
    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        miniTournamentId: {
            type: [Number, String],
            required: true,
        },
        participants: {
            type: Array,
            default: () => [],
        },
        courtNumber: {
            type: Number,
            default: null,
        },
    },
    emits: ['update:modelValue', 'match-accepted'],
    setup(props, { emit }) {
        const isLoading = ref(false);
        const error = ref(null);
        const currentSuggestion = ref(null);
        const showSettings = ref(false);
        const isParticipantsCollapsed = ref(true);
        
        const settings = ref({
            fair_play: true,
            balance_team: true,
            prefer_high_tier_match: true,
            prevent_three_consecutive: true,
            organizer_as_backup: false,
        });

        // Local participants with skip, tier, and display_gender state
        const localParticipants = ref([]);

        // Sync participants from props to local state with tier and gender
        watch(() => props.participants, (newParticipants) => {
            localParticipants.value = (newParticipants || []).map(p => {
                // Calculate tier from vn_dupr score (not from modify_tier)
                let tier = 'green';
                const score = p.user?.sports?.[0]?.scores?.vndupr_score 
                    || p.user?.sports?.[0]?.scores?.personal_score 
                    || 0;
                const numScore = parseFloat(score);
                if (numScore >= 3.5) tier = 'purple';
                else if (numScore >= 2.5) tier = 'red';
                else if (numScore >= 1.5) tier = 'yellow';
                else tier = 'green';

                // Gender from user (not modify_gender)
                const display_gender = p.user?.gender || p.gender || 'male';

                return {
                    ...p,
                    skip: p.skip || false,
                    tier,
                    display_gender,
                };
            });
        }, { immediate: true, deep: true });

        const close = () => {
            emit('update:modelValue', false);
        };

        const openSettings = () => {
            showSettings.value = true;
        };

        const updateSettings = (newSettings) => {
            settings.value = { ...newSettings };
        };

        const generate = async () => {
            isLoading.value = true;
            error.value = null;

            try {
                // Get active participants (not skipped) with their current modal state
                const activeParticipants = localParticipants.value.filter(p => !p.skip);
                
                // Transform participants to API format: { mini_participant_id, tier }
                // Gender is read from user table on backend
                const apiParticipants = activeParticipants.map(p => ({
                    mini_participant_id: p.id || p.mini_participant_id,
                    tier: p.tier || 'green',
                }));

                if (apiParticipants.length < 4) {
                    error.value = 'Cần ít nhất 4 người chơi để gợi ý trận đấu';
                    isLoading.value = false;
                    return;
                }

                const response = await MatchSuggestionService.getSuggestions(props.miniTournamentId, {
                    participants: apiParticipants,
                    settings: settings.value,
                });

                // Laravel Resource wraps data in response.data.data
                const rawData = response?.data || response;
                const suggestionData = rawData?.data || rawData;

                if (!suggestionData?.match) {
                    error.value = response?.message || suggestionData?.error_message || 'Không có gợi ý nào';
                }

                currentSuggestion.value = suggestionData;
            } catch (err) {
                const errorMsg = err.response?.data?.message
                    || err.response?.data?.errors
                    || err.message
                    || 'Không thể tạo gợi ý';
                error.value = typeof errorMsg === 'object' ? Object.values(errorMsg).flat().join(', ') : errorMsg;
            } finally {
                isLoading.value = false;
            }
        };

        const regenerate = async () => {
            await generate();
        };

        const accept = () => {
            if (currentSuggestion.value) {
                emit('match-accepted', {
                    suggestion: currentSuggestion.value,
                    participants: localParticipants.value,
                    settings: settings.value,
                });
                close();
            }
        };

        // Auto-generate when modal opens
        watch(() => props.modelValue, (newVal) => {
            if (newVal) {
                // Reset collapse state
                isParticipantsCollapsed.value = true;
                generate();
            }
        });

        return {
            isLoading,
            error,
            currentSuggestion,
            settings,
            showSettings,
            isParticipantsCollapsed,
            localParticipants,
            close,
            openSettings,
            updateSettings,
            regenerate,
            accept,
        };
    },
};
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .bg-white,
.modal-leave-active .bg-white {
    transition: transform 0.3s ease;
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white {
    transform: scale(0.95);
}
</style>
