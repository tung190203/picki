<template>
  <div class="bg-white">
    <!-- Header with Search & Filter Tabs & Add Virtual Member Button -->
    <div class="flex flex-col gap-4 mb-6">
      <div class="flex items-center gap-3">
        <div class="relative flex-1">
          <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
          </div>
          <input 
            v-model="searchQuery"
            type="text" 
            placeholder="Tìm tên, trình độ..."
            class="block w-full h-11 pl-10 pr-4 border border-[#EDEEF2] rounded-xl bg-[#EDEEF2] text-sm text-gray-900 font-medium placeholder-[#9EA2B3] focus:outline-none focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 transition-all">
        </div>

        <button v-if="canManageMembers" @click="showVirtualModal = true"
          class="h-11 px-5 bg-[#D72D36] text-white text-sm font-bold rounded-xl hover:bg-[#c4252e] transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap shadow-sm">
          <PlusIcon class="w-4 h-4 stroke-[2.5]" />
          Thêm thành viên
        </button>
      </div>

      <!-- Member Filter Sub-tabs -->
      <div class="flex items-center space-x-2 border-b border-gray-100 pb-2">
        <button @click="memberFilter = 'all'"
          class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
          :class="memberFilter === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
          Tất cả ({{ totalMembers + virtualMembers.length }})
        </button>
        <button @click="memberFilter = 'real'"
          class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
          :class="memberFilter === 'real' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
          Thành viên ({{ totalMembers }})
        </button>
        <button @click="memberFilter = 'virtual'"
          class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
          :class="memberFilter === 'virtual' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
          Thành viên ảo ({{ virtualMembers.length }})
        </button>
      </div>
    </div>

    <!-- Content with Loading Overlay -->
    <div class="relative min-h-[300px]">
      <!-- Loading Overlay -->
      <div v-if="loading" 
        class="absolute inset-0 z-10 flex justify-center items-start pt-12 bg-white/60 backdrop-blur-[1px] transition-all duration-300">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      </div>

      <!-- Main Content -->
      <div :class="{ 'opacity-40 pointer-events-none': loading }" class="transition-opacity duration-300">
      
      <!-- Section 1: Ban Quản Trị -->
      <div v-if="(memberFilter === 'all' || memberFilter === 'real') && managementMembers.length > 0" class="mb-8">
        <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-tight mb-4 flex items-center gap-1.5">
          BAN QUẢN TRỊ <span class="text-gray-400 text-lg">•</span> {{ managementMembers.length }}
        </h3>
        <div v-for="member in managementMembers" :key="member.id"
          class="flex items-center justify-between py-4 border-b border-gray-200">
          <div class="flex items-center gap-3">
            <div class="relative p-0.5 rounded-full border-2"
              :class="getRoleBorderColor(member.role)">
              <img :src="member.user?.avatar_url || defaultAvatar" 
                :alt="member.user?.full_name" 
                class="w-14 h-14 rounded-full object-cover">
              <div 
                class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full flex items-center justify-center border-2 border-white"
                :class="getRoleBadgeColor(member.role)">
                <ShieldCheckIcon v-if="member.role === 'admin'" class="w-3 h-3 text-white" />
                <MoneyIcon v-else-if="member.role === 'treasurer'" class="w-3 h-3 text-white" />
              </div>
            </div>

            <div>
              <div class="flex items-center gap-2">
                <p class="font-semibold text-[#374151]">{{ member.user?.full_name || 'N/A' }}</p>
                <span :class="[
                  'px-2 py-0.5 text-[10px] font-bold rounded text-white uppercase',
                  getRoleTagColor(member.role)
                ]">
                  {{ getRoleLabel(member.role) }}
                </span>
              </div>
              <p class="text-xs text-gray-400 font-medium flex items-center gap-1">
                {{ getVpScore(member.user) }} PICKI
                <span class="inline-block w-1 h-1 rounded-full bg-gray-400"></span>
                {{ getRolePosition(member.role) }}
              </p>
            </div>
          </div>

          <div class="relative">
            <button
              @click="toggleMenu(member.id)"
              class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200 transition-colors">
              <EllipsisHorizontalIcon class="w-4 h-4" />
            </button>

            <!-- Dropdown Menu -->
            <div v-if="openMenuId === member.id" 
              class="absolute right-0 top-10 w-44 bg-white rounded-xl shadow-xl py-2 z-[10000] border border-gray-100 animate-in fade-in zoom-in duration-200">
              <button 
                @click="viewInfo(member)"
                class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                <InformationCircleIcon class="w-4 h-4 text-gray-400" />
                Xem thông tin
              </button>
              <button v-if="member.user?.id !== getUser.id && canManageMembers"
                @click="assignRole(member)"
                class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                <ShieldCheckIcon class="w-4 h-4 text-gray-400" />
                Bổ nhiệm
              </button>
              <button v-if="member.user?.id !== getUser.id && canManageMembers"
                @click="confirmDeleteMember(member)"
                class="w-full text-left px-4 py-3 text-sm font-medium text-red-700 hover:bg-red-50 flex items-center gap-2">
                <TrashIcon class="w-4 h-4 text-red-400" />
                Xoá khỏi CLB
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 2: Thành viên Thật -->
      <div class="mt-8" v-if="(memberFilter === 'all' || memberFilter === 'real') && regularMembers.length > 0">
        <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-tight mb-4 flex items-center gap-1.5">
          THÀNH VIÊN <span class="text-gray-400 text-lg">•</span> {{ totalRegularMembers }}
        </h3>
        <div v-for="member in regularMembers" :key="member.id"
          class="flex items-center justify-between py-4 border-b border-gray-200">
          <div class="flex items-center gap-3">
            <div class="relative">
              <img :src="member.user?.avatar_url || defaultAvatar" 
                :alt="member.user?.full_name" 
                class="w-14 h-14 rounded-full object-cover">
              <!-- Online indicator -->
              <span v-if="getOnlineStatus(member.user?.last_login_at).show"
                class="absolute bottom-0 right-0 border-2 border-white rounded-full flex items-center justify-center text-[10px] font-bold text-white px-1 py-0.5"
                :class="getOnlineStatus(member.user?.last_login_at).isActive ? 'w-3.5 h-3.5 bg-green-500' : 'bg-gray-400 opacity-90'">
              </span>
            </div>

            <div>
              <p class="font-semibold text-[#374151]">{{ member.user?.full_name || 'N/A' }}</p>
              <p class="text-xs text-gray-400 font-medium flex items-center gap-1">
                {{ getVpScore(member.user) }} PICKI
                <span class="inline-block w-1 h-1 rounded-full bg-gray-400"></span>
                Thành viên
              </p>
            </div>
          </div>

          <div class="relative">
            <button
              @click="toggleMenu(member.id)"
              class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200 transition-colors">
              <EllipsisHorizontalIcon class="w-4 h-4" />
            </button>

            <!-- Dropdown Menu -->
            <div v-if="openMenuId === member.id" 
              class="absolute right-0 top-10 w-44 bg-white rounded-xl shadow-xl py-2 z-[10000] border border-gray-100 animate-in fade-in zoom-in duration-200">
              <button 
                @click="viewInfo(member)"
                class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                <InformationCircleIcon class="w-4 h-4 text-gray-400" />
                Xem thông tin
              </button>
              <button v-if="canManageMembers"
                @click="assignRole(member)"
                class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                <ShieldCheckIcon class="w-4 h-4 text-gray-400" />
                Bổ nhiệm
              </button>
              <button v-if="canManageMembers"
                @click="confirmDeleteMember(member)"
                class="w-full text-left px-4 py-3 text-sm font-medium text-red-700 hover:bg-red-50 flex items-center gap-2">
                <TrashIcon class="w-4 h-4 text-red-400" />
                Xoá khỏi CLB
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 3: Thành viên Ảo -->
      <div class="mt-8" v-if="(memberFilter === 'all' || memberFilter === 'virtual') && virtualMembers.length > 0">
        <h3 class="text-sm font-semibold text-purple-600 uppercase tracking-tight mb-4 flex items-center gap-1.5">
          THÀNH VIÊN ẢO <span class="text-purple-400 text-lg">•</span> {{ virtualMembers.length }}
        </h3>
        <div v-for="vm in virtualMembers" :key="'vm_' + vm.id"
          class="flex items-center justify-between py-3.5 border-b border-gray-100">
          <div class="flex items-center gap-3">
            <img :src="vm.avatar_url || defaultAvatar" :alt="vm.name" class="w-12 h-12 rounded-full object-cover border border-purple-200">
            <div>
              <div class="flex items-center gap-1.5">
                <p class="font-semibold text-gray-800">{{ vm.name }}</p>
                <span class="text-[9px] bg-purple-100 text-purple-700 font-bold px-1.5 py-0.5 rounded">ẢO</span>
              </div>
              <p v-if="vm.notes" class="text-xs text-gray-400 mt-0.5">{{ vm.notes }}</p>
            </div>
          </div>

          <button v-if="canManageMembers" @click="handleDeleteVirtual(vm.id)"
            class="text-xs font-semibold text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg border border-red-100 transition-colors">
            Xóa
          </button>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="!loading && !regularMembers.length && !managementMembers.length && !virtualMembers.length" class="text-center py-12 text-gray-400">
        Chưa có thành viên nào
      </div>

      <!-- Pagination for real members -->
      <div v-if="totalPages > 1 && (memberFilter === 'all' || memberFilter === 'real')" class="mt-6 flex justify-center gap-2">
        <button 
          @click="goToPage(currentPage - 1)" 
          :disabled="currentPage === 1"
          class="px-3 py-1 text-sm border rounded hover:bg-gray-50 disabled:opacity-50">
          Trước
        </button>
        <span class="px-3 py-1 text-sm text-gray-600">
          Trang {{ currentPage }} / {{ totalPages }}
        </span>
        <button 
          @click="goToPage(currentPage + 1)" 
          :disabled="currentPage === totalPages"
          class="px-3 py-1 text-sm border rounded hover:bg-gray-50 disabled:opacity-50">
          Sau
        </button>
      </div>

      </div>
    </div>

    <!-- Modals -->
    <AssignRoleModal
      v-model="showAssignRoleModal"
      :member="selectedMember"
      :current-user-role="currentUserRole"
      @save="handleAssignRole"
    />

    <DeleteConfirmationModal
      v-model="showDeleteModal"
      title="Xoá thành viên"
      :message="`Bạn có chắc chắn muốn xoá ${memberToDelete?.user?.full_name} khỏi câu lạc bộ?`"
      confirm-button-text="Xoá ngay"
      @confirm="handleDeleteMember"
    />

    <ClubVirtualMemberModal
      v-model="showVirtualModal"
      :is-submitting="isCreatingVirtual"
      @submit="handleCreateVirtual"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useUserStore } from '@/store/auth'
import { 
  MagnifyingGlassIcon, 
  EllipsisHorizontalIcon,
  InformationCircleIcon,
  ShieldCheckIcon,
  TrashIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'
import MoneyIcon from '@/assets/images/money.svg'
const defaultAvatar = 'https://picki.vn/images/default-avatar.png'
import * as ClubService from '@/service/club'
import AssignRoleModal from '@/components/molecules/AssignRoleModal.vue'
import DeleteConfirmationModal from '@/components/molecules/DeleteConfirmationModal.vue'
import ClubVirtualMemberModal from '@/components/organisms/ClubVirtualMemberModal.vue'
import { toast } from 'vue3-toastify'

const props = defineProps({
  clubId: {
    type: [Number, String],
    required: true
  },
  isJoined: {
    type: Boolean,
    default: false
  },
  currentUserRole: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['refresh-club'])

const router = useRouter()
const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)

const members = ref([])
const virtualMembers = ref([])
const allManagementMembers = ref([])
const statistics = ref({})
const loading = ref(false)
const searchQuery = ref('')
const memberFilter = ref('all') // 'all' | 'real' | 'virtual'
const currentPage = ref(1)
const totalPages = ref(1)
const totalMembers = ref(0)
const totalRegularMembers = ref(0)
const perPage = ref(15)
const openMenuId = ref(null)
let searchTimeout = null

const showAssignRoleModal = ref(false)
const showDeleteModal = ref(false)
const showVirtualModal = ref(false)
const isCreatingVirtual = ref(false)
const selectedMember = ref(null)
const memberToDelete = ref(null)

const ROLE_COLORS = {
  admin: 'bg-blue-600',
  manager: 'bg-purple-600',
  treasurer: 'bg-orange-600',
  secretary: 'bg-green-600'
}

const canManageMembers = computed(() => {
  if (getUser.value?.is_super_admin) return true
  if (props.currentUserRole && ['admin', 'manager', 'secretary'].includes(props.currentUserRole)) return true
  const currentUserId = getUser.value?.id
  if (currentUserId && allManagementMembers.value.some(m => (m.user_id === currentUserId || m.user?.id === currentUserId) && ['admin', 'manager', 'secretary'].includes(m.role))) {
    return true
  }
  return true
})

const managementMembers = computed(() => {
  const managementRoles = ['admin', 'manager', 'treasurer', 'secretary']
  let list = allManagementMembers.value.filter(m => managementRoles.includes(m.role))

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(m => m.user?.full_name?.toLowerCase().includes(q))
  }

  return list
})

const regularMembers = computed(() => {
  return members.value.filter(m => m.role === 'member')
})

const fetchManagementMembers = async () => {
  try {
    const response = await ClubService.getMembers(props.clubId, {
      roles: ['admin', 'manager', 'treasurer', 'secretary'],
      per_page: 100
    })
    allManagementMembers.value = response.data.members || []
  } catch (error) {
    console.error('Error fetching management members:', error)
  }
}

const fetchVirtualMembers = async () => {
  try {
    const res = await ClubService.getVirtualMembers(props.clubId, {
      search: searchQuery.value
    })
    virtualMembers.value = res.data || []
  } catch (e) {
    virtualMembers.value = []
  }
}

const fetchMembers = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value
    }
    
    if (searchQuery.value) {
      params.search = searchQuery.value
    }
    
    const response = await ClubService.getMembers(props.clubId, params)
    
    members.value = response.data.members || []
    statistics.value = response.data.statistics || {}
    currentPage.value = response.meta.current_page || 1
    totalPages.value = response.meta.last_page || 1
    totalMembers.value = response.meta.total || 0
    totalRegularMembers.value = response.data.statistics?.by_role?.member || 0
    perPage.value = response.meta.per_page || 15
  } catch (error) {
    console.error('Error fetching members:', error)
    members.value = []
  } finally {
    loading.value = false
  }
}

const handleCreateVirtual = async (formData) => {
  isCreatingVirtual.value = true
  try {
    await ClubService.createVirtualMember(props.clubId, formData)
    toast.success('Thêm thành viên thành công')
    showVirtualModal.value = false
    await fetchVirtualMembers()
  } catch (e) {
    toast.error('Có lỗi xảy ra khi thêm thành viên')
  } finally {
    isCreatingVirtual.value = false
  }
}

const handleDeleteVirtual = async (vmId) => {
  try {
    await ClubService.deleteVirtualMember(props.clubId, vmId)
    toast.success('Đã xóa thành viên khỏi danh sách')
    await fetchVirtualMembers()
  } catch (e) {
    toast.error('Có lỗi khi xóa thành viên')
  }
}

const fetchData = async () => {
  await fetchManagementMembers()
  await fetchMembers()
  await fetchVirtualMembers()
}

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    fetchMembers()
  }
}

const getRoleBorderColor = (role) => {
  const colors = {
    'admin': 'border-blue-400',
    'manager': 'border-purple-400',
    'treasurer': 'border-orange-400',
    'secretary': 'border-green-400'
  }
  return colors[role] || 'border-gray-300'
}

const getRoleBadgeColor = (role) => {
  return ROLE_COLORS[role] || 'bg-gray-500'
}

const getRoleTagColor = (role) => {
  return ROLE_COLORS[role] || 'bg-gray-500'
}

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

const getRolePosition = (role) => {
  const positions = {
    'admin': 'Chủ câu lạc bộ',
    'manager': 'Quản lý',
    'treasurer': 'Thủ quỹ',
    'secretary': 'Thư ký'
  }
  return positions[role] || 'Thành viên'
}

const getVpScore = (user) => {
  const pickleball = user?.sports?.find(s => s.sport_id === 1 || s.sport_name === 'Pickleball');
  const score = pickleball?.scores?.vndupr_score;
  return score ? Number(score).toFixed(1) : '0';
}

const getOnlineStatus = (lastLogin) => {
  if (!lastLogin) return { show: false, isActive: false, label: '' }
  const lastLoginDate = new Date(lastLogin)
  const now = new Date()
  const diffMinutes = Math.floor((now - lastLoginDate) / (1000 * 60))

  if (diffMinutes <= 1) {
    return { show: true, isActive: true, label: '' }
  } else if (diffMinutes < 60) {
    return { show: true, isActive: false, label: `${diffMinutes} phút` }
  } else {
    return { show: false, isActive: false, label: '' }
  }
}

const toggleMenu = (id) => {
  openMenuId.value = openMenuId.value === id ? null : id
}

const closeMenu = () => {
  openMenuId.value = null
}

const viewInfo = (member) => {
  if (member.is_virtual || member.is_guest || !member.user?.id) {
    toast.info('Thành viên ảo (khách vãng lai) không có hồ sơ cá nhân.')
    closeMenu()
    return
  }
  if (member.user?.id) {
    router.push({ name: 'profile', params: { id: member.user.id } })
  }
  closeMenu()
}

const assignRole = (member) => {
  selectedMember.value = member
  showAssignRoleModal.value = true
  closeMenu()
}

const handleAssignRole = async ({ memberId, role }) => {
  try {
    await ClubService.updateMemberRole(props.clubId, memberId, { role })
    toast.success('Bổ nhiệm thành công')
    await fetchData()
    emit('refresh-club')
  } catch (error) {
    toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi bổ nhiệm')
  }
}

const confirmDeleteMember = (member) => {
  memberToDelete.value = member
  showDeleteModal.value = true
  closeMenu()
}

const handleDeleteMember = async () => {
  if (!memberToDelete.value) return
  try {
    await ClubService.removeMember(props.clubId, memberToDelete.value.id)
    toast.success('Xoá thành viên thành công')
    fetchMembers()
    fetchManagementMembers()
    emit('refresh-club')
  } catch (error) {
    toast.error('Có lỗi xảy ra khi xoá thành viên')
  } finally {
    showDeleteModal.value = false
    memberToDelete.value = null
  }
}

watch(searchQuery, () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }
  
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    fetchMembers()
    fetchVirtualMembers()
  }, 300)
})

onMounted(() => {
  fetchData()
})
</script>

<style scoped>
.tracking-tight {
  letter-spacing: -0.015em;
}
</style>