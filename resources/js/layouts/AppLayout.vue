<template>
    <div class="flex h-screen overflow-hidden relative bg-gray-50">
        <!-- Sidebar: desktop = fixed, mobile = drawer -->
        <Sidebar 
            ref="sidebarRef" 
            :isMobile="isMobile" 
            :isDrawerOpen="isMobileSidebarOpen"
            @close="closeMobileSidebar"
        />
        <div
            class="flex-1 flex flex-col overflow-hidden transition-all duration-300 ease-in-out"
            :style="mainStyle"
        >
            <Header @toggle-mobile-menu="toggleMobileSidebar" />
            <main class="flex-1 overflow-y-auto">
                <router-view :key="$route.fullPath" />
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from "vue";
import Header from "@/components/organisms/Header.vue";
import Sidebar from "@/components/organisms/Sidebar.vue";

const sidebarRef = ref(null);
const isMobile = ref(window.innerWidth <= 1024);
const isMobileSidebarOpen = ref(false);

const onResize = () => {
    isMobile.value = window.innerWidth <= 1024;
    // Đóng sidebar khi resize về desktop
    if (!isMobile.value) {
        isMobileSidebarOpen.value = false;
    }
};

const toggleMobileSidebar = () => {
    isMobileSidebarOpen.value = !isMobileSidebarOpen.value;
};

const closeMobileSidebar = () => {
    isMobileSidebarOpen.value = false;
};

onMounted(() => window.addEventListener("resize", onResize));
onBeforeUnmount(() => window.removeEventListener("resize", onResize));

const collapsedPx = "4rem";
const mainStyle = computed(() => {
    // Trên mobile: không cần margin vì sidebar là drawer overlay
    if (isMobile.value) {
        return {
            marginLeft: '0',
            width: '100%',
        };
    }
    
    // Trên desktop: giữ khoảng 4rem để tránh sidebar đè
    return {
        marginLeft: collapsedPx,
        width: `calc(100% - ${collapsedPx})`,
    };
});
</script>
