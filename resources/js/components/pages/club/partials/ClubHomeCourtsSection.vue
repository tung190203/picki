<template>
    <div class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-2xl shadow-sm p-4 sm:p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-[#FBEAEB] dark:bg-[#D72D36]/20 flex items-center justify-center">
                    <MapPinIcon class="w-5 h-5 text-[#D72D36] dark:text-red-400" />
                </div>
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-[#1F2937] dark:text-slate-100">Sân nhà</h3>
                    <p class="text-xs sm:text-sm text-[#838799] dark:text-slate-400">
                        {{ canManage ? 'Quản lý các sân tổ chức hoạt động' : 'Danh sách sân CLB thường tổ chức' }}
                    </p>
                </div>
            </div>
            <button v-if="canManage" @click="openAddModal"
                class="flex items-center gap-1.5 px-3 sm:px-4 py-2 bg-[#D72D36] hover:bg-[#c9252e] text-white text-sm font-semibold rounded-lg transition-colors">
                <PlusIcon class="w-4 h-4" />
                <span class="hidden sm:inline">Thêm sân</span>
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="py-8 text-center">
            <div class="inline-block w-6 h-6 border-2 border-[#D72D36] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Empty -->
        <div v-else-if="courts.length === 0" class="py-8 text-center text-sm text-[#838799] dark:text-slate-400">
            <MapPinIcon class="w-10 h-10 mx-auto mb-2 opacity-30" />
            <p>Chưa có sân nhà nào</p>
        </div>

        <!-- List -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div v-for="court in courts" :key="court.id"
                class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-[#FBEAEB] dark:bg-[#D72D36]/20 flex items-center justify-center">
                    <MapPinIcon class="w-4 h-4 text-[#D72D36] dark:text-red-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-[#1F2937] dark:text-slate-100 truncate">
                        {{ court.name }}
                    </div>
                    <div class="text-xs text-[#838799] dark:text-slate-400 truncate">
                        {{ court.address || '—' }}
                    </div>
                    <div class="flex items-center gap-3 mt-1 text-[11px] text-[#838799] dark:text-slate-500">
                        <span v-if="court.distance_km !== null">📍 {{ court.distance_km }} km</span>
                        <span>🏆 {{ court.events_hosted_count }} kèo/giải</span>
                    </div>
                </div>
                <div v-if="canManage" class="flex items-center gap-1 flex-shrink-0">
                    <button @click="confirmDelete(court)"
                        class="p-1.5 text-gray-400 hover:text-red-500 transition-colors" title="Xóa">
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Add -->
        <Transition name="fade">
            <div v-if="showFormModal"
                class="fixed inset-0 z-[9999] flex items-center justify-center p-3 bg-black/60 backdrop-blur-sm"
                @click.self="closeFormModal">
                <div class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-2xl w-full max-w-[480px] shadow-2xl flex flex-col max-h-[90vh]">
                    <div class="p-4 sm:p-5 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between">
                        <h3 class="font-bold text-[#1F2937] dark:text-slate-100">Thêm sân nhà</h3>
                        <button @click="closeFormModal" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-4 sm:p-5 space-y-3 overflow-y-auto">
                        <div>
                            <label class="block text-sm font-medium text-[#3E414C] dark:text-slate-200 mb-1">
                                Tìm sân <span class="text-[#D72D36]">*</span>
                            </label>
                            <input v-model="searchKeyword" @input="onSearchInput" type="text"
                                placeholder="Nhập tên hoặc địa chỉ (tối thiểu 2 ký tự)"
                                class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36]" />
                            <p class="text-[11px] text-[#838799] dark:text-slate-500 mt-1">
                                Khoảng cách và số kèo/giải sẽ được tự động tính.
                            </p>
                        </div>

                        <div v-if="searching" class="py-2 text-center">
                            <div class="inline-block w-4 h-4 border-2 border-[#D72D36] border-t-transparent rounded-full animate-spin"></div>
                        </div>

                        <div v-else-if="searchKeyword.length >= 2 && filteredLocations.length === 0" class="py-2 text-center text-xs text-[#838799]">
                            Không tìm thấy sân nào
                        </div>

                        <div v-else-if="searchKeyword.length >= 2" class="max-h-[280px] overflow-y-auto space-y-1 border border-gray-100 dark:border-slate-700 rounded-lg p-1">
                            <button v-for="loc in filteredLocations" :key="loc.id" @click="selectLocation(loc)"
                                :class="[
                                    'w-full text-left px-3 py-2 rounded-md text-sm transition-colors',
                                    form.competition_location_id === loc.id
                                        ? 'bg-[#FBEAEB] dark:bg-[#D72D36]/20 text-[#D72D36] dark:text-red-400 font-semibold'
                                        : 'hover:bg-gray-50 dark:hover:bg-slate-800/50 text-[#1F2937] dark:text-slate-100'
                                ]">
                                <div class="font-medium truncate">{{ loc.name }}</div>
                                <div class="text-[11px] text-[#838799] dark:text-slate-400 truncate">
                                    {{ loc.address || '—' }}
                                </div>
                            </button>
                        </div>

                        <div v-if="selectedLocation" class="p-3 bg-gray-50 dark:bg-slate-800/60 rounded-lg">
                            <div class="text-[11px] text-[#838799] dark:text-slate-400 mb-1">Đã chọn</div>
                            <div class="font-semibold text-sm text-[#1F2937] dark:text-slate-100">
                                {{ selectedLocation.name }}
                            </div>
                            <div class="text-xs text-[#838799] dark:text-slate-400">
                                {{ selectedLocation.address || '—' }}
                            </div>
                        </div>
                    </div>

                    <div class="p-4 sm:p-5 border-t border-gray-100 dark:border-slate-800 flex gap-2">
                        <button @click="closeFormModal"
                            class="flex-1 px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-700 text-[#3E414C] dark:text-slate-200 font-semibold hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                            Hủy
                        </button>
                        <button @click="submitForm" :disabled="submitting || !form.competition_location_id"
                            class="flex-1 px-4 py-2 rounded-lg bg-[#D72D36] text-white font-semibold hover:bg-[#c9252e] transition-colors disabled:opacity-50">
                            {{ submitting ? 'Đang lưu...' : 'Thêm' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Confirm Delete -->
        <Transition name="fade">
            <div v-if="showDeleteConfirm"
                class="fixed inset-0 z-[9999] flex items-center justify-center p-3 bg-black/60 backdrop-blur-sm"
                @click.self="showDeleteConfirm = false">
                <div class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-2xl w-full max-w-[400px] shadow-2xl p-5">
                    <h3 class="font-bold text-[#1F2937] dark:text-slate-100 mb-2">Xóa sân nhà?</h3>
                    <p class="text-sm text-[#838799] dark:text-slate-400 mb-5">
                        Bạn có chắc muốn xóa <strong>{{ deletingCourt?.name }}</strong> khỏi danh sách sân nhà?
                    </p>
                    <div class="flex gap-2">
                        <button @click="showDeleteConfirm = false"
                            class="flex-1 px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-700 font-semibold hover:bg-gray-50 dark:hover:bg-slate-800">
                            Hủy
                        </button>
                        <button @click="doDelete" :disabled="deleting"
                            class="flex-1 px-4 py-2 rounded-lg bg-red-500 text-white font-semibold hover:bg-red-600 disabled:opacity-50">
                            {{ deleting ? 'Đang xóa...' : 'Xóa' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { toast } from 'vue3-toastify'
import {
    MapPinIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline'
import {
    getHomeCourts,
    setHomeCourts,
    deleteHomeCourt
} from '@/service/club'
import { getAllCompetitionLocations } from '@/service/competitionLocation'
import { requestUserAnchor } from '@/utils/httpRequest'

const props = defineProps({
    clubId: { type: [String, Number], required: true },
    canManage: { type: Boolean, default: false },
    initialCourts: { type: Array, default: () => [] }
})

const emit = defineEmits(['updated'])

const courts = ref([...props.initialCourts])
const loading = ref(false)

const showFormModal = ref(false)
const submitting = ref(false)
const searchKeyword = ref('')
const searching = ref(false)
const searchResults = ref([])
const form = ref({ competition_location_id: null })
const selectedLocation = computed(() =>
    searchResults.value.find(l => l.id === form.value.competition_location_id) || null
)
const filteredLocations = computed(() => {
    const taken = new Set(courts.value.map(c => c.competition_location_id))
    return searchResults.value.filter(l => !taken.has(l.id))
})

const showDeleteConfirm = ref(false)
const deletingCourt = ref(null)
const deleting = ref(false)

let searchDebounce = null

const fetchCourts = async () => {
    loading.value = true
    try {
        courts.value = await getHomeCourts(props.clubId)
    } catch (err) {
        toast.error('Không tải được danh sách sân nhà')
    } finally {
        loading.value = false
    }
}

const onSearchInput = () => {
    if (searchDebounce) clearTimeout(searchDebounce)
    searchDebounce = setTimeout(runSearch, 350)
}

const runSearch = async () => {
    const kw = searchKeyword.value.trim()
    if (kw.length < 2) {
        searchResults.value = []
        return
    }
    searching.value = true
    try {
        const res = await getAllCompetitionLocations(kw, 1)
        const raw = res?.data?.competition_locations ?? res?.data?.data ?? res?.data ?? []
        searchResults.value = Array.isArray(raw) ? raw : []
    } catch (err) {
        searchResults.value = []
        toast.error('Không tìm được sân')
    } finally {
        searching.value = false
    }
}

const selectLocation = (loc) => {
    form.value.competition_location_id = loc.id
}

const openAddModal = () => {
    form.value = { competition_location_id: null }
    searchKeyword.value = ''
    searchResults.value = []
    showFormModal.value = true
}

const closeFormModal = () => {
    showFormModal.value = false
    if (searchDebounce) clearTimeout(searchDebounce)
}

const submitForm = async () => {
    if (!form.value.competition_location_id) return
    submitting.value = true
    try {
        const payload = [{
            competition_location_id: form.value.competition_location_id,
            position: courts.value.length
        }]
        const updated = await setHomeCourts(props.clubId, payload)
        courts.value = Array.isArray(updated) ? updated : await getHomeCourts(props.clubId)
        toast.success('Đã thêm sân nhà')
        emit('updated')
        closeFormModal()
    } catch (err) {
        toast.error(err?.response?.data?.message || 'Không thêm được sân')
    } finally {
        submitting.value = false
    }
}

const confirmDelete = (court) => {
    deletingCourt.value = court
    showDeleteConfirm.value = true
}

const doDelete = async () => {
    deleting.value = true
    try {
        await deleteHomeCourt(props.clubId, deletingCourt.value.id)
        toast.success('Đã xóa sân nhà')
        await fetchCourts()
        emit('updated')
        showDeleteConfirm.value = false
    } catch (err) {
        toast.error(err?.response?.data?.message || 'Không xóa được')
    } finally {
        deleting.value = false
    }
}

watch(() => props.initialCourts, (val) => {
    if (val && val.length > 0) {
        courts.value = [...val]
    }
}, { deep: true })

onMounted(async () => {
    // Đặt anchor toạ độ user (nếu được cấp quyền) trước khi fetch
    requestUserAnchor()
    if (props.initialCourts?.length === 0) {
        await fetchCourts()
    }
})

onUnmounted(() => {
    if (searchDebounce) clearTimeout(searchDebounce)
})
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
