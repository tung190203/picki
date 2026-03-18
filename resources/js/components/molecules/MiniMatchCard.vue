<template>
    <div class="bg-[#F6F7F9] border border-[#E2E5EA] rounded-2xl p-4 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="relative flex cursor-pointer group"
                    :class="!selectable ? 'opacity-50 cursor-not-allowed' : ''"
                    @click.stop="selectable && emit('update:selected', !selected)"
                >
                    <input
                        type="checkbox"
                        class="sr-only peer"
                        :checked="selected"
                        :disabled="!selectable"
                        tabindex="-1"
                    />
                    <span
                        class="relative w-[18px] h-[18px] rounded-full border-2 border-[#9AA5B4] bg-white transition-all
                        peer-checked:border-[#D72D36] peer-checked:bg-white"
                    >
                        <span
                            class="absolute w-2.5 h-2.5 rounded-full bg-[#D72D36] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2
                            opacity-0 peer-checked:opacity-100 transition-opacity"
                        ></span>
                    </span>
                </button>
                <h3
                    class="text-base leading-5 font-bold text-[#3E414C] max-w-[180px] overflow-hidden"
                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"
                    v-tooltip="displayTitle"
                >
                    {{ displayTitle }}
                </h3>
            </div>
            <div class="flex items-center gap-1.5 text-[#7D8696]">
                <CalendarIcon class="w-4 h-4" />
                <span class="text-base font-semibold">{{ courtName }}</span>
            </div>
        </div>

        <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] gap-2 sm:gap-3 items-center mb-4">
            <div
                class="rounded-xl p-2.5 sm:p-3 min-h-[112px] flex items-center justify-center min-w-0 overflow-hidden"
                :class="teamWinId === team1[0]?.team_id ? 'border border-[#EA4452] bg-[#FFF7F8]' : 'bg-[#DCDDE2]'"
            >
                <div class="flex items-start justify-center gap-1.5 sm:gap-2 flex-nowrap min-w-0">
                    <UserCard
                        v-for="player in team1"
                        :key="player.id"
                        :name="player.name"
                        :avatar="player.avatar"
                        :rating="player.rating"
                        :status="player.status"
                        :size="10"
                        :badgeSize="4"
                        :ratingSize="6"
                        :maxWidth="56"
                        :flagDelete="false"
                    />
                    <div
                        v-if="miniMatchType !== MATCH_TYPE_SINGLE && team1.length < 2"
                        class="w-10 h-10 sm:w-11 sm:h-11 border-2 border-dashed border-[#8F97A5] bg-[#ECEEF2] rounded-full flex items-center justify-center shrink-0"
                    >
                        <PlusIcon class="w-5 h-5 sm:w-6 sm:h-6 text-[#8F97A5]"/>
                    </div>
                </div>
            </div>

            <div class="flex justify-center items-center">
                <span class="text-2xl font-bold text-[#5D758F]">VS</span>
            </div>

            <div
                class="rounded-xl p-2.5 sm:p-3 min-h-[112px] flex items-center justify-center min-w-0 overflow-hidden"
                :class="teamWinId === team2[0]?.team_id ? 'border border-[#4E8DE9] bg-[#F4F8FF]' : 'bg-[#DCDDE2]'"
            >
                <div class="flex items-start justify-center gap-1.5 sm:gap-2 flex-nowrap min-w-0">
                    <UserCard
                        v-for="player in team2"
                        :key="player.id"
                        :name="player.name"
                        :avatar="player.avatar"
                        :rating="player.rating"
                        :status="player.status"
                        :size="10"
                        :badgeSize="4"
                        :ratingSize="6"
                        :maxWidth="56"
                        :flagDelete="false"
                    />
                    <div
                        v-if="miniMatchType !== MATCH_TYPE_SINGLE && team2.length < 2"
                        class="w-10 h-10 sm:w-11 sm:h-11 border-2 border-dashed border-[#8F97A5] bg-[#ECEEF2] rounded-full flex items-center justify-center shrink-0"
                    >
                        <PlusIcon class="w-5 h-5 sm:w-6 sm:h-6 text-[#8F97A5]"/>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="setsToDisplay.length > 0" class="grid gap-0" :class="gridColsClass">
            <div
                v-for="(set, index) in setsToDisplay"
                :key="`set-${set.set_number || index}`"
                class="text-center"
                :class="index < setsToDisplay.length - 1 ? 'border-r border-[#DCE7F5]' : ''"
            >
                <div class="flex items-center justify-center gap-1 mb-1">
                    <span class="text-[#6AAAEB] font-medium text-xs">Set</span>
                    <div class="bg-[#d2e5fa] text-[#6AAAEB] border border-[#6AAAEB] rounded px-1.5 py-0 text-xs font-semibold min-w-[18px] leading-4">
                        {{ set.set_number || index + 1 }}
                    </div>
                </div>
                <div class="text-2xl leading-7 font-bold text-[#0057A8]">
                    {{ set.team1 }}-{{ set.team2 }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { CalendarIcon, PlusIcon } from '@heroicons/vue/24/outline'
import UserCard from '@/components/molecules/UserCard.vue'

const MATCH_TYPE_SINGLE = 2

const props = defineProps({
    matchId: [String, Number],
    matchTitle: String,
    matchTime: String,
    courtName: String,
    miniMatchType: Number,
    teamWinId: Number,
    team1: Array,
    team2: Array,
    sets: Array,

    selected: {
        type: Boolean,
        default: false
    },
    selectable: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['update:selected'])

const displayTitle = computed(() => {
    const title = typeof props.matchTitle === 'string' ? props.matchTitle.trim() : ''
    return title || 'Trận đấu'
})

const setsToDisplay = computed(() => {
    const normalized = Array.isArray(props.sets) ? props.sets : []
    return normalized.filter((set) => Number(set?.team1 || 0) > 0 || Number(set?.team2 || 0) > 0)
})

const gridColsClass = computed(() => {
    const count = setsToDisplay.value.length
    if (count <= 1) return 'grid-cols-1'
    if (count === 2) return 'grid-cols-2'
    return 'grid-cols-3'
})
</script>
