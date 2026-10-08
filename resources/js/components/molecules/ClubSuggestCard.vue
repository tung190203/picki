<template>
    <div
        class="bg-white dark:bg-[#1F2228] border border-gray-200 dark:border-[#2A2D36] rounded-[8px] hover:border-gray-300 dark:hover:border-[#3E414C] transition-colors flex flex-col overflow-hidden cursor-pointer"
        @click="handleCardClick"
    >
        <div class="px-3 sm:px-4 pt-3 sm:pt-4 pb-3 sm:pb-4 flex-1 flex flex-col">
            <!-- Top: avatar + name + recruitment badge -->
            <div class="flex items-start gap-2.5 sm:gap-3 mb-2">
                <div class="relative shrink-0">
                    <img
                        :src="club.logo_url || DEFAULT_AVATAR"
                        :alt="club.name"
                        class="w-11 h-11 sm:w-12 sm:h-12 rounded-full object-cover border-2 border-gray-200 dark:border-[#2A2D36] bg-gray-200 dark:bg-[#2A2D36]"
                    />
                    <div
                        v-if="club.is_verified"
                        class="absolute -bottom-0.5 -right-0.5 bg-[#4392E0] rounded-full p-0.5 z-10 border-2 border-white dark:border-[#1F2228]"
                    >
                        <VerifyIcon class="w-2.5 h-2.5 text-white" />
                    </div>
                </div>
                <div class="flex-1 min-w-0 pt-0.5">
                    <div class="flex items-start gap-2">
                        <h3
                            class="text-sm sm:text-[15px] font-bold text-gray-900 dark:text-white line-clamp-1 flex-1 min-w-0"
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
                </div>
            </div>

            <!-- Location + distance -->
            <p
                v-if="locationText"
                class="text-[11px] sm:text-xs text-gray-500 dark:text-[#A0A3AD] line-clamp-1 mb-1.5"
                v-tooltip="locationTooltip"
            >
                {{ locationText }}
            </p>

            <!-- Stats: members · followers · score -->
            <p
                v-if="statsText"
                class="text-[11px] sm:text-xs text-gray-500 dark:text-[#838799] line-clamp-1 mb-1.5"
            >
                {{ statsText }}
            </p>

            <!-- Schedule -->
            <p
                v-if="club.recurring_schedule_text"
                class="text-[11px] sm:text-xs text-gray-500 dark:text-[#A0A3AD] line-clamp-1 mb-2 flex items-center gap-1"
            >
                <CalendarIcon class="w-3 h-3 sm:w-3.5 sm:h-3.5 shrink-0 text-gray-500 dark:text-[#838799]" />
                <span class="truncate">{{ club.recurring_schedule_text }}</span>
            </p>

            <!-- Divider + admin + button -->
            <div class="flex items-center gap-2 sm:gap-3 pt-2.5 mt-auto border-t border-gray-200 dark:border-[#2A2D36]">
                <!-- Admin avatar + info -->
                <div v-if="club.admin" class="flex items-center gap-2 flex-1 min-w-0">
                    <img
                        :src="club.admin.avatar_url || DEFAULT_AVATAR"
                        :alt="club.admin.full_name"
                        class="w-7 h-7 sm:w-8 sm:h-8 rounded-full object-cover border border-gray-200 dark:border-[#2A2D36] shrink-0"
                    />
                    <div class="flex-1 min-w-0">
                        <div class="text-[11px] sm:text-xs font-semibold text-gray-900 dark:text-white line-clamp-1 flex items-center gap-1">
                            <span class="truncate">{{ club.admin.full_name || '—' }}</span>
                            <span class="shrink-0 inline-flex items-center px-1 py-0.5 rounded bg-gray-100 dark:bg-[#2A2D36] text-[9px] sm:text-[10px] font-bold text-gray-600 dark:text-[#A0A3AD] uppercase">
                                Admin
                            </span>
                        </div>
                        <div class="text-[10px] sm:text-xs text-[#838799] dark:text-gray-400 line-clamp-1">
                            <template v-if="club.score_range_text">
                                trình {{ club.score_range_text }}
                            </template>
                        </div>
                    </div>
                </div>
                <div v-else class="flex-1 min-w-0">
                    <div class="text-[11px] sm:text-xs text-gray-500 dark:text-[#838799] line-clamp-1">—</div>
                </div>

                <!-- Status button -->
                <button
                    v-if="isMember"
                    disabled
                    class="shrink-0 inline-flex items-center justify-center gap-1 px-2.5 sm:px-3 py-1.5 rounded-lg text-[11px] sm:text-xs font-semibold bg-[#00B377]/20 text-[#00B377] border border-[#00B377]/40 cursor-default"
                >
                    <CheckIcon class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                    <span>Thành viên</span>
                </button>
                <button
                    v-else
                    @click.stop="handleFollowClick"
                    :disabled="isFollowLoading"
                    class="shrink-0 inline-flex items-center justify-center gap-1 px-2.5 sm:px-3 py-1.5 rounded-lg text-[11px] sm:text-xs font-semibold transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    :class="localFollowing
                        ? 'bg-gray-200 dark:bg-[#3E414C] text-gray-900 dark:text-white hover:bg-gray-300 dark:hover:bg-[#2A2D36]'
                        : 'bg-[#D72D36] text-white hover:bg-[#c4252e]'"
                >
                    <span
                        v-if="isFollowLoading"
                        class="w-3 h-3 border-2 border-white/40 border-t-white rounded-full animate-spin"
                    ></span>
                    <BellAlertIcon v-else-if="!localFollowing" class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                    <BellSlashIcon v-else class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                    <span>{{ localFollowing ? 'Đang theo dõi' : 'Theo dõi' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import VerifyIcon from '@/assets/images/verify-icon.svg'
import {
    CalendarIcon,
    CheckIcon,
    BellAlertIcon,
    BellSlashIcon,
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

// Sync local follow state when the parent refreshes the list with new server data
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

const formatNumber = (n) => {
    if (n == null) return '-'
    const num = Number(n)
    return Number.isFinite(num) ? num.toFixed(1) : '-'
}

// "Golden Pickleball Hạ Long" or fallback to address
const locationBaseText = computed(() => {
    const court = props.club?.primary_home_court?.name
    if (court) return court
    return props.club?.address || ''
})

// Append distance only if present
const locationText = computed(() => {
    const base = locationBaseText.value
    if (!base) return ''
    const dist = formatDistance(props.club?.distance)
    return dist ? `${base} · ${dist}` : base
})

const locationTooltip = computed(() => {
    return props.club?.primary_home_court?.address || locationBaseText.value
})

// Build "trình X.X-Y.Y" with graceful null handling
const scoreText = computed(() => {
    const txt = props.club?.score_range_text
    if (txt) return `trình ${txt}`
    const range = props.club?.score_range
    if (range && (range.min != null || range.max != null)) {
        if (range.min != null && range.max != null) {
            return `trình ${formatNumber(range.min)}–${formatNumber(range.max)}`
        }
        if (range.min != null) return `trình ${formatNumber(range.min)}+`
        if (range.max != null) return `trình ≤${formatNumber(range.max)}`
    }
    return ''
})

const statsText = computed(() => {
    const members = `${props.club?.quantity_members ?? 0} thành viên`
    const followers = `${props.club?.followers_count ?? 0} theo dõi`
    const score = scoreText.value
    return score ? `${members} · ${followers} · ${score}` : `${members} · ${followers}`
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
