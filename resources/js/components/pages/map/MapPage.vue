<template>
    <div class="bg-gray-50">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div
                    class="lg:col-span-4 h-[86vh] bg-white shadow-lg rounded-md overflow-hidden flex flex-col border border-gray-100">
                    <div class="p-4">
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <MagnifyingGlassIcon
                                    class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                <SearchInput v-model="searchValue" :placeholder="searchPlaceholder" />
                            </div>
                            <button @click="isFilterModalOpen = true"
                                class="p-2 h-9 border border-gray-300 rounded hover:bg-gray-50 flex items-center justify-center flex-shrink-0">
                                <FunnelIcon class="w-5 h-5 text-gray-600" />
                            </button>
                        </div>
                    </div>
                    <div class="px-4 py-3 border-gray-100 space-y-2">
                        <!-- Tab chips -->
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id" :class="[
                                'flex items-center justify-center gap-1 w-full py-2 rounded border text-sm font-medium transition-all',
                                activeTab === tab.id
                                    ? 'border-[#D72D36] text-gray-800 bg-white'
                                    : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50'
                            ]">
                                <span :class="[
                                    'w-4 h-4 rounded-full border flex items-center justify-center',
                                    activeTab === tab.id
                                        ? 'border-[#D72D36] border-2'
                                        : 'border-gray-400'
                                ]">
                                    <span v-if="activeTab === tab.id" class="w-2 h-2 bg-[#D72D36] rounded-full"></span>
                                </span>
                                {{ tab.label }}
                            </button>
                        </div>

                        <!-- Timeline chips (only for tabs that support them) -->
                        <div v-if="subTabOptions.length > 1" class="flex gap-2 overflow-x-auto pb-0.5">
                            <button
                                v-for="opt in subTabOptions"
                                :key="opt.value"
                                @click="selectSubTab(opt.value)"
                                :class="[
                                    'flex-shrink-0 px-3 py-1 rounded-full text-xs font-medium border transition-all whitespace-nowrap',
                                    subTab === opt.value
                                        ? 'bg-[#D72D36] text-white border-[#D72D36]'
                                        : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'
                                ]"
                            >
                                {{ opt.label }}
                                <span v-if="opt.badge" :class="[
                                    'ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold',
                                    subTab === opt.value ? 'bg-white/20 text-white' : 'bg-red-50 text-[#D72D36]'
                                ]">
                                    {{ opt.badge }}
                                </span>
                            </button>
                        </div>

                        <!-- Club dropdown for same_club sub-tab -->
                        <div v-if="activeTab === 'user' && subTab === 'same_club'" class="mt-2">
                            <select
                                v-model="selectedClubIdForSameClub"
                                @change="onSameClubChange"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:border-[#D72D36]"
                            >
                                <option :value="null" disabled>-- Chọn CLB --</option>
                                <option v-for="club in getUser.clubs" :key="club.id" :value="club.id">
                                    {{ club.name }}
                                </option>
                            </select>
                            <p v-if="!getUser.clubs?.length" class="text-xs text-gray-400 mt-1">
                                Chưa có CLB nào. Tham gia CLB để sử dụng tính năng này.
                            </p>
                        </div>

                    </div>

                    <div class="px-4 pt-3 pb-2">
                        <p class="text-[#D72D36] font-semibold text-sm">{{ searchResultText }}</p>
                    </div>

                    <div class="flex-1 overflow-y-auto px-4 py-1" @scroll="handleScroll">
                        <div class="space-y-3">
                            <template v-if="activeTab === 'court'">
                                <CourtListItem v-for="court in displayedListData" :key="court.id" :court="court"
                                    :selected="selectedCourt?.id" :defaultImage="defaultImage" :toHourMinute="toHourMinute"
                                    @select="focusItemAuto" />
                            </template>
                            <template v-else-if="activeTab === 'mini-tournament' || activeTab === 'tournament'">
                                <MatchListItem v-for="(match, index) in displayedListData" :key="match.id ?? index"
                                    :match="match" :selected="selectedMatches?.value" @select="focusItemAuto" :defaultImage="defaultImage"/>
                            </template>
                            <template v-else-if="activeTab === 'user'">
                                <UserListItem v-for="user in displayedListData" :key="user.id" :user="user"
                                    :selected="selectedUser?.id" :defaultImage="defaultImage" :maleIcon="maleIcon"
                                    :femaleIcon="femaleIcon" :getUserRating="getUserRating"
                                    :getVisibilityText="getVisibilityText" @select="focusItemAuto" @toggle-follow="handleToggleFollow" />
                            </template>
                            <template v-else-if="activeTab === 'club'">
                                <ClubListItem v-for="club in displayedListData" :key="club.id" :club="club"
                                    :selected="selectedClubItem?.id" :defaultImage="defaultImage"
                                    @select="focusItemAuto" />
                            </template>

                            <div v-if="(activeTab === 'mini-tournament' || activeTab === 'tournament') && isLoadingMoreMatches" class="text-center py-4 text-sm text-gray-500">
                                <div class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-4 w-4 text-[#4392E0]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Đang tải thêm...</span>
                                </div>
                            </div>
                            <div v-else-if="activeTab !== 'mini-tournament' && activeTab !== 'tournament' && visibleItems < listData.length" class="text-center py-2 text-sm text-gray-500">
                                Đang tải thêm...
                            </div>
                            <div v-else-if="(activeTab === 'mini-tournament' || activeTab === 'tournament') &&
                                !isLoadingMoreMatches &&
                                ((activeMatchTab === 'mini' && miniMatchPage >= miniMatchLastPage) ||
                                 (activeMatchTab === 'tournament' && tournamentPage >= tournamentLastPage)) &&
                                listData.length > 0" class="text-center py-4 text-sm text-gray-500">
                                Đã hiển thị tất cả
                            </div>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-8 h-[86vh] bg-white shadow-lg rounded-md overflow-hidden p-5 relative">
                    <Transition enter-active-class="transition-opacity duration-200"
                        leave-active-class="transition-opacity duration-200" enter-from-class="opacity-0"
                        enter-to-class="opacity-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
                        <div v-if="isLoadingMap"
                            class="absolute top-6 left-1/2 transform -translate-x-1/2 z-[1000] pointer-events-none">
                            <div
                                class="bg-white px-4 py-2 rounded-full shadow-lg border border-gray-200 flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-[#4392E0]" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                <span class="text-sm font-medium text-gray-700">Đang tải dữ liệu...</span>
                            </div>
                        </div>
                    </Transition>

                    <div id="map" class="w-full h-full"></div>
                </div>
            </div>
        </div>

        <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0"
            enter-to-class="opacity-100" leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="isFilterModalOpen" @click.self="closeFilterModal"
                class="fixed inset-0 z-[9999] bg-gray-900 bg-opacity-40 backdrop-brightness-90 backdrop-blur-[1px]"
                aria-modal="true" role="dialog">
            </div>
        </Transition>

        <template v-if="activeTab === 'court'">
            <Transition enter-active-class="transition ease-out duration-300" enter-from-class="translate-x-full"
                enter-to-class="translate-x-0" leave-active-class="transition ease-in duration-200"
                leave-from-class="translate-x-0" leave-to-class="translate-x-full">
                <div v-if="isFilterModalOpen"
                    class="fixed inset-y-0 right-4 z-[10000] w-full max-w-sm h-[95vh] mt-6 bg-white shadow-xl rounded-md flex flex-col">

                    <!-- ===== HEADER (KHÔNG SCROLL) ===== -->
                    <div
                        class="px-4 pt-4 pb-3 flex justify-between items-center border-b bg-white rounded-tl-md rounded-tr-md">
                        <h3 class="text-2xl font-semibold text-gray-900">
                            Trình lọc sân bóng
                        </h3>
                        <button @click="closeFilterModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-full hover:bg-gray-100">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <div class="px-4 py-4 border-b bg-white">
                            <h3 class="text-xl text-gray-900 mb-4">
                                Bộ môn thể thao
                            </h3>
                            <Swiper :slides-per-view="'auto'" :space-between="8" :freeMode="true"
                                :mousewheel="{ forceToAxis: true }" :modules="modules" class="mt-2 !pb-2">
                                <SwiperSlide v-for="sport in sports" :key="sport.id" class="!w-auto">
                                    <div @click="selectedSportId = sport.id" :class="[
                                        'px-6 py-2 rounded-full text-sm font-semibold cursor-pointer transition select-none whitespace-nowrap flex items-center gap-2',
                                        selectedSportId === sport.id
                                            ? 'bg-[#D72D36] text-white border border-[#D72D36]'
                                            : 'border border-[#BBBFCC] bg-white text-gray-700 hover:border-gray-400'
                                    ]">
                                        <img v-if="sport.icon" :src="sport.icon" class="w-4 h-4"
                                            :class="{ 'filter brightness-0 invert': selectedSportId === sport.id }"
                                            draggable="false" />
                                        {{ sport.name }}
                                    </div>
                                </SwiperSlide>
                            </Swiper>
                        </div>

                        <div class="p-4 space-y-6">

                            <div class="flex justify-between items-center">
                                <p class="font-medium text-gray-900 text-xl">
                                    Hiển thị sân bóng tôi theo dõi
                                </p>
                                <button @click="isShowMyFollow = !isShowMyFollow"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="isShowMyFollow ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        :class="isShowMyFollow ? 'translate-x-6' : 'translate-x-1'" />
                                </button>
                            </div>

                            <!-- Xung quanh -->
                            <div class="flex justify-between items-center">
                                <p class="font-medium text-gray-900 text-xl">Xung quanh bạn</p>
                                <div class="relative">
                                    <button @click="isRadiusDropdownOpen = !isRadiusDropdownOpen"
                                        class="text-[#207AD5] flex items-center gap-1 cursor-pointer font-semibold">
                                        <p>{{ selectedRadiusLabel }}</p>
                                        <ChevronRightIcon class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-90': isRadiusDropdownOpen }" />
                                    </button>
                                    <Transition enter-active-class="transition ease-out duration-100"
                                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75"
                                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                                        <div v-if="isRadiusDropdownOpen"
                                            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                            <div class="py-1">
                                                <button v-for="option in radiusOptions" :key="option.value"
                                                    @click="selectRadius(option)"
                                                    :disabled="selectedRadiusValue === option.value" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedRadiusValue === option.value
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    {{ option.label }}
                                                </button>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>

                            <!-- Khu vực -->
                            <div class="flex justify-between items-center">
                                <p class="font-medium text-gray-900 text-xl">Khu vực</p>
                                <div class="relative">
                                    <button @click="isLocationDropdownOpen = !isLocationDropdownOpen"
                                        class="text-[#207AD5] flex items-center gap-1 cursor-pointer font-semibold">
                                        <p>{{ selectedLocationLabel }}</p>
                                        <ChevronRightIcon class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-90': isLocationDropdownOpen }" />
                                    </button>
                                    <Transition enter-active-class="transition ease-out duration-100"
                                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75"
                                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                                        <div v-if="isLocationDropdownOpen"
                                            class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                            <div class="p-2 border-b">
                                                <div class="relative">
                                                    <MagnifyingGlassIcon
                                                        class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                                                    <input v-model="locationSearchQuery" type="text"
                                                        placeholder="Tìm kiếm địa điểm..."
                                                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36] focus:border-transparent" />
                                                </div>
                                            </div>
                                            <div class="max-h-60 overflow-y-auto py-1">
                                                <button @click="selectLocation(null)"
                                                    :disabled="selectedLocationValue === null" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedLocationValue === null
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    Chọn địa điểm
                                                </button>
                                                <button v-for="location in filteredLocations" :key="location.id"
                                                    @click="selectLocation(location)"
                                                    :disabled="selectedLocationValue === location.id" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedLocationValue === location.id
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    {{ location.name }}
                                                </button>
                                                <div v-if="filteredLocations.length === 0"
                                                    class="px-4 py-3 text-sm text-gray-500 text-center">
                                                    Không tìm thấy địa điểm
                                                </div>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>

                            <!-- Số sân -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Số sân</p>

                                <template v-if="courtCounts?.length">
                                    <div class="grid grid-cols-3 gap-4">
                                        <label v-for="n in courtCounts" :key="n"
                                            class="flex items-center gap-3 cursor-pointer relative"
                                            @click="toggleCourtCount(n)">
                                            <input type="checkbox" :checked="isCourtCountSelected(n)" class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                           checked:bg-[#D72D36] checked:border-[#D72D36]" @click.prevent />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px] opacity-0
                           peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ n }}+</span>
                                        </label>
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="text-gray-400 italic text-sm flex justify-center">
                                        Tính năng đang phát triển
                                    </div>
                                </template>
                            </div>
                            <!-- Loại sân -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Loại sân</p>

                                <template v-if="yardTypes?.length">
                                    <div class="grid grid-cols-2 gap-4">
                                        <label v-for="yardType in yardTypes" :key="yardType.id"
                                            class="flex items-center gap-3 cursor-pointer relative"
                                            @click="toggleCourtType(yardType.id)">
                                            <input type="checkbox" :checked="isCourtTypeSelected(yardType.id)" class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                           checked:bg-[#D72D36] checked:border-[#D72D36]" @click.prevent />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                           opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ yardType.name }}</span>
                                        </label>
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="text-gray-400 italic text-sm flex justify-center">
                                        Tính năng đang phát triển
                                    </div>
                                </template>
                            </div>

                            <!-- Tiện ích -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Tiện ích đi kèm</p>
                                <template v-if="facilities?.length">
                                    <div class="space-y-4">
                                        <label v-for="facility in facilities" :key="facility.id"
                                            class="flex items-center gap-3 cursor-pointer relative"
                                            @click="toggleFacility(facility.id)">
                                            <input type="checkbox" :checked="isFacilitySelected(facility.id)" class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                           checked:bg-[#D72D36] checked:border-[#D72D36]" @click.prevent />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                           opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ facility.name }}</span>
                                        </label>
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="text-gray-400 italic text-sm flex justify-center">
                                        Tính năng đang phát triển
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- ===== FOOTER (KHÔNG SCROLL) ===== -->
                    <div class="p-4 border-t bg-white flex justify-between gap-3 rounded-bl-md rounded-br-md">
                        <div class="flex items-center gap-2 cursor-pointer" @click="resetFilter">
                            <p>Làm mới</p>
                            <ArrowPathIcon class="w-5 h-5 text-[#4392E0]" :class="{ 'animate-spin': spinning }" />
                        </div>
                        <button @click="applyFilter"
                            class="px-8 py-2 text-sm font-medium text-white bg-[#D72D36] rounded-full hover:bg-[#c22830]">
                            Lọc
                        </button>
                    </div>
                </div>
            </Transition>
        </template>
        <template v-else-if="activeTab === 'mini-tournament'">
            <Transition enter-active-class="transition ease-out duration-300" enter-from-class="translate-x-full"
                enter-to-class="translate-x-0" leave-active-class="transition ease-in duration-200"
                leave-from-class="translate-x-0" leave-to-class="translate-x-full">
                <div v-if="isFilterModalOpen"
                    class="fixed inset-y-0 right-4 z-[10000] w-full max-w-sm h-[95vh] mt-6 bg-white shadow-xl rounded-md flex flex-col">
                    <!-- ===== HEADER ===== -->
                    <div class="px-4 pt-4 pb-3 flex justify-between items-center border-b bg-white rounded-tl-md rounded-tr-md">
                        <h3 class="text-2xl font-semibold text-gray-900">
                            Trình lọc trận đấu
                        </h3>
                        <button @click="closeFilterModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-full hover:bg-gray-100">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto">
                        <!-- Sport selector -->
                        <div class="px-4 py-4 border-b bg-white">
                            <h3 class="text-xl text-gray-900 mb-4">
                                Bộ môn thể thao
                            </h3>
                            <Swiper :slides-per-view="'auto'" :space-between="8" :freeMode="true"
                                :mousewheel="{ forceToAxis: true }" :modules="modules" class="mt-2 !pb-2">
                                <SwiperSlide v-for="sport in sports" :key="sport.id" class="!w-auto">
                                    <div @click="selectedSportId = sport.id" :class="[
                                        'px-6 py-2 rounded-full text-sm font-semibold cursor-pointer transition select-none whitespace-nowrap flex items-center gap-2',
                                        selectedSportId === sport.id
                                            ? 'bg-[#D72D36] text-white border border-[#D72D36]'
                                            : 'border border-[#BBBFCC] bg-white text-gray-700 hover:border-gray-400'
                                    ]">
                                        <img v-if="sport.icon" :src="sport.icon" class="w-4 h-4"
                                            :class="{ 'filter brightness-0 invert': selectedSportId === sport.id }"
                                            draggable="false" />
                                        {{ sport.name }}
                                    </div>
                                </SwiperSlide>
                            </Swiper>
                        </div>

                        <div class="p-4 space-y-6">
                            <!-- Xung quanh -->
                            <div class="flex justify-between items-center">
                                <p class="font-medium text-gray-900 text-xl">Xung quanh bạn</p>
                                <div class="relative">
                                    <button @click="isRadiusDropdownOpen = !isRadiusDropdownOpen"
                                        class="text-[#207AD5] flex items-center gap-1 cursor-pointer font-semibold">
                                        <p>{{ selectedRadiusLabel }}</p>
                                        <ChevronRightIcon class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-90': isRadiusDropdownOpen }" />
                                    </button>
                                    <Transition enter-active-class="transition ease-out duration-100"
                                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75"
                                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                                        <div v-if="isRadiusDropdownOpen"
                                            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                            <div class="py-1">
                                                <button v-for="option in radiusOptions" :key="option.value"
                                                    @click="selectRadius(option)"
                                                    :disabled="selectedRadiusValue === option.value" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedRadiusValue === option.value
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    {{ option.label }}
                                                </button>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>

                            <!-- Khu vực -->
                            <div class="flex justify-between items-center">
                                <p class="font-medium text-gray-900 text-xl">Khu vực</p>
                                <div class="relative">
                                    <button @click="isLocationDropdownOpen = !isLocationDropdownOpen"
                                        class="text-[#207AD5] flex items-center gap-1 cursor-pointer font-semibold">
                                        <p>{{ selectedLocationLabel }}</p>
                                        <ChevronRightIcon class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-90': isLocationDropdownOpen }" />
                                    </button>
                                    <Transition enter-active-class="transition ease-out duration-100"
                                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75"
                                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                                        <div v-if="isLocationDropdownOpen"
                                            class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                            <div class="p-2 border-b">
                                                <div class="relative">
                                                    <MagnifyingGlassIcon
                                                        class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                                                    <input v-model="locationSearchQuery" type="text"
                                                        placeholder="Tìm kiếm địa điểm..."
                                                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36] focus:border-transparent" />
                                                </div>
                                            </div>
                                            <div class="max-h-60 overflow-y-auto py-1">
                                                <button @click="selectLocation(null)"
                                                    :disabled="selectedLocationValue === null" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedLocationValue === null
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    Chọn địa điểm
                                                </button>
                                                <button v-for="location in filteredLocations" :key="location.id"
                                                    @click="selectLocation(location)"
                                                    :disabled="selectedLocationValue === location.id" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedLocationValue === location.id
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    {{ location.name }}
                                                </button>
                                                <div v-if="filteredLocations.length === 0"
                                                    class="px-4 py-3 text-sm text-gray-500 text-center">
                                                    Không tìm thấy địa điểm
                                                </div>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>

                            <!-- Trình độ -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Trình độ</p>
                                <template v-if="ratingOptions.length">
                                    <div class="grid grid-cols-2 gap-4">
                                        <label v-for="item in ratingOptions" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="selectedRating" :value="item.value"
                                                class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                                checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                                opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ item.label }}</span>
                                        </label>
                                    </div>
                                </template>
                            </div>

                            <!-- Thời gian chơi trong ngày -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Thời gian</p>
                                <template v-if="timePlay.length">
                                    <div class="grid grid-cols-1 gap-4">
                                        <label v-for="item in timePlay" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="selectedTimePlay" :value="item.value"
                                                class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                                checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                                opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ item.label }}</span>
                                        </label>
                                    </div>
                                </template>
                            </div>

                            <!-- Tình trạng -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Tình trạng</p>
                                <template v-if="slotStatusOptions.length">
                                    <div class="grid grid-cols-2 gap-4">
                                        <label v-for="item in slotStatusOptions" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="selectedSlotStatus" :value="item.value"
                                                class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                                checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                                opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ item.label }}</span>
                                        </label>
                                    </div>
                                </template>
                            </div>

                            <!-- Phí -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Phí</p>
                                <template v-if="feeOptions.length">
                                    <div class="grid grid-cols-2 gap-4">
                                        <label v-for="item in feeOptions" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="selectedFee" :value="item.value"
                                                class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                                checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                                opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ item.label }}</span>
                                        </label>
                                    </div>
                                </template>
                            </div>

                            <!-- Loại kèo -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Loại kèo</p>
                                <div class="grid grid-cols-3 gap-4">
                                    <label v-for="item in playModeOptions" :key="item.value"
                                        class="flex items-center gap-3 cursor-pointer relative select-none">
                                        <input type="checkbox" v-model="selectedPlayMode" :value="item.value"
                                            class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                            checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                        <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                            opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                        <span>{{ item.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Giới tính -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Giới tính</p>
                                <div class="grid grid-cols-3 gap-4">
                                    <label v-for="item in genderMatchOptions" :key="item.value"
                                        class="flex items-center gap-3 cursor-pointer relative select-none">
                                        <input type="checkbox" v-model="selectedGenderMatch" :value="item.value"
                                            class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                            checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                        <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                            opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                        <span>{{ item.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Trình độ (min/max) -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Trình độ</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm text-gray-500 mb-1">Từ</label>
                                        <select v-model="selectedMinLevel"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#D72D36]">
                                            <option :value="null">Không giới hạn</option>
                                            <option v-for="opt in levelOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-500 mb-1">Đến</label>
                                        <select v-model="selectedMaxLevel"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#D72D36]">
                                            <option :value="null">Không giới hạn</option>
                                            <option v-for="opt in levelOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- CLB -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">CLB</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <label v-for="item in clubTypeOptions" :key="item.value"
                                        class="flex items-center gap-3 cursor-pointer relative select-none">
                                        <input type="checkbox" v-model="selectedClubType" :value="item.value"
                                            class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                            checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                        <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                            opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                        <span>{{ item.label }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== FOOTER ===== -->
                    <div class="p-4 border-t bg-white flex justify-between gap-3 rounded-bl-md rounded-br-md">
                        <div class="flex items-center gap-2 cursor-pointer" @click="resetFilter">
                            <p>Làm mới</p>
                            <ArrowPathIcon class="w-5 h-5 text-[#4392E0]" :class="{ 'animate-spin': spinning }" />
                        </div>
                        <button @click="applyFilter"
                            class="px-8 py-2 text-sm font-medium text-white bg-[#D72D36] rounded-full hover:bg-[#c22830]">
                            Lọc
                        </button>
                    </div>
                </div>
            </Transition>
        </template>
        <template v-else-if="activeTab === 'user'">
            <Transition enter-active-class="transition ease-out duration-300" enter-from-class="translate-x-full"
                enter-to-class="translate-x-0" leave-active-class="transition ease-in duration-200"
                leave-from-class="translate-x-0" leave-to-class="translate-x-full">
                <div v-if="isFilterModalOpen"
                    class="fixed inset-y-0 right-4 z-[10000] w-full max-w-sm h-[95vh] mt-6 bg-white shadow-xl rounded-md flex flex-col">

                    <!-- ===== HEADER (KHÔNG SCROLL) ===== -->
                    <div
                        class="px-4 pt-4 pb-3 flex justify-between items-center border-b bg-white rounded-tl-md rounded-tr-md">
                        <h3 class="text-2xl font-semibold text-gray-900">
                            Trình lọc người chơi
                        </h3>
                        <button @click="closeFilterModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-full hover:bg-gray-100">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <div class="px-4 py-4 border-b bg-white">
                            <h3 class="text-xl text-gray-900 mb-4">
                                Bộ môn thể thao
                            </h3>
                            <Swiper :slides-per-view="'auto'" :space-between="8" :freeMode="true"
                                :mousewheel="{ forceToAxis: true }" :modules="modules" class="mt-2 !pb-2">
                                <SwiperSlide v-for="sport in sports" :key="sport.id" class="!w-auto">
                                    <div @click="selectedSportId = sport.id" :class="[
                                        'px-6 py-2 rounded-full text-sm font-semibold cursor-pointer transition select-none whitespace-nowrap flex items-center gap-2',
                                        selectedSportId === sport.id
                                            ? 'bg-[#D72D36] text-white border border-[#D72D36]'
                                            : 'border border-[#BBBFCC] bg-white text-gray-700 hover:border-gray-400'
                                    ]">
                                        <img v-if="sport.icon" :src="sport.icon" class="w-4 h-4"
                                            :class="{ 'filter brightness-0 invert': selectedSportId === sport.id }"
                                            draggable="false" />
                                        {{ sport.name }}
                                    </div>
                                </SwiperSlide>
                            </Swiper>
                        </div>

                        <div class="p-4 space-y-6">
                            <!-- Xung quanh -->
                            <div class="flex justify-between items-center">
                                <p class="font-medium text-gray-900 text-xl">Xung quanh bạn</p>
                                <div class="relative">
                                    <button @click="isRadiusDropdownOpen = !isRadiusDropdownOpen"
                                        class="text-[#207AD5] flex items-center gap-1 cursor-pointer font-semibold">
                                        <p>{{ selectedRadiusLabel }}</p>
                                        <ChevronRightIcon class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-90': isRadiusDropdownOpen }" />
                                    </button>
                                    <Transition enter-active-class="transition ease-out duration-100"
                                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75"
                                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                                        <div v-if="isRadiusDropdownOpen"
                                            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                            <div class="py-1">
                                                <button v-for="option in radiusOptions" :key="option.value"
                                                    @click="selectRadius(option)"
                                                    :disabled="selectedRadiusValue === option.value" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedRadiusValue === option.value
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    {{ option.label }}
                                                </button>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>

                            <!-- Khu vực -->
                            <div class="flex justify-between items-center">
                                <p class="font-medium text-gray-900 text-xl">Khu vực</p>
                                <div class="relative">
                                    <button @click="isLocationDropdownOpen = !isLocationDropdownOpen"
                                        class="text-[#207AD5] flex items-center gap-1 cursor-pointer font-semibold">
                                        <p>{{ selectedLocationLabel }}</p>
                                        <ChevronRightIcon class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-90': isLocationDropdownOpen }" />
                                    </button>
                                    <Transition enter-active-class="transition ease-out duration-100"
                                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75"
                                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                                        <div v-if="isLocationDropdownOpen"
                                            class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                            <div class="p-2 border-b">
                                                <div class="relative">
                                                    <MagnifyingGlassIcon
                                                        class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                                                    <input v-model="locationSearchQuery" type="text"
                                                        placeholder="Tìm kiếm địa điểm..."
                                                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36] focus:border-transparent" />
                                                </div>
                                            </div>
                                            <div class="max-h-60 overflow-y-auto py-1">
                                                <button @click="selectLocation(null)"
                                                    :disabled="selectedLocationValue === null" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedLocationValue === null
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    Chọn địa điểm
                                                </button>
                                                <button v-for="location in filteredLocations" :key="location.id"
                                                    @click="selectLocation(location)"
                                                    :disabled="selectedLocationValue === location.id" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedLocationValue === location.id
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    {{ location.name }}
                                                </button>
                                                <div v-if="filteredLocations.length === 0"
                                                    class="px-4 py-3 text-sm text-gray-500 text-center">
                                                    Không tìm thấy địa điểm
                                                </div>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>

                            <!-- Gồm các giải thi đấu -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Gồm cả các giải thi đấu</p>

                                <template v-if="matchesType?.length">
                                    <div class="grid grid-cols-2 gap-4">
                                        <label v-for="n in matchesType" :key="n"
                                            class="flex items-center gap-3 cursor-pointer relative"
                                            @click="toggleCourtCount(n)">
                                            <input type="checkbox" :checked="isCourtCountSelected(n)" class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                           checked:bg-[#D72D36] checked:border-[#D72D36]" @click.prevent />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px] opacity-0
                           peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ n }}+</span>
                                        </label>
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="text-gray-400 italic text-sm flex justify-center">
                                        Tính năng đang phát triển
                                    </div>
                                </template>
                            </div>
                            <div class="border-t pt-4 space-y-2">
                                <div class="flex justify-between items-center">
                                    <p class="font-medium text-gray-900 text-xl">
                                        Người chơi yêu thích
                                    </p>
                                    <button @click="isShowFavoritePlayer = !isShowFavoritePlayer"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                        :class="isShowFavoritePlayer ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                            :class="isShowFavoritePlayer ? 'translate-x-6' : 'translate-x-1'" />
                                    </button>
                                </div>
                                <div class="flex justify-between items-center">
                                    <p class="font-medium text-gray-900 text-xl">
                                        Có kết nối với bạn
                                    </p>
                                    <button @click="isConnected = !isConnected"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                        :class="isConnected ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                            :class="isConnected ? 'translate-x-6' : 'translate-x-1'" />
                                    </button>
                                </div>
                                <div class="flex justify-between items-center">
                                    <p class="font-medium text-gray-900 text-xl">Giới tính</p>
                                    <div class="relative">
                                        <button @click="isGenderDropdownOpen = !isGenderDropdownOpen"
                                            class="text-[#207AD5] flex items-center gap-1 cursor-pointer font-semibold">
                                            <p>{{ selectedGenderLabel }}</p>
                                            <ChevronRightIcon class="w-4 h-4 transition-transform"
                                                :class="{ 'rotate-90': isGenderDropdownOpen }" />
                                        </button>
                                        <Transition enter-active-class="transition ease-out duration-100"
                                            enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                                            leave-active-class="transition ease-in duration-75"
                                            leave-from-class="opacity-100 scale-100"
                                            leave-to-class="opacity-0 scale-95">
                                            <div v-if="isGenderDropdownOpen"
                                                class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                                <div class="py-1">
                                                    <button v-for="option in genderOptions" :key="option.value"
                                                        @click="selectGender(option)"
                                                        :disabled="selectedGenderValue === option.value" :class="[
                                                            'w-full text-left px-4 py-2 text-sm',
                                                            selectedGenderValue === option.value
                                                                ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                                : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                        ]">
                                                        {{ option.label }}
                                                    </button>
                                                </div>
                                            </div>
                                        </Transition>
                                    </div>
                                </div>
                            </div>
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Thời gian chơi trong ngày</p>

                                <template v-if="timePlay?.length">
                                    <div class="grid grid-cols-1 gap-4">
                                        <label v-for="item in timePlay" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="selectedTimePlay" :value="item.value" class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
               checked:bg-[#D72D36] checked:border-[#D72D36]" />

                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
               opacity-0 peer-checked:opacity-100 pointer-events-none" />

                                            <span>{{ item.label }}</span>
                                        </label>

                                    </div>
                                </template>

                                <template v-else>
                                    <div class="text-gray-400 italic text-sm flex justify-center">
                                        Tính năng đang phát triển
                                    </div>
                                </template>
                            </div>
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Trình độ</p>

                                <template v-if="rating?.length">
                                    <div class="grid grid-cols-2 gap-4">
                                        <label v-for="item in rating" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="selectedRating" :value="item.value" class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
               checked:bg-[#D72D36] checked:border-[#D72D36]" />

                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
               opacity-0 peer-checked:opacity-100 pointer-events-none" />

                                            <span>{{ item.label }}</span>
                                        </label>

                                    </div>
                                </template>

                                <template v-else>
                                    <div class="text-gray-400 italic text-sm flex justify-center">
                                        Tính năng đang phát triển
                                    </div>
                                </template>
                            </div>
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Mức độ hoạt động</p>

                                <template v-if="onlineRecently?.length">
                                    <div class="grid grid-cols-2 gap-4">
                                        <label v-for="item in onlineRecently" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="isOnlineRecently" :value="item.value" class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
               checked:bg-[#D72D36] checked:border-[#D72D36]" />

                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
               opacity-0 peer-checked:opacity-100 pointer-events-none" />

                                            <span>{{ item.label }}</span>
                                        </label>
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="text-gray-400 italic text-sm flex justify-center">
                                        Tính năng đang phát triển
                                    </div>
                                </template>
                                <p class="my-4 text-gray-900">Số trận đã chơi gần đây</p>
                                <template v-if="quantityMatchesHasPlayRecently?.length">
                                    <div class="grid grid-cols-1 gap-4">
                                        <label v-for="item in quantityMatchesHasPlayRecently" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="isQuantityMatcheshasPlayRecently"
                                                :value="item.value" class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
               checked:bg-[#D72D36] checked:border-[#D72D36]" />

                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
               opacity-0 peer-checked:opacity-100 pointer-events-none" />

                                            <span>{{ item.label }}</span>
                                        </label>

                                    </div>
                                </template>

                                <template v-else>
                                    <div class="text-gray-400 italic text-sm flex justify-center">
                                        Tính năng đang phát triển
                                    </div>
                                </template>
                            </div>
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Câu lạc bộ chung</p>

                                <template v-if="getUser.clubs?.length">
                                    <div class="grid grid-cols-1 gap-4">
                                        <label v-for="item in getUser.clubs" :key="item.id"
                                            class="flex items-center justify-between gap-3 cursor-pointer relative select-none">
                                            <div class="flex gap-4">
                                                <img :src="item.logo_url || defaultImage" alt=""
                                                    class="rounded-full w-8 h-8">
                                                <span>{{ item.name }}</span>
                                            </div>
                                            <input type="checkbox" v-model="selectedClub" :value="item.id" class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
               checked:bg-[#D72D36] checked:border-[#D72D36]" />

                                            <CheckIcon class="w-4 h-4 text-white absolute right-[2px]
               opacity-0 peer-checked:opacity-100 pointer-events-none" />

                                        </label>

                                    </div>
                                </template>

                                <template v-else>
                                    <div class="text-gray-400 italic text-sm flex justify-center">
                                        Chưa có CLB nào
                                    </div>
                                </template>
                            </div>
                            <div class="border-t pt-4 space-y-2">
                                <div class="flex justify-between items-center">
                                    <p class="font-medium text-gray-900 text-xl">
                                        Đã xác thực profile
                                    </p>
                                    <button @click="is_verify_profile = !is_verify_profile"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                        :class="is_verify_profile ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                            :class="is_verify_profile ? 'translate-x-6' : 'translate-x-1'" />
                                    </button>
                                </div>
                                <div class="flex justify-between items-center">
                                    <p class="font-medium text-gray-900 text-xl">
                                        Thành tích, giải thưởng
                                    </p>
                                    <button @click="isHasAchievement = !isHasAchievement"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                        :class="isHasAchievement ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                            :class="isHasAchievement ? 'translate-x-6' : 'translate-x-1'" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== FOOTER (KHÔNG SCROLL) ===== -->
                    <div class="p-4 border-t bg-white flex justify-between gap-3 rounded-bl-md rounded-br-md">
                        <div class="flex items-center gap-2 cursor-pointer" @click="resetFilter">
                            <p>Làm mới</p>
                            <ArrowPathIcon class="w-5 h-5 text-[#4392E0]" :class="{ 'animate-spin': spinning }" />
                        </div>
                        <button @click="applyFilter"
                            class="px-8 py-2 text-sm font-medium text-white bg-[#D72D36] rounded-full hover:bg-[#c22830]">
                            Lọc
                        </button>
                    </div>
                </div>
            </Transition>
        </template>
        <template v-else-if="activeTab === 'tournament'">
            <Transition enter-active-class="transition ease-out duration-300" enter-from-class="translate-x-full"
                enter-to-class="translate-x-0" leave-active-class="transition ease-in duration-200"
                leave-from-class="translate-x-0" leave-to-class="translate-x-full">
                <div v-if="isFilterModalOpen"
                    class="fixed inset-y-0 right-4 z-[10000] w-full max-w-sm h-[95vh] mt-6 bg-white shadow-xl rounded-md flex flex-col">
                    <!-- ===== HEADER ===== -->
                    <div class="px-4 pt-4 pb-3 flex justify-between items-center border-b bg-white rounded-tl-md rounded-tr-md">
                        <h3 class="text-2xl font-semibold text-gray-900">
                            Trình lọc giải đấu
                        </h3>
                        <button @click="closeFilterModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-full hover:bg-gray-100">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto">
                        <!-- Sport selector -->
                        <div class="px-4 py-4 border-b bg-white">
                            <h3 class="text-xl text-gray-900 mb-4">
                                Bộ môn thể thao
                            </h3>
                            <Swiper :slides-per-view="'auto'" :space-between="8" :freeMode="true"
                                :mousewheel="{ forceToAxis: true }" :modules="modules" class="mt-2 !pb-2">
                                <SwiperSlide v-for="sport in sports" :key="sport.id" class="!w-auto">
                                    <div @click="selectedSportId = sport.id" :class="[
                                        'px-6 py-2 rounded-full text-sm font-semibold cursor-pointer transition select-none whitespace-nowrap flex items-center gap-2',
                                        selectedSportId === sport.id
                                            ? 'bg-[#D72D36] text-white border border-[#D72D36]'
                                            : 'border border-[#BBBFCC] bg-white text-gray-700 hover:border-gray-400'
                                    ]">
                                        <img v-if="sport.icon" :src="sport.icon" class="w-4 h-4"
                                            :class="{ 'filter brightness-0 invert': selectedSportId === sport.id }"
                                            draggable="false" />
                                        {{ sport.name }}
                                    </div>
                                </SwiperSlide>
                            </Swiper>
                        </div>

                        <div class="p-4 space-y-6">
                            <!-- Xung quanh -->
                            <div class="flex justify-between items-center">
                                <p class="font-medium text-gray-900 text-xl">Xung quanh bạn</p>
                                <div class="relative">
                                    <button @click="isRadiusDropdownOpen = !isRadiusDropdownOpen"
                                        class="text-[#207AD5] flex items-center gap-1 cursor-pointer font-semibold">
                                        <p>{{ selectedRadiusLabel }}</p>
                                        <ChevronRightIcon class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-90': isRadiusDropdownOpen }" />
                                    </button>
                                    <Transition enter-active-class="transition ease-out duration-100"
                                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75"
                                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                                        <div v-if="isRadiusDropdownOpen"
                                            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                            <div class="py-1">
                                                <button v-for="option in radiusOptions" :key="option.value"
                                                    @click="selectRadius(option)"
                                                    :disabled="selectedRadiusValue === option.value" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedRadiusValue === option.value
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    {{ option.label }}
                                                </button>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>

                            <!-- Khu vực -->
                            <div class="flex justify-between items-center">
                                <p class="font-medium text-gray-900 text-xl">Khu vực</p>
                                <div class="relative">
                                    <button @click="isLocationDropdownOpen = !isLocationDropdownOpen"
                                        class="text-[#207AD5] flex items-center gap-1 cursor-pointer font-semibold">
                                        <p>{{ selectedLocationLabel }}</p>
                                        <ChevronRightIcon class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-90': isLocationDropdownOpen }" />
                                    </button>
                                    <Transition enter-active-class="transition ease-out duration-100"
                                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75"
                                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                                        <div v-if="isLocationDropdownOpen"
                                            class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                            <div class="p-2 border-b">
                                                <div class="relative">
                                                    <MagnifyingGlassIcon
                                                        class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                                                    <input v-model="locationSearchQuery" type="text"
                                                        placeholder="Tìm kiếm địa điểm..."
                                                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36] focus:border-transparent" />
                                                </div>
                                            </div>
                                            <div class="max-h-60 overflow-y-auto py-1">
                                                <button @click="selectLocation(null)"
                                                    :disabled="selectedLocationValue === null" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedLocationValue === null
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    Chọn địa điểm
                                                </button>
                                                <button v-for="location in filteredLocations" :key="location.id"
                                                    @click="selectLocation(location)"
                                                    :disabled="selectedLocationValue === location.id" :class="[
                                                        'w-full text-left px-4 py-2 text-sm',
                                                        selectedLocationValue === location.id
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-700 hover:bg-gray-50 cursor-pointer'
                                                    ]">
                                                    {{ location.name }}
                                                </button>
                                                <div v-if="filteredLocations.length === 0"
                                                    class="px-4 py-3 text-sm text-gray-500 text-center">
                                                    Không tìm thấy địa điểm
                                                </div>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>

                            <!-- Trình độ -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Trình độ</p>
                                <template v-if="ratingOptions.length">
                                    <div class="grid grid-cols-2 gap-4">
                                        <label v-for="item in ratingOptions" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="selectedRating" :value="item.value"
                                                class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                                checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                                opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ item.label }}</span>
                                        </label>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="text-gray-400 italic text-sm flex justify-center">
                                        Tính năng đang phát triển
                                    </div>
                                </template>
                            </div>

                            <!-- Thời gian chơi trong ngày -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Thời gian</p>
                                <template v-if="timePlay.length">
                                    <div class="grid grid-cols-1 gap-4">
                                        <label v-for="item in timePlay" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="selectedTimePlay" :value="item.value"
                                                class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                                checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                                opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ item.label }}</span>
                                        </label>
                                    </div>
                                </template>
                            </div>

                            <!-- Tình trạng -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Tình trạng</p>
                                <template v-if="slotStatusOptions.length">
                                    <div class="grid grid-cols-2 gap-4">
                                        <label v-for="item in slotStatusOptions" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="selectedSlotStatus" :value="item.value"
                                                class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                                checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                                opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ item.label }}</span>
                                        </label>
                                    </div>
                                </template>
                            </div>

                            <!-- Phí -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Phí</p>
                                <template v-if="feeOptions.length">
                                    <div class="grid grid-cols-2 gap-4">
                                        <label v-for="item in feeOptions" :key="item.value"
                                            class="flex items-center gap-3 cursor-pointer relative select-none">
                                            <input type="checkbox" v-model="selectedFee" :value="item.value"
                                                class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                                checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                            <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                                opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                            <span>{{ item.label }}</span>
                                        </label>
                                    </div>
                                </template>
                            </div>

                            <!-- Loại giải (age group) -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Loại giải</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <label v-for="item in tournamentTypeOptions" :key="item.value"
                                        class="flex items-center gap-3 cursor-pointer relative select-none">
                                        <input type="checkbox" v-model="selectedTournamentType" :value="item.value"
                                            class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                            checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                        <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                            opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                        <span>{{ item.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Giới tính -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Giới tính</p>
                                <div class="grid grid-cols-3 gap-4">
                                    <label v-for="item in genderTourOptions" :key="item.value"
                                        class="flex items-center gap-3 cursor-pointer relative select-none">
                                        <input type="checkbox" v-model="selectedGenderTour" :value="item.value"
                                            class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                            checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                        <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                            opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                        <span>{{ item.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Trình độ (min/max) -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">Trình độ</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm text-gray-500 mb-1">Từ</label>
                                        <select v-model="selectedMinLevel"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#D72D36]">
                                            <option :value="null">Không giới hạn</option>
                                            <option v-for="opt in levelOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-500 mb-1">Đến</label>
                                        <select v-model="selectedMaxLevel"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#D72D36]">
                                            <option :value="null">Không giới hạn</option>
                                            <option v-for="opt in levelOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- CLB -->
                            <div class="border-t pt-4">
                                <p class="font-medium text-gray-900 mb-4 text-xl">CLB</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <label v-for="item in clubTypeOptions" :key="item.value"
                                        class="flex items-center gap-3 cursor-pointer relative select-none">
                                        <input type="checkbox" v-model="selectedClubType" :value="item.value"
                                            class="peer appearance-none w-5 h-5 rounded border-2 border-[#D72D36]
                                            checked:bg-[#D72D36] checked:border-[#D72D36]" />
                                        <CheckIcon class="w-4 h-4 text-white absolute left-[2px]
                                            opacity-0 peer-checked:opacity-100 pointer-events-none" />
                                        <span>{{ item.label }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== FOOTER ===== -->
                    <div class="p-4 border-t bg-white flex justify-between gap-3 rounded-bl-md rounded-br-md">
                        <div class="flex items-center gap-2 cursor-pointer" @click="resetFilter">
                            <p>Làm mới</p>
                            <ArrowPathIcon class="w-5 h-5 text-[#4392E0]" :class="{ 'animate-spin': spinning }" />
                        </div>
                        <button @click="applyFilter"
                            class="px-8 py-2 text-sm font-medium text-white bg-[#D72D36] rounded-full hover:bg-[#c22830]">
                            Lọc
                        </button>
                    </div>
                </div>
            </Transition>
        </template>
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { FunnelIcon, MagnifyingGlassIcon, XMarkIcon, ArrowPathIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { toast } from 'vue3-toastify';
import * as MapService from '@/service/map.js';
import * as SearchService from '@/service/search.js';
import { followUser, unFollowUser } from '@/service/follow.js';
import * as UserService from '@/service/auth.js';
import * as SportService from '@/service/sport.js';
import * as LocationService from '@/service/location.js';
import { useUserStore } from '@/store/auth.js';
import { useTimeFormat } from '@/composables/formatTime.js';
import { getVisibilityText } from "@/composables/formatVisibilityText";
import defaultImage from '@/assets/images/default-image.jpeg';
import maleIcon from '@/assets/images/male.svg';
import femaleIcon from '@/assets/images/female.svg';
import maleIconRaw from '@/assets/images/male.svg?raw';
import femaleIconRaw from '@/assets/images/female.svg?raw';
import { useMap } from '@/composables/useMap.js';
import { CheckIcon } from '@heroicons/vue/16/solid';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { FreeMode, Mousewheel } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/free-mode';
import SearchInput from '@/components/atoms/SearchInput.vue';
import CourtListItem from '@/components/molecules/CourtListItem.vue';
import MatchListItem from '@/components/molecules/MatchListItem.vue';
import UserListItem from '@/components/molecules/UserListItem.vue';
import ClubListItem from '@/components/molecules/ClubListItem.vue';

const router = useRouter();
const { toHourMinute } = useTimeFormat();
const { initMap, clearAllMarkers, addCourtMarkers, addUserMarkers, addMatchMarkers, addClubMarkers, focusItem } = useMap();
const userStore = useUserStore();
const { getUser } = storeToRefs(userStore);
const currentBounds = ref(null);
const courtsMap = ref(new Map());
const usersMap = ref(new Map());
const matchesMap = ref(new Map());
const clubsMap = ref(new Map());
const isInitialLoad = ref(true);
const isLoadingMap = ref(false);

// Tab state
const activeTab = ref('mini-tournament');
const activeMatchTab = ref('mini');
const subTab = ref('all');

// Sub-tab options per tab — static config matching backend SearchFilterConfig
const SUB_TAB_OPTIONS = {
    'mini-tournament': [
        { value: 'all', label: 'Tất cả', badge: null },
        { value: 'mine', label: 'Của tôi', badge: null },
        { value: 'today', label: 'Hôm nay', badge: 'Hôm nay' },
        { value: 'this_week', label: 'Tuần này', badge: null },
        { value: 'this_month', label: 'Tháng này', badge: null },
    ],
    tournament: [
        { value: 'all', label: 'Tất cả', badge: null },
        { value: 'mine', label: 'Của tôi', badge: null },
        { value: 'today', label: 'Hôm nay', badge: 'Hôm nay' },
        { value: 'this_week', label: 'Tuần này', badge: null },
        { value: 'this_month', label: 'Tháng này', badge: null },
    ],
    club: [
        { value: 'all', label: 'Tất cả', badge: null },
        { value: 'mine', label: 'Của tôi', badge: null },
        { value: 'joined', label: 'Đã tham gia', badge: null },
    ],
    user: [
        { value: 'all', label: 'Tất cả', badge: null },
        { value: 'friends', label: 'Bạn bè', badge: null },
        { value: 'same_club', label: 'Cùng CLB', badge: null },
    ],
    court: [
        { value: 'all', label: 'Tất cả', badge: null },
    ],
};

const subTabOptions = computed(() => SUB_TAB_OPTIONS[activeTab.value] || []);

const matchesMini = ref([]);
const matchesTournament = ref([]);
// Pagination state for matches
const miniMatchPage = ref(1);
const tournamentPage = ref(1);
const miniMatchPerPage = ref(15);
const tournamentPerPage = ref(15);
const miniMatchLastPage = ref(1);
const tournamentLastPage = ref(1);
const isLoadingMoreMatches = ref(false);

// Shared state
const sports = ref([]);
const selectedSportId = ref(null);
const isFilterModalOpen = ref(false);
const spinning = ref(false);
const searchKeyword = ref(''); // unified keyword for all tabs
const lat = ref(null);
const lng = ref(null);
const radiusKm = ref(null); // km value when nearby is selected

// Court-only state
const isShowMyFollow = ref(false);
const selectedCourtCounts = ref([]);
const selectedCourtTypes = ref([]);
const selectedFacilities = ref([]);
const facilities = ref([]);
const yardTypes = ref([]);
const courtCounts = []; // placeholder for court counts
const matchesType = [];
const modules = [FreeMode, Mousewheel];
const visibleItems = ref(20);
const itemsPerLoad = ref(10);

// Player-only state
const isShowFavoritePlayer = ref(false);
const isConnected = ref(false);
const is_verify_profile = ref(false);
const isHasAchievement = ref(false);
const selectedTimePlay = ref([]);
const selectedRating = ref([]);
const selectedClub = ref([]);
const isOnlineRecently = ref(false);
const quantityMatchesHasPlayRecently = ref([
    { label: 'Ít', value: 'low' },
    { label: 'Trung bình', value: 'medium' },
    { label: 'Nhiều', value: 'high' },
]);
const isQuantityMatcheshasPlayRecently = ref([]);
const selectedGenderValue = ref(null);
const selectedGenderLabel = ref('Tất cả');
const selectedGender = ref(null); // 'male'|'female'|'other'|null for API
const genderOptions = [
    { value: null, label: 'Tất cả' },
    { value: 1, label: 'Nam' },
    { value: 2, label: 'Nữ' },
    { value: 0, label: 'Khác' },
    { value: 3, label: 'Không tiết lộ' },
];
const isGenderDropdownOpen = ref(false);
const onlineRecently = [{ label: 'Online gần đây', value: false }];

// Club-only state
const joinedOnly = ref(false);
const sameClubId = ref(null);
const selectedClubIdForSameClub = ref(null);

// Tournament-only state
const selectedSlotStatus = ref([]); // ['con_trong', 'da_day']
const selectedFee = ref([]);         // ['free', 'paid']

const slotStatusOptions = [
    { label: 'Còn trống', value: 'con_trong' },
    { label: 'Đã đầy',    value: 'da_day' },
];
const feeOptions = [
    { label: 'Miễn phí', value: 'free' },
    { label: 'Có phí',    value: 'paid' },
];

// Match-only state
const selectedMatchType = ref([]); // ['single', 'double']
const matchTypeOptions = [
    { label: 'Đánh đơn', value: 'single' },
    { label: 'Đánh đôi', value: 'double' },
];

// Club type filter (match + tournament)
const selectedClubType = ref([]); // ['thuong', 'clb']
const clubTypeOptions = [
    { label: 'Kèo thường', value: 'thuong' },
    { label: 'Kèo CLB',    value: 'clb' },
];

// Play mode filter (mini-tournament only)
const selectedPlayMode = ref([]); // ['casual', 'competition', 'practice']
const playModeOptions = [
    { label: 'Kèo thường',  value: 'casual' },
    { label: 'Kèo thi đấu', value: 'competition' },
    { label: 'Kèo tập luyện', value: 'practice' },
];

// Gender filter (mini-tournament)
const selectedGenderMatch = ref([]); // ['male', 'female', 'mixed']
const genderMatchOptions = [
    { label: 'Nam',      value: 'male' },
    { label: 'Nữ',       value: 'female' },
    { label: 'Nam nữ',   value: 'mixed' },
];

// Gender filter (tournament)
const selectedGenderTour = ref([]); // ['male', 'female', 'mixed']
const genderTourOptions = [
    { label: 'Nam',      value: 'male' },
    { label: 'Nữ',       value: 'female' },
    { label: 'Nam nữ',   value: 'mixed' },
];

// Level range (mini-tournament + tournament)
const selectedMinLevel = ref(null); // float
const selectedMaxLevel = ref(null); // float

// Tournament type filter (tournament only)
const selectedTournamentType = ref([]); // ['all', 'youth', 'adult', 'senior']
const tournamentTypeOptions = [
    { label: 'Mọi lứa tuổi', value: 'all' },
    { label: 'Dưới 18',      value: 'youth' },
    { label: '18-55',         value: 'adult' },
    { label: 'Trên 55',       value: 'senior' },
];

// Shared filter state
const isRadiusDropdownOpen = ref(false);
const selectedRadiusValue = ref(null);
const selectedRadiusLabel = ref('Chọn');
const userLocation = ref(null);
const radiusOptions = [
    { value: null, label: 'Tất cả' },
    { value: 'nearby', label: 'Gần đây (20km)' },
];
const isLocationDropdownOpen = ref(false);
const selectedLocationValue = ref(null);
const selectedLocationLabel = ref('Chọn địa điểm');
const locations = ref([]);
const locationSearchQuery = ref('');

// Time-of-day options (for filter modal)
const timePlay = [
    { label: 'Sáng (Trước 11:00 AM)', value: 'morning' },
    { label: 'Chiều (Từ 11:00 AM - 4:00 PM)', value: 'afternoon' },
    { label: 'Tối (Sau 4:00 PM)', value: 'evening' },
];
// Rating options (for filter modal)
const ratingOptions = [
    { label: '2+', value: 2 },
    { label: '3+', value: 3 },
    { label: '4+', value: 4 },
    { label: '5+', value: 5 },
];

// Level options (for min/max level selects)
const levelOptions = [
    { label: '1.0', value: 1.0 },
    { label: '1.5', value: 1.5 },
    { label: '2.0', value: 2.0 },
    { label: '2.5', value: 2.5 },
    { label: '3.0', value: 3.0 },
    { label: '3.5', value: 3.5 },
    { label: '4.0', value: 4.0 },
    { label: '4.5', value: 4.5 },
    { label: '5.0', value: 5.0 },
];

// Selection tracking (for highlighting on map)
const selectedCourt = ref(null);
const selectedUser = ref(null);
const selectedMatches = ref(null);
const selectedClubItem = ref(null);

// Quantity counters
const quantityCourts = ref(0);
const quantityUsers = ref(0);
const quantityMatches = ref(0);
const quantityClubs = ref(0);

const tabs = [
    { id: 'mini-tournament', label: 'Kèo đấu' },
    { id: 'tournament', label: 'Giải đấu' },
    { id: 'club', label: 'Câu lạc bộ' },
    { id: 'user', label: 'Người chơi' },
    { id: 'court', label: 'Sân bãi' },
];

// Convert Map sang Array
const courts = computed(() => Array.from(courtsMap.value.values()));
const users = computed(() => Array.from(usersMap.value.values()));
const matches = computed(() => Array.from(matchesMap.value.values()));
const clubs = computed(() => Array.from(clubsMap.value.values()));

// Convert Map sang Array
const listData = computed(() => {
    if (activeTab.value === 'court') return courts.value;
    if (activeTab.value === 'mini-tournament') {
        if (activeMatchTab.value === 'mini') return matchesMini.value;
        if (activeMatchTab.value === 'tournament') return matchesTournament.value;
    }
    if (activeTab.value === 'tournament') return matchesTournament.value;
    if (activeTab.value === 'user') return users.value;
    if (activeTab.value === 'club') return clubs.value;
    return [];
});

const displayedListData = computed(() => {
    const data = listData.value;
    // For match/tournament tabs, show all loaded data (pagination handled by API)
    if (activeTab.value === 'mini-tournament' || activeTab.value === 'tournament') {
        return data;
    }
    // For other tabs, use visibleItems slicing
    return data.slice(0, visibleItems.value);
});

const searchResultText = computed(() => {
    const map = {
        court: `${quantityCourts.value ?? 0} Sân bãi được tìm thấy`,
        'mini-tournament': `${quantityMatches.value ?? 0} Kèo đấu được tìm thấy`,
        tournament: `${quantityMatches.value ?? 0} Giải đấu được tìm thấy`,
        user: `${quantityUsers.value ?? 0} Người chơi được tìm thấy`,
        club: `${quantityClubs.value ?? 0} Câu lạc bộ được tìm thếy`,
    }

    return map[activeTab.value] ?? '0 kết quả được tìm thấy'
})

// Scroll handler cho infinite loading
const handleScroll = async (event) => {
    const target = event.target;
    const scrollPercentage = (target.scrollTop + target.clientHeight) / target.scrollHeight;

    // For match/tournament tabs, load more from API when scrolling
    if ((activeTab.value === 'mini-tournament' || activeTab.value === 'tournament') && scrollPercentage > 0.8) {
        const hasMore = activeTab.value === 'mini-tournament' && activeMatchTab.value === 'mini'
            ? miniMatchPage.value < miniMatchLastPage.value
            : tournamentPage.value < tournamentLastPage.value;

        if (hasMore && !isLoadingMoreMatches.value) {
            await loadMoreMatches();
        }
    }

    // For other tabs, just show more items from existing data
    if (activeTab.value === 'court' || activeTab.value === 'user' || activeTab.value === 'club') {
        if (scrollPercentage > 0.8 && visibleItems.value < listData.value.length) {
            visibleItems.value = Math.min(
                visibleItems.value + itemsPerLoad.value,
                listData.value.length
            );
        }
    }
};

watch([activeTab, searchKeyword], () => {
    visibleItems.value = 20;
});

const mergeData = (existingMap, newDataArray, isFiltered = false) => {
    if (isFiltered) {
        existingMap.clear();
        newDataArray.forEach(item => {
            existingMap.set(item.id, item);
        });
    } else {
        newDataArray.forEach(item => {
            existingMap.set(item.id, item);
        });
    }
};

const hasActiveFilters = computed(() => {
    return !!(
        searchKeyword.value?.trim() ||
        selectedSportId.value ||
        subTab.value !== 'all' ||
        isShowMyFollow.value ||
        isShowFavoritePlayer.value ||
        isConnected.value ||
        selectedCourtCounts.value.length > 0 ||
        selectedCourtTypes.value.length > 0 ||
        selectedFacilities.value.length > 0 ||
        selectedRadiusValue.value !== null ||
        selectedLocationValue.value !== null ||
        is_verify_profile.value ||
        isHasAchievement.value ||
        selectedTimePlay.value.length > 0 ||
        selectedRating.value.length > 0 ||
        selectedClub.value.length > 0 ||
        selectedSlotStatus.value.length > 0 ||
        selectedFee.value.length > 0 ||
        selectedMatchType.value.length > 0 ||
        selectedPlayMode.value.length > 0 ||
        selectedGenderMatch.value.length > 0 ||
        selectedGenderTour.value.length > 0 ||
        selectedMinLevel.value != null ||
        selectedMaxLevel.value != null ||
        selectedTournamentType.value.length > 0 ||
        joinedOnly.value
    );
});

navigator.geolocation.getCurrentPosition(
  ({ coords }) => {
    lat.value = coords.latitude
    lng.value = coords.longitude
  }
)

/**
 * Unified search using search-v2 API.
 * Handles all tabs: match, tournament, club, user, court.
 *
 * @param {boolean} isLoadMore - true = append page, false = replace
 * @param {Object} bounds - map bounds { getSouth(), getNorth(), getWest(), getEast() }
 */
const doSearch = async (isLoadMore = false, bounds = null) => {
    const tab = activeTab.value;

    // Build base params for search-v2 API
    const params = {
        tab,
        sub_tab: subTab.value,
        keyword: searchKeyword.value?.trim() || undefined,
        sport_id: selectedSportId.value || undefined,
        location_id: selectedLocationValue.value || undefined,
        per_page: 200, // load enough for list view
    };

    // club_id for same_club sub-tab
    if (subTab.value === 'same_club' && selectedClubIdForSameClub.value) {
        params.club_id = selectedClubIdForSameClub.value;
    }

    // Pagination for match/tournament tabs
    if (tab === 'mini-tournament' || tab === 'tournament') {
        params.page = isLoadMore
            ? (tab === 'mini-tournament' ? miniMatchPage.value : tournamentPage.value)
            : 1;
    } else {
        // Other tabs: map mode, load all at once
        params.map_mode = true;
    }

    // Geo params: user location > bounds
    if (lat.value && lng.value) {
        params.lat = lat.value;
        params.lng = lng.value;
    } else if (bounds) {
        params.minLat = bounds.getSouth();
        params.maxLat = bounds.getNorth();
        params.minLng = bounds.getWest();
        params.maxLng = bounds.getEast();
    }

    // Nearby radius
    if (selectedRadiusValue.value === 'nearby') {
        params.radius = 20 * 1000; // 20km in meters
        if (lat.value && lng.value) {
            // already have lat/lng above
        } else if (userLocation.value) {
            params.lat = userLocation.value.lat;
            params.lng = userLocation.value.lng;
        }
    }

    // Build filters bundle
    const filters = SearchService.buildFilters(tab, {
        selectedRadiusValue: selectedRadiusValue.value,
        radiusKm: radiusKm.value,
        selectedRating: selectedRating.value,
        selectedTimePlay: selectedTimePlay.value,
        slotStatus: tab === 'mini-tournament' || tab === 'tournament' ? selectedSlotStatus.value : [],
        fee: tab === 'mini-tournament' || tab === 'tournament' ? selectedFee.value : null,
        matchType: tab === 'mini-tournament' ? selectedMatchType.value : null,
        clubType: tab === 'mini-tournament' || tab === 'tournament' ? selectedClubType.value : [],
        joinedOnly: joinedOnly.value,
        selectedGender: selectedGender.value,
        sameClubId: sameClubId.value,
        playMode: selectedPlayMode.value,
        selectedGenderMatch: selectedGenderMatch.value,
        selectedGenderTour: selectedGenderTour.value,
        selectedMinLevel: selectedMinLevel.value,
        selectedMaxLevel: selectedMaxLevel.value,
        tournamentType: tab === 'tournament' ? selectedTournamentType.value : null,
    });
    if (filters) params.filters = filters;

    // Clean undefined values
    Object.keys(params).forEach(key => {
        if (params[key] === undefined) delete params[key];
    });

    try {
        isLoadingMap.value = true;
        const res = await SearchService.search(params);

        // ---- MATCH / TOURNAMENT TAB ----
        if (tab === 'mini-tournament' || tab === 'tournament') {
            const apiTab = tab;
            const items = res.data?.data || [];

            if (!isLoadMore) {
                // Replace data
                if (apiTab === 'mini-tournament') {
                    matchesMini.value = items.map(m => ({ ...m, id: `mini_${m.id}`, original_id: m.id, type: 'mini' }));
                    matchesTournament.value = [];
                } else {
                    matchesTournament.value = items.map(t => ({ ...t, id: `tour_${t.id}`, original_id: t.id, type: 'tournament' }));
                    matchesMini.value = [];
                }
                matchesMap.value.clear();
            } else {
                // Append data
                if (apiTab === 'mini-tournament') {
                    const newItems = items.map(m => ({ ...m, id: `mini_${m.id}`, original_id: m.id, type: 'mini' }));
                    if (activeMatchTab.value === 'mini') {
                        matchesMini.value = [...matchesMini.value, ...newItems];
                    }
                } else {
                    const newItems = items.map(t => ({ ...t, id: `tour_${t.id}`, original_id: t.id, type: 'tournament' }));
                    if (activeMatchTab.value === 'tournament') {
                        matchesTournament.value = [...matchesTournament.value, ...newItems];
                    }
                }
            }

            // Update pagination meta
            const meta = res.data?.meta;
            if (meta) {
                if (apiTab === 'mini-tournament') {
                    miniMatchLastPage.value = meta.last_page || 1;
                    miniMatchPage.value = meta.current_page || 1;
                } else {
                    tournamentLastPage.value = meta.last_page || 1;
                    tournamentPage.value = meta.current_page || 1;
                }
            }

            const allMatches = [...matchesMini.value, ...matchesTournament.value];
            mergeData(matchesMap.value, allMatches, !isLoadMore);
            quantityMatches.value = matchesMap.value.size;

            // Update map markers
            clearAllMarkers();
            addMatchMarkers(matches.value, router, focusItemAuto, !isLoadMore, defaultImage);
        }

        // ---- CLUB TAB ----
        if (tab === 'club') {
            const clubsData = res.data?.data || [];
            mergeData(clubsMap.value, clubsData, !isLoadMore);
            quantityClubs.value = clubsMap.value.size;

            clearAllMarkers();
            addClubMarkers(clubs.value, router, focusItemAuto, !isLoadMore, defaultImage);
        }

        // ---- USER TAB ----
        if (tab === 'user') {
            const usersData = res.data?.data || [];
            mergeData(usersMap.value, usersData, !isLoadMore);
            quantityUsers.value = usersMap.value.size;

            clearAllMarkers();
            addUserMarkers(users.value, defaultImage, maleIconRaw, femaleIconRaw, getVisibilityText, getUserRating, router, focusItemAuto, !isLoadMore);
        }

        // ---- COURT TAB ----
        if (tab === 'court') {
            const courtsData = res.data?.data || [];
            mergeData(courtsMap.value, courtsData, !isLoadMore);
            quantityCourts.value = courtsMap.value.size;

            clearAllMarkers();
            addCourtMarkers(courts.value, toHourMinute, defaultImage, focusItemAuto, !isLoadMore);
        }

    } catch (error) {
        console.error('Search error:', error);
        toast.error(error.response?.data?.message || 'Lỗi khi tải dữ liệu');
    } finally {
        isLoadingMap.value = false;
    }
};

const getListMatches = async (bounds = null, isLoadMore = false) => {
    await doSearch(isLoadMore, bounds);
};

const loadMoreMatches = async () => {
    if (isLoadingMoreMatches.value) return;
    isLoadingMoreMatches.value = true;
    try {
        if (activeTab.value === 'mini-tournament' && activeMatchTab.value === 'mini') {
            miniMatchPage.value += 1;
        } else {
            tournamentPage.value += 1;
        }
        await doSearch(true, currentBounds.value);
    } finally {
        isLoadingMoreMatches.value = false;
    }
};

// Watch activeMatchTab to reload markers (only when on match tab)
watch(activeMatchTab, () => {
    if (activeTab.value !== 'mini-tournament') return;

    // Reset pagination when switching sub-tabs
    miniMatchPage.value = 1;
    tournamentPage.value = 1;

    matchesMap.value.clear();
    const dataToShow = activeMatchTab.value === 'mini' ? matchesMini.value : matchesTournament.value;
    mergeData(matchesMap.value, dataToShow, true);
    quantityMatches.value = matchesMap.value.size;
    clearAllMarkers();
    addMatchMarkers(matches.value, router, focusItemAuto, true, defaultImage);
});

const getListSports = async () => {
    try {
        const res = await SportService.getAllSports();
        sports.value = res || [];
    } catch (error) {
        console.error("Error fetching sports data:", error);
        toast.error(error.response?.data?.message || "Lỗi khi tải dữ liệu bộ môn thể thao");
    }
};

const getListLocation = async () => {
    try {
        const res = await LocationService.getAllLocations();
        locations.value = res || [];
    } catch (error) {
        console.error('Error fetching locations data:', error);
        toast.error(error.response?.data?.message || "Lỗi khi tải dữ liệu địa điểm");
    }
}

const loadTabContent = async (tab, bounds = null) => {
    if (bounds) {
        currentBounds.value = bounds;
    }

    if (hasActiveFilters.value) {
        clearAllMarkers();
    }

    const shouldUpdate = !isInitialLoad.value && bounds !== null && !hasActiveFilters.value;

    if (shouldUpdate) {
        isLoadingMap.value = true;
    }

    try {
        if (tab === 'court') {
            await doSearch(false, bounds);
        } else if (tab === 'mini-tournament' || tab === 'tournament') {
            await doSearch(false, bounds);
        } else if (tab === 'user') {
            await doSearch(false, bounds);
        } else if (tab === 'club') {
            await doSearch(false, bounds);
        }
    } finally {
        isLoadingMap.value = false;
    }

    if (isInitialLoad.value) {
        isInitialLoad.value = false;
    }
};

const refresh = async () => {
    if (spinning.value) return;
    spinning.value = true;

    // Reset match tab pagination
    miniMatchPage.value = 1;
    tournamentPage.value = 1;

    courtsMap.value.clear();
    usersMap.value.clear();
    matchesMap.value.clear();
    clubsMap.value.clear();

    clearAllMarkers();
    await loadTabContent(activeTab.value, currentBounds.value);

    setTimeout(() => {
        spinning.value = false;
    }, 700);
};

const closeFilterModal = () => {
    isFilterModalOpen.value = false;
};

const applyFilter = async () => {
    // Reset pagination
    miniMatchPage.value = 1;
    tournamentPage.value = 1;

    courtsMap.value.clear();
    usersMap.value.clear();
    matchesMap.value.clear();
    clubsMap.value.clear();

    clearAllMarkers();
    await loadTabContent(activeTab.value, currentBounds.value);
    isFilterModalOpen.value = false;
    toast.success('Lọc thành công');
};

const resetFilter = async () => {
    // Reset các trường chung
    selectedCourtCounts.value = [];
    selectedCourtTypes.value = [];
    selectedFacilities.value = [];
    selectedSportId.value = null;
    isShowMyFollow.value = false;
    isShowFavoritePlayer.value = false;
    searchKeyword.value = '';
    selectedRadiusValue.value = null;
    selectedRadiusLabel.value = 'Chọn';
    userLocation.value = null;
    selectedLocationValue.value = null;
    selectedLocationLabel.value = 'Chọn địa điểm';
    locationSearchQuery.value = '';
    subTab.value = 'all';

    // Reset các trường của tab Players
    selectedTimePlay.value = [];
    selectedRating.value = [];
    selectedClub.value = [];
    isOnlineRecently.value = false;
    isQuantityMatcheshasPlayRecently.value = [];
    isConnected.value = false;
    is_verify_profile.value = false;
    isHasAchievement.value = false;
    selectedGenderValue.value = null;
    selectedGenderLabel.value = 'Tất cả';
    selectedGender.value = null;

    // Reset club-only
    joinedOnly.value = false;
    sameClubId.value = null;

    // Reset tournament/match-only
    selectedSlotStatus.value = [];
    selectedFee.value = [];
    selectedMatchType.value = [];
    selectedClubType.value = [];
    selectedPlayMode.value = [];
    selectedGenderMatch.value = [];
    selectedGenderTour.value = [];
    selectedMinLevel.value = null;
    selectedMaxLevel.value = null;
    selectedTournamentType.value = [];

    // Reset pagination
    miniMatchPage.value = 1;
    tournamentPage.value = 1;

    courtsMap.value.clear();
    usersMap.value.clear();
    matchesMap.value.clear();
    clubsMap.value.clear();
    clearAllMarkers();

    await loadTabContent(activeTab.value, currentBounds.value);
    toast.success('Đã làm mới bộ lọc');
};

const toggleCourtCount = (count) => {
    const index = selectedCourtCounts.value.indexOf(count);
    if (index > -1) {
        selectedCourtCounts.value.splice(index, 1);
    } else {
        selectedCourtCounts.value.push(count);
    }
};

const toggleCourtType = (typeId) => {
    const index = selectedCourtTypes.value.indexOf(typeId);
    if (index > -1) {
        selectedCourtTypes.value.splice(index, 1);
    } else {
        selectedCourtTypes.value.push(typeId);
    }
};

const toggleFacility = (facilityId) => {
    const index = selectedFacilities.value.indexOf(facilityId);
    if (index > -1) {
        selectedFacilities.value.splice(index, 1);
    } else {
        selectedFacilities.value.push(facilityId);
    }
};

const isCourtCountSelected = (count) => {
    return selectedCourtCounts.value.includes(count);
};

const isCourtTypeSelected = (typeId) => {
    return selectedCourtTypes.value.includes(typeId);
};

const isFacilitySelected = (facilityId) => {
    return selectedFacilities.value.includes(facilityId);
};

const getUserLocation = () => {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Trình duyệt không hỗ trợ định vị'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (position) => resolve({ lat: position.coords.latitude, lng: position.coords.longitude }),
            (error) => reject(error)
        );
    });
};

const selectRadius = async (option) => {
    if (selectedRadiusValue.value === option.value) return;

    selectedRadiusValue.value = option.value;
    selectedRadiusLabel.value = option.label;
    isRadiusDropdownOpen.value = false;

    if (option.value === 'nearby') {
        radiusKm.value = 20;
        try {
            userLocation.value = await getUserLocation();
        } catch (error) {
            toast.error('Không thể lấy vị trí của bạn. Vui lòng cho phép truy cập vị trí.');
            selectedRadiusValue.value = null;
            selectedRadiusLabel.value = 'Chọn';
            radiusKm.value = null;
            return;
        }
    } else {
        userLocation.value = null;
        radiusKm.value = null;
    }

    isInitialLoad.value = true;
};

const selectGender = async (option) => {
    if (selectedGenderValue.value === option.value) return;
    selectedGenderValue.value = option.value;
    selectedGenderLabel.value = option.label;
    isGenderDropdownOpen.value = false;
    isInitialLoad.value = true;
}

const selectLocation = async (location) => {
    if (selectedLocationValue.value === (location?.id || null)) return;

    selectedLocationValue.value = location?.id || null;
    selectedLocationLabel.value = location?.name || 'Chọn địa điểm';
    isLocationDropdownOpen.value = false;
    locationSearchQuery.value = '';

    isInitialLoad.value = true;
};

const selectedMap = {
    court: selectedCourt,
    user: selectedUser,
    'mini-tournament': selectedMatches,
    club: selectedClubItem,
}

const focusItemAuto = (item) => {
    const selectedRef = selectedMap[activeTab.value]
    if (!selectedRef) return

    // Mini-tournament/tournament items have competition_location but we need their own id (which was transformed: "mini_X", "tour_X")
    const itemId = item.id
    selectedRef.value = itemId
    focusItem(itemId)
}

const getUserRating = (user) => {
    const score = user?.vndupr_score ?? user?.sports?.[0]?.scores?.vndupr_score ?? 0;
    return typeof score === 'number' ? score.toFixed(1) : "0";
};

const updateUserFollowState = (userId, payload = {}, targetUser = null) => {
    const current = usersMap.value.get(userId);
    if (!current) return null;

    const nextState = {
        ...current,
        is_follow: payload.is_follow ?? current.is_follow,
        is_friend: payload.is_friend ?? current.is_friend,
    };

    usersMap.value.set(userId, nextState);

    if (targetUser) {
        targetUser.is_follow = nextState.is_follow;
        targetUser.is_friend = nextState.is_friend;
    }

    return nextState;
}

const refreshUserMarkers = () => {
    if (activeTab.value !== 'user') return;

    clearAllMarkers();
    addUserMarkers(users.value, defaultImage, maleIconRaw, femaleIconRaw, getVisibilityText, getUserRating, router, focusItemAuto, true);
}

const handleToggleFollow = async (user) => {
    if (!user?.id) return;

    const wasFollowing = Boolean(user.is_follow);
    const previousIsFriend = user.is_friend;
    const optimisticState = {
        is_follow: !wasFollowing,
        is_friend: wasFollowing ? false : previousIsFriend,
    };

    updateUserFollowState(user.id, optimisticState, user);
    refreshUserMarkers();

    try {
        const payload = {
            followable_type: 'user',
            followable_id: user.id,
        };

        const response = wasFollowing
            ? await unFollowUser(payload)
            : await followUser(payload);

        const responseData = response?.data && typeof response.data === 'object'
            ? response.data
            : response;

        if (responseData && typeof responseData === 'object') {
            updateUserFollowState(user.id, {
                is_follow: responseData.is_follow ?? optimisticState.is_follow,
                is_friend: responseData.is_friend ?? optimisticState.is_friend,
            }, user);
            refreshUserMarkers();
        }

        toast.success(wasFollowing ? 'Hủy follow thành công' : 'Follow thành công');
    } catch (error) {
        updateUserFollowState(user.id, {
            is_follow: wasFollowing,
            is_friend: previousIsFriend,
        }, user);
        refreshUserMarkers();

        console.error('Toggle follow failed:', error);
        toast.error(error.response?.data?.message || 'Không thể cập nhật trạng thái follow');
    }
};

// Timeline helpers
const onSameClubChange = async () => {
    if (selectedClubIdForSameClub.value) {
        usersMap.value.clear();
        clearAllMarkers();
        await loadTabContent(activeTab.value, currentBounds.value);
    }
};

const selectSubTab = async (value) => {
    if (value !== 'same_club') {
        selectedClubIdForSameClub.value = null;
    }
    subTab.value = value;
    isInitialLoad.value = true;
    // Reset match pagination when changing time filter
    if (activeTab.value === 'mini-tournament' || activeTab.value === 'tournament') {
        miniMatchPage.value = 1;
        tournamentPage.value = 1;
    }
    courtsMap.value.clear();
    usersMap.value.clear();
    matchesMap.value.clear();
    clubsMap.value.clear();
    clearAllMarkers();
    await loadTabContent(activeTab.value, currentBounds.value);
};

const searchValue = computed({
    get() { return searchKeyword.value; },
    set(val) { searchKeyword.value = val; }
})

const searchPlaceholder = computed(() => {
    const map = {
        'mini-tournament': 'Tìm kèo đấu',
        tournament: 'Tìm giải đấu',
        club: 'Tìm câu lạc bộ',
        user: 'Tìm người chơi',
        court: 'Tìm sân bãi',
    };
    return map[activeTab.value] ?? 'Tìm kiếm';
})

const filteredLocations = computed(() => {
    if (!locationSearchQuery.value.trim()) {
        return locations.value;
    }
    const query = locationSearchQuery.value.toLowerCase().trim();
    return locations.value.filter(location =>
        location.name.toLowerCase().includes(query)
    );
});

watch(activeTab, (newTab) => {
    isInitialLoad.value = true;

    // Reset pagination
    miniMatchPage.value = 1;
    tournamentPage.value = 1;

    courtsMap.value.clear();
    usersMap.value.clear();
    matchesMap.value.clear();
    clubsMap.value.clear();

    clearAllMarkers();
    loadTabContent(newTab, currentBounds.value);
});

let searchDebounceTimer = null;
watch(searchKeyword, () => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);

    if (!searchKeyword.value?.trim()) {
        searchDebounceTimer = setTimeout(async () => {
            isInitialLoad.value = true;
            courtsMap.value.clear();
            usersMap.value.clear();
            matchesMap.value.clear();
            clubsMap.value.clear();
            clearAllMarkers();
            await loadTabContent(activeTab.value, currentBounds.value);
        }, 300);
        return;
    }

    searchDebounceTimer = setTimeout(async () => {
        isInitialLoad.value = true;
        courtsMap.value.clear();
        usersMap.value.clear();
        matchesMap.value.clear();
        clubsMap.value.clear();
        clearAllMarkers();
        await loadTabContent(activeTab.value, currentBounds.value);
    }, 800);
});

onUnmounted(() => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
});

const handleBoundsChange = (bounds) => {
    currentBounds.value = bounds;
    loadTabContent(activeTab.value, bounds);
};

onMounted(async () => {
    await getListSports();
    await getListLocation();
});

initMap(handleBoundsChange, handleBoundsChange);
</script>
<style>
#map {
    z-index: 0 !important;
}

.custom-cluster-icon {
    background: transparent !important;
}

.leaflet-marker-icon:hover {
    transform: scale(1.1);
    transition: transform 0.2s ease;
}

.leaflet-popup-content-wrapper {
    cursor: pointer !important;
}

@keyframes spin-once {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.animate-spin-once {
    animation: spin-once 0.7s ease-in-out forwards;
}
</style>
