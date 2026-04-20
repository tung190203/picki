<template>
  <div class="flex min-h-screen bg-[#f7f9fb] font-body text-on-surface">
    <!-- SideNavBar -->
    <AdminSidebar />

    <!-- Main Content -->
    <main class="ml-64 flex-1">
      <AdminHeader />
      <div class="p-8">

        <!-- Performance Insights -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          <div class="insight-card bg-primary/5 border-primary/10 rounded-xl">
            <span class="material-symbols-outlined text-primary text-4xl mb-4">groups</span>
            <h4 class="font-headline font-bold text-lg mb-1 text-on-surface">Tổng Users</h4>
            <p class="text-3xl font-headline font-extrabold text-primary">10,450</p>
            <p class="text-xs text-on-surface-variant mt-2">+145 tuần này</p>
          </div>
          <div class="insight-card bg-surface-container-low border-outline-variant/10 rounded-xl">
            <span class="material-symbols-outlined text-secondary text-4xl mb-4">sports_tennis</span>
            <h4 class="font-headline font-bold text-lg mb-1 text-on-surface">Kèo Active</h4>
            <p class="text-3xl font-headline font-extrabold text-on-surface">342</p>
            <p class="text-xs text-on-surface-variant mt-2">+12% so với tuần trước</p>
          </div>
          <div class="insight-card bg-tertiary-fixed-dim/20 border-tertiary/10 rounded-xl">
            <span class="material-symbols-outlined text-tertiary text-4xl mb-4">emoji_events</span>
            <h4 class="font-headline font-bold text-lg mb-1 text-on-surface">Giải Đấu</h4>
            <p class="text-3xl font-headline font-extrabold text-tertiary">15</p>
            <p class="text-xs text-on-surface-variant mt-2">Trong tháng này</p>
          </div>
          <div class="insight-card bg-error-container/30 border-error/10 rounded-xl">
            <span class="material-symbols-outlined text-error text-4xl mb-4">analytics</span>
            <h4 class="font-headline font-bold text-lg mb-1 text-on-surface">Tỷ lệ tranh chấp</h4>
            <p class="text-3xl font-headline font-extrabold text-error">3.4%</p>
            <p class="text-xs text-on-surface-variant mt-2">↓ 0.8% so với tuần trước</p>
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

          <Pagination :meta="usersMeta" @page-change="usersPage = $event" />
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

          <Pagination :meta="matchesMeta" @page-change="matchesPage = $event" />
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

          <Pagination :meta="tournamentsMeta" @page-change="tournamentsPage = $event" />
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

const route = useRoute()
const router = useRouter()

// ===========================
// TAB STATE
// ===========================
const tabs = [
  { key: 'users', label: 'Người dùng', icon: 'group', badge: '10K+' },
  { key: 'matches', label: 'Kèo đấu', icon: 'sports_tennis', badge: '342' },
  { key: 'tournaments', label: 'Giải đấu', icon: 'emoji_events', badge: '15' },
]

const validTabs = tabs.map(t => t.key)
const activeTab = ref('users')

const switchTab = (tabKey) => {
  activeTab.value = tabKey
  router.replace({ query: { ...route.query, tab: tabKey } })
}

// Sync tab from URL query on mount & on route change
const syncTabFromRoute = () => {
  const queryTab = route.query.tab
  if (queryTab && validTabs.includes(queryTab)) {
    activeTab.value = queryTab
  }
}

onMounted(syncTabFromRoute)
watch(() => route.query.tab, syncTabFromRoute)

// ===========================
// PAGINATION STATE
// ===========================
const usersPage = ref(1)
const matchesPage = ref(1)
const tournamentsPage = ref(1)

const PER_PAGE = 5
const TOURNAMENT_PER_PAGE = 6

// ===========================
// MOCK DATA: USERS
// ===========================
const allUsers = [
  { id: 1045, name: 'Tuấn Nghĩa', location: 'Hanoi', reliability: 98, matches: 142, status: 'Ban', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuB-FFeb29ihHt4ZhmPCIOdp_vO_f2IULwLlhdN4Nwb_LwqBcaW2o-cZYqX2VdtGcQ2aZtDXeWEoxZPaQY9qJKGbJ91Y97kdrepUR6-k-XWyfAvLGxfbxA-b3GBSFnaHUBL9bKSK4NALrOqg-VJvKah42Z0g7DltA1hcG2YpAMoVQAxZ4hVVqKJbxOAVvVp7VvDahn-hEp9SOqOr6T2Z0dBVu0jGTPzB6BU-RapRhMaZzwsvir9xEpvqKTPTVdZw49P2O8UIudcFL7U', reliabilityClass: 'text-on-surface', progressClass: 'bg-tertiary', buttonClass: 'px-3 py-1 bg-surface-container-low text-primary rounded-lg text-xs font-bold hover:bg-error-container transition-colors' },
  { id: 899, name: 'Hải Pickleball', location: 'Da Nang', reliability: 42, matches: 89, status: 'Banned', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDsOEh9OecxfR7z-xZF96EknZoTad0ZAqn09lqzXOdDfq-4-TOkJGIGd7ZB8D1K_6qO2DwWkw-gic95_okzM2ZPVeY6R8599iGbBT9gyJxnlqMoa0ltDOUS3l2sqN5EbgS_8sUwFEQpJnTj5FJLbbIQb_u2vgBD_RlXb8p8eTxl5x1iQuw5M52B6pN4zlqHVX3yRedL_KQpSpkkhEOpZn6d0smCUODhNWuDdrVC1veSr8tOIA-KKB4qmaar1xBzLrbvShBJm8UZIoo', reliabilityClass: 'text-error', progressClass: 'bg-error', buttonClass: 'px-3 py-1 bg-error text-on-error rounded-lg text-xs font-bold shadow-lg shadow-error/20' },
  { id: 1204, name: 'Vân Tay', location: 'HCMC', reliability: 75, matches: 214, status: 'Ban', avatar: '', reliabilityClass: 'text-on-surface', progressClass: 'bg-secondary-container', buttonClass: 'px-3 py-1 bg-surface-container-low text-primary rounded-lg text-xs font-bold hover:bg-error-container transition-colors' },
  { id: 1301, name: 'Phạm Quốc Trung', location: 'HCMC', reliability: 91, matches: 67, status: 'Ban', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDkdSASuiJ0RjRDq50txQsGfTTU170IfYCj6i4HGpAYDIMZs1zY4y_mzEvDDIuqJ52qRPGZ5NBvK4mlfYF1G1-BqqSVx-Lf3PnFty49dUcLtT46hoYrBpmxgYVZanhxKod741QVWiXpHjujPsEpgK9Vu_z-23_wVrG2Svf0YhJ4GKDDJKE1YgTn35erCaxyxNv2ZdM5A9eaOJIr9MAW1-K8zR798NM2WKEglw5reThMQzwpJLHYKOdoHM7ni0gF79jTTdnREax9AMI', reliabilityClass: 'text-on-surface', progressClass: 'bg-tertiary', buttonClass: 'px-3 py-1 bg-surface-container-low text-primary rounded-lg text-xs font-bold hover:bg-error-container transition-colors' },
  { id: 1350, name: 'Lê Hoàng Anh', location: 'Hanoi', reliability: 88, matches: 55, status: 'Ban', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuBHo-C_Nubb8n7QVl7_RDGl54tKR0zRhMumIkqF8pxmChfG2zsX-Tqq6dIuTgesbK-v2ECA33xANfGQqwa5mdU2IYiv1iMWith2VXM41qu3GkTxh8Mw_vjPF4fN-2aBl0mPIM664AT_bFzoyzaSBCBuOtwKIFcvHe1BU7SmU5L6Lb13ly9MvlfHlki1NdzCxH7Fo5OoIG4b-LhDLypkWFrjLuLJd5PE_s4Mw-zmAbiQ8ZJA7hzafSyY2m6RhsNrsgPGXJw7k_4bYVY', reliabilityClass: 'text-on-surface', progressClass: 'bg-tertiary', buttonClass: 'px-3 py-1 bg-surface-container-low text-primary rounded-lg text-xs font-bold hover:bg-error-container transition-colors' },
  { id: 1402, name: 'Nguyễn Thanh Tùng', location: 'HCMC', reliability: 95, matches: 310, status: 'Ban', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuAo0qmbrJen3Nd0KiFC30x8Ykw4Er3C-9bKMNaNTLr1pDjktcmKLhwFOxak1fxLW8QP_SE4CsZHWuwtt_ATphrtYig-p_xCSJ21iqfSEVuLzd4XKR6lUMMfVV7idJcR25kCQsrU_yJpVAmG9PG3wZmKii5cjv1zTSZmJbSKwamJNFIkpW8q8qNJxBvepT_MSvKkYMeByh_f42rNVyaOt0I_A2B3_LiKqvGs7EnXBqF7QHFWR3f6-TynqYZi21nNis0_dYsboMl4eZ0', reliabilityClass: 'text-on-surface', progressClass: 'bg-tertiary', buttonClass: 'px-3 py-1 bg-surface-container-low text-primary rounded-lg text-xs font-bold hover:bg-error-container transition-colors' },
  { id: 1500, name: 'Trần Đức Huy', location: 'Da Nang', reliability: 60, matches: 45, status: 'Ban', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuB-FFeb29ihHt4ZhmPCIOdp_vO_f2IULwLlhdN4Nwb_LwqBcaW2o-cZYqX2VdtGcQ2aZtDXeWEoxZPaQY9qJKGbJ91Y97kdrepUR6-k-XWyfAvLGxfbxA-b3GBSFnaHUBL9bKSK4NALrOqg-VJvKah42Z0g7DltA1hcG2YpAMoVQAxZ4hVVqKJbxOAVvVp7VvDahn-hEp9SOqOr6T2Z0dBVu0jGTPzB6BU-RapRhMaZzwsvir9xEpvqKTPTVdZw49P2O8UIudcFL7U', reliabilityClass: 'text-on-surface', progressClass: 'bg-secondary-container', buttonClass: 'px-3 py-1 bg-surface-container-low text-primary rounded-lg text-xs font-bold hover:bg-error-container transition-colors' },
  { id: 1555, name: 'Đỗ Minh Khôi', location: 'Hanoi', reliability: 33, matches: 12, status: 'Banned', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDsOEh9OecxfR7z-xZF96EknZoTad0ZAqn09lqzXOdDfq-4-TOkJGIGd7ZB8D1K_6qO2DwWkw-gic95_okzM2ZPVeY6R8599iGbBT9gyJxnlqMoa0ltDOUS3l2sqN5EbgS_8sUwFEQpJnTj5FJLbbIQb_u2vgBD_RlXb8p8eTxl5x1iQuw5M52B6pN4zlqHVX3yRedL_KQpSpkkhEOpZn6d0smCUODhNWuDdrVC1veSr8tOIA-KKB4qmaar1xBzLrbvShBJm8UZIoo', reliabilityClass: 'text-error', progressClass: 'bg-error', buttonClass: 'px-3 py-1 bg-error text-on-error rounded-lg text-xs font-bold shadow-lg shadow-error/20' },
  { id: 1600, name: 'Mai Thị Lan', location: 'HCMC', reliability: 82, matches: 99, status: 'Ban', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuBHo-C_Nubb8n7QVl7_RDGl54tKR0zRhMumIkqF8pxmChfG2zsX-Tqq6dIuTgesbK-v2ECA33xANfGQqwa5mdU2IYiv1iMWith2VXM41qu3GkTxh8Mw_vjPF4fN-2aBl0mPIM664AT_bFzoyzaSBCBuOtwKIFcvHe1BU7SmU5L6Lb13ly9MvlfHlki1NdzCxH7Fo5OoIG4b-LhDLypkWFrjLuLJd5PE_s4Mw-zmAbiQ8ZJA7hzafSyY2m6RhsNrsgPGXJw7k_4bYVY', reliabilityClass: 'text-on-surface', progressClass: 'bg-tertiary', buttonClass: 'px-3 py-1 bg-surface-container-low text-primary rounded-lg text-xs font-bold hover:bg-error-container transition-colors' },
  { id: 1650, name: 'Bùi Văn Hùng', location: 'Da Nang', reliability: 70, matches: 180, status: 'Ban', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuAo0qmbrJen3Nd0KiFC30x8Ykw4Er3C-9bKMNaNTLr1pDjktcmKLhwFOxak1fxLW8QP_SE4CsZHWuwtt_ATphrtYig-p_xCSJ21iqfSEVuLzd4XKR6lUMMfVV7idJcR25kCQsrU_yJpVAmG9PG3wZmKii5cjv1zTSZmJbSKwamJNFIkpW8q8qNJxBvepT_MSvKkYMeByh_f42rNVyaOt0I_A2B3_LiKqvGs7EnXBqF7QHFWR3f6-TynqYZi21nNis0_dYsboMl4eZ0', reliabilityClass: 'text-on-surface', progressClass: 'bg-secondary-container', buttonClass: 'px-3 py-1 bg-surface-container-low text-primary rounded-lg text-xs font-bold hover:bg-error-container transition-colors' },
  { id: 1700, name: 'Hoàng Quang Hải', location: 'Hanoi', reliability: 90, matches: 250, status: 'Ban', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuB-FFeb29ihHt4ZhmPCIOdp_vO_f2IULwLlhdN4Nwb_LwqBcaW2o-cZYqX2VdtGcQ2aZtDXeWEoxZPaQY9qJKGbJ91Y97kdrepUR6-k-XWyfAvLGxfbxA-b3GBSFnaHUBL9bKSK4NALrOqg-VJvKah42Z0g7DltA1hcG2YpAMoVQAxZ4hVVqKJbxOAVvVp7VvDahn-hEp9SOqOr6T2Z0dBVu0jGTPzB6BU-RapRhMaZzwsvir9xEpvqKTPTVdZw49P2O8UIudcFL7U', reliabilityClass: 'text-on-surface', progressClass: 'bg-tertiary', buttonClass: 'px-3 py-1 bg-surface-container-low text-primary rounded-lg text-xs font-bold hover:bg-error-container transition-colors' },
  { id: 1750, name: 'Võ Thị Ngọc', location: 'HCMC', reliability: 77, matches: 130, status: 'Ban', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuBHo-C_Nubb8n7QVl7_RDGl54tKR0zRhMumIkqF8pxmChfG2zsX-Tqq6dIuTgesbK-v2ECA33xANfGQqwa5mdU2IYiv1iMWith2VXM41qu3GkTxh8Mw_vjPF4fN-2aBl0mPIM664AT_bFzoyzaSBCBuOtwKIFcvHe1BU7SmU5L6Lb13ly9MvlfHlki1NdzCxH7Fo5OoIG4b-LhDLypkWFrjLuLJd5PE_s4Mw-zmAbiQ8ZJA7hzafSyY2m6RhsNrsgPGXJw7k_4bYVY', reliabilityClass: 'text-on-surface', progressClass: 'bg-secondary-container', buttonClass: 'px-3 py-1 bg-surface-container-low text-primary rounded-lg text-xs font-bold hover:bg-error-container transition-colors' },
]

const paginatedUsers = computed(() => {
  const start = (usersPage.value - 1) * PER_PAGE
  return allUsers.slice(start, start + PER_PAGE)
})

const usersMeta = computed(() => ({
  current_page: usersPage.value,
  last_page: Math.ceil(allUsers.length / PER_PAGE),
  total: allUsers.length
}))

// ===========================
// MOCK DATA: MATCHES
// ===========================
const defaultPlayers = [
  'https://lh3.googleusercontent.com/aida-public/AB6AXuBEBdBl2c7kjbwANBjlQXqSGDGTFsdd531jevXIKquqY9d9rkwwHtum9bJ0GSyM_Ve-38FBPueN_bPeho2Pi9KxwlPS0ssHptXCRo8KNfrc5V7K_pnvtsG47b73P2sWDuvjRhige0y18vVd48nYX0Y2oaQ5soI2_WbO2AZJXicd8B_cFDpdACTjpEZrDVF9vGo99HiCpJBtyjcQqst5QpTrsc1Pjl9NybsmgKXI-KVF99jfnm_3bTe_IpwH8U4zJ3XmDUkPOnA9zx8',
  'https://lh3.googleusercontent.com/aida-public/AB6AXuAZhBvlWZYFhUzsKjCEf7Z8sLg1iBZ5AurMAF3Xj0yK6q4dd_F_OSq86EG8SeaauJV-JDtNTpjprAX1U9Q7kLRWQ0oWWg5-uN4yEoVB0Z7aXVrQEE2xP5wKsTqr28K17cWm5_fOGBR_N3gX1OGs3plobWbyqyBd2j9LcBsGq_ilLd71pyN-kTnW64-OHvIVYhvFa3nFo79z4cTS3VAZ6dBvK-wlPdU52yCO7iagNCuSu5QxG3CFCnbcUTPXLzL1s3aemQDI711dv8A'
]

const defaultCreatorAvatar = 'https://lh3.googleusercontent.com/aida-public/AB6AXuDkdSASuiJ0RjRDq50txQsGfTTU170IfYCj6i4HGpAYDIMZs1zY4y_mzEvDDIuqJ52qRPGZ5NBvK4mlfYF1G1-BqqSVx-Lf3PnFty49dUcLtT46hoYrBpmxgYVZanhxKod741QVWiXpHjujPsEpgK9Vu_z-23_wVrG2Svf0YhJ4GKDDJKE1YgTn35erCaxyxNv2ZdM5A9eaOJIr9MAW1-K8zR798NM2WKEglw5reThMQzwpJLHYKOdoHM7ni0gF79jTTdnREax9AMI'

const allMatches = [
  { id: 1, time: '08:00 AM', date: '15/08/2024', court: 'Sân Pickleball ABC', location: 'Quận 7, TP.HCM', creator: 'Tuấn Nghĩa', creatorAvatar: defaultCreatorAvatar, players: defaultPlayers, extra: 2, status: 'Đang chờ (2/4)', statusClass: 'px-3 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
  { id: 2, time: '09:30 AM', date: '15/08/2024', court: 'Sân Pickleball Sunshine', location: 'Quận 2, TP.HCM', creator: 'Hải PB', creatorAvatar: defaultCreatorAvatar, players: defaultPlayers, extra: 0, status: 'Full (4/4)', statusClass: 'px-3 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
  { id: 3, time: '10:00 AM', date: '16/08/2024', court: 'Sân Thể Thao Quận 1', location: 'Quận 1, TP.HCM', creator: 'Vân Tay', creatorAvatar: defaultCreatorAvatar, players: defaultPlayers, extra: 1, status: 'Đang chờ (3/4)', statusClass: 'px-3 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
  { id: 4, time: '14:00 PM', date: '16/08/2024', court: 'Sân PB Thủ Đức', location: 'TP. Thủ Đức', creator: 'Minh Khôi', creatorAvatar: defaultCreatorAvatar, players: defaultPlayers, extra: 0, status: 'Full (4/4)', statusClass: 'px-3 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
  { id: 5, time: '16:00 PM', date: '17/08/2024', court: 'Sân PB Gò Vấp', location: 'Gò Vấp, TP.HCM', creator: 'Quốc Trung', creatorAvatar: defaultCreatorAvatar, players: defaultPlayers, extra: 3, status: 'Đang chờ (1/4)', statusClass: 'px-3 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
  { id: 6, time: '07:00 AM', date: '18/08/2024', court: 'Sân PB Bình Thạnh', location: 'Bình Thạnh, TP.HCM', creator: 'Hoàng Anh', creatorAvatar: defaultCreatorAvatar, players: defaultPlayers, extra: 2, status: 'Đang chờ (2/4)', statusClass: 'px-3 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
  { id: 7, time: '18:00 PM', date: '18/08/2024', court: 'Sân PB Quận 9', location: 'Quận 9, TP.HCM', creator: 'Thanh Tùng', creatorAvatar: defaultCreatorAvatar, players: defaultPlayers, extra: 0, status: 'Full (4/4)', statusClass: 'px-3 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
  { id: 8, time: '06:30 AM', date: '19/08/2024', court: 'Sân PB Phú Nhuận', location: 'Phú Nhuận, TP.HCM', creator: 'Đức Huy', creatorAvatar: defaultCreatorAvatar, players: defaultPlayers, extra: 1, status: 'Đang chờ (3/4)', statusClass: 'px-3 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
  { id: 9, time: '08:00 AM', date: '20/08/2024', court: 'Sân PB Tân Bình', location: 'Tân Bình, TP.HCM', creator: 'Lan Ngọc', creatorAvatar: defaultCreatorAvatar, players: defaultPlayers, extra: 0, status: 'Full (4/4)', statusClass: 'px-3 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
  { id: 10, time: '15:00 PM', date: '20/08/2024', court: 'Sân PB Quận 3', location: 'Quận 3, TP.HCM', creator: 'Văn Hùng', creatorAvatar: defaultCreatorAvatar, players: defaultPlayers, extra: 2, status: 'Đang chờ (2/4)', statusClass: 'px-3 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
  { id: 11, time: '09:00 AM', date: '21/08/2024', court: 'Sân PB Tân Phú', location: 'Tân Phú, TP.HCM', creator: 'Quang Hải', creatorAvatar: defaultCreatorAvatar, players: defaultPlayers, extra: 0, status: 'Full (4/4)', statusClass: 'px-3 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
]

const paginatedMatches = computed(() => {
  const start = (matchesPage.value - 1) * PER_PAGE
  return allMatches.slice(start, start + PER_PAGE)
})

const matchesMeta = computed(() => ({
  current_page: matchesPage.value,
  last_page: Math.ceil(allMatches.length / PER_PAGE),
  total: allMatches.length
}))

// ===========================
// MOCK DATA: TOURNAMENTS
// ===========================
const tournamentImages = [
  'https://lh3.googleusercontent.com/aida-public/AB6AXuB37u6BPVF4qqLStg5ZVuywrYghO58EJRpFuSs73Krxh4q1yvNq-Y3s3dfMM3N4ge33SZWTGUkCJiJZhUB4TxXb92D6gowfePVdZtRNapGOqIvCIgf14nt4c1W9mmbDH0r_NIfSQZhTKOj54uZs2W9bUY5B6rblP20CMNJGCyxddMR1eWQzHD8dtwPIeQY5h9rXKWhIvDh1GR2aMliiWJMlVa7mjvxmqqyRKCJ3TKZfjFTD91IfTr-Li7HrQHhOk4I3h8m9jyolNbQ',
  'https://lh3.googleusercontent.com/aida-public/AB6AXuBH42G3g7GxJZ9J3VmNpGm8Za1Umgj13DbeGeVTkSW8_7RfkDIktF-w--nkc7LR0V3u0GLCYGwKckoehdrGtkYfHoND0zT1s8g6Dnh7vABFjTzVv97PNq6kD6RMzs05dkqjlN9Ap2_lkCo4GwsVPtVSDVpplsVYdadblEMRw-UugxhSU88FmH_NjPTQhpDOuhR4rEh3YBJPGpivrLYrakW9jo0TSaAPgtncAvFTXdkuWZjyoxQ7uH59nHOsmskgI-OB0LjXyKpvRZQ',
  'https://lh3.googleusercontent.com/aida-public/AB6AXuAnwseVfwQVJoBb5GAUE6bdJZhQJ4cd-SouvHoGgKb2vZiKHGPF0xyGMJIrlg1t3W2dnwfLlcqESb5EslNivW1nGgDq_CQtPgcP1dP5tJDsUpqLkRBcqsXtEvA0bRRe9jxl56ubB0QTGhv-5jjVyIvWKledgUjFNXc6m1wTapM9YbJhXzv2OSP1Gd8BWSDKUlhP9nJO0ukUz_YIw_VhvsBNuK5U4DRC3_RFN2Cct2OmUfY_WcMLbp6l5NElHfmURqNQGGh4SDl8v18',
]

const allTournaments = [
  { id: 1, name: 'Summer Smash Open 2024', dates: '25/08 - 28/08', location: 'TP. Thủ Đức', regCount: '128', regText: 'Người tham gia đăng ký', image: tournamentImages[0], status: 'Đang mở', statusClass: 'px-2 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
  { id: 2, name: 'Pickleball National Championship', dates: '10/09 - 15/09', location: 'Quận 7, HCM', regCount: '256', regText: 'Suất tham gia giới hạn', image: tournamentImages[1], status: 'Chờ duyệt', statusClass: 'px-2 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
  { id: 3, name: 'Night Smash League', dates: 'Every Friday', location: 'Quận 1, HCM', regCount: '64', regText: 'Chỉ dành cho Member', image: tournamentImages[2], status: 'Đang mở', statusClass: 'px-2 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
  { id: 4, name: 'Pro Pickleball Series', dates: '01/09 - 05/09', location: 'Da Nang', regCount: '96', regText: 'Giới hạn 96 suất', image: tournamentImages[0], status: 'Chờ duyệt', statusClass: 'px-2 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
  { id: 5, name: 'Saigon Open Cup', dates: '15/09 - 18/09', location: 'Quận 2, HCM', regCount: '200', regText: 'Đăng ký mở', image: tournamentImages[1], status: 'Đang mở', statusClass: 'px-2 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
  { id: 6, name: 'Weekend Warriors', dates: 'Every Saturday', location: 'Bình Thạnh', regCount: '48', regText: 'CLB nội bộ', image: tournamentImages[2], status: 'Đang mở', statusClass: 'px-2 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
  { id: 7, name: 'Autumn Classic 2024', dates: '20/10 - 23/10', location: 'Hanoi', regCount: '180', regText: 'Đăng ký mở', image: tournamentImages[0], status: 'Chờ duyệt', statusClass: 'px-2 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
  { id: 8, name: 'Pickle & Chill', dates: 'Monthly', location: 'Gò Vấp', regCount: '32', regText: 'Casual event', image: tournamentImages[1], status: 'Đang mở', statusClass: 'px-2 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' },
  { id: 9, name: 'Corporate PB League', dates: '05/11 - 10/11', location: 'Quận 7, HCM', regCount: '160', regText: 'Doanh nghiệp', image: tournamentImages[2], status: 'Chờ duyệt', statusClass: 'px-2 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
]

const paginatedTournaments = computed(() => {
  const start = (tournamentsPage.value - 1) * TOURNAMENT_PER_PAGE
  return allTournaments.slice(start, start + TOURNAMENT_PER_PAGE)
})

const tournamentsMeta = computed(() => ({
  current_page: tournamentsPage.value,
  last_page: Math.ceil(allTournaments.length / TOURNAMENT_PER_PAGE),
  total: allTournaments.length
}))
</script>

<style scoped>
.font-headline { font-family: 'Manrope', sans-serif; }
.font-body { font-family: 'Inter', sans-serif; }

.tab-btn {
  @apply flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl transition-all duration-200 cursor-pointer text-on-surface-variant hover:bg-surface-container-high;
}

.tab-btn-active {
  @apply bg-[#af101a] text-white shadow-lg shadow-red-900/15 hover:bg-[#af101a];
}

.tab-badge {
  @apply text-[10px] font-bold px-1.5 py-0.5 rounded-full;
}

.table-head {
  @apply px-6 py-4 font-label text-[10px] uppercase tracking-wider text-on-surface-variant font-extrabold;
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
