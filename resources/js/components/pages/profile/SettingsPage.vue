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
                    ? 'bg-primary text-white shadow-sm font-semibold' 
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
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
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
                    <button class="mt-3 px-4 py-2 bg-primary text-white rounded-lg hover:bg-red-700 transition text-sm">
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
                    class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-red-700 transition disabled:opacity-50"
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
                    <Toggle v-model="security.twoFactor" />
                  </div>
                </div>

                <!-- Face ID / Touch ID / Biometrics Section -->
                <div v-if="isBiometricAvailable" class="border-t pt-6 mt-6">
                  <div class="p-4 bg-gradient-to-r from-red-50 to-rose-50 dark:from-slate-800 dark:to-slate-800 border border-red-100 dark:border-slate-700 rounded-lg">
                    <div class="flex items-center justify-between">
                      <div>
                        <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                          Đăng nhập bằng Face ID / Vân tay
                        </h3>
                        <p class="text-xs text-gray-600 mt-1">Đăng nhập nhanh chóng và bảo mật trên thiết bị này mà không cần nhập mật khẩu.</p>
                      </div>
                      <button
                        type="button"
                        :disabled="isRegisteringBiometric"
                        @click="handleRegisterBiometric"
                        class="px-4 py-2 bg-primary hover:bg-red-700 text-white rounded-lg text-sm font-medium transition shadow-sm whitespace-nowrap"
                      >
                        {{ isRegisteringBiometric ? 'Đang thêm...' : '+ Thêm thiết bị này' }}
                      </button>
                    </div>

                    <!-- Registered Biometric Devices List -->
                    <div v-if="biometricDevices.length > 0" class="mt-4 space-y-2 border-t border-blue-200/60 pt-3">
                      <div class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Thiết bị đã đăng ký:</div>
                      <div 
                        v-for="device in biometricDevices" 
                        :key="device.id" 
                        class="flex items-center justify-between p-2.5 bg-white rounded border border-gray-200 text-xs"
                      >
                        <div class="flex items-center gap-2">
                          <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="device.platform === 'ios' ? 'bg-black text-white' : 'bg-green-600 text-white'">
                            {{ device.platform || 'web' }}
                          </span>
                          <span class="font-medium text-gray-800">{{ device.device_name }}</span>
                        </div>
                        <button 
                          type="button" 
                          @click="confirmDeleteBiometric(device)"
                          class="text-red-600 hover:text-red-800 hover:bg-red-50 rounded px-2.5 py-1 text-xs font-medium transition"
                        >
                          Xoá
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
  
                <div class="flex justify-end gap-3 pt-4">
                  <button class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Hủy
                  </button>
                  <button class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-red-700 transition">
                    Cập nhật mật khẩu
                  </button>
                </div>
              </div>
            </div>

            <!-- Appearance Settings (Dark/Light/System Theme) -->
            <div v-if="activeTab === 'appearance'" class="bg-white rounded-lg shadow-sm p-6">
              <h2 class="text-xl font-semibold mb-2 text-gray-900">Giao diện người dùng</h2>
              <p class="text-sm text-gray-600 mb-6">Tùy chỉnh chế độ hiển thị Sáng / Tối hoặc tự động đồng bộ theo Cài đặt hệ thống của thiết bị.</p>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Light Mode Card -->
                <button
                  type="button"
                  @click="handleSetTheme('light')"
                  class="flex flex-col items-center justify-center p-5 rounded-xl border-2 transition-all text-center relative cursor-pointer"
                  :class="themeMode === 'light' ? 'border-primary bg-red-50/60 dark:bg-red-950/40 text-primary dark:text-red-400 font-semibold shadow-sm' : 'border-gray-200 hover:border-gray-300 text-gray-700 bg-white'"
                >
                  <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                  </div>
                  <span class="text-base font-semibold">Giao diện Sáng</span>
                  <span class="text-xs text-gray-500 mt-1">Tone màu sáng truyền thống</span>
                  <span v-if="themeMode === 'light'" class="absolute top-2 right-3 text-primary dark:text-red-400 font-bold text-lg">✓</span>
                </button>

                <!-- Dark Mode Card -->
                <button
                  type="button"
                  @click="handleSetTheme('dark')"
                  class="flex flex-col items-center justify-center p-5 rounded-xl border-2 transition-all text-center relative cursor-pointer"
                  :class="themeMode === 'dark' ? 'border-primary bg-red-50/60 dark:bg-red-950/40 text-primary dark:text-red-400 font-semibold shadow-sm' : 'border-gray-200 hover:border-gray-300 text-gray-700 bg-white'"
                >
                  <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                  </div>
                  <span class="text-base font-semibold">Giao diện Tối</span>
                  <span class="text-xs text-gray-500 mt-1">Dịu mắt, tiết kiệm pin</span>
                  <span v-if="themeMode === 'dark'" class="absolute top-2 right-3 text-primary dark:text-red-400 font-bold text-lg">✓</span>
                </button>

                <!-- System Mode Card -->
                <button
                  type="button"
                  @click="handleSetTheme('system')"
                  class="flex flex-col items-center justify-center p-5 rounded-xl border-2 transition-all text-center relative cursor-pointer"
                  :class="themeMode === 'system' ? 'border-primary bg-red-50/60 dark:bg-red-950/40 text-primary dark:text-red-400 font-semibold shadow-sm' : 'border-gray-200 hover:border-gray-300 text-gray-700 bg-white'"
                >
                  <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <span class="text-base font-semibold">Theo hệ thống</span>
                  <span class="text-xs text-gray-500 mt-1">Tự đồng bộ theo OS thiết bị</span>
                  <span v-if="themeMode === 'system'" class="absolute top-2 right-3 text-primary dark:text-red-400 font-bold text-lg">✓</span>
                </button>
              </div>

              <!-- Pinned Favorite Features Section -->
              <div class="mt-8 pt-6 border-t border-gray-100 dark:border-slate-800">
                <div class="flex items-center justify-between mb-4">
                  <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Tính năng ưa thích (Phím tắt ghim)</h3>
                    <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Chọn tối đa 4 tính năng hiển thị nhanh trên Bảng điều khiển trang chủ.</p>
                  </div>
                  <button
                    type="button"
                    @click="isFavoriteModalOpen = true"
                    class="px-4 py-2 bg-[#D72D36] hover:bg-[#c22830] text-white text-xs font-bold rounded-lg shadow-sm transition flex items-center gap-1.5 cursor-pointer"
                  >
                    <PencilIcon class="w-3.5 h-3.5" />
                    Tùy chỉnh ngay
                  </button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3">
                  <div
                    v-for="(f, i) in favoriteFeaturesList"
                    :key="i"
                    class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-slate-700/60 bg-gray-50/50 dark:bg-[#1E293B]"
                  >
                    <div class="w-9 h-9 rounded-full bg-red-100 dark:bg-red-950/40 text-red-600 dark:text-red-400 flex items-center justify-center shrink-0">
                      <component :is="f.icon" class="w-5 h-5" />
                    </div>
                    <span class="text-xs font-semibold text-gray-800 dark:text-slate-200 truncate">{{ f.label }}</span>
                  </div>
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
                  <Toggle v-model="notif.enabled" />
                </div>
              </div>
  
              <div class="flex justify-end gap-3 pt-6 mt-6 border-t">
                <button class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-red-700 transition">
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
                    <Toggle v-model="privacy.publicProfile" />
                  </div>
  
                  <div class="flex items-center justify-between p-4 border rounded-lg">
                    <div>
                      <h3 class="font-medium text-gray-900">Chia sẻ dữ liệu phân tích</h3>
                      <p class="text-sm text-gray-600">Giúp cải thiện trải nghiệm của bạn</p>
                    </div>
                    <Toggle v-model="privacy.analytics" />
                  </div>
                </div>
  
                <div class="border-t pt-6">
                  <h3 class="font-medium text-gray-900 mb-4">Quản lý dữ liệu</h3>
                  <div class="space-y-3">
                    <button class="w-full sm:w-auto px-6 py-2 border border-primary text-primary rounded-lg hover:bg-red-50 dark:hover:bg-red-950/30 transition">
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

      <!-- Biometric Device Deletion Confirmation Modal -->
      <div 
        v-if="showDeleteConfirmModal" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm transition-opacity"
      >
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4 border border-gray-100 animate-in fade-in zoom-in duration-200">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900">Xác nhận xoá thiết bị</h3>
              <p class="text-xs text-gray-500">Thao tác này không thể hoàn tác</p>
            </div>
          </div>

          <p class="text-sm text-gray-600 leading-relaxed">
            Bạn có chắc chắn muốn xoá thiết bị <strong class="text-gray-900 font-semibold">{{ selectedDeviceToDelete?.device_name }}</strong> khỏi danh sách sinh trắc học không? Sau khi xoá, thiết bị này sẽ không thể dùng Face ID / Vân tay để đăng nhập nhanh nữa.
          </p>

          <div class="flex justify-end gap-3 pt-2">
            <button
              type="button"
              :disabled="isDeletingDevice"
              @click="showDeleteConfirmModal = false; selectedDeviceToDelete = null;"
              class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition"
            >
              Hủy
            </button>
            <button
              type="button"
              :disabled="isDeletingDevice"
              @click="executeDeleteBiometric"
              class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition flex items-center gap-2"
            >
              <span v-if="isDeletingDevice">Đang xoá...</span>
              <span v-else>Xác nhận xoá</span>
            </button>
          </div>
        </div>
      </div>
      <FavoriteFeaturesModal
      v-model:isOpen="isFavoriteModalOpen"
      @saved="onFavoriteFeaturesSaved"
    />
  </div>
  </template>
  
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useUserStore } from '@/store/auth.js'
import { storeToRefs } from 'pinia'
import { toast } from 'vue3-toastify'
import { isBiometricSupported, registerDeviceBiometric, getBiometricType } from '@/utils/biometrics.js'
import * as AuthService from '@/service/auth.js'
import { themeMode, setThemeMode } from '@/utils/theme.js'
import Toggle from '@/components/atoms/Toggle.vue'
import FavoriteFeaturesModal, { getSavedFavoriteFeatures } from '@/components/molecules/FavoriteFeaturesModal.vue'
import { PencilIcon } from '@heroicons/vue/24/outline'

const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)
const activeTab = ref('profile')
const isLoading = ref(false)
const isSaving = ref(false)
const isFavoriteModalOpen = ref(false)
const favoriteFeaturesList = ref(getSavedFavoriteFeatures(getUser.value))

watch(getUser, (newUser) => {
  if (newUser) {
    favoriteFeaturesList.value = getSavedFavoriteFeatures(newUser);
  }
}, { immediate: true })

const onFavoriteFeaturesSaved = (newFeatures) => {
  favoriteFeaturesList.value = newFeatures;
}
const isBiometricAvailable = ref(false)
const isRegisteringBiometric = ref(false)
const biometricDevices = ref([])

const hasCurrentDeviceRegistered = computed(() => {
  const bio = getBiometricType()
  const currentPlatform = (bio.icon === 'face_id' || bio.icon === 'touch_id') ? 'ios' : (bio.icon === 'fingerprint' ? 'android' : 'web')
  return biometricDevices.value.some(d => d.platform === currentPlatform)
})

const handleSetTheme = (mode) => {
  setThemeMode(mode)
  toast.success('Đã cập nhật giao diện thành công!')
}

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
      id: 'appearance',
      label: 'Giao diện',
      icon: 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z'
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

  const loadBiometricDevices = async () => {
    try {
      const res = await AuthService.listBiometrics()
      biometricDevices.value = res?.biometrics || []
    } catch (err) {
      console.warn('Cannot load biometrics:', err)
    }
  }

  const handleRegisterBiometric = async () => {
    try {
      isRegisteringBiometric.value = true
      const challengeRes = await AuthService.getBiometricChallenge()
      const challengeStr = challengeRes?.challenge || 'picki-biometric-challenge'

      const credentialData = await registerDeviceBiometric(userStore.getUser, challengeStr)
      await AuthService.registerBiometric(credentialData)

      localStorage.setItem('vpick_biometric_credential_id', credentialData.credential_id)
      toast.success('Đã bật Face ID / Vân tay thành công cho thiết bị này!')
      await loadBiometricDevices()
    } catch (error) {
      console.error('Register Biometric Error:', error)
      if (error.name === 'NotAllowedError') {
        toast.info('Bạn đã huỷ tạo Face ID / Vân tay.')
      } else {
        toast.error(error.response?.data?.message || error.message || 'Không thể bật Face ID / Vân tay trên thiết bị này.')
      }
    } finally {
      isRegisteringBiometric.value = false
    }
  }

  const showDeleteConfirmModal = ref(false)
  const selectedDeviceToDelete = ref(null)
  const isDeletingDevice = ref(false)

  const confirmDeleteBiometric = (device) => {
    selectedDeviceToDelete.value = device
    showDeleteConfirmModal.value = true
  }

  const executeDeleteBiometric = async () => {
    if (!selectedDeviceToDelete.value) return
    try {
      isDeletingDevice.value = true
      await AuthService.deleteBiometric(selectedDeviceToDelete.value.id)
      toast.success('Đã xoá thiết bị thành công.')
      showDeleteConfirmModal.value = false
      selectedDeviceToDelete.value = null
      await loadBiometricDevices()
    } catch (error) {
      toast.error(error.response?.data?.message || 'Không thể xoá thiết bị.')
    } finally {
      isDeletingDevice.value = false
    }
  }

  onMounted(async () => {
    fetchProfile()
    isBiometricAvailable.value = await isBiometricSupported()
    if (isBiometricAvailable.value) {
      loadBiometricDevices()
    }
  })
  </script>