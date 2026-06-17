<template>
  <div class="flex min-h-screen bg-[#f7f9fb] font-body text-on-surface">
    <!-- SideNavBar -->
    <AdminSidebar />

    <!-- Main Content -->
    <main class="ml-64 flex-1">
      <AdminHeader />
      <div class="p-8">
        <!-- Loading State -->
        <div v-if="dashboardLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          <div v-for="i in 4" :key="i" class="h-28 rounded-xl animate-pulse" style="background-color: var(--surface-container-low, #fff0ef);"></div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="mb-8 p-6 rounded-xl text-center" style="background-color: var(--error-container, #ffdad6); color: var(--on-error-container, #93000a);">
          {{ error }}
        </div>

        <!-- Performance Insights -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          <div class="insight-card bg-primary/5 border-primary/10 rounded-xl">
            <span class="material-symbols-outlined text-primary text-4xl mb-4">groups</span>
            <h4 class="font-headline font-bold text-lg mb-1 text-on-surface">Tổng Users</h4>
            <p class="text-3xl font-headline font-extrabold text-primary">{{ dashboardStats?.user_growth?.total?.toLocaleString() ?? '...' }}</p>
            <p class="text-xs text-on-surface-variant mt-2">+{{ dashboardStats?.user_growth?.new_this_week ?? 0 }} tuần này</p>
          </div>
          <div class="insight-card bg-surface-container-low border-outline-variant/10 rounded-xl">
            <span class="material-symbols-outlined text-secondary text-4xl mb-4">sports_tennis</span>
            <h4 class="font-headline font-bold text-lg mb-1 text-on-surface">Kèo Active</h4>
            <p class="text-3xl font-headline font-extrabold text-on-surface">{{ dashboardStats?.mini_tournament_growth?.active_today ?? '...' }}</p>
            <p class="text-xs text-on-surface-variant mt-2">{{ dashboardStats?.mini_tournament_growth?.growth_percent > 0 ? '+' : '' }}{{ dashboardStats?.mini_tournament_growth?.growth_percent ?? 0 }}% so với tuần trước</p>
          </div>
          <div class="insight-card bg-tertiary-fixed-dim/20 border-tertiary/10 rounded-xl">
            <span class="material-symbols-outlined text-tertiary text-4xl mb-4">emoji_events</span>
            <h4 class="font-headline font-bold text-lg mb-1 text-on-surface">Giải Đấu</h4>
            <p class="text-3xl font-headline font-extrabold text-tertiary">{{ dashboardStats?.tournaments_this_month ?? '...' }}</p>
            <p class="text-xs text-on-surface-variant mt-2">Trong tháng này</p>
          </div>
          <div class="insight-card bg-error-container/30 border-error/10 rounded-xl">
            <span class="material-symbols-outlined text-error text-4xl mb-4">analytics</span>
            <h4 class="font-headline font-bold text-lg mb-1 text-on-surface">Tỷ lệ tranh chấp</h4>
            <p class="text-3xl font-headline font-extrabold text-error">{{ dashboardStats?.dispute_rate ?? '...' }}%</p>
            <p class="text-xs text-on-surface-variant mt-2">{{ dashboardStats?.dispute_rate_change > 0 ? '↑' : '↓' }} {{ Math.abs(dashboardStats?.dispute_rate_change ?? 0) }}% so với tuần trước</p>
          </div>
        </div>

        <!-- Tab Navigation -->
        <div class="flex items-center gap-1 mb-8 bg-surface-container-low rounded-2xl p-1.5 w-fit border border-outline-variant/10 shadow-sm">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="switchTab(tab.key)"
            class="tab-btn"
            :class="{ 'tab-btn-active': activeTab === tab.key }"
          >
            <span class="material-symbols-outlined text-lg" :class="{ 'icon-fill': activeTab === tab.key }">{{ tab.icon }}</span>
            <span>{{ tab.label }}</span>
            <span v-if="tab.badge" class="tab-badge" :class="activeTab === tab.key ? 'bg-white/20 text-white' : 'bg-primary/10 text-primary'">{{ tab.badge }}</span>
          </button>
        </div>

        <!-- ==================== TAB: USERS ==================== -->
        <div v-if="activeTab === 'users'">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-headline font-bold text-xl">Quản lý người dùng</h3>
            <div class="relative">
              <input
                class="bg-surface-container-low border-none rounded-xl py-2 pl-10 pr-4 text-sm w-64 focus:ring-2 focus:ring-secondary/20 transition-all outline-none"
                placeholder="Tìm kiếm theo ID hoặc tên..."
                type="text"
              />
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">search</span>
            </div>
          </div>

          <div class="bg-surface-container-lowest rounded-xl overflow-hidden shadow-sm border border-outline-variant/5">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-surface-container-high">
                  <th class="table-head">Thông tin người dùng</th>
                  <th class="table-head">Độ tin cậy</th>
                  <th class="table-head">Số trận</th>
                  <th class="table-head text-right text-right-important">Hành động</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/5">
                <tr v-for="user in paginatedUsers" :key="user.id" class="hover:bg-surface-container-low transition-colors group">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                      <img :src="user.avatar" class="w-10 h-10 rounded-full object-cover grayscale group-hover:grayscale-0 transition-all shadow-sm" />
                      <div>
                        <p class="font-bold text-sm text-on-surface">{{ user.name }}</p>
                        <p class="text-xs text-on-surface-variant">#{{ user.id }} • {{ user.location }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex flex-col gap-1">
                      <div class="flex justify-between items-center w-24">
                        <span class="text-[10px] font-bold" :class="user.reliabilityClass">{{ user.reliability }}%</span>
                      </div>
                      <div class="w-24 h-1.5 bg-surface-container-high rounded-full overflow-hidden">
                        <div class="h-full" :class="user.progressClass" :style="{ width: user.reliability + '%' }"></div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <span class="text-sm font-manrope font-bold text-on-surface">{{ user.matches }}</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <div class="flex justify-end gap-2">
                      <button class="p-2 hover:bg-surface-variant rounded-lg transition-colors text-on-surface-variant">
                        <span class="material-symbols-outlined text-lg">refresh</span>
                      </button>
                      <button :class="user.buttonClass">
                        {{ user.status }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <Pagination :meta="usersMeta" @page-change="onUsersPageChange" />
        </div>

        <!-- ==================== TAB: MATCHES ==================== -->
        <div v-if="activeTab === 'matches'">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-headline font-bold text-xl">Quản lý kèo đấu</h3>
            <div class="relative">
              <input
                class="bg-surface-container-low border-none rounded-xl py-2 pl-10 pr-4 text-sm w-64 focus:ring-2 focus:ring-secondary/20 transition-all outline-none"
                placeholder="Tìm kiếm kèo..."
                type="text"
              />
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">search</span>
            </div>
          </div>

          <div class="bg-surface-container-lowest rounded-xl overflow-hidden shadow-sm border border-outline-variant/5">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-surface-container-high">
                  <th class="table-head">Thời gian</th>
                  <th class="table-head">Sân & Địa điểm</th>
                  <th class="table-head">Người tạo</th>
                  <th class="table-head">Người chơi</th>
                  <th class="table-head text-right text-right-important">Trạng thái</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/5">
                <tr v-for="match in paginatedMatches" :key="match.id" class="hover:bg-surface-container-low transition-colors group cursor-pointer">
                  <td class="px-6 py-4">
                    <div class="font-bold text-sm text-on-surface">{{ match.time }}</div>
                    <div class="text-[10px] text-on-surface-variant">{{ match.date }}</div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="font-bold text-sm text-on-surface">{{ match.court }}</div>
                    <div class="text-[10px] text-on-surface-variant flex items-center gap-1">
                      <span class="material-symbols-outlined text-xs">location_on</span> {{ match.location }}
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                      <img :src="match.creatorAvatar" class="w-8 h-8 rounded-full object-cover shadow-sm" />
                      <div>
                        <p class="font-bold text-sm text-on-surface">{{ match.creator }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex -space-x-2">
                      <img v-for="(p, pi) in match.players" :key="pi" :src="p" class="w-7 h-7 rounded-full border-2 border-surface-container-low object-cover" />
                      <div v-if="match.extra" class="w-7 h-7 rounded-full border-2 border-surface-container-low bg-surface-container-high flex items-center justify-center text-[10px] font-bold">+{{ match.extra }}</div>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <span :class="match.statusClass">{{ match.status }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <Pagination :meta="matchesMeta" @page-change="onMatchesPageChange" />
        </div>

        <!-- ==================== TAB: TOURNAMENTS ==================== -->
        <div v-if="activeTab === 'tournaments'">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-headline font-bold text-xl">Quản lý giải đấu</h3>
            <div class="relative">
              <input
                class="bg-surface-container-low border-none rounded-xl py-2 pl-10 pr-4 text-sm w-64 focus:ring-2 focus:ring-secondary/20 transition-all outline-none"
                placeholder="Tìm kiếm giải đấu..."
                type="text"
              />
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">search</span>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="t in paginatedTournaments" :key="t.id" class="group bg-surface-container-lowest rounded-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 border border-outline-variant/10 shadow-sm">
              <div class="relative h-48 overflow-hidden">
                <img :src="t.image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                <div class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-black/80 to-transparent"></div>
                <div class="absolute bottom-4 left-4 right-4">
                  <h4 class="text-white font-headline font-bold text-lg leading-tight">{{ t.name }}</h4>
                </div>
                <!-- Status badge -->
                <div class="absolute top-3 right-3">
                  <span :class="t.statusClass">{{ t.status }}</span>
                </div>
              </div>
              <div class="p-5 space-y-4">
                <div class="flex justify-between items-center text-on-surface-variant text-xs">
                  <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">calendar_today</span>{{ t.dates }}</span>
                  <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">location_on</span>{{ t.location }}</span>
                </div>
                <div class="flex items-center gap-3 py-2 border-y border-outline-variant/10 text-on-surface-variant">
                  <div class="w-6 h-6 rounded-full bg-surface-container-high border border-white flex items-center justify-center text-[8px] font-bold shadow-sm">{{ t.regCount }}</div>
                  <span class="text-[10px] font-medium">{{ t.regText }}</span>
                </div>
                <div class="flex gap-2">
                  <button class="flex-1 py-2 bg-surface-container-high text-on-surface rounded-xl font-bold text-xs hover:bg-primary hover:text-white transition-all active:scale-95">Chi tiết</button>
                  <button class="flex-1 py-2 bg-primary text-white rounded-xl font-bold text-xs shadow-md shadow-primary/20 active:scale-95 transition-all hover:bg-primary/90">Duyệt</button>
                </div>
              </div>
            </div>
          </div>

          <Pagination :meta="tournamentsMeta" @page-change="onTournamentsPageChange" />
        </div>

        <!-- ==================== TAB: CLUBS ==================== -->
        <div v-if="activeTab === 'clubs'">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-headline font-bold text-xl">Quản lý câu lạc bộ</h3>
            <div class="relative">
              <input
                v-model="clubSearch"
                @input="onClubSearchInput"
                class="bg-surface-container-low border-none rounded-xl py-2 pl-10 pr-4 text-sm w-64 focus:ring-2 focus:ring-secondary/20 transition-all outline-none"
                placeholder="Tìm kiếm câu lạc bộ..."
                type="text"
              />
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">search</span>
            </div>
          </div>

          <div class="bg-surface-container-lowest rounded-xl overflow-hidden shadow-sm border border-outline-variant/5">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-surface-container-high">
                  <th class="table-head">Câu lạc bộ</th>
                  <th class="table-head">Quản trị viên</th>
                  <th class="table-head">Thành viên</th>
                  <th class="table-head">Kèo đang đấu</th>
                  <th class="table-head">Giải đấu</th>
                  <th class="table-head">Trạng thái</th>
                  <th class="table-head text-right text-right-important">Hành động</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/5">
                <tr v-for="club in paginatedClubs" :key="club.id" class="hover:bg-surface-container-low transition-colors group">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                      <img :src="club.avatar" class="w-10 h-10 rounded-full object-cover shadow-sm" />
                      <div>
                        <p class="font-bold text-sm text-on-surface">{{ club.name }}</p>
                        <p class="text-xs text-on-surface-variant truncate max-w-[200px]">{{ club.address }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div v-if="club.admin" class="flex items-center gap-2">
                      <img :src="club.adminAvatar" class="w-7 h-7 rounded-full object-cover shadow-sm" />
                      <span class="text-sm text-on-surface font-medium">{{ club.admin.name }}</span>
                    </div>
                    <span v-else class="text-xs text-on-surface-variant">—</span>
                  </td>
                  <td class="px-6 py-4">
                    <span class="text-sm font-manrope font-bold text-on-surface">{{ club.membersCount }}</span>
                  </td>
                  <td class="px-6 py-4">
                    <span class="text-sm font-manrope font-bold text-secondary">{{ club.activeMatchesCount }}</span>
                  </td>
                  <td class="px-6 py-4">
                    <span class="text-sm font-manrope font-bold text-tertiary">{{ club.activeTournamentsCount }}</span>
                  </td>
                  <td class="px-6 py-4">
                    <span :class="club.statusClass">{{ club.statusLabel }}</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <div class="flex justify-end gap-2">
                      <button
                        :disabled="togglingId === club.id"
                        @click="toggleClubStatus(club)"
                        :class="club.isBanned
                          ? 'px-3 py-1 bg-tertiary text-white rounded-lg text-xs font-bold shadow-md hover:bg-tertiary/90 transition-colors'
                          : 'px-3 py-1 bg-error text-white rounded-lg text-xs font-bold shadow-lg shadow-error/20 hover:bg-error/90 transition-colors'"
                      >
                        {{ togglingId === club.id ? '...' : (club.isBanned ? 'Mở khóa' : 'Khoá') }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <Pagination :meta="clubsMeta" @page-change="onClubsPageChange" />
        </div>

        <!-- ==================== TAB: VENUES ==================== -->
        <div v-if="activeTab === 'venues'">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-headline font-bold text-xl">Quản lý sân thi đấu</h3>
            <div class="relative">
              <input
                v-model="venueSearch"
                @input="onVenueSearchInput"
                class="bg-surface-container-low border-none rounded-xl py-2 pl-10 pr-4 text-sm w-64 focus:ring-2 focus:ring-secondary/20 transition-all outline-none"
                placeholder="Tìm kiếm sân thi đấu..."
                type="text"
              />
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">search</span>
            </div>
          </div>

          <div class="bg-surface-container-lowest rounded-xl overflow-hidden shadow-sm border border-outline-variant/5">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-surface-container-high">
                  <th class="table-head">Sân thi đấu</th>
                  <th class="table-head">Địa chỉ</th>
                  <th class="table-head">Môn thể thao</th>
                  <th class="table-head">Kèo đang đấu</th>
                  <th class="table-head">Giải đấu</th>
                  <th class="table-head">Trạng thái</th>
                  <th class="table-head text-right text-right-important">Hành động</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/5">
                <tr v-for="venue in paginatedVenues" :key="venue.id" class="hover:bg-surface-container-low transition-colors group">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                      <img v-if="venue.imageUrl" :src="venue.imageUrl" class="w-10 h-10 rounded-lg object-cover shadow-sm" />
                      <div v-else class="w-10 h-10 rounded-lg bg-surface-container-high flex items-center justify-center">
                        <span class="material-symbols-outlined text-on-surface-variant text-lg">stadium</span>
                      </div>
                      <div>
                        <p class="font-bold text-sm text-on-surface">{{ venue.name }}</p>
                        <p class="text-xs text-on-surface-variant">{{ venue.summary }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <span class="text-xs text-on-surface-variant max-w-[180px] truncate block">{{ venue.address }}</span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex flex-wrap gap-1">
                      <span v-for="sport in venue.sports.slice(0, 2)" :key="sport.id" class="px-2 py-0.5 bg-surface-container-high text-on-surface-variant text-[10px] font-bold rounded-full">
                        {{ sport.name }}
                      </span>
                      <span v-if="venue.sports.length > 2" class="px-2 py-0.5 bg-surface-container-high text-on-surface-variant text-[10px] font-bold rounded-full">
                        +{{ venue.sports.length - 2 }}
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <span class="text-sm font-manrope font-bold text-secondary">{{ venue.activeMatchesCount }}</span>
                  </td>
                  <td class="px-6 py-4">
                    <span class="text-sm font-manrope font-bold text-tertiary">{{ venue.activeTournamentsCount }}</span>
                  </td>
                  <td class="px-6 py-4">
                    <span :class="venue.statusClass">{{ venue.statusLabel }}</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <div class="flex justify-end gap-2">
                      <button
                        :disabled="togglingId === venue.id"
                        @click="toggleVenueStatus(venue)"
                        :class="venue.isBanned
                          ? 'px-3 py-1 bg-tertiary text-white rounded-lg text-xs font-bold shadow-md hover:bg-tertiary/90 transition-colors'
                          : 'px-3 py-1 bg-error text-white rounded-lg text-xs font-bold shadow-lg shadow-error/20 hover:bg-error/90 transition-colors'"
                      >
                        {{ togglingId === venue.id ? '...' : (venue.isBanned ? 'Mở khóa' : 'Khoá') }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <Pagination :meta="venuesMeta" @page-change="onVenuesPageChange" />
        </div>





      </div>
    </main>
  </div>
</template>

<script setup>
import AdminSidebar from '@/components/organisms/AdminSidebar.vue'
import AdminHeader from '@/components/organisms/AdminHeader.vue'
import Pagination from '@/components/molecules/Pagination.vue'
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { get, post } from '@/utils/httpRequest.js'
import { formatedDate } from '@/composables/formatedDate.js'

const route = useRoute()
const router = useRouter()

// ===========================
// LOADING & ERROR STATE
// ===========================
const loading = ref(false)
const dashboardLoading = ref(true)
const dashboardStats = ref(null)
const error = ref(null)

// ===========================
// DATA STATE
// ===========================
const allUsers = ref([])
const allMatches = ref([])
const allTournaments = ref([])
const allClubs = ref([])
const allVenues = ref([])
const togglingId = ref(null)
const clubSearch = ref('')
const venueSearch = ref('')

// Pagination metadata
const usersMeta = ref({ current_page: 1, last_page: 1, total: 0 })
const matchesMeta = ref({ current_page: 1, last_page: 1, total: 0 })
const tournamentsMeta = ref({ current_page: 1, last_page: 1, total: 0 })
const clubsMeta = ref({ current_page: 1, last_page: 1, total: 0 })
const venuesMeta = ref({ current_page: 1, last_page: 1, total: 0 })

// ===========================
// TAB STATE
// ===========================
const tabs = computed(() => [
  { key: 'users', label: 'Người dùng', icon: 'group', badge: dashboardStats.value?.total_users ? formatCount(dashboardStats.value.total_users) : '...' },
  { key: 'matches', label: 'Kèo đấu', icon: 'sports_tennis', badge: dashboardStats.value?.active_matches ? formatCount(dashboardStats.value.active_matches) : '...' },
  { key: 'tournaments', label: 'Giải đấu', icon: 'emoji_events', badge: dashboardStats.value?.total_tournaments ? formatCount(dashboardStats.value.total_tournaments) : '...' },
  { key: 'clubs', label: 'Câu lạc bộ', icon: 'diversity_3', badge: dashboardStats.value?.total_clubs ? formatCount(dashboardStats.value.total_clubs) : '...' },
  { key: 'venues', label: 'Sân thi đấu', icon: 'stadium', badge: dashboardStats.value?.total_venues ? formatCount(dashboardStats.value.total_venues) : '...' },
])

const formatCount = (num) => {
  if (!num) return '...'
  if (num >= 1000) return (num / 1000).toFixed(1) + 'K'
  return num.toString()
}

const validTabs = new Set(['users', 'matches', 'tournaments', 'clubs', 'venues'])
const activeTab = ref('users')

const switchTab = (tabKey) => {
  activeTab.value = tabKey
  router.replace({ query: { ...route.query, tab: tabKey } })
}

// Sync tab from URL query on mount & on route change
const syncTabFromRoute = () => {
  const queryTab = route.query.tab
  if (queryTab && validTabs.has(queryTab)) {
    activeTab.value = queryTab
  }
}

onMounted(() => {
  syncTabFromRoute()
  fetchDashboardStats()
})
watch(() => route.query.tab, syncTabFromRoute)

// Debounced search
let clubSearchTimer = null
let venueSearchTimer = null

const onClubSearchInput = () => {
  clearTimeout(clubSearchTimer)
  clubSearchTimer = setTimeout(() => fetchClubs(1), 400)
}

const onVenueSearchInput = () => {
  clearTimeout(venueSearchTimer)
  venueSearchTimer = setTimeout(() => fetchVenues(1), 400)
}

// ===========================
// FETCH FUNCTIONS
// ===========================
const fetchDashboardStats = async () => {
  try {
    dashboardLoading.value = true
    const res = await get('/admin/dashboard')
    dashboardStats.value = res.data.data
  } catch (e) {
    console.error('Dashboard stats error:', e)
  } finally {
    dashboardLoading.value = false
  }
}

const fetchUsers = async (page = 1) => {
  try {
    loading.value = true
    const res = await get('/admin/users', { params: { page, limit: 10 } })
    allUsers.value = res.data.data
    usersMeta.value = {
      current_page: res.data.meta?.current_page ?? 1,
      last_page: res.data.meta?.last_page ?? 1,
      total: res.data.meta?.total ?? 0
    }
  } catch (e) {
    error.value = 'Không thể tải danh sách người dùng.'
    console.error('Users error:', e)
  } finally {
    loading.value = false
  }
}

const fetchMatches = async (page = 1) => {
  try {
    loading.value = true
    const res = await get('/admin/mini-tournaments', { params: { page, limit: 10 } })
    allMatches.value = res.data.data
    matchesMeta.value = {
      current_page: res.data.meta?.current_page ?? 1,
      last_page: res.data.meta?.last_page ?? 1,
      total: res.data.meta?.total ?? 0
    }
  } catch (e) {
    error.value = 'Không thể tải danh sách kèo đấu.'
    console.error('Matches error:', e)
  } finally {
    loading.value = false
  }
}

const fetchTournaments = async (page = 1) => {
  try {
    loading.value = true
    const res = await get('/admin/tournaments', { params: { page, limit: 12 } })
    allTournaments.value = res.data.data
    tournamentsMeta.value = {
      current_page: res.data.meta?.current_page ?? 1,
      last_page: res.data.meta?.last_page ?? 1,
      total: res.data.meta?.total ?? 0
    }
  } catch (e) {
    error.value = 'Không thể tải danh sách giải đấu.'
    console.error('Tournaments error:', e)
  } finally {
    loading.value = false
  }
}

const fetchClubs = async (page = 1) => {
  try {
    loading.value = true
    const res = await get('/admin/clubs', { params: { page, limit: 10, keyword: clubSearch.value || undefined } })
    allClubs.value = res.data.data
    clubsMeta.value = {
      current_page: res.data.meta?.current_page ?? 1,
      last_page: res.data.meta?.last_page ?? 1,
      total: res.data.meta?.total ?? 0
    }
  } catch (e) {
    error.value = 'Không thể tải danh sách câu lạc bộ.'
    console.error('Clubs error:', e)
  } finally {
    loading.value = false
  }
}

const fetchVenues = async (page = 1) => {
  try {
    loading.value = true
    const res = await get('/admin/competition-locations', { params: { page, limit: 10, keyword: venueSearch.value || undefined } })
    allVenues.value = res.data.data
    venuesMeta.value = {
      current_page: res.data.meta?.current_page ?? 1,
      last_page: res.data.meta?.last_page ?? 1,
      total: res.data.meta?.total ?? 0
    }
  } catch (e) {
    error.value = 'Không thể tải danh sách sân thi đấu.'
    console.error('Venues error:', e)
  } finally {
    loading.value = false
  }
}

// Fetch data when tab changes
watch(activeTab, (tab) => {
  switch (tab) {
    case 'users':
      fetchUsers()
      break
    case 'matches':
      fetchMatches()
      break
    case 'tournaments':
      fetchTournaments()
      break
    case 'clubs':
      fetchClubs()
      break
    case 'venues':
      fetchVenues()
      break
  }
}, { immediate: true })

// ===========================
// PAGE CHANGE HANDLERS
// ===========================
const onUsersPageChange = (page) => {
  usersMeta.value.current_page = page
  fetchUsers(page)
}

const onMatchesPageChange = (page) => {
  matchesMeta.value.current_page = page
  fetchMatches(page)
}

const onTournamentsPageChange = (page) => {
  tournamentsMeta.value.current_page = page
  fetchTournaments(page)
}

const onClubsPageChange = (page) => {
  clubsMeta.value.current_page = page
  fetchClubs(page)
}

const onVenuesPageChange = (page) => {
  venuesMeta.value.current_page = page
  fetchVenues(page)
}

// ===========================
// MAPPED DATA
// ===========================
const paginatedUsers = computed(() => {
  return allUsers.value.map(u => ({
    id: u.id,
    name: u.full_name,
    avatar: u.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.full_name ?? '?'),
    location: u.location_id || '—',
    reliability: u.trust_score ? Math.round(u.trust_score) : 0,
    matches: (u.sports?.reduce((sum, s) => sum + (s.total_matches || 0), 0)) || 0,
    status: u.is_banned ? 'Banned' : 'Active',
    reliabilityClass: u.is_banned ? 'text-error' : 'text-on-surface',
    progressClass: u.is_banned ? 'bg-error' : 'bg-tertiary',
    buttonClass: u.is_banned
      ? 'px-3 py-1 bg-error text-on-error rounded-lg text-xs font-bold shadow-lg shadow-error/20'
      : 'px-3 py-1 bg-surface-container-low text-primary rounded-lg text-xs font-bold hover:bg-error-container transition-colors'
  }))
})

const paginatedMatches = computed(() => {
  return allMatches.value.map(m => {
    const statusMap = {
      1: { label: 'Nháp', class: 'px-3 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
      2: { label: 'Mở', class: 'px-3 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
    }
    const mapped = statusMap[m.status] ?? statusMap[2]
    return {
      id: m.id,
      time: formatedDate(m.start_time, 'time') || '—',
      date: formatedDate(m.start_time, 'dateDMY') || '—',
      court: m.name || 'Kèo không tên',
      location: m.competition_location?.name || '—',
      creator: m.creator?.full_name || '—',
      creatorAvatar: m.creator?.avatar_url || 'https://ui-avatars.com/api/?name=U',
      players: m.participants?.slice(0, 4).map(p => p.user?.avatar_url) || [],
      extra: Math.max(0, (m.max_players || 4) - (m.participants?.length || 0)),
      status: mapped.label,
      statusClass: mapped.class
    }
  })
})

const paginatedTournaments = computed(() => {
  return allTournaments.value.map(t => {
    const statusMap = {
      1: { label: 'Nháp', class: 'px-2 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
      2: { label: 'Đang mở', class: 'px-2 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
    }
    const mapped = statusMap[t.status] ?? statusMap[2]
    return {
      id: t.id,
      name: t.name || 'Giải không tên',
      status: mapped.label,
      statusClass: mapped.class,
      dates: t.start_date ? formatedDate(t.start_date, 'dateDMY') : '—',
      location: t.competition_location?.name || '—',
      regCount: t.fee || '—',
      regText: t.fee ? `${Number(t.fee).toLocaleString()} VNĐ` : 'Miễn phí',
      image: t.poster || 'https://images.unsplash.com/photo-1530549387789-4c1017266635?w=800&q=80'
    }
  })
})

const paginatedClubs = computed(() => {
  return allClubs.value.map(c => {
    const isBanned = c.is_banned ?? (c.status === 'banned' || c.status === 'suspended')
    return {
      id: c.id,
      name: c.name,
      logoUrl: c.logo_url || null,
      address: c.address || '—',
      admin: c.admin ? { name: c.admin.full_name, avatar: c.admin.avatar_url || null } : null,
      membersCount: c.members_count ?? 0,
      activeMatchesCount: c.active_matches_count ?? 0,
      activeTournamentsCount: c.active_tournaments_count ?? 0,
      announcementsCount: c.announcements_count ?? 0,
      summary: c.summary || '',
      isVerified: c.is_verified,
      isPublic: c.is_public,
      isBanned,
      statusLabel: isBanned ? 'Banned' : 'Active',
      statusClass: isBanned
        ? 'px-3 py-1 bg-error text-on-error rounded-lg text-xs font-bold shadow-lg shadow-error/20'
        : 'px-3 py-1 bg-tertiary-container text-on-tertiary-container rounded-lg text-xs font-bold',
      avatar: c.logo_url || (c.name ? 'https://ui-avatars.com/api/?name=' + encodeURIComponent(c.name) : null),
      adminAvatar: c.admin?.avatar_url || (c.admin?.full_name ? 'https://ui-avatars.com/api/?name=' + encodeURIComponent(c.admin.full_name) : null),
    }
  })
})

const paginatedVenues = computed(() => {
  return allVenues.value.map(v => {
    const isBanned = v.is_banned ?? v.status === 'banned'
    return {
      id: v.id,
      name: v.name,
      image: v.image || null,
      address: v.address || '—',
      activeMatchesCount: v.active_matches_count ?? 0,
      activeTournamentsCount: v.active_tournaments_count ?? 0,
      summary: v.summary || '',
      sports: v.sports ?? [],
      isBanned,
      statusLabel: isBanned ? 'Banned' : 'Active',
      statusClass: isBanned
        ? 'px-3 py-1 bg-error text-on-error rounded-lg text-xs font-bold shadow-lg shadow-error/20'
        : 'px-3 py-1 bg-tertiary-container text-on-tertiary-container rounded-lg text-xs font-bold',
      imageUrl: v.image || (v.name ? 'https://ui-avatars.com/api/?name=' + encodeURIComponent(v.name) + '&background=random' : null),
    }
  })
})

const toggleClubStatus = async (club) => {
  const nextBanned = !club.isBanned
  togglingId.value = club.id
  try {
    await post(`/admin/clubs/${club.id}/ban`, { is_banned: nextBanned })
    await fetchClubs(clubsMeta.value.current_page)
  } catch (e) {
    console.error('Toggle club ban error:', e)
  } finally {
    togglingId.value = null
  }
}

const toggleVenueStatus = async (venue) => {
  const nextBanned = !venue.isBanned
  togglingId.value = venue.id
  try {
    await post(`/admin/competition-locations/${venue.id}/ban`, { is_banned: nextBanned })
    await fetchVenues(venuesMeta.value.current_page)
  } catch (e) {
    console.error('Toggle venue ban error:', e)
  } finally {
    togglingId.value = null
  }
}
</script>

<style scoped>
.font-headline { font-family: 'Manrope', sans-serif; }
.font-body { font-family: 'Inter', sans-serif; }

.tab-btn {
  @apply flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl transition-all duration-200 cursor-pointer;
  color: var(--on-surface-variant, #5b403d);
}

.tab-btn:hover {
  background-color: var(--surface-container-high, #ffe2de);
}

.tab-btn-active {
  @apply bg-[#af101a] text-white shadow-lg shadow-red-900/15 hover:bg-[#af101a];
}

.tab-badge {
  @apply text-[10px] font-bold px-1.5 py-0.5 rounded-full;
}

.table-head {
  @apply px-6 py-4 text-[10px] uppercase tracking-wider font-extrabold;
  font-family: 'Inter', sans-serif;
  color: var(--on-surface-variant, #5b403d);
}

.text-right-important {
  @apply text-right;
}

.insight-card {
  @apply p-6 border transition-all hover:scale-[1.02] cursor-default;
}

.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}

.icon-fill {
  font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
</style>
