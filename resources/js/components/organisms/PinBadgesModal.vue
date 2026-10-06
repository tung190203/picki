<template>
  <transition name="modal">
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
      
      <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden transform transition-all flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 flex-shrink-0">
          <div>
            <h3 class="text-lg font-bold text-slate-800 font-headline">Tuỳ chỉnh huy hiệu hiển thị</h3>
            <p class="text-xs text-slate-500 mt-1">Chọn tối đa 3 huy hiệu nổi bật nhất của bạn để ghim lên hồ sơ.</p>
          </div>
          <button @click="close" class="text-slate-400 hover:text-slate-600 transition-colors cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="p-6 overflow-y-auto flex-1">
          <div v-if="loading" class="flex justify-center py-10">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#E8192C]"></div>
          </div>
          
          <div v-else-if="unlockedBadges.length === 0" class="text-center py-10">
            <div class="w-16 h-16 mx-auto mb-4 bg-slate-100 rounded-full flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <p class="text-slate-600 font-medium">Bạn chưa mở khóa huy hiệu nào.</p>
            <p class="text-sm text-slate-400 mt-1">Hãy tham gia các hoạt động để nhận thêm huy hiệu nhé!</p>
          </div>
          
          <div v-else>
            <!-- Selected Badges Preview -->
            <div class="mb-6 p-4 bg-slate-50 rounded-2xl border border-slate-100">
              <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Đã chọn ({{ selectedIds.length }}/3)</p>
              <div class="flex gap-4 min-h-[60px]">
                <div 
                  v-for="i in 3" 
                  :key="`slot-${i}`" 
                  class="w-16 h-16 rounded-2xl border-2 flex items-center justify-center transition-all"
                  :class="selectedBadges[i-1] ? 'border-[#E8192C] bg-red-50' : 'border-dashed border-slate-200 bg-white'"
                >
                  <div v-if="selectedBadges[i-1]" class="relative cursor-pointer group" @click="toggleSelection(selectedBadges[i-1])">
                    <BadgeIcon :badge="selectedBadges[i-1]" size="lg" :showHoverCard="false" />
                    <div class="absolute -top-2 -right-2 bg-slate-800 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                      </svg>
                    </div>
                  </div>
                  <span v-else class="text-slate-300 text-2xl font-light">+</span>
                </div>
              </div>
            </div>

            <!-- Badges List -->
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Tất cả huy hiệu của bạn</p>
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-4">
              <div 
                v-for="badge in unlockedBadges" 
                :key="badge.id"
                class="flex flex-col items-center gap-2 p-3 rounded-2xl cursor-pointer transition-all border-2"
                :class="isSelected(badge) ? 'border-[#E8192C] bg-red-50/50' : 'border-transparent hover:bg-slate-50'"
                @click="toggleSelection(badge)"
              >
                <div class="relative">
                  <BadgeIcon :badge="badge" size="xl" :showHoverCard="true" />
                  <div v-if="isSelected(badge)" class="absolute -bottom-1 -right-1 bg-[#E8192C] text-white rounded-full p-0.5 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                  </div>
                </div>
                <span class="text-xs font-medium text-center text-slate-700 line-clamp-2 leading-tight">{{ badge.name }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-3 flex-shrink-0">
          <button @click="close" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-sm hover:bg-slate-100 transition-colors cursor-pointer">
            Hủy
          </button>
          <button 
            @click="save" 
            :disabled="saving" 
            class="px-6 py-2.5 rounded-xl bg-[#E8192C] hover:bg-[#c91223] text-white font-bold text-sm shadow-md transition-all active:scale-95 cursor-pointer flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg v-if="saving" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}</span>
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axiosInstance from '@/utils/httpRequest.js'
import { toast } from 'vue3-toastify'
import BadgeIcon from '@/components/atoms/BadgeIcon.vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  userId: {
    type: [Number, String],
    required: true
  }
})

const emit = defineEmits(['update:show', 'saved'])

const loading = ref(false)
const saving = ref(false)
const badges = ref([])
const selectedIds = ref([]) // array of user_badge_id

const unlockedBadges = computed(() => {
  return badges.value.filter(b => b.is_unlocked)
})

const selectedBadges = computed(() => {
  return selectedIds.value.map(id => unlockedBadges.value.find(b => b.user_badge_id === id)).filter(Boolean)
})

const isSelected = (badge) => {
  return selectedIds.value.includes(badge.user_badge_id)
}

const toggleSelection = (badge) => {
  const index = selectedIds.value.indexOf(badge.user_badge_id)
  if (index > -1) {
    selectedIds.value.splice(index, 1)
  } else {
    if (selectedIds.value.length < 3) {
      selectedIds.value.push(badge.user_badge_id)
    } else {
      toast.warning('Bạn chỉ có thể ghim tối đa 3 huy hiệu!')
    }
  }
}

const fetchBadges = async () => {
  try {
    loading.value = true
    const response = await axiosInstance.get(`/user/${props.userId}/badges`)
    if (response.data && response.data.data) {
      badges.value = response.data.data
      
      selectedIds.value = badges.value
        .filter(b => b.is_unlocked && b.is_featured)
        .slice(0, 3)
        .map(b => b.user_badge_id)
    }
  } catch (error) {
    console.error('Error fetching badges for modal:', error)
    toast.error('Không thể tải danh sách huy hiệu')
  } finally {
    loading.value = false
  }
}

const save = async () => {
  if (unlockedBadges.value.length > 0 && selectedIds.value.length === 0) {
    toast.warning('Bạn cần giữ và hiển thị ít nhất 1 huy hiệu nhé!')
    return
  }

  try {
    saving.value = true
    await axiosInstance.put('/user/badges/featured', {
      user_badge_ids: selectedIds.value
    })
    toast.success('Đã lưu huy hiệu nổi bật thành công!')
    emit('saved')
    close()
  } catch (error) {
    console.error('Error saving featured badges:', error)
    toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lưu')
  } finally {
    saving.value = false
  }
}

const close = () => {
  emit('update:show', false)
}

watch(() => props.show, (newVal) => {
  if (newVal && props.userId) {
    fetchBadges()
  }
})
</script>
