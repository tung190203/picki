<template>
  <div class="min-h-screen" style="background-color: var(--surface-bright, #fff8f7);">
    <AdminHeader />

    <div class="p-6 max-w-5xl mx-auto">
      <!-- Page Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-800">Tạo thông báo Push</h1>
            <p class="text-gray-500 mt-1">Gửi thông báo đẩy đến người dùng ứng dụng</p>
          </div>
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

    <!-- Template Modal -->
    <AdminNotificationTemplateModal
      v-model="showTemplateModal"
      @apply-template="handleApplyTemplate"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { toast } from 'vue3-toastify'
import AdminHeader from '@/components/organisms/AdminHeader.vue'
import AdminNotificationTemplateModal from '@/components/organisms/AdminNotificationTemplateModal.vue'
import axiosInstance from '@/utils/httpRequest.js'

const showTemplateModal = ref(false)
const isDragging = ref(false)
const imagePreview = ref(null)
const estimatedCount = ref(null)
const isSendingTest = ref(false)
const isCreating = ref(false)

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
