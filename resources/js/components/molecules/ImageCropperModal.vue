<template>
  <Teleport to="body">
    <div v-if="isOpen" class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[95vh] sm:max-h-[90vh]">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b flex-shrink-0">
                <h3 class="text-base sm:text-lg font-semibold text-gray-800">Chỉnh sửa ảnh</h3>
                <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700 p-1">
                    <XMarkIcon class="w-5 h-5 sm:w-6 sm:h-6" />
                </button>
            </div>

            <!-- Cropper Area -->
            <div class="flex-1 overflow-hidden bg-gray-100 min-h-[240px] sm:min-h-[300px] flex items-center justify-center relative">
                <cropper
                    ref="cropperRef"
                    class="h-full w-full object-contain"
                    :src="image"
                    :stencil-props="stencilProps"
                    :check-orientation="true"
                    image-restriction="stencil"
                    :default-size="defaultSize"
                />
            </div>

            <!-- Footer -->
            <div class="p-3 sm:p-4 border-t flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3 flex-shrink-0">
                <button
                    @click="$emit('close')"
                    class="w-full sm:w-auto px-6 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
                    Hủy
                </button>
                <button
                    @click="cropImage"
                    class="w-full sm:w-auto px-6 py-2 rounded-lg bg-[#4392E0] text-white hover:bg-[#3476B8] transition-colors font-medium">
                    Lưu
                </button>
            </div>
        </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    isOpen: Boolean,
    image: String, // Base64 or URL
    stencilProps: {
        type: Object,
        default: () => ({
            aspectRatio: 1
        })
    }
});

const emit = defineEmits(['close', 'save']);
const cropperRef = ref(null);

const defaultSize = ({ imageSize, visibleArea }) => {
    return {
        width: (visibleArea || imageSize).width,
        height: (visibleArea || imageSize).height,
    }
}

const cropImage = () => {
    const { canvas } = cropperRef.value.getResult();
    if (canvas) {
        canvas.toBlob((blob) => {
            emit('save', blob);
        }, 'image/jpeg', 0.9);
    }
};
</script>

<style scoped>
.cropper {
    background: #ddd;
}
</style>
