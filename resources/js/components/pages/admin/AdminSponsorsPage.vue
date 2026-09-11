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
              <span class="material-symbols-outlined text-primary text-3xl">storefront</span>
              <h1 class="text-2xl lg:text-3xl font-headline font-bold text-on-surface">Quản lý Nhãn hàng Tài trợ</h1>
            </div>
            <p class="text-sm text-on-surface-variant mt-1">
              Quản lý logo đối tác và nhãn hàng tài trợ hiển thị trên trang chủ web & ứng dụng.
            </p>
          </div>

          <button
            @click="openCreateModal"
            class="flex items-center justify-center gap-2 bg-[#E8192C] hover:bg-[#c91223] text-white px-5 py-3 rounded-xl font-bold text-sm shadow-md transition-all active:scale-95 cursor-pointer w-fit"
          >
            <span class="material-symbols-outlined text-xl">add</span>
            <span>Thêm nhãn hàng mới</span>
          </button>
        </div>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700">
              <span class="material-symbols-outlined text-2xl">loyalty</span>
            </div>
            <div>
              <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tổng nhãn hàng</p>
              <p class="text-2xl font-headline font-extrabold text-slate-800">{{ stats.total }}</p>
            </div>
          </div>

          <div class="bg-white rounded-2xl p-5 border border-emerald-200/80 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
              <span class="material-symbols-outlined text-2xl">check_circle</span>
            </div>
            <div>
              <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Đang hiển thị</p>
              <p class="text-2xl font-headline font-extrabold text-emerald-700">{{ stats.active }}</p>
            </div>
          </div>

          <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500">
              <span class="material-symbols-outlined text-2xl">visibility_off</span>
            </div>
            <div>
              <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Đang ẩn</p>
              <p class="text-2xl font-headline font-extrabold text-slate-700">{{ stats.inactive }}</p>
            </div>
          </div>

          <div class="bg-white rounded-2xl p-5 border border-blue-200/80 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
              <span class="material-symbols-outlined text-2xl">open_in_new</span>
            </div>
            <div>
              <p class="text-xs font-bold text-blue-600 uppercase tracking-wider">Có liên kết web</p>
              <p class="text-2xl font-headline font-extrabold text-blue-700">{{ stats.with_link }}</p>
            </div>
          </div>
        </div>

        <!-- Filter Tabs & Search Controls -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-center gap-1 overflow-x-auto custom-scrollbar pb-2 md:pb-0">
            <button
              v-for="tab in filterTabs"
              :key="tab.id"
              @click="activeFilter = tab.id"
              :class="[
                'px-4 py-2 rounded-xl font-bold text-xs transition-all whitespace-nowrap cursor-pointer flex items-center gap-2',
                activeFilter === tab.id
                  ? 'bg-[#E8192C] text-white shadow-sm'
                  : 'text-slate-600 hover:bg-slate-100'
              ]"
            >
              <span>{{ tab.label }}</span>
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-[10px] font-extrabold',
                  activeFilter === tab.id ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'
                ]"
              >
                {{ tab.count }}
              </span>
            </button>
          </div>

          <div class="relative w-full md:w-72">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Tìm kiếm theo tên nhãn hàng..."
              class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
            />
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="py-16 flex flex-col items-center justify-center text-slate-400">
          <span class="material-symbols-outlined text-4xl animate-spin text-primary">progress_activity</span>
          <p class="mt-3 text-sm font-semibold">Đang tải danh sách nhãn hàng tài trợ...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredSponsors.length === 0" class="bg-white rounded-2xl border border-slate-200 p-12 text-center my-4 shadow-sm">
          <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mx-auto mb-3">
            <span class="material-symbols-outlined text-3xl">storefront</span>
          </div>
          <p class="font-bold text-slate-700 text-base">Chưa có nhãn hàng tài trợ nào</p>
          <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
            Thêm logo nhãn hàng tài trợ để hiển thị dạng dải chạy ngang nổi bật trên trang chủ web.
          </p>
          <button
            @click="openCreateModal"
            class="mt-4 inline-flex items-center gap-1.5 bg-[#E8192C] hover:bg-[#c91223] text-white px-4 py-2 rounded-xl font-bold text-xs shadow transition-all cursor-pointer"
          >
            <span class="material-symbols-outlined text-base">add</span>
            <span>Thêm nhãn hàng đầu tiên</span>
          </button>
        </div>

        <!-- SPONSOR LIST -->
        <div v-else class="space-y-4">
          <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                Danh sách nhãn hàng ({{ filteredSponsors.length }})
              </span>
              <span class="text-xs text-slate-400">
                Thứ tự số nhỏ hơn sẽ được xếp trước
              </span>
            </div>

            <div class="divide-y divide-slate-100">
              <div
                v-for="(sponsor, idx) in filteredSponsors"
                :key="sponsor.id"
                class="p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 hover:bg-slate-50/60 transition-colors"
              >
                <!-- Left: Order, Logo & Info -->
                <div class="flex items-center gap-4 min-w-0">
                  <!-- Display Order Badge -->
                  <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-xs font-extrabold text-slate-600 flex-shrink-0">
                    #{{ sponsor.display_order }}
                  </div>

                  <!-- Logo Boxes: Dương bản (nền sáng) & Âm bản (nền tối) -->
                  <div class="flex items-center gap-2 flex-shrink-0">
                    <!-- Dương bản -->
                    <div class="w-16 h-12 rounded-xl border border-slate-200 bg-white p-1.5 flex flex-col items-center justify-center shadow-xs overflow-hidden checker-pattern group relative" title="Logo Dương bản (Nền sáng)">
                      <img
                        :src="getLogoUrl(sponsor.logo_url)"
                        :alt="sponsor.name || 'Logo dương bản'"
                        class="max-w-full max-h-full object-contain"
                      />
                    </div>
                    <!-- Âm bản -->
                    <div
                      class="w-16 h-12 rounded-xl border border-slate-700 bg-[#0F172A] p-1.5 flex flex-col items-center justify-center shadow-xs overflow-hidden dark-checker-pattern relative"
                      :title="sponsor.logo_dark_url ? 'Logo Âm bản (Nền tối / Dark Mode)' : 'Chưa có logo âm bản riêng'"
                    >
                      <img
                        v-if="sponsor.logo_dark_url"
                        :src="getLogoUrl(sponsor.logo_dark_url)"
                        :alt="sponsor.name || 'Logo âm bản'"
                        class="max-w-full max-h-full object-contain"
                      />
                      <span v-else class="text-[9px] text-slate-500 font-medium select-none">Mặc định</span>
                    </div>
                  </div>

                  <!-- Name & External Link -->
                  <div class="min-w-0">
                    <div class="flex items-center gap-2">
                      <h4 class="font-bold text-slate-900 text-sm truncate">
                        {{ sponsor.name || '(Chưa đặt tên logo)' }}
                      </h4>
                      <span
                        :class="[
                          'px-2 py-0.5 rounded-full text-[10px] font-extrabold flex-shrink-0',
                          sponsor.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'
                        ]"
                      >
                        {{ sponsor.is_active ? 'Đang bật' : 'Đang ẩn' }}
                      </span>
                    </div>

                    <div class="flex items-center gap-3 mt-1">
                      <!-- External Link -->
                      <a
                        v-if="sponsor.website_url"
                        :href="sponsor.website_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1 hover:underline truncate max-w-[280px]"
                        :title="sponsor.website_url"
                      >
                        <span class="material-symbols-outlined text-sm">link</span>
                        <span class="truncate">{{ sponsor.website_url }}</span>
                        <span class="material-symbols-outlined text-[13px]">open_in_new</span>
                      </a>
                      <span v-else class="text-xs text-slate-400 italic">
                        Không có liên kết web
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Right: Action Buttons -->
                <div class="flex items-center gap-2 w-full md:w-auto justify-end border-t md:border-t-0 pt-3 md:pt-0 border-slate-100">
                  <!-- Quick Toggle Active Status -->
                  <button
                    @click="toggleStatus(sponsor)"
                    :class="[
                      'px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                      sponsor.is_active
                        ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200'
                    ]"
                  >
                    <span class="material-symbols-outlined text-sm">
                      {{ sponsor.is_active ? 'check_circle' : 'do_not_disturb_on' }}
                    </span>
                    <span>{{ sponsor.is_active ? 'Bật' : 'Tắt' }}</span>
                  </button>

                  <!-- Edit Button -->
                  <button
                    @click="openEditModal(sponsor)"
                    class="p-2 rounded-xl text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors cursor-pointer"
                    title="Chỉnh sửa nhãn hàng"
                  >
                    <span class="material-symbols-outlined text-lg">edit</span>
                  </button>

                  <!-- Delete Button -->
                  <button
                    @click="confirmDelete(sponsor)"
                    class="p-2 rounded-xl text-red-500 hover:bg-red-50 hover:text-red-700 transition-colors cursor-pointer"
                    title="Xóa nhãn hàng"
                  >
                    <span class="material-symbols-outlined text-lg">delete</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- CREATE / EDIT MODAL -->
      <transition name="modal-fade">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
          <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden border border-slate-100">
            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
              <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
                  <span class="material-symbols-outlined text-xl">storefront</span>
                </div>
                <h3 class="font-headline font-bold text-lg text-slate-900">
                  {{ isEditing ? 'Chỉnh sửa nhãn hàng tài trợ' : 'Thêm nhãn hàng tài trợ mới' }}
                </h3>
              </div>
              <button
                @click="showModal = false"
                class="w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors cursor-pointer"
              >
                <span class="material-symbols-outlined text-xl">close</span>
              </button>
            </div>

            <!-- Modal Body -->
            <form @submit.prevent="saveSponsor" class="p-6 space-y-5">
              <!-- Upload Logos: Dương bản & Âm bản -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- 1. Logo Dương bản (Nền sáng) -->
                <div>
                  <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                      Logo Dương bản <span class="text-red-500">*</span>
                    </label>
                    <span class="text-[10px] font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                      Nền sáng
                    </span>
                  </div>

                  <div
                    @click="triggerFileInput"
                    class="border-2 border-dashed border-slate-200 hover:border-primary/60 rounded-2xl p-4 text-center cursor-pointer transition-all bg-slate-50/50 hover:bg-red-50/20 group relative overflow-hidden h-44 flex flex-col items-center justify-center"
                  >
                    <input
                      ref="fileInput"
                      type="file"
                      accept="image/*,.svg"
                      class="hidden"
                      @change="onFileSelected"
                    />

                    <!-- Image Preview -->
                    <div v-if="logoPreview || formData.logo_url" class="flex flex-col items-center">
                      <div class="w-32 h-20 rounded-xl border border-slate-200 bg-white p-2 flex items-center justify-center mb-2 shadow-xs checker-pattern">
                        <img
                          :src="logoPreview || getLogoUrl(formData.logo_url)"
                          alt="Logo dương bản preview"
                          class="max-w-full max-h-full object-contain"
                        />
                      </div>
                      <p class="text-xs font-bold text-primary group-hover:underline">Bấm để thay đổi</p>
                      <p class="text-[10px] text-slate-400 mt-0.5">PNG trong suốt hoặc SVG</p>
                    </div>

                    <!-- Upload Placeholder -->
                    <div v-else class="py-2">
                      <span class="material-symbols-outlined text-3xl text-slate-400 group-hover:text-primary transition-colors">cloud_upload</span>
                      <p class="text-xs font-bold text-slate-700 mt-1">Chọn logo dương bản</p>
                      <p class="text-[10px] text-slate-400 mt-0.5">Dùng cho giao diện nền sáng</p>
                    </div>
                  </div>
                </div>

                <!-- 2. Logo Âm bản (Nền tối / Dark Mode) -->
                <div>
                  <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                      Logo Âm bản
                    </label>
                    <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                      Nền tối / Dark
                    </span>
                  </div>

                  <div
                    @click="triggerDarkFileInput"
                    class="border-2 border-dashed border-slate-300 hover:border-slate-500 rounded-2xl p-4 text-center cursor-pointer transition-all bg-slate-900/90 hover:bg-slate-900 group relative overflow-hidden h-44 flex flex-col items-center justify-center"
                  >
                    <input
                      ref="darkFileInput"
                      type="file"
                      accept="image/*,.svg"
                      class="hidden"
                      @change="onDarkFileSelected"
                    />

                    <!-- Image Preview -->
                    <div v-if="logoDarkPreview || formData.logo_dark_url" class="flex flex-col items-center relative w-full">
                      <div class="w-32 h-20 rounded-xl border border-slate-700 bg-[#0F172A] p-2 flex items-center justify-center mb-2 shadow-xs dark-checker-pattern">
                        <img
                          :src="logoDarkPreview || getLogoUrl(formData.logo_dark_url)"
                          alt="Logo âm bản preview"
                          class="max-w-full max-h-full object-contain"
                        />
                      </div>
                      <div class="flex items-center gap-2">
                        <p class="text-xs font-bold text-slate-300 group-hover:underline">Bấm để thay đổi</p>
                        <button
                          type="button"
                          @click.stop="removeDarkLogo"
                          class="text-[10px] text-rose-400 hover:text-rose-300 hover:underline cursor-pointer"
                        >
                          (Gỡ bỏ)
                        </button>
                      </div>
                      <p class="text-[10px] text-slate-400 mt-0.5">Dành cho nền tối / Dark Mode</p>
                    </div>

                    <!-- Upload Placeholder -->
                    <div v-else class="py-2 text-slate-300">
                      <span class="material-symbols-outlined text-3xl text-slate-400 group-hover:text-slate-200 transition-colors">dark_mode</span>
                      <p class="text-xs font-bold text-slate-200 mt-1">Chọn logo âm bản (Tùy chọn)</p>
                      <p class="text-[10px] text-slate-400 mt-0.5">Nếu để trống, tự dùng logo dương bản</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tên Logo / Nhãn Hàng (Optional) -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    Tên Nhãn Hàng
                  </label>
                  <span class="text-[11px] font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                    Tùy chọn (Optional)
                  </span>
                </div>
                <input
                  v-model="formData.name"
                  type="text"
                  placeholder="Ví dụ: Wilson, Franklin, Selkirk, Joola..."
                  class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                />
                <p class="text-[11px] text-slate-400 mt-1">
                  Nếu để trống, trên web sẽ chỉ hiển thị ảnh logo nhãn hàng.
                </p>
              </div>

              <!-- Link ngoài app (Optional) -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    Liên kết truy cập ngoài app
                  </label>
                  <span class="text-[11px] font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                    Tùy chọn (Optional)
                  </span>
                </div>
                <div class="relative">
                  <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">public</span>
                  <input
                    v-model="formData.website_url"
                    type="url"
                    placeholder="https://brand-website.com"
                    class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                  />
                </div>
                <p class="text-[11px] text-slate-400 mt-1">
                  Khi người dùng bấm vào logo trên web, liên kết sẽ được mở trong tab mới.
                </p>
              </div>

              <!-- Display Order & Active Toggle -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Thứ tự hiển thị
                  </label>
                  <input
                    v-model.number="formData.display_order"
                    type="number"
                    min="0"
                    placeholder="1"
                    class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                  />
                </div>

                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Trạng thái hiển thị
                  </label>
                  <div class="flex items-center h-10">
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input
                        type="checkbox"
                        v-model="formData.is_active"
                        class="sr-only peer"
                      />
                      <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                      <span class="ml-3 text-xs font-bold" :class="formData.is_active ? 'text-emerald-600' : 'text-slate-400'">
                        {{ formData.is_active ? 'Đang hiển thị' : 'Đang ẩn' }}
                      </span>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Modal Actions -->
              <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button
                  type="button"
                  @click="showModal = false"
                  class="px-5 py-2.5 rounded-xl font-bold text-xs text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                >
                  Hủy
                </button>
                <button
                  type="submit"
                  :disabled="saving"
                  class="flex items-center justify-center gap-2 bg-[#E8192C] hover:bg-[#c91223] text-white px-6 py-2.5 rounded-xl font-bold text-xs shadow-md transition-all active:scale-95 cursor-pointer disabled:opacity-50"
                >
                  <span v-if="saving" class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
                  <span>{{ saving ? 'Đang lưu...' : (isEditing ? 'Lưu thay đổi' : 'Thêm nhãn hàng') }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </transition>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminSidebar from '@/components/organisms/AdminSidebar.vue';
import AdminHeader from '@/components/organisms/AdminHeader.vue';
import {
  getAdminSponsors,
  createAdminSponsor,
  updateAdminSponsor,
  toggleAdminSponsorStatus,
  deleteAdminSponsor,
} from '@/service/sponsor';
import { toast } from 'vue3-toastify';

const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const isEditing = ref(false);

const activeFilter = ref('all');
const searchQuery = ref('');

const allSponsors = ref([]);
const stats = ref({
  total: 0,
  active: 0,
  inactive: 0,
  with_link: 0,
});

const fileInput = ref(null);
const logoFile = ref(null);
const logoPreview = ref(null);

const darkFileInput = ref(null);
const logoDarkFile = ref(null);
const logoDarkPreview = ref(null);

const formData = ref({
  id: null,
  name: '',
  logo_url: '',
  logo_dark_url: '',
  website_url: '',
  display_order: 1,
  is_active: true,
});

const getLogoUrl = (url) => {
  if (!url) return '';
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url;
  }
  const storageBase = import.meta.env.VITE_STORAGE_URL || '/storage/';
  return `${storageBase.replace(/\/$/, '')}/${url.replace(/^\//, '')}`;
};

const filterTabs = computed(() => [
  { id: 'all', label: 'Tất cả', count: allSponsors.value.length },
  { id: 'active', label: 'Đang hiển thị', count: allSponsors.value.filter(s => s.is_active).length },
  { id: 'inactive', label: 'Đang ẩn', count: allSponsors.value.filter(s => !s.is_active).length },
]);

const filteredSponsors = computed(() => {
  return allSponsors.value.filter(s => {
    // Filter tab
    if (activeFilter.value === 'active' && !s.is_active) return false;
    if (activeFilter.value === 'inactive' && s.is_active) return false;

    // Search query
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.trim().toLowerCase();
      const matchName = s.name ? s.name.toLowerCase().includes(q) : false;
      const matchLink = s.website_url ? s.website_url.toLowerCase().includes(q) : false;
      return matchName || matchLink;
    }

    return true;
  });
});

const fetchSponsors = async () => {
  loading.value = true;
  try {
    const res = await getAdminSponsors();
    if (res.data && res.data.data) {
      allSponsors.value = res.data.data.sponsors || [];
      if (res.data.data.stats) {
        stats.value = res.data.data.stats;
      }
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách nhãn hàng:', err);
    toast.error('Không thể tải danh sách nhãn hàng tài trợ');
  } finally {
    loading.value = false;
  }
};

const triggerFileInput = () => {
  fileInput.value?.click();
};

const triggerDarkFileInput = () => {
  darkFileInput.value?.click();
};

const onFileSelected = (e) => {
  const file = e.target.files[0];
  if (!file) return;

  if (file.size > 5 * 1024 * 1024) {
    toast.error('Kích thước ảnh không được vượt quá 5MB');
    return;
  }

  logoFile.value = file;
  logoPreview.value = URL.createObjectURL(file);
};

const onDarkFileSelected = (e) => {
  const file = e.target.files[0];
  if (!file) return;

  if (file.size > 5 * 1024 * 1024) {
    toast.error('Kích thước ảnh âm bản không được vượt quá 5MB');
    return;
  }

  logoDarkFile.value = file;
  logoDarkPreview.value = URL.createObjectURL(file);
};

const removeDarkLogo = () => {
  logoDarkFile.value = null;
  logoDarkPreview.value = null;
  formData.value.logo_dark_url = '';
  if (darkFileInput.value) {
    darkFileInput.value.value = '';
  }
};

const openCreateModal = () => {
  isEditing.value = false;
  logoFile.value = null;
  logoPreview.value = null;
  logoDarkFile.value = null;
  logoDarkPreview.value = null;
  formData.value = {
    id: null,
    name: '',
    logo_url: '',
    logo_dark_url: '',
    website_url: '',
    display_order: allSponsors.value.length + 1,
    is_active: true,
  };
  showModal.value = true;
};

const openEditModal = (sponsor) => {
  isEditing.value = true;
  logoFile.value = null;
  logoPreview.value = null;
  logoDarkFile.value = null;
  logoDarkPreview.value = null;
  formData.value = {
    id: sponsor.id,
    name: sponsor.name || '',
    logo_url: sponsor.logo_url || '',
    logo_dark_url: sponsor.logo_dark_url || '',
    website_url: sponsor.website_url || '',
    display_order: sponsor.display_order ?? 1,
    is_active: Boolean(sponsor.is_active),
  };
  showModal.value = true;
};

const toggleStatus = async (sponsor) => {
  try {
    const res = await toggleAdminSponsorStatus(sponsor.id);
    if (res.data && res.data.status === 'success') {
      sponsor.is_active = !sponsor.is_active;
      toast.success(sponsor.is_active ? 'Đã bật hiển thị nhãn hàng' : 'Đã ẩn nhãn hàng');
      await fetchSponsors();
    }
  } catch (err) {
    console.error('Lỗi khi đổi trạng thái nhãn hàng:', err);
    toast.error('Không thể đổi trạng thái');
  }
};

const saveSponsor = async () => {
  if (!isEditing.value && !logoFile.value && !formData.value.logo_url) {
    toast.error('Vui lòng tải lên ảnh logo nhãn hàng');
    return;
  }

  saving.value = true;
  try {
    const payload = new FormData();
    payload.append('name', formData.value.name ? formData.value.name.trim() : '');
    payload.append('website_url', formData.value.website_url ? formData.value.website_url.trim() : '');
    payload.append('display_order', formData.value.display_order || 1);
    payload.append('is_active', formData.value.is_active ? 1 : 0);

    if (logoFile.value) {
      payload.append('logo', logoFile.value);
    } else if (formData.value.logo_url) {
      payload.append('logo_url', formData.value.logo_url);
    }

    // Logo âm bản (nền tối / Dark Mode)
    if (logoDarkFile.value) {
      payload.append('logo_dark', logoDarkFile.value);
    } else {
      payload.append('logo_dark_url', formData.value.logo_dark_url || '');
    }

    let res;
    if (isEditing.value) {
      res = await updateAdminSponsor(formData.value.id, payload);
    } else {
      res = await createAdminSponsor(payload);
    }

    if (res.data && (res.data.status === 'success' || res.data.status === true)) {
      toast.success(isEditing.value ? 'Cập nhật nhãn hàng thành công!' : 'Thêm nhãn hàng mới thành công!');
      showModal.value = false;
      await fetchSponsors();
    } else {
      toast.error(res.data?.message || 'Có lỗi xảy ra khi lưu nhãn hàng');
    }
  } catch (err) {
    console.error('Lỗi khi lưu nhãn hàng:', err);
    toast.error(err.response?.data?.message || 'Không thể lưu nhãn hàng tài trợ');
  } finally {
    saving.value = false;
  }
};

const confirmDelete = async (sponsor) => {
  const displayName = sponsor.name ? `"${sponsor.name}"` : 'nhãn hàng này';
  if (!confirm(`Bạn có chắc chắn muốn xóa logo ${displayName}?`)) {
    return;
  }

  try {
    await deleteAdminSponsor(sponsor.id);
    toast.success('Đã xóa nhãn hàng tài trợ');
    await fetchSponsors();
  } catch (err) {
    console.error('Lỗi khi xóa nhãn hàng:', err);
    toast.error('Không thể xóa nhãn hàng tài trợ');
  }
};

onMounted(() => {
  fetchSponsors();
});
</script>

<style scoped>
.checker-pattern {
  background-image: linear-gradient(45deg, #f1f5f9 25%, transparent 25%),
    linear-gradient(-45deg, #f1f5f9 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, #f1f5f9 75%),
    linear-gradient(-45deg, transparent 75%, #f1f5f9 75%);
  background-size: 12px 12px;
  background-position: 0 0, 0 6px, 6px -6px, -6px 0px;
}

.dark-checker-pattern {
  background-color: #0f172a;
  background-image: linear-gradient(45deg, #1e293b 25%, transparent 25%),
    linear-gradient(-45deg, #1e293b 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, #1e293b 75%),
    linear-gradient(-45deg, transparent 75%, #1e293b 75%);
  background-size: 10px 10px;
  background-position: 0 0, 0 5px, 5px -5px, -5px 0px;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
  transform: scale(0.97);
}

.custom-scrollbar::-webkit-scrollbar {
  height: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
</style>
