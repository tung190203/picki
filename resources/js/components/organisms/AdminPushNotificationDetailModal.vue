<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="modelValue"
      class="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-4 bg-black/40 backdrop-blur-sm"
      @click.self="close"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl relative z-[10000] overflow-hidden animate-in fade-in zoom-in duration-300 max-h-[95vh] sm:max-h-[85vh] flex flex-col">
        <!-- Header -->
        <div class="p-4 sm:p-6 pb-3 sm:pb-4 border-b flex items-start justify-between sticky top-0 bg-white z-10 flex-shrink-0">
          <div class="flex-1 pr-4">
            <div class="flex items-center gap-2 mb-1">
              <span
                v-if="campaign?.status"
                class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                :class="statusBadgeClass(campaign.status)"
              >
                {{ campaign.status_label || campaign.status }}
              </span>
              <span class="text-xs text-gray-500">
                {{ campaign?.created_at ? formatDate(campaign.created_at) : '' }}
              </span>
            </div>
            <h3 class="text-xl font-bold text-gray-800">
              {{ campaign?.title || 'Chi tiết thông báo' }}
            </h3>
          </div>
          <button @click="close" class="text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0">
            <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-5">
          <!-- Loading -->
          <div v-if="isLoading" class="flex items-center justify-center py-12">
            <div class="w-8 h-8 border-4 border-[#D72D36] border-t-transparent rounded-full animate-spin"></div>
          </div>

          <template v-else-if="campaign">
            <!-- Content -->
            <section>
              <h4 class="text-sm font-semibold text-gray-500 mb-2">Nội dung</h4>
              <p class="text-gray-800 whitespace-pre-wrap">{{ campaign.content }}</p>
            </section>

            <!-- Image -->
            <section v-if="campaign.image_url">
              <h4 class="text-sm font-semibold text-gray-500 mb-2">Hình ảnh</h4>
              <img :src="campaign.image_url" alt="Notification image" class="max-h-48 rounded-lg border" />
            </section>

            <!-- Recipient -->
            <section>
              <h4 class="text-sm font-semibold text-gray-500 mb-2">Đối tượng</h4>
              <div class="bg-gray-50 rounded-lg p-3">
                <p class="font-medium text-gray-800">{{ campaign.recipient_label || '-' }}</p>
                <p v-if="campaign.warnings && campaign.warnings.length" class="text-xs text-amber-700 mt-2">
                  <span v-for="(w, idx) in campaign.warnings" :key="idx">• {{ w }}</span>
                </p>
              </div>
            </section>

            <!-- Send Type -->
            <section class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <h4 class="text-sm font-semibold text-gray-500 mb-1">Kiểu gửi</h4>
                <p class="text-gray-800">
                  {{ campaign.send_type === 'IMMEDIATE' ? 'Gửi ngay' : 'Hẹn giờ' }}
                </p>
                <p v-if="campaign.scheduled_at" class="text-xs text-gray-500 mt-1">
                  Hẹn: {{ formatDate(campaign.scheduled_at) }}
                </p>
                <p v-if="campaign.sent_at" class="text-xs text-gray-500 mt-1">
                  Đã gửi: {{ formatDate(campaign.sent_at) }}
                </p>
              </div>
              <div>
                <h4 class="text-sm font-semibold text-gray-500 mb-1">Người tạo</h4>
                <p class="text-gray-800">{{ campaign.creator_name || '-' }}</p>
              </div>
            </section>

            <!-- Stats + Progress Bar -->
            <section v-if="campaign.actual_recipient_count !== null">
              <h4 class="text-sm font-semibold text-gray-500 mb-2">Tiến độ gửi</h4>
              <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                <div class="grid grid-cols-3 gap-2 text-center">
                  <div>
                    <p class="text-xs text-gray-500">Ước tính</p>
                    <p class="text-lg font-bold text-gray-800">{{ campaign.estimated_recipient_count ?? 0 }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500">Thực gửi</p>
                    <p class="text-lg font-bold text-gray-800">{{ campaign.actual_recipient_count ?? 0 }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500">Tỉ lệ thành công</p>
                    <p class="text-lg font-bold" :class="successRateColor">
                      {{ campaign.success_rate !== null ? `${campaign.success_rate}%` : '-' }}
                    </p>
                  </div>
                </div>

                <!-- Progress bar -->
                <div>
                  <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span>Thành công: {{ campaign.success_count ?? 0 }}</span>
                    <span>Thất bại: {{ campaign.failure_count ?? 0 }}</span>
                  </div>
                  <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
                    <div
                      class="h-full transition-all"
                      :class="successBarColor"
                      :style="{ width: `${campaign.success_rate || 0}%` }"
                    ></div>
                  </div>
                </div>
              </div>
            </section>

            <!-- Failed Users -->
            <section v-if="campaign.failed_users && campaign.failed_users.length">
              <h4 class="text-sm font-semibold text-gray-500 mb-2">
                User chưa nhận được thành công ({{ campaign.failed_users.length }})
              </h4>
              <div class="bg-red-50 rounded-lg p-3 max-h-48 overflow-y-auto">
                <ul class="space-y-1.5 text-sm">
                  <li v-for="user in campaign.failed_users" :key="user.id" class="flex items-center gap-2">
                    <span class="w-6 h-6 bg-red-100 rounded-full flex items-center justify-center text-xs font-semibold text-red-600">
                      {{ user.id }}
                    </span>
                    <span class="text-gray-700">{{ user.name || `User #${user.id}` }}</span>
                  </li>
                </ul>
              </div>
            </section>

            <!-- Error -->
            <section v-if="campaign.error_message">
              <h4 class="text-sm font-semibold text-gray-500 mb-2">Lỗi</h4>
              <p class="bg-red-50 text-red-700 p-3 rounded-lg text-sm">{{ campaign.error_message }}</p>
            </section>
          </template>

          <!-- Empty state -->
          <div v-else class="text-center py-12 text-gray-500">
            Không có dữ liệu
          </div>
        </div>

        <!-- Footer -->
        <div class="p-4 sm:p-6 border-t flex justify-end flex-shrink-0">
          <button
            @click="close"
            class="w-full sm:w-auto px-5 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  campaign: {
    type: Object,
    default: null
  },
  isLoading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue'])

const close = () => emit('update:modelValue', false)

const formatDate = (iso) => {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString('vi-VN', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    })
  } catch {
    return iso
  }
}

const statusBadgeClass = (status) => {
  const map = {
    SENT: 'bg-green-100 text-green-700',
    PROCESSING: 'bg-yellow-100 text-yellow-700',
    SCHEDULED: 'bg-blue-100 text-blue-700',
    FAILED: 'bg-red-100 text-red-700',
    PARTIAL: 'bg-orange-100 text-orange-700',
    DRAFT: 'bg-gray-100 text-gray-700',
    CANCELLED: 'bg-gray-200 text-gray-600'
  }
  return map[status] || 'bg-gray-100 text-gray-700'
}

const successRateColor = computed(() => {
  const rate = props.campaign?.success_rate
  if (rate === null || rate === undefined) return 'text-gray-400'
  if (rate >= 95) return 'text-green-600'
  if (rate >= 80) return 'text-yellow-600'
  return 'text-red-600'
})

const successBarColor = computed(() => {
  const rate = props.campaign?.success_rate
  if (rate === null || rate === undefined) return 'bg-gray-300'
  if (rate >= 95) return 'bg-green-500'
  if (rate >= 80) return 'bg-yellow-500'
  return 'bg-red-500'
})
</script>
