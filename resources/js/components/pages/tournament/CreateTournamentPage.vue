<template>
    <div class="figma-create-page bg-[#F7F8FA] min-h-screen py-6 px-3 lg:px-6">
        <div class="max-w-[1320px] mx-auto grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6">
            <!-- LEFT COLUMN: Main Form (8 cols on desktop) -->
            <div class="space-y-4 lg:col-span-8 lg:order-1">

                <!-- Thông tin cơ bản -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <div class="flex items-center gap-4">
                        <!-- Upload poster (60x60) -->
                        <div
                            class="relative w-[60px] h-[60px] bg-[#EDEEF2] border border-dashed border-[#838799] rounded-[8px] flex items-center justify-center flex-shrink-0 cursor-pointer hover:bg-[#e1e2e8] transition-colors"
                            @click="posterInputRef && posterInputRef.click()">
                            <img v-if="posterPreview" :src="posterPreview" alt="Poster"
                                class="w-full h-full object-cover rounded-[8px]">
                            <span v-else class="text-gray-400 text-[11px] text-center leading-tight px-1">Thêm ảnh
                                bìa</span>
                            <input ref="posterInputRef" type="file" accept="image/webp,image/*" class="hidden"
                                @change="onPosterFileChange">

                            <button v-if="posterPreview" @click.stop="removePoster"
                                class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors shadow-lg z-10">
                                <XCircleIcon class="w-2 h-2" />
                            </button>
                        </div>
                        <div class="flex-1 space-y-2">
                            <input v-model="tournamentName" type="text" placeholder="Tên giải đấu (bắt buộc)"
                                class="w-full px-3 py-2 border-b border-[#DCDEE6] focus:outline-none focus:border-[#D72D36] placeholder:text-sm placeholder:text-[#9EA2B3] bg-transparent font-bold text-[16px]" />
                            <input v-model="tournamentNote" type="text" placeholder="Ghi chú: trình độ, lưu ý sân...."
                                class="w-full px-3 py-1 focus:outline-none placeholder:text-[12px] placeholder:text-[#9EA2B3] bg-transparent text-[12px]" />
                        </div>
                    </div>
                </div>

                <!-- Thời gian & Địa điểm -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide">Thời gian & Địa điểm</h3>
                    </div>
                    <div class="space-y-4">
                        <!-- Ngày giờ bắt đầu -->
                        <div class="bg-[#EDEEF2] rounded-[4px] overflow-visible relative" @click.stop>
                            <button @click="toggleOpenDate"
                                class="w-full flex items-center justify-between rounded-[4px] px-2 py-1 hover:bg-gray-200 transition-colors">
                                <div class="flex items-center">
                                    <div class="w-9 h-9 flex items-center justify-center">
                                        <CalendarDaysIcon class="w-5 h-5 text-gray-700" />
                                    </div>
                                    <span class="text-sm"
                                        :class="{ 'text-[#BBBFCC]': !formattedDate, 'text-gray-900 font-medium': formattedDate }">
                                        {{ formattedDate || 'Ngày & Giờ bắt đầu' }}
                                    </span>
                                </div>
                                <ChevronDownIcon class="w-5 h-5 transition-transform text-gray-700"
                                    :class="{ 'rotate-180': openDate }" />
                            </button>

                            <Transition name="fade">
                                <div v-if="openDate"
                                    class="absolute top-full left-0 right-0 mt-2 p-4 z-50 bg-white rounded-lg shadow-lg">
                                    <VueDatePicker v-model="date" :locale="vi" inline auto-apply enable-time-picker />
                                </div>
                            </Transition>
                        </div>

                        <!-- Thời lượng -->
                        <div class="bg-[#EDEEF2] rounded-[4px] overflow-visible relative" @click.stop>
                            <button @click="toggleOpenDuration"
                                class="w-full flex items-center justify-between rounded-[4px] px-2 py-1 hover:bg-gray-200 transition-colors">
                                <div class="flex items-center">
                                    <div class="w-9 h-9 flex items-center justify-center">
                                        <ClockIcon class="w-5 h-5 text-gray-700" />
                                    </div>
                                    <span class="text-sm"
                                        :class="{ 'text-[#BBBFCC]': !durationLabel, 'text-gray-900 font-medium': durationLabel }">
                                        {{ durationLabel || 'Thời lượng giải đấu' }}
                                    </span>
                                </div>
                                <ChevronDownIcon class="w-5 h-5 transition-transform text-gray-700"
                                    :class="{ 'rotate-180': openDuration }" />
                            </button>

                            <Transition name="fade">
                                <div v-if="openDuration"
                                    class="absolute top-full left-0 right-0 mt-2 p-2 z-40 bg-white rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <button v-for="option in durationOptions" :key="option.value"
                                        @click="selectDuration(option)"
                                        class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 rounded block whitespace-nowrap"
                                        :class="{ 'bg-gray-50 font-medium': duration === option.value }">
                                        {{ option.label }}
                                    </button>
                                </div>
                            </Transition>
                        </div>

                        <!-- Địa điểm -->
                        <div class="relative flex items-center" @click.stop>
                            <MapPinIcon class="w-5 h-5 text-gray-700 absolute top-1/2 left-4 -translate-y-1/2" />
                            <input v-model="locationKeyword" @input="fetchCompetitionLocations(locationKeyword)"
                                @focus="isLocationDropdownOpen = competitionLocations.length > 0 || locationKeyword.length >= 2"
                                @blur="setTimeout(() => isLocationDropdownOpen = false, 200)" type="text"
                                placeholder="Địa điểm thi đấu"
                                class="w-full pl-11 pr-4 py-2 my-1 border rounded focus:outline-none placeholder:text-sm placeholder:text-[#BBBFCC] bg-[#EDEEF2]" />

                            <div v-if="isLocationDropdownOpen"
                                class="absolute left-0 right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50 max-h-60 overflow-y-auto">
                                <button v-for="location in competitionLocations" :key="location.id"
                                    @mousedown.prevent="selectLocation(location)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': selectedLocation && selectedLocation.id === location.id }">
                                    {{ location.name }}
                                    <p v-if="location.address" class="text-xs text-gray-500 truncate">{{
                                        location.address }}</p>
                                </button>
                                <p v-if="!competitionLocations.length && locationKeyword.length >= 2"
                                    class="p-4 text-gray-500 text-sm">Không tìm thấy địa điểm nào.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thời gian đăng ký -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-4">Thời gian đăng ký</h3>
                    <div class="space-y-3">
                        <!-- Mở đăng ký -->
                        <div class="bg-[#EDEEF2] rounded-[4px] overflow-visible relative" @click.stop>
                            <button @click="toggleOpenRegistrationOpenAt"
                                class="w-full flex items-center justify-between rounded-[4px] px-2 py-1 hover:bg-gray-200 transition-colors">
                                <div class="flex items-center">
                                    <div class="w-9 h-9 flex items-center justify-center">
                                        <CalendarIcon class="w-5 h-5 text-gray-700" />
                                    </div>
                                    <span class="text-sm"
                                        :class="{ 'text-[#BBBFCC]': !formattedRegistrationOpenAt, 'text-gray-900 font-medium': formattedRegistrationOpenAt }">
                                        {{ formattedRegistrationOpenAt || 'Mở đăng ký' }}
                                    </span>
                                </div>
                                <ChevronDownIcon class="w-5 h-5 transition-transform text-gray-700"
                                    :class="{ 'rotate-180': openRegistrationOpenAt }" />
                            </button>
                            <Transition name="fade">
                                <div v-if="openRegistrationOpenAt"
                                    class="absolute top-full left-0 right-0 mt-2 p-4 z-50 bg-white rounded-lg shadow-lg">
                                    <VueDatePicker v-model="registrationOpenAt" :locale="vi" inline auto-apply enable-time-picker />
                                </div>
                            </Transition>
                        </div>

                        <!-- Hạn đăng ký sớm -->
                        <div class="bg-[#EDEEF2] rounded-[4px] overflow-visible relative" @click.stop>
                            <button @click="toggleOpenEarlyDeadline"
                                class="w-full flex items-center justify-between rounded-[4px] px-2 py-1 hover:bg-gray-200 transition-colors">
                                <div class="flex items-center">
                                    <div class="w-9 h-9 flex items-center justify-center">
                                        <StarIcon class="w-5 h-5 text-gray-700" />
                                    </div>
                                    <span class="text-sm"
                                        :class="{ 'text-[#BBBFCC]': !formattedEarlyRegistrationDeadline, 'text-gray-900 font-medium': formattedEarlyRegistrationDeadline }">
                                        {{ formattedEarlyRegistrationDeadline || 'Hạn đăng ký sớm' }}
                                    </span>
                                </div>
                                <ChevronDownIcon class="w-5 h-5 transition-transform text-gray-700"
                                    :class="{ 'rotate-180': openEarlyDeadline }" />
                            </button>
                            <Transition name="fade">
                                <div v-if="openEarlyDeadline"
                                    class="absolute top-full left-0 right-0 mt-2 p-4 z-50 bg-white rounded-lg shadow-lg">
                                    <VueDatePicker v-model="earlyRegistrationDeadline" :locale="vi" inline auto-apply enable-time-picker />
                                </div>
                            </Transition>
                        </div>

                        <!-- Hạn chót đăng ký -->
                        <div class="bg-[#EDEEF2] rounded-[4px] overflow-visible relative" @click.stop>
                            <button @click="toggleOpenClosedDeadline"
                                class="w-full flex items-center justify-between rounded-[4px] px-2 py-1 hover:bg-gray-200 transition-colors">
                                <div class="flex items-center">
                                    <div class="w-9 h-9 flex items-center justify-center">
                                        <ClockIcon class="w-5 h-5 text-gray-700" />
                                    </div>
                                    <span class="text-sm"
                                        :class="{ 'text-[#BBBFCC]': !formattedRegistrationClosedAt, 'text-gray-900 font-medium': formattedRegistrationClosedAt }">
                                        {{ formattedRegistrationClosedAt || 'Hạn chót đăng ký' }}
                                    </span>
                                </div>
                                <ChevronDownIcon class="w-5 h-5 transition-transform text-gray-700"
                                    :class="{ 'rotate-180': openClosedDeadline }" />
                            </button>
                            <Transition name="fade">
                                <div v-if="openClosedDeadline"
                                    class="absolute top-full left-0 right-0 mt-2 p-4 z-50 bg-white rounded-lg shadow-lg">
                                    <VueDatePicker v-model="registrationClosedAt" :locale="vi" inline auto-apply enable-time-picker />
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <!-- Trình độ & Giới tính -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-4">Trình độ & Giới tính</h3>
                    <div class="space-y-4">
                        <!-- DUPR & VNDUPR toggles -->
                        <div class="flex items-center justify-between pb-3 border-b border-[#DCDEE6]">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Tích điểm DUPR</p>
                                <p class="text-xs text-gray-500">Cập nhật điểm DUPR cho người chơi</p>
                            </div>
                            <button @click="toggleDUPR"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="duprEnabled ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="duprEnabled ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <div class="flex items-center justify-between pb-3 border-b border-[#DCDEE6]">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Tích điểm PICKI</p>
                                <p class="text-xs text-gray-500">Cập nhật điểm PICKI cho người chơi</p>
                            </div>
                            <button @click="toggleVNDUPR"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="vnduprEnabled ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="vnduprEnabled ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <!-- Trình độ tối thiểu -->
                        <div class="flex items-center justify-between relative" @click.stop>
                            <span class="text-sm text-gray-700">Trình độ tối thiểu</span>
                            <button @click="toggleOpenMinLevel"
                                class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                <span class="font-medium text-sm">{{ minLevel }}</span>
                                <ChevronDownIcon class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-180': openMinLevel }" />
                            </button>
                            <div v-if="openMinLevel" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50 max-h-60 overflow-y-auto">
                                <button @click="selectMinLevel('Không giới hạn')"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': minLevel === 'Không giới hạn' }">
                                    Không giới hạn
                                </button>
                                <button v-for="level in levels" :key="level" @click="selectMinLevel(level)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': minLevel === level }">
                                    {{ level }}
                                </button>
                            </div>
                        </div>

                        <!-- Trình độ tối đa -->
                        <div class="flex items-center justify-between relative" @click.stop>
                            <span class="text-sm text-gray-700">Trình độ tối đa</span>
                            <button @click="toggleOpenMaxLevel"
                                class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                <span class="font-medium text-sm">{{ maxLevel }}</span>
                                <ChevronDownIcon class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-180': openMaxLevel }" />
                            </button>
                            <div v-if="openMaxLevel" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50 max-h-60 overflow-y-auto">
                                <button @click="selectMaxLevel('Không giới hạn')"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': maxLevel === 'Không giới hạn' }">
                                    Không giới hạn
                                </button>
                                <button v-for="level in levels" :key="level" @click="selectMaxLevel(level)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': maxLevel === level }">
                                    {{ level }}
                                </button>
                            </div>
                        </div>

                        <hr>

                        <!-- Lứa tuổi -->
                        <div class="flex items-center justify-between relative" @click.stop>
                            <span class="text-sm text-gray-700">Lứa tuổi</span>
                            <button @click="toggleOpenAgeGroup"
                                class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                <span class="font-medium text-sm">{{ ageGroupLabel }}</span>
                                <ChevronDownIcon class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-180': openAgeGroup }" />
                            </button>
                            <div v-if="openAgeGroup" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50">
                                <button v-for="ag in ageGroupOptions" :key="ag.value"
                                    @click="selectAgeGroup(ag.value)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': ageGroup === ag.value }">
                                    {{ ag.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Giới tính -->
                        <div class="flex items-center justify-between relative" @click.stop>
                            <span class="text-sm text-gray-700">Giới tính</span>
                            <button @click="toggleOpenGenderPolicy"
                                class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                <span class="font-medium text-sm">{{ genderPolicyLabel }}</span>
                                <ChevronDownIcon class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-180': openGenderPolicy }" />
                            </button>
                            <div v-if="openGenderPolicy" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50">
                                <button v-for="gp in genderPolicyOptions" :key="gp.value"
                                    @click="selectGenderPolicy(gp.value)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': genderPolicy === gp.value }">
                                    {{ gp.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Người tham gia -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-4">Người tham gia</h3>
                    <div class="space-y-4">
                        <!-- Hình thức: Đơn / Đội -->
                        <div>
                            <p class="text-sm text-gray-600 font-medium block mb-2">Hình thức tham gia</p>
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="p in participantOptions" :key="p.value" @click="participant = p.value"
                                    class="py-2.5 text-sm font-medium rounded-[4px] transition-all border"
                                    :class="participant === p.value ? 'bg-[#D72D36] border-[#D72D36] text-white shadow-md' : 'bg-white border-gray-200 text-[#838799] hover:border-gray-300'">
                                    {{ p.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Số đội tối đa -->
                        <div v-if="participant === 'team'" class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Số đội chơi tối đa</p>
                                <p class="text-xs text-gray-500">Số lượng đội tham gia tối đa</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button @click="decreaseTeam"
                                    class="w-7 h-7 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none font-bold">
                                    −
                                </button>
                                <span class="text-xl font-semibold w-8 text-center select-none">{{ teamCount }}</span>
                                <button @click="increaseTeam"
                                    class="w-7 h-7 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none font-bold">
                                    +
                                </button>
                            </div>
                        </div>

                        <!-- Số người mỗi đội -->
                        <div v-if="participant === 'team'" class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Số thành viên / đội</p>
                                <p class="text-xs text-gray-500">Số người trong mỗi đội</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button @click="decreasePlayerPerTeam"
                                    class="w-7 h-7 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none font-bold">
                                    −
                                </button>
                                <span class="text-xl font-semibold w-8 text-center select-none">{{ playerPerTeam }}</span>
                                <button @click="increasePlayerPerTeam"
                                    class="w-7 h-7 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none font-bold">
                                    +
                                </button>
                            </div>
                        </div>

                        <!-- Số người chơi tối đa (khi tham gia đơn) -->
                        <div v-if="participant === 'player'" class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Số người chơi tối đa</p>
                                <p class="text-xs text-gray-500">Số lượng người tham gia tối đa</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button @click="decreaseMaxPlayer"
                                    class="w-7 h-7 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none font-bold">
                                    −
                                </button>
                                <span class="text-xl font-semibold w-8 text-center select-none">{{ maxPlayer }}</span>
                                <button @click="increaseMaxPlayer"
                                    class="w-7 h-7 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none font-bold">
                                    +
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Phí giải đấu -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-4">Phí giải đấu</h3>
                    <div class="space-y-4">
                        <!-- Toggle Thu phí -->
                        <div class="flex items-center justify-between pb-3 border-b border-[#DCDEE6]">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Thu phí tham gia</p>
                                <p class="text-xs text-gray-500">Thu tiền từ người tham gia</p>
                            </div>
                            <button @click="hasFee = !hasFee"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="hasFee ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="hasFee ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <template v-if="hasFee">
                            <!-- Quản lý tài chính -->
                            <div class="flex items-center justify-between pb-3 border-b border-[#DCDEE6]">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Quản lý tài chính</p>
                                    <p class="text-xs text-gray-500">Theo dõi và quản lý thu chi</p>
                                </div>
                                <button @click="hasFinancialManagement = !hasFinancialManagement"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="hasFinancialManagement ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        :class="hasFinancialManagement ? 'translate-x-6' : 'translate-x-1'" />
                                </button>
                            </div>

                            <!-- Chia tiền tự động -->
                            <div class="flex items-center justify-between pb-3 border-b border-[#DCDEE6]">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Chia tiền tự động</p>
                                    <p class="text-xs text-gray-500">Tổng tiền / số người</p>
                                </div>
                                <button @click="autoSplitFee = !autoSplitFee"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="autoSplitFee ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        :class="autoSplitFee ? 'translate-x-6' : 'translate-x-1'" />
                                </button>
                            </div>

                            <!-- Phí tham gia -->
                            <div>
                                <p class="text-sm text-gray-600 font-medium block mb-1">
                                    {{ autoSplitFee ? 'Tổng tiền phí (VNĐ)' : 'Phí / người (VNĐ)' }}
                                </p>
                                <input v-model="feeAmount" type="number" min="0" step="10000"
                                    placeholder="Nhập số tiền VNĐ"
                                    class="w-full px-3 py-2 border rounded focus:outline-none placeholder:text-sm placeholder:text-[#BBBFCC] bg-[#EDEEF2]" />
                            </div>

                            <!-- QR Code Upload -->
                            <div>
                                <p class="text-sm text-gray-600 font-medium block mb-1">Mã QR thanh toán</p>
                                <input ref="qrFileInput" type="file" accept="image/webp,image/*" class="hidden"
                                    @change="onQrFileChange" />

                                <!-- Khi đã có preview (file mới hoặc cached) -->
                                <div v-if="qrCodePreview" class="relative inline-block">
                                    <img :src="qrCodePreview" alt="QR Code thanh toán"
                                        class="w-32 h-32 object-contain mx-auto rounded-lg border" />
                                    <button @click="removeQrCode"
                                        class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1">
                                        <XMarkIcon class="w-4 h-4" />
                                    </button>
                                    <p v-if="useCachedQr" class="text-center text-xs text-green-600 mt-1 font-medium">
                                        Đang dùng mã QR đã lưu
                                    </p>
                                </div>

                                <!-- Khi chưa có preview: cho chọn upload HOẶC dùng cached -->
                                <div v-else class="space-y-2">
                                    <div @click="$refs.qrFileInput.click()"
                                        class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-[#D72D36] transition-colors">
                                        <div class="flex flex-col items-center">
                                            <ArrowUpTrayIcon class="w-8 h-8 text-gray-400 mb-2" aria-hidden="true" />
                                            <p class="text-sm text-gray-500">Tải ảnh lên</p>
                                            <p class="text-xs text-gray-400">JPG, PNG (tối đa 5MB)</p>
                                        </div>
                                    </div>

                                    <button type="button"
                                        v-if="user.latest_used_qr"
                                        @click="useCachedQr = true; qrCodePreview = user.latest_used_qr"
                                        class="w-full py-2 px-4 bg-green-50 border border-green-300 rounded-lg text-center cursor-pointer hover:bg-green-100 transition-colors text-sm text-green-700 font-medium">
                                        Dùng mã QR đã lưu trước đó
                                    </button>
                                </div>
                            </div>

                            <!-- Ghi chú thanh toán -->
                            <div>
                                <p class="text-sm text-gray-600 font-medium block mb-1">Ghi chú thanh toán</p>
                                <textarea v-model="feeDescription" rows="2"
                                    placeholder="VD: STK, tên TK, nội dung chuyển khoản..."
                                    class="w-full px-3 py-2 border rounded focus:outline-none placeholder:text-sm placeholder:text-[#BBBFCC] bg-[#EDEEF2] resize-none"></textarea>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Quyền riêng tư & Cài đặt nâng cao -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-4">Quyền riêng tư & Cài đặt</h3>
                    <div class="space-y-4">
                        <!-- Quyền riêng tư -->
                        <div class="flex items-center justify-between relative" @click.stop>
                            <span class="text-sm text-gray-700">Quyền riêng tư</span>
                            <button @click="toggleOpenPrivacy"
                                class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                                <span class="font-medium text-sm">{{ privacyLabel }}</span>
                                <ChevronDownIcon class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-180': openPrivacy }" />
                            </button>
                            <div v-if="openPrivacy" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50">
                                <button v-for="p in privacyOptions" :key="p.value"
                                    @click="selectPrivacy(p.value)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': isPrivate === p.value }">
                                    {{ p.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Tự động duyệt -->
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Duyệt tự động</p>
                                <p class="text-xs text-gray-500">Tự động chấp nhận người đăng ký</p>
                            </div>
                            <button @click="autoApprove = !autoApprove"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="autoApprove ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="autoApprove ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <!-- Nhánh công khai -->
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Nhánh công khai</p>
                                <p class="text-xs text-gray-500">Hiển thị lịch thi đấu công khai</p>
                            </div>
                            <button @click="isPublicBranch = !isPublicBranch"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="isPublicBranch ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="isPublicBranch ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <!-- Tự ghi điểm -->
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Tự ghi điểm</p>
                                <p class="text-xs text-gray-500">Người tham gia tự nhập kết quả</p>
                            </div>
                            <button @click="isOwnScore = !isOwnScore"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="isOwnScore ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="isOwnScore ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <!-- Tôi tham gia giải đấu -->
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Tôi tham gia giải đấu</p>
                                <p class="text-xs text-gray-500">Đăng ký tham gia với tư cách VĐV</p>
                            </div>
                            <button @click="toggleCreatorJoin"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="creatorJoin ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="creatorJoin ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Liên kết CLB -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-4">Liên kết CLB</h3>
                    <select v-model="selectedClubId"
                        class="w-full px-3 py-2 border rounded focus:outline-none bg-[#EDEEF2] text-sm">
                        <option :value="null">-- Không thuộc CLB --</option>
                        <option v-for="club in myClubsList" :key="club.id" :value="club.id">
                            {{ club.name }}
                        </option>
                    </select>
                    <p v-if="selectedClubId" class="text-xs text-green-600 mt-2">
                        ✓ Đang tạo giải cho CLB
                    </p>
                    <p v-else class="text-xs text-gray-400 mt-2">
                        Đây là giải thường (không thuộc CLB)
                    </p>
                </div>

                <!-- Nút hành động -->
                <div class="flex items-center gap-3">
                    <button @click="handleSubmit" :disabled="isSubmitting"
                        class="flex-1 py-3 bg-[#D72D36] text-white rounded-lg font-bold hover:bg-red-700 transition-colors text-sm disabled:opacity-50">
                        {{ btnTitle }}
                    </button>
                    <button @click="router.back()" v-if="isEditMode"
                        class="px-6 py-3 bg-gray-100 text-gray-600 rounded-lg font-medium hover:bg-gray-200 transition-colors text-sm">
                        Quay lại
                    </button>
                </div>

                <div class="flex flex-col gap-2">
                    <button v-if="!isEditMode" type="button" @click="openTemplateModal"
                        class="w-full border border-[#D72D36] text-[#D72D36] font-bold py-3.5 rounded-[12px] flex items-center justify-center gap-2 hover:bg-[#FFF5F5] transition-colors">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3H5a2 2 0 0 0-2 2v14l4-3 4 3 4-3 4 3V5a2 2 0 0 0-2-2z" />
                        </svg>
                        <span>Chọn mẫu giải đấu</span>
                    </button>

                    <button v-if="!isEditMode" type="button" @click="handleSaveTemplate"
                        class="w-full border border-[#D72D36] text-[#D72D36] font-bold py-3.5 rounded-[12px] flex items-center justify-center gap-2 hover:bg-[#FFF5F5] transition-colors">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17 3H7a2 2 0 0 0-2 2v14l4-3 4 3 4-3 4 3V5a2 2 0 0 0-2-2h-2z" />
                            <path d="M15 9H9V7h6v2z" fill="#fff" />
                        </svg>
                        <span>Lưu cài đặt này làm mẫu</span>
                    </button>
                </div>
            </div>

            <!-- RIGHT COLUMN: Sidebar (4 cols on desktop) -->
            <div class="space-y-4 lg:col-span-4 lg:order-2">
                <!-- Môn thể thao -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-3">Môn thể thao</h3>
                    <div class="space-y-2">
                        <button v-for="sport in sports" :key="sport.id" @click="selectedSportId = sport.id" :class="[
                            'w-full flex items-center gap-3 px-4 py-3 rounded-[8px] border transition-colors',
                            selectedSportId === sport.id
                                ? 'bg-[#D72D36] text-white border-[#D72D36]'
                                : 'border-[#BBBFCC] text-gray-700 hover:border-gray-400'
                        ]">
                            <img :src="sport.icon || '/images/basketball.png'" alt="" draggable="false"
                                class="w-6 h-6"
                                :class="{ 'filter brightness-0 invert': selectedSportId === sport.id }" />
                            <span class="text-[14px] font-semibold">{{ sport.name }}</span>
                        </button>
                    </div>
                </div>

                <!-- Tóm tắt giải đấu -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-3">Tóm tắt</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Môn thể thao:</span>
                            <span class="font-semibold text-[#3E414C]">{{ getSportName() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Hình thức:</span>
                            <span class="font-semibold text-[#3E414C]">{{ participantLabel }}</span>
                        </div>
                        <div v-if="participant === 'team'" class="flex justify-between">
                            <span class="text-[#6B6F80]">Số đội:</span>
                            <span class="font-semibold text-[#3E414C]">{{ teamCount }} đội</span>
                        </div>
                        <div v-if="participant === 'team'" class="flex justify-between">
                            <span class="text-[#6B6F80]">Người/đội:</span>
                            <span class="font-semibold text-[#3E414C]">{{ playerPerTeam }} người</span>
                        </div>
                        <div v-if="participant === 'player'" class="flex justify-between">
                            <span class="text-[#6B6F80]">Người chơi tối đa:</span>
                            <span class="font-semibold text-[#3E414C]">{{ maxPlayer }} người</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Trình độ:</span>
                            <span class="font-semibold text-[#3E414C]">{{ minLevel }} - {{ maxLevel }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Giới tính:</span>
                            <span class="font-semibold text-[#3E414C]">{{ genderPolicyLabel }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Lứa tuổi:</span>
                            <span class="font-semibold text-[#3E414C]">{{ ageGroupLabel }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Phí:</span>
                            <span class="font-semibold text-[#3E414C]">{{ hasFee ? formatCurrency(feeAmount) : 'Miễn phí' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Quyền riêng tư:</span>
                            <span class="font-semibold text-[#3E414C]">{{ privacyLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal chọn mẫu giải đấu -->
    <div v-if="isTemplateModalOpen"
        class="fixed inset-0 z-[99] flex items-center justify-center bg-gray-600 bg-opacity-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-semibold">Chọn mẫu giải đấu</h4>
                <button @click="closeTemplateModal" class="text-gray-400 hover:text-gray-600">
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </div>

            <div v-if="isLoadingTemplates" class="py-6 text-center text-sm text-gray-500">
                Đang tải...
            </div>
            <div v-else-if="!templates.length" class="py-6 text-center text-sm text-gray-500">
                Bạn chưa có mẫu giải đấu nào.
            </div>
            <div v-else class="space-y-3 max-h-80 overflow-y-auto">
                <button v-for="template in templates" :key="template.id" type="button"
                    @click="applyTemplate(template)"
                    class="w-full flex items-center justify-between px-4 py-3 rounded-[10px] border border-[#DCDEE6] hover:border-[#D72D36] hover:bg-[#FFF5F5] transition-colors text-left">
                    <p class="text-[14px] font-semibold text-[#3E414C]">{{ template.name }}</p>
                    <ChevronRightIcon class="w-4 h-4 text-[#D72D36]" />
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import VueDatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import { vi } from 'date-fns/locale'
import { ChevronDownIcon, ChevronRightIcon, XCircleIcon, XMarkIcon } from "@heroicons/vue/24/solid";
import { CalendarDaysIcon, CalendarIcon, ClockIcon, MapPinIcon, StarIcon, ArrowUpTrayIcon } from "@heroicons/vue/24/outline";
import * as TournamentService from '@/service/tournament'
import * as SportService from '@/service/sport'
import * as CompetitionLocationService from '@/service/competitionLocation'
import * as ClubService from '@/service/club'
import { toast } from 'vue3-toastify'
import { levels } from '@/constants/levels';
import { useFormattedDate } from '@/composables/formatedDate'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/store/auth'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const user = userStore.getUser
const tournamentId = route.params.id || null
const isEditMode = computed(() => !!tournamentId)
const btnTitle = computed(() => isEditMode.value ? 'Chỉnh sửa giải đấu' : 'Tạo giải đấu');

// =================================================================================
// Constants
// =================================================================================
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

const ageGroupOptions = [
    { label: 'Mọi lứa tuổi', value: 1 },
    { label: 'Thiếu niên (dưới 18)', value: 2 },
    { label: 'Người lớn (18-55)', value: 3 },
    { label: 'Cao tuổi (trên 55)', value: 4 },
]

const genderPolicyOptions = [
    { label: 'Nam', value: 1 },
    { label: 'Nữ', value: 2 },
    { label: 'Nam Nữ', value: 3 },
]

const participantOptions = [
    { label: 'Đơn', value: 'player' },
    { label: 'Đội', value: 'team' },
]

const privacyOptions = [
    { label: 'Công khai', value: false },
    { label: 'Giải riêng tư', value: true },
]

const formatToISOString = (dateObj) => {
    if (!dateObj) return null;
    const d = dateObj instanceof Date ? dateObj : new Date(dateObj);
    if (Number.isNaN(d.getTime())) return null;
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const seconds = String(d.getSeconds()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

// =================================================================================
// Refs and State
// =================================================================================
const openDate = ref(false)
const openDuration = ref(false)
const openMinLevel = ref(false)
const openMaxLevel = ref(false)
const openAgeGroup = ref(false)
const openGenderPolicy = ref(false)
const openPrivacy = ref(false)
const openRegistrationOpenAt = ref(false)
const openEarlyDeadline = ref(false)
const openClosedDeadline = ref(false)

const date = ref(null)
const duration = ref(durationOptions[0].value)
const sports = ref([])
const selectedSportId = ref(null)
const tournamentName = ref('')
const tournamentNote = ref('')
const teamCount = ref(8)
const playerPerTeam = ref(2)
const maxPlayer = ref(32)

const duprEnabled = ref(true)
const vnduprEnabled = ref(true)
const minLevel = ref('Không giới hạn')
const maxLevel = ref('Không giới hạn')
const ageGroup = ref(1)
const genderPolicy = ref(3)
const participant = ref('team')

const locationKeyword = ref('')
const competitionLocations = ref([])
const selectedLocation = ref(null)
const isLocationDropdownOpen = ref(false)

const registrationOpenAt = ref(null)
const earlyRegistrationDeadline = ref(null)
const registrationClosedAt = ref(null)

const hasFee = ref(false)
const hasFinancialManagement = ref(false)
const feeAmount = ref(100000)
const autoSplitFee = ref(false)
const feeDescription = ref('')
const qrCodeFile = ref(null)
const qrCodePreview = ref(null)
const qrCodeImage = ref(null)
const qrFileInput = ref(null)
const useCachedQr = ref(false)
const posterFile = ref(null)
const posterPreview = ref(null)
const posterInputRef = ref(null)

const isPrivate = ref(false)
const autoApprove = ref(false)
const isPublicBranch = ref(true)
const isOwnScore = ref(false)
const creatorJoin = ref(false)

const selectedClubId = ref(null)
const myClubsList = ref([])

const isTemplateModalOpen = ref(false)
const templates = ref([])
const isLoadingTemplates = ref(false)
const isSubmitting = ref(false)

// =================================================================================
// Computed
// =================================================================================
const { formattedDate } = useFormattedDate(date)
const { formattedDate: formattedRegistrationOpenAt } = useFormattedDate(registrationOpenAt)
const { formattedDate: formattedEarlyRegistrationDeadline } = useFormattedDate(earlyRegistrationDeadline)
const { formattedDate: formattedRegistrationClosedAt } = useFormattedDate(registrationClosedAt)

const durationLabel = computed(() => durationOptions.find(d => d.value === duration.value)?.label || 'Chọn thời lượng')

const ageGroupLabel = computed(() => ageGroupOptions.find(a => a.value === ageGroup.value)?.label || 'Mọi lứa tuổi')
const genderPolicyLabel = computed(() => genderPolicyOptions.find(g => g.value === genderPolicy.value)?.label || 'Nam Nữ')
const privacyLabel = computed(() => isPrivate.value ? 'Giải riêng tư' : 'Công khai')
const participantLabel = computed(() => participant.value === 'team' ? 'Đội' : 'Đơn')

const buildImageUrl = (url) => {
    if (!url) return null
    if (url.startsWith('http')) return url
    return `/storage/${url}`
}

const parseLevel = (val, fallback) => {
    if (val !== null && val !== undefined) return val.toString()
    return fallback
}
const fetchTemplates = async () => {
    isLoadingTemplates.value = true
    try {
        const res = await TournamentService.getTournamentTemplates()
        templates.value = res?.data?.templates || res?.templates || []
    } catch (error) {
        console.error('Error fetching tournament templates:', error)
        toast.error('Không tải được danh sách mẫu giải đấu.')
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

// =================================================================================
// Toggle Functions
// =================================================================================
const closeOtherDropdowns = (exceptRef) => {
    if (exceptRef !== openDate) openDate.value = false
    if (exceptRef !== openDuration) openDuration.value = false
    if (exceptRef !== openMinLevel) openMinLevel.value = false
    if (exceptRef !== openMaxLevel) openMaxLevel.value = false
    if (exceptRef !== openAgeGroup) openAgeGroup.value = false
    if (exceptRef !== openGenderPolicy) openGenderPolicy.value = false
    if (exceptRef !== openPrivacy) openPrivacy.value = false
    if (exceptRef !== openRegistrationOpenAt) openRegistrationOpenAt.value = false
    if (exceptRef !== openEarlyDeadline) openEarlyDeadline.value = false
    if (exceptRef !== openClosedDeadline) openClosedDeadline.value = false
    if (exceptRef !== isLocationDropdownOpen) isLocationDropdownOpen.value = false
}

const handleClickOutside = (event) => {
    closeOtherDropdowns(null)
}

const toggleOpenDate = () => {
    const current = openDate.value
    closeOtherDropdowns(openDate)
    openDate.value = !current
}

const toggleOpenDuration = () => {
    const current = openDuration.value
    closeOtherDropdowns(openDuration)
    openDuration.value = !current
}

const toggleOpenMinLevel = () => {
    const current = openMinLevel.value
    closeOtherDropdowns(openMinLevel)
    openMinLevel.value = !current
}

const toggleOpenMaxLevel = () => {
    const current = openMaxLevel.value
    closeOtherDropdowns(openMaxLevel)
    openMaxLevel.value = !current
}

const toggleOpenAgeGroup = () => {
    const current = openAgeGroup.value
    closeOtherDropdowns(openAgeGroup)
    openAgeGroup.value = !current
}

const toggleOpenGenderPolicy = () => {
    const current = openGenderPolicy.value
    closeOtherDropdowns(openGenderPolicy)
    openGenderPolicy.value = !current
}

const toggleOpenPrivacy = () => {
    const current = openPrivacy.value
    closeOtherDropdowns(openPrivacy)
    openPrivacy.value = !current
}

const toggleOpenRegistrationOpenAt = () => {
    const current = openRegistrationOpenAt.value
    closeOtherDropdowns(openRegistrationOpenAt)
    openRegistrationOpenAt.value = !current
}

const toggleOpenEarlyDeadline = () => {
    const current = openEarlyDeadline.value
    closeOtherDropdowns(openEarlyDeadline)
    openEarlyDeadline.value = !current
}

const toggleOpenClosedDeadline = () => {
    const current = openClosedDeadline.value
    closeOtherDropdowns(openClosedDeadline)
    openClosedDeadline.value = !current
}

const toggleDUPR = () => { duprEnabled.value = !duprEnabled.value }
const toggleVNDUPR = () => { vnduprEnabled.value = !vnduprEnabled.value }
const toggleCreatorJoin = () => { creatorJoin.value = !creatorJoin.value }

// =================================================================================
// Select Handlers
// =================================================================================
const selectDuration = (option) => {
    duration.value = option.value
    openDuration.value = false
}

const selectMinLevel = (level) => {
    minLevel.value = level
    openMinLevel.value = false
}

const selectMaxLevel = (level) => {
    maxLevel.value = level
    openMaxLevel.value = false
}

const selectAgeGroup = (value) => {
    ageGroup.value = value
    openAgeGroup.value = false
}

const selectGenderPolicy = (value) => {
    genderPolicy.value = value
    openGenderPolicy.value = false
}

const selectPrivacy = (value) => {
    isPrivate.value = value
    openPrivacy.value = false
}

const selectLocation = (location) => {
    selectedLocation.value = location
    locationKeyword.value = location.name
    isLocationDropdownOpen.value = false
}

const decreaseTeam = () => { if (teamCount.value > 1) teamCount.value-- }
const increaseTeam = () => { teamCount.value++ }
const decreasePlayerPerTeam = () => { if (playerPerTeam.value > 1) playerPerTeam.value-- }
const increasePlayerPerTeam = () => { playerPerTeam.value++ }
const decreaseMaxPlayer = () => { if (maxPlayer.value > 1) maxPlayer.value-- }
const increaseMaxPlayer = () => { maxPlayer.value++ }

// =================================================================================
// File Handlers
// =================================================================================
const onQrFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    if (file.size > 5 * 1024 * 1024) {
        toast.error('Kích thước ảnh không được quá 5MB')
        return
    }
    useCachedQr.value = false
    qrCodeFile.value = file
    qrCodePreview.value = URL.createObjectURL(file)
}

const removeQrCode = () => {
    qrCodeFile.value = null
    qrCodePreview.value = null
    qrCodeImage.value = null
    useCachedQr.value = false
}

const onPosterFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    if (file.size > 5 * 1024 * 1024) {
        toast.error('Kích thước ảnh không được quá 5MB')
        return
    }
    posterFile.value = file
    posterPreview.value = URL.createObjectURL(file)
}

const removePoster = () => {
    posterFile.value = null
    posterPreview.value = null
}

// =================================================================================
// Helper Methods
// =================================================================================
const getSportName = () => {
    const sport = sports.value.find(s => s.id === selectedSportId.value)
    return sport ? sport.name : 'Chưa chọn'
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
}

const getNumericLevel = (level) => {
    if (level === 'Không giới hạn') return null
    return Number.parseFloat(level)
}

// =================================================================================
// Fetch Data
// =================================================================================
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

const fetchMyClubs = async () => {
    try {
        myClubsList.value = await ClubService.myClubs()
    } catch (error) {
        console.error('Error fetching clubs:', error)
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

// =================================================================================
// Submit
// =================================================================================
const handleSubmit = async () => {
    const startsAt = formatToISOString(date.value)
    const regOpenAt = formatToISOString(registrationOpenAt.value)
    const earlyDeadline = formatToISOString(earlyRegistrationDeadline.value)
    const closedDeadline = formatToISOString(registrationClosedAt.value)

    const data = {
        sport_id: selectedSportId.value,
        name: tournamentName.value,
        competition_location_id: selectedLocation.value ? selectedLocation.value.id : null,
        start_date: startsAt,
        registration_open_at: regOpenAt,
        registration_closed_at: closedDeadline,
        early_registration_deadline: earlyDeadline,
        duration: duration.value,
        enable_dupr: duprEnabled.value,
        enable_vndupr: vnduprEnabled.value,
        min_level: getNumericLevel(minLevel.value),
        max_level: getNumericLevel(maxLevel.value),
        age_group: ageGroup.value,
        gender_policy: genderPolicy.value,
        participant: participant.value,
        max_team: participant.value === 'team' ? teamCount.value : null,
        player_per_team: participant.value === 'team' ? playerPerTeam.value : null,
        max_player: participant.value === 'player' ? maxPlayer.value : null,
        is_private: isPrivate.value,
        auto_approve: autoApprove.value,
        description: tournamentNote.value || null,
        club_id: selectedClubId.value,
        has_fee: hasFee.value,
        has_financial_management: hasFinancialManagement.value,
        fee_amount: hasFee.value ? feeAmount.value : null,
        auto_split_fee: autoSplitFee.value,
        fee_description: feeDescription.value || null,
        qr_code_url: qrCodeImage.value || null,
        is_public_branch: isPublicBranch.value,
        is_own_score: isOwnScore.value,
        creator_join: creatorJoin.value,
        use_cached_qr: useCachedQr.value,
    }

    if (qrCodeFile.value || posterFile.value || useCachedQr.value) {
        const formData = new FormData()
        Object.entries(data).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                formData.append(key, value)
            }
        })
        if (qrCodeFile.value) formData.append('qr_code_url', qrCodeFile.value)
        if (posterFile.value) formData.append('poster', posterFile.value)

        if (isEditMode.value) {
            await updateTournament(tournamentId, formData)
        } else {
            await createTournament(formData)
        }
        return
    }

    if (isEditMode.value) {
        await updateTournament(tournamentId, data)
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
        if (res && res.id) {
            setTimeout(() => {
                router.push({ name: 'tournament-detail', params: { id: res.id } })
            }, 1000)
        }
    } catch (error) {
        console.error('Error creating tournament:', error)
        toast.error('Tạo giải đấu thất bại. Vui lòng kiểm tra lại thông tin.')
    }
}

// =================================================================================
// Prefill Form
// =================================================================================
const prefillForm = (data) => {
    if (!data) return

    selectedSportId.value = data.sport_id || null
    tournamentName.value = data.name || ''
    tournamentNote.value = data.description || ''

    if (data.competition_location) {
        selectedLocation.value = data.competition_location
        locationKeyword.value = data.competition_location.name || ''
    }

    if (data.start_date) date.value = new Date(data.start_date)
    if (data.registration_open_at) registrationOpenAt.value = new Date(data.registration_open_at)
    if (data.early_registration_deadline) earlyRegistrationDeadline.value = new Date(data.early_registration_deadline)
    if (data.registration_closed_at) registrationClosedAt.value = new Date(data.registration_closed_at)
    if (data.duration) duration.value = data.duration

    duprEnabled.value = data.enable_dupr ?? true
    vnduprEnabled.value = data.enable_vndupr ?? true

    minLevel.value = parseLevel(data.min_level, 'Không giới hạn')
    maxLevel.value = parseLevel(data.max_level, 'Không giới hạn')

    ageGroup.value = data.age_group || 1
    genderPolicy.value = data.gender_policy || 3
    participant.value = data.participant || 'team'

    teamCount.value = data.max_team || 8
    playerPerTeam.value = data.player_per_team || 2
    maxPlayer.value = data.max_player || 32

    hasFee.value = !!data.has_fee
    hasFinancialManagement.value = !!data.has_financial_management
    feeAmount.value = Number(data.fee_amount) || 100000
    autoSplitFee.value = !!data.auto_split_fee
    feeDescription.value = data.fee_description || ''
    qrCodeImage.value = data.qr_code_url || null
    qrCodePreview.value = buildImageUrl(data.qr_code_url)

    posterPreview.value = buildImageUrl(data.poster)

    isPrivate.value = !!data.is_private
    autoApprove.value = !!data.auto_approve
    isPublicBranch.value = !!data.is_public_branch
    isOwnScore.value = !!data.is_own_score
    creatorJoin.value = !!data.creator_join

    selectedClubId.value = data.club_id || null
}

const detailTournament = async (id) => {
    try {
        const data = await TournamentService.getTournamentById(id)
        prefillForm(data)
    } catch (error) {
        console.error('Error fetching tournament details:', error)
    }
}

// =================================================================================
// Template Application
// =================================================================================
const applyTemplate = (template) => {
    const s = template?.settings || {}

    selectedSportId.value = s.sport_id || selectedSportId.value
    tournamentName.value = s.name || tournamentName.value
    tournamentNote.value = s.description || tournamentNote.value

    if (s.competition_location_id) {
        selectedLocation.value = { id: s.competition_location_id, name: s.competition_location_name || '' }
        locationKeyword.value = s.competition_location_name || ''
    }

    if (s.start_date) date.value = new Date(s.start_date)
    if (s.registration_open_at) registrationOpenAt.value = new Date(s.registration_open_at)
    if (s.early_registration_deadline) earlyRegistrationDeadline.value = new Date(s.early_registration_deadline)
    if (s.registration_closed_at) registrationClosedAt.value = new Date(s.registration_closed_at)
    if (s.duration) duration.value = s.duration

    duprEnabled.value = s.enable_dupr ?? true
    vnduprEnabled.value = s.enable_vndupr ?? true

    minLevel.value = parseLevel(s.min_level, 'Không giới hạn')
    maxLevel.value = parseLevel(s.max_level, 'Không giới hạn')

    ageGroup.value = s.age_group || 1
    genderPolicy.value = s.gender_policy || 3
    participant.value = s.participant || 'team'
    teamCount.value = s.max_team || 8
    playerPerTeam.value = s.player_per_team || 2
    maxPlayer.value = s.max_player || 32

    hasFee.value = !!s.has_fee
    hasFinancialManagement.value = !!s.has_financial_management
    feeAmount.value = Number(s.fee_amount) || 100000
    autoSplitFee.value = !!s.auto_split_fee
    feeDescription.value = s.fee_description || ''
    qrCodeImage.value = s.qr_code_url || null
    qrCodePreview.value = buildImageUrl(s.qr_code_url)

    isPrivate.value = !!s.is_private
    autoApprove.value = !!s.auto_approve
    isPublicBranch.value = !!s.is_public_branch
    isOwnScore.value = !!s.is_own_score
    creatorJoin.value = !!s.creator_join

    selectedClubId.value = s.club_id || null

    isTemplateModalOpen.value = false
    toast.success('Đã áp dụng mẫu giải đấu')
}

const buildTemplateSettings = () => {
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
        duration: duration.value,
        enable_dupr: duprEnabled.value,
        enable_vndupr: vnduprEnabled.value,
        min_level: getNumericLevel(minLevel.value),
        max_level: getNumericLevel(maxLevel.value),
        age_group: ageGroup.value,
        gender_policy: genderPolicy.value,
        participant: participant.value,
        max_team: participant.value === 'team' ? teamCount.value : null,
        player_per_team: participant.value === 'team' ? playerPerTeam.value : null,
        max_player: participant.value === 'player' ? maxPlayer.value : null,
        is_private: isPrivate.value,
        auto_approve: autoApprove.value,
        creator_join: creatorJoin.value,
        club_id: selectedClubId.value,
        has_fee: hasFee.value,
        has_financial_management: hasFinancialManagement.value,
        fee_amount: hasFee.value ? feeAmount.value : null,
        auto_split_fee: autoSplitFee.value,
        fee_description: feeDescription.value || null,
        qr_code_url: qrCodeImage.value || null,
        is_public_branch: isPublicBranch.value,
        is_own_score: isOwnScore.value,
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
        toast.success(res?.message || 'Đã lưu cài đặt này làm mẫu')
    } catch (error) {
        console.error('Error saving tournament template:', error)
        toast.error('Lưu mẫu thất bại. Vui lòng thử lại.')
    } finally {
        isSubmitting.value = false
    }
}

// =================================================================================
// Lifecycle
// =================================================================================
onMounted(async () => {
    await Promise.all([fetchSports(), fetchMyClubs()])
    if (isEditMode.value) {
        await detailTournament(tournamentId)
    }
    document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.1s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
