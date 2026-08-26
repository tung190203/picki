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
                                <span class="text-gray-800 dark:text-gray-200">
                                    {{ participant.user?.full_name || participant.guest_name || 'Khách' }}
                                </span>
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
        }
    },
    emits: ['update:participants', 'toggle-collapse'],
    setup(props, { emit }) {
        const displayedParticipants = computed(() => {
            if (props.collapsed && props.participants.length > 5) {
                return props.participants.slice(0, 5);
            }
            return props.participants;
        });

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
