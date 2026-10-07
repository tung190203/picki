<template>
  <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
    <div v-if="loading" class="flex justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4392E0]"></div>
    </div>
    
    <div v-else-if="badges.length === 0" class="text-center py-8 text-gray-500">
      Chưa có huy hiệu nào trong hệ thống.
    </div>

    <div v-else>
      <div v-for="(group, typeName) in groupedBadges" :key="typeName" class="mb-8 last:mb-0 relative hover:z-50">
        <div class="flex items-center gap-2 mb-4">
          <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider">{{ typeName }}</h3>
          <div v-if="group[0]?.type_description" class="group/tooltip relative flex items-center justify-center cursor-help">
            <span class="material-symbols-outlined text-[16px] text-slate-400 hover:text-slate-600 transition-colors">help</span>
            <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 hidden group-hover/tooltip:block w-56 p-2.5 bg-white text-xs rounded-lg shadow-lg text-center z-[100] normal-case tracking-normal font-normal">
              {{ group[0].type_description }}
              <div class="absolute left-1/2 -translate-x-1/2 top-full w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-slate-800"></div>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6">
          <div 
            v-for="badge in group" 
            :key="badge.id"
            class="flex flex-col items-center gap-2 group relative hover:z-50"
          >
            <!-- Badge Icon -->
            <div class="relative cursor-pointer transition-transform hover:scale-110 hover:z-50">
              <BadgeIcon :badge="badge" size="2xl" :showBadge="true" class="drop-shadow-md" :showHoverCard="true" />
            </div>
            
            <!-- Badge Info -->
            <div class="text-center">
              <p class="text-sm font-bold" :class="badge.is_unlocked ? 'text-gray-800' : 'text-gray-400'">{{ badge.name }}</p>
              <p v-if="badge.is_unlocked && badge.acquired_at" class="text-[10px] text-gray-500 mt-1">
                {{ formatDate(badge.acquired_at) }}
              </p>
              <p v-else-if="!badge.is_unlocked" class="text-[10px] text-gray-400 mt-1">
                Chưa mở khóa
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { toast } from 'vue3-toastify'
import axiosInstance from '@/utils/httpRequest.js'
import BadgeIcon from '@/components/atoms/BadgeIcon.vue'
import dayjs from 'dayjs'
import { MapPinIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  userId: {
    type: Number,
    required: true
  },
  isOwner: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update'])

const badges = ref([])
const loading = ref(false)

const groupedBadges = computed(() => {
  const groups = {}
  badges.value.forEach(badge => {
    const typeName = badge.type_name || badge.type || 'Khác'
    if (!groups[typeName]) {
      groups[typeName] = []
    }
    groups[typeName].push(badge)
  })
  return groups
})

const fetchBadges = async () => {
  if (!props.userId) return
  
  loading.value = true
  try {
    const response = await axiosInstance.get(`/user/${props.userId}/badges`)
    badges.value = response.data.data
  } catch (error) {
    console.error('Lỗi khi tải huy hiệu:', error)
  } finally {
    loading.value = false
  }
}

const togglePin = async (badge) => {
  if (!badge.is_unlocked) return

  // Count current featured
  const currentFeaturedIds = badges.value
    .filter(b => b.is_featured && b.id !== badge.id)
    .map(b => b.user_badge_id)

  let newUserBadgeIds = [...currentFeaturedIds]

  if (badge.is_featured) {
    // Unpin
    badge.is_featured = false
  } else {
    // Pin (limit to 3)
    if (newUserBadgeIds.length >= 3) {
      toast.warning('Bạn chỉ có thể ghim tối đa 3 huy hiệu!')
      return
    }
    newUserBadgeIds.push(badge.user_badge_id)
    badge.is_featured = true
  }

  try {
    await axiosInstance.put('/user/badges/featured', {
      user_badge_ids: newUserBadgeIds
    })
    toast.success('Đã cập nhật huy hiệu ghim')
    emit('update')
  } catch (error) {
    toast.error('Có lỗi xảy ra khi cập nhật')
    // Revert visually on error
    badge.is_featured = !badge.is_featured
  }
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return dayjs(dateString).format('DD/MM/YYYY')
}

watch(() => props.userId, (newId) => {
  if (newId) fetchBadges()
})

onMounted(() => {
  fetchBadges()
})
</script>
