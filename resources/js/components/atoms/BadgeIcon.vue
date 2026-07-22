<template>
  <!-- Primary badge (highest priority) -->
  <div
    v-if="badge && showBadge"
    class="inline-flex items-center justify-center rounded-full flex-shrink-0"
    :class="[badgeClass, sizeClass]"
    :title="badgeTooltip"
    v-tooltip="badgeTooltip"
  >
    <component :is="badgeIcon" :class="iconSizeClass" />
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
  // 'sm' | 'md' | 'lg'
  size: {
    type: String,
    default: 'md',
  },
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

const badgeIcon = computed(() => badgeInfo.value?.icon || StarIcon)
const badgeClass = computed(() => badgeInfo.value?.class || 'bg-gray-400 text-white')
const badgeTooltip = computed(() => badgeInfo.value?.label || '')

const sizeConfig = {
  sm: { wrapper: 'w-4 h-4', icon: 'w-2.5 h-2.5' },
  md: { wrapper: 'w-5 h-5', icon: 'w-3 h-3' },
  lg: { wrapper: 'w-6 h-6', icon: 'w-4 h-4' },
}

const sizeClass = computed(() => sizeConfig[props.size]?.wrapper || sizeConfig.md.wrapper)
const iconSizeClass = computed(() => sizeConfig[props.size]?.icon || sizeConfig.md.icon)
</script>
