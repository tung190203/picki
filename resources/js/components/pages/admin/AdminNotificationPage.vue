<template>
  <div class="flex min-h-screen bg-[#f7f9fb] font-body text-slate-800">
    <!-- SideNavBar -->
    <AdminSidebar />

    <!-- Main Content Area -->
    <main class="flex-1 md:ml-64 min-h-screen bg-[#f7f9fb] pb-16">
      <AdminHeader />

      <div class="p-6 max-w-5xl mx-auto">
        <!-- Page Header -->
        <div class="mb-6">
          <h1 class="text-3xl font-bold text-gray-800">Quản lý thông báo Push</h1>
          <p class="text-gray-500 mt-1">Gửi và xem lịch sử thông báo đẩy đến người dùng</p>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 mb-6">
          <div class="flex border-b">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              @click="activeTab = tab.key"
              class="px-6 py-3 font-semibold text-sm transition-colors border-b-2 -mb-px"
              :class="activeTab === tab.key
                ? 'text-[#D72D36] border-[#D72D36]'
                : 'text-gray-500 border-transparent hover:text-gray-700'"
            >
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Tab: Create -->
        <div v-show="activeTab === 'create'">
      <div class="flex items-center justify-between mb-6">
        <div class="flex gap-3">
          <button
            @click="showTemplateModal = true"
            class="flex items-center gap-2 px-4 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
            </svg>
            Mẫu đã lưu
          </button>
        </div>
      </div>

      <!-- Notification Form -->
      <div class="bg-white rounded-2xl shadow-sm border p-6">
        <form @submit.prevent="createCampaign" class="space-y-6">
          <!-- Title -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Tiêu đề thông báo <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.title"
              type="text"
              maxlength="50"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
              placeholder="VD: Giải đấu mới sắp diễn ra"
              required
            />
            <p class="text-xs text-gray-400 mt-1">{{ form.title.length }}/50 ký tự</p>
          </div>

          <!-- Content -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Nội dung thông báo <span class="text-red-500">*</span>
            </label>
            <textarea
              v-model="form.content"
              maxlength="150"
              rows="3"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent resize-none"
              placeholder="VD: Giải đấu Picki Cup 2026 sẽ bắt đầu vào ngày mai. Đăng ký ngay!"
              required
            ></textarea>
            <p class="text-xs text-gray-400 mt-1">{{ form.content.length }}/150 ký tự</p>
          </div>

          <!-- Image Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Hình ảnh (tùy chọn)</label>
            <div
              class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-[#D72D36] transition-colors cursor-pointer"
              :class="{ 'border-[#D72D36] bg-red-50': isDragging }"
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
              @drop.prevent="handleDrop"
              @click="$refs.imageInput.click()"
            >
              <input
                ref="imageInput"
                type="file"
                accept="image/*"
                class="hidden"
                @change="handleImageSelect"
              />
              <div v-if="form.image" class="relative inline-block">
                <img :src="imagePreview" alt="Preview" class="max-h-32 mx-auto rounded-lg" />
                <button
                  type="button"
                  @click.stop="removeImage"
                  class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <div v-else>
                <svg class="w-10 h-10 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-gray-500 text-sm">Kéo thả hoặc click để tải ảnh lên</p>
                <p class="text-gray-400 text-xs mt-1">PNG, JPG, WEBP (tối đa 1MB)</p>
              </div>
            </div>
          </div>

          <!-- Action Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Loại hành động</label>
            <select
              v-model="form.action_type"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
            >
              <option value="NONE">Không có hành động</option>
              <option value="MATCH">Trận đấu</option>
              <option value="TOURNAMENT">Giải đấu</option>
              <option value="CLUB">Câu lạc bộ</option>
            </select>
          </div>

          <!-- Recipient Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Loại người nhận <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.recipient_type"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
              required
            >
              <option value="ALL">Tất cả người dùng</option>
              <option value="CLUB">Theo câu lạc bộ</option>
              <option value="ACTIVITY">Theo mức độ hoạt động</option>
              <option value="USERS">Theo danh sách người dùng</option>
            </select>
          </div>

          <!-- Recipient Config -->
          <div v-if="form.recipient_type === 'CLUB'">
            <label class="block text-sm font-medium text-gray-700 mb-1">ID Câu lạc bộ</label>
            <input
              v-model.number="form.recipient_config.club_id"
              type="number"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
              placeholder="Nhập ID câu lạc bộ"
            />
          </div>

          <div v-if="form.recipient_type === 'ACTIVITY'">
            <label class="block text-sm font-medium text-gray-700 mb-1">Mức độ hoạt động</label>
            <select
              v-model="form.recipient_config.level"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
            >
              <option value="">Chọn mức độ</option>
              <option value="HOT">HOT - Hoạt động cao</option>
              <option value="WARM">WARM - Hoạt động trung bình</option>
              <option value="COLD">COLD - Hoạt động thấp</option>
            </select>
          </div>

          <div v-if="form.recipient_type === 'USERS'">
            <label class="block text-sm font-medium text-gray-700 mb-1">Danh sách User IDs</label>
            <input
              :value="form.recipient_config.user_ids?.join(', ')"
              @input="e => form.recipient_config.user_ids = e.target.value.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id))"
              type="text"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
              placeholder="1, 2, 3, 4, 5"
            />
            <p class="text-xs text-gray-400 mt-1">Tối đa 1000 người dùng</p>
          </div>

          <!-- Send Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Kiểu gửi <span class="text-red-500">*</span>
            </label>
            <div class="flex gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="form.send_type"
                  type="radio"
                  value="IMMEDIATE"
                  class="w-4 h-4 text-[#D72D36] focus:ring-[#D72D36]"
                />
                <span class="text-gray-700">Gửi ngay</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="form.send_type"
                  type="radio"
                  value="SCHEDULED"
                  class="w-4 h-4 text-[#D72D36] focus:ring-[#D72D36]"
                />
                <span class="text-gray-700">Hẹn giờ gửi</span>
              </label>
            </div>
          </div>

          <!-- Scheduled Time -->
          <div v-if="form.send_type === 'SCHEDULED'">
            <label class="block text-sm font-medium text-gray-700 mb-1">Thời gian gửi</label>
            <input
              v-model="form.scheduled_at"
              type="datetime-local"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
              :min="minScheduleTime"
            />
          </div>

          <!-- Preview & Estimate -->
          <div v-if="form.title && form.content" class="bg-gray-50 rounded-xl p-4">
            <h4 class="font-semibold text-gray-700 mb-3">Xem trước</h4>
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg bg-[#D72D36] flex items-center justify-center flex-shrink-0">
                <span class="text-white text-lg">P</span>
              </div>
              <div>
                <p class="font-semibold text-gray-800">{{ form.title }}</p>
                <p class="text-sm text-gray-500">{{ form.content }}</p>
              </div>
            </div>
            <div class="mt-3 pt-3 border-t border-gray-200">
              <p class="text-sm text-gray-500">
                Ước tính người nhận: <span class="font-medium text-gray-700">{{ estimatedCount ?? '...' }}</span>
              </p>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-4 border-t">
            <button
              type="button"
              @click="sendTest"
              :disabled="isSendingTest || !form.title || !form.content"
              class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
            >
              {{ isSendingTest ? 'Đang gửi...' : 'Gửi thử' }}
            </button>
            <button
              type="submit"
              :disabled="isCreating || !isFormValid"
              class="flex-1 px-6 py-3 bg-[#D72D36] text-white font-medium rounded-lg hover:bg-[#c4252e] transition-colors disabled:opacity-50"
            >
              {{ isCreating ? 'Đang tạo...' : 'Tạo chiến dịch' }}
            </button>
          </div>
        </form>
      </div>
        </div>

        <!-- Tab: History -->
        <div v-show="activeTab === 'history'" class="bg-white rounded-2xl shadow-sm border p-6">
          <!-- Filters -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Trạng thái</label>
              <select
                v-model="filters.status"
                @change="fetchCampaigns(1)"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
              >
                <option value="">Tất cả</option>
                <option value="DRAFT">Nháp</option>
                <option value="SCHEDULED">Đã hẹn giờ</option>
                <option value="PROCESSING">Đang gửi</option>
                <option value="SENT">Đã gửi</option>
                <option value="PARTIAL">Gửi một phần</option>
                <option value="FAILED">Thất bại</option>
                <option value="CANCELLED">Đã hủy</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Loại người nhận</label>
              <select
                v-model="filters.recipient_type"
                @change="fetchCampaigns(1)"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
              >
                <option value="">Tất cả</option>
                <option value="ALL">Tất cả người dùng</option>
                <option value="CLUB">Theo câu lạc bộ</option>
                <option value="ACTIVITY">Theo mức độ hoạt động</option>
                <option value="USERS">Theo danh sách</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-gray-600 mb-1">Tìm kiếm</label>
              <input
                v-model="filters.search"
                @keyup.enter="fetchCampaigns(1)"
                type="text"
                placeholder="Tìm theo tiêu đề hoặc nội dung..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
              />
            </div>
          </div>

          <!-- Loading -->
          <div v-if="isLoadingHistory" class="flex items-center justify-center py-16">
            <div class="w-8 h-8 border-4 border-[#D72D36] border-t-transparent rounded-full animate-spin"></div>
          </div>

          <!-- Empty -->
          <div v-else-if="campaigns.length === 0" class="text-center py-16">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
            </div>
            <p class="text-gray-500">Chưa có chiến dịch nào</p>
          </div>

          <!-- List -->
          <div v-else class="space-y-3">
            <div
              v-for="item in campaigns"
              :key="item.id"
              @click="openDetail(item)"
              class="border border-gray-200 rounded-xl p-4 hover:border-[#D72D36] hover:shadow-sm transition-all cursor-pointer"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span
                      class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                      :class="statusBadgeClass(item.status)"
                    >
                      {{ item.status_label || item.status }}
                    </span>
                    <span class="text-xs text-gray-500">
                      {{ item.created_at ? formatDate(item.created_at) : '' }}
                    </span>
                  </div>
                  <h4 class="font-semibold text-gray-800 truncate">{{ item.title }}</h4>
                  <p class="text-sm text-gray-500 truncate mt-0.5">{{ item.content }}</p>
                  <p class="text-xs text-gray-500 mt-1">
                    Đối tượng: <span class="font-medium text-gray-700">{{ item.recipient_label || '-' }}</span>
                  </p>
                </div>

                <div class="text-right flex-shrink-0 w-40">
                  <p class="text-xs text-gray-500">Gửi thành công</p>
                  <p class="text-lg font-bold" :class="successRateColor(item.success_rate)">
                    {{ item.success_rate !== null && item.success_rate !== undefined ? `${item.success_rate}%` : '-' }}
                  </p>
                  <p class="text-xs text-gray-500 mt-1">
                    {{ item.success_count ?? 0 }}/{{ item.actual_recipient_count ?? 0 }}
                  </p>
                  <!-- Mini progress bar -->
                  <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden mt-1">
                    <div
                      class="h-full transition-all"
                      :class="successBarColor(item.success_rate)"
                      :style="{ width: `${item.success_rate || 0}%` }"
                    ></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="historyMeta && historyMeta.last_page > 1" class="flex items-center justify-between mt-6 pt-4 border-t">
            <p class="text-sm text-gray-500">
              Trang {{ historyMeta.current_page }} / {{ historyMeta.last_page }} - Tổng {{ historyMeta.total }}
            </p>
            <div class="flex gap-2">
              <button
                @click="fetchCampaigns(historyMeta.current_page - 1)"
                :disabled="historyMeta.current_page <= 1"
                class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors disabled:opacity-50"
              >
                Trước
              </button>
              <button
                @click="fetchCampaigns(historyMeta.current_page + 1)"
                :disabled="historyMeta.current_page >= historyMeta.last_page"
                class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors disabled:opacity-50"
              >
                Sau
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Template Modal -->
    <AdminNotificationTemplateModal
      v-model="showTemplateModal"
      @apply-template="handleApplyTemplate"
    />

    <!-- Detail Modal -->
    <AdminPushNotificationDetailModal
      v-model="showDetailModal"
      :campaign="selectedCampaign"
      :is-loading="isLoadingDetail"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { toast } from 'vue3-toastify'
import AdminSidebar from '@/components/organisms/AdminSidebar.vue'
import AdminHeader from '@/components/organisms/AdminHeader.vue'
import AdminNotificationTemplateModal from '@/components/organisms/AdminNotificationTemplateModal.vue'
import AdminPushNotificationDetailModal from '@/components/organisms/AdminPushNotificationDetailModal.vue'
import axiosInstance from '@/utils/httpRequest.js'
import { listCampaigns as fetchListCampaigns, getCampaignDetail as fetchCampaignDetail } from '@/service/adminPushNotification.js'

const tabs = [
  { key: 'create', label: 'Tạo thông báo' },
  { key: 'history', label: 'Lịch sử' }
]

const activeTab = ref('create')
const showTemplateModal = ref(false)
const isDragging = ref(false)
const imagePreview = ref(null)
const estimatedCount = ref(null)
const isSendingTest = ref(false)
const isCreating = ref(false)

// History state
const campaigns = ref([])
const historyMeta = ref(null)
const isLoadingHistory = ref(false)
const showDetailModal = ref(false)
const selectedCampaign = ref(null)
const isLoadingDetail = ref(false)

const filters = reactive({
  status: '',
  recipient_type: '',
  search: ''
})

const form = reactive({
  title: '',
  content: '',
  image: null,
  action_type: 'NONE',
  action_id: null,
  recipient_type: 'ALL',
  recipient_config: {},
  send_type: 'IMMEDIATE',
  scheduled_at: null
})

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

const successRateColor = (rate) => {
  if (rate === null || rate === undefined) return 'text-gray-400'
  if (rate >= 95) return 'text-green-600'
  if (rate >= 80) return 'text-yellow-600'
  return 'text-red-600'
}

const successBarColor = (rate) => {
  if (rate === null || rate === undefined) return 'bg-gray-300'
  if (rate >= 95) return 'bg-green-500'
  if (rate >= 80) return 'bg-yellow-500'
  return 'bg-red-500'
}

const fetchCampaigns = async (page = 1) => {
  isLoadingHistory.value = true
  try {
    const params = {
      page,
      limit: 15,
      ...(filters.status ? { status: filters.status } : {}),
      ...(filters.recipient_type ? { recipient_type: filters.recipient_type } : {}),
      ...(filters.search ? { search: filters.search } : {})
    }
    const response = await fetchListCampaigns(params)
    campaigns.value = response.data || []
    historyMeta.value = response.meta || null
  } catch (error) {
    console.error('Failed to load campaigns:', error)
    toast.error('Không thể tải lịch sử thông báo')
    campaigns.value = []
    historyMeta.value = null
  } finally {
    isLoadingHistory.value = false
  }
}

const openDetail = async (item) => {
  showDetailModal.value = true
  selectedCampaign.value = item
  isLoadingDetail.value = true
  try {
    const response = await fetchCampaignDetail(item.id)
    selectedCampaign.value = response.data || item
  } catch (error) {
    console.error('Failed to load campaign detail:', error)
    toast.error('Không thể tải chi tiết thông báo')
  } finally {
    isLoadingDetail.value = false
  }
}

watch(activeTab, (val) => {
  if (val === 'history' && campaigns.value.length === 0) {
    fetchCampaigns(1)
  }
})

const minScheduleTime = computed(() => {
  const now = new Date()
  now.setMinutes(now.getMinutes() + 5)
  return now.toISOString().slice(0, 16)
})

const isFormValid = computed(() => {
  if (!form.title || !form.content || !form.recipient_type || !form.send_type) return false

  if (form.recipient_type === 'CLUB' && !form.recipient_config.club_id) return false
  if (form.recipient_type === 'ACTIVITY' && !form.recipient_config.level) return false
  if (form.recipient_type === 'USERS' && (!form.recipient_config.user_ids || form.recipient_config.user_ids.length === 0)) return false

  if (form.send_type === 'SCHEDULED' && !form.scheduled_at) return false

  return true
})

const handleImageSelect = (event) => {
  const file = event.target.files[0]
  if (file) {
    form.image = file
    imagePreview.value = URL.createObjectURL(file)
  }
}

const handleDrop = (event) => {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (file && file.type.startsWith('image/')) {
    form.image = file
    imagePreview.value = URL.createObjectURL(file)
  }
}

const removeImage = () => {
  form.image = null
  imagePreview.value = null
}

const estimateRecipients = async () => {
  if (!form.recipient_type) return

  try {
    const response = await axiosInstance.post('/admin/push-notifications/estimate-recipients', {
      recipient_type: form.recipient_type,
      recipient_config: form.recipient_config
    })
    estimatedCount.value = response.data.data?.estimated_recipient_count ?? 0
  } catch (error) {
    console.error('Failed to estimate recipients:', error)
    estimatedCount.value = null
  }
}

const sendTest = async () => {
  if (!form.title || !form.content) return

  isSendingTest.value = true
  try {
    const formData = new FormData()
    formData.append('title', form.title)
    formData.append('content', form.content)
    formData.append('action_type', form.action_type)
    if (form.image) formData.append('image', form.image)

    await axiosInstance.post('/admin/push-notifications/test', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    toast.success('Đã gửi thông báo thử thành công!')
  } catch (error) {
    console.error('Failed to send test notification:', error)
    toast.error(error.response?.data?.message || 'Gửi thông báo thử thất bại')
  } finally {
    isSendingTest.value = false
  }
}

const createCampaign = async () => {
  if (!isFormValid.value) return

  isCreating.value = true
  try {
    const formData = new FormData()
    formData.append('title', form.title)
    formData.append('content', form.content)
    formData.append('action_type', form.action_type)
    formData.append('recipient_type', form.recipient_type)
    formData.append('recipient_config', JSON.stringify(form.recipient_config))
    formData.append('send_type', form.send_type)
    if (form.action_id) formData.append('action_id', form.action_id)
    if (form.image) formData.append('image', form.image)
    if (form.scheduled_at) formData.append('scheduled_at', form.scheduled_at)

    await axiosInstance.post('/admin/push-notifications', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    toast.success('Tạo chiến dịch thành công!')
    resetForm()
  } catch (error) {
    console.error('Failed to create campaign:', error)
    toast.error(error.response?.data?.message || 'Tạo chiến dịch thất bại')
  } finally {
    isCreating.value = false
  }
}

const resetForm = () => {
  form.title = ''
  form.content = ''
  form.image = null
  form.action_type = 'NONE'
  form.action_id = null
  form.recipient_type = 'ALL'
  form.recipient_config = {}
  form.send_type = 'IMMEDIATE'
  form.scheduled_at = null
  imagePreview.value = null
  estimatedCount.value = null
}

const handleApplyTemplate = (template) => {
  form.title = template.title || ''
  form.content = template.content || ''
  form.action_type = template.action_type || 'NONE'
  form.action_id = template.action_id || null
  form.recipient_type = template.recipient_type || 'ALL'
  form.recipient_config = template.recipient_config || {}
}

watch(
  () => [form.recipient_type, form.recipient_config],
  () => {
    if (form.recipient_type) {
      estimateRecipients()
    }
  },
  { deep: true }
)
</script>
