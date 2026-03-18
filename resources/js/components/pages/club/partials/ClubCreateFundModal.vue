<template>
    <Transition name="fade">
        <div v-if="isOpen" 
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="close">
            <Transition name="scale">
                <div v-if="isOpen" 
                    class="bg-white rounded-[24px] w-full max-w-[850px] max-h-[90vh] transition-all duration-300 flex flex-col p-6 md:p-6 relative shadow-2xl overflow-hidden">
                    <!-- Modal Close -->
                    <button 
                        @click="close"
                        class="absolute right-8 top-8 text-gray-400 hover:text-gray-600 transition-colors z-10">
                        <XMarkIcon class="w-7 h-7" />
                    </button>

                    <!-- Modal Header -->
                    <div class="mb-8 flex-shrink-0">
                        <h2 class="text-[20px] font-bold text-[#1F2937]">Tạo Khoản Thu</h2>
                    </div>

                    <div class="flex flex-col md:flex-row gap-6 md:gap-10 min-h-0 flex-1 overflow-y-auto md:overflow-hidden custom-scrollbar">
                        <!-- Left Column: Form Info -->
                        <div class="w-full md:w-[45%] flex flex-col space-y-6 md:space-y-8 md:overflow-y-auto custom-scrollbar pr-2 flex-shrink-0">
                            <div class="flex flex-col items-center flex-shrink-0">
                                <p class="font-semibold text-[#838799] uppercase tracking-wider mb-4">SỐ TIỀN MỖI NGƯỜI</p>
                                <div class="flex items-center justify-center space-x-1 border-b-2 rounded-[4px] border-[#E36C72] pb-2 px-8 w-fit mx-auto">
                                    <input 
                                        type="text" 
                                        :value="fundAmount"
                                        @input="onAmountInput"
                                        @keypress="isNumber($event)"
                                        placeholder="0"
                                        class="text-[36px] md:text-[48px] font-bold text-[#2D3139] bg-transparent border-none p-0 focus:outline-none w-40 md:w-60 text-center"
                                    />
                                    <span class="text-[20px] md:text-[24px] font-bold text-[#838799]">đ</span>
                                </div>
                            </div>

                            <div class="bg-white border border-[#F2F3F5] rounded-xl shadow-sm overflow-hidden flex-shrink-0">
                                <div class="p-5 border-b border-[#F2F3F5]">
                                    <label class="block text-[11px] font-bold text-[#838799] uppercase tracking-wider mb-2">NỘI DUNG THU</label>
                                    <input 
                                        type="text" 
                                        v-model="fundTitle"
                                        class="w-full font-semibold text-[#3E414C] bg-transparent border-none p-0 focus:outline-none placeholder:text-gray-300"
                                        placeholder="VD: Quỹ tháng 1/2026"
                                    />
                                </div>

                                <div class="p-5">
                                    <label class="block text-[11px] font-bold text-[#838799] uppercase tracking-wider mb-2 whitespace-nowrap">HẠN CHÓT (DEADLINE)</label>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-2 mr-2">
                                            <CalendarIcon class="w-5 h-5 text-[#D72D36]" />
                                            <span class="font-semibold text-[#3E414C] whitespace-nowrap">{{ formattedFundDeadline }}</span>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <VueDatePicker 
                                                v-model="fundDeadline" 
                                                :enable-time-picker="false" 
                                                auto-apply
                                                :format="'dd/MM/yyyy'" 
                                                teleport="body"
                                                position="right"
                                                class="w-auto"
                                            >
                                                <template #trigger>
                                                    <button class="p-2 bg-[#F2F3F5] rounded-[4px] hover:bg-gray-200 transition-colors">
                                                        <PencilIcon class="w-5 h-5 text-[#3E414C]" />
                                                    </button>
                                                </template>
                                            </VueDatePicker>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Included in Club Fund Toggle -->
                            <div class="flex items-center justify-between bg-[#F8FAFC] rounded-xl p-4 border border-[#F2F3F5] flex-shrink-0">
                                <div>
                                    <p class="text-[13px] font-bold text-[#1F2937]">Quỹ chung CLB</p>
                                    <p class="text-[11px] text-[#838799] mt-0.5">Dùng chung mã QR ví CLB</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="includedInClubFund" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#D72D36]"></div>
                                </label>
                            </div>

                            <!-- QR Code Upload Section -->
                            <div class="space-y-3 flex-shrink-0" v-if="!includedInClubFund">
                                <label class="block text-[11px] font-bold text-[#838799] uppercase tracking-wider">ẢNH QR CHUYỂN KHOẢN (TÙY CHỌN)</label>
                                
                                <div 
                                    @click="triggerFileInput"
                                    @dragover.prevent="isDragging = true"
                                    @dragleave.prevent="isDragging = false"
                                    @drop.prevent="onDrop"
                                    :class="[
                                        'relative border-2 border-dashed rounded-xl transition-all duration-200 cursor-pointer overflow-hidden flex flex-col items-center justify-center min-h-[140px]',
                                        isDragging ? 'border-[#D72D36] bg-[#D72D36]/5' : 'border-gray-200 hover:border-[#D72D36] hover:bg-gray-50',
                                        qrCodePreview ? 'border-solid' : ''
                                    ]"
                                >
                                    <input 
                                        type="file" 
                                        ref="fileInput" 
                                        class="hidden" 
                                        accept="image/*"
                                        @change="handleFileChange" 
                                    />

                                    <div v-if="!qrCodePreview" class="flex flex-col items-center p-6 text-center">
                                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-[#D72D36]/10 transition-colors">
                                            <ArrowUpTrayIcon class="w-6 h-6 text-gray-400 group-hover:text-[#D72D36]" />
                                        </div>
                                        <p class="text-sm font-semibold text-[#3E414C]">Thả ảnh vào đây hoặc nhấn để tải lên</p>
                                        <p class="text-xs text-[#838799] mt-1">Hỗ trợ JPG, PNG (Tối đa 5MB)</p>
                                    </div>

                                    <div v-else class="relative w-full h-full flex items-center justify-center p-2">
                                        <img :src="qrCodePreview" class="max-h-[120px] rounded-lg shadow-sm" />
                                        <button 
                                            @click.stop="removeQrCode"
                                            class="absolute top-2 right-2 p-1.5 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors shadow-lg"
                                        >
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Member Selection -->
                        <div class="w-full md:w-[55%] flex flex-col min-h-[300px] md:min-h-0 md:border-l border-[#F2F3F5] md:pl-5">
                            <div class="flex items-center justify-between mb-6 flex-shrink-0">
                                <div class="flex items-center space-x-2 font-semibold text-[#838799]">
                                    <span class="uppercase tracking-wider">ÁP DỤNG CHO</span>
                                    <span>•</span>
                                    <span>{{ selectedMemberIds.length }}/{{ club.members?.length || 0 }}</span>
                                </div>
                                <button 
                                    @click="selectAllMembers"
                                    class="text-[#D72D36] text-sm font-semibold"
                                >
                                    Chọn tất cả
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar space-y-2">
                                <div 
                                    v-for="member in club.members" 
                                    :key="member.user_id"
                                    @click="toggleMemberSelection(member.user_id)"
                                    class="flex items-center justify-between p-3 hover:bg-[#F8FAFC] rounded-xl transition-colors cursor-pointer group"
                                >
                                    <div class="flex items-center space-x-4">
                                        <div class="relative">
                                            <div class="w-14 h-14 rounded-full border-2 border-white shadow-sm overflow-hidden bg-gray-100">
                                                <img :src="member.user?.avatar_url || `https://ui-avatars.com/api/?name=${member.user?.full_name || 'User'}&background=random`" class="w-full h-full object-cover" />
                                            </div>
                                            <div class="absolute bottom-0 left-0 w-4 h-4 bg-[#4392E0] text-white text-[8px] font-semibold rounded-full border border-white flex items-center justify-center">
                                                {{ Number(member.user?.sports[0]?.scores?.vndupr_score).toFixed(1) || 0 }}
                                            </div>
                                        </div>
                                        <span class="font-bold text-[#1F2937] text-[15px] truncate max-w-[150px] md:max-w-none">{{ member.user?.full_name || 'Thành viên' }}</span>
                                    </div>
                                    <div 
                                        class="w-6 h-6 rounded-full border-2 transition-all flex items-center justify-center"
                                        :class="selectedMemberIds.includes(member.user_id) ? 'bg-[#D72D36] border-[#D72D36]' : 'border-gray-200 group-hover:border-[#D72D36]'"
                                    >
                                        <CheckIcon v-if="selectedMemberIds.includes(member.user_id)" class="w-4 h-4 text-white stroke-[3px]" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="pt-6 md:pt-6 border-t border-[#F2F3F5] flex flex-col md:flex-row items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-normal text-[#6B6F80] tracking-wider mb-1">Tổng thu dự kiến</p>
                            <div class="flex items-baseline space-x-1">
                                <span class="text-[28px] font-bold text-[#1F2937]">{{ totalExpectedAmount }}</span>
                                <span class="text-[20px] font-bold text-[#1F2937]">đ</span>
                            </div>
                        </div>
                        <button 
                            @click="submitCreateFund"
                            class="bg-[#2D3139] text-white px-10 py-4 rounded-[4px] font-semibold hover:bg-black transition-all shadow-lg active:scale-[0.98]"
                        >
                            Gửi yêu cầu
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>

    <ImageCropperModal
        :is-open="showCropper"
        :image="cropImageSrc"
        @close="showCropper = false"
        @save="handleCropSave"
    />
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import {
    XMarkIcon,
    CalendarIcon,
    PencilIcon,
    CheckIcon,
    PhotoIcon,
    ArrowUpTrayIcon,
    TrashIcon
} from '@heroicons/vue/24/outline'
import VueDatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import dayjs from 'dayjs'
import ImageCropperModal from '@/components/molecules/ImageCropperModal.vue'

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    club: {
        type: Object,
        required: true
    },
    initialData: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['update:isOpen', 'submit'])

const fundAmount = ref('')
const fundTitle = ref('')
const fundDeadline = ref(new Date())
const selectedMemberIds = ref([])
const qrImageFile = ref(null)
const qrCodePreview = ref(null)
const isDragging = ref(false)
const fileInput = ref(null)
const showCropper = ref(false)
const cropImageSrc = ref(null)
const includedInClubFund = ref(true)

watch(() => props.isOpen, (newVal) => {
    if (newVal && props.initialData) {
        if (props.initialData.title) fundTitle.value = props.initialData.title
        if (props.initialData.amount) {
            let val = props.initialData.amount.toString()
            fundAmount.value = val.replace(/\B(?=(\d{3})+(?!\d))/g, ".")
        }
        if (props.initialData.memberIds) {
            selectedMemberIds.value = [...props.initialData.memberIds]
        }
        // Load existing QR code if available
        if (props.initialData.qr_code_url) {
            qrCodePreview.value = props.initialData.qr_code_url
            // Don't set qrImageFile since it's an existing URL, not a new file
        }
    }
}, { immediate: true })

const formattedFundDeadline = computed(() => {
    return dayjs(fundDeadline.value).format('DD/MM/YYYY')
})

const close = () => {
    emit('update:isOpen', false)
}

const toggleMemberSelection = (memberId) => {
    const index = selectedMemberIds.value.indexOf(memberId)
    if (index === -1) {
        selectedMemberIds.value.push(memberId)
    } else {
        selectedMemberIds.value.splice(index, 1)
    }
}

const isNumber = (evt) => {
    evt = (evt) ? evt : window.event;
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if ((charCode > 31 && (charCode < 48 || charCode > 57)) && charCode !== 46) {
        evt.preventDefault();
    } else {
        return true;
    }
}

const triggerFileInput = () => {
    fileInput.value.click()
}

const handleFileChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        processFile(file)
    }
}

const onDrop = (event) => {
    isDragging.value = false
    const file = event.dataTransfer.files[0]
    if (file) {
        processFile(file)
    }
}

const processFile = (file) => {
    if (!file.type.startsWith('image/')) {
        alert('Vui lòng chọn tệp hình ảnh')
        return
    }
    
    // Create URL for cropper
    const reader = new FileReader()
    reader.onload = (e) => {
        cropImageSrc.value = e.target.result
        showCropper.value = true
    }
    reader.readAsDataURL(file)
    
    // Clear input value to allow selecting same file again
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const handleCropSave = (blob) => {
    // Convert blob to file to ensure it has a name and type for backend processing
    const file = new File([blob], "qr-code.jpg", { type: "image/jpeg" })
    qrImageFile.value = file
    qrCodePreview.value = URL.createObjectURL(blob)
    showCropper.value = false
    cropImageSrc.value = null
}

const removeQrCode = () => {
    qrImageFile.value = null
    qrCodePreview.value = null
}

const selectAllMembers = () => {
    if (selectedMemberIds.value.length === props.club.members?.length) {
        selectedMemberIds.value = []
    } else {
        selectedMemberIds.value = props.club.members?.map(m => m.user_id) || []
    }
}

const formatPrice = (value) => {
    if (!value) return '0'
    return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")
}

const onAmountInput = (event) => {
    // Remove all non-numeric characters (including manual paste or other methods)
    let value = event.target.value.replace(/[^\d]/g, '')

    // Format with dots
    if (value) {
        value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".")
    }

    fundAmount.value = value
}

const totalExpectedAmount = computed(() => {
    const amount = parseInt(fundAmount.value.replace(/\./g, '')) || 0
    return formatPrice(amount * selectedMemberIds.value.length)
})

const submitCreateFund = () => {
    const rawAmount = parseInt(fundAmount.value.replace(/\./g, '')) || 0
    const deadlineDate = fundDeadline.value
        ? dayjs(fundDeadline.value)
        : null

    emit('submit', {
        title: fundTitle.value,
        description: fundTitle.value,
        target_amount: rawAmount * selectedMemberIds.value.length,
        amount_per_member: rawAmount,
        start_date: dayjs().format('YYYY-MM-DD'),
        deadline: deadlineDate ? deadlineDate.format('YYYY-MM-DD') : null,
        end_date: deadlineDate ? deadlineDate.format('YYYY-MM-DD') : null,
        member_ids: selectedMemberIds.value,
        qr_image: qrImageFile.value,
        qr_code_url: qrImageFile.value ? null : (qrCodePreview.value || null), // Nếu không upload file mới, giữ URL cũ
        included_in_club_fund: includedInClubFund.value ? 1 : 0
    })
    close()
    resetForm()
}

const resetForm = () => {
    fundAmount.value = ''
    fundTitle.value = ''
    fundDeadline.value = new Date()
    selectedMemberIds.value = []
    qrImageFile.value = null
    qrCodePreview.value = null
    includedInClubFund.value = true
}
</script>

<style scoped>
/* Custom scrollbar for a cleaner look */
::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #e5e7eb;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #d1d5db;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.scale-enter-active,
.scale-leave-active {
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.scale-enter-from,
.scale-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(20px);
}
</style>
