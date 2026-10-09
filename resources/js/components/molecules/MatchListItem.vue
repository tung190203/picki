<template>
  <div
    @click="$emit('select', match)"
    :class="[
      'border rounded-xl cursor-pointer transition-all overflow-hidden flex flex-col h-fit bg-white dark:bg-slate-900',
      match.id === selected
        ? 'border-primary shadow-md ring-1 ring-primary'
        : 'border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-md hover:border-gray-300 dark:hover:border-slate-600'
    ]"
  >
    <!-- ============ GIAI DAU (Tournament) Layout ============ -->
    <template v-if="match.type === 'tournament'">
      <div class="flex items-start p-3 gap-3">
        <div class="w-28 h-28 flex-shrink-0 bg-gray-100 dark:bg-slate-800 rounded-md overflow-hidden border border-gray-100 dark:border-slate-700">
          <img
            :src="match.poster || defaultImage"
            @error="e => e.target.src = defaultImage"
            class="w-full h-full object-cover"
          />
        </div>

        <div class="flex-1 min-w-0 flex flex-col justify-between h-28">
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h4 class="font-bold text-gray-900 dark:text-slate-100 text-sm line-clamp-2 leading-tight">
                {{ match.name }}
              </h4>
              <span
                v-if="match.is_completed"
                class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-200 text-gray-700 dark:bg-slate-700 dark:text-slate-200 whitespace-nowrap"
              >Đã kết thúc</span>
            </div>
            <p
              v-if="match.description"
              class="text-xs text-gray-500 dark:text-slate-400 line-clamp-2 mt-0.5"
            >{{ match.description }}</p>
          </div>

          <div class="space-y-1 mt-1">
            <div
              class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-slate-300"
              v-if="match.competition_location"
            >
              <MapPinIcon class="w-4 h-4 text-[#4392e0] dark:text-sky-400 flex-shrink-0" />
              <span class="line-clamp-1 font-medium">
                {{ match.competition_location.name || match.competition_location.address }}
              </span>
            </div>
            <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-slate-300">
              <CalendarIcon class="w-4 h-4 text-[#4392e0] dark:text-sky-400 flex-shrink-0" />
              <span class="font-medium capitalize">
                {{ formatDateRange(match.start_date, match.end_date) }}
              </span>
            </div>
            <div
              class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-slate-300"
              v-if="match.distance != null"
            >
              <MapPinIcon class="w-3.5 h-3.5 text-[#4392e0] dark:text-sky-400 flex-shrink-0" />
              <span class="font-medium">{{ match.distance }} km</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer: organizer + badges -->
      <div class="flex items-center px-3 py-2 border-t border-gray-100 dark:border-slate-800 gap-2">
        <div class="flex-1 min-w-0 flex items-center gap-2">
          <template v-if="firstOrganizer">
            <img
              :src="firstOrganizer.avatar_url || firstOrganizer.avatar || defaultImage"
              @error="e => e.target.src = defaultImage"
              class="w-6 h-6 rounded-full object-cover ring-1 ring-gray-200 dark:ring-slate-600"
            />
            <span class="text-xs text-gray-700 dark:text-slate-300 font-medium truncate">
              {{ firstOrganizer.full_name || firstOrganizer.name }}
            </span>
          </template>
          <span v-else class="text-xs text-gray-400 dark:text-slate-500 italic">Chưa có BTC</span>
        </div>
        <div class="flex items-center gap-1 flex-shrink-0 flex-wrap justify-end">
          <span
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium text-white whitespace-nowrap"
            :class="match.is_private ? 'bg-red-500' : 'bg-emerald-500'"
          >
            <component :is="match.is_private ? LockClosedIcon : LockOpenIcon" class="w-2.5 h-2.5" />
            {{ match.is_private ? 'Private' : 'Public' }}
          </span>
          <span
            v-if="match.has_fee && match.fee_amount != null"
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-orange-500 text-white whitespace-nowrap"
          >
            💰 {{ formatCurrency(match.fee_amount) }}
          </span>
          <span
            v-if="match.max_player || match.max_team"
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-blue-500 text-white whitespace-nowrap"
          >
            <UserGroupIcon class="w-2.5 h-2.5" />
            {{ match.max_team || match.max_player }} đội
          </span>
          <span
            v-if="match.participated_team"
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-purple-500 text-white whitespace-nowrap"
          >
            ✓ {{ match.participated_team }}
          </span>
          <span
            v-if="match.is_joined"
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold bg-primary text-white whitespace-nowrap"
          >
            ✓ Đã tham gia
          </span>
          <span
            v-else-if="match.is_registered"
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-gray-500 text-white whitespace-nowrap"
          >
            ⏳ Đã đăng ký
          </span>
        </div>
      </div>
    </template>

    <!-- ============ KEO DAU (Mini) Layout ============ -->
    <template v-else>
      <div class="p-3">
        <div class="flex justify-between items-start gap-3">
          <!-- Left Content -->
          <div class="flex-1 min-w-0 space-y-2">
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="font-bold text-gray-900 dark:text-slate-100 text-md line-clamp-2 leading-snug">
                  {{ match.name }}
                </h3>
                <span
                  v-if="match.is_completed"
                  class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-200 text-gray-700 dark:bg-slate-700 dark:text-slate-200 whitespace-nowrap"
                >Đã kết thúc</span>
              </div>
              <div
                class="flex items-center gap-1 mt-1 text-xs text-blue-500 dark:text-sky-400 font-medium"
                v-if="match.competition_location"
              >
                <MapPinIcon class="w-4 h-4 flex-shrink-0" />
                <span class="line-clamp-1">
                  {{ match.competition_location.name || match.competition_location.address }}
                </span>
                <span
                  v-if="match.distance != null"
                  class="ml-1 px-1.5 rounded bg-blue-50 dark:bg-sky-900/30 text-blue-600 dark:text-sky-300"
                >{{ match.distance }} km</span>
              </div>
            </div>

            <!-- Date & Time -->
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-slate-300">
                <CalendarDaysIcon class="w-4 h-4 text-blue-500 dark:text-sky-400 flex-shrink-0" />
                <span class="font-medium capitalize">{{ formatDateText(match.starts_at) }}</span>
              </div>
              <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-slate-300">
                <ClockIcon class="w-4 h-4 text-blue-500 dark:text-sky-400 flex-shrink-0" />
                <span class="font-medium">{{ formatTimeRange(match.starts_at, match.duration_minutes) }}</span>
              </div>
            </div>

            <!-- Players count -->
            <div
              v-if="match.max_players"
              class="flex items-center gap-2 text-xs text-gray-700 dark:text-slate-300"
            >
              <UserGroupIcon class="w-4 h-4 text-blue-500 dark:text-sky-400" />
              <span class="font-medium">
                {{ match.joined_count || match.participants_count || 0 }}/{{ match.max_players }}
                <span
                  class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold"
                  :class="match.slot_status === 'con_trong'
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                    : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
                >{{ match.slot_status === 'con_trong' ? 'Còn trống' : 'Đã đầy' }}</span>
              </span>
            </div>
          </div>

          <!-- Right: poster + participants -->
          <div class="flex flex-col items-end gap-2 flex-shrink-0">
            <div
              v-if="match.poster"
              class="w-20 h-20 bg-gray-100 dark:bg-slate-800 rounded-md overflow-hidden border border-gray-100 dark:border-slate-700"
            >
              <img
                :src="match.poster"
                @error="e => e.target.src = defaultImage"
                class="w-full h-full object-cover"
              />
            </div>

            <div
              class="flex -space-x-2 py-1 items-center"
              v-if="match.participants && match.participants.length > 0"
            >
              <img
                v-for="(p, idx) in match.participants.slice(0, 3)"
                :key="idx"
                :src="p.avatar_url || defaultImage"
                @error="e => e.target.src = defaultImage"
                class="inline-block h-7 w-7 rounded-full ring-2 ring-white dark:ring-slate-900 object-cover shadow-sm"
              />
              <div
                v-if="match.participants.length > 3"
                class="flex items-center justify-center h-7 w-7 rounded-full ring-2 ring-white dark:ring-slate-900 bg-red-50 dark:bg-red-950/60 text-[#D72D36] dark:text-red-400 text-xs font-bold shadow-sm"
              >+{{ match.participants.length - 3 }}</div>
            </div>
            <div
              v-else-if="(match.joined_count || 0) > 0"
              class="flex items-center justify-center h-7 w-7 rounded-full bg-red-50 dark:bg-red-950/60 text-[#D72D36] dark:text-red-400 text-xs font-bold border border-red-100 dark:border-red-900/40"
            >+{{ match.joined_count }}</div>
          </div>
        </div>

        <!-- Footer: organizer + badges -->
        <div class="flex items-center mt-3 pt-2 border-t border-gray-100 dark:border-slate-800 gap-2">
          <div class="flex-1 min-w-0 flex items-center gap-2">
            <template v-if="firstOrganizer">
              <img
                :src="firstOrganizer.avatar_url || firstOrganizer.avatar || defaultImage"
                @error="e => e.target.src = defaultImage"
                class="w-6 h-6 rounded-full object-cover ring-1 ring-gray-200 dark:ring-slate-600"
              />
              <span class="text-xs text-gray-700 dark:text-slate-300 font-medium truncate">
                {{ firstOrganizer.full_name || firstOrganizer.name }}
              </span>
            </template>
            <span v-else class="text-xs text-gray-400 dark:text-slate-500 italic">Chưa có BTC</span>
          </div>
          <div class="flex items-center gap-1 flex-shrink-0 flex-wrap justify-end">
            <span
              class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium text-white whitespace-nowrap"
              :class="match.is_private ? 'bg-red-500' : 'bg-emerald-500'"
            >
              <component :is="match.is_private ? LockClosedIcon : LockOpenIcon" class="w-2.5 h-2.5" />
              {{ match.is_private ? 'Private' : 'Public' }}
            </span>
            <span
              v-if="match.has_fee && match.fee_amount != null"
              class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-orange-500 text-white whitespace-nowrap"
            >
              💰 {{ formatCurrency(match.fee_amount) }}
            </span>
            <span
              v-if="match.min_rating != null || match.max_rating != null"
              class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-slate-700 text-white whitespace-nowrap"
            >
              <FlagIcon class="w-2.5 h-2.5" />
              {{ match.min_rating ?? '?' }} - {{ match.max_rating ?? '?' }}
            </span>
            <span
              v-if="match.is_dupr"
              class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-slate-700 text-white whitespace-nowrap"
            >
              <StarIcon class="w-2.5 h-2.5" />
              DUPR
            </span>
            <span
              v-if="match.is_joined"
              class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold bg-primary text-white whitespace-nowrap"
            >
              ✓ Đã tham gia
            </span>
            <span
              v-else-if="match.is_registered"
              class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-gray-500 text-white whitespace-nowrap"
            >
              ⏳ Đã đăng ký
            </span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import {
  ClockIcon, MapPinIcon, CalendarIcon, CalendarDaysIcon,
  UserGroupIcon, StarIcon, LockOpenIcon, LockClosedIcon,
} from '@heroicons/vue/24/outline';
import { FlagIcon } from '@heroicons/vue/24/solid';
import dayjs from 'dayjs';
import 'dayjs/locale/vi';
import relativeTime from 'dayjs/plugin/relativeTime';
import { Swiper, SwiperSlide } from 'swiper/vue';
import 'swiper/css';

dayjs.extend(relativeTime);
dayjs.locale('vi');

const props = defineProps({
  match: {
    type: Object,
    required: true
  },
  selected: [String, Number],
  defaultImage: String
})

defineEmits(['select'])

/**
 * Lấy người tổ chức đầu tiên (để hiển thị avatar).
 * RBAC v2: staff.organizer (số ít) là key chính cho mini.
 * Tournament: organizer (Phase 4) hoặc tournamentStaff filter ROLE_ORGANIZER.
 */
const firstOrganizer = computed(() => {
  const org = props.match?.organizer
  if (org) return org
  const staff = props.match?.staff
  if (staff?.organizer?.length) return staff.organizer[0]?.user
  if (props.match?.tournamentStaff?.length) {
    return props.match.tournamentStaff[0]
  }
  return null
})

const formatDateText = (date) => {
  if (!date) return ''
  return dayjs(date).format('dddd, DD/MM')
}

const formatDateRange = (start, end) => {
  if (!start) return ''
  if (end && dayjs(start).format('YYYY-MM-DD') !== dayjs(end).format('YYYY-MM-DD')) {
    return `${dayjs(start).format('DD/MM')} - ${dayjs(end).format('DD/MM')}`
  }
  return dayjs(start).format('DD/MM/YYYY')
}

const formatTimeRange = (start, duration_minutes) => {
  const startTime = dayjs(start)
  const endTime = startTime.add(duration_minutes || 0, 'minute')
  return `${startTime.format('HH:mm')} - ${endTime.format('HH:mm')}`
}

const formatCurrency = (v) => {
  if (v == null) return ''
  return new Intl.NumberFormat('vi-VN').format(v) + 'đ'
}
</script>
