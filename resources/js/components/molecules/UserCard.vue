<template>
  <div class="flex flex-col items-center gap-2" :style="{ maxWidth: `${maxWidth}px` }">
    <div class="relative group">
      <div v-if="empty" @click="handleClick"
        :class="[
          `w-${computedSize} h-${computedSize}`,
          'border-2 border-dashed border-gray-400 rounded-full flex items-center justify-center bg-white cursor-pointer hover:border-blue-500 transition-colors'
        ]">
        <PlusIcon :class="`w-${iconSize} h-${iconSize} text-gray-400`" />
      </div>

      <div v-else :class="`w-${computedSize} h-${computedSize} rounded-full overflow-hidden`">
        <img :src="avatar || defaultImage" :alt="name" @error="event.target.src=defaultImage"
          class="w-full h-full object-cover cursor-pointer hover:scale-110 transition-transform duration-300" />
      </div>

      <button
        v-if="!empty && showHoverDelete && flagDelete"
        @click.stop="showModal = true"
        class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center border-2 border-white
              opacity-0 group-hover:opacity-100 transition-opacity">
        <XMarkIcon class="w-3 h-3" />
      </button>

      <div v-if="!empty && rating"
        :class="[
          `absolute -bottom-1 -left-1 w-${badgeSize} h-${badgeSize}`,
          'bg-blue-500 text-white font-bold rounded-full flex items-center justify-center border-2 border-white'
        ]"
        :style="{ fontSize: ratingSize + 'px' }"
        >
        {{ rating }}
      </div>

      <div v-if="!empty && status"
        :class="[
          `absolute -bottom-1 -right-1 w-${badgeSize} h-${badgeSize} rounded-full flex items-center justify-center border-2 border-white`,
          statusColor
        ]">
        <CheckIcon v-if="status === 'approved'" :class="`w-${iconInnerSize} h-${iconInnerSize} text-white`" />
        <QuestionMarkCircleIcon v-else-if="status === 'pending'" :class="`w-${iconInnerSize} h-${iconInnerSize} text-white`" />
        <XMarkIcon v-else-if="status === 'rejected'" :class="`w-${iconInnerSize} h-${iconInnerSize} text-white`" />
      </div>

      <button v-if="empty" @click="handleClick"
        :class="[
          `absolute -bottom-1 -right-1 w-${badgeSize} h-${badgeSize}`,
          'bg-blue-500 text-white rounded-full hover:bg-blue-600 transition-colors flex items-center justify-center border-2 border-white'
        ]">
        <PencilIcon :class="`w-${iconInnerSize} h-${iconInnerSize}`" />
      </button>
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
  <DeleteConfirmationModal
    v-model="showModal"
    title="Xóa người dùng"
    :message="`Bạn có chắc muốn xoá ${name}?`"
    confirmButtonText="Xóa"
    confirmButtonClass="bg-red-600 hover:bg-red-700"
    @confirm="confirmDelete"
  />
</template>

<script setup>
import { computed, ref } from 'vue'
import { PlusIcon, PencilIcon, CheckIcon, QuestionMarkCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import DeleteConfirmationModal from '@/components/molecules/DeleteConfirmationModal.vue'

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
  rating: {
    type: [Number, String],
    default: null,
  },
  status: {
    type: String,
    default: '',
  },
  empty: {
    type: Boolean,
    default: false,
  },
  size: {
    type: Number,
    default: 16,
  },
  maxWidth: {
    type: Number,
    default: 80,
  },
  badgeSize: {
    type: Number,
    default: 6, // Default badge size (tailwind unit)
  },
  ratingSize: {
    type: Number,
    default: 11,
  },
  showHoverDelete: {
    type: Boolean,
    default: true,
  },
  defaultImage: {
    type: String,
    default: false,
  },
    userId: {
        type: [Number, String],
        default: null,
    },
    flagDelete: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['clickEmpty', 'removeUser'])

const showModal = ref(false)

const confirmDelete = () => {
  emit('removeUser', {
    id: props.id,
    userId: props.userId ?? props.id,
    name: props.name,
    avatar: props.avatar,
  })
}

const handleClick = () => {
  if (props.empty) emit('clickEmpty')
}

const computedSize = computed(() => `${props.size}`)
const iconSize = computed(() => `${Math.floor(props.size / 2)}`)
const badgeSize = computed(() => `${props.badgeSize}`)
const iconInnerSize = computed(() => `${Math.floor(props.badgeSize / 1.5)}`)
const statusColor = computed(() => {
  if (props.status === 'approved') return 'bg-green-500'
  if (props.status === 'pending') return 'bg-yellow-400'
  if (props.status === 'rejected') return 'bg-red-500'
  return 'bg-gray-400'
})
</script>
