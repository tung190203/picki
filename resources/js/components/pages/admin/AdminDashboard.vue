<template>
  <div class="flex min-h-screen overflow-x-hidden" style="background-color: var(--surface-bright, #fff8f7); color: var(--on-surface, #271716);">
    <!-- SideNavBar -->
    <AdminSidebar />

    <!-- Main Content Area -->
    <main class="flex-1 md:ml-64 min-h-screen" style="background-color: var(--surface-bright, #fff8f7);">
    <AdminHeader />

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
import { get } from '@/utils/httpRequest.js'
import { ref, computed, onMounted } from 'vue'
import { formatedDate } from '@/composables/formatedDate.js'

const loading = ref(true)
const error = ref(null)
const dashboardData = ref(null)

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
