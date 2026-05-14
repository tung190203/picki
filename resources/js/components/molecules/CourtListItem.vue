<template>
    <div
      @click="$emit('select', court)"
      :class="[
        'border rounded-lg cursor-pointer transition-all overflow-hidden flex h-fit px-2 items-center',
        court.id === selected
          ? 'border-red-500 shadow-md ring-1 ring-red-500'
          : 'border-gray-200 shadow-sm'
      ]"
    >
      <div class="w-28 h-28 flex-shrink-0 relative overflow-hidden bg-gray-100 rounded-md">
        <img
          :src="court.image || defaultImage"
          @error="e => e.target.src = defaultImage"
          class="absolute inset-0 w-full h-full object-cover"
        />
      </div>

      <div class="flex-1 min-w-0 p-3">
        <h3 class="font-semibold text-gray-900 text-base line-clamp-2" v-tooltip="court.name">
          {{ court.name }}
        </h3>

        <div class="space-y-2 mt-1 text-sm text-gray-600">
          <div class="flex items-center gap-1.5">
            <ClockIcon class="w-5 h-5 text-[#4392E0]" />
            <span>
              Giờ mở cửa: {{ toHourMinute(court.opening_time) }} - {{ toHourMinute(court.closing_time) }}
            </span>
          </div>

          <div class="flex items-center gap-1.5">
            <PhoneIcon class="w-5 h-5 text-[#4392E0]" />
            <span>{{ court.phone }}</span>
          </div>

          <div class="flex items-center gap-1.5">
            <MapPinIcon class="w-5 h-5 text-[#4392E0]" />
            <span class="line-clamp-1" v-tooltip="court.address">
              {{ court.address }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
    import { ClockIcon, PhoneIcon, MapPinIcon } from '@heroicons/vue/24/outline'
    
    defineProps({
      court: {
        type: Object,
        required: true
      },
      selected: [String, Number],
      defaultImage: String,
      toHourMinute: {
        type: Function,
        default: v => v
      }
    })
    
    defineEmits(['select'])
    </script>
    
  