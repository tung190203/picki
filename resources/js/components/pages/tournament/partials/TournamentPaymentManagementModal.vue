<template>
  <Transition name="fade">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
      @click.self="closeModal"
    >
      <Transition name="scale">
        <div
          v-if="isOpen"
          class="bg-[#F8F9FD] rounded-2xl w-full max-w-[960px] h-[90vh] max-h-[760px] transition-all duration-300 flex flex-col relative shadow-2xl overflow-hidden"
        >
          <!-- Header -->
          <div class="p-6 px-8 flex items-center justify-between border-b border-gray-100 bg-white flex-shrink-0">
            <div>
              <h2 class="text-xl font-bold text-[#2D3139]">
                Quản lý thanh toán giải đấu
              </h2>
              <p class="text-xs text-[#6B6F80] mt-1">
                Theo dõi trạng thái thanh toán của từng người tham gia
              </p>
            </div>
            <button
              @click="closeModal"
              class="text-gray-400 hover:text-gray-600 transition-colors"
            >
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 min-h-0 overflow-y-auto p-5 space-y-4">
            <!-- Payment config & summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Config -->
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
                <p class="text-xs text-[#10B981] font-medium" v-if="paymentConfig.auto_split_fee">
                  ✓ Chia tự động theo số người
                </p>
                <p class="text-xs text-[#6B6F80]" v-if="paymentConfig.fee_description">
                  {{ paymentConfig.fee_description }}
                </p>
              </div>

              <!-- Summary -->
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

              <!-- Status -->
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
                <div class="mt-3 space-y-2">
                  <p class="text-[11px] text-[#6B6F80]">
                    <span class="text-[#10B981]">✓</span> Chủ giải & guest bảo lãnh bởi chủ giải: tự động xác nhận
                  </p>
                  <button
                    type="button"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-full text-xs font-semibold bg-[#FBEAEB] text-[#D72D36] hover:bg-[#F7D5D7] transition w-full"
                    @click="handleRemindAll"
                  >
                    Nhắc tất cả chưa thanh toán
                  </button>
                </div>
              </div>
            </div>

            <!-- Tabs -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
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

              <!-- Tab: Loading -->
              <div v-if="loading" class="py-10 text-center text-sm text-gray-400">
                Đang tải dữ liệu...
              </div>

              <!-- Tab: Empty -->
              <div v-else-if="currentPayments.length === 0" class="py-10 text-center text-sm text-gray-400">
                {{ emptyText }}
              </div>

              <!-- Tab: Payment list -->
              <div v-else class="divide-y divide-gray-50 max-h-[420px] overflow-y-auto">
                <!-- Pending -->
                <div v-if="activeTab === 'pending'">
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

                <!-- Awaiting confirmation -->
                <div v-else-if="activeTab === 'awaiting'">
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
                          <!-- Receipt preview -->
                          <div v-if="payment.receipt_image" class="mt-1">
                            <button
                              class="text-[11px] text-[#4392E0] hover:underline"
                              @click="showReceipt(payment.receipt_image)"
                            >
                              Xem biên nhận
                            </button>
                          </div>
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

                <!-- Confirmed -->
                <div v-else-if="activeTab === 'confirmed'">
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
          </div>

          <!-- Reject Modal -->
          <div v-if="rejectModalOpen" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[10001] p-4" @click.self="rejectModalOpen = false">
            <div class="bg-white rounded-2xl shadow-lg p-6 w-full max-w-md">
              <h3 class="font-semibold text-gray-900 mb-4">Từ chối thanh toán</h3>
              <textarea
                v-model="rejectReason"
                rows="3"
                placeholder="Lý do từ chối..."
                class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm resize-none mb-4"
              ></textarea>
              <div class="flex gap-2 justify-end">
                <button
                  @click="rejectModalOpen = false"
                  class="px-4 py-2 border border-gray-200 rounded-full text-sm text-gray-600 hover:bg-gray-50 transition"
                >
                  Hủy
                </button>
                <button
                  @click="confirmReject"
                  class="px-4 py-2 bg-[#D72D36] text-white rounded-full text-sm font-semibold hover:bg-[#b91c1c] transition"
                >
                  Xác nhận từ chối
                </button>
              </div>
            </div>
          </div>

          <!-- Receipt Modal -->
          <div v-if="receiptModalOpen" class="fixed inset-0 bg-black/70 flex items-center justify-center z-[10001] p-4" @click.self="receiptModalOpen = false">
            <div class="bg-white rounded-lg shadow-lg p-4 max-w-lg w-full">
              <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-gray-900">Biên nhận thanh toán</h3>
                <button @click="receiptModalOpen = false" class="text-gray-500 hover:text-gray-700">
                  <XMarkIcon class="w-5 h-5" />
                </button>
              </div>
              <img :src="currentReceiptUrl" alt="Biên nhận thanh toán" class="w-full object-contain max-h-[70vh] rounded" />
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </Transition>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { toast } from 'vue3-toastify'
import dayjs from 'dayjs'
import {
  getTournamentPayments,
  confirmTournamentPayment,
  rejectTournamentPayment,
  remindTournamentUser,
  remindAllTournamentPayments,
} from '@/service/tournamentPayment.js'
import { formatCurrency } from '@/composables/formatCurrency.js'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  tournamentId: {
    type: [String, Number],
    required: true,
  },
})

const emit = defineEmits(['update:isOpen'])

const loading = ref(false)
const paymentConfig = ref({
  has_fee: false,
  auto_split_fee: false,
  fee_amount: 0,
  fee_per_person: 0,
  fee_description: '',
  qr_code_url: null,
})
const summary = ref({
  total_participants: 0,
  total_expected: 0,
  total_collected: 0,
  total_pending: 0,
  total_awaiting_confirmation: 0,
  total_confirmed: 0,
})
const pendingPayments = ref([])
const awaitingPayments = ref([])
const confirmedPayments = ref([])

const activeTab = ref('pending')

const tabs = computed(() => [
  {
    key: 'pending',
    label: `Chưa thanh toán (${summary.value.total_pending || 0})`,
  },
  {
    key: 'awaiting',
    label: `Chờ duyệt (${summary.value.total_awaiting_confirmation || 0})`,
  },
  {
    key: 'confirmed',
    label: `Đã xác nhận (${summary.value.total_confirmed || 0})`,
  },
])

const currentPayments = computed(() => {
  if (activeTab.value === 'pending') return pendingPayments.value
  if (activeTab.value === 'awaiting') return awaitingPayments.value
  return confirmedPayments.value
})

const emptyText = computed(() => {
  if (activeTab.value === 'pending') return 'Không có thành viên nào ở trạng thái chờ thanh toán.'
  if (activeTab.value === 'awaiting') return 'Chưa có thanh toán nào chờ duyệt.'
  return 'Chưa có thanh toán nào được xác nhận.'
})

const closeModal = () => {
  emit('update:isOpen', false)
}

const formatTime = (value) => {
  if (!value) return ''
  return dayjs(value).format('DD/MM HH:mm')
}

const fetchPayments = async () => {
  if (!props.tournamentId) return
  try {
    loading.value = true
    const data = await getTournamentPayments(props.tournamentId)

    if (data?.payment_config) {
      paymentConfig.value = data.payment_config
    } else {
      paymentConfig.value = {
        has_fee: data?.tournament?.has_fee ?? false,
        auto_split_fee: data?.tournament?.auto_split_fee ?? false,
        fee_amount: data?.tournament?.fee_amount || 0,
        fee_per_person: data?.tournament?.fee_per_person || 0,
        fee_description: data?.tournament?.fee_description || '',
        qr_code_url: data?.tournament?.qr_code_url || null,
      }
    }

    if (data?.summary) {
      summary.value = data.summary
    }

    // Handle both new API (nested) and old API (flat)
    if (data?.pending_payments !== undefined) {
      pendingPayments.value = data.pending_payments?.data || data.pending_payments || []
      awaitingPayments.value = data.awaiting_confirmation_payments?.data || data.awaiting_confirmation_payments || []
      confirmedPayments.value = data.confirmed_payments?.data || data.confirmed_payments || []
    } else {
      const allPayments = data?.payments || []
      pendingPayments.value = allPayments.filter(p => p.status === 'pending')
      awaitingPayments.value = allPayments.filter(p => p.status === 'paid')
      confirmedPayments.value = allPayments.filter(p => p.status === 'confirmed')
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể tải thông tin thanh toán')
  } finally {
    loading.value = false
  }
}

const handleConfirm = async (payment) => {
  try {
    await confirmTournamentPayment(props.tournamentId, payment.id)
    toast.success('Đã xác nhận thanh toán')
    await fetchPayments()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể xác nhận thanh toán')
  }
}

// Reject
const rejectModalOpen = ref(false)
const rejectPayment = ref(null)
const rejectReason = ref('')

const openRejectModal = (payment) => {
  rejectPayment.value = payment
  rejectReason.value = ''
  rejectModalOpen.value = true
}

const confirmReject = async () => {
  if (rejectReason.value.trim() === '') {
    toast.error('Vui lòng nhập lý do từ chối')
    return
  }
  try {
    await rejectTournamentPayment(props.tournamentId, rejectPayment.value.id, rejectReason.value)
    toast.success('Đã từ chối thanh toán')
    rejectModalOpen.value = false
    await fetchPayments()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể từ chối thanh toán')
  }
}

const handleRemind = async (payment) => {
  try {
    await remindTournamentUser(props.tournamentId, payment.user_id)
    toast.success('Đã gửi nhắc thanh toán')
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể gửi nhắc thanh toán')
  }
}

const handleRemindAll = async () => {
  try {
    await remindAllTournamentPayments(props.tournamentId)
    toast.success('Đã gửi nhắc thanh toán cho tất cả thành viên chưa thanh toán')
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể gửi nhắc thanh toán')
  }
}

// Receipt viewer
const receiptModalOpen = ref(false)
const currentReceiptUrl = ref('')

const showReceipt = (url) => {
  currentReceiptUrl.value = url
  receiptModalOpen.value = true
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      fetchPayments()
    }
  }
)

onMounted(() => {
  if (props.isOpen) {
    fetchPayments()
  }
})
</script>

<style scoped>
.scale-enter-active,
.scale-leave-active {
  transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.scale-enter-from,
.scale-leave-to {
  opacity: 0;
  transform: scale(0.9) translateY(20px);
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
