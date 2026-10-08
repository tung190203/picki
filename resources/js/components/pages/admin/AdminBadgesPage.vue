<template>
  <div class="flex min-h-screen bg-[#f7f9fb] font-body text-on-surface">
    <!-- SideNavBar -->
    <AdminSidebar />

    <!-- Main Content -->
    <main class="ml-64 flex-1 pb-16">
      <AdminHeader />

      <div class="p-8 lg:p-12 max-w-7xl mx-auto">
        <!-- Page Title & Primary Actions -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
          <div>
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-primary text-3xl">military_tech</span>
              <h1 class="text-2xl lg:text-3xl font-headline font-bold text-on-surface">Quản lý Huy Hiệu</h1>
            </div>
            <p class="text-sm text-on-surface-variant mt-1">
              Tạo và quản lý danh sách các huy hiệu hệ thống.
            </p>
          </div>

          <div class="flex gap-3">
            <button
              v-if="activeTab === 'badges'"
              @click="openCreateModal"
              class="flex items-center justify-center gap-2 bg-[#E8192C] hover:bg-[#c91223] text-white px-5 py-3 rounded-xl font-bold text-sm shadow-md transition-all active:scale-95 cursor-pointer w-fit"
            >
              <span class="material-symbols-outlined text-xl">add</span>
              <span>Tạo huy hiệu mới</span>
            </button>
            <button
              v-if="activeTab === 'types'"
              @click="openCreateTypeModal"
              class="flex items-center justify-center gap-2 bg-[#E8192C] hover:bg-[#c91223] text-white px-5 py-3 rounded-xl font-bold text-sm shadow-md transition-all active:scale-95 cursor-pointer w-fit"
            >
              <span class="material-symbols-outlined text-xl">add</span>
              <span>Tạo loại mới</span>
            </button>
          </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-6 border-b border-slate-200 mb-8">
          <button @click="activeTab = 'badges'" :class="['pb-3 text-sm font-bold border-b-2 transition-colors', activeTab === 'badges' ? 'border-[#E8192C] text-[#E8192C]' : 'border-transparent text-slate-500 hover:text-slate-700']">Danh sách huy hiệu</button>
          <button @click="activeTab = 'types'" :class="['pb-3 text-sm font-bold border-b-2 transition-colors', activeTab === 'types' ? 'border-[#E8192C] text-[#E8192C]' : 'border-transparent text-slate-500 hover:text-slate-700']">Loại huy hiệu</button>
        </div>

        <!-- Filters -->
        <div v-if="activeTab === 'badges'" class="flex flex-col sm:flex-row gap-4 mb-6">
          <div class="relative flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input 
              v-model="searchQuery" 
              @input="onSearch"
              type="text" 
              placeholder="Tìm theo tên hoặc mã huy hiệu..." 
              class="w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-sm focus:border-[#E8192C] focus:outline-none transition-colors shadow-sm"
            />
          </div>
          <div class="w-full sm:w-64">
            <select 
              v-model="filterType" 
              @change="onFilterChange"
              class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm focus:border-[#E8192C] focus:outline-none transition-colors shadow-sm cursor-pointer"
            >
              <option value="">Tất cả các loại</option>
              <option v-for="type in allBadgeTypes" :key="type.code" :value="type.code">
                {{ type.name }}
              </option>
            </select>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="space-y-3">
          <div v-for="i in 5" :key="i" class="h-16 bg-slate-200/60 rounded-2xl animate-pulse"></div>
        </div>

        <!-- Empty State Badges -->
        <div v-else-if="activeTab === 'badges' && allBadges.length === 0" class="bg-white rounded-2xl border border-slate-200 p-12 text-center my-8">
          <span class="material-symbols-outlined text-5xl text-slate-300 mb-2">military_tech</span>
          <p class="font-bold text-slate-700 text-base">Chưa có huy hiệu nào</p>
          <p class="text-xs text-slate-400 mt-1">Bấm tạo huy hiệu mới để bắt đầu</p>
        </div>

        <!-- Empty State Types -->
        <div v-else-if="activeTab === 'types' && allBadgeTypes.length === 0" class="bg-white rounded-2xl border border-slate-200 p-12 text-center my-8">
          <span class="material-symbols-outlined text-5xl text-slate-300 mb-2">category</span>
          <p class="font-bold text-slate-700 text-base">Chưa có loại huy hiệu nào</p>
        </div>

        <!-- LIST VIEW BADGES -->
        <div v-else-if="activeTab === 'badges'" class="space-y-8">
          <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                  <th class="p-4">Icon & Tên</th>
                  <th class="p-4">Code</th>
                  <th class="p-4">Loại</th>
                  <th class="p-4">Độ ưu tiên</th>
                  <th class="p-4">Trạng thái</th>
                  <th class="p-4 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="badge in allBadges" :key="badge.id" class="hover:bg-slate-50/50 transition-colors">
                  <td class="p-4">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center overflow-hidden flex-shrink-0">
                        <img v-if="badge.icon_url" :src="getIconUrl(badge.icon_url)" class="w-full h-full object-cover" />
                        <span v-else class="material-symbols-outlined text-slate-400">military_tech</span>
                      </div>
                      <div>
                        <p class="font-bold text-sm text-slate-800">{{ badge.name }}</p>
                        <p class="text-xs text-slate-500 line-clamp-1 max-w-[200px]" :title="badge.description">{{ badge.description }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="p-4">
                    <span class="font-mono text-xs font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded">{{ badge.code }}</span>
                  </td>
                  <td class="p-4">
                    <span class="text-xs font-medium text-slate-600">{{ getTypeName(badge.type) }}</span>
                  </td>
                  <td class="p-4 text-sm font-medium text-slate-700">
                    {{ badge.priority }}
                  </td>
                  <td class="p-4">
                    <span :class="[
                      'px-2 py-1 rounded-full text-[10px] font-extrabold',
                      badge.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'
                    ]">
                      {{ badge.is_active ? 'Đang bật' : 'Đang tắt' }}
                    </span>
                  </td>
                  <td class="p-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <button @click="openEditModal(badge)" class="p-2 rounded-xl text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer" title="Sửa">
                        <span class="material-symbols-outlined text-[20px]">edit</span>
                      </button>
                      <button @click="confirmDelete(badge)" class="p-2 rounded-xl text-rose-600 hover:bg-rose-100 transition-colors cursor-pointer" title="Xóa">
                        <span class="material-symbols-outlined text-[20px]">delete</span>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            
            <!-- Pagination Controls -->
            <div v-if="lastPage > 1" class="flex items-center justify-between p-4 border-t border-slate-200 bg-slate-50">
              <div class="text-sm text-slate-500">
                Hiển thị trang <span class="font-bold text-slate-700">{{ currentPage }}</span> / <span class="font-bold text-slate-700">{{ lastPage }}</span> (Tổng {{ totalBadges }})
              </div>
              <div class="flex items-center gap-2">
                <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-1.5 text-sm font-medium border border-slate-300 rounded-lg hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                  Trước
                </button>
                <button @click="changePage(currentPage + 1)" :disabled="currentPage === lastPage" class="px-3 py-1.5 text-sm font-medium border border-slate-300 rounded-lg hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                  Sau
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- LIST VIEW TYPES -->
        <div v-else-if="activeTab === 'types'" class="space-y-8">
          <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                  <th class="p-4">Tên loại</th>
                  <th class="p-4">Mã</th>
                  <th class="p-4 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="type in allBadgeTypes" :key="type.id" class="hover:bg-slate-50/50 transition-colors">
                  <td class="p-4 font-bold text-slate-700">{{ type.name }}</td>
                  <td class="p-4 text-sm text-slate-600 font-mono bg-slate-50 rounded px-2">{{ type.code }}</td>
                  <td class="p-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <button @click="editType(type)" class="p-2 text-slate-400 hover:text-[#4392E0] hover:bg-blue-50 rounded-lg transition-colors" title="Chỉnh sửa">
                        <span class="material-symbols-outlined text-lg">edit</span>
                      </button>
                      <button @click="confirmDeleteType(type)" class="p-2 text-slate-400 hover:text-[#E8192C] hover:bg-red-50 rounded-lg transition-colors" title="Xóa">
                        <span class="material-symbols-outlined text-lg">delete</span>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- CREATE / EDIT MODAL -->
      <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all flex flex-col max-h-[90vh]">
            
            <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-[#E8192C]">military_tech</span>
                <h3 class="font-bold text-lg">
                  {{ isEditing ? 'Chỉnh sửa Huy Hiệu' : 'Tạo Huy Hiệu Mới' }}
                </h3>
              </div>
              <button @click="showModal = false" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-white text-sm">close</span>
              </button>
            </div>

            <div class="p-6 overflow-y-auto flex-1 space-y-5">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Tên Huy Hiệu <span class="text-rose-500">*</span></label>
                <input v-model="formData.name" type="text" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-[#E8192C] focus:outline-none transition-all" />
              </div>
              
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Mã <span class="text-rose-500">*</span></label>
                <input v-model="formData.code" type="text" :disabled="isEditing" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-[#E8192C] focus:outline-none transition-all disabled:opacity-70 uppercase font-mono" placeholder="VD: CHAMPION" />
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Ảnh Icon</label>
                <div
                  @click="triggerFileInput"
                  class="border-2 border-dashed border-slate-300 hover:border-[#E8192C] bg-slate-50 hover:bg-red-50/20 rounded-2xl p-4 text-center cursor-pointer transition-all flex flex-col items-center justify-center relative group"
                >
                  <div v-if="imagePreview || formData.icon_url" class="w-16 h-16 rounded-full bg-white border border-slate-200 flex items-center justify-center overflow-hidden shadow-sm">
                    <img :src="imagePreview || getIconUrl(formData.icon_url)" class="w-full h-full object-cover" />
                  </div>
                  <div v-else class="py-2">
                    <span class="material-symbols-outlined text-3xl text-slate-400 mb-1 group-hover:scale-110 transition-transform">add_photo_alternate</span>
                    <p class="text-xs font-bold text-slate-700">Bấm để tải ảnh lên</p>
                  </div>
                  <div v-if="imagePreview || formData.icon_url" class="mt-2 text-[10px] font-bold text-[#E8192C] hover:underline">
                    Đổi ảnh khác
                  </div>
                </div>
                <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleImageChange" />
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Mô tả</label>
                <textarea v-model="formData.description" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-[#E8192C] focus:outline-none transition-all"></textarea>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Loại</label>
                  <select v-model="formData.type" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-[#E8192C]">
                    <option value="" disabled>Chọn loại huy hiệu</option>
                    <option v-for="type in allBadgeTypes" :key="type.code" :value="type.code">
                      {{ type.name }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Độ ưu tiên</label>
                  <input v-model.number="formData.priority" type="number" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-[#E8192C]" />
                </div>
              </div>

              <div class="flex items-center gap-3 mt-4">
                <input v-model="formData.is_active" type="checkbox" id="isActive" class="w-4 h-4 text-[#E8192C] rounded focus:ring-0 accent-[#E8192C]" />
                <label for="isActive" class="text-sm font-bold text-slate-700 cursor-pointer">Kích hoạt huy hiệu này</label>
              </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-3">
              <button @click="showModal = false" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-sm hover:bg-slate-100 transition-colors cursor-pointer">Hủy</button>
              <button @click="saveBadge" :disabled="saving" class="px-6 py-2.5 rounded-xl bg-[#E8192C] hover:bg-[#c91223] text-white font-bold text-sm shadow-md transition-all active:scale-95 cursor-pointer flex items-center gap-2">
                <span v-if="saving" class="material-symbols-outlined text-sm animate-spin">sync</span>
                <span>{{ saving ? 'Đang lưu...' : 'Lưu' }}</span>
              </button>
            </div>
          </div>
        </div>
      </transition>

      <!-- Modal Create/Edit Badge Type -->
      <transition name="modal">
        <div v-if="showTypeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showTypeModal = false"></div>
          
          <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
              <h3 class="text-lg font-bold text-slate-800 font-headline">{{ isEditingType ? 'Sửa loại huy hiệu' : 'Tạo loại huy hiệu mới' }}</h3>
              <button @click="showTypeModal = false" class="text-slate-400 hover:text-slate-600 transition-colors cursor-pointer">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>

            <div class="p-6 space-y-5">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Tên loại <span class="text-[#E8192C]">*</span></label>
                <input v-model="typeFormData.name" type="text" placeholder="VD: Hạng, Thành tựu, Sự kiện..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-[#E8192C] focus:outline-none transition-all" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Mã <span class="text-[#E8192C]">*</span></label>
                <input v-model="typeFormData.code" type="text" placeholder="VD: rank, achievement, event..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-[#E8192C] focus:outline-none transition-all" :disabled="isEditingType" :class="{'opacity-60 cursor-not-allowed': isEditingType}" />
                <p v-if="!isEditingType" class="text-xs text-slate-400 mt-1">Mã không có dấu, không khoảng trắng (vd: event)</p>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Mô tả</label>
                <textarea v-model="typeFormData.description" placeholder="Mô tả về loại huy hiệu này (hiển thị khi di chuột vào dấu chấm hỏi)" rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-[#E8192C] focus:outline-none transition-all"></textarea>
              </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-3">
              <button @click="showTypeModal = false" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-sm hover:bg-slate-100 transition-colors cursor-pointer">Hủy</button>
              <button @click="saveBadgeType" :disabled="savingType" class="px-6 py-2.5 rounded-xl bg-[#E8192C] hover:bg-[#c91223] text-white font-bold text-sm shadow-md transition-all active:scale-95 cursor-pointer flex items-center gap-2">
                <span v-if="savingType" class="material-symbols-outlined text-sm animate-spin">sync</span>
                <span>{{ savingType ? 'Đang lưu...' : 'Lưu' }}</span>
              </button>
            </div>
          </div>
        </div>
      </transition>
      <!-- Delete Confirmation Modal -->
      <DeleteConfirmationModal
        v-model="showDeleteConfirm"
        title="Xác nhận xóa"
        :message="deleteConfirmMessage"
        confirmButtonText="Xóa"
        @confirm="onConfirmDelete"
      />
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminSidebar from '@/components/organisms/AdminSidebar.vue';
import AdminHeader from '@/components/organisms/AdminHeader.vue';
import DeleteConfirmationModal from '@/components/molecules/DeleteConfirmationModal.vue';
import http from '@/utils/httpRequest';
import { toast } from 'vue3-toastify';

const showDeleteConfirm = ref(false);
const deleteTarget = ref(null);
const deleteType = ref('badge');
const deleteConfirmMessage = ref('');

const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const isEditing = ref(false);
const allBadges = ref([]);

const currentPage = ref(1);
const lastPage = ref(1);
const totalBadges = ref(0);

const activeTab = ref('badges');
const allBadgeTypes = ref([]);
const showTypeModal = ref(false);
const savingType = ref(false);
const isEditingType = ref(false);

const searchQuery = ref('');
const filterType = ref('');
let searchTimeout = null;

const onSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    currentPage.value = 1;
    fetchBadges();
  }, 500);
};

const onFilterChange = () => {
  currentPage.value = 1;
  fetchBadges();
};

const typeFormData = ref({
  id: null,
  code: '',
  name: '',
  description: '',
});

const fileInput = ref(null);
const imageFile = ref(null);
const imagePreview = ref(null);

const formData = ref({
  id: null,
  code: '',
  name: '',
  description: '',
  icon_url: '',
  type: 'achievement',
  priority: 0,
  is_active: true,
});

const getIconUrl = (path) => {
  if (!path) return '';
  if (path.startsWith('http://') || path.startsWith('https://')) return path;
  return path;
};

const getTypeName = (typeCode) => {
  const found = allBadgeTypes.value.find(t => t.code === typeCode);
  return found ? found.name : typeCode;
};

const triggerFileInput = () => {
  if (fileInput.value) fileInput.value.click();
};

const handleImageChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    imageFile.value = file;
    imagePreview.value = URL.createObjectURL(file);
  }
};

const fetchBadges = async () => {
  loading.value = true;
  try {
    const params = new URLSearchParams({ page: currentPage.value });
    if (searchQuery.value) params.append('search', searchQuery.value);
    if (filterType.value) params.append('type', filterType.value);

    const res = await http.get(`/admin/badges?${params.toString()}`);
    if (res.data && res.data.data) {
      allBadges.value = res.data.data;
      if (res.data.meta) {
        currentPage.value = res.data.meta.current_page;
        lastPage.value = res.data.meta.last_page;
        totalBadges.value = res.data.meta.total;
      }
    }
  } catch (e) {
    console.error('Lỗi khi tải danh sách:', e);
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => {
  if (page >= 1 && page <= lastPage.value) {
    currentPage.value = page;
    fetchBadges();
  }
};

const openCreateModal = () => {
  isEditing.value = false;
  imageFile.value = null;
  imagePreview.value = null;
  formData.value = {
    id: null,
    code: '',
    name: '',
    description: '',
    icon_url: '',
    type: 'achievement',
    priority: 0,
    is_active: true,
  };
  showModal.value = true;
};

const openEditModal = (badge) => {
  isEditing.value = true;
  imageFile.value = null;
  imagePreview.value = null;
  formData.value = {
    id: badge.id,
    code: badge.code,
    name: badge.name,
    description: badge.description || '',
    icon_url: badge.icon_url || '',
    type: badge.type || 'achievement',
    priority: badge.priority || 0,
    is_active: badge.is_active === 1 || badge.is_active === true,
  };
  showModal.value = true;
};

const saveBadge = async () => {
  if (!formData.value.name || !formData.value.code) {
    toast.error('Vui lòng nhập Tên và Mã huy hiệu');
    return;
  }
  
  saving.value = true;
  try {
    const payload = new FormData();
    payload.append('code', formData.value.code.toUpperCase().replace(/\s+/g, '_'));
    payload.append('name', formData.value.name);
    payload.append('description', formData.value.description);
    payload.append('type', formData.value.type);
    payload.append('priority', formData.value.priority);
    payload.append('is_active', formData.value.is_active ? 1 : 0);
    
    if (imageFile.value) {
      payload.append('icon', imageFile.value);
    }
    
    if (isEditing.value) {
      payload.append('_method', 'PUT'); // For laravel spoofing
      await http.post(`/admin/badges/${formData.value.id}`, payload);
      toast.success('Cập nhật thành công!');
    } else {
      await http.post('/admin/badges', payload);
      toast.success('Tạo huy hiệu mới thành công!');
    }
    
    showModal.value = false;
    await fetchBadges();
  } catch (e) {
    console.error('Lỗi lưu:', e);
    toast.error(e.response?.data?.message || 'Có lỗi xảy ra');
  } finally {
    saving.value = false;
  }
};

const confirmDelete = (badge) => {
  deleteTarget.value = badge;
  deleteType.value = 'badge';
  deleteConfirmMessage.value = `Bạn có chắc chắn muốn xóa huy hiệu ${badge.name}?`;
  showDeleteConfirm.value = true;
};

const onConfirmDelete = async () => {
  if (deleteType.value === 'badge') {
    try {
      await http.delete(`/admin/badges/${deleteTarget.value.id}`);
      toast.success('Đã xóa huy hiệu');
      await fetchBadges();
    } catch (e) {
      toast.error('Có lỗi xảy ra khi xóa');
    }
  } else if (deleteType.value === 'badgeType') {
    try {
      await http.delete(`/admin/badge-types/${deleteTarget.value.id}`);
      toast.success('Đã xóa loại huy hiệu');
      await fetchBadgeTypes();
    } catch (e) {
      toast.error(e.response?.data?.message || 'Có lỗi xảy ra khi xóa');
    }
  }
  showDeleteConfirm.value = false;
};

// BADGE TYPES
const fetchBadgeTypes = async () => {
  try {
    const res = await http.get('/admin/badge-types');
    if (res.data && res.data.data) {
      allBadgeTypes.value = res.data.data;
    }
  } catch (e) {
    console.error('Lỗi khi tải danh sách loại:', e);
  }
};

const openCreateTypeModal = () => {
  isEditingType.value = false;
  typeFormData.value = {
    id: null,
    code: '',
    name: '',
    description: '',
  };
  showTypeModal.value = true;
};

const editType = (type) => {
  isEditingType.value = true;
  typeFormData.value = {
    id: type.id,
    code: type.code,
    name: type.name,
    description: type.description || '',
  };
  showTypeModal.value = true;
};

const saveBadgeType = async () => {
  if (!typeFormData.value.name || !typeFormData.value.code) {
    toast.error('Vui lòng nhập Tên và Mã loại');
    return;
  }

  savingType.value = true;
  try {
    const payload = {
      code: typeFormData.value.code.toLowerCase().replace(/\s+/g, '_'),
      name: typeFormData.value.name,
      description: typeFormData.value.description,
    };

    if (isEditingType.value) {
      await http.put(`/admin/badge-types/${typeFormData.value.id}`, payload);
      toast.success('Cập nhật thành công!');
    } else {
      await http.post('/admin/badge-types', payload);
      toast.success('Tạo loại huy hiệu thành công!');
    }

    showTypeModal.value = false;
    await fetchBadgeTypes();
  } catch (e) {
    console.error('Lỗi lưu loại:', e);
    toast.error(e.response?.data?.message || 'Có lỗi xảy ra');
  } finally {
    savingType.value = false;
  }
};

const confirmDeleteType = (type) => {
  deleteTarget.value = type;
  deleteType.value = 'badgeType';
  deleteConfirmMessage.value = `Bạn có chắc chắn muốn xóa loại huy hiệu ${type.name}?`;
  showDeleteConfirm.value = true;
};

onMounted(() => {
  fetchBadges();
  fetchBadgeTypes();
});
</script>
