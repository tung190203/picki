<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[9999] p-3 sm:p-4" @click.self="closeModal">
        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[95vh] sm:max-h-[90vh]">
          <!-- Header -->
          <div class="p-4 sm:p-6 pb-3 sm:pb-4 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10 flex-shrink-0">
            <h3 class="text-lg sm:text-xl font-bold text-gray-900">Chọn người kế vị</h3>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
              <XMarkIcon class="w-5 h-5 sm:w-6 sm:h-6" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-4 sm:p-6 overflow-y-auto custom-scrollbar flex-1">
            <p class="text-sm text-gray-500 mb-6">
              Vui lòng chọn một thành viên để bàn giao quyền Quản trị viên trước khi rời câu lạc bộ.
            </p>

            <!-- Search -->
            <div class="relative mb-6">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
              </div>
              <input 
                v-model="searchQuery"
                type="text" 
                placeholder="Tìm tên thành viên"
                class="block w-full pl-10 pr-3 py-2.5 border border-[#EDEEF2] rounded-lg bg-[#F8F9FB] text-sm focus:outline-none focus:ring-2 focus:ring-[#D72D36]/10 focus:border-[#D72D36] transition-all">
            </div>

            <!-- Member List -->
            <div class="space-y-2">
              <div v-if="loading" class="flex justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#D72D36]"></div>
              </div>

              <template v-else-if="filteredMembers.length > 0">
                <div 
                  v-for="member in filteredMembers" 
                  :key="member.id"
                  @click="selectedUserId = member.user_id"
                  class="flex items-center justify-between p-3 rounded-xl border transition-all cursor-pointer group"
                  :class="selectedUserId === member.user_id 
                    ? 'border-[#D72D36] bg-[#FBEAEB]' 
                    : 'border-gray-100 hover:border-gray-200'"
                >
                  <div class="flex items-center gap-3">
                    <img :src="member.user?.avatar_url || defaultAvatar" class="w-14 h-14 rounded-full object-cover" />
                    <div>
                      <p class="font-semibold text-gray-900">{{ member.user?.full_name }}</p>
                      <p class="text-xs text-gray-500">{{ getRoleLabel(member.role) }}</p>
                    </div>
                  </div>
                  <div 
                    class="w-5 h-5 rounded-full border flex items-center justify-center transition-colors"
                    :class="selectedUserId === member.user_id 
                      ? 'bg-[#D72D36] border-[#D72D36]' 
                      : 'border-gray-300'"
                  >
                    <CheckIcon v-if="selectedUserId === member.user_id" class="w-3 h-3 text-white" />
                  </div>
                </div>
              </template>

              <div v-else class="text-center py-12 text-gray-400 italic">
                Không tìm thấy thành viên nào
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="p-6 border-t border-gray-100 bg-gray-50 flex gap-3">
            <Button 
                size="md" 
                color="white" 
                class="flex-1 border border-gray-200"
                @click="closeModal"
            >
                Hủy
            </Button>
            <Button 
                size="md" 
                color="danger" 
                class="flex-1 bg-[#D72D36] text-white" 
                :disabled="!selectedUserId || isSubmitting"
                @click="confirm"
            >
                <span v-if="isSubmitting">Đang xử lý...</span>
                <span v-else>Xác nhận chuyển nhượng</span>
            </Button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { XMarkIcon, MagnifyingGlassIcon, CheckIcon } from '@heroicons/vue/24/outline'
import Button from '@/components/atoms/Button.vue'
import * as ClubService from '@/service/club.js'
import { useUserStore } from '@/store/auth'
import { storeToRefs } from 'pinia'

const defaultAvatar = "/images/default-avatar.png";

const props = defineProps({
  modelValue: Boolean,
  clubId: {
    type: [String, Number],
    required: true
  },
  isSubmitting: Boolean
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)

const searchQuery = ref('')
const selectedUserId = ref(null)
const loading = ref(false)
const members = ref([])

const fetchMembers = async () => {
  if (!props.clubId) return
  loading.value = true
  try {
    const response = await ClubService.getMembers(props.clubId, {
      status: 'active',
      per_page: 100 // Load many for selection
    })
    // Filter out current user (the one leaving)
    members.value = (response.data.members || []).filter(m => m.user_id !== getUser.value.id)
  } catch (error) {
    console.error('Error fetching members for transfer:', error)
  } finally {
    loading.value = false
  }
}

const filteredMembers = computed(() => {
  if (!searchQuery.value) return members.value
  const query = searchQuery.value.toLowerCase()
  return members.value.filter(m => 
    m.user?.full_name?.toLowerCase().includes(query)
  )
})

const getRoleLabel = (role) => {
  const labels = {
    'admin': 'Admin',
    'manager': 'Quản lý',
    'treasurer': 'Thủ quỹ',
    'secretary': 'Thư ký',
    'member': 'Thành viên'
  }
  return labels[role] || role
}

const closeModal = () => {
  emit('update:modelValue', false)
}

const confirm = () => {
  if (selectedUserId.value) {
    emit('confirm', selectedUserId.value)
  }
}

watch(() => props.modelValue, (newVal) => {
  if (newVal) {
    selectedUserId.value = null
    searchQuery.value = ''
    fetchMembers()
  }
})

onMounted(() => {
  if (props.modelValue) {
    fetchMembers()
  }
})
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .bg-white,
.modal-leave-active .bg-white {
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white {
  transform: scale(0.95);
  opacity: 0;
}

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
