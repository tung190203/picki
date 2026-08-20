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
              <span class="material-symbols-outlined text-primary text-3xl">view_carousel</span>
              <h1 class="text-2xl lg:text-3xl font-headline font-bold text-on-surface">Quản lý Banner Carousel</h1>
            </div>
            <p class="text-sm text-on-surface-variant mt-1">
              Quản lý danh sách banner trang chủ, lên lịch hiển thị, kéo-thả sắp xếp vị trí carousel và phân tập đối tượng.
            </p>
          </div>

          <button
            @click="openCreateModal"
            class="flex items-center justify-center gap-2 bg-[#E8192C] hover:bg-[#c91223] text-white px-5 py-3 rounded-xl font-bold text-sm shadow-md transition-all active:scale-95 cursor-pointer w-fit"
          >
            <span class="material-symbols-outlined text-xl">add</span>
            <span>Tạo banner mới</span>
          </button>
        </div>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700">
              <span class="material-symbols-outlined text-2xl">collections</span>
            </div>
            <div>
              <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tổng Banner</p>
              <p class="text-2xl font-headline font-extrabold text-slate-800">{{ allBanners.length }}</p>
            </div>
          </div>

          <div class="bg-white rounded-2xl p-5 border border-emerald-200/80 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
              <span class="material-symbols-outlined text-2xl">play_circle</span>
            </div>
            <div>
              <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Đang chạy</p>
              <p class="text-2xl font-headline font-extrabold text-emerald-700">{{ stats.live }}</p>
            </div>
          </div>

          <div class="bg-white rounded-2xl p-5 border border-amber-200/80 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
              <span class="material-symbols-outlined text-2xl">warning</span>
            </div>
            <div>
              <p class="text-xs font-bold text-amber-600 uppercase tracking-wider">Sắp hết hạn (≤5d)</p>
              <p class="text-2xl font-headline font-extrabold text-amber-700">{{ stats.expiring }}</p>
            </div>
          </div>

          <div class="bg-white rounded-2xl p-5 border border-blue-200/80 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
              <span class="material-symbols-outlined text-2xl">schedule</span>
            </div>
            <div>
              <p class="text-xs font-bold text-blue-600 uppercase tracking-wider">Lên lịch</p>
              <p class="text-2xl font-headline font-extrabold text-blue-700">{{ stats.scheduled }}</p>
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

          <div class="relative min-w-[240px]">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Tìm kiếm banner..."
              class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:border-[#E8192C] focus:outline-none transition-all"
            />
          </div>
        </div>

        <!-- Reorder Drag Notification Hint -->
        <div class="flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-xl p-3.5 mb-6 text-xs text-blue-700 font-medium">
          <span class="material-symbols-outlined text-base">drag_indicator</span>
          <span><b>Kéo - thả biểu tượng ☰</b> để sắp xếp nhanh thứ tự vị trí hiển thị Carousel trên Trang chủ.</span>
        </div>

        <!-- Banner Loading State -->
        <div v-if="loading" class="space-y-3">
          <div v-for="i in 5" :key="i" class="h-16 bg-slate-200/60 rounded-2xl animate-pulse"></div>
        </div>

        <!-- Banner Empty State -->
        <div v-else-if="filteredBanners.length === 0" class="bg-white rounded-2xl border border-slate-200 p-12 text-center my-8">
          <span class="material-symbols-outlined text-5xl text-slate-300 mb-2">image_search</span>
          <p class="font-bold text-slate-700 text-base">Không tìm thấy banner phù hợp</p>
          <p class="text-xs text-slate-400 mt-1">Thử thay đổi bộ lọc hoặc tạo banner mới</p>
        </div>

        <!-- BANNER LIST VIEW (DẠNG DANH SÁCH / BẢNG) -->
        <div v-else class="space-y-8">

          <!-- Nhóm 1: Đang hiển thị (Cho phép Kéo-Thả) -->
          <div v-if="activeSectionBanners.length > 0">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-2">
              <span>Đang hiển thị trong Carousel ({{ activeSectionBanners.length }})</span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100 overflow-hidden">
              <div
                v-for="(banner, idx) in activeSectionBanners"
                :key="banner.id"
                draggable="true"
                @dragstart="onDragStart(idx, $event)"
                @dragover.prevent="onDragOver(idx, $event)"
                @drop="onDrop(idx, $event)"
                @dragend="onDragEnd"
                :class="[
                  'p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all duration-200 hover:bg-slate-50/80 group',
                  draggedIndex === idx ? 'opacity-40 bg-blue-50/50 border-blue-300' : '',
                  dragOverIndex === idx ? 'border-t-2 border-t-[#E8192C] bg-red-50/20' : ''
                ]"
              >
                <!-- Left: Drag handle + Thumbnail + Info -->
                <div class="flex items-center gap-4 min-w-0 flex-1">
                  <!-- Drag Handle -->
                  <div class="cursor-grab active:cursor-grabbing text-slate-300 group-hover:text-slate-600 transition-colors p-1" title="Kéo để đổi thứ tự">
                    <span class="material-symbols-outlined text-xl">drag_indicator</span>
                  </div>

                  <!-- Order Index Badge -->
                  <div class="w-7 h-7 rounded-lg bg-slate-100 font-bold text-slate-700 text-xs flex items-center justify-center flex-shrink-0">
                    #{{ idx + 1 }}
                  </div>

                  <!-- Thumbnail Image -->
                  <div class="w-20 h-10 rounded-lg overflow-hidden bg-slate-900 border border-slate-200 flex-shrink-0">
                    <img :src="getBannerUrl(banner.image_url)" :alt="banner.internal_name" class="w-full h-full object-cover" />
                  </div>

                  <!-- Internal Name & Link -->
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                      <h4 class="font-bold text-slate-900 text-sm truncate" :title="banner.internal_name">
                        {{ banner.internal_name }}
                      </h4>
                      <span :class="['px-2 py-0.5 rounded-full text-[10px] font-extrabold flex-shrink-0', getBadgeClass(banner.status_badge)]">
                        {{ getBadgeLabel(banner.status_badge, banner.days_remaining) }}
                      </span>
                    </div>

                    <p class="text-xs text-slate-400 font-mono truncate mt-0.5">
                      {{ banner.link_type === 'none' ? 'Không có link' : banner.link_value }}
                    </p>
                  </div>
                </div>

                <!-- Center: Schedule Dates & Audience -->
                <div class="flex items-center gap-6 text-xs text-slate-600 flex-shrink-0">
                  <div class="text-right hidden sm:block">
                    <p class="text-[11px] text-slate-400">Lịch hiển thị</p>
                    <p class="font-semibold">{{ formatDate(banner.start_date) }} → {{ formatDate(banner.end_date) }}</p>
                  </div>

                  <div class="text-right hidden lg:block">
                    <p class="text-[11px] text-slate-400">Đối tượng</p>
                    <span class="font-semibold text-[#E8192C] bg-red-50 px-2 py-0.5 rounded-md text-[11px]">
                      {{ formatSegments(banner.audience_segment_ids) }}
                    </span>
                  </div>
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center justify-end gap-3 flex-shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-slate-100">
                  <!-- Toggle Enable switch -->
                  <button
                    @click="toggleEnabled(banner)"
                    :class="[
                      'px-3 py-1.5 rounded-xl font-bold text-xs transition-colors cursor-pointer',
                      banner.is_enabled ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-200 text-slate-700 hover:bg-slate-300'
                    ]"
                  >
                    {{ banner.is_enabled ? 'Đang bật' : 'Đang tắt' }}
                  </button>

                  <!-- Edit button -->
                  <button
                    @click="openEditModal(banner)"
                    class="p-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                    title="Chỉnh sửa banner"
                  >
                    <span class="material-symbols-outlined text-lg">edit</span>
                  </button>

                  <!-- Delete button -->
                  <button
                    @click="confirmDelete(banner)"
                    class="p-2 rounded-xl text-rose-600 hover:bg-rose-100 transition-colors cursor-pointer"
                    title="Xóa banner"
                  >
                    <span class="material-symbols-outlined text-lg">delete</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Nhóm 2: Đã kết thúc (Không kéo thả) -->
          <div v-if="endedSectionBanners.length > 0">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
              Đã kết thúc / Đang tắt ({{ endedSectionBanners.length }})
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100 overflow-hidden opacity-75">
              <div
                v-for="banner in endedSectionBanners"
                :key="banner.id"
                class="p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/50 hover:bg-slate-100/60 transition-all"
              >
                <!-- Left: Thumbnail + Info -->
                <div class="flex items-center gap-4 min-w-0 flex-1">
                  <div class="w-20 h-10 rounded-lg overflow-hidden bg-slate-900 border border-slate-200 flex-shrink-0">
                    <img :src="getBannerUrl(banner.image_url)" :alt="banner.internal_name" class="w-full h-full object-cover" />
                  </div>

                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                      <h4 class="font-bold text-slate-800 text-sm truncate" :title="banner.internal_name">
                        {{ banner.internal_name }}
                      </h4>
                      <span :class="['px-2 py-0.5 rounded-full text-[10px] font-extrabold flex-shrink-0', getBadgeClass(banner.status_badge)]">
                        {{ getBadgeLabel(banner.status_badge, banner.days_remaining) }}
                      </span>
                    </div>

                    <p class="text-xs text-slate-400 font-mono truncate mt-0.5">
                      {{ banner.link_type === 'none' ? 'Không có link' : banner.link_value }}
                    </p>
                  </div>
                </div>

                <!-- Center: Dates & Audience -->
                <div class="flex items-center gap-6 text-xs text-slate-600 flex-shrink-0">
                  <div class="text-right hidden sm:block">
                    <p class="text-[11px] text-slate-400">Lịch hiển thị</p>
                    <p class="font-semibold">{{ formatDate(banner.start_date) }} → {{ formatDate(banner.end_date) }}</p>
                  </div>

                  <div class="text-right hidden lg:block">
                    <p class="text-[11px] text-slate-400">Đối tượng</p>
                    <span class="font-semibold text-slate-600 bg-slate-200 px-2 py-0.5 rounded-md text-[11px]">
                      {{ formatSegments(banner.audience_segment_ids) }}
                    </span>
                  </div>
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center justify-end gap-2 flex-shrink-0">
                  <button
                    @click="toggleEnabled(banner)"
                    class="px-3 py-1.5 rounded-xl font-bold text-xs bg-slate-200 text-slate-700 hover:bg-slate-300 transition-colors cursor-pointer"
                  >
                    Bật lại
                  </button>

                  <button
                    @click="openEditModal(banner)"
                    class="p-2 rounded-xl text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer"
                    title="Chỉnh sửa banner"
                  >
                    <span class="material-symbols-outlined text-lg">edit</span>
                  </button>

                  <button
                    @click="confirmDelete(banner)"
                    class="p-2 rounded-xl text-rose-600 hover:bg-rose-100 transition-colors cursor-pointer"
                    title="Xóa banner"
                  >
                    <span class="material-symbols-outlined text-lg">delete</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- CREATE / EDIT MODAL WITH REAL-TIME MOBILE SIMULATION -->
      <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm overflow-y-auto">
          <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 w-full max-w-5xl overflow-hidden my-8 transform transition-all flex flex-col max-h-[90vh]">
            
            <!-- Modal Header -->
            <div class="px-8 py-5 bg-slate-900 text-white flex justify-between items-center sticky top-0 z-10">
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-[#E8192C] text-2xl">view_carousel</span>
                <h3 class="font-bold text-lg">
                  {{ isEditing ? 'Chỉnh sửa Banner: ' + formData.internal_name : 'Tạo Banner mới' }}
                </h3>
              </div>
              <button @click="showModal = false" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-white text-lg">close</span>
              </button>
            </div>

            <!-- Modal Content Grid -->
            <div class="p-8 overflow-y-auto grid grid-cols-1 lg:grid-cols-12 gap-8 flex-1">
              
              <!-- Left Form Controls (7 cols) -->
              <div class="lg:col-span-7 space-y-6">
                <!-- Expiration Warning Banner -->
                <div
                  v-if="isEditing && formData.status_badge === 'expiring'"
                  class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-800 flex items-start gap-3"
                >
                  <span class="material-symbols-outlined text-amber-600 text-lg">warning</span>
                  <div>
                    <p class="font-bold">⚠ Banner này sắp hết hạn (Còn {{ formData.days_remaining }} ngày)</p>
                    <p class="mt-0.5 text-amber-700">Ngày kết thúc hiện tại: {{ formatDate(formData.end_date) }}. Điều chỉnh ngày kết thúc nếu bạn muốn tiếp tục hiển thị.</p>
                  </div>
                </div>

                <!-- Internal Name -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Tên Banner (Nội bộ) <span class="text-rose-500">*</span>
                  </label>
                  <input
                    v-model="formData.internal_name"
                    type="text"
                    placeholder="VD: Khuyến mãi tháng 8 (Picki tặng bạn)"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:border-[#E8192C] focus:outline-none transition-all"
                  />
                  <p class="text-[11px] text-slate-400 mt-1">Chỉ hiển thị trong Super Admin để phân biệt chiến dịch, không hiện ra ngoài với user.</p>
                </div>

                <!-- Banner Image Upload -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Ảnh Banner <span class="text-rose-500">*</span>
                  </label>
                  <div
                    @click="triggerFileInput"
                    class="border-2 border-dashed border-slate-300 hover:border-[#E8192C] bg-slate-50 hover:bg-red-50/20 rounded-2xl p-6 text-center cursor-pointer transition-all flex flex-col items-center justify-center relative overflow-hidden group"
                  >
                    <img
                      v-if="imagePreview || formData.image_url"
                      :src="imagePreview || getBannerUrl(formData.image_url)"
                      alt="Banner Preview"
                      class="w-full aspect-[2.25/1] object-cover rounded-xl shadow-sm"
                    />
                    <div v-else class="py-6">
                      <span class="material-symbols-outlined text-4xl text-slate-400 mb-2 group-hover:scale-110 transition-transform">add_photo_alternate</span>
                      <p class="text-sm font-bold text-slate-700">Bấm để chọn ảnh từ máy tính</p>
                      <p class="text-xs text-slate-400 mt-1">Hỗ trợ PNG, JPG, WEBP (Tối đa 5MB)</p>
                    </div>

                    <div v-if="imagePreview || formData.image_url" class="mt-3 text-xs font-bold text-[#E8192C] hover:underline">
                      Đổi ảnh khác
                    </div>
                  </div>
                  <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleImageChange" />
                  <p class="text-[11px] text-slate-400 mt-1.5">Kích thước khuyến nghị: <b>1080×480px (Tỉ lệ 2.25:1)</b>. Ảnh hiển thị đúng tỷ lệ, không crop tự động.</p>
                </div>

                <!-- Link Destination -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Link đích khi bấm vào Banner</label>
                  <select
                    v-model="formData.link_type"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:border-[#E8192C] focus:outline-none transition-all cursor-pointer"
                  >
                    <option value="none">Không có link (Chỉ hiển thị banner)</option>
                    <option value="internal_deeplink">Mở trang trong app (Deep Link)</option>
                    <option value="external_url">Mở trang ngoài (URL Webview)</option>
                  </select>

                  <input
                    v-if="formData.link_type !== 'none'"
                    v-model="formData.link_value"
                    type="text"
                    class="w-full mt-2.5 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:border-[#E8192C] focus:outline-none transition-all"
                    :placeholder="formData.link_type === 'internal_deeplink' ? 'VD: /rewards/tang-ban hoặc /tournament/1283' : 'VD: https://picki.vn/khuyen-mai'"
                  />
                </div>

                <!-- Schedule Dates -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Lịch hiển thị <span class="text-rose-500">*</span>
                  </label>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <span class="text-xs text-slate-500 block mb-1">Bắt đầu (00:00)</span>
                      <input v-model="formData.start_date" type="date" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:outline-none focus:border-[#E8192C]" />
                    </div>
                    <div>
                      <span class="text-xs text-slate-500 block mb-1">Kết thúc (23:59)</span>
                      <input v-model="formData.end_date" type="date" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:outline-none focus:border-[#E8192C]" />
                    </div>
                  </div>
                </div>

                <!-- Audience Segments -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Đối tượng áp dụng <span class="text-rose-500">*</span>
                  </label>
                  <div class="space-y-2">
                    <label
                      v-for="seg in audienceSegments"
                      :key="seg.id"
                      :class="[
                        'flex items-center justify-between p-3 rounded-xl border transition-all cursor-pointer',
                        isSegmentChecked(seg.id) ? 'border-[#E8192C] bg-red-50/40 text-slate-900' : 'border-slate-200 bg-slate-50 text-slate-600'
                      ]"
                    >
                      <div class="flex items-center gap-3">
                        <input
                          type="checkbox"
                          :value="seg.id"
                          :checked="isSegmentChecked(seg.id)"
                          @change="toggleSegment(seg.id)"
                          class="w-4 h-4 text-[#E8192C] rounded focus:ring-0 accent-[#E8192C]"
                        />
                        <span class="text-xs font-bold">{{ seg.name }}</span>
                      </div>
                      <span class="text-[11px] font-mono text-slate-400">~{{ seg.estimated_count }} user</span>
                    </label>
                  </div>
                </div>

                <!-- Manual Toggle -->
                <div class="pt-2">
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Trạng thái Bật/Tắt</label>
                  <button
                    type="button"
                    @click="formData.is_enabled = !formData.is_enabled"
                    :class="[
                      'w-full px-4 py-3 rounded-xl font-bold text-xs transition-all flex items-center justify-center gap-2 cursor-pointer',
                      formData.is_enabled ? 'bg-emerald-500 text-white shadow-sm' : 'bg-slate-200 text-slate-700'
                    ]"
                  >
                    <span class="material-symbols-outlined text-lg">{{ formData.is_enabled ? 'toggle_on' : 'toggle_off' }}</span>
                    <span>{{ formData.is_enabled ? 'ĐANG BẬT' : 'ĐANG TẮT' }}</span>
                  </button>
                </div>
              </div>

              <!-- Right Realtime Mobile Homepage Simulator (5 cols) -->
              <div class="lg:col-span-5 flex flex-col items-center justify-start bg-slate-100 rounded-3xl p-6 border border-slate-200">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-sm">smartphone</span>
                  <span>Xem trước Realtime trên App</span>
                </p>

                <!-- Phone Frame Mockup -->
                <div class="w-[320px] bg-white rounded-[36px] shadow-2xl border-4 border-slate-800 overflow-hidden flex flex-col text-slate-800">
                  <!-- Status Bar mockup -->
                  <div class="bg-slate-900 text-white px-6 pt-3 pb-1 flex justify-between items-center text-[10px] font-bold">
                    <span>9:41</span>
                    <div class="flex items-center gap-1 text-[8px]">
                      <span>5G</span>
                      <span class="material-symbols-outlined text-xs">battery_full</span>
                    </div>
                  </div>

                  <!-- Header mockup -->
                  <div class="bg-[#E8192C] text-white px-4 py-3 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                      <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center font-black text-xs">P</div>
                      <span class="font-bold text-sm">Picki</span>
                    </div>
                    <span class="material-symbols-outlined text-sm">notifications</span>
                  </div>

                  <!-- Homepage Content Simulation -->
                  <div class="p-3 bg-slate-50 space-y-3">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Bảng tin nổi bật</div>

                    <!-- Simulated Carousel Banner -->
                    <div class="relative rounded-2xl overflow-hidden shadow-md bg-slate-800 aspect-[2.25/1]">
                      <img
                        :src="imagePreview || getBannerUrl(formData.image_url)"
                        alt="Simulated banner"
                        class="w-full h-full object-cover"
                      />

                      <!-- Dots simulator -->
                      <div class="absolute bottom-2 left-0 right-0 flex justify-center gap-1 z-10">
                        <span class="w-4 h-1.5 rounded-full bg-[#E8192C]"></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-white/60"></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-white/60"></span>
                      </div>
                    </div>

                    <!-- Mockup Homepage items below banner -->
                    <div class="bg-white rounded-xl p-2.5 shadow-sm space-y-1.5">
                      <div class="h-2 bg-slate-200 rounded w-1/3"></div>
                      <div class="h-2 bg-slate-100 rounded w-2/3"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer Actions -->
            <div class="px-8 py-4 bg-slate-50 border-t border-slate-200 flex justify-end items-center gap-3">
              <button @click="showModal = false" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-sm hover:bg-slate-100 transition-colors cursor-pointer">
                Hủy bỏ
              </button>
              <button @click="saveBanner" :disabled="saving" class="px-6 py-2.5 rounded-xl bg-[#E8192C] hover:bg-[#c91223] text-white font-bold text-sm shadow-md transition-all active:scale-95 cursor-pointer flex items-center gap-2">
                <span v-if="saving" class="material-symbols-outlined text-sm animate-spin">sync</span>
                <span>{{ saving ? 'Đang lưu...' : 'Lưu Banner' }}</span>
              </button>
            </div>
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
import http, { get, post } from '@/utils/httpRequest';
import { toast } from 'vue3-toastify';

const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const isEditing = ref(false);

const activeFilter = ref('all');
const searchQuery = ref('');

const allBanners = ref([]);
const audienceSegments = ref([]);

const fileInput = ref(null);
const imageFile = ref(null);
const imagePreview = ref(null);

const draggedIndex = ref(null);
const dragOverIndex = ref(null);

const formData = ref({
  id: null,
  internal_name: '',
  title: '',
  subtitle: '',
  image_url: '',
  link_type: 'none',
  link_value: '',
  start_date: new Date().toISOString().split('T')[0],
  end_date: new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0],
  audience_segment_ids: ['ALL'],
  display_order: 1,
  is_enabled: true,
  status_badge: 'live',
  days_remaining: null,
});

const stats = computed(() => {
  return {
    live: allBanners.value.filter(b => b.status_badge === 'live').length,
    expiring: allBanners.value.filter(b => b.status_badge === 'expiring').length,
    scheduled: allBanners.value.filter(b => b.status_badge === 'scheduled').length,
    ended: allBanners.value.filter(b => b.status_badge === 'expired' || b.status_badge === 'disabled').length,
  };
});

const filterTabs = computed(() => [
  { id: 'all', label: 'Tất cả', count: allBanners.value.length },
  { id: 'live', label: 'Đang chạy', count: stats.value.live },
  { id: 'expiring', label: 'Sắp hết hạn', count: stats.value.expiring },
  { id: 'scheduled', label: 'Lên lịch', count: stats.value.scheduled },
  { id: 'ended', label: 'Đã kết thúc', count: stats.value.ended },
]);

const filteredBanners = computed(() => {
  return allBanners.value.filter(b => {
    if (activeFilter.value === 'live' && b.status_badge !== 'live') return false;
    if (activeFilter.value === 'expiring' && b.status_badge !== 'expiring') return false;
    if (activeFilter.value === 'scheduled' && b.status_badge !== 'scheduled') return false;
    if (activeFilter.value === 'ended' && b.status_badge !== 'expired' && b.status_badge !== 'disabled') return false;

    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase();
      const matchName = (b.internal_name || '').toLowerCase().includes(q);
      const matchLink = (b.link_value || '').toLowerCase().includes(q);
      return matchName || matchLink;
    }

    return true;
  });
});

const activeSectionBanners = computed(() => {
  return filteredBanners.value.filter(b => b.status_badge !== 'expired' && b.status_badge !== 'disabled');
});

const endedSectionBanners = computed(() => {
  return filteredBanners.value.filter(b => b.status_badge === 'expired' || b.status_badge === 'disabled');
});

const getBannerUrl = (path) => {
  if (!path) return 'https://images.unsplash.com/photo-1614632537423-1e6c2e7e0aab?w=600&h=260&fit=crop';
  if (path.startsWith('http://') || path.startsWith('https://')) return path;
  return `/storage/${path}`;
};

const formatDate = (d) => {
  if (!d) return '-';
  const parts = d.split('-');
  if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
  return d;
};

const formatSegments = (segs) => {
  if (!segs || segs.length === 0 || segs.includes('ALL')) return 'Tất cả user';
  const names = segs.map(id => {
    const found = audienceSegments.value.find(s => s.id === id);
    return found ? found.name : id;
  });
  return names.join(', ');
};

const getBadgeClass = (badge) => {
  switch (badge) {
    case 'live': return 'bg-emerald-100 text-emerald-800';
    case 'expiring': return 'bg-amber-100 text-amber-800';
    case 'scheduled': return 'bg-blue-100 text-blue-800';
    case 'expired': return 'bg-slate-200 text-slate-700';
    case 'disabled': return 'bg-slate-200 text-slate-600';
    default: return 'bg-slate-200 text-slate-700';
  }
};

const getBadgeLabel = (badge, daysLeft) => {
  switch (badge) {
    case 'live': return '● Đang chạy';
    case 'expiring': return `⚠ Sắp hết hạn (${daysLeft ?? '<5'} ngày)`;
    case 'scheduled': return '🕒 Lên lịch';
    case 'expired': return 'Hết hạn';
    case 'disabled': return 'Tắt thủ công';
    default: return 'Không hoạt động';
  }
};

const fetchBanners = async () => {
  loading.value = true;
  try {
    const res = await get('/admin/banners');
    if (res.data && (res.data.status === 'success' || res.data.status === true) && res.data.data) {
      const active = res.data.data.active_banners || [];
      const ended = res.data.data.ended_banners || [];
      allBanners.value = [...active, ...ended];
      audienceSegments.value = res.data.data.audience_segments || [];
    }
  } catch (e) {
    console.error('Lỗi khi tải danh sách banner:', e);
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  isEditing.value = false;
  imageFile.value = null;
  imagePreview.value = null;
  formData.value = {
    id: null,
    internal_name: 'Banner quảng cáo #' + (allBanners.value.length + 1),
    title: '',
    subtitle: '',
    image_url: '',
    link_type: 'none',
    link_value: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0],
    audience_segment_ids: ['ALL'],
    display_order: allBanners.value.length + 1,
    is_enabled: true,
    status_badge: 'live',
    days_remaining: null,
  };
  showModal.value = true;
};

const openEditModal = (banner) => {
  isEditing.value = true;
  imageFile.value = null;
  imagePreview.value = null;
  formData.value = {
    ...banner,
    audience_segment_ids: Array.isArray(banner.audience_segment_ids) ? [...banner.audience_segment_ids] : ['ALL'],
  };
  showModal.value = true;
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

const isSegmentChecked = (segId) => {
  return formData.value.audience_segment_ids.includes(segId);
};

const toggleSegment = (segId) => {
  const idx = formData.value.audience_segment_ids.indexOf(segId);
  if (idx > -1) {
    if (formData.value.audience_segment_ids.length > 1) {
      formData.value.audience_segment_ids.splice(idx, 1);
    }
  } else {
    formData.value.audience_segment_ids.push(segId);
  }
};

const toggleEnabled = async (banner) => {
  const newStatus = !banner.is_enabled;
  banner.is_enabled = newStatus;
  try {
    const data = new FormData();
    data.append('is_enabled', newStatus ? 1 : 0);
    await post(`/admin/banners/${banner.id}`, data);
    toast.success(newStatus ? 'Đã bật banner!' : 'Đã tắt banner!');
    await fetchBanners();
  } catch (e) {
    console.error('Lỗi toggle trạng thái:', e);
  }
};

// Drag and Drop handlers
const onDragStart = (idx, e) => {
  draggedIndex.value = idx;
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move';
  }
};

const onDragOver = (idx, e) => {
  dragOverIndex.value = idx;
};

const onDrop = async (dropIdx, e) => {
  if (draggedIndex.value === null || draggedIndex.value === dropIdx) {
    draggedIndex.value = null;
    dragOverIndex.value = null;
    return;
  }

  const list = [...activeSectionBanners.value];
  const [draggedItem] = list.splice(draggedIndex.value, 1);
  list.splice(dropIdx, 0, draggedItem);

  draggedIndex.value = null;
  dragOverIndex.value = null;

  // Build payload
  const orders = list.map((b, index) => ({
    id: b.id,
    display_order: index + 1,
  }));

  try {
    await post('/admin/banners/reorder', { orders });
    toast.success('Đã cập nhật vị trí carousel!');
    await fetchBanners();
  } catch (err) {
    console.error('Lỗi khi lưu thứ tự kéo thả:', err);
  }
};

const onDragEnd = () => {
  draggedIndex.value = null;
  dragOverIndex.value = null;
};

const moveOrder = async (banner, direction) => {
  const idx = activeSectionBanners.value.findIndex(b => b.id === banner.id);
  if (idx < 0) return;

  const targetIdx = direction === 'up' ? idx - 1 : idx + 1;
  if (targetIdx < 0 || targetIdx >= activeSectionBanners.value.length) return;

  const list = [...activeSectionBanners.value];
  const temp = list[idx];
  list[idx] = list[targetIdx];
  list[targetIdx] = temp;

  const orders = list.map((b, index) => ({
    id: b.id,
    display_order: index + 1,
  }));

  try {
    await post('/admin/banners/reorder', { orders });
    toast.success('Đã cập nhật vị trí carousel!');
    await fetchBanners();
  } catch (e) {
    console.error('Lỗi khi đổi thứ tự:', e);
  }
};

const saveBanner = async () => {
  if (!formData.value.internal_name) {
    formData.value.internal_name = 'Banner mới #' + (allBanners.value.length + 1);
  }

  saving.value = true;
  try {
    const data = new FormData();
    data.append('internal_name', formData.value.internal_name);
    data.append('link_type', formData.value.link_type);
    data.append('link_value', formData.value.link_value || '');
    data.append('start_date', formData.value.start_date);
    data.append('end_date', formData.value.end_date);
    data.append('display_order', formData.value.display_order || (allBanners.value.length + 1));
    data.append('is_enabled', formData.value.is_enabled ? 1 : 0);

    formData.value.audience_segment_ids.forEach((seg, i) => {
      data.append(`audience_segment_ids[${i}]`, seg);
    });

    if (imageFile.value) {
      data.append('image', imageFile.value);
    } else if (formData.value.image_url) {
      data.append('image_url', formData.value.image_url);
    }

    let url = '/admin/banners';
    if (isEditing.value) {
      url = `/admin/banners/${formData.value.id}`;
    }

    const res = await post(url, data);

    if (res.data && (res.data.status === 'success' || res.data.status === true)) {
      toast.success(isEditing.value ? 'Cập nhật banner thành công!' : 'Tạo banner mới thành công!');
      showModal.value = false;
      await fetchBanners();
    } else {
      toast.error(res.data?.message || 'Có lỗi xảy ra khi lưu banner');
    }
  } catch (e) {
    console.error('Lỗi khi lưu banner:', e);
    toast.error(e.response?.data?.message || 'Không thể lưu banner');
  } finally {
    saving.value = false;
  }
};

const confirmDelete = async (banner) => {
  if (!confirm(`Bạn có chắc chắn muốn xóa banner "${banner.internal_name}"?`)) {
    return;
  }

  try {
    await http.delete(`/admin/banners/${banner.id}`);
    toast.success('Đã xóa banner');
    await fetchBanners();
  } catch (e) {
    console.error('Lỗi khi xóa banner:', e);
  }
};

onMounted(() => {
  fetchBanners();
});
</script>

<style scoped>
.font-body { font-family: 'Manrope', sans-serif; }
.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.custom-scrollbar::-webkit-scrollbar {
  height: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
</style>
