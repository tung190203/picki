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
      class="fixed inset-0 z-[10001] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md transform-gpu" 
      @click.self="close"
    >
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 scale-95 translateY(20px)"
        enter-to-class="opacity-100 scale-100 translateY(0)"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 scale-100 translateY(0)"
        leave-to-class="opacity-0 scale-95 translateY(20px)"
      >
        <div 
          v-if="modelValue && notification"
          class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-2xl shadow-2xl w-full max-w-2xl relative overflow-hidden flex flex-col max-h-[90vh]"
        >
          <!-- Header Strip with Icon -->
          <div :class="['h-24 flex items-center px-8 relative overflow-hidden', currentColors.cardBg]">
             <div class="absolute inset-0 opacity-10 pointer-events-none">
                <div class="absolute inset-0 bg-repeat bg-center opacity-10"
                    style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;20&quot; height=&quot;20&quot; viewBox=&quot;0 0 20 20&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h10v10H0zM10 10h10v10H10z&quot; fill=&quot;%23000000&quot; fill-opacity=&quot;1&quot;/%3E%3C/svg%3E')">
                </div>
            </div>
            
            <div 
              class="w-12 h-12 rounded-xl flex items-center justify-center shadow-md relative z-10"
              :class="currentColors.iconBg"
            >
              <component :is="currentIcon" class="w-6 h-6 text-white" />
            </div>
            <div class="ml-5 relative z-10 flex-1 min-w-0">
              <span class="text-[10px] font-bold uppercase tracking-widest opacity-60" :class="currentColors.title">Thông báo</span>
              <h2 v-if="!isEditMode" class="text-xl font-bold leading-tight" :class="currentColors.title">{{ notification.title }}</h2>
              <p v-else class="text-sm font-medium opacity-70" :class="currentColors.title">Đang chỉnh sửa thông báo</p>
            </div>
            
            <div class="flex items-center gap-2 absolute top-4 right-4 z-10">
              <!-- Edit toggle button for admin -->
              <button 
                v-if="isAdmin && !isEditMode" 
                @click="enterEditMode"
                class="p-2 rounded-full hover:bg-black/10 transition-colors"
                :class="currentColors.title"
                title="Chỉnh sửa thông báo"
              >
                <PencilIcon class="w-5 h-5" />
              </button>
              <!-- Delete button for admin -->
              <button 
                v-if="isAdmin && !isEditMode" 
                @click="$emit('delete', notification.id)"
                class="p-2 rounded-full hover:bg-black/10 transition-colors"
                :class="currentColors.title"
                title="Xóa thông báo"
              >
                <TrashIcon class="w-5 h-5" />
              </button>
              <button @click="close" class="p-2 rounded-full hover:bg-black/5 transition-colors text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-white">
                <XMarkIcon class="w-6 h-6" stroke-width="2" />
              </button>
            </div>
          </div>

          <!-- VIEW MODE: Content Area -->
          <div v-if="!isEditMode" class="p-8 overflow-y-auto custom-scrollbar flex-1">
            <!-- Author Info -->
            <div class="flex items-center space-x-3 mb-6">
              <img :src="notification.creator?.avatar_url || 'https://ui-avatars.com/api/?name=' + notification.creator?.full_name" class="w-10 h-10 rounded-full object-cover border border-gray-100 dark:border-slate-700 shadow-sm" />
              <div>
                <p class="font-bold text-[#3E414C] dark:text-slate-100 text-[15px]">{{ notification.creator?.full_name }}</p>
                <div class="flex items-center text-[12px] text-[#838799] dark:text-slate-400 space-x-2">
                  <span>{{ getJoinedDate(notification.sent_at || notification.created_at) }}</span>
                  <span v-if="notification.is_pinned" class="flex items-center text-[#D72D36] dark:text-red-400 font-semibold">
                    <span class="mx-1">•</span>
                    <PinIcon class="w-3.5 h-3.5 mr-1" />
                    Đã ghim
                  </span>
                </div>
              </div>
            </div>

            <!-- Body Text -->
            <div class="prose prose-slate dark:prose-invert max-w-none mb-8 text-[#3E414C] dark:text-slate-200 text-[14px] leading-relaxed whitespace-pre-wrap">
              {{ notification.content }}
            </div>

            <!-- Attachment -->
            <div v-if="notification.attachment_url" class="mt-6 border-t border-gray-100 dark:border-slate-800 pt-6">
              <h3 class="text-base font-bold text-[#3E414C] dark:text-slate-100 mb-3 flex items-center">
                <PaperClipIcon class="w-4 h-4 mr-2 text-[#D72D36]" />
                Đính kèm
              </h3>
              <div class="group relative rounded-xl overflow-hidden border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-shadow">
                <img v-if="isImage(notification.attachment_url)" :src="notification.attachment_url" class="w-full h-auto max-h-[300px] object-contain bg-gray-50 dark:bg-slate-800" />
                <div v-else class="p-6 bg-gray-50 dark:bg-slate-800 flex items-center">
                  <DocumentIcon class="w-8 h-8 text-gray-400 dark:text-slate-400 mr-4" />
                  <span class="text-gray-600 dark:text-slate-300 font-medium truncate">{{ getFileName(notification.attachment_url) }}</span>
                  <a :href="notification.attachment_url" download target="_blank" class="ml-auto bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 px-4 py-2 rounded-xl text-sm font-bold text-[#3E414C] dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors">
                    Tải về
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- EDIT MODE: Title + Type (outside scroll — avoids overflow clipping on dropdown) -->
          <div v-if="isEditMode" class="px-6 pt-6 pb-4 space-y-4 border-b border-gray-100 dark:border-slate-800">
            <!-- Title -->
            <div>
              <label class="block font-semibold text-[#3E414C] dark:text-slate-200 mb-2">Tiêu đề thông báo</label>
              <input
                v-model="editForm.title"
                type="text"
                class="w-full px-4 py-3 bg-[#EDEEF2] dark:bg-slate-800 border-none rounded-lg text-[#3E414C] dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 transition-colors placeholder-[#9EA2B3] dark:placeholder:text-slate-500"
                placeholder="Ví dụ: Thay đổi lịch đấu"
              />
            </div>

            <!-- Type dropdown — outside overflow-y-auto so it's never clipped -->
            <div class="relative" ref="typeDropdownRef">
              <label class="block font-semibold text-[#3E414C] dark:text-slate-200 mb-2">Loại thông báo</label>
              <div
                class="w-full flex items-center justify-between px-4 py-3 bg-white dark:bg-slate-800 border border-[#D72D36]/30 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                :class="{'ring-2 ring-[#D72D36]/20 border-[#D72D36]': isTypeDropdownOpen}"
                @click="toggleTypeDropdown"
              >
                <div class="flex items-center gap-3">
                  <component v-if="selectedTypeObj?.icon" :is="selectedTypeObj.icon" class="w-5 h-5 text-gray-500 dark:text-slate-400" />
                  <span class="font-bold" :class="selectedTypeObj?.label ? 'text-gray-700 dark:text-slate-200' : 'text-gray-400 dark:text-slate-500'">
                    {{ selectedTypeObj?.label || 'Chọn loại thông báo' }}
                  </span>
                </div>
                <ChevronDownIcon class="h-4 w-4 text-gray-500 dark:text-slate-400 transition-transform duration-200" :class="{'rotate-180': isTypeDropdownOpen}" />
              </div>
              <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="transform scale-95 opacity-0"
                enter-to-class="transform scale-100 opacity-100"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="transform scale-100 opacity-100"
                leave-to-class="transform scale-95 opacity-0"
              >
                <div v-if="isTypeDropdownOpen" class="absolute z-[100] w-full mt-2 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-100 dark:border-slate-700 py-2">
                  <div
                    v-for="type in mappedNotificationTypes"
                    :key="type.value"
                    class="px-4 py-3 cursor-pointer flex items-center gap-3 transition-colors"
                    :class="editForm.club_notification_type_id === type.value ? 'bg-[#fff5f5] dark:bg-red-950/40' : 'hover:bg-gray-50 dark:hover:bg-slate-700'"
                    @click.stop="selectType(type.value)"
                  >
                    <component
                      :is="type.icon"
                      class="w-5 h-5"
                      :class="editForm.club_notification_type_id === type.value ? 'text-[#D72D36] dark:text-red-400' : 'text-[#3E414C] dark:text-slate-300'"
                    />
                    <span
                      class="font-normal"
                      :class="editForm.club_notification_type_id === type.value ? 'text-[#D72D36] dark:text-red-400' : 'text-[#3E414C] dark:text-slate-300'"
                    >
                      {{ type.label }}
                    </span>
                  </div>
                </div>
              </Transition>
            </div>
          </div>

          <!-- EDIT MODE: Content + Image (scrollable) -->
          <div v-if="isEditMode" class="p-6 overflow-y-auto custom-scrollbar flex-1 space-y-5">
            <!-- Content -->
            <div>
              <div class="flex justify-between mb-2">
                <label class="block font-semibold text-[#3E414C] dark:text-slate-200">Nội dung thông báo</label>
                <span class="text-xs text-gray-400 dark:text-slate-400">{{ editForm.content.length }}/300</span>
              </div>
              <textarea
                v-model="editForm.content"
                rows="5"
                maxlength="300"
                class="w-full px-4 py-3 bg-[#EDEEF2] dark:bg-slate-800 border-none rounded-lg text-[#3E414C] dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 transition-colors resize-none placeholder-[#9EA2B3] dark:placeholder:text-slate-500"
                placeholder="Nhập nội dung chi tiết"
              ></textarea>
            </div>

            <!-- Image Upload -->
            <div
              class="border-2 border-dashed border-gray-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 p-4 flex flex-col items-center justify-center cursor-pointer hover:border-[#D72D36]/50 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all relative overflow-hidden h-36"
              @click="triggerFileInput"
            >
              <template v-if="editPreviewUrl">
                <img :src="editPreviewUrl" class="absolute inset-0 w-full h-full object-cover" alt="Preview" />
                <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                  <p class="text-white font-medium">Thay đổi ảnh</p>
                </div>
              </template>
              <template v-else>
                <div class="w-10 h-10 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mb-2 text-gray-400 dark:text-slate-400">
                  <PhotoIcon class="w-5 h-5" />
                </div>
                <p class="text-sm font-bold text-gray-700 dark:text-slate-200">Nhấn để tải ảnh lên</p>
                <p class="text-xs text-gray-400 dark:text-slate-400 mt-1">PNG, JPG, GIF (Tối đa 5MB)</p>
              </template>
              <input type="file" ref="fileInput" class="hidden" accept="image/*" @change="handleFileChange" />
            </div>
          </div>

          <!-- Bottom Actions -->
          <div class="p-5 bg-gray-50 dark:bg-[#161F33] border-t border-gray-100 dark:border-slate-800 flex justify-between items-center">
            <!-- View mode actions -->
            <template v-if="!isEditMode">
              <div v-if="isAdmin" class="flex space-x-3">
                <button
                  @click="$emit('unpin', notification.id)"
                  v-if="notification.is_pinned"
                  class="flex items-center px-5 py-2 rounded-lg border border-gray-200 dark:border-slate-700 text-[#3E414C] dark:text-slate-200 text-sm font-semibold hover:bg-white dark:hover:bg-slate-800 transition-colors"
                >
                  <PinIcon class="w-4 h-4 mr-2" />
                  Bỏ ghim
                </button>
                <button
                  @click="$emit('pin', notification.id)"
                  v-else
                  class="flex items-center px-5 py-2 rounded-lg border border-gray-200 dark:border-slate-700 text-[#3E414C] dark:text-slate-200 text-sm font-semibold hover:bg-white dark:hover:bg-slate-800 transition-colors"
                >
                  <PinIcon class="w-4 h-4 mr-2" />
                  Ghim ngay
                </button>
              </div>
              <div v-else></div>
              <button @click="close" class="px-5 py-2 rounded-lg border border-gray-200 dark:border-slate-700 text-[#3E414C] dark:text-slate-200 text-sm font-semibold hover:bg-white dark:hover:bg-slate-800 transition-colors">
                Đóng
              </button>
            </template>

            <!-- Edit mode actions -->
            <template v-else>
              <button
                @click="cancelEdit"
                class="px-5 py-2 rounded-lg border border-gray-200 dark:border-slate-700 text-[#3E414C] dark:text-slate-200 text-sm font-semibold hover:bg-white dark:hover:bg-slate-800 transition-colors"
                :disabled="isUpdating"
              >
                Hủy
              </button>
              <button
                @click="handleUpdate"
                class="flex items-center gap-2 px-6 py-2 rounded-lg bg-[#D72D36] text-white text-sm font-semibold hover:bg-[#c4252e] transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                :disabled="isUpdating || !editForm.title"
              >
                <span v-if="isUpdating">Đang lưu...</span>
                <span v-else>Lưu thay đổi</span>
              </button>
            </template>
          </div>
        </div>
      </Transition>
    </div>
  </Transition>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { XMarkIcon, PaperClipIcon, DocumentIcon, PencilIcon, ChevronDownIcon, PhotoIcon, BellIcon, ExclamationTriangleIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { UserGroupIcon } from '@heroicons/vue/24/solid'
import PinIcon from '@/assets/images/pin_icon.svg'
import FinancialIcon from "@/assets/images/money-atm.svg";
import DateIcon from "@/assets/images/date.svg";
import { getJoinedDate } from '@/composables/formatDatetime.js'
import { NOTIFICATION_COLOR_MAP, NOTIFICATION_ICON_MAP } from '@/data/club/index.js'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  notification: {
    type: Object,
    default: null
  },
  isAdmin: {
    type: Boolean,
    default: false
  },
  isUpdating: {
    type: Boolean,
    default: false
  },
  notificationTypes: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'close', 'pin', 'unpin', 'update', 'delete'])

// Edit mode state
const isEditMode = ref(false)
const isTypeDropdownOpen = ref(false)
const typeDropdownRef = ref(null)
const fileInput = ref(null)
const editPreviewUrl = ref(null)

const editForm = ref({
  title: '',
  content: '',
  club_notification_type_id: null,
  attachment: null
})

const iconMapping = {
  'bell': BellIcon,
  'calendar': DateIcon,
  'currency': FinancialIcon,
  'users': UserGroupIcon,
  'alert': ExclamationTriangleIcon
}

const mappedNotificationTypes = computed(() => {
  const types = Array.isArray(props.notificationTypes) ? props.notificationTypes : []
  return types.map(type => ({
    value: type.id,
    label: type.name,
    icon: iconMapping[type.icon] || BellIcon
  }))
})

const selectedTypeObj = computed(() => {
  if (!mappedNotificationTypes.value.length) return null
  return mappedNotificationTypes.value.find(t => t.value === editForm.value.club_notification_type_id) || mappedNotificationTypes.value[0]
})

const close = () => {
  isEditMode.value = false
  isTypeDropdownOpen.value = false
  emit('update:modelValue', false)
  emit('close')
}

const enterEditMode = () => {
  if (!props.notification) return
  editForm.value = {
    title: props.notification.title || '',
    content: props.notification.content || '',
    club_notification_type_id: props.notification.club_notification_type_id || null,
    attachment: null
  }
  editPreviewUrl.value = props.notification.attachment_url || null
  isEditMode.value = true
}

const cancelEdit = () => {
  isEditMode.value = false
  isTypeDropdownOpen.value = false
}

const toggleTypeDropdown = () => {
  isTypeDropdownOpen.value = !isTypeDropdownOpen.value
}

const selectType = (value) => {
  editForm.value.club_notification_type_id = value
  isTypeDropdownOpen.value = false
}

const triggerFileInput = () => {
  fileInput.value?.click()
}

const handleFileChange = (event) => {
  const file = event.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (e) => {
    editPreviewUrl.value = e.target.result
    editForm.value.attachment = file
  }
  reader.readAsDataURL(file)
}

const handleUpdate = () => {
  if (!editForm.value.title) return
  emit('update', {
    id: props.notification.id,
    ...editForm.value
  })
}

const currentColors = computed(() => {
  if (!props.notification) return NOTIFICATION_COLOR_MAP[1]
  return NOTIFICATION_COLOR_MAP[props.notification.club_notification_type_id] || NOTIFICATION_COLOR_MAP[1]
})

const currentIcon = computed(() => {
  if (!props.notification) return NOTIFICATION_ICON_MAP[1]
  return NOTIFICATION_ICON_MAP[props.notification.club_notification_type_id] || NOTIFICATION_ICON_MAP[1]
})

const isImage = (url) => {
  if (!url) return false
  const extension = url.split('.').pop().toLowerCase()
  return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(extension)
}

const getFileName = (url) => {
  if (!url) return ''
  return url.split('/').pop()
}

// Reset edit mode when modal closes
watch(() => props.modelValue, (val) => {
  if (!val) {
    isEditMode.value = false
    isTypeDropdownOpen.value = false
  }
})

// Exit edit mode after successful update
watch(() => props.isUpdating, (newVal, oldVal) => {
  if (oldVal === true && newVal === false) {
    isEditMode.value = false
  }
})
</script>

<style scoped>
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
</style>
