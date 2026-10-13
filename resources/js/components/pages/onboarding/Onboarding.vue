<script setup>
import Button from '@/components/atoms/Button.vue'
import { useRouter } from 'vue-router'
import { LOCAL_STORAGE_KEY } from "@/constants/index.js"
import LogoSplash from '@/assets/images/logo-splash.svg'
import { onMounted } from 'vue'

const router = useRouter()
const query = router.currentRoute.value.query

// Mark onboarding as seen as soon as the page renders, so subsequent
// navigations don't bounce the user back here in a loop.
onMounted(() => {
  localStorage.setItem(LOCAL_STORAGE_KEY.ONBOARDING, 'true')
})

// Anonymous users can't reach /tournament-detail/:id; rewrite any such redirect
// to the matching public landing page so they don't get bounced to login.
function publicRedirect(q) {
  const r = q?.redirect
  if (typeof r !== 'string') return q
  const m = r.match(/^\/tournament-detail\/([^/?#]+)/)
  if (!m) return q
  const { redirect, ...rest } = q
  return { ...rest, redirect: `/tournament-landing/${m[1]}` }
}

function goRegister() {
  localStorage.setItem(LOCAL_STORAGE_KEY.ONBOARDING, 'true')
  router.push({ path: '/register', query: publicRedirect(query) })
}

function goLogin() {
  localStorage.setItem(LOCAL_STORAGE_KEY.ONBOARDING, 'true')
  router.push({ path: '/login', query: publicRedirect(query) })
}
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center px-4">
    <LogoSplash class="w-[60%]" />
    <div class="text-center mb-8 mt-4">
      <h1 class="text-white text-2xl mb-2">THAM GIA CỘNG ĐỒNG PICKERBALL NGAY TRÊN PICKI</h1>
      <p class="text-sm text-white font-light">
        Tận hưởng toàn bộ tính năng của Pickleball, bao gồm cập nhật giải đấu, 
        bảng xếp hạng và thông báo trận đấu độc quyền!
      </p>
    </div>
    <div class="w-full max-w-md space-y-4">
      <Button class="w-full bg-white !text-[#333333] hover:bg-secondary hover:!text-white" @click="goRegister">
        Đăng ký
      </Button>
      <Button class="w-full bg-secondary hover:bg-white hover:text-[#333333]" @click="goLogin">
        Đăng nhập
      </Button>
    </div>
  </div>
</template>
