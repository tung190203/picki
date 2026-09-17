<template>
    <div class="min-h-screen">
        <!-- Header -->
        <div class="flex items-center px-3 sm:px-4 py-2 sm:py-3 border-gray-100 sticky top-0 bg-transparent backdrop-blur-md z-20">
            <button @click="$router.back()" class="mr-2 sm:mr-4 hover:bg-gray-100 p-1.5 sm:p-2 rounded-full transition-colors">
                <ArrowLeftIcon class="w-5 h-5 sm:w-6 sm:h-6 text-gray-900" />
            </button>
            <h1 class="text-base sm:text-lg font-semibold text-[#3E414C]">Tạo câu lạc bộ</h1>
        </div>

        <div class="mx-auto p-3 sm:p-4 md:p-6 lg:p-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 md:gap-8">
                <!-- Left Column: Form -->
                <div class="lg:col-span-8 space-y-4 sm:space-y-6 md:space-y-8 p-3 sm:p-4 rounded-xl bg-white shadow-lg">

                    <!-- Cover & Avatar Section -->
                    <div class="relative mb-8 sm:mb-10 md:mb-12">
                        <!-- Cover Image -->
                        <div class="w-full aspect-[3/1] bg-gray-900 rounded-xl overflow-hidden relative group">
                            <img :src="form.cover_image_url || defaultCover" class="w-full h-full object-cover"
                                :class="{ 'opacity-50': !form.cover_image_url }" alt="Cover" />

                            <div class="absolute inset-0 flex items-center justify-center">
                                <button @click="triggerFileInput('coverInput')"
                                    class="bg-white/90 hover:bg-white text-gray-800 px-3 sm:px-4 py-1.5 sm:py-2 rounded-md flex items-center gap-1 sm:gap-2 text-xs sm:text-sm font-medium shadow-sm transition-all">
                                    <CameraIcon class="w-4 h-4 sm:w-5 sm:h-5" />
                                    <span class="hidden sm:inline">{{ form.cover_image_url ? 'Thay ảnh bìa' : 'Thêm ảnh bìa' }}</span>
                                    <span class="sm:hidden">{{ form.cover_image_url ? 'Đổi' : 'Thêm' }}</span>
                                </button>
                            </div>
                            <input type="file" ref="coverInput" class="hidden" accept="image/*"
                                @change="handleFileChange($event, 'cover')" />
                        </div>

                        <!-- Avatar -->
                        <div class="absolute -bottom-8 sm:-bottom-10 left-1/2 transform -translate-x-1/2">
                            <div class="relative group">
                                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full overflow-hidden bg-white shadow-md">
                                    <img :src="form.logo_url || defaultAvatar" class="w-full h-full object-cover"
                                        alt="Avatar" />
                                </div>
                                <div class="absolute bottom-0 right-0 bg-[#4392E0] rounded-full p-1 sm:p-1.5 shadow-lg cursor-pointer hover:bg-[#3280ce] transition-colors z-10 border border-white"
                                    @click="triggerFileInput('avatarInput')">
                                    <PencilIcon class="w-3 h-3 sm:w-4 sm:h-4 text-white" />
                                </div>
                                <input type="file" ref="avatarInput" class="hidden" accept="image/*"
                                    @change="handleFileChange($event, 'avatar')" />
                            </div>
                        </div>
                    </div>

                    <!-- Basic Info -->
                    <div class="space-y-4 sm:space-y-6">
                        <!-- Club Name -->
                        <div>
                            <label class="block text-base sm:text-lg md:text-xl font-semibold text-[#838799] uppercase mb-2">TÊN CÂU LẠC
                                BỘ</label>
                            <div class="relative">
                                <input v-model="form.name" type="text"
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 bg-gray-100 border-none rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 transition-colors placeholder:text-gray-400 font-medium text-sm sm:text-base text-gray-900"
                                    :class="{ 'ring-2 ring-red-500/50 bg-red-50': errors.name }"
                                    placeholder="Tên CLB" @input="errors.name = ''" />
                                <CheckIcon v-if="form.name && !errors.name"
                                    class="w-4 h-4 sm:w-5 sm:h-5 text-green-500 absolute right-3 top-1/2 transform -translate-y-1/2" />
                            </div>
                            <p v-if="errors.name" class="mt-1 text-xs sm:text-sm text-red-500">{{ errors.name }}</p>
                        </div>

                        <!-- Location Search -->
                        <div>
                            <label class="block text-base sm:text-lg md:text-xl font-semibold text-[#838799] uppercase mb-2">ĐỊA ĐIỂM
                                HOẠT ĐỘNG</label>
                            <SearchSelect v-model="form.address" :items="locations" placeholder="Tìm kiếm địa điểm"
                                :has-icon="true" :has-arrow="false" @select="onLocationSelect">
                                <template #icon>
                                    <MapPinIcon class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400" />
                                </template>
                            </SearchSelect>
                        </div>
                        <!-- Introduction -->
                        <div>
                            <div class="flex justify-between mb-2">
                                <label class="block text-base sm:text-lg md:text-xl font-semibold text-[#838799] uppercase">GIỚI THIỆU
                                    CLB</label>
                                <span class="text-[10px] sm:text-xs text-gray-400">{{ form.description.length }}/300</span>
                            </div>
                            <textarea v-model="form.description" rows="4" maxlength="300"
                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 bg-gray-100 border-none rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 transition-colors placeholder:text-gray-400 text-sm sm:text-base text-gray-900"
                                placeholder="Hãy chia sẻ một chút về CLB của bạn"></textarea>
                        </div>
                    </div>
                    <!-- Public Toggle -->
                    <div class="py-2">
                        <Toggle :value="form.is_public" label="Công khai CLB"
                            description="Cho phép mọi người có thể tìm thấy CLB"
                            @update="val => form.is_public = val" />
                    </div>

                    <!-- Footer Action -->
                    <div
                        class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 p-3 sm:p-4 z-30 lg:relative lg:border-none lg:bg-transparent lg:p-0 flex flex-col sm:flex-row gap-3 sm:gap-4 mt-6 sm:mt-8">
                        <button @click="submitClub(false)" :disabled="isLoading"
                            class="w-full sm:flex-1 lg:flex-none lg:px-12 py-2.5 sm:py-3 bg-[#D72D36] hover:bg-[#D72D36]/80 text-white font-semibold rounded-md transition-colors justify-center flex items-center gap-2 text-sm sm:text-base"
                            :class="{ 'opacity-70 cursor-not-allowed': isLoading }">
                            <span v-if="isLoading"
                                class="w-4 h-4 sm:w-5 sm:h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                            <span>{{ isLoading ? 'Đang xử lý...' : 'Tạo CLB' }}</span>
                        </button>
                        <button @click="submitClub(true)" :disabled="isLoading"
                            class="w-full sm:flex-1 lg:flex-none lg:px-12 py-2.5 sm:py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-md transition-colors justify-center text-sm sm:text-base">
                            Lưu bản nháp
                        </button>
                    </div>

                    <!-- Safe space for fixed footer on mobile -->
                    <div class="h-20 lg:hidden"></div>

                </div>

                <!-- Right Column: Preview -->
                <div class="hidden lg:block lg:col-span-4 p-4 rounded-xl bg-white shadow-lg h-fit">
                    <div class="sticky top-24 overflow-hidden">
                        <!-- Preview Header/Cover -->
                        <div class="h-32 w-full bg-gray-900 relative">
                            <img :src="form.cover_image_url || defaultCover"
                                class="w-full h-full object-cover opacity-80" alt="Cover Preview" />
                        </div>

                        <div class="px-4 sm:px-6 pb-4 sm:pb-6 mt-[-40px] relative">
                            <!-- Preview Avatar -->
                            <div class="text-center mb-3 sm:mb-4">
                                <div
                                    class="w-18 h-18 sm:w-20 sm:h-20 rounded-full overflow-hidden bg-white mx-auto shadow-sm inline-block">
                                    <img :src="form.logo_url || defaultAvatar" class="w-full h-full object-cover"
                                        alt="Avatar Preview" />
                                </div>
                            </div>

                            <!-- Preview Info -->
                            <div class="mb-4 sm:mb-6">
                                <h3 class="text-base sm:text-lg font-semibold text-[#3E414C] mb-1">{{ form.name || 'Tên CLB' }}</h3>
                                <p class="text-xs sm:text-sm text-gray-500 flex items-baseline gap-1">
                                    <MapPinIcon class="w-3 h-3 sm:w-4 sm:h-4 shrink-0" />
                                    <span>{{ form.address || 'Vị trí' }}</span>
                                </p>
                            </div>

                            <!-- Preview Stats -->
                            <div class="grid grid-cols-[80px_1fr] sm:grid-cols-[100px_1fr] gap-y-2 sm:gap-y-3 gap-x-3 sm:gap-x-4 text-xs sm:text-sm mb-4 sm:mb-6">
                                <div class="text-gray-400">Quyền riêng tư</div>
                                <div class="text-right">
                                    <span v-if="form.is_public"
                                        class="bg-[#C8F6E7] text-[#00B377] text-[10px] sm:text-xs px-1.5 sm:px-2 py-0.5 sm:py-1 rounded font-medium">Công
                                        khai</span>
                                    <span v-else
                                        class="bg-[#F6F6F6] text-[#838799] text-[10px] sm:text-xs px-1.5 sm:px-2 py-0.5 sm:py-1 rounded font-medium">Riêng
                                        tư</span>
                                </div>
                            </div>

                            <!-- Preview Description -->
                            <div>
                                <div class="text-[10px] sm:text-xs font-bold text-gray-400 uppercase mb-1 sm:mb-2">MÔ TẢ</div>
                                <p class="text-xs sm:text-sm text-gray-600 leading-relaxed whitespace-pre-wrap">
                                    {{ form.description || 'Hãy chia sẻ một chút về CLB của bạn' }}
                                </p>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick, onUnmounted } from 'vue'
import { ArrowLeftIcon, CameraIcon, PencilIcon, CheckIcon, MapPinIcon } from '@heroicons/vue/24/outline'
import Toggle from '@/components/atoms/Toggle.vue'
import SearchSelect from '@/components/molecules/SearchSelect.vue'
import defaultCover from '@/assets/images/club-default-thumbnail.svg?url'
import * as ClubService from '@/service/club'
import debounce from 'lodash.debounce'
import { watch } from 'vue'
import { toast } from 'vue3-toastify'
import { useRouter } from 'vue-router'

const router = useRouter()
const locations = ref([])
const defaultAvatar = "/images/default-avatar.png";
const coverInput = ref(null)
const avatarInput = ref(null)
const isLoading = ref(false)
const isSelecting = ref(false)
const errors = ref({})

const form = ref({
    name: '',
    description: '',
    address: '',
    latitude: null,
    longitude: null,
    cover_image_url: null,
    logo_url: null,
    cover_file: null,
    logo_file: null,
    is_public: true
})

const triggerFileInput = (refName) => {
    if (refName === 'coverInput' && coverInput.value) coverInput.value.click()
    if (refName === 'avatarInput' && avatarInput.value) avatarInput.value.click()
}

const handleFileChange = (event, type) => {
    const file = event.target.files[0]
    if (!file) return

    if (file.size > 2 * 1024 * 1024) {
        toast.error('Kích thước ảnh không được quá 2MB')
        return
    }

    const reader = new FileReader()
    reader.onload = (e) => {
        if (type === 'cover') {
            form.value.cover_image_url = e.target.result
            form.value.cover_file = file
        } else {
            form.value.logo_url = e.target.result
            form.value.logo_file = file
        }
    }
    reader.readAsDataURL(file)
}

const fetchLocations = async (query) => {
    if (!query) return
    try {
        const res = await ClubService.searchLocation({ query })
        // Map Google Place predictions to { name: description, place_id: ... }
        if (res?.data) {
            locations.value = res.data.map(p => ({
                name: p.description,
                place_id: p.place_id
            }))
        }
    } catch (e) {
        console.error(e)
    }
}

const onLocationSelect = async (item) => {
    debouncedFetchLocations.cancel()
    if (item && item.place_id) {
        isSelecting.value = true
        form.value.address = item.name // Update display
        await nextTick()
        isSelecting.value = false

        try {
            const detail = await ClubService.locationDetail({ place_id: item.place_id })
            if (detail?.data?.result?.geometry?.location) {
                form.value.latitude = detail.data.result.geometry.location.lat
                form.value.longitude = detail.data.result.geometry.location.lng
            }
        } catch (e) {
            console.error('Error fetching location detail:', e)
        }
    }
}

const debouncedFetchLocations = debounce(fetchLocations, 500)

watch(() => form.value.address, (val) => {
    if (!isSelecting.value) {
        debouncedFetchLocations(val)
    }
})

onUnmounted(() => {
    debouncedFetchLocations.cancel()
})

const validateForm = () => {
    errors.value = {}
    let isValid = true

    if (!form.value.name || !form.value.name.trim()) {
        errors.value.name = 'Tên câu lạc bộ là bắt buộc'
        isValid = false
    }

    return isValid
}

const submitClub = async (isDraft = false) => {
    if (!validateForm()) {
        toast.error('Vui lòng kiểm tra lại thông tin')
        return
    }

    if (isLoading.value) return
    isLoading.value = true

    try {
        const formData = new FormData()
        formData.append('name', form.value.name)
        formData.append('description', form.value.description)
        formData.append('address', form.value.address)
        if (form.value.latitude) formData.append('latitude', form.value.latitude)
        if (form.value.longitude) formData.append('longitude', form.value.longitude)
        formData.append('is_public', form.value.is_public ? 1 : 0)

        if (isDraft) {
            formData.append('status', 'draft')
        }

        if (form.value.cover_file) {
            formData.append('cover_image_url', form.value.cover_file)
        }
        if (form.value.logo_file) {
            formData.append('logo_url', form.value.logo_file)
        }

        const res = await ClubService.createClub(formData)

        if (isDraft) {
            toast.success('Lưu nháp thành công')
        } else {
            toast.success('Tạo CLB thành công')
            router.push({ name: 'club-detail', params: { id: res.data.id } })
        }
    } catch (error) {
        console.error('Error creating club:', error)
        toast.error('Có lỗi xảy ra, vui lòng thử lại')
    } finally {
        isLoading.value = false
    }
}

</script>

<style scoped>
/* Any additional specific styles if needed */
</style>
