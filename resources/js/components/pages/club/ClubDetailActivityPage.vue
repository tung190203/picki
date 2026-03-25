<template>
  <div class="p-4 md:p-6 min-h-[calc(100vh-5rem)] bg-[#F8F9FA]">
    <!-- Header -->
    <div class="flex items-center mb-6">
      <button @click="goBack" class="p-2 hover:bg-gray-100 rounded-full transition-colors mr-2">
        <ArrowLeftIcon class="w-6 h-6 text-[#3E414C]" />
      </button>
      <h1 class="text-xl font-semibold text-[#3E414C]">Chi tiết sự kiện</h1>
    </div>

    <!-- Skeleton Loader -->
    <div v-if="isLoading" class="animate-pulse">
      <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-8">
        <div class="flex-1 space-y-4">
          <div class="flex gap-3">
            <div class="h-8 w-64 bg-gray-200 rounded"></div>
            <div class="h-8 w-20 bg-gray-200 rounded"></div>
          </div>
          <div class="h-4 w-3/4 bg-gray-200 rounded"></div>
        </div>
        <div class="flex gap-3">
          <div class="h-12 w-32 bg-gray-200 rounded"></div>
          <div class="h-12 w-32 bg-gray-200 rounded"></div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div class="lg:col-span-8 space-y-6">
          <div class="bg-white rounded-[16px] p-6 border border-gray-50 h-[400px]">
            <div class="h-20 bg-gray-100 rounded-lg mb-6"></div>
            <div class="grid grid-cols-2 gap-4">
              <div class="h-32 bg-gray-50 rounded-2xl"></div>
              <div class="h-32 bg-gray-50 rounded-2xl"></div>
            </div>
            <div class="mt-8 space-y-3">
              <div class="h-4 w-1/4 bg-gray-100 rounded"></div>
              <div class="h-4 w-full bg-gray-100 rounded"></div>
              <div class="h-4 w-full bg-gray-100 rounded"></div>
            </div>
          </div>
        </div>
        <div class="lg:col-span-4 space-y-6">
          <div class="bg-white rounded-[24px] h-[300px] border border-gray-50"></div>
          <div class="bg-white rounded-[24px] h-[300px] border border-gray-50"></div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div v-else-if="!isLoading && activity.title">
      <!-- Page Title & Actions -->
      <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-8">
        <div class="flex-1">
          <div class="flex items-center gap-3 mb-2">
            <h2 class="text-2xl font-semibold text-[#3E414C]">{{ activity.title }}</h2>
            <span class="px-3 py-1 bg-[#F2F7FC] text-sm font-semibold rounded-[4px]" :class="activity.is_private ? 'text-[#4392E0]' : 'text-[#00B377]'">
              {{ activity.is_private ? 'Private' : 'Public' }}
            </span>
          </div>
          <p class="text-[#838799] whitespace-pre-line">{{ activity.summary }}</p>
        </div>
        <div class="flex items-center gap-3">
          <Button
            v-if="isOwner && !isFinished && activity.status !== 'completed' && activity.status !== 'cancelled'"
            size="lg"
            color="primary"
            class="px-4 py-1 rounded-[4px] font-semibold shadow-lg shadow-blue-100"
            @click="updateEvent"
          >
            <div class="flex items-center gap-2">
              <WrenchIcon class="w-5 h-5" />
              Cập nhật
            </div>
          </Button>
          <Button
            v-if="registrationButtonState.showAbsent"
            size="lg"
            color="white"
            class="px-4 py-1 rounded-[4px] font-semibold border border-[#DCDEE6] bg-[#EDEEF2] text-[#3E414C] shadow-sm transition-all hover:bg-gray-50"
            @click="handleSelfAbsentActivity"
          >
            <div class="flex items-center gap-2">
              <XMarkIcon class="w-5 h-5" />
              Báo vắng
            </div>
          </Button>
          <Button
            size="lg"
            :color="registrationButtonState.color"
            class="px-4 py-1 rounded-[4px] font-semibold"
            :class="registrationButtonState.shadowClass"
            :disabled="registrationButtonState.disabled"
            @click="registrationButtonState.action"
          >
            <div class="flex items-center gap-2">
              <component :is="registrationButtonState.icon" class="w-5 h-5" />
              {{ registrationButtonState.text }}
            </div>
          </Button>
          <Button
            v-if="isOwner && !isFinished && activity.status !== 'completed' && activity.status !== 'cancelled'"
            size="lg"
            color="danger"
            class="px-4 py-1 rounded-[4px] font-semibold shadow-lg shadow-red-100"
            @click="shareEvent"
          >
            <div class="flex items-center gap-2">
              <ShareIcon class="w-5 h-5" />
              Chia sẻ
            </div>
          </Button>
          <Button
            v-if="isOwner && !isFinished && activity.status !== 'completed' && activity.status !== 'cancelled'"
            size="lg"
            color="white"
            class="px-4 py-1 rounded-[4px] font-semibold border border-[#D72D36] bg-[#FFF5F5] text-[#D72D36] shadow-sm transition-all hover:bg-[#FFEBEB]"
            @click="openPromotionModal"
          >
            <div class="flex items-center gap-2">
              <MegaphoneIcon class="w-5 h-5" />
              Quảng bá
            </div>
          </Button>
          <Button
            v-if="isOwner && !isFinished && activity.status !== 'completed' && activity.status !== 'cancelled'"
            size="lg"
            color="white"
            class="px-4 py-1 rounded-[4px] font-semibold border border-[#DCDEE6] bg-[#EDEEF2] text-[#3E414C] shadow-sm transition-all hover:bg-gray-50"
            @click="cancelEvent"
          >
            <div class="flex items-center gap-2">
              <XMarkIcon class="w-5 h-5" />
              Huỷ sự kiện
            </div>
          </Button>
          <Button
            v-if="!isOwner && !isFinished && activity.status !== 'completed' && activity.status !== 'cancelled'"
            size="lg"
            color="white"
            class="px-4 py-1 rounded-[4px] font-semibold border border-[#DCDEE6] bg-[#EDEEF2] text-[#3E414C] shadow-sm transition-all hover:bg-gray-50"
            @click="shareEvent"
          >
            <div class="flex items-center gap-2">
              <ShareIcon class="w-5 h-5" />
              Chia sẻ
            </div>
          </Button>
        </div>
      </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      <!-- Left Column (Main Info) -->
      <div class="lg:col-span-8 space-y-6">
        <!-- Event Quick Info Cards -->
        <div class="bg-white rounded-[16px] p-6 shadow-sm border border-gray-50">
          <div class="flex flex-wrap gap-4 bg-[#FBEAEB] rounded-lg p-4">
            <!-- Time Card -->
            <div class="flex-1 min-w-[200px] flex items-center gap-4">
              <div class="w-12 h-12 bg-white rounded-lg shadow-sm flex items-center justify-center text-[#D72D36]">
                <ClockIcon class="w-6 h-6" />
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <div class="font-semibold text-[#3E414C]">{{ formatTimeRange(activity.start_time, activity.end_time) }}</div>
                  <span class="px-2 py-0.5 bg-[#D72D36] text-white text-[11px] rounded-[4px] font-bold">
                    {{ diffTimeText(activity.start_time, activity.end_time) }}
                  </span>
                </div>
                <div class="text-sm text-[#838799] font-medium">{{ formatDate(activity.start_time) }}</div>
              </div>
            </div>

            <!-- Location Card -->
            <div class="flex-1 min-w-[200px] flex items-center gap-4">
              <div class="w-12 h-12 bg-white rounded-lg shadow-sm flex items-center justify-center text-[#D72D36]">
                <MapPinIcon class="w-6 h-6" />
              </div>
              <div>
                <div class="font-semibold text-[#3E414C] truncate max-w-[200px]" v-tooltip="activity.location">{{ activity.location }}</div>
              </div>
            </div>

            <!-- Frequency Card -->
            <div class="flex-1 min-w-[200px] flex items-center gap-4">
              <div class="w-12 h-12 bg-white rounded-lg shadow-sm flex items-center justify-center text-[#D72D36]">
                <ArrowPathRoundedSquareIcon class="w-6 h-6" />
              </div>
              <div class="font-semibold text-[#3E414C]">{{ recurringText }}</div>
            </div>
          </div>

          <!-- Secondary Detailed Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
            <!-- Cost Card -->
            <div class="border border-gray-100 rounded-2xl p-5 hover:border-[#D72D36]/20 transition-colors">
              <div class="flex items-center gap-3 mb-4">
                <div class="w-8 h-8 bg-[#D72D36] rounded-full p-2 flex items-center justify-center text-white">
                  <PriceCheckIcon class="w-5 h-5" />
                </div>
                <h4 class="font-semibold text-[#3E414C] text-xl">Chia tiền</h4>
              </div>
              <div class="space-y-2">
                <div class="flex justify-between items-center text-sm">
                  <span class="text-[#3E414C]">Cơ chế chia tiền</span>
                  <span class="font-semibold text-[#D72D36]">{{ feeSplitLabel }}</span>
                </div>
                <div class="flex justify-between items-center text-sm">
                  <span class="text-[#3E414C]">Thu khách vãng lai</span>
                  <span v-if="activity.guest_fee > 0" class="font-semibold text-[#D72D36]">{{ formatCurrency(activity.guest_fee) }}/người</span>
                  <span v-else class="font-semibold text-[#D72D36]">Không thu</span>
                </div>
              </div>
            </div>

            <!-- Regulation Card -->
            <div class="border border-gray-100 rounded-2xl p-5 hover:border-[#D72D36]/20 transition-colors">
              <div class="flex items-center gap-3 mb-4">
                <div class="w-8 h-8 bg-[#D72D36] rounded-full p-2 flex items-center justify-center text-white">
                  <RuleIcon class="w-5 h-5" />
                </div>
                <h4 class="font-semibold text-[#3E414C] text-xl">Quy định</h4>
              </div>
              <div class="space-y-2">
                <div class="flex justify-between items-center text-sm">
                  <span class="text-[#3E414C]">Hạn chót hủy kèo</span>
                  <span class="font-semibold text-[#D72D36]">Trước {{ activity.cancellation_deadline_hours }} Tiếng</span>
                </div>
                <div class="flex justify-between items-center text-sm">
                  <span class="text-[#3E414C]">Phạt hủy muộn</span>
                  <span v-if="activity.penalty_amount > 0" class="font-semibold text-[#D72D36]">{{ formatCurrency(activity.penalty_amount) }}/người</span>
                  <span v-else class="font-semibold text-[#D72D36]">Không có</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Description Section -->
          <div class="mt-8">
            <h4 class="font-semibold text-[#838799] uppercase tracking-wider mb-4">Mô tả chi tiết</h4>
            <div class="text-[#3E414C] leading-relaxed whitespace-pre-line">
              {{ activity.description }}
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column (Sidebar) -->
      <div class="lg:col-span-4 space-y-6">
        <!-- Join Request Card -->
        <div class="bg-white rounded-[24px] shadow-sm border border-gray-50 overflow-hidden" v-if="isOwner">
           <div class="p-5 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 bg-[#D72D36] rounded-full p-2 flex items-center justify-center text-white">
                <PlusCircleIcon class="w-5 h-5" />
              </div>
              <h3 class="font-semibold text-[#3E414C]">Yêu cầu tham gia</h3>
            </div>
            <button @click="isJoinRequestExpanded = !isJoinRequestExpanded" class="p-1 hover:bg-gray-100 rounded-full transition-transform duration-300" :class="{ 'rotate-180': !isJoinRequestExpanded }">
              <ChevronDownIcon class="w-5 h-5 text-gray-400" />
            </button>
          </div>

          <div class="grid transition-all duration-300 ease-in-out" :class="isJoinRequestExpanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'">
            <div class="overflow-hidden">
              <div class="px-8 py-4 flex flex-col items-center" v-if="joinActivityRequests.length === 0">
                <p class="text-[#838799] text-sm">Không có yêu cầu tham gia</p>
              </div>
              <template v-else>
                <div v-for="request in joinActivityRequests" :key="request.id" class="px-8 py-4 border-t border-gray-50">
                  <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                      <img :src="request.user.avatar_url" class="w-14 h-14 rounded-full" />
                      <div class="font-semibold text-[#3E414C]">{{ request.user.full_name }}</div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button
                          class="w-10 h-10 rounded-full bg-[#D72D36] flex items-center justify-center hover:bg-[#c4252e] transition-colors"
                          @click="rejectJoinActivityRequest(request.id)">
                          <XMarkIcon class="w-5 h-5 text-white" stroke-width="2.5" />
                        </button>
                        <button
                          class="w-10 h-10 rounded-full bg-[#00B377] flex items-center justify-center hover:bg-[#00a16b] transition-colors"
                          @click="approveJoinActivityRequest(request.id)">
                          <CheckIcon class="w-5 h-5 text-white" stroke-width="2.5" />
                        </button>
                      </div>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
        <!-- Participants Card -->
        <div class="bg-white rounded-[24px] shadow-sm border border-gray-50 overflow-hidden">
            <div class="p-5 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-[#D72D36] rounded-full p-2 flex items-center justify-center text-white">
                  <UsersIcon class="w-5 h-5" />
                </div>
                <h3 class="font-semibold text-[#3E414C]">Thành viên tham gia</h3>
                <span class="w-1 h-1 rounded-full bg-[#3E414C]"></span>
                <span class="px-2 py-0.5 bg-gray-100 text-[#838799] text-xs font-bold rounded-full">
                  {{ participants.filter(p => p.status !== 'pending').length }}/{{ activity.max_participants || '∞' }}
                </span>
              </div>
              <button @click="isParticipantsExpanded = !isParticipantsExpanded" class="p-1 hover:bg-gray-100 rounded-full transition-transform duration-300" :class="{ 'rotate-180': !isParticipantsExpanded }">
                <ChevronDownIcon class="w-5 h-5 text-gray-400" />
              </button>
            </div>

          <div class="grid transition-all duration-300 ease-in-out" :class="isParticipantsExpanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'">
            <div class="overflow-hidden">
              <div class="px-6 pb-6 pt-2">
                <h4 class="font-bold text-[#8E95A2] text-[13px] uppercase tracking-[0.2em] mb-6">NGƯỜI TẠO</h4>
                <!-- Creator -->
                <div class="flex items-center gap-5 mb-10">
                  <div class="relative">
                      <img :src="creator.avatar || defaultAvatar" class="w-14 h-14 rounded-full border-[2px] border-[#4392E0] shadow-sm object-cover" />
                      <div class="absolute -bottom-0.5 -right-0.5 p-1 bg-[#4392E0] rounded-full ring-2 ring-white">
                        <ShieldCheckIcon class="w-3 h-3 text-white" />
                      </div>
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-bold text-[#3E414C] text-[17px]">{{ creator.name }}</span>
                        <span class="px-2 py-0.5 bg-[#4392E0] text-white text-[11px] font-bold rounded-md uppercase">Admin</span>
                    </div>
                    <div class="text-[14px] text-[#8E95A2] font-medium leading-tight">
                        {{ Number(creator.level).toFixed(2) }} PICKI <span class="mx-1">•</span> Chủ kèo
                    </div>
                  </div>
                </div>
                  <div class="flex items-center gap-2 mb-4">
                    <h4 class="font-bold text-[#8E95A2] text-[13px] uppercase tracking-[0.2em]">THÀNH VIÊN</h4>
                    <span class="w-1 h-1 rounded-full bg-[#3E414C]"></span>
                    <span class="px-2 py-0.5 text-[#838799] text-[13px] font-bold uppercase tracking-[0.2rem]">
                      Đã check-in ({{ totalParticipantAttended }})
                    </span>
                  </div>
                <div class="space-y-0" v-if="participants && participants.filter(p => p.status !== 'pending').length">
                  <div v-for="(user, index) in participants.filter(p => p.status !== 'pending')" :key="user.id"
                    class="flex items-center py-5 group cursor-pointer gap-5" @click="goToProfile(user.userId)"
                    :class="{ 'border-t border-[#F0F2F5]': index !== 0 }">
                    <div class="relative flex-shrink-0">
                      <img :src="user.avatar || defaultAvatar" class="w-14 h-14 rounded-full bg-[#F2F4F7] object-cover" />
                      <div class="absolute -left-1 -bottom-0.5 w-5 h-5 bg-[#4392E0] text-white text-[8px] font-semibold flex items-center justify-center rounded-full ring-2 ring-white">
                        {{ Number(user.level).toFixed(2) }}
                      </div>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div class="flex items-center justify-between gap-4 mb-1">
                        <div class="font-bold text-[#3E414C] text-[17px] truncate">{{ user.name }}</div>
                        <div class="flex-shrink-0">
                          <span v-if="user.has_checked_in" class="px-2.5 py-1.5 bg-[#E9FBF3] text-[#00B377] text-[10px] font-bold rounded-[8px] uppercase border border-[#00B377]/10 whitespace-nowrap tracking-wide leading-tight">
                            ĐÃ CHECK-IN
                          </span>
                          <span v-else-if="user.is_absent" class="px-2.5 py-1.5 bg-[#FFF5F5] text-[#D72D36] text-[10px] font-bold rounded-[8px] uppercase border border-[#D72D36]/10 whitespace-nowrap tracking-wide leading-tight">
                            VẮNG
                          </span>
                          <span v-else class="px-2.5 py-1.5 bg-[#F8F9FA] text-[#838799] text-[10px] font-bold rounded-[8px] uppercase border border-gray-100 whitespace-nowrap tracking-wide leading-tight">
                            CHƯA CHECK-IN
                          </span>
                        </div>
                      </div>
                      <div class="text-[14px] text-[#A4B0C1] font-medium truncate">{{ user.joined_at || 'Tham gia 3 ngày trước' }}</div>
                    </div>
                  </div>
                </div>
                <div v-else class="text-center py-8">
                  <p class="text-[#8E95A2] text-[14px]">Chưa có thành viên tham gia sự kiện</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- QR Code Card -->
        <div class="bg-white rounded-[24px] shadow-sm border border-gray-50 overflow-hidden">
           <div class="p-5 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 bg-[#D72D36] rounded-full p-2 flex items-center justify-center text-white">
                <QrcodeIcon class="w-5 h-5" />
              </div>
              <h3 class="font-semibold text-[#3E414C]">Mã QR hoạt động</h3>
            </div>
            <button @click="isQRExpanded = !isQRExpanded" class="p-1 hover:bg-gray-100 rounded-full transition-transform duration-300" :class="{ 'rotate-180': !isQRExpanded }">
              <ChevronDownIcon class="w-5 h-5 text-gray-400" />
            </button>
          </div>

          <div class="grid transition-all duration-300 ease-in-out" :class="isQRExpanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'">
            <div class="overflow-hidden">
              <div class="p-8 flex flex-col items-center">
                <div ref="qrcodeContainer" class="p-4 mb-6 border rounded-[12px]">
                  <div class="space-y-2 mb-4">
                    <p class="text-center font-semibold text-[#3E414C]">{{ activity.title }}</p>
                    <p class="text-center text-[#3E414C] text-xs">{{ club.name }}</p>
                    <p class="text-center text-[#3E414C] text-xs">{{ formatTimeRange(activity.start_time, activity.end_time) }}, {{ formatDate(activity.start_time) }}</p>
                  </div>
                  <qrcode-vue :value="pageUrl" :size="200" level="H" class="rounded-[4px]" />
                </div>
                <div class="mb-6 w-full text-center">
                  <span class="text-sm text-[#4392E0] break-all">{{ pageUrl }}</span>
                </div>
                <div class="flex items-center gap-3 w-full">
                  <Button
                      size="md"
                      color="white"
                      class="flex-1 bg-white text-[#D72D36] border border-[#D72D36] font-bold py-3 hover:bg-[#FFF5F5] flex items-center justify-center"
                      @click="copyLink"
                  >
                    <LinkIcon class="w-5 h-5 mr-2" />
                    Sao chép link
                  </Button>
                  <Button
                      size="md"
                      color="danger"
                      class="flex-1 bg-[#D72D36] text-white border border-[#D72D36] font-bold py-3 hover:bg-[#c4252e] flex items-center justify-center"
                      @click="downloadQR"
                  >
                    <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
                    Lưu mã QR
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!isLoading && !activity.title" class="flex flex-col items-center justify-center py-20">
      <p class="text-[#838799] text-lg">Không tìm thấy sự kiện</p>
    </div>

    <!-- Cancellation Modal -->
    <CancelActivityModal
      v-model="showCancelModal"
      :is-submitting="isCancelling"
      :recurrence-series-id="activity.recurrence_series_id"
      @confirm="confirmCancelEvent"
    />

    <PromotionModal
      v-model="isPromotionModalOpen"
      promotable-type="club_activity"
      :promotable-id="Number(activity.id ?? activityId)"
      @success="toast.success('Đã gửi quảng bá thành công')"
    />

    <ClubCreateFundModal
      v-model:is-open="showCreateFundModal"
      :club="club"
      :initial-data="initialFundData"
      @submit="handleSubmitFundRevenue"
    />

    <ClubCreateExpenseModal
      v-model:is-open="showCreateExpenseModal"
      :initial-data="initialExpenseData"
      @submit="handleSubmitFundExpense"
    />
  </div>
</template>

<script setup>
import {
    ArrowLeftIcon,
    ShareIcon,
    ClockIcon,
    MapPinIcon,
    UsersIcon,
    ChevronDownIcon,
    ArrowDownTrayIcon,
    ArrowPathRoundedSquareIcon,
    XMarkIcon,
    PlusCircleIcon,
    CheckIcon,
    WrenchIcon,
    MegaphoneIcon,
    DocumentTextIcon,
    LinkIcon
} from '@heroicons/vue/24/outline'
import RegistrationIcon from '@/assets/images/registration.svg'
import PriceCheckIcon from '@/assets/images/price_check.svg'
import RuleIcon from '@/assets/images/rule.svg'
import ShieldCheckIcon from "@/assets/images/shield_check.svg";
import QrcodeIcon from '@/assets/images/qr_code.svg'
import Button from '@/components/atoms/Button.vue'
import CancelActivityModal from '@/components/molecules/CancelActivityModal.vue'
import PromotionModal from '@/components/organisms/PromotionModal.vue'
import ClubCreateFundModal from '@/components/pages/club/partials/ClubCreateFundModal.vue'
import ClubCreateExpenseModal from '@/components/pages/club/partials/ClubCreateExpenseModal.vue'
import QrcodeVue from 'qrcode.vue'
import html2canvas from 'html2canvas'
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '@/store/auth'
import { storeToRefs } from 'pinia'
import * as ClubService from '@/service/club.js'
import { toast } from 'vue3-toastify'
import dayjs from 'dayjs'
import relativeTime from 'dayjs/plugin/relativeTime'
import { diffTimeText } from '@/composables/formatTime'
import debounce from 'lodash.debounce'
import { formatCurrency } from '@/composables/formatCurrency'
import 'dayjs/locale/vi'
dayjs.extend(relativeTime)
dayjs.locale('vi')
const defaultAvatar = "/images/default-avatar.png";

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)
const clubId = route.params.id
const activityId = computed(() => route.params.activityId)
const pageUrl = computed(() => window.location.href)
const activity = ref({})
const club = ref({})
const participants = ref([])
const creator = ref({})
const isParticipantsExpanded = ref(true)
const isQRExpanded = ref(true)
const isJoinRequestExpanded = ref(true)
const qrcodeContainer = ref(null)
const isLoading = ref(true)
const joinActivityRequests = ref([])
const showCancelModal = ref(false)
const isCancelling = ref(false)
const isPromotionModalOpen = ref(false)
const showCreateFundModal = ref(false)
const showCreateExpenseModal = ref(false)

const isOwner = computed(() => {
    return activity.value.created_by === getUser.value.id
})

const isFinished = computed(() => {
    if (!activity.value.end_time) return false
    return dayjs().isAfter(dayjs(activity.value.end_time))
})

const recurringText = computed(() => {
    const schedule = activity.value.recurring_schedule
    if (!schedule) return 'Không lặp lại'

    // Handle new object structure
    if (typeof schedule === 'object' && schedule.period) {
        if (schedule.period === 'daily') return 'Lặp lại hàng ngày'
        if (schedule.period === 'weekly') {
            const days = schedule.week_days
            if (!days || (Array.isArray(days) && days.length === 0)) return 'Hàng tuần'

            const rawDays = Array.isArray(days) ? days : String(days).split(',').filter(d => d !== '').map(Number)
            const dayValues = rawDays.map(d => Number(d) === 7 ? 0 : Number(d))

            const uniqueDays = [...new Set(dayValues)].sort((a, b) => {
                if (a === 0) return 1
                if (b === 0) return -1
                return a - b
            })

            if (uniqueDays.length === 7) return 'Lặp lại hàng tuần'

            const dayLabels = {
                1: 'T2', 2: 'T3', 3: 'T4', 4: 'T5', 5: 'T6', 6: 'T7', 0: 'CN'
            }

            const selectedLabels = uniqueDays.map(d => dayLabels[d])
            return `Lặp lại: ${selectedLabels.join(', ')}`
        }
        if (schedule.period === 'monthly') return 'Lặp lại hàng tháng'
        if (schedule.period === 'quarterly') return 'Lặp lại hàng quý'
        if (schedule.period === 'yearly') return 'Lặp lại hàng năm'
        return 'Có lặp lại'
    }

    // Handle old format (array or string)
    const days = schedule
    if (Array.isArray(days) && days.length === 0) return 'Không lặp lại'

    const rawDays = Array.isArray(days) ? days : String(days).split(',').filter(d => d !== '').map(Number)
    const dayValues = rawDays.map(d => Number(d) === 7 ? 0 : Number(d))

    const uniqueDays = [...new Set(dayValues)].sort((a, b) => {
        if (a === 0) return 1
        if (b === 0) return -1
        return a - b
    })

    if (uniqueDays.length === 7) return 'Lặp lại hàng tuần'

    const dayLabels = {
        1: 'T2', 2: 'T3', 3: 'T4', 4: 'T5', 5: 'T6', 6: 'T7', 0: 'CN'
    }

    const selectedLabels = uniqueDays.map(d => dayLabels[d])
    return `Lặp lại: ${selectedLabels.join(', ')}`
})

const totalParticipantAttended = computed(() => {
  return participants.value.filter(p => p.has_checked_in).length
})

const initialFundData = computed(() => {
  if (!activity.value) return {}

  const checkedInUserIds = participants.value
    .filter(p => p.has_checked_in)
    .map(p => p.userId)

  let amount = 0
  if (activity.value.fee_split_type === 'fixed') {
    amount = activity.value.fee_amount || 0
  } else if (activity.value.fee_split_type === 'equal') {
    const totalAttended = totalParticipantAttended.value
    if (totalAttended > 0) {
      amount = Math.round((activity.value.fee_amount || 0) / totalAttended)
    }
  }

  return {
    title: `Chốt bill: ${activity.value.title}`,
    amount: amount,
    memberIds: checkedInUserIds.length > 0 ? checkedInUserIds : participants.value.filter(p => p.status !== 'pending').map(p => p.userId),
    qr_code_url: activity.value.fund_collection?.qr_code_url || activity.value.qr_code_url || null
  }
})

const initialExpenseData = computed(() => {
  if (!activity.value) return {}
  return {
    title: `Chi phí sự kiện: ${activity.value.title}`,
    amount: activity.value.fee_amount || 0
  }
})

const feeSplitLabel = computed(() => {
  const map = {
    equal: 'Chia đều',
    fixed: 'Cố định',
    fund: 'Quỹ chi',
  }
    return map[activity.value.fee_split_type] || ''
})

const getActivityDetail = async () => {
    try {
        const response = await ClubService.getClubActivityDetail(clubId, activityId.value)
        const data = response.data
        if (data) {
            activity.value = {
                ...data,
                summary: data.description || '',
                is_private: data.type === 'private',
                guest_fee: data.guest_fee || 0
            }

            if (data.participants) {
                participants.value = data.participants?.map(p => ({
                    id: p.id,
                    name: p.user?.full_name || 'Thành viên',
                    avatar: p.user?.avatar_url || p.user?.thumbnail,
                    level: p.user?.sports[0]?.scores?.vndupr_score || 'N/A',
                    joined_at: p.created_at ? dayjs(p.created_at).fromNow() : 'Vừa tham gia',
                    status: p.status,
                    userId: p.user_id,
                    is_absent: !!p.is_absent,
                    has_checked_in: !!p.has_checked_in
                }))
            }

            if (data.creator) {
                creator.value = {
                    name: data.creator.full_name,
                    level: data.creator?.sports[0]?.scores?.vndupr_score || 'N/A',
                    avatar: data.creator.avatar_url || data.creator.thumbnail
                }
            }
        }
    } catch (error) {
        console.error(error)
        toast.error('Không thể lấy thông tin chi tiết sự kiện')
    }
}

const getClubDetail = async () => {
    try {
        const response = await ClubService.clubDetail(clubId)
        club.value = response
    } catch (error) {
        console.error(error)
    }
}

const goBack = () => {
    router.back()
}

const registerEvent = () => {
    if (activity.value.is_public) {
        joinActivityRequest()
    } else {
        toast.info('Tính năng đăng ký đang được xử lý')
    }
}

const cancelJoinEvent = async () => {
    const userId = getUser.value.id
    const pendingRequest = joinActivityRequests.value.find(r => r.user_id === userId || r.user?.id === userId)
    if (!pendingRequest) return

    try {
        await ClubService.cancelActivityJoinRequest(clubId, activityId.value, pendingRequest.id)
        toast.success('Đã hủy yêu cầu tham gia')
        await Promise.all([
            getActivityDetail(),
            getListJoinActivityRequest()
        ])
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể hủy yêu cầu tham gia')
    }
}

const currentUserParticipant = computed(() => {
    return participants.value.find(p => p.userId === getUser.value.id)
})

const registrationButtonState = computed(() => {
    const userId = getUser.value.id
    const isCancelled = activity.value.status === 'cancelled'
    const isCompleted = activity.value.status === 'completed' || isFinished.value

    if (isCancelled) {
        return {
            text: 'Hoạt động đã bị huỷ',
            color: 'white',
            shadowClass: 'border border-[#DCDEE6] bg-gray-100 text-[#838799] shadow-sm cursor-not-allowed',
            disabled: true,
            action: () => { },
            icon: XMarkIcon,
            showAbsent: false
        }
    }

    if (isCompleted && activity.value.has_transaction) {
        return {
            text: 'Bảng thu tiền',
            color: 'danger',
            shadowClass: 'shadow-lg shadow-red-100',
            disabled: false,
            action: goToFundPage,
            icon: DocumentTextIcon,
            showAbsent: false
        }
    }

    if (isOwner.value && isFinished.value && !activity.value.has_transaction) {
        return {
            text: activity.value.fee_split_type === 'fund' ? 'Chốt bill hoạt động' : 'Chốt bill chia tiền',
            color: 'danger',
            shadowClass: 'shadow-lg shadow-red-100',
            disabled: false,
            action: handleOpenFinalizeBill,
            showAbsent: false
        }
    }

    // 1. Check if user is in joinActivityRequests (Pending)
    const pendingRequest = joinActivityRequests.value.find(r => r.user_id === userId || r.user?.id === userId)
    if (pendingRequest) {
        return {
            text: 'Đang chờ duyệt',
            color: 'white',
            shadowClass: 'border border-[#DCDEE6] bg-[#EDEEF2] text-[#3E414C] shadow-sm',
            disabled: false,
            action: cancelJoinEvent,
            icon: ClockIcon,
            showAbsent: false
        }
    }

    // 2. Check if user is in participants (Joined)
    const participant = participants.value.find(p => p.userId === userId)
    if (participant) {
        if (participant.status === 'pending') {
            return {
                text: 'Đang chờ duyệt',
                color: 'white',
                shadowClass: 'border border-[#DCDEE6] bg-[#EDEEF2] text-[#3E414C] shadow-sm',
                disabled: false,
                action: cancelJoinEvent,
                icon: ClockIcon,
                showAbsent: false
            }
        }
        if (participant.has_checked_in) {
            return {
                text: 'Đã check-in',
                color: 'white',
                shadowClass: 'border border-[#DCDEE6] bg-gray-100 text-[#838799] shadow-sm cursor-not-allowed',
                disabled: true,
                action: () => { },
                icon: CheckIcon,
                showAbsent: false
            }
        }
        // Joined but not attended -> Show Check-in
        return {
            text: 'Check-in ngay',
            color: 'primary',
            shadowClass: 'shadow-lg shadow-blue-100',
            disabled: false,
            action: handleSelfCheckInActivity,
            icon: CheckIcon,
            showAbsent: !isOwner.value && !participant.is_absent && !isFinished.value
        }
    }

    if (isCompleted) {
        return {
            text: 'Đã xong',
            color: 'white',
            shadowClass: 'border border-[#DCDEE6] bg-gray-100 text-[#838799] shadow-sm cursor-not-allowed',
            disabled: true,
            action: () => { },
            icon: CheckIcon,
            showAbsent: false
        }
    }

    // Default state: Register Now
    return {
        text: 'Đăng ký ngay',
        color: 'danger',
        shadowClass: 'shadow-lg shadow-red-100',
        disabled: false,
        action: registerEvent,
        icon: RegistrationIcon,
        showAbsent: false
    }
})

const joinActivityRequest = async () => {
    try {
        await ClubService.joinActivityRequest(clubId, activityId.value)
        toast.success('Đã gửi yêu cầu tham gia thành công')
        await Promise.all([
            getActivityDetail(),
            getListJoinActivityRequest()
        ])
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể gửi yêu cầu tham gia')
    }
}

const updateEvent = () => {
    router.push({name: 'club-activity-edit', params: {id: clubId, activityId: activityId.value}})
}

const goToFundPage = () => {
    router.push({
        name: 'club-fund',
        params: { id: clubId },
        query: { collectionId: activity.value.fund_collection_id }
    })
}

const handleOpenFinalizeBill = () => {
    if (activity.value.fee_split_type === 'fund') {
        showCreateExpenseModal.value = true
    } else {
        showCreateFundModal.value = true
    }
}

const handleSubmitFundRevenue = async (data) => {
    const formData = new FormData();
    formData.append('title', data.title);
    formData.append('description', data.description);
    formData.append('target_amount', data.target_amount);
    formData.append('amount_per_member', data.amount_per_member);
    formData.append('start_date', data.start_date);
    formData.append('deadline', data.deadline);
    formData.append('end_date', data.end_date);
    formData.append('activity_id', activityId.value);

    // Sử dụng included_in_club_fund từ modal (user đã chọn), không dùng từ activity
    formData.append('included_in_club_fund', data.included_in_club_fund ?? 1);

    if (Array.isArray(data.member_ids)) {
        data.member_ids.forEach(id => {
            formData.append('member_ids[]', id);
        });
    }

    // Gửi QR: ưu tiên file mới, nếu không có thì giữ QR cũ từ activity
    if (data.qr_image) {
        formData.append('qr_image', data.qr_image);
    } else if (data.qr_code_url) {
        // Nếu có QR cũ từ activity nhưng user không upload mới, gửi URL cũ
        formData.append('qr_code_url', data.qr_code_url);
    }

    try {
        const response = await ClubService.createdFundRevenue(clubId, formData)
        toast.success(response.message || 'Tạo khoản thu thành công')
        showCreateFundModal.value = false
        await getActivityDetail()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi tạo khoản thu')
    }
}

const handleSubmitFundExpense = async (data) => {
    const payload = {
        ...data,
        activity_id: activityId.value
    }

    try {
        const response = await ClubService.createFundExpenses(clubId, payload)
        toast.success(response.message || 'Tạo khoản chi thành công')
        showCreateExpenseModal.value = false
        await getActivityDetail()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi tạo khoản chi')
    }
}

const openPromotionModal = () => {
  isPromotionModalOpen.value = true
}

const shareEvent = () => {
   navigator.share({
        title: activity.value.title,
        text: activity.value.description,
        url: pageUrl.value
    })
    .then(() => {
        toast.info('Đã chia sẻ sự kiện')
    })
    .catch((error) => {
        console.error('Error sharing:', error)
        toast.error('Không thể chia sẻ sự kiện')
    })
}

const cancelEvent = () => {
    showCancelModal.value = true
}

const confirmCancelEvent = async (data) => {
    if (isCancelling.value) return
    isCancelling.value = true
    try {
        if (data.cancel_series && activity.value.recurrence_series_id) {
            await ClubService.cancelRecurrenceSeries(clubId, activityId.value, {
                cancellation_reason: data.cancellation_reason,
                cancel_transactions: data.cancel_transactions
            })
        } else {
            await ClubService.cancelActivity(clubId, activityId.value, data)
        }
        showCancelModal.value = false
        await router.push({name: 'club-detail', params: {id: clubId}})
        toast.success(data.cancel_series ? 'Đã hủy toàn bộ chuỗi sự kiện' : 'Đã hủy sự kiện')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể huỷ sự kiện')
    } finally {
        isCancelling.value = false
    }
}

const copyLink = async () => {
    if (pageUrl.value) {
        try {
            await navigator.clipboard.writeText(pageUrl.value)
            toast.success('Đã sao chép đường link')
        } catch (err) {
            toast.error('Không thể sao chép link')
        }
    }
}

const downloadQR = async () => {
  if (!qrcodeContainer.value) return;

  try {
    const canvas = await html2canvas(qrcodeContainer.value, {
      scale: 2,
      backgroundColor: '#ffffff',
      useCORS: true
    });

    triggerDownload(canvas);
  } catch (error) {
    console.error('Lỗi khi tạo ảnh QR:', error);
    toast.error('Có lỗi xảy ra khi lưu mã QR');
  }
}

const triggerDownload = (canvas) => {
    const url = canvas.toDataURL('image/png');
    const link = document.createElement('a');
    link.href = url;
    link.download = `QR_${activity.value.title || 'event'}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

const formatDate = (date) => {
    if (!date) return ''
    const d = dayjs(date)
    const days = ['Chủ Nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7']
    return `${days[d.day()]}, ${d.format('DD/MM/YYYY')}`
}

const formatTimeRange = (start, end) => {
    if (!start || !end) return '18:00 - 20:00'
    return `${dayjs(start).format('HH:mm')} - ${dayjs(end).format('HH:mm')}`
}

const goToProfile = (id) => {
    router.push({ name: 'profile', params: { id } });
};

const handleSelfCheckInActivity = async () => {
    try {
        await ClubService.selfCheckinActivity(clubId, activityId.value)
        toast.success('Đã check-in thành công')
        await getActivityDetail()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể check-in')
    }
}

const handleSelfAbsentActivity = async () => {
    try {
        await ClubService.selfAbsentActivity(clubId, activityId.value)
        toast.success('Đã báo vắng thành công')
        await getActivityDetail()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể báo vắng')
    }
}

const getListJoinActivityRequest = async () => {
    try {
        const response = await ClubService.getListJoinActivityRequest(clubId, activityId.value, {
          status: 'pending'
        })
        joinActivityRequests.value = response.data?.participants || []
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể lấy danh sách yêu cầu tham gia')
    }
}

const approveJoinActivityRequest = async (userId) => {
    try {
        await ClubService.approveJoinActivityRequest(clubId, activityId.value, userId)
        toast.success('Đã duyệt yêu cầu tham gia')
        getListJoinActivityRequest()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể duyệt yêu cầu tham gia')
    }
}

const rejectJoinActivityRequest = async (userId) => {
    try {
        await ClubService.rejectJoinActivityRequest(clubId, activityId.value, userId)
        toast.success('Đã từ chối yêu cầu tham gia')
        getListJoinActivityRequest()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể từ chối yêu cầu tham gia')
    }
}

onMounted(async () => {
    if (clubId && activityId.value) {
        isLoading.value = true
        try {
            // First fetch initial info
            await Promise.all([
                getActivityDetail(),
                getClubDetail(),
                getListJoinActivityRequest()
            ])
        } finally {
            isLoading.value = false
        }
    }
})
</script>

<style scoped>
.grid-rows-\[1fr\] {
  grid-template-rows: 1fr;
}
.grid-rows-\[0fr\] {
  grid-template-rows: 0fr;
}
</style>
