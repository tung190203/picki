<template>
  <div class="min-h-screen bg-gray-100 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-red-custom text-white rounded-[8px] shadow-lg p-6 relative overflow-hidden"
          :style="{ backgroundImage: `url(${Background})` }">
          <div class="absolute top-0 left-0 w-full h-full opacity-10 bg-gradient-to-br from-red-500 to-red-700"></div>

          <div class="relative z-10">
            <div class="flex flex-col md:flex-row md:justify-between md:items-start">
              <div class="mb-6 md:mb-0">
                <div class="flex items-center gap-2">
                  <VerifyIcon v-if="homeData.user_info?.is_verify" class="w-6 h-6 text-white" />
                  <div class="opacity-90 text-[32px] font-semibold">PICKI</div>
                </div>
                <div class="text-6xl font-bold leading-none mb-4 text-[100px]">{{
                  Number(homeData.user_info?.sports[0]?.scores?.vndupr_score || 0).toFixed(2)
                  }}
                </div>
                <div class="cursor-pointer hover:opacity-80 transition-opacity" @click="navigateTo('/leaderboard')">
                  <div class="opacity-90 mb-1 text-[32px] font-semibold">VN RANK</div>
                  <div class="flex items-baseline gap-3">
                    <div class="text-5xl font-bold leading-none text-[100px]">{{
                      getUser.vn_rank || 'Unranked'
                      }}</div>
                    <div v-if="getWeeklyChange !== null" class="flex items-center gap-1 text-2xl font-semibold" :class="getWeeklyChangeClass">
                      <ArrowTrendingUpIcon v-if="getWeeklyChange < 0" class="w-8 h-8" />
                      <ArrowTrendingDownIcon v-else-if="getWeeklyChange > 0" class="w-8 h-8" />
                      <MinusIcon v-else class="w-8 h-8" />
                      {{ Math.abs(getWeeklyChange) }}
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex flex-col items-end justify-between h-[264px]">
                <QrCodeIcon class="w-12 h-12 mb-6 text-white cursor-pointer" @click="openQrActionChooser" />
                <div class="flex items-center space-x-8">
                  <div class="flex flex-col items-center">
                    <div class="relative w-32 h-32">
                      <svg class="w-32 h-32 transform rotate-[225deg]" viewBox="0 0 140 140">
                        <path d="M 70 10 A 60 60 0 1 1 10 70" stroke="white" stroke-width="16" fill="none"
                          opacity="0.25" />
                        <path d="M 70 10 A 60 60 0 1 1 10 70" stroke="white" stroke-width="16" fill="none"
                          :stroke-dasharray="`${282.74 * (homeData.user_info?.win_rate || 0) / 100} 282.74`"
                          class="transition-all duration-1000 ease-out" />
                      </svg>
                      <div class="absolute inset-0 flex items-center justify-center text-3xl font-semibold">
                        {{ Number(homeData.user_info?.win_rate || 0).toFixed(1) }}%
                      </div>
                    </div>
                    <p class="text-[24px] font-medium">Chiến thắng</p>
                  </div>

                  <div class="flex flex-col items-center">
                    <div class="relative w-32 h-32">
                      <svg class="w-32 h-32 transform rotate-[225deg]" viewBox="0 0 140 140">
                        <path d="M 70 10 A 60 60 0 1 1 10 70" stroke="white" stroke-width="16" fill="none"
                          opacity="0.25" />
                        <path d="M 70 10 A 60 60 0 1 1 10 70" stroke="white" stroke-width="16" fill="none"
                          :stroke-dasharray="`${282.74 * (homeData.user_info?.performance || 0) / 100} 282.74`"
                          class="transition-all duration-1000 ease-out" />
                      </svg>
                      <div class="absolute inset-0 flex items-center justify-center text-center">
                        <span class="text-lg font-bold leading-tight" v-html="getPerformanceLevel"></span>
                      </div>
                    </div>
                    <p class="text-[24px] font-medium">Phong độ</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <section>
          <div class="flex items-center justify-start mb-4">
            <h2 class="text-xl font-semibold text-gray-800">Kèo đấu sắp tới</h2>
            <div
              class="flex items-center text-sm text-gray-600 ml-4 cursor-pointer hover:text-gray-800 bg-[#FFFFFF] p-1.5 rounded-full shadow-md">
              <ArrowUpRightIcon class="w-4 h-4 text-gray-[#838799]" />
            </div>
          </div>

          <template v-if="!homeData?.upcoming_mini_tournament?.length">
            <div class="min-h-[220px] flex items-center justify-center text-gray-500 text-sm">
              Không có kèo đấu nào sắp tới
            </div>
          </template>

          <Swiper v-else :slides-per-view="'auto'" :space-between="16" :freeMode="true"
            :mousewheel="{ forceToAxis: true }" :modules="modules" class="!pb-2">
            <SwiperSlide v-for="(mini, i) in homeData.upcoming_mini_tournament" :key="i" class="!w-[320px]">
              <!-- 🔥 GIỮ NGUYÊN CARD -->
              <div
                class="bg-white rounded-[8px] shadow hover:shadow-lg p-4 transition-all relative cursor-pointer h-full">
                <div
                  v-if="isMiniOrganizer(mini)"
                  class="absolute top-4 right-4 text-red-500 cursor-pointer hover:text-red-600"
                  @click.stop="openPromotionModal(mini.id)"
                  
                >
                  <BellAlertIcon class="w-5 h-5" />
                </div>

                <div @click="goToMiniTournamentDetail(mini.id)">
                  <div class="text-base text-gray-700 font-semibold">
                    {{ formatTime(mini.start_time || mini.starts_at) }}
                  </div>
                  <div class="text-sm text-gray-500 mt-0.5">
                    {{ formatDate(mini.start_time || mini.starts_at) }}
                  </div>
                  <div class="text-base text-gray-900 font-bold mt-2 line-clamp-1">
                    {{ mini.name }}
                  </div>
                </div>

                <div class="pt-4 border-gray-100" @click="goToMiniTournamentDetail(mini.id)">
                  <div class="flex justify-start space-x-4">
                    <div class="flex flex-col items-start pr-4 border-r">
                      <span class="text-xs text-gray-500 font-medium mb-2">Người tạo</span>
                      <div class="flex -space-x-2">
                        <img v-for="(organizer, idx) in mini.staff?.organizer" :key="'creator-' + idx"
                          :src="organizer.user.avatar_url" :alt="organizer.user.full_name"
                          class="w-8 h-8 rounded-full border-2 border-white object-cover" />
                      </div>
                    </div>

                    <div class="flex flex-col items-start">
                      <span class="text-xs text-gray-500 font-medium mb-2">Người tham gia</span>
                      <div class="flex items-center -space-x-2">
                        <img v-for="(user, idx) in mini.all_users.slice(0, 3)" :key="'participant-' + idx"
                          :src="user.avatar_url" :alt="user.full_name"
                          class="w-8 h-8 rounded-full border-2 border-white object-cover" />
                        <div v-if="mini.all_users.length > 3"
                          class="w-8 h-8 rounded-full border-2 border-white bg-gray-200 flex items-center justify-center text-xs font-semibold text-gray-600">
                          +{{ mini.all_users.length - 3 }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- 🔥 END CARD -->
            </SwiperSlide>
          </Swiper>
        </section>

        <section>
          <div class="flex items-center justify-start mb-4">
            <h2 class="text-xl font-semibold text-gray-800">Giải đấu sắp tới</h2>
            <div
              class="flex items-center text-sm text-gray-600 ml-4 cursor-pointer hover:text-gray-800 bg-[#FFFFFF] p-1.5 rounded-full shadow-md">
              <ArrowUpRightIcon class="w-4 h-4 text-gray-[#838799]" />
            </div>
          </div>

          <template v-if="!homeData?.upcoming_tournaments?.length">
            <div class="min-h-[220px] flex items-center justify-center text-gray-500 text-sm">
              Không có giải đấu nào sắp tới
            </div>
          </template>

          <Swiper v-else :slides-per-view="'auto'" :space-between="16" :freeMode="true"
            :mousewheel="{ forceToAxis: true }" :modules="modules" class="!pb-2">
            <SwiperSlide v-for="(t, i) in homeData.upcoming_tournaments" :key="i" class="!w-[320px] !h-auto">
              <!-- 🔥 CARD CỐ ĐỊNH CHIỀU CAO -->
              <div
                class="bg-white rounded-[8px] shadow hover:shadow-lg overflow-hidden transition-all p-[16px] cursor-pointer flex flex-col"
                style="height: 340px;">
                <div class="relative h-40 rounded-[4px] cursor-pointer overflow-hidden flex-shrink-0"
                  @click="goToTournamentDetail(t.id)"
                  :style="!t.poster ? { backgroundColor: getRandomColor(t.id) } : {}">
                  <img v-if="t.poster" :src="t.poster" alt="" class="w-full h-full object-cover rounded-[4px]" />
                </div>
                <div class="py-4 flex-1 flex flex-col justify-between" @click="goToTournamentDetail(t.id)">
                  <div>
                    <div class="text-sm font-bold text-gray-900 mb-2 cursor-pointer line-clamp-2">
                      {{ t.name }}
                    </div>

                    <div class="text-xs text-[#004D99] flex items-center">
                      <MapPinIcon class="w-4 h-4 mr-1 flex-shrink-0 mt-0.5 text-[#4392E0]" />
                      <span class="line-clamp-1">
                        {{ t.competition_location?.name ?? 'Không rõ' }}
                      </span>
                    </div>
                    <div class="text-xs text-[#004D99] flex items-center my-2">
                      <CalendarDaysIcon class="w-4 h-4 mr-1 flex-shrink-0 mt-0.5 text-[#4392E0]" />
                      <span class="line-clamp-1">
                        {{ formatDate(t.start_date) }}
                      </span>
                    </div>
                  </div>
                  <p class="text-sm line-clamp-2 text-gray-600">
                    {{ t.description || '' }}
                  </p>
                </div>
              </div>
              <!-- 🔥 END CARD -->
            </SwiperSlide>
          </Swiper>
        </section>

        <!-- CLB của tôi -->
        <div class="bg-white rounded-2xl p-5"
          style="box-shadow: 0 2px 12px rgba(0,0,0,0.06)">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <h3 class="font-semibold text-gray-900 text-base">CLB của tôi</h3>
              <span v-if="homeData.my_club?.length"
                class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-50 text-red-600 text-xs font-semibold">
                {{ homeData.my_club.length }}
              </span>
            </div>
            <button
              class="flex items-center text-sm text-[#D72D36] font-medium hover:text-red-700 transition-colors"
              @click="navigateTo('/club')">
              Xem tất cả
              <ArrowUpRightIcon class="w-4 h-4 ml-1" />
            </button>
          </div>

          <template v-if="!homeData?.my_club?.length">
            <div class="min-h-[120px] flex flex-col items-center justify-center text-gray-400 text-sm gap-2">
              <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              Bạn chưa tham gia CLB nào
            </div>
          </template>

          <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div
              v-for="club in homeData.my_club"
              :key="club.id"
              class="group rounded-2xl p-4 flex flex-col items-center text-center cursor-pointer transition-all duration-200"
              style="background: #F8F9FC; box-shadow: 0 2px 8px rgba(0,0,0,0.04);"
              @mouseenter="hoveredClub = club.id"
              @mouseleave="hoveredClub = null"
              :style="hoveredClub === club.id ? 'background: #FFFFFF; box-shadow: 0 8px 24px rgba(0,0,0,0.10); transform: translateY(-2px);' : ''"
              @click="navigateTo(`/club/${club.id}`)">
              <img
                v-if="club.logo_url"
                :src="getClubLogoUrl(club.logo_url)"
                :alt="club.name"
                class="w-14 h-14 rounded-2xl object-cover mb-3 bg-gray-100"
                @error="e => { e.target.style.display = 'none'; e.target.nextElementSibling.style.display = 'flex'; }" />
              <div
                v-if="club.logo_url"
                class="hidden w-14 h-14 rounded-2xl object-cover mb-3 bg-gradient-to-br from-red-100 to-red-200 text-red-600 font-bold text-xl items-center justify-center">
                {{ club.name.charAt(0).toUpperCase() }}
              </div>
              <div
                v-if="!club.logo_url"
                class="w-14 h-14 rounded-2xl mb-3 bg-gradient-to-br from-red-100 to-red-200 text-red-600 font-bold text-xl flex items-center justify-center">
                {{ club.name.charAt(0).toUpperCase() }}
              </div>
              <span class="text-sm font-semibold text-gray-900 line-clamp-1 w-full">{{ club.name }}</span>
              <span class="text-xs text-gray-400 mt-0.5">{{ club.quantity_members }} thành viên</span>
              <div v-if="club.skill_level?.max"
                class="mt-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full"
                style="background: rgba(67, 146, 224, 0.1);">
                <svg class="w-3 h-3 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
                <span class="text-xs font-semibold text-[#4392E0]">{{ club.skill_level.max.toFixed(2) }}</span>
              </div>
              <div v-else class="mt-2 h-5"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="bg-white rounded-[8px] shadow p-5">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-900 text-base">Tính năng ưa thích</h3>
            <button class="text-gray-600 hover:text-gray-700 rounded-full p-2 bg-[#EDEEF2] shadow-md">
              <PencilIcon class="w-3 h-3" />
            </button>
          </div>
          <div class="grid grid-cols-4 gap-4">
            <div v-for="(f, i) in features" :key="i" class="flex flex-col items-center text-center cursor-pointer group"
              @click="f.action ? f.action() : navigateTo(f.route)">
              <div class="w-12 h-12 bg-red-100 text-red-600 flex items-center justify-center rounded-full mb-2 group-hover:bg-red-200 transition-colors">
                <component :is="f.icon" class="w-6 h-6" />
              </div>
              <p class="text-xs text-gray-700 font-medium">{{ f.label }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-[8px] h-[133px] shadow relative overflow-hidden">
          <Swiper v-if="homeData.banners && homeData.banners.length > 0" :modules="modules" :slides-per-view="1"
            :space-between="0" :loop="homeData.banners.length > 1" :pagination="{ clickable: true }" :autoplay="{
              delay: 5000,
              disableOnInteraction: false,
            }" class="w-full h-[133px] rounded-[8px]">
            <SwiperSlide v-for="banner in homeData.banners" :key="banner.id" class="h-full">
              <a :href="banner.link" :target="banner.link ? '_blank' : '_self'"
                :class="{ 'cursor-pointer': banner.link }" class="block w-full h-full">
                <img :src="getBannerUrl(banner.image_url)" :alt="banner.title || 'Banner'"
                  class="w-full h-full object-cover">
              </a>
            </SwiperSlide>
          </Swiper>
          <div v-else
            class="w-full h-full flex items-center justify-center font-semibold text-lg bg-white text-gray-500">
            Không có banner
          </div>
        </div>
        <LeaderboardSection />
      </div>
    </div>
  </div>

  <Transition name="modal">
    <div v-if="isChoosingQrAction" class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black bg-opacity-80 backdrop-blur-sm modal-overlay" @click="closeAllQrModals">
      </div>

      <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden modal-body">
        <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4 flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
              <QrCodeIcon class="w-6 h-6 text-white" />
            </div>
            <h2 class="text-xl font-bold text-white">QR & Quét mã</h2>
          </div>
          <button @click="closeAllQrModals"
            class="w-8 h-8 flex items-center justify-center rounded-full bg-white bg-opacity-20 hover:bg-opacity-30 transition-all text-white">
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div @click="openQrScanner"
            class="flex items-center p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-red-50 hover:border-red-300 transition-all cursor-pointer">
            <div class="rounded-full bg-slate-200 p-2">
              <QrCodeIcon class="w-6 h-6 text-red-600 flex-shrink-0 mt-0.5" />
            </div>
            <div class="ml-4">
              <p class="font-semibold text-gray-800">Quét mã QR</p>
              <p class="text-xs text-gray-500">Quét mã QR của người khác hoặc CLB / giải đấu</p>
            </div>
          </div>

          <div @click="openMyQrCode"
            class="flex items-center p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-red-50 hover:border-red-300 transition-all cursor-pointer">
            <div class="rounded-full bg-slate-200 p-2">
              <QrCodeIcon class="w-6 h-6 flex-shrink-0 mt-0.5" />
            </div>
            <div class="ml-4">
              <p class="font-semibold text-gray-800">QR của tôi</p>
              <p class="text-xs text-gray-500">Hiển thị mã QR của bạn để người khác quét</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>

  <Transition name="modal">
    <div v-if="isShowingScanner" class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black bg-opacity-80 backdrop-blur-sm modal-overlay" @click="closeScanner"></div>

      <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden modal-body">

        <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4 flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
              <QrCodeIcon class="w-6 h-6 text-white" />
            </div>
            <h2 class="text-xl font-bold text-white">Quét mã QR</h2>
          </div>
          <button @click="closeScanner"
            class="w-8 h-8 flex items-center justify-center rounded-full bg-white bg-opacity-20 hover:bg-opacity-30 transition-all text-white">
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <div class="p-6">
          <div class="relative w-full mx-auto mb-6">
            <div id="qr-reader" class="w-full h-full rounded-2xl overflow-hidden shadow-inner"></div>
          </div>
          <div class="text-center space-y-2">
            <p class="text-gray-700 font-medium">Đưa mã QR vào khung để quét</p>
            <p class="text-sm text-gray-500">Đảm bảo mã QR rõ ràng và đủ ánh sáng</p>
          </div>
        </div>

        <div class="bg-gray-50 px-6 py-4 border-t border-gray-100">
          <div class="flex items-start space-x-2 text-xs text-gray-600">
            <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                clip-rule="evenodd" />
            </svg>
            <p>Camera sẽ tự động quét khi phát hiện mã QR hợp lệ</p>
          </div>
        </div>
      </div>
    </div>
  </Transition>

  <Transition name="modal">
    <div v-if="isShowingConfirmation" class="fixed inset-0 z-[10000] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black bg-opacity-80 backdrop-blur-sm modal-overlay" @click="rescanQrCode">
      </div>

      <div class="relative bg-white rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden modal-body">
        <div class="p-6 text-center">
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 text-red-600 mb-4">
            <QrCodeIcon class="w-6 h-6" />
          </div>
          <h3 class="text-lg font-bold leading-6 text-gray-900 mb-2">Mã QR đã được quét</h3>
          <p class="text-sm text-gray-500 mb-4">
            Tiếp tục chuyển hướng
          </p>
          <div class="mt-4 flex justify-end space-x-3">
            <button @click="rescanQrCode" type="button"
              class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:text-sm">
              Quét lại
            </button>
            <button @click="useScannedCode" type="button"
              class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:text-sm">
              Tiếp tục
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
  <Transition name="modal">
    <QRcodeModal v-if="isShowingMyQr" :value="profileLink" @close="closeMyQrCode" />
  </Transition>

  <PromotionModal
    v-if="selectedPromotionMiniId !== null"
    v-model="isPromotionModalOpen"
    promotableType="mini_tournament"
    :promotableId="selectedPromotionMiniId"
    @success="toast.success('Đã gửi quảng bá thành công')"
  />
</template>
<script setup>
import { onMounted, ref, computed, nextTick, provide } from "vue";
import { useRouter } from 'vue-router'
import { toast } from "vue3-toastify";
import {
  MapPinIcon as MapPinIconOutline,
  UserGroupIcon,
  ChartBarIcon,
  QrCodeIcon,
  PlusCircleIcon,
  BellAlertIcon,
  ArrowUpRightIcon,
  PencilIcon,
  XMarkIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  MinusIcon
} from "@heroicons/vue/24/outline";
import QRcodeModal from '@/components/molecules/QRcodeModal.vue'
import { MapPinIcon, CalendarDaysIcon } from "@heroicons/vue/24/solid";
import { Swiper, SwiperSlide } from 'swiper/vue';
import { Autoplay, Pagination, FreeMode, Mousewheel } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/pagination';
import 'swiper/css/free-mode'
import { Html5Qrcode } from "html5-qrcode";
import * as HomeService from "@/service/home";
import * as ClubService from "@/service/club";
import Background from "@/assets/images/dashboard-bg.svg?url";
import { useUserStore } from "@/store/auth";
import { storeToRefs } from "pinia";
import VerifyIcon from "@/assets/images/verify-icon.svg";
import PromotionModal from "@/components/organisms/PromotionModal.vue";
import LeaderboardSection from "./LeaderboardSection.vue";

const userStore = useUserStore();
const { getUser } = storeToRefs(userStore);
const router = useRouter()
const modules = [Autoplay, Pagination, FreeMode, Mousewheel];
const BASE_STORAGE_URL = 'http://localhost:8000/storage/';
const BASE_FRONTEND_URL = import.meta.env.VITE_FRONTEND_URL;
const homeData = ref({});
provide('myClubs', computed(() => homeData.value?.my_club || []));
const isChoosingQrAction = ref(false);
const isShowingScanner = ref(false);
const isShowingMyQr = ref(false);
// KHAI BÁO BIẾN TRẠNG THÁI MỚI CHO LOGIC QR
const isShowingConfirmation = ref(false);
const decodedQrCode = ref('');
const isPromotionModalOpen = ref(false);
const selectedPromotionMiniId = ref(null);
const hoveredClub = ref(null);
// KẾT THÚC KHAI BÁO MỚI
let html5QrCode = null;
const profileLink = computed(() => {
  return getUser.value ? `${BASE_FRONTEND_URL}/profile/${getUser.value.id}` : '';
});

const features = [
  { label: "CLB", icon: UserGroupIcon, route: '/club' },
  { label: "Tạo trận đấu nhanh", icon: PlusCircleIcon, route: '/quick-match/create' },
  { label: "Tìm sân", icon: MapPinIconOutline, route: '/map' },
  { label: "Xếp hạng", icon: ChartBarIcon, route: '/leaderboard' },
];

const getPerformanceLevel = computed(() => {
  const performance = homeData.value?.user_info?.performance || 0;
  if (performance >= 76) return 'Xuất <br/> sắc';
  if (performance >= 51) return 'Tốt';
  if (performance >= 26) return 'Trung <br/> bình';

  return 'Kém';
});

const getWeeklyChange = computed(() => {
  const leaderboard = homeData.value?.leaderboard || [];
  const userId = getUser.value?.id;
  const currentUser = leaderboard.find(u => u.id === userId);
  return currentUser?.weekly_change ?? null;
});

const getWeeklyChangeClass = computed(() => {
  const change = getWeeklyChange.value;
  if (change === null) return '';
  if (change < 0) return 'text-green-500'; // Cải thiện rank (số nhỏ hơn = rank cao hơn)
  if (change > 0) return 'text-red-500';  // Tụt rank
  return 'text-gray-400'; // Không đổi
});
const getHomeData = async () => {
  try {
    const response = await HomeService.getHomeData({
      mini_tournament_per_page: 50,
      tournament_per_page: 50,
      leaderboard_per_page: 50
    });
    homeData.value = response;
  } catch (error) {
    console.error("Error fetching home data:", error);
  }
};

// Mở modal lựa chọn hành động QR
const openQrActionChooser = () => {
  isChoosingQrAction.value = true;
};

// Đóng tất cả các modal liên quan đến QR
const closeAllQrModals = () => {
  isChoosingQrAction.value = false;
  closeScanner(); // Sẽ dừng hoàn toàn nếu đang quét
  closeMyQrCode();
};

// HÀM XỬ LÝ QUÉT THÀNH CÔNG
const onScanSuccess = (decodedText) => {
  if (html5QrCode && html5QrCode.isScanning) {
    // 1. Tạm dừng quét ngay sau khi phát hiện mã
    html5QrCode.pause(true);

    // 2. Lưu mã và hiển thị modal xác nhận
    decodedQrCode.value = decodedText;
    isShowingConfirmation.value = true;
  }
};
// KẾT THÚC HÀM XỬ LÝ QUÉT THÀNH CÔNG


// Mở modal quét mã QR
const openQrScanner = async () => {
  isChoosingQrAction.value = false;
  isShowingScanner.value = true;
  await nextTick();
  try {
    const cameras = await Html5Qrcode.getCameras();
    if (!cameras.length) {
      toast.error("Không tìm thấy camera trên thiết bị");
      closeScanner();
      return;
    }

    const cameraId = cameras[0].id;

    html5QrCode = new Html5Qrcode("qr-reader");

    await html5QrCode.start(
      cameraId,
      {
        fps: 60,
        qrbox: 300,
        videoConstraints: {
          facingMode: "environment",
          aspectRatio: 1.0
        }
      },
      onScanSuccess,
      (err) => console.warn("QR error:", err)
    );
  } catch (err) {
    console.error("Camera error:", err);
    toast.error("Không thể khởi động camera: " + err.message);
    closeScanner();
  }
};

// Helper function to check if URL is a check-in URL
const isCheckInUrl = (url) => {
  try {
    const urlObj = new URL(url);
    // Check if URL matches pattern: /api/clubs/{clubId}/activities/{activityId}/check-in
    const pathRegex = /\/api\/clubs\/(\d+)\/activities\/(\d+)\/check-in/;
    return pathRegex.test(urlObj.pathname);
  } catch (e) {
    return false;
  }
};

// Helper function to parse check-in URL
const parseCheckInUrl = (url) => {
  try {
    const urlObj = new URL(url);
    const pathRegex = /\/api\/clubs\/(\d+)\/activities\/(\d+)\/check-in/;
    const match = urlObj.pathname.match(pathRegex);

    if (match) {
      return {
        clubId: match[1],
        activityId: match[2],
        token: urlObj.searchParams.get('token')
      };
    }
    return null;
  } catch (e) {
    return null;
  }
};

// HÀM SỬ DỤNG MÃ (CHUYỂN HƯỚNG) (ĐÃ SỬA)
const useScannedCode = async () => {
  const url = decodedQrCode.value;

  // 1. Dừng hoàn toàn QR reader và xóa đối tượng
  try {
    if (html5QrCode && html5QrCode.isScanning) {
      await html5QrCode.stop();
      await html5QrCode.clear();
      html5QrCode = null;
    }
  } catch (e) {
    console.error("Error stopping scanner on use:", e);
  }

  // 2. Đóng modal xác nhận và modal quét chính
  isShowingConfirmation.value = false;
  isShowingScanner.value = false;

  // 3. Kiểm tra nếu là check-in URL
  if (url && isCheckInUrl(url)) {
    const checkInData = parseCheckInUrl(url);

    if (checkInData && checkInData.token) {
      try {
        // Gọi API check-in
        const response = await ClubService.checkInActivity(
          checkInData.clubId,
          checkInData.activityId,
          checkInData.token
        );

        // Chuyển hướng đến trang success với thông tin activity
        router.push({
          name: 'checkin-success',
          query: {
            clubId: checkInData.clubId,
            activityId: checkInData.activityId,
            message: response.message || 'Check-in thành công!',
            activityData: JSON.stringify(response.data?.activity || {})
          }
        });
      } catch (error) {
        console.error('Check-in error:', error);
        // Chuyển hướng đến trang success với error
        router.push({
          name: 'checkin-success',
          query: {
            error: error.response?.data?.message || 'Check-in thất bại. Vui lòng thử lại.'
          }
        });
      }
    } else {
      toast.error('Mã QR check-in không hợp lệ');
    }
  } else if (url) {
    // 4. Nếu không phải check-in URL, chuyển hướng bình thường
    window.open(url, '_self');
  }
};
// KẾT THÚC HÀM SỬ DỤNG MÃ (CHUYỂN HƯỚNG)

// HÀM QUÉT LẠI (ĐÃ SỬA)
const rescanQrCode = async () => {
  isShowingConfirmation.value = false;
  decodedQrCode.value = '';

  // Tiếp tục quét (resume)
  if (html5QrCode) {
    try {
      await html5QrCode.resume();
    } catch (e) {
      console.error("Error resuming scanner:", e);
    }
  }
};
// KẾT THÚC HÀM QUÉT LẠI


// Đóng modal quét mã QR (ĐÃ SỬA)
const closeScanner = async () => {
  isShowingScanner.value = false;
  isShowingConfirmation.value = false;

  // Dừng hoàn toàn HTML5 QR Code khi người dùng đóng modal Quét chính
  try {
    if (html5QrCode && html5QrCode.isScanning) {
      await html5QrCode.stop();
      await html5QrCode.clear();
      html5QrCode = null;
    }
  } catch (e) {
    // Bắt lỗi khi cố gắng dừng một thứ đã dừng/null
    console.error(e);
  }
};

// Mở modal hiển thị QR của tôi
const openMyQrCode = () => {
  isChoosingQrAction.value = false; // Đóng modal lựa chọn
  isShowingMyQr.value = true; // Mở modal QR của tôi
  // Component QRcodeModal sẽ được hiển thị
};

// Đóng modal hiển thị QR của tôi
const closeMyQrCode = () => {
  isShowingMyQr.value = false;
};

const openPromotionModal = (miniId) => {
  selectedPromotionMiniId.value = miniId;
  isPromotionModalOpen.value = true;
};

const isMiniOrganizer = (mini) => {
  const currentUserId = getUser.value?.id;
  const organizers = mini?.staff?.organizer || [];

  if (!currentUserId || !Array.isArray(organizers)) return false;

  return organizers.some((organizer) => organizer?.user?.id === currentUserId);
};


const getClubLogoUrl = (url) => {
  if (!url) return '';
  if (url.startsWith('http')) return url;
  return BASE_STORAGE_URL + url;
};

const getBannerUrl = (url) => {
  if (url && (url.startsWith('http') || url.startsWith('https'))) {
    return url;
  }
  return url ? BASE_STORAGE_URL + url : '';
};

const getRandomColor = (seed) => {
  const colors = ['#E57373', '#64B5F6', '#81C784', '#FFD54F', '#BA68C8', '#4DD0E1'];
  return colors[seed % colors.length];
};

function formatTime(datetime) {
  const date = new Date(datetime);
  let hours = date.getHours();
  const minutes = date.getMinutes();
  const ampm = hours >= 12 ? 'PM' : 'AM';

  hours = hours % 12;
  hours = hours ? hours : 12;

  const minutesStr = minutes < 10 ? '0' + minutes : minutes;

  return `${hours}:${minutesStr} ${ampm}`;
}

function formatDate(datetime) {
  const date = new Date(datetime);
  const days = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
  const dayName = days[date.getDay()];
  const day = date.getDate();
  const month = date.getMonth() + 1;

  return `${dayName}, ${day.toString().padStart(2, '0')}/${month.toString().padStart(2, '0')}`;
}

function goToMiniTournamentDetail(id) {
  if (!id) return
  router.push({ name: 'mini-tournament-detail', params: { id } })
}

function goToTournamentDetail(id) {
  if (!id) return
  router.push({ name: 'tournament-detail', params: { id } })
}

function navigateTo(route) {
  if (route) {
    router.push(route)
  }
}
onMounted(async () => {
  await getHomeData();
});

</script>

<style scoped>
.bg-red-custom {
  background-size: cover;
  background-position: center;
}

.swiper-pagination-bullet-active {
  background-color: white !important;
}

#qr-reader video,
#qr-reader canvas {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover !important;
}

/* ------------------------------------- */
/* CSS TRANSITIONS CHO MODAL */
/* ------------------------------------- */

/* 1. Transition cho toàn bộ Modal (Bao gồm lớp phủ và nội dung) */
/* Lớp phủ (modal-overlay) sẽ mờ dần, nội dung (modal-body) sẽ mờ và scale */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

/* 2. Transition riêng cho lớp phủ (overlay) */
.modal-enter-active .modal-overlay,
.modal-leave-active .modal-overlay {
  transition: opacity 0.3s ease;
}

.modal-enter-from .modal-overlay,
.modal-leave-to .modal-overlay {
  opacity: 0;
}

/* 3. Transition riêng cho phần nội dung chính của Modal (body) */
.modal-enter-active .modal-body {
  /* Hiệu ứng nảy nhẹ khi mở (scale) */
  transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.modal-leave-active .modal-body {
  /* Hiệu ứng đóng nhanh hơn */
  transition: all 0.2s ease-in;
}

.modal-enter-from .modal-body,
.modal-leave-to .modal-body {
  opacity: 0;
  transform: scale(0.95);
}

/* ------------------------------------- */
/* CSS TRANSITIONS CHO LEADERBOARD */
/* ------------------------------------- */

.list-fade-enter-active,
.list-fade-leave-active {
  transition: opacity 0.2s ease;
}

.list-fade-enter-from,
.list-fade-leave-to {
  opacity: 0;
}
</style>
