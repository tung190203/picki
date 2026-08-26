<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="modelValue"
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm transform-gpu"
            @click.self="close"
        >
            <div
                class="bg-white dark:bg-[#161F33] border border-gray-100 dark:border-slate-800 rounded-2xl shadow-2xl w-full max-w-lg relative z-[10000] overflow-hidden animate-in fade-in zoom-in duration-300 h-[calc(100vh-7rem)] flex flex-col">
                <!-- Fixed Header -->
                <div class="p-6 pb-2">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-[28px] font-bold text-[#3E414C] dark:text-slate-100">Thông báo</h3>
                        <button @click="close"
                            class="text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-white transition-colors">
                            <XMarkIcon class="w-8 h-8" stroke-width="2.5" />
                        </button>
                    </div>
                    <div class="flex items-center justify-end">
                        <button class="text-[#D72D36] dark:text-red-400 text-sm font-semibold hover:opacity-80" @click="$emit('markAllAsRead')">
                            Đánh dấu đã đọc
                        </button>
                    </div>
                </div>
                <div v-if="notifications.length === 0" class="flex items-start justify-center mt-4">
                    <p class="text-[#838799] dark:text-slate-400">Hiện chưa có thông báo</p>
                </div>

                <!-- Scrollable Content -->
                <div class="p-6 pt-3 flex-1 overflow-y-auto custom-scrollbar" v-else>
                    <div class="mb-8">
                        <div class="space-y-2">
                            <div v-for="(notification, index) in notifications" class="cursor-pointer"
                                :key="index"
                                :class="['flex gap-4 p-4 rounded-2xl transition-colors', !notification.is_read_by_me ? 'bg-[#F8F9FB] dark:bg-slate-800/80 border border-transparent dark:border-slate-700/50' : 'bg-transparent hover:bg-slate-800/30']"  
                                @click="handleNotificationClick(notification)"
                            >
                                <div class="relative flex-shrink-0">
                                    <div
                                        :class="['w-14 h-14 rounded-xl flex items-center justify-center', (NOTIFICATION_COLOR_MAP[notification.club_notification_type_id] || NOTIFICATION_COLOR_MAP[1]).cardBg]">
                                        <component :is="NOTIFICATION_ICON_MAP[notification.club_notification_type_id] || NOTIFICATION_ICON_MAP[1]" class="w-7 h-7" :class="NOTIFICATION_COLOR_MAP[notification.club_notification_type_id].iconColor" />
                                    </div>
                                    <div v-if="!notification.is_read_by_me"
                                        class="absolute -right-1 bottom-0 w-4 h-4 border-2 border-white dark:border-slate-800 rounded-full" :class="NOTIFICATION_COLOR_MAP[notification.club_notification_type_id].iconBg">
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0 relative">
                                    <div class="flex items-start justify-between gap-2 mb-1">
                                        <h4 class="font-semibold text-base text-[#3E414C] dark:text-slate-100 truncate">{{
                                            notification.title }}
                                        </h4>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[#838799] dark:text-slate-400 text-xs whitespace-nowrap pt-1">{{ getJoinedDate(notification.created_at) }}</span>
                                            <div v-if="isAdminOrStaff" class="pt-1 absolute top-[-2rem] right-[-1.5rem]">
                                                <button v-if="notification.is_pinned" @click.stop="$emit('unpin', notification.id)" class="transition-all transform hover:scale-110">
                                                    <PinIcon class="w-5 h-5 transform rotate-45 transition-transform group-hover:rotate-12 text-[#D72D36]" />
                                                </button>
                                                <button v-else @click.stop="$emit('pin', notification.id)" class="transition-all transform hover:scale-110"
                                                    :title="canPinMore ? 'Ghim thông báo' : 'Đã đạt giới hạn ghim thông báo, vui lòng gỡ bớt ghim trước khi ghim thêm'">
                                                    <PinIcon class="w-5 h-5 text-gray-300 dark:text-slate-600 hover:text-[#D72D36]" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-[#838799] dark:text-slate-400 leading-4 line-clamp-2">{{ notification.content }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Load More Button -->
                        <div v-if="meta && meta.current_page < meta.last_page" class="mt-4 flex justify-center">
                            <button
                                @click="$emit('loadMore')"
                                :disabled="isLoadingMore"
                                class="text-[#D72D36] dark:text-red-400 font-semibold hover:underline disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                            >
                                <span v-if="isLoadingMore" class="w-4 h-4 border-2 border-[#D72D36] border-t-transparent rounded-full animate-spin"></span>
                                {{ isLoadingMore ? 'Đang tải...' : 'Xem thêm' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Create Notification Button -->
                <div class="absolute bottom-6 right-6 z-20" v-if="isAdminOrStaff">
                    <button
                        class="flex items-center gap-2 bg-[#D72D36] hover:bg-[#c4252e] text-white px-4 py-4 rounded-full shadow-lg transition-colors font-semibold shadow-red-200 dark:shadow-none"
                        @click="$emit('create')">
                        <NotificationAddIcon class="w-6 h-6" />
                        <span>Tạo thông báo</span>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'
import PinIcon from '@/assets/images/pin_icon.svg'
import NotificationAddIcon from '@/assets/images/notification_add.svg'
import { getJoinedDate } from '@/composables/formatDatetime.js'
import { NOTIFICATION_COLOR_MAP, NOTIFICATION_ICON_MAP } from '@/data/club/index.js'

defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    notifications: {
        type: Array,
        default: () => []
    },
    meta: {
        type: Object,
        default: () => ({})
    },
    isLoadingMore: {
        type: Boolean,
        default: false
    },
    isAdminOrStaff: {
        type: Boolean,
        default: false
    },
    canPinMore: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['update:modelValue', 'close', 'loadMore', 'markAsRead', 'markAllAsRead', 'unpin', 'pin', 'create', 'clickNotification'])

const close = () => {
    emit('update:modelValue', false)
    emit('close')
}

const handleNotificationClick = (notification) => {
    if (!notification.is_read_by_me) {
        emit('markAsRead', notification.id)
    }
    emit('clickNotification', notification)
}
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #E5E7EB;
  border-radius: 20px;
}
</style>
