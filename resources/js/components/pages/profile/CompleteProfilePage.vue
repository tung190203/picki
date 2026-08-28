<script setup>
import Button from '@/components/atoms/Button.vue'
import { useRouter } from 'vue-router'
import LogoSplash from '@/assets/images/logo-splash.svg'

const router = useRouter()

function goCompleteProfile() {
  router.push('/update-profile')
}
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center px-4">
    <LogoSplash class="w-[60%]" alt="Logo" />
    <div class="text-center mb-8 mt-4">
      <h1 class="text-white text-2xl mb-2">CHÀO MỪNG BẠN ĐẾN VỚI PICKI</h1>
      <p class="text-sm text-white font-light">
        Chúng tôi rất vui khi có bạn tham gia <br>
        Hãy cùng cài đặt hồ sơ của bạn nhé
      </p>
    </div>
    <div class="w-full max-w-md space-y-4">
      <Button class="w-full bg-secondary hover:bg-white hover:text-[#333333]" @click="goCompleteProfile">
        Bắt đầu hoàn tất hồ sơ
      </Button>
    </div>
  </div>
</template>
