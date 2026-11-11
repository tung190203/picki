<script setup>
import { reactive, computed, ref } from 'vue'
import useVuelidate from '@vuelidate/core'
import { required, helpers } from '@vuelidate/validators'
import LogoSplash from '@/assets/images/logo-splash.svg'
import { useRoute, useRouter } from 'vue-router'
import Button from '@/components/atoms/Button.vue'
import { useUserStore } from '@/store/auth'
import { toast } from 'vue3-toastify'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const login = ref(route.query.login || '')

const data = reactive({
  login: login,
  full_name: '',
  password: '',
  password_confirmation: '',
})

const notDefaultName = helpers.withMessage(
  'Vui lòng nhập họ và tên thực tế của bạn (không dùng tên mặc định dạng PickiUser)',
  (value) => {
    if (!value) return true
    return !/^picki([\s_-]*u?s*e*r*|\d+|$)/i.test(value.trim())
  }
)

const rules = computed(() => ({
    full_name: {
      required: helpers.withMessage('Họ và tên không được để trống', required),
      minLength: helpers.withMessage('Họ và tên tối thiểu 2 ký tự', value => !value || value.trim().length >= 2),
      notDefaultName,
    },
    password: {
      required: helpers.withMessage('Không được để trống', required),
      minLength: helpers.withMessage('Mật khẩu phải có ít nhất 6 ký tự', value => !value || value.length >= 6)
    },
    password_confirmation: {
      required: helpers.withMessage('Không được để trống', required),
      sameAsPassword: helpers.withMessage('Mật khẩu xác nhận không khớp', value => value === data.password)
    }
}))

const v$ = useVuelidate(rules, data)

const fillData = async () => {
  v$.value.$touch()
  if (!v$.value.$invalid) {
    try {
      await userStore.fillPassword(data)
      toast.success('Hoàn tất đăng ký')
      setTimeout(() => {
        router.push({ path: '/login' })
      }, 1000)
    } catch (error) {
      const message = error.response?.data?.message || 'Đăng ký thất bại, vui lòng thử lại!'
      toast.error(`${message}`)
      console.error('Đăng ký thất bại:', error)
    }
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center px-4">
    <LogoSplash class="w-[60%]" />
    <div class="text-center mb-8 mt-8">
      <h1 class="text-white text-2xl mb-2">Hoàn tất đăng ký</h1>
      <p class="text-sm text-white font-light">Tận hưởng toàn bộ tính năng của Pickleball, bao gồm cập nhật giải đấu, 
        bảng xếp hạng và thông báo trận đấu độc quyền!</p>
    </div>
    <div class="w-full max-w-md p-8 bg-white rounded-[12px] shadow">
      <form @submit.prevent="fillData" class="space-y-4">
        <div>
          <label for="full_name" class="form-in font-semibold text-[14px]">Họ và tên</label>
          <input
            id="full_name"
            type="text"
            placeholder="Nhập họ và tên của bạn"
            v-model="data.full_name"
            class="w-full px-4 py-2 my-1 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder:text-sm"
          />
          <p v-for="err in v$.full_name.$errors" class="text-sm text-red-500 mt-1">
            {{ err.$message }}
          </p>
        </div>
        <div>
          <label for="password" class="form-in font-semibold text-[14px]">Mật khẩu</label>
          <input
            id="password"
            type="password"
            placeholder="Nhập mật khẩu của bạn"
            v-model="data.password"
            class="w-full px-4 py-2 my-1 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder:text-sm"
          />
          <p v-for="err in v$.password.$errors" class="text-sm text-red-500 mt-1">
            {{ err.$message }}
          </p>
        </div>
        <div>
          <label for="password_confirmation" class="form-in font-semibold text-[14px]">Xác nhận mật khẩu</label>
          <input
            id="password_confirmation"
            type="password"
            placeholder="Xác nhận mật khẩu của bạn"
            v-model="data.password_confirmation"
            class="w-full px-4 py-2 my-1 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder:text-sm"
          />
          <p v-for="err in v$.password_confirmation.$errors" class="text-sm text-red-500 mt-1">
            {{ err.$message }}
          </p>
        </div>
        <Button
          type="submit"
          :class="{
            'w-full !bg-primary hover:!bg-secondary': data.full_name && data.password && data.password_confirmation,
            'w-full !bg-[#edeef2] !text-[#333333] hover:!bg-[#edeefe]': !data.full_name || !data.password || !data.password_confirmation
          }"
        >
            Hoàn tất đăng ký
        </Button>
      </form>
    </div>
  </div>
</template>
