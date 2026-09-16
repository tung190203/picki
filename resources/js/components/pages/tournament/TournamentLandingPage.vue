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
              {{ tournament.has_fee ? formatCurrency(tournament.fee_amount) : 'Miễn phí' }}
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
          <!-- Loading state -->
          <div v-if="isLoadingTeams" class="flex justify-center py-8">
            <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin"></div>
          </div>
          <!-- Empty state -->
          <div v-else-if="teams.length === 0" class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <UsersIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
            <p class="text-gray-500">Chưa có đội tham gia nào.</p>
          </div>
          <!-- Teams grid -->
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            <div v-for="team in teams" :key="team.id" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition">
              <div class="w-16 h-16 rounded-full bg-[#FFF5F5] mx-auto mb-3 flex items-center justify-center overflow-hidden">
                <img v-if="team.avatar" :src="team.avatar" class="w-full h-full object-cover" :alt="team.name" />
                <UsersIcon v-else class="w-6 h-6 text-[#D72D36]" />
              </div>
              <p class="font-semibold text-gray-900 text-sm truncate" :title="team.name">{{ team.name }}</p>
              <p class="text-xs text-gray-400 mt-1">{{ team.members?.length || 0 }} thành viên</p>
              <p v-if="team.members?.length" class="text-xs text-gray-500 mt-1 truncate">
                {{ team.members[0].full_name || team.members[0].name }}
              </p>
            </div>
          </div>
        </div>

        <!-- Schedule Tab -->
        <div v-else-if="activeTab === 'schedule'" class="space-y-4">
          <!-- Loading state -->
          <div v-if="isLoadingBracket" class="flex justify-center py-8">
            <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin"></div>
          </div>
          
          <template v-else-if="bracketData">
            <!-- Stage tabs for Mixed format -->
            <div v-if="tournamentTypeFormat === 1" class="flex justify-start gap-2 mb-4">
              <button @click="currentMixedStage = 'pool'" :class="[
                'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                currentMixedStage === 'pool'
                  ? 'bg-[#D72D36] text-white shadow-md'
                  : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'
              ]">
                Vòng bảng
              </button>
              <button @click="currentMixedStage = 'knockout'" :class="[
                'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                currentMixedStage === 'knockout'
                  ? 'bg-[#D72D36] text-white shadow-md'
                  : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'
              ]">
                Vòng loại trực tiếp
              </button>
            </div>

            <!-- Pool Stage (Mixed format) -->
            <template v-if="tournamentTypeFormat === 1 && currentMixedStage === 'pool'">
              <div v-if="mixedBracket.poolStage?.length > 0">
                <div v-for="group in mixedBracket.poolStage" :key="group.group_id" class="mb-6">
                  <div class="bg-[#EDEEF2] px-4 py-3 rounded-lg mb-4">
                    <h3 class="font-bold text-[#3E414C]">{{ group.group_name }}</h3>
                  </div>
                  <div v-if="group.matches?.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-3 px-2">
                    <PoolStageMatchCard 
                      v-for="match in group.matches" 
                      :key="match.match_id" 
                      :match="normalizeMatchForCard(match)"
                      :enable-drag-drop="false"
                      :fillAvailable="true" />
                  </div>
                  <div v-else class="text-center text-gray-500 py-4">
                    Chưa có trận đấu trong {{ group.group_name }}
                  </div>
                </div>
              </div>
              <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                <p class="text-gray-500">Chưa có trận đấu vòng bảng.</p>
              </div>
            </template>

            <!-- Knockout Stage (Mixed format) -->
            <template v-if="tournamentTypeFormat === 1 && currentMixedStage === 'knockout'">
              <div v-if="currentKnockoutRound" class="mb-6">
                <div class="flex justify-between items-center mb-4 px-2">
                  <p class="text-sm font-semibold text-gray-700">
                    {{ currentKnockoutRound.round_name }} • {{ currentKnockoutRound.matches.length }} trận đấu
                  </p>
                  <p class="text-sm font-semibold text-gray-500">
                    {{ getKnockoutStatusText(currentKnockoutRound.matches) }}
                  </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 px-2">
                  <PoolStageMatchCard 
                    v-for="match in currentKnockoutRound.matches" 
                    :key="match.match_id" 
                    :match="normalizeMatchForCard(match)"
                    :enable-drag-drop="false" />
                </div>
              </div>

              <!-- Navigation for knockout rounds -->
              <div v-if="bracketData.knockout_stage?.length > 1" class="flex justify-center items-center gap-4 mt-4">
                <button @click="previousKnockoutRound" :disabled="!hasPreviousKnockoutRound" :class="[
                  'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                  hasPreviousKnockoutRound
                    ? 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200 cursor-pointer'
                    : 'bg-gray-100 text-gray-400 cursor-not-allowed border'
                ]">
                  ← Vòng trước
                </button>
                <span class="text-sm text-gray-600">Vòng {{ currentKnockoutRoundIndex + 1 }} / {{ bracketData.knockout_stage.length }}</span>
                <button @click="nextKnockoutRound" :disabled="!hasNextKnockoutRound" :class="[
                  'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                  hasNextKnockoutRound
                    ? 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200 cursor-pointer'
                    : 'bg-gray-100 text-gray-400 cursor-not-allowed border'
                ]">
                  Vòng sau →
                </button>
              </div>
            </template>

            <!-- Elimination format -->
            <template v-if="tournamentTypeFormat === 2">
              <div v-if="currentEliminationRound" class="mb-6">
                <div class="flex justify-between items-center mb-4 px-2">
                  <p class="text-sm font-semibold text-gray-700">
                    {{ currentEliminationRound.round_name }} • {{ currentEliminationRound.matches.length }} trận đấu
                  </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 px-2">
                  <PoolStageMatchCard 
                    v-for="match in currentEliminationRound.matches" 
                    :key="match.match_id" 
                    :match="normalizeMatchForCard(match)"
                    :enable-drag-drop="false" />
                </div>
              </div>
            </template>

            <!-- Round Robin / Other formats -->
            <template v-if="tournamentTypeFormat === 3 || !tournamentTypeFormat">
              <div v-if="bracketData.bracket?.length > 0" class="space-y-4">
                <div v-for="round in bracketData.bracket" :key="round.round" class="mb-6">
                  <div class="bg-[#EDEEF2] px-4 py-3 rounded-lg mb-4">
                    <h3 class="font-bold text-[#3E414C]">Vòng {{ round.round }}</h3>
                  </div>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3 px-2">
                    <PoolStageMatchCard 
                      v-for="match in round.matches" 
                      :key="match.match_id || match.id" 
                      :match="normalizeMatchForCard(match)"
                      :enable-drag-drop="false" />
                  </div>
                </div>
              </div>
              <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                <p class="text-gray-500">Chưa có trận đấu nào.</p>
              </div>
            </template>
          </template>

          <!-- Empty/Error state -->
          <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
            <AdjustmentsVerticalIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
            <p class="text-gray-500">Lịch thi đấu đang được cập nhật.</p>
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
import * as TeamService from '@/service/team.js'
import * as TournamentTypeService from '@/service/tournamentType.js'
import { useFormatDate, formatEventDate } from '@/composables/formatDatetime.js'
import { LOCAL_STORAGE_KEY } from '@/constants/index.js'
import PoolStageMatchCard from '@/components/molecules/PoolStageMatchCard.vue'
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

// Teams state
const teams = ref([])
const isLoadingTeams = ref(false)

// Bracket/Schedule state
const bracketData = ref(null)
const isLoadingBracket = ref(false)
const scheduleActiveTab = ref('matches') // 'matches' or 'ranking'
const currentMixedStage = ref('pool') // 'pool' or 'knockout'
const currentKnockoutRoundIndex = ref(0)
const currentEliminationRoundIndex = ref(0)

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

  document.title = 'PICKI'
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
    // Fetch teams after tournament is loaded
    await fetchTeams()
    // Fetch bracket data after tournament types are loaded
    if (response.tournament_types?.length) {
      await fetchBracket()
    }
  } catch (err) {
    error.value = true
    console.error('Error fetching tournament:', err)
  } finally {
    isLoading.value = false
  }
}

async function fetchTeams() {
  if (!tournamentId.value) return
  isLoadingTeams.value = true
  try {
    const response = await TeamService.getTeamsByTournamentId(tournamentId.value)
    // Response shape: { teams: [...] } - normalize to array
    if (Array.isArray(response)) {
      teams.value = response
    } else if (response && Array.isArray(response.teams)) {
      teams.value = response.teams
    } else {
      teams.value = []
    }
  } catch (err) {
    console.error('Error fetching teams:', err)
    teams.value = []
  } finally {
    isLoadingTeams.value = false
  }
}

async function fetchBracket() {
  const tournamentTypeId = tournament.value?.tournament_types?.[0]?.id
  if (!tournamentTypeId) return
  
  isLoadingBracket.value = true
  try {
    const response = await TournamentTypeService.getBracketByTournamentTypeId(tournamentTypeId)
    bracketData.value = response
  } catch (err) {
    console.error('Error fetching bracket:', err)
    bracketData.value = null
  } finally {
    isLoadingBracket.value = false
  }
}

// Schedule tab computed properties
const tournamentTypeFormat = computed(() => {
  return tournament.value?.tournament_types?.[0]?.format
})

const mixedBracket = computed(() => {
  if (!bracketData.value) return { poolStage: [], leftSide: [], rightSide: [], finalMatch: null }
  
  const poolStage = bracketData.value.pool_stage || []
  const knockoutStage = bracketData.value.knockout_stage || []
  
  // Compute leftSide/rightSide from knockout_stage
  const leftSide = []
  const rightSide = []
  let finalMatch = null
  let thirdPlaceMatch = null
  
  const maxRound = Math.max(...knockoutStage.map(r => r.round || 0), 0)
  
  knockoutStage.forEach(roundData => {
    const round = roundData.round || 0
    const matches = roundData.matches || []
    
    // Find third place match
    const thirdPlaceData = matches.find(m => m.is_third_place === true || m.is_third_place === 1)
    
    if (round === maxRound) {
      // Final round - extract final match
      if (!finalMatch) {
        finalMatch = matches.find(m => m.is_third_place !== true && m.is_third_place !== 1)
      }
      if (!thirdPlaceMatch && thirdPlaceData) {
        thirdPlaceMatch = thirdPlaceData
      }
      return
    }
    
    // Split matches between left/right
    const nonThirdMatches = matches.filter(m => m.is_third_place !== true && m.is_third_place !== 1)
    if (nonThirdMatches.length === 0) return
    
    const mid = Math.ceil(nonThirdMatches.length / 2)
    
    if (nonThirdMatches.slice(0, mid).length > 0) {
      leftSide.push({ round, round_name: roundData.round_name, matches: nonThirdMatches.slice(0, mid) })
    }
    if (nonThirdMatches.slice(mid).length > 0) {
      rightSide.push({ round, round_name: roundData.round_name, matches: nonThirdMatches.slice(mid) })
    }
  })
  
  return {
    poolStage,
    leftSide,
    rightSide,
    finalMatch,
    thirdPlaceMatch
  }
})

const eliminationBracket = computed(() => {
  return bracketData.value?.bracket || []
})

const currentEliminationRound = computed(() => {
  return eliminationBracket.value[currentEliminationRoundIndex.value] || null
})

const currentKnockoutRound = computed(() => {
  const roundsMap = new Map()
  
  // Build rounds from knockout_stage
  if (bracketData.value?.knockout_stage) {
    bracketData.value.knockout_stage.forEach(round => {
      const roundNum = round.round || 0
      roundsMap.set(roundNum, {
        round: roundNum,
        round_name: round.round_name || `Vòng ${roundNum}`,
        matches: round.matches || []
      })
    })
  }
  
  // Add final match
  if (mixedBracket.value.finalMatch) {
    roundsMap.set(999, {
      round: 999,
      round_name: mixedBracket.value.finalMatch.round_name || 'Chung kết',
      matches: [mixedBracket.value.finalMatch]
    })
  }
  
  const sortedRounds = Array.from(roundsMap.values()).sort((a, b) => (a.round || 0) - (b.round || 0))
  return sortedRounds[currentKnockoutRoundIndex.value] || sortedRounds[0] || null
})

const hasPreviousKnockoutRound = computed(() => {
  const roundsCount = bracketData.value?.knockout_stage?.length || 0
  return currentKnockoutRoundIndex.value > 0
})

const hasNextKnockoutRound = computed(() => {
  const roundsCount = bracketData.value?.knockout_stage?.length || 0
  return currentKnockoutRoundIndex.value < roundsCount - 1
})

const previousKnockoutRound = () => {
  if (hasPreviousKnockoutRound.value) currentKnockoutRoundIndex.value--
}

const nextKnockoutRound = () => {
  if (hasNextKnockoutRound.value) currentKnockoutRoundIndex.value++
}

const getKnockoutStatusText = (matches) => {
  if (!matches || matches.length === 0) return 'Chưa có trận đấu'
  const completedCount = matches.filter(m => m.status === 'completed').length
  const pendingCount = matches.filter(m => m.status === 'pending').length
  if (completedCount === matches.length) return `Hoàn thành`
  return `Đang diễn ra • ${completedCount}/${matches.length}`
}

const normalizeMatchForCard = (match) => {
  const homeTeamId = match.home_team?.id
  const awayTeamId = match.away_team?.id
  
  // Normalize legs
  if (match.legs && Array.isArray(match.legs) && match.legs.length > 0) {
    const normalizedLegs = match.legs.map(leg => {
      if (leg.sets && typeof leg.sets === 'object' && !Array.isArray(leg.sets)) {
        return leg
      }
      
      let sets = {}
      if (leg.sets && Array.isArray(leg.sets)) {
        leg.sets.forEach((set, index) => {
          const key = `set_${index + 1}`
          sets[key] = Array.isArray(set) ? set : [set]
        })
      } else if (leg.results && Array.isArray(leg.results)) {
        leg.results.forEach(result => {
          const setNum = result.set_number || 1
          const key = `set_${setNum}`
          if (!sets[key]) sets[key] = []
          sets[key].push({ team_id: result.team_id, score: result.score || 0 })
        })
      } else if (leg.home_score !== undefined && leg.away_score !== undefined) {
        sets = {
          set_1: [
            { team_id: homeTeamId, score: leg.home_score || 0 },
            { team_id: awayTeamId, score: leg.away_score || 0 }
          ]
        }
      }
      
      return { ...leg, sets }
    })
    
    return { ...match, match_id: match.match_id || match.id, legs: normalizedLegs }
  }
  
  // Create legs from match data
  let sets = {}
  if (match.results && Array.isArray(match.results)) {
    match.results.forEach(result => {
      const setNum = result.set_number || 1
      const key = `set_${setNum}`
      if (!sets[key]) sets[key] = []
      sets[key].push({ team_id: result.team_id, score: result.score || 0 })
    })
  } else if (match.home_score !== undefined && match.away_score !== undefined) {
    sets = {
      set_1: [
        { team_id: homeTeamId, score: match.home_score || 0 },
        { team_id: awayTeamId, score: match.away_score || 0 }
      ]
    }
  }
  
  const legs = [{
    id: match.id || match.match_id,
    leg: 1,
    court: match.court || 1,
    status: match.status || (match.is_completed ? 'completed' : 'pending'),
    scheduled_at: match.scheduled_at,
    is_completed: match.is_completed || match.status === 'completed',
    sets
  }]
  
  return {
    ...match,
    match_id: match.match_id || match.id,
    status: match.status || (match.is_completed ? 'completed' : 'pending'),
    legs,
    aggregate_score: match.aggregate_score || { home: match.home_score || 0, away: match.away_score || 0 },
    winner_team_id: match.winner_team_id || (
      match.is_completed && match.home_score > match.away_score
        ? homeTeamId
        : (match.is_completed && match.away_score > match.home_score ? awayTeamId : null)
    )
  }
}

function handleRegister() {
  if (!tournament.value?.id) return
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
