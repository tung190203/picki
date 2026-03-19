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
                            <input ref="posterInputRef" type="file" accept="image/*" class="hidden"
                                @change="handlePosterUpload">

                            <button v-if="posterPreview" @click.stop="clearPoster"
                                class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors shadow-lg z-10">
                                <XCircleIcon class="w-2 h-2" />
                            </button>
                        </div>
                        <div class="flex-1 space-y-2">
                            <input v-model="tournamentName" type="text" placeholder="Tên kèo đấu (bắt buộc)"
                                class="w-full px-3 py-2 border-b border-[#DCDEE6] focus:outline-none focus:border-[#D72D36] placeholder:text-sm placeholder:text-[#9EA2B3] bg-transparent font-bold text-[16px]" />
                            <input v-model="tournamentNote" type="text" placeholder="Ghi chú: trình độ, lưu ý sân...."
                                class="w-full px-3 py-1 focus:outline-none placeholder:text-[12px] placeholder:text-[#9EA2B3] bg-transparent text-[12px]" />
                        </div>
                    </div>
                </div>

                <!-- Chế độ chơi -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-3">Chế độ chơi</h3>
                    <div class="grid grid-cols-3 gap-3">
                        <!-- Giải trí -->
                        <button @click="handlePlayModeChange(1)" :class="[
                            'flex flex-col items-center justify-center rounded-[8px] border px-3 py-5 min-h-[120px] transition-all',
                            selectedPlayMode === 1
                                ? 'bg-[#D72D36] text-white border-[#D72D36] shadow-md'
                                : 'border-[#DCDEE6] text-gray-700 hover:border-[#D72D36] hover:bg-[#FFF5F5]'
                        ]">
                            <div :class="[
                                'w-12 h-12 rounded-full flex items-center justify-center mb-3 transition-colors',
                                selectedPlayMode === 1 ? 'bg-white' : 'bg-[#FBEAEB]'
                            ]">
                                <!-- sentiment_satisfied icon -->
                                <svg class="w-6 h-6 text-[#D72D36]" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-5-9c.83 0 1.5-.67 1.5-1.5S7.83 8 7 8s-1.5.67-1.5 1.5S6.17 11 7 11zm10 0c.83 0 1.5-.67 1.5-1.5S17.83 8 17 8s-1.5.67-1.5 1.5.67 1.5 1.5 1.5zm-5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z" />
                                </svg>
                            </div>
                            <span class="text-[16px] font-bold tracking-[-0.25px]">Giải trí</span>
                        </button>

                        <!-- Thi đấu -->
                        <button @click="handlePlayModeChange(2)" :class="[
                            'flex flex-col items-center justify-center rounded-[8px] border px-3 py-5 min-h-[120px] transition-all',
                            selectedPlayMode === 2
                                ? 'bg-[#D72D36] text-white border-[#D72D36] shadow-md'
                                : 'border-[#DCDEE6] text-gray-700 hover:border-[#D72D36] hover:bg-[#FFF5F5]'
                        ]">
                            <div :class="[
                                'w-12 h-12 rounded-full flex items-center justify-center mb-3 transition-colors',
                                selectedPlayMode === 2 ? 'bg-white' : 'bg-[#FBEAEB]'
                            ]">
                                <!-- scoreboard icon -->
                                <svg class="w-6 h-6 text-[#D72D36]" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M17.5 13.5H16v-3h1.5v3zM22 4h-5V2h-2v2H9V2H7v2H2v16h20V4zm-2 14H4V8h16v10zm-5.5-9h-5v7h5v-7zm-1.5 5.5H10v-4h3v4z" />
                                </svg>
                            </div>
                            <span class="text-[16px] font-bold tracking-[-0.25px]">Thi đấu</span>
                        </button>

                        <!-- Luyện tập -->
                        <button @click="handlePlayModeChange(3)" :class="[
                            'flex flex-col items-center justify-center rounded-[8px] border px-3 py-5 min-h-[120px] transition-all',
                            selectedPlayMode === 3
                                ? 'bg-[#D72D36] text-white border-[#D72D36] shadow-md'
                                : 'border-[#DCDEE6] text-gray-700 hover:border-[#D72D36] hover:bg-[#FFF5F5]'
                        ]">
                            <div :class="[
                                'w-12 h-12 rounded-full flex items-center justify-center mb-3 transition-colors',
                                selectedPlayMode === 3 ? 'bg-white' : 'bg-[#FBEAEB]'
                            ]">
                                <!-- sports_tennis/padel icon -->
                                <svg class="w-6 h-6 text-[#D72D36]" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M19.52 2.49c-2.34-2.34-6.62-1.87-9.55 1.06-1.6 1.6-2.52 3.87-2.54 5.46-.02 1.58.26 3.89-1.35 5.5l-4.24 4.24 1.42 1.42 4.24-4.24c1.61-1.61 3.92-1.33 5.5-1.35 1.59-.02 3.86-.94 5.46-2.54 2.93-2.93 3.4-7.21 1.06-9.55zm-9.2 9.19c-1.53-1.53-1.05-4.61 1.06-6.72s5.18-2.59 6.72-1.06c1.53 1.54 1.05 4.61-1.06 6.72s-5.18 2.59-6.72 1.06zM18 17c.53 0 1.04.21 1.41.59.78.78.78 2.05 0 2.83-.37.37-.88.58-1.41.58s-1.04-.21-1.41-.59c-.78-.78-.78-2.05 0-2.83.37-.37.88-.58 1.41-.58m0-2c-1.02 0-2.05.39-2.83 1.17-1.56 1.56-1.56 4.09 0 5.66.78.78 1.81 1.17 2.83 1.17s2.05-.39 2.83-1.17c1.56-1.56 1.56-4.09 0-5.66-.78-.78-1.81-1.17-2.83-1.17z" />
                                </svg>
                            </div>
                            <span class="text-[16px] font-bold tracking-[-0.25px]">Luyện tập</span>
                        </button>
                    </div>
                    <p v-if="selectedPlayMode === 2" class="text-[14px] text-[#9EA2B3] italic mt-3">*Kết quả sẽ cập nhật vào hệ thống Picki Rating</p>
                </div>
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide">Thời gian</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="bg-[#EDEEF2] rounded-[4px] overflow-visible relative" @click.stop>
                            <button @click="toggleOpenDate"
                                class="w-full flex items-center justify-between rounded-[4px] px-2 py-1 hover:bg-gray-200 transition-colors">
                                <div class="flex items-center">
                                    <div class="w-9 h-9 flex items-center justify-center">
                                        <CalendarDaysIcon class="w-5 h-5 text-gray-700" />
                                    </div>
                                    <span class="text-sm"
                                        :class="{ 'text-[#BBBFCC]': !formattedDate, 'text-gray-900 font-medium': formattedDate }">
                                        {{ formattedDate || 'Ngày & Giờ' }}
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

                        <div class="bg-[#EDEEF2] rounded-[4px] overflow-visible relative" @click.stop>
                            <button @click="toggleOpenTime"
                                class="w-full flex items-center justify-between rounded-[4px] px-2 py-1 hover:bg-gray-200 transition-colors">
                                <div class="flex items-center">
                                    <div class="w-9 h-9 flex items-center justify-center">
                                        <ClockIcon class="w-5 h-5 text-gray-700" />
                                    </div>
                                    <span class="text-sm"
                                        :class="{ 'text-[#BBBFCC]': !selectedDuration, 'text-gray-900 font-medium': selectedDuration }">
                                        {{ selectedDuration || 'Thời lượng' }}
                                    </span>
                                </div>
                                <ChevronDownIcon class="w-5 h-5 transition-transform text-gray-700"
                                    :class="{ 'rotate-180': openTime }" />
                            </button>

                            <Transition name="fade">
                                <div v-if="openTime"
                                    class="absolute top-full left-0 right-0 mt-2 p-2 z-40 bg-white rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <button v-for="option in durationOptions" :key="option.value"
                                        @click="selectDuration(option)"
                                        class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 rounded block whitespace-nowrap"
                                        :class="{ 'bg-gray-50 font-medium': durationMinutes === option.value }">
                                        {{ option.label }}
                                    </button>
                                </div>
                            </Transition>
                        </div>

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

                        <div class="border border-[#DCDEE6] rounded-[8px] px-3 py-3 bg-white" @click.stop>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <ArrowPathRoundedSquareIcon class="w-5 h-5 text-[#D72D36]" />
                                    <p class="text-sm font-semibold text-[#3E414C]">Thiết lập lặp lại</p>
                                </div>
                                <Toggle v-model="isRepeated" />
                            </div>

                            <div v-if="isRepeated"
                                class="space-y-4 mt-3 animate-in fade-in slide-in-from-top-2 duration-300">
                                <div class="grid grid-cols-4 gap-2">
                                    <button v-for="unit in repeatUnits" :key="unit" @click="repeatUnit = unit"
                                        class="py-2.5 text-sm font-bold rounded-[4px] transition-all border"
                                        :class="repeatUnit === unit ? 'bg-[#D72D36] border-[#D72D36] text-white shadow-md shadow-red-100' : 'bg-white border-gray-200 text-[#838799] hover:border-gray-300'">
                                        {{ unit }}
                                    </button>
                                </div>

                                <div v-if="repeatUnit === 'Tuần'" class="flex justify-between gap-2 p-1 bg-white">
                                    <button v-for="day in daysOfWeek" :key="day.value"
                                        @click="toggleRecurringDay(day.value)"
                                        class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold transition-all border"
                                        :class="recurringWeekDays.includes(day.value) ? 'bg-[#D72D36] text-white border-[#D72D36] shadow-red-100 shadow-md' : 'bg-white text-[#838799] border-gray-200 hover:bg-gray-50'">
                                        {{ day.label }}
                                    </button>
                                </div>

                                <div v-if="repeatUnit === 'Tuần'"
                                    class="bg-[#FFF5F5] border border-[#FBEAEB] px-4 py-2 rounded-[4px] flex items-center justify-center gap-3">
                                    <ArrowPathRoundedSquareIcon class="w-5 h-5 text-[#D72D36]" />
                                    <p class="text-sm font-normal text-[#D72D36]">
                                        Kèo này sẽ tự động tạo vào <span class="font-bold">{{ formattedRepeatTime
                                            }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="bg-white rounded-lg py-2 space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <UsersIcon class="w-5 h-5 text-gray-700" />
                                    <span class="text-gray-700">Số người chơi</span>
                                    <div class="block text-[11px] leading-tight text-gray-500">
                                        Bao gồm cả bạn
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="decreasePlayer" aria-label="Giảm số người chơi"
                                        class="w-6 h-6 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-gray-800">
                                        −
                                    </button>
                                    <span class="text-xl font-semibold w-12 text-center select-none">{{ playerCount
                                    }}</span>
                                    <button type="button" @click="increasePlayer" aria-label="Tăng số người chơi"
                                        class="w-6 h-6 bg-gray-800 text-white rounded hover:bg-gray-700 flex items-center justify-center text-sm select-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-gray-800">
                                        +
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between relative">
                                <div class="flex items-center gap-3">
                                    <LockClosedIcon class="w-5 h-5 text-gray-700" />
                                    <span class="text-gray-700">Quyền riêng tư</span>
                                </div>
                                <button @click="toggleOpenPrivacy" @click.stop
                                    class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
                                    <span class="font-medium">{{ privacy }}</span>
                                    <ChevronRightIcon class="w-5 h-5 transition-transform"
                                        :class="{ 'rotate-90': openPrivacy }" />
                                </button>

                                <div v-if="openPrivacy" @click.stop
                                    class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50">
                                    <button @click="selectPrivacy('Công khai')"
                                        class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg block whitespace-nowrap"
                                        :class="{ 'bg-gray-50 font-medium': privacy === 'Công khai' }">
                                        Công khai
                                        <p class="text-[11px] text-gray-500">Ai cũng có thể tìm thấy và đăng kí tham gia
                                        </p>
                                    </button>
                                    <button @click="selectPrivacy('Riêng tư')"
                                        class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 last:rounded-b-lg block whitespace-nowrap"
                                        :class="{ 'bg-gray-50 font-medium': privacy === 'Riêng tư' }">
                                        Riêng tư
                                        <p class="text-[11px] text-gray-500">Chỉ có những thành viên được mời có thể
                                            thấy và yêu cầu tham gia</p>
                                    </button>
                                </div>
                            </div>

                            <!-- Toggle Phí tham gia -->
                            <div class="flex items-center justify-between relative">
                                <div class="flex items-center gap-3">
                                    <CurrencyDollarIcon class="w-5 h-5 text-gray-700" />
                                    <span class="text-gray-700">Phí tham gia</span>
                                </div>
                                <button type="button" @click="toggleHasFee" :aria-checked="hasFee"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-red-500"
                                    :class="hasFee ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        :class="hasFee ? 'translate-x-6' : 'translate-x-1'" />
                                </button>
                            </div>

                            <!-- Fee details - chỉ hiện khi hasFee = true -->
                            <div v-if="hasFee" class="space-y-4 pl-4 border-l-2 border-red-200 ml-2">
                                <!-- Miễn phí / Có phí -->
                                <!-- <div class="flex gap-2">
                                    <button @click="hasFee = false"
                                        class="flex-1 py-2 rounded text-sm font-medium transition-colors"
                                        :class="!hasFee ? 'bg-[#D72D36] text-white' : 'bg-gray-200 text-gray-700'">
                                        Miễn phí
                                    </button>
                                    <button @click="hasFee = true"
                                        class="flex-1 py-2 rounded text-sm font-medium transition-colors"
                                        :class="hasFee ? 'bg-[#D72D36] text-white' : 'bg-gray-200 text-gray-700'">
                                        Có phí
                                    </button>
                                </div> -->

                                <!-- Chia tiền sân tự động toggle -->
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-700">Chia tiền sân tự động</p>
                                        <p class="text-xs text-gray-500">Tổng tiền / số người tham gia</p>
                                    </div>
                                    <button @click="toggleAutoSplit"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                        :class="autoSplitCourtFee ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                            :class="autoSplitCourtFee ? 'translate-x-6' : 'translate-x-1'" />
                                    </button>
                                </div>

                                <!-- Số tiền input -->
                                <div>
                                    <label class="text-sm text-gray-600 block mb-1" for="fee-amount-input">
                                        {{ autoSplitCourtFee ? 'Tổng tiền sân (VNĐ)' : 'Tiền cố định/người (VNĐ)' }}
                                    </label>
                                    <input id="fee-amount-input" v-model="formattedFeeAmount" @input="handleFeeInput"
                                        type="text" inputmode="numeric" placeholder="Nhập số tiền…"
                                        class="w-full px-3 py-2 border rounded focus:outline-none placeholder:text-sm placeholder:text-[#BBBFCC] bg-[#EDEEF2]" />
                                </div>

                                <!-- Ghi chú -->
                                <div>
                                    <label class="text-sm text-gray-600 block mb-1" for="fee-note-input">Ghi chú</label>
                                    <textarea id="fee-note-input" v-model="paymentNote" rows="2"
                                        placeholder="Thêm ghi chú về chi phí…"
                                        class="w-full px-3 py-2 border rounded focus:outline-none placeholder:text-sm placeholder:text-[#BBBFCC] bg-[#EDEEF2] resize-none"></textarea>
                                </div>

                                <!-- QR Code Upload -->
                                <div>
                                    <label class="text-sm text-gray-600 block mb-1" for="qr-file-input">Mã QR thanh
                                        toán</label>
                                    <button v-if="!qrCodePreview" type="button"
                                        class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-[#D72D36] transition-colors w-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-red-500"
                                        @click="$refs.qrFileInput.click()">
                                        <input id="qr-file-input" type="file" ref="qrFileInput" class="hidden"
                                            accept="image/*" @change="handleQrCodeUpload" />
                                        <div class="flex flex-col items-center">
                                            <ArrowUpTrayIcon class="w-8 h-8 text-gray-400 mb-2" aria-hidden="true" />
                                            <p class="text-sm text-gray-500">Tải ảnh lên</p>
                                            <p class="text-xs text-gray-400">JPG, PNG (tối đa 5MB)</p>
                                        </div>
                                    </button>
                                    <div v-else class="relative">
                                        <img :src="qrCodePreview" alt="QR Code thanh toán"
                                            class="w-32 h-32 object-contain mx-auto rounded-lg border" />
                                        <button type="button" @click="clearQrCode" aria-label="Xóa mã QR"
                                            class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-red-500">
                                            <XMarkIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Cảnh báo khi chọn chia tự động -->
                                <div v-if="autoSplitCourtFee" class="bg-yellow-50 border border-yellow-200 rounded p-3">
                                    <p class="text-sm text-yellow-700">
                                        <span class="font-medium">Lưu ý:</span> Phí sẽ được chia đều theo số người tham
                                        gia thực tế.
                                        Vui lòng chuẩn bị danh sách người tham gia trước khi tạo kèo đấu.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Trình độ & Luật thi đấu buttons -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Button Trình độ -->
                        <button @click="openLevelModal" type="button"
                            class="flex flex-col items-center justify-center rounded-[8px] border border-[#DCDEE6] px-4 py-5 hover:border-[#D72D36] hover:bg-[#FFF5F5] transition-all group">
                            <div
                                class="w-12 h-12 rounded-full bg-[#FBEAEB] flex items-center justify-center mb-3 group-hover:bg-[#D72D36] transition-colors">
                                <svg class="w-6 h-6 text-[#D72D36] group-hover:text-white transition-colors" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <span class="text-[16px] font-bold text-[#3E414C] mb-1">Trình độ</span>
                            <span class="text-[12px] text-[#6B6F80]">{{ minLevel }} - {{ maxLevel }}</span>
                        </button>

                        <!-- Button Luật thi đấu -->
                        <button @click="openRulesModal" type="button"
                            class="flex flex-col items-center justify-center rounded-[8px] border border-[#DCDEE6] px-4 py-5 hover:border-[#D72D36] hover:bg-[#FFF5F5] transition-all group">
                            <div
                                class="w-12 h-12 rounded-full bg-[#FBEAEB] flex items-center justify-center mb-3 group-hover:bg-[#D72D36] transition-colors">
                                <svg class="w-6 h-6 text-[#D72D36] group-hover:text-white transition-colors" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <span class="text-[16px] font-bold text-[#3E414C] mb-1">Luật thi đấu</span>
                            <span class="text-[12px] text-[#6B6F80]">{{ rulesCardSubtitle }}</span>
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide">Cài đặt nâng cao</h3>
                    </div>
                    <div class="space-y-4">
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

                        <div class="flex items-center justify-between">
                            <span class="text-gray-700">Duyệt tự động</span>
                            <button type="button" @click="autoApprove = !autoApprove" :aria-checked="autoApprove"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-red-500"
                                :class="autoApprove ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="autoApprove ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-gray-700">Cho phép người tham gia thêm bạn</span>
                            <button type="button" @click="allowParticipantAddFriends = !allowParticipantAddFriends"
                                :aria-checked="allowParticipantAddFriends"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-red-500"
                                :class="allowParticipantAddFriends ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="allowParticipantAddFriends ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-gray-700">Cho phép hủy kèo</span>
                            <button type="button" @click="allowCancellation = !allowCancellation"
                                :aria-checked="allowCancellation"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-red-500"
                                :class="allowCancellation ? 'bg-[#D72D36]' : 'bg-gray-300'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="allowCancellation ? 'translate-x-6' : 'translate-x-1'" />
                            </button>
                        </div>

                        <div v-if="allowCancellation" class="flex items-center justify-between relative">
                            <span class="text-gray-700">Hạn chót hủy kèo</span>
                            <button @click="toggleOpenLock" @click.stop
                                class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
                                <span class="font-medium">{{ lockCancellationLabel }}</span>
                                <ChevronRightIcon class="w-5 h-5 transition-transform"
                                    :class="{ 'rotate-90': openLock }" />
                            </button>
                            <div v-if="openLock" @click.stop
                                class="absolute right-0 top-full mt-2 bg-white border rounded-lg shadow-lg z-50">
                                <button v-for="lock in lockCancellationOptions" :key="lock.value"
                                    @click="selectLock(lock.value)"
                                    class="px-4 py-2 w-full text-sm text-left hover:bg-gray-100 first:rounded-t-lg last:rounded-b-lg block whitespace-nowrap"
                                    :class="{ 'bg-gray-50 font-medium': lockCancellation === lock.value }">
                                    {{ lock.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: Sidebar (4 cols on desktop) -->
            <div class="space-y-4 lg:col-span-4 lg:order-2">
                <!-- Môn thể thao -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-3">Môn thể thao</h3>
                    <div class="space-y-2">
                        <button v-for="sport in sports" :key="sport.id" @click="selectSport(sport.id)" :class="[
                            'w-full flex items-center gap-3 px-4 py-3 rounded-[8px] border transition-colors',
                            selectedSportId === sport.id
                                ? 'bg-[#D72D36] text-white border-[#D72D36]'
                                : 'border-[#BBBFCC] text-gray-700 hover:border-gray-400'
                        ]">
                            <span class="text-[24px]">{{ getSportIcon(sport.id) }}</span>
                            <span class="text-[14px] font-semibold">{{ sport.name }}</span>
                        </button>
                    </div>
                </div>

                <!-- Thể thức -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-3">Thể thức</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <button v-for="format in formats" :key="format.id" @click="selectFormat(format.id)" :class="[
                            'flex flex-col items-center justify-center rounded-[8px] border px-2 py-3 min-h-[80px] transition-colors',
                            selectedFormat === format.id
                                ? 'bg-[#D72D36] text-white border-[#D72D36]'
                                : 'border-[#BBBFCC] text-gray-700 hover:border-gray-400'
                        ]">
                            <span class="text-[20px] leading-none mb-1">{{ getFormatIcon(format.id) }}</span>
                            <span class="text-[12px] font-semibold text-center">{{ format.name }}</span>
                        </button>
                    </div>
                </div>

                <!-- Tóm tắt kèo đấu -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-5">
                    <h3 class="font-bold text-[#838799] text-[14px] uppercase tracking-wide mb-3">Tóm tắt</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Môn thể thao:</span>
                            <span class="font-semibold text-[#3E414C]">Pickleball</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Chế độ:</span>
                            <span class="font-semibold text-[#3E414C]">{{ getPlayModeName(selectedPlayMode) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Thể thức:</span>
                            <span class="font-semibold text-[#3E414C]">{{ getFormatName(selectedFormat) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Số người:</span>
                            <span class="font-semibold text-[#3E414C]">{{ playerCount }} người</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6B6F80]">Chi phí:</span>
                            <span class="font-semibold text-[#3E414C]">{{ feeAmount > 0 ? formatCurrency(feeAmount) :
                                'Miễn phí' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Nút chọn kèo mẫu -->
                <button type="button" @click="openTemplateModal"
                    class="w-full mb-3 border border-[#D72D36] text-[#D72D36] font-bold py-3.5 rounded-[12px] flex items-center justify-center gap-2 hover:bg-[#FFF5F5] transition-colors">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M19 3H5a2 2 0 0 0-2 2v14l4-3 4 3 4-3 4 3V5a2 2 0 0 0-2-2z" />
                    </svg>
                    <span>Chọn kèo mẫu</span>
                </button>

                <!-- Nút lưu mẫu cài đặt -->
                <button type="button" @click="handleSaveTemplate"
                    class="w-full border border-[#D72D36] text-[#D72D36] font-bold py-3.5 rounded-[12px] flex items-center justify-center gap-2 hover:bg-[#FFF5F5] transition-colors">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M17 3H7a2 2 0 0 0-2 2v14l4-3 4 3 4-3 4 3V5a2 2 0 0 0-2-2h-2z" />
                        <path d="M15 9H9V7h6v2z" fill="#fff" />
                    </svg>
                    <span>Lưu cài đặt này làm mẫu</span>
                </button>

                <!-- Nút hoàn thành -->
                <button @click="handleSubmit" :disabled="isSubmitting"
                    class="w-full mt-3 bg-[#D72D36] text-white font-bold py-4 rounded-[12px] hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ isSubmitting ? 'Đang tạo...' : 'Hoàn thành' }}
                </button>
            </div>
        </div>

        <div v-if="isPointModalOpen"
            class="fixed inset-0 z-[99] flex items-center justify-center bg-gray-600 bg-opacity-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm" @click.stop>
                <h4 class="text-lg font-semibold mb-4">{{ pointModalTitle }}</h4>
                <div class="grid grid-cols-12 gap-2 items-center">
                    <!-- Nút trừ -->
                    <button @click="pointInput = Math.max(minPointValue, pointInput - 1)"
                        class="col-span-2 aspect-square bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 flex items-center justify-center text-2xl font-bold">
                        −
                    </button>

                    <!-- Input -->
                    <input type="text" v-model.number="pointInput"
                        class="col-span-8 text-3xl text-center border-b-2 border-gray-300 focus:border-[#D72D36] outline-none py-2"
                        :min="minPointValue" readonly />

                    <!-- Nút cộng -->
                    <button @click="pointInput++"
                        class="col-span-2 aspect-square bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 flex items-center justify-center text-2xl font-bold">
                        +
                    </button>
                </div>

                <p v-if="pointInputError" class="text-sm text-red-500 mt-2">{{ pointInputError }}</p>
                <div class="flex justify-end gap-3 mt-6">
                    <button @click="closePointModal"
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">
                        Hủy
                    </button>
                    <button @click="handlePointConfirm"
                        class="px-4 py-2 bg-[#D72D36] text-white rounded-lg hover:bg-red-700">
                        Xác nhận
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal chọn kèo mẫu -->
    <div v-if="isTemplateModalOpen"
        class="fixed inset-0 z-[99] flex items-center justify-center bg-gray-600 bg-opacity-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-semibold">Chọn kèo mẫu</h4>
                <button @click="closeTemplateModal" class="text-gray-400 hover:text-gray-600">
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </div>

            <div v-if="isLoadingTemplates" class="py-6 text-center text-sm text-gray-500">
                Đang tải danh sách kèo mẫu...
            </div>
            <div v-else>
                <div v-if="!templates.length" class="py-6 text-center text-sm text-gray-500">
                    Bạn chưa có kèo mẫu nào.
                </div>
                <div v-else class="space-y-3 max-h-80 overflow-y-auto">
                    <button v-for="template in templates" :key="template.id" type="button"
                        @click="applyTemplate(template)"
                        class="w-full flex items-center justify-between px-4 py-3 rounded-[10px] border border-[#DCDEE6] hover:border-[#D72D36] hover:bg-[#FFF5F5] transition-colors">
                        <div class="text-left">
                            <p class="text-[14px] font-semibold text-[#3E414C]">
                                {{ template.name }}
                            </p>
                            <p class="text-[12px] text-[#6B6F80] mt-0.5">
                                Người chơi: {{ template.settings?.max_players ?? '-' }} •
                                Phí: {{ template.settings?.has_fee ? (template.settings?.fee_amount || 0).toLocaleString('vi-VN') + 'đ' : 'Miễn phí' }}
                            </p>
                        </div>
                        <ChevronRightIcon class="w-4 h-4 text-[#D72D36]" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Trình độ -->
    <div v-if="isLevelModalOpen"
        class="fixed inset-0 z-[99] flex items-center justify-center bg-gray-600 bg-opacity-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md" @click.stop>
            <h4 class="text-lg font-semibold mb-4">Chọn trình độ</h4>

            <div class="space-y-5">
                <div class="flex items-center justify-between bg-[#F7F8FA] rounded-[8px] px-3 py-2">
                    <span class="text-sm text-[#6B6F80]">Khoảng trình độ</span>
                    <span class="text-sm font-semibold text-[#3E414C]">
                        {{ isLevelUnlimited ? 'Không giới hạn' : `${levelRangeMin} - ${levelRangeMax}` }}
                    </span>
                </div>

                <div :class="isLevelUnlimited ? 'opacity-60' : ''">
                    <label class="text-sm font-medium text-gray-700 block mb-2">
                        Trình độ tối thiểu: {{ levelRangeMin }}
                    </label>
                    <input
                        v-model.number="levelRangeMin"
                        @input="isLevelUnlimited = false"
                        type="range"
                        :min="LEVEL_MIN"
                        :max="LEVEL_MAX"
                        :step="1"
                        class="w-full accent-[#D72D36]"
                    />
                </div>

                <div :class="isLevelUnlimited ? 'opacity-60' : ''">
                    <label class="text-sm font-medium text-gray-700 block mb-2">
                        Trình độ tối đa: {{ levelRangeMax }}
                    </label>
                    <input
                        v-model.number="levelRangeMax"
                        @input="isLevelUnlimited = false"
                        type="range"
                        :min="LEVEL_MIN"
                        :max="LEVEL_MAX"
                        :step="1"
                        class="w-full accent-[#D72D36]"
                    />
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button
                    @click="setLevelNoLimit"
                    class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg hover:bg-gray-200">
                    Không giới hạn
                </button>
                <button @click="applyLevelModal" class="px-4 py-2 bg-[#D72D36] text-white rounded-lg hover:bg-red-700">
                    Xác nhận
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Luật thi đấu -->
    <div v-if="isRulesModalOpen"
        class="fixed inset-0 z-[99] flex items-center justify-center bg-gray-600 bg-opacity-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md" @click.stop>
            <h4 class="text-lg font-semibold mb-4">Luật thi đấu</h4>

            <div class="space-y-4">
                <!-- Áp dụng luật -->
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <label class="text-sm font-medium text-gray-700">Áp dụng luật thi đấu</label>
                    <Toggle v-model="applyRule" />
                </div>

                <template v-if="applyRule">
                    <!-- Số set đấu -->
                    <div>
                        <label class="text-sm font-medium text-gray-700 block mb-2">Số set đấu</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="set in setOptions" :key="set.value" @click="selectSet(set.value)"
                                class="py-2 text-sm font-medium rounded-[4px] transition-all border"
                                :class="setNumber === set.value ? 'bg-[#D72D36] border-[#D72D36] text-white' : 'bg-white border-gray-200 text-gray-700 hover:border-gray-300'">
                                {{ set.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Điểm kết thúc mỗi trận -->
                    <div>
                        <label class="text-sm font-medium text-gray-700 block mb-2">Điểm kết thúc mỗi trận</label>
                        <div class="flex items-center gap-2">
                            <button @click="gamesPerSet = Math.max(1, gamesPerSet - 1)"
                                class="w-10 h-10 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 flex items-center justify-center text-xl font-bold">
                                −
                            </button>
                            <input type="number" v-model.number="gamesPerSet"
                                class="flex-1 text-2xl text-center border-b-2 border-gray-300 focus:border-[#D72D36] outline-none py-2"
                                min="1" />
                            <button @click="gamesPerSet++"
                                class="w-10 h-10 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 flex items-center justify-center text-xl font-bold">
                                +
                            </button>
                        </div>
                    </div>

                    <!-- Quy tắc thắng -->
                    <div>
                        <label class="text-sm font-medium text-gray-700 block mb-2">Quy tắc thắng</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button v-for="rule in winRuleOptions" :key="rule.value" @click="selectWinRule(rule.value)"
                                class="py-2 text-sm font-medium rounded-[4px] transition-all border"
                                :class="pointsDifference === rule.value ? 'bg-[#D72D36] border-[#D72D36] text-white' : 'bg-white border-gray-200 text-gray-700 hover:border-gray-300'">
                                {{ rule.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Điểm tối đa -->
                    <div>
                        <label class="text-sm font-medium text-gray-700 block mb-2">Điểm tối đa</label>
                        <div class="flex items-center gap-2">
                            <button @click="maxPoints = Math.max(1, maxPoints - 1)"
                                class="w-10 h-10 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 flex items-center justify-center text-xl font-bold">
                                −
                            </button>
                            <input type="number" v-model.number="maxPoints"
                                class="flex-1 text-2xl text-center border-b-2 border-gray-300 focus:border-[#D72D36] outline-none py-2"
                                min="1" />
                            <button @click="maxPoints++"
                                class="w-10 h-10 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 flex items-center justify-center text-xl font-bold">
                                +
                            </button>
                        </div>
                    </div>
                </template>

                <div v-else class="text-center py-4 text-gray-500 text-sm">
                    Không áp dụng luật thi đấu. Kết quả sẽ được nhập tự do.
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button @click="closeRulesModal" class="px-4 py-2 bg-[#D72D36] text-white rounded-lg hover:bg-red-700">
                    Xác nhận
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import VueDatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import { vi } from 'date-fns/locale'
import { ChevronDownIcon, ChevronRightIcon, XCircleIcon, XMarkIcon } from "@heroicons/vue/24/solid";
import { CalendarDaysIcon, ClockIcon, MapPinIcon, UsersIcon, LockClosedIcon, CurrencyDollarIcon, ArrowUpTrayIcon, ArrowPathRoundedSquareIcon } from "@heroicons/vue/24/outline";
import Toggle from '@/components/atoms/Toggle.vue'
import * as MiniTournamentService from '@/service/miniTournament'
import * as SportService from '@/service/sport'
import * as CompetitionLocationService from '@/service/competitionLocation'
import { toast } from 'vue3-toastify'
import { FreeMode, Mousewheel } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/free-mode'
import { genderOptions } from '@/constants/genderOption';
import { playModes, formats } from '@/constants/playModeAndFormat';
import { setOptions } from '@/constants/setOption';
import { winRuleOptions } from '@/constants/winRuleOption';
import { lockCancellationOptions } from '@/constants/lockCancellationOption';
import { durationOptions } from '@/constants/durationOption';
import { useFormattedDate } from '@/composables/formatedDate'
import { useRoute, useRouter } from 'vue-router'
const modules = [FreeMode, Mousewheel]
const router = useRouter()
const route = useRoute()
const miniTournamentId = route.params.id || null
const isEditMode = computed(() => !!miniTournamentId)
const btnTitle = computed(() => isEditMode.value ? 'Chỉnh sửa kèo đấu' : 'Tạo kèo đấu');
const getPlayModeIcon = (modeId) => {
    // Return SVG or icon class based on mode
    if (modeId === 1) return 'image.png' // Giải trí
    if (modeId === 2) return 'image.png' // Thi đấu
    if (modeId === 3) return '⚡' // Luyện tập
    return '•'
}

// =================================================================================
// Refs and State (Existing)
// =================================================================================
const openDate = ref(false)
const openTime = ref(false)
const openPrivacy = ref(false)
const openFee = ref(false)
const openMinLevel = ref(false)
const openMaxLevel = ref(false)
const openRepeat = ref(false)
const isLevelModalOpen = ref(false)
const isRulesModalOpen = ref(false)
const date = ref(null)
const durationMinutes = ref(null)
const selectedDuration = ref('')
// Số người chơi tối đa (bao gồm cả bạn), mặc định 4
const playerCount = ref(4)
const privacy = ref('Công khai')

// Fee fields - new structure
const hasFee = ref(false)
const autoSplitCourtFee = ref(false)
const paymentNote = ref('')
const qrCodeImage = ref(null)
const qrCodePreview = ref(null)
const qrCodeFile = ref(null) // File object for upload
const isSubmitting = ref(false)
const qrFileInput = ref(null)

// Legacy fee fields
const fee = ref('none')
const feeAmount = ref(0)
const formattedFeeAmount = ref('')

const sports = ref([])
// Chỉ có 1 môn Pickleball, mặc định sport_id = 1
const selectedSportId = ref(1)
const tournamentName = ref('')
const tournamentNote = ref('')

const minLevel = ref('Không giới hạn')
const maxLevel = ref('Không giới hạn')
const selectedPlayMode = ref(1)
const selectedFormat = ref(2)
const autoApprove = ref(true)
const allowParticipantAddFriends = ref(true)
const { formattedDate } = useFormattedDate(date)
const posterFile = ref(null)
const posterPreview = ref(null)
const posterImage = ref(null)
const posterInputRef = ref(null)

// =================================================================================
// REFS CHO PHẦN TÌM KIẾM ĐỊA ĐIỂM
// =================================================================================
const locationKeyword = ref('')
const competitionLocations = ref([])
const selectedLocation = ref(null)
const isLocationDropdownOpen = ref(false)

// =================================================================================
// New Refs and Consts for Tournament Rules and Advanced Settings
// =================================================================================

// Luật thi đấu
const setNumber = ref(1)
const gamesPerSet = ref(11)
const pointsDifference = ref(2)
const maxPoints = ref(11)
const applyRule = ref(true)

/** Dòng mô tả dưới ô "Luật thi đấu" trên form (không hiện set/điểm khi đã tắt áp dụng luật) */
const rulesCardSubtitle = computed(() => {
    if (!applyRule.value) return 'Không áp dụng luật'
    return `${setNumber.value} set, ${gamesPerSet.value} điểm`
})

const openSet = ref(false)
const openWinRule = ref(false)
const winRuleLabel = computed(() => {
    return winRuleOptions.find(r => r.value === pointsDifference.value)?.label || 'Cách biệt 2 điểm'
})

const isPointModalOpen = ref(false)
const pointModalTitle = ref('')
const pointInputType = ref('')
const pointInput = ref(0)
const pointInputError = ref('')
const isTemplateModalOpen = ref(false)
const templates = ref([])
const isLoadingTemplates = ref(false)
const LEVEL_MIN = 1
const LEVEL_MAX = 8
const levelRangeMin = ref(LEVEL_MIN)
const levelRangeMax = ref(LEVEL_MAX)
const isLevelUnlimited = ref(true)

const minPointValue = computed(() => {
    if (pointInputType.value === 'max_points') {
        return gamesPerSet.value > 0 ? gamesPerSet.value : 1
    }
    return 1
})

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


const genderPolicy = ref(3)
const isRepeated = ref(false)
const repeatUnit = ref('Tuần')
const recurringWeekDays = ref([])
const lockCancellation = ref(1)
const allowCancellation = ref(true)

const openGender = ref(false)
const openLock = ref(false)

const genderLabel = computed(() => genderOptions.find(g => g.value === genderPolicy.value)?.label || 'Không giới hạn')
const lockCancellationLabel = computed(() => {
    return lockCancellation.value === 0
        ? 'Không có'
        : lockCancellationOptions.find(l => l.value === lockCancellation.value)?.label || 'Không có'
})
const repeatUnits = ['Tuần', 'Tháng', 'Quý', 'Năm']
const daysOfWeek = [
    { label: 'T2', value: 1 },
    { label: 'T3', value: 2 },
    { label: 'T4', value: 3 },
    { label: 'T5', value: 4 },
    { label: 'T6', value: 5 },
    { label: 'T7', value: 6 },
    { label: 'CN', value: 0 },
]
const formattedRepeatTime = computed(() => {
    const selected = daysOfWeek
        .filter((d) => recurringWeekDays.value.includes(d.value))
        .map((d) => d.label)
        .join('-')
    const d = date.value ? new Date(date.value) : null
    const hh = d ? String(d.getHours()).padStart(2, '0') : '--'
    const mm = d ? String(d.getMinutes()).padStart(2, '0') : '--'
    return `${hh}:${mm} ${selected || 'Chưa chọn ngày'} hàng tuần`
})

// Định nghĩa trạng thái ban đầu để reset form
const initialStates = {
    openDate: false, openTime: false, openPrivacy: false, openFee: false, openMinLevel: false, openMaxLevel: false,
    openSet: false, openWinRule: false, openGender: false, openLock: false,
    isLocationDropdownOpen: false, isPointModalOpen: false,
    date: null, durationMinutes: null, selectedDuration: '', playerCount: 4, privacy: 'Công khai',
    fee: 'none', feeAmount: 0, formattedFeeAmount: '',
    tournamentName: '', tournamentNote: '', selectedPlayMode: 1, selectedFormat: 2, selectedSportId: 1,
    minLevel: 'Không giới hạn', maxLevel: 'Không giới hạn',
    locationKeyword: '', selectedLocation: null, competitionLocations: [],
    setNumber: 1, gamesPerSet: 11, pointsDifference: 2, maxPoints: 11,
    genderPolicy: 3, isRepeated: false, repeatUnit: 'Tuần', recurringWeekDays: [], lockCancellation: 1,
    allowCancellation: true, autoApprove: true, allowParticipantAddFriends: true,
};

const resetFormState = () => {
    // Cập nhật tất cả refs về trạng thái ban đầu
    date.value = initialStates.date;
    durationMinutes.value = initialStates.durationMinutes;
    selectedDuration.value = initialStates.selectedDuration;
    playerCount.value = initialStates.playerCount;
    privacy.value = initialStates.privacy;
    fee.value = initialStates.fee;
    feeAmount.value = initialStates.feeAmount;
    formattedFeeAmount.value = initialStates.formattedFeeAmount;
    tournamentName.value = initialStates.tournamentName;
    tournamentNote.value = initialStates.tournamentNote;
    minLevel.value = initialStates.minLevel;
    maxLevel.value = initialStates.maxLevel;
    locationKeyword.value = initialStates.locationKeyword;
    selectedLocation.value = initialStates.selectedLocation;
    setNumber.value = initialStates.setNumber;
    gamesPerSet.value = initialStates.gamesPerSet;
    pointsDifference.value = initialStates.pointsDifference;
    maxPoints.value = initialStates.maxPoints;
    genderPolicy.value = initialStates.genderPolicy;
    isRepeated.value = initialStates.isRepeated;
    repeatUnit.value = initialStates.repeatUnit;
    recurringWeekDays.value = [...initialStates.recurringWeekDays];
    lockCancellation.value = initialStates.lockCancellation;
    allowCancellation.value = initialStates.allowCancellation;
    autoApprove.value = initialStates.autoApprove;
    allowParticipantAddFriends.value = initialStates.allowParticipantAddFriends;
    competitionLocations.value = initialStates.competitionLocations;
    isLocationDropdownOpen.value = initialStates.isLocationDropdownOpen;
    qrCodeImage.value = null;
    qrCodePreview.value = null;
    qrCodeFile.value = null;
    if (posterInputRef.value) {
        posterInputRef.value.value = '';
    }
    posterImage.value = null;
    posterPreview.value = null;
    posterFile.value = null;
    // Đảm bảo chọn lại môn thể thao đầu tiên (Pickleball id=1)
    selectedSportId.value = 1;
    // Đóng tất cả dropdown UI
    closeOtherDropdowns(null);
};
// =================================================================================
// Global Dropdown/Modal Handlers
// =================================================================================

const closeOtherDropdowns = (exceptRef) => {
    // Chỉ đóng nếu không phải là ngoại lệ được truyền vào
    if (exceptRef !== openDate) openDate.value = false
    if (exceptRef !== openTime) openTime.value = false
    if (exceptRef !== openPrivacy) openPrivacy.value = false
    if (exceptRef !== openFee) openFee.value = false
    if (exceptRef !== openMinLevel) openMinLevel.value = false
    if (exceptRef !== openMaxLevel) openMaxLevel.value = false
    if (exceptRef !== openSet) openSet.value = false
    if (exceptRef !== openWinRule) openWinRule.value = false
    if (exceptRef !== openGender) openGender.value = false
    if (exceptRef !== openLock) openLock.value = false
    // Đóng dropdown địa điểm
    if (exceptRef !== isLocationDropdownOpen) isLocationDropdownOpen.value = false
}

// Hàm xử lý click bên ngoài để đóng dropdown (trừ modal điểm)
const handleClickOutside = (event) => {
    if (isPointModalOpen.value) return;
    // Nếu event không bị dừng lan truyền bởi @click.stop trên các element tương tác, thì đóng tất cả.
    closeOtherDropdowns(null);
}

// Toggles (Đã được cập nhật để sử dụng closeOtherDropdowns)
const toggleOpenDate = () => {
    const currentState = openDate.value
    closeOtherDropdowns(openDate)
    openDate.value = !currentState
}

const toggleOpenTime = () => {
    const currentState = openTime.value
    closeOtherDropdowns(openTime)
    openTime.value = !currentState
}

const toggleOpenPrivacy = () => {
    const currentState = openPrivacy.value
    closeOtherDropdowns(openPrivacy)
    openPrivacy.value = !currentState
}

const toggleOpenFee = () => {
    const currentState = openFee.value
    closeOtherDropdowns(openFee)
    openFee.value = !currentState
}

// Toggle hasFee - khi tắt thì reset toàn bộ fee fields
const toggleHasFee = () => {
    hasFee.value = !hasFee.value
    if (!hasFee.value) {
        // Reset all fee related fields
        autoSplitCourtFee.value = false
        paymentNote.value = ''
        qrCodeImage.value = null
        qrCodePreview.value = null
        qrCodeFile.value = null
        fee.value = 'none'
        feeAmount.value = 0
        formattedFeeAmount.value = ''
    }
}

// Toggle auto split court fee
const toggleAutoSplit = () => {
    autoSplitCourtFee.value = !autoSplitCourtFee.value
    if (autoSplitCourtFee.value) {
        fee.value = 'auto_split'
    } else {
        fee.value = 'per_person'
    }
}

// Handle QR code file upload
const handleQrCodeUpload = (event) => {
    const file = event.target.files[0]
    if (!file) return

    if (file.size > 5 * 1024 * 1024) {
        toast.error('Kích thước ảnh không được quá 5MB')
        return
    }

    qrCodeFile.value = file
    const reader = new FileReader()
    reader.onload = (e) => {
        qrCodePreview.value = e.target.result
        qrCodeImage.value = e.target.result // base64 for preview
    }
    reader.readAsDataURL(file)
}

//handle poster file upload
const handlePosterUpload = (event) => {
    const file = event.target.files[0]
    if (!file) return

    if (file.size > 5 * 1024 * 1024) {
        toast.error('Kích thước ảnh không được quá 5MB')
        return
    }

    posterImage.value = file
    const reader = new FileReader()
    reader.onload = (e) => {
        posterPreview.value = e.target.result
        posterImage.value = e.target.result // base64 for preview
    }
    reader.readAsDataURL(file)
}

//handle max_players
const setPlayerCount = (value) => {
    playerCount.value = value
}

// =================================================================================
// Template helpers
// =================================================================================

const fetchTemplates = async () => {
    isLoadingTemplates.value = true
    try {
        const res = await MiniTournamentService.getMiniTournamentTemplates()
        // Controller trả về ['templates' => $templates]
        templates.value = res?.data?.templates || res?.templates || []
    } catch (error) {
        console.error('Error fetching mini tournament templates:', error)
        const errMessage = error?.response?.data?.message || 'Không tải được danh sách kèo mẫu.'
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
    selectedSportId.value = s.sport_id ?? selectedSportId.value ?? 1
    tournamentName.value = s.name ?? tournamentName.value
    tournamentNote.value = s.description ?? tournamentNote.value

    // Chế độ chơi & thể thức
    if (s.play_mode !== undefined && s.play_mode !== null) {
        selectedPlayMode.value = s.play_mode
    }
    if (s.format !== undefined && s.format !== null) {
        selectedFormat.value = s.format
    }

    // Người chơi & quyền riêng tư
    if (s.max_players) {
        playerCount.value = s.max_players
    }
    if (s.is_private !== undefined) {
        privacy.value = s.is_private ? 'Riêng tư' : 'Công khai'
    }

    // Phí tham gia
    if (s.has_fee !== undefined) {
        hasFee.value = !!s.has_fee
    }
    if (s.auto_split_fee !== undefined) {
        autoSplitCourtFee.value = !!s.auto_split_fee
    }
    if (s.fee_amount !== undefined && s.fee_amount !== null) {
        feeAmount.value = s.fee_amount
        formattedFeeAmount.value = s.fee_amount
            ? Number(s.fee_amount).toLocaleString('vi-VN')
            : ''
    }
    if (s.fee_description !== undefined) {
        paymentNote.value = s.fee_description || ''
    }

    // Trình độ
    if (s.min_rating !== undefined && s.min_rating !== null) {
        minLevel.value = s.min_rating
    }
    if (s.max_rating !== undefined && s.max_rating !== null) {
        maxLevel.value = s.max_rating
    }

    // Luật thi đấu
    if (s.set_number) setNumber.value = s.set_number
    if (s.base_points) gamesPerSet.value = s.base_points
    if (s.points_difference !== undefined && s.points_difference !== null) {
        pointsDifference.value = s.points_difference
    }
    if (s.max_points) maxPoints.value = s.max_points
    if (s.apply_rule !== undefined) {
        applyRule.value = !!s.apply_rule
    }

    // Giới tính & lặp lại
    if (s.gender !== undefined && s.gender !== null) {
        genderPolicy.value = s.gender
    }
    if (s.recurring_schedule !== undefined) {
        applyRecurringScheduleFromData(s.recurring_schedule)
    }

    // Cài đặt nâng cao
    if (s.auto_approve !== undefined) {
        autoApprove.value = !!s.auto_approve
    }
    if (s.allow_participant_add_friends !== undefined) {
        allowParticipantAddFriends.value = !!s.allow_participant_add_friends
    }

    isTemplateModalOpen.value = false
    toast.success('Đã áp dụng kèo mẫu')
}

// Clear poster
const clearPoster = () => {
    posterImage.value = null
    posterPreview.value = null
    posterFile.value = null
    if (posterInputRef.value) posterInputRef.value.value = ''
}

// Clear QR code
const clearQrCode = () => {
    qrCodeImage.value = null
    qrCodePreview.value = null
    qrCodeFile.value = null
    if (qrFileInput.value) qrFileInput.value.value = ''
}

// Chọn thể thức (format) - 1: Đánh đơn, 2: Đánh đôi
const selectFormat = (formatId) => {
    selectedFormat.value = formatId
}

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
    // DUPR tự động theo play_mode, không cần toggle riêng
}

const toggleVNDUPR = () => {
    // VNDUPR tự động theo play_mode, không cần toggle riêng
}

// Computed để kiểm tra có cho phép chỉnh sửa DUPR/VNDUPR không
const canEditDuprSettings = computed(() => selectedPlayMode.value === 2)

// Khi play_mode thay đổi, tự động set DUPR/VNDUPR
const handlePlayModeChange = (mode) => {
    selectedPlayMode.value = mode

    // Giữ mặc định thể thức đánh đôi khi thay đổi play_mode
    selectedFormat.value = 2
}

const toggleOpenSet = () => {
    const currentState = openSet.value
    closeOtherDropdowns(openSet)
    openSet.value = !currentState
}

const toggleOpenWinRule = () => {
    const currentState = openWinRule.value
    closeOtherDropdowns(openWinRule)
    openWinRule.value = !currentState
}

const toggleOpenGender = () => {
    const currentState = openGender.value
    closeOtherDropdowns(openGender)
    openGender.value = !currentState
}

const toggleOpenLock = () => {
    const currentState = openLock.value
    closeOtherDropdowns(openLock)
    openLock.value = !currentState
}

// =================================================================================
// Select Handlers (Giữ nguyên)
// =================================================================================
const decreasePlayer = () => {
    // Tối thiểu 2 người chơi (bao gồm cả bạn)
    if (playerCount.value > 2) {
        playerCount.value--
    }
}

const increasePlayer = () => {
    // Có thể thêm giới hạn tối đa nếu BE yêu cầu (ví dụ 32)
    playerCount.value++
}

const selectPrivacy = (value) => {
    privacy.value = value
    openPrivacy.value = false
}

const selectFee = (value) => {
    fee.value = value
    openFee.value = false
}

const selectDuration = (option) => {
    durationMinutes.value = option.value
    selectedDuration.value = option.label
    openTime.value = false
}

const handleFeeInput = (event) => {
    let value = event.target.value.replaceAll(/[^\d]/g, '')
    feeAmount.value = value ? Number.parseInt(value) : 0
    if (value) {
        formattedFeeAmount.value = Number.parseInt(value).toLocaleString('vi-VN')
    } else {
        formattedFeeAmount.value = ''
    }
}

const selectSet = (value) => {
    setNumber.value = value
    openSet.value = false
}

const selectWinRule = (value) => {
    pointsDifference.value = value
    openWinRule.value = false
}

const selectGender = (value) => {
    genderPolicy.value = value
    openGender.value = false
}

const toggleRecurringDay = (day) => {
    const idx = recurringWeekDays.value.indexOf(day)
    if (idx === -1) recurringWeekDays.value.push(day)
    else recurringWeekDays.value.splice(idx, 1)
}

const selectLock = (value) => {
    lockCancellation.value = value
    openLock.value = false
}

const selectLocation = (location) => {
    selectedLocation.value = location
    locationKeyword.value = location.name
    isLocationDropdownOpen.value = false
}

// =================================================================================
// Point Modal Logic (Giữ nguyên)
// =================================================================================

const openPointModal = (type) => {
    closeOtherDropdowns(null)
    pointInputType.value = type
    pointInputError.value = ''

    if (type === 'games_per_set') {
        pointModalTitle.value = 'Điểm kết thúc mỗi trận'
        pointInput.value = gamesPerSet.value
    } else if (type === 'max_points') {
        pointModalTitle.value = 'Điểm tối đa'
        pointInput.value = maxPoints.value
    }

    isPointModalOpen.value = true
}

const closePointModal = () => {
    isPointModalOpen.value = false
    pointInputError.value = ''
}

const openLevelModal = () => {
    const parseLevel = (value, fallback) => {
        if (value === 'Không giới hạn' || value === null || value === undefined || value === '') return fallback
        const parsed = Number.parseInt(String(value), 10)
        if (Number.isNaN(parsed)) return fallback
        return Math.min(LEVEL_MAX, Math.max(LEVEL_MIN, parsed))
    }

    const hasLimit = minLevel.value !== 'Không giới hạn' && maxLevel.value !== 'Không giới hạn'
    isLevelUnlimited.value = !hasLimit
    levelRangeMin.value = parseLevel(minLevel.value, LEVEL_MIN)
    levelRangeMax.value = parseLevel(maxLevel.value, LEVEL_MAX)
    if (levelRangeMin.value > levelRangeMax.value) {
        levelRangeMin.value = levelRangeMax.value
    }
    isLevelModalOpen.value = true
}

const closeLevelModal = () => {
    isLevelModalOpen.value = false
}

watch(levelRangeMin, (val) => {
    if (val > levelRangeMax.value) {
        levelRangeMax.value = val
    }
})

watch(levelRangeMax, (val) => {
    if (val < levelRangeMin.value) {
        levelRangeMin.value = val
    }
})

const setLevelNoLimit = () => {
    minLevel.value = 'Không giới hạn'
    maxLevel.value = 'Không giới hạn'
    isLevelUnlimited.value = true
    closeLevelModal()
}

const applyLevelModal = () => {
    if (isLevelUnlimited.value) {
        minLevel.value = 'Không giới hạn'
        maxLevel.value = 'Không giới hạn'
    } else {
        minLevel.value = String(levelRangeMin.value)
        maxLevel.value = String(levelRangeMax.value)
    }
    closeLevelModal()
}

const openRulesModal = () => {
    isRulesModalOpen.value = true
}

const closeRulesModal = () => {
    isRulesModalOpen.value = false
}

const handlePointConfirm = () => {
    if (pointInput.value < minPointValue.value) {
        pointInputError.value = `Điểm tối thiểu phải là ${minPointValue.value}.`
        return
    }

    if (pointInputType.value === 'games_per_set') {
        gamesPerSet.value = pointInput.value
        if (maxPoints.value < gamesPerSet.value) {
            maxPoints.value = gamesPerSet.value
        }
    } else if (pointInputType.value === 'max_points') {
        maxPoints.value = pointInput.value
    }

    closePointModal()
}

// Build settings object for saving template
const buildTemplateSettings = () => {
    const ruleSettings = applyRule.value
        ? {
            set_number: setNumber.value,
            base_points: gamesPerSet.value,
            points_difference: pointsDifference.value,
            max_points: maxPoints.value,
        }
        : {
            set_number: null,
            base_points: null,
            points_difference: null,
            max_points: null,
        }

    return {
        sport_id: selectedSportId.value,
        name: tournamentName.value,
        description: tournamentNote.value || null,
        play_mode: selectedPlayMode.value,
        format: selectedFormat.value,
        max_players: playerCount.value,
        is_private: privacy.value === 'Riêng tư',
        has_fee: hasFee.value,
        auto_split_fee: autoSplitCourtFee.value,
        fee_amount: hasFee.value ? feeAmount.value : null,
        fee_description: paymentNote.value || null,
        min_rating: minLevel.value,
        max_rating: maxLevel.value,
        ...ruleSettings,
        gender: genderPolicy.value,
        recurring_schedule: buildRecurringSchedule(),
        apply_rule: applyRule.value,
        allow_cancellation: allowCancellation.value,
        cancellation_duration: allowCancellation.value ? getCancellationDuration() : null,
        auto_approve: autoApprove.value,
        allow_participant_add_friends: allowParticipantAddFriends.value,
    }
}

const handleSaveTemplate = async () => {
    if (isSubmitting.value) return
    isSubmitting.value = true
    try {
        const settings = buildTemplateSettings()
        const payload = {
            name: tournamentName.value || 'Kèo mẫu Picki',
            settings,
        }
        const res = await MiniTournamentService.saveMiniTournamentTemplate(payload)
        const message = res?.message || 'Đã lưu cài đặt này làm mẫu'
        toast.success(message)
    } catch (error) {
        console.error('Error saving mini tournament template:', error)
        const errMessage = error?.response?.data?.message || 'Lưu mẫu thất bại. Vui lòng thử lại.'
        toast.error(errMessage)
    } finally {
        isSubmitting.value = false
    }
}


// =================================================================================
// Computed and Submit
// =================================================================================

const buildRecurringSchedule = () => {
    if (!isRepeated.value) return null
    if (!date.value) return null

    const periodMap = {
        'Tuần': 'weekly',
        'Tháng': 'monthly',
        'Quý': 'quarterly',
        'Năm': 'yearly',
    }
    const period = periodMap[repeatUnit.value] || 'weekly'
    const startDate = new Date(date.value)

    if (period === 'weekly') {
        const weekDays = recurringWeekDays.value.length
            ? [...recurringWeekDays.value]
            : [startDate.getDay()]
        return {
            period: 'weekly',
            week_days: weekDays,
            recurring_date: null,
        }
    }

    const recurringDate = `${startDate.getFullYear()}-${String(startDate.getMonth() + 1).padStart(2, '0')}-${String(startDate.getDate()).padStart(2, '0')}`
    return {
        period,
        week_days: null,
        recurring_date: recurringDate,
    }
}

const getCancellationDuration = () => {
    const minutesMap = {
        1: 30,    // 30 phút
        2: 60,    // 1 giờ
        3: 120,   // 2 giờ
        4: 180,   // 3 giờ
        5: 240,   // 4 giờ
        6: 360,   // 6 giờ
        7: 720,   // 12 giờ
        8: 1440,  // 24 giờ
    }
    return minutesMap[lockCancellation.value] || null
}

const handleSubmit = async () => {
    if (isSubmitting.value) return
    isSubmitting.value = true
    try {
        // Nếu kèo có thu phí nhưng không có QR mới và cũng không có QR cũ => bắt buộc upload
        if (hasFee.value && !qrCodeFile.value && !qrCodeImage.value) {
            toast.error('Vui lòng tải ảnh mã QR thanh toán lên')
            return
        }
        let startTime = null;
        if (date.value) {
            const d = new Date(date.value);

            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            const hours = String(d.getHours()).padStart(2, '0');
            const minutes = String(d.getMinutes()).padStart(2, '0');
            const seconds = String(d.getSeconds()).padStart(2, '0');

            startTime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        }

        const getNumericLevel = (level) => {
            if (level === 'Không giới hạn') return null
            return Number.parseFloat(level)
        }

        const rulePayload = applyRule.value
            ? {
                set_number: setNumber.value,
                base_points: gamesPerSet.value,
                points_difference: pointsDifference.value,
                max_points: maxPoints.value,
            }
            : {
                set_number: null,
                base_points: null,
                points_difference: null,
                max_points: null,
            }

        const data = {
            sport_id: selectedSportId.value,
            name: tournamentName.value,
            description: tournamentNote.value || null,
            play_mode: selectedPlayMode.value,
            format: selectedFormat.value,
            start_time: startTime,
            duration: durationMinutes.value,
            competition_location_id: selectedLocation.value ? selectedLocation.value?.id : null,
            max_players: playerCount.value,
            is_private: privacy.value === 'Riêng tư',

            has_fee: hasFee.value,
            auto_split_fee: autoSplitCourtFee.value,
            fee_description: paymentNote.value || null,
            fee_amount: hasFee.value ? feeAmount.value : null,

            min_rating: getNumericLevel(minLevel.value),
            max_rating: getNumericLevel(maxLevel.value),
            ...rulePayload,
            gender: genderPolicy.value,
            recurring_schedule: buildRecurringSchedule(),
            apply_rule: applyRule.value,
            allow_cancellation: allowCancellation.value,
            cancellation_duration: allowCancellation.value ? getCancellationDuration() : null,
            auto_approve: autoApprove.value,
            allow_participant_add_friends: allowParticipantAddFriends.value,
            status: 1,
            invite_user: []
        }

        if (isEditMode.value) {
            await updateMiniTournament(miniTournamentId, data)
        } else {
            // Khi có file QR code, dùng FormData để gửi multipart/form-data
            const payload = qrCodeFile.value
                ? buildFormDataWithFile(data)
                : { ...data, qr_code_url: qrCodeImage.value || null }
            await createMiniTournament(payload)
        }
    } finally {
        isSubmitting.value = false
    }
}

const buildFormDataWithFile = (data) => {
    const formData = new FormData()
    Object.entries(data).forEach(([key, value]) => {
        if (value === null || value === undefined) return
        if (key === 'recurring_schedule' && typeof value === 'object') {
            formData.append('recurring_schedule[period]', value.period)
            if (Array.isArray(value.week_days) && value.week_days.length) {
                value.week_days.forEach((day, index) => {
                    formData.append(`recurring_schedule[week_days][${index}]`, day)
                })
            }
            if (value.recurring_date) {
                formData.append('recurring_schedule[recurring_date]', value.recurring_date)
            }
        } else if (key === 'invite_user' && Array.isArray(value)) {
            value.forEach((id) => formData.append('invite_user[]', id))
        } else if (typeof value === 'boolean') {
            formData.append(key, value ? '1' : '0')
        } else {
            formData.append(key, value)
        }
    })
    formData.append('qr_code_url', qrCodeFile.value)
    return formData
}

const updateMiniTournament = async (id, data) => {
    try {
        await MiniTournamentService.updateMiniTournament(id, data)
        toast.success('Cập nhật kèo đấu thành công.')
        setTimeout(() => {
            router.push({ name: 'mini-tournament-detail', params: { id: miniTournamentId } })
        }, 1000)
    } catch (error) {
        console.error('Error updating mini tournament:', error)
        toast.error(getApiErrorMessage(error, 'Cập nhật kèo đấu thất bại.'))
    }
}

const createMiniTournament = async (data) => {
    try {
        const res = await MiniTournamentService.storeMiniTournament(data)
        toast.success('Tạo kèo đấu thành công.')
        resetFormState()
        if (res && res.id) {
            setTimeout(() => {
                router.push({ name: 'mini-tournament-detail', params: { id: res.id } })
            }, 1000)
        }
    } catch (error) {
        console.error('Error creating mini tournament:', error)
        toast.error(getApiErrorMessage(error, 'Tạo kèo đấu thất bại.'))
    }
}

const fetchSports = async () => {
    try {
        const res = await SportService.getAllSports()
        sports.value = res
        // BE hiện tại chỉ dùng Pickleball, mặc định sport_id = 1
        selectedSportId.value = 1
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
        const rawLocations = res?.data?.competition_locations
        let normalizedLocations = []
        if (Array.isArray(rawLocations)) {
            normalizedLocations = rawLocations
        } else if (Array.isArray(rawLocations?.data)) {
            normalizedLocations = rawLocations.data
        }

        competitionLocations.value = normalizedLocations
        isLocationDropdownOpen.value = competitionLocations.value.length > 0
    } catch (error) {
        console.error('Error fetching competition locations:', error)
        competitionLocations.value = []
        isLocationDropdownOpen.value = false
    }
}

const getApiErrorMessage = (error, fallback = 'Có lỗi xảy ra.') => {
    const validationErrors = error?.response?.data?.errors
    if (validationErrors && typeof validationErrors === 'object') {
        const firstFieldErrors = Object.values(validationErrors)[0]
        if (Array.isArray(firstFieldErrors) && firstFieldErrors.length) {
            return firstFieldErrors[0]
        }
    }
    return error?.response?.data?.message || fallback
}

const applyRecurringScheduleFromData = (recurringSchedule) => {
    if (recurringSchedule?.period) {
        isRepeated.value = true
        const periodLabelMap = {
            weekly: 'Tuần',
            monthly: 'Tháng',
            quarterly: 'Quý',
            yearly: 'Năm',
        }
        repeatUnit.value = periodLabelMap[recurringSchedule.period] || 'Tuần'
        recurringWeekDays.value = Array.isArray(recurringSchedule.week_days)
            ? [...recurringSchedule.week_days]
            : []
        return
    }

    isRepeated.value = false
    repeatUnit.value = 'Tuần'
    recurringWeekDays.value = []
}

const prefillForm = (data) => {
    if (!data) return;
    // Thông tin cơ bản
    selectedSportId.value = data?.sport.id || null;
    tournamentName.value = data?.name || '';
    tournamentNote.value = data?.description || '';

    // Play mode và format
    if (data?.play_mode) {
        selectedPlayMode.value = data.play_mode
    }
    if (data?.format) {
        selectedFormat.value = data.format
    }

    // Ngày giờ - địa điểm  - người chơi
    if (data?.start_time) {
        date.value = new Date(data.start_time);
    }
    if (data?.duration) {
        durationMinutes.value = data.duration;
        const durationOption = durationOptions.find(option => option.value === data.duration);
        selectedDuration.value = durationOption ? durationOption.label : '';
    }
    if (data?.competition_location) {
        selectedLocation.value = data.competition_location;
        locationKeyword.value = data.competition_location.name || '';
    }
    playerCount.value = data?.max_players || 1;
    privacy.value = data?.is_private ? 'Riêng tư' : 'Công khai';

    // Phí
    hasFee.value = !!data?.has_fee;
    autoSplitCourtFee.value = !!data?.auto_split_fee;
    paymentNote.value = data?.fee_description || '';
    qrCodePreview.value = data?.qr_code_url || null;
    qrCodeImage.value = data?.qr_code_url || null;
    feeAmount.value = data?.fee_amount || 0;
    if (feeAmount.value) {
        formattedFeeAmount.value = feeAmount.value.toLocaleString('vi-VN');
    } else {
        formattedFeeAmount.value = '';
    }

    // Trình độ
    minLevel.value = data?.min_rating ? data.min_rating.toString() : 'Không giới hạn';
    maxLevel.value = data?.max_rating ? data.max_rating.toString() : 'Không giới hạn';

    // Luật thi đấu
    setNumber.value = data?.set_number || 1;
    gamesPerSet.value = data?.base_points || 11;
    pointsDifference.value = data?.points_difference || 2;
    maxPoints.value = data?.max_points || 11;
    applyRule.value = data?.apply_rule !== undefined ? !!data.apply_rule : true;

    // Cài đặt nâng cao
    genderPolicy.value = data?.gender || 3
    applyRecurringScheduleFromData(data?.recurring_schedule)

    // Allow cancellation và cancellation duration
    allowCancellation.value = data?.allow_cancellation !== undefined ? !!data.allow_cancellation : true
    if (data?.cancellation_duration) {
        // Map từ phút về value tương ứng
        const minutesToValueMap = {
            30: 1,
            60: 2,
            120: 3,
            180: 4,
            240: 5,
            360: 6,
            720: 7,
            1440: 8,
        }
        lockCancellation.value = minutesToValueMap[data.cancellation_duration] || 1
    }

    autoApprove.value = !!data?.auto_approve
    allowParticipantAddFriends.value = !!data?.allow_participant_add_friends
}

const detailMiniTournament = async (id) => {
    try {
        const data = await MiniTournamentService.getMiniTournamentById(id);
        prefillForm(data);
    } catch (error) {
        console.error('Error fetching mini tournament details:', error);
        throw error;
    }
};

// Helper methods for RIGHT COLUMN
const getSportName = (sportId) => {
    if (!sports.value || !Array.isArray(sports.value)) return 'Chưa chọn'
    const sport = sports.value.find(s => s.id === sportId)
    return sport ? sport.name : 'Chưa chọn'
}

const getPlayModeName = (playModeId) => {
    if (!playModes || !Array.isArray(playModes)) return 'Chưa chọn'
    const mode = playModes.find(m => m.id === playModeId)
    return mode ? mode.name : 'Chưa chọn'
}

const getFormatName = (formatId) => {
    if (!formats || !Array.isArray(formats)) return 'Chưa chọn'
    const format = formats.find(f => f.id === formatId)
    return format ? format.name : 'Chưa chọn'
}

const getSportIcon = (sportId) => {
    const icons = {
        1: '🏸', // Cầu lông
        2: '🎾', // Tennis
        3: '🏓', // Bóng bàn
    }
    return icons[sportId] || '🏸'
}

const getFormatIcon = (formatId) => {
    const icons = {
        1: '👤', // Đơn
        2: '👥', // Đôi
    }
    return icons[formatId] || '👤'
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
}

onMounted(async () => {
    await fetchSports()
    if (isEditMode.value) {
        await detailMiniTournament(miniTournamentId);
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
