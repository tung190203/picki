<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center min-h-screen">
      <div class="w-10 h-10 border-4 border-red-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex flex-col items-center justify-center min-h-screen px-4 text-center">
      <div class="text-6xl mb-4">🔍</div>
      <h2 class="text-2xl font-bold text-gray-800 mb-2">Không tìm thấy giải đấu</h2>
      <p class="text-gray-500 mb-6">Giải đấu này không tồn tại hoặc đã bị xóa.</p>
      <a href="/" class="px-6 py-3 bg-[#D72D36] text-white rounded-lg font-medium hover:bg-red-700 transition">
        Quay về trang chủ
      </a>
    </div>

    <!-- Main Content -->
    <template v-else-if="tournament">
      <!-- Hero Section -->
      <div class="relative">
        <!-- Background Poster -->
        <div class="w-full h-72 md:h-96 bg-gray-200 overflow-hidden relative">
          <img
            v-if="tournament.poster"
            :src="tournament.poster"
            :alt="tournament.name"
            class="w-full h-full object-cover"
          />
          <div v-else class="w-full h-full bg-gradient-to-br from-[#BA110B] to-[#520011] flex items-center justify-center">
            <span class="text-white text-6xl font-bold opacity-30">{{ tournament.name?.charAt(0) }}</span>
          </div>
          <!-- Gradient Overlay -->
          <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
          <!-- Share Button -->
          <button
            @click="showShareModal = true"
            class="absolute top-4 right-4 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white rounded-full p-2 transition"
          >
            <ShareIcon class="w-6 h-6" />
          </button>
        </div>

        <!-- Hero Content -->
        <div class="absolute bottom-0 left-0 right-0 px-4 md:px-8 pb-6">
          <!-- Sport Badge -->
          <div v-if="tournament.sport" class="flex items-center gap-2 mb-2">
            <span class="bg-white/20 backdrop-blur-sm text-white text-xs px-3 py-1 rounded-full font-medium">
              {{ tournament.sport.name }}
            </span>
            <span v-if="tournament.is_private" class="bg-yellow-500/80 text-white text-xs px-2 py-1 rounded-full font-medium flex items-center gap-1">
              <LockClosedIcon class="w-3 h-3" />
              Riêng tư
            </span>
          </div>
          <!-- Tournament Name -->
          <h1 class="text-2xl md:text-4xl font-bold text-white mb-2">{{ tournament.name }}</h1>
          <!-- Location & Date -->
          <div class="flex flex-wrap items-center gap-4 text-white/90 text-sm">
            <div v-if="tournament.competition_location" class="flex items-center gap-1">
              <MapPinIcon class="w-4 h-4" />
              <span>{{ tournament.competition_location.name }}</span>
            </div>
            <div v-if="tournament.start_date" class="flex items-center gap-1">
              <CalendarDaysIcon class="w-4 h-4" />
              <span>{{ formatEventDate(tournament.start_date) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Countdown Timer -->
      <div v-if="tournament.start_date && isUpcoming" class="bg-gradient-to-r from-[#BA110B] to-[#D72D36] py-6">
        <div class="max-w-6xl mx-auto px-4">
          <p class="text-white/80 text-center text-sm mb-3">Thời gian đến ngày thi đấu</p>
          <div class="flex justify-center gap-3 md:gap-6">
            <div v-for="(unit, index) in countdown" :key="index" class="text-center">
              <div class="bg-white/20 backdrop-blur-sm rounded-lg min-w-[60px] md:min-w-[80px] py-3 px-2">
                <span class="text-2xl md:text-4xl font-bold text-white">{{ unit.value }}</span>
              </div>
              <span class="text-white/80 text-xs mt-1 block">{{ unit.label }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Info Cards -->
      <div class="max-w-6xl mx-auto px-4 py-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <!-- Teams -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition">
            <UsersIcon class="w-6 h-6 text-[#D72D36] mx-auto mb-2" />
            <p class="text-xl font-bold text-gray-900">{{ tournament.max_team || 0 }}</p>
            <p class="text-xs text-gray-500">Đội tham gia</p>
          </div>
          <!-- Registration -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition">
            <ClockIcon class="w-6 h-6 text-[#D72D36] mx-auto mb-2" />
            <p class="text-sm font-bold text-gray-900">{{ registrationStatus }}</p>
            <p class="text-xs text-gray-500">Đăng ký</p>
          </div>
          <!-- Level -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition">
            <StarIcon class="w-6 h-6 text-[#D72D36] mx-auto mb-2" />
            <p class="text-sm font-bold text-gray-900">
              {{ tournament.min_level || tournament.max_level ? `${tournament.min_level || 0} - ${tournament.max_level || 0}` : 'Mở' }}
            </p>
            <p class="text-xs text-gray-500">Trình độ</p>
          </div>
          <!-- Fee -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition">
            <BanknotesIcon class="w-6 h-6 text-[#D72D36] mx-auto mb-2" />
            <p class="text-sm font-bold text-gray-900">
              {{ tournament.fee === 'free' ? 'Miễn phí' : formatCurrency(tournament.standard_fee_amount) }}
            </p>
            <p class="text-xs text-gray-500">Phí tham gia</p>
          </div>
          <!-- Gender -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition">
            <UserGroupIcon class="w-6 h-6 text-[#D72D36] mx-auto mb-2" />
            <p class="text-sm font-bold text-gray-900">{{ tournament.gender_policy_text || 'Mở' }}</p>
            <p class="text-xs text-gray-500">Giới tính</p>
          </div>
          <!-- Age Group -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition">
            <CalendarIcon class="w-6 h-6 text-[#D72D36] mx-auto mb-2" />
            <p class="text-sm font-bold text-gray-900">{{ tournament.age_group_text || 'Mở' }}</p>
            <p class="text-xs text-gray-500">Nhóm tuổi</p>
          </div>
          <!-- Player per team -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition">
            <UserIcon class="w-6 h-6 text-[#D72D36] mx-auto mb-2" />
            <p class="text-xl font-bold text-gray-900">{{ tournament.player_per_team || tournament.max_player || '?' }}</p>
            <p class="text-xs text-gray-500">Người/đội</p>
          </div>
          <!-- Format -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition">
            <AdjustmentsVerticalIcon class="w-6 h-6 text-[#D72D36] mx-auto mb-2" />
            <p class="text-sm font-bold text-gray-900">{{ tournamentFormat }}</p>
            <p class="text-xs text-gray-500">Thể thức</p>
          </div>
        </div>
      </div>

      <!-- Tabs Navigation -->
      <div class="bg-white border-b border-gray-200 sticky top-0 z-10">
        <div class="max-w-6xl mx-auto px-4">
          <div class="flex items-center gap-1 overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
              :class="activeTab === tab.id
                ? 'border-[#D72D36] text-[#D72D36]'
                : 'border-transparent text-gray-500 hover:text-gray-700'"
            >
              {{ tab.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- Tab Content -->
      <div class="max-w-6xl mx-auto px-4 py-6">
        <!-- Overview Tab -->
        <div v-if="activeTab === 'overview'" class="space-y-6">
          <!-- Description -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Giới thiệu giải đấu</h2>
            <div v-if="tournament.description" class="prose prose-sm max-w-none text-gray-700">
              <p>{{ tournament.description }}</p>
            </div>
            <div v-else class="text-gray-400 text-center py-8">
              <p>Giải đấu chưa có mô tả.</p>
            </div>
          </div>

          <!-- Registration Info -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Thông tin đăng ký</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                  <CalendarDaysIcon class="w-5 h-5 text-green-600" />
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-900">Mở đăng ký</p>
                  <p class="text-sm text-gray-500">{{ formatDateTime(tournament.registration_open_at) }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                  <CalendarDaysIcon class="w-5 h-5 text-red-600" />
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-900">Hạn chót đăng ký</p>
                  <p class="text-sm text-gray-500">{{ formatDateTime(tournament.registration_closed_at) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Location -->
          <div v-if="tournament.competition_location" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Địa điểm</h2>
            <div class="flex items-start gap-3">
              <MapPinIcon class="w-5 h-5 text-[#D72D36] mt-1 flex-shrink-0" />
              <div>
                <p class="font-semibold text-gray-900">{{ tournament.competition_location.name }}</p>
                <p class="text-sm text-gray-500">{{ tournament.competition_location.address }}</p>
              </div>
            </div>
          </div>

          <!-- Organizers -->
          <div v-if="organizers.length" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Ban tổ chức</h2>
            <div class="flex flex-wrap gap-3">
              <div v-for="org in organizers" :key="org.id" class="flex items-center gap-2 bg-gray-50 rounded-full px-3 py-1.5">
                <div class="w-8 h-8 rounded-full bg-[#D72D36] flex items-center justify-center text-white text-sm font-bold overflow-hidden">
                  <img v-if="org.staff?.avatar" :src="org.staff.avatar" class="w-full h-full object-cover" :alt="org.staff.name" />
                  <span v-else>{{ org.staff?.name?.charAt(0) || '?' }}</span>
                </div>
                <span class="text-sm font-medium text-gray-700">{{ org.staff?.name || 'BTC' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Teams Tab -->
        <div v-else-if="activeTab === 'teams'" class="space-y-4">
          <div v-if="participants.length === 0" class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <UsersIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
            <p class="text-gray-500">Chưa có đội/nhóm tham gia nào.</p>
          </div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            <div v-for="participant in participants" :key="participant.id" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition">
              <div class="w-16 h-16 rounded-full bg-[#FFF5F5] mx-auto mb-3 flex items-center justify-center overflow-hidden">
                <img v-if="getParticipantAvatar(participant)" :src="getParticipantAvatar(participant)" class="w-full h-full object-cover" :alt="getParticipantName(participant)" />
                <UsersIcon v-else class="w-6 h-6 text-[#D72D36]" />
              </div>
              <p class="font-semibold text-gray-900 text-sm truncate">{{ getParticipantName(participant) }}</p>
              <p class="text-xs text-gray-400 mt-1">{{ participant.user?.full_name || '' }}</p>
              <span class="inline-block mt-2 text-xs px-2 py-0.5 rounded-full" :class="getParticipantStatusClass(participant)">
                {{ getParticipantStatusLabel(participant) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Schedule Tab -->
        <div v-else-if="activeTab === 'schedule'" class="space-y-4">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Lịch thi đấu</h2>
            <div v-if="tournament.tournament_types && tournament.tournament_types.length" class="space-y-4">
              <div v-for="type in tournament.tournament_types" :key="type.id" class="border border-gray-100 rounded-lg p-4">
                <div class="flex items-center gap-3 mb-3">
                  <AdjustmentsVerticalIcon class="w-5 h-5 text-[#D72D36]" />
                  <span class="font-semibold text-gray-900">{{ type.format_label || 'Chưa xác định' }}</span>
                </div>
                <div class="grid grid-cols-2 gap-3 text-sm">
                  <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-gray-500">Tổng trận đấu</p>
                    <p class="font-bold text-gray-900">{{ type.total_matches || 0 }}</p>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-gray-500">Đội vào vòng loại</p>
                    <p class="font-bold text-gray-900">{{ type.format_specific_config?.[0]?.pool_stage?.num_advancing_teams || '-' }}</p>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8 text-gray-400">
              <p>Lịch thi đấu đang được cập nhật.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer CTA -->
      <div class="bg-gradient-to-r from-[#BA110B] to-[#D72D36] py-8">
        <div class="max-w-6xl mx-auto px-4 text-center">
          <h2 class="text-2xl font-bold text-white mb-2">Tham gia ngay!</h2>
          <p class="text-white/80 mb-6">Đăng ký tham gia giải đấu ngay hôm nay</p>
          <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a
              v-if="isLoggedIn"
              :href="registerLink"
              class="px-8 py-3 bg-white text-[#D72D36] rounded-lg font-bold hover:bg-gray-100 transition shadow-lg"
              @click.prevent="handleRegister"
            >
              Đăng ký tham gia
            </a>
            <a
              v-else
              :href="loginLink"
              class="px-8 py-3 bg-white text-[#D72D36] rounded-lg font-bold hover:bg-gray-100 transition shadow-lg"
            >
              Đăng nhập để đăng ký
            </a>
            <a
              :href="registerLink"
              class="px-8 py-3 bg-transparent border-2 border-white text-white rounded-lg font-bold hover:bg-white/10 transition"
            >
              Tạo tài khoản mới
            </a>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <footer class="bg-gray-900 py-6">
        <div class="max-w-6xl mx-auto px-4 text-center">
          <p class="text-gray-400 text-sm">© 2024 PICKI. Nền tảng quản lý giải đấu Pickleball.</p>
        </div>
      </footer>

      <!-- Share Card Modal -->
      <ShareCard
        :is-visible="showShareModal"
        :tournament="tournament"
        @close="showShareModal = false"
      />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ChevronLeftIcon,
  MapPinIcon,
  CalendarDaysIcon,
  UsersIcon,
  ClockIcon,
  StarIcon,
  BanknotesIcon,
  UserGroupIcon,
  CalendarIcon,
  UserIcon,
  AdjustmentsVerticalIcon,
  LockClosedIcon,
  ShareIcon,
} from '@heroicons/vue/24/outline'
import * as TournamentService from '@/service/tournament.js'
import { useFormatDate, formatEventDate } from '@/composables/formatDatetime.js'
import { LOCAL_STORAGE_KEY } from '@/constants/index.js'
import ShareCard from './shared/ShareCard.vue'

const route = useRoute()
const router = useRouter()
const { formatDateTime } = useFormatDate()

const tournament = ref(null)
const isLoading = ref(true)
const error = ref(false)
const activeTab = ref('overview')
const countdown = ref([])
let countdownInterval = null
const showShareModal = ref(false)

const tournamentId = computed(() => route.params.id)

const isMobile = computed(() =>
  /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
)

const deeplinkBase = 'vpick://tournament-detail'
const webBase = 'https://picki.vn/tournament-detail'

const registerLink = computed(() => {
  const base = isMobile.value ? deeplinkBase : webBase
  return `${base}/${tournamentId.value}?ref=register`
})

const shareLink = computed(() => {
  const base = isMobile.value ? deeplinkBase : webBase
  return `${base}/${tournamentId.value}?ref=share`
})

const tabs = [
  { id: 'overview', label: 'Tổng quan' },
  { id: 'teams', label: 'Đội tham gia' },
  { id: 'schedule', label: 'Lịch thi đấu' },
]

const isLoggedIn = computed(() => {
  return !!localStorage.getItem(LOCAL_STORAGE_KEY.LOGIN_TOKEN)
})

const isUpcoming = computed(() => {
  if (!tournament.value?.start_date) return false
  return new Date(tournament.value.start_date) > new Date()
})

const registrationStatus = computed(() => {
  if (!tournament.value) return 'N/A'
  const now = new Date()
  const open = tournament.value.registration_open_at ? new Date(tournament.value.registration_open_at) : null
  const close = tournament.value.registration_closed_at ? new Date(tournament.value.registration_closed_at) : null

  if (open && now < open) return 'Chưa mở'
  if (close && now > close) return 'Đã đóng'
  return 'Đang mở'
})

watch(tournament, () => {
  if (!tournament.value) return
  const t = tournament.value
  const ogImage = t.poster || ''
  const shareUrl = `${webBase}/${tournamentId.value}`

  document.title = `${t.name} | PICKI`
  updateMeta('og:title', t.name)
  updateMeta('og:description', t.description || `Đăng ký tham gia giải đấu Pickleball ${t.name}`)
  updateMeta('og:type', 'website')
  updateMeta('og:url', shareUrl)
  updateMeta('og:image', ogImage)
  updateMeta('og:site_name', 'PICKI')
  updateMeta('twitter:card', 'summary_large_image')
  updateMeta('twitter:title', t.name)
  updateMeta('twitter:description', t.description || '')
  updateMeta('twitter:image', ogImage)
  updateCanonical(shareUrl)
}, { immediate: true })

function updateMeta(name, content) {
  let el = document.querySelector(`meta[property="${name}"], meta[name="${name}"]`)
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(name.startsWith('og:') ? 'property' : 'name', name)
    document.head.appendChild(el)
  }
  el.setAttribute('content', content)
}

function updateCanonical(url) {
  let el = document.querySelector('link[rel="canonical"]')
  if (!el) {
    el = document.createElement('link')
    el.setAttribute('rel', 'canonical')
    document.head.appendChild(el)
  }
  el.setAttribute('href', url)
}

const tournamentFormat = computed(() => {
  const types = tournament.value?.tournament_types
  if (!types || types.length === 0) return 'Chưa xác định'
  return types.map(t => t.format_label || 'N/A').join(', ')
})

const participants = computed(() => {
  return tournament.value?.tournament_participants || []
})

const organizers = computed(() => {
  return tournament.value?.tournament_staff?.filter(s => s.role === 1) || []
})

function formatCurrency(amount) {
  if (!amount) return 'Miễn phí'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
}

function getParticipantName(p) {
  if (!p) return ''
  return p.guest_name || p.name || p.user?.full_name || ''
}

function getParticipantAvatar(p) {
  if (!p) return ''
  return p.avatar || p.user?.avatar_url || p.guest_avatar || ''
}

function getParticipantStatusClass(p) {
  if (p.checked_in_at) return 'bg-green-100 text-green-700'
  if (p.is_absent) return 'bg-red-100 text-red-700'
  if (p.is_confirmed) return 'bg-blue-100 text-blue-700'
  return 'bg-yellow-100 text-yellow-700'
}

function getParticipantStatusLabel(p) {
  if (p.checked_in_at) return 'Đã checkin'
  if (p.is_absent) return 'Vắng'
  if (p.is_confirmed) return 'Đã xác nhận'
  return 'Chờ xác nhận'
}

function updateCountdown() {
  if (!tournament.value?.start_date) {
    countdown.value = []
    return
  }

  const target = new Date(tournament.value.start_date).getTime()
  const now = Date.now()
  const diff = target - now

  if (diff <= 0) {
    countdown.value = [
      { value: '00', label: 'Ngày' },
      { value: '00', label: 'Giờ' },
      { value: '00', label: 'Phút' },
      { value: '00', label: 'Giây' },
    ]
    return
  }

  const days = Math.floor(diff / (1000 * 60 * 60 * 24))
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
  const seconds = Math.floor((diff % (1000 * 60)) / 1000)

  countdown.value = [
    { value: String(days).padStart(2, '0'), label: 'Ngày' },
    { value: String(hours).padStart(2, '0'), label: 'Giờ' },
    { value: String(minutes).padStart(2, '0'), label: 'Phút' },
    { value: String(seconds).padStart(2, '0'), label: 'Giây' },
  ]
}

async function fetchTournament() {
  isLoading.value = true
  error.value = false
  try {
    const id = route.params.id
    const response = await TournamentService.getTournamentById(id)
    tournament.value = response
    updateCountdown()
  } catch (err) {
    error.value = true
    console.error('Error fetching tournament:', err)
  } finally {
    isLoading.value = false
  }
}

function handleRegister() {
  router.push({ name: 'tournament-detail', params: { id: tournament.value.id } })
}

onMounted(async () => {
  await fetchTournament()
  countdownInterval = setInterval(updateCountdown, 1000)
})

onUnmounted(() => {
  if (countdownInterval) {
    clearInterval(countdownInterval)
  }
})
</script>
