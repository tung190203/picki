<template>
    <div class="p-4 max-w-5xl mx-auto overflow-visible">
        <div class="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-6 overflow-visible">
            <div class="space-y-4">
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="font-semibold text-gray-800 text-base">Môn thể thao</h3>
                    </div>
                    <div class="px-4 py-3">
                        <p class="text-xs text-gray-500 mb-3">Môn thể thao của tôi • {{ sports.length }}</p>
                        <Swiper :slides-per-view="'auto'" :space-between="8" :freeMode="true" @swiper="onSwiperInit"
                            :mousewheel="{ forceToAxis: true }" :modules="modules" class="!pb-2">
                            <SwiperSlide v-for="sport in sports" :key="sport.id" class="!w-28">
                                <div @click="selectedSportId = sport.id" :class="[
                                    'flex flex-col items-center justify-center px-3 py-3 rounded-lg cursor-pointer transition select-none',
                                    selectedSportId === sport.id
                                        ? 'bg-[#D72D36] text-white'
                                        : 'border border-gray-200 text-gray-700 hover:border-gray-400 bg-gray-50'
                                ]">
                                    <img :src="sport.icon || '/images/basketball.png'"
                                        :class="{ 'filter brightness-0 invert': selectedSportId === sport.id }" alt=""
                                        draggable="false" class="w-8 h-8 mb-2" />
                                    <div class="font-semibold text-xs text-center leading-tight">
                                        {{ sport.name }}
                                    </div>
                                </div>
                            </SwiperSlide>
                        </Swiper>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <!-- Thông tin giải đấu -->
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="font-semibold text-gray-800 text-base">Thông tin giải đấu</h3>
                    </div>
                    <div class="px-4 py-4 space-y-4">
                        <input v-model="tournamentName" type="text" placeholder="Điền tên giải đấu của bạn"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400 placeholder:text-sm placeholder:text-gray-400 bg-white" />

                        <!-- Poster upload -->
                        <div>
                            <label class="text-xs text-gray-600 font-medium block mb-1">Ảnh bìa giải đấu</label>
                            <input ref="posterFileInput" type="file" accept="image/*" @change="onPosterFileChange" class="hidden" />
                            <div v-if="!posterPreview"
                                @click="$refs.posterFileInput.click()"
                                class="border-2 border-dashed border-gray-200 rounded-lg p-4 text-center cursor-pointer hover:border-red-400 transition-colors">
                                <div class="flex flex-col items-center gap-1">
                                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-xs text-gray-400">Tải lên ảnh bìa (PNG, JPG, tối đa 5MB)</p>
                                </div>
                            </div>
                            <div v-else class="relative inline-block w-full">
                                <img :src="posterPreview" class="w-full h-32 object-cover rounded-lg" />
                                <button @click="removePoster"
                                    class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold hover:bg-red-600">×</button>
                            </div>
                        </div>

                        <textarea v-model="tournamentNote" rows="3" placeholder="Thêm ghi chú cho giải đấu"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400 placeholder:text-sm placeholder:text-gray-400 bg-white resize-none"></textarea>
                    </div>
                </div>

                <!-- Liên kết CLB -->
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-visible">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="font-semibold text-gray-800 text-base">Liên kết CLB</h3>
                    </div>
                    <div class="px-4 py-4">
                        <div class="relative" @click.stop>
                            <input v-model="clubKeyword"
                                @input="searchClubs(clubKeyword)"
                                @focus="clubKeyword.length >= 2 && (isClubDropdownOpen = clubResults.length > 0)"
                                @blur="setTimeout(() => isClubDropdownOpen = false, 200)"
                                type="text" placeholder="Tìm kiếm CLB của bạn..."
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400 placeholder:text-sm placeholder:text-gray-400 bg-white" />
                            <button v-if="selectedClub" @click="clearClub" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
                            <div v-if="isClubDropdownOpen"
                                class="absolute left-0 right-0 top-full mt-2 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-60 overflow-y-auto">
                                <button v-for="club in clubResults" :key="club.id"
                                    @mousedown.prevent="selectClub(club)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg block">
                                    <span class="font-medium">{{ club.name }}</span>
                                    <p v-if="club.description" class="text-xs text-gray-500 truncate">{{ club.description }}</p>
                                </button>
                                <p v-if="!clubResults.length && clubKeyword.length >= 2" class="p-4 text-gray-500 text-sm">Không tìm thấy CLB nào.</p>
                            </div>
                        </div>
                        <p v-if="selectedClub" class="text-xs text-green-600 mt-2">
                            ✓ Đã chọn: {{ selectedClub.name }}
                        </p>
                        <p v-else class="text-xs text-gray-400 mt-2">
                            Để trống nếu đây là giải thường (không thuộc CLB)
                        </p>
                    </div>
                </div>

                <!-- Chi tiết -->
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-visible">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="font-semibold text-gray-800 text-base">Chi tiết</h3>
                    </div>
                    <div class="px-4 py-4 space-y-4">
                        <div class="relative flex items-center overflow-visible" @click.stop>
                            <MapPinIcon class="w-4 h-4 text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input v-model="locationKeyword" @input="fetchCompetitionLocations(locationKeyword)"
                                @focus="isLocationDropdownOpen = competitionLocations.length > 0 || locationKeyword.length >= 2"
                                @blur="setTimeout(() => isLocationDropdownOpen = false, 200)" type="text"
                                placeholder="Địa điểm"
                                class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400 placeholder:text-sm placeholder:text-gray-400 bg-white" />

                            <div v-if="isLocationDropdownOpen"
                                class="absolute left-0 right-0 top-full mt-2 bg-white border border-gray-200 rounded-lg shadow-lg z-[200] max-h-60 overflow-y-auto">
                                <button v-for="location in competitionLocations" :key="location.id"
                                    @mousedown.prevent="selectLocation(location)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': selectedLocation && selectedLocation.id === location.id }">
                                    {{ location.name }}
                                    <p v-if="location.address" class="text-xs text-gray-500 truncate">{{
                                        location.address }}</p>
                                </button>
                                <p v-if="!competitionLocations.length && locationKeyword.length >= 2"
                                    class="p-4 text-gray-500 text-sm">Không tìm thấy địa điểm nào.</p>
                            </div>
                        </div>
                        <div class="relative bg-gray-50 rounded-lg" @click.stop>
                            <button @click="toggleOpenDate"
                                class="w-full flex items-center justify-between px-3 py-2 hover:bg-gray-100 rounded-lg transition-colors">
                                <div class="flex items-center gap-2">
                                    <CalendarDaysIcon class="w-4 h-4 text-gray-500" />
                                    <span class="text-sm"
                                        :class="{ 'text-gray-400': !formattedDate, 'text-gray-800 font-medium': formattedDate }">
                                        {{ formattedDate || 'Thời gian dự kiến bắt đầu giải' }}
                                    </span>
                                </div>
                                <ChevronRightIcon class="w-4 h-4 transition-transform text-gray-500"
                                    :class="{ 'rotate-90': openDate }" />
                            </button>

                            <Transition name="fade">
                                <div v-if="openDate"
                                    class="absolute top-full left-0 right-0 mt-2 p-4 z-[200] bg-white rounded-lg shadow-lg border border-gray-200">
                                    <VueDatePicker v-model="date" :locale="vi" inline auto-apply enable-time-picker />
                                </div>
                            </Transition>
                        </div>
                        <span class="text-xs text-gray-400 block">Bạn có thể bắt đầu giải ngày khi có đủ người chơi hoặc đội chơi</span>
                    </div>
                </div>

                <!-- Thời gian đăng kí -->
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-visible">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="font-semibold text-gray-800 text-base">Thời gian đăng kí</h3>
                    </div>
                    <div class="px-4 py-4 space-y-3">
                        <div class="flex items-center justify-between relative">
                            <span class="text-sm text-gray-700">Mở đăng kí</span>
                            <button @click="toggleOpenRegistrationOpenAt" @click.stop
                                class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                <span class="font-medium text-sm text-blue-600">{{ formattedRegistrationOpenAt || 'Chọn thời gian' }}</span>
                                <ChevronRightIcon class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-90': openRegistrationOpenAt }" />
                            </button>
                            <Transition name="fade">
                                <div v-if="openRegistrationOpenAt" @click.stop
                                    class="absolute right-0 top-full mt-2 p-4 z-[200] bg-white rounded-lg shadow-lg border border-gray-200">
                                    <VueDatePicker v-model="registrationOpenAt" :locale="vi" inline auto-apply
                                        enable-time-picker />
                                </div>
                            </Transition>
                        </div>
                        <div class="flex items-center justify-between relative">
                            <span class="text-sm text-gray-700">Hạn đăng kí sớm</span>
                            <button @click="toggleOpenEarlyDeadline" @click.stop
                                class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                <span class="font-medium text-sm text-blue-600">{{ formattedEarlyRegistrationDeadline || 'Chọn thời gian' }}</span>
                                <ChevronRightIcon class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-90': openEarlyDeadline }" />
                            </button>
                            <Transition name="fade">
                                <div v-if="openEarlyDeadline" @click.stop
                                    class="absolute right-0 top-full mt-2 p-4 z-[200] bg-white rounded-lg shadow-lg border border-gray-200">
                                    <VueDatePicker v-model="earlyRegistrationDeadline" :locale="vi" inline auto-apply
                                        enable-time-picker />
                                </div>
                            </Transition>
                        </div>
                        <div class="flex items-center justify-between relative">
                            <span class="text-sm text-gray-700">Hạn chót đăng kí</span>
                            <button @click="toggleOpenClosedDeadline" @click.stop
                                class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                <span class="font-medium text-sm text-blue-600">{{ formattedRegistrationClosedAt || 'Chọn thời gian' }}</span>
                                <ChevronRightIcon class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-90': openClosedDeadline }" />
                            </button>
                            <Transition name="fade">
                                <div v-if="openClosedDeadline" @click.stop
                                    class="absolute right-0 top-full mt-2 p-4 z-[200] bg-white rounded-lg shadow-lg border border-gray-200">
                                    <VueDatePicker v-model="registrationClosedAt" :locale="vi" inline auto-apply
                                        enable-time-picker />
                                </div>
                            </Transition>
                        </div>
                        <div class="flex items-center justify-between relative">
                            <span class="text-sm text-gray-700">Thời lượng</span>
                            <button @click="toggleOpenDuration" @click.stop
                                class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                <span class="font-medium text-sm text-blue-600">{{ durationLabel }}</span>
                                <ChevronRightIcon class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-90': openDuration }" />
                            </button>
                            <div v-if="openDuration" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                                <button v-for="d in durationOptions" :key="d.value" @click="selectDuration(d.value)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': duration === d.value }">
                                    {{ d.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DUPR -->
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-visible">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="font-semibold text-gray-800 text-base">DUPR</h3>
                    </div>
                    <div class="px-4 py-4 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-700">Tích điểm DUPR</span>
                            <button @click="toggleDUPR"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="duprEnabled ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="duprEnabled ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-700">Tích điểm PICKI</span>
                            <button @click="toggleVNDUPR"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="vnduprEnabled ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="vnduprEnabled ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>
                        <div class="border-t border-gray-100 pt-3 space-y-3">
                            <div class="flex items-center justify-between relative">
                                <span class="text-sm text-gray-700">Trình độ tối thiểu</span>
                                <button @click="toggleOpenMinLevel" @click.stop
                                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                    <span class="font-medium text-sm">{{ minLevel }}</span>
                                    <ChevronRightIcon class="w-4 h-4 transition-transform"
                                        :class="{ 'rotate-90': openMinLevel }" />
                                </button>

                                <div v-if="openMinLevel" @click.stop
                                    class="absolute right-0 top-full mt-2 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                                    <button v-for="level in levels" :key="level" @click="selectMinLevel(level)"
                                        class="px-4 py-2 w-full text-sm text-left hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                        :class="{ 'bg-gray-50 font-medium': minLevel === level }">
                                        {{ level }}
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between relative">
                                <span class="text-sm text-gray-700">Trình độ tối đa</span>
                                <button @click="toggleOpenMaxLevel" @click.stop
                                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                    <span class="font-medium text-sm">{{ maxLevel }}</span>
                                    <ChevronRightIcon class="w-4 h-4 transition-transform"
                                        :class="{ 'rotate-90': openMaxLevel }" />
                                </button>

                                <div v-if="openMaxLevel" @click.stop
                                    class="absolute right-0 top-full mt-2 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                                    <button v-for="level in levels" :key="level" @click="selectMaxLevel(level)"
                                        class="px-4 py-2 w-full text-sm text-left hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                        :class="{ 'bg-gray-50 font-medium': maxLevel === level }">
                                        {{ level }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Người tham gia -->
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="font-semibold text-gray-800 text-base">Người tham gia</h3>
                    </div>
                    <div class="px-4 py-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Số đội chơi tối đa</span>
                            <div class="flex items-center gap-3">
                                <button @click="decreaseTeam"
                                    class="w-7 h-7 bg-gray-700 text-white rounded hover:bg-gray-600 flex items-center justify-center text-sm select-none font-bold">
                                    −
                                </button>
                                <span class="text-lg font-semibold w-8 text-center select-none">{{ teamCount
                                }}</span>
                                <button @click="increaseTeam"
                                    class="w-7 h-7 bg-gray-700 text-white rounded hover:bg-gray-600 flex items-center justify-center text-sm select-none font-bold">
                                    +
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Số thành viên trong 1 đội</span>
                            <div class="flex items-center gap-3">
                                <button @click="decreasePlayerPerTeam"
                                    class="w-7 h-7 bg-gray-700 text-white rounded hover:bg-gray-600 flex items-center justify-center text-sm select-none font-bold">
                                    −
                                </button>
                                <span class="text-lg font-semibold w-8 text-center select-none">{{ playerPerTeam
                                }}</span>
                                <button @click="increasePlayerPerTeam"
                                    class="w-7 h-7 bg-gray-700 text-white rounded hover:bg-gray-600 flex items-center justify-center text-sm select-none font-bold">
                                    +
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Phí giải đấu -->
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="font-semibold text-gray-800 text-base">Phí giải đấu</h3>
                    </div>
                    <div class="px-4 py-4 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <div>
                                <span class="text-sm text-gray-700 font-medium">Thu phí tham gia</span>
                                <p class="text-xs text-gray-400">Thu tiền từ người tham gia</p>
                            </div>
                            <button @click="hasFee = !hasFee"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="hasFee ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                    :class="hasFee ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <div v-if="hasFee">
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div>
                                    <span class="text-sm text-gray-700 font-medium">Quản lý tài chính</span>
                                    <p class="text-xs text-gray-400">Theo dõi và quản lý thu chi</p>
                                </div>
                                <button @click="hasFinancialManagement = !hasFinancialManagement"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="hasFinancialManagement ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                        :class="hasFinancialManagement ? 'translate-x-6' : 'translate-x-1'" />
                                </button>
                            </div>

                            <div class="flex items-center justify-between pt-3">
                                <div>
                                    <span class="text-sm text-gray-700">Chia tiền tự động</span>
                                    <p class="text-xs text-gray-400">Tổng tiền / số người</p>
                                </div>
                                <button @click="autoSplitFee = !autoSplitFee"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="autoSplitFee ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                        :class="autoSplitFee ? 'translate-x-6' : 'translate-x-1'" />
                                </button>
                            </div>

                            <div class="flex items-center justify-between relative mt-3">
                                <span class="text-sm text-gray-700">Phí tham gia</span>
                                <button @click="toggleFeeAmountInput" @click.stop
                                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                    <span class="font-medium text-sm text-blue-600">{{ formattedFeeAmount }}</span>
                                    <ChevronRightIcon class="w-4 h-4 transition-transform"
                                        :class="{ 'rotate-90': isFeeAmountInputOpen }" />
                                </button>

                                <div v-if="isFeeAmountInputOpen" @click.stop
                                    class="absolute right-0 top-full mt-2 bg-white border border-gray-200 rounded-lg shadow-lg z-50 p-3">
                                    <input v-model="feeAmount" type="number" min="0" step="10000"
                                        class="w-40 px-2 py-1 border border-gray-200 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400" />
                                    <div class="mt-1 text-xs text-gray-400">Nhập số tiền VNĐ</div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="text-xs text-gray-600 font-medium block mb-1">Mã QR thanh toán</label>
                                <input ref="qrFileInput" type="file" accept="image/*" @change="onQrFileChange" class="hidden" />
                                <div v-if="!qrCodePreview"
                                    @click="$refs.qrFileInput.click()"
                                    class="border-2 border-dashed border-gray-200 rounded-lg p-4 text-center cursor-pointer hover:border-red-400 transition-colors">
                                    <p class="text-xs text-gray-400">Tải ảnh QR (PNG, JPG, tối đa 5MB)</p>
                                </div>
                                <div v-else class="relative inline-block">
                                    <img :src="qrCodePreview" class="w-20 h-20 object-cover rounded" />
                                    <button @click="removeQrCode"
                                        class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">×</button>
                                </div>
                            </div>

                            <div class="mt-3">
                                <textarea v-model="feeDescription" rows="2"
                                    placeholder="Ghi chú thanh toán (VD: STK, tên TK, nội dung chuyển khoản...)"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400 resize-none" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quyền riêng tư -->
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="font-semibold text-gray-800 text-base">Quyền riêng tư</h3>
                    </div>
                    <div class="px-4 py-4 space-y-2">
                        <div @click="isPrivate = false" class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors"
                            :class="isPrivate === false ? 'bg-blue-50' : 'hover:bg-gray-50'">
                            <GlobeAsiaAustraliaIcon class="w-5 h-5"
                                :class="isPrivate === false ? 'text-blue-600' : 'text-gray-400'" />
                            <div>
                                <span :class="isPrivate === false ? 'text-blue-700 font-medium' : 'text-gray-700'">Công khai</span>
                                <p class="text-xs" :class="isPrivate === false ? 'text-blue-500' : 'text-gray-400'">Ai cũng có thể tìm thấy và đăng ký</p>
                            </div>
                        </div>
                        <div @click="isPrivate = true" class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors"
                            :class="isPrivate === true ? 'bg-blue-50' : 'hover:bg-gray-50'">
                            <LockClosedIcon class="w-5 h-5"
                                :class="isPrivate === true ? 'text-blue-600' : 'text-gray-400'" />
                            <div>
                                <span :class="isPrivate === true ? 'text-blue-700 font-medium' : 'text-gray-700'">Giải riêng tư</span>
                                <p class="text-xs" :class="isPrivate === true ? 'text-blue-500' : 'text-gray-400'">Chỉ dành cho những ai được mời</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tôi tham gia -->
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
                    <div class="flex items-center justify-between px-4 py-4">
                        <div>
                            <span class="font-medium text-gray-800 text-sm">Tôi tham gia giải đấu</span>
                            <p class="text-xs text-gray-400">Đăng ký tham gia với tư cách vận động viên</p>
                        </div>
                        <button @click="toggleCreatorJoin"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="creatorJoin ? 'bg-[#D72D36]' : 'bg-gray-300'">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                :class="creatorJoin ? 'translate-x-6' : 'translate-x-1'" />
                        </button>
                    </div>
                </div>

                <!-- Nút hành động -->
                <div class="flex items-center gap-3">
                    <button @click="handleSubmit"
                        class="flex-1 py-3 bg-[#D72D36] text-white rounded-lg font-medium hover:bg-red-700 transition-colors text-sm">
                        {{ btnTitle }}
                    </button>
                    <button @click="router.back()" v-if="isEditMode"
                        class="px-6 py-3 bg-gray-100 text-gray-600 rounded-lg font-medium hover:bg-gray-200 transition-colors text-sm">
                        Quay lại
                    </button>
                </div>

                <div class="flex flex-col gap-2">
                    <button v-if="!isEditMode" type="button" @click="openTemplateModal"
                        class="w-full border border-red-200 text-[#D72D36] font-medium py-3 rounded-lg flex items-center justify-center gap-2 hover:bg-red-50 transition-colors text-sm">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3H5a2 2 0 0 0-2 2v14l4-3 4 3 4-3 4 3V5a2 2 0 0 0-2-2z" />
                        </svg>
                        <span>Chọn mẫu giải đấu</span>
                    </button>

                    <button v-if="!isEditMode" type="button" @click="handleSaveTemplate"
                        class="w-full border border-red-200 text-[#D72D36] font-medium py-3 rounded-lg flex items-center justify-center gap-2 hover:bg-red-50 transition-colors text-sm">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17 3H7a2 2 0 0 0-2 2v14l4-3 4 3 4-3 4 3V5a2 2 0 0 0-2-2h-2z" />
                            <path d="M15 9H9V7h6v2z" fill="#fff" />
                        </svg>
                        <span>Lưu làm mẫu</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal chọn mẫu giải đấu -->
    <div v-if="isTemplateModalOpen"
        class="fixed inset-0 z-[99] flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h4 class="text-base font-semibold text-gray-800">Chọn mẫu giải đấu</h4>
                <button @click="closeTemplateModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-5 py-4">
                <div v-if="isLoadingTemplates" class="py-6 text-center text-sm text-gray-500">
                    Đang tải...
                </div>
                <div v-else-if="!templates.length" class="py-6 text-center text-sm text-gray-500">
                    Bạn chưa có mẫu giải đấu nào.
                </div>
                <div v-else class="space-y-2 max-h-80 overflow-y-auto">
                    <button v-for="template in templates" :key="template.id" type="button"
                        @click="applyTemplate(template)"
                        class="w-full flex items-center justify-between px-4 py-3 rounded-lg border border-gray-200 hover:border-red-400 hover:bg-red-50 transition-colors text-left">
                        <p class="text-sm font-medium text-gray-700">
                            {{ template.name }}
                        </p>
                        <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18l6-6-6-6" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import VueDatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import { vi } from 'date-fns/locale'
import { ChevronRightIcon, GlobeAsiaAustraliaIcon, LockClosedIcon } from "@heroicons/vue/24/solid";
import { CalendarDaysIcon, MapPinIcon } from "@heroicons/vue/24/outline";
import * as TournamentService from '@/service/tournament'
import * as SportService from '@/service/sport'
import * as CompetitionLocationService from '@/service/competitionLocation'
import { toast } from 'vue3-toastify'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { FreeMode, Mousewheel } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/free-mode'
import { levels } from '@/constants/levels';
import { useFormattedDate } from '@/composables/formatedDate'
import { useRoute, useRouter } from 'vue-router'
import { watch } from 'vue';
import axiosInstance from "@/utils/httpRequest.js"

const router = useRouter()
const route = useRoute()
const modules = [FreeMode, Mousewheel]
const tournamentId = route.params.id || null
const isEditMode = computed(() => !!tournamentId)
const btnTitle = computed(() => isEditMode.value ? 'Chỉnh sửa giải đấu' : 'Tạo giải đấu');

// Constants
const durationOptions = [
    { label: '1 ngày', value: 1440 },
    { label: '2 ngày', value: 2880 },
    { label: '3 ngày', value: 4320 },
    { label: '1 tuần', value: 10080 },
    { label: '2 tuần', value: 20160 },
    { label: '3 tuần', value: 30240 },
    { label: '4 tuần', value: 40320 },
    { label: '1 tháng', value: 43200 },
    { label: '2 tháng', value: 86400 },
    { label: '3 tháng', value: 129600 },
]

// Hàm định dạng ngày giờ sang chuỗi YYYY-MM-DD HH:mm:ss
// Định nghĩa lại ở đây để khắc phục lỗi import bị thiếu
const formatToISOString = (dateObj) => {
    if (!dateObj) return null;
    const d = dateObj instanceof Date ? dateObj : new Date(dateObj);
    
    if (isNaN(d.getTime())) return null; 

    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const seconds = String(d.getSeconds()).padStart(2, '0');
    
    // Trả về định dạng: 2025-09-20 08:00:00 (Ví dụ)
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}


// =================================================================================
// Refs and State (Existing)
// =================================================================================
const openDate = ref(false)
const openMinLevel = ref(false)
const openMaxLevel = ref(false)
const date = ref(null) // Thời gian dự kiến bắt đầu giải
const sports = ref([])
const teamCount = ref(1)
const playerPerTeam = ref(2)
const selectedSportId = ref(null)
const tournamentName = ref('')
const tournamentNote = ref('')

const duprEnabled = ref(true)
const vnduprEnabled = ref(true)

const minLevel = ref('Không giới hạn')
const maxLevel = ref('Không giới hạn')

const { formattedDate } = useFormattedDate(date)

// =================================================================================
// REFS CHO PHẦN TÌM KIẾM ĐỊA ĐIỂM
// =================================================================================
const locationKeyword = ref('')
const competitionLocations = ref([])
const selectedLocation = ref(null)
const isLocationDropdownOpen = ref(false)

// =================================================================================
// REFS CHO PHẦN ĐĂNG KÍ
// =================================================================================
const openRegistrationOpenAt = ref(false)
const registrationOpenAt = ref(null) // Thời gian mở đăng kí

const openEarlyDeadline = ref(false)
const earlyRegistrationDeadline = ref(null) // Hạn đăng kí sớm

const openClosedDeadline = ref(false)
const registrationClosedAt = ref(null) // Hạn chót đăng kí

const openDuration = ref(false)
const duration = ref(durationOptions[durationOptions.length - 10].value)

const { formattedDate: formattedRegistrationOpenAt } = useFormattedDate(registrationOpenAt)
const { formattedDate: formattedEarlyRegistrationDeadline } = useFormattedDate(earlyRegistrationDeadline)
const { formattedDate: formattedRegistrationClosedAt } = useFormattedDate(registrationClosedAt)

const durationLabel = computed(() => durationOptions.find(d => d.value === duration.value)?.label || 'Chọn thời lượng')
const durationInMinutes = computed(() => duration.value)

const swiperInstance = ref(null);
const onSwiperInit = (swiper) => {
  swiperInstance.value = swiper;
};

watch(selectedSportId, (id) => {
  const index = sports.value.findIndex(s => s.id === id);
  if (index !== -1 && swiperInstance.value) {
    swiperInstance.value.slideTo(index);
  }
});

// =================================================================================
// REFS CHO PHẦN PHÍ GIẢI ĐẤU
// =================================================================================
const hasFee = ref(false)
const hasFinancialManagement = ref(false)
const feeAmount = ref(100000)
const isFeeAmountInputOpen = ref(false)
const autoSplitFee = ref(false)
const feeDescription = ref('')
const qrCodeFile = ref(null)
const qrCodeImage = ref(null)
const qrCodePreview = ref(null)
const qrFileInput = ref(null)
const posterFile = ref(null)
const posterPreview = ref(null)
const posterFileInput = ref(null)

const formattedFeeAmount = computed(() => {
    return `VNĐ${feeAmount.value.toLocaleString('vi-VN')}`
})

// =================================================================================
// REFS CHO LIÊN KẾT CLB
// =================================================================================
const selectedClub = ref(null)
const clubKeyword = ref('')
const clubResults = ref([])
const isClubDropdownOpen = ref(false)


// =================================================================================
// REFS CHO PHẦN QUYỀN RIÊNG TƯ
// =================================================================================
const isPrivate = ref(false) // false: Công khai, true: Giải riêng tư
const creatorJoin = ref(false)

// =================================================================================
// Template refs
// =================================================================================
const isTemplateModalOpen = ref(false)
const templates = ref([])
const isLoadingTemplates = ref(false)
const isSubmitting = ref(false)

// Định nghĩa trạng thái ban đầu để reset form
const initialStates = {
    openDate: false, openMinLevel: false, openMaxLevel: false,
    isLocationDropdownOpen: false, isFeeAmountInputOpen: false,
    openRegistrationOpenAt: false, openEarlyDeadline: false, openClosedDeadline: false, openDuration: false,
    date: null, teamCount: 1, playerPerTeam: 2,
    tournamentName: '', tournamentNote: '', selectedSportId: null,
    duprEnabled: true, vnduprEnabled: true, minLevel: 'Không giới hạn', maxLevel: 'Không giới hạn',
    locationKeyword: '', selectedLocation: null, competitionLocations: [],
    registrationOpenAt: null, earlyRegistrationDeadline: null, registrationClosedAt: null, duration: durationOptions[durationOptions.length - 1].value,
    hasFee: false, hasFinancialManagement: false, feeAmount: 100000,
    isPrivate: false,
    creatorJoin: false,
};

const resetFormState = () => {
    // Basic Info
    date.value = initialStates.date;
    teamCount.value = initialStates.teamCount;
    playerPerTeam.value = initialStates.playerPerTeam;
    tournamentName.value = initialStates.tournamentName;
    tournamentNote.value = initialStates.tournamentNote;

    // DUPR
    duprEnabled.value = initialStates.duprEnabled;
    vnduprEnabled.value = initialStates.vnduprEnabled;
    minLevel.value = initialStates.minLevel;
    maxLevel.value = initialStates.maxLevel;

    // Location
    locationKeyword.value = initialStates.locationKeyword;
    selectedLocation.value = initialStates.selectedLocation;
    competitionLocations.value = initialStates.competitionLocations;

    // Registration
    registrationOpenAt.value = initialStates.registrationOpenAt;
    earlyRegistrationDeadline.value = initialStates.earlyRegistrationDeadline;
    registrationClosedAt.value = initialStates.registrationClosedAt;
    duration.value = initialStates.duration;

    // Fee
    hasFee.value = initialStates.hasFee;
    hasFinancialManagement.value = initialStates.hasFinancialManagement;
    feeAmount.value = initialStates.feeAmount;

    // Poster
    posterFile.value = null;
    posterPreview.value = null;

    // Privacy
    isPrivate.value = initialStates.isPrivate;
    creatorJoin.value = initialStates.creatorJoin;

    // Reset Sport Selection
    if (sports.value.length > 0) {
        selectedSportId.value = sports.value[0].id;
    }

    // Close all dropdowns
    closeOtherDropdowns(null);
};
// =================================================================================
// Global Dropdown/Modal Handlers
// =================================================================================

const closeOtherDropdowns = (exceptRef) => {
    // Chi tiết giải đấu
    if (exceptRef !== openDate) openDate.value = false
    if (exceptRef !== isLocationDropdownOpen) isLocationDropdownOpen.value = false

    // DUPR
    if (exceptRef !== openMinLevel) openMinLevel.value = false
    if (exceptRef !== openMaxLevel) openMaxLevel.value = false

    // Thời gian đăng kí
    if (exceptRef !== openRegistrationOpenAt) openRegistrationOpenAt.value = false
    if (exceptRef !== openEarlyDeadline) openEarlyDeadline.value = false
    if (exceptRef !== openClosedDeadline) openClosedDeadline.value = false
    if (exceptRef !== openDuration) openDuration.value = false

    // Phí giải đấu
    if (exceptRef !== isFeeAmountInputOpen) isFeeAmountInputOpen.value = false
}

// Hàm xử lý click bên ngoài để đóng dropdown (trừ modal điểm)
const handleClickOutside = (event) => {
    closeOtherDropdowns(null);
}

// Toggles (Đã được cập nhật để sử dụng closeOtherDropdowns)
const toggleOpenDate = () => {
    const currentState = openDate.value
    closeOtherDropdowns(openDate)
    openDate.value = !currentState
}

// DUPR Toggles
const toggleOpenMinLevel = () => {
    const currentState = openMinLevel.value
    closeOtherDropdowns(openMinLevel)
    openMinLevel.value = !currentState
}

const toggleOpenMaxLevel = () => {
    const currentState = openMaxLevel.value
    closeOtherDropdowns(openMaxLevel)
    openMaxLevel.value = !currentState
}

const toggleDUPR = () => {
    duprEnabled.value = !duprEnabled.value
}

const toggleVNDUPR = () => {
    vnduprEnabled.value = !vnduprEnabled.value
}

const toggleCreatorJoin = () => {
    creatorJoin.value = !creatorJoin.value
}

// Player Limit Toggles

// Registration Toggles
const toggleOpenRegistrationOpenAt = () => {
    const currentState = openRegistrationOpenAt.value
    closeOtherDropdowns(openRegistrationOpenAt)
    openRegistrationOpenAt.value = !currentState
}

const toggleOpenEarlyDeadline = () => {
    const currentState = openEarlyDeadline.value
    closeOtherDropdowns(openEarlyDeadline)
    openEarlyDeadline.value = !currentState
}

const toggleOpenClosedDeadline = () => {
    const currentState = openClosedDeadline.value
    closeOtherDropdowns(openClosedDeadline)
    openClosedDeadline.value = !currentState
}

const toggleOpenDuration = () => {
    const currentState = openDuration.value
    closeOtherDropdowns(openDuration)
    openDuration.value = !currentState
}

// Fee Toggles/Handlers
const toggleFeeAmountInput = () => {
    const currentState = isFeeAmountInputOpen.value
    closeOtherDropdowns(isFeeAmountInputOpen)
    isFeeAmountInputOpen.value = !currentState
}

// =================================================================================
// Select Handlers (Giữ nguyên và bổ sung)
// =================================================================================
const decreaseTeam = () => {
    if (teamCount.value > 1) {
        teamCount.value--
    }
}
const increaseTeam = () => {
    teamCount.value++
}
const decreasePlayerPerTeam = () => {
    if (playerPerTeam.value > 1) {
        playerPerTeam.value--
    }
}
const increasePlayerPerTeam = () => {
    playerPerTeam.value++
}
const selectMinLevel = (level) => {
    minLevel.value = level
    openMinLevel.value = false
}

const selectMaxLevel = (level) => {
    maxLevel.value = level
    openMaxLevel.value = false
}

const selectDuration = (value) => {
    duration.value = value
    openDuration.value = false
}

const selectLocation = (location) => {
    selectedLocation.value = location
    locationKeyword.value = location.name
    isLocationDropdownOpen.value = false
}

// =================================================================================
// Computed and Submit
// =================================================================================

const handleSubmit = async () => {
    // Sử dụng hàm formatToISOString cục bộ đã được định nghĩa
    const startsAt = formatToISOString(date.value)
    const regOpenAt = formatToISOString(registrationOpenAt.value)
    const earlyDeadline = formatToISOString(earlyRegistrationDeadline.value)
    const closedDeadline = formatToISOString(registrationClosedAt.value)

    const getNumericLevel = (level) => {
        if (level === 'Không giới hạn') return null
        return parseFloat(level)
    }

    const data = {
        sport_id: selectedSportId.value,
        name: tournamentName.value,
        competition_location_id: selectedLocation.value ? selectedLocation.value?.id : null,
        start_date: startsAt,
        registration_open_at: regOpenAt,
        registration_closed_at: closedDeadline,
        early_registration_deadline: earlyDeadline,
        duration: durationInMinutes.value,
        enable_dupr: duprEnabled.value,
        enable_vndupr: vnduprEnabled.value,
        min_level: getNumericLevel(minLevel.value),
        max_level: getNumericLevel(maxLevel.value),
        participants: "team",
        max_team: teamCount.value,
        player_per_team: playerPerTeam.value,
        is_private: isPrivate.value,
        creator_join: creatorJoin.value,
        description: tournamentNote.value || null,
        club_id: selectedClub.value?.id || null,
        has_fee: hasFee.value,
        has_financial_management: hasFinancialManagement.value,
        fee_amount: hasFee.value ? feeAmount.value : null,
        auto_split_fee: autoSplitFee.value,
        fee_description: feeDescription.value || null,
        qr_code_url: qrCodeImage.value || null,
    }

    if (qrCodeFile.value) {
        const formData = new FormData()
        Object.entries(data).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                formData.append(key, value)
            }
        })
        formData.append('qr_code_url', qrCodeFile.value)
        if (posterFile.value) {
            formData.append('poster', posterFile.value)
        }
        // Replace data with formData for submission
        const finalData = formData
        if(isEditMode.value) {
            await updateTournament(tournamentId, finalData)
        } else {
            await createTournament(finalData)
        }
        return
    }

    if (posterFile.value) {
        const formData = new FormData()
        Object.entries(data).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                formData.append(key, value)
            }
        })
        formData.append('poster', posterFile.value)
        if(isEditMode.value) {
            await updateTournament(tournamentId, formData)
        } else {
            await createTournament(formData)
        }
        return
    }

    if(isEditMode.value) {
        await updateTournament(tournamentId, data)
        return
    } else {
        await createTournament(data)
    }
}

const updateTournament = async (id, data) => {
    try {
        await TournamentService.updateTournament(id, data)
        toast.success('Chỉnh sửa giải đấu thành công!')
        setTimeout(() => {
            router.push({ name: 'tournament-detail', params: { id: id } })
        }, 1000)
    } catch (error) {
        console.error('Error updating tournament:', error)
        toast.error('Chỉnh sửa giải đấu thất bại. Vui lòng kiểm tra lại thông tin.')
    }
}

const createTournament = async (data) => {
    try {
        const res = await TournamentService.storeTournament(data)
        toast.success('Tạo giải đấu thành công!')
        resetFormState()
        if(res && res.id) {
            setTimeout(() => {
                router.push({ name: 'tournament-detail', params: { id: res.id } })
            }, 1000)
        }
    } catch (error) {
        console.error('Error creating tournament:', error)
        toast.error('Tạo giải đấu thất bại. Vui lòng kiểm tra lại thông tin.')
    }
}

const fetchSports = async () => {
    try {
        const res = await SportService.getAllSports()
        sports.value = res
        if (res.length > 0) {
            selectedSportId.value = res[0].id
        }
    } catch (error) {
        console.error('Error fetching sports:', error)
    }
}

const fetchCompetitionLocations = async (keyword) => {
    if (!keyword || keyword.length < 2) {
        competitionLocations.value = []
        isLocationDropdownOpen.value = false
        return
    }

    closeOtherDropdowns(isLocationDropdownOpen)

    try {
        const res = await CompetitionLocationService.getAllCompetitionLocations(keyword)

        if (Array.isArray(res.data.competition_locations)) {
            competitionLocations.value = res.data.competition_locations
            isLocationDropdownOpen.value = competitionLocations.value.length > 0
        } else {
            competitionLocations.value = []
            isLocationDropdownOpen.value = false
        }
    } catch (error) {
        console.error('Error fetching competition locations:', error)
        competitionLocations.value = []
        isLocationDropdownOpen.value = false
    }
}

const searchClubs = async (keyword) => {
    if (!keyword || keyword.length < 2) {
        clubResults.value = []
        isClubDropdownOpen.value = false
        return
    }
    closeOtherDropdowns(isClubDropdownOpen)
    try {
        const res = await axiosInstance.get('/clubs/search', { params: { keyword } })
        clubResults.value = res.data?.data || res.data || []
        isClubDropdownOpen.value = clubResults.value.length > 0
    } catch (error) {
        console.error('Error searching clubs:', error)
        clubResults.value = []
        isClubDropdownOpen.value = false
    }
}

const selectClub = (club) => {
    selectedClub.value = club
    clubKeyword.value = club.name || ''
    isClubDropdownOpen.value = false
}

const clearClub = () => {
    selectedClub.value = null
    clubKeyword.value = ''
    clubResults.value = []
    isClubDropdownOpen.value = false
}

const onQrFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    if (file.size > 5 * 1024 * 1024) {
        toast.error('QR tối đa 5MB')
        return
    }
    qrCodeFile.value = file
    qrCodePreview.value = URL.createObjectURL(file)
}

const removeQrCode = () => {
    qrCodeFile.value = null
    qrCodePreview.value = null
    qrCodeImage.value = null
}

const onPosterFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    if (file.size > 5 * 1024 * 1024) {
        toast.error('Ảnh bìa tối đa 5MB')
        return
    }
    posterFile.value = file
    posterPreview.value = URL.createObjectURL(file)
}

const removePoster = () => {
    posterFile.value = null
    posterPreview.value = null
}
const prefillForm = (data) => {
    if (!data) return;

    // Thông tin cơ bản
    selectedSportId.value = data.sport_id || null;
    tournamentName.value = data.name || '';
    tournamentNote.value = data.description || '';

    // Địa điểm - nếu có competition_location
    if (data.competition_location) {
        selectedLocation.value = data.competition_location;
        locationKeyword.value = data.competition_location.name || '';
    }

    // Thời gian dự kiến bắt đầu
    if (data.start_date) {
        date.value = new Date(data.start_date);
    }

    // Thời gian đăng ký
    if (data.registration_open_at) {
        registrationOpenAt.value = new Date(data.registration_open_at);
    }
    if (data.early_registration_deadline) {
        earlyRegistrationDeadline.value = new Date(data.early_registration_deadline);
    }
    if (data.registration_closed_at) {
        registrationClosedAt.value = new Date(data.registration_closed_at);
    }

    // Thời lượng
    if (data.duration) {
        duration.value = data.duration;
    }

    // DUPR
    duprEnabled.value = data.enable_dupr ?? true;
    vnduprEnabled.value = data.enable_vndupr ?? true;
    
    // Min/Max Level
    if (data.min_level !== null && data.min_level !== undefined) {
        minLevel.value = data.min_level.toString();
    } else {
        minLevel.value = 'Không giới hạn';
    }
    
    if (data.max_level !== null && data.max_level !== undefined) {
        maxLevel.value = data.max_level.toString();
    } else {
        maxLevel.value = 'Không giới hạn';
    }

    // Người tham gia
    teamCount.value = data.max_team || 1;
    playerPerTeam.value = data.player_per_team || 2;

    // Phí giải đấu
    hasFee.value = !!data.has_fee;
    hasFinancialManagement.value = !!data.has_financial_management;
    feeAmount.value = Number(data.fee_amount) || 100000;

    // Quyền riêng tư
    isPrivate.value = !!data.is_private ?? false;

    // Creator join
    creatorJoin.value = !!data.creator_join ?? false;

    // Tài chính giải đấu
    autoSplitFee.value = !!data.auto_split_fee ?? false;
    feeDescription.value = data.fee_description || '';
    qrCodeImage.value = data.qr_code_url || null;
    qrCodePreview.value = data.qr_code_url ? (data.qr_code_url.startsWith('http') ? data.qr_code_url : `/storage/${data.qr_code_url}`) : null;

    // Poster
    posterFile.value = null;
    posterPreview.value = data.poster ? (data.poster.startsWith('http') ? data.poster : `/storage/${data.poster}`) : null;

    // Club
    if (data.club_id && data.club) {
        selectedClub.value = data.club;
        clubKeyword.value = data.club.name || '';
    } else if (data.club_id) {
        selectedClub.value = { id: data.club_id };
    }
};

const detailTournament = async (id) => {
    try {
        const data = await TournamentService.getTournamentById(id);
        prefillForm(data);
    } catch (error) {
        console.error('Error fetching tournament details:', error);
        throw error;
    }
};

// =================================================================================
// Template helpers
// =================================================================================

const fetchTemplates = async () => {
    isLoadingTemplates.value = true
    try {
        const res = await TournamentService.getTournamentTemplates()
        templates.value = res?.data?.templates || res?.templates || []
    } catch (error) {
        console.error('Error fetching tournament templates:', error)
        const errMessage = error?.response?.data?.message || 'Không tải được danh sách mẫu giải đấu.'
        toast.error(errMessage)
    } finally {
        isLoadingTemplates.value = false
    }
}

const openTemplateModal = async () => {
    isTemplateModalOpen.value = true
    if (!templates.value.length) {
        await fetchTemplates()
    }
}

const closeTemplateModal = () => {
    isTemplateModalOpen.value = false
}

const applyTemplate = (template) => {
    const s = template?.settings || {}

    // Thông tin cơ bản
    selectedSportId.value = s.sport_id || selectedSportId.value
    tournamentName.value = s.name || tournamentName.value
    tournamentNote.value = s.description || tournamentNote.value

    // Địa điểm
    if (s.competition_location_id) {
        selectedLocation.value = {
            id: s.competition_location_id,
            name: s.competition_location_name || '',
        }
        locationKeyword.value = s.competition_location_name || ''
    } else {
        selectedLocation.value = null
        locationKeyword.value = ''
    }

    // Thời gian dự kiến bắt đầu
    if (s.start_date) {
        date.value = new Date(s.start_date)
    } else {
        date.value = null
    }

    // Thời gian đăng ký
    if (s.registration_open_at) {
        registrationOpenAt.value = new Date(s.registration_open_at)
    } else {
        registrationOpenAt.value = null
    }
    if (s.early_registration_deadline) {
        earlyRegistrationDeadline.value = new Date(s.early_registration_deadline)
    } else {
        earlyRegistrationDeadline.value = null
    }
    if (s.registration_closed_at) {
        registrationClosedAt.value = new Date(s.registration_closed_at)
    } else {
        registrationClosedAt.value = null
    }

    // Thời lượng
    if (s.duration !== undefined && s.duration !== null) {
        duration.value = s.duration
    }

    // DUPR
    duprEnabled.value = s.enable_dupr ?? true
    vnduprEnabled.value = s.enable_vndupr ?? true

    // Min/Max Level
    if (s.min_level !== null && s.min_level !== undefined) {
        minLevel.value = s.min_level.toString()
    } else {
        minLevel.value = 'Không giới hạn'
    }
    if (s.max_level !== null && s.max_level !== undefined) {
        maxLevel.value = s.max_level.toString()
    } else {
        maxLevel.value = 'Không giới hạn'
    }

    // Người tham gia
    teamCount.value = s.max_team || 1
    playerPerTeam.value = s.player_per_team || 2

    // Phí giải đấu
    hasFee.value = !!s.has_fee
    hasFinancialManagement.value = !!s.has_financial_management
    feeAmount.value = Number(s.fee_amount) || 100000

    // Quyền riêng tư
    isPrivate.value = !!s.is_private ?? false

    // Creator join
    creatorJoin.value = !!s.creator_join ?? false

    // Tài chính giải đấu
    autoSplitFee.value = !!s.auto_split_fee ?? false
    feeDescription.value = s.fee_description || ''
    qrCodeImage.value = s.qr_code_url || null
    qrCodePreview.value = s.qr_code_url ? (s.qr_code_url.startsWith('http') ? s.qr_code_url : `/storage/${s.qr_code_url}`) : null

    // Club
    if (s.club_id && s.club) {
        selectedClub.value = s.club
        clubKeyword.value = s.club.name || ''
    } else if (s.club_id) {
        selectedClub.value = { id: s.club_id }
    }

    isTemplateModalOpen.value = false
    toast.success('Đã áp dụng mẫu giải đấu')
}

const buildTemplateSettings = () => {
    const getNumericLevel = (level) => {
        if (level === 'Không giới hạn') return null
        return parseFloat(level)
    }

    return {
        sport_id: selectedSportId.value,
        name: tournamentName.value,
        description: tournamentNote.value || null,
        competition_location_id: selectedLocation.value?.id || null,
        competition_location_name: selectedLocation.value?.name || null,
        start_date: date.value ? date.value.toISOString() : null,
        registration_open_at: registrationOpenAt.value ? registrationOpenAt.value.toISOString() : null,
        early_registration_deadline: earlyRegistrationDeadline.value ? earlyRegistrationDeadline.value.toISOString() : null,
        registration_closed_at: registrationClosedAt.value ? registrationClosedAt.value.toISOString() : null,
        duration: durationInMinutes.value,
        enable_dupr: duprEnabled.value,
        enable_vndupr: vnduprEnabled.value,
        min_level: getNumericLevel(minLevel.value),
        max_level: getNumericLevel(maxLevel.value),
        max_team: teamCount.value,
        player_per_team: playerPerTeam.value,
        is_private: isPrivate.value,
        creator_join: creatorJoin.value,
        club_id: selectedClub.value?.id || null,
        has_fee: hasFee.value,
        has_financial_management: hasFinancialManagement.value,
        fee_amount: hasFee.value ? feeAmount.value : null,
        auto_split_fee: autoSplitFee.value,
        fee_description: feeDescription.value || null,
        qr_code_url: qrCodeImage.value || null,
    }
}

const handleSaveTemplate = async () => {
    if (isSubmitting.value) return
    isSubmitting.value = true
    try {
        const settings = buildTemplateSettings()
        const payload = {
            name: tournamentName.value || 'Mẫu giải đấu Picki',
            settings,
        }
        const res = await TournamentService.saveTournamentTemplate(payload)
        const message = res?.message || 'Đã lưu cài đặt này làm mẫu'
        toast.success(message)
    } catch (error) {
        console.error('Error saving tournament template:', error)
        const errMessage = error?.response?.data?.message || 'Lưu mẫu thất bại. Vui lòng thử lại.'
        toast.error(errMessage)
    } finally {
        isSubmitting.value = false
    }
}

onMounted(async () => {
    await fetchSports()
    if (isEditMode.value) {
        await detailTournament(tournamentId)
    }
    // Thêm listener cho sự kiện click toàn cục
    document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
    // Xóa listener khi component bị hủy
    document.removeEventListener('click', handleClickOutside)
})

</script>

<style scoped>
.filter-invert-white {
    filter: invert(1) grayscale(100%) brightness(200%) contrast(150%);
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.1s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>