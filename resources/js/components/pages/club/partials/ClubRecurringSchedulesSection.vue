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

        <!-- Grouped by day -->
        <div v-else class="space-y-3">
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
                <div class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-2xl w-full max-w-[420px] shadow-2xl flex flex-col max-h-[90vh]">
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
                                placeholder="vd: Sân chính, Tập cơ bản"
                                class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36]" />
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
    deleteRecurringSchedule
} from '@/service/club'

const props = defineProps({
    clubId: { type: [String, Number], required: true },
    canManage: { type: Boolean, default: false },
    initialSchedules: { type: Array, default: () => [] }
})

const emit = defineEmits(['updated'])

const DAY_LABELS = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7']

const schedules = ref([...props.initialSchedules])
const loading = ref(false)

const showFormModal = ref(false)
const formMode = ref('add')
const editingItem = ref(null)
const formError = ref('')
const form = ref({ day_of_week: 1, start_time: '18:00', end_time: '21:00', note: '' })
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

const openAddModal = () => {
    formMode.value = 'add'
    formError.value = ''
    form.value = { day_of_week: 1, start_time: '18:00', end_time: '21:00', note: '' }
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
        note: item.note || ''
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

    submitting.value = true
    try {
        if (formMode.value === 'add') {
            await createRecurringSchedule(props.clubId, { ...form.value })
            toast.success('Đã thêm lịch sinh hoạt')
        } else {
            await updateRecurringSchedule(props.clubId, editingItem.value.id, { ...form.value })
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

onMounted(() => {
    if (props.initialSchedules?.length === 0) {
        fetchSchedules()
    }
})
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
