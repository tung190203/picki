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
                            v-model="search"
                            type="text"
                            placeholder="Tìm kiếm CLB..."
                            class="w-full pl-9 sm:pl-10 pr-3 sm:pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all focus:outline-none text-sm"
                        />
                    </div>
                </div>
            </div>

            <!-- Sub-tabs -->
            <div class="mb-3 sm:mb-4 bg-white rounded-[8px] shadow-sm border border-gray-100 flex">
                <button
                    v-for="tab in subTabs"
                    :key="tab.key"
                    @click="activeSubTab = tab.key"
                    class="flex-1 px-3 sm:px-4 py-3 text-center text-xs sm:text-sm font-semibold transition-colors relative"
                    :class="activeSubTab === tab.key
                        ? 'text-[#D72D36] font-bold'
                        : 'text-[#838799] hover:text-gray-800'"
                >
                    {{ tab.label }}
                    <div
                        v-if="activeSubTab === tab.key"
                        class="absolute bottom-0 left-0 w-full h-0.5 bg-[#D72D36]"
                    ></div>
                </button>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-6">
                <div v-for="n in 6" :key="n" class="bg-white rounded-[8px] shadow p-4 sm:p-5 animate-pulse">
                    <div class="flex items-start gap-3 sm:gap-4 mb-3 sm:mb-4">
                        <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gray-200 rounded-full"></div>
                        <div class="flex-1 space-y-2 mt-1">
                            <div class="h-3 sm:h-4 bg-gray-200 rounded w-3/4"></div>
                            <div class="h-2 sm:h-3 bg-gray-200 rounded w-1/2"></div>
                        </div>
                    </div>
                    <div class="h-2 sm:h-3 bg-gray-200 rounded w-full mb-2"></div>
                    <div class="h-2 sm:h-3 bg-gray-200 rounded w-2/3"></div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!hasAnyContent" class="bg-white rounded-[8px] shadow p-8 sm:p-12 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full mb-3 sm:mb-4">
                    <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-1">Chưa có kết quả</h3>
                <p class="text-xs sm:text-sm text-gray-500 mb-4">Thử từ khóa khác hoặc chuyển tab.</p>
                <button
                    @click="fetchClubs(true)"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#D72D36] hover:bg-[#c4252e] text-white text-sm font-medium rounded-lg transition-colors"
                >
                    Làm mới
                </button>
            </div>

            <!-- Cards list: 1 card / row (horizontal layout) -->
            <div v-else class="grid grid-cols-1 gap-3 sm:gap-4">
                <ClubSuggestCard
                    v-for="club in clubs"
                    :key="club.id"
                    :club="club"
                    @click="handleClubClick"
                    @follow="handleFollow"
                />
            </div>

            <!-- Top-right action: create club -->
            <div class="mt-6 flex justify-end">
                <button
                    @click="router.push({ name: 'create-club' })"
                    class="inline-flex items-center justify-center gap-2 px-3 sm:px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg shadow transition-colors"
                >
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tạo câu lạc bộ
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import * as ClubService from '@/service/club.js'
import { search as searchApi } from '@/service/search.js'
import { useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'
import { requestUserAnchor } from '@/utils/httpRequest.js'
import ClubSuggestCard from '@/components/molecules/ClubSuggestCard.vue'

const router = useRouter()

/**
 * @typedef {Object} ClubCardClub
 * @property {number} id
 * @property {string} name
 * @property {string|null} [logo_url]
 * @property {string|null} [address]
 * @property {string|null} [recurring_schedule_text]
 * @property {string|null} [recruitment_status]      // 'open' | 'closed' | 'invite_only'
 * @property {string|null} [recruitment_status_text]
 * @property {number}    [quantity_members]
 * @property {number}    [followers_count]
 * @property {boolean}   [is_following]
 * @property {boolean}   [is_member]
 * @property {boolean}   [is_admin]
 * @property {boolean}   [is_verified]
 * @property {number|null} [distance]
 * @property {{min:number,max:number}|null} [score_range]
 * @property {string|null} [score_range_text]
 * @property {{id:number,name:string,address:string}|null} [primary_home_court]
 * @property {{id:number,full_name:string,avatar_url:string|null,vndupr_score:number|null}|null} [admin]
 * @property {number} [total_mini_tournaments_count]
 * @property {number} [total_tournaments_count]
 */

const subTabs = [
    { key: 'suggest', label: 'Gợi ý' },
    { key: 'following', label: 'Đang theo dõi' },
    { key: 'suit_level', label: 'Hợp trình tôi' },
]

// 'suggest' is the default tab; BE treats it as no sub_tab (all clubs).
// 'following' and 'suit_level' map directly to sub_tab.
const SUB_TAB_MAP = {
    suggest: 'all',
    following: 'following',
    suit_level: 'suit_level',
}

const activeSubTab = ref('suggest')
const clubs = ref([])
const loading = ref(false)
const search = ref('')

const hasAnyContent = computed(() => clubs.value.length > 0)

const buildParams = () => {
    const params = {
        tab: 'club',
        sub_tab: SUB_TAB_MAP[activeSubTab.value] ?? 'all',
        per_page: 30,
    }
    const q = search.value?.trim()
    if (q) params.keyword = q
    return params
}

const fetchClubs = async (silent = false) => {
    if (!silent) loading.value = true
    try {
        // Best-effort geolocation for distance; never blocks
        requestUserAnchor()
        const response = await searchApi(buildParams())
        const list = response?.data
        clubs.value = Array.isArray(list) ? list : []
    } catch (error) {
        if (error?.response?.status === 401) {
            toast.error('Vui lòng đăng nhập để xem CLB')
        } else {
            toast.error(error?.response?.data?.message || 'Không thể tải danh sách CLB')
        }
        clubs.value = []
    } finally {
        loading.value = false
    }
}

const handleClubClick = (club) => {
    if (!club?.id) return
    router.push({ name: 'club-detail', params: { id: club.id } })
}

const handleFollow = async ({ club, next }) => {
    try {
        if (next) {
            await ClubService.followClub(club.id)
            toast.success('Đã theo dõi CLB')
        } else {
            await ClubService.unfollowClub(club.id)
            toast.success('Đã bỏ theo dõi CLB')
        }
        // Refresh so is_following / counts on the card stay in sync
        await fetchClubs(true)
    } catch (error) {
        toast.error(error?.response?.data?.message || 'Không thể thay đổi trạng thái theo dõi')
        throw error
    }
}

// Refetch when tab changes
watch(activeSubTab, () => {
    fetchClubs()
})

// Debounced refetch on keyword change (server-side search)
let searchTimer = null
watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => fetchClubs(), 300)
})

onBeforeUnmount(() => {
    if (searchTimer) clearTimeout(searchTimer)
})

onMounted(async () => {
    await fetchClubs()
})
</script>
