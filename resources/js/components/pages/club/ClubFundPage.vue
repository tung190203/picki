<template>
    <div class="m-4 max-w-8xl rounded-md flex flex-col relative">
        <ClubFundSkeleton v-if="isInitialLoading" />

        <template v-else>
            <!-- Header Section -->
            <div class="bg-club-default text-white rounded-[16px] shadow-lg p-8 relative overflow-hidden flex flex-col justify-between"
                :style="{ backgroundImage: `url(${Background})` }">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center space-x-3">
                        <ArrowLeftIcon class="w-6 h-6 cursor-pointer text-white" @click="goBack" />
                        <p class="text-2xl font-semibold">Thu Chi</p>
                    </div>
                    <div class="flex items-center space-x-1 relative rounded-[8px] bg-[#3E414C] p-3 cursor-pointer" @click="showQRModal = true">
                        <QRCodeIcon class="w-6 h-6 text-white" />
                    </div>
                </div>
                
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-8 lg:gap-0">
                    <div>
                        <p class="text-sm font-medium text-white opacity-60 mb-1">Quỹ chung hiện có</p>
                        <div class="flex items-baseline space-x-2">
                            <p class="text-4xl md:text-5xl lg:text-[64px] font-bold leading-tight">{{ formatCurrency(fundOverview.balance) }}</p>
                            <p class="text-xs font-semibold text-[#00B377]">VND</p>
                        </div>
                    </div>
                    
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4 lg:gap-6">
                        <div class="flex flex-col items-start bg-[#3E414C]/80 backdrop-blur-sm p-6 rounded-2xl w-full md:w-[280px] space-y-2 border border-white/10">
                            <div class="flex items-center space-x-2 text-[#4ADE80] font-semibold text-sm">
                                <ArrowDownIcon class="w-4 h-4" />
                                <p>Thu tháng này</p>
                            </div>
                            <div class="flex items-baseline space-x-1">
                                <p class="text-2xl md:text-3xl font-bold">{{ formatSpecialCurrency(fundOverview.total_income) }}</p>
                            </div>
                        </div>
                        
                        <div class="flex flex-col items-start bg-[#3E414C]/80 backdrop-blur-sm p-6 rounded-2xl w-full md:w-[280px] space-y-2 border border-white/10">
                            <div class="flex items-center space-x-2 text-[#F87171] font-semibold text-sm">
                                <ArrowUpIcon class="w-4 h-4" />
                                <p>Chi tháng này</p>
                            </div>
                            <div class="flex items-baseline space-x-1">
                                <p class="text-2xl md:text-3xl font-bold">{{ formatSpecialCurrency(fundOverview.total_expense) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <!-- Content Grid: Admin / Secretary / Treasurer -->
            <div class="grid grid-cols-12 gap-6 py-6 flex-1" v-if="hasAnyRole(['admin', 'secretary', 'treasurer'])">
                <!-- Left Column: ĐỢT THU & XÁC NHẬN -->
                <div class="col-span-12 lg:col-span-4 flex flex-col">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col">
                        <template v-if="fundCollections.length > 0">
                            <div v-for="collection in fundCollections.slice(0, 1)" :key="collection.id" class="flex flex-col">
                                <!-- ĐỢT THU ĐANG MỞ -->
                                <div class="p-6 border-b border-gray-100">
                                    <div class="flex items-center justify-between mb-6">
                                        <h2 class="text-[#838799] font-bold text-[13px] tracking-wider uppercase">ĐỢT THU ĐANG MỞ</h2>
                                        <button 
                                            @click="handleOpenCollectionDetail(collection.id)"
                                            class="text-[#D72D36] text-[13px] font-bold"
                                        >
                                            Xem chi tiết
                                        </button>
                                    </div>

                                    <div class="space-y-4">
                                        <div class="flex justify-between items-start">
                                            <h3 class="text-[17px] font-bold text-[#1F2937] flex-1 min-w-[120px] break-words line-clamp-1 mr-4" v-tooltip="collection.title">{{ collection.title }}</h3>
                                            <div class="text-right flex-shrink-0 max-w-[50%]">
                                                <p class="text-[17px] font-bold text-[#1F2937] truncate whitespace-nowrap" v-tooltip="formatCurrency(collection.amount_per_member) + ' ' + collection.currency">
                                                    {{ formatCurrency(collection.amount_per_member) + ' ' + collection.currency }}
                                                </p>
                                                <p class="text-[12px] text-[#838799]">/người</p>
                                            </div>
                                        </div>
                                        
                                        <p class="text-[13px] text-[#838799]">Hạn chót: {{ formatDatetime(collection.end_date, '/') }}</p>

                                        <div class="space-y-2">
                                            <div class="w-full h-2 bg-[#F2F3F5] rounded-full overflow-hidden">
                                                <div class="h-full bg-[#D72D36] rounded-full" :style="{ width: (collection.progress_percentage) + '%' }"></div>
                                            </div>
                                            <div class="flex justify-between items-center text-[13px]">
                                                <span class="text-[#1F2937] font-medium">Đã thu: {{ collection.confirmed_count }}/{{ collection.assigned_members_count }} người</span>
                                                <span class="text-[#1F2937] font-bold">{{ Number(collection.progress_percentage).toFixed(2) }}%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cần xác nhận -->
                                <div class="flex flex-col overflow-hidden p-6" v-if="collection.pending_count > 0">
                                    <div class="mb-4">
                                        <div class="flex items-center space-x-2 text-[#838799] font-bold text-[15px] tracking-wider">
                                            <span>Cần xác nhận</span>
                                            <span>•</span>
                                            <span class="text-[#D72D36]">({{ collection.pending_count }})</span>
                                        </div>
                                    </div>

                                    <div class="overflow-y-auto">
                                        <div v-for="(tr, idx) in collection.pending_contributions" :key="idx" class="flex items-center justify-between py-4 border-b border-[#F2F3F5] last:border-b-0">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 rounded-full bg-gray-100 overflow-hidden">
                                                    <img :src="tr.user?.avatar_url || `https://ui-avatars.com/api/?name=${tr.user?.full_name}&background=random`" alt="avatar" />
                                                </div>
                                                <span class="font-bold text-[#1F2937] text-sm">{{ tr.user?.full_name }}</span>
                                            </div>
                                            <button 
                                                @click="handleApproveContribution(collection.id, tr.id)"
                                                class="bg-[#10B981] text-white px-4 py-1.5 rounded-[4px] text-[9px] font-bold hover:bg-[#059669] transition-colors"
                                            >
                                                Duyệt
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center justify-center p-6 text-center">
                                    <p class="text-[#838799] text-[13px]">Không có giao dịch chờ duyệt</p>
                                </div>
                            </div>
                        </template>
                        <div v-else class="flex flex-col items-center justify-center p-12 text-center">
                            <p class="text-[#838799] text-sm font-medium">Hiện không có khoản thu nào</p>
                        </div>
                    </div>
                </div>

                <!-- Right Column: LỊCH SỬ THU CHI -->
                <div class="col-span-12 lg:col-span-8 flex flex-col h-full">
                    <div class="bg-white rounded-[24px] shadow-sm border border-gray-50 flex-1 flex flex-col">
                        <!-- Section Header -->
                        <div class="px-6 pt-6 pb-2 mb-2 flex items-center justify-between">
                            <div class="text-[#838799] font-bold text-[15px] tracking-wider uppercase">
                                Lịch sử thu chi
                            </div>
                            <button class="text-[#D72D36] text-[13px] font-bold">Xem tất cả</button>
                        </div>
                        
                        <!-- Search & Filter -->
                        <div class="px-6 py-4">
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                                    <MagnifyingGlassIcon class="w-5 h-5 text-[#838799]" />
                                </div>
                                <input 
                                    v-model="searchQuery"
                                    type="text" 
                                    placeholder="Tìm kiếm lịch sử giao dịch"
                                    @input="handleSearch"
                                    class="w-full bg-[#EDEEF2] border-none rounded-md py-3.5 pl-12 pr-12 text-sm focus:ring-0 placeholder:text-[#9EA2B3] placeholder:font-normal"
                                />
                                <div class="absolute inset-y-0 right-4 flex items-center">
                                    <div class="relative">
                                        <FunnelIcon 
                                            class="w-5 h-5 text-[#838799] cursor-pointer hover:text-[#D72D36] transition-colors" 
                                            @click.stop="showFilterDropdown = !showFilterDropdown"
                                        />
                                        <div v-if="activeFilterCount > 0" class="absolute -top-1 -right-1 w-2 h-2 bg-[#D72D36] rounded-full"></div>
                                        
                                        <!-- Filter Dropdown -->
                                        <Transition name="fade">
                                            <div v-if="showFilterDropdown" 
                                                class="absolute right-0 top-full mt-4 w-[650px] bg-white rounded-2xl shadow-2xl border border-gray-100 z-[100] p-6 text-left overflow-visible"
                                                v-click-outside="() => showFilterDropdown = false">
                                                <div class="flex items-center justify-between mb-6">
                                                    <h3 class="text-lg font-bold text-[#1F2937]">Bộ lọc giao dịch</h3>
                                                    <button @click="clearFilters" class="text-sm text-[#838799] hover:text-[#D72D36] font-medium transition-colors">Xoá lọc</button>
                                                </div>

                                                <div class="space-y-6 max-h-[80vh] overflow-y-auto pr-2 custom-scrollbar">
                                                    <!-- Direction -->
                                                    <div>
                                                        <label class="block text-xs font-bold text-[#838799] uppercase tracking-wider mb-3">Loại giao dịch</label>
                                                        <div class="flex gap-2">
                                                            <button 
                                                                v-for="opt in [{v: '', l: 'Tất cả'}, {v: 'in', l: 'Thu'}, {v: 'out', l: 'Chi'}]"
                                                                :key="opt.v"
                                                                @click="filters.direction = opt.v"
                                                                :class="[
                                                                    'flex-1 py-2 px-3 rounded-lg text-sm font-bold transition-all border',
                                                                    filters.direction === opt.v 
                                                                        ? 'bg-[#D72D36] text-white border-[#D72D36] shadow-sm' 
                                                                        : 'bg-gray-50 text-[#3E414C] border-gray-100 hover:border-gray-300'
                                                                ]"
                                                            >
                                                                {{ opt.l }}
                                                            </button>
                                                            </div>
                                                        </div>

                                                    <!-- Source Type -->
                                                    <div>
                                                        <label class="block text-xs font-bold text-[#838799] uppercase tracking-wider mb-3">Nguồn tiền</label>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <label 
                                                                v-for="opt in sourceTypeOptions" 
                                                                :key="opt.value"
                                                                class="flex items-center space-x-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors group"
                                                            >
                                                                <input 
                                                                    type="checkbox" 
                                                                    v-model="filters.source_types" 
                                                                    :value="opt.value"
                                                                    class="w-4 h-4 text-[#D72D36] border-gray-300 rounded focus:ring-[#D72D36] accent-[#D72D36]"
                                                                />
                                                                <span class="text-sm font-medium text-[#3E414C] group-hover:text-[#1F2937]">{{ opt.label }}</span>
                                                            </label>
                                                            </div>
                                                        </div>

                                                    <!-- Status & Date Range row -->
                                                    <div class="grid grid-cols-2 gap-6">
                                                        <!-- Status -->
                                                        <div>
                                                        <label class="block text-xs font-bold text-[#838799] uppercase tracking-wider mb-3">Trạng thái</label>
                                                        <div class="grid grid-cols-1 gap-2">
                                                            <label 
                                                                v-for="opt in statusOptions" 
                                                                :key="opt.value"
                                                                class="flex items-center space-x-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors group"
                                                            >
                                                                <input 
                                                                    type="checkbox" 
                                                                    v-model="filters.statuses" 
                                                                    :value="opt.value"
                                                                    class="w-4 h-4 text-[#D72D36] border-gray-300 rounded focus:ring-[#D72D36] accent-[#D72D36]"
                                                                />
                                                                <span class="text-sm font-medium text-[#3E414C] group-hover:text-[#1F2937]">{{ opt.label }}</span>
                                                            </label>
                                                            </div>
                                                        </div>

                                                    <!-- Date Range -->
                                                    <div>
                                                        <label class="block text-xs font-bold text-[#838799] uppercase tracking-wider mb-3">Khoảng thời gian</label>
                                                        <div class="space-y-3">
                                                            <div class="relative">
                                                                <VueDatePicker 
                                                                    v-model="filters.date_from"
                                                                    :enable-time-picker="false"
                                                                    auto-apply
                                                                    placeholder="Từ ngày"
                                                                    text-input
                                                                    :format="'dd/MM/yyyy'"
                                                                    class="v-datepicker-custom"
                                                                />
                                                            </div>
                                                            <div class="relative">
                                                                <VueDatePicker 
                                                                    v-model="filters.date_to"
                                                                    :enable-time-picker="false"
                                                                    auto-apply
                                                                    placeholder="Đến ngày"
                                                                    text-input
                                                                    :format="'dd/MM/yyyy'"
                                                                    class="v-datepicker-custom"
                                                                />
                                                            </div>
                                                            </div>
                                                        </div>
                                                </div>

                                                </div>

                                                <div class="mt-8">
                                                    <button 
                                                        @click="applyFilters"
                                                        class="w-full py-4 bg-[#2D3139] text-white rounded-xl font-bold hover:bg-black transition-all shadow-lg active:scale-[0.98]"
                                                    >
                                                        Áp dụng bộ lọc
                                                    </button>
                                                </div>
                                            </div>
                                        </Transition>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- History List -->
                        <div class="flex-1 overflow-y-auto px-6">
                            <!-- Transaction Item -->
                            <div v-if="transactions.length === 0" class="text-center py-12">
                                <p class="text-[#838799] text-sm">Chưa có giao dịch nào</p>
                            </div>
                            <div class="flex items-center justify-between py-5 border-b border-[#F2F3F5] hover:bg-gray-50/30 transition-colors cursor-pointer last:border-b-0"
                                v-for="(item, index) in transactions" :key="index">
                                <div class="flex items-center space-x-4">
                                    <div>
                                        <p class="font-bold text-[#1F2937] text-[15px] whitespace-pre-line">{{ item.description || 'Chưa có mô tả' }}</p>
                                        <p class="text-[12px] text-[#838799] font-normal mt-0.5">
                                            {{ item.source_type === 'income' ? 'Thu' : 'Chi' }} ngày {{ formatDatetime(item.created_at, '/') }}
                                        </p>
                                    </div>
                                </div>
                                <span :class="['font-bold text-[16px]', item.source_type === 'expense' ? 'text-[#D72D36]' : 'text-[#10B981]']">
                                   <span>{{ item.source_type === 'expense' ? '-' : '+' }}</span> {{ formatCurrency(item.amount) }}
                                </span>
                            </div>

                            <!-- Pagination -->
                            <div class="px-6 pb-6">
                                <Pagination 
                                    :meta="{ current_page: currentPage, last_page: lastPage }" 
                                    @page-change="handlePageChange" 
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Grid: Member / Manager -->
            <div class="grid grid-cols-12 gap-6 py-6 flex-1" v-else>
                <!-- Left Column: CẦN THANH TOÁN -->
                <div class="col-span-12 lg:col-span-4 flex flex-col" v-if="hasAnyRole(['manager', 'member'])">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col">
                        <!-- Section Header -->
                        <div class="p-6 pb-4">
                            <div class="flex items-center space-x-2 text-[#838799] font-semibold tracking-wide">
                                <span>CẦN THANH TOÁN</span>
                                <span class="text-[#838799]">•</span>
                                <span class="text-[#D72D36] tracking-normal" v-if="myTransactions.need_payment.length > 0">({{ myTransactions.need_payment.length }})</span>
                            </div>
                        </div>

                        <!-- Items Container -->
                        <div class="flex flex-col flex-1 overflow-hidden">
                            <!-- Need Payment List -->
                            <div class="max-h-[350px] overflow-y-auto overflow-x-hidden px-6 pb-4 space-y-6 custom-scrollbar border-b border-gray-100">
                                <template v-if="myTransactions.need_payment && myTransactions.need_payment.length > 0">
                                    <!-- Actionable Payment Item -->
                                    <div class="space-y-4 border-b border-[#dcdee6] pb-4" v-for="(item, index) in myTransactions.need_payment" :key="index">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h3 class="font-semibold text-[#1F2937] whitespace-pre-line">{{ item.description }}</h3>
                                                <div class="flex items-center space-x-1.5 mt-1 text-[#D72D36]">
                                                    <CalendarIcon class="w-4 h-4" />
                                                    <span class="text-sm font-semibold">Hạn chót: {{ formatDatetime(item.end_date, '/') }}</span>
                                                </div>
                                            </div>
                                            <span class="text-[#D72D36] font-semibold">{{ formatCurrency(item.amount_due) + 'đ' }}</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <button 
                                                @click="handleViewDetail(item)"
                                                class="py-2.5 px-4 bg-[#F2F3F5] text-[#2D3139] rounded-[4px] font-bold text-sm hover:bg-gray-200 transition-colors"
                                            >
                                                Chi tiết
                                            </button>
                                            <button 
                                                @click="handlePayNow(item)"
                                                class="py-2.5 px-4 bg-[#2D3139] text-white rounded-[4px] font-bold text-sm hover:bg-black transition-colors"
                                            >
                                                Thanh toán ngay
                                            </button>
                                        </div>
                                    </div>
                                </template>
                                <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                                    <p class="text-[#838799] text-sm font-medium">Không có khoản thu cần thanh toán</p>
                                </div>
                            </div>

                            <!-- Pending Approval Section -->
                            <div class="flex flex-col flex-1 overflow-hidden mt-2">
                                <div class="max-h-[300px] overflow-y-auto overflow-x-hidden px-6 pb-6 space-y-4 custom-scrollbar">
                                    <template v-if="myTransactions.pending && myTransactions.pending.length > 0">
                                        <!-- Pending Approval Card (Sample) -->
                                        <div class="bg-[#FDF2E2] rounded-lg p-4 border-l-[3px] border-[#F0AC3A] relative flex flex-col space-y-4 shadow-sm" v-for="(item, index) in myTransactions.pending" :key="index">
                                            <div class="flex justify-between items-start">
                                                <h3 class="font-semibold text-[#1F2937] whitespace-pre-line">{{ item.description }}</h3>
                                                <span class="text-[#1F2937] font-semibold">{{ formatCurrency(item.my_contribution?.amount) + 'đ' }}</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center text-[12px] text-[#A6753A] font-medium">
                                                    <span>Đã chuyển khoản</span>
                                                    <span class="mx-1.5 text-[#A6753A]">•</span>
                                                    <span>Chờ admin xác nhận</span>
                                                </div>
                                                <div class="bg-[#F0A31D] text-white px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider">Chờ duyệt</div>
                                            </div>
                                        </div>
                                    </template>
                                    <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                                        <p class="text-[#838799] text-sm font-medium">Không có khoản thu nào cần chờ xác nhận</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: LỊCH SỬ CỦA TÔI -->
                <div :class="[
                    'flex flex-col h-full',
                    hasAnyRole(['manager', 'member']) ? 'col-span-12 lg:col-span-8' : 'col-span-12'
                ]">
                    <div class="bg-white rounded-[24px] shadow-sm border border-gray-50 flex-1 flex flex-col">
                        <!-- Section Header -->
                        <div class="px-6 pt-6 pb-2">
                            <div class="text-[#838799] font-bold text-[15px] tracking-wide uppercase">
                                Lịch sử của tôi
                            </div>
                        </div>
                        
                        <!-- Search & Filter -->
                        <div class="px-6 py-4">
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                                    <MagnifyingGlassIcon class="w-5 h-5 text-[#838799]" />
                                </div>
                                <input 
                                    v-model="searchQuery"
                                    type="text" 
                                    placeholder="Tìm kiếm lịch sử giao dịch"
                                    @input="handleSearch"
                                    class="w-full bg-[#EDEEF2] border-none rounded-md py-3.5 pl-12 pr-12 text-sm focus:ring-0 placeholder:text-[#9EA2B3] placeholder:font-normal"
                                />
                                <div class="absolute inset-y-0 right-4 flex items-center">
                                    <div class="relative">
                                        <FunnelIcon 
                                            class="w-5 h-5 text-[#838799] cursor-pointer hover:text-[#D72D36] transition-colors" 
                                            @click.stop="showFilterDropdown = !showFilterDropdown"
                                        />
                                        <div v-if="activeFilterCount > 0" class="absolute -top-1 -right-1 w-2 h-2 bg-[#D72D36] rounded-full"></div>
                                        
                                        <!-- Filter Dropdown -->
                                        <Transition name="fade">
                                            <div v-if="showFilterDropdown" 
                                                class="absolute right-0 top-full mt-4 w-[650px] bg-white rounded-2xl shadow-2xl border border-gray-100 z-[100] p-6 text-left overflow-visible"
                                                v-click-outside="() => showFilterDropdown = false">
                                                <div class="flex items-center justify-between mb-6">
                                                    <h3 class="text-lg font-bold text-[#1F2937]">Bộ lọc giao dịch</h3>
                                                    <button @click="clearFilters" class="text-sm text-[#838799] hover:text-[#D72D36] font-medium transition-colors">Xoá lọc</button>
                                                </div>

                                                <div class="space-y-6 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                                                    <!-- Direction -->
                                                    <div>
                                                        <label class="block text-xs font-bold text-[#838799] uppercase tracking-wider mb-3">Loại giao dịch</label>
                                                        <div class="flex gap-2">
                                                            <button 
                                                                v-for="opt in [{v: '', l: 'Tất cả'}, {v: 'in', l: 'Thu'}, {v: 'out', l: 'Chi'}]"
                                                                :key="opt.v"
                                                                @click="filters.direction = opt.v"
                                                                :class="[
                                                                    'flex-1 py-2 px-3 rounded-lg text-sm font-bold transition-all border',
                                                                    filters.direction === opt.v 
                                                                        ? 'bg-[#D72D36] text-white border-[#D72D36] shadow-sm' 
                                                                        : 'bg-gray-50 text-[#3E414C] border-gray-100 hover:border-gray-300'
                                                                ]"
                                                            >
                                                                {{ opt.l }}
                                                            </button>
                                                            </div>
                                                        </div>

                                                    <!-- Source Type -->
                                                    <div>
                                                        <label class="block text-xs font-bold text-[#838799] uppercase tracking-wider mb-3">Nguồn tiền</label>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <label 
                                                                v-for="opt in sourceTypeOptions" 
                                                                :key="opt.value"
                                                                class="flex items-center space-x-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors group"
                                                            >
                                                                <input 
                                                                    type="checkbox" 
                                                                    v-model="filters.source_types" 
                                                                    :value="opt.value"
                                                                    class="w-4 h-4 text-[#D72D36] border-gray-300 rounded focus:ring-[#D72D36] accent-[#D72D36]"
                                                                />
                                                                <span class="text-sm font-medium text-[#3E414C] group-hover:text-[#1F2937]">{{ opt.label }}</span>
                                                            </label>
                                                            </div>
                                                        </div>

                                                    <!-- Status & Date Range row -->
                                                    <div class="grid grid-cols-2 gap-6">
                                                        <!-- Status -->
                                                        <div>
                                                        <label class="block text-xs font-bold text-[#838799] uppercase tracking-wider mb-3">Trạng thái</label>
                                                        <div class="grid grid-cols-1 gap-2">
                                                            <label 
                                                                v-for="opt in statusOptions" 
                                                                :key="opt.value"
                                                                class="flex items-center space-x-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors group"
                                                            >
                                                                <input 
                                                                    type="checkbox" 
                                                                    v-model="filters.statuses" 
                                                                    :value="opt.value"
                                                                    class="w-4 h-4 text-[#D72D36] border-gray-300 rounded focus:ring-[#D72D36] accent-[#D72D36]"
                                                                />
                                                                <span class="text-sm font-medium text-[#3E414C] group-hover:text-[#1F2937]">{{ opt.label }}</span>
                                                            </label>
                                                            </div>
                                                        </div>

                                                    <!-- Date Range -->
                                                    <div>
                                                        <label class="block text-xs font-bold text-[#838799] uppercase tracking-wider mb-3">Khoảng thời gian</label>
                                                        <div class="space-y-3">
                                                            <div class="relative">
                                                                <VueDatePicker 
                                                                    v-model="filters.date_from"
                                                                    :enable-time-picker="false"
                                                                    auto-apply
                                                                    placeholder="Từ ngày"
                                                                    text-input
                                                                    :format="'dd/MM/yyyy'"
                                                                    class="v-datepicker-custom"
                                                                />
                                                            </div>
                                                            <div class="relative">
                                                                <VueDatePicker 
                                                                    v-model="filters.date_to"
                                                                    :enable-time-picker="false"
                                                                    auto-apply
                                                                    placeholder="Đến ngày"
                                                                    text-input
                                                                    :format="'dd/MM/yyyy'"
                                                                    class="v-datepicker-custom"
                                                                />
                                                            </div>
                                                            </div>
                                                        </div>
                                                </div>

                                                </div>

                                                <div class="mt-8">
                                                    <button 
                                                        @click="applyFilters"
                                                        class="w-full py-4 bg-[#2D3139] text-white rounded-xl font-bold hover:bg-black transition-all shadow-lg active:scale-[0.98]"
                                                    >
                                                        Áp dụng bộ lọc
                                                    </button>
                                                </div>
                                            </div>
                                        </Transition>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- History List -->
                        <div class="flex-1 overflow-y-auto">
                            <div v-if="transactions.length === 0" class="text-center py-12">
                                <p class="text-[#838799] text-sm">Chưa có giao dịch nào</p>
                            </div>
                            <div v-for="(item, i) in transactions" :key="i" 
                                class="flex items-center justify-between mx-6 py-5 border-b border-[#dcdee6] hover:bg-gray-50/30 transition-colors cursor-pointer last:border-b-0">
                                <div class="flex items-center space-x-4">
                                    <div>
                                        <p class="font-bold text-[#1F2937] text-[15px] whitespace-pre-line">{{ item.description || 'Chưa có mô tả' }}</p>
                                        <p class="text-[12px] text-[#10B981] font-normal mt-0.5">Hoàn tất: {{ formatDatetime(item.created_at, '/') }}</p>
                                    </div>
                                </div>
                                <span class="font-bold text-[#1F2937] text-[15px]">{{ formatCurrency(item.amount) + item.currency }}</span>
                            </div>

                            <!-- Pagination -->
                            <div class="px-6 pb-6">
                                <Pagination 
                                    :meta="{ current_page: currentPage, last_page: lastPage }" 
                                    @page-change="handlePageChange" 
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QR Code Modal -->
            <Transition name="fade">
                <div v-if="showQRModal" 
                    class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="showQRModal = false">
                    <Transition name="scale">
                        <div v-if="showQRModal" 
                            :class="[
                                'bg-white rounded-[24px] w-full transition-all duration-300 flex flex-col p-6 relative shadow-2xl overflow-hidden',
                                hasAnyRole(['admin', 'secretary', 'treasurer']) ? 'max-w-[850px] min-h-[600px] lg:p-8' : 'max-w-[390px] h-fit max-h-[90vh]'
                            ]">
                            <!-- Modal Close -->
                            <button 
                                @click="showQRModal = false"
                                class="absolute right-8 top-8 text-gray-400 hover:text-gray-600 transition-colors z-10">
                                <XMarkIcon class="w-7 h-7" />
                            </button>

                            <!-- Modal Header -->
                            <div class="mb-6 flex-shrink-0">
                                <h2 class="text-[22px] font-bold text-[#2D3139] mb-4">Mã QR</h2>
                            </div>

                            <div :class="['flex min-h-0 gap-10 flex-col', hasAnyRole(['admin', 'secretary', 'treasurer']) ? 'lg:flex-row' : '']">
                                <!-- Left Column: MÃ QR HIỆN CÓ -->
                                <div :class="[hasAnyRole(['admin', 'secretary', 'treasurer']) ? 'w-full lg:w-[45%] flex flex-col' : 'w-full flex flex-col min-h-0']">
                                    <!-- Section Subtitle -->
                                    <div class="mb-6 flex-shrink-0">
                                        <div class="flex items-center space-x-2 text-[#838799] text-sm font-bold tracking-wider uppercase">
                                            <span>MÃ QR CHUNG CỦA CLB</span>
                                        </div>
                                    </div>

                                    <!-- Single QR Display -->
                                    <div class="w-full overflow-hidden relative mb-6 min-h-0 flex-1">
                                        <div v-if="mainQrCode?.qr_code_url" class="bg-white border border-[#F2F3F5] rounded-[24px] p-6 h-full flex flex-col items-center shadow-sm">
                                            <div class="w-full aspect-[4/3] flex items-center justify-center overflow-hidden flex-shrink border border-gray-100 rounded-xl bg-[#F8FAFC] min-h-0 mb-5 relative group">
                                                <img :src="mainQrCode.qr_code_url" class="absolute inset-0 w-full h-full object-contain mix-blend-multiply p-2" />
                                            </div>
                                            <div class="text-center flex-shrink-0 w-full mb-5">
                                                <p class="text-[14px] text-[#3E414C] font-medium leading-relaxed max-h-[80px] overflow-y-auto whitespace-pre-line px-2">{{ mainQrCode.qr_note || 'Chưa có ghi chú cho mã QR này' }}</p>
                                            </div>
                                            <div class="flex items-center justify-center space-x-4 w-full mt-auto pt-4 border-t border-gray-100">
                                                <button @click="downloadQrCode" class="w-11 h-11 rounded-full bg-[#f1f5f9] flex items-center justify-center text-[#475569] hover:bg-[#e2e8f0] transition-colors" title="Tải xuống mã QR">
                                                    <ArrowDownTrayIcon class="w-5 h-5" />
                                                </button>
                                                <button v-if="hasAnyRole(['admin', 'secretary', 'treasurer'])" class="w-11 h-11 rounded-full bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-100 transition-colors" @click="confirmDeleteQR">
                                                    <TrashIcon class="w-5 h-5" />
                                                </button>
                                            </div>
                                        </div>
                                        <div v-else class="bg-[#F8FAFC] border-2 border-dashed border-gray-200 rounded-[24px] p-6 h-full flex flex-col items-center justify-center text-center">
                                            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-sm mb-4">
                                                <QRCodeIcon class="w-8 h-8 text-gray-400" />
                                            </div>
                                            <p class="text-[#64748B] font-medium text-[14px]">Chưa thiết lập mã QR chung</p>
                                            <p class="text-[#94A3B8] text-[12px] mt-1">Thêm mã QR để thành viên dễ dàng chuyển khoản</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Column: THÊM MÃ QR MỚI (Admin only) -->
                                <div v-if="hasAnyRole(['admin', 'secretary', 'treasurer'])" class="w-full lg:w-[55%] flex flex-col">
                                    <div class="mb-6">
                                        <h2 class="text-[14px] font-bold text-[#838799] tracking-wider uppercase">{{ mainQrCode?.qr_code_url ? 'CẬP NHẬT MÃ QR CHUNG' : 'THÊM MÃ QR CHUNG' }}</h2>
                                    </div>
                                    
                                    <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar">
                                        <div 
                                            class="w-full h-[180px] bg-white border-2 border-dashed border-gray-200 rounded-[12px] flex flex-col items-center justify-center cursor-pointer hover:border-[#D72D36] transition-colors mb-6 group relative overflow-hidden"
                                            @click="triggerFileInput"
                                        >
                                            <input 
                                                type="file" 
                                                ref="fileInput" 
                                                class="hidden" 
                                                accept="image/*" 
                                                @change="handleFileUpload"
                                            />
                                            <template v-if="previewImage">
                                                <img :src="previewImage" class="absolute inset-0 w-full h-full object-contain mix-blend-multiply p-2" />
                                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                    <button 
                                                        @click.stop="removePreview"
                                                        class="bg-white/20 hover:bg-white/40 p-2.5 rounded-full backdrop-blur-md transition-colors"
                                                    >
                                                        <TrashIcon class="w-6 h-6 text-white" />
                                                    </button>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="w-12 h-12 bg-[#F1F5F9] rounded-full flex items-center justify-center mb-3 group-hover:bg-[#FEE2E2] transition-colors">
                                                    <PhotoIcon class="w-6 h-6 text-[#64748B] group-hover:text-[#D72D36]" />
                                                </div>
                                                <p class="font-bold text-[14px] text-[#1E293B]">
                                                    {{ mainQrCode?.qr_code_url ? 'Nhấn để tải ảnh lên (tùy chọn)' : 'Nhấn để tải ảnh lên' }}
                                                </p>
                                                <p class="text-[12px] text-[#64748B] mt-1">
                                                    {{ mainQrCode?.qr_code_url ? 'Sẽ giữ nguyên ảnh cũ nếu không đổi' : 'PNG, JPG, GIF (Tối đa 5MB)' }}
                                                </p>
                                            </template>
                                        </div>

                                        <div class="space-y-6">
                                            <div>
                                                <div class="flex items-center justify-between mb-2">
                                                    <label class="block text-[13px] font-bold text-[#1F2937] uppercase tracking-wide">Nội dung ghi chú</label>
                                                    <span class="text-[11px] text-[#9CA3AF] font-medium">{{ newQRDescription.length }}/300</span>
                                                </div>
                                                <div class="relative">
                                                    <div class="absolute top-[18px] left-4 pointer-events-none">
                                                        <PencilSquareIcon class="w-[18px] h-[18px] text-[#9CA3AF]" />
                                                    </div>
                                                    <textarea 
                                                        v-model="newQRDescription"
                                                        rows="3"
                                                        placeholder="VD: Chuyển khoản quỹ chung CLB VPick..."
                                                        class="w-full bg-[#F8FAFC] border border-gray-100 rounded-[12px] py-4 pl-11 pr-4 text-[14px] focus:bg-white focus:ring-2 focus:ring-[#D72D36]/20 focus:border-[#D72D36] placeholder:text-[#9CA3AF] transition-all resize-none shadow-sm"
                                                    ></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button 
                                        @click="handleSaveQRCode"
                                        :disabled="isSubmittingQR"
                                        class="mx-auto w-full max-w-[280px] py-3.5 bg-[#D72D36] text-white rounded-[8px] font-bold text-[15px] hover:bg-[#b91c1c] active:scale-[0.98] transition-all mt-6 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed shadow-md shadow-red-500/20"
                                    >
                                        <template v-if="isSubmittingQR">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Đang lưu...
                                        </template>
                                        <template v-else>
                                            {{ mainQrCode?.qr_code_url ? 'Cập nhật mã QR' : 'Thêm mã QR' }}
                                        </template>
                                     </button>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>

            <!-- Create Fund Modal -->
            <ClubCreateFundModal 
                v-model:isOpen="showCreateFundModal"
                :club="club"
                :initialData="fundEditData"
                @submit="handleSubmitFundRevenue"
            />

            <!-- Create Expense Modal -->
            <ClubCreateExpenseModal 
                v-model:isOpen="showCreateExpenseModal"
                @submit="handleSubmitFundExpenses"
            />

            <!-- Delete Confirmation Modal -->
            <DeleteConfirmationModal
                v-model="showDeleteQRModal"
                title="Xác nhận xoá QR Code"
                message="Bạn có chắc chắn muốn xoá mã QR này không? Thao tác này không thể hoàn tác."
                confirmButtonText="Xoá ngay"
                @confirm="handleConfirmDelete"
            />

            <!-- Fund Collection Detail Modal -->
            <ClubCollectionDetailModal
                v-model:isOpen="showCollectionDetailModal"
                :clubId="clubId"
                :fundCollections="allCollections"
                :initialCollectionId="selectedCollectionId"
                :initialContributionId="selectedContributionId"
                :canManage="hasAnyRole(['admin', 'secretary', 'treasurer'])"
                @delete="handleDeleteCollection"
            />

            <!-- Delete Fund Collection Modal -->
            <DeleteConfirmationModal
                v-model="showDeleteCollectionModal"
                title="Xác nhận xoá đợt thu"
                message="Dữ liệu những thành viên đã đóng tiền hoặc đang chờ xác nhận cũng sẽ bị xóa. Bạn có chắc chắn muốn xóa khoản thu này?"
                confirmButtonText="Đồng ý"
                @confirm="handleConfirmDeleteCollection"
            />

            <!-- Submit Receipt Modal -->
            <ClubSubmitReceiptModal
                v-model:isOpen="showSubmitReceiptModal"
                :clubId="clubId"
                :collectionId="selectedCollectionIdForReceipt"
                :qrDetail="selectedQRDetail"
                :paymentItem="selectedPaymentItem"
                @success="handleReceiptSuccess"
            />


            <!-- Floating Action Buttons -->
            <div class="fixed bottom-8 right-8 flex flex-col space-y-4" v-if="hasAnyRole(['admin', 'secretary', 'treasurer'])">
                <button 
                    @click="showCreateExpenseModal = true"
                    class="w-12 h-12 bg-[#2D3139] text-white rounded-full flex items-center justify-center shadow-lg hover:bg-black transition-all"
                >
                    <MinusIcon class="w-6 h-6" />
                </button>
                <button 
                    @click="openCreateFundModal"
                    class="w-12 h-12 bg-[#E36C72] text-white rounded-full flex items-center justify-center shadow-lg hover:bg-[#d05a60] transition-all"
                >
                    <PlusIcon class="w-6 h-6" />
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import Background from '@/assets/images/club-default-thumbnail.svg?url'
import ClubFundSkeleton from '@/components/molecules/ClubFundSkeleton.vue'
import ClubCreateExpenseModal from '@/components/pages/club/partials/ClubCreateExpenseModal.vue'
import ClubCreateFundModal from '@/components/pages/club/partials/ClubCreateFundModal.vue'
import ClubCollectionDetailModal from '@/components/pages/club/partials/ClubCollectionDetailModal.vue'
import ClubSubmitReceiptModal from '@/components/pages/club/partials/ClubSubmitReceiptModal.vue'
import DeleteConfirmationModal from '@/components/molecules/DeleteConfirmationModal.vue'
import Pagination from '@/components/molecules/Pagination.vue'
import {
    ArrowDownIcon,
    ArrowLeftIcon,
    ArrowUpIcon,
    CalendarIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    PlusIcon,
    MinusIcon,
    XMarkIcon,
    ArrowDownTrayIcon,
    TrashIcon,
    PhotoIcon,
    PencilSquareIcon,
} from '@heroicons/vue/24/outline'
import { useRouter, useRoute } from 'vue-router'
import { onMounted, ref, computed, watch } from 'vue'
import QRCodeIcon from "@/assets/images/qr_code.svg";
import * as ClubService from '@/service/club.js'
import { toast } from 'vue3-toastify'
import { useUserStore } from '@/store/auth'
import { storeToRefs } from 'pinia'
import { formatCurrency, formatSpecialCurrency } from '@/composables/formatCurrency'
import { formatDatetime } from '@/composables/formatDatetime'
import VueDatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import dayjs from 'dayjs'
import { vClickOutside } from "@/directives/clickOutside"

const userStore = useUserStore()
const { getUser } = storeToRefs(userStore)
const router = useRouter()
const route = useRoute()
const club = ref([]);
const clubId = ref(route.params.id);
const showQRModal = ref(false)
const showCreateFundModal = ref(false)
const showCreateExpenseModal = ref(false)
const showDeleteQRModal = ref(false)
const showDeleteCollectionModal = ref(false)
const showCollectionDetailModal = ref(false)
const showSubmitReceiptModal = ref(false)
const selectedCollectionId = ref(null)
const selectedCollectionIdForReceipt = ref(null)
const selectedPaymentItem = ref(null)
const selectedQRDetail = ref(null)
const selectedContributionId = ref(null)
const collectionToDelete = ref(null)
const fundEditData = ref({})
const isInitialLoading = ref(true)
const fileInput = ref(null)
const previewImage = ref(null)
const fundOverview = ref(null)
const mainQrCode = ref(null)

// Main QR Form State
const newQRDescription = ref('')
const selectedFile = ref(null)
const isSubmittingQR = ref(false)
const transactions = ref([])

const triggerFileInput = () => {
    fileInput.value?.click()
}

const openCreateFundModal = () => {
    fundEditData.value = {}
    showCreateFundModal.value = true
}


const searchQuery = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const isTransactionsLoading = ref(false)
const itemsPerPage = 10
const fundCollections = ref([])
const isFundCollectionLoading = ref(false)
const fundCollectionPage = ref(1)
const fundCollectionLastPage = ref(1)
const isFundCollectionLoadingMore = ref(false)
const showCollectionDropdown = ref(false)
const myTransactions = ref([])

const allCollections = computed(() => {
    let collections = []
    if (hasAnyRole(['admin', 'secretary'])) {
        collections = fundCollections.value
    } else if (myTransactions.value) {
        collections = [
            ...(myTransactions.value.need_payment || []),
            ...(myTransactions.value.pending || []),
            ...(myTransactions.value.confirmed || [])
        ]
    }
    
    return collections.map(c => ({
        ...c,
        title: c.title || c.description,
        amount_per_member: c.amount_per_member || c.amount_due || c.my_contribution?.amount || c.amount || 0
    }))
})

const showFilterDropdown = ref(false)
const filters = ref({
    direction: '', // 'in' | 'out'
    source_types: [], // monthly_fee, fund_collection, etc.
    statuses: [], // pending, confirmed, rejected
    date_from: null,
    date_to: null
})

const sourceTypeOptions = [
    { value: 'monthly_fee', label: 'Quỹ tháng' },
    { value: 'fund_collection', label: 'Đợt thu' },
    { value: 'expense', label: 'Chi phí' },
    { value: 'donation', label: 'Quyên góp' },
    { value: 'adjustment', label: 'Điều chỉnh' },
    { value: 'activity', label: 'Hoạt động' },
    { value: 'activity_penalty', label: 'Phạt hoạt động' }
]

const statusOptions = [
    { value: 'pending', label: 'Chờ duyệt' },
    { value: 'confirmed', label: 'Đã xác nhận' },
    { value: 'rejected', label: 'Từ chối' }
]

const activeFilterCount = computed(() => {
    let count = 0
    if (filters.value.direction) count++
    if (filters.value.source_types.length > 0) count++
    if (filters.value.statuses.length > 0) count++
    if (filters.value.date_from) count++
    if (filters.value.date_to) count++
    return count
})

const applyFilters = () => {
    currentPage.value = 1
    getAllTransaction()
    getAllMyTransaction()
    showFilterDropdown.value = false
}

const clearFilters = () => {
    filters.value = {
        direction: '',
        source_types: [],
        statuses: [],
        date_from: null,
        date_to: null
    }
    applyFilters()
}

// Debounce for search
let searchTimeout = null
const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        currentPage.value = 1
        getAllTransaction()
        getAllMyTransaction()
    }, 500)
}

const handleFileUpload = (event) => {
    const file = event.target.files[0]
    if (file) {
        selectedFile.value = file
        // Create preview URL
        previewImage.value = URL.createObjectURL(file)
        console.log('File selected:', file.name)
    }
}

const removePreview = () => {
    previewImage.value = null
    selectedFile.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const currentUserMember = computed(() => {
    return club.value?.members?.find(member => member.user_id === getUser.value.id) || null
})

const hasAnyRole = (roles = []) => {
    return roles.includes(currentUserMember.value?.role)
}

const goBack = () => {
    router.back()
}

const downloadQrCode = async () => {
    if (!mainQrCode.value?.qr_code_url) return
    try {
        const response = await fetch(mainQrCode.value.qr_code_url)
        const blob = await response.blob()
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = 'qr-code-clb.png'
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
    } catch (e) {
        toast.error('Không thể tải mã QR. Vui lòng thử lại.')
    }
}

const getClubDetail = async () => {
    try {
        const response = await ClubService.clubDetail(clubId.value)
        club.value = response
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy thông tin câu lạc bộ')
    }
}

const getFundOverview = async () => {
    try {
        const response = await ClubService.fundOverview(clubId.value)
        fundOverview.value = response.data
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy thông tin quỹ')
    }
}

const getlistQrCodes = async () => {
    try {
        const response = await ClubService.listQrCodes(clubId.value)
        mainQrCode.value = response.data || null
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy mã QR chung')
    }
}

const createQrCode = async () => {
    try {
        isSubmittingQR.value = true
        const formData = new FormData();
        formData.append('qr_note', newQRDescription.value);
        if (selectedFile.value) {
            formData.append('image', selectedFile.value);
        }
        
        const response = await ClubService.createQrCode(clubId.value, formData)
        toast.success(response.message || 'Cập nhật mã QR chung thành công')
        
        // Reset form
        newQRDescription.value = ''
        removePreview()
        
        // Refresh list
        await getlistQrCodes()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi cập nhật mã QR')
    } finally {
        isSubmittingQR.value = false
    }
}

const deleteQrCode = async () => {
    try {
        isSubmittingQR.value = true
        const response = await ClubService.destroyMainQrCode(clubId.value)
        toast.success(response.message || 'Xóa mã QR chung thành công')
        mainQrCode.value = null
        await getlistQrCodes()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi xóa mã QR')
    } finally {
        isSubmittingQR.value = false
    }
}

const confirmDeleteQR = () => {
    showDeleteQRModal.value = true
}

const handleOpenCollectionDetail = (id) => {
    selectedCollectionId.value = id
    showCollectionDetailModal.value = true
}

const handleApproveContribution = async (collectionId, contributionId) => {
    try {
        const response = await ClubService.confirmFundContribution(clubId.value, collectionId, contributionId)
        toast.success(response.message || 'Duyệt giao dịch thành công')
        
        // Refresh data
        await getFundOverview()
        await getAllTransaction()
        await getAllMyTransaction()
        await getFundCollection()
        await getMyCollections()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi duyệt giao dịch')
    }
}

const handleViewDetail = (item) => {
    selectedCollectionId.value = item.id
    showCollectionDetailModal.value = true
}

const handlePayNow = async (item) => {
    selectedPaymentItem.value = item
    
    try {
        const response = await ClubService.getQrCode(clubId.value, item.id)
        selectedQRDetail.value = response.data || {}
    } catch (e) {
        toast.error('Không tải được thông tin QR. Vui lòng thử lại.')
        return
    }

    selectedCollectionIdForReceipt.value = item.id
    showSubmitReceiptModal.value = true
}

const handleOpenReceiptModal = async () => {
    // Only used conceptually, no longer an action from the MÃ QR HIỆN CÓ list as it is just 1 global QR now.
}

const handleReceiptSuccess = async () => {
    await getFundOverview()
    await getAllTransaction()
    await getAllMyTransaction()
    await getMyCollections()
}

const handleConfirmDelete = async () => {
    await deleteQrCode()
}

const handleSaveQRCode = async () => {
    if (!selectedFile.value && !mainQrCode.value?.qr_code_url && !newQRDescription.value) {
        toast.error('Vui lòng chọn ảnh hoặc nhập nội dung mã QR')
        return
    }

    await createQrCode()
}

const handleSubmitFundRevenue = async (data) => {
    const formData = new FormData();
    formData.append('title', data.title);
    formData.append('description', data.description);
    formData.append('target_amount', data.target_amount);
    formData.append('amount_per_member', data.amount_per_member);
    formData.append('start_date', data.start_date);
    formData.append('deadline', data.deadline);
    formData.append('end_date', data.end_date);
    formData.append('included_in_club_fund', data.included_in_club_fund ?? 1);
    
    if (Array.isArray(data.member_ids)) {
        data.member_ids.forEach(id => {
            formData.append('member_ids[]', id);
        });
    }

    // Luôn gửi qr_image nếu có (để làm fallback khi CLB chưa có QR chung)
    if (data.qr_image) {
        formData.append('qr_image', data.qr_image);
    }

    try {
        const response = await ClubService.createdFundRevenue(clubId.value, formData)
        toast.success(response.message || 'Tạo khoản thu thành công')
        showCreateFundModal.value = false
        // Refresh data
        await getFundOverview()
        await getAllTransaction()
        await getAllMyTransaction()
        await getFundCollection()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi tạo khoản thu')
    }
}
const handleDeleteCollection = (id) => {
    collectionToDelete.value = id
    showDeleteCollectionModal.value = true
}

const handleConfirmDeleteCollection = async () => {
    if (!collectionToDelete.value) return
    
    try {
        const response = await ClubService.deleteFundCollection(clubId.value, collectionToDelete.value)
        toast.success(response.message || 'Xóa đợt thu thành công')
        showDeleteCollectionModal.value = false
        showCollectionDetailModal.value = false
        
        // Refresh data
        await getFundOverview()
        await getAllTransaction()
        await getAllMyTransaction()
        await getFundCollection()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể xóa đợt thu')
    } finally {
        collectionToDelete.value = null
    }
}

const handleSubmitFundExpenses = async (data) => {
    try {
        const response = await ClubService.createFundExpenses(clubId.value, data)
        toast.success(response.message || 'Tạo khoản chi thành công')
        showCreateExpenseModal.value = false
        // Refresh data
        await getFundOverview()
        await getAllTransaction()
        await getAllMyTransaction()
        await getFundCollection()
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi tạo khoản chi')
    }
}

const getAllTransaction = async () => {
    if(!hasAnyRole(['admin', 'secretary'])) return
    try {
        isTransactionsLoading.value = true
        const params = {
            page: currentPage.value,
            per_page: itemsPerPage,
            search: searchQuery.value,
            direction: filters.value.direction || undefined,
            'source_types[]': filters.value.source_types.length > 0 ? filters.value.source_types : undefined,
            'statuses[]': filters.value.statuses.length > 0 ? filters.value.statuses : undefined,
            date_from: filters.value.date_from ? dayjs(filters.value.date_from).format('YYYY-MM-DD') : undefined,
            date_to: filters.value.date_to ? dayjs(filters.value.date_to).format('YYYY-MM-DD') : undefined
        }
        
        const response = await ClubService.getAllTransaction(clubId.value, params)
        transactions.value = response.data?.transactions || []
        lastPage.value = response.meta?.last_page || 1
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy danh sách giao dịch')
    } finally {
        isTransactionsLoading.value = false
    }
}

const getAllMyTransaction = async () => {
    if(!hasAnyRole(['member', 'treasurer', 'manager'])) return
    try {
        isTransactionsLoading.value = true
        const params = {
            page: currentPage.value,
            per_page: itemsPerPage,
            search: searchQuery.value,
            direction: filters.value.direction || undefined,
            'source_types[]': filters.value.source_types.length > 0 ? filters.value.source_types : undefined,
            'statuses[]': filters.value.statuses.length > 0 ? filters.value.statuses : undefined,
            date_from: filters.value.date_from ? dayjs(filters.value.date_from).format('YYYY-MM-DD') : undefined,
            date_to: filters.value.date_to ? dayjs(filters.value.date_to).format('YYYY-MM-DD') : undefined
        }
        
        const response = await ClubService.getAllMyTransaction(clubId.value, params)
        transactions.value = response.data?.transactions || []
        lastPage.value = response.meta?.last_page || 1
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy danh sách giao dịch')
    } finally {
        isTransactionsLoading.value = false
    }
}

const handlePageChange = (page) => {
    currentPage.value = page
    getAllTransaction()
    getAllMyTransaction()
}

const getFundCollection = async (isLoadMore = false) => {
    if(!hasAnyRole(['admin', 'secretary'])) return
    try {
        if (isLoadMore) {
            isFundCollectionLoadingMore.value = true
        } else {
            isFundCollectionLoading.value = true
            fundCollectionPage.value = 1
        }

        const response = await ClubService.getFundCollection(clubId.value, {
            status: 'active',
            page: fundCollectionPage.value,
            per_page: 10
        })

        const newCollections = response.data?.collections || []
        if (isLoadMore) {
            fundCollections.value = [...fundCollections.value, ...newCollections]
        } else {
            fundCollections.value = newCollections
        }
        
        fundCollectionLastPage.value = response.meta?.last_page || 1
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi lấy danh sách khoản thu')
    } finally {
        isFundCollectionLoading.value = false
        isFundCollectionLoadingMore.value = false
    }
}

const getMyCollections = async () => {
    if(!hasAnyRole(['manager', 'member'])) return
    
    try {
        const response = await ClubService.getMyCollections(clubId.value)
        myTransactions.value = response.data || []
    } catch (error) {
        toast.error('Không thể tải thông tin chi tiết đợt thanh toán')
    }
}

onMounted(async () => {
    if (!clubId.value) {
        isInitialLoading.value = false;
        return;
    }
    
    isInitialLoading.value = true;
    await getClubDetail();
    await getFundOverview();
    await getlistQrCodes();
    await getAllTransaction();
    await getAllMyTransaction();
    await getFundCollection();
    await getMyCollections();

    if (route.query.collectionId) {
        selectedCollectionId.value = route.query.collectionId
        selectedContributionId.value = route.query.contributionId
        showCollectionDetailModal.value = true
    }

    // Simulate loading delay
    setTimeout(() => {
        isInitialLoading.value = false
    }, 1000)
})

watch(() => route.query, (newQuery) => {
    if (newQuery.collectionId) {
        selectedCollectionId.value = newQuery.collectionId
        selectedContributionId.value = newQuery.contributionId
        showCollectionDetailModal.value = true
    }
}, { deep: true })
</script>
<style scoped>
.bg-club-default {
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-color: #000;
    min-height: 286px;
}

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

.v-datepicker-custom :deep(.dp__input) {
    background-color: #f9fafb;
    border: 1px solid #f3f4f6;
    border-radius: 12px;
    padding: 12px 16px 12px 42px;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
}

.v-datepicker-custom :deep(.dp__input:hover) {
    border-color: #d1d5db;
}

.v-datepicker-custom :deep(.dp__input:focus) {
    border-color: #D72D36;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #E5E7EB;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #D1D5DB;
}
</style>