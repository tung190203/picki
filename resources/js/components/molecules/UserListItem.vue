<template>
    <div
      @click="$emit('select', user)"
      :class="[
        'border rounded-lg cursor-pointer transition-all overflow-hidden flex h-fit p-2 items-center gap-3',
        user.id === selected
          ? 'border-red-500 shadow-md ring-1 ring-red-500'
          : 'border-gray-200 shadow-sm'
      ]"
    >
      <UserCard
        :avatar="user.avatar_url"
        :show-hover-delete="false"
        :rating="getUserRating(user)"
        :defaultImage="defaultImage"
      />

      <div class="flex-1 min-w-0 flex flex-col justify-start gap-1">
        <div class="flex justify-start items-center gap-2">
          <h3
            class="font-semibold text-gray-900 text-base leading-tight truncate"
            v-tooltip="user.full_name"
          >
            {{ user.full_name }}
          </h3>

          <span
            class="px-2 py-1 rounded text-xs font-medium capitalize whitespace-nowrap"
            :class="{
              'bg-green-100 text-green-700': user.visibility === 'open',
              'bg-yellow-100 text-yellow-700': user.visibility === 'friend-only',
              'bg-red-100 text-red-700': user.visibility === 'private'
            }"
          >
            {{ getVisibilityText(user.visibility) }}
          </span>
        </div>

        <div class="flex items-center gap-1.5 text-xs text-gray-600 truncate">
          <component :is="user.gender == 1 ? maleIcon : femaleIcon" class="w-4 h-4" />
          <span class="truncate">
            {{ user.gender_text || 'Khác' }}
            {{ user.age_group ? ' • ' + user.age_group : '' }}
          </span>
        </div>

        <!-- Stats row: distance | vndupr | win_rate -->
        <div class="flex items-center gap-3 mt-0.5">
          <span v-if="user.distance != null" class="flex items-center gap-1 text-xs text-[#4392E0] font-medium">
            <MapPinIcon class="w-3 h-3" />
            {{ user.distance }} km
          </span>
          <span v-if="user.vndupr_score != null" class="flex items-center gap-1 text-xs text-orange-600 font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
            </svg>
            VNDUPR {{ Number(user.vndupr_score).toFixed(1) }}
          </span>
          <span v-if="user.win_rate != null" class="flex items-center gap-1 text-xs text-green-600 font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
            </svg>
            {{ Number(user.win_rate).toFixed(1) }}%
          </span>
        </div>

        <div class="mt-2" v-if="!isOwnUser">
          <button
            @click.stop="$emit('toggle-follow', user)"
            :class="[
              'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors',
              user.is_follow
                ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-300'
                : 'bg-[#D72D36] text-white hover:bg-[#c22830] border border-[#D72D36]'
            ]"
          >
            <component :is="user.is_follow ? UserMinusIcon : UserPlusIcon" class="w-4 h-4" />
            {{ user.is_follow ? 'Hủy follow' : 'Follow' }}
          </button>
        </div>
      </div>

      <div class="flex-shrink-0 w-1/4">
        <p class="text-xs text-[#207AD5] line-clamp-2 break-words" v-tooltip="user.address">
          {{ user.address }}
        </p>
      </div>
    </div>
  </template>

  <script setup>
    import { computed } from 'vue'
    import UserCard from '@/components/molecules/UserCard.vue';
    import { MapPinIcon, UserPlusIcon, UserMinusIcon } from '@heroicons/vue/24/outline';
    import { storeToRefs } from 'pinia'
    import { useUserStore } from '@/store/auth'
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
  