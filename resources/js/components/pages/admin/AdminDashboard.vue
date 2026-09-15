<template>
  <div class="flex min-h-screen bg-[#f7f9fb] font-body text-slate-800 overflow-x-hidden">
    <!-- SideNavBar -->
    <AdminSidebar />

    <!-- Main Content Area -->
    <main class="flex-1 md:ml-64 min-h-screen bg-[#f7f9fb] pb-16">
      <AdminHeader />

      <!-- Loading State -->
      <div v-if="loading" class="p-8 max-w-[1400px] mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="i in 4" :key="i" class="h-28 rounded-2xl animate-pulse bg-white border border-slate-200/80 shadow-sm"></div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="p-8">
        <div class="p-6 rounded-2xl text-center bg-red-50 text-red-700 border border-red-200">
          {{ error }}
        </div>
      </div>

      <!-- Content -->
      <div v-else class="p-8 space-y-8 max-w-[1400px] mx-auto">
        <!-- Hero Stats -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="stat in topStats" :key="stat.label"
            class="bg-white rounded-2xl p-6 transition-all border-l-4 border-y border-r border-slate-200/80 shadow-sm"
            :class="stat.borderColor"
          >
            <p class="text-[11px] font-bold uppercase tracking-widest mb-2 text-slate-500 font-body">{{ stat.label }}</p>
            <div class="flex items-baseline gap-3">
              <h2 class="text-3xl font-extrabold" style="font-family: 'Manrope', sans-serif;" :style="{ color: stat.valueColor }">{{ stat.value }}</h2>
              <span v-if="stat.trend" class="text-xs font-bold flex items-center text-sky-700">
                <span class="material-symbols-outlined text-xs mr-0.5">trending_up</span>
                {{ stat.trend }}
              </span>
              <span v-else-if="stat.subtext" class="text-[10px] font-medium text-slate-400">{{ stat.subtext }}</span>
            </div>
          </div>
        </section>

        <!-- Urgent Alerts -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="p-5 rounded-2xl flex items-center justify-between border border-red-200 bg-red-50/50 shadow-sm">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-full flex items-center justify-center shadow-md text-white bg-red-600">
                <span class="material-symbols-outlined icon-fill">gavel</span>
              </div>
              <div>
                <h3 class="font-bold text-red-950" style="font-family: 'Manrope', sans-serif;">{{ disputeAlert?.count ?? 0 }} Kết quả đang Tranh chấp</h3>
                <p class="text-sm text-red-700">Yêu cầu can thiệp ngay lập tức.</p>
              </div>
            </div>
            <button class="px-4 py-2 rounded-xl text-sm font-bold shadow-md text-white transition-all hover:opacity-90 active:scale-95 bg-red-600">Xử lý ngay</button>
          </div>

          <div class="p-5 rounded-2xl flex items-center justify-between border border-slate-200/80 bg-white shadow-sm">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-full flex items-center justify-center bg-amber-50 text-amber-600">
                <span class="material-symbols-outlined">report</span>
              </div>
              <div>
                <h3 class="font-bold text-slate-800" style="font-family: 'Manrope', sans-serif;">{{ reportAlert?.count ?? 0 }} Report Vi phạm & Toxic</h3>
                <p class="text-sm text-slate-500">Kiểm tra lịch sử chat và hành vi người dùng.</p>
              </div>
            </div>
            <button class="px-4 py-2 rounded-xl text-sm font-bold transition-all hover:bg-slate-200 active:scale-95 bg-slate-100 text-slate-700">Review</button>
          </div>
        </section>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
          <!-- New Users -->
          <section class="xl:col-span-1 space-y-4">
            <div class="flex items-center justify-between px-2">
              <h3 class="font-bold text-lg text-slate-800" style="font-family: 'Manrope', sans-serif;">User Mới Đăng Ký</h3>
              <router-link :to="{ name: 'admin.moderation', query: { tab: 'users' } }"
                class="text-xs font-bold uppercase tracking-tight hover:underline cursor-pointer text-[#b3111b]">Xem tất cả</router-link>
            </div>
            <div class="grid grid-cols-1 gap-4">
              <div v-for="user in mappedNewUsers" :key="user.id"
                class="flex items-center gap-4 p-3 rounded-2xl transition-all cursor-pointer group bg-white border border-slate-200/80 shadow-sm hover:border-slate-300">
                <div class="relative flex-shrink-0">
                  <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-white shadow-sm">
                    <img :src="user.avatar_url" alt="Avatar" class="w-full h-full object-cover" />
                  </div>
                  <div class="absolute bottom-0 left-0 text-white text-[9px] font-bold w-6 h-6 rounded-full flex items-center justify-center border-2 border-white shadow-sm"
                    style="background-color: #4392E0;">{{ user.rating }}</div>
                  <div class="absolute bottom-0 -right-0.5 w-5 h-5 rounded-full border-2 border-white shadow-sm" style="background-color: #00B16A;"></div>
                </div>
                <div class="flex flex-col justify-center gap-0.5">
                  <h4 class="font-bold text-[17px] leading-tight transition-colors group-hover:text-[#b3111b] text-slate-800">{{ user.full_name }}</h4>
                  <p class="text-[13px] font-medium text-slate-400">Tham gia {{ user.joinedDaysAgo }}</p>
                </div>
              </div>
              <div v-if="mappedNewUsers.length === 0" class="text-center py-8 text-slate-400">
                Chưa có user mới đăng ký.
              </div>
            </div>
          </section>

          <!-- Active Matches -->
          <section class="xl:col-span-2 space-y-4">
            <div class="flex items-center justify-between px-2">
              <h3 class="font-bold text-lg text-slate-800" style="font-family: 'Manrope', sans-serif;">Kèo mới đang mở</h3>
              <router-link :to="{ name: 'admin.moderation', query: { tab: 'matches' } }"
                class="text-xs font-bold uppercase tracking-tight hover:underline cursor-pointer text-[#b3111b]">Xem tất cả</router-link>
            </div>
            <div class="rounded-2xl shadow-sm border border-slate-200/80 bg-white overflow-hidden">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="table-head">Thời gian</th>
                    <th class="table-head">Kèo</th>
                    <th class="table-head">Địa điểm</th>
                    <th class="table-head">Người chơi</th>
                    <th class="table-head text-right">Trạng thái</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="match in openMatches" :key="match.id"
                    class="transition-colors group cursor-pointer hover:bg-slate-50/80">
                    <td class="px-6 py-4">
                      <div class="font-bold text-sm text-slate-800">{{ match.time }}</div>
                      <div class="text-[10px] text-slate-400">{{ match.date }}</div>
                    </td>
                    <td class="px-6 py-4">
                      <div class="font-bold text-sm text-slate-800">{{ match.title }}</div>
                    </td>
                    <td class="px-6 py-4">
                      <span class="text-sm font-medium text-slate-600">{{ match.location || '—' }}</span>
                    </td>
                    <td class="px-6 py-4">
                      <span class="text-sm font-medium text-slate-600">
                        {{ match.players_count }}/4 người
                      </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                      <span :class="match.statusClass">{{ match.statusLabel }}</span>
                    </td>
                  </tr>
                  <tr v-if="openMatches.length === 0">
                    <td colspan="5" class="px-6 py-8 text-center text-slate-400">Không có kèo nào đang mở.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- Tournaments section -->
        <section class="space-y-4">
          <div class="flex items-center justify-between px-2">
            <h3 class="font-bold text-lg text-slate-800" style="font-family: 'Manrope', sans-serif;">Giải đấu mới</h3>
            <router-link :to="{ name: 'admin.moderation', query: { tab: 'tournaments' } }"
              class="text-xs font-bold uppercase tracking-tight hover:underline cursor-pointer text-[#b3111b]">Xem tất cả</router-link>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="t in openTournaments" :key="t.id"
              class="group rounded-2xl overflow-hidden transition-all duration-300 transform hover:-translate-y-1 shadow-sm bg-white border border-slate-200/80">
              <div class="relative h-48 overflow-hidden">
                <img :src="t.image" alt="Banner giải đấu" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                <div class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-black/80 to-transparent"></div>
                <div class="absolute bottom-4 left-4 right-4">
                  <h4 class="text-white font-bold text-lg leading-tight" style="font-family: 'Manrope', sans-serif;">{{ t.name }}</h4>
                </div>
              </div>
              <div class="p-5 space-y-4">
                <div class="flex justify-between items-center text-xs text-slate-500">
                  <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">calendar_today</span>{{ t.dates }}</span>
                  <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">location_on</span>{{ t.location }}</span>
                </div>
                <div class="flex items-center gap-3 py-2 border-y border-slate-100 text-slate-500">
                  <div class="w-6 h-6 rounded-full border border-slate-200 bg-slate-100 flex items-center justify-center text-[8px] font-bold text-slate-700 shadow-sm">{{ t.regCount }}</div>
                  <span class="text-[10px] font-medium">{{ t.regText }}</span>
                </div>
                <button class="w-full py-2.5 rounded-xl font-bold text-sm shadow-md transition-all hover:opacity-90 active:scale-95 text-white bg-[#b3111b]">Register Now</button>
              </div>
            </div>
            <div v-if="openTournaments.length === 0" class="col-span-full text-center py-8 text-slate-400">
              Không có giải đấu nào đang mở.
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<script setup>
import AdminSidebar from '@/components/organisms/AdminSidebar.vue'
import AdminHeader from '@/components/organisms/AdminHeader.vue'
import { get } from '@/utils/httpRequest.js'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { formatedDate } from '@/composables/formatedDate.js'

const loading = ref(true)
const error = ref(null)
const dashboardData = ref(null)

// ---------- Socket listeners ----------
let echoChannel = null

const setupSocketListeners = () => {
    if (!window.Echo) {
        return
    }

    const channelName = 'DashboardAdminChannel'

    echoChannel = window.Echo.private(channelName)
    echoChannel
        .subscribed(() => {
        })
        .error((status) => {
        })
        // .listen() trả this nên chain được
        .listen('.super_admin.tournament', (e) => {
            handleTournamentEvent(e)
        })
        .listen('.super_admin.mini_tournament', (e) => {
            handleMiniTournamentEvent(e)
        })
        .listen('.super_admin.match', (e) => {
            handleMatchEvent(e)
        })
        .listen('.super_admin.dispute', (e) => {
            handleDisputeEvent(e)
        })
        .listen('.super_admin.report', (e) => {
            handleReportEvent(e)
        })
        .listen('.super_admin.payment', (e) => {
            handlePaymentEvent(e)
        })
        .listen('.super_admin.dashboard_stat', (e) => {
            handleDashboardStatEvent(e)
        })
        .listen('.super_admin.user', (e) => {
            handleUserEvent(e)
        })
}

const handleTournamentEvent = (e) => {
    const { action, data } = e

    if (!dashboardData.value) {
        return
    }

    switch (action) {
        case 'created':
            if (!dashboardData.value.open_tournaments) {
                dashboardData.value.open_tournaments = []
            }
            dashboardData.value.open_tournaments = [
                formatTournament(data),
                ...dashboardData.value.open_tournaments,
            ]
            // Update stat counters
            if (dashboardData.value.active_tournaments !== undefined) {
                dashboardData.value.active_tournaments++
            }
            // NOTE: tournaments_this_month is handled by handleDashboardStatEvent
            // via DashboardStatUpdated event to avoid double increment
            break
        case 'updated':
            if (dashboardData.value.open_tournaments) {
                const idx = dashboardData.value.open_tournaments.findIndex(t => t.id === data.id)
                if (idx >= 0) {
                    dashboardData.value.open_tournaments[idx] = {
                        ...dashboardData.value.open_tournaments[idx],
                        ...data,
                    }
                }
            }
            break
        case 'deleted':
            if (dashboardData.value.open_tournaments) {
                dashboardData.value.open_tournaments = dashboardData.value.open_tournaments.filter(t => t.id !== data.id)
            }
            // NOTE: active_tournaments is handled by handleDashboardStatEvent
            // via DashboardStatUpdated event to avoid double decrement.
            break
        case 'member_added':
            if (dashboardData.value.open_tournaments) {
                const t = dashboardData.value.open_tournaments.find(t => t.id === data.tournament_id)
                if (t) {
                    t.participants_count = (t.participants_count || 0) + 1
                    if (data.member_type === 'guest') {
                        t.latest_guest = data.member
                    }
                }
            }
            break
    }
}

const handleMiniTournamentEvent = (e) => {
    const { action, data } = e

    if (!dashboardData.value) {
        return
    }

    switch (action) {
        case 'created':
            if (!dashboardData.value.open_mini_tournaments) {
                dashboardData.value.open_mini_tournaments = []
            }
            dashboardData.value.open_mini_tournaments = [
                formatMiniTournament(data),
                ...dashboardData.value.open_mini_tournaments,
            ]
            // NOTE: mini_tournament_growth.active_today is handled by handleDashboardStatEvent
            // via DashboardStatUpdated event to avoid double increment.
            // growth_percent will be recalculated there.
            break
        case 'updated':
            if (dashboardData.value.open_mini_tournaments) {
                const idx = dashboardData.value.open_mini_tournaments.findIndex(m => m.id === data.id)
                if (idx >= 0) {
                    dashboardData.value.open_mini_tournaments[idx] = {
                        ...dashboardData.value.open_mini_tournaments[idx],
                        ...data,
                    }
                }
            }
            break
        case 'deleted':
            if (dashboardData.value.open_mini_tournaments) {
                dashboardData.value.open_mini_tournaments = dashboardData.value.open_mini_tournaments.filter(m => m.id !== data.id)
            }
            // NOTE: mini_tournament_growth is handled by handleDashboardStatEvent
            // via DashboardStatUpdated event to avoid double decrement.
            break
        case 'member_added':
            if (dashboardData.value.open_mini_tournaments) {
                const m = dashboardData.value.open_mini_tournaments.find(m => m.id === data.mini_tournament_id)
                if (m) {
                    m.players_count = (m.players_count || 0) + 1
                    if (data.member_type === 'guest') {
                        m.latest_guest = data.member
                    }
                }
            }
            break
    }
}

const handleMatchEvent = (e) => {}

const handleDisputeEvent = (e) => {
    if (!dashboardData.value) return
    switch (e.action) {
        case 'opened':
            dashboardData.value.open_disputes_count = (dashboardData.value.open_disputes_count ?? 0) + 1
            break
        case 'resolved':
            dashboardData.value.open_disputes_count = Math.max(0, (dashboardData.value.open_disputes_count ?? 1) - 1)
            break
    }
}

const handleReportEvent = (e) => {
    if (!dashboardData.value) return
    if (e.action === 'created') {
        dashboardData.value.pending_reports_count = (dashboardData.value.pending_reports_count ?? 0) + 1
    }
}

const handlePaymentEvent = (e) => {
    if (!dashboardData.value) return
    if (e.action === 'confirmed') {
        dashboardData.value.monthly_revenue = (dashboardData.value.monthly_revenue ?? 0) + (e.data?.amount ?? 0)
        dashboardData.value.total_revenue = (dashboardData.value.total_revenue ?? 0) + (e.data?.amount ?? 0)
    }
}

const handleDashboardStatEvent = (e) => {
    if (!dashboardData.value) return
    const payload = e.data ?? e
    const { stat_key, value } = payload
    switch (stat_key) {
        case 'active_tournaments':
            if (e.action === 'incremented') {
                dashboardData.value.active_tournaments = (dashboardData.value.active_tournaments ?? 0) + 1
            } else if (e.action === 'decremented' && dashboardData.value.active_tournaments > 0) {
                dashboardData.value.active_tournaments--
            }
            break
        case 'tournaments_this_month':
            if (e.action === 'incremented') {
                dashboardData.value.tournaments_this_month = (dashboardData.value.tournaments_this_month ?? 0) + 1
            } else if (e.action === 'decremented' && dashboardData.value.tournaments_this_month > 0) {
                dashboardData.value.tournaments_this_month--
            }
            break
        case 'user_growth_week':
            if (e.action === 'incremented' && dashboardData.value.user_growth) {
                dashboardData.value.user_growth.new_this_week = (dashboardData.value.user_growth.new_this_week ?? 0) + 1
            } else if (e.action === 'decremented' && dashboardData.value.user_growth && dashboardData.value.user_growth.new_this_week > 0) {
                dashboardData.value.user_growth.new_this_week--
            }
            break
        case 'mini_tournament_growth':
            if (dashboardData.value.mini_tournament_growth) {
                if (e.action === 'incremented') {
                    dashboardData.value.mini_tournament_growth.active_today = (dashboardData.value.mini_tournament_growth.active_today ?? 0) + 1
                } else if (e.action === 'decremented' && dashboardData.value.mini_tournament_growth.active_today > 0) {
                    dashboardData.value.mini_tournament_growth.active_today--
                }
                // Recalculate growth_percent
                const newToday = dashboardData.value.mini_tournament_growth.active_today
                const yesterday = dashboardData.value.mini_tournament_growth.active_yesterday ?? 0
                dashboardData.value.mini_tournament_growth.growth_percent = yesterday > 0
                    ? Math.round(((newToday - yesterday) / yesterday) * 100)
                    : (newToday > 0 ? 100 : 0)
            }
            break
    }
}

const handleUserEvent = (e) => {
    const { action, data } = e
    if (!dashboardData.value) return

    switch (action) {
        case 'created':
            if (!dashboardData.value.recent_new_users) {
                dashboardData.value.recent_new_users = []
            }
            dashboardData.value.recent_new_users = [
                data,
                ...dashboardData.value.recent_new_users,
            ]
            // Update total users count
            if (dashboardData.value.user_growth) {
                dashboardData.value.user_growth.total =
                    (dashboardData.value.user_growth.total ?? 0) + 1
                dashboardData.value.user_growth.new_this_week =
                    (dashboardData.value.user_growth.new_this_week ?? 0) + 1
            }
            break
    }
}

// ---------- Format helpers ----------
const formatTournament = (data) => ({
    id: data.id,
    name: data.name,
    poster_url: data.poster_url || 'https://images.unsplash.com/photo-1530549387789-4c1017266635?w=800&q=80',
    competition_location: data.competition_location || data.club,
    start_date: data.start_date,
    fee: data.fee,
    status: data.status,
    participants_count: data.participants_count ?? 0,
})

const formatMiniTournament = (data) => ({
    id: data.id,
    name: data.name,
    start_time: data.start_time,
    competition_location: data.competition_location,
    players_count: data.players_count ?? 0,
    status: data.status,
    has_dispute: data.has_dispute ?? 0,
})

// ---------- Fetch data ----------
onMounted(async () => {
    try {
        loading.value = true
        const res = await get('/admin/dashboard')
        console.log('Dashboard API response:', res.data)
        dashboardData.value = res.data.data
        console.log('dashboardData:', dashboardData.value)
    } catch (e) {
        error.value = 'Không thể tải dữ liệu dashboard.'
        console.error('Dashboard error:', e)
    } finally {
        loading.value = false
    }

    setupSocketListeners()
})

onUnmounted(() => {
    if (echoChannel) {
        echoChannel.stopListening('.super_admin.tournament')
        echoChannel.stopListening('.super_admin.mini_tournament')
        echoChannel.stopListening('.super_admin.match')
        echoChannel.stopListening('.super_admin.user')
        echoChannel = null
    }
})

// ---------- Top Stats ----------
const topStats = computed(() => {
  const d = dashboardData.value
  if (!d) return []
  const formatRevenue = (amount) => {
    if (!amount) return '0'
    if (amount >= 1_000_000) return (amount / 1_000_000).toFixed(1) + 'M'
    if (amount >= 1_000) return (amount / 1_000).toFixed(0) + 'K'
    return amount.toLocaleString()
  }
  return [
    {
      label: 'Tổng Users',
      value: d.user_growth?.total?.toLocaleString() ?? '0',
      trend: d.user_growth?.new_this_week ? `+${d.user_growth.new_this_week}` : null,
      borderColor: 'border-[#b3111b]',
      valueColor: '#271716',
    },
    {
      label: 'Kèo Đang Active',
      value: d.mini_tournament_growth?.active_today?.toString() ?? '0',
      trend: d.mini_tournament_growth?.growth_percent !== null && d.mini_tournament_growth?.growth_percent !== undefined
        ? `${d.mini_tournament_growth.growth_percent > 0 ? '+' : ''}${d.mini_tournament_growth.growth_percent}%`
        : null,
      borderColor: 'border-[#a03e38]',
      valueColor: '#271716',
    },
    {
      label: 'Giải Đấu (Tháng)',
      value: d.tournaments_this_month?.toString() ?? '0',
      borderColor: 'border-[#00627d]',
      valueColor: '#271716',
    },
    {
      label: 'Phí Thu Được (VNĐ)',
      value: formatRevenue(d.monthly_revenue),
      subtext: 'Doanh thu tháng này',
      borderColor: 'border-[#741e1b]',
      valueColor: '#00627d',
    },
  ]
})

// ---------- Alert Cards ----------
const disputeAlert = computed(() => {
  const d = dashboardData.value
  if (!d) return null
  return {
    count: d.open_disputes_count ?? 0,
  }
})

const reportAlert = computed(() => {
  const d = dashboardData.value
  if (!d) return null
  return {
    count: d.pending_reports_count ?? 0,
  }
})

// ---------- Recent New Users ----------
const mappedNewUsers = computed(() => {
  const users = dashboardData.value?.recent_new_users ?? []
  return users.slice(0, 5).map(u => ({
    id: u.id,
    full_name: u.full_name,
    avatar_url: u.avatar_url ?? 'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.full_name ?? '?'),
    rating: u.trust_score ? u.trust_score.toFixed(1) : '—',
    joinedDaysAgo: formatedDate(u.created_at, 'daysAgo'),
  }))
})

// ---------- Open Matches (MiniTournament) ----------
const openMatches = computed(() => {
  const matches = dashboardData.value?.open_mini_tournaments ?? []
  return matches.slice(0, 5).map(m => {
    const statusMap = {
      1: { label: 'Nháp', class: 'bg-[#ffe9e7] text-[#410003] px-3 py-1 text-[10px] font-bold rounded-full' },
      2: { label: 'Mở', class: 'bg-[#bce9ff] text-[#001f2a] px-3 py-1 text-[10px] font-bold rounded-full' },
    }
    const mapped = statusMap[m.status] ?? statusMap[2]
    return {
      id: m.id,
      title: m.name ?? 'Kèo không tên',
      time: formatedDate(m.start_time ?? m.created_at, 'time'),
      date: formatedDate(m.start_time ?? m.created_at, 'dateDMY'),
      players_count: m.players_count ?? 0,
      location: m.competition_location?.name ?? '',
      statusLabel: mapped.label + ` (${m.players_count ?? 0}/${m.max_players ?? '—'})`,
      statusClass: mapped.class,
      hasDispute: m.has_dispute > 0,
    }
  })
})

// ---------- Open Tournaments ----------
const openTournaments = computed(() => {
  const tours = dashboardData.value?.open_tournaments ?? []
  return tours.slice(0, 3).map(t => {
    const statusMap = {
      1: 'Nháp',
      2: 'Mở đăng ký',
    }
    return {
      id: t.id,
      name: t.name ?? 'Giải không tên',
      status: statusMap[t.status] ?? t.status,
      dates: t.start_date ? formatedDate(t.start_date, 'dateDMY') : 'Sắp tới',
      location: t.competition_location?.name ?? 'Việt Nam',
      regCount: t.fee ?? '—',
      regText: t.fee ? `${Number(t.fee).toLocaleString()} VNĐ` : 'Miễn phí',
      image: t.poster || 'https://images.unsplash.com/photo-1530549387789-4c1017266635?w=800&q=80',
    }
  })
})
</script>

<style scoped>
.font-manrope { font-family: 'Manrope', sans-serif; }
.table-head {
  @apply px-6 py-4 text-[11px] font-bold uppercase tracking-widest text-slate-500;
}

.nav-btn {
  @apply w-8 h-8 rounded-full flex items-center justify-center bg-slate-100 text-slate-700 hover:bg-[#b3111b] hover:text-white transition-colors duration-200;
}
</style>
