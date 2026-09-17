<template>
    <div class="min-h-screen bg-gray-100 p-3 lg:p-4 xl:p-6">
        <div class="max-w-7xl mx-auto">
            <!-- Header Card -->
            <div class="bg-white rounded-[8px] shadow p-4 sm:p-6 mb-4 sm:mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 sm:gap-4">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1">Câu lạc bộ Pickleball</h1>
                        <p class="text-xs sm:text-sm text-gray-500">Khám phá và tham gia cộng đồng Pickleball</p>
                    </div>
                    <div class="relative flex-1 md:max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 sm:h-5 sm:w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            v-model="search" 
                            placeholder="Tìm kiếm câu lạc bộ..."
                            class="w-full pl-9 sm:pl-10 pr-3 sm:pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all focus:outline-none text-sm"
                        />
                    </div>
                </div>
            </div>

            <!-- Results Count -->
            <div class="mb-3 sm:mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
                <p class="text-xs sm:text-sm text-gray-600">
                    <span class="font-semibold text-gray-900">{{ totalClubs }}</span> câu lạc bộ
                </p>
                <button 
                    @click="router.push({ name: 'create-club' })"
                    class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-3 sm:px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg shadow transition-colors"
                >
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tạo câu lạc bộ
                </button>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-6">
                <div v-for="n in 6" :key="n" class="bg-white rounded-[8px] shadow p-4 sm:p-5 animate-pulse">
                    <div class="flex items-start gap-3 sm:gap-4 mb-3 sm:mb-4">
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-200 rounded-full"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-3 sm:h-4 bg-gray-200 rounded w-3/4"></div>
                            <div class="h-2 sm:h-3 bg-gray-200 rounded w-1/2"></div>
                        </div>
                    </div>
                    <div class="h-2 sm:h-3 bg-gray-200 rounded w-full mb-2"></div>
                    <div class="h-2 sm:h-3 bg-gray-200 rounded w-2/3"></div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="clubs.length === 0" class="bg-white rounded-[8px] shadow p-8 sm:p-12 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full mb-3 sm:mb-4">
                    <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-1">Không tìm thấy câu lạc bộ</h3>
                <p class="text-xs sm:text-sm text-gray-500">Thử tìm kiếm với từ khóa khác</p>
            </div>

            <!-- Club Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-6">
                <div 
                    v-for="club in clubs" 
                    :key="club.id"
                    @click="handleClubClick(club)"
                    class="bg-white rounded-[8px] shadow hover:shadow-lg transition-all cursor-pointer flex flex-col overflow-hidden"
                >
                    <!-- Cover Image -->
                    <div class="w-full h-20 sm:h-24 md:h-28 bg-gray-100 shrink-0 relative">
                        <img 
                            :src="club.cover_image_url || Background" 
                            alt="Cover" 
                            class="w-full h-full object-cover"
                        />
                    </div>
                    
                    <!-- Content -->
                    <div class="px-4 sm:px-5 pb-4 sm:pb-5 pt-0 flex-1 flex flex-col relative">
                        <!-- Header: Logo + Name overlay -->
                        <div class="relative -mt-6 mb-3 inline-block self-start">
                            <img 
                                :src="club.logo_url || 'https://picki.vn/images/default-avatar.png'" 
                                alt="Logo"
                                class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 rounded-full object-cover border-3 sm:border-4 border-white bg-white shadow-sm"
                            />
                            <div v-if="club.is_verified" class="absolute bottom-0 right-0 bg-[#4392E0] rounded-full p-0.5 sm:p-1 z-10 border-2 border-white box-content">
                                <VerifyIcon class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-white" />
                            </div>
                        </div>

                        <!-- Name & Address -->
                        <div class="mb-3 sm:mb-4">
                            <h3 class="text-sm sm:text-base font-bold text-gray-900 mb-1 line-clamp-1" v-tooltip="club.name">
                                {{ club.name }}
                            </h3>
                            <div v-if="club.address" class="flex items-start gap-1 text-[10px] sm:text-xs text-gray-500">
                                <MapPinIcon class="w-3 h-3 sm:w-4 sm:h-4 shrink-0 mt-0.5 text-gray-400" />
                                <span class="line-clamp-2" v-tooltip="club.address">{{ club.address }}</span>
                            </div>
                        </div>

                        <!-- Description -->
                        <p class="text-xs sm:text-sm text-gray-600 line-clamp-2 mb-3 sm:mb-4 leading-relaxed whitespace-pre-wrap">
                            {{ club.profile?.description || 'Chưa có mô tả' }}
                        </p>

                        <!-- Stats -->
                        <div class="flex items-center justify-between pt-3 sm:pt-4 border-t border-gray-100 mt-auto">
                            <div class="flex items-center gap-2 sm:gap-4 text-[10px] sm:text-xs text-gray-600">
                                <div class="flex items-center gap-1">
                                    <svg class="w-3 h-3 sm:w-4 sm:h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                    </svg>
                                    <span class="font-medium">{{ club.quantity_members }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <svg class="w-3 h-3 sm:w-4 sm:h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                    <span class="font-medium">{{ club.skill_level?.min || 0 }}-{{ club.skill_level?.max || 0 }}</span>
                                </div>
                                <div v-if="club.vnrank" class="flex items-center gap-1 text-yellow-600">
                                    <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="font-medium">{{ club.vnrank }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="clubs.length > 0 && lastPage > 1" class="mt-4 sm:mt-6 flex justify-center">
                <div class="inline-flex items-center gap-1 sm:gap-2 bg-white rounded-[8px] shadow p-1">
                    <button 
                        @click="page--" 
                        :disabled="page <= 1 || loading"
                        class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        Trước
                    </button>
                    <span class="px-3 sm:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-medium text-gray-900">
                        {{ page }} / {{ lastPage }}
                    </span>
                    <button 
                        @click="page++" 
                        :disabled="page >= lastPage || loading"
                        class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        Sau
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import Background from '@/assets/images/club-default-thumbnail.svg?url'
import * as ClubService from '@/service/club.js'
import { MapPinIcon } from '@heroicons/vue/24/solid'
import VerifyIcon from "@/assets/images/verify-icon.svg";
import debounce from 'lodash.debounce'
import { useRouter } from 'vue-router'

const router = useRouter()
const search = ref('')
const clubs = ref([])
const loading = ref(false)
const page = ref(1)
const perPage = ref(9)
const totalClubs = ref(0)
const lastPage = ref(1)

const getAllClubs = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (search.value?.trim()) params.name = search.value.trim()
        const response = await ClubService.getAllClubs(params)
        // Laravel Resource::collection wraps in { data: [...] }
        const clubsData = response.data?.clubs
        clubs.value = Array.isArray(clubsData) ? clubsData : (clubsData?.data || [])
        totalClubs.value = response.meta?.total ?? 0
        lastPage.value = response.meta?.last_page ?? 1
    } catch (error) {
        console.error('Lỗi tải danh sách CLB:', error)
        clubs.value = []
    } finally {
        loading.value = false
    }
}

// Debounce tìm kiếm
const debouncedSearch = debounce(() => {
    page.value = 1
    getAllClubs()
}, 500)

watch(search, () => {
    debouncedSearch()
})

watch(page, () => {
    getAllClubs()
    // Scroll lên đầu trang khi chuyển trang
    window.scrollTo({ top: 0, behavior: 'smooth' })
})

const handleClubClick = (club) => {
    router.push({ name: 'club-detail', params: { id: club.id } })
}

onMounted(async () => {
    await getAllClubs()
})
</script>