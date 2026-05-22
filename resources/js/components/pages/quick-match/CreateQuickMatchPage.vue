<template>
    <div class="bg-[#F7F8FA] min-h-screen">
        <div class="max-w-[680px] mx-auto px-4 py-6">

            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <button
                    @click="goBack"
                    class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-gray-200 transition-colors"
                >
                    <svg class="w-5 h-5 text-[#3E414C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <h1 class="text-[20px] font-bold text-[#3E414C] tracking-[-0.5px]">Quick Match</h1>
                <span
                    v-if="isMatchDone"
                    class="ml-2 px-2 py-0.5 bg-green-100 text-green-700 text-[12px] font-semibold rounded-full"
                >
                    Đã xác nhận
                </span>
            </div>

            <!-- Form -->
            <div class="space-y-4" :class="{ 'opacity-40 pointer-events-none select-none': isMatchDone }">

                <!-- Name + Note -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-4">
                    <div class="flex items-start gap-4">
                        <!-- Venue image upload -->
                        <div
                            class="relative w-[60px] h-[60px] bg-[#EDEEF2] border border-dashed border-[#838799] rounded-[8px] flex items-center justify-center flex-shrink-0 cursor-pointer hover:bg-[#e1e2e8] transition-colors overflow-hidden"
                            @click="venueInputRef && venueInputRef.click()"
                        >
                            <img v-if="venuePreview" :src="venuePreview" alt="Venue" class="w-full h-full object-cover" />
                            <svg v-else class="w-7 h-7 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                            </svg>
                            <button
                                v-if="venuePreview"
                                @click.stop="clearVenue"
                                class="absolute top-1 right-1 p-0.5 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors z-10"
                            >
                                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <input ref="venueInputRef" type="file" accept="image/webp,image/*" class="hidden" @change="handleVenueUpload" />
                        </div>
                        <div class="flex-1 space-y-2">
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Tên kèo đấu"
                                class="w-full px-3 py-2 border-b border-[#DCDEE6] focus:outline-none focus:border-[#D72D36] placeholder:text-sm placeholder:text-[#9EA2B3] bg-transparent font-bold text-[16px] text-[#3E414C]"
                            />
                            <input
                                v-model="form.note"
                                type="text"
                                placeholder="Ghi chú: vị trí sân, thời tiết...."
                                class="w-full px-3 py-1 focus:outline-none placeholder:text-[12px] placeholder:text-[#9EA2B3] bg-transparent text-[12px] text-[#838799]"
                            />
                        </div>
                    </div>
                </div>

                <!-- Match Type Toggle -->
                <div class="grid grid-cols-2 gap-3">
                    <!-- Rank -->
                    <button
                        @click="form.matchType = 'rank'"
                        :class="[
                            'flex flex-col items-center justify-center rounded-[12px] px-3 py-4 transition-all min-h-[88px]',
                            form.matchType === 'rank'
                                ? 'bg-[#D72D36] text-white shadow-md'
                                : 'bg-[#EDEEF2] text-[#838799] hover:opacity-80'
                        ]"
                    >
                        <div :class="[
                            'w-10 h-10 rounded-full flex items-center justify-center mb-2',
                            form.matchType === 'rank' ? 'bg-white/20' : 'bg-[#FBEAEB]'
                        ]">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v4m-2-2h4" stroke-width="2" />
                            </svg>
                        </div>
                        <span class="text-[14px] font-semibold tracking-[-0.25px]">Thi đấu Rank</span>
                    </button>

                    <!-- Casual -->
                    <button
                        @click="form.matchType = 'casual'"
                        :class="[
                            'flex flex-col items-center justify-center rounded-[12px] px-3 py-4 transition-all min-h-[88px]',
                            form.matchType === 'casual'
                                ? 'bg-[#D72D36] text-white shadow-md'
                                : 'bg-[#EDEEF2] text-[#838799] hover:opacity-80'
                        ]"
                    >
                        <div :class="[
                            'w-10 h-10 rounded-full flex items-center justify-center mb-2',
                            form.matchType === 'casual' ? 'bg-white/20' : 'bg-[#FBEAEB]'
                        ]">
                            <svg class="w-5 h-5 text-[#D72D36]" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-5-9c.83 0 1.5-.67 1.5-1.5S7.83 8 7 8s-1.5.67-1.5 1.5S6.17 11 7 11zm10 0c.83 0 1.5-.67 1.5-1.5S17.83 8 17 8s-1.5.67-1.5 1.5.67 1.5 1.5 1.5zm-5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z" />
                            </svg>
                        </div>
                        <span class="text-[14px] font-semibold tracking-[-0.25px]">Vui vẻ</span>
                    </button>
                </div>

                <!-- Team Selection -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-4">
                    <p class="text-[14px] font-bold text-[#838799] uppercase tracking-[-0.25px] mb-4">Chọn đội</p>

                    <div class="flex items-center justify-between gap-4">
                        <!-- TEAM A -->
                        <div class="bg-[#F2F7FC] rounded-[8px] p-4 flex-1">
                            <p class="text-center text-[14px] font-semibold text-[#4392E0] mb-3 tracking-[-0.25px]">TEAM A</p>
                            <div class="flex items-center justify-center gap-2 mb-2">
                                <div
                                    v-for="(user, idx) in teamAUsers"
                                    :key="user.id"
                                    class="flex flex-col items-center group"
                                >
                                    <div class="relative">
                                        <img
                                            :src="user.avatar_url || defaultAvatar"
                                            alt="User avatar"
                                            class="w-[42px] h-[42px] rounded-full object-cover border-2 border-white"
                                        />
                                        <button
                                            @click.stop="removeUser('team_a', idx)"
                                            class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm hover:bg-red-600"
                                        >
                                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="text-[12px] font-semibold text-[#3E414C] mt-1 text-center leading-tight max-w-[42px] truncate">
                                        {{ user.short_name || user.full_name?.substring(0, 3) }}
                                    </p>
                                </div>
                                <!-- Empty slot -->
                                <div
                                    v-for="n in (2 - teamAUsers.length)"
                                    :key="'empty-a-' + n"
                                    class="w-[42px] flex flex-col items-center cursor-pointer hover:opacity-70"
                                    @click="openUserModal('team_a')"
                                >
                                    <div class="w-[42px] h-[42px] rounded-full bg-[#DCDEE6] border-2 border-dashed border-[#838799] flex items-center justify-center">
                                        <svg class="w-4 h-4 text-[#838799]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <p v-if="teamAUsers.length === 1" class="text-center text-[11px] text-[#838799]">
                                + Thêm đồng đội
                            </p>
                        </div>

                        <!-- VS -->
                        <span class="text-[14px] font-bold text-[#3E414C] shrink-0">VS</span>

                        <!-- TEAM B -->
                        <div class="bg-[#F2F7FC] rounded-[8px] p-4 flex-1">
                            <p class="text-center text-[14px] font-semibold text-[#4392E0] mb-3 tracking-[-0.25px]">TEAM B</p>
                            <div class="flex items-center justify-center gap-2 mb-2">
                                <div
                                    v-for="(user, idx) in teamBUsers"
                                    :key="user.id"
                                    class="flex flex-col items-center group"
                                >
                                    <div class="relative">
                                        <img
                                            :src="user.avatar_url || defaultAvatar"
                                            alt="User avatar"
                                            class="w-[42px] h-[42px] rounded-full object-cover border-2 border-white"
                                        />
                                        <button
                                            @click.stop="removeUser('team_b', idx)"
                                            class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm hover:bg-red-600"
                                        >
                                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="text-[12px] font-semibold text-[#3E414C] mt-1 text-center leading-tight max-w-[42px] truncate">
                                        {{ user.short_name || user.full_name?.substring(0, 3) }}
                                    </p>
                                </div>
                                <!-- Empty slot -->
                                <div
                                    v-for="n in (2 - teamBUsers.length)"
                                    :key="'empty-b-' + n"
                                    class="w-[42px] flex flex-col items-center cursor-pointer hover:opacity-70"
                                    @click="openUserModal('team_b')"
                                >
                                    <div class="w-[42px] h-[42px] rounded-full bg-[#DCDEE6] border-2 border-dashed border-[#838799] flex items-center justify-center">
                                        <svg class="w-4 h-4 text-[#838799]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <p v-if="teamBUsers.length === 0" class="text-center text-[11px] text-[#838799]">
                                + Mời đối thủ
                            </p>
                            <p v-else-if="teamBUsers.length === 1" class="text-center text-[11px] text-[#838799]">
                                + Thêm đồng đội
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Score Section -->
                <div class="bg-white rounded-[12px] border border-[#DCDEE6] p-4">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-[14px] font-bold text-[#838799] uppercase tracking-[-0.25px]">Kết quả</p>
                        <button
                            @click="showRefereeScreen = true"
                            class="bg-[#FFF5F5] border border-[#F3C0C3] text-[#D72D36] text-[12px] font-semibold px-3 py-1.5 rounded-full hover:bg-[#FFE5E5] transition-colors tracking-[-0.25px]"
                        >
                            Nhập điểm trọng tài
                        </button>
                    </div>

                    <!-- Sets -->
                    <div class="space-y-2">
                        <div
                            v-for="(set, idx) in scoreSets"
                            :key="idx"
                            class="flex items-center justify-between gap-3"
                        >
                            <!-- Team A score -->
                            <div class="flex items-center gap-2 flex-1">
                                <button
                                    @click="decrementScore(idx, 'team_a')"
                                    class="w-8 h-8 rounded bg-[#EDEEF2] flex items-center justify-center hover:bg-[#DCDEE6] transition-colors"
                                >
                                    <svg class="w-4 h-4 text-[#3E414C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" />
                                    </svg>
                                </button>
                                <div class="flex-1 text-center border border-[#DCDEE6] rounded-[8px] py-2 text-[24px] font-bold text-[#141519]">
                                    {{ set.team_a }}
                                </div>
                                <button
                                    @click="incrementScore(idx, 'team_a')"
                                    class="w-8 h-8 rounded bg-[#EDEEF2] flex items-center justify-center hover:bg-[#DCDEE6] transition-colors"
                                >
                                    <svg class="w-4 h-4 text-[#3E414C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>

                            <span class="text-[12px] text-[#838799] font-semibold shrink-0 w-10 text-center">
                                Hiệp {{ idx + 1 }}
                            </span>

                            <!-- Team B score -->
                            <div class="flex items-center gap-2 flex-1">
                                <button
                                    @click="decrementScore(idx, 'team_b')"
                                    class="w-8 h-8 rounded bg-[#EDEEF2] flex items-center justify-center hover:bg-[#DCDEE6] transition-colors"
                                >
                                    <svg class="w-4 h-4 text-[#3E414C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" />
                                    </svg>
                                </button>
                                <div class="flex-1 text-center border border-[#DCDEE6] rounded-[8px] py-2 text-[24px] font-bold text-[#141519]">
                                    {{ set.team_b }}
                                </div>
                                <button
                                    @click="incrementScore(idx, 'team_b')"
                                    class="w-8 h-8 rounded bg-[#EDEEF2] flex items-center justify-center hover:bg-[#DCDEE6] transition-colors"
                                >
                                    <svg class="w-4 h-4 text-[#3E414C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Add set -->
                    <button
                        @click="addSet"
                        class="w-full mt-3 py-2 border border-[#DCDEE6] rounded-[8px] text-[12px] text-[#838799] font-semibold hover:bg-[#F7F8FA] transition-colors flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Thêm hiệp
                    </button>

                    <!-- QR info -->
                    <div class="mt-4 flex items-center justify-end gap-3">
                        <div class="text-right">
                            <p class="text-[14px] font-bold text-[#838799]">Quét mã QR</p>
                            <p class="text-[12px] text-[#6B6F80] leading-tight">
                                Đối thủ (Team B) quét mã QR để xác nhận tham gia trận đấu
                            </p>
                        </div>
                        <div class="w-10 h-10 bg-[#4392E0] rounded-[4px] flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 20.25h12m-7.5-3v3m3-3v3m-10.125-3h17.25c.621 0 1.125-.504 1.125-1.125V6.875c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </div>
                    </div>
                </div>

            </div>

                <!-- Bottom CTA -->
                <div class="mt-6" :class="{ 'opacity-40 pointer-events-none select-none': isMatchDone }">
                    <button
                        @click="submitMatch"
                        :disabled="isSubmitting || isMatchDone"
                        :class="[
                            'w-full py-3 rounded-[4px] text-[16px] font-semibold text-white flex items-center justify-center gap-2 transition-all',
                            isSubmitting || isMatchDone
                                ? 'bg-gray-400 cursor-not-allowed'
                                : 'bg-[#D72D36] hover:bg-[#c02630] shadow-md'
                        ]"
                    >
                        <span v-if="isSubmitting">Đang tạo...</span>
                        <span v-else-if="isMatchDone">Trận đấu đã hoàn tất</span>
                        <template v-else>
                            Hoàn tất & Hiện mã QR
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </template>
                    </button>
                </div>

        </div>

        <!-- User Select Modal -->
        <QuickMatchUserModal
            v-model="showUserModal"
            :title="selectingTeam === 'team_b' ? 'Mời đối thủ' : 'Thêm đồng đội'"
            @select="onUserSelected"
        />

        <!-- QR Modal -->
        <Transition name="fade">
            <div
                v-if="showQrModal"
                class="fixed inset-0 z-[10000] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="showQrModal = false"
            >
                <div class="bg-white rounded-[16px] w-full max-w-sm p-6 flex flex-col items-center">
                    <div class="flex items-center justify-between w-full mb-6">
                        <h2 class="text-[18px] font-bold text-[#2D3139]">Mã QR trận đấu</h2>
                        <button @click="showQrModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    <!-- Đã confirmed/completed -->
                    <div v-if="isMatchDone" class="flex flex-col items-center gap-4">
                        <div class="w-20 h-20 bg-[#E8F5E9] rounded-full flex items-center justify-center">
                            <svg class="w-10 h-10 text-green-500" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="text-center">
                            <p class="text-[16px] font-semibold text-[#2D3139]">Trận đấu đã được xác nhận!</p>
                            <p class="text-[14px] text-[#6B6F80] mt-1">Trận đấu đã được tất cả người chơi xác nhận.</p>
                        </div>
                        <!-- Score summary -->
                        <div v-if="createdMatch?.score" class="flex items-center gap-3 bg-[#F7F8FA] rounded-[8px] px-4 py-2">
                            <div class="text-center">
                                <p class="text-[20px] font-bold text-[#2D3139]">
                                    {{ createdMatch.score.team_a?.join(' - ') || '0' }}
                                </p>
                                <p class="text-[10px] text-[#838799] uppercase">Team A</p>
                            </div>
                            <span class="text-[12px] text-[#838799] font-semibold">vs</span>
                            <div class="text-center">
                                <p class="text-[20px] font-bold text-[#2D3139]">
                                    {{ createdMatch.score.team_b?.join(' - ') || '0' }}
                                </p>
                                <p class="text-[10px] text-[#838799] uppercase">Team B</p>
                            </div>
                        </div>
                    </div>

                    <!-- Chưa confirm → hiện QR + nút xác nhận -->
                    <div v-else class="flex flex-col items-center gap-4">
                        <div class="bg-white p-4 rounded-[12px] border border-[#DCDEE6]">
                            <QrcodeVue :value="qrScanUrl" :size="200" level="M" />
                        </div>
                        <p class="text-[14px] text-[#6B6F80] text-center">
                            Đưa mã QR cho đối thủ quét để xác nhận trận đấu
                        </p>
                        <div class="w-full flex items-center gap-2 bg-[#F7F8FA] rounded-[8px] p-3">
                            <input
                                :value="qrScanUrl"
                                readonly
                                class="flex-1 bg-transparent text-[12px] text-[#838799] outline-none"
                            />
                            <button
                                @click="copyQrUrl"
                                class="text-[#D72D36] text-[12px] font-semibold hover:underline shrink-0"
                            >
                                Sao chép
                            </button>
                        </div>
                        <button
                            @click="confirmMatch"
                            :disabled="isConfirming"
                            :class="[
                                'w-full py-3 rounded-[4px] text-[16px] font-semibold text-white flex items-center justify-center gap-2 transition-all',
                                isConfirming
                                    ? 'bg-gray-400 cursor-not-allowed'
                                    : 'bg-[#27AE60] hover:bg-[#219a52] shadow-md'
                            ]"
                        >
                            <span v-if="isConfirming">Đang xác nhận...</span>
                            <span v-else>Xác nhận đã quét QR</span>
                        </button>
                    </div>

                    <button
                        @click="goToMatchDetail"
                        class="mt-6 w-full py-3 bg-[#D72D36] text-white font-semibold rounded-[4px] hover:bg-[#c02630] transition-colors"
                    >
                        Xem chi tiết trận đấu
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Referee Scoring Modal -->
        <RefereeScoringScreen
            v-if="showRefereeScreen"
            :is-open="showRefereeScreen"
            :team-a="{ name: 'Team A', members: teamAUsers.map(u => ({ user: u })) }"
            :team-b="{ name: 'Team B', members: teamBUsers.map(u => ({ user: u })) }"
            @close="showRefereeScreen = false"
            @save="onRefereeSaved"
        />
    </div>
</template>

<script setup>
import { ref, computed, reactive, watch, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'
import QrcodeVue from 'qrcode.vue'
import QuickMatchUserModal from '@/components/molecules/QuickMatchUserModal.vue'
import RefereeScoringScreen from '@/components/molecules/referee-scoring/RefereeScoringScreen.vue'
import { useUserStore } from '@/store/auth'
import { storeToRefs } from 'pinia'
import { quickMatchService } from '@/service/quickMatch.js'

const router = useRouter()
const userStore = useUserStore()
// Use storeToRefs for reactive state + direct access for computed getUser
// In script: userStore.getUser.value gives the actual User object
// In template: Vue auto-unwraps ComputedRef<User> → User, so {{ getUser.id }} works
const { getUser } = storeToRefs(userStore)

const defaultAvatar = '/images/default-avatar.png'

// Venue image
const venueInputRef = ref(null)
const venuePreview = ref('')

const handleVenueUpload = (event) => {
    const file = event.target.files[0]
    if (!file) return

    if (file.size > 5 * 1024 * 1024) {
        toast.error('Kích thước ảnh không được quá 5MB')
        return
    }

    const reader = new FileReader()
    reader.onload = (e) => {
        venuePreview.value = e.target.result
    }
    reader.readAsDataURL(file)
}

const clearVenue = () => {
    venuePreview.value = ''
    if (venueInputRef.value) venueInputRef.value.value = ''
}

// Form state
const form = reactive({
    name: '',
    note: '',
    matchType: 'rank',
})

const teamAUsers = ref(getUser.value ? [{ ...getUser.value }] : [])
const teamBUsers = ref([])
const scoreSets = ref([{ team_a: 0, team_b: 0 }])
const isSubmitting = ref(false)
const showUserModal = ref(false)
const selectingTeam = ref(null)
const showQrModal = ref(false)
const showRefereeScreen = ref(false)
const createdMatch = ref(null)
const isConfirming = ref(false)

const isMatchDone = computed(() => {
    return createdMatch.value?.status === 'completed'
})

// QuickMatchUserModal emits the user directly (not wrapped)
const onUserSelected = (user) => {
    if (!user) return

    if (selectingTeam.value === 'team_a') {
        if (teamAUsers.value.length >= 2) {
            toast.error('Mỗi đội tối đa 2 người')
            return
        }
        teamAUsers.value.push(user)
    } else if (selectingTeam.value === 'team_b') {
        if (teamBUsers.value.length >= 2) {
            toast.error('Mỗi đội tối đa 2 người')
            return
        }
        teamBUsers.value.push(user)
    }
}

const removeUser = (team, idx) => {
    if (team === 'team_a') {
        const user = teamAUsers.value[idx]
        // Không cho xóa chính mình khỏi team A
        if (user.id === getUser.value?.id) {
            return
        }
        teamAUsers.value.splice(idx, 1)
    } else if (team === 'team_b') {
        teamBUsers.value.splice(idx, 1)
    }
}

const openUserModal = (team) => {
    selectingTeam.value = team
    showUserModal.value = true
}

const incrementScore = (setIdx, team) => {
    scoreSets.value[setIdx][team]++
}

const decrementScore = (setIdx, team) => {
    if (scoreSets.value[setIdx][team] > 0) {
        scoreSets.value[setIdx][team]--
    }
}

const addSet = () => {
    scoreSets.value.push({ team_a: 0, team_b: 0 })
}

const onRefereeSaved = (refereeScores) => {
    scoreSets.value = refereeScores.map(s => ({
        team_a: s.team1,
        team_b: s.team2
    }))
    showRefereeScreen.value = false
}

const qrScanUrl = computed(() => {
    // Use qr_code_url directly from API response
    return createdMatch.value?.qr_code_url || ''
})

const copyQrUrl = async () => {
    try {
        await navigator.clipboard.writeText(qrScanUrl.value)
        toast.success('Đã sao chép liên kết!')
    } catch {
        toast.error('Không thể sao chép')
    }
}

const confirmMatch = async () => {
    if (!createdMatch.value?.qr_code_url) return

    isConfirming.value = true
    try {
        const qrCode = createdMatch.value.qr_code_url.split('/confirm/').pop()
        const res = await quickMatchService.confirm(qrCode)
        const updated = res.data?.data
        if (updated) {
            createdMatch.value = { ...createdMatch.value, ...updated }
        }
        toast.success('Đã lưu điểm và hoàn tất trận đấu!')
    } catch (error) {
        const msg = error.response?.data?.message || 'Không thể hoàn tất trận đấu'
        toast.error(msg)
    } finally {
        isConfirming.value = false
    }
}

const goToMatchDetail = () => {
    if (createdMatch.value?.id) {
        showQrModal.value = false
        router.push({ name: 'quick-match-detail', params: { id: createdMatch.value.id } })
    }
}

const goBack = () => router.back()

// ─── Socket: realtime QR confirmation ───
const echoChannel = ref(null)

watch(showQrModal, (isOpen) => {
    if (!isOpen || !createdMatch.value?.id || !window.Echo) return

    if (echoChannel.value) {
        echoChannel.value.stopListening('.quick_match.confirmed')
        echoChannel.value.unsubscribe()
        echoChannel.value = null
    }

    echoChannel.value = window.Echo.private(`quick-match.${createdMatch.value.id}`)

    echoChannel.value.listen('.quick_match.confirmed', (data) => {
        if (data.quick_match) {
            createdMatch.value = { ...createdMatch.value, ...data.quick_match }
        }
    })
}, { immediate: true })

// Cleanup when modal closes
watch(showQrModal, (isOpen) => {
    if (isOpen) return
    if (echoChannel.value) {
        echoChannel.value.stopListening('.quick_match.confirmed')
        echoChannel.value.unsubscribe()
        echoChannel.value = null
    }
})

onUnmounted(() => {
    if (echoChannel.value) {
        echoChannel.value.stopListening('.quick_match.confirmed')
        echoChannel.value.unsubscribe()
    }
})

const submitMatch = async () => {
    if (teamAUsers.value.length === 0) {
        toast.error('Team A phải có ít nhất 1 người chơi')
        return
    }
    if (teamBUsers.value.length === 0) {
        toast.error('Vui lòng mời ít nhất 1 đối thủ')
        return
    }

    isSubmitting.value = true

    try {
        const payload = {
            name: form.name || undefined,
            note: form.note || undefined,
            match_type: form.matchType,
            team_a: teamAUsers.value.map(u => u.id),
            team_b: teamBUsers.value.map(u => u.id),
            score: {
                team_a: scoreSets.value.map(s => s.team_a),
                team_b: scoreSets.value.map(s => s.team_b),
            },
        }

        const res = await quickMatchService.create(payload)
        const matchData = res.data?.data

        createdMatch.value = matchData
        toast.success('Tạo trận đấu nhanh thành công!')
        showQrModal.value = true

    } catch (error) {
        const msg = error.response?.data?.message || 'Tạo trận đấu thất bại'
        toast.error(msg)
    } finally {
        isSubmitting.value = false
    }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
