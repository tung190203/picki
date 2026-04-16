<template>
  <div class="p-4 max-w-6xl mx-auto">
    <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
      <div class="space-y-6 lg:col-span-2">
        <div class="bg-white rounded-[8px] shadow p-5">
          <div class="flex items-center justify-between space-x-2 flex-wrap">
            <template v-for="(tab, index) in tabs" :key="tab.name">
              <button @click="activeTab = tab.name" class="text-sm font-semibold transition-all flex items-center"
                :class="[
                  activeTab === tab.name
                    ? 'bg-red-600 text-white rounded-full px-5 py-2 shadow-sm'
                    : 'text-gray-700 hover:text-red-600 px-5 py-2'
                ]">
                {{ tab.label }}
              </button>
              <ChevronRightIcon v-if="index < tabs.length - 1" class="w-4 h-4 text-gray-500 font-semibold mx-2" />
            </template>
          </div>
        </div>

        <div class="bg-white rounded-[8px] shadow min-h-[200px] transition-all duration-300"
          :class="[activeTab === 'discuss' ? 'p-0' : 'p-5']">
          <Transition name="fade" mode="out-in">
            <div v-if="activeTab === 'detail'" key="detail">
              <div class="flex flex-wrap items-center gap-2 mb-4">
                <h3 class="font-semibold text-gray-900 text-[20px]">
                  {{ tournament.name }}
                </h3>
                <component :is="tournament.is_private ? LockClosedIcon : LockOpenIcon" class="w-5 h-5" />
              </div>
              <div class="py-4">
                <div v-if="preview" class="relative">
                  <div class="relative rounded-xl overflow-hidden h-72">
                    <img :src="preview" alt="Preview" class="w-full h-full object-cover" />
                    <button @click="handleRemove" v-if="isCreator"
                      class="absolute top-2 right-2 bg-white rounded-full p-1.5 shadow-lg hover:bg-gray-100 transition-colors">
                      <XMarkIcon class="w-5 h-5 text-gray-700" />
                    </button>
                  </div>
                </div>

                <div v-else @click="handleClick" @dragover.prevent="handleDragOver" @dragleave.prevent="handleDragLeave"
                  @drop.prevent="handleDrop" :class="[
                    'relative border rounded-xl text-center cursor-pointer transition-all duration-200 bg-white/50 h-72 flex flex-col justify-center items-center',
                    isDragging ? 'border-rose-500 bg-white' : 'border-[#D72D36] hover:bg-white hover:border-red-500'
                  ]">

                  <div class="text-center mb-4">
                    <h2 class="font-semibold text-gray-800 mb-1">
                      Thêm ảnh bia giải đấu
                    </h2>
                    <p class="text-gray-600 text-xs">
                      Kích thước ảnh tải lên không quá 5MB
                    </p>
                  </div>
                  <div :class="[
                    'p-4 rounded-md border border-dashed transition-colors',
                    isDragging ? 'border-[#D72D36] bg-white' : 'border-[#D72D36] bg-white'
                  ]">
                    <ArrowUpTrayIcon class="w-8 h-8 text-[#D72D36]" />
                  </div>

                  <input ref="fileInput" type="file" accept="image/*" @change="handleFileInputChange" class="hidden" />
                </div>
              </div>

              <div class="space-y-2 mb-4">
                <div class="flex items-start justify-between gap-2">
                  <div class="flex gap-2">
                    <CalendarDaysIcon class="w-5 h-5 shrink-0" />
                    <div>
                      <p class="text-gray-900 font-medium">{{ formatEventDate(tournament.start_date) }}</p>
                      <p class="text-gray-500 text-sm">{{ tournament.duration }} phút</p>
                    </div>
                  </div>
                  <a href="#" class="text-blue-600 text-sm font-medium hover:underline">Thêm vào
                    lịch</a>
                </div>

                <div class="flex items-start justify-between gap-2">
                  <div class="flex gap-2">
                    <MapPinIcon class="w-5 h-5 shrink-0" />
                    <div>
                      <p class="text-gray-900 font-medium">
                        {{ tournament.competition_location?.name }}
                      </p>
                      <p class="text-gray-500 text-sm">{{ tournament.competition_location?.address }}</p>
                    </div>
                  </div>
                  <a href="#" class="text-blue-600 text-sm font-medium hover:underline whitespace-nowrap">Hiển thị
                    trên bản đồ</a>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                <div class="border rounded p-3 flex items-center gap-3 hover:shadow-md transition cursor-pointer">
                  <UsersIcon class="w-6 h-6 text-red-600" />
                  <div>
                    <p class="font-medium text-gray-800">{{ tournament.max_team }} Đội</p>
                    <p class="text-sm text-gray-500">{{ tournament.player_per_team }} thành viên mỗi đội</p>
                  </div>
                </div>

                <div class="border rounded p-3 flex items-center gap-3 hover:shadow-md transition cursor-pointer">
                  <template v-if="tournament.tournament_types && tournament.tournament_types.length">
                    <component :is="displayFormat.icon" class="w-6 h-6 text-[#D72D36]" />
                    <div v-for="type in tournament.tournament_types">
                      <p class="font-medium text-gray-800">{{ type.format_label }}</p>
                      <p class="text-sm text-gray-500">Tổng {{ type.total_matches ?? 0 }} trận</p>
                    </div>
                  </template>
                  <template v-else>
                    <AdjustmentsVerticalIcon class="w-6 h-6 text-red-600" />
                    <div>
                      <p class="font-medium text-gray-800">Chưa chọn thể thức thi đấu</p>
                    </div>
                  </template>
                </div>

                <div class="border rounded p-3 flex items-center gap-3 hover:shadow-md transition cursor-pointer">
                  <CircleStackIcon class="w-6 h-6 text-red-600" />
                  <div>
                    <p class="font-medium text-gray-800">
                      {{ tournament.min_rating != null && tournament.max_rating != null
                        ? `${tournament.min_rating} - ${tournament.max_rating}`
                        : 'Không giới hạn'
                      }}
                    </p>
                    <p class="text-sm text-gray-500">Trung bình điểm DUPR</p>
                  </div>
                </div>
                <div class="border rounded p-3 flex items-center gap-3 hover:shadow-md transition cursor-pointer">
                  <UserIcon class="w-6 h-6 text-red-600" />
                  <div>
                    <p class="font-medium text-gray-800">{{ tournament.gender_policy_text }}</p>
                    <p class="text-sm text-gray-500">{{ tournament.age_group_text }}</p>
                  </div>
                </div>
                <div class="border rounded p-3 flex items-center gap-3 hover:shadow-md transition cursor-pointer">
                  <CalendarDaysIcon class="w-6 h-6" />
                  <div>
                    <p class="font-medium text-gray-800">Mở đăng kí</p>
                    <p class="text-sm text-gray-500">{{ formatDateTime(tournament.registration_open_at) }}</p>
                  </div>
                </div>
                <div class="border rounded p-3 flex items-center gap-3 hover:shadow-md transition cursor-pointer">
                  <CalendarDaysIcon class="w-6 h-6" />
                  <div>
                    <p class="font-medium text-gray-800">Hạn chót đăng kí</p>
                    <p class="text-sm text-gray-500">{{ formatDateTime(tournament.registration_closed_at) }}</p>
                  </div>
                </div>
              </div>

              <div class="mb-4">
                <div v-if="!tournament.description && !isEditingDescription && isCreator">
                  <a href="javascript:void(0)" @click="setupDescription"
                    class="text-blue-600 text-sm font-medium hover:underline mt-2 inline-block">Thêm ghi
                    chú</a>
                </div>

                <div v-if="isEditingDescription || tournament.description">
                  <label class="block font-medium text-gray-700 mb-2">Ghi chú về giải đấu</label>
                  <textarea rows="4" placeholder="Ghi chú về giải đấu..." v-model="descriptionModel"
                    class="w-full border border-gray-300 rounded-md p-3 resize-none focus:outline-none focus:ring-1 focus:ring-red-500 placeholder:text-gray-400"
                    :class="isCreator ? '' : 'bg-gray-100 cursor-not-allowed'"
                    :readonly="tournament?.created_by?.id != getUser.id"></textarea>
                  <Transition name="fade">
                    <button v-if="isDescriptionChanged" @click="saveDescription" :disabled="!isDescriptionChanged"
                      :class="[
                        'mt-2 px-4 py-2 font-medium rounded-md transition-colors shadow-md',
                        isDescriptionChanged
                          ? 'bg-red-600 text-white hover:bg-red-700'
                          : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                      ]">
                      {{ tournament.description ? 'Lưu thay đổi' : 'Thêm ghi chú' }}
                    </button>
                  </Transition>
                </div>
              </div>
              <div class="flex flex-wrap gap-3" v-if="isCreator">
                <button @click="publicTournament"
                  class="flex items-center justify-center gap-2 bg-[#D72D36] hover:bg-white text-white hover:text-[#D72D36] border hover:border-[#D72D36] font-medium px-6 py-2 rounded-md transition">
                  {{ tournament?.status == 1 ? 'Công bố giải' : 'Huỷ công bố' }}
                </button>
                <button @click="goToEditPage"
                  class="flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-900 font-medium px-4 py-2 rounded-md transition">
                  Chỉnh sửa
                  <PencilIcon class="w-4 h-4" />
                </button>

                <button @click="confirmRemoval"
                  class="flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-900 font-medium px-4 py-2 rounded-md transition">
                  Hủy bỏ
                  <XCircleIcon class="w-5 h-5" />
                </button>
              </div>
              <template v-else>
                <div v-if="!tournament.is_joined">
                  <button
                    class="flex items-center justify-center gap-2 bg-[#D72D36] hover:bg-white text-white hover:text-[#D72D36] border hover:border-[#D72D36] font-medium px-6 py-2 rounded-md transition"
                    @click="joinerTournament">
                    Tham gia giải đấu
                  </button>
                </div>
                <div
                  v-if="tournament.is_joined && !tournament.is_confirmed_by_organizer && tournament.is_invite_by_organizer">
                  <button
                    class="flex items-center justify-center gap-2 bg-[#D72D36] hover:bg-white text-white hover:text-[#D72D36] border hover:border-[#D72D36] font-medium px-6 py-2 rounded-md transition"
                    @click="confirmTournament">
                    Xác nhận lời mời
                  </button>
                </div>
              </template>

              <!-- Landing Page Button -->
              <div class="mt-3">
                <a
                  :href="`/tournament-landing/${tournament.id}`"
                  target="_blank"
                  class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium transition">
                  <ExternalLinkIcon class="w-4 h-4" />
                  Xem Landing Page
                </a>
              </div>
            </div>

            <div v-else-if="activeTab === 'list'" key="list">
              <div class="flex items-center justify-between border-b border-[#BBBFCC] px-3 py-4 mb-4" v-if="isCreator">
                <p class="font-semibold uppercase">Duyệt yêu cầu tham gia tự động</p>
                <button @click="toggleAutoApprove"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                  :class="autoApprove ? 'bg-[#D72D36]' : 'bg-gray-300'">
                  <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="autoApprove ? 'translate-x-6' : 'translate-x-1'" />
                </button>
              </div>
              <div class="flex justify-start gap-2 mb-4">
                <button v-for="tab in listTabs" :key="tab.id" @click="listActiveTab = tab.id" :class="[
                  'px-3 py-1.5 rounded-full text-sm font-medium transition-colors',
                  listActiveTab === tab.id
                    ? 'bg-red-500 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                ]">
                  {{ tab.label }}
                </button>
              </div>
              <template v-if="listActiveTab === 'staffs'">
                <!-- Organizers Section -->
                <div class="border border-[#BBBFCC] rounded-lg my-4 p-4">
                  <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-[#6B6F80] uppercase text-sm">
                      NGƯỜI TỔ CHỨC • {{ organizersList.length }}
                    </h4>
                    <button
                      v-if="isCreator"
                      @click="openInviteModalStaff('organizer')"
                      class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                    >
                      + Mời vào ban tổ chức
                    </button>
                  </div>
                  <div v-if="organizersList.length">
                    <div class="grid grid-cols-2 sm:grid-cols-6 lg:grid-cols-6 gap-4">
                      <UserCard v-for="(item, index) in organizersList" :key="'org-' + index" :id="item.id"
                        :userId="item.staff.id" :name="item.staff.name" :avatar="item.staff.avatar"
                        :rating="getUserScore(item.staff)" status="approved" @removeUser="handleRemoveStaff" />
                    </div>
                  </div>
                </div>

                <!-- Referees Section -->
                <div class="border border-[#BBBFCC] rounded-lg my-4 p-4">
                  <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-[#6B6F80] uppercase text-sm">
                      TRỌNG TÀI • {{ refereesList.length }}
                    </h4>
                    <button
                      v-if="isCreator"
                      @click="openInviteModalStaff('referee')"
                      class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                    >
                      + Mời Trọng tài
                    </button>
                  </div>
                  <div v-if="refereesList.length">
                    <div class="grid grid-cols-2 sm:grid-cols-6 lg:grid-cols-6 gap-4">
                      <UserCard v-for="(item, index) in refereesList" :key="'ref-' + index" :id="item.id"
                        :userId="item.staff.id" :name="item.staff.name" :avatar="item.staff.avatar"
                        :rating="getUserScore(item.staff)" status="approved" @removeUser="handleRemoveStaff" />
                    </div>
                  </div>
                  <div v-else class="text-center text-gray-400 py-4">
                    Chưa có trọng tài nào
                  </div>
                </div>
              </template>
              <template v-else-if="listActiveTab === 'paticipants'">
                <div class="space-y-4 my-4">
                  <!-- Header with stats and actions -->
                  <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                      <span class="font-semibold text-gray-900">
                        {{ allParticipants.length }} / {{ tournament.max_team * tournament.player_per_team }} người
                      </span>
                      <div class="flex items-center gap-2 text-xs">
                        <span class="flex items-center gap-1">
                          <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                          Chờ {{ waitingConfirmationParticipants.length }}
                        </span>
                        <span class="flex items-center gap-1">
                          <span class="w-2 h-2 rounded-full bg-green-500"></span>
                          Xác nhận {{ confirmedParticipants.length }}
                        </span>
                        <span class="flex items-center gap-1">
                          <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                          Checkin {{ checkedInParticipants.length }}
                        </span>
                        <span class="flex items-center gap-1">
                          <span class="w-2 h-2 rounded-full bg-red-500"></span>
                          Vắng {{ absentParticipants.length }}
                        </span>
                      </div>
                    </div>
                    <div class="flex items-center gap-3">
                      <span class="text-[#207AD5] text-xs font-semibold cursor-pointer" v-if="isCreator"
                        @click="openInviteModalWithFriends">Mời bạn bè</span>
                      <span v-if="isCreator && allParticipants.length < (tournament.max_team * tournament.player_per_team)" class="text-gray-300">|</span>
                      <button
                        v-if="isCreator && allParticipants.length < (tournament.max_team * tournament.player_per_team)"
                        @click="showAddGuestModal = true"
                        class="flex items-center gap-1 text-[#D72D36] text-xs font-semibold hover:underline">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        Thêm Guest
                      </button>
                    </div>
                  </div>

                  <!-- Section: Chờ xác nhận -->
                  <div v-if="waitingConfirmationParticipants.length > 0" class="border border-[#BBBFCC] rounded-lg p-4">
                    <h4 class="font-semibold text-yellow-600 uppercase text-sm mb-3 flex items-center gap-2">
                      <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                      Chờ xác nhận • {{ waitingConfirmationParticipants.length }}
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                      <UserCard v-for="(item, index) in waitingConfirmationParticipants" :key="'waiting-' + index" :id="item.id"
                        :name="getParticipantDisplayName(item)"
                        :avatar="getParticipantAvatar(item)"
                        :rating="getUserScore(item)"
                        status="pending"
                        :showActions="true"
                        :is_invite_by_organizer="item.is_invite_by_organizer"
                        @removeUser="handleRemoveUser"
                        @click="openMemberActionModal($event)"
                        @confirm="handleConfirmUser(item)"
                        @reject="handleRejectUser(item)" />
                      <UserCardPending v-for="(item, index) in guestPendingParticipants" :key="'guest-pending-' + index" :id="item.id"
                        :name="getParticipantDisplayName(item)"
                        :avatar="getParticipantAvatar(item)"
                        :showActions="true"
                        @confirm="handleConfirmUser(item)"
                        @reject="handleRejectUser(item)" />
                      <UserCard v-if="isCreator"
                        :empty="true" @clickEmpty="openInviteModalWithFriends" />
                    </div>
                  </div>

                  <!-- Section: Chưa checkin -->
                  <div class="border border-[#BBBFCC] rounded-lg p-4">
                    <h4 class="font-semibold text-green-600 uppercase text-sm mb-3 flex items-center gap-2">
                      <span class="w-2 h-2 rounded-full bg-green-500"></span>
                      Chưa checkin • {{ confirmedParticipants.length }}
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                      <UserCard v-for="(item, index) in confirmedParticipants" :key="'confirmed-' + index" :id="item.id"
                        :name="getParticipantDisplayName(item)"
                        :avatar="getParticipantAvatar(item)"
                        :rating="getUserScore(item)"
                        status="approved" @removeUser="handleRemoveUser"
                        @click="openMemberActionModal($event)" />
                      <UserCard v-if="isCreator"
                        :empty="true" @clickEmpty="openInviteModalWithFriends" />
                    </div>
                  </div>

                  <!-- Section: Đã checkin -->
                  <div v-if="checkedInParticipants.length > 0" class="border border-[#BBBFCC] rounded-lg p-4">
                    <h4 class="font-semibold text-blue-600 uppercase text-sm mb-3 flex items-center gap-2">
                      <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                      Đã checkin • {{ checkedInParticipants.length }}
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                      <UserCard v-for="(item, index) in checkedInParticipants" :key="'checkedin-' + index" :id="item.id"
                        :name="getParticipantDisplayName(item)"
                        :avatar="getParticipantAvatar(item)"
                        :rating="getUserScore(item)"
                        status="approved" @removeUser="handleRemoveUser"
                        @click="openMemberActionModal($event)" />
                    </div>
                  </div>

                  <!-- Section: Đã báo vắng -->
                  <div v-if="absentParticipants.length > 0" class="border border-[#BBBFCC] rounded-lg p-4">
                    <h4 class="font-semibold text-red-600 uppercase text-sm mb-3 flex items-center gap-2">
                      <span class="w-2 h-2 rounded-full bg-red-500"></span>
                      Đã báo vắng • {{ absentParticipants.length }}
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                      <UserCard v-for="(item, index) in absentParticipants" :key="'absent-' + index" :id="item.id"
                        :name="getParticipantDisplayName(item)"
                        :avatar="getParticipantAvatar(item)"
                        :rating="getUserScore(item)"
                        status="absent" @removeUser="handleRemoveUser"
                        @click="openMemberActionModal($event)" />
                    </div>
                  </div>

                  <!-- Empty state -->
                  <div v-if="!allParticipants.length" class="border border-[#BBBFCC] rounded-lg p-4">
                    <div class="flex flex-col justify-center items-center gap-6 p-7">
                      <div class="flex items-center justify-center my-4 rounded-full bg-[#FFF5F5] w-20 h-20 mx-auto">
                        <UserMultiple class="w-10 h-10 text-[#D72D36]" />
                      </div>
                      <p class="text-gray-700">
                        Chưa có người tham gia nào trong giải đấu này.
                      </p>
                    </div>
                  </div>

                </div>
              </template>
              <template v-else-if="listActiveTab === 'split'">
                <div class="flex items-center justify-between mb-4 uppercase">
                  <p class="text-sm font-semibold">Xác nhận tham gia • {{ listTeams.length ?? 0 }}</p>
                  <p class="text-sm font-semibold">Chờ xác nhận • 0</p>
                </div>
                <template v-if="listTeams && listTeams.length">
                  <div class="border border-[#BBBFCC] rounded flex items-center justify-between p-4 mb-4"
                    v-for="team in listTeams" :key="team.id">
                      <div class="flex items-center gap-3 min-w-0 flex-1 px-2">
                      <div class="relative w-[4.875rem] h-[4.875rem] flex-shrink-0" @click="openEditTeamModal(team)">
                        <div class="w-full h-full rounded-lg overflow-hidden" v-if="team.avatar">
                          <img :src="team.avatar" alt="User" class="w-full h-full object-cover" />
                        </div>
                        <div v-else class="w-full h-full flex items-center justify-center rounded-lg"
                          :class="getTeamBgClass(team.id)">
                          <UserMultiple class="w-8 h-8 text-white" />
                        </div>
                        <button
                          class="absolute bottom-0 right-0 translate-x-1/4 translate-y-1/4 bg-blue-500 hover:bg-blue-600 text-white rounded-full p-1 shadow-md transition">
                          <PencilIcon class="w-4 h-4" />
                        </button>
                      </div>
                      <p class="font-medium text-gray-900 break-words whitespace-normal leading-snug min-w-0">{{ team.name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 flex-shrink-0">
                      <UserCard v-for="(member, index) in team.members" :key="index" :name="member.full_name"
                        :id="member.id" :avatar="member.avatar" :rating="getUserScore(member)"
                        :status="member.is_confirmed == true ? 'approved' : 'pending'"
                        @removeUser="handleRemoveMember($event, team.id)" />

                      <UserCard v-for="n in getRemainingSlots(team.members)" :key="`empty-${team.id}-${n}`"
                        :empty="true" @clickEmpty="openInviteUserToTeamModal(team)" />
                    </div>
                  </div>
                </template>
                <template v-else>
                  <div
                    class="flex flex-col items-center justify-center h-64 text-center text-gray-500 border-gray-300 rounded-lg p-6 mt-8">
                    <p class="text-lg font-medium">Chưa có đội nào được tạo.</p>
                    <p class="text-sm">Hãy tạo đội đầu tiên để bắt đầu sắp xếp giải đấu.</p>
                  </div>
                </template>
                <div class="flex items-center justify-start mb-2 mt-28 gap-4" v-if="isCreator">
                  <button @click="openCreateTeamModal"
                    class="flex items-center justify-center gap-2 bg-[#D72D36] hover:bg-red-500 text-white font-medium px-4 py-2 rounded-md transition">
                    (+) Thêm đội</button>
                  <button @click="autoAssign"
                    class="flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-900 font-medium px-4 py-2 rounded-md transition">
                    Tự động chia đội
                  </button>
                </div>
              </template>
              <template v-else-if="listActiveTab === 'invite'">
                <div v-if="!listHasInvite.length">
                  <div class="flex items-center justify-center my-8 rounded-full bg-[#FFF5F5] w-20 h-20 mx-auto">
                    <EnvelopeIcon class="w-10 h-10 text-[#D72D36]" />
                  </div>
                  <div class="flex flex-col justify-center items-center gap-6 p-7">
                    <p class="text-gray-700">
                      Mời bạn bè tham gia giải đấu
                    </p>
                    <button @click="openInviteModalWithFriends"
                      class="flex items-center justify-center gap-2 bg-[#D72D36] hover:bg-red-500 text-white font-medium px-4 py-2 rounded-md transition">
                      Mời bạn
                    </button>
                  </div>
                </div>
                <div v-else>
                  <div class="grid grid-cols-1 gap-4">
                    <div v-for="item in listHasInvite" :key="item.id"
                      class="border p-4 flex justify-between items-center gap-4 rounded cursor-pointer hover:shadow-md transition">
                      <div class="flex justify-start items-center gap-4">
                        <UserCard :avatar="item.avatar" :status="item.is_confirmed == 1 ? 'approved' : 'pending'"
                          :show-hover-delete="false" />
                        <div>
                          <p>{{ item.name }}</p>
                          <p class="flex items-center gap-1 text-sm text-gray-500">
                            <component :is="item.gender == 1 ? MaleIcon : FemaleIcon" class="w-4 h-4" />
                            {{ item.gender_text }}
                            <span :class="[
                              'px-2 py-0.5 rounded text-xs font-medium',
                              item.visibility === 'open'
                                ? 'bg-blue-100 text-blue-700'
                                : 'bg-green-100 text-green-700'
                            ]">
                              {{ item.visibility === 'open' ? 'Open' : 'Friend-Only' }}
                            </span>
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
            </div>

            <div v-else-if="activeTab === 'type'" key="type" class="min-h-[70vh]">
              <template v-if="!tournament.tournament_types || !tournament.tournament_types.length">

                <template v-if="!showFormatType">
                  <div class="flex flex-col justify-center items-center gap-6 p-7">
                    <div class="flex items-center justify-center my-4 rounded-full bg-[#FFF5F5] w-20 h-20 mx-auto">
                      <AdjustmentsVerticalIcon class="w-10 h-10 text-[#D72D36]" />
                    </div>
                    <p class="text-gray-700">
                      Chưa có thể thức thi đấu nào được thiết lập cho giải đấu này.
                    </p>

                    <button @click="startSetup" v-if="isCreator"
                      class="px-6 py-2 bg-[#D72D36] text-white font-medium rounded-lg hover:bg-red-700 transition-colors shadow-md">
                      Bắt đầu cài đặt thể thức
                    </button>
                  </div>
                </template>

                <template v-else>
                  <FormatType :data="tournament" @update:config="handleConfigUpdate" @submit="handleFormSubmit"
                    @back="showFormatType = false" />
                </template>

              </template>
              <template v-else>
                <div class="flex items-center justify-between border-b border-[#BBBFCC] px-3 py-4 mb-4"
                  v-if="isCreator">
                  <p class="font-semibold uppercase">Công khai bảng đấu</p>
                  <button @click="togglePublicBranch"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                    :class="publicBracket ? 'bg-[#D72D36]' : 'bg-gray-300'">
                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                      :class="publicBracket ? 'translate-x-6' : 'translate-x-1'" />
                  </button>
                </div>
                <div v-if="displayFormat"
                  class="border border-[#BBBFCC] flex justify-start items-start p-4 rounded gap-2">
                  <component :is="displayFormat.icon" class="w-6 h-6 text-[#D72D36]" />
                  <div>
                    <h3 class="font-semibold text-gray-900 mb-1">
                      {{ displayFormat.title }}
                    </h3>
                    <p class="text-gray-700 mb-1 text-xs">
                      {{ displayFormat.description }}
                    </p>
                    <p class="text-xs underline text-[#4392E0] cursor-pointer" @click="confirmChangeType"
                      v-if="isCreator">Thay đổi thể thức</p>
                  </div>
                </div>
                <div v-if="tournament?.tournament_types?.[0]?.format === FORMAT_MIXED"
                  class="border border-[#BBBFCC] rounded my-4 px-4 py-3 flex justify-between items-center cursor-pointer hover:shadow-md transition"
                  @click="openGroupsSortPage">
                  <div class="flex items-center gap-3">
                    <TableChartIcon class="w-5 h-5" />
                    <p>Sắp xếp bảng thi đấu</p>
                  </div>
                  <ChevronRightIcon class="w-5 h-5 text-gray-400" />
                </div>
                <div v-if="publicBracket == true || isCreator"
                  class="border border-[#BBBFCC] rounded my-4 px-4 py-3 flex justify-between items-center cursor-pointer hover:shadow-md transition"
                  @click="openBracketPage">
                  <div class="flex items-center gap-3">
                    <ScheduleIcon class="w-5 h-5" />
                    <p>Sơ đồ thi đấu</p>
                  </div>
                  <ChevronRightIcon class="w-5 h-5 text-gray-400" />
                </div>
                <template v-for="type in tournament?.tournament_types" :key="type.id">
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 border-b pb-4"
                    :class="publicBracket == true ? 'mt-0' : 'mt-4'">
                    <div v-if="type.format == 1"
                      class="w-full h-[140px] bg-[#FFF5F5] hover:shadow-md transition rounded-md p-4 flex flex-col space-y-2 items-center justify-center">
                      <p class="font-semibold">{{ type.format_specific_config[0]?.pool_stage?.number_competing_teams }}
                      </p>
                      <p class="text-xs">Bảng đấu</p>
                    </div>
                    <div v-if="type.format == 1"
                      class="w-full h-[140px] bg-[#FFF5F5] hover:shadow-md transition rounded-md p-4 flex flex-col space-y-2 items-center justify-center">
                      <p class="font-semibold">{{ type.format_specific_config[0]?.pool_stage?.num_advancing_teams }}</p>
                      <p class="text-xs">Đội vào vòng loại mỗi bảng</p>
                    </div>
                    <div
                      class="w-full h-[140px] bg-[#FFF5F5] hover:shadow-md transition rounded-md p-4 flex flex-col space-y-2 items-center justify-center">
                      <p class="font-semibold">{{ formatMatchCount(type.total_matches_per_team) }}</p>
                      <p class="text-xs">Số trận đấu mỗi đội</p>
                    </div>
                    <div
                      class="w-full h-[140px] bg-[#FFF5F5] hover:shadow-md transition rounded-md p-4 flex flex-col space-y-2 items-center justify-center">
                      <p class="font-semibold">{{ JSON.parse(type.format_specific_config[0]?.has_third_place_match ||
                        'false') ? 1 : 0 }}</p>
                      <p class="text-xs">Trận tranh hạng ba</p>
                    </div>
                    <div
                      class="w-full h-[140px] bg-[#FFF5F5] hover:shadow-md transition rounded-md p-4 flex flex-col space-y-2 items-center justify-center">
                      <p class="font-semibold" v-for="rank in type.format_specific_config[0]?.ranking" :key="rank">
                        {{ getRankingLabel(rank) }}
                      </p>
                      <p class="text-xs">Cách tính xếp hạng</p>
                    </div>
                    <div
                      class="w-full h-[140px] bg-[#FFF5F5] hover:shadow-md transition rounded-md p-4 flex flex-col items-center justify-center">
                      <p class="font-semibold">H2H thắng</p>
                      <p class="font-semibold">Hiệu số</p>
                      <p class="font-semibold">H2H hiệu số</p>
                      <p class="text-xs">Nhánh thua thi đấu</p>
                    </div>
                  </div>
                </template>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-4 my-4 pb-4">
                  <div>
                    <p class="text-sm font-semibold uppercase">Giá trị điểm</p>
                    <ul class="mt-4 space-y-3">
                      <li class="flex justify-between items-center">
                        <p>Thắng</p>
                        <span class="text-[#4392E0]">3</span>
                      </li>
                      <li class="flex justify-between items-center">
                        <p>Thua</p>
                        <span class="text-[#4392E0]">0</span>
                      </li>
                      <li class="flex justify-between items-center">
                        <p>Hoà</p>
                        <span class="text-[#4392E0]">1</span>
                      </li>
                    </ul>
                  </div>
                  <div>
                    <p class="text-sm font-semibold uppercase">Thông số</p>
                    <ul class="mt-4 space-y-3">
                      <li class="flex justify-between items-center">
                        <p>Tổng số trận đấu</p>
                        <span class="text-[#4392E0]">{{ totalMatches }}</span>
                      </li>
                      <li class="flex justify-between items-center">
                        <p>Thời lượng giải đấu</p>
                        <span class="text-[#4392E0]">{{ totalTime }} phút ~ {{ totalTimeInHours }} giờ</span>
                      </li>
                    </ul>
                  </div>
                </div>
                <!-- <p class="text-[#D72D36] text-sm cursor-pointer hover:underline"
                  @click="showReGenerateBracketModal = true" v-if="isCreator">Chia lại cặp đấu</p> -->
              </template>
            </div>

            <div v-else-if="activeTab === 'schedule'" key="schedule" class="flex flex-col min-h-[70vh]">
              <ScheduleTab :isCreator="isCreator" :toggle="isHandleOwnScore" @handle-toggle="handleUpdateOwnScore"
                :rank="ranks" :data="tournament" :activeTab="activeTab" />
            </div>
            <div v-else-if="activeTab === 'discuss'" key="discuss" class="flex flex-col h-[70vh]">
              <ChatForm :tournamentId="tournament.id" />
            </div>
          </Transition>
        </div>
      </div>
      <QRcodeModal v-if="showQRCodeModal" :value="tournamentLink" @close="showQRCodeModal = false" />

      <ShareAction :buttons="[
        { label: 'Gửi link', icon: LinkIcon, onClick: copyLink },
        { label: 'Quét mã QR', icon: QrCodeIcon, onClick: showQRCode },
        isCreator ? { label: 'Mời nhóm', icon: UserMultiple, onClick: () => openInviteModalDefault() } : null,
        { label: 'Yêu cầu xác nhận KQ', icon: ClipboardDocumentCheckIcon }
      ].filter(Boolean)" :subtitle="'Hãy chia sẻ thông tin tới bạn bè để cùng tham gia giải đấu'" />
    </div>

    <InviteGroup
  v-model="showInviteModal"
  :data="inviteGroupData"
  :clubs="clubs"
  :active-scope="activeScope"
  :search-query="searchQuery"
  :current-radius="currentRadius"
  :current-club-id="selectedClub"
  :is-loading-more="isLoadingMoreInvite"
  :has-more="hasMoreInvite"
  :invite-type="inviteType"
  :selected-staff-role="selectedStaffRole"
  @update:searchQuery="onSearchChange"
  @change-scope="onScopeChange"
  @change-club="onClubChange"
  @update:radius="onRadiusChange"
  @invite="handleInviteAction"
  @load-more="loadMoreInviteUsers"
/>

    <DeleteConfirmationModal v-model="showDeleteModal" title="Xác nhận hủy bỏ giải đấu"
      message="Thao tác này không thể hoàn tác." confirmButtonText="Xác nhận" @confirm="removeTournament" />
    <DeleteConfirmationModal v-model="showDeleteTournamentTypeModal" title="Xác nhận thay đổi thể thức"
      message="Thao tác này sẽ xoá toàn bộ các trận đấu và các cài đặt thể thức trước đó" confirmButtonText="Xác nhận"
      @confirm="removeTournamentType" />
    <DeleteConfirmationModal v-model="showReGenerateBracketModal" title="Xác nhận chia lại cặp đấu"
      message="Thao tác này sẽ xoá toàn bộ các trận đấu và các kết quả" confirmButtonText="Xác nhận"
      @confirm="reGenerateMatches" />
    <EditTeamModal v-model="isOpenUpdateTeamModal" :data="selectedTeamDetail || {}" :isSaving="isSavingTeam"
      @update-info="handleUpdateInfo" @delete="handleDeleteTeam" />
    <CreateTeamModal v-model="isOpenCreateTeamModal" :isCreating="isLoading" @create-team="handleCreateInfo" />
    <AddMemberModal v-model="showInviteUserToTeamModal" :data="nonTeamParticipants" @add="handleAddUserToTeam"
      title="Thêm Thành Viên Vào Đội" emptyText="Không có vận động viên nào chưa có đội"
      :isLoading="isFetchingNonTeamUsers" />
    <PlayerActionModal v-model="showActionModal" :user="selectedUser" @view-profile="viewProfile"
      @confirm="confirmUser" />
    <MemberActionModal
      v-model="showMemberActionModal"
      :member="selectedMember"
      :current-user="getUser"
      tournament-type="tournament"
      :is-current-user-organizer="isScorer"
      :is-current-user-participant="isCurrentUserParticipant"
      @view-profile="handleMemberViewProfile"
      @check-in="handleMemberCheckIn"
      @absent="handleMemberAbsent"
      @self-check-in="handleMemberSelfCheckIn"
      @self-absent="handleMemberSelfAbsent"
    />
    <AddTournamentGuestModal
      v-model:isOpen="showAddGuestModal"
      :tournament="tournament"
      @success="detailTournament(id)"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { AdjustmentsVerticalIcon, ArrowUpTrayIcon, ChevronRightIcon, EnvelopeIcon, LinkIcon, LockClosedIcon, LockOpenIcon, QrCodeIcon, XMarkIcon } from '@heroicons/vue/24/solid'
import {
  CalendarDaysIcon,
  MapPinIcon,
  CircleStackIcon,
  UserIcon,
  PencilIcon,
  XCircleIcon,
  UserGroupIcon as UserMultiple,
  UsersIcon,
  ClipboardDocumentCheckIcon,
  CheckIcon,
  ArrowTopRightOnSquareIcon as ExternalLinkIcon
} from '@heroicons/vue/24/outline'
import UserCard from '@/components/molecules/UserCard.vue'
import UserCardPending from '@/components/molecules/UserCardPending.vue'
import QRcodeModal from '@/components/molecules/QRcodeModal.vue'
import InviteGroup from '@/components/molecules/InviteGroup.vue'
import * as TournamentService from '@/service/tournament.js'
import * as TournamentTypeService from '@/service/tournamentType.js'
import * as TeamService from '@/service/team.js'
import * as ParticipantService from '@/service/participant.js'
import {
  markParticipantCheckIn,
  markParticipantAbsent,
  selfCheckInTournament,
  selfMarkAbsentTournament,
} from '@/service/participant.js'
import * as TournamentStaffService from '@/service/tournamentStaff.js'
import * as ClubService from '@/service/club.js'
import { useRoute, useRouter } from 'vue-router'
import ShareAction from '@/components/molecules/ShareAction.vue'
import { toast } from 'vue3-toastify'
import { useFormatDate } from '@/composables/formatDatetime.js'
import { formatEventDate } from '@/composables/formatDatetime.js'
import FormatType from '@/components/organisms/FormatType.vue'
import mixedIcon from '@/assets/images/mixed.svg';
import directIcon from '@/assets/images/direct.svg';
import roundRobinIcon from '@/assets/images/round-robin.svg';
import { TABS, LIST_TABS, BACKGROUND_COLORS, FORMAT_DETAILS } from '@/data/tournament/index.js'
import DeleteConfirmationModal from '@/components/molecules/DeleteConfirmationModal.vue'
import EditTeamModal from '@/components/molecules/EditTeamModal.vue'
import CreateTeamModal from '@/components/molecules/CreateTeamModal.vue'
import debounce from 'lodash.debounce'
import MaleIcon from '@/assets/images/male.svg';
import FemaleIcon from '@/assets/images/female.svg';
import guestDefaultAvatar from '@/assets/images/guest-default-avatar.svg';
import { useUserStore } from '@/store/auth'
import { storeToRefs } from 'pinia'
import AddMemberModal from '@/components/molecules/AddMemberModal.vue'
import ScheduleTab from '@/components/molecules/ScheduleTab.vue'
import ChatForm from '@/components/organisms/ChatForm.vue'
import PlayerActionModal from '@/components/molecules/PlayerActionModal.vue'
import AddTournamentGuestModal from '@/components/pages/tournament/partials/AddTournamentGuestModal.vue'
import MemberActionModal from '@/components/molecules/MemberActionModal.vue'
import TableChartIcon from '@/assets/images/table_chart.svg';
import ScheduleIcon from '@/assets/images/branch.svg';

const inviteType = ref('participant')
const selectedStaffRole = ref('organizer')
const userStore = useUserStore()
const { getUser, getRole } = storeToRefs(userStore)
const { formatDateTime } = useFormatDate()
const route = useRoute()
const router = useRouter()
const preview = ref(null);
const isDragging = ref(false);
const fileInput = ref(null);
const fileToUpload = ref(null);
const currentConfig = ref({});
const showFormatType = ref(false);
const tabs = computed(() => {
  const baseTabs = [
    { name: 'detail', label: 'Chi tiết' },
    { name: 'list', label: 'Danh sách' },
    { name: 'type', label: 'Thể thức' },
    { name: 'schedule', label: 'Lịch thi đấu' },
    { name: 'discuss', label: 'Thảo luận' }
  ]
  if (getRole.value === 'referee') {
    return baseTabs.filter(tab => ['detail', 'schedule'].includes(tab.name))
  }
  return baseTabs
})
const listTabs = LIST_TABS
const id = route.params.id
const tournament = ref([])
const activeTab = ref('detail')
const listActiveTab = ref('paticipants')
const autoApprove = ref(false)
const publicBracket = ref(false)
const showInviteModal = ref(false)
const showDeleteModal = ref(false);
const showDeleteTournamentTypeModal = ref(false);
const showReGenerateBracketModal = ref(false);
const isEditingDescription = ref(false);
const descriptionModel = ref('');
const inviteGroupData = ref([]);
const listTeams = ref([])
const isOpenUpdateTeamModal = ref(false)
const isOpenCreateTeamModal = ref(false)
const selectedTeamDetail = ref(null)
const isSavingTeam = ref(false);
const isLoading = ref(false);
const listHasInvite = ref([])
const showInviteUserToTeamModal = ref(false);
const selectedTeam = ref(null);
const nonTeamParticipants = ref([]);
const isFetchingNonTeamUsers = ref(false);
const isHandleOwnScore = ref(false);
const ranks = ref([])
const clubs = ref([])
const activeScope = ref('all');
const selectedClub = ref(null);
const searchQuery = ref('')
const showQRCodeModal = ref(false);
const currentRadius = ref(10);
const userLatitude = ref(null);
const userLongitude = ref(null);
const invitePage = ref(1)
const isLoadingMoreInvite = ref(false)
const hasMoreInvite = ref(true)


const isDescriptionChanged = computed(() => {
  return descriptionModel.value !== tournament.value.description;
});
// const isCreator = computed(() => tournament.value?.created_by?.id === getUser.value.id)
const isCreator = computed(() => {
  return tournament.value?.tournament_staff?.some(
    staff => staff.role === 1 && staff.staff?.id === getUser.value.id
  )
})
const isScorer = computed(() => {
  return tournament.value?.tournament_staff?.some(
    staff => [1, 2, 3].includes(staff.role) && staff.staff?.id === getUser.value.id
  )
})

const isCurrentUserParticipant = computed(() => {
  return tournament.value?.tournament_participants?.some(
    p => p.user?.id === getUser.value?.id
  )
})
const organizersList = computed(() => {
  return tournament.value?.tournament_staff?.filter(staff => staff.role === 1) || []
})
const refereesList = computed(() => {
  return tournament.value?.tournament_staff?.filter(staff => staff.role === 3) || []
})

// Computed properties for VDV tab - filter participants by status
const allParticipants = computed(() => tournament.value?.tournament_participants || [])

const waitingConfirmationParticipants = computed(() => {
  return allParticipants.value.filter(p => !p.is_confirmed && !p.is_guest)
})

const guestPendingParticipants = computed(() => {
  return allParticipants.value.filter(p => !p.is_confirmed && p.is_guest)
})

const confirmedParticipants = computed(() => {
  return allParticipants.value.filter(p => p.is_confirmed && !p.checked_in_at && !p.is_absent)
})

const checkedInParticipants = computed(() => {
  return allParticipants.value.filter(p => p.is_confirmed && p.checked_in_at && !p.is_absent)
})

const absentParticipants = computed(() => {
  return allParticipants.value.filter(p => p.is_absent)
})

const setupDescription = () => {
  descriptionModel.value = tournament.value.description || '';
  isEditingDescription.value = true;
};
const showActionModal = ref(false)
const selectedUser = ref(null)
const showAddGuestModal = ref(false)
const showMemberActionModal = ref(false)
const selectedMember = ref(null)

function openActionModal(user) {
  selectedUser.value = user
  showActionModal.value = true
}

function viewProfile() {
  router.push(`/profile/${selectedUser.value.user.id}`)
}

function openMemberActionModal(param) {
  // param có thể là props object (từ UserCard click event) hoặc participant item (từ @click.stop)
  if (param && param.id !== undefined && !param.user && !param.checked_in_at) {
    // Đây là UserCard props — construct participant object từ props
    selectedMember.value = {
      id: param.userId || param.id,
      participant_id: param.id,
      name: param.name,
      avatar: param.avatar,
      rating: param.rating,
      user: { id: param.userId || param.id, full_name: param.name, avatar_url: param.avatar }
    }
  } else {
    // Đây là participant item — đảm bảo có participant_id
    selectedMember.value = { ...param, participant_id: param.id }
  }
  showMemberActionModal.value = true
}

function handleMemberViewProfile(member) {
  router.push(`/profile/${member.user?.id}`)
}

async function handleMemberCheckIn(member) {
  try {
    await markParticipantCheckIn(id, member.id)
    toast.success('Đã đánh dấu check-in thành công!')
    await detailTournament(id)
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi đánh dấu check-in.')
  }
}

async function handleMemberAbsent(member) {
  try {
    await markParticipantAbsent(id, member.id)
    toast.success('Đã đánh dấu vắng mặt thành công!')
    await detailTournament(id)
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi đánh dấu vắng mặt.')
  }
}

async function handleMemberSelfCheckIn(member) {
  try {
    await selfCheckInTournament(id)
    toast.success('Check-in thành công!')
    await detailTournament(id)
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi check-in.')
  }
}

async function handleMemberSelfAbsent(member) {
  try {
    await selfMarkAbsentTournament(id)
    toast.success('Đã báo vắng thành công!')
    await detailTournament(id)
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi báo vắng.')
  }
}

function confirmUser() {
  confirm(selectedUser.value.id)
}

const handleConfirmUser = async (item) => {
  try {
    await ParticipantService.confirmParticipants(item.id)
    toast.success('Đã xác nhận thành viên tham gia giải đấu!')
    await detailTournament(id)
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi xác nhận thành viên.')
  }
}

const handleRejectUser = async (item) => {
  try {
    await ParticipantService.rejectParticipant(item.id)
    toast.success('Đã từ chối thành viên tham gia giải đấu!')
    await detailTournament(id)
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi từ chối thành viên.')
  }
}

const getMyClubs = async () => {
  try {
    const response = await ClubService.myClubs();
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

const handleRemoveUser = async (data) => {
  // Support both old format (id only) and new format (object from UserCard)
  const participantId = typeof data === 'object' ? data.id : data;
  if (!participantId) {
    toast.error('Không tìm thấy ID người tham gia');
    return;
  }
  try {
    await ParticipantService.deleteParticipant(participantId);
    toast.success('Đã xóa người chơi khỏi giải đấu');
    await detailTournament(id);
    await getTeams();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Xóa người chơi thất bại');
  }
};

const handleRemoveStaff = async (data) => {
  // data.id = tournament_staff.id, data.userId = user.id
  const tournamentStaffId = data.id;
  if (!tournamentStaffId) {
    toast.error('Không tìm thấy ID thành viên ban tổ chức');
    return;
  }
  try {
    const response = await TournamentStaffService.removeTournamentStaff(id, tournamentStaffId);
    toast.success(response.message);
    await detailTournament(id);
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi xóa người trong ban tổ chức');
  }
};

// Hàm xử lý thống nhất
const handleInviteAction = async (user) => {
  if (inviteType.value === 'staff') {
    await inviteStaff(user.id, user.role);
  } else {
    await invite(user.id);
  }
  await detailTournament(id);
}

// Cập nhật các hàm mở modal
const openInviteModalWithFriends = async () => {
  inviteType.value = 'participant'
  activeScope.value = 'friends'
  await getInviteGroupData()
  showInviteModal.value = true
}

const openInviteModalDefault = async () => {
  inviteType.value = 'participant' // hoặc 'participant' tuỳ ngữ cảnh
  activeScope.value = 'all'
  await getInviteGroupData()
  showInviteModal.value = true
}

const openInviteModalStaff = async (role = 'organizer') => {
  inviteType.value = 'staff'
  selectedStaffRole.value = role
  activeScope.value = 'all'
  await getInviteGroupData()
  showInviteModal.value = true
}

const handleRemoveMember = async (data, teamId) => {
  // Support both old format (id only) and new format (object from UserCard)
  const memberId = typeof data === 'object' ? data.id : data;
  if (!memberId) {
    toast.error('Không tìm thấy ID thành viên');
    return;
  }
  try {
    await TeamService.removeMember(memberId, teamId);
    toast.success('Đã xóa thành viên khỏi đội');
    await getTeams();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Xóa thành viên thất bại');
  }
};

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
    ...(activeScope.value === 'club' ? { club_id: selectedClub.value } : {}),
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
    const resp = await ParticipantService.getTournamentInviteGroups(id, payload)

    const newData = resp?.result || []

    if (loadMore) {
      inviteGroupData.value.result.push(...newData)
    } else {
      inviteGroupData.value = resp
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

// Khi user đổi CLB trong component con
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

const getRanks = async () => {
  try {
    const response = await TournamentTypeService.getRanks(id);
    ranks.value = response || [];
  } catch (error) {
    toast.error(error.response?.data?.message || 'Lấy bảng xếp hạng thất bại');
  }
}

const handleUpdateOwnScore = debounce(async () => {
  isHandleOwnScore.value = !isHandleOwnScore.value
  await updateTournament(tournament.value.id, { is_own_score: isHandleOwnScore.value })
})

const openBracketPage = () => {
  router.push({ name: 'tournament-bracket', param: { id: id }, query: { tab: activeTab.value } });
};

const openGroupsSortPage = () => {
  router.push({ name: 'tournament-groups-sort', params: { id: id }, query: { tab: activeTab.value } });
}

function formatMatchCount(matches) {
  if (!matches) return "-";
  const { min, max } = matches;
  return min === max ? `${min}` : `${min}-${max}`;
}
const tournamentLink = window.location.href;
const copyLink = () => {
  if (navigator.share) {
    navigator.share({
      title: 'Hãy tham gia giải đấu ' + tournament.value.name + ' của tôi!',
      url: tournamentLink
    }).then(() => {
      toast.success('Đã sao chép link giải đấu vào clipboard!');
    }).catch(console.error);
  } else if (navigator.clipboard) {
    navigator.clipboard.writeText(tournamentLink).then(() => {
      toast.success('Đã sao chép link giải đấu vào clipboard!');
    }).catch(console.error);
  } else {
    alert(`Link giải đấu: ${tournamentLink}`);
  }
}

const showQRCode = () => {
  showQRCodeModal.value = true;
}

function getRankingLabel(value) {
  const map = {
    1: "Thắng / Hòa / Thua",
    2: "% Thắng",
    3: "Số hiệp thắng",
    4: "Số điểm thắng",
    5: "Đối đầu",
    6: "Bốc thăm",
  };
  return map[value] || "-";
}

const FORMAT_MIXED = 1;
const FORMAT_ELIMINATION = 2;
const FORMAT_ROUND_ROBIN = 3;

const totalMatches = computed(() => {
  const numLegs = parseInt(tournament.value?.tournament_types?.[0]?.num_legs) || 1;
  const teams = parseInt(tournament.value?.max_team) || 0;
  const tournamentType = tournament.value?.tournament_types?.[0];

  if (!teams || teams < 2) return 0;
  let currentFormat = tournamentType?.format;

  let matches = 0;
  switch (currentFormat) {
    case FORMAT_ROUND_ROBIN:
      matches = (teams * (teams - 1) / 2);
      return Math.floor(matches * numLegs);
    case FORMAT_ELIMINATION:
      const hasThirdDirect = JSON.parse(tournamentType?.format_specific_config?.[0]?.has_third_place_match || 'false');
      matches = teams - 1 + (hasThirdDirect ? 1 : 0);
      return Math.floor(matches * numLegs);
    case FORMAT_MIXED:
    default:
      const numGroups = parseInt(tournamentType?.format_specific_config?.[0]?.pool_stage?.number_competing_teams) || 1;
      const teamsPerGroup = Math.floor(teams / numGroups);
      const remainder = teams % numGroups;
      let totalGroupMatches = 0;
      for (let i = 0; i < numGroups; i++) {
        const groupSize = teamsPerGroup + (i < remainder ? 1 : 0);
        if (groupSize >= 2) {
          const matchesInGroup = (groupSize * (groupSize - 1)) / 2;
          totalGroupMatches += matchesInGroup;
        }
      }
      totalGroupMatches = totalGroupMatches * numLegs;
      const numAdvancingTeamsPerGroup = parseInt(tournamentType?.format_specific_config?.[0]?.pool_stage?.num_advancing_teams) || 0;
      const qualifiedTeams = numAdvancingTeamsPerGroup * numGroups;
      let knockoutMatches = 0;
      if (qualifiedTeams >= 2) {
        const hasThirdDirect = JSON.parse(tournamentType?.format_specific_config?.[0]?.has_third_place_match || 'false');
        knockoutMatches = qualifiedTeams - 1 + (hasThirdDirect ? 1 : 0);
      }
      const totalKnockoutMatches = knockoutMatches * numLegs;

      return Math.floor(totalGroupMatches + totalKnockoutMatches);
  }
});

const totalTime = computed(() => {
  return totalMatches.value * 15;
});

const totalTimeInHours = computed(() => {
  return Math.ceil(totalTime.value / 60);
});

const openInviteUserToTeamModal = async (team) => {
  if (!isCreator.value) {
    toast.error('Bạn không có quyền thực hiện thay đổi này')
    return
  }
  selectedTeam.value = team;
  showInviteUserToTeamModal.value = true;
  await getNonTeamParticipants();
};

const getNonTeamParticipants = async () => {
  isFetchingNonTeamUsers.value = true;
  try {
    const res = await ParticipantService.getParticipantsNonTeam(id)
    if (res) {
      nonTeamParticipants.value = res?.participants || [];
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi tải danh sách người chơi.');
  } finally {
    isFetchingNonTeamUsers.value = false;
  }
};

const handleAddUserToTeam = async (user) => {
  if (!selectedTeam.value || !user.id) return;
  try {
    await TeamService.addUserToTeam(selectedTeam.value.id, user.user.id);
    toast.success(`Đã thêm ${user.user.name} vào đội ${selectedTeam.value.name}!`);
    showInviteUserToTeamModal.value = false;
    await getTeams();
    await getNonTeamParticipants();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi thêm người dùng vào đội.');
  }
}

const confirmRemoval = () => {
  showDeleteModal.value = true;
};

const confirmChangeType = () => {
  showDeleteTournamentTypeModal.value = true;
};

const startSetup = () => {
  showFormatType.value = true;
};

const backgroundClasses = BACKGROUND_COLORS;

const getTeamBgClass = (teamId) => {
  const idNumber = Number(teamId);
  const colorIndex = idNumber % backgroundClasses.length;

  return backgroundClasses[colorIndex];
};

const getRemainingSlots = (members) => {
  const maxPlayers = tournament.value.player_per_team || 0;
  const currentCount = members ? members.length : 0;

  return Math.max(0, maxPlayers - currentCount);
};

const displayFormat = computed(() => {
  const type = tournament.value?.tournament_types?.[0];

  if (!type) {
    return null;
  }

  return FORMAT_DETAILS[type.format] || {
    icon: directIcon,
    title: 'Thể thức không xác định',
    description: 'Vui lòng kiểm tra lại cấu hình thể thức giải đấu.'
  };
});

const handleConfigUpdate = (configData) => {
  currentConfig.value = configData;
};

const handleFormSubmit = async (finalConfig) => {
  const tournamentId = tournament.value.id || null;
  if (!tournamentId) {
    toast.error('ID giải đấu không hợp lệ');
    return;
  }
  const formData = finalConfig;
  formData.append('tournament_id', tournamentId);
  await storeTournamentType(formData);
};

const storeTournamentType = async (payload) => {
  try {
    await TournamentTypeService.createTournamentType(payload);
    toast.success('Thể thức thi đấu đã được lưu thành công!');
    showFormatType.value = false;
    const response = await TournamentService.getTournamentById(tournament.value.id);
    tournament.value.tournament_types = response.tournament_types;
    await getRanks();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi lưu thể thức thi đấu.');
  }
};

const handleFileSelect = (file) => {
  if (!file) return;

  if (!file.type.startsWith('image/')) {
    toast.error('Ảnh tải lên không hợp lệ');
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    toast.error('Kích thước ảnh vượt quá 5MB');
    return;
  }

  fileToUpload.value = file;

  const reader = new FileReader();
  reader.onload = (e) => {
    preview.value = e.target.result;
    updateTournament(tournament.value.id, { poster: fileToUpload.value });
  };
  reader.readAsDataURL(file);
};

const handleFileInputChange = (e) => {
  const file = e.target.files[0];
  handleFileSelect(file);
};

const handleDragOver = () => {
  if (!isCreator.value) {
    toast.error('Bạn không có quyền thực hiện thay đổi này')
    return
  }
  isDragging.value = true;
};

const handleDragLeave = () => {
  if (!isCreator.value) {
    toast.error('Bạn không có quyền thực hiện thay đổi này')
    return
  }
  isDragging.value = false;
};

const handleDrop = (e) => {
  isDragging.value = false;
  const file = e.dataTransfer.files[0];
  handleFileSelect(file);
};

const handleClick = () => {
  if (!isCreator.value) {
    toast.error('Bạn không có quyền thực hiện thay đổi này')
    return
  }
  fileInput.value?.click();
};

const handleRemove = () => {
  preview.value = null;
  if (fileInput.value) {
    fileInput.value.value = '';
  }
  updateTournament(tournament.value.id, { remove_poster: true });
};

const handleInvite = async (user) => {
  await invite(user.id);
  await detailTournament(id);
}

const handleInviteUser = async (user) => {
  await inviteStaff(user.id);
  await detailTournament(id);
}

/** Tab VĐV: ParticipantResource có name/avatar root + user.full_name; sports nằm trong user.sports */
function getParticipantDisplayName(p) {
  if (!p) return ''
  if (p.is_guest && p.guest_name) return p.guest_name
  return p.user?.full_name ?? p.name ?? ''
}

function getParticipantAvatar(p) {
  if (!p) return ''
  if (p.is_guest) {
    if (p.guest_avatar) return p.guest_avatar
    return guestDefaultAvatar
  }
  return p.avatar ?? p.user?.avatar_url ?? ''
}

const getUserScore = (user) => {
  if (!user) {
    return '0';
  }

  // Guest: estimated_level ở root participant
  if (user.is_guest && user.estimated_level != null && user.estimated_level !== '') {
    const score = parseFloat(user.estimated_level);
    return isNaN(score) ? '0' : score.toFixed(1);
  }

  // Staff: sports ở root. Participant tab: sports trong user.sports. Team member: sports ở root.
  const sportsData = user.sports ?? user.user?.sports;
  if (!sportsData || sportsData.length === 0 || !tournament?.value?.sport_id) {
    return '0';
  }

  const requiredSportId = Number(tournament.value.sport_id);
  const matchedSport = sportsData.find((s) => Number(s.sport_id) === requiredSportId);
  if (!matchedSport || !matchedSport.scores) {
    return '0';
  }

  const rawScore = matchedSport.scores.vndupr_score ?? matchedSport.scores.vndupr;
  if (rawScore != null && rawScore !== '') {
    const score = parseFloat(String(rawScore).replace(/,/g, ''));
    return isNaN(score) ? '0' : score.toFixed(1);
  }

  return '0';
};

const detailTournament = async (tournamentId) => {
  try {
    const response = await TournamentService.getTournamentById(tournamentId)
    tournament.value = response
    autoApprove.value = response.auto_approve
    publicBracket.value = response.is_public_branch
    isHandleOwnScore.value = response.is_own_score
    preview.value = response.poster || null
    if (response.description) {
      isEditingDescription.value = true;
    }
    descriptionModel.value = response.description || '';
    if (tournament.value?.tournament_types?.length) {
      await getRanks();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi tải thông tin giải đấu.')
  }
}

const updateTournament = async (id, payload) => {
  try {
    const formData = new FormData()
    for (const key in payload) {
      let value = payload[key]
      if (typeof value === 'boolean') {
        value = value ? '1' : '0'
      }
      formData.append(key, value)
    }
    await TournamentService.updateTournament(id, formData)
    toast.success('Cập nhật thông tin giải đấu thành công!')
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi cập nhật thông tin giải đấu.')
  }
}

const toggleAutoApprove = debounce(async () => {
  autoApprove.value = !autoApprove.value
  await updateAutoApprove(autoApprove.value)
}, 300)

const updateAutoApprove = async (value) => {
  try {
    await updateTournament(tournament.value.id, { auto_approve: value })
    autoApprove.value = value
  } catch (error) {
  }
}

const togglePublicBranch = debounce(async () => {
  publicBracket.value = !publicBracket.value
  await updatePublicBranch(publicBracket.value)
}, 300);

const updatePublicBranch = async (value) => {
  try {
    await updateTournament(tournament.value.id, { is_public_branch: value })
    publicBracket.value = value
  } catch (error) {
  }
}

const confirm = async (participantId) => {
  try {
    await ParticipantService.confirmParticipants(participantId)
    toast.success('Xác nhận thành viên thành công!')
    await detailTournament(id);
    await getListHasInvite();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi xác nhận thành viên.')
  }
}

const goToEditPage = () => {
  router.push({
    name: 'edit-tournament',
    params: { id: tournament.value.id }
  });
};

const saveDescription = async () => {
  await updateTournament(tournament.value.id, { description: descriptionModel.value });
  tournament.value.description = descriptionModel.value;
  isEditingDescription.value = false;
};

const publicTournament = async () => {
  const newStatus = tournament.value.status === 1 ? 2 : 1;
  let res = null;

  try {
    res = await updateTournament(tournament.value.id, { status: newStatus });
    if (res && res.status) {
      tournament.value.status = res.status;
    } else {
      tournament.value.status = newStatus;
    }
  } catch (error) {
  }
}

const removeTournament = async () => {
  const id = tournament.value.id
  try {
    await TournamentService.deleteTournament(id)
    toast.success('Xoá giải đấu thành công!')
    setTimeout(() => {
      router.push('/')
    }, 1500)
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi xoá giải đấu.')
  }
}

const removeTournamentType = async () => {
  const tournamentType = tournament.value?.tournament_types?.[0];
  if (!tournamentType || !tournamentType.id) {
    toast.error('Không tìm thấy thể thức thi đấu để xoá. Vui lòng thử lại.');
    return;
  }
  try {
    await TournamentTypeService.deleteTournamentType(tournamentType.id)
    toast.success('Thể thức thi đấu đã được xoá thành công!')
    showDeleteTournamentTypeModal.value = false;
    showFormatType.value = true;
    await detailTournament(id);
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi xoá thể thức thi đấu.')
  }
}

const reGenerateMatches = async () => {
  const tournamentType = tournament.value?.tournament_types?.[0];
  if (!tournamentType || !tournamentType.id) {
    toast.error('Không tìm thấy thể thức thi đấu để chia lại cặp đấu. Vui lòng thử lại.');
    return;
  }
  try {
    await resetBracket()
    showReGenerateBracketModal.value = false;
    showFormatType.value = true;
    await detailTournament(id);
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi xoá các trận đấu cũ.')
  }
}

const autoAssign = async () => {
  try {
    const teamsResponse = await TeamService.autoAssignTeams(id)
    const teams = teamsResponse || []
    if (teams.length === 0) {
      toast.info('Không có đội nào để phân chia tự động.')
      await getTeams();
      return
    }
    await getTeams();
    toast.success('Đã phân chia đội tự động thành công!')
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi phân chia đội tự động.')
  }
}

const invite = async (friendId) => {
  try {
    await ParticipantService.sendInvitation(id, [friendId]);
    toast.success('Đã gửi lời mời thành công!');
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi gửi lời mời.');
  }
};

const inviteStaff = async (userId, role = 'organizer') => {
  try {
    let response;
    if (role === 'referee') {
      response = await TournamentStaffService.addReferee(id, userId);
    } else {
      response = await TournamentStaffService.addTournamentStaff(id, userId);
    }
    toast.success(response.message);
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi');
  }
};

const getTeams = async () => {
  try {
    const response = await TeamService.getTeamsByTournamentId(id, { per_page: 50 })
    listTeams.value = response.teams || []
  } catch (error) {
    return []
  }
}

const openEditTeamModal = (team) => {
  if (!isCreator.value) {
    return
  }
  isOpenUpdateTeamModal.value = true
  selectedTeamDetail.value = team
}

const openCreateTeamModal = () => {
  isOpenCreateTeamModal.value = true
}

const handleUpdateInfo = async (payload) => {
  isSavingTeam.value = true;
  const formData = new FormData();
  formData.append('name', payload.name);
  if (payload.avatar) {
    formData.append('avatar', payload.avatar);
  }
  try {
    const teamId = selectedTeamDetail.value.id;
    await TeamService.updateTeam(teamId, formData);
    isOpenUpdateTeamModal.value = false;
    toast.success('Cập nhật đội thành công!');
    await getTeams();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi cập nhật đội.');
  } finally {
    isSavingTeam.value = false;
  }
};

const handleDeleteTeam = async (teamId) => {
  try {
    await TeamService.deleteTeam(teamId);
    toast.success('Xoá đội thành công!');
    isOpenUpdateTeamModal.value = false;
    await getTeams();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi xoá đội.');
  } finally {
    isSavingTeam.value = false;
  }
};

const handleCreateInfo = async (payload) => {
  isLoading.value = true;
  const formData = new FormData();
  formData.append('name', payload.name);
  if (payload.avatar) {
    formData.append('avatar', payload.avatar);
  }
  try {
    await TeamService.createTeam(id, formData);
    isOpenCreateTeamModal.value = false;
    toast.success('Tạo đội thành công!');
    await getTeams();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi tạo đội.');
  } finally {
    isLoading.value = false;
  }
};

const getListHasInvite = async (page = 1) => {
  try {
    const response = await ParticipantService.listInviteUsers(id, { page: page });
    if (page > 1) {
      listHasInvite.value.push(...(response.invitations || []));
    } else {
      listHasInvite.value = response.invitations || [];
    }
  } catch (error) {
  }
};

const resetBracket = async () => {
  const tournamentTypeId = tournament.value?.tournament_types?.[0]?.id;
  try {
    await TournamentTypeService.reGenerateMatches(tournamentTypeId);
    toast.success('Đã chia lại cặp đấu thành công!');
    showReGenerateBracketModal.value = false;
    await detailTournament(id);
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi chia lại cặp đấu.');
  }
};

const joinerTournament = async () => {
  const tournamentId = tournament.value.id;
  try {
    const res = await ParticipantService.joinTournament(tournamentId);
    if (res) {
      toast.success('Tham gia giải đấu thành công, Bạn có thể cần chờ xác nhận trước khi được bổ nhiệm vào 1 đội')
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Lỗi khi thực hiện yêu cầu này')
  }
}

const confirmTournament = async () => {
  const participantId =
    tournament.value?.tournament_participants?.find(
      p => p.user.id === getUser.value.id
    )?.id ?? null;
  try {
    const res = await ParticipantService.acceptInviteTournament(participantId)
    if (res) {
      await detailTournament(id)
      toast.success('Xác nhận tham gia giải đấu thành công')
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Lỗi khi thực hiện yêu cầu này')
  }
}

onMounted(async () => {
  activeTab.value = route.query.tab || 'detail'
  if (id) {
    await Promise.all([
      detailTournament(id),
      getTeams(),
      getListHasInvite(),
    ])
    await getMyClubs();
    await getInviteGroupData();
  }
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.overflow-y-auto::-webkit-scrollbar {
  width: 0;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
