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
                    <div class="px-4 py-3 border-gray-100">
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
                    </div>

                    <div class="px-4 pt-3 pb-2">
                        <p class="text-[#D72D36] font-semibold text-sm">{{ searchResultText }}</p>
                    </div>

                    <div class="flex-1 overflow-y-auto px-4 py-1" @scroll="handleScroll">
                        <div class="space-y-3">
                            <template v-if="activeTab === 'courts'">
                                <CourtListItem v-for="court in displayedListData" :key="court.id" :court="court"
                                    :selected="selectedCourt" :defaultImage="defaultImage" :toHourMinute="toHourMinute"
                                    @select="focusItemAuto" />
                            </template>
                            <template v-else-if="activeTab === 'match'">
                                <!-- Sub tabs -->
                                <div class="grid grid-cols-2 gap-2 mb-3">
                                    <button @click="activeMatchTab = 'mini'" :class="[
                                        'py-1.5 text-sm font-medium rounded transition-colors',
                                        activeMatchTab === 'mini' 
                                            ? 'bg-red-50 text-[#D72D36] border border-[#D72D36]'
                                            : 'text-gray-500 hover:bg-gray-50 border'
                                    ]">
                                        Kèo đấu
                                    </button>
                                    <button @click="activeMatchTab = 'tournament'" :class="[
                                        'py-1.5 text-sm font-medium rounded transition-colors',
                                        activeMatchTab === 'tournament'
                                            ? 'bg-red-50 text-[#D72D36] border border-[#D72D36]'
                                            : 'text-gray-500 hover:bg-gray-50 border'
                                    ]">
                                        Giải đấu
                                    </button>
                                </div>

                                <MatchListItem v-for="(match, index) in displayedListData" :key="match.id ?? index"
                                    :match="match" :selected="selectedMatches" @select="focusItemAuto" :defaultImage="defaultImage"/>
                            </template>
                            <template v-else-if="activeTab === 'players'">
                                <UserListItem v-for="user in displayedListData" :key="user.id" :user="user"
                                    :selected="selectedUser" :defaultImage="defaultImage" :maleIcon="maleIcon"
                                    :femaleIcon="femaleIcon" :getUserRating="getUserRating"
                                    :getVisibilityText="getVisibilityText" @select="focusItemAuto" />
                            </template>
                            <template v-else-if="activeTab === 'clubs'">
                                <ClubListItem v-for="club in displayedListData" :key="club.id" :club="club"
                                    :selected="selectedClubItem" :defaultImage="defaultImage"
                                    @select="focusItemAuto" />
                            </template>

                            <div v-if="activeTab === 'match' && isLoadingMoreMatches" class="text-center py-4 text-sm text-gray-500">
                                <div class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-4 w-4 text-[#4392E0]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Đang tải thêm...</span>
                                </div>
                            </div>
                            <div v-else-if="activeTab !== 'match' && visibleItems < listData.length" class="text-center py-2 text-sm text-gray-500">
                                Đang tải thêm...
                            </div>
                            <div v-else-if="activeTab === 'match' && !isLoadingMoreMatches && 
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

        <template v-if="activeTab === 'courts'">
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
        <template v-else-if="activeTab === 'match'">
            <Transition enter-active-class="transition ease-out duration-300" enter-from-class="translate-x-full"
                enter-to-class="translate-x-0" leave-active-class="transition ease-in duration-200"
                leave-from-class="translate-x-0" leave-to-class="translate-x-full">
                <div v-if="isFilterModalOpen"
                    class="fixed inset-y-0 right-4 z-[10000] w-full max-w-sm h-[95vh] mt-6 bg-white shadow-xl overflow-y-auto transform flex flex-col rounded-md">
                    <div class="px-4 pt-4 flex justify-between items-center sticky top-0 bg-white z-10">
                        <h3 class="text-2xl font-semibold text-gray-900">
                            Trình lọc trận đấu
                        </h3>
                        <button @click="closeFilterModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-full hover:bg-gray-100">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>
                    <div class="px-4 pb-4 border-b sticky top-0 bg-white z-10">
                        <h3 class="text-xl text-gray-900">
                            Bộ môn thể thao
                        </h3>
                        <div class="mt-4 flex gap-2 font-semibold">
                            <div
                                class="px-6 py-2 rounded-full bg-[#D72D36] border inline-block text-white text-sm cursor-pointer">
                                Bóng đá
                            </div>
                            <div
                                class="px-6 py-2 rounded-full border border-[#BBBFCC] bg-white inline-block text-sm cursor-pointer">
                                Tennis
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 p-4 space-y-6">
                        <div class="flex justify-between items-center">
                            <p class="font-medium text-gray-900">Hiển thị sân bóng tôi theo dõi</p>
                            <button @click="isShowMyFollow = !isShowMyFollow"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="isShowMyFollow ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="isShowMyFollow ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>
                        <div class="flex justify-between items-center">
                            <p class="font-medium text-gray-900">Xung quanh bạn</p>
                        </div>
                    </div>

                    <div class="p-4 border-t sticky bottom-0 bg-white flex justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <p>Làm mới</p>
                            <ArrowPathIcon class="w-5 h-5 text-[#4392E0] cursor-pointer"
                                :class="{ 'animate-spin-once': spinning }" @click="refresh" />
                        </div>
                        <button @click="applyFilter"
                            class="px-4 py-2 text-sm font-medium text-white bg-[#D72D36] rounded-md hover:bg-[#c22830] transition-colors">
                            Áp dụng Lọc
                        </button>
                    </div>
                </div>
            </Transition>
        </template>
        <template v-else-if="activeTab === 'players'">
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

                                <template v-if="myClub?.length">
                                    <div class="grid grid-cols-1 gap-4">
                                        <label v-for="item in myClub" :key="item.id"
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
                                        Bạn chưa tham gia câu lạc bộ nào
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
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { FunnelIcon, MagnifyingGlassIcon, XMarkIcon, ArrowPathIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { toast } from 'vue3-toastify';
import * as MapService from '@/service/map.js';
import * as UserService from '@/service/auth.js';
import * as SportService from '@/service/sport.js';
import * as LocationService from '@/service/location.js';
import * as ClubService from '@/service/club.js'
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
const currentBounds = ref(null);
const courtsMap = ref(new Map());
const usersMap = ref(new Map());
const matchesMap = ref(new Map());
const clubsMap = ref(new Map());
const isInitialLoad = ref(true);
const isLoadingMap = ref(false);
const activeTab = ref('courts');
const activeMatchTab = ref('mini'); // 'mini' (Kèo đấu) or 'tournament' (Giải đấu)
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
const isShowMyFollow = ref(false);
const isShowFavoritePlayer = ref(false);
const isConnected = ref(false);
const is_verify_profile = ref(false);
const isHasAchievement = ref(false);
const selectedCourt = ref(null);
const selectedUser = ref(null);
const selectedMatches = ref(null);
const selectedClubItem = ref(null);
const quantityCourts = ref(0);
const quantityUsers = ref(0);
const quantityMatches = ref(0);
const quantityClubs = ref(0);
const sports = ref([]);
const selectedSportId = ref(null);
const isFilterModalOpen = ref(false);
const spinning = ref(false);
const searchCourt = ref('');
const searchMatch = ref('');
const searchUser = ref('');
const searchClub = ref('');
const selectedCourtCounts = ref([]);
const selectedCourtTypes = ref([]);
const selectedFacilities = ref([]);
const facilities = ref([]);
const yardTypes = ref([]);
const courtCounts = []; // Sau cho hiện sân thì thêm vào đây
const matchesType = [];
const modules = [FreeMode, Mousewheel];
const visibleItems = ref(20);
const itemsPerLoad = ref(10);
const isRadiusDropdownOpen = ref(false);
const selectedRadiusValue = ref(null);
const selectedRadiusLabel = ref('Chọn');
const userLocation = ref(null);
const radiusOptions = [
    { value: null, label: 'Tất cả' },
    { value: 'nearby', label: 'Gần đây (20km)' }
];
const isLocationDropdownOpen = ref(false);
const selectedLocationValue = ref(null);
const selectedLocationLabel = ref('Chọn địa điểm');
const locations = ref([]);
const locationSearchQuery = ref('');
const isGenderDropdownOpen = ref(false);
const selectedGenderValue = ref(null);
const selectedGenderLabel = ref('Tất cả');
const selectedTimePlay = ref([]);
const selectedRating = ref([]);
const selectedClub = ref([]);
const isOnlineRecently = ref(false);
const quantityMatchesHasPlayRecently = [
    {
        label: 'Ít',
        value: 'low',
    },
    {
        label: 'Trung bình',
        value: 'medium',
    },
    {
        label: 'Nhiều',
        value: 'high',
    },
];
const isQuantityMatcheshasPlayRecently = ref([]);
const genderOptions = [
    { value: null, label: 'Tất cả' },
    { value: 1, label: 'Nam' },
    { value: 2, label: 'Nữ' },
    { value: 0, label: 'Khác' },
    { value: 3, label: 'Không tiết lộ' },
];

const timePlay = [
    {
        label: 'Sáng (Trước 11:00 AM)',
        value: 'morning',
    },
    {
        label: 'Chiều (Từ 11:00 AM - 4:00 PM)',
        value: 'afternoon',
    },
    {
        label: 'Tối (Sau 4:00 PM)',
        value: 'evening',
    },
];
const rating = [
    {
        label: '2+',
        value: 2,
    },
    {
        label: '3+',
        value: 3,
    },
    {
        label: '4+',
        value: 4,
    },
    {
        label: '5+',
        value: 5,
    },
];

const onlineRecently = [
    {
        label: 'Online gần đây',
        value: false
    }
]

const tabs = [
    { id: 'courts', label: 'Sân bóng' },
    { id: 'match', label: 'Trận đấu' },
    { id: 'players', label: 'Người chơi' },
    { id: 'clubs', label: 'Câu lạc bộ' }
];

const myClub = ref([]);
const lat = ref(null)
const lng = ref(null)

// Convert Map sang Array
const courts = computed(() => Array.from(courtsMap.value.values()));
const users = computed(() => Array.from(usersMap.value.values()));
const matches = computed(() => Array.from(matchesMap.value.values()));
const clubs = computed(() => Array.from(clubsMap.value.values()));

// Convert Map sang Array
const listData = computed(() => {
    if (activeTab.value === 'courts') return courts.value;
    if (activeTab.value === 'match') {
        if (activeMatchTab.value === 'mini') return matchesMini.value;
        if (activeMatchTab.value === 'tournament') return matchesTournament.value;
    }
    if (activeTab.value === 'players') return users.value;
    if (activeTab.value === 'clubs') return clubs.value;
    return [];
});

const displayedListData = computed(() => {
    const data = listData.value;
    // For match tab, show all loaded data (pagination handled by API)
    if (activeTab.value === 'match') {
        return data;
    }
    // For other tabs, use visibleItems slicing
    return data.slice(0, visibleItems.value);
});

const searchResultText = computed(() => {
    const map = {
        courts: `${quantityCourts.value ?? 0} Sân bóng được tìm thấy`,
        match: `${quantityMatches.value ?? 0} Trận đấu được tìm thấy`,
        players: `${quantityUsers.value ?? 0} Người dùng được tìm thấy`,
        clubs: `${quantityClubs.value ?? 0} Câu lạc bộ được tìm thấy`
    }

    return map[activeTab.value] ?? '0 kết quả được tìm thấy'
})

// Scroll handler cho infinite loading
const handleScroll = async (event) => {
    const target = event.target;
    const scrollPercentage = (target.scrollTop + target.clientHeight) / target.scrollHeight;

    // For match tab, load more from API when scrolling
    if (activeTab.value === 'match' && scrollPercentage > 0.8) {
        const currentData = activeMatchTab.value === 'mini' ? matchesMini.value : matchesTournament.value;
        const hasMore = activeMatchTab.value === 'mini' 
            ? miniMatchPage.value < miniMatchLastPage.value
            : tournamentPage.value < tournamentLastPage.value;
        
        if (hasMore && !isLoadingMoreMatches.value) {
            await loadMoreMatches();
        }
    }
    
    // For other tabs, just show more items from existing data
    if (activeTab.value !== 'match' && scrollPercentage > 0.8 && visibleItems.value < listData.value.length) {
        visibleItems.value = Math.min(
            visibleItems.value + itemsPerLoad.value,
            listData.value.length
        );
    }
};

watch([activeTab, searchCourt, searchMatch, searchUser], () => {
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
        searchCourt.value?.trim() ||
        searchMatch.value?.trim() ||
        searchUser.value?.trim() ||
        selectedSportId.value ||
        isShowMyFollow.value ||
        isShowFavoritePlayer.value ||
        isConnected.value ||
        selectedCourtCounts.value.length > 0 ||
        selectedCourtTypes.value.length > 0 ||
        selectedFacilities.value.length > 0 ||
        selectedRadiusValue.value !== null ||
        selectedLocationValue.value !== null ||
        is_verify_profile.value ||
        isHasAchievement.value
    );
});

navigator.geolocation.getCurrentPosition(
  ({ coords }) => {
    lat.value = coords.latitude
    lng.value = coords.longitude
  }
)

const getCompetitionLocation = async (bounds = null) => {
    try {
        const params = {
            is_map: 1,
            keyword: searchCourt.value?.trim() || undefined,
            sport_id: selectedSportId.value || undefined,
            is_followed: isShowMyFollow.value ? 1 : 0,
            number_of_yards: selectedCourtCounts.value.length > 0 ? selectedCourtCounts.value : undefined,
            yard_type: selectedCourtTypes.value.length > 0 ? selectedCourtTypes.value : undefined,
            facility_id: selectedFacilities.value.length > 0 ? selectedFacilities.value : undefined,
            location_id: selectedLocationValue.value || undefined,
        };

        if (selectedRadiusValue.value === 'nearby' && userLocation.value) {
            params.lat = userLocation.value.lat;
            params.lng = userLocation.value.lng;
            params.radius = 20;
        } else if (bounds) {
            params.minLat = bounds.getSouth();
            params.maxLat = bounds.getNorth();
            params.minLng = bounds.getWest();
            params.maxLng = bounds.getEast();
        }

        if(lat.value && lng.value) {
            params.lat = lat.value,
            params.lng = lng.value   
        }

        Object.keys(params).forEach(key => {
            if (params[key] === undefined) {
                delete params[key];
            }
        });

        const res = await MapService.getCourtData(params);
        if (res.data) {
            mergeData(courtsMap.value, res.data.competition_locations, hasActiveFilters.value);
            quantityCourts.value = courtsMap.value.size;

            if (res.data.facilities) {
                facilities.value = res.data.facilities;
            }
            if (res.data.yard_types) {
                yardTypes.value = res.data.yard_types;
            }
        }
    } catch (error) {
        console.error("Error fetching map data:", error);
        toast.error(error.response?.data?.message || "Lỗi khi tải dữ liệu sân bóng");
    }
};

const getListUser = async (bounds = null) => {
    try {
        const params = {
            keyword: searchUser.value?.trim() || undefined,
            sport_id: selectedSportId.value || undefined,
            favourite_player: isShowFavoritePlayer.value ? 1 : 0,
            is_connected: isConnected.value ? 1 : 0,
            is_map: 1,
            location_id: selectedLocationValue.value || undefined,
            gender: selectedGenderValue.value ?? undefined,
            time_of_day: selectedTimePlay.value ?? undefined,
            rating: selectedRating.value ?? undefined,
            online_recently: isOnlineRecently.value ? 1 : 0,
            recent_matches: isQuantityMatcheshasPlayRecently.value ?? undefined,
            same_club_id: selectedClub.value ?? undefined,
            verify_profile: is_verify_profile.value ? 1 : undefined,
            achievement: isHasAchievement.value ? 1 : undefined
        };

        // if (bounds) {
        //     params.minLat = bounds.getSouth();
        //     params.maxLat = bounds.getNorth();
        //     params.minLng = bounds.getWest();
        //     params.maxLng = bounds.getEast();
        // }
        if(lat.value && lng.value) {
            params.lat = lat.value,
            params.lng = lng.value   
        }

        Object.keys(params).forEach(key => {
            if (params[key] === undefined) {
                delete params[key];
            }
        });

        const res = await UserService.getUserData(params);
        if (res.data) {
            mergeData(usersMap.value, res.data.users || [], hasActiveFilters.value);
            quantityUsers.value = usersMap.value.size;
        }
    } catch (error) {
        console.error("Error fetching user data:", error);
        toast.error(error.response?.data?.message || "Lỗi khi tải dữ liệu người chơi");
    }
};

const getListMatches = async (bounds = null, isLoadMore = false) => {
    try {
        const params = {
            keyword: searchMatch.value?.trim() || undefined,
            sport_id: selectedSportId.value || undefined,
            is_followed: isShowMyFollow.value ? 1 : 0 || undefined,
        };

        // Always use pagination for list view
        if (activeMatchTab.value === 'mini') {
            params.mini_tournament_page = isLoadMore ? miniMatchPage.value : 1;
            params.mini_tournament_per_page = miniMatchPerPage.value;
        } else {
            params.tournament_page = isLoadMore ? tournamentPage.value : 1;
            params.tournament_per_page = tournamentPerPage.value;
        }

        if (bounds) {
            params.min_lat = bounds.getSouth();
            params.max_lat = bounds.getNorth();
            params.min_lng = bounds.getWest();
            params.max_lng = bounds.getEast();
        }

        if(lat.value && lng.value) {
            params.lat = lat.value,
            params.lng = lng.value   
        }

        Object.keys(params).forEach(key => {
            if (params[key] === undefined) {
                delete params[key];
            }
        });

        const res = await MapService.getMatchesData(params);
        
        if (!isLoadMore) {
            matchesMap.value.clear();
        }

        if (res && typeof res === 'object' && !Array.isArray(res)) {
             const mini = (res.mini_tournaments?.data || []).map(m => ({ ...m, id: `mini_${m.id}`, original_id: m.id, type: 'mini' }));
             const tour = (res.tournaments?.data || []).map(t => ({ ...t, id: `tour_${t.id}`, original_id: t.id, type: 'tournament' }));
             
             // Update pagination meta
             if (res.mini_tournaments?.meta) {
                 miniMatchLastPage.value = res.mini_tournaments.meta.last_page || 1;
                 if (!isLoadMore) {
                     miniMatchPage.value = res.mini_tournaments.meta.current_page || 1;
                 } else {
                     // Update current page when loading more
                     miniMatchPage.value = res.mini_tournaments.meta.current_page || miniMatchPage.value;
                 }
             }
             if (res.tournaments?.meta) {
                 tournamentLastPage.value = res.tournaments.meta.last_page || 1;
                 if (!isLoadMore) {
                     tournamentPage.value = res.tournaments.meta.current_page || 1;
                 } else {
                     // Update current page when loading more
                     tournamentPage.value = res.tournaments.meta.current_page || tournamentPage.value;
                 }
             }

             if (isLoadMore) {
                 // Append new data when loading more - only for active tab
                 if (activeMatchTab.value === 'mini') {
                     matchesMini.value = [...matchesMini.value, ...mini];
                 } else {
                     matchesTournament.value = [...matchesTournament.value, ...tour];
                 }
             } else {
                 // Replace data on initial load or filter
                 matchesMini.value = mini;
                 matchesTournament.value = tour;
             }

             const dataToShow = activeMatchTab.value === 'mini' ? matchesMini.value : matchesTournament.value;
             mergeData(matchesMap.value, dataToShow, !isLoadMore && hasActiveFilters.value);
             
        } else {
            if (!isLoadMore) {
                matchesMini.value = [];
                matchesTournament.value = [];
            }
        }
        
        quantityMatches.value = matchesMap.value.size;
    } catch (error) {
        console.error("Error fetching match data:", error);
        toast.error(error.response?.data?.message || "Lỗi khi tải dữ liệu trận đấu");
    }
};

const loadMoreMatches = async () => {
    if (isLoadingMoreMatches.value) return;
    
    isLoadingMoreMatches.value = true;
    try {
        if (activeMatchTab.value === 'mini') {
            miniMatchPage.value += 1;
        } else {
            tournamentPage.value += 1;
        }
        
        await getListMatches(currentBounds.value, true);
        
        // Update markers with new data
        clearAllMarkers();
        addMatchMarkers(matches.value, router, focusItemAuto, false, defaultImage);
    } catch (error) {
        // Revert page on error
        if (activeMatchTab.value === 'mini') {
            miniMatchPage.value -= 1;
        } else {
            tournamentPage.value -= 1;
        }
    } finally {
        isLoadingMoreMatches.value = false;
    }
};

// Watch activeMatchTab to reload markers
watch(activeMatchTab, () => {
    // Reset pagination when switching tabs
    miniMatchPage.value = 1;
    tournamentPage.value = 1;
    
    matchesMap.value.clear();
    const dataToShow = activeMatchTab.value === 'mini' ? matchesMini.value : matchesTournament.value;
    mergeData(matchesMap.value, dataToShow, true); // Treat like filter change
    quantityMatches.value = matchesMap.value.size; // Cập nhật số lượng
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
        if (tab === 'courts') {
            await getCompetitionLocation(bounds);
            addCourtMarkers(courts.value, toHourMinute, defaultImage, focusItemAuto, shouldUpdate);
        } else if (tab === 'match') {
            await getListMatches(bounds);
            addMatchMarkers(matches.value, router, focusItemAuto, shouldUpdate, defaultImage);
        } else if (tab === 'players') {
            await getListUser(bounds);
            addUserMarkers(users.value, defaultImage, maleIconRaw, femaleIconRaw, getVisibilityText, getUserRating, router, focusItemAuto, shouldUpdate);
        } else if (tab === 'clubs') {
            await getListClubs(bounds);
            addClubMarkers(clubs.value, router, focusItemAuto, shouldUpdate, defaultImage);
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

    if (activeTab.value === 'courts') {
        courtsMap.value.clear();
    } else if (activeTab.value === 'match') {
        matchesMap.value.clear();
    } else if (activeTab.value === 'players') {
        usersMap.value.clear();
    } else if (activeTab.value === 'clubs') {
        clubsMap.value.clear();
    }

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
    if (activeTab.value === 'courts') {
        courtsMap.value.clear();
    } else if (activeTab.value === 'match') {
        // Reset pagination when applying filter
        miniMatchPage.value = 1;
        tournamentPage.value = 1;
        matchesMap.value.clear();
    } else if (activeTab.value === 'players') {
        usersMap.value.clear();
    } else if (activeTab.value === 'clubs') {
        clubsMap.value.clear();
    }

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
    searchCourt.value = '';
    searchMatch.value = '';
    searchUser.value = '';
    searchClub.value = '';
    selectedRadiusValue.value = null;
    selectedRadiusLabel.value = 'Chọn';
    userLocation.value = null;
    selectedLocationValue.value = null;
    selectedLocationLabel.value = 'Chọn địa điểm';
    locationSearchQuery.value = '';
    
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
        try {
            userLocation.value = await getUserLocation();
        } catch (error) {
            toast.error('Không thể lấy vị trí của bạn. Vui lòng cho phép truy cập vị trí.');
            selectedRadiusValue.value = null;
            selectedRadiusLabel.value = 'Chọn';
            return;
        }
    } else {
        userLocation.value = null;
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
    courts: selectedCourt,
    players: selectedUser,
    match: selectedMatches,
    clubs: selectedClubItem
}

const focusItemAuto = (item) => {
    const selectedRef = selectedMap[activeTab.value]
    if (!selectedRef) return

    selectedRef.value = item.id
    focusItem(item.id)
}

const getUserRating = (user) => {
    if (!user?.sports?.length) return "0";
    const pickleballSport = user.sports.find(sport => sport.sport_name === "Pickleball");
    if (!pickleballSport) return "0";
    return parseFloat(pickleballSport.scores.vndupr_score).toFixed(1) || "0";
};

const getMyClubs = async () => {
    try {
        const response = await ClubService.myClubs();
        myClub.value = response || [];
    } catch (e) {
        toast.error(e.response?.data?.message || "Lấy danh sách câu lạc bộ không thành công");
    }
};

const getListClubs = async (bounds = null) => {
    try {
        const params = {
            is_map: true,
            name: searchClub.value?.trim() || undefined,
        };

        if (lat.value && lng.value) {
            params.lat = lat.value;
            params.lng = lng.value;
        } else if (bounds) {
            params.minLat = bounds.getSouth();
            params.maxLat = bounds.getNorth();
            params.minLng = bounds.getWest();
            params.maxLng = bounds.getEast();
        }

        Object.keys(params).forEach(key => {
            if (params[key] === undefined) {
                delete params[key];
            }
        });

        const res = await ClubService.getAllClubs(params);
        const clubsData = res?.clubs ?? res?.data ?? [];
        mergeData(clubsMap.value, clubsData, hasActiveFilters.value);
        quantityClubs.value = clubsMap.value.size;
    } catch (error) {
        console.error("Error fetching clubs data:", error);
        toast.error(error.response?.data?.message || "Lỗi khi tải dữ liệu câu lạc bộ");
    }
};

onMounted(async () => {
    await getListSports();
    await getListLocation();
    await getMyClubs();
});

const searchValue = computed({
    get() {
        if (activeTab.value === 'courts') return searchCourt.value
        if (activeTab.value === 'match') return searchMatch.value
        if (activeTab.value === 'players') return searchUser.value
        if (activeTab.value === 'clubs') return searchClub.value
        return ''
    },
    set(val) {
        if (activeTab.value === 'courts') searchCourt.value = val
        if (activeTab.value === 'match') searchMatch.value = val
        if (activeTab.value === 'players') searchUser.value = val
        if (activeTab.value === 'clubs') searchClub.value = val
    }
})

const searchPlaceholder = computed(() => {
    if (activeTab.value === 'courts') return 'Tìm sân'
    if (activeTab.value === 'match') return 'Tìm trận'
    if (activeTab.value === 'players') return 'Tìm người chơi'
    if (activeTab.value === 'clubs') return 'Tìm câu lạc bộ'
    return ''
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

    if (newTab === 'courts') {
        courtsMap.value.clear();
    } else if (newTab === 'match') {
        // Reset pagination when switching to match tab
        miniMatchPage.value = 1;
        tournamentPage.value = 1;
        matchesMap.value.clear();
    } else if (newTab === 'players') {
        usersMap.value.clear();
    } else if (newTab === 'clubs') {
        clubsMap.value.clear();
    }

    clearAllMarkers();
    loadTabContent(newTab, currentBounds.value);
});

let searchDebounceTimer = null;
watch([searchCourt, searchMatch, searchUser, searchClub], ([newCourt, newMatch, newUser, newClub], [oldCourt, oldMatch, oldUser, oldClub]) => {
    const activeSearchValue = activeTab.value === 'courts' ? newCourt :
        activeTab.value === 'match' ? newMatch :
        activeTab.value === 'players' ? newUser : newClub;
    const oldSearchValue = activeTab.value === 'courts' ? oldCourt :
        activeTab.value === 'match' ? oldMatch :
        activeTab.value === 'players' ? oldUser : oldClub;

    if (activeSearchValue === oldSearchValue) return;

    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);

    if (!activeSearchValue?.trim()) {
        searchDebounceTimer = setTimeout(async () => {
            isInitialLoad.value = true;

            if (activeTab.value === 'courts') {
                courtsMap.value.clear();
            } else if (activeTab.value === 'match') {
                matchesMap.value.clear();
            } else if (activeTab.value === 'players') {
                usersMap.value.clear();
            } else if (activeTab.value === 'clubs') {
                clubsMap.value.clear();
            }

            clearAllMarkers();
            await loadTabContent(activeTab.value, currentBounds.value);
        }, 300);
        return;
    }

    searchDebounceTimer = setTimeout(async () => {
        isInitialLoad.value = true;

        if (activeTab.value === 'courts') {
            courtsMap.value.clear();
        } else if (activeTab.value === 'match') {
            matchesMap.value.clear();
        } else if (activeTab.value === 'players') {
            usersMap.value.clear();
        } else if (activeTab.value === 'clubs') {
            clubsMap.value.clear();
        }

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