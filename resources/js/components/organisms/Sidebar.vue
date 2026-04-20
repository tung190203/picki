<template>
    <div>
        <!-- Backdrop overlay when expanded (desktop/mobile) -->
        <div
            v-if="isExpanded"
            class="fixed inset-0 bg-black/10 backdrop-blur-[1px] z-40"
            @click="collapseOnBackdrop"
        ></div>

        <!-- Actual sidebar -->
        <aside
            class="bg-white shadow-lg flex flex-col justify-between py-4 transition-all duration-300 ease-in-out z-50 h-screen fixed left-0 top-0"
            :class="isExpanded ? 'w-64' : 'w-16'"
            @mouseenter="expand(true)"
            @mouseleave="expand(false)"
        >
            <!-- Top Section (Logo) -->
            <div class="px-3">
                <div class="flex items-center space-x-3 cursor-pointer" @click="goToDashboard">
                    <div
                        class="w-10 h-10 flex items-center justify-center flex-shrink-0"
                    >
                         <LogoUrl class="w-10 h-10" />
                    </div>

                    <div
                        class="overflow-hidden transition-all duration-300"
                        :class="
                            isExpanded
                                ? 'opacity-100 max-w-xs'
                                : 'opacity-0 max-w-0'
                        "
                    >
                    <LogoExplainUrl class="h-8" />
                    </div>
                </div>
            </div>

            <!-- Middle Section - Navigation (Mobile only < 1024px) -->
            <nav class="lg:hidden flex flex-col space-y-1 px-2 flex-1 mt-6 overflow-y-auto">
                <!-- Player -->
                <template v-if="activeMenu === ROLE.PLAYER">
                    <RouterLink to="/" :class="mobileLinkClass('/')">
                        <HomeIcon class="w-5 h-5 flex-shrink-0" />
                        <span
                            class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                            :class="
                                isExpanded
                                    ? 'opacity-100 ml-3 max-w-xs'
                                    : 'opacity-0 ml-0 max-w-0'
                            "
                        >
                            Trang chủ
                        </span>
                    </RouterLink>

                    <RouterLink to="/friends" :class="mobileLinkClass('/friends')">
                        <UsersIcon class="w-5 h-5 flex-shrink-0" />
                        <span
                            class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                            :class="
                                isExpanded
                                    ? 'opacity-100 ml-3 max-w-xs'
                                    : 'opacity-0 ml-0 max-w-0'
                            "
                        >
                            Bạn bè
                        </span>
                    </RouterLink>

                    <RouterLink
                        to="/mini-tournament/create"
                        :class="mobileLinkClass('/mini-tournament/create')"
                    >
                        <PlusCircleIcon class="w-5 h-5 flex-shrink-0" />
                        <span
                            class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                            :class="
                                isExpanded
                                    ? 'opacity-100 ml-3 max-w-xs'
                                    : 'opacity-0 ml-0 max-w-0'
                            "
                        >
                            Tạo kèo đấu
                        </span>
                    </RouterLink>
                    <RouterLink
                        to="/tournament/create"
                        :class="mobileLinkClass('/tournament/create')"
                    >
                        <PlusCircleIcon class="w-5 h-5 flex-shrink-0" />
                        <span
                            class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                            :class="
                                isExpanded
                                    ? 'opacity-100 ml-3 max-w-xs'
                                    : 'opacity-0 ml-0 max-w-0'
                            "
                        >
                            Tạo giải đấu
                        </span>
                    </RouterLink>

                    <RouterLink to="/map" :class="mobileLinkClass('/map')">
                        <BriefcaseIcon class="w-5 h-5 flex-shrink-0" />
                        <span
                            class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                            :class="
                                isExpanded
                                    ? 'opacity-100 ml-3 max-w-xs'
                                    : 'opacity-0 ml-0 max-w-0'
                            "
                        >
                            Công cụ
                        </span>
                    </RouterLink>
                </template>

                <!-- Referee -->
                <template v-else-if="activeMenu === ROLE.REFEREE">
                    <RouterLink
                        to="/referee/dashboard"
                        :class="mobileLinkClass('/referee/dashboard')"
                    >
                        <HomeIcon class="w-5 h-5 flex-shrink-0" />
                        <span
                            class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                            :class="
                                isExpanded
                                    ? 'opacity-100 ml-3 max-w-xs'
                                    : 'opacity-0 ml-0 max-w-0'
                            "
                        >
                            Trang chủ
                        </span>
                    </RouterLink>

                    <RouterLink
                        to="/referee/tournaments"
                        :class="mobileLinkClass('/referee/tournaments')"
                    >
                        <BriefcaseIcon class="w-5 h-5 flex-shrink-0" />
                        <span
                            class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                            :class="
                                isExpanded
                                    ? 'opacity-100 ml-3 max-w-xs'
                                    : 'opacity-0 ml-0 max-w-0'
                            "
                        >
                            Giải đấu được phân công
                        </span>
                    </RouterLink>

                    <RouterLink
                        to="/referee/reports"
                        :class="mobileLinkClass('/referee/reports')"
                    >
                        <UsersIcon class="w-5 h-5 flex-shrink-0" />
                        <span
                            class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                            :class="
                                isExpanded
                                    ? 'opacity-100 ml-3 max-w-xs'
                                    : 'opacity-0 ml-0 max-w-0'
                            "
                        >
                            Báo cáo / Khiếu nại
                        </span>
                    </RouterLink>
                </template>
            </nav>

            <!-- Bottom Section (Actions + User Info) -->
            <div class="flex flex-col space-y-1 px-2 mb-4">
                <!-- Admin Dash -->
                <router-link v-if="getRole === ROLE.ADMIN" to="/admin/dashboard" v-slot="{ isActive }">
                    <button
                        :class="[
                            'flex items-center h-12 px-3 rounded-xl transition-all w-full text-left',
                            isActive
                                ? 'bg-gray-100 text-gray-900'
                                : 'text-gray-600 hover:bg-gray-100',
                        ]"
                    >
                        <component :is="getUser.is_super_admin ? ShieldCheckIcon : HomeIcon" class="w-5 h-5 flex-shrink-0" />
                        <span
                            class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                            :class="
                                isExpanded
                                    ? 'opacity-100 ml-3 max-w-xs'
                                    : 'opacity-0 ml-0 max-w-0'
                            "
                        >
                            {{ getUser.is_super_admin ? 'Super Admin' : 'Admin Dash' }}
                        </span>
                    </button>
                </router-link>

                <!-- Notification -->
                <router-link to="/notifications" v-slot="{ isActive }">
                <button
                    class="flex items-center h-12 px-3 rounded-xl text-gray-600 hover:bg-gray-100 transition-all relative w-full text-left"
                    :class="isActive ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-100'"
                >
                    <div class="relative flex-shrink-0">
                        <BellIcon class="w-5 h-5" />
                        <span
                            v-if="hasNotification"
                            class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full"
                        ></span>
                    </div>

                    <span
                        class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                        :class="
                            isExpanded
                                ? 'opacity-100 ml-3 max-w-xs'
                                : 'opacity-0 ml-0 max-w-0'
                        "
                    >
                        Thông báo
                    </span>
                </button>
                </router-link>

                <!-- Settings -->
                <router-link to="/settings" v-slot="{ isActive }">
                    <button
                        :class="[
                            'flex items-center h-12 px-3 rounded-xl transition-all w-full text-left',
                            isActive
                                ? 'bg-gray-100 text-gray-900'
                                : 'text-gray-600 hover:bg-gray-100',
                        ]"
                    >
                        <Cog6ToothIcon class="w-5 h-5 flex-shrink-0" />
                        <span
                            class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                            :class="
                                isExpanded
                                    ? 'opacity-100 ml-3 max-w-xs'
                                    : 'opacity-0 ml-0 max-w-0'
                            "
                        >
                            Cài đặt
                        </span>
                    </button>
                </router-link>

                <!-- Logout -->
                <button
                    @click="handleLogout"
                    class="flex items-center h-12 px-3 rounded-xl text-gray-600 hover:bg-red-50 hover:text-red-500 transition-all w-full text-left"
                >
                    <ArrowRightOnRectangleIcon class="w-5 h-5 flex-shrink-0" />
                    <span
                        class="font-medium whitespace-nowrap overflow-hidden transition-all duration-300"
                        :class="
                            isExpanded
                                ? 'opacity-100 ml-3 max-w-xs'
                                : 'opacity-0 ml-0 max-w-0'
                        "
                    >
                        Đăng xuất
                    </span>
                </button>

                <!-- User Info (Mobile only) - Đưa xuống dưới cùng -->
                <div class="lg:hidden flex items-center h-12 px-3 rounded-xl bg-gray-50 mt-2 cursor-pointer"  @click="goToProfile(getUser.id)">
                    <div class="relative flex-shrink-0">
                        <img
                            :src="getUser.avatar_url || defaultAvatar"
                            alt="avatar"
                            class="w-8 h-8 rounded-full"
                        />
                        <span
                            class="absolute -bottom-1 -right-1 bg-blue-500 text-white text-[8px] font-semibold border border-white rounded-full w-4 h-4 flex items-center justify-center"
                        >
                        {{ getUser?.sports?.[0]?.scores?.vndupr_score ? Number(getUser.sports[0].scores.vndupr_score).toFixed(1) : '' }}
                        </span>
                    </div>
                    <div
                        class="text-left overflow-hidden transition-all duration-300"
                        :class="
                            isExpanded
                                ? 'opacity-100 ml-3 max-w-xs'
                                : 'opacity-0 ml-0 max-w-0'
                        "
                    >
                        <p class="text-[11px] text-gray-600">Xin chào,</p>
                        <p class="font-semibold text-gray-800 text-sm">
                            {{ getUser.full_name }}
                        </p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useUserStore } from "@/store/auth";
import { storeToRefs } from "pinia";
import { toast } from "vue3-toastify";
import { ROLE } from "@/constants/index";
import * as NotificationService from '@/service/notifications'
import LogoUrl from '@/assets/images/logo.svg'
import LogoExplainUrl from '@/assets/images/logo-explain.svg'

import {
    BellIcon,
    Cog6ToothIcon,
    ArrowRightOnRectangleIcon,
    HomeIcon,
    UsersIcon,
    PlusCircleIcon,
    BriefcaseIcon,
    ShieldCheckIcon,
} from "@heroicons/vue/24/outline";

const router = useRouter();
const route = useRoute();
const userStore = useUserStore();
const { getRole, getUser } = storeToRefs(userStore);
const defaultAvatar = "/images/default-avatar.png";

const isExpanded = ref(false);
const hasNotification = ref(true);

const activeMenu = computed(() => {
    if (getRole.value === ROLE.ADMIN) return ROLE.PLAYER;
    return getRole.value;
});

const expand = (state) => {
    isExpanded.value = state;
};

const collapseOnBackdrop = () => {
    isExpanded.value = false;
};

const goToDashboard = () => {
    switch (getRole.value) {
        case ROLE.ADMIN:
            router.push({ name: "dashboard" });
            break;
        case ROLE.REFEREE:
            router.push({ name: "referee.dashboard" });
            break;
        default:
            router.push({ name: "dashboard" });
            break;
    }
};

const handleLogout = async () => {
    try {
        await userStore.logoutUser();
        toast.success("Đăng xuất thành công!");
        setTimeout(() => {
            router.push({ name: "login" });
        }, 500);
    } catch (error) {
        toast.error(error.response?.data?.message || "Đăng xuất thất bại!");
    }
};

const loadData = async () => {
  const res = await NotificationService.getNotifications({
    type: 'all',
    page: 1,
    per_page: 1,
  })

  hasNotification.value = res.meta.unread_count > 0;
}

const mobileLinkClass = (path) => {
    const base = "flex items-center h-12 px-3 rounded-xl transition-all w-full text-left";
    const active = "bg-red-600 text-white";
    const normal = "text-gray-600 hover:bg-gray-100";

    const isActive = path === "/" ? route.path === "/" : route.path.startsWith(path);

    return isActive ? `${base} ${active}` : `${base} ${normal}`;
};

const goToProfile = (id) => {
    router.push({ name: 'profile', params: { id } });
};

onMounted(async () => {
  await loadData();
})
</script>

<style scoped>
@media (max-width: 1024px) {
    aside {
        left: 0;
        top: 0;
        height: 100%;
    }
}
</style>