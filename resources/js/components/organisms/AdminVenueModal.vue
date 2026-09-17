<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue"
          class="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto"
        @click.self="closeModal"
      >
        <div
          class="bg-white rounded-2xl shadow-2xl border border-slate-200/80 w-full max-w-3xl overflow-hidden flex flex-col max-h-[95vh] sm:max-h-[90vh] animate-in fade-in zoom-in-95 duration-200 my-auto"
        >
          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50 sticky top-0 z-10">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-xl">stadium</span>
              </div>
              <div>
                <h3 class="text-lg font-bold text-slate-800">
                  {{ isEdit ? 'Cập nhật sân thi đấu' : 'Thêm sân thi đấu mới' }}
                </h3>
                <p class="text-xs text-slate-500">
                  {{ isEdit ? `ID: #${form.id} - ${form.name}` : 'Nhập thông tin sân thi đấu và danh sách sân con' }}
                </p>
              </div>
            </div>
            <button
              type="button"
              @click="closeModal"
              class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
            >
              <span class="material-symbols-outlined text-xl">close</span>
            </button>
          </div>

          <!-- Body Form -->
          <form @submit.prevent="handleSubmit" class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- Alert error nếu có -->
            <div v-if="errorMessage" class="p-3 bg-red-50 border border-red-200 rounded-xl flex items-start gap-2 text-sm text-red-600">
              <span class="material-symbols-outlined text-base mt-0.5">error</span>
              <span class="flex-1">{{ errorMessage }}</span>
            </div>

            <!-- PHẦN 1: THÔNG TIN CƠ BẢN -->
            <div>
              <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-slate-500">info</span>
                Thông tin chung
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Tên sân -->
                <div class="md:col-span-2">
                  <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Tên sân thi đấu <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.name"
                    type="text"
                    required
                    placeholder="Ví dụ: Cụm sân Pickleball D-Sport"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                  />
                </div>

                <!-- Tỉnh / Thành phố -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Tỉnh / Thành phố
                  </label>
                  <select
                    v-model="form.location_id"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                  >
                    <option :value="null">-- Chọn khu vực --</option>
                    <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                      {{ loc.name }}
                    </option>
                  </select>
                </div>

                <!-- Số điện thoại -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Số điện thoại liên hệ
                  </label>
                  <input
                    v-model="form.phone"
                    type="text"
                    placeholder="Ví dụ: 0987654321"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                  />
                </div>

                <!-- Địa chỉ -->
                <div class="md:col-span-2">
                  <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Địa chỉ chi tiết <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.address"
                    type="text"
                    required
                    placeholder="Ví dụ: Số 123 Đường Nguyễn Huệ, Phường Bến Nghé, Quận 1"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                  />
                </div>

                <!-- Giờ mở / đóng cửa -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Giờ mở cửa
                  </label>
                  <input
                    v-model="form.opening_time"
                    type="text"
                    placeholder="Ví dụ: 06:00"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                  />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Giờ đóng cửa
                  </label>
                  <input
                    v-model="form.closing_time"
                    type="text"
                    placeholder="Ví dụ: 22:00"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                  />
                </div>

                <!-- Tọa độ bản đồ -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Vĩ độ (Latitude)
                  </label>
                  <input
                    v-model="form.latitude"
                    type="number"
                    step="any"
                    placeholder="Ví dụ: 10.776889"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                  />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Kinh độ (Longitude)
                  </label>
                  <input
                    v-model="form.longitude"
                    type="number"
                    step="any"
                    placeholder="Ví dụ: 106.700806"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                  />
                </div>

                <!-- Website / Fanpage -->
                <div class="md:col-span-2">
                  <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Website / Fanpage liên kết
                  </label>
                  <input
                    v-model="form.website"
                    type="url"
                    placeholder="https://facebook.com/..."
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                  />
                </div>

                <!-- Ghi chú đặt sân -->
                <div class="md:col-span-2">
                  <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Ghi chú & Quy định đặt sân
                  </label>
                  <textarea
                    v-model="form.note_booking"
                    rows="2"
                    placeholder="Ví dụ: Cọc trước 50%, hủy sân báo trước 2 tiếng..."
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder:text-slate-400"
                  ></textarea>
                </div>
              </div>
            </div>

            <hr class="border-slate-100" />

            <!-- PHẦN 2: HÌNH ẢNH SÂN -->
            <div>
              <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-slate-500">image</span>
                Hình ảnh sân thi đấu
              </h4>

              <div class="flex flex-col sm:flex-row gap-4 items-start">
                <!-- Preview box -->
                <div class="w-full sm:w-44 h-32 rounded-xl bg-slate-100 border-2 border-dashed border-slate-200 overflow-hidden flex items-center justify-center relative group flex-shrink-0">
                  <img
                    v-if="imagePreview"
                    :src="imagePreview"
                    alt="Venue preview"
                    class="w-full h-full object-cover"
                  />
                  <div v-else class="text-center p-3 text-slate-400">
                    <span class="material-symbols-outlined text-3xl">add_photo_alternate</span>
                    <p class="text-[11px] mt-1">Chưa có ảnh</p>
                  </div>
                  <button
                    v-if="imagePreview"
                    type="button"
                    @click="clearImage"
                    class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-red-600 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow"
                    title="Gỡ ảnh"
                  >
                    <span class="material-symbols-outlined text-xs">close</span>
                  </button>
                </div>

                <!-- Upload trigger & URL input -->
                <div class="flex-1 space-y-3 w-full">
                  <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                      Tải lên từ thiết bị
                    </label>
                    <div class="flex items-center gap-2">
                      <input
                        type="file"
                        ref="fileInputRef"
                        accept="image/*"
                        @change="handleFileSelect"
                        class="hidden"
                      />
                      <button
                        type="button"
                        @click="triggerFileInput"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 flex items-center gap-1.5 transition-colors"
                      >
                        <span class="material-symbols-outlined text-base">upload</span>
                        Chọn tệp ảnh
                      </button>
                      <span v-if="selectedFileName" class="text-xs text-slate-500 truncate max-w-[200px]">
                        {{ selectedFileName }}
                      </span>
                    </div>
                  </div>

                  <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                      Hoặc dán URL ảnh trực tiếp
                    </label>
                    <input
                      v-model="imageUrlInput"
                      type="url"
                      placeholder="https://example.com/court.jpg"
                      @input="handleUrlInput"
                      class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                    />
                  </div>
                </div>
              </div>
            </div>

            <hr class="border-slate-100" />

            <!-- PHẦN 3: MÔN THỂ THAO & TIỆN ÍCH -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Môn thể thao -->
              <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-sm text-slate-500">sports_tennis</span>
                  Môn thể thao
                </h4>
                <div v-if="sports.length" class="flex flex-wrap gap-2">
                  <button
                    v-for="sport in sports"
                    :key="sport.id"
                    type="button"
                    @click="toggleSport(sport.id)"
                    :class="form.sport_ids.includes(sport.id)
                      ? 'bg-primary text-white border-primary shadow-sm'
                      : 'bg-slate-100 text-slate-700 border-slate-200 hover:bg-slate-200'"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-all flex items-center gap-1.5"
                  >
                    <span v-if="form.sport_ids.includes(sport.id)" class="material-symbols-outlined text-xs">check</span>
                    {{ sport.name }}
                  </button>
                </div>
                <p v-else class="text-xs text-slate-400 italic">Đang tải danh sách môn thể thao...</p>
              </div>

              <!-- Tiện ích -->
              <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-sm text-slate-500">local_convenience_store</span>
                  Tiện ích sân
                </h4>
                <div v-if="facilities.length" class="flex flex-wrap gap-2">
                  <button
                    v-for="fac in facilities"
                    :key="fac.id"
                    type="button"
                    @click="toggleFacility(fac.id)"
                    :class="form.facility_ids.includes(fac.id)
                      ? 'bg-sky-600 text-white border-sky-600 shadow-sm'
                      : 'bg-slate-100 text-slate-700 border-slate-200 hover:bg-slate-200'"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-all flex items-center gap-1.5"
                  >
                    <span v-if="form.facility_ids.includes(fac.id)" class="material-symbols-outlined text-xs">check</span>
                    {{ fac.name }}
                  </button>
                </div>
                <p v-else class="text-xs text-slate-400 italic">Chưa có tiện ích nào trong hệ thống</p>
              </div>
            </div>

            <hr class="border-slate-100" />

            <!-- PHẦN 4: DANH SÁCH SÂN CON (YARDS) -->
            <div>
              <div class="flex items-center justify-between mb-3">
                <div>
                  <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-slate-500">grid_view</span>
                    Danh sách sân con (Yards)
                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold">
                      {{ form.yards.length }}
                    </span>
                  </h4>
                  <p class="text-xs text-slate-500 mt-0.5">
                    Khai báo tên/số sân và loại sân (trong nhà, ngoài trời, có mái che...)
                  </p>
                </div>
                <button
                  type="button"
                  @click="addYard"
                  class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl flex items-center gap-1 transition-colors shadow-sm"
                >
                  <span class="material-symbols-outlined text-base">add</span>
                  Thêm sân
                </button>
              </div>

              <!-- Yard rows -->
              <div v-if="form.yards.length" class="space-y-2.5">
                <div
                  v-for="(yard, index) in form.yards"
                  :key="index"
                  class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl flex items-center gap-3 group hover:border-slate-300 transition-colors"
                >
                  <span class="text-xs font-bold text-slate-400 w-6 text-center">#{{ index + 1 }}</span>

                  <!-- Tên/Số sân -->
                  <div class="flex-1">
                    <input
                      v-model="yard.yard_number"
                      type="text"
                      required
                      placeholder="Tên / Số sân (vd: Sân 1, Sân A)"
                      class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    />
                  </div>

                  <!-- Loại sân -->
                  <div class="w-40">
                    <select
                      v-model="yard.yard_type"
                      class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    >
                      <option v-for="t in YARD_TYPE_OPTIONS" :key="t.value" :value="t.value">
                        {{ t.label }}
                      </option>
                    </select>
                  </div>

                  <!-- Nút xóa -->
                  <button
                    type="button"
                    @click="removeYard(index)"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                    title="Xóa sân con này"
                  >
                    <span class="material-symbols-outlined text-base">delete</span>
                  </button>
                </div>
              </div>

              <div v-else class="text-center py-6 bg-slate-50 border border-dashed border-slate-200 rounded-xl">
                <p class="text-xs text-slate-500 mb-2">Chưa có sân con nào được cấu hình</p>
                <button
                  type="button"
                  @click="addYard"
                  class="text-xs text-primary font-bold hover:underline inline-flex items-center gap-1"
                >
                  <span class="material-symbols-outlined text-sm">add_circle</span>
                  Nhấn vào đây để thêm sân đầu tiên
                </button>
              </div>
            </div>
          </form>

          <!-- Footer Actions -->
          <div class="px-4 sm:px-6 py-4 border-t border-slate-200 bg-slate-50/50 flex flex-col-reverse sm:flex-row items-center justify-end gap-2 sm:gap-3 sticky bottom-0 z-10">
            <button
              type="button"
              @click="closeModal"
              :disabled="isSubmitting"
              class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-100 text-xs font-bold transition-colors disabled:opacity-50"
            >
              Hủy bỏ
            </button>
            <button
              type="button"
              @click="handleSubmit"
              :disabled="isSubmitting"
              class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-lg shadow-primary/20 flex items-center gap-2 transition-all disabled:opacity-50"
            >
              <span v-if="isSubmitting" class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
              <span>{{ isSubmitting ? 'Đang lưu...' : (isEdit ? 'Lưu cập nhật' : 'Tạo sân thi đấu') }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { get, post } from '@/utils/httpRequest.js'
import { getAllLocations } from '@/service/location.js'
import { getAllSports } from '@/service/sport.js'
import { toast } from 'vue3-toastify'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  venue: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'success'])

const YARD_TYPE_OPTIONS = [
  { value: 1, label: 'Trong nhà (Indoor)' },
  { value: 2, label: 'Ngoài trời (Outdoor)' },
  { value: 3, label: 'Thuê riêng (Private)' },
  { value: 4, label: 'Đóng phí (Pay fee)' },
  { value: 5, label: 'Mái che (Roofed)' },
]

// Metadata dropdowns
const locations = ref([])
const sports = ref([])
const facilities = ref([])

// Form state
const form = ref({
  id: null,
  name: '',
  location_id: null,
  address: '',
  phone: '',
  opening_time: '',
  closing_time: '',
  latitude: '',
  longitude: '',
  website: '',
  note_booking: '',
  image: '',
  sport_ids: [],
  facility_ids: [],
  yards: [],
})

const isEdit = computed(() => Boolean(form.value.id))
const isSubmitting = ref(false)
const errorMessage = ref('')

// Image handling
const fileInputRef = ref(null)
const imageFile = ref(null)
const imagePreview = ref('')
const selectedFileName = ref('')
const imageUrlInput = ref('')

const triggerFileInput = () => {
  fileInputRef.value?.click()
}

const handleFileSelect = (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  imageFile.value = file
  selectedFileName.value = file.name
  imageUrlInput.value = ''
  form.value.image = ''

  const reader = new FileReader()
  reader.onload = (e) => {
    imagePreview.value = e.target?.result || ''
  }
  reader.readAsDataURL(file)
}

const handleUrlInput = () => {
  if (imageUrlInput.value) {
    imageFile.value = null
    selectedFileName.value = ''
    form.value.image = imageUrlInput.value
    imagePreview.value = imageUrlInput.value
  }
}

const clearImage = () => {
  imageFile.value = null
  selectedFileName.value = ''
  imageUrlInput.value = ''
  form.value.image = ''
  imagePreview.value = ''
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}

// Sports & Facilities toggle
const toggleSport = (sportId) => {
  const idx = form.value.sport_ids.indexOf(sportId)
  if (idx > -1) {
    form.value.sport_ids.splice(idx, 1)
  } else {
    form.value.sport_ids.push(sportId)
  }
}

const toggleFacility = (facilityId) => {
  const idx = form.value.facility_ids.indexOf(facilityId)
  if (idx > -1) {
    form.value.facility_ids.splice(idx, 1)
  } else {
    form.value.facility_ids.push(facilityId)
  }
}

// Yards handling
const addYard = () => {
  const nextNum = form.value.yards.length + 1
  form.value.yards.push({
    id: null,
    yard_number: `Sân ${nextNum}`,
    yard_type: 1,
  })
}

const removeYard = (index) => {
  form.value.yards.splice(index, 1)
}

// Fetch metadata on mount
const fetchMetadata = async () => {
  try {
    const [locData, sportData, facRes] = await Promise.allSettled([
      getAllLocations(),
      getAllSports(),
      get('/facilities/index'),
    ])

    if (locData.status === 'fulfilled' && Array.isArray(locData.value)) {
      locations.value = locData.value
    }
    if (sportData.status === 'fulfilled' && Array.isArray(sportData.value)) {
      sports.value = sportData.value
    }
    if (facRes.status === 'fulfilled') {
      facilities.value = facRes.value?.data?.data || []
    }
  } catch (e) {
    console.error('Error fetching metadata for venue modal:', e)
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    name: '',
    location_id: null,
    address: '',
    phone: '',
    opening_time: '',
    closing_time: '',
    latitude: '',
    longitude: '',
    website: '',
    note_booking: '',
    image: '',
    sport_ids: sports.value.length ? [sports.value[0].id] : [],
    facility_ids: [],
    yards: [
      { id: null, yard_number: 'Sân 1', yard_type: 1 },
      { id: null, yard_number: 'Sân 2', yard_type: 1 },
    ],
  }
  clearImage()
  errorMessage.value = ''
}

// Watch props.venue to populate form
watch(
  () => props.venue,
  (v) => {
    if (v) {
      form.value = {
        id: v.id,
        name: v.name || '',
        location_id: v.location_id || v.location?.id || null,
        address: v.address || '',
        phone: v.phone || '',
        opening_time: v.opening_time || '',
        closing_time: v.closing_time || '',
        latitude: v.latitude ?? '',
        longitude: v.longitude ?? '',
        website: v.website || '',
        note_booking: v.note_booking || '',
        image: v.image || '',
        sport_ids: (v.sports || []).map((s) => s.id),
        facility_ids: (v.facilities || []).map((f) => f.id),
        yards: (v.yards || []).map((y) => ({
          id: y.id,
          yard_number: y.yard_number,
          yard_type: y.yard_type ?? 1,
        })),
      }

      // If venue has no yards, initialize with empty or 1 yard
      if (!form.value.yards.length) {
        form.value.yards = [{ id: null, yard_number: 'Sân 1', yard_type: 1 }]
      }

      // Set image preview
      imageFile.value = null
      selectedFileName.value = ''
      if (v.image) {
        if (v.image.startsWith('http')) {
          imagePreview.value = v.image
          imageUrlInput.value = v.image
        } else {
          imagePreview.value = `/storage/${v.image.replace(/^storage\//, '')}`
          imageUrlInput.value = ''
        }
      } else if (v.imageUrl) {
        imagePreview.value = v.imageUrl
        imageUrlInput.value = ''
      } else {
        imagePreview.value = ''
        imageUrlInput.value = ''
      }
      errorMessage.value = ''
    } else {
      resetForm()
    }
  },
  { immediate: true }
)

const closeModal = () => {
  emit('update:modelValue', false)
}

// Submit
const handleSubmit = async () => {
  if (!form.value.name.trim()) {
    errorMessage.value = 'Vui lòng nhập tên sân thi đấu.'
    return
  }
  if (!form.value.address.trim()) {
    errorMessage.value = 'Vui lòng nhập địa chỉ sân thi đấu.'
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''

  try {
    const formData = new FormData()
    formData.append('name', form.value.name.trim())
    if (form.value.location_id) {
      formData.append('location_id', form.value.location_id)
    }
    formData.append('address', form.value.address.trim())
    if (form.value.phone) formData.append('phone', form.value.phone.trim())
    if (form.value.opening_time) formData.append('opening_time', form.value.opening_time.trim())
    if (form.value.closing_time) formData.append('closing_time', form.value.closing_time.trim())
    if (form.value.latitude) formData.append('latitude', form.value.latitude)
    if (form.value.longitude) formData.append('longitude', form.value.longitude)
    if (form.value.website) formData.append('website', form.value.website.trim())
    if (form.value.note_booking) formData.append('note_booking', form.value.note_booking.trim())

    if (imageFile.value) {
      formData.append('image', imageFile.value)
    } else if (form.value.image) {
      formData.append('image', form.value.image)
    }

    formData.append('sport_ids', JSON.stringify(form.value.sport_ids))
    formData.append('facility_ids', JSON.stringify(form.value.facility_ids))
    formData.append('yards', JSON.stringify(form.value.yards))

    if (isEdit.value) {
      await post(`/admin/competition-locations/${form.value.id}`, formData)
      toast.success('Cập nhật sân thi đấu thành công!')
    } else {
      await post('/admin/competition-locations', formData)
      toast.success('Thêm sân thi đấu thành công!')
    }

    emit('success')
    closeModal()
  } catch (err) {
    console.error('Save venue error:', err)
    const apiMsg = err.response?.data?.message || err.message || 'Có lỗi xảy ra khi lưu thông tin sân thi đấu.'
    errorMessage.value = apiMsg
    toast.error(apiMsg)
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  fetchMetadata()
})
</script>
