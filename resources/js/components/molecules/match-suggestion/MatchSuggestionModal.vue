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
                            <!-- Pairing Status Banner -->
                            <div v-if="selectedForPairing" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-yellow-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                    </svg>
                                    <span class="text-sm text-yellow-700">Đang chọn cặp cho <strong>{{ getParticipantName(selectedForPairing) }}</strong>...</span>
                                </div>
                                <button @click="cancelPairingSelection" class="text-yellow-500 hover:text-yellow-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

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

                            <!-- Participants List with Skip Toggle and Pairing -->
                            <ParticipantsList
                                v-model:participants="localParticipants"
                                :collapsed="isParticipantsCollapsed"
                                :player-pairs="playerPairs"
                                :selected-for-pairing="selectedForPairing"
                                @toggle-collapse="isParticipantsCollapsed = !isParticipantsCollapsed"
                                @pair-toggle="handlePairToggle"
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

        // Fixed pairing state
        const playerPairs = ref([]);
        const selectedForPairing = ref(null);
        const PAIR_COLORS = ['cyan', 'orange', 'teal', 'purple', 'pink', 'amber'];

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

        // Load player pairs when modal opens
        watch(() => props.modelValue, async (newVal) => {
            if (newVal) {
                // Reset collapse state
                isParticipantsCollapsed.value = true;
                await loadPlayerPairs();
                generate();
            }
        });

        // Load player pairs from server
        const loadPlayerPairs = async () => {
            try {
                const response = await MatchSuggestionService.getPlayerPairs(props.miniTournamentId);
                const data = response?.data || response;
                playerPairs.value = data?.data || data || [];
            } catch (err) {
                console.error('Failed to load player pairs:', err);
                playerPairs.value = [];
            }
        };

        // Get participant name by ID
        const getParticipantName = (participantId) => {
            const p = localParticipants.value.find(p => p.id === participantId || p.mini_participant_id === participantId);
            return p?.user?.full_name || p?.guest_name || 'Người chơi';
        };

        // Handle pair toggle from ParticipantsList
        const handlePairToggle = async (participant) => {
            const participantId = participant.id || participant.mini_participant_id;
            const isGuest = participant.is_guest || false;

            // Check if participant is already paired
            const existingPair = findPairForParticipant(participantId, isGuest);

            if (existingPair) {
                // Already paired - click to unpair
                await unpairParticipant(existingPair, participantId);
            } else if (selectedForPairing.value === participantId) {
                // Currently waiting for pairing - cancel selection
                cancelPairingSelection();
            } else if (selectedForPairing.value !== null) {
                // Has someone waiting - create pair with them
                const waitingId = selectedForPairing.value;
                const waitingParticipant = localParticipants.value.find(p => 
                    (p.id || p.mini_participant_id) === waitingId
                );
                const waitingIsGuest = waitingParticipant?.is_guest || false;

                await createPair(waitingId, participantId, waitingIsGuest, isGuest);
                selectedForPairing.value = null;
            } else {
                // No one waiting - select this participant
                selectedForPairing.value = participantId;
            }
        };

        // Find pair for a participant
        const findPairForParticipant = (participantId, isGuest) => {
            return playerPairs.value.find(pair => {
                const player1Match = String(pair.player1_id) === String(participantId) && pair.player1_is_guest === isGuest;
                const player2Match = String(pair.player2_id) === String(participantId) && pair.player2_is_guest === isGuest;
                return player1Match || player2Match;
            });
        };

        // Create a new pair
        const createPair = async (player1Id, player2Id, player1IsGuest, player2IsGuest) => {
            try {
                await MatchSuggestionService.createPlayerPair(
                    props.miniTournamentId,
                    player1Id,
                    player2Id,
                    player1IsGuest,
                    player2IsGuest
                );
                await loadPlayerPairs();
            } catch (err) {
                console.error('Failed to create pair:', err);
                // Could show toast error here
            }
        };

        // Unpair a participant (delete their pair)
        const unpairParticipant = async (pair, participantId) => {
            try {
                await MatchSuggestionService.deletePlayerPair(props.miniTournamentId, pair.id);
                await loadPlayerPairs();
            } catch (err) {
                console.error('Failed to unpair:', err);
            }
        };

        // Cancel pairing selection
        const cancelPairingSelection = () => {
            selectedForPairing.value = null;
        };

        // Get pair color for a participant
        const getPairColor = (participantId, isGuest = false) => {
            const pair = findPairForParticipant(participantId, isGuest);
            return pair?.pair_color || null;
        };

        // Get pairing partner name
        const getPairPartnerName = (participantId, isGuest = false) => {
            const pair = findPairForParticipant(participantId, isGuest);
            if (!pair) return null;

            const partnerId = String(pair.player1_id) === String(participantId) && pair.player1_is_guest === isGuest
                ? pair.player2_id
                : pair.player1_id;
            const partnerIsGuest = String(pair.player1_id) === String(participantId) && pair.player1_is_guest === isGuest
                ? pair.player2_is_guest
                : pair.player1_is_guest;

            return getParticipantName(partnerId);
        };

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
                const apiParticipants = buildApiParticipants();

                if (apiParticipants.length < 4) {
                    error.value = 'Cần ít nhất 4 người chơi để gợi ý trận đấu';
                    isLoading.value = false;
                    return;
                }

                const response = await MatchSuggestionService.getSuggestions(props.miniTournamentId, {
                    participants: apiParticipants,
                    settings: settings.value,
                    fixed_pairs: buildFixedPairs(),
                });

                const suggestionData = extractSuggestionData(response);
                if (!suggestionData?.match) {
                    error.value = response?.message || suggestionData?.error_message || 'Không có gợi ý nào';
                }

                currentSuggestion.value = suggestionData;
            } catch (err) {
                handleGenerateError(err);
            } finally {
                isLoading.value = false;
            }
        };

        const regenerate = async () => {
            isLoading.value = true;
            error.value = null;

            try {
                const apiParticipants = buildApiParticipants();

                if (apiParticipants.length < 4) {
                    error.value = 'Cần ít nhất 4 người chơi để gợi ý trận đấu';
                    isLoading.value = false;
                    return;
                }

                const response = await MatchSuggestionService.regenerateMatchSuggestion(
                    props.miniTournamentId,
                    {
                        participants: apiParticipants,
                        settings: settings.value,
                        fixed_pairs: buildFixedPairs(),
                        previous_suggestion: currentSuggestion.value || null,
                    }
                );

                const suggestionData = extractSuggestionData(response);
                if (!suggestionData?.match) {
                    error.value = response?.message || suggestionData?.error_message || 'Không có gợi ý nào';
                }

                currentSuggestion.value = suggestionData;
            } catch (err) {
                handleGenerateError(err);
            } finally {
                isLoading.value = false;
            }
        };

        const buildApiParticipants = () => {
            const activeParticipants = localParticipants.value.filter(p => !p.skip);
            return activeParticipants.map(p => ({
                mini_participant_id: p.id || p.mini_participant_id,
                tier: p.tier || 'green',
            }));
        };

        const buildFixedPairs = () => {
            return playerPairs.value.map(pair => ({
                player1_id: pair.player1_id,
                player2_id: pair.player2_id,
                player1_is_guest: pair.player1_is_guest,
                player2_is_guest: pair.player2_is_guest,
            }));
        };

        const extractSuggestionData = (response) => {
            const rawData = response?.data || response;
            return rawData?.data || rawData;
        };

        const handleGenerateError = (err) => {
            const errorMsg = err.response?.data?.message
                || err.response?.data?.errors
                || err.message
                || 'Không thể tạo gợi ý';
            error.value = typeof errorMsg === 'object' ? Object.values(errorMsg).flat().join(', ') : errorMsg;
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

        return {
            isLoading,
            error,
            currentSuggestion,
            settings,
            showSettings,
            isParticipantsCollapsed,
            localParticipants,
            playerPairs,
            selectedForPairing,
            PAIR_COLORS,
            getParticipantName,
            handlePairToggle,
            getPairColor,
            getPairPartnerName,
            cancelPairingSelection,
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
