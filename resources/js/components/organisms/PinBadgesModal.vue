<template>
  <transition name="modal">
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
      
      <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden transform transition-all flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="px-6 pt-5 pb-4 border-b border-slate-100 bg-white flex-shrink-0">
          <div class="flex justify-between items-start mb-3">
            <div>
              <h3 class="text-xl font-bold text-slate-900 font-headline">Cài đặt huy hiệu</h3>
              <p class="text-xs text-slate-500 mt-0.5">Tùy chỉnh huy hiệu khoe lên Avatar và ghim trong hồ sơ cá nhân</p>
            </div>
            <button @click="close" class="text-slate-400 hover:text-slate-600 transition-colors p-1 rounded-lg hover:bg-slate-100 cursor-pointer">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Tab Switcher (Segmented Control) -->
          <div class="flex p-1 bg-slate-100 rounded-xl gap-1">
            <button
              type="button"
              @click="activeTab = 'avatar'"
              class="flex-1 py-2 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
              :class="activeTab === 'avatar' 
                ? 'bg-white text-slate-900 shadow-sm' 
                : 'text-slate-500 hover:text-slate-800'"
            >
              <span>Huy hiệu Avatar</span>
            </button>

            <button
              type="button"
              @click="activeTab = 'featured'"
              class="flex-1 py-2 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
              :class="activeTab === 'featured' 
                ? 'bg-white text-slate-900 shadow-sm' 
                : 'text-slate-500 hover:text-slate-800'"
            >
              <span>Ghim trong Hồ sơ</span>
              <span 
                class="px-1.5 py-0.5 rounded text-[10px] font-semibold"
                :class="selectedIds.length > 0 ? 'bg-red-50 text-[#E8192C]' : 'bg-slate-200 text-slate-500'"
              >
                {{ selectedIds.length }}
              </span>
            </button>
          </div>
        </div>

        <!-- Body -->
        <div class="p-6 overflow-y-auto flex-1 bg-slate-50/50">
          <div v-if="loading" class="flex justify-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#E8192C]"></div>
          </div>
          
          <div v-else-if="unlockedBadges.length === 0" class="text-center py-12 bg-white rounded-2xl border border-slate-200/60 p-6">
            <div class="w-16 h-16 mx-auto mb-4 bg-slate-100 rounded-full flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <p class="text-slate-700 font-semibold text-base">Bạn chưa mở khóa huy hiệu nào</p>
            <p class="text-xs text-slate-400 mt-1">Hãy tham gia các giải đấu hoặc tạo kèo để nhận huy hiệu nhé!</p>
          </div>
          
          <div v-else>
            <!-- ================= TAB 1: KHOE LÊN AVATAR ================= -->
            <div v-if="activeTab === 'avatar'">
              <!-- Preview Card -->
              <div class="mb-5 p-4 bg-white rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between gap-4">
                <div class="flex items-center gap-3.5 min-w-0">
                  <!-- Avatar with badge overlay -->
                  <div class="relative flex-shrink-0">
                    <img 
                      :src="userAvatar || defaultAvatar" 
                      class="w-14 h-14 rounded-full object-cover border-2 border-white shadow-sm"
                      alt="avatar" 
                    />
                    <div 
                      v-if="avatarBadge" 
                      class="absolute -bottom-1 -right-1 w-6 h-6 flex items-center justify-center rounded-full bg-white shadow-md border border-slate-100"
                    >
                      <BadgeIcon :badge="avatarBadge" size="sm" :showHoverCard="false" />
                    </div>
                  </div>

                  <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-800 mt-1 truncate">
                      {{ avatarBadge ? avatarBadge.name : 'Chưa chọn huy hiệu' }}
                    </p>
                    <p class="text-[11px] text-slate-500">
                      {{ avatarBadge ? 'Sẽ xuất hiện cạnh avatar của bạn tại BXH và các nơi khác' : 'Bấm vào 1 huy hiệu bên dưới để khoe cạnh avatar' }}
                    </p>
                  </div>
                </div>

                <button 
                  v-if="avatarBadge" 
                  @click="avatarBadgeId = null" 
                  type="button"
                  class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:text-red-600 hover:bg-red-50 border border-slate-200 transition-colors flex-shrink-0 cursor-pointer"
                >
                  Gỡ bỏ
                </button>
              </div>

              <!-- Badges Grid for Avatar -->
              <div class="flex items-center justify-between mb-3 px-1">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                  Chọn 1 huy hiệu để khoe
                </p>
                <span class="text-[11px] text-slate-400">Bấm để chọn / bấm lại để bỏ</span>
              </div>

              <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                <div 
                  v-for="badge in unlockedBadges" 
                  :key="badge.id"
                  @click="toggleAvatar(badge)"
                  class="flex flex-col items-center justify-between p-3.5 rounded-2xl transition-all cursor-pointer border-2 bg-white relative group hover:z-30"
                  :class="avatarBadgeId === badge.user_badge_id 
                    ? 'border-[#E8192C] bg-red-50/20 shadow-md ring-2 ring-[#E8192C]/20' 
                    : 'border-slate-100 hover:border-slate-300 hover:shadow-sm'"
                >
                  <div class="my-2 transition-transform group-hover:scale-105">
                    <BadgeIcon :badge="badge" size="xl" :showHoverCard="true" />
                  </div>

                  <span class="text-xs font-medium text-center text-slate-800 line-clamp-2 leading-tight">
                    {{ badge.name }}
                  </span>
                </div>
              </div>
            </div>

            <!-- ================= TAB 2: GHIM TRONG PROFILE ================= -->
            <div v-else>
              <!-- Selected Badges Preview -->
              <div class="mb-5 p-4 bg-white rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                  <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-[#E8192C] bg-red-50 px-2 py-0.5 rounded-full border border-red-200/60">
                      Đã ghim: {{ selectedIds.length }} huy hiệu
                    </span>
                    <p class="text-[11px] text-slate-500 mt-1">Các huy hiệu này sẽ hiển thị bên cạnh tên trên trang cá nhân của bạn (không giới hạn số lượng).</p>
                  </div>

                  <button 
                    v-if="selectedIds.length > 0" 
                    @click="selectedIds = []" 
                    type="button"
                    class="text-xs font-semibold text-slate-500 hover:text-red-600 underline cursor-pointer"
                  >
                    Gỡ tất cả
                  </button>
                </div>

                <!-- Chips row -->
                <div v-if="selectedBadges.length > 0" class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-slate-100">
                  <div 
                    v-for="badge in selectedBadges" 
                    :key="badge.id"
                    class="flex items-center gap-1.5 pl-2 pr-1.5 py-1 rounded-lg bg-red-50/60 border border-red-200/60 text-xs font-medium text-slate-800"
                  >
                    <BadgeIcon :badge="badge" size="xs" :showHoverCard="false" />
                    <span class="truncate max-w-[120px]">{{ badge.name }}</span>
                    <button 
                      @click.stop="toggleSelection(badge)" 
                      type="button"
                      class="w-4 h-4 rounded-full flex items-center justify-center hover:bg-red-200 text-slate-500 hover:text-red-700 transition-colors cursor-pointer"
                    >
                      ×
                    </button>
                  </div>
                </div>
                <div v-else class="text-xs text-slate-400 italic pt-2">
                  Chưa ghim huy hiệu nào. Bấm vào các huy hiệu bên dưới để ghim vào hồ sơ cá nhân.
                </div>
              </div>

              <!-- Badges Grid for Profile -->
              <div class="flex items-center justify-between mb-3 px-1">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                  Tất cả huy hiệu của bạn
                </p>
                <span class="text-[11px] text-slate-400">Bấm để bật/tắt ghim</span>
              </div>

              <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                <div 
                  v-for="badge in unlockedBadges" 
                  :key="badge.id"
                  @click="toggleSelection(badge)"
                  class="flex flex-col items-center justify-between p-3.5 rounded-2xl transition-all cursor-pointer border-2 bg-white relative group hover:z-30"
                  :class="isSelected(badge) 
                    ? 'border-[#E8192C] bg-red-50/20 shadow-md ring-2 ring-[#E8192C]/20' 
                    : 'border-slate-100 hover:border-slate-300 hover:shadow-sm'"
                >
                  <div class="my-2 transition-transform group-hover:scale-105">
                    <BadgeIcon :badge="badge" size="xl" :showHoverCard="true" />
                  </div>

                  <span class="text-xs font-medium text-center text-slate-800 line-clamp-2 leading-tight">
                    {{ badge.name }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-white border-t border-slate-100 flex items-center justify-between gap-4 flex-shrink-0">
          <div class="flex items-center gap-2.5 ml-auto">
            <button 
              @click="close" 
              class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-bold text-xs hover:bg-slate-100 transition-colors cursor-pointer"
            >
              Hủy
            </button>
            <button 
              @click="save" 
              :disabled="saving" 
              class="px-6 py-2.5 rounded-xl bg-[#E8192C] hover:bg-[#c91223] text-white font-bold text-xs shadow-md transition-all active:scale-95 cursor-pointer flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
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
    </div>
  </transition>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axiosInstance from '@/utils/httpRequest.js'
import { toast } from 'vue3-toastify'
import BadgeIcon from '@/components/atoms/BadgeIcon.vue'
import defaultAvatar from '@/assets/images/default-image.jpeg'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  userId: {
    type: [Number, String],
    required: true
  },
  userAvatar: {
    type: String,
    default: null
  },
  userName: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:show', 'saved'])

const activeTab = ref('avatar') // 'avatar' | 'featured'
const loading = ref(false)
const saving = ref(false)
const badges = ref([])
const selectedIds = ref([]) // array of user_badge_id for profile featured (NO LIMIT)
const avatarBadgeId = ref(null) // user_badge_id for avatar (MAX 1)

const unlockedBadges = computed(() => {
  return badges.value.filter(b => b.is_unlocked)
})

const selectedBadges = computed(() => {
  return selectedIds.value.map(id => unlockedBadges.value.find(b => b.user_badge_id === id)).filter(Boolean)
})

const avatarBadge = computed(() => {
  return unlockedBadges.value.find(b => b.user_badge_id === avatarBadgeId.value)
})

const isSelected = (badge) => {
  return selectedIds.value.includes(badge.user_badge_id)
}

// Ghim vào hồ sơ: toggle thoải mái, KHÔNG giới hạn số lượng!
const toggleSelection = (badge) => {
  const index = selectedIds.value.indexOf(badge.user_badge_id)
  if (index > -1) {
    selectedIds.value.splice(index, 1)
  } else {
    selectedIds.value.push(badge.user_badge_id)
  }
}

// Khoe lên avatar: tối đa 1 cái, bấm lại là bỏ
const toggleAvatar = (badge) => {
  if (avatarBadgeId.value === badge.user_badge_id) {
    avatarBadgeId.value = null
  } else {
    avatarBadgeId.value = badge.user_badge_id
  }
}

const fetchBadges = async () => {
  try {
    loading.value = true
    const response = await axiosInstance.get(`/user/${props.userId}/badges`)
    if (response.data && response.data.data) {
      badges.value = response.data.data
      
      // Avatar badge (max 1)
      const avatarItem = badges.value.find(b => b.is_unlocked && (b.is_avatar || b.is_primary))
      avatarBadgeId.value = avatarItem ? avatarItem.user_badge_id : null

      // Profile featured badges (bỏ giới hạn slice(0, 3))
      selectedIds.value = badges.value
        .filter(b => b.is_unlocked && b.is_featured)
        .sort((a, b) => (a.featured_order ?? a.id) - (b.featured_order ?? b.id))
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
  try {
    saving.value = true
    await axiosInstance.put('/user/badges/featured', {
      user_badge_ids: selectedIds.value,
      avatar_user_badge_id: avatarBadgeId.value
    })
    toast.success('Đã lưu cấu hình huy hiệu thành công!')
    emit('saved')
    close()
  } catch (error) {
    console.error('Error saving badges:', error)
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
