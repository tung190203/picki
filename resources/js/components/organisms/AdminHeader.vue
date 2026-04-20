<template>
  <header class="flex justify-between items-center w-full px-8 py-3 h-16 bg-surface sticky top-0 z-30 shadow-sm font-manrope">
    <div class="flex items-center gap-8">
      <div class="relative hidden lg:block">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-sm">search</span>
        <input 
          class="pl-10 pr-4 py-1.5 bg-surface-container-low border-none rounded-full text-sm w-64 focus:ring-1 focus:ring-primary outline-none transition-all" 
          placeholder="Tìm kiếm hệ thống..." 
          type="text"
        />
      </div>
    </div>

    <div class="flex items-center gap-4">
      <button class="w-10 h-10 flex items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high transition-colors active:scale-95">
        <span class="material-symbols-outlined">notifications</span>
      </button>
      <div class="h-8 w-[1px] bg-outline-variant/20 mx-2"></div>
      
      <!-- Profile Area with Dropdown -->
      <div class="relative">
        <div 
          @click="isProfileOpen = !isProfileOpen"
          class="flex items-center gap-3 cursor-pointer p-1.5 px-3 rounded-xl hover:bg-surface-container-low transition-colors active:scale-95"
          :class="{ 'bg-surface-container-low': isProfileOpen }"
        >
          <div class="text-right hidden sm:block">
            <p class="text-xs font-bold text-on-surface">{{ getUser?.full_name || 'Administrator' }}</p>
            <p class="text-[10px] text-on-surface-variant uppercase tracking-tighter">Super Admin</p>
          </div>
          <img :src="getUser?.avatar_url || defaultProfile" class="w-9 h-9 rounded-full border-2 border-primary/10 shadow-sm object-cover" />
          <span class="material-symbols-outlined text-on-surface-variant text-sm transition-transform duration-300" :class="{ 'rotate-180': isProfileOpen }">expand_more</span>
        </div>

        <!-- Dropdown Menu -->
        <transition 
          enter-active-class="transition duration-200 ease-out" 
          enter-from-class="transform scale-95 opacity-0 -translate-y-2" 
          enter-to-class="transform scale-100 opacity-100 translate-y-0" 
          leave-active-class="transition duration-150 ease-in" 
          leave-from-class="transform scale-100 opacity-100 translate-y-0" 
          leave-to-class="transform scale-95 opacity-0 -translate-y-2"
        >
          <div v-if="isProfileOpen" class="absolute right-0 mt-3 w-56 bg-surface-container-lowest rounded-2xl shadow-2xl border border-outline-variant/10 py-2 z-50 overflow-hidden">
            <div class="px-4 py-3 border-b border-outline-variant/5 mb-1 bg-surface-container-low/30 lg:hidden">
               <p class="text-sm font-bold text-on-surface">{{ getUser?.full_name || 'Administrator' }}</p>
               <p class="text-[10px] text-on-surface-variant">superadmin@vpick.com</p>
            </div>

            <button 
              @click="goToHome"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface-variant hover:text-primary hover:bg-primary/5 transition-all group"
            >
              <div class="w-8 h-8 rounded-lg bg-surface-container-high flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-colors">
                <span class="material-symbols-outlined text-xl">home</span>
              </div>
              <span class="font-bold">Quay lại trang chủ</span>
            </button>

            <button 
              @click="handleLogout"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-error hover:bg-error/5 transition-all group"
            >
              <div class="w-8 h-8 rounded-lg bg-error/10 flex items-center justify-center group-hover:bg-error group-hover:text-white transition-colors">
                <span class="material-symbols-outlined text-xl">logout</span>
              </div>
              <span class="font-bold">Đăng xuất</span>
            </button>
          </div>
        </transition>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref } from 'vue'
import { useUserStore } from '@/store/auth'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'

const router = useRouter()
const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)

const isProfileOpen = ref(false)

const defaultProfile = 'https://lh3.googleusercontent.com/aida-public/AB6AXuDItTo1Q3O6X5piPFY8gePY9twSzQ5VGxia4NpMafRDgdI69nGICqNUKgLQ_tLPcZAD-Yl-kRA5MV0n8U19NQ4g48athgx2rYRlRxT-e2TPJ2wpx49H01JV84yooXa-nOaudjreX720uRhscExSIxuIPMu-czlT4LIOaelTUATcoSwb_slhCcljhzE1_qGL6k4M1CdFceCrV3Ld9n8oVrNKmQczZ9mfF7F3V6xz4G4IWMZqo4B4qwicfSRWRC3GXVbQlT8CdxLc7Gk'

const goToHome = () => {
  isProfileOpen.value = false
  router.push({ name: 'dashboard' })
}

const handleLogout = async () => {
  isProfileOpen.value = false
  try {
    await userStore.logoutUser()
    toast.success('Đăng xuất thành công!')
    setTimeout(() => {
      router.push({ name: 'login' })
    }, 500)
  } catch (error) {
    toast.error(error.response?.data?.message || 'Đăng xuất thất bại!')
  }
}
</script>

<style scoped>
.font-manrope { font-family: 'Manrope', sans-serif; }
.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.icon-fill {
  font-variation-settings: 'FILL' 1;
}
</style>
