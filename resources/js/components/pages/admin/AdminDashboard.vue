<template>
  <div class="flex min-h-screen overflow-x-hidden" style="background-color: var(--surface-bright, #fff8f7); color: var(--on-surface, #271716);">
    <!-- SideNavBar -->
    <AdminSidebar />

    <!-- Main Content Area -->
    <main class="flex-1 md:ml-64 min-h-screen" style="background-color: var(--surface-bright, #fff8f7);">
    <AdminHeader />

      <!-- 🔍 Socket Debug Bar -->
      <div class="px-4 py-2 text-xs font-mono flex items-center gap-4 flex-wrap"
        style="background: #1a1a2e; color: #00ff88;">
        <span class="font-bold text-white">SOCKET DEBUG:</span>
        <span>[Echo] {{ socketStatus.echoAvailable ? '✓ available' : '✗ NOT available' }}</span>
        <span>[Channel] {{ socketStatus.channelName || '—' }}</span>
        <span>[Subscribed] {{ socketStatus.subscribed ? '✓ YES' : '✗ NO' }}</span>
        <span v-if="socketStatus.error" class="text-red-400">[ERROR] {{ socketStatus.error }}</span>
        <button @click="testSocket"
          class="px-3 py-1 rounded text-xs font-bold text-white"
          style="background:#b3111b; margin-left: auto;">
          🔬 Test Socket
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="p-8 max-w-[1400px] mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="i in 4" :key="i" class="h-28 rounded-xl animate-pulse" style="background-color: var(--surface-container-low, #fff0ef);"></div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="p-8">
        <div class="p-6 rounded-xl text-center" style="background-color: var(--error-container, #ffdad6); color: var(--on-error-container, #93000a);">
          {{ error }}
        </div>
      </div>

      <!-- Content -->
      <div v-else class="p-8 space-y-8 max-w-[1400px] mx-auto">
        <!-- Hero Stats -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="stat in topStats" :key="stat.label"
            class="rounded-xl p-6 transition-all border-l-4 shadow-sm"
            style="background-color: var(--surface-container-low, #fff0ef);"
            :class="stat.borderColor"
          >
            <p class="text-[11px] font-bold uppercase tracking-widest mb-2" style="color: var(--on-surface-variant, #5b403d); font-family: 'Inter', sans-serif;">{{ stat.label }}</p>
            <div class="flex items-baseline gap-3">
              <h2 class="text-3xl font-extrabold" style="font-family: 'Manrope', sans-serif;" :style="{ color: stat.valueColor }">{{ stat.value }}</h2>
              <span v-if="stat.trend" class="text-xs font-bold flex items-center" style="color: var(--tertiary, #00627d);">
                <span class="material-symbols-outlined text-xs mr-0.5">trending_up</span>
                {{ stat.trend }}
              </span>
              <span v-else-if="stat.subtext" class="text-[10px] font-medium" style="color: var(--on-surface-variant, #5b403d);">{{ stat.subtext }}</span>
            </div>
          </div>
        </section>

        <!-- Urgent Alerts -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="p-5 rounded-xl flex items-center justify-between border shadow-sm"
            style="background-color: rgba(255,210,207,0.4); border-color: rgba(186,26,26,0.1);">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-full flex items-center justify-center shadow-md text-white"
                style="background-color: var(--error, #ba1a1a);">
                <span class="material-symbols-outlined icon-fill">gavel</span>
              </div>
              <div>
                <h3 class="font-bold" style="font-family: 'Manrope', sans-serif; color: var(--on-error-container, #93000a);">{{ disputeAlert?.count ?? 0 }} Kết quả đang Tranh chấp</h3>
                <p class="text-sm" style="color: rgba(147,0,10,0.7);">Yêu cầu can thiệp ngay lập tức.</p>
              </div>
            </div>
            <button class="px-4 py-2 rounded-lg text-sm font-bold shadow-lg text-white transition-all hover:opacity-90 active:scale-95"
              style="background-color: var(--error, #ba1a1a);">Xử lý ngay</button>
          </div>

          <div class="p-5 rounded-xl flex items-center justify-between border shadow-sm"
            style="background-color: var(--surface-container-low, #fff0ef); border-color: rgba(228,190,186,0.3);">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-full flex items-center justify-center"
                style="background-color: var(--surface-container-high, #ffe2de); color: var(--on-surface-variant, #5b403d);">
                <span class="material-symbols-outlined">report</span>
              </div>
              <div>
                <h3 class="font-bold" style="font-family: 'Manrope', sans-serif; color: var(--on-surface, #271716);">{{ reportAlert?.count ?? 0 }} Report Vi phạm & Toxic</h3>
                <p class="text-sm" style="color: var(--on-surface-variant, #5b403d);">Kiểm tra lịch sử chat và hành vi người dùng.</p>
              </div>
            </div>
            <button class="px-4 py-2 rounded-lg text-sm font-bold transition-all hover:opacity-80 active:scale-95"
              style="background-color: var(--surface-variant, #fadcd9); color: var(--on-surface-variant, #5b403d);">Review</button>
          </div>
        </section>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
          <!-- New Users -->
          <section class="xl:col-span-1 space-y-4">
            <div class="flex items-center justify-between px-2">
              <h3 class="font-bold text-lg" style="font-family: 'Manrope', sans-serif; color: var(--on-surface, #271716);">User Mới Đăng Ký</h3>
              <router-link :to="{ name: 'admin.moderation', query: { tab: 'users' } }"
                class="text-xs font-bold uppercase tracking-tight hover:underline cursor-pointer"
                style="color: var(--primary, #b3111b);">Xem tất cả</router-link>
            </div>
            <div class="grid grid-cols-1 gap-4">
              <div v-for="user in mappedNewUsers" :key="user.id"
                class="flex items-center gap-4 p-3 rounded-2xl transition-all cursor-pointer group"
                style="background-color: var(--surface-container-lowest, #ffffff);">
                <div class="relative flex-shrink-0">
                  <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-white shadow-sm">
                    <img :src="user.avatar_url" alt="Avatar" class="w-full h-full object-cover" />
                  </div>
                  <div class="absolute bottom-0 left-0 text-white text-[9px] font-bold w-6 h-6 rounded-full flex items-center justify-center border-2 border-white shadow-sm"
                    style="background-color: #4392E0;">{{ user.rating }}</div>
                  <div class="absolute bottom-0 -right-0.5 w-5 h-5 rounded-full border-2 border-white shadow-sm" style="background-color: #00B16A;"></div>
                </div>
                <div class="flex flex-col justify-center gap-0.5">
                  <h4 class="font-bold text-[17px] leading-tight transition-colors group-hover:text-[#b3111b]"
                    style="color: #373A40;">{{ user.full_name }}</h4>
                  <p class="text-[13px] font-medium" style="color: #9BA4B5;">Tham gia {{ user.joinedDaysAgo }}</p>
                </div>
              </div>
              <div v-if="mappedNewUsers.length === 0" class="text-center py-8" style="color: var(--on-surface-variant, #5b403d);">
                Chưa có user mới đăng ký.
              </div>
            </div>
          </section>

          <!-- Active Matches -->
          <section class="xl:col-span-2 space-y-4">
            <div class="flex items-center justify-between px-2">
              <h3 class="font-bold text-lg" style="font-family: 'Manrope', sans-serif; color: var(--on-surface, #271716);">Kèo mới đang mở</h3>
              <router-link :to="{ name: 'admin.moderation', query: { tab: 'matches' } }"
                class="text-xs font-bold uppercase tracking-tight hover:underline cursor-pointer"
                style="color: var(--primary, #b3111b);">Xem tất cả</router-link>
            </div>
            <div class="rounded-xl shadow-sm border" style="background-color: var(--surface-container-low, #fff0ef); border-color: rgba(228,190,186,0.1);">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr style="background-color: var(--surface-container-high, #ffe2de);">
                    <th class="table-head">Thời gian</th>
                    <th class="table-head">Kèo</th>
                    <th class="table-head">Địa điểm</th>
                    <th class="table-head">Người chơi</th>
                    <th class="table-head text-right">Trạng thái</th>
                  </tr>
                </thead>
                <tbody class="divide-y" style="border-color: rgba(228,190,186,0.1);">
                  <tr v-for="match in openMatches" :key="match.id"
                    class="transition-colors group cursor-pointer"
                    style="background-color: rgba(255,240,239,0.5);">
                    <td class="px-6 py-4">
                      <div class="font-bold text-sm" style="color: var(--on-surface, #271716);">{{ match.time }}</div>
                      <div class="text-[10px]" style="color: var(--on-surface-variant, #5b403d);">{{ match.date }}</div>
                    </td>
                    <td class="px-6 py-4">
                      <div class="font-bold text-sm" style="color: var(--on-surface, #271716);">{{ match.title }}</div>
                    </td>
                    <td class="px-6 py-4">
                      <span class="text-sm font-medium" style="color: var(--on-surface-variant, #5b403d);">{{ match.location || '—' }}</span>
                    </td>
                    <td class="px-6 py-4">
                      <span class="text-sm font-medium" style="color: var(--on-surface-variant, #5b403d);">
                        {{ match.players_count }}/4 người
                      </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                      <span :class="match.statusClass">{{ match.statusLabel }}</span>
                    </td>
                  </tr>
                  <tr v-if="openMatches.length === 0">
                    <td colspan="5" class="px-6 py-8 text-center" style="color: var(--on-surface-variant, #5b403d);">Không có kèo nào đang mở.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- Tournaments section -->
        <section class="space-y-4">
            <div class="flex items-center justify-between px-2">
              <h3 class="font-bold text-lg" style="font-family: 'Manrope', sans-serif; color: var(--on-surface, #271716);">Giải đấu mới</h3>
              <router-link :to="{ name: 'admin.moderation', query: { tab: 'tournaments' } }"
                class="text-xs font-bold uppercase tracking-tight hover:underline cursor-pointer"
                style="color: var(--primary, #b3111b);">Xem tất cả</router-link>
            </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="t in openTournaments" :key="t.id"
              class="group rounded-xl overflow-hidden transition-all duration-300 transform hover:-translate-y-1 shadow-sm"
              style="background-color: var(--surface-container-low, #fff0ef); border: 1px solid rgba(228,190,186,0.1);">
              <div class="relative h-48 overflow-hidden">
                <img :src="t.image" alt="Banner giải đấu" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                <div class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-black/80 to-transparent"></div>
                <div class="absolute bottom-4 left-4 right-4">
                  <h4 class="text-white font-bold text-lg leading-tight" style="font-family: 'Manrope', sans-serif;">{{ t.name }}</h4>
                </div>
              </div>
              <div class="p-5 space-y-4">
                <div class="flex justify-between items-center text-xs" style="color: var(--on-surface-variant, #5b403d);">
                  <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">calendar_today</span>{{ t.dates }}</span>
                  <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">location_on</span>{{ t.location }}</span>
                </div>
                <div class="flex items-center gap-3 py-2 border-y" style="border-color: rgba(228,190,186,0.1); color: var(--on-surface-variant, #5b403d);">
                  <div class="w-6 h-6 rounded-full border border-white flex items-center justify-center text-[8px] font-bold shadow-sm"
                    style="background-color: var(--surface-container-high, #ffe2de);">{{ t.regCount }}</div>
                  <span class="text-[10px] font-medium">{{ t.regText }}</span>
                </div>
                <button class="w-full py-2.5 rounded-xl font-bold text-sm shadow-md transition-all hover:opacity-90 active:scale-95 text-white"
                  style="background-color: var(--primary, #b3111b);">Register Now</button>
              </div>
            </div>
            <div v-if="openTournaments.length === 0" class="col-span-full text-center py-8" style="color: var(--on-surface-variant, #5b403d);">
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
import { get, post } from '@/utils/httpRequest.js'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { formatedDate } from '@/composables/formatedDate.js'

const loading = ref(true)
const error = ref(null)
const dashboardData = ref(null)

// ---------- Socket listeners ----------
let echoChannel = null
const socketStatus = ref({
    echoAvailable: false,
    channelName: '',
    subscribed: false,
    error: null,
})

const setupSocketListeners = () => {
    console.log('[SuperAdmin] setupSocketListeners called')

    if (!window.Echo) {
        console.error('[SuperAdmin] window.Echo is NOT available')
        socketStatus.value.echoAvailable = false
        socketStatus.value.error = 'window.Echo không tồn tại. Kiểm tra bootstrap.js'
        return
    }
    socketStatus.value.echoAvailable = true
    console.log('[SuperAdmin] window.Echo is available ✓')

    // Dùng DashboardAdminChannel — đổi tên theo yêu cầu
    const channelName = 'DashboardAdminChannel'
    socketStatus.value.channelName = channelName
    console.log('[SuperAdmin] Subscribing to channel:', channelName)

    echoChannel = window.Echo.private(channelName)

    // Dùng .subscribed() và .error() thay vì .bind() — đây là API chuẩn của laravel-echo v2
    echoChannel
        .subscribed(() => {
            console.log('[SuperAdmin] ✓ Subscribed to', channelName)
            socketStatus.value.subscribed = true
        })
        .error((status) => {
            console.error('[SuperAdmin] ✗ Subscription error:', status)
            socketStatus.value.error = `Lỗi subscribe: ${status}`
            socketStatus.value.subscribed = false
        })
        // .listen() trả this nên chain được
        .listen('.super_admin.tournament', (e) => {
            console.log('[SuperAdmin] 🎯 RECEIVED .super_admin.tournament:', JSON.stringify(e, null, 2))
            handleTournamentEvent(e)
        })
        .listen('.super_admin.mini_tournament', (e) => {
            console.log('[SuperAdmin] 🎯 RECEIVED .super_admin.mini_tournament:', JSON.stringify(e, null, 2))
            handleMiniTournamentEvent(e)
        })
        .listen('.super_admin.match', (e) => {
            console.log('[SuperAdmin] 🎯 RECEIVED .super_admin.match:', JSON.stringify(e, null, 2))
            handleMatchEvent(e)
        })
        .listen('.super_admin.dispute', (e) => {
            console.log('[SuperAdmin] 🎯 RECEIVED .super_admin.dispute:', JSON.stringify(e, null, 2))
            handleDisputeEvent(e)
        })
        .listen('.super_admin.report', (e) => {
            console.log('[SuperAdmin] 🎯 RECEIVED .super_admin.report:', JSON.stringify(e, null, 2))
            handleReportEvent(e)
        })
        .listen('.super_admin.payment', (e) => {
            console.log('[SuperAdmin] 🎯 RECEIVED .super_admin.payment:', JSON.stringify(e, null, 2))
            handlePaymentEvent(e)
        })
        .listen('.super_admin.dashboard_stat', (e) => {
            console.log('[SuperAdmin] 🎯 RECEIVED .super_admin.dashboard_stat:', JSON.stringify(e, null, 2))
            handleDashboardStatEvent(e)
        })
        .listen('.super_admin.user', (e) => {
            console.log('[SuperAdmin] 🎯 RECEIVED .super_admin.user:', JSON.stringify(e, null, 2))
            handleUserEvent(e)
        })
}

const handleTournamentEvent = (e) => {
    const { action, data } = e
    console.log('[SuperAdmin] handleTournamentEvent called with action:', action, 'data:', data)

    if (!dashboardData.value) {
        console.warn('[SuperAdmin] dashboardData is null, skipping event')
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
    console.log('[SuperAdmin] handleMiniTournamentEvent called with action:', action, 'data:', data)

    if (!dashboardData.value) {
        console.warn('[SuperAdmin] dashboardData is null, skipping mini event')
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

const handleMatchEvent = (e) => {
    const { action, data } = e
    console.log('[SuperAdmin] Match event:', action, data)
}

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

// ---------- Test Socket ----------
const testSocket = async () => {
    console.log('[SuperAdmin] 🔬 testSocket called')
    if (!echoChannel) {
        console.error('[SuperAdmin] echoChannel is null')
        return
    }
    // Gửi test event lên backend để verify
    try {
        await post('/admin/test-socket', {})
        console.log('[SuperAdmin] Test socket request sent')
    } catch (e) {
        console.error('[SuperAdmin] Test socket request failed:', e)
    }
}

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
        echoChannel.leave()
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
      value: d.mini_match_growth?.active_today?.toString() ?? '0',
      trend: d.mini_match_growth?.growth_percent !== null && d.mini_match_growth?.growth_percent !== undefined
        ? `${d.mini_match_growth.growth_percent > 0 ? '+' : ''}${d.mini_match_growth.growth_percent}%`
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
      statusLabel: mapped.label + (m.players_count !== undefined ? ` (${m.players_count}/—)` : ''),
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
      image: t.poster_url || 'https://images.unsplash.com/photo-1530549387789-4c1017266635?w=800&q=80',
    }
  })
})
</script>

<style scoped>
.font-manrope { font-family: 'Manrope', sans-serif; }
.table-head {
  @apply px-6 py-4 text-[11px] font-bold uppercase tracking-widest;
  color: var(--on-surface-variant, #5b403d);
}

.nav-btn {
  background-color: var(--surface-container-high, #ffe2de);
  @apply w-8 h-8 rounded-full flex items-center justify-center hover:bg-primary hover:text-white transition-colors duration-200;
}
</style>
