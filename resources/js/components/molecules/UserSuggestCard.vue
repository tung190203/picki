<template>
    <article
        class="group bg-white dark:bg-[#1F2228] border border-gray-200 dark:border-[#2A2D36] rounded-2xl overflow-hidden cursor-pointer transition-all duration-200 ease-out hover:border-gray-300 dark:hover:border-[#3E414C] hover:shadow-lg flex items-stretch"
        @click="handleCardClick"
    >
        <!-- Left: avatar block -->
        <div class="relative shrink-0 w-20 sm:w-24 flex items-center justify-center bg-gradient-to-br from-red-50 via-white to-amber-50 dark:from-[#262A33] dark:via-[#1F2228] dark:to-[#2A2418]">
            <img
                :src="user.avatar_url || DEFAULT_AVATAR"
                :alt="user.full_name"
                class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl object-cover border-2 border-white dark:border-[#2A2D36] shadow-md bg-gray-100 dark:bg-[#2A2D36] transition-transform duration-300 group-hover:scale-105"
            />
            <span
                v-if="user.is_online"
                class="absolute bottom-1.5 right-1.5 w-3 h-3 bg-green-500 border-2 border-white dark:border-[#1F2228] rounded-full"
                title="Đang online"
            />
        </div>

        <!-- Middle: info -->
        <div class="flex-1 min-w-0 px-3 sm:px-4 py-3 flex flex-col gap-1.5">
            <!-- Name + category_text badge -->
            <div class="flex items-center gap-2 min-w-0">
                <h3
                    class="text-sm sm:text-base font-extrabold text-gray-900 dark:text-white line-clamp-1 flex-1 min-w-0"
                    v-tooltip="user.full_name"
                >
                    {{ user.full_name }}
                </h3>
                <span
                    v-if="relationBadgeText"
                    class="shrink-0 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-[#D72D36]/10 text-[#D72D36] text-[9px] sm:text-[10px] font-bold uppercase whitespace-nowrap"
                >
                    <span class="w-1 h-1 rounded-full bg-[#D72D36] animate-pulse"></span>
                    {{ relationBadgeText }}
                </span>
            </div>

            <!-- Gender/age + club -->
            <p class="text-[11px] sm:text-xs text-gray-500 dark:text-[#A0A3AD] line-clamp-1 flex items-center gap-1 min-h-[16px]">
                <span>{{ user.gender_text || 'Khác' }}</span>
                <template v-if="user.age_group">
                    <span>•</span>
                    <span>{{ user.age_group }}</span>
                </template>
                <template v-if="user.clubs && user.clubs.length">
                    <span>•</span>
                    <span class="truncate text-gray-700 dark:text-slate-300">{{ user.clubs[0].name }}</span>
                </template>
            </p>

            <!-- Stats: distance | vndupr | vn_rank | win_rate | matches -->
            <div class="flex items-center flex-wrap gap-x-3 gap-y-1 text-[11px] sm:text-xs min-h-[18px]">
                <span v-if="user.distance != null" class="flex items-center gap-1 text-[#4392E0] dark:text-sky-400 font-medium">
                    <MapPinIcon class="w-3 h-3" />
                    {{ formatDistance(user.distance) }}
                </span>
                <span v-if="user.vndupr_score != null" class="flex items-center gap-1 text-orange-600 dark:text-orange-400 font-medium">
                    <StarIconSolid class="w-3 h-3" />
                    VNDUPR {{ Number(user.vndupr_score).toFixed(3) }}
                </span>
                <span v-if="user.vn_rank != null" class="flex items-center gap-1 text-purple-600 dark:text-purple-400 font-medium">
                    <TrophyIcon class="w-3 h-3" />
                    #{{ user.vn_rank }}
                </span>
                <span v-if="user.win_rate != null" class="flex items-center gap-1 text-green-600 dark:text-green-400 font-medium">
                    <ChartIcon class="w-3 h-3" />
                    {{ Number(user.win_rate).toFixed(1) }}%
                </span>
                <span v-if="user.total_matches != null" class="flex items-center gap-1 text-blue-600 dark:text-blue-400 font-medium">
                    <HashtagIcon class="w-3 h-3" />
                    {{ user.total_matches }} trận
                </span>
            </div>

            <!-- Relation strip (only when present) -->
            <div
                v-if="hasRelation"
                class="flex items-center gap-2 pt-1.5 mt-0.5 border-t border-gray-100 dark:border-[#2A2D36] flex-wrap"
            >
                <span
                    v-if="user.relation.matches_together > 0"
                    class="inline-flex items-center gap-1 text-[10px] sm:text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                    v-tooltip="`Số trận đã chơi cùng`"
                >
                    <SparklesIcon class="w-3 h-3" />
                    {{ user.relation.matches_together }} trận
                </span>
                <span
                    v-if="user.relation.mini_tournaments_together > 0"
                    class="inline-flex items-center gap-1 text-[10px] sm:text-[11px] font-semibold text-amber-600 dark:text-amber-400"
                    v-tooltip="`Số kèo mini-tournament đã chơi cùng`"
                >
                    <TrophyIcon class="w-3 h-3" />
                    {{ user.relation.mini_tournaments_together }} kèo
                </span>
                <span
                    v-if="user.relation.tournaments_together > 0"
                    class="inline-flex items-center gap-1 text-[10px] sm:text-[11px] font-semibold text-indigo-600 dark:text-indigo-400"
                    v-tooltip="`Số giải đấu đã chơi cùng`"
                >
                    <StarIconSolid class="w-3 h-3" />
                    {{ user.relation.tournaments_together }} giải
                </span>
                <span
                    v-if="user.relation.clubs_together > 0"
                    class="inline-flex items-center gap-1 text-[10px] sm:text-[11px] font-semibold text-rose-600 dark:text-rose-400"
                    v-tooltip="`Số CLB chung`"
                >
                    <UsersIcon class="w-3 h-3" />
                    {{ user.relation.clubs_together }} CLB
                </span>
            </div>
        </div>

        <!-- Right: action -->
        <div class="shrink-0 p-3 sm:p-4 flex items-center">
            <button
                v-if="user.is_follow"
                @click.stop="handleFollowClick"
                :disabled="isFollowLoading"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-[11px] sm:text-xs font-semibold bg-gray-100 dark:bg-[#3E414C] text-gray-900 dark:text-white hover:bg-gray-200 dark:hover:bg-[#2A2D36] transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed active:scale-95 whitespace-nowrap"
            >
                <UserMinusIcon class="w-3.5 h-3.5" />
                <span>Đang theo dõi</span>
            </button>
            <button
                v-else
                @click.stop="handleFollowClick"
                :disabled="isFollowLoading"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-[11px] sm:text-xs font-semibold bg-gradient-to-r from-[#D72D36] to-[#E8454E] text-white hover:shadow-md hover:shadow-red-500/30 transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed active:scale-95 whitespace-nowrap"
            >
                <UserPlusIcon class="w-3.5 h-3.5" />
                <span>Theo dõi</span>
            </button>
        </div>
    </article>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import {
    MapPinIcon,
    UserPlusIcon,
    UserMinusIcon,
    TrophyIcon,
    StarIcon as StarIconSolid,
    HashtagIcon,
    UsersIcon,
    SparklesIcon,
} from '@heroicons/vue/24/solid'

const ChartIcon = {
    template: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>`
}

const DEFAULT_AVATAR = '/images/default-avatar.png'

const props = defineProps({
    /** @type {import('vue').PropType<{id:number,full_name:string,avatar_url?:string,relation?:object,category?:string,category_text?:string,distance?:number,is_follow?:boolean,is_online?:boolean,vndupr_score?:number|null,vn_rank?:number|null,win_rate?:number,total_matches?:number,gender_text?:string,age_group?:string,clubs?:Array}>} */
    user: { type: Object, required: true },
})

const emit = defineEmits(['click', 'follow'])

const localFollowing = ref(Boolean(props.user?.is_follow))
const isFollowLoading = ref(false)

watch(
    () => props.user?.is_follow,
    (val) => {
        if (!isFollowLoading.value) {
            localFollowing.value = Boolean(val)
        }
    }
)

const handleCardClick = () => {
    if (!props.user?.id) return
    emit('click', props.user)
}

const handleFollowClick = async () => {
    if (isFollowLoading.value) return
    const prev = localFollowing.value
    localFollowing.value = !prev
    isFollowLoading.value = true
    try {
        await emit('follow', { user: props.user, next: localFollowing.value })
    } catch (_) {
        localFollowing.value = prev
    } finally {
        isFollowLoading.value = false
    }
}

const formatDistance = (km) => {
    if (km == null || km === '') return ''
    const num = Number(km)
    if (!Number.isFinite(num)) return ''
    if (num < 1) return `${Math.round(num * 1000)} m`
    return `${num.toFixed(1)} km`
}

const hasRelation = computed(() => {
    const r = props.user?.relation
    if (!r) return false
    return (
        (r.matches_together ?? 0) > 0 ||
        (r.mini_tournaments_together ?? 0) > 0 ||
        (r.tournaments_together ?? 0) > 0 ||
        (r.clubs_together ?? 0) > 0
    )
})

const relationBadgeText = computed(() => {
    // Chỉ hiển thị category_text ở góc (kiểu badge)
    return props.user?.category_text || null
})
</script>

<style scoped>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
