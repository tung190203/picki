<template>
  <div class="p-4 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <button @click="$router.back()" class="text-gray-500 hover:text-gray-700 mb-2 flex items-center gap-1 text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
          Quay lại
        </button>
        <h1 class="text-2xl font-bold text-gray-900">
          {{ paymentConfig.auto_split_fee ? 'Quản lý thanh toán' : 'Thanh toán giải đấu' }}
        </h1>
        <p v-if="tournament" class="text-xs text-[#6B6F80] mt-1">
          {{ paymentConfig.auto_split_fee ? 'Theo dõi trạng thái thanh toán của từng người (chia tự động)' : 'Theo dõi trạng thái thanh toán từng người tham gia giải đấu' }}
        </p>
      </div>
      <button
        v-if="isAdmin"
        @click="handleRemindAll"
        :disabled="isLoading"
        class="inline-flex items-center justify-center px-4 py-2 rounded-full text-xs font-semibold bg-[#FBEAEB] text-[#D72D36] hover:bg-[#F7D5D7] transition disabled:opacity-50">
        Nhắc tất cả chưa thanh toán
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="text-center py-16 text-gray-400">
      Đang tải...
    </div>

    <div v-else class="space-y-4">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Payment Config Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-3">
          <div v-if="paymentConfig.qr_code_url" class="w-full flex justify-center">
            <div class="w-28 h-28 bg-gray-50 border border-dashed border-gray-200 rounded-xl flex items-center justify-center overflow-hidden">
              <img
                :src="paymentConfig.qr_code_url"
                alt="QR thanh toán"
                class="w-full h-full object-contain mix-blend-multiply"
              />
            </div>
          </div>
          <p class="text-xs font-semibold text-[#838799] uppercase tracking-wide">Cấu hình phí</p>
          <p class="text-sm text-[#3E414C]">
            {{ paymentConfig.has_fee ? 'Có thu phí tham gia' : 'Không thu phí' }}
          </p>
          <p class="text-sm text-[#3E414C]" v-if="paymentConfig.has_fee">
            Tổng phí: <span class="font-semibold">{{ formatCurrency(paymentConfig.fee_amount) }}đ</span>
          </p>
          <p class="text-sm text-[#3E414C]" v-if="paymentConfig.fee_per_person">
            Mỗi người: <span class="font-semibold text-[#D72D36]">{{ formatCurrency(paymentConfig.fee_per_person) }}đ</span>
          </p>
          <p class="text-xs text-[#6B6F80]" v-if="paymentConfig.fee_description">
            {{ paymentConfig.fee_description }}
          </p>
        </div>

        <!-- Overview Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-2">
          <p class="text-xs font-semibold text-[#838799] uppercase tracking-wide">Tổng quan</p>
          <p class="text-sm text-[#3E414C]">
            Tổng người tham gia:
            <span class="font-semibold">{{ summary.total_participants }}</span>
          </p>
          <p class="text-sm text-[#3E414C]" v-if="paymentConfig.has_fee">
            Dự kiến:
            <span class="font-semibold">{{ formatCurrency(summary.total_expected) }}đ</span>
          </p>
          <p class="text-sm text-[#00B377]">
            Đã thu:
            <span class="font-semibold">{{ formatCurrency(summary.total_collected) }}đ</span>
          </p>
        </div>

        <!-- Status Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex flex-col justify-between">
          <div>
            <p class="text-xs font-semibold text-[#838799] uppercase tracking-wide">Trạng thái</p>
            <p class="text-sm text-[#3E414C] mt-1">
              Đã xác nhận:
              <span class="font-semibold">{{ summary.total_confirmed }}</span>
            </p>
            <p class="text-sm text-[#F97316]">
              Chờ duyệt:
              <span class="font-semibold">{{ summary.total_awaiting_confirmation }}</span>
            </p>
            <p class="text-sm text-[#D72D36]">
              Chưa thanh toán:
              <span class="font-semibold">{{ summary.total_pending }}</span>
            </p>
          </div>
          <div class="mt-3">
            <p class="text-[11px] text-[#6B6F80]">
              <span class="text-[#10B981]">&#10003;</span> Chủ giải & guest bảo lãnh bởi chủ giải: tự động xác nhận
            </p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div v-if="isAdmin" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex border-b border-gray-50 p-2 bg-gray-50/50">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="activeTab = tab.key"
            :class="[
              'flex-1 py-3 px-4 text-xs md:text-sm font-bold transition-all relative',
              activeTab === tab.key ? 'text-[#D72D36]' : 'text-gray-400 hover:text-gray-600'
            ]"
          >
            {{ tab.label }}
            <div
              v-if="activeTab === tab.key"
              class="absolute bottom-0 left-8 right-8 h-0.5 bg-[#D72D36]"
            ></div>
          </button>
        </div>

        <div class="divide-y divide-gray-50 max-h-[420px] overflow-y-auto">
          <!-- Pending (chưa thanh toán) - Tab 1 -->
          <div v-if="activeTab === 'pending'">
            <div
              v-if="pendingPayments.length === 0"
              class="py-10 text-center text-sm text-gray-400"
            >
              Không có thành viên nào ở trạng thái chờ thanh toán.
            </div>
            <div
              v-for="payment in pendingPayments"
              :key="payment.id"
              class="flex items-center justify-between p-4 hover:bg-gray-50 transition"
            >
              <div class="flex items-center gap-4">
                <template v-if="payment.is_guest">
                  <div class="w-10 h-10 rounded-full bg-[#FDE68A] border border-[#F59E0B] flex items-center justify-center text-xs font-bold text-[#92400E] shrink-0">
                    G
                  </div>
                </template>
                <template v-else>
                  <img
                    :src="payment.user?.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(payment.user?.full_name || '')}`"
                    :alt="payment.user?.full_name || 'Avatar'"
                    class="w-10 h-10 rounded-full border border-gray-100"
                  />
                </template>
                <div>
                  <p class="font-semibold text-sm text-[#1F2937]">
                    <span v-if="payment.is_guest" class="mr-1 text-[10px] font-bold bg-[#FDE68A] text-[#92400E] px-1.5 py-0.5 rounded">Guest</span>
                    {{ payment.is_guest ? payment.participant?.guest_name : (payment.user?.full_name || 'Ẩn danh') }}
                  </p>
                  <p class="text-xs text-[#6B6F80] mt-0.5">
                    Trạng thái: {{ payment.status_text }}
                  </p>
                  <template v-if="payment.is_guest && payment.participant?.guarantor">
                    <p class="text-[11px] text-[#B45309]">
                      Bảo lãnh: {{ payment.participant?.guarantor?.full_name || payment.participant?.guarantor?.name }}
                    </p>
                  </template>
                </div>
              </div>
              <button
                type="button"
                class="px-4 py-1.5 rounded-full text-xs font-semibold bg-[#F6E4C8] text-[#E0A243] hover:bg-[#D48D3B] hover:text-white transition"
                @click="handleRemind(payment)"
              >
                Nhắc thanh toán
              </button>
            </div>
          </div>

          <!-- Awaiting confirmation - Tab 2 -->
          <div v-else-if="activeTab === 'awaiting'">
            <div
              v-if="awaitingPayments.length === 0"
              class="py-10 text-center text-sm text-gray-400"
            >
              Chưa có thanh toán nào chờ duyệt.
            </div>
            <div
              v-for="payment in awaitingPayments"
              :key="payment.id"
              class="p-4 hover:bg-gray-50 transition"
            >
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-4">
                  <template v-if="payment.is_guest">
                    <div class="w-10 h-10 rounded-full bg-[#FDE68A] border border-[#F59E0B] flex items-center justify-center text-xs font-bold text-[#92400E] shrink-0">
                      G
                    </div>
                  </template>
                  <template v-else>
                    <img
                      :src="payment.user?.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(payment.user?.full_name || '')}`"
                      :alt="payment.user?.full_name || 'Avatar'"
                      class="w-10 h-10 rounded-full border border-gray-100"
                    />
                  </template>
                  <div>
                    <p class="font-semibold text-sm text-[#1F2937]">
                      <span v-if="payment.is_guest" class="mr-1 text-[10px] font-bold bg-[#FDE68A] text-[#92400E] px-1.5 py-0.5 rounded">Guest</span>
                      {{ payment.is_guest ? payment.participant?.guest_name : (payment.user?.full_name || 'Ẩn danh') }}
                    </p>
                    <p class="text-xs text-[#6B6F80] mt-0.5">
                      Đã thanh toán:
                      <span class="font-semibold text-[#D72D36]">
                        {{ formatCurrency(payment.amount) }}đ
                      </span>
                      <span v-if="payment.paid_at"> • {{ formatTime(payment.paid_at) }}</span>
                    </p>
                    <template v-if="payment.is_guest">
                      <p class="text-[11px] text-[#B45309] mt-0.5">
                        SĐT: {{ payment.participant?.guest_phone }}
                        <span v-if="payment.participant?.guarantor" class="ml-2">
                          • Bảo lãnh: {{ payment.participant?.guarantor?.full_name || payment.participant?.guarantor?.name }}
                        </span>
                      </p>
                    </template>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold bg-white text-[#D72D36] border border-[#D72D36] hover:bg-red-50 transition"
                    @click="openRejectModal(payment)"
                  >
                    Không duyệt
                  </button>
                  <button
                    type="button"
                    class="px-4 py-1.5 rounded-full text-xs font-semibold bg-[#10B981] text-white hover:bg-[#059669] transition"
                    @click="handleConfirm(payment)"
                  >
                    Duyệt
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Confirmed - Tab 3 -->
          <div v-else-if="activeTab === 'confirmed'">
            <div
              v-if="confirmedPayments.length === 0"
              class="py-10 text-center text-sm text-gray-400"
            >
              Chưa có thanh toán nào được xác nhận.
            </div>
            <div
              v-for="payment in confirmedPayments"
              :key="payment.id"
              class="flex items-center justify-between p-4 hover:bg-gray-50 transition"
            >
              <div class="flex items-center gap-4">
                <template v-if="payment.is_guest">
                  <div class="w-10 h-10 rounded-full bg-[#FDE68A] border border-[#F59E0B] flex items-center justify-center text-xs font-bold text-[#92400E] shrink-0">
                    G
                  </div>
                </template>
                <template v-else>
                  <img
                    :src="payment.user?.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(payment.user?.full_name || '')}`"
                    :alt="payment.user?.full_name || 'Avatar'"
                    class="w-10 h-10 rounded-full border border-gray-100"
                  />
                </template>
                <div>
                  <p class="font-semibold text-sm text-[#1F2937]">
                    <span v-if="payment.is_guest" class="mr-1 text-[10px] font-bold bg-[#FDE68A] text-[#92400E] px-1.5 py-0.5 rounded">Guest</span>
                    {{ payment.is_guest ? payment.participant?.guest_name : (payment.user?.full_name || 'Ẩn danh') }}
                  </p>
                  <p class="text-xs text-[#6B6F80] mt-0.5">
                    Đã thu:
                    <span class="font-semibold text-[#10B981]">
                      {{ formatCurrency(payment.amount) }}đ
                    </span>
                    <span v-if="payment.confirmed_at"> • {{ formatTime(payment.confirmed_at) }}</span>
                  </p>
                  <template v-if="payment.is_guest && payment.participant?.guarantor">
                    <p class="text-[11px] text-[#B45309]">
                      Bảo lãnh: {{ payment.participant?.guarantor?.full_name || payment.participant?.guarantor?.name }}
                    </p>
                  </template>
                </div>
              </div>
              <span class="px-3 py-1 rounded-full text-[10px] font-semibold bg-[#10B981]/10 text-[#10B981]">
                ĐÃ XÁC NHẬN
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- User: My Payment Status -->
      <div v-else-if="myPayment" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Thông tin thanh toán của bạn</h2>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-gray-600">Phí tham gia</span>
            <span class="font-bold text-lg">{{ formatCurrency(myPayment.amount || paymentConfig.fee_per_person || paymentConfig.fee_amount) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-gray-600">Trạng thái</span>
            <span :class="statusClass(myPayment.status)" class="px-3 py-1 rounded-full text-sm font-medium">
              {{ myPayment.status_text || statusText(myPayment.status) }}
            </span>
          </div>
          <div v-if="myPayment.admin_note" class="bg-red-50 border border-red-200 rounded p-3 text-sm text-red-700">
            <strong>Lý do từ chối:</strong> {{ myPayment.admin_note }}
          </div>
          <div v-if="myPayment.receipt_image" class="mt-4">
            <p class="text-gray-600 text-sm mb-2">Biên nhận đã nộp:</p>
            <img :src="myPayment.receipt_image" class="w-32 h-32 object-cover rounded border" />
          </div>
          <div v-if="paymentConfig.qr_code_url" class="mt-4 text-center">
            <p class="text-gray-600 text-sm mb-2">Quét QR để thanh toán:</p>
            <img :src="paymentConfig.qr_code_url" class="w-40 h-40 object-contain border rounded mx-auto" />
          </div>
          <div v-if="paymentConfig.fee_description" class="bg-gray-50 rounded p-3 text-sm text-gray-600">
            {{ paymentConfig.fee_description }}
          </div>
        </div>
      </div>

      <!-- User: Submit payment form -->
      <div v-else-if="canSubmitPayment" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Nộp thanh toán</h2>
        <div class="bg-yellow-50 border border-yellow-200 rounded p-3 mb-4 text-sm text-yellow-800">
          <strong>Phí tham gia:</strong> {{ formatCurrency(paymentConfig.fee_per_person || paymentConfig.fee_amount) }}đ
        </div>
        <div v-if="paymentConfig.qr_code_url" class="mb-4 text-center">
          <p class="text-gray-600 text-sm mb-2">Quét QR để thanh toán:</p>
          <img :src="paymentConfig.qr_code_url" class="w-48 h-48 object-contain border rounded mx-auto" />
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Ảnh biên nhận (PNG, JPG, tối đa 5MB)</label>
            <input ref="receiptFileInput" type="file" accept="image/*" @change="onReceiptChange"
              class="w-full text-sm border rounded p-2" />
            <div v-if="receiptPreview" class="mt-2 relative inline-block">
              <img :src="receiptPreview" class="w-24 h-24 object-cover rounded border" />
              <button @click="removeReceipt" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs">×</button>
            </div>
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Ghi chú (tùy chọn)</label>
            <textarea v-model="paymentNote" rows="2" placeholder="VD: Đã chuyển khoản lúc..."
              class="w-full px-2 py-2 border rounded text-sm resize-none"></textarea>
          </div>
          <button @click="submitPayment"
            :disabled="isSubmitting"
            class="w-full py-3 bg-[#D72D36] text-white rounded-lg font-semibold hover:bg-red-700 disabled:opacity-50">
            {{ isSubmitting ? 'Đang gửi...' : 'Nộp thanh toán' }}
          </button>
        </div>
      </div>

      <!-- No financial management -->
      <div v-else-if="paymentConfig && !paymentConfig.has_fee" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
        <p class="text-gray-500">Giải đấu này không bật quản lý tài chính trong app.</p>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="rejectModalOpen" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="rejectModalOpen = false">
      <div class="bg-white rounded-2xl shadow-lg p-6 w-full max-w-md">
        <h3 class="font-semibold text-gray-900 mb-4">Từ chối thanh toán</h3>
        <textarea v-model="rejectReason" rows="3" placeholder="Lý do từ chối..."
          class="w-full px-2 py-2 border rounded text-sm resize-none mb-4"></textarea>
        <div class="flex gap-2 justify-end">
          <button @click="rejectModalOpen = false" class="px-4 py-2 border rounded text-gray-600 hover:bg-gray-50">Hủy</button>
          <button @click="confirmReject" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Xác nhận từ chối</button>
        </div>
      </div>
    </div>

    <!-- Receipt Modal -->
    <div v-if="receiptModalOpen" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4" @click.self="receiptModalOpen = false">
      <div class="bg-white rounded-lg shadow-lg p-4 max-w-lg w-full">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-semibold text-gray-900">Biên nhận</h3>
          <button @click="receiptModalOpen = false" class="text-gray-500 hover:text-gray-700">×</button>
        </div>
        <img :src="currentReceiptUrl" class="w-full object-contain max-h-[70vh]" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue3-toastify'
import dayjs from 'dayjs'
import * as tp from '@/service/tournamentPayment'
import { formatCurrency } from '@/composables/formatCurrency.js'

const route = useRoute()
const tournamentId = computed(() => route.params.id)

const isLoading = ref(true)
const isSubmitting = ref(false)

const tournament = ref(null)
const payments = ref([])
const summary = ref({
  total_participants: 0,
  total_expected: 0,
  total_collected: 0,
  total_pending: 0,
  total_awaiting_confirmation: 0,
  total_confirmed: 0,
  total_rejected: 0,
})
const paymentConfig = ref({
  has_fee: false,
  auto_split_fee: false,
  fee_amount: 0,
  fee_per_person: 0,
  fee_description: '',
  qr_code_url: null,
})
const myPayment = ref(null)
const isAdmin = ref(false)

const activeTab = ref('pending')

const tabs = computed(() => [
  { key: 'pending', label: `Chưa thanh toán (${summary.value.total_pending || 0})` },
  { key: 'awaiting', label: `Chờ duyệt (${summary.value.total_awaiting_confirmation || 0})` },
  { key: 'confirmed', label: `Đã xác nhận (${summary.value.total_confirmed || 0})` },
])

// Backwards-compatible payment lists
const pendingPayments = computed(() => {
  if (payments.value?.pending_payments !== undefined) {
    return payments.value.pending_payments || []
  }
  // Old API: filter from flat payments array
  return (payments.value.payments || []).filter(p => p.status === 'pending')
})

const awaitingPayments = computed(() => {
  if (payments.value?.awaiting_confirmation_payments !== undefined) {
    return payments.value.awaiting_confirmation_payments || []
  }
  return (payments.value.payments || []).filter(p => p.status === 'paid')
})

const confirmedPayments = computed(() => {
  if (payments.value?.confirmed_payments !== undefined) {
    return payments.value.confirmed_payments || []
  }
  return (payments.value.payments || []).filter(p => p.status === 'confirmed')
})

// Submit payment
const receiptFile = ref(null)
const receiptPreview = ref(null)
const receiptFileInput = ref(null)
const paymentNote = ref('')

// Reject modal
const rejectModalOpen = ref(false)
const rejectPayment = ref(null)
const rejectReason = ref('')

// Receipt modal
const receiptModalOpen = ref(false)
const currentReceiptUrl = ref('')

const canSubmitPayment = computed(() => {
  return paymentConfig.value?.has_fee && !myPayment.value
})

const statusText = (status) => ({
  pending: 'Chờ thanh toán',
  paid: 'Chờ xác nhận',
  confirmed: 'Đã xác nhận',
  rejected: 'Bị từ chối',
}[status] || status)

const statusClass = (status) => ({
  pending: 'bg-gray-100 text-gray-700',
  paid: 'bg-yellow-100 text-yellow-700',
  confirmed: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
}[status] || 'bg-gray-100 text-gray-700')

const formatTime = (value) => {
  if (!value) return ''
  return dayjs(value).format('DD/MM HH:mm')
}

const loadData = async () => {
  isLoading.value = true
  try {
    const paymentData = await tp.getTournamentPayments(tournamentId.value)
    payments.value = paymentData || {}
    if (paymentData?.payment_config) {
      paymentConfig.value = paymentData.payment_config
    } else {
      paymentConfig.value = {
        has_fee: paymentData?.tournament?.has_fee ?? false,
        auto_split_fee: paymentData?.tournament?.auto_split_fee ?? false,
        fee_amount: paymentData?.tournament?.fee_amount || 0,
        fee_per_person: paymentData?.tournament?.fee_per_person || 0,
        fee_description: paymentData?.tournament?.fee_description || '',
        qr_code_url: paymentData?.tournament?.qr_code_url || null,
      }
    }
    if (paymentData?.summary) {
      summary.value = paymentData.summary
    }
    if (paymentData?.tournament) {
      tournament.value = { id: paymentData.tournament.id, name: paymentData.tournament.name }
    }
    isAdmin.value = true

    const myData = await tp.getMyTournamentPayment(tournamentId.value)
    myPayment.value = myData?.payment || null
    if (myData?.payment_config) {
      paymentConfig.value = { ...paymentConfig.value, ...myData.payment_config }
    }
    if (!tournament.value && myData?.tournament) {
      tournament.value = { id: myData.tournament.id, name: myData.tournament.name }
    }
  } catch (e) {
    try {
      const myData = await tp.getMyTournamentPayment(tournamentId.value)
      myPayment.value = myData?.payment || null
      if (myData?.payment_config) {
        paymentConfig.value = myData.payment_config
      } else if (myData?.tournament) {
        paymentConfig.value = {
          has_fee: myData.tournament.has_fee ?? false,
          auto_split_fee: myData.tournament.auto_split_fee ?? false,
          fee_amount: myData.tournament.fee_amount || 0,
          fee_per_person: myData.tournament.fee_per_person || 0,
          fee_description: myData.tournament.fee_description || '',
          qr_code_url: myData.tournament.qr_code_url || null,
        }
      }
      tournament.value = { id: myData.tournament.id, name: myData.tournament.name }
    } catch (err) {
      toast.error('Không thể tải dữ liệu thanh toán')
      console.error('Error loading payments:', err)
    }
  } finally {
    isLoading.value = false
  }
}

const onReceiptChange = (e) => {
  const file = e.target.files[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) {
    toast.error('Ảnh tối đa 5MB')
    return
  }
  receiptFile.value = file
  receiptPreview.value = URL.createObjectURL(file)
}

const removeReceipt = () => {
  receiptFile.value = null
  receiptPreview.value = null
}

const submitPayment = async () => {
  isSubmitting.value = true
  try {
    const formData = new FormData()
    if (receiptFile.value) {
      formData.append('receipt_image', receiptFile.value)
    }
    if (paymentNote.value) {
      formData.append('note', paymentNote.value)
    }
    await tp.submitTournamentPayment(tournamentId.value, formData)
    toast.success('Nộp thanh toán thành công, chờ BTC xác nhận')
    await loadData()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Nộp thất bại')
  } finally {
    isSubmitting.value = false
  }
}

const handleConfirm = async (payment) => {
  try {
    await tp.confirmTournamentPayment(tournamentId.value, payment.id)
    toast.success('Đã xác nhận thanh toán')
    await loadData()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Xác nhận thất bại')
  }
}

const openRejectModal = (payment) => {
  rejectPayment.value = payment
  rejectReason.value = ''
  rejectModalOpen.value = true
}

const confirmReject = async () => {
  if (!rejectReason.value.trim()) {
    toast.error('Vui lòng nhập lý do từ chối')
    return
  }
  try {
    await tp.rejectTournamentPayment(tournamentId.value, rejectPayment.value.id, rejectReason.value)
    toast.success('Đã từ chối thanh toán')
    rejectModalOpen.value = false
    await loadData()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Từ chối thất bại')
  }
}

const handleRemind = async (payment) => {
  try {
    await tp.remindTournamentUser(tournamentId.value, payment.user_id)
    toast.success('Đã gửi nhắc thanh toán')
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Gửi nhắc thất bại')
  }
}

const handleRemindAll = async () => {
  try {
    await tp.remindAllTournamentPayments(tournamentId.value)
    toast.success(`Đã gửi nhắc thanh toán cho tất cả thành viên chưa thanh toán`)
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Gửi nhắc thất bại')
  }
}

const showReceipt = (url) => {
  currentReceiptUrl.value = url
  receiptModalOpen.value = true
}

onMounted(loadData)
</script>
