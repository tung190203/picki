<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="isOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                @click.self="closeModal">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-sm overflow-hidden animate-scaleIn">
                    <!-- Header -->
                    <header class="flex items-center justify-between p-4 border-b">
                        <h2 class="text-lg font-semibold text-gray-800">
                            Thông tin thành viên
                        </h2>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </header>

                    <!-- Member Info -->
                    <section class="p-5">
                        <div class="flex flex-col items-center text-center mb-5">
                            <div class="relative mb-3">
                                <img :src="memberAvatar" :alt="memberName"
                                    class="w-20 h-20 rounded-full object-cover bg-gray-100"
                                    @error="event => event.target.src = defaultAvatar" />
                                <!-- Status badge -->
                                <div v-if="memberStatus !== 'normal'"
                                    class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full flex items-center justify-center border-2 border-white text-white text-[9px] font-bold"
                                    :class="statusBadgeClass">
                                    <CheckBadgeIcon v-if="memberStatus === 'checked_in'" class="w-4 h-4" />
                                    <XCircleIcon v-else-if="memberStatus === 'absent'" class="w-4 h-4" />
                                </div>
                            </div>
                            <h3 class="text-base font-bold text-gray-800 mb-0.5">{{ memberName }}</h3>
                            <p class="text-sm text-gray-500 mb-1">{{ memberRating }}</p>
                            <span class="text-xs px-3 py-1 rounded-full font-medium"
                                :class="statusTextClass">
                                {{ statusText }}
                            </span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="space-y-2.5">
                            <button @click="handleViewProfile"
                                class="w-full py-2.5 px-4 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition flex items-center justify-center gap-2 font-medium text-sm">
                                <UserIcon class="w-5 h-5" />
                                Xem hồ sơ
                            </button>

                            <!-- Organizer actions: Check-in / Báo vắng cho VĐV -->
                            <template v-if="isOrganizer">
                                <button v-if="canCheckIn" @click="handleCheckIn"
                                    class="w-full py-2.5 px-4 rounded-lg bg-green-600 text-white hover:bg-green-700 transition flex items-center justify-center gap-2 font-medium text-sm">
                                    <CheckIcon class="w-5 h-5" />
                                    Check-in
                                </button>
                                <button v-if="canMarkAbsent" @click="handleAbsent"
                                    class="w-full py-2.5 px-4 rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition flex items-center justify-center gap-2 font-medium text-sm">
                                    <XMarkIcon class="w-5 h-5" />
                                    Báo vắng
                                </button>
                            </template>

                            <!-- Participant actions: Tự check-in / Tự báo vắng (chỉ khi chính mình) -->
                            <template v-if="isSelfParticipant">
                                <button v-if="canSelfCheckIn" @click="handleSelfCheckIn"
                                    class="w-full py-2.5 px-4 rounded-lg bg-green-600 text-white hover:bg-green-700 transition flex items-center justify-center gap-2 font-medium text-sm">
                                    <CheckIcon class="w-5 h-5" />
                                    Tự check-in
                                </button>
                                <button v-if="canSelfMarkAbsent" @click="handleSelfAbsent"
                                    class="w-full py-2.5 px-4 rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition flex items-center justify-center gap-2 font-medium text-sm">
                                    <XMarkIcon class="w-5 h-5" />
                                    Tự báo vắng
                                </button>
                            </template>
                        </div>
                    </section>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import {
    XMarkIcon, UserIcon, CheckIcon, CheckBadgeIcon, XCircleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: Boolean,
    member: { type: Object, default: null },
    currentUser: { type: Object, default: null },
    tournamentType: { type: String, default: 'tournament' },
    isCurrentUserOrganizer: { type: Boolean, default: false },
    isCurrentUserParticipant: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'view-profile', 'check-in', 'absent', 'self-check-in', 'self-absent'])

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
})

const closeModal = () => (isOpen.value = false)

const defaultAvatar = 'https://ui-avatars.com/api/?name=User&background=f3f4f6&color=374151&size=128'

const memberName = computed(() => {
    if (!props.member) return ''
    if (props.member.is_guest) return props.member.guest_name || 'Khách'
    return props.member.user?.full_name || props.member.name || ''
})

const memberAvatar = computed(() => {
    if (!props.member) return defaultAvatar
    if (props.member.is_guest) return props.member.guest_avatar || defaultAvatar
    return props.member.user?.avatar_url || props.member.avatar || defaultAvatar
})

const memberRating = computed(() => {
    if (!props.member) return ''
    const sport = props.member.user?.sports?.find(s =>
        s.sport_name === 'Pickleball' || s.sport_id === 1
    )
    const score = sport?.scores?.vndupr_score || sport?.scores?.dupr_score
    return score ? `Rating: ${Number(score).toFixed(1)}` : ''
})

const memberStatus = computed(() => {
    if (!props.member) return 'normal'
    if (props.member.checked_in_at && !props.member.is_absent) return 'checked_in'
    if (props.member.is_absent) return 'absent'
    if (props.member.is_confirmed) return 'confirmed'
    return 'pending'
})

const statusText = computed(() => {
    const map = {
        checked_in: 'Đã check-in',
        absent: 'Vắng mặt',
        confirmed: 'Đã xác nhận',
        pending: 'Chờ xác nhận',
    }
    return map[memberStatus.value] || ''
})

const statusTextClass = computed(() => {
    const map = {
        checked_in: 'bg-green-100 text-green-700',
        absent: 'bg-red-100 text-red-700',
        confirmed: 'bg-blue-100 text-blue-700',
        pending: 'bg-gray-100 text-gray-500',
    }
    return map[memberStatus.value] || 'bg-gray-100 text-gray-500'
})

const statusBadgeClass = computed(() => {
    const map = {
        checked_in: 'bg-green-500',
        absent: 'bg-red-500',
    }
    return map[memberStatus.value] || ''
})

const isOrganizer = computed(() => props.isCurrentUserOrganizer)
const isSelfParticipant = computed(() => {
    if (!props.member || !props.currentUser) return false
    const memberUserId = props.member.user?.id
    return props.isCurrentUserParticipant && memberUserId === props.currentUser.id
})

const canCheckIn = computed(() => {
    if (!props.member) return false
    return !props.member.checked_in_at && !props.member.is_absent
})

const canMarkAbsent = computed(() => {
    if (!props.member) return false
    return !props.member.is_absent && !props.member.checked_in_at
})

const canSelfCheckIn = computed(() => {
    if (!props.member) return false
    return !props.member.checked_in_at && !props.member.is_absent
})

const canSelfMarkAbsent = computed(() => {
    if (!props.member) return false
    return !props.member.is_absent && !props.member.checked_in_at
})

const handleViewProfile = () => {
    emit('view-profile', props.member)
    closeModal()
}

const handleCheckIn = () => {
    emit('check-in', props.member)
    closeModal()
}

const handleAbsent = () => {
    emit('absent', props.member)
    closeModal()
}

const handleSelfCheckIn = () => {
    emit('self-check-in', props.member)
    closeModal()
}

const handleSelfAbsent = () => {
    emit('self-absent', props.member)
    closeModal()
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.25s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

@keyframes scaleIn {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.animate-scaleIn {
    animation: scaleIn 0.25s ease;
}
</style>
