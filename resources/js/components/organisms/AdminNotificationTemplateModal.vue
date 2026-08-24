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
      class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
      @click.self="close"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl relative z-[10000] overflow-hidden animate-in fade-in zoom-in duration-300 max-h-[85vh] flex flex-col">
        <!-- Fixed Header -->
        <div class="p-6 pb-4 border-b">
          <div class="flex items-center justify-between">
            <h3 class="text-2xl font-bold text-gray-800">Quản lý mẫu thông báo</h3>
            <button @click="close" class="text-gray-400 hover:text-gray-600 transition-colors">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Tabs -->
        <div class="flex border-b px-6">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="activeTab = tab.key"
            class="px-4 py-3 font-semibold text-sm transition-colors border-b-2 -mb-px"
            :class="activeTab === tab.key
              ? 'text-[#D72D36] border-[#D72D36]'
              : 'text-gray-500 border-transparent hover:text-gray-700'"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6">
          <!-- Tab: Danh sách mẫu -->
          <div v-if="activeTab === 'list'">
            <div v-if="isLoading" class="flex items-center justify-center py-12">
              <div class="w-8 h-8 border-4 border-[#D72D36] border-t-transparent rounded-full animate-spin"></div>
            </div>

            <div v-else-if="templates.length === 0" class="text-center py-12">
              <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <p class="text-gray-500">Chưa có mẫu thông báo nào</p>
              <p class="text-gray-400 text-sm mt-1">Tạo mẫu đầu tiên để sử dụng lại</p>
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="template in templates"
                :key="template.id"
                class="p-4 rounded-xl border hover:border-[#D72D36] hover:shadow-md transition-all cursor-pointer group"
                :class="selectedTemplate?.id === template.id ? 'border-[#D72D36] bg-red-50' : 'border-gray-200'"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1" @click="selectTemplate(template)">
                    <h4 class="font-semibold text-gray-800 group-hover:text-[#D72D36] transition-colors">{{ template.name }}</h4>
                    <p class="text-sm text-gray-500 mt-1">
                      <span class="font-medium">Tiêu đề:</span> {{ template.title }}
                    </p>
                    <p class="text-sm text-gray-500 truncate">
                      <span class="font-medium">Nội dung:</span> {{ template.content }}
                    </p>
                    <div class="flex gap-2 mt-2">
                      <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                        {{ template.recipient_type_label }}
                      </span>
                      <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                        {{ template.action_type_label }}
                      </span>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 ml-4">
                    <button
                      @click.stop="applyTemplate(template)"
                      class="px-3 py-1.5 text-sm font-medium bg-[#D72D36] text-white rounded-lg hover:bg-[#c4252e] transition-colors"
                    >
                      Áp dụng
                    </button>
                    <button
                      @click.stop="editTemplate(template)"
                      class="p-1.5 text-gray-400 hover:text-gray-600 transition-colors"
                      title="Sửa"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                    <button
                      @click.stop="confirmDelete(template)"
                      class="p-1.5 text-gray-400 hover:text-red-500 transition-colors"
                      title="Xóa"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tab: Tạo/Sửa mẫu -->
          <div v-if="activeTab === 'create' || activeTab === 'edit'">
            <form @submit.prevent="saveTemplate" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tên mẫu *</label>
                <input
                  v-model="formData.name"
                  type="text"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
                  placeholder="VD: Thông báo sự kiện mới"
                  required
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tiêu đề thông báo *</label>
                <input
                  v-model="formData.title"
                  type="text"
                  maxlength="50"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
                  placeholder="VD: Giải đấu mới sắp diễn ra"
                  required
                />
                <p class="text-xs text-gray-400 mt-1">{{ formData.title.length }}/50 ký tự</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nội dung thông báo *</label>
                <textarea
                  v-model="formData.content"
                  maxlength="150"
                  rows="3"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent resize-none"
                  placeholder="VD: Giải đấu Picki Cup 2026 sẽ bắt đầu vào ngày mai..."
                  required
                ></textarea>
                <p class="text-xs text-gray-400 mt-1">{{ formData.content.length }}/150 ký tự</p>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Loại hành động</label>
                  <select
                    v-model="formData.action_type"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
                  >
                    <option value="NONE">Không có</option>
                    <option value="MATCH">Trận đấu</option>
                    <option value="TOURNAMENT">Giải đấu</option>
                    <option value="CLUB">Câu lạc bộ</option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Loại người nhận *</label>
                  <select
                    v-model="formData.recipient_type"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
                    required
                  >
                    <option value="ALL">Tất cả người dùng</option>
                    <option value="CLUB">Theo câu lạc bộ</option>
                    <option value="ACTIVITY">Theo mức độ hoạt động</option>
                    <option value="USERS">Theo danh sách người dùng</option>
                  </select>
                </div>
              </div>

              <!-- Recipient config based on type -->
              <div v-if="formData.recipient_type === 'CLUB'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Chọn câu lạc bộ *</label>
                <input
                  v-model.number="formData.recipient_config.club_id"
                  type="number"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
                  placeholder="Nhập ID câu lạc bộ"
                />
              </div>

              <div v-if="formData.recipient_type === 'ACTIVITY'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Mức độ hoạt động *</label>
                <select
                  v-model="formData.recipient_config.level"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
                >
                  <option value="">Chọn mức độ</option>
                  <option value="HOT">HOT - Hoạt động cao</option>
                  <option value="WARM">WARM - Hoạt động trung bình</option>
                  <option value="COLD">COLD - Hoạt động thấp</option>
                </select>
              </div>

              <div v-if="formData.recipient_type === 'USERS'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Danh sách User IDs *</label>
                <input
                  :value="formData.recipient_config.user_ids?.join(', ')"
                  @input="e => formData.recipient_config.user_ids = e.target.value.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id))"
                  type="text"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#D72D36] focus:border-transparent"
                  placeholder="1, 2, 3, 4 (tối đa 1000)"
                />
              </div>

              <div class="flex gap-3 pt-4">
                <button
                  type="button"
                  @click="activeTab = 'list'"
                  class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors"
                >
                  Hủy
                </button>
                <button
                  type="submit"
                  :disabled="isSaving"
                  class="flex-1 px-4 py-2.5 bg-[#D72D36] text-white font-medium rounded-lg hover:bg-[#c4252e] transition-colors disabled:opacity-50"
                >
                  {{ isSaving ? 'Đang lưu...' : (activeTab === 'edit' ? 'Cập nhật' : 'Tạo mẫu') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </Transition>

  <!-- Delete Confirmation Modal -->
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 z-[10001] flex items-center justify-center p-4 bg-black/50"
      @click.self="showDeleteConfirm = false"
    >
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <div class="text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 flex items-center justify-center">
            <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <h4 class="text-lg font-semibold text-gray-800 mb-2">Xác nhận xóa mẫu</h4>
          <p class="text-gray-500 mb-6">Bạn có chắc muốn xóa mẫu "{{ templateToDelete?.name }}" không?</p>
          <div class="flex gap-3">
            <button
              @click="showDeleteConfirm = false"
              class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors"
            >
              Hủy
            </button>
            <button
              @click="deleteTemplate"
              :disabled="isDeleting"
              class="flex-1 px-4 py-2.5 bg-red-500 text-white font-medium rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50"
            >
              {{ isDeleting ? 'Đang xóa...' : 'Xóa' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { toast } from 'vue3-toastify'
import { getNotificationTemplates, createNotificationTemplate, updateNotificationTemplate, deleteNotificationTemplate } from '@/service/notificationTemplate.js'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'applyTemplate'])

const tabs = [
  { key: 'list', label: 'Danh sách mẫu' },
  { key: 'create', label: 'Tạo mẫu mới' }
]

const activeTab = ref('list')
const templates = ref([])
const isLoading = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const showDeleteConfirm = ref(false)
const templateToDelete = ref(null)
const selectedTemplate = ref(null)
const editingTemplateId = ref(null)

const defaultFormData = () => ({
  name: '',
  title: '',
  content: '',
  action_type: 'NONE',
  action_id: null,
  recipient_type: 'ALL',
  recipient_config: {}
})

const formData = reactive(defaultFormData())

const loadTemplates = async () => {
  isLoading.value = true
  try {
    const response = await getNotificationTemplates()
    templates.value = response.data?.templates || []
  } catch (error) {
    console.error('Failed to load templates:', error)
    templates.value = []
  } finally {
    isLoading.value = false
  }
}

const resetForm = () => {
  Object.assign(formData, defaultFormData())
  editingTemplateId.value = null
  selectedTemplate.value = null
}

const selectTemplate = (template) => {
  selectedTemplate.value = selectedTemplate.value?.id === template.id ? null : template
}

const applyTemplate = (template) => {
  emit('applyTemplate', { ...template })
  close()
  toast.success('Đã áp dụng mẫu: ' + template.name)
}

const editTemplate = (template) => {
  editingTemplateId.value = template.id
  Object.assign(formData, {
    name: template.name,
    title: template.title,
    content: template.content,
    action_type: template.action_type || 'NONE',
    action_id: template.action_id,
    recipient_type: template.recipient_type,
    recipient_config: template.recipient_config || {}
  })
  tabs.splice(1, 1, { key: 'edit', label: 'Sửa mẫu' })
  activeTab.value = 'edit'
}

const saveTemplate = async () => {
  isSaving.value = true
  try {
    const payload = {
      name: formData.name,
      title: formData.title,
      content: formData.content,
      action_type: formData.action_type,
      action_id: formData.action_id,
      recipient_type: formData.recipient_type,
      recipient_config: formData.recipient_config
    }

    if (editingTemplateId.value) {
      await updateNotificationTemplate(editingTemplateId.value, payload)
    } else {
      await createNotificationTemplate(payload)
    }

    await loadTemplates()
    activeTab.value = 'list'
    tabs.splice(1, 1, { key: 'create', label: 'Tạo mẫu mới' })
    resetForm()
    toast.success(editingTemplateId.value ? 'Cập nhật mẫu thành công' : 'Tạo mẫu thành công')
  } catch (error) {
    console.error('Failed to save template:', error)
    toast.error(error.response?.data?.message || 'Lưu mẫu thất bại')
  } finally {
    isSaving.value = false
  }
}

const confirmDelete = (template) => {
  templateToDelete.value = template
  showDeleteConfirm.value = true
}

const deleteTemplate = async () => {
  if (!templateToDelete.value) return

  isDeleting.value = true
  try {
    await deleteNotificationTemplate(templateToDelete.value.id)
    await loadTemplates()
    showDeleteConfirm.value = false
    templateToDelete.value = null
    toast.success('Xóa mẫu thành công')
  } catch (error) {
    console.error('Failed to delete template:', error)
    toast.error(error.response?.data?.message || 'Xóa mẫu thất bại')
  } finally {
    isDeleting.value = false
  }
}

const close = () => {
  emit('update:modelValue', false)
}

watch(() => props.modelValue, (newVal) => {
  if (newVal) {
    loadTemplates()
    activeTab.value = 'list'
    tabs.splice(1, 1, { key: 'create', label: 'Tạo mẫu mới' })
    resetForm()
  }
})
</script>
