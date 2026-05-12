<template>
    <div
      @click="$emit('select', club)"
      :class="[
        'border rounded-lg cursor-pointer transition-all overflow-hidden flex items-center px-2 py-2',
        club.id === selected
          ? 'border-red-500 shadow-md ring-1 ring-red-500'
          : 'border-gray-200 shadow-sm'
      ]"
    >
      <div class="w-14 h-14 flex-shrink-0 relative overflow-hidden bg-gray-100 rounded-full">
        <img
          :src="club.logo_url || defaultImage"
          :alt="club.name || 'Club Logo'"
          @error="e => e.target.src = defaultImage"
          class="absolute inset-0 w-full h-full object-cover"
        />
      </div>

      <div class="flex-1 min-w-0 pl-3">
        <h3 class="font-semibold text-gray-900 text-base line-clamp-1" v-tooltip="club.name">
          {{ club.name }}
        </h3>

        <div class="flex items-center gap-1.5 mt-1 text-sm text-gray-600">
          <MapPinIcon class="w-4 h-4 text-[#4392E0] flex-shrink-0" />
          <span class="line-clamp-1" v-tooltip="club.address || 'Chưa có địa chỉ'">
            {{ club.address || 'Chưa có địa chỉ' }}
          </span>
        </div>

        <div v-if="club.members_count !== undefined || club.quantity_members !== undefined" class="flex items-center gap-1.5 mt-1 text-sm text-gray-500">
          <UserGroupIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
          <span>{{ (club.members_count ?? club.quantity_members) }} thành viên</span>
        </div>
      </div>
    </div>
</template>

<script setup>
    import { MapPinIcon, UserGroupIcon } from '@heroicons/vue/24/outline'

    defineProps({
      club: {
        type: Object,
        required: true
      },
      selected: [String, Number],
      defaultImage: String,
    })

    defineEmits(['select'])
</script>
