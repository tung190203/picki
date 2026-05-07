<template>
  <div 
    class="flex items-center justify-between p-4 bg-white rounded-lg shadow-sm border-l-[2px] mb-4 transition cursor-pointer hover:shadow-md hover:border-l-4"
    @click="$emit('click-card', { id, itemType })"
    :class="[
      disabled ? 'border-gray-200 opacity-70 grayscale-[0.5]' : (
        type === 'danger' ? 'border-red-500' : 
        type === 'primary' ? 'border-[#4392E0]' : 'border-[#BBBFCC]'
      )
    ]"
  >
    <div class="flex items-center space-x-4">
      <!-- Date Section -->
      <div 
        class="flex flex-col items-center justify-center w-16 h-16 rounded-md border"
        :class="[
          type === 'danger' ? 'bg-red-50 border-[#EB969B] text-[#D72D36]' : 
          type === 'primary' ? 'bg-blue-50 border-[#4392E0] text-[#4392E0]' : 'bg-gray-50 border-[#BBBFCC] text-[#838799]'
        ]"
      >
        <span class="text-xs font-bold uppercase">{{ day }}</span>
        <span class="text-2xl font-bold">{{ date }}</span>
      </div>

      <!-- Info Section -->
      <div class="flex flex-col space-y-1">
        <h3 class="text-xl font-semi-bold text-[#3E414C]">{{ title }}</h3>
        <div class="flex items-center space-x-4 text-sm text-[#838799]">
          <div class="flex items-center space-x-1">
            <ClockIcon class="w-4 h-4" />
            <span>{{ time }}</span>
          </div>
        </div>
        <div class="flex items-center space-x-4 text-sm text-[#838799]" v-if="address">
          <div class="flex items-center space-x-1">
            <MapPinIcon class="w-4 h-4 flex-shrink-0" />
            <span class="truncate max-w-[450px]" :title="address">{{ address }}</span>
          </div>
        </div>
        <div class="flex items-center space-x-1 text-sm text-[#838799]">
            <UsersIcon class="w-4 h-4" />
            <span>{{ participants }} người tham gia</span>
        </div>
      </div>
    </div>

    <!-- Action Section -->
    <div class="flex flex-col items-end space-y-6">
      <span 
        v-if="status"
        class="text-[10px] font-bold px-2 py-0.5 rounded uppercase whitespace-nowrap h-fit"
        :class="{
            'bg-[#E3F7EF] text-[#2D9B71]': status === 'open',
            'bg-[#F2F7FC] text-[#4392E0]': status === 'private',
            'bg-[#EDEEF2] text-[#838799]': !['open', 'private'].includes(status),
        }"
      >
        {{ statusText || status }}
      </span>
       <div class="flex items-center gap-2">
         <template v-if="registrationStatus === 'pending'">
            <Button 
              size="md" 
              color="secondary"
              class="font-bold px-4 py-3 rounded-[4px] bg-[#EDEEF2] text-[#3E414C] border border-[#DCDEE6] shadow-sm"
              :disabled="disabled"
              @click.stop="$emit('cancel-join')"
            >
              Huỷ tham gia
            </Button>
            <Button 
              size="md" 
              color="secondary"
              class="font-bold px-4 py-3 rounded-[4px] bg-[#EDEEF2] text-[#3E414C] border border-[#DCDEE6] shadow-sm"
              :disabled="true"
            >
              <div class="flex items-center gap-2">
                <ClockIcon class="w-4 h-4" />
                <span>{{ buttonText }}</span>
              </div>
            </Button>
         </template>
         <template v-else-if="registrationStatus === 'accepted'">
            <Button 
              size="md" 
              color="secondary"
              class="font-bold px-4 py-3 rounded-[4px]"
              :disabled="disabled"
              @click.stop="$emit('self-absent')"
            >
              Báo vắng
            </Button>
            <Button 
              size="md" 
              color="primary"
              class="font-bold px-4 py-3 rounded-[4px]"
              :disabled="disabled"
              @click.stop="$emit('check-in')"
            >
              Check-in
            </Button>
         </template>
         <template v-else>
           <Button 
            size="md" 
            :color="registrationStatus === 'attended' ? 'secondary' : buttonColor"
            class="font-bold px-4 py-3 rounded-[4px]"
            :class="{ 'bg-[#EDEEF2] text-[#3E414C] border border-[#DCDEE6] shadow-sm': registrationStatus === 'attended' }"
            :disabled="disabled || registrationStatus === 'attended'"
            @click.stop="registrationStatus === 'attended' ? null : $emit('register')"
          >
            <div class="flex items-center gap-2">
              <span>{{ registrationStatus === 'attended' ? 'Đã check-in' : buttonText }}</span>
            </div>
          </Button>
         </template>
        <Button 
          v-if="isCreator"
          size="md" 
          color="secondary"
          class="font-bold px-3 py-3.5 rounded-[4px]"
          @click.stop="$emit('edit')"
        >
          <PencilIcon class="w-5 h-5 text-[#3E414C]" />
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ClockIcon, MapPinIcon, UsersIcon, PencilIcon } from '@heroicons/vue/24/outline'
import Button from '@/components/atoms/Button.vue'
import { computed } from 'vue'

defineEmits(['click-card', 'edit', 'register', 'cancel-join', 'self-absent', 'check-in'])

const props = defineProps({
  id: {
    type: [Number, String],
    default: null
  },
  itemType: {
    type: String,
    default: null
  },
  day: {
    type: String,
    required: true
  },
  date: {
    type: String,
    required: true
  },
  title: {
    type: String,
    required: true
  },
  time: {
    type: String,
    required: true
  },
  participants: {
    type: String,
    required: true
  },
  status: {
    type: String,
    default: ''
  },
  address: {
    type: String,
    default: ''
  },
  statusText: {
    type: String,
    default: ''
  },
  buttonText: {
    type: String,
    default: 'Đăng ký'
  },
  type: {
    type: String,
    default: 'primary' // 'danger', 'primary', 'secondary'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  isCreator: {
    type: Boolean,
    default: false
  },
  registrationStatus: {
    type: String,
    default: 'none'
  }
})

const buttonColor = computed(() => {
  if (props.type === 'danger') return 'danger'
  if (props.type === 'primary') return 'primary'
  return 'secondary'
})
</script>
