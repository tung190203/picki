<template>
    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Danh sách người chơi</h4>
        
        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-slate-700">
                        <th class="text-left py-2 px-2 font-semibold text-gray-600 dark:text-gray-300">TÊN</th>
                        <th class="text-center py-2 px-2 font-semibold text-gray-600 dark:text-gray-300">GT</th>
                        <th class="text-center py-2 px-2 font-semibold text-gray-600 dark:text-gray-300">SỐ TRẬN</th>
                        <th class="text-center py-2 px-2 font-semibold text-gray-600 dark:text-gray-300">MÀU</th>
                        <th class="text-center py-2 px-2 font-semibold text-gray-600 dark:text-gray-300">CẶP</th>
                        <th class="text-center py-2 px-2 font-semibold text-gray-600 dark:text-gray-300">BỎ VÒNG</th>
                    </tr>
                </thead>
                <tbody>
                    <tr 
                        v-for="participant in displayedParticipants" 
                        :key="participant.id"
                        class="border-b border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50"
                    >
                        <td class="py-3 px-2">
                            <div class="flex items-center gap-2">
                                <img 
                                    :src="participant.user?.avatar_url || participant.guest_avatar || '/default-avatar.png'"
                                    :alt="participant.user?.full_name || participant.guest_name"
                                    class="w-8 h-8 rounded-full object-cover border-2"
                                    :class="getAvatarBorderClass(participant.tier)"
                                />
                                <div class="flex flex-col">
                                    <span class="text-gray-800 dark:text-gray-200">
                                        {{ participant.user?.full_name || participant.guest_name || 'Khách' }}
                                    </span>
                                    <!-- Paired chip -->
                                    <span v-if="getPairingStatus(participant) === 'paired'" 
                                          class="inline-flex items-center mt-0.5 px-1.5 py-0.5 text-[10px] rounded font-medium w-fit"
                                          :class="getPairChipClass(participant)">
                                        {{ getPairPartnerName(participant) }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <span class="text-gray-600 dark:text-gray-400">
                                {{ getGenderIcon(participant) }}
                            </span>
                        </td>
                        <td class="py-3 px-2 text-center text-gray-600 dark:text-gray-400">
                            {{ participant.played_matches || participant.played_matches_count || 0 }}
                        </td>
                        <td class="py-3 px-2 text-center">
                            <div class="flex gap-1 justify-center">
                                <button
                                    v-for="color in ['green', 'yellow', 'red', 'purple']"
                                    :key="color"
                                    @click="updateTier(participant, color)"
                                    class="w-6 h-6 rounded-full border-2 transition-all hover:scale-110"
                                    :class="[
                                        getColorClass(color),
                                        participant.tier === color ? 'ring-2 ring-offset-1 ring-gray-500 dark:ring-gray-300' : 'opacity-40'
                                    ]"
                                    :title="getTierLabel(color)"
                                ></button>
                            </div>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <!-- Pairing button -->
                            <button
                                @click="$emit('pair-toggle', participant)"
                                class="w-7 h-7 rounded border-2 transition-all flex items-center justify-center mx-auto"
                                :class="getPairingButtonClass(participant)"
                                title="Ghép cặp cố định"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                </svg>
                            </button>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <button
                                @click="toggleSkip(participant)"
                                class="w-6 h-6 rounded border-2 transition-all flex items-center justify-center"
                                :class="participant.skip 
                                    ? 'bg-red-500 border-red-500 text-white' 
                                    : 'border-gray-300 dark:border-slate-600 text-transparent'"
                            >
                                <svg v-if="participant.skip" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Collapse/Expand Button -->
        <div v-if="participants.length > 5" class="mt-4 text-center">
            <button 
                @click="$emit('toggle-collapse')"
                class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium"
            >
                {{ collapsed ? `Mở rộng ▾` : `Thu gọn ▴` }}
            </button>
        </div>
    </div>
</template>

<script>
import { computed } from 'vue';

export default {
    name: 'ParticipantsList',
    props: {
        participants: {
            type: Array,
            default: () => []
        },
        collapsed: {
            type: Boolean,
            default: true
        },
        playerPairs: {
            type: Array,
            default: () => []
        },
        selectedForPairing: {
            type: [Number, Object],
            default: null
        }
    },
    emits: ['update:participants', 'toggle-collapse', 'pair-toggle'],
    setup(props, { emit }) {
        const displayedParticipants = computed(() => {
            if (props.collapsed && props.participants.length > 5) {
                return props.participants.slice(0, 5);
            }
            return props.participants;
        });

        // Pairing colors (6 màu xoay vòng, riêng biệt với tier)
        const PAIR_COLOR_CLASSES = {
            cyan: { border: 'border-cyan-500', text: 'text-cyan-600', bg: 'bg-cyan-100' },
            orange: { border: 'border-orange-500', text: 'text-orange-600', bg: 'bg-orange-100' },
            teal: { border: 'border-teal-500', text: 'text-teal-600', bg: 'bg-teal-100' },
            purple: { border: 'border-purple-500', text: 'text-purple-600', bg: 'bg-purple-100' },
            pink: { border: 'border-pink-500', text: 'text-pink-600', bg: 'bg-pink-100' },
            amber: { border: 'border-amber-500', text: 'text-amber-600', bg: 'bg-amber-100' },
        };

        // Get participant ID
        const getParticipantId = (participant) => {
            return participant.id || participant.mini_participant_id;
        };

        // Check if participant is already paired
        const findPairForParticipant = (participantId, isGuest = false) => {
            return props.playerPairs.find(pair => {
                const player1Match = String(pair.player1_id) === String(participantId) && pair.player1_is_guest === isGuest;
                const player2Match = String(pair.player2_id) === String(participantId) && pair.player2_is_guest === isGuest;
                return player1Match || player2Match;
            });
        };

        // Get pairing status: 'none' | 'waiting' | 'paired'
        const getPairingStatus = (participant) => {
            const participantId = getParticipantId(participant);
            const isGuest = participant.is_guest || false;

            if (props.selectedForPairing === participantId) {
                return 'waiting';
            }

            const pair = findPairForParticipant(participantId, isGuest);
            return pair ? 'paired' : 'none';
        };

        // Get pair color for participant
        const getPairColor = (participant) => {
            const participantId = getParticipantId(participant);
            const isGuest = participant.is_guest || false;
            const pair = findPairForParticipant(participantId, isGuest);
            return pair?.pair_color || null;
        };

        // Get partner name
        const getPairPartnerName = (participant) => {
            const participantId = getParticipantId(participant);
            const isGuest = participant.is_guest || false;
            const pair = findPairForParticipant(participantId, isGuest);
            
            if (!pair) return '';

            const isPlayer1 = String(pair.player1_id) === String(participantId) && pair.player1_is_guest === isGuest;
            const partnerId = isPlayer1 ? pair.player2_id : pair.player1_id;

            // Find partner participant
            const partner = props.participants.find(p => 
                String(p.id || p.mini_participant_id) === String(partnerId)
            );
            return partner?.user?.full_name || partner?.guest_name || 'Người chơi';
        };

        // Get pairing button class
        const getPairingButtonClass = (participant) => {
            const status = getPairingStatus(participant);
            
            if (status === 'waiting') {
                return 'border-yellow-400 text-yellow-500 bg-yellow-50 border-dashed';
            }
            
            if (status === 'paired') {
                const color = getPairColor(participant);
                const colorClass = PAIR_COLOR_CLASSES[color] || PAIR_COLOR_CLASSES.cyan;
                return `${colorClass.border} ${colorClass.text} ${colorClass.bg}`;
            }
            
            return 'border-gray-300 dark:border-slate-600 text-gray-400 hover:border-gray-400';
        };

        // Get pair chip class
        const getPairChipClass = (participant) => {
            const color = getPairColor(participant);
            const colorClass = PAIR_COLOR_CLASSES[color] || PAIR_COLOR_CLASSES.cyan;
            return `${colorClass.bg} ${colorClass.text}`;
        };

        const toggleSkip = (participant) => {
            const updated = props.participants.map(p => 
                p.id === participant.id 
                    ? { ...p, skip: !p.skip }
                    : p
            );
            emit('update:participants', updated);
        };

        const updateTier = (participant, tier) => {
            const updated = props.participants.map(p => 
                p.id === participant.id 
                    ? { ...p, tier }
                    : p
            );
            emit('update:participants', updated);
        };

        const getGenderIcon = (participant) => {
            // Gender from user only (not modify_gender)
            const gender = participant.user?.gender || participant.gender;
            
            if (gender === 'male') return '♂';
            if (gender === 'female') return '♀';
            return '-';
        };

        const getColorClass = (tier) => {
            const colors = {
                'purple': 'bg-purple-500',
                'red': 'bg-red-500',
                'yellow': 'bg-yellow-400',
                'green': 'bg-green-500'
            };
            return colors[tier] || 'bg-gray-400';
        };

        const getAvatarBorderClass = (tier) => {
            const colors = {
                'purple': 'border-purple-500',
                'red': 'border-red-500',
                'yellow': 'border-yellow-400',
                'green': 'border-green-500'
            };
            return colors[tier] || 'border-gray-400';
        };

        const getTierLabel = (tier) => {
            const labels = {
                'green': 'Xanh lá (Thấp)',
                'yellow': 'Vàng (Trung bình)',
                'red': 'Đỏ (Cao)',
                'purple': 'Tím (Rất cao)'
            };
            return labels[tier] || tier;
        };

        return {
            displayedParticipants,
            getPairingStatus,
            getPairingButtonClass,
            getPairChipClass,
            getPairPartnerName,
            toggleSkip,
            updateTier,
            getGenderIcon,
            getColorClass,
            getAvatarBorderClass,
            getTierLabel
        };
    }
}
</script>
