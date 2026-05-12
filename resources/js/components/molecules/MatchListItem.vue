<template>
  <div @click="$emit('select', match)" :class="[
    'border rounded-lg cursor-pointer transition-all overflow-hidden flex flex-col h-fit bg-white hover:shadow-lg',
    match.id === selected
      ? 'border-red-500 shadow-md ring-1 ring-red-500'
      : 'border-gray-200 shadow-sm'
  ]">
    <!-- GIAI DAU (Tournament) Layout -->
    <template v-if="match.type === 'tournament'">
      <div class="flex items-start p-3 gap-3">
        <div class="w-28 h-28 flex-shrink-0 bg-gray-100 rounded-md overflow-hidden border border-gray-100">
          <img :src="match.poster || defaultImage" @error="e => e.target.src = defaultImage"
            class="w-full h-full object-cover" />
        </div>

        <div class="flex-1 min-w-0 flex flex-col justify-between h-20">
          <div>
            <h4 class="font-bold text-gray-900 text-sm line-clamp-2 leading-tight mb-1">
              {{ match.name }}
            </h4>
          </div>

          <div class="space-y-1 pt-2">
            <div class="flex items-center gap-1.5 text-xs text-gray-600" v-if="match.competition_location">
              <MapPinIcon class="w-4 h-4 text-[#4392e0]" />
              <span class="line-clamp-1 font-medium">
                {{ match.competition_location.name || match.competition_location.address }}
              </span>
            </div>
            <div class="flex items-center gap-1.5 text-xs text-gray-600">
              <CalendarIcon class="w-4 h-4 text-[#4392e0]" />
              <span class="font-medium capitalization">
                {{ formatDateText(match.start_date || match.starts_at) }}
              </span>
            </div>

            <!-- Description / Rules if available -->
            <p v-if="match.description || match.rules" class="text-xs text-gray-500 font-medium line-clamp-2 mb-1">
              {{ match.description || match.rules }}
            </p>
          </div>
        </div>
      </div>
    </template>

    <!-- KEO DAU (Mini) Layout -->
    <template v-else>
      <div class="p-3">
        <div class="flex justify-between items-start gap-2">
          <!-- Left Content -->
          <div class="flex-1 min-w-0 space-y-2">
            <div>
              <h3 class="font-bold text-gray-900 text-md line-clamp-2 leading-snug">
                {{ match.name }}
              </h3>
              <div class="flex items-center gap-1 mt-1 text-xs text-blue-500 font-medium"
                v-if="match.competition_location">
                <MapPinIcon class="w-4 h-4" />
                <span class="line-clamp-1">
                  {{ match.competition_location.name || match.competition_location.address }}
                </span>
              </div>
            </div>

            <!-- Date & Time -->
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-gray-600">
                <CalendarDaysIcon class="w-4 h-4 text-blue-500" />
                <span class="font-medium capitalization">{{ formatDateText(match.starts_at) }}</span>
              </div>
              <div class="flex items-center gap-2 text-xs text-gray-600">
                <ClockIcon class="w-4 h-4 text-blue-500" />
                <span class="font-medium">{{ formatTimeRange(match.starts_at, match.duration_minutes) }}</span>
              </div>
            </div>
          </div>

          <!-- Right: Participants & Badge -->
          <div class="flex flex-col items-end gap-3">
            <!-- Avatar Group (Mock/Real) -->
            <div class="flex -space-x-2 overflow-hidden py-1"
              v-if="match.participants && match.participants.length > 0">
              <img v-for="(p, idx) in match.participants.slice(0, 2)" :key="idx" :src="p.avatar_url || defaultImage"
                class="inline-block h-12 w-12 rounded-full ring-2 ring-white object-cover" />
              <div v-if="match.participants.length > 3"
                class="flex items-center justify-center h-8 w-8 rounded-full ring-2 ring-white bg-red-50 text-[#D72D36] text-xs font-bold">
                +{{ match.participants.length - 3 }}
              </div>
            </div>
            <!-- Placeholder if no participants yet but showing join count -->
            <div v-else-if="match.joined_count > 0"
              class="flex items-center justify-center h-8 w-8 rounded-full bg-red-50 text-[#D72D36] text-xs font-bold border border-red-100">
              +{{ match.joined_count }}
            </div>
          </div>
        </div>
        <!-- Creator -->
        <!-- Creator & Badges Footer -->
        <div class="flex items-center mt-2 pt-2 border-t border-gray-100 h-10">
          <!-- Creator (60%) -->
          <div class="w-[60%] flex items-center gap-2 pr-2 border-gray-100 h-full">
            <div class="flex items-center gap-2" v-if="match.staff?.organizer?.[0]?.user">
              <img 
                :src="match.staff.organizer[0].user.avatar_url || defaultImage"
                @error="e => e.target.src = defaultImage"
                class="w-6 h-6 rounded-full object-cover ring-1 ring-gray-200" 
              />
              <span class="text-xs text-gray-700 font-medium truncate">
                {{ match.staff.organizer[0].user.full_name }}
              </span>
            </div>
            <!-- Fallback if no organizer -->
            <div v-else-if="match.creator || match.user" class="flex items-center gap-2">
               <img 
                :src="(match.creator || match.user)?.avatar || defaultImage"
                @error="e => e.target.src = defaultImage"
                class="w-6 h-6 rounded-full object-cover ring-1 ring-gray-200" 
              />
              <span class="text-xs text-gray-700 font-medium truncate">
                {{ (match.creator || match.user)?.full_name || (match.creator || match.user)?.name }}
              </span>
            </div>
          </div>

          <!-- Badges Swiper (40%) -->
          <div class="w-[40%] pl-2 overflow-hidden">
             <swiper
                :slides-per-view="'auto'"
                :space-between="8"
                :loop="true"
                class="h-full flex items-center"
              >
                <!-- Privacy Badge -->
                <swiper-slide class="!w-auto flex items-center">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 gap-1 text-[10px] font-medium text-white bg-gray-800 whitespace-nowrap">
                      <LockIcon class="w-2.5 h-2.5 text-white" v-if="match.is_private" />
                      <LockOpenIcon class="w-2.5 h-2.5 text-white" v-else />
                      {{ match.is_private ? 'Private' : 'Public' }}
                    </span>
                </swiper-slide>

                <!-- Rating Badge -->
                <swiper-slide class="!w-auto flex items-center" v-if="match.min_rating !== null || match.max_rating !== null">
                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-800 px-2 py-0.5 text-[10px] font-medium text-white whitespace-nowrap">
                      <FlagIcon class="w-2.5 h-2.5 text-white" />
                      {{ match.min_rating }} - {{ match.max_rating }}
                    </span>
                </swiper-slide>

                <!-- DUPR Badge -->
                <swiper-slide class="!w-auto flex items-center" v-if="match.is_dupr">
                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-800 px-2 py-0.5 text-[10px] font-medium text-white whitespace-nowrap">
                      <StarIcon class="w-2.5 h-2.5 text-white" />
                      DUPR
                    </span>
                </swiper-slide>

                <!-- Max Players Badge -->
                <swiper-slide class="!w-auto flex items-center" v-if="match.max_players">
                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-800 px-2 py-0.5 text-[10px] font-medium text-white whitespace-nowrap">
                      <UserGroupIcon class="w-2.5 h-2.5 text-white" />
                      Max {{ match.max_players }}
                    </span>
                </swiper-slide>
             </swiper>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ClockIcon, MapPinIcon, CalendarIcon, CalendarDaysIcon, UserGroupIcon, StarIcon, LockOpenIcon } from '@heroicons/vue/24/outline'; // Using outline icons
import { FlagIcon } from '@heroicons/vue/24/solid';
import dayjs from 'dayjs';
import 'dayjs/locale/vi';
import relativeTime from 'dayjs/plugin/relativeTime';
import { Swiper, SwiperSlide } from 'swiper/vue';
import 'swiper/css';

dayjs.extend(relativeTime);
dayjs.locale('vi');

defineProps({
  match: {
    type: Object,
    required: true
  },
  selected: [String, Number],
  defaultImage: String
})

defineEmits(['select'])

const formatDateText = (date) => {
  if (!date) return '';
  return dayjs(date).format('dddd, DD/MM');
}

const formatTimeRange = (start, duration_minutes) => {
  const startTime = dayjs(start);
  const endTime = startTime.add(duration_minutes, 'minute');

  return `${startTime.format('HH:mm')} - ${endTime.format('HH:mm')}`;
};
</script>