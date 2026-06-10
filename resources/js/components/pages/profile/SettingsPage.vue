<template>
    <div class="min-h-screen bg-gray-50">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
          <!-- Sidebar Navigation -->
          <div class="lg:col-span-1">
            <nav class="bg-white rounded-lg shadow-sm p-3 space-y-1 sticky top-6">
              <button
                v-for="item in menuItems"
                :key="item.id"
                @click="activeTab = item.id"
                :class="[
                  'w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left transition-colors',
                  activeTab === item.id 
                    ? 'bg-blue-600 text-white shadow-sm' 
                    : 'text-gray-700 hover:bg-gray-100'
                ]"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                </svg>
                <span class="font-medium">{{ item.label }}</span>
              </button>
            </nav>
          </div>
  
          <!-- Main Content Area -->
          <div class="lg:col-span-3">
            <!-- Profile Settings -->
            <div v-if="activeTab === 'profile'" class="bg-white rounded-lg shadow-sm p-6">
              <div v-if="isLoading" class="flex justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
              </div>
              <template v-else>
              <h2 class="text-xl font-semibold mb-6 text-gray-900">Thông tin cá nhân</h2>
              
              <div class="space-y-6">
                <!-- Avatar Section -->
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 pb-6 border-b">
                  <div class="w-24 h-24 rounded-full overflow-hidden flex items-center justify-center text-white text-3xl font-bold shadow-lg"
                    :class="profile.avatar_url ? '' : 'bg-gradient-to-br from-blue-500 to-purple-600'"
                  >
                    <img v-if="profile.avatar_url" :src="profile.avatar_url" alt="Avatar" class="w-full h-full object-cover" />
                    <span v-else>{{ profile.full_name ? profile.full_name.charAt(0).toUpperCase() : '?' }}</span>
                  </div>
                  <div class="text-center sm:text-left">
                    <h3 class="text-lg font-semibold text-gray-900">{{ profile.full_name || '...' }}</h3>
                    <p class="text-gray-600 text-sm">{{ profile.email }}</p>
                    <button class="mt-3 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                      Thay đổi ảnh đại diện
                    </button>
                  </div>
                </div>
  
                <!-- Form Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Họ và tên</label>
                    <input 
                      v-model="profile.full_name" 
                      type="text" 
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Nhập họ và tên"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input 
                      v-model="profile.email" 
                      type="email" 
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Số điện thoại</label>
                    <input 
                      v-model="profile.phone" 
                      type="tel" 
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Địa chỉ</label>
                    <input 
                      v-model="profile.address" 
                      type="text" 
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                  </div>
                </div>
  
                <div class="flex justify-end gap-3 pt-4">
                  <button class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Hủy
                  </button>
                  <button
                    :disabled="isSaving"
                    @click="saveProfile"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50"
                  >
                    {{ isSaving ? 'Đang lưu...' : 'Lưu thay đổi' }}
                  </button>
                </div>
              </div>
              </template>
            </div>

            <!-- Security Settings -->
            <div v-if="activeTab === 'security'" class="bg-white rounded-lg shadow-sm p-6">
              <h2 class="text-xl font-semibold mb-6 text-gray-900">Bảo mật</h2>
              
              <div class="space-y-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Mật khẩu hiện tại</label>
                  <input 
                    v-model="security.currentPassword" 
                    type="password" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Mật khẩu mới</label>
                  <input 
                    v-model="security.newPassword" 
                    type="password" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Xác nhận mật khẩu mới</label>
                  <input 
                    v-model="security.confirmPassword" 
                    type="password" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
  
                <div class="border-t pt-6 mt-6">
                  <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                      <h3 class="font-medium text-gray-900">Xác thực hai yếu tố</h3>
                      <p class="text-sm text-gray-600">Tăng cường bảo mật tài khoản của bạn</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input v-model="security.twoFactor" type="checkbox" class="sr-only peer">
                      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                  </div>
                </div>
  
                <div class="flex justify-end gap-3 pt-4">
                  <button class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Hủy
                  </button>
                  <button class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Cập nhật mật khẩu
                  </button>
                </div>
              </div>
            </div>
  
            <!-- Notifications Settings -->
            <div v-if="activeTab === 'notifications'" class="bg-white rounded-lg shadow-sm p-6">
              <h2 class="text-xl font-semibold mb-6 text-gray-900">Thông báo</h2>
              
              <div class="space-y-4">
                <div v-for="notif in notificationSettings" :key="notif.id" class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition">
                  <div class="flex-1">
                    <h3 class="font-medium text-gray-900">{{ notif.title }}</h3>
                    <p class="text-sm text-gray-600">{{ notif.description }}</p>
                  </div>
                  <label class="relative inline-flex items-center cursor-pointer ml-4">
                    <input v-model="notif.enabled" type="checkbox" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </div>
              </div>
  
              <div class="flex justify-end gap-3 pt-6 mt-6 border-t">
                <button class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                  Lưu cài đặt
                </button>
              </div>
            </div>
  
            <!-- Privacy Settings -->
            <div v-if="activeTab === 'privacy'" class="bg-white rounded-lg shadow-sm p-6">
              <h2 class="text-xl font-semibold mb-6 text-gray-900">Quyền riêng tư</h2>
              
              <div class="space-y-6">
                <div class="space-y-4">
                  <div class="flex items-center justify-between p-4 border rounded-lg">
                    <div>
                      <h3 class="font-medium text-gray-900">Hiển thị hồ sơ công khai</h3>
                      <p class="text-sm text-gray-600">Cho phép người khác xem hồ sơ của bạn</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input v-model="privacy.publicProfile" type="checkbox" class="sr-only peer">
                      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                  </div>
  
                  <div class="flex items-center justify-between p-4 border rounded-lg">
                    <div>
                      <h3 class="font-medium text-gray-900">Chia sẻ dữ liệu phân tích</h3>
                      <p class="text-sm text-gray-600">Giúp cải thiện trải nghiệm của bạn</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input v-model="privacy.analytics" type="checkbox" class="sr-only peer">
                      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                  </div>
                </div>
  
                <div class="border-t pt-6">
                  <h3 class="font-medium text-gray-900 mb-4">Quản lý dữ liệu</h3>
                  <div class="space-y-3">
                    <button class="w-full sm:w-auto px-6 py-2 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50 transition">
                      Tải xuống dữ liệu của tôi
                    </button>
                    <button class="w-full sm:w-auto px-6 py-2 border border-red-600 text-red-600 rounded-lg hover:bg-red-50 transition ml-0 sm:ml-3">
                      Xóa tài khoản
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
<script setup>
import { ref, onMounted } from 'vue'
import { useUserStore } from '@/store/auth.js'
import { toast } from 'vue3-toastify'

const userStore = useUserStore()
const activeTab = ref('profile')
const isLoading = ref(false)
const isSaving = ref(false)
  
  const menuItems = ref([
    {
      id: 'profile',
      label: 'Hồ sơ',
      icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'
    },
    {
      id: 'security',
      label: 'Bảo mật',
      icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'
    },
    {
      id: 'notifications',
      label: 'Thông báo',
      icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'
    },
    {
      id: 'privacy',
      label: 'Riêng tư',
      icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'
    }
  ])
  
  const profile = ref({
    full_name: userStore.getUser.full_name || '',
    email: userStore.getUser.email || '',
    phone: userStore.getUser.phone || '',
    address: userStore.getUser.address || '',
    avatar_url: userStore.getUser.avatar_url || '',
  })
  
  const security = ref({
    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
    twoFactor: false
  })
  
  const notificationSettings = ref([
    {
      id: 1,
      title: 'Thông báo Email',
      description: 'Nhận thông báo qua email về hoạt động tài khoản',
      enabled: true
    },
    {
      id: 2,
      title: 'Thông báo Push',
      description: 'Nhận thông báo đẩy trên thiết bị di động',
      enabled: true
    },
    {
      id: 3,
      title: 'Cập nhật sản phẩm',
      description: 'Nhận thông tin về tính năng và cập nhật mới',
      enabled: false
    },
    {
      id: 4,
      title: 'Khuyến mãi',
      description: 'Nhận thông báo về ưu đãi và khuyến mãi đặc biệt',
      enabled: true
    }
  ])
  
  const privacy = ref({
    publicProfile: true,
    analytics: false
  })
  
  const saveProfile = async () => {
    try {
      isSaving.value = true
      await userStore.updateUser({ full_name: profile.value.full_name })
      toast.success('Cập nhật thông tin thành công!')
    } catch (error) {
      toast.error(error.response?.data?.message || 'Có lỗi xảy ra')
    } finally {
      isSaving.value = false
    }
  }

  const fetchProfile = async () => {
    try {
      isLoading.value = true
      const data = await userStore.fetchMe()
      profile.value = {
        full_name: data.full_name || '',
        email: data.email || '',
        phone: data.phone || '',
        address: data.address || '',
        avatar_url: data.avatar_url || '',
      }
    } catch (error) {
      toast.error('Không thể tải thông tin người dùng')
    } finally {
      isLoading.value = false
    }
  }

  onMounted(() => {
    fetchProfile()
  })
  </script>