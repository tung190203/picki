<template>
    <div class="p-4 mx-auto w-full max-w-8xl rounded-md flex flex-col overflow-y-auto">
        <!-- Loading Skeleton -->
        <ClubDetailSkeleton v-if="isInitialLoading" />

        <template v-else>
            <!-- Global Backdrop for menu closing -->
            <div v-if="isMenuOpen || isChangeClubOpen" class="fixed inset-0 z-[35]"
                @click="closeMenu">
            </div>
            <!-- Admin/Staff View -->
            <div v-if="hasAnyRole(['admin', 'manager', 'secretary', 'treasurer'])"
                class=" text-white rounded-[8px] shadow-lg px-6 pt-4 pb-6 relative flex flex-col justify-between aspect-[4/1] bg-cover bg-center"
                :style="{ backgroundImage: `url(${club.profile?.cover_image_url || Background})` }">
                <!-- Overlay to improve readability on white backgrounds -->
                <div class="absolute inset-0 backdrop-blur-[1px] z-10 rounded-[8px]"
                    style="background: linear-gradient(to bottom, rgba(0, 0, 0, 0.12), rgba(0, 0, 0, 0.38))"></div>

                <div class="flex items-center justify-between relative z-40">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white/10 p-2 rounded-full cursor-pointer hover:bg-white/20 transition-colors"
                            @click="goBack">
                            <ArrowLeftIcon class="w-6 h-6 text-white" />
                        </div>

                        <!-- Logo Container -->
                        <div class="relative">
                            <img v-if="club.logo_url" :src="club.logo_url"
                                class="w-16 h-16 rounded-full object-cover border-2 border-white/20 shadow-sm" />
                            <div v-else
                                class="w-16 h-16 rounded-full bg-red-100 text-[#D72D36] flex items-center justify-center font-bold text-2xl border-2 border-white/20">
                                {{ club.name?.charAt(0).toUpperCase() }}
                            </div>
                            <!-- Verify Badge -->
                            <div v-if="club.is_verified"
                                class="absolute bottom-0 right-0 bg-[#4392E0] rounded-full p-0.5 border border-white shadow-sm">
                                <VerifyIcon class="w-4 h-4 text-white" />
                            </div>
                        </div>

                        <div class="flex flex-col">
                            <h1 class="text-4xl font-bold leading-tight">{{ club.name }}</h1>
                            <p class="text-white text-sm font-medium bg-black/40 px-4 py-1 rounded-md w-fit">{{ getRoleName(currentUserRole) }}</p>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <div class="p-2 cursor-pointer hover:bg-white/10 rounded-full transition-colors"
                            @click="toggleChangeClub" v-if="myClubs.length > 0">
                            <ChangeCircleIcon class="w-6 h-6 text-white" />
                        </div>
                        <div v-else class="text-white/70 text-sm font-medium px-2">
                            Chưa có CLB nào
                        </div>
                        <div class="p-2 cursor-pointer hover:bg-white/10 rounded-full transition-colors"
                            @click="inviteMembers">
                            <UserPlusIcon class="w-6 h-6 text-white" />
                        </div>
                        <div class="p-2 cursor-pointer hover:bg-white/10 rounded-full transition-colors"
                            @click="shareClub">
                            <ShareIcon class="w-6 h-6 text-white" />
                        </div>
                        <div class="p-2 cursor-pointer hover:bg-white/10 rounded-full transition-colors"
                            @click="handleCampaign">
                            <CampaignIcon class="w-6 h-6 text-white" />
                        </div>
                        <div class="p-2 cursor-pointer hover:bg-white/10 rounded-full transition-colors"
                            @click="toggleMenu">
                            <EllipsisVerticalIcon class="w-6 h-6 text-white" />
                        </div>

                        <div v-if="isMenuOpen"
                            class="absolute right-0 top-14 w-56 bg-white rounded-xl shadow-2xl py-2 z-50 text-gray-800 border border-gray-100 animate-in fade-in zoom-in duration-200">
                            <!-- Add admin specific menu items if needed, for now reuse existing -->
                            <button v-if="hasAnyRole(['admin', 'secretary'])"
                                class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-gray-100 transition-colors"
                                @click="openEditModal">
                                <EditNoteIcon class="w-5 h-5 text-gray-500" />
                                <span class="font-medium">Chỉnh sửa</span>
                            </button>
                            <button
                                class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-gray-100 transition-colors"
                                @click="openZaloModal">
                                <ZaloIcon class="w-5 h-5 text-gray-500" />
                                <span class="font-medium">Thêm nhóm Zalo</span>
                            </button>
                            <button v-if="currentUserRole === 'admin' && adminCount === 1"
                                class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-gray-100 transition-colors"
                                @click="openTransferModal">
                                <CompareArrowsIcon class="w-5 h-5 text-gray-500" />
                                <span class="font-medium">Nhượng CLB</span>
                            </button>
                            <button
                                v-if="(currentUserRole === 'admin' && adminCount > 1) || hasAnyRole(['manager', 'secretary', 'treasurer'])"
                                class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-[#FBEAEA] transition-colors"
                                @click="leaveClub">
                                <ArrowRightOnRectangleIcon class="w-5 h-5 text-[#D72D36]" />
                                <span class="font-medium text-[#D72D36]">Rời CLB</span>
                            </button>
                            <button v-if="hasAnyRole(['admin'])"
                                class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-[#FBEAEA] transition-colors"
                                @click="deleteClub">
                                <TrashIcon class="w-5 h-5 text-[#D72D36]" />
                                <span class="font-medium text-[#D72D36]">Xoá CLB</span>
                            </button>
                        </div>
                        <div v-if="isChangeClubOpen"
                            class="absolute right-0 top-14 w-56 bg-white rounded-xl shadow-2xl z-50 text-gray-800 border border-gray-100 animate-in fade-in zoom-in duration-200">
                            <div class="py-2 max-h-48 overflow-y-auto custom-scrollbar">
                                <template v-for="item in myClubs" :key="item.id">
                                    <div class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-100 transition-colors cursor-pointer"
                                        @click="changeClub(item)">
                                        <img v-if="item.logo_url" :src="item.logo_url" alt=""
                                            class="w-8 h-8 rounded-full object-cover">
                                        <div v-else
                                            class="w-8 h-8 rounded-full bg-red-100 text-[#D72D36] flex items-center justify-center font-bold text-xs">
                                            {{ item.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <span class="font-medium truncate" v-tooltip="item.name">{{ item.name }}</span>
                                        <span v-if="item.id == clubId"
                                            class="ml-auto text-[10px] bg-green-50 text-green-600 px-1.5 py-0.5 rounded-full font-bold uppercase whitespace-nowrap">
                                            hiện tại
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-8 relative z-20">
                    <div v-for="(stat, index) in statsAdmin" :key="index"
                        class="bg-[#3E414C]/80 backdrop-blur-md rounded-2xl p-4 sm:p-6 border border-white/5 shadow-inner">
                        <p class="text-sm font-semibold text-gray-300 mb-2 uppercase tracking-wide">{{ stat.label }}</p>
                        <div class="flex items-baseline space-x-1">
                            <span class="text-4xl font-bold">{{ stat.value }}</span>
                            <span class="text-sm font-medium opacity-60 ml-2" :class="stat.unitClass">{{ stat.unit
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Background Pattern -->
                <div class="absolute inset-0 opacity-10 pointer-events-none">
                    <div class="absolute inset-0 bg-repeat bg-center"
                        style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;20&quot; height=&quot;20&quot; viewBox=&quot;0 0 20 20&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h10v10H0zM10 10h10v10H10z&quot; fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.1&quot;/%3E%3C/svg%3E')">
                    </div>
                </div>
            </div>

            <!-- Member/Guest View (Existing) -->
            <div v-else
                class="bg-club-default text-white rounded-[8px] shadow-lg p-6 relative flex flex-col justify-between aspect-[4/1] bg-cover bg-center"
                :style="{ backgroundImage: `url(${club.profile?.cover_image_url || Background})` }">
                <!-- Overlay for readability -->
                <div class="absolute inset-0 backdrop-blur-[1px] z-10 rounded-[8px]"
                    style="background: linear-gradient(to bottom, rgba(0, 0, 0, 0.12), rgba(0, 0, 0, 0.38))"></div>

                <div class="flex items-center justify-between relative z-40">
                    <div>
                        <ArrowLeftIcon class="w-6 h-6 cursor-pointer text-white" @click="goBack" />
                    </div>
                    <div class="flex items-center space-x-1 relative">
                        <ShareIcon class="w-6 h-6 cursor-pointer text-white" @click="shareClub" />
                        <EllipsisVerticalIcon class="w-9 h-9 cursor-pointer text-white" @click="toggleMenu" />

                        <!-- Dropdown Menu -->
                        <div v-if="isMenuOpen"
                            class="absolute right-0 top-10 w-56 bg-white rounded-xl shadow-2xl py-2 z-50 text-gray-800 border border-gray-100 animate-in fade-in zoom-in duration-200">
                            <button
                                class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-gray-100 transition-colors"
                                @click="openNotification" v-if="is_joined">
                                <BellIcon class="w-5 h-5 text-gray-500" />
                                <span class="font-medium">Thông báo</span>
                            </button>
                            <button
                                class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-gray-100 transition-colors" @click="handleReportClub">
                                <InformationCircleIcon class="w-5 h-5 text-gray-500" />
                                <span class="font-medium">Báo cáo CLB</span>
                            </button>
                            <div class="h-px bg-gray-100 my-1 mx-2"></div>
                            <button class="w-full flex items-center space-x-3 px-4 py-3 transition-colors rounded-lg"
                                @click="leaveClub" :class="is_joined
                                    ? 'hover:bg-red-50 text-red-600 cursor-pointer'
                                    : 'text-gray-400 bg-gray-100 cursor-not-allowed'
                                    " :disabled="!is_joined">
                                <ArrowLeftOnRectangleIcon class="w-5 h-5"
                                    :class="is_joined ? 'text-red-600' : 'text-gray-400'" />
                                <span class="font-medium">
                                    Rời câu lạc bộ
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex items-center relative z-20">
                    <div>
                        <div v-if="club.profile?.address"
                            class="flex items-center space-x-2 relative rounded-full overflow-hidden bg-white w-fit text-black py-1 px-2 mb-2">
                            <MapPinIcon class="w-5 h-5" />
                            <div class="text-sm font-semibold">{{ club.profile.address }}</div>
                        </div>
                        <div class="flex items-center space-x-4 mb-2">
                             <!-- Logo Container -->
                            <div class="relative">
                                <img v-if="club.logo_url" :src="club.logo_url"
                                    class="w-16 h-16 rounded-full object-cover border-2 border-white/20 shadow-sm" />
                                <div v-else
                                    class="w-16 h-16 rounded-full bg-red-100 text-[#D72D36] flex items-center justify-center font-bold text-2xl border-2 border-white/20">
                                    {{ club.name?.charAt(0).toUpperCase() }}
                                </div>
                                <!-- Verify Badge -->
                                <div v-if="club.is_verified"
                                    class="absolute bottom-0 right-0 bg-[#4392E0] rounded-full p-0.5 border border-white shadow-sm">
                                    <VerifyIcon class="w-4 h-4 text-white" />
                                </div>
                            </div>

                            <div class="flex flex-col">
                                <h1 class="text-3xl sm:text-4xl lg:text-[44px] font-bold leading-tight">{{ club.name }}</h1>
                                <p class="text-white/70 text-sm font-medium">{{ is_joined ? getRoleName(currentUserRole) : 'Khách' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2" v-if="!is_joined">
                            <template v-if="club.has_invitation">
                                <Button size="md" color="success"
                                    class="px-6 sm:px-12 md:px-[35px] bg-[#00B377] border border-[#00B377] text-white hover:bg-[#009664] hover:border-[#009664] flex gap-2"
                                    @click.stop="acceptJoinClubInvitation">
                                    Đồng ý
                                </Button>
                                <Button size="md" color="danger"
                                    class="px-6 sm:px-12 md:px-[35px] bg-[#D72D36] border border-[#D72D36] text-white hover:bg-[#b5222a] hover:border-[#b5222a] flex gap-2"
                                    @click.stop="rejectJoinClubInvitation">
                                    Từ chối
                                </Button>
                            </template>
                            <template v-else-if="!club.has_pending_request">
                                <Button size="md" color="danger"
                                    class="px-6 sm:px-12 md:px-[75px] bg-[#D72D36] border border-[#D72D36] text-white hover:bg-white hover:text-[#D72D36] flex gap-2"
                                    @click.stop="joinClubRequest">
                                    <PlusIcon class="w-5 h-5" />
                                    Tham gia CLB
                                </Button>
                            </template>
                            <template v-else>
                                <Button size="md" color="danger"
                                    class="px-6 sm:px-12 md:px-[75px] bg-[#D72D36] border border-[#D72D36] text-white hover:bg-white hover:text-[#D72D36] flex gap-2"
                                    @click.stop="cancelJoinRequest">
                                    Hủy tham gia
                                </Button>
                            </template>
                            <Button v-if="club.profile?.qr_zalo_enabled || club.profile?.zalo_link_enabled" size="md"
                                color="white" class="bg-[#FBEAEB] rounded-full p-2" @click="openClubChat">
                                <MessageIcon class="w-6.5 h-6.5 text-[#D72D36]" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-12 gap-4 py-4">
                <div class="col-span-12 lg:col-span-8 order-2 lg:order-1">
                    <div v-if="is_joined">
                        <div class="flex items-baseline justify-between">
                            <h2 class="text-2xl text-[#838799] font-semibold uppercase mb-4">Thông báo</h2>
                            <p class="text-[#D72D36] font-semibold cursor-pointer" @click="openNotification">Xem tất cả
                            </p>
                        </div>
                        <template v-if="pinnedNotifications.length > 0">
                            <NotificationCard v-for="(notification, index) in pinnedNotifications" :key="index"
                                :data="notification"
                                :is-admin="hasAnyRole(['admin', 'manager', 'secretary'])"
                                @unpin="handleUnpinNotification"
                                @pin="handlePinNotification"
                                @click="handleNotificationClick(notification)" />
                        </template>
                        <div v-else class="p-4 text-center">
                            <p class="text-[#838799]">Hiện chưa có thông báo ghim nào</p>
                        </div>
                    </div>
                    <div v-if="is_joined">
                        <div class="flex items-baseline justify-between">
                            <h2 class="text-2xl text-[#838799] font-semibold uppercase mb-4">Lịch hoạt động</h2>
                            <p class="text-[#D72D36] font-semibold cursor-pointer" @click="openActivityModal">Xem tất cả
                            </p>
                        </div>
                        <template v-if="activities.length > 0">
                            <ActivityScheduleCard v-for="(activity, index) in activities" :key="index" v-bind="activity"
                                @click-card="goToActivityDetail(activity)" @edit="handleEditActivity(activity)"
                                @register="handleRegisterActivity(activity)"
                                @cancel-join="handleCancelJoinActivity(activity)"
                                @self-absent="handleSelfAbsentActivity(club.id, activity.id)"
                                @check-in="handleSelfCheckInActivity(club.id, activity.id)" />
                        </template>
                        <div v-else class="p-4 text-center">
                            <p class="text-[#838799]">Hiện chưa có lịch thi đấu</p>
                        </div>
                    </div>
                    <ClubInfoTabs :club="club" :isJoined="is_joined" :currentUserRole="currentUserRole"
                        :top-three="topThree" :leaderboard="leaderboard" :leaderboard-meta="leaderboardMeta"
                        :leaderboard-filters="leaderboardFilters" :leaderboard-loading="isLeaderboardLoading"
                        :is-saving="isUpdatingIntro"
                        @leaderboard-filter="handleLeaderboardFilter"
                        @leaderboard-page-change="handleLeaderboardPageChange" @tab-change="handleTabChange"
                        @refresh-club="getClubDetail" @update-intro="handleUpdateIntro" />
                </div>
                <div class="col-span-12 lg:col-span-4 space-y-4 order-1 lg:order-2">
                    <div class="max-w-3xl mx-auto" v-if="!hasAnyRole(['admin', 'manager', 'secretary', 'treasurer'])">
                        <div class="bg-white rounded-2xl shadow-md px-6 py-5">
                            <div class="grid grid-cols-3 divide-x divide-gray-200 text-center">
                                <div v-for="(stat, index) in clubStats" :key="index"
                                    class="flex flex-col items-center gap-2">
                                    <div class="text-[#D72D36] h-12 flex items-center justify-center">
                                        <component :is="stat.icon" class="w-12 h-12" />
                                    </div>
                                    <div class="font-semibold text-gray-800">{{ statsValue[stat.key] }}</div>
                                    <div class="text-sm text-[#838799]">{{ stat.label }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="max-w-3xl mx-auto"
                        v-if="hasAnyRole(['admin', 'manager', 'secretary', 'treasurer', 'member'])">
                        <div class="bg-white rounded-2xl shadow-md px-2 py-5">
                            <div class="grid text-center"
                                :class="filteredClubModules.length === 4 ? 'grid-cols-4' : 'grid-cols-3'">

                                <!-- Schedule module: dropdown on hover -->
                                <div class="flex flex-col items-center gap-2">
                                    <div class="relative schedule-dropdown-trigger" ref="scheduleDropdownRef">
                                        <div class="text-[#D72D36] rounded-md bg-[#FBEAEB] p-4 cursor-pointer"
                                            @mouseenter="showScheduleDropdown = true"
                                            @click.stop="showScheduleDropdown = !showScheduleDropdown">
                                            <CalendarIcon class="w-6 h-6" />
                                        </div>
                                        <!-- Dropdown -->
                                        <div v-if="showScheduleDropdown"
                                            class="absolute left-1/2 -translate-x-1/2 top-full mt-2 w-52 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50 animate-in fade-in zoom-in duration-200">
                                            <button @click="goToCreateActivity(); showScheduleDropdown = false"
                                                class="w-full px-4 py-3 text-left text-sm hover:bg-gray-50 flex items-center gap-3 transition-colors">
                                                <svg class="w-5 h-5 text-[#D72D36] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <div>
                                                    <p class="font-semibold text-[#3E414C]">Tạo sự kiện</p>
                                                    <p class="text-[11px] text-[#9EA2B3]">Hoạt động sinh hoạt CLB</p>
                                                </div>
                                            </button>
                                            <div class="border-t border-gray-100 my-1"></div>
                                            <button @click="goToCreateTournament(); showScheduleDropdown = false"
                                                class="w-full px-4 py-3 text-left text-sm hover:bg-gray-50 flex items-center gap-3 transition-colors">
                                                <svg class="w-5 h-5 text-[#D72D36] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                                <div>
                                                    <p class="font-semibold text-[#3E414C]">Tạo kèo</p>
                                                    <p class="text-[11px] text-[#9EA2B3]">Kèo đấu Pickleball</p>
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="text-sm text-[#3E414C]">Tạo lịch</div>
                                </div>

                                <!-- Other modules (excluding schedule) -->
                                <div v-for="(module, index) in filteredClubModules.filter(m => m.key !== 'schedule')" :key="index"
                                    class="flex flex-col items-center gap-2">
                                    <div class="text-[#D72D36] rounded-md bg-[#FBEAEB] p-4 cursor-pointer relative"
                                        @click="handleModuleClick(module)">
                                        <component :is="module.icon" class="w-6 h-6" />
                                        <div v-if="module.key === 'notification' && hasUnreadNotifications"
                                            class="absolute -bottom-1 -right-1 w-5 h-5 bg-white rounded-full flex items-center justify-center animate-bounce-subtle shadow-sm">
                                            <div class="w-3.5 h-3.5 bg-[#D72D36] rounded-full"></div>
                                        </div>
                                    </div>
                                    <div class="text-sm text-[#3E414C]">{{ module.label }}</div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="max-w-3xl mx-auto" v-if="hasAnyRole(['admin', 'secretary'])">
                        <div class="bg-white rounded-2xl shadow-md p-6">
                            <div class="flex items-center gap-2 mb-6">
                                <p class="uppercase font-bold text-[#838799] text-sm">Yêu cầu tham gia</p>
                                <span class="w-1 h-1 rounded-full bg-[#3E414C]"></span>
                                <span class="font-bold text-[#D72D36] text-sm">({{ joiningRequests.length }})</span>
                            </div>

                            <div v-if="joiningRequests.length > 0" class="space-y-6">
                                <template v-for="(request, index) in joiningRequests" :key="request.id">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <img :src="request.user.avatar_url" alt="Avatar"
                                                class="w-12 h-12 rounded-full object-cover border border-gray-100" />
                                            <div>
                                                <h4 class="font-bold text-[#3E414C] text-base">{{ request.user.full_name
                                                    }}</h4>
                                                <p class="text-xs text-[#838799] mt-1">
                                                    Trình {{
                                                        Number(request.user?.sports[0]?.scores?.vndupr_score).toFixed(1) }}
                                                    {{ request.message ? `• ${request.message}` : '' }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-3">
                                            <button
                                                class="w-10 h-10 rounded-full bg-[#D72D36] flex items-center justify-center hover:bg-[#c4252e] transition-colors"
                                                @click="rejectJoinRequest(request.id)">
                                                <XMarkIcon class="w-5 h-5 text-white" stroke-width="2.5" />
                                            </button>
                                            <button
                                                class="w-10 h-10 rounded-full bg-[#00B377] flex items-center justify-center hover:bg-[#00a16b] transition-colors"
                                                @click="approveJoinRequest(request.id)">
                                                <CheckIcon class="w-5 h-5 text-white" stroke-width="2.5" />
                                            </button>
                                        </div>
                                    </div>
                                    <div v-if="index < joiningRequests.length - 1" class="h-px bg-gray-100"></div>
                                </template>
                            </div>
                            <div v-else class="p-4 text-center">
                                <p class="text-[#838799]">Hiện chưa có yêu cầu tham gia nào</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notification Modal -->
            <ClubNotificationModal v-model="isNotificationModalOpen" :notifications="notifications"
                :meta="notificationMeta" :is-loading-more="isLoadingMoreNotifications"
                :is-admin-or-staff="hasAnyRole(['admin', 'manager', 'secretary'])"
                :can-pin-more="pinnedNotifications.length < MAX_PINNED_NOTIFICATIONS"
                @close="closeNotification"
                @load-more="loadMoreNotifications" @mark-as-read="markAsRead" @mark-all-as-read="markAllAsRead"
                @unpin="handleUnpinNotification" @pin="handlePinNotification" @create="handleCreateNotification"
                @click-notification="handleNotificationClick" />

            <!-- Notification Detail Modal -->
            <ClubNotificationDetailModal v-model="isDetailModalOpen" :notification="selectedNotification"
                :is-admin="hasAnyRole(['admin', 'manager', 'secretary'])"
                :is-updating="isUpdatingNotification"
                :notification-types="notificationType"
                @unpin="handleUnpinNotification" @pin="handlePinNotification"
                @update="handleUpdateNotification" @delete="handleDeleteNotificationAsk" />

            <!-- Activity Schedule Modal -->
            <ClubActivityModal :is-open="isActivityModalOpen" :thumbnail="Thumbnail"
                :upcoming-activities="upcomingActivities" :history-activities="historyActivities"
                :next-match="nextMatch" :countdown="countdownText"
                :is-loading-upcoming="isLoadingMoreUpcoming" :is-loading-history="isLoadingMoreHistory"
                :has-more-upcoming="currentUpcomingPage < upcomingMeta.last_page"
                :has-more-history="currentHistoryPage < historyMeta.last_page"
                @close="closeActivityModal"
                @edit="handleEditActivity" @click-card="goToActivityDetail" @register="handleRegisterActivity"
                @cancel-join="handleCancelJoinActivity" @self-absent="handleSelfAbsentActivity" @check-in="handleSelfCheckInActivity"
                @load-more-upcoming="handleLoadMoreUpcoming" @load-more-history="handleLoadMoreHistory" />

            <!-- Edit Club Modal -->
            <ClubEditModal v-model="isEditModalOpen" :club="club" :is-loading="isUpdatingClub"
                @save="handleUpdateClub" />

            <!-- Zalo Modal -->
            <ClubZaloModal v-model="isZaloModalOpen" :club="club" :is-loading="isUpdatingZalo"
                @save="handleUpdateZalo" />

            <!-- Create Notification Modal -->
            <ClubCreateNotificationModal v-model="isCreateNotificationModalOpen" :club="club"
                :is-loading="isCreatingNotification" @create="handleCreateNotificationSubmit"
                :notification-type="notificationType" />

            <DeleteConfirmationModal v-model="isDeleteModalOpen" title="Xoá câu lạc bộ"
                message="Bạn có chắc chắn muốn xoá câu lạc bộ này? Thao tác này không thể hoàn tác."
                confirmButtonText="Xoá ngay" @confirm="confirmDeleteClub" />

            <ClubTransferModal v-model="isTransferModalOpen" :club-id="clubId" :is-submitting="isSubmittingTransfer"
                @confirm="handleTransferConfirm" />

            <!-- Unpin Confirmation Modal -->
            <DeleteConfirmationModal v-model="isUnpinModalOpen" title="Bỏ ghim thông báo"
                message="Bạn muốn bỏ ghim thông báo này?" confirmButtonText="Bỏ ghim"
                @confirm="confirmUnpinNotification" />

            <!-- Pin Confirmation Modal -->
            <DeleteConfirmationModal v-model="isPinModalOpen" title="Ghim thông báo"
                message="Bạn muốn ghim thông báo này? (Tối đa 3 thông báo được ghim)" confirmButtonText="Ghim ngay"
                confirmButtonClass="!bg-[#00B377] hover:!bg-[#009664]"
                @confirm="confirmPinNotification" />

            <!-- Delete Notification Confirmation Modal -->
            <DeleteConfirmationModal v-model="isDeleteNotificationModalOpen" title="Xoá thông báo"
                message="Bạn có chắc chắn muốn xoá thông báo này? Thao tác này không thể hoàn tác."
                confirmButtonText="Xoá ngay" @confirm="confirmDeleteNotification" />

            <!-- Zalo QR Modal -->
            <Transition name="modal">
                <div v-if="isZaloQRModalOpen" class="fixed inset-0 z-[10000] flex items-center justify-center">
                    <Transition name="backdrop">
                        <div v-if="isZaloQRModalOpen" class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                            @click="isZaloQRModalOpen = false"></div>
                    </Transition>
                    <Transition name="modal-content">
                        <div v-if="isZaloQRModalOpen"
                            class="relative bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full mx-4 z-10">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-[#3E414C]">Mã QR nhóm Zalo</h3>
                                <button @click="isZaloQRModalOpen = false"
                                    class="text-gray-400 hover:text-gray-600 transition-colors">
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>
                            <div class="flex flex-col items-center">
                                <img :src="club.profile?.qr_code_image_url" alt="Zalo QR Code"
                                    class="w-64 h-64 object-contain rounded-lg border border-gray-200" />
                                <p class="text-sm text-[#838799] mt-4 text-center">Quét mã QR để tham gia nhóm Zalo của
                                    câu lạc bộ
                                </p>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>

            <!-- Zalo Link Confirmation Modal -->
            <Transition name="modal">
                <div v-if="isZaloLinkConfirmModalOpen" class="fixed inset-0 z-[10000] flex items-center justify-center">
                    <Transition name="backdrop">
                        <div v-if="isZaloLinkConfirmModalOpen" class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                            @click="isZaloLinkConfirmModalOpen = false"></div>
                    </Transition>
                    <Transition name="modal-content">
                        <div v-if="isZaloLinkConfirmModalOpen"
                            class="relative bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full mx-4 z-10">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-[#3E414C]">Mở nhóm Zalo</h3>
                                <button @click="isZaloLinkConfirmModalOpen = false"
                                    class="text-gray-400 hover:text-gray-600 transition-colors">
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>
                            <div class="mb-6">
                                <p class="text-[#838799] text-center">Bạn có muốn mở nhóm Zalo của câu lạc bộ không?</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button @click="isZaloLinkConfirmModalOpen = false"
                                    class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 text-[#3E414C] font-medium hover:bg-gray-50 transition-colors">
                                    Hủy
                                </button>
                                <button @click="confirmOpenZaloLink"
                                    class="flex-1 px-4 py-2.5 rounded-lg bg-[#D72D36] text-white font-medium hover:bg-[#c4252e] transition-colors">
                                    Xác nhận
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>

            <!-- Footer -->
            <div v-if="club.profile?.footer || club.footer" class="mt-8 pt-6 pb-4 border-t border-gray-100 text-center font-bold text-[#838799] text-sm w-full">
                <p class="whitespace-pre-line leading-relaxed">{{ club.profile?.footer || club.footer }}</p>
            </div>
        </template>
    </div>
    <InviteGroup v-model="showInviteModal" :data="inviteGroupData" :clubs="clubs" :active-scope="activeScope"
        :search-query="searchQuery" :current-radius="currentRadius" :current-club-id="selectedClub"
        :is-loading-more="isLoadingMoreInvite" :has-more="hasMoreInvite" @update:searchQuery="onSearchChange"
        @change-scope="onScopeChange" @change-club="onClubChange" @update:radius="onRadiusChange"
        @invite="handleInviteAction" @load-more="loadMoreInviteUsers" title="Mời thành viên" />
    <ClubReportModal v-model="isReportModalOpen" :is-loading="isReportingClub" @submit="submitClubReport" />
    <PromotionModal
        v-model="isPromotionModalOpen"
        promotable-type="club"
        :promotable-id="Number(clubId)"
        @success="toast.success('Đã gửi quảng bá thành công')"
    />
</template>

<script setup>
import Background from '@/assets/images/club-default-thumbnail.svg?url'
import InviteGroup from '@/components/molecules/InviteGroup.vue'
import Thumbnail from "@/assets/images/dashboard-bg.svg?url";
import {
    ArrowLeftIcon,
    ShareIcon,
    EllipsisVerticalIcon,
    MapPinIcon,
    PlusIcon,
    BellIcon,
    ArrowLeftOnRectangleIcon,
    InformationCircleIcon,
    XMarkIcon,
    CheckIcon,
    TrashIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline'
import CampaignIcon from "@/assets/images/campaign.svg";
import { useRouter, useRoute } from 'vue-router'
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import VerifyIcon from "@/assets/images/verify-icon.svg";
import Button from '@/components/atoms/Button.vue';
import MessageIcon from "@/assets/images/message.svg";
import ChangeCircleIcon from "@/assets/images/change_circle.svg";
import CompareArrowsIcon from "@/assets/images/compare_arrows.svg";
import EditNoteIcon from "@/assets/images/edit_note.svg";
import ZaloIcon from "@/assets/images/zalo.svg";
import ActivityScheduleCard from '@/components/molecules/ActivityScheduleCard.vue';
import NotificationCard from '@/components/molecules/NotificationCard.vue';
import ClubInfoTabs from '@/components/organisms/ClubInfoTabs.vue';
import ClubEditModal from '@/components/organisms/ClubEditModal.vue';
import ClubZaloModal from '@/components/organisms/ClubZaloModal.vue';
import ClubNotificationModal from '@/components/organisms/ClubNotificationModal.vue';
import ClubNotificationDetailModal from '@/components/organisms/ClubNotificationDetailModal.vue';
import { CLUB_STATS, CLUB_MODULES } from '@/data/club/index.js';
import * as ClubService from '@/service/club.js'
import { toast } from 'vue3-toastify';
import { useUserStore } from '@/store/auth'
import { storeToRefs } from 'pinia'
import ClubActivityModal from '@/components/organisms/ClubActivityModal.vue';
import ClubCreateNotificationModal from '@/components/organisms/ClubCreateNotificationModal.vue';
import ClubTransferModal from '@/components/organisms/ClubTransferModal.vue';
import DeleteConfirmationModal from '@/components/molecules/DeleteConfirmationModal.vue';
import dayjs from 'dayjs'
import 'dayjs/locale/vi'
import ClubDetailSkeleton from '@/components/molecules/ClubDetailSkeleton.vue';
import UserPlusIcon from '@/assets/images/group_add_member.svg';
import debounce from 'lodash.debounce'
import { getVietnameseDay } from '@/composables/formatedDate'
import { getRoleName } from '@/helpers/role'
import ClubReportModal from '@/components/organisms/ClubReportModal.vue'
import PromotionModal from '@/components/organisms/PromotionModal.vue'

dayjs.locale('vi')

const router = useRouter()
const route = useRoute()
const clubStats = CLUB_STATS;
const clubModules = CLUB_MODULES;
const isMenuOpen = ref(false)
const isChangeClubOpen = ref(false)
const showScheduleDropdown = ref(false)
const isNotificationModalOpen = ref(false)
const isActivityModalOpen = ref(false)
const isEditModalOpen = ref(false)
const isZaloModalOpen = ref(false)
const isCreateNotificationModalOpen = ref(false)
const isDetailModalOpen = ref(false)
const isDeleteModalOpen = ref(false)
const isTransferModalOpen = ref(false)
const isZaloQRModalOpen = ref(false)
const isZaloLinkConfirmModalOpen = ref(false)
const isUpdatingClub = ref(false)
const isUpdatingZalo = ref(false)
const isCreatingNotification = ref(false)
const isUpdatingNotification = ref(false)
const isSubmittingTransfer = ref(false)
const isUnpinModalOpen = ref(false)
const isPinModalOpen = ref(false)
const isDeleteNotificationModalOpen = ref(false)
const notificationToUnpin = ref(null)
const notificationToPin = ref(null)
const notificationToDelete = ref(null)
const isUpdatingIntro = ref(false)
const club = ref([]);
const clubId = ref(route.params.id);
const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)
const notifications = ref([])
const pinnedNotifications = ref([])
const notificationMeta = ref({})
const currentNotificationPage = ref(1)
const notificationPerPage = ref(15)
const isLoadingMoreNotifications = ref(false)
const myClubs = ref([])
const activities = ref([])
const upcomingActivities = ref([])
const historyActivities = ref([])
const upcomingMeta = ref({})
const historyMeta = ref({})
const currentUpcomingPage = ref(1)
const currentHistoryPage = ref(1)
const isLoadingMoreUpcoming = ref(false)
const isLoadingMoreHistory = ref(false)
const activityPerPage = ref(20)
const fund = ref([])
const notificationType = ref([])
const hasFetchedLeaderboard = ref(false)
const showInviteModal = ref(false)
const clubs = ref([])
const selectedClub = ref(null);
const activeScope = ref('all');
const searchQuery = ref('');
const inviteGroupData = ref([]);
const invitePage = ref(1)
const isLoadingMoreInvite = ref(false)
const hasMoreInvite = ref(true)
const userLatitude = ref(null);
const userLongitude = ref(null);
const currentRadius = ref(10);
const topThree = ref([])
const leaderboard = ref([])
const leaderboardMeta = ref({})
const leaderboardFilters = ref({
    month: dayjs().month() + 1,
    year: dayjs().year(),
    page: 1,
    per_page: 10
})
const isInitialLoading = ref(true)
const isLeaderboardLoading = ref(false)
const joiningRequests = ref([])
const selectedNotification = ref(null)
const currentTime = ref(dayjs())
let timeInterval = null

const nextMatch = computed(() => {
    const now = currentTime.value

    const match = activities.value
        .filter(a => a.status !== 'cancelled' && a.status !== 'completed' && dayjs(a.end_time || dayjs(a.start_time).add(2, 'hour')).isAfter(now))
        .sort((a, b) =>
            dayjs(a.start_time).diff(dayjs(b.start_time))
        )

    return match[0] || null
})

const countdownText = computed(() => {
    if (!nextMatch.value) return ''

    const now = currentTime.value
    const start = dayjs(nextMatch.value.start_time)
    const diff = start.diff(now)

    if (diff <= 0) {
        return 'Đang diễn ra'
    }

    const hours = Math.floor(diff / (1000 * 60 * 60))
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
    const seconds = Math.floor((diff % (1000 * 60)) / 1000)

    const formattedMinutes = minutes.toString().padStart(2, '0')
    const formattedSeconds = seconds.toString().padStart(2, '0')

    if (hours > 0) {
        return `${hours.toString().padStart(2, '0')}:${formattedMinutes}:${formattedSeconds}`
    }
    return `${formattedMinutes}:${formattedSeconds}`
})

const isReportModalOpen = ref(false)
const isReportingClub = ref(false)
const isPromotionModalOpen = ref(false)

const currentUserMember = computed(() => {
    return club.value?.members?.find(member => member.user_id === getUser.value.id) || null
})

const hasAnyRole = (roles = []) => {
    return roles.includes(currentUserMember.value?.role)
}

const currentUserRole = computed(() => {
    return currentUserMember.value?.role || null
})

const adminCount = computed(() => {
    return club.value?.members?.filter(member => member.role === 'admin').length || 0
})

const inviteMembers = async () => {
    invitePage.value = 1
    hasMoreInvite.value = true
    searchQuery.value = ''
    activeScope.value = 'all'
    await getInviteGroupData()
    showInviteModal.value = true
}

const getMyClubs = async () => {
    if (!hasAnyRole(['admin', 'secretary', 'manager', 'manager'])) {
        return
    }
    try {
        const response = await ClubService.myClubs();
        myClubs.value = response || [];
        clubs.value = response || [];

        if (clubs.value.length === 0) {
            selectedClub.value = null;
        } else {
            selectedClub.value = clubs.value[0].id;
        }
    } catch (e) {
        clubs.value = [];
        selectedClub.value = null;
    }
};

const statsAdmin = computed(() => {
    const now = dayjs()
    const fundBalance = Number(fund.value?.balance ?? 0)
    const activitiesThisWeek = activities.value?.filter(a =>
        dayjs(a.start_time).isSame(now, 'week')
    ).length ?? 0

    return [
        {
            label: 'Quỹ hiện tại',
            value: fundBalance.toLocaleString(),
            unit: fund.value?.currency,
            unitClass: 'text-[#00B377]'
        },
        {
            label: 'Thành viên',
            value: club.value?.quantity_members ?? 0,
            unit: 'người'
        },
        {
            label: 'Hoạt động tuần này',
            value: activitiesThisWeek,
            unit: 'Buổi'
        }
    ]
})

const handleCampaign = () => {
    isPromotionModalOpen.value = true
}

const statsValue = computed(() => {
    const sl = club.value?.skill_level
    const levelStr = sl && sl.min != null && sl.max != null ? (sl.min === sl.max ? `${sl.min}` : `${sl.min} - ${sl.max}`) : '-'
    return {
        members: club.value?.quantity_members ?? 0,
        level: levelStr,
        price: club.value?.rank ?? '-'
    }
});

const filteredClubModules = computed(() => {
    return clubModules.filter(module => {
        if (module.key === 'chat') {
            return club.value?.profile?.qr_zalo_enabled || club.value?.profile?.zalo_link_enabled
        }
        return true
    })
})

const hasUnreadNotifications = computed(() => {
    return notifications.value.some(n => !n.is_read_by_me) || pinnedNotifications.value.some(n => !n.is_read_by_me)
})

const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value
    if (isMenuOpen.value) {
        isChangeClubOpen.value = false
    }
}

const toggleChangeClub = () => {
    isChangeClubOpen.value = !isChangeClubOpen.value
    if (isChangeClubOpen.value) {
        isMenuOpen.value = false
    }
}

const closeMenu = () => {
    isMenuOpen.value = false
    isChangeClubOpen.value = false
    showScheduleDropdown.value = false
}

const closeChangeClub = () => {
    isChangeClubOpen.value = false
}

const changeClub = (item) => {
    isChangeClubOpen.value = false
    router.push({ name: 'club-detail', params: { id: item.id } })
}

const shareClub = async () => {
    const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
    if (isMobile && navigator.share) {
        try {
            await navigator.share({
                title: club.value?.name,
                text: `Tham gia câu lạc bộ ${club.value?.name}`,
                url: window.location.href,
            });
        } catch (error) {
            console.error('Lỗi khi chia sẻ:', error);
        }
    } else {
        try {
            await navigator.clipboard.writeText(window.location.href);
            toast.success('Đã sao chép link câu lạc bộ');
        } catch (error) {
            console.error('Lỗi khi sao chép:', error);
            toast.error('Không thể sao chép link');
        }
    }
}

const openNotification = () => {
    isNotificationModalOpen.value = true
}

const openEditModal = () => {
    isEditModalOpen.value = true
}

const openZaloModal = () => {
    isZaloModalOpen.value = true
}

const openTransferModal = () => {
    isTransferModalOpen.value = true
}

watch(
    () => [
        isNotificationModalOpen.value,
        isEditModalOpen.value,
        isZaloModalOpen.value,
        isTransferModalOpen.value,
        isTransferModalOpen.value,
        isUnpinModalOpen.value,
        isPinModalOpen.value,
        isDetailModalOpen.value,
        isReportModalOpen.value,
    ],
    (values) => {
        if (values.some(v => v)) {
            closeMenu()
            closeChangeClub()
        }
    }
)

const closeNotification = () => {
    isNotificationModalOpen.value = false
}

const openActivityModal = () => {
    isActivityModalOpen.value = true
    getMoreUpcomingActivities()
    getMoreHistoryActivities()
}

const closeActivityModal = () => {
    isActivityModalOpen.value = false
}

const openClubChat = () => {
    // Priority: zalo_link_enabled > qr_zalo_enabled
    if (club.value?.profile?.zalo_link_enabled && club.value?.profile?.zalo_link) {
        // Show confirmation modal before redirecting to Zalo
        isZaloLinkConfirmModalOpen.value = true
    } else if (club.value?.profile?.qr_zalo_enabled && club.value?.profile?.qr_code_image_url) {
        // Show QR modal
        isZaloQRModalOpen.value = true
    } else {
        toast.info('Chức năng nhóm chat chưa được cấu hình')
    }
}

const handleModuleClick = (module) => {
    if (module.key === 'schedule') {
        showScheduleDropdown.value = !showScheduleDropdown.value
    } else if (module.key === 'notification') {
        openNotification()
    } else if (module.key === 'chat') {
        openClubChat()
    } else if (module.key === 'fund') {
        router.push({ name: 'club-fund', params: { id: clubId.value } })
    }
}

const goToCreateActivity = () => {
    if (!hasAnyRole(['admin', 'manager', 'secretary'])) {
        toast.warning('Bạn không có quyền thực hiện chức năng này')
        return
    }
    showScheduleDropdown.value = false
    router.push({ name: 'club-create-activity', params: { id: clubId.value } })
}

const goToCreateTournament = () => {
    if (!hasAnyRole(['admin', 'manager', 'secretary'])) {
        toast.warning('Bạn không có quyền thực hiện chức năng này')
        return
    }
    showScheduleDropdown.value = false
    router.push({ name: 'create-mini-tournament', query: { clubId: clubId.value } })
}

const handleCreateNotification = async () => {
    await getNotificationType()
    isCreateNotificationModalOpen.value = true
    isNotificationModalOpen.value = false
}

const handleNotificationClick = (notification) => {
    selectedNotification.value = notification
    isDetailModalOpen.value = true
}

const handleCreateNotificationSubmit = async (data) => {
    isCreatingNotification.value = true
    try {
        const formData = new FormData()
        formData.append('title', data.title)
        formData.append('content', data.content)
        formData.append('club_notification_type_id', data.club_notification_type_id)
        formData.append('is_pinned', data.is_pinned)
        formData.append('status', data.status)
        if (Array.isArray(data.user_ids)) {
            data.user_ids.forEach(id => {
                formData.append('user_ids[]', id)
            })
        } else {
            formData.append('user_ids[]', data.user_ids)
        }

        if (data.attachment) {
            formData.append('attachment', data.attachment)
        }

        await ClubService.createNotification(clubId.value, formData)
        await getClubNotification()
        await getPinnedNotifications()
        isCreateNotificationModalOpen.value = false
        toast.success('Tạo thông báo thành công')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi tạo thông báo')
    } finally {
        isCreatingNotification.value = false
    }
}

const handleUpdateNotification = async (data) => {
    isUpdatingNotification.value = true
    try {
        const formData = new FormData()
        formData.append('title', data.title)
        formData.append('content', data.content || '')
        if (data.club_notification_type_id) {
            formData.append('club_notification_type_id', data.club_notification_type_id)
        }
        if (data.attachment) {
            formData.append('attachment', data.attachment)
        }
        await ClubService.updateNotification(clubId.value, data.id, formData)
        await getClubNotification()
        await getPinnedNotifications()
        // Update selectedNotification to reflect changes in view mode
        const updated = notifications.value.find(n => n.id === data.id) ||
            pinnedNotifications.value.find(n => n.id === data.id)
        if (updated) selectedNotification.value = updated
        toast.success('Cập nhật thông báo thành công')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi cập nhật thông báo')
    } finally {
        isUpdatingNotification.value = false
    }
}

const handleDeleteNotificationAsk = (id) => {
    notificationToDelete.value = id
    isDeleteNotificationModalOpen.value = true
}

const confirmDeleteNotification = async () => {
    try {
        await ClubService.deleteNotification(clubId.value, notificationToDelete.value)
        await getClubNotification()
        await getPinnedNotifications()
        isDeleteNotificationModalOpen.value = false
        isDetailModalOpen.value = false // close detail modal if it's open
        toast.success('Xoá thông báo thành công')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi xoá thông báo')
    }
}

const getNotificationType = async () => {
    try {
        const types = await ClubService.getNotificationType(clubId.value)
        notificationType.value = Array.isArray(types) ? types : []
    } catch (error) {
        console.error('Error fetching notification types:', error)
        notificationType.value = []
        toast.error(error.response?.data?.message || 'Không thể tải danh sách loại thông báo')
    }
}

const handleEditActivity = (activity) => {
    router.push({
        name: 'club-activity-edit',
        params: { id: clubId.value, activityId: activity.id }
    })
}

const goToActivityDetail = (activity) => {
    router.push({
        name: 'club-detail-activity',
        params: { id: clubId.value, activityId: activity.id }
    })
}

const formatActivity = (item) => {
    const userId = getUser.value.id
    const isCancelled = item.status === 'cancelled'
    const isCompleted = item.status === 'completed' || dayjs().isAfter(dayjs(item.end_time)) || isCancelled
    const userParticipant = item.participants?.find(p => p.user_id === userId)
    const isRegistered = !!userParticipant

    let registrationStatus = 'none'
    if (userParticipant) {
        if (userParticipant.status === 'pending') {
            registrationStatus = 'pending'
        } else if (userParticipant.status === 'attended') {
            registrationStatus = 'attended'
        } else if (userParticipant.status === 'accepted' || !userParticipant.status) {
            registrationStatus = 'accepted'
        }
    }

    // Determine type (border color)
    let type = 'danger' // Default to red (not registered)
    if (isCompleted) {
        type = 'secondary' // Gray
    } else if (isRegistered) {
        type = 'primary' // Blue (registered/pending)
    }

    // Determine status badge (colors) - ActivityScheduleCard & ActivitySmallCard
    let status = item.is_public ? 'open' : 'private'
    if (isCancelled) status = 'cancelled'
    else if (isCompleted) status = 'completed'

    // Determine statusText (label) - ActivitySmallCard & updated ActivityScheduleCard
    let statusText = item.is_public ? 'Công khai' : 'Nội bộ'
    if (isCancelled) statusText = 'Đã hủy'
    else if (isCompleted) statusText = 'Hoàn tất'

    // Determine button text
    let buttonText = 'Đăng ký'
    if (isCompleted) {
        buttonText = isCancelled ? 'Đã hủy' : 'Đã xong'
    } else if (registrationStatus === 'attended') {
        buttonText = 'Đã check-in'
    } else if (isRegistered) {
        buttonText = registrationStatus === 'pending' ? 'Đang chờ duyệt' : 'Check-in ngay'
    }

    return {
        ...item,
        day: getVietnameseDay(item.start_time),
        date: dayjs(item.start_time).format('DD'),
        time: `${dayjs(item.start_time).format('HH:mm')} - ${dayjs(item.end_time).format('HH:mm')}`,
        participants: `${item.participants_count}/${item.max_participants || '∞'}`,
        status,
        statusText,
        buttonText,
        type,
        disabled: isCompleted,
        isCreator: item.created_by === getUser.value.id,
        location: item.location,
        participants_list: item.participants || [],
        result: isCompleted ? `Địa điểm: ${item.location}` : null,
        registrationStatus
    }
}

const getClubDetail = async () => {
    try {
        const response = await ClubService.clubDetail(clubId.value)
        club.value = response
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy thông tin câu lạc bộ')
    }
}

const handleUpdateIntro = async (newDescription) => {
    isUpdatingIntro.value = true
    try {
        const formData = new FormData()
        formData.append('description', newDescription)

        await ClubService.updateClub(clubId.value, formData)
        await getClubDetail()
        toast.success('Cập nhật giới thiệu thành công')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi cập nhật giới thiệu')
    } finally {
        isUpdatingIntro.value = false
    }
}

const handleUpdateClub = async (data) => {
    isUpdatingClub.value = true
    try {
        const formData = new FormData()
        Object.keys(data).forEach(key => {
            if (data[key] !== null && data[key] !== undefined) {
                if (key === 'cover_file' && data[key]) {
                    formData.append('cover_image_url', data[key])
                } else if (key === 'logo_file' && data[key]) {
                    formData.append('logo_url', data[key])
                } else if (!['cover_image_url', 'logo_url', 'cover_file', 'logo_file'].includes(key)) {
                    formData.append(key, data[key])
                }
            }
        })

        await ClubService.updateClub(clubId.value, formData)
        await getClubDetail()
        isEditModalOpen.value = false
        toast.success('Cập nhật thông tin CLB thành công')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi cập nhật thông tin')
    } finally {
        isUpdatingClub.value = false
    }
}

const handleUpdateZalo = async (data) => {
    isUpdatingZalo.value = true
    try {
        const formData = new FormData()
        formData.append('zalo_link_enabled', data.zalo_link_enabled ? '1' : '0')
        formData.append('zalo_link', data.zalo_link || '')
        formData.append('qr_zalo_enabled', data.qr_zalo_enabled ? '1' : '0')

        if (data.qr_code_image_url instanceof File) {
            formData.append('qr_code_image_url', data.qr_code_image_url)
        }

        await ClubService.updateClub(clubId.value, formData)
        await getClubDetail()
        isZaloModalOpen.value = false
        toast.success('Cập nhật thông tin Zalo thành công')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi cập nhật thông tin')
    } finally {
        isUpdatingZalo.value = false
    }
}

const joinClubRequest = async () => {
    try {
        await ClubService.joinRequest(clubId.value)
        await getClubDetail(clubId.value)
        toast.success('Yêu cầu tham gia đã được gửi thành công')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi gửi yêu cầu tham gia')
    }
}

const cancelJoinRequest = async () => {
    try {
        await ClubService.cancelJoinRequest(clubId.value)
        await getClubDetail(clubId.value)
        toast.success('Đã huỷ yêu cầu tham gia')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi huỷ yêu cầu tham gia')
    }
}

const leaveClub = async () => {
    try {
        await ClubService.leaveClub(clubId.value)
        await getClubDetail(clubId.value)
        isMenuOpen.value = false
        toast.success('Đã rời câu lạc bộ')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi rời câu lạc bộ')
    }
}

const getClubNotification = async (append = false) => {
    if (append) {
        isLoadingMoreNotifications.value = true
    }
    try {
        const response = await ClubService.clubNotification(clubId.value, {
            page: currentNotificationPage.value,
            per_page: notificationPerPage.value,
            status: 'sent'
        })
        if (append) {
            notifications.value = [...notifications.value, ...response.data.notifications]
        } else {
            notifications.value = response.data.notifications
        }
        notificationMeta.value = response.meta
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy thông tin thông báo')
    } finally {
        isLoadingMoreNotifications.value = false
    }
}

const loadMoreNotifications = async () => {
    if (currentNotificationPage.value < notificationMeta.value.last_page) {
        currentNotificationPage.value++
        await getClubNotification(true)
    }
}

const getPinnedNotifications = async () => {
    try {
        const response = await ClubService.clubNotification(clubId.value, {
            is_pinned: true,
            status: 'sent',
            per_page: 10
        })
        pinnedNotifications.value = response.data.notifications
    } catch (error) {
        console.error('Error fetching pinned notifications:', error)
    }
}

const markAsRead = async (notificationId) => {
    try {
        await ClubService.markAsRead(clubId.value, notificationId)
        await Promise.all([
            getClubNotification(),
            getPinnedNotifications()
        ])
        if (selectedNotification.value && selectedNotification.value.id === notificationId) {
            selectedNotification.value = { ...selectedNotification.value, is_read_by_me: true }
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi đánh dấu thông báo đã đọc')
    }
}

const markAllAsRead = async () => {
    try {
        await ClubService.markAllAsRead(clubId.value)
        currentNotificationPage.value = 1
        await Promise.all([
            getClubNotification(),
            getPinnedNotifications()
        ])
        toast.success('Đánh dấu tất cả thông báo đã đọc')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi đánh dấu tất cả thông báo đã đọc')
    }
}

const handleUnpinNotification = (notificationId) => {
    if (!hasAnyRole(['admin', 'manager', 'secretary'])) return
    notificationToUnpin.value = notificationId
    isUnpinModalOpen.value = true
}

const confirmUnpinNotification = async () => {
    if (!notificationToUnpin.value) return
    try {
        await ClubService.togglePin(clubId.value, notificationToUnpin.value)
        const unpinnedId = notificationToUnpin.value
        await Promise.all([
            getClubNotification(),
            getPinnedNotifications()
        ])
        if (selectedNotification.value && selectedNotification.value.id === unpinnedId) {
            selectedNotification.value = { ...selectedNotification.value, is_pinned: false }
        }
        toast.success('Đã gỡ ghim thông báo')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi gỡ ghim thông báo')
    } finally {
        notificationToUnpin.value = null
    }
}

const MAX_PINNED_NOTIFICATIONS = 3

const handlePinNotification = (notificationId) => {
    if (!hasAnyRole(['admin', 'manager', 'secretary'])) return
    const pinnedCount = notifications.value.filter(n => n.is_pinned).length
    if (pinnedCount >= MAX_PINNED_NOTIFICATIONS) {
        toast.error('Đã đạt giới hạn ghim thông báo. Vui lòng gỡ bớt thông báo ghim trước khi ghim thêm.')
        return
    }
    notificationToPin.value = notificationId
    isPinModalOpen.value = true
}

const confirmPinNotification = async () => {
    if (!notificationToPin.value) return
    try {
        await ClubService.togglePin(clubId.value, notificationToPin.value)
        const pinnedId = notificationToPin.value
        await Promise.all([
            getClubNotification(),
            getPinnedNotifications()
        ])
        if (selectedNotification.value && selectedNotification.value.id === pinnedId) {
            selectedNotification.value = { ...selectedNotification.value, is_pinned: true }
        }
        toast.success('Đã ghim thông báo')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi ghim thông báo')
    } finally {
        notificationToPin.value = null
    }
}

const deleteClub = () => {
    isDeleteModalOpen.value = true
}

const is_joined = computed(() => {
    return club.value?.members?.some(member => member.user_id === getUser.value.id && member.status == 'active') ?? false
})

const goBack = () => {
    router.back()
}

const getClubActivities = async () => {
    try {
        const response = await ClubService.getClubActivities(clubId.value, {
            page: 1,
            per_page: activityPerPage.value,
            statuses: ['scheduled', 'ongoing'],
        })
        const activitiesData = response.data?.activities
        const activitiesList = Array.isArray(activitiesData) ? activitiesData : (activitiesData?.data || [])
        const allActivities = activitiesList.map(formatActivity)
        activities.value = allActivities.slice(0, 5)
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy thông tin hoạt động')
    }
}

const getMoreUpcomingActivities = async (append = false) => {
    if (append) {
        isLoadingMoreUpcoming.value = true
    } else {
        currentUpcomingPage.value = 1
    }
    try {
        const response = await ClubService.getClubActivities(clubId.value, {
            page: currentUpcomingPage.value,
            per_page: activityPerPage.value,
            statuses: ['scheduled', 'ongoing']
        })
        const activitiesData = response.data?.activities
        const activitiesList = Array.isArray(activitiesData) ? activitiesData : (activitiesData?.data || [])
        const formatted = activitiesList.map(formatActivity)
        if (append) {
            upcomingActivities.value = [...upcomingActivities.value, ...formatted]
        } else {
            upcomingActivities.value = formatted
        }
        upcomingMeta.value = response.meta
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy danh sách hoạt động sắp tới')
    } finally {
        isLoadingMoreUpcoming.value = false
    }
}

const getMoreHistoryActivities = async (append = false) => {
    if (append) {
        isLoadingMoreHistory.value = true
    } else {
        currentHistoryPage.value = 1
    }
    try {
        const response = await ClubService.getClubActivities(clubId.value, {
            page: currentHistoryPage.value,
            per_page: activityPerPage.value,
            statuses: ['completed', 'cancelled']
        })
        const activitiesData = response.data?.activities
        const activitiesList = Array.isArray(activitiesData) ? activitiesData : (activitiesData?.data || [])
        const formatted = activitiesList.map(formatActivity)
        if (append) {
            historyActivities.value = [...historyActivities.value, ...formatted]
        } else {
            historyActivities.value = formatted
        }
        historyMeta.value = response.meta
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy lịch sử hoạt động')
    } finally {
        isLoadingMoreHistory.value = false
    }
}

const handleLoadMoreUpcoming = async () => {
    if (currentUpcomingPage.value < upcomingMeta.value.last_page) {
        currentUpcomingPage.value++
        await getMoreUpcomingActivities(true)
    }
}

const handleLoadMoreHistory = async () => {
    if (currentHistoryPage.value < historyMeta.value.last_page) {
        currentHistoryPage.value++
        await getMoreHistoryActivities(true)
    }
}

const getClubLeaderBoard = async () => {
    isLeaderboardLoading.value = true
    try {
        const response = await ClubService.getClubLeaderBoard(clubId.value, {
            month: leaderboardFilters.value.month,
            year: leaderboardFilters.value.year,
            per_page: leaderboardFilters.value.per_page,
            page: leaderboardFilters.value.page,
        })

        const allData = response.data.leaderboard

        if (leaderboardFilters.value.page === 1) {
            topThree.value = allData.slice(0, 3)
            leaderboard.value = allData.slice(3)
        } else {
            leaderboard.value = allData
        }

        leaderboardMeta.value = response.meta
        hasFetchedLeaderboard.value = true
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy thông tin xếp hạng')
    } finally {
        isLeaderboardLoading.value = false
    }
}

const getClubJoiningRequests = async () => {
    if (!hasAnyRole(['admin', 'secretary'])) {
        isInitialLoading.value = false
        return
    }
    try {
        const response = await ClubService.joiningRequests(clubId.value)
        joiningRequests.value = response.data
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy thông tin yêu cầu tham gia')
    }
}

const approveJoinRequest = async (requestId) => {
    try {
        await ClubService.approveJoinRequest(clubId.value, requestId)
        await getClubJoiningRequests()
        toast.success('Đã duyệt yêu cầu tham gia')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi duyệt yêu cầu tham gia')
    }
}

const rejectJoinRequest = async (requestId) => {
    try {
        await ClubService.rejectJoinRequest(clubId.value, requestId)
        await getClubJoiningRequests()
        toast.success('Đã từ chối yêu cầu tham gia')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi từ chối yêu cầu tham gia')
    }
}

const handleLeaderboardFilter = (filters) => {
    leaderboardFilters.value = { ...leaderboardFilters.value, ...filters, page: 1 }
    getClubLeaderBoard()
}

const handleLeaderboardPageChange = (page) => {
    leaderboardFilters.value.page = page
    getClubLeaderBoard()
}

const getFund = async () => {
    try {
        const response = await ClubService.getFund(clubId.value)
        fund.value = response.data
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy thông tin quỹ')
    }
}

const handleTabChange = (tabId) => {
    if (tabId === 'ranking' && !hasFetchedLeaderboard.value) {
        getClubLeaderBoard()
    }
}

const loadAllData = async () => {
    isInitialLoading.value = true
    try {
        await getClubDetail()
        await Promise.all([
            getClubNotification(),
            getPinnedNotifications(),
            getClubActivities(),
            getClubJoiningRequests(),
            getFund(),
            getMyClubs()
        ])
    } finally {
        isInitialLoading.value = false
    }
}


const confirmDeleteClub = async () => {
    try {
        await ClubService.deleteClub(clubId.value)
        toast.success('Xoá câu lạc bộ thành công')
        router.push({ name: 'club' })
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi xoá câu lạc bộ')
    }
}

const handleTransferConfirm = async (transferToUserId) => {
    isSubmittingTransfer.value = true
    try {
        await ClubService.leaveClub(clubId.value, {
            transfer_to_user_id: transferToUserId
        })
        toast.success('Nhượng câu lạc bộ và rời đi thành công')
        isTransferModalOpen.value = false
        router.push({ name: 'club' })
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi nhượng câu lạc bộ')
    } finally {
        isSubmittingTransfer.value = false
    }
}

watch(() => route.params.id, (newId) => {
    if (newId && newId !== clubId.value) {
        clubId.value = newId
        hasFetchedLeaderboard.value = false
        loadAllData()
    }
})

const acceptJoinClubInvitation = async () => {
    try {
        await ClubService.acceptInvite(clubId.value)
        toast.success('Bạn đã tham gia CLB thành công')
        await getClubDetail()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi chấp nhận lời mời')
    }
}

const rejectJoinClubInvitation = async () => {
    try {
        await ClubService.declineInvite(clubId.value)
        toast.success('Đã từ chối lời mời tham gia CLB')
        await getClubDetail()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi từ chối lời mời')
    }
}

const confirmOpenZaloLink = () => {
    if (club.value?.profile?.zalo_link) {
        window.open(club.value.profile.zalo_link, '_blank')
        isZaloLinkConfirmModalOpen.value = false
    }
}

const getInviteGroupData = async ({ loadMore = false } = {}) => {
    if (activeScope.value === 'club' && !selectedClub.value) {
        inviteGroupData.value = []
        return
    }

    if (isLoadingMoreInvite.value) return

    if (!loadMore) {
        invitePage.value = 1
        hasMoreInvite.value = true
    }

    if (!hasMoreInvite.value) return

    isLoadingMoreInvite.value = true

    const payload = {
        scope: activeScope.value,
        per_page: 20,
        page: invitePage.value,
        club_id: clubId.value,
        ...(activeScope.value === 'club' ? { source_club_id: selectedClub.value } : {}),
        ...(activeScope.value === 'area'
            ? {
                lat: userLatitude.value,
                lng: userLongitude.value,
                radius: currentRadius.value
            }
            : {}),
        ...(searchQuery.value ? { search: searchQuery.value } : {})
    }

    try {
        const resp = await ClubService.getClubCandidates(payload)
        const newData = resp?.data?.result || []

        if (loadMore) {
            if (inviteGroupData.value?.result) {
                inviteGroupData.value.result.push(...newData)
            }
        } else {
            inviteGroupData.value = resp?.data
        }

        if (newData.length < 20) {
            hasMoreInvite.value = false
        } else {
            invitePage.value++
        }
    } catch (e) {
        if (!loadMore) {
            inviteGroupData.value = []
        }
    } finally {
        isLoadingMoreInvite.value = false
    }
}

const onSearchChange = debounce(async (query) => {
    searchQuery.value = query
    await getInviteGroupData({ loadMore: false })
}, 300)

const onScopeChange = async (scope) => {
    activeScope.value = scope

    if (scope === 'area') {
        await initializeUserLocation()
    }

    await getInviteGroupData({ loadMore: false })
}

const onClubChange = async (clubId) => {
    selectedClub.value = clubId
    await getInviteGroupData({ loadMore: false })
}

const onRadiusChange = debounce(async (radius) => {
    currentRadius.value = radius
    await getInviteGroupData({ loadMore: false })
}, 300)

const loadMoreInviteUsers = async () => {
    await getInviteGroupData({ loadMore: true })
}


const initializeUserLocation = async () => {
    if (getUser.value?.latitude && getUser.value?.longitude) {
        userLatitude.value = getUser.value.latitude;
        userLongitude.value = getUser.value.longitude;
    } else {
        try {
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject);
            });
            userLatitude.value = position.coords.latitude;
            userLongitude.value = position.coords.longitude;
        } catch (error) {
            toast.error('Không thể lấy vị trí hiện tại. Vui lòng cho phép truy cập vị trí.');
            userLatitude.value = null;
            userLongitude.value = null;
        }
    }
};

const handleInviteAction = async (user) => {
    try {
        await ClubService.inviteMember(clubId.value, { user_id: user.id })
        toast.success(`Đã gửi lời mời đến ${user.name}`)
        // Update local state to show invited
        if (inviteGroupData.value?.result) {
            const index = inviteGroupData.value.result.findIndex(u => u.id === user.id)
            if (index !== -1) {
                inviteGroupData.value.result[index].invited = true
            }
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể gửi lời mời')
    }
}

const refreshActivityData = async () => {
    await getClubActivities()
    if (isActivityModalOpen.value) {
        await Promise.all([
            getMoreUpcomingActivities(),
            getMoreHistoryActivities()
        ])
    }
}

const handleRegisterActivity = async (activity) => {
    try {
        await ClubService.joinActivityRequest(clubId.value, activity.id)
        toast.success('Đã gửi yêu cầu tham gia thành công')
        await refreshActivityData()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể gửi yêu cầu tham gia')
    }
}

const handleCancelJoinActivity = async (activity) => {
    const userId = getUser.value.id
    const userParticipant = activity.participants_list?.find(p => p.user_id === userId)
    if (!userParticipant) return

    try {
        await ClubService.cancelActivityJoinRequest(clubId.value, activity.id, userParticipant.id)
        toast.success('Đã hủy yêu cầu tham gia')
        await refreshActivityData()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể hủy yêu cầu tham gia')
    }
}

const handleSelfCheckInActivity = async (arg1, arg2) => {
    // If arg1 is an object (activity), or arg2 is undefined, use clubId.value
    const finalClubId = (arg2 !== undefined && typeof arg1 !== 'object') ? arg1 : clubId.value
    const finalActivityId = (arg2 !== undefined) ? arg2 : (arg1?.id || arg1)

    if (!finalClubId || !finalActivityId) {
        toast.error('Thiếu thông tin câu lạc bộ hoặc hoạt động')
        return
    }

    try {
        await ClubService.selfCheckinActivity(finalClubId, finalActivityId)
        toast.success('Đã check-in thành công')
        await refreshActivityData()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể check-in')
    }
}

const handleSelfAbsentActivity = async (arg1, arg2) => {
    const finalClubId = (arg2 !== undefined && typeof arg1 !== 'object') ? arg1 : clubId.value
    const finalActivityId = (arg2 !== undefined) ? arg2 : (arg1?.id || arg1)

    if (!finalClubId || !finalActivityId) {
        toast.error('Thiếu thông tin câu lạc bộ hoặc hoạt động')
        return
    }

    try {
        await ClubService.selfAbsentActivity(finalClubId, finalActivityId)
        toast.success('Đã báo vắng thành công')
        await refreshActivityData()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể báo vắng')
    }
}

const handleReportClub = () => {
    isReportModalOpen.value = true
}

const submitClubReport = async (data) => {
    isReportingClub.value = true
    try {
        await ClubService.reportClub(clubId.value, data)
        toast.success('Báo cáo CLB thành công')
        isReportModalOpen.value = false
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể báo cáo CLB')
    } finally {
        isReportingClub.value = false
    }
}

onMounted(async () => {
    if (!clubId.value) {
        isInitialLoading.value = false;
        return;
    }
    await loadAllData()

    timeInterval = setInterval(() => {
        currentTime.value = dayjs()
    }, 1000)

    // Always load notification types in background so they are ready for edit modal
    getNotificationType()

    if (route.query.showNotifications) {
        openNotification()
    }

    document.addEventListener('click', handleClickOutside)
})

const handleClickOutside = (event) => {
    const scheduleBtn = document.querySelector('.schedule-dropdown-trigger')
    if (scheduleBtn && !scheduleBtn.contains(event.target)) {
        showScheduleDropdown.value = false
    }
}

onBeforeUnmount(() => {
    if (timeInterval) clearInterval(timeInterval)
    document.removeEventListener('click', handleClickOutside)
})

watch(() => route.query.showNotifications, (newVal) => {
    if (newVal) {
        openNotification()
    }
})
</script>
<style scoped>
.bg-club-default {
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-color: #000;
    min-height: 286px;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #E5E7EB;
    border-radius: 20px;
}

/* Modal animations */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

/* Backdrop animations */
.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.3s ease;
}

.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}

/* Modal content animations */
.modal-content-enter-active {
    animation: modal-scale-in 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal-content-leave-active {
    animation: modal-scale-out 0.2s ease-in;
}

@keyframes modal-scale-in {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }

    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes modal-scale-out {
    from {
        opacity: 1;
        transform: scale(1) translateY(0);
    }

    to {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
}

@keyframes bounce-subtle {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-2px);
    }
}

.animate-bounce-subtle {
    animation: bounce-subtle 1s infinite ease-in-out;
}
</style>
