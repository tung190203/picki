<template>
  <div class="flex min-h-screen bg-background font-body text-on-surface">
    <!-- SideNavBar -->
    <AdminSidebar />

    <!-- Main Content -->
    <main class="ml-64 flex-1">
      <AdminHeader />
      <div class="p-8 lg:p-12">
      <div class="grid grid-cols-12 gap-8">
        <!-- Section 1: Algorithm & Limits -->
        <section class="col-span-12 lg:col-span-12 xl:col-span-7">
          <div class="flex items-center gap-3 mb-6">
            <span class="material-symbols-outlined text-secondary icon-fill">analytics</span>
            <h3 class="text-xl font-headline font-bold text-on-surface">Thuật toán & Giới hạn</h3>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- KPI Card 1: K-Factor -->
            <div class="bg-secondary-fixed-dim/20 rounded-xl p-6 relative overflow-hidden group border border-secondary/5">
              <div class="relative z-10">
                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest block mb-4">Hệ số K-Factor (Elo Rating)</label>
                <div class="flex items-baseline gap-2">
                  <input class="bg-transparent border-none p-0 text-5xl font-headline font-extrabold text-on-secondary-fixed focus:ring-0 w-32 outline-none" type="number" v-model="kFactor" />
                  <span class="text-secondary font-bold">PT</span>
                </div>
                <p class="text-xs text-on-surface-variant mt-4 leading-relaxed">Xác định mức độ nhạy cảm của thay đổi điểm số người chơi sau mỗi trận đấu.</p>
              </div>
              <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-9xl">trending_up</span>
              </div>
            </div>

            <!-- KPI Card 2: Service Fee -->
            <div class="bg-tertiary-fixed-dim/20 rounded-xl p-6 relative overflow-hidden group border border-tertiary/5">
              <div class="relative z-10">
                <label class="text-[10px] font-bold text-tertiary uppercase tracking-widest block mb-4">Phí dịch vụ thu hộ (%)</label>
                <div class="flex items-baseline gap-2">
                  <input class="bg-transparent border-none p-0 text-5xl font-headline font-extrabold text-on-tertiary-fixed focus:ring-0 w-32 outline-none" step="0.1" type="number" v-model="serviceFee" />
                  <span class="text-tertiary font-bold">%</span>
                </div>
                <p class="text-xs text-on-surface-variant mt-4 leading-relaxed">Tỷ lệ cắt phế khi người dùng thanh toán qua App Picki.</p>
              </div>
              <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-9xl">payments</span>
              </div>
            </div>

            <!-- KPI Card 3: Auto-Confirm Time -->
            <div class="col-span-1 md:col-span-2 bg-surface-container-low rounded-xl p-8 border border-outline-variant/10 shadow-sm">
              <div class="flex justify-between items-center mb-6">
                <div>
                  <label class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest block mb-1">Thời gian Auto-Confirm</label>
                  <h4 class="text-lg font-bold text-on-surface">Thời gian tự động xác nhận</h4>
                </div>
                <div class="text-right">
                  <div class="text-3xl font-headline font-extrabold text-primary">{{ autoConfirmTime }} <span class="text-sm font-bold uppercase">Giờ</span></div>
                </div>
              </div>
              <div class="relative h-2 bg-surface-container-high rounded-full overflow-hidden mb-6">
                <div class="absolute top-0 left-0 h-full bg-primary transition-all duration-500" :style="{ width: (autoConfirmTime / 72 * 100) + '%' }"></div>
              </div>
              <div class="grid grid-cols-4 gap-4">
                <button 
                  v-for="time in [12, 24, 48, 72]" 
                  :key="time"
                  @click="autoConfirmTime = time"
                  class="py-2 rounded-lg text-xs font-bold transition-all"
                  :class="autoConfirmTime === time ? 'bg-primary text-on-primary shadow-md shadow-primary/20' : 'bg-surface text-on-surface shadow-sm hover:bg-primary-fixed'"
                >
                  {{ time }}h
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- Section 2: Feature Flags -->
        <section class="col-span-12 lg:col-span-12 xl:col-span-5">
          <div class="flex items-center gap-3 mb-6">
            <span class="material-symbols-outlined text-primary icon-fill">flag</span>
            <h3 class="text-xl font-headline font-bold text-on-surface">Cấu hình tính năng</h3>
          </div>
          
          <div class="space-y-4">
            <div 
              v-for="flag in featureFlags" 
              :key="flag.name"
              class="group bg-surface-container-lowest rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex items-center justify-between border border-outline-variant/10 hover:border-outline-variant/30"
            >
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="flag.bgClass">
                  <span class="material-symbols-outlined" :class="flag.iconClass">{{ flag.icon }}</span>
                </div>
                <div>
                  <h5 class="font-bold text-on-surface">{{ flag.name }}</h5>
                  <p class="text-xs text-on-surface-variant italic">{{ flag.desc }}</p>
                </div>
              </div>
              <Toggle v-model="flag.enabled" />
            </div>
          </div>
        </section>

        <!-- Footer Action -->
        <div class="col-span-12 mt-6 flex items-center justify-end">
          <button @click="saveConfig" class="bg-[#af101a] hover:bg-red-700 text-white font-headline font-bold px-10 py-4 rounded-xl shadow-lg shadow-red-900/20 active:scale-95 transition-all flex items-center gap-3">
            <span class="material-symbols-outlined">save</span>
            Lưu cấu hình
          </button>
      </div>
      </div>
    </div>
  </main>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { toast } from 'vue3-toastify'
import AdminSidebar from '@/components/organisms/AdminSidebar.vue'
import AdminHeader from '@/components/organisms/AdminHeader.vue'
import Toggle from '@/components/atoms/Toggle.vue'
import { get, put } from '@/utils/httpRequest.js'

const loading = ref(true)
const error = ref(null)
const saving = ref(false)

const kFactor = ref(32)
const serviceFee = ref(5.5)
const autoConfirmTime = ref(24)

const featureFlags = ref([
  {
    key: 'ai_assistant',
    name: 'AI Assistant (Bé Pi)',
    desc: 'Cho phép AI quét và tự động gửi Push Noti gạ kèo user.',
    icon: 'smart_toy',
    bgClass: 'bg-tertiary-container/10',
    iconClass: 'text-tertiary'
  },
  {
    key: 'online_payment',
    name: 'Cổng thanh toán trực tuyến',
    desc: 'Bật tắt việc thanh toán VNPay/Momo. Nếu tắt, user chỉ cần dùng tiền mặt.',
    icon: 'account_balance_wallet',
    bgClass: 'bg-secondary-container/10',
    iconClass: 'text-secondary'
  },
  {
    key: 'maintenance_mode',
    name: 'Chế độ bảo trì (maintenance)',
    desc: 'Khóa toàn bộ App để nâng cấp Server. Chỉ admin được vào.',
    icon: 'construction',
    bgClass: 'bg-error-container/20',
    iconClass: 'text-error'
  }
])

const fetchSettings = async () => {
  try {
    loading.value = true
    const res = await get('/admin/settings')
    const data = res.data.data

    kFactor.value = data.k_factor ?? 32
    serviceFee.value = data.service_fee_percent ?? 5.5
    autoConfirmTime.value = data.auto_confirm_hours ?? 24

    // Map features
    const features = data.features ?? {}
    featureFlags.value = featureFlags.value.map(flag => ({
      ...flag,
      enabled: features[flag.key] ?? false
    }))
  } catch (e) {
    error.value = 'Không thể tải cấu hình.'
    console.error('Settings error:', e)
  } finally {
    loading.value = false
  }
}

const saveConfig = async () => {
  try {
    saving.value = true

    const features = {}
    featureFlags.value.forEach(flag => {
      features[flag.key] = flag.enabled
    })

    await put('/admin/settings', {
      k_factor: kFactor.value,
      service_fee_percent: serviceFee.value,
      auto_confirm_hours: autoConfirmTime.value,
      features: features
    })

    toast.success('Cấu hình đã được lưu thành công!', {
      position: 'bottom-right'
    })
  } catch (e) {
    toast.error('Lỗi khi lưu cấu hình.', {
      position: 'bottom-right'
    })
    console.error('Save config error:', e)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<style scoped>
.font-headline { font-family: 'Manrope', sans-serif; }
.font-body { font-family: 'Inter', sans-serif; }

/* Chrome, Safari, Edge, Opera */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* Firefox */
input[type=number] {
  -moz-appearance: textfield;
}
</style>
