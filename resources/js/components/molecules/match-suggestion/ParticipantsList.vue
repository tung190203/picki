<template>
    <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
        <h4 class="text-sm font-semibold text-gray-700 mb-4">Danh sách người chơi</h4>
        
        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-2 px-2 font-semibold text-gray-600">TÊN</th>
                        <th class="text-center py-2 px-2 font-semibold text-gray-600">GT</th>
                        <th class="text-center py-2 px-2 font-semibold text-gray-600">SỐ TRẬN</th>
                        <th class="text-center py-2 px-2 font-semibold text-gray-600">MÀU</th>
                        <th class="text-center py-2 px-2 font-semibold text-gray-600">BỎ VÒNG</th>
                    </tr>
                </thead>
                <tbody>
                    <tr 
                        v-for="(participant, index) in displayedParticipants" 
                        :key="participant.id"
                        class="border-b border-gray-100 hover:bg-gray-50"
                    >
                        <td class="py-3 px-2">
                            <div class="flex items-center gap-2">
                                <div class="relative">
                                    <img 
                                        :src="participant.user?.avatar_url || participant.guest_avatar || '/default-avatar.png'"
                                        :alt="participant.user?.full_name || participant.guest_name"
                                        class="w-8 h-8 rounded-full object-cover border-2"
                                        :class="getAvatarBorderClass(participant.tier)"
                                    />
                                </div>
                                <span class="text-gray-800">
                                    {{ participant.user?.full_name || participant.guest_name || 'Khách' }}
                                </span>
                            </div>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <select 
                                :value="participant.gender || getGender(participant)"
                                @change="updateGender(participant, $event.target.value)"
                                class="px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-red-500 focus:border-red-500"
                            >
                                <option value="">-</option>
                                <option value="male">♂ Nam</option>
                                <option value="female">♀ Nữ</option>
                            </select>
                        </td>
                        <td class="py-3 px-2 text-center text-gray-600">
                            {{ participant.played_matches_count || 0 }}
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
                                        participant.tier === color ? 'ring-2 ring-offset-1 ring-gray-900' : 'opacity-50'
                                    ]"
                                    :title="getTierLabel(color)"
                                ></button>
                            </div>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <input 
                                type="checkbox"
                                :checked="participant.skip"
                                @change="toggleSkip(participant)"
                                class="w-4 h-4 text-red-600 rounded focus:ring-red-500"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Collapse/Expand Button -->
        <div v-if="participants.length > 5" class="mt-4 text-center">
            <button 
                @click="$emit('toggle-collapse')"
                class="text-sm text-blue-600 hover:text-blue-700 font-medium"
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

        const updateGender = (participant, gender) => {
            const updated = props.participants.map(p => 
                p.id === participant.id 
                    ? { ...p, gender: gender || null }
                    : p
            );
            emit('update:participants', updated);
        };

        const getGender = (participant) => {
            // Try to determine gender from user data
            if (participant.user?.gender) {
                return participant.user.gender;
            }
            return null;
        };

        const getTierFromScore = (participant) => {
            // Auto-assign tier based on score
            // Priority: Purple (highest) > Red > Yellow > Green (lowest)
            const score = participant.user?.sports?.[0]?.scores?.vndupr_score || 
                         participant.user?.sports?.[0]?.scores?.personal_score || 0;
            
            const numScore = parseFloat(score);
            
            if (numScore >= 3.5) return 'purple';
            if (numScore >= 2.5) return 'red';
            if (numScore >= 1.5) return 'yellow';
            return 'green';
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
            updateGender,
            getGender,
            getTierFromScore,
            getColorClass,
            getAvatarBorderClass,
            getTierLabel
        };
    }
}
</script>
