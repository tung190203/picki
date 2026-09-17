<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="isOpen"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        @click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[95vh] sm:max-h-[90vh] overflow-hidden animate-scaleIn relative flex flex-col">
          <!-- Header -->
          <div class="px-4 sm:px-6 pt-4 sm:pt-5 flex items-center justify-between border-b border-gray-50 flex-shrink-0">
            <h2 class="text-base sm:text-[20px] font-bold text-[#374151]">Bổ nhiệm</h2>
            <button @click="closeModal" class="p-2 text-gray-500 hover:text-gray-700 transition-colors">
              <XMarkIcon class="w-5 h-5 sm:w-7 sm:h-7" />
            </button>
          </div>

          <!-- Content -->
          <div class="px-4 sm:px-6 pb-4 sm:pb-6 pt-3 overflow-y-auto flex-1">
            <!-- Member Info Header -->
            <div v-if="member" class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100">
              <div class="relative">
                <img :src="member.user?.avatar_url || 'https://picki.vn/images/default-avatar.png'" 
                  :alt="member.user?.full_name"
                  class="w-14 h-14 rounded-full object-cover border-2 border-gray-100">
                <!-- VP Score Badge -->
                <div class="absolute -bottom-1 -left-1 w-6 h-6 bg-[#D72D36] rounded-full flex items-center justify-center border-2 border-white">
                  <span class="text-white text-[9px] font-bold">{{ getVpScore(member.user) }}</span>
                </div>
              </div>
              <div class="flex-1">
                <h3 class="text-[16px] font-bold text-[#374151] mb-1">{{ member.user?.full_name || 'N/A' }}</h3>
                <div class="flex items-center gap-2">
                  <span class="text-[12px] text-gray-500">{{ getRoleLabel(member.role) }}</span>
                  <span class="text-gray-300">•</span>
                  <span class="text-[12px] text-gray-400">Không rõ thời gian tham gia CLB</span>
                </div>
              </div>
            </div>

            <!-- Section Title -->
            <h4 class="text-[13px] font-bold text-gray-400 uppercase tracking-wide mb-4">VAI TRÒ TRONG CLB</h4>

            <!-- Role Options -->
            <div class="space-y-1 mb-4">
              <button
                v-for="role in availableRoles"
                :key="role.value"
                @click="selectedRole = role.value"
                class="w-full text-left p-4 border-2 rounded-2xl transition-all hover:border-[#D72D36] hover:bg-[#FEF2F2]"
                :class="selectedRole === role.value ? 'border-[#D72D36] bg-[#FEF2F2]' : 'border-[#EDEEF2]'">
                <div class="flex items-center gap-3">
                  <!-- Role Info -->
                  <div class="flex-1">
                    <h3 class="font-semibold text-[#374151] mb-1">{{ role.label }}</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">{{ role.description }}</p>
                  </div>
                  <!-- Radio Button -->
                  <div class="mt-0.5">
                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                      :class="selectedRole === role.value ? 'border-[#D72D36]' : 'border-gray-300'">
                      <div v-if="selectedRole === role.value" class="w-3 h-3 rounded-full bg-[#D72D36]"></div>
                    </div>
                  </div>
                </div>
              </button>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3">
              <button @click="closeModal"
                class="flex-1 py-3 bg-[#F3F4F6] text-[#374151] font-bold rounded-full transition-all hover:bg-gray-200 active:scale-[0.98]">
                Đóng
              </button>
              <button @click="handleConfirm"
                :disabled="!selectedRole"
                class="flex-1 py-3 font-bold rounded-full transition-all active:scale-[0.98]"
                :class="selectedRole 
                  ? 'bg-[#D72D36] text-white hover:bg-[#c4252e]' 
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'">
                Xác nhận
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: Boolean,
  member: { type: Object, default: null },
  currentUserRole: { type: String, default: null }
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const selectedRole = ref(null)

const isOpen = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

// Watch for modal opening and set default selected role
watch(() => props.modelValue, (newValue) => {
  if (newValue && props.member) {
    selectedRole.value = props.member.role
  }
})

// Role options based on current user role
const roleOptions = [
  {
    value: 'admin',
    label: 'Quản trị viên',
    description: 'Người tạo CLB, có quyền cao nhất, có thể phân quyền cho các thành viên khác'
  },
  {
    value: 'manager',
    label: 'Quản lý',
    description: 'Có quyền tạo kèo, tạo sự kiện, giải đấu, nhập điểm nhanh cho CLB'
  },
  {
    value: 'treasurer',
    label: 'Thủ quỹ',
    description: 'Có quyền tạo và kiểm duyệt các khoản thu chi'
  },
  {
    value: 'secretary',
    label: 'Thư ký',
    description: 'Có quyền tương đương với quản trị viên'
  },
  {
    value: 'member',
    label: 'Thành viên',
    description: 'Có quyền tiếp cận và tham gia mọi hoạt động của CLB'
  }
]

const availableRoles = computed(() => {
  let roles = roleOptions
  
  // Secretary cannot assign admin role
  if (props.currentUserRole === 'secretary') {
    roles = roleOptions.filter(role => role.value !== 'admin')
  }
  
  return roles
})

const closeModal = () => {
  selectedRole.value = null
  isOpen.value = false
}

const handleConfirm = () => {
  if (selectedRole.value && props.member) {
    emit('confirm', {
      memberId: props.member.id,
      role: selectedRole.value
    })
    closeModal()
  }
}

const getVpScore = (user) => {
  const pickleball = user?.sports?.find(s => s.sport_id === 1 || s.sport_name === 'Pickleball');
  const score = pickleball?.scores?.vndupr_score;
  return score ? Number(score).toFixed(1) : '0';
}

const getRoleLabel = (role) => {
  const labels = {
    'admin': 'Quản trị viên',
    'manager': 'Quản lý',
    'treasurer': 'Thủ quỹ',
    'secretary': 'Thư ký',
    'member': 'Thành viên'
  }
  return labels[role] || role
}
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

@keyframes scaleIn {
  from {
    transform: scale(0.95);
    opacity: 0;
  }

  to {
    transform: scale(1);
    opacity: 1;
  }
}

.animate-scaleIn {
  animation: scaleIn 0.3s ease-out;
}
</style>
