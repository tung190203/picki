<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[9999] p-3 sm:p-4" @click.self="closeModal">
        <div class="bg-white rounded-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[95vh] sm:max-h-[90vh]">
          <!-- Header -->
          <div class="p-4 sm:p-6 pb-3 sm:pb-4 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10 flex-shrink-0">
            <h3 class="text-lg sm:text-xl font-bold text-gray-900">Tạo thông báo</h3>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
              <XMarkIcon class="w-5 h-5 sm:w-6 sm:h-6" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-4 sm:p-6 overflow-y-auto custom-scrollbar">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <!-- Left Column -->
              <div class="space-y-6">
                <!-- Title -->
                <div>
                  <label class="block font-semibold text-[#3E414C] mb-2">Tiêu đề thông báo</label>
                  <input 
                    v-model="form.title" 
                    type="text" 
                    class="w-full px-4 py-3 bg-[#EDEEF2] border-none rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 transition-colors placeholder-[#9EA2B3]" 
                    placeholder="Ví dụ: Thay đổi lịch đấu" 
                  />
                </div>

                <!-- Type -->
                <div class="relative" ref="typeDropdownRef">
                  <label class="block font-semibold text-[#3E414C] mb-2">Loại thông báo</label>
                  
                  <!-- Dropdown Trigger -->
                  <div 
                    class="w-full flex items-center justify-between px-4 py-3 bg-white border border-[#D72D36]/30 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
                    :class="{'ring-2 ring-[#D72D36]/20 border-[#D72D36]': isTypeDropdownOpen}"
                    @click="toggleTypeDropdown"
                  >
                     <div class="flex items-center gap-3">
                        <component v-if="selectedTypeObj.icon" :is="selectedTypeObj.icon" class="w-5 h-5 text-gray-500" />
                        <span class="font-bold" :class="selectedTypeObj.label ? 'text-gray-700' : 'text-gray-400'">{{ selectedTypeObj.label || 'Chọn loại thông báo' }}</span>
                     </div>
                     <ChevronDownIcon class="h-4 w-4 text-gray-500 transition-transform duration-200" :class="{'rotate-180': isTypeDropdownOpen}" />
                  </div>

                  <!-- Dropdown Menu -->
                  <Transition
                      enter-active-class="transition duration-100 ease-out"
                      enter-from-class="transform scale-95 opacity-0"
                      enter-to-class="transform scale-100 opacity-100"
                      leave-active-class="transition duration-75 ease-in"
                      leave-from-class="transform scale-100 opacity-100"
                      leave-to-class="transform scale-95 opacity-0"
                  >
                    <div v-if="isTypeDropdownOpen" class="absolute z-20 w-full mt-2 bg-white rounded-xl shadow-xl border border-gray-100 py-2 overflow-hidden">
                        <div 
                            v-for="type in notificationTypes" 
                            :key="type.value"
                            class="px-4 py-3 cursor-pointer flex items-center gap-3 transition-colors"
                            :class="form.club_notification_type_id === type.value ? 'bg-[#fff5f5]' : 'hover:bg-gray-50'"
                            @click="selectType(type.value)"
                        >
                            <component 
                                :is="type.icon" 
                                class="w-5 h-5" 
                                :class="form.club_notification_type_id === type.value ? 'text-[#D72D36]' : 'text-[#3E414C]'" 
                            />
                            <span 
                                class="font-normal"
                                :class="form.club_notification_type_id === type.value ? 'text-[#D72D36] font-normal' : 'text-[#3E414C]'"
                            >
                                {{ type.label }}
                            </span>
                        </div>
                    </div>
                  </Transition>
                </div>

                <!-- Content -->
                <div>
                  <div class="flex justify-between mb-2">
                    <label class="block font-semibold text-[#3E414C]">Nội dung thông báo</label>
                    <span class="text-xs text-gray-400">{{ form.content.length }}/300</span>
                  </div>
                  <textarea 
                    v-model="form.content" 
                    rows="6" 
                    maxlength="300" 
                    class="w-full px-4 py-3 bg-[#EDEEF2] border-none rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 transition-colors resize-none placeholder-[#9EA2B3]" 
                    placeholder="Nhập nội dung chi tiết"
                  ></textarea>
                </div>
              </div>

              <!-- Right Column -->
              <div class="space-y-5">
                <!-- Target Audience -->
                <div>
                   <label class="block font-semibold text-[#838799] mb-4 uppercase">ĐỐI TƯỢNG NHẬN</label>
                   <div class="space-y-3">
                      <!-- Option 1: Admin -->
                      <label class="flex items-center p-4 border rounded-2xl cursor-pointer transition-all hover:bg-gray-50" :class="target === 'admin' ? 'border-[#ffcccb] bg-[#fff5f5]' : 'border-gray-100 bg-white'">
                        <div class="w-10 h-10 rounded-full bg-[#FBEAEB] flex items-center justify-center mr-4 text-[#D72D36]">
                          <ShieldCheckIcon class="w-5 h-5" />
                        </div>
                         <div class="flex-1">
                          <span class="block font-bold text-gray-800">Ban quản trị</span>
                          <span class="block text-xs text-[#D72D36] font-medium">Chỉ gửi cho quản trị viên</span>
                        </div>
                        <div class="relative flex items-center">
                           <input type="radio" v-model="target" value="admin" class="peer h-5 w-5 cursor-pointer appearance-none rounded-full border border-gray-300 checked:border-[#D72D36] transition-all" />
                           <span class="absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#D72D36] opacity-0 transition-opacity peer-checked:opacity-100"></span>
                        </div>
                      </label>
                      <!-- Option 2: All Members -->
                      <label class="flex items-center p-4 border rounded-2xl cursor-pointer transition-all hover:bg-gray-50" :class="target === 'all' ? 'border-[#ffcccb] bg-[#fff5f5]' : 'border-gray-100 bg-white'">
                        <div class="w-10 h-10 rounded-full bg-[#EDEEF2] flex items-center justify-center mr-4 text-gray-600">
                          <UserGroupIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1">
                          <span class="block font-bold text-gray-800">Tất cả thành viên</span>
                          <span class="block text-xs text-gray-500">Gửi đến toàn bộ {{ club.quantity_members || 0 }} thành viên</span>
                        </div>
                        <div class="relative flex items-center">
                           <input type="radio" v-model="target" value="all" class="peer h-5 w-5 cursor-pointer appearance-none rounded-full border border-gray-300 checked:border-[#D72D36] transition-all" />
                           <span class="absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#D72D36] opacity-0 transition-opacity peer-checked:opacity-100"></span>
                        </div>
                      </label>

                       <!-- Option 3: Unpaid Members -->
                       <div class="space-y-3">
                            <label class="flex items-center p-4 border rounded-2xl cursor-pointer transition-all hover:bg-gray-50" :class="target === 'unpaid' ? 'border-[#ffcccb] bg-[#fff5f5]' : 'border-gray-100 bg-white'">
                                <div class="w-10 h-10 rounded-full bg-[#EDEEF2] flex items-center justify-center mr-4 text-gray-600">
                                    <CurrencyDollarIcon class="w-5 h-5" />
                                </div>
                                <div class="flex-1">
                                    <span class="block font-bold text-gray-800">Thành viên chưa nộp quỹ</span>
                                    <span class="block text-xs text-gray-500">Nhắc nhở {{ selectedMemberIds.size }} thành viên</span>
                                </div>
                                <div class="relative flex items-center">
                                    <input type="radio" v-model="target" value="unpaid" class="peer h-5 w-5 cursor-pointer appearance-none rounded-full border border-gray-300 checked:border-[#D72D36] transition-all" />
                                    <span class="absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#D72D36] opacity-0 transition-opacity peer-checked:opacity-100"></span>
                                </div>
                            </label>

                            <!-- Member Selection Dropdown -->
                            <div v-if="target === 'unpaid'" class="w-full">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Chọn thành viên nhận thông báo</label>
                                
                                <div class="relative" ref="memberDropdownRef">
                                    <!-- Trigger Button -->
                                    <div 
                                        class="w-full bg-white border border-gray-300 rounded-lg px-4 py-3 cursor-pointer flex items-center justify-between hover:border-[#D72D36] transition-colors"
                                        :class="{'ring-2 ring-[#D72D36]/20 border-[#D72D36]': isMemberDropdownOpen}"
                                        @click="toggleMemberDropdown"
                                    >
                                        <div class="flex items-center gap-2 overflow-hidden">
                                            <span v-if="selectedMemberIds.size === 0" class="text-gray-500">Chọn thành viên...</span>
                                            <span v-else class="text-gray-900 font-medium truncate">
                                                Đã chọn {{ selectedMemberIds.size }} thành viên
                                            </span>
                                        </div>
                                        <ChevronDownIcon 
                                            class="w-4 h-4 text-gray-400 transition-transform duration-200"
                                            :class="{'rotate-180': isMemberDropdownOpen}"
                                        />
                                    </div>

                                    <!-- Dropdown Panel -->
                                    <Transition
                                        enter-active-class="transition duration-100 ease-out"
                                        enter-from-class="transform scale-95 opacity-0"
                                        enter-to-class="transform scale-100 opacity-100"
                                        leave-active-class="transition duration-75 ease-in"
                                        leave-from-class="transform scale-100 opacity-100"
                                        leave-to-class="transform scale-95 opacity-0"
                                    >
                                        <div v-if="isMemberDropdownOpen" class="absolute z-50 w-full mt-2 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden flex flex-col max-h-[320px]">
                                            <!-- Sticky Search Header -->
                                            <div class="p-3 border-b border-gray-100 bg-white sticky top-0 z-10">
                                                <div class="relative">
                                                    <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                                                    <input 
                                                        ref="memberSearchInput"
                                                        v-model="memberQuery"
                                                        type="text" 
                                                        class="w-full pl-9 pr-3 py-2 bg-gray-50 border-none rounded-lg text-sm focus:ring-0 focus:bg-[#EDEEF2] transition-colors placeholder-gray-400"
                                                        placeholder="Tìm tên hoặc email..."
                                                    />
                                                </div>
                                            </div>

                                            <!-- Members List -->
                                            <div class="overflow-y-auto custom-scrollbar p-1">
                                                <div class="space-y-1">
                                                     <div 
                                                        v-for="member in filteredMembers" 
                                                        :key="member.id"
                                                        class="px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer flex items-center justify-between transition-colors group"
                                                        @click="toggleMemberSelection(member)"
                                                    >
                                                        <div class="flex items-center gap-3 overflow-hidden">
                                                            <div class="relative">
                                                                <img :src="member.user.avatar_url || 'https://ui-avatars.com/api/?name=' + member.user.full_name" class="w-10 h-10 rounded-full object-cover border border-gray-100" />
                                                            </div>
                                                            <div class="flex-col overflow-hidden">
                                                                <p class="text-sm font-semibold text-gray-800 truncate group-hover:text-[#D72D36] transition-colors">{{ member.user.full_name }}</p>
                                                                <p class="text-xs text-gray-400 truncate">{{ member.user.email }}</p>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Checkbox -->
                                                        <div 
                                                            class="w-5 h-5 min-w-[20px] rounded-full border flex items-center justify-center transition-all ml-2"
                                                            :class="isSelected(member.id) ? 'bg-[#D72D36] border-[#D72D36]' : 'border-gray-200 bg-white group-hover:border-[#D72D36]/50'"
                                                        >
                                                             <CheckIcon v-if="isSelected(member.id)" class="w-3.5 h-3.5 text-white stroke-[3]" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Footer Actions -->
                                            <div class="p-3 border-t border-gray-100 bg-gray-50 flex justify-between items-center text-xs font-medium sticky bottom-0 z-10">
                                                <span class="text-gray-500">{{ selectedMemberIds.size }} đã chọn</span>
                                                <div class="flex gap-3">
                                                    <button 
                                                        v-if="filteredMembers.length > 0 && selectedMemberIds.size !== filteredMembers.length"
                                                        class="text-[#D72D36] hover:text-[#b91c1c] transition-colors"
                                                        @click.stop="selectAllMembers"
                                                    >
                                                        Chọn tất cả
                                                    </button>
                                                    <button 
                                                        v-if="selectedMemberIds.size > 0"
                                                        class="text-gray-500 hover:text-gray-700 transition-colors" 
                                                        @click.stop="deselectAllMembers"
                                                    >
                                                        Bỏ chọn
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>
                       </div>
                   </div>
                </div>

                <!-- Image Upload -->

                 <div class="border-2 border-dashed border-gray-200 rounded-xl bg-white p-6 flex flex-col items-center justify-center cursor-pointer hover:border-[#D72D36]/50 hover:bg-gray-50 transition-all relative overflow-hidden h-40" @click="triggerFileInput">
                    <template v-if="previewUrl">
                         <img :src="previewUrl" class="absolute inset-0 w-full h-full object-cover" alt="Preview" />
                         <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                             <p class="text-white font-medium">Thay đổi ảnh</p>
                         </div>
                    </template>
                    <template v-else>
                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mb-2 text-gray-400">
                             <PhotoIcon class="w-5 h-5" />
                        </div>
                         <p class="text-sm font-bold text-gray-700">Nhấn để tải ảnh lên</p>
                         <p class="text-xs text-gray-400 mt-1">PNG, JPG, GIF (Tối đa 5MB)</p>
                    </template>
                    <input type="file" ref="fileInput" class="hidden" accept="image/*" @change="handleFileChange" />
                </div>
                
                 <!-- Apply to other clubs -->
                 <Toggle 
                    :value="form.is_pinned" 
                    label="Ghim thông báo" 
                    description="Ghim thông báo này để hiển thị ở đầu danh sách ghim" 
                    @update="val => form.is_pinned = val" 
                 />
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="p-6 pt-4 bg-white sticky bottom-0 z-10 text-right border-t border-gray-100">
            <Button size="md" color="danger" class="bg-[#D72D36] hover:bg-[#c9252e] text-white rounded-[4px] px-6 py-3 font-semibold" @click="handleSubmit" :disabled="isLoading || !form.title">
                <span v-if="isLoading">Đang gửi...</span>
                <span v-else>Gửi thông báo</span>
            </Button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { XMarkIcon, BellIcon, ChevronDownIcon, PhotoIcon, ExclamationTriangleIcon, CheckIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import { UserGroupIcon, CurrencyDollarIcon } from '@heroicons/vue/24/solid'
import FinancialIcon from "@/assets/images/money-atm.svg";
import DateIcon from "@/assets/images/date.svg";
import Button from '@/components/atoms/Button.vue'
import Toggle from '@/components/atoms/Toggle.vue'
import ShieldCheckIcon from "@/assets/images/shield_check.svg";

const props = defineProps({
  modelValue: Boolean,
  club: {
    type: Object,
    default: () => ({})
  },
  isLoading: Boolean,
  notificationType: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'create'])
const fileInput = ref(null)
const typeDropdownRef = ref(null)
const isTypeDropdownOpen = ref(false)

// Member selection refs
const memberDropdownRef = ref(null)
const isMemberDropdownOpen = ref(false)
const memberQuery = ref('')
const selectedMemberIds = ref(new Set())
const memberSearchInput = ref(null)

const previewUrl = ref(null)
const target = ref('admin')

const form = reactive({
    title: '',
    club_notification_type_id: null,
    content: '',
    attachment: null,
    is_pinned: false,
    status: 'sent'
})

const iconMapping = {
    'bell': BellIcon,
    'calendar': DateIcon,
    'currency': FinancialIcon,
    'users': UserGroupIcon,
    'alert': ExclamationTriangleIcon
}

const notificationTypes = computed(() => {
    const types = Array.isArray(props.notificationType) ? props.notificationType : []
    return types.map(type => ({
        value: type.id,
        label: type.name,
        icon: iconMapping[type.icon] || BellIcon
    }))
})

const selectedTypeObj = computed(() => {
    if (!notificationTypes.value.length) return {}
    return notificationTypes.value.find(t => t.value === form.club_notification_type_id) || notificationTypes.value[0]
})

const members = computed(() => props.club.members || [])

const filteredMembers = computed(() => {
    if (!memberQuery.value) return members.value
    const query = memberQuery.value.toLowerCase()
    return members.value.filter(member => 
        (member.user.full_name || '').toLowerCase().includes(query) || 
        (member.user.email || '').toLowerCase().includes(query)
    )
})

const selectedMembersList = computed(() => {
    return members.value.filter(m => selectedMemberIds.value.has(m.id))
})


const toggleTypeDropdown = () => {
    isTypeDropdownOpen.value = !isTypeDropdownOpen.value
}

const selectType = (value) => {
    form.club_notification_type_id = value
    isTypeDropdownOpen.value = false
}

const handleClickOutside = (event) => {
    if (typeDropdownRef.value && !typeDropdownRef.value.contains(event.target)) {
        isTypeDropdownOpen.value = false
    }
    if (memberDropdownRef.value && !memberDropdownRef.value.contains(event.target)) {
        isMemberDropdownOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

const closeModal = () => {
  emit('update:modelValue', false)
}

const triggerFileInput = () => {
    fileInput.value.click()
}

const handleFileChange = (event) => {
    const file = event.target.files[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = (e) => {
        previewUrl.value = e.target.result
        form.attachment = file
    }
    reader.readAsDataURL(file)
}

const handleSubmit = () => {
    let userIds = []
    
    if (target.value === 'admin') {
        const adminRoles = ['admin', 'manager', 'treasurer', 'secretary']
        userIds = members.value
            .filter(m => adminRoles.includes(m.role))
            .map(m => m.user?.id)
            .filter(id => id)
    } else if (target.value === 'all') {
        userIds = members.value
            .map(m => m.user?.id)
            .filter(id => id)
    } else if (target.value === 'unpaid') {
        userIds = members.value
            .filter(m => selectedMemberIds.value.has(m.id))
            .map(m => m.user?.id)
            .filter(id => id)
    }

    const payload = { 
        ...form,
        user_ids: userIds
    }
    emit('create', payload)
}

const toggleMemberDropdown = () => {
    isMemberDropdownOpen.value = !isMemberDropdownOpen.value
    if (isMemberDropdownOpen.value) {
        nextTick(() => {
            if (memberSearchInput.value) {
                memberSearchInput.value.focus()
            }
        })
    }
}

const toggleMemberSelection = (member) => {
    if (selectedMemberIds.value.has(member.id)) {
        selectedMemberIds.value.delete(member.id)
    } else {
        selectedMemberIds.value.add(member.id)
    }
}

const isSelected = (id) => selectedMemberIds.value.has(id)

const selectAllMembers = () => {
    filteredMembers.value.forEach(m => selectedMemberIds.value.add(m.id))
}

const deselectAllMembers = () => {
    selectedMemberIds.value.clear()
}

watch(
  () => notificationTypes.value,
  (types) => {
    if (types.length && !form.club_notification_type_id) {
      form.club_notification_type_id = types[0].value
    }
  },
  { immediate: true }
)

const resetForm = () => {
    form.title = ''
    form.club_notification_type_id = notificationTypes.value[0]?.value || null
    form.content = ''
    form.attachment = null
    form.is_pinned = false
    form.status = 'sent'
    previewUrl.value = null
    target.value = 'admin'
    selectedMemberIds.value.clear()
    memberQuery.value = ''
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        resetForm()
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
