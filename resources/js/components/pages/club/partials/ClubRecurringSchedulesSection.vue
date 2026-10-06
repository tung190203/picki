<template>
    <div class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-2xl shadow-sm p-4 sm:p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-[#FBEAEB] dark:bg-[#D72D36]/20 flex items-center justify-center">
                    <CalendarDaysIcon class="w-5 h-5 text-[#D72D36] dark:text-red-400" />
                </div>
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-[#1F2937] dark:text-slate-100">Lịch sinh hoạt định kỳ</h3>
                    <p class="text-xs sm:text-sm text-[#838799] dark:text-slate-400">
                        {{ canManage ? 'Thiết lập khung giờ tập luyện hàng tuần' : 'Khung giờ CLB sinh hoạt hàng tuần' }}
                    </p>
                </div>
            </div>
            <button v-if="canManage" @click="openAddModal"
                class="flex items-center gap-1.5 px-3 sm:px-4 py-2 bg-[#D72D36] hover:bg-[#c9252e] text-white text-sm font-semibold rounded-lg transition-colors">
                <PlusIcon class="w-4 h-4" />
                <span class="hidden sm:inline">Thêm lịch</span>
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="py-8 text-center">
            <div class="inline-block w-6 h-6 border-2 border-[#D72D36] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Empty -->
        <div v-else-if="schedules.length === 0" class="py-8 text-center text-sm text-[#838799] dark:text-slate-400">
            <CalendarDaysIcon class="w-10 h-10 mx-auto mb-2 opacity-30" />
            <p>Chưa có lịch sinh hoạt</p>
        </div>

        <!-- Weekly Grid (desktop) -->
        <div v-if="!loading && schedules.length > 0"
            class="hidden lg:grid grid-cols-7 gap-2 mb-4">
            <div v-for="(label, dayIdx) in DAY_LABELS" :key="dayIdx" class="min-h-[80px]">
                <div class="text-xs font-bold text-[#838799] dark:text-slate-400 mb-2 text-center uppercase">{{ label }}</div>
                <div class="space-y-1.5">
                    <div v-for="item in getSchedulesForDay(dayIdx)" :key="item.id"
                        @click="canManage && openEditModal(item)"
                        :class="['p-2 rounded-lg border text-xs transition-colors',
                            canManage
                                ? 'border-gray-200 dark:border-slate-700 hover:border-[#D72D36] hover:bg-[#FBEAEB] dark:hover:bg-[#D72D36]/20 cursor-pointer'
                                : 'border-gray-100 dark:border-slate-800 cursor-default']">
                        <div class="font-semibold text-[#1F2937] dark:text-slate-100">
                            {{ formatTimeRange(item.start_time, item.end_time) }}
                        </div>
                        <div class="text-[#838799] dark:text-slate-400 truncate mt-0.5">
                            {{ item.note || 'Buổi tập' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grouped by day (mobile/tablet) -->
        <div v-else-if="!loading" class="lg:hidden space-y-3">
            <div v-for="group in groupedByDay" :key="group.day">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="text-sm font-bold text-[#1F2937] dark:text-slate-100">{{ group.label }}</span>
                    <span class="text-[11px] text-[#838799] dark:text-slate-500">({{ group.items.length }})</span>
                </div>
                <div class="space-y-1.5">
                    <div v-for="item in group.items" :key="item.id"
                        class="flex items-center gap-3 p-2.5 rounded-lg border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-xs font-semibold text-[#3E414C] dark:text-slate-200">
                            {{ formatTimeRange(item.start_time, item.end_time) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-[#1F2937] dark:text-slate-100 truncate">
                                {{ item.note || 'Buổi tập' }}
                            </div>
                            <div class="text-[11px] text-[#838799] dark:text-slate-500 truncate mt-0.5">
                                <span v-if="!item.competition_location_ids || item.competition_location_ids.length === 0">
                                    Tất cả sân nhà
                                </span>
                                <span v-else>
                                    {{ homeCourtsLabel(item) }}
                                </span>
                            </div>
                        </div>
                        <div v-if="canManage" class="flex items-center gap-1 flex-shrink-0">
                            <button @click="openEditModal(item)"
                                class="p-1.5 text-gray-400 hover:text-[#D72D36] transition-colors" title="Sửa">
                                <PencilSquareIcon class="w-4 h-4" />
                            </button>
                            <button @click="confirmDelete(item)"
                                class="p-1.5 text-gray-400 hover:text-red-500 transition-colors" title="Xóa">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Add/Edit -->
        <Transition name="fade">
            <div v-if="showFormModal"
                class="fixed inset-0 z-[9999] flex items-center justify-center p-3 bg-black/60 backdrop-blur-sm"
                @click.self="closeFormModal">
                <div class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-2xl w-full max-w-[460px] shadow-2xl flex flex-col max-h-[90vh]">
                    <div class="p-4 sm:p-5 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between">
                        <h3 class="font-bold text-[#1F2937] dark:text-slate-100">
                            {{ formMode === 'add' ? 'Thêm lịch sinh hoạt' : 'Sửa lịch sinh hoạt' }}
                        </h3>
                        <button @click="closeFormModal" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-4 sm:p-5 space-y-3 overflow-y-auto">
                        <div>
                            <label class="block text-sm font-medium text-[#3E414C] dark:text-slate-200 mb-1">
                                Thứ trong tuần <span class="text-[#D72D36]">*</span>
                            </label>
                            <select v-model.number="form.day_of_week"
                                class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36]">
                                <option v-for="(label, idx) in DAY_LABELS" :key="idx" :value="idx">{{ label }}</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-medium text-[#3E414C] dark:text-slate-200 mb-1">
                                    Giờ bắt đầu <span class="text-[#D72D36]">*</span>
                                </label>
                                <input v-model="form.start_time" type="time"
                                    class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36]" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[#3E414C] dark:text-slate-200 mb-1">
                                    Giờ kết thúc <span class="text-[#D72D36]">*</span>
                                </label>
                                <input v-model="form.end_time" type="time"
                                    class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36]" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-[#3E414C] dark:text-slate-200 mb-1">
                                Ghi chú
                            </label>
                            <input v-model="form.note" type="text" maxlength="255"
                                placeholder="vd: Tập cơ bản, Giao lưu CLB"
                                class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36]" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-[#3E414C] dark:text-slate-200 mb-1">
                                Sân nhà áp dụng
                            </label>
                            <p class="text-[11px] text-[#838799] dark:text-slate-500 mb-2">
                                Không chọn → áp dụng cho tất cả sân nhà.
                            </p>

                            <div v-if="homeCourts.length === 0" class="text-xs text-[#838799] italic">
                                CLB chưa có sân nhà nào.
                            </div>
                            <div v-else class="space-y-1 max-h-[180px] overflow-y-auto p-1 border border-gray-100 dark:border-slate-700 rounded-lg">
                                <label v-for="court in homeCourts" :key="court.competition_location_id"
                                    class="flex items-start gap-2 px-2 py-1.5 rounded-md hover:bg-gray-50 dark:hover:bg-slate-800/50 cursor-pointer">
                                    <input type="checkbox" :value="court.competition_location_id"
                                        v-model="form.competition_location_ids"
                                        class="mt-0.5 w-4 h-4 accent-[#D72D36]" />
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-[#1F2937] dark:text-slate-100 truncate">
                                            {{ court.name }}
                                        </div>
                                        <div class="text-[11px] text-[#838799] dark:text-slate-500 truncate">
                                            {{ court.address || '—' }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <p v-if="formError" class="text-xs text-[#D72D36]">{{ formError }}</p>
                    </div>

                    <div class="p-4 sm:p-5 border-t border-gray-100 dark:border-slate-800 flex gap-2">
                        <button @click="closeFormModal"
                            class="flex-1 px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-700 text-[#3E414C] dark:text-slate-200 font-semibold hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                            Hủy
                        </button>
                        <button @click="submitForm" :disabled="submitting"
                            class="flex-1 px-4 py-2 rounded-lg bg-[#D72D36] text-white font-semibold hover:bg-[#c9252e] transition-colors disabled:opacity-50">
                            {{ submitting ? 'Đang lưu...' : (formMode === 'add' ? 'Thêm' : 'Cập nhật') }}
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
                    <h3 class="font-bold text-[#1F2937] dark:text-slate-100 mb-2">Xóa lịch sinh hoạt?</h3>
                    <p class="text-sm text-[#838799] dark:text-slate-400 mb-5">
                        Bạn có chắc muốn xóa lịch <strong>{{ deletingItem ? formatDay(deletingItem.day_of_week) + ' ' + formatTimeRange(deletingItem.start_time, deletingItem.end_time) : '' }}</strong>?
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
import { ref, computed, onMounted, watch } from 'vue'
import { toast } from 'vue3-toastify'
import {
    CalendarDaysIcon,
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline'
import {
    getRecurringSchedules,
    createRecurringSchedule,
    updateRecurringSchedule,
    deleteRecurringSchedule,
    getHomeCourts
} from '@/service/club'

const props = defineProps({
    clubId: { type: [String, Number], required: true },
    canManage: { type: Boolean, default: false },
    initialSchedules: { type: Array, default: () => [] },
    initialHomeCourts: { type: Array, default: () => [] }
})

const emit = defineEmits(['updated'])

const DAY_LABELS = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7']

const schedules = ref([...props.initialSchedules])
const homeCourts = ref([...props.initialHomeCourts])
const loading = ref(false)

const showFormModal = ref(false)
const formMode = ref('add')
const editingItem = ref(null)
const formError = ref('')
const form = ref({
    day_of_week: 1,
    start_time: '18:00',
    end_time: '21:00',
    note: '',
    competition_location_ids: []
})
const submitting = ref(false)

const showDeleteConfirm = ref(false)
const deletingItem = ref(null)
const deleting = ref(false)

const formatDay = (idx) => DAY_LABELS[idx] || ''
const formatTime = (t) => {
    if (!t) return ''
    return String(t).slice(0, 5)
}
const formatTimeRange = (s, e) => `${formatTime(s)} - ${formatTime(e)}`

const homeCourtsLabel = (item) => {
    const ids = item.competition_location_ids || []
    if (ids.length === 0) return 'Tất cả sân nhà'
    return homeCourts.value
        .filter(c => ids.includes(c.competition_location_id))
        .map(c => c.name)
        .join(', ')
}

const groupedByDay = computed(() => {
    const map = new Map()
    schedules.value.forEach(item => {
        if (!map.has(item.day_of_week)) {
            map.set(item.day_of_week, [])
        }
        map.get(item.day_of_week).push(item)
    })
    return Array.from(map.entries())
        .sort((a, b) => a[0] - b[0])
        .map(([day, items]) => ({
            day,
            label: formatDay(day),
            items: items.sort((a, b) => String(a.start_time).localeCompare(String(b.start_time)))
        }))
})

const getSchedulesForDay = (dayIdx) => {
    return schedules.value
        .filter(s => s.day_of_week === dayIdx)
        .sort((a, b) => String(a.start_time).localeCompare(String(b.start_time)))
}

const fetchSchedules = async () => {
    loading.value = true
    try {
        schedules.value = await getRecurringSchedules(props.clubId)
    } catch (err) {
        toast.error('Không tải được lịch sinh hoạt')
    } finally {
        loading.value = false
    }
}

const fetchHomeCourts = async () => {
    try {
        homeCourts.value = await getHomeCourts(props.clubId)
    } catch {
        homeCourts.value = []
    }
}

const openAddModal = () => {
    formMode.value = 'add'
    formError.value = ''
    form.value = {
        day_of_week: 1,
        start_time: '18:00',
        end_time: '21:00',
        note: '',
        competition_location_ids: []
    }
    showFormModal.value = true
}

const openEditModal = (item) => {
    formMode.value = 'edit'
    editingItem.value = item
    formError.value = ''
    form.value = {
        day_of_week: item.day_of_week,
        start_time: formatTime(item.start_time),
        end_time: formatTime(item.end_time),
        note: item.note || '',
        competition_location_ids: Array.isArray(item.competition_location_ids) ? [...item.competition_location_ids] : []
    }
    showFormModal.value = true
}

const closeFormModal = () => {
    showFormModal.value = false
    editingItem.value = null
    formError.value = ''
}

const submitForm = async () => {
    formError.value = ''
    if (!form.value.start_time || !form.value.end_time) {
        formError.value = 'Vui lòng chọn giờ bắt đầu và kết thúc'
        return
    }
    if (form.value.end_time <= form.value.start_time) {
        formError.value = 'Giờ kết thúc phải sau giờ bắt đầu'
        return
    }

    const payload = {
        day_of_week: form.value.day_of_week,
        start_time: form.value.start_time,
        end_time: form.value.end_time,
        note: form.value.note || null,
        competition_location_ids: [...form.value.competition_location_ids]
    }

    submitting.value = true
    try {
        if (formMode.value === 'add') {
            await createRecurringSchedule(props.clubId, payload)
            toast.success('Đã thêm lịch sinh hoạt')
        } else {
            await updateRecurringSchedule(props.clubId, editingItem.value.id, payload)
            toast.success('Đã cập nhật lịch sinh hoạt')
        }
        await fetchSchedules()
        emit('updated')
        closeFormModal()
    } catch (err) {
        const msg = err?.response?.data?.message
        formError.value = Array.isArray(msg) ? msg[0] : (msg || 'Không lưu được lịch')
    } finally {
        submitting.value = false
    }
}

const confirmDelete = (item) => {
    deletingItem.value = item
    showDeleteConfirm.value = true
}

const doDelete = async () => {
    deleting.value = true
    try {
        await deleteRecurringSchedule(props.clubId, deletingItem.value.id)
        toast.success('Đã xóa lịch sinh hoạt')
        await fetchSchedules()
        emit('updated')
        showDeleteConfirm.value = false
    } catch (err) {
        toast.error(err?.response?.data?.message || 'Không xóa được')
    } finally {
        deleting.value = false
    }
}

watch(() => props.initialSchedules, (val) => {
    if (val && val.length > 0) {
        schedules.value = [...val]
    }
}, { deep: true })

watch(() => props.initialHomeCourts, (val) => {
    if (val && val.length > 0) {
        homeCourts.value = [...val]
    }
}, { deep: true })

onMounted(() => {
    if (props.initialHomeCourts?.length === 0) {
        fetchHomeCourts()
    }
    if (props.initialSchedules?.length === 0) {
        fetchSchedules()
    }
})
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
