<template>
  <div class="flex min-h-screen bg-background text-on-surface font-body overflow-x-hidden">
    <!-- SideNavBar -->
    <AdminSidebar />

    <!-- Main Content Area -->
    <main class="flex-1 md:ml-64 min-h-screen bg-[#fff8f7]">
    <AdminHeader />

      <div class="p-8 space-y-8 max-w-[1400px] mx-auto">
        <!-- Hero Stats -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="stat in topStats" :key="stat.label" 
            class="bg-surface-container-low rounded-xl p-6 transition-all hover:bg-surface-container-lowest border-l-4 shadow-sm"
            :class="stat.borderColor"
          >
            <p class="text-on-surface-variant text-[11px] font-bold uppercase tracking-widest mb-2 font-label">{{ stat.label }}</p>
            <div class="flex items-baseline gap-3">
              <h2 class="text-3xl font-extrabold font-manrope" :class="stat.valueColor">{{ stat.value }}</h2>
              <span v-if="stat.trend" class="text-tertiary text-xs font-bold flex items-center">
                <span class="material-symbols-outlined text-xs mr-0.5">trending_up</span>
                {{ stat.trend }}
              </span>
              <span v-else-if="stat.subtext" class="text-on-surface-variant text-[10px] font-medium">{{ stat.subtext }}</span>
            </div>
          </div>
        </section>

        <!-- Urgent Alerts -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-error-container/40 p-5 rounded-xl flex items-center justify-between border border-error/10 shadow-sm">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-full bg-error flex items-center justify-center text-white shadow-md shadow-error/10">
                <span class="material-symbols-outlined icon-fill">gavel</span>
              </div>
              <div>
                <h3 class="font-manrope font-bold text-on-error-container">3 Kết quả đang Tranh chấp</h3>
                <p class="text-sm text-on-error-container/70">Yêu cầu can thiệp ngay lập tức.</p>
              </div>
            </div>
            <button class="bg-error text-white px-4 py-2 rounded-lg text-sm font-bold shadow-lg shadow-error/20 active:scale-95 transition-all hover:bg-error/90">Xử lý ngay</button>
          </div>
          
          <div class="bg-surface-container-low p-5 rounded-xl flex items-center justify-between border border-outline-variant/30 shadow-sm">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant">
                <span class="material-symbols-outlined">report</span>
              </div>
              <div>
                <h3 class="font-manrope font-bold text-on-surface">12 Report Vi phạm & Toxic</h3>
                <p class="text-sm text-on-surface-variant">Kiểm tra lịch sử chat và hành vi người dùng.</p>
              </div>
            </div>
            <button class="bg-surface-variant text-on-surface-variant px-4 py-2 rounded-lg text-sm font-bold active:scale-95 transition-all hover:bg-surface-variant/80">Review</button>
          </div>
        </section>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
          <!-- New Users -->
          <section class="xl:col-span-1 space-y-4">
            <div class="flex items-center justify-between px-2">
              <h3 class="font-manrope font-bold text-lg text-on-surface">User Mới Đăng Ký</h3>
              <router-link :to="{ name: 'admin.moderation', query: { tab: 'users' } }" class="text-primary text-xs font-bold uppercase tracking-tight hover:underline cursor-pointer">Xem tất cả</router-link>
            </div>
            <div class="grid grid-cols-1 gap-4">
              <div v-for="(user, index) in mappedNewUsers" :key="user.id" class="flex items-center gap-4 bg-white p-3 rounded-2xl transition-all hover:bg-gray-50/50 cursor-pointer group">
                <div class="relative flex-shrink-0">
                  <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-white shadow-sm">
                    <img :src="user.avatar_url" class="w-full h-full object-cover" />
                  </div>
                  <!-- Rating Badge -->
                  <div class="absolute bottom-0 left-0 bg-[#4392E0] text-white text-[9px] font-bold w-6 h-6 rounded-full flex items-center justify-center border-2 border-white shadow-sm">
                    {{ user.rating }}
                  </div>
                  <!-- Status Dot -->
                  <div class="absolute bottom-0 -right-0.5 bg-[#00B16A] w-5 h-5 rounded-full border-2 border-white shadow-sm"></div>
                </div>
                <div class="flex flex-col justify-center gap-0.5">
                  <h4 class="font-bold text-[17px] text-[#373A40] leading-tight group-hover:text-primary transition-colors">{{ user.full_name }}</h4>
                  <p class="text-[13px] text-[#9BA4B5] font-medium">Tham gia {{ index % 3 + 1 }} ngày trước</p>
                </div>
              </div>
            </div>
          </section>

          <!-- Active Matches -->
          <section class="xl:col-span-2 space-y-4">
            <div class="flex items-center justify-between px-2">
              <h3 class="font-manrope font-bold text-lg text-on-surface">Kèo mới đang mở</h3>
              <router-link :to="{ name: 'admin.moderation', query: { tab: 'matches' } }" class="text-primary text-xs font-bold uppercase tracking-tight hover:underline cursor-pointer">Xem tất cả</router-link>
            </div>
            <div class="bg-surface-container-low rounded-xl shadow-sm border border-outline-variant/10">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="bg-surface-container-high">
                    <th class="table-head">Thời gian</th>
                    <th class="table-head">Sân & Địa điểm</th>
                    <th class="table-head">Người chơi</th>
                    <th class="table-head text-right">Trạng thái</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                  <tr v-for="match in activeMatches" :key="match.court" class="hover:bg-surface-container-lowest transition-colors group cursor-pointer bg-surface-container-low/50">
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
                      <div class="flex -space-x-2">
                        <img v-for="p in match.players" :key="p" :src="p" class="w-7 h-7 rounded-full border-2 border-surface-container-low object-cover" />
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
          </section>
        </div>

        <!-- Tournaments section -->
        <section class="space-y-4">
            <div class="flex items-center justify-between px-2">
              <h3 class="font-manrope font-bold text-lg text-on-surface">Giải đấu mới</h3>
              <router-link :to="{ name: 'admin.moderation', query: { tab: 'tournaments' } }" class="text-primary text-xs font-bold uppercase tracking-tight hover:underline cursor-pointer">Xem tất cả</router-link>
            </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="t in tours" :key="t.name" class="group bg-surface-container-low rounded-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 border border-outline-variant/10 shadow-sm">
              <div class="relative h-48 overflow-hidden">
                <img :src="t.image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                <div class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-black/80 to-transparent"></div>
                <div class="absolute bottom-4 left-4 right-4">
                  <h4 class="text-white font-manrope font-bold text-lg leading-tight">{{ t.name }}</h4>
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
                <button class="w-full py-2.5 bg-primary text-white rounded-xl font-bold text-sm shadow-md shadow-primary/20 active:scale-95 transition-all hover:bg-primary/95">Register Now</button>
              </div>
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
import { computed } from 'vue'

const topStats = [
  { label: 'Tổng Users', value: '10,450', trend: '+145', borderColor: 'border-primary', valueColor: 'text-on-surface' },
  { label: 'Kèo Đang Active', value: '342', trend: '+12%', borderColor: 'border-secondary', valueColor: 'text-on-surface' },
  { label: 'Giải Đấu (Tháng)', value: '15', borderColor: 'border-tertiary', valueColor: 'text-on-surface' },
  { label: 'Phí Thu Được (VNĐ)', value: '45.2M', borderColor: 'border-on-secondary-container', valueColor: 'text-secondary' },
]

const newUsers = [
  { name: 'Lê Văn Cường', rating: '4.5', joinDate: '15/08', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDkdSASuiJ0RjRDq50txQsGfTTU170IfYCj6i4HGpAYDIMZs1zY4y_mzEvDDIuqJ52qRPGZ5NBvK4mlfYF1G1-BqqSVx-Lf3PnFty49dUcLtT46hoYrBpmxgYVZanhxKod741QVWiXpHjujPsEpgK9Vu_z-23_wVrG2Svf0YhJ4GKDDJKE1YgTn35erCaxyxNv2ZdM5A9eaOJIr9MAW1-K8zR798NM2WKEglw5reThMQzwpJLHYKOdoHM7ni0gF79jTTdnREax9AMI' },
  { name: 'Nguyễn Thùy', rating: '3.8', joinDate: '14/08', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuBHo-C_Nubb8n7QVl7_RDGl54tKR0zRhMumIkqF8pxmChfG2zsX-Tqq6dIuTgesbK-v2ECA33xANfGQqwa5mdU2IYiv1iMWith2VXM41qu3GkTxh8Mw_vjPF4fN-2aBl0mPIM664AT_bFzoyzaSBCBuOtwKIFcvHe1BU7SmU5L6Lb13ly9MvlfHlki1NdzCxH7Fo5OoIG4b-LhDLypkWFrjLuLJd5PE_s4Mw-zmAbiQ8ZJA7hzafSyY2m6RhsNrsgPGXJw7k_4bYVY' },
  { name: 'Trần Minh', rating: '4.2', joinDate: '14/08', avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuAo0qmbrJen3Nd0KiFC30x8Ykw4Er3C-9bKMNaNTLr1pDjktcmKLhwFOxak1fxLW8QP_SE4CsZHWuwtt_ATphrtYig-p_xCSJ21iqfSEVuLzd4XKR6lUMMfVV7idJcR25kCQsrU_yJpVAmG9PG3wZmKii5cjv1zTSZmJbSKwamJNFIkpW8q8qNJxBvepT_MSvKkYMeByh_f42rNVyaOt0I_A2B3_LiKqvGs7EnXBqF7QHFWR3f6-TynqYZi21nNis0_dYsboMl4eZ0' }
]

const activeMatches = [
  { time: '08:00 AM', date: '15/08/2024', court: 'Sân Pickleball ABC', location: 'Quận 7, TP.HCM', players: ['https://lh3.googleusercontent.com/aida-public/AB6AXuBEBdBl2c7kjbwANBjlQXqSGDGTFsdd531jevXIKquqY9d9rkwwHtum9bJ0GSyM_Ve-38FBPueN_bPeho2Pi9KxwlPS0ssHptXCRo8KNfrc5V7K_pnvtsG47b73P2sWDuvjRhige0y18vVd48nYX0Y2oaQ5soI2_WbO2AZJXicd8B_cFDpdACTjpEZrDVF9vGo99HiCpJBtyjcQqst5QpTrsc1Pjl9NybsmgKXI-KVF99jfnm_3bTe_IpwH8U4zJ3XmDUkPOnA9zx8', 'https://lh3.googleusercontent.com/aida-public/AB6AXuAZhBvlWZYFhUzsKjCEf7Z8sLg1iBZ5AurMAF3Xj0yK6q4dd_F_OSq86EG8SeaauJV-JDtNTpjprAX1U9Q7kLRWQ0oWWg5-uN4yEoVB0Z7aXVrQEE2xP5wKsTqr28K17cWm5_fOGBR_N3gX1OGs3plobWbyqyBd2j9LcBsGq_ilLd71pyN-kTnW64-OHvIVYhvFa3nFo79z4cTS3VAZ6dBvK-wlPdU52yCO7iagNCuSu5QxG3CFCnbcUTPXLzL1s3aemQDI711dv8A'], extra: 2, status: 'Đang chờ (2/4)', statusClass: 'px-3 py-1 bg-secondary-fixed text-on-secondary-fixed text-[10px] font-bold rounded-full' },
  { time: '09:30 AM', date: '15/08/2024', court: 'Sân Pickleball Sunshine', location: 'Quận 2, TP.HCM', players: ['https://lh3.googleusercontent.com/aida-public/AB6AXuCpioUtpsuJAFwiGsHaJEIQkEflzwMIzed54yKZ_3ChHHhJf8vvlowe6K-Nlo543w62WZ9QSj8TAMz8Mduip6UQ42WP6NYt3LYI8PORSktKyATihl_4uJjJLwJwbMpxaCOLFuAbEdE46l8L5yUcb__codNndKuKD4RdsmwQIKbhZCoY4lWj7cOcs11922xrpr1kZN4RetJfgnloP2TAGAupjdwr8VXsly5v9iJr9l9NxRTMSFc2qfwm660KtUa0zLKeQovHzhHjX_4', 'https://lh3.googleusercontent.com/aida-public/AB6AXuA1pQvjKU9qRgFvZEFmOk90EbSJe-JqnkQks-dTOpQle6IpCpH4FE6NJ58vVG3wGVbIB0dDWfZ9Tx6VWXAMoVrX2OiAy9tSnLaM6DBMXTBjgfHpgHjaR1WH_i1DS3O5Wqh3C-KzoDeCVVed_YtaJJy4ZExb1iC8079FZVYFX-DzotlKIrAHCsBYL2yHLmEVOCGH_BHcNDvESbsupMT3nytG1tAVZPDf5ZOYO1tcR-MXKl78lYgt3tDFiYJUZAPEFYsh_c3SeVurZi8'], extra: 0, status: 'Full (4/4)', statusClass: 'px-3 py-1 bg-tertiary-container text-on-tertiary-container text-[10px] font-bold rounded-full' }
]

const tours = [
  { name: 'Summer Smash Open 2024', dates: '25/08 - 28/08', location: 'TP. Thủ Đức', regCount: '128', regText: 'Người tham gia đăng ký', image: 'https://lh3.googleusercontent.com/aida-public/AB6AXuB37u6BPVF4qqLStg5ZVuywrYghO58EJRpFuSs73Krxh4q1yvNq-Y3s3dfMM3N4ge33SZWTGUkCJiJZhUB4TxXb92D6gowfePVdZtRNapGOqIvCIgf14nt4c1W9mmbDH0r_NIfSQZhTKOj54uZs2W9bUY5B6rblP20CMNJGCyxddMR1eWQzHD8dtwPIeQY5h9rXKWhIvDh1GR2aMliiWJMlVa7mjvxmqqyRKCJ3TKZfjFTD91IfTr-Li7HrQHhOk4I3h8m9jyolNbQ' },
  { name: 'Pickleball National Championship', dates: '10/09 - 15/09', location: 'Quận 7, HCM', regCount: '256', regText: 'Suất tham gia giới hạn', image: 'https://lh3.googleusercontent.com/aida-public/AB6AXuBH42G3g7GxJZ9J3VmNpGm8Za1Umgj13DbeGeVTkSW8_7RfkDIktF-w--nkc7LR0V3u0GLCYGwKckoehdrGtkYfHoND0zT1s8g6Dnh7vABFjTzVv97PNq6kD6RMzs05dkqjlN9Ap2_lkCo4GwsVPtVSDVpplsVYdadblEMRw-UugxhSU88FmH_NjPTQhpDOuhR4rEh3YBJPGpivrLYrakW9jo0TSaAPgtncAvFTXdkuWZjyoxQ7uH59nHOsmskgI-OB0LjXyKpvRZQ' },
  { name: 'Night Smash League', dates: 'Every Friday', location: 'Quận 1, HCM', regCount: '64', regText: 'Chỉ dành cho Member', image: 'https://lh3.googleusercontent.com/aida-public/AB6AXuAnwseVfwQVJoBb5GAUE6bdJZhQJ4cd-SouvHoGgKb2vZiKHGPF0xyGMJIrlg1t3W2dnwfLlcqESb5EslNivW1nGgDq_CQtPgcP1dP5tJDsUpqLkRBcqsXtEvA0bRRe9jxl56ubB0QTGhv-5jjVyIvWKledgUjFNXc6m1wTapM9YbJhXzv2OSP1Gd8BWSDKUlhP9nJO0ukUz_YIw_VhvsBNuK5U4DRC3_RFN2Cct2OmUfY_WcMLbp6l5NElHfmURqNQGGh4SDl8v18' },
]

const mappedNewUsers = computed(() => {
  return newUsers.map((u, index) => ({
    id: index + 1,
    full_name: u.name,
    avatar_url: u.avatar,
    rating: u.rating,
    joinDate: u.joinDate
  }))
})
</script>

<style scoped>
.font-manrope { font-family: 'Manrope', sans-serif; }
.table-head {
  @apply px-6 py-4 text-[11px] font-bold text-on-surface-variant uppercase tracking-widest;
}

.nav-btn {
  @apply w-8 h-8 rounded-full bg-surface-container-high flex items-center justify-center hover:bg-primary hover:text-white transition-colors duration-200;
}
</style>
