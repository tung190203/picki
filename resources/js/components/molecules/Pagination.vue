<template>
  <div v-if="meta?.last_page > 1" class="flex justify-center items-center gap-2 mt-8">
    <button @click="changePage(meta.current_page - 1)" :disabled="meta.current_page === 1"
      class="p-2 rounded-lg border border-gray-200 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors">
      <ChevronLeftIcon class="w-5 h-5 text-gray-600" />
    </button>
    
    <div class="flex items-center gap-1">
      <template v-for="page in visiblePages" :key="page">
        <button v-if="page !== '...'" @click="changePage(page)"
          class="w-10 h-10 rounded-lg flex items-center justify-center font-semibold text-sm transition-all"
          :class="meta.current_page === page ? 'bg-[#D72D36] text-white' : 'text-gray-600 hover:bg-gray-100 border border-transparent'">
          {{ page }}
        </button>
        <span v-else class="px-2 text-gray-400">...</span>
      </template>
    </div>

    <button @click="changePage(meta.current_page + 1)" :disabled="meta.current_page === meta.last_page"
      class="p-2 rounded-lg border border-gray-200 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors">
      <ChevronRightIcon class="w-5 h-5 text-gray-600" />
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
  meta: {
    type: Object,
    required: true,
    default: () => ({
      current_page: 1,
      last_page: 1,
      total: 0
    })
  },
  delta: {
    type: Number,
    default: 2
  }
})

const emit = defineEmits(['page-change'])

const visiblePages = computed(() => {
  const current = props.meta?.current_page ?? 1
  const last = props.meta?.last_page ?? 1
  const delta = props.delta
  const range = []
  const rangeWithDots = []
  let l

  range.push(1)
  for (let i = current - delta; i <= current + delta; i++) {
    if (i < last && i > 1) {
      range.push(i)
    }
  }
  if (last > 1) range.push(last)

  for (let i of range) {
    if (l) {
      if (i - l === 2) {
        rangeWithDots.push(l + 1)
      } else if (i - l !== 1) {
        rangeWithDots.push('...')
      }
    }
    rangeWithDots.push(i)
    l = i
  }

  return rangeWithDots
})

const changePage = (page) => {
  if (page >= 1 && page <= (props.meta?.last_page ?? 1)) {
    emit('page-change', page)
  }
}
</script>
