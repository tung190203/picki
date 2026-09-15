<template>
  <header class="flex justify-between items-center w-full px-8 py-3 h-16 bg-white sticky top-0 z-30 border-b border-slate-200/80 shadow-sm font-manrope">
    <div class="flex items-center gap-8">
      <div class="relative hidden lg:block">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
        <input 
          class="pl-10 pr-4 py-1.5 bg-slate-100 border border-transparent rounded-full text-sm w-64 focus:bg-white focus:border-slate-300 focus:ring-1 focus:ring-primary outline-none text-slate-800 placeholder-slate-400 transition-all" 
          placeholder="Tìm kiếm hệ thống..." 
          type="text"
        />
      </div>
    </div>

    <div class="flex items-center gap-4">
      <button class="w-10 h-10 flex items-center justify-center rounded-full text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors active:scale-95">
        <span class="material-symbols-outlined">notifications</span>
      </button>
      <div class="h-8 w-[1px] bg-slate-200 mx-2"></div>
      
      <!-- Profile Area with Dropdown -->
      <div class="relative">
        <div 
          @click="isProfileOpen = !isProfileOpen"
          class="flex items-center gap-3 cursor-pointer p-1.5 px-3 rounded-xl hover:bg-slate-100 transition-colors active:scale-95"
          :class="{ 'bg-slate-100': isProfileOpen }"
        >
          <div class="text-right hidden sm:block">
            <p class="text-xs font-bold text-slate-800">{{ getUser?.full_name || 'Administrator' }}</p>
            <p class="text-[10px] text-slate-500 uppercase tracking-tighter">Super Admin</p>
          </div>
          <img :src="getUser?.avatar_url || defaultProfile" class="w-9 h-9 rounded-full border-2 border-primary/10 shadow-sm object-cover" />
          <span class="material-symbols-outlined text-slate-500 text-sm transition-transform duration-300" :class="{ 'rotate-180': isProfileOpen }">expand_more</span>
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
          <div v-if="isProfileOpen" class="absolute right-0 mt-3 w-56 bg-white rounded-2xl shadow-xl border border-slate-200/80 py-2 z-50 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 mb-1 bg-slate-50 lg:hidden">
               <p class="text-sm font-bold text-slate-800">{{ getUser?.full_name || 'Administrator' }}</p>
               <p class="text-[10px] text-slate-500">superadmin@vpick.com</p>
            </div>

            <button 
              @click="goToHome"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-600 hover:text-primary hover:bg-primary/5 transition-all group"
            >
              <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-colors">
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
