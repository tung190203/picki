<template>
    <Transition name="modal-fade">
      <div v-if="modelValue"
        class="fixed inset-0 z-[10010] flex items-center justify-center p-4 bg-gray-900 bg-opacity-50 backdrop-blur-sm"
        @click.self="closeModal">
        
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm max-h-[95vh] sm:max-h-[90vh] transform transition-all duration-300 overflow-hidden flex flex-col"
          role="dialog" aria-modal="true">

          <div class="p-4 sm:p-5 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <h3 class="text-base sm:text-xl font-semibold text-gray-900">
              {{ title }}
            </h3>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
              <XMarkIcon class="w-5 h-5 sm:w-6 sm:h-6" />
            </button>
          </div>

          <div class="p-4 sm:p-5 overflow-y-auto flex-1">
            <p class="text-gray-600 mb-4 sm:mb-6 text-center text-sm sm:text-base">
              {{ message }}
            </p>
          </div>

          <div class="px-4 sm:px-5 py-3 sm:py-4 bg-gray-50 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3 flex-shrink-0">
            <button @click="closeModal"
              class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
              Hủy
            </button>
            <button @click="handleConfirm"
              class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors shadow-md"
              :class="confirmButtonClass">
              {{ confirmButtonText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </template>
  
  <script setup>
  import { XMarkIcon } from '@heroicons/vue/24/outline';
  import { defineProps, defineEmits } from 'vue';
  
  // Khai báo Props
  const props = defineProps({
    // v-model để quản lý trạng thái hiển thị
    modelValue: {
      type: Boolean,
      required: true,
    },
    title: {
      type: String,
      default: 'Xác nhận Thao tác',
    },
    message: {
      type: String,
      default: 'Bạn có chắc chắn muốn thực hiện hành động này? Thao tác này có thể không hoàn tác được.',
    },
    confirmButtonText: {
      type: String,
      default: 'Xác nhận',
    },
    confirmButtonClass: {
      type: String,
      default: '',
    },
  });
  
  // Khai báo Events
  const emit = defineEmits(['update:modelValue', 'confirm']);
  
  // Hàm đóng modal
  const closeModal = () => {
    emit('update:modelValue', false);
  };
  
  // Hàm xử lý xác nhận
  const handleConfirm = () => {
    emit('confirm');
    closeModal(); // Đóng modal sau khi xác nhận
  };
  </script>
  
  <style scoped>
  /* Transition cho Modal */
  .modal-fade-enter-active,
  .modal-fade-leave-active {
    transition: opacity 0.25s ease;
  }
  
  .modal-fade-enter-from,
  .modal-fade-leave-to {
    opacity: 0;
  }
  
  /* Thêm transition cho phần tử bên trong modal để tạo hiệu ứng trượt */
  .modal-fade-enter-active .bg-white,
  .modal-fade-leave-active .bg-white {
    transition: transform 0.25s ease;
  }
  
  .modal-fade-enter-from .bg-white,
  .modal-fade-leave-to .bg-white {
    transform: scale(0.95) translateY(10px);
  }
  </style>