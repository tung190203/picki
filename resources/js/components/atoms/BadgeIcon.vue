<template>
  <div
    v-if="badge && showBadge"
    class="inline-flex items-center justify-center rounded-full flex-shrink-0 group relative"
    :class="[sizeClass]"
  >
    <div class="flex items-center justify-center w-full h-full rounded-full transition-all" :class="[badgeClass, { 'opacity-40 grayscale': !isUnlocked }]">
      <img v-if="customIconUrl" :src="customIconUrl" :class="[sizeClass, 'object-contain drop-shadow-sm']" />
      <component v-else :is="badgeIcon" :class="iconSizeClass" />
    </div>

    <!-- Rich Hover Tooltip -->
    <div v-if="showHoverCard" class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 pointer-events-none w-max max-w-[250px] bg-white text-gray-800 rounded-xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.15)] border border-gray-100 p-3">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center">
          <img v-if="customIconUrl" :src="customIconUrl" class="w-8 h-8 object-contain drop-shadow-sm" />
          <component v-else :is="badgeIcon" class="w-full h-full p-2 rounded-full" :class="badgeClass" />
        </div>
        <div class="flex flex-col text-left">
          <span class="font-bold text-sm leading-tight text-slate-800">{{ badgeName }}</span>
          <span v-if="badgeDescription" class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ badgeDescription }}</span>
          <span v-if="badgeDate" class="text-[10px] text-slate-400 mt-1 flex items-center gap-1 font-medium">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            {{ badgeDate }}
          </span>
        </div>
      </div>
      <!-- Triangle pointer -->
      <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-white border-b border-r border-gray-100 transform rotate-45"></div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  ShieldCheckIcon,
  StarIcon,
  TrophyIcon,
  SparklesIcon
} from '@heroicons/vue/24/solid'

const props = defineProps({
  // 'verified' | 'anchor' | 'champion' | 'picki' | { type: string, ... }
  badge: {
    type: [String, Object],
    default: null,
  },
  // Show the badge icon
  showBadge: {
    type: Boolean,
    default: true,
  },
  // 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl'
  size: {
    type: String,
    default: 'md',
  },
  // Toggle rich hover card
  showHoverCard: {
    type: Boolean,
    default: true,
  }
})

// Normalize badge to lowercase string key
const badgeKey = computed(() => {
  if (!props.badge) return null
  if (typeof props.badge === 'string') {
    return props.badge.toLowerCase()
  }
  if (typeof props.badge === 'object' && props.badge?.type) {
    return String(props.badge.type).toLowerCase()
  }
  return null
})

const badgeConfig = {
  verified: {
    icon: ShieldCheckIcon,
    class: 'bg-green-500 text-white',
    label: 'Đã xác minh',
  },
  anchor: {
    icon: StarIcon,
    class: 'bg-[#4392E0] text-white',
    label: 'Anchor',
  },
  champion: {
    icon: TrophyIcon,
    class: 'bg-yellow-500 text-white',
    label: 'Vô địch',
  },
  picki: {
    icon: SparklesIcon,
    class: 'bg-gradient-to-br from-pink-500 to-purple-500 text-white',
    label: 'Picki Team',
  },
}

const badgeInfo = computed(() => badgeConfig[badgeKey.value] || null)

const customIconUrl = computed(() => {
  if (typeof props.badge === 'object' && props.badge?.icon_url) {
    return props.badge.icon_url
  }
  return null
})

const badgeIcon = computed(() => badgeInfo.value?.icon || StarIcon)
const badgeClass = computed(() => customIconUrl.value ? 'bg-transparent' : (badgeInfo.value?.class || 'bg-gray-400 text-white'))

const badgeName = computed(() => {
  if (typeof props.badge === 'object' && props.badge?.name) {
    return props.badge.name;
  }
  return badgeInfo.value?.label || badgeKey.value || 'Huy hiệu';
})

const badgeDescription = computed(() => {
  if (typeof props.badge === 'object' && props.badge?.description) {
    return props.badge.description;
  }
  return '';
})

const badgeDate = computed(() => {
  if (typeof props.badge === 'object' && props.badge?.acquired_at) {
    const d = new Date(props.badge.acquired_at);
    if (!isNaN(d)) {
      return `${d.getDate().toString().padStart(2, '0')}/${(d.getMonth() + 1).toString().padStart(2, '0')}/${d.getFullYear()}`;
    }
  }
  return '';
})

const isUnlocked = computed(() => {
  if (typeof props.badge === 'object' && props.badge !== null) {
    if (props.badge.is_unlocked === false) return false;
  }
  return true;
})

const sizeConfig = {
  sm: { wrapper: 'w-4 h-4', icon: 'w-2.5 h-2.5' },
  md: { wrapper: 'w-5 h-5', icon: 'w-3 h-3' },
  lg: { wrapper: 'w-6 h-6', icon: 'w-4 h-4' },
  xl: { wrapper: 'w-10 h-10', icon: 'w-6 h-6' },
  '2xl': { wrapper: 'w-16 h-16', icon: 'w-10 h-10' },
  '3xl': { wrapper: 'w-24 h-24', icon: 'w-14 h-14' },
}

const sizeClass = computed(() => sizeConfig[props.size]?.wrapper || sizeConfig.md.wrapper)
const iconSizeClass = computed(() => sizeConfig[props.size]?.icon || sizeConfig.md.icon)
</script>
