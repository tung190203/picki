<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in"
    @click.self="$emit('close')"
  >
    <div class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl w-full max-w-md shadow-2xl p-5 space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2">
          <FlagIcon class="w-5 h-5 text-red-500" />
          <span>Báo cáo cuộc trò chuyện</span>
        </h3>
        <button
          @click="$emit('close')"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded-lg transition"
          title="Đóng"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>

      <p class="text-xs text-gray-500 dark:text-slate-400">
        Đội ngũ quản trị viên sẽ xem xét nội dung và thực hiện các biện pháp kiểm duyệt nếu phát hiện vi phạm.
      </p>

      <div class="space-y-2">
        <label class="text-xs font-semibold text-gray-700 dark:text-slate-300">Lý do báo cáo:</label>
        <div class="space-y-1.5">
          <label
            v-for="r in reasons"
            :key="r.value"
            class="flex items-center gap-2 p-2 rounded-lg border border-gray-100 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-800 cursor-pointer text-xs text-gray-800 dark:text-slate-200"
          >
            <input
              type="radio"
              v-model="selectedReason"
              :value="r.value"
              class="text-[#D72D36] focus:ring-[#D72D36]"
            />
            <span>{{ r.label }}</span>
          </label>
        </div>
      </div>

      <div class="space-y-1">
        <label class="text-xs font-semibold text-gray-700 dark:text-slate-300">Mô tả thêm (tùy chọn):</label>
        <textarea
          v-model="reasonDetails"
          rows="3"
          placeholder="Cung cấp thêm chi tiết vi phạm..."
          class="w-full bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white rounded-lg p-2.5 text-xs outline-none focus:ring-2 focus:ring-[#D72D36]"
        ></textarea>
      </div>

      <div class="flex gap-2 justify-end pt-2">
        <button
          @click="$emit('close')"
          class="px-4 py-2 rounded-lg text-xs font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 transition"
        >
          Hủy
        </button>
        <button
          @click="submitReport"
          :disabled="submitting || !selectedReason"
          class="px-4 py-2 rounded-lg text-xs font-semibold bg-red-600 hover:bg-red-700 text-white transition disabled:opacity-50"
        >
          {{ submitting ? 'Đang gửi...' : 'Gửi báo cáo' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { FlagIcon, XMarkIcon } from "@heroicons/vue/24/solid";
import { chatService } from "@/service/chat.js";
import { toast } from "vue3-toastify";

const props = defineProps({
  isOpen: Boolean,
  conversationId: Number,
  reportedUserId: Number,
});

const emit = defineEmits(["close", "reported"]);

const reasons = [
  { value: "harassment", label: "Quấy rối, xúc phạm hoặc đe dọa" },
  { value: "spam", label: "Gửi tin nhắn rác / spam quảng cáo" },
  { value: "fraud", label: "Lừa đảo, gian lận cược hoặc giải đấu" },
  { value: "inappropriate_content", label: "Nội dung phản cảm, đồi trụy" },
  { value: "other", label: "Lý do khác" },
];

const selectedReason = ref("harassment");
const reasonDetails = ref("");
const submitting = ref(false);

const submitReport = async () => {
  if (!selectedReason.value) return;
  try {
    submitting.value = true;
    const finalReason = reasonDetails.value
      ? `${selectedReason.value}: ${reasonDetails.value}`
      : selectedReason.value;

    await chatService.reportChat({
      conversation_id: props.conversationId,
      reported_user_id: props.reportedUserId,
      reason: finalReason,
    });

    emit("reported");
    emit("close");
    toast.success("Đã gửi báo cáo thành công. Cảm ơn bạn!");
  } catch (err) {
    console.error("submitReport error:", err);
    toast.error(err?.response?.data?.message || "Có lỗi xảy ra khi gửi báo cáo");
  } finally {
    submitting.value = false;
  }
};
</script>
