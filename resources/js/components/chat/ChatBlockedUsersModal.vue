<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in"
      @click.self="$emit('close')"
    >
      <div class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl w-full max-w-md h-[500px] max-h-[85vh] shadow-2xl overflow-hidden flex flex-col">
        <!-- Header -->
        <div class="p-4 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between flex-shrink-0">
          <div class="flex items-center gap-2">
            <NoSymbolIcon class="w-5 h-5 text-red-500" />
            <h3 class="font-bold text-gray-900 dark:text-white text-base">
              Danh sách đã chặn
            </h3>
          </div>
          <button
            @click="$emit('close')"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition"
            title="Đóng"
          >
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Subtitle note -->
        <div class="px-4 py-2.5 bg-gray-50 dark:bg-slate-800/40 border-b border-gray-100 dark:border-slate-800 text-xs text-gray-500 dark:text-slate-400 flex-shrink-0">
          Người dùng trong danh sách này sẽ không thể gửi tin nhắn cho bạn.
        </div>

        <!-- List items -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3 min-h-0">
          <div v-if="loading" class="text-center py-16 text-xs text-gray-400">
            Đang tải danh sách chặn...
          </div>

          <div v-else-if="blockedUsers.length === 0" class="text-center py-16 space-y-2">
            <CheckCircleIcon class="w-10 h-10 text-emerald-500 mx-auto opacity-80" />
            <p class="text-xs text-gray-500 dark:text-slate-400">Bạn chưa chặn người dùng nào</p>
          </div>

          <div
            v-else
            v-for="item in blockedUsers"
            :key="item.id"
            class="flex items-center justify-between p-3 rounded-xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-800/50 hover:bg-gray-50 dark:hover:bg-slate-800 transition"
          >
            <div class="flex items-center gap-3 min-w-0 pr-2">
              <img
                :src="item.blocked_user?.avatar_url || '/images/default-avatar.png'"
                class="w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-slate-700 flex-shrink-0"
                @error="$event.target.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(item.blocked_user?.full_name || 'User')"
              />
              <div class="min-w-0">
                <h5 class="font-bold text-xs sm:text-sm text-gray-900 dark:text-white truncate">
                  {{ item.blocked_user?.full_name || 'Người dùng' }}
                </h5>
                <p class="text-[11px] text-gray-400 dark:text-slate-400 truncate mt-0.5">
                  Đã chặn {{ formatDate(item.created_at) }}
                </p>
              </div>
            </div>

            <button
              @click="unblock(item)"
              :disabled="unblockingId === item.blocked_user_id"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-red-200 dark:border-red-900/60 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 transition flex-shrink-0 disabled:opacity-50"
            >
              {{ unblockingId === item.blocked_user_id ? 'Đang mở...' : 'Bỏ chặn' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from "vue";
import { NoSymbolIcon, XMarkIcon, CheckCircleIcon } from "@heroicons/vue/24/solid";
import { chatService } from "@/service/chat.js";
import { toast } from "vue3-toastify";

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["close", "unblocked"]);

const blockedUsers = ref([]);
const loading = ref(false);
const unblockingId = ref(null);

const loadBlockedList = async () => {
  try {
    loading.value = true;
    const res = await chatService.getBlocks();
    blockedUsers.value = res.data || [];
  } catch (err) {
    console.error("loadBlockedList error:", err);
  } finally {
    loading.value = false;
  }
};

const unblock = async (item) => {
  const userId = item.blocked_user_id;
  try {
    unblockingId.value = userId;
    await chatService.unblockUser(userId);
    blockedUsers.value = blockedUsers.value.filter((b) => b.blocked_user_id !== userId);
    toast.success(`Đã bỏ chặn ${item.blocked_user?.full_name || 'người dùng'}`);
    emit("unblocked", userId);
  } catch (err) {
    toast.error(err?.response?.data?.message || "Lỗi khi bỏ chặn");
  } finally {
    unblockingId.value = null;
  }
};

const formatDate = (dateStr) => {
  if (!dateStr) return "";
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString("vi-VN");
  } catch {
    return "";
  }
};

watch(
  () => props.isOpen,
  (val) => {
    if (val) {
      loadBlockedList();
    }
  },
  { immediate: true }
);
</script>
