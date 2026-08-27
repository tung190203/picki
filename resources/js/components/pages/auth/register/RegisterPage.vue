<script setup>
import { ref, reactive, computed } from 'vue'
import useVuelidate from '@vuelidate/core'
import { required, helpers } from '@vuelidate/validators'
import { useRouter } from 'vue-router'
import Button from '@/components/atoms/Button.vue'
import { useUserStore } from '@/store/auth'
import { toast } from 'vue3-toastify'
import GoogleIcon from '@/assets/images/google-icon.svg'
import FacebookIcon from '@/assets/images/facebook-icon.svg'
import AppleIcon from '@/assets/images/apple-icon.svg'
import LogoSplash from '@/assets/images/logo-splash.svg'
import TurnstileWidget from '@/components/common/TurnstileWidget.vue'

const turnstileToken = ref('')
const onTurnstileVerify = (token) => {
  turnstileToken.value = token
}

const router = useRouter()
const userStore = useUserStore()

const data = reactive({
  login: '',
})

const isEmailOrPhone = helpers.withMessage(
  'Vui lòng nhập email hoặc số điện thoại hợp lệ',
  value => {
    if (!value) return true
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    const phoneRegex = /^0\d{9,10}$/
    return emailRegex.test(value) || phoneRegex.test(value)
  }
)

const rules = computed(() => ({
  login: {
    required: helpers.withMessage('Không được để trống', required),
    isEmailOrPhone
  },
}))

const v$ = useVuelidate(rules, data)

const register = async () => {
  v$.value.$touch()
  if (!v$.value.$invalid) {
    try {
      await userStore.registerUser({
        ...data,
        'cf-turnstile-response': turnstileToken.value
      })
      toast.success('Đăng ký thành công!')
      setTimeout(() => {
        router.push({ path: '/verify', query: { login: data.login } })
      }, 1000)
    } catch (error) {
      const message = error.response?.data?.message || 'Đăng ký thất bại, vui lòng thử lại!'
      toast.error(`${message}`)
      if(error.response?.data?.errors?.status_code === "PASSWORD_PENDING") {
        setTimeout(() => {
          router.push({ path: '/complete-registration', query: { login: data.login } })
        }, 1000)
      }
      if(error.response?.data?.errors?.status_code === "OTP_PENDING") {
        setTimeout(() => {
          router.push({ path: '/verify', query: { login: data.login } })
        }, 1000)
      }
    }
  }
}

const registerWithGoogle = () => {
  window.location.href = import.meta.env.VITE_BASE_URL + '/auth/google/redirect'
}
const registerWithFacebook = () => {
  window.location.href = import.meta.env.VITE_BASE_URL + '/auth/facebook/redirect'
}

const registerWithApple = () => {
  window.location.href = import.meta.env.VITE_BASE_URL + '/auth/apple/redirect'
}
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center px-4">
    <LogoSplash class="w-[60%]" />
    <div class="text-center mb-8 mt-8">
      <h1 class="text-white text-2xl mb-2">Đăng nhập/Đăng ký</h1>
      <p class="text-sm text-white font-light">Tận hưởng toàn bộ tính năng của Pickleball, bao gồm cập nhật giải đấu, 
        bảng xếp hạng và thông báo trận đấu độc quyền!</p>
    </div>
    <div class="w-full max-w-md p-8 bg-white rounded-[12px] shadow">
      <form @submit.prevent="register" class="space-y-4">
        <div>
          <label for="login" class="form-in font-semibold text-[14px]">Email</label>
          <input
            id="login"
            type="text"
            placeholder="Nhập email của bạn"
            v-model="data.login"
            class="w-full px-4 py-2 my-1 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder:text-sm"
          />
          <p v-for="err in v$.login.$errors" class="text-sm text-red-500 mt-1">
            {{ err.$message }}
          </p>
        </div>
        <Button 
          type="submit"
          :class="{
            'w-full !bg-primary hover:!bg-secondary': data.login,
            'w-full !bg-[#edeef2] !text-[#333333] hover:!bg-[#edeefe]': !data.login
          }"
        >
          Tiếp tục
        </Button>
        <TurnstileWidget size="flexible" @verify="onTurnstileVerify" @expire="turnstileToken = ''" />
      </form>

      <div class="flex items-center my-4 text-sm text-gray-500">
        <div class="flex-grow border-t border-gray-300"></div>
        <span class="px-4">Hoặc đăng ký với</span>
        <div class="flex-grow border-t border-gray-300"></div>
      </div>

      <div class="flex gap-2">
        <Button
        class="w-full flex items-center justify-center gap-2 bg-white !text-gray-800 border border-gray-300 hover:bg-gray-50"
        @click="registerWithGoogle"
      >
        <GoogleIcon class="w-5 h-5" />
        <p class="text-sm">Google</p>
      </Button>
      <Button
        class="w-full flex items-center justify-center gap-2 bg-white !text-gray-800 border border-gray-300 hover:bg-gray-50"
        @click="registerWithFacebook"
      >
        <FacebookIcon class="w-5 h-5" />
        <p class="text-sm">Facebook</p>
      </Button>
      <Button
        class="w-full flex items-center justify-center gap-2 bg-white !text-gray-800 border border-gray-300 hover:bg-gray-50"
        @click="registerWithApple"
      >
        <AppleIcon class="w-5 h-5" />
        <p class="text-sm">Apple</p>
      </Button>
      </div>

      <div class="text-sm mt-4 text-center">
        Đã có tài khoản?
        <router-link to="/login" class="text-[#4392E0] hover:underline">
          Đăng nhập ngay
        </router-link>
      </div>
    </div>
  </div>
</template>
