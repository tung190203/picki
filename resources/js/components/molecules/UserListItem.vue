<template>
  <div
    @click="$emit('select', user)"
    :class="[
      'border rounded-xl cursor-pointer transition-all overflow-hidden flex h-fit p-3 gap-3 bg-white dark:bg-slate-900',
      user.id === selected
        ? 'border-primary shadow-md ring-1 ring-primary'
        : 'border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-md hover:border-gray-300 dark:hover:border-slate-600'
    ]"
  >
    <!-- Avatar + online dot -->
    <div class="relative flex-shrink-0">
      <UserCard
        :avatar="user.avatar_url"
        :show-hover-delete="false"
        :rating="getUserRating(user)"
        :defaultImage="defaultImage"
      />
      <span
        v-if="user.is_online"
        class="absolute bottom-0.5 right-0.5 w-3 h-3 bg-green-500 border-2 border-white dark:border-slate-900 rounded-full"
        :title="'Đang online'"
      />
    </div>

    <div class="flex-1 min-w-0 flex flex-col gap-1.5">
      <!-- Row 1: name + visibility + badges -->
      <div class="flex items-center gap-2 flex-wrap">
        <h3
          class="font-semibold text-gray-900 dark:text-slate-100 text-base leading-tight truncate"
          v-tooltip="user.full_name"
        >
          {{ user.full_name }}
        </h3>

        <span
          v-if="user.visibility"
          class="px-2 py-0.5 rounded text-xs font-medium capitalize whitespace-nowrap"
          :class="{
            'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300': user.visibility === 'open',
            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300': user.visibility === 'friend-only',
            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300': user.visibility === 'private'
          }"
        >
          {{ getVisibilityText(user.visibility) }}
        </span>

        <span
          v-if="user.primary_badge"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 whitespace-nowrap"
        >
          🏅 {{ user.primary_badge }}
        </span>
      </div>

      <!-- Row 2: gender/age/club -->
      <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-slate-400 truncate">
        <component :is="user.gender == 1 ? maleIcon : femaleIcon" class="w-4 h-4" />
        <span class="truncate">
          {{ user.gender_text || 'Khác' }}
          {{ user.age_group ? ' • ' + user.age_group : '' }}
          <template v-if="user.clubs && user.clubs.length">
            • <span class="text-gray-700 dark:text-slate-300">{{ user.clubs[0].name }}</span>
            <span v-if="user.clubs.length > 1" class="text-gray-500 dark:text-slate-500"> +{{ user.clubs.length - 1 }}</span>
          </template>
        </span>
      </div>

      <!-- Row 3: stats (distance | vndupr | vn_rank | win_rate | matches) -->
      <div class="flex items-center gap-3 flex-wrap">
        <span v-if="user.distance != null" class="flex items-center gap-1 text-xs text-[#4392E0] dark:text-sky-400 font-medium">
          <MapPinIcon class="w-3 h-3" />
          {{ user.distance }} km
        </span>
        <span v-if="user.vndupr_score != null" class="flex items-center gap-1 text-xs text-orange-600 dark:text-orange-400 font-medium">
          <StarIconSolid class="w-3 h-3" />
          VNDUPR {{ Number(user.vndupr_score).toFixed(3) }}
        </span>
        <span v-if="user.vn_rank != null" class="flex items-center gap-1 text-xs text-purple-600 dark:text-purple-400 font-medium">
          <TrophyIcon class="w-3 h-3" />
          #{{ user.vn_rank }}
        </span>
        <span v-if="user.win_rate != null" class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400 font-medium">
          <ChartIcon class="w-3 h-3" />
          {{ Number(user.win_rate).toFixed(1) }}%
        </span>
        <span v-if="user.total_matches != null" class="flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 font-medium">
          <HashtagIcon class="w-3 h-3" />
          {{ user.total_matches }} trận
        </span>
      </div>

      <!-- Row 4: follow button -->
      <div class="mt-1" v-if="!isOwnUser">
        <button
          @click.stop="$emit('toggle-follow', user)"
          :class="[
            'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors',
            user.is_follow
              ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-300 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700'
              : 'bg-primary text-white hover:bg-[#c22830] border border-primary'
          ]"
        >
          <component :is="user.is_follow ? UserMinusIcon : UserPlusIcon" class="w-4 h-4" />
          {{ user.is_follow ? 'Hủy follow' : 'Follow' }}
        </button>
      </div>
    </div>

    <!-- Right: address -->
    <div class="flex-shrink-0 w-1/4 min-w-[80px]">
      <p
        v-if="user.address"
        class="text-xs text-[#207AD5] dark:text-sky-400 line-clamp-3 break-words"
        v-tooltip="user.address"
      >
        {{ user.address }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import UserCard from '@/components/molecules/UserCard.vue';
import {
  MapPinIcon,
  UserPlusIcon,
  UserMinusIcon,
  TrophyIcon,
  HashtagIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid';
import { storeToRefs } from 'pinia'
import { useUserStore } from '@/store/auth'

// Inline icons (no extra import)
const ChartIcon = {
  template: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>`
}

const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)
const props = defineProps({
  user: {
    type: Object,
    required: true
  },
  selected: [String, Number],
  defaultImage: String,
  maleIcon: String,
  femaleIcon: String,
  getUserRating: {
    type: Function,
    required: true
  },
  getVisibilityText: {
    type: Function,
    required: true
  }
})

const isOwnUser = computed(() => Number(props.user?.id) === Number(getUser.value?.id))

defineEmits(['select', 'toggle-follow'])
</script>
