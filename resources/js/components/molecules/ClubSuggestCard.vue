<template>
    <article
        class="group bg-white dark:bg-[#1F2228] border border-gray-200 dark:border-[#2A2D36] rounded-2xl overflow-hidden cursor-pointer transition-all duration-200 ease-out hover:border-gray-300 dark:hover:border-[#3E414C] hover:shadow-lg flex items-stretch"
        @click="handleCardClick"
    >
        <!-- Left: logo block with gradient bg -->
        <div class="relative shrink-0 w-24 sm:w-28 bg-gradient-to-br from-red-50 via-white to-amber-50 dark:from-[#262A33] dark:via-[#1F2228] dark:to-[#2A2418] flex items-center justify-center">
            <img
                :src="club.logo_url || DEFAULT_AVATAR"
                :alt="club.name"
                class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl object-cover border-2 border-white dark:border-[#2A2D36] shadow-md bg-gray-100 dark:bg-[#2A2D36] transition-transform duration-300 group-hover:scale-105"
            />
            <div
                v-if="club.is_verified"
                class="absolute top-2 left-2 bg-[#4392E0] rounded-full p-0.5 border-2 border-white dark:border-[#1F2228]"
            >
                <VerifyIcon class="w-2.5 h-2.5 text-white" />
            </div>
        </div>

        <!-- Middle: info -->
        <div class="flex-1 min-w-0 px-3.5 sm:px-4 py-3 flex flex-col gap-1.5">
            <!-- Name + recruitment badge -->
            <div class="flex items-center gap-2 min-w-0">
                <h3
                    class="text-sm sm:text-base font-extrabold text-gray-900 dark:text-white line-clamp-1 flex-1 min-w-0"
                    v-tooltip="club.name"
                >
                    {{ club.name }}
                </h3>
                <span
                    v-if="recruitmentBadgeText"
                    class="shrink-0 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-[#00B377] text-white text-[9px] sm:text-[10px] font-bold uppercase whitespace-nowrap"
                >
                    <span class="w-1 h-1 rounded-full bg-white animate-pulse"></span>
                    {{ recruitmentBadgeText }}
                </span>
            </div>

            <!-- Location + distance (always rendered for consistent height) -->
            <p
                class="text-[11px] sm:text-xs text-gray-500 dark:text-[#A0A3AD] line-clamp-1 flex items-center gap-1 min-h-[16px]"
                :class="{ 'opacity-0': !locationText }"
                v-tooltip="locationTooltip || null"
            >
                <MapPinIcon class="w-3 h-3 shrink-0" />
                <span class="truncate">{{ locationText || '—' }}</span>
            </p>

            <!-- Schedule + stats + score pill row (always rendered) -->
            <div class="flex items-center flex-wrap gap-x-3 gap-y-1 text-[11px] sm:text-xs min-h-[18px]">
                <span
                    v-if="club.recurring_schedule_text"
                    class="flex items-center gap-1 text-gray-600 dark:text-[#A0A3AD] min-w-0"
                >
                    <CalendarIcon class="w-3 h-3 shrink-0 text-gray-500 dark:text-[#838799]" />
                    <span class="truncate max-w-[180px] sm:max-w-[220px]">{{ club.recurring_schedule_text }}</span>
                </span>
                <span v-else class="flex items-center gap-1 text-gray-400 dark:text-[#6B6E78] italic">
                    <CalendarIcon class="w-3 h-3 shrink-0" />
                    <span>Chưa có lịch</span>
                </span>
                <span class="flex items-center gap-1 text-gray-700 dark:text-gray-300">
                    <UsersIcon class="w-3 h-3 shrink-0 text-gray-500 dark:text-[#838799]" />
                    <span class="font-semibold">{{ club.quantity_members ?? 0 }}</span>
                    <span class="text-gray-500 dark:text-[#A0A3AD]">TV</span>
                </span>
                <span class="flex items-center gap-1 text-gray-700 dark:text-gray-300">
                    <BellIcon class="w-3 h-3 shrink-0 text-gray-500 dark:text-[#838799]" />
                    <span class="font-semibold">{{ club.followers_count ?? 0 }}</span>
                    <span class="text-gray-500 dark:text-[#A0A3AD]">theo dõi</span>
                </span>
                <span
                    v-if="club.score_range_text"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gradient-to-r from-amber-400 to-orange-500 text-white font-bold shadow-sm"
                >
                    Trình {{ club.score_range_text }}
                </span>
            </div>

            <!-- Admin strip -->
            <div
                v-if="club.admin"
                class="flex items-center gap-2 pt-1.5 mt-0.5 border-t border-gray-100 dark:border-[#2A2D36]"
            >
                <img
                    :src="club.admin.avatar_url || DEFAULT_AVATAR"
                    :alt="club.admin.full_name"
                    class="w-6 h-6 rounded-full object-cover border border-gray-200 dark:border-[#2A2D36] shrink-0"
                />
                <span class="text-[11px] sm:text-xs text-gray-600 dark:text-gray-300 truncate">
                    {{ club.admin.full_name || '—' }}
                </span>
                <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 text-[9px] sm:text-[10px] font-bold uppercase tracking-wide">
                    Admin
                </span>
            </div>
        </div>

        <!-- Right: action -->
        <div class="shrink-0 p-3 sm:p-4 flex items-center">
            <button
                v-if="isMember"
                disabled
                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-[11px] sm:text-xs font-semibold bg-[#00B377]/15 text-[#00B377] border border-[#00B377]/40 cursor-default whitespace-nowrap"
            >
                <CheckIcon class="w-3.5 h-3.5" />
                <span>Thành viên</span>
            </button>
            <button
                v-else
                @click.stop="handleFollowClick"
                :disabled="isFollowLoading"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-[11px] sm:text-xs font-semibold transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed active:scale-95 whitespace-nowrap"
                :class="localFollowing
                    ? 'bg-gray-100 dark:bg-[#3E414C] text-gray-900 dark:text-white hover:bg-gray-200 dark:hover:bg-[#2A2D36]'
                    : 'bg-gradient-to-r from-[#D72D36] to-[#E8454E] text-white hover:shadow-md hover:shadow-red-500/30'"
            >
                <span
                    v-if="isFollowLoading"
                    class="w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"
                ></span>
                <BellAlertIcon v-else-if="!localFollowing" class="w-3.5 h-3.5" />
                <BellSlashIcon v-else class="w-3.5 h-3.5" />
                <span>{{ localFollowing ? 'Đang theo dõi' : 'Theo dõi' }}</span>
            </button>
        </div>
    </article>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import VerifyIcon from '@/assets/images/verify-icon.svg'
import {
    CalendarIcon,
    CheckIcon,
    BellAlertIcon,
    BellSlashIcon,
    UsersIcon,
    BellIcon,
    MapPinIcon,
} from '@heroicons/vue/24/solid'

const DEFAULT_AVATAR = '/images/default-avatar.png'

const props = defineProps({
    /** @type {import('vue').PropType<ClubCardClub>} */
    club: { type: Object, required: true },
})

const emit = defineEmits(['click', 'follow'])

const isMember = computed(() => Boolean(props.club?.is_member))
const localFollowing = ref(Boolean(props.club?.is_following))
const isFollowLoading = ref(false)

watch(
    () => props.club?.is_following,
    (val) => {
        if (!isFollowLoading.value) {
            localFollowing.value = Boolean(val)
        }
    }
)

const handleCardClick = () => {
    if (!props.club?.id) return
    emit('click', props.club)
}

const handleFollowClick = async () => {
    if (isFollowLoading.value || isMember.value) return
    const prev = localFollowing.value
    localFollowing.value = !prev
    isFollowLoading.value = true
    try {
        await emit('follow', { club: props.club, next: localFollowing.value })
    } catch (_) {
        localFollowing.value = prev
    } finally {
        isFollowLoading.value = false
    }
}

// ---- Formatters ----

const formatDistance = (km) => {
    if (km == null || km === '') return ''
    const num = Number(km)
    if (!Number.isFinite(num)) return ''
    if (num < 1) return `${Math.round(num * 1000)} m`
    return `${num.toFixed(1)} km`
}

const locationBaseText = computed(() => {
    const court = props.club?.primary_home_court?.name
    if (court) return court
    return props.club?.address || ''
})

const locationText = computed(() => {
    const base = locationBaseText.value
    if (!base) return ''
    const dist = formatDistance(props.club?.distance)
    return dist ? `${base} · ${dist}` : base
})

const locationTooltip = computed(() => {
    return props.club?.primary_home_court?.address || locationBaseText.value
})

const recruitmentBadgeText = computed(() => {
    if (props.club?.recruitment_status === 'open') {
        return props.club?.recruitment_status_text || 'Tuyển TV'
    }
    return ''
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
