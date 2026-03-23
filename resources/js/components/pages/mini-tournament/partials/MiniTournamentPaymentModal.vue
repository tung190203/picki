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
                {{ paymentConfig.auto_split_fee ? 'Quản lý thanh toán' : 'Thanh toán kèo đấu' }}
              </h2>
              <p class="text-xs text-[#6B6F80] mt-1">
                {{ paymentConfig.auto_split_fee ? 'Theo dõi trạng thái thanh toán của từng người (chia tự động)' : 'Theo dõi trạng thái thanh toán từng người tham gia kèo' }}
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
              <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-3">
                <div v-if="paymentConfig.qr_code_url" class="w-full flex justify-center">
                  <div class="w-28 h-28 bg-gray-50 border border-dashed border-gray-200 rounded-xl flex items-center justify-center overflow-hidden">
                    <img
                      :src="paymentConfig.qr_code_url"
                      alt="QR thanh toán kèo đấu"
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
                <p class="text-sm text-[#3E414C]" v-if="paymentConfig.auto_split_fee">
                  Mỗi người: <span class="font-semibold text-[#D72D36]">{{ formatCurrency(paymentConfig.fee_per_person) }}đ</span>
                </p>
                <p class="text-xs text-[#10B981] font-medium" v-if="paymentConfig.auto_split_fee">
                  ✓ Chia tự động theo số người
                </p>
                <p class="text-xs text-[#6B6F80]" v-if="paymentConfig.fee_description">
                  {{ paymentConfig.fee_description }}
                </p>
              </div>

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
                  <p class="text-[11px] text-[#6B6F80]" v-if="paymentConfig.auto_split_fee">
                    <span class="text-[#10B981]">✓</span> Chủ kèo & guest bảo lãnh bởi chủ kèo: tự động xác nhận
                  </p>
                  <button
                    v-if="canManage"
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
                            Bảo lãnh: {{ payment.participant?.guarantor?.full_name }}
                          </p>
                        </template>
                      </div>
                    </div>
                    <button
                      v-if="canManage"
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
                    v-if="awaitingConfirmationPayments.length === 0"
                    class="py-10 text-center text-sm text-gray-400"
                  >
                    Chưa có thanh toán nào chờ duyệt.
                  </div>
                  <div
                    v-for="payment in awaitingConfirmationPayments"
                    :key="payment.id"
                    class="p-4 hover:bg-gray-50 transition"
                  >
                    <!-- Main payment info -->
                    <div class="flex items-center justify-between mb-2">
                      <div class="flex items-center gap-4">
                        <!-- Guest avatar: initials badge -->
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
                          <!-- Guest detail when is_guest -->
                          <template v-if="payment.is_guest">
                            <p class="text-[11px] text-[#B45309] mt-0.5">
                              SĐT: {{ payment.participant?.guest_phone }}
                              <span v-if="payment.participant?.guarantor" class="ml-2">
                                • Bảo lãnh: {{ payment.participant?.guarantor?.full_name }}
                              </span>
                            </p>
                          </template>
                        </div>
                      </div>
                      <div class="flex items-center gap-2" v-if="canManage">
                        <button
                          type="button"
                          class="px-3 py-1.5 rounded-full text-xs font-semibold bg-white text-[#D72D36] border border-[#D72D36] hover:bg-red-50 transition"
                          @click="handleReject(payment)"
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
                            Bảo lãnh: {{ payment.participant?.guarantor?.full_name }}
                          </p>
                        </template>
                      </div>
                    </div>
                    <span
                      class="px-3 py-1 rounded-full text-[10px] font-semibold bg-[#10B981]/10 text-[#10B981]"
                    >
                      ĐÃ XÁC NHẬN
                    </span>
                  </div>
                </div>
              </div>
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
  getMiniTournamentPayments,
  confirmMiniTournamentPayment,
  rejectMiniTournamentPayment,
  remindMiniTournamentPayment,
  remindAllMiniTournamentPayments,
} from '@/service/miniTournament'
import { formatCurrency } from '@/composables/formatCurrency'

const props = defineProps({
  isOpen: Boolean,
  miniId: {
    type: [String, Number],
    required: true,
  },
  canManage: {
    type: Boolean,
    default: false,
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
  payment_account_id: null,
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
const awaitingConfirmationPayments = ref([])
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

const closeModal = () => {
  emit('update:isOpen', false)
}

const formatTime = (value) => {
  if (!value) return ''
  return dayjs(value).format('DD/MM HH:mm')
}

const fetchPayments = async () => {
  if (!props.miniId) return
  try {
    loading.value = true
    const data = await getMiniTournamentPayments(props.miniId)
    paymentConfig.value = data.payment_config || paymentConfig.value
    summary.value = data.summary || summary.value
    pendingPayments.value = data.pending_payments?.data || data.pending_payments || []
    awaitingConfirmationPayments.value =
      data.awaiting_confirmation_payments?.data || data.awaiting_confirmation_payments || []
    confirmedPayments.value = data.confirmed_payments?.data || data.confirmed_payments || []
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể tải thông tin thanh toán kèo đấu')
  } finally {
    loading.value = false
  }
}

const handleConfirm = async (payment) => {
  try {
    await confirmMiniTournamentPayment(props.miniId, payment.id)
    toast.success('Đã xác nhận thanh toán')
    await fetchPayments()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể xác nhận thanh toán')
  }
}

const handleReject = async (payment) => {
  try {
    await rejectMiniTournamentPayment(props.miniId, payment.id)
    toast.success('Đã từ chối thanh toán')
    await fetchPayments()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể từ chối thanh toán')
  }
}

const handleRemind = async (payment) => {
  try {
    await remindMiniTournamentPayment(props.miniId, payment.participant_id)
    toast.success('Đã gửi nhắc thanh toán')
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể gửi nhắc thanh toán')
  }
}

const handleRemindAll = async () => {
  try {
    await remindAllMiniTournamentPayments(props.miniId)
    toast.success('Đã gửi nhắc thanh toán cho tất cả thành viên chưa thanh toán')
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể gửi nhắc thanh toán')
  }
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

