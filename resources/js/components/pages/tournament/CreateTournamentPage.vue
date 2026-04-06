<template>
    <div class="p-4 max-w-5xl mx-auto">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-[8px] shadow p-5 sticky top-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 text-[20px]">Môn thể thao</h3>
                    </div>
                    <p class="text-[#838799]">Môn thể thao của tôi • {{ sports.length }}</p>
                    <Swiper :slides-per-view="'auto'" :space-between="8" :freeMode="true" @swiper="onSwiperInit"
                        :mousewheel="{ forceToAxis: true }" :modules="modules" class="mt-2 !pb-2">
                        <SwiperSlide v-for="sport in sports" :key="sport.id" class="!w-32">
                            <div @click="selectedSportId = sport.id" :class="[
                                'flex flex-col items-center justify-center px-6 py-4 rounded-lg cursor-pointer transition select-none',
                                selectedSportId === sport.id
                                    ? 'bg-[#D72D36] text-white'
                                    : 'border border-[#BBBFCC] text-gray-700 hover:border-gray-400'
                            ]">
                                <div class="text-3xl my-4">
                                    <img :src="sport.icon || '/images/basketball.png'"
                                        :class="{ 'filter brightness-0 invert': selectedSportId === sport.id }" alt=""
                                        draggable="false" class="w-10 h-10" />
                                </div>
                                <div class="font-semibold text-sm text-center">
                                    {{ sport.name }}
                                </div>
                            </div>
                        </SwiperSlide>
                    </Swiper>
                    <div>

                    </div>
                </div>
            </div>

            <div class="space-y-6 lg:col-span-2">
                <div class="bg-white rounded-[8px] shadow p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 text-[20px]">Thông tin giải đấu</h3>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <input v-model="tournamentName" type="text" placeholder="Điền tên giải đấu của bạn"
                                class="w-full px-2 py-2 my-1 border rounded focus:outline-none placeholder:text-sm placeholder:text-[#BBBFCC] bg-[#EDEEF2]" />
                            <textarea v-model="tournamentNote" rows="4" placeholder="Thêm ghi chú cho giải đấu"
                                class="w-full px-2 py-2 my-1 border rounded focus:outline-none placeholder:text-sm placeholder:text-[#BBBFCC] bg-[#EDEEF2] resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-[8px] shadow p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 text-[20px]">Chi tiết</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="relative flex items-center" @click.stop>
                            <MapPinIcon class="w-5 h-5 text-gray-700 absolute top-1/2 left-4 -translate-y-1/2" />
                            <input v-model="locationKeyword" @input="fetchCompetitionLocations(locationKeyword)"
                                @focus="isLocationDropdownOpen = competitionLocations.length > 0 || locationKeyword.length >= 2"
                                @blur="setTimeout(() => isLocationDropdownOpen = false, 200)" type="text"
                                placeholder="Địa điểm"
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
                        <div class="bg-[#EDEEF2] rounded-[4px] overflow-visible relative" @click.stop>
                            <button @click="toggleOpenDate"
                                class="w-full flex items-center justify-between rounded-[4px] px-2 py-1 hover:bg-gray-200 transition-colors">
                                <div class="flex items-center">
                                    <div class="w-9 h-9 flex items-center justify-center">
                                        <CalendarDaysIcon class="w-5 h-5 text-gray-700" />
                                    </div>
                                    <span class="text-sm"
                                        :class="{ 'text-[#BBBFCC]': !formattedDate, 'text-gray-900 font-medium': formattedDate }">
                                        {{ formattedDate || 'Thời gian dự kiến bắt đầu giải' }}
                                    </span>
                                </div>
                                <ChevronRightIcon class="w-5 h-5 transition-transform text-gray-700"
                                    :class="{ 'rotate-90': openDate }" />
                            </button>

                            <Transition name="fade">
                                <div v-if="openDate"
                                    class="absolute top-full left-0 right-0 mt-2 p-4 z-50 bg-white rounded-lg shadow-lg">
                                    <VueDatePicker v-model="date" :locale="vi" inline auto-apply enable-time-picker />
                                </div>
                            </Transition>
                        </div>
                        <span class="text-xs text-[#6B6F80]">Bạn có thể bắt đầu giải ngày khi có đủ người chơi hoặc đội
                            chơi</span>
                    </div>
                </div>
                <div class="bg-white rounded-[8px] shadow p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 text-[20px]">Thời gian đăng kí</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between relative">
                            <span class="text-gray-700">Mở đăng kí</span>
                            <button @click="toggleOpenRegistrationOpenAt" @click.stop
                                class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
                                <span class="font-medium text-[#4392E0]">{{ formattedRegistrationOpenAt || 'Chọn thời gian' }}</span>
                                <ChevronRightIcon class="w-5 h-5 transition-transform"
                                    :class="{ 'rotate-90': openRegistrationOpenAt }" />
                            </button>
                            <Transition name="fade">
                                <div v-if="openRegistrationOpenAt" @click.stop
                                    class="absolute right-0 top-full mt-2 p-4 z-50 bg-white rounded-lg shadow-lg">
                                    <VueDatePicker v-model="registrationOpenAt" :locale="vi" inline auto-apply
                                        enable-time-picker />
                                </div>
                            </Transition>
                        </div>
                        <div class="flex items-center justify-between relative">
                            <span class="text-gray-700">Hạn đăng kí sớm</span>
                            <button @click="toggleOpenEarlyDeadline" @click.stop
                                class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
                                <span class="font-medium text-[#4392E0]">{{ formattedEarlyRegistrationDeadline || 'Chọn thời gian' }}</span>
                                <ChevronRightIcon class="w-5 h-5 transition-transform"
                                    :class="{ 'rotate-90': openEarlyDeadline }" />
                            </button>
                            <Transition name="fade">
                                <div v-if="openEarlyDeadline" @click.stop
                                    class="absolute right-0 top-full mt-2 p-4 z-50 bg-white rounded-lg shadow-lg">
                                    <VueDatePicker v-model="earlyRegistrationDeadline" :locale="vi" inline auto-apply
                                        enable-time-picker />
                                </div>
                            </Transition>
                        </div>
                        <div class="flex items-center justify-between relative">
                            <span class="text-gray-700">Hạn chót đăng kí</span>
                            <button @click="toggleOpenClosedDeadline" @click.stop
                                class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
                                <span class="font-medium text-[#4392E0]">{{ formattedRegistrationClosedAt || 'Chọn thời gian' }}</span>
                                <ChevronRightIcon class="w-5 h-5 transition-transform"
                                    :class="{ 'rotate-90': openClosedDeadline }" />
                            </button>
                            <Transition name="fade">
                                <div v-if="openClosedDeadline" @click.stop
                                    class="absolute right-0 top-full mt-2 p-4 z-50 bg-white rounded-lg shadow-lg">
                                    <VueDatePicker v-model="registrationClosedAt" :locale="vi" inline auto-apply
                                        enable-time-picker />
                                </div>
                            </Transition>
                        </div>
                        <div class="flex items-center justify-between relative">
                            <span class="text-gray-700">Thời lượng</span>
                            <button @click="toggleOpenDuration" @click.stop
                                class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
                                <span class="font-medium text-[#4392E0]">{{ durationLabel }}</span>
                                <ChevronRightIcon class="w-5 h-5 transition-transform"
                                    :class="{ 'rotate-90': openDuration }" />
                            </button>
                            <div v-if="openDuration" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50">
                                <button v-for="d in durationOptions" :key="d.value" @click="selectDuration(d.value)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': duration === d.value }">
                                    {{ d.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-[8px] shadow p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 text-[20px]">DUPR</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Tích điểm DUPR</span>
                            <button @click="toggleDUPR"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="duprEnabled ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="duprEnabled ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Tích điểm PICKI</span>
                            <button @click="toggleVNDUPR"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="vnduprEnabled ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="vnduprEnabled ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>
                        <hr class="my-4 border-1">
                        <div class="flex items-center justify-between relative">
                            <div class="flex items-center gap-3">
                                <span class="text-gray-700">Trình độ tối thiểu</span>
                            </div>
                            <button @click="toggleOpenMinLevel" @click.stop
                                class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
                                <span class="font-medium">{{ minLevel }}</span>
                                <ChevronRightIcon class="w-5 h-5 transition-transform"
                                    :class="{ 'rotate-90': openMinLevel }" />
                            </button>

                            <div v-if="openMinLevel" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50">
                                <button v-for="level in levels" :key="level" @click="selectMinLevel(level)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': minLevel === level }">
                                    {{ level }}
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between relative">
                            <div class="flex items-center gap-3">
                                <span class="text-gray-700">Trình độ tối đa</span>
                            </div>
                            <button @click="toggleOpenMaxLevel" @click.stop
                                class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
                                <span class="font-medium">{{ maxLevel }}</span>
                                <ChevronRightIcon class="w-5 h-5 transition-transform"
                                    :class="{ 'rotate-90': openMaxLevel }" />
                            </button>

                            <div v-if="openMaxLevel" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50">
                                <button v-for="level in levels" :key="level" @click="selectMaxLevel(level)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': maxLevel === level }">
                                    {{ level }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-[8px] shadow p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 text-[20px]">Giới hạn người chơi</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between relative">
                            <span class="text-gray-700">Độ tuổi</span>
                            <button @click="toggleOpenAge" @click.stop
                                class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
                                <span class="font-medium">{{ ageGroupLabel }}</span>
                                <ChevronRightIcon class="w-5 h-5 transition-transform"
                                    :class="{ 'rotate-90': openAge }" />
                            </button>
                            <div v-if="openAge" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50">
                                <button v-for="age in ageGroupOptions" :key="age.value" @click="selectAge(age.value)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': ageGroup === age.value }">
                                    {{ age.label }}
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between relative">
                            <span class="text-gray-700">Giới tính</span>
                            <button @click="toggleOpenGender" @click.stop
                                class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
                                <span class="font-medium">{{ genderLabel }}</span>
                                <ChevronRightIcon class="w-5 h-5 transition-transform"
                                    :class="{ 'rotate-90': openGender }" />
                            </button>
                            <div v-if="openGender" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50">
                                <button v-for="gender in genderOptions" :key="gender.value"
                                    @click="selectGender(gender.value)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': genderPolicy === gender.value }">
                                    {{ gender.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-[8px] shadow p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 text-[20px]">Người tham gia</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-gray-700">Số đội chơi tối đa</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="decreaseTeam"
                                    class="w-6 h-6 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none">
                                    −
                                </button>
                                <span class="text-xl font-semibold w-12 text-center select-none">{{ teamCount
                                }}</span>
                                <button @click="increaseTeam"
                                    class="w-6 h-6 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none">
                                    +
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-gray-700">Số thành viên trong 1 đội</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="decreasePlayerPerTeam"
                                    class="w-6 h-6 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none">
                                    −
                                </button>
                                <span class="text-xl font-semibold w-12 text-center select-none">{{ playerPerTeam
                                }}</span>
                                <button @click="increasePlayerPerTeam"
                                    class="w-6 h-6 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none">
                                    +
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-[8px] shadow p-5">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-gray-900 text-[20px]">Phí giải đấu</h3>
                    </div>
                    <div class="flex items-center justify-start gap-4 mb-2">
                        <button @click="feeType = 'pair'" :class="[
                            'px-5 py-2 rounded font-semibold transition-colors',
                            feeType === 'pair' ? 'bg-[#D72D36] text-white hover:bg-red-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                        ]">
                            Mỗi đội
                        </button>
                        <button @click="feeType = 'free'" :class="[
                            'px-5 py-2 rounded font-semibold transition-colors',
                            feeType === 'free' ? 'bg-[#D72D36] text-white hover:bg-red-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                        ]">
                            Miễn phí
                        </button>
                    </div>
                    <div v-if="feeType === 'pair'" class="flex items-center justify-between relative">
                        <div class="flex items-center gap-3">
                            <span class="text-gray-700">Phí tiêu chuẩn</span>
                        </div>
                        <button @click="toggleFeeAmountInput" @click.stop
                            class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
                            <span class="font-medium">{{ formattedFeeAmount }}</span>
                            <ChevronRightIcon class="w-5 h-5 transition-transform"
                                :class="{ 'rotate-90': isFeeAmountInputOpen }" />
                        </button>

                        <div v-if="isFeeAmountInputOpen" @click.stop
                            class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50 p-3">
                            <input v-model="feeAmountInput" type="number" min="0" step="10000"
                                class="w-full px-2 py-1 border rounded focus:outline-none placeholder:text-sm placeholder:text-[#BBBFCC] bg-white"
                                placeholder="Nhập số tiền (VNĐ)" @blur="updateStandardFeeAmount" />
                            <div class="mt-2 text-xs text-gray-500">Nhập số tiền VNĐ</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-[8px] shadow p-5">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-gray-900 text-[20px]">Quyền riêng tư</h3>
                    </div>
                    <div @click="isPrivate = false" class="flex items-center justify-between mb-2 cursor-pointer p-2 rounded-lg transition-colors"
                        :class="isPrivate === false ? 'bg-white' : 'hover:bg-gray-50'">
                        <div class="flex items-center gap-3">
                            <GlobeAsiaAustraliaIcon class="w-6 h-6"
                                :class="isPrivate === false ? 'text-[#4392E0]' : 'text-gray-700'" />
                            <div>
                                <span :class="isPrivate === false ? 'text-[#207AD5]' : 'text-gray-900'">Công khai</span>
                                <p class="text-xs"
                                    :class="isPrivate === false ? 'text-[#004D99]' : 'text-gray-500'">Ai cũng có thể tìm thấy và đăng ký
                                </p>
                            </div>
                        </div>
                    </div>
                    <div @click="isPrivate = true" class="flex items-center justify-between cursor-pointer p-2 rounded-lg transition-colors"
                        :class="isPrivate === true ? 'bg-white' : 'hover:bg-gray-50'">
                        <div class="flex items-center gap-3">
                                <LockClosedIcon class="w-6 h-6"
                                :class="isPrivate === true ? 'text-[#4392E0]' : 'text-gray-700'" />
                            <div>
                                <span :class="isPrivate === true ? 'text-[#207AD5]' : 'text-gray-900'">Giải riêng tư</span>
                                <p class="text-xs"
                                    :class="isPrivate === true ? 'text-[#004D99]' : 'text-gray-500'">Chỉ dành cho những ai được mời
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-[8px] shadow p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-semibold text-gray-900">Tôi tham gia giải đấu</span>
                            <p class="text-xs text-gray-500">Đăng ký tham gia với tư cách vận động viên</p>
                        </div>
                        <button @click="toggleCreatorJoin"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="creatorJoin ? 'bg-[#D72D36]' : 'bg-gray-300'">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                :class="creatorJoin ? 'translate-x-6' : 'translate-x-1'" />
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-start gap-4">
                    <button @click="handleSubmit"
                        class="w-full max-w-[228px] py-3 bg-[#D72D36] text-white rounded font-semibold hover:bg-red-700 transition-colors">
                        {{ btnTitle }}
                    </button>
                    <button @click="router.back()" v-if="isEditMode"
                        class="w-fit px-4 py-3 bg-gray-200 text-gray-700 rounded font-semibold hover:bg-gray-300 transition-colors">
                        Quay lại
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
import { genderOptions } from '@/constants/genderOption';
import { levels } from '@/constants/levels';
import { ageGroupOptions } from '@/constants/ageGroupOption';
import { useFormattedDate } from '@/composables/formatedDate'
import { useRoute, useRouter } from 'vue-router'
import { watch } from 'vue';

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
const feeType = ref('pair') // 'pair' hoặc 'free'
const standardFeeAmount = ref(100000)
const isFeeAmountInputOpen = ref(false)
const feeAmountInput = ref(100000)

const formattedFeeAmount = computed(() => {
    return `VNĐ${standardFeeAmount.value.toLocaleString('vi-VN')}`
})


// =================================================================================
// REFS CHO PHẦN QUYỀN RIÊNG TƯ
// =================================================================================
const isPrivate = ref(false) // false: Công khai, true: Giải riêng tư
const creatorJoin = ref(false)

// =================================================================================
// New Refs and Consts for Tournament Advanced Settings
// =================================================================================

const genderPolicy = ref(3)
const ageGroup = ref(1)

const openGender = ref(false)
const openAge = ref(false)

const genderLabel = computed(() => genderOptions.find(g => g.value === genderPolicy.value)?.label || 'Không giới hạn')
const ageGroupLabel = computed(() => ageGroupOptions.find(a => a.value === ageGroup.value)?.label || 'Không giới hạn')

// Định nghĩa trạng thái ban đầu để reset form
const initialStates = {
    openDate: false, openMinLevel: false, openMaxLevel: false,
    openGender: false, openAge: false,
    isLocationDropdownOpen: false, isFeeAmountInputOpen: false,
    openRegistrationOpenAt: false, openEarlyDeadline: false, openClosedDeadline: false, openDuration: false,
    date: null, teamCount: 1, playerPerTeam: 2,
    tournamentName: '', tournamentNote: '', selectedSportId: null,
    duprEnabled: true, vnduprEnabled: true, minLevel: 'Không giới hạn', maxLevel: 'Không giới hạn',
    locationKeyword: '', selectedLocation: null, competitionLocations: [],
    genderPolicy: 3, ageGroup: 1,
    registrationOpenAt: null, earlyRegistrationDeadline: null, registrationClosedAt: null, duration: durationOptions[durationOptions.length - 1].value,
    feeType: 'pair', standardFeeAmount: 100000, feeAmountInput: 100000,
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

    // Player Limits
    genderPolicy.value = initialStates.genderPolicy;
    ageGroup.value = initialStates.ageGroup;

    // Registration
    registrationOpenAt.value = initialStates.registrationOpenAt;
    earlyRegistrationDeadline.value = initialStates.earlyRegistrationDeadline;
    registrationClosedAt.value = initialStates.registrationClosedAt;
    duration.value = initialStates.duration;

    // Fee
    feeType.value = initialStates.feeType;
    standardFeeAmount.value = initialStates.standardFeeAmount;
    feeAmountInput.value = initialStates.feeAmountInput;

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

    // Giới hạn người chơi
    if (exceptRef !== openGender) openGender.value = false
    if (exceptRef !== openAge) openAge.value = false

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
const toggleOpenGender = () => {
    const currentState = openGender.value
    closeOtherDropdowns(openGender)
    openGender.value = !currentState
}

const toggleOpenAge = () => {
    const currentState = openAge.value
    closeOtherDropdowns(openAge)
    openAge.value = !currentState
}

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

    // Cập nhật giá trị input khi mở
    if (isFeeAmountInputOpen.value) {
        feeAmountInput.value = standardFeeAmount.value
    } else {
        // Cập nhật standardFeeAmount khi đóng
        updateStandardFeeAmount()
    }
}

const updateStandardFeeAmount = () => {
    const numericValue = parseFloat(feeAmountInput.value)
    if (!isNaN(numericValue) && numericValue >= 0) {
        standardFeeAmount.value = Math.round(numericValue)
    } else {
        // Đặt lại 0 nếu không hợp lệ
        standardFeeAmount.value = 0
    }
    isFeeAmountInputOpen.value = false
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

const selectGender = (value) => {
    genderPolicy.value = value
    openGender.value = false
}

const selectAge = (value) => {
    ageGroup.value = value
    openAge.value = false
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
        age_group: ageGroup.value,
        gender_policy: genderPolicy.value,
        participants: "team", // sau này sẽ mở rộng thêm user tạm fix cứng là team
        max_team: teamCount.value,
        player_per_team: playerPerTeam.value,
        fee: feeType.value,
        standard_fee_amount: feeType.value === 'free' ? 0 : standardFeeAmount.value,
        is_private: isPrivate.value,
        creator_join: creatorJoin.value,
        description: tournamentNote.value || null,
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

    // Giới hạn người chơi
    ageGroup.value = data.age_group ?? 1;
    genderPolicy.value = data.gender_policy ?? 3;

    // Người tham gia
    teamCount.value = data.max_team || 1;
    playerPerTeam.value = data.player_per_team || 2;

    // Phí giải đấu
    if (data.fee === 'free') {
        feeType.value = 'free';
        standardFeeAmount.value = 0;
        feeAmountInput.value = 0;
    } else {
        feeType.value = 'pair';
        standardFeeAmount.value = Number(data.standard_fee_amount) || standardFeeAmount.value;
        feeAmountInput.value = Number(data.standard_fee_amount) || standardFeeAmount.value;
    }

    // Quyền riêng tư
    isPrivate.value = !!data.is_private ?? false;

    // Creator join
    creatorJoin.value = !!data.creator_join ?? false;
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