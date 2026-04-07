<template>
  <div class="flex flex-col items-center gap-2" :style="{ maxWidth: `${maxWidth}px` }">
    <div class="relative group">
      <div :class="`w-${computedSize} h-${computedSize} rounded-full overflow-hidden`">
        <img :src="avatar || defaultImage" :alt="name" @error="handleImageError"
          class="w-full h-full object-cover cursor-pointer hover:scale-110 transition-transform duration-300" />
      </div>

      <div v-if="showActions"
        class="absolute bottom-0 left-1/2 -translate-x-1/2 translate-y-full flex gap-1 mt-1 z-10">
        <button @click.stop="$emit('confirm')"
          class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center hover:bg-green-600 transition-colors shadow"
          v-tooltip="'Xác nhận'">
          <CheckIcon class="w-3 h-3" />
        </button>
        <button @click.stop="$emit('reject')"
          class="w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors shadow"
          v-tooltip="'Từ chối'">
          <XMarkIcon class="w-3 h-3" />
        </button>
      </div>
    </div>

    <div
      v-if="name"
      class="text-sm font-medium text-gray-700 text-center leading-4 max-w-[80px] overflow-hidden break-words"
      :style="{
        display: '-webkit-box',
        WebkitLineClamp: 2,
        WebkitBoxOrient: 'vertical',
        minHeight: '32px',
        maxWidth: `${maxWidth}px`
      }"
      v-tooltip="name"
    >
      {{ name }}
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  id: {
    type: [Number, String],
    required: true,
  },
  name: {
    type: String,
    default: '',
  },
  avatar: {
    type: String,
    default: '',
  },
  size: {
    type: Number,
    default: 16,
  },
  maxWidth: {
    type: Number,
    default: 80,
  },
  showActions: {
    type: Boolean,
    default: true,
  },
  defaultImage: {
    type: String,
    default: '',
  },
})

defineEmits(['confirm', 'reject'])

const computedSize = computed(() => `${props.size}`)

const handleImageError = (event) => {
  if (props.defaultImage) {
    event.target.src = props.defaultImage
  }
}
</script>
