<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="isOpen"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        @click.self="closeModal">
        <div class="bg-white rounded-[32px] shadow-2xl w-full max-w-md max-h-[95vh] sm:max-h-[90vh] overflow-hidden animate-scaleIn relative flex flex-col">
          <!-- Header -->
          <div class="px-4 sm:px-6 py-4 sm:py-5 flex items-center justify-between border-b border-gray-50 flex-shrink-0">
            <h2 class="text-base sm:text-[20px] font-bold text-[#374151]">Thông tin thành viên CLB</h2>
            <button @click="closeModal" class="p-2 text-gray-500 hover:text-gray-700 transition-colors">
              <XMarkIcon class="w-5 h-5 sm:w-7 sm:h-7" />
            </button>
          </div>

          <!-- Content -->
          <div class="p-4 sm:p-6 overflow-y-auto flex-1">
            <!-- Member Header -->
            <div class="flex items-center gap-4 mb-8">
              <div class="relative">
                <img :src="member?.user.avatar_url" :alt="member?.user.full_name"
                  class="w-16 h-16 rounded-full object-cover bg-blue-50">
                <div v-if="getVpScore(member?.user)"
                  class="absolute -bottom-1 -left-1 w-6 h-6 bg-[#4B88FF] rounded-full flex items-center justify-center border-2 border-white text-white text-[10px] font-bold">
                  {{ getVpScore(member?.user) }}
                </div>
              </div>
              <div class="flex flex-col">
                <span class="text-[18px] font-bold text-[#374151]">{{ member?.user.full_name }}</span>
                <span class="text-[14px] text-gray-400 font-medium">Tham gia {{ dayjs(member?.joined_at).fromNow()
                  }}</span>
              </div>
            </div>

            <!-- Role Section -->
            <div class="mb-8">
              <h3 class="text-[14px] font-bold text-[#9EA2B3] uppercase tracking-wider mb-3">VAI TRÒ TRONG CLB</h3>
              <div class="flex items-center gap-4 p-4 border border-[#EDEEF2] rounded-2xl bg-white">
                <div class="w-12 h-12 rounded-full bg-[#F3F4F6] flex items-center justify-center">
                  <UserIcon class="w-6 h-6 text-[#374151]" />
                </div>
                <span class="text-[16px] font-bold text-[#374151]">
                  {{ ROLE_LABELS[member?.role] || 'Thành viên' }}
                </span>
              </div>
            </div>
            <div v-if="specialization" class="mb-8">
              <h3 class="text-[14px] font-bold text-[#9EA2B3] uppercase tracking-wider mb-3">
                PHỤ TRÁCH CHUYÊN MÔN
              </h3>
              <div class="flex items-center gap-2 px-6 py-2.5 rounded-full w-fit"
                :class="[specialization.bg, specialization.text]">
                <component :is="specialization.icon" class="w-5 h-5" />
                <span class="text-[16px] font-bold">
                  {{ specialization.label }}
                </span>
              </div>
            </div>
            <!-- Action Buttons -->
            <div class="space-y-3">
              <button v-if="member.user?.id !== getUser.id" @click="handleFollowAction"
                class="w-full py-3 border-2 border-[#4392E0] text-[#4392E0] bg-[#f2f7fc] font-bold rounded-full flex items-center justify-center gap-2 hover:bg-[#4392E0] hover:text-white transition-all active:scale-[0.98]">
                <component :is="member.user?.is_follow ? UserMinusIcon : UserPlusIcon" class="w-6 h-6" />
                <span>
                  {{
                    member.user?.is_follow
                      ? (member.user?.is_friend ? 'Bạn bè' : 'Đang theo dõi')
                      : 'Theo dõi'
                  }}
                </span>
              </button>
              <button @click="closeModal"
                class="w-full py-3 bg-[#F3F4F6] text-[#374151] font-bold rounded-full transition-all hover:bg-gray-200 active:scale-[0.98]">
                Hủy
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { XMarkIcon, UserIcon, UserPlusIcon, UserMinusIcon } from '@heroicons/vue/24/outline'
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime'
import 'dayjs/locale/vi';
import { ROLE_LABELS, ROLE_SPECIALIZATION } from '@/data/club/index.js';
import { storeToRefs } from 'pinia'
import { useUserStore } from '@/store/auth'
import { followUser, unFollowUser } from '@/service/follow.js'
import { toast } from "vue3-toastify";

const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)

dayjs.extend(relativeTime)
dayjs.locale('vi')

const props = defineProps({
  modelValue: Boolean,
  member: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue'])

const isOpen = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

const closeModal = () => (isOpen.value = false)

const handleFollowAction = () => {
  if (props.member.user?.is_follow) {
    handleUnFollow()
  } else {
    handleFollow()
  }
}

const handleFollow = async () => {
  if (!props.member?.user?.id) return
  try {
    const res = await followUser({
      followable_type: 'user',
      followable_id: props.member.user.id
    })
    if (res && typeof res === 'object') {
      if (res.is_friend !== undefined) props.member.user.is_friend = res.is_friend
      if (res.is_follow !== undefined) props.member.user.is_follow = res.is_follow
    } else {
      props.member.user.is_follow = true
    }
    toast.success('Theo dõi thành công')
    emit('updated')
  } catch (error) {
    console.error('Follow failed:', error)
    toast.error(error.response?.data?.message || 'Theo dõi thất bại')
  }
}

const handleUnFollow = async () => {
  if (!props.member?.user?.id) return
  try {
    const res = await unFollowUser({
      followable_type: 'user',
      followable_id: props.member.user.id
    })
    if (res && typeof res === 'object') {
      if (res.is_friend !== undefined) props.member.user.is_friend = res.is_friend
      if (res.is_follow !== undefined) props.member.user.is_follow = res.is_follow
    } else {
      props.member.user.is_follow = false
      props.member.user.is_friend = false
    }
    toast.success('Hủy theo dõi thành công')
    emit('updated')
  } catch (error) {
    console.error('Unfollow failed:', error)
    toast.error(error.response?.data?.message || 'Hủy theo dõi thất bại')
  }
}

const specialization = computed(() => {
  return ROLE_SPECIALIZATION[props.member?.role] || null
})

const getVpScore = (user) => {
  const pickleball = user?.sports?.find(s => s.sport_id === 1 || s.sport_name === 'Pickleball');
  const score = pickleball?.scores?.vndupr_score;
  return score ? Number(score).toFixed(1) : '0';
}
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

@keyframes scaleIn {
  from {
    transform: scale(0.95);
    opacity: 0;
  }

  to {
    transform: scale(1);
    opacity: 1;
  }
}

.animate-scaleIn {
  animation: scaleIn 0.3s ease-out;
}
</style>
