<template>
    <div class="bg-white rounded-xl shadow p-4 select-none">
        <!-- Header -->
        <div class="flex items-center justify-between cursor-pointer" @click="emit('toggle')">
            <div class="flex items-center gap-2">
                <img :src="icon" class="w-6 h-6 dark:invert dark:brightness-200" />
                <div>
                    <p class="font-semibold text-gray-800">{{ title }}</p>
                    <p class="text-gray-500 text-sm">{{ subtitle }}</p>
                </div>
            </div>

            <ChevronRightIcon :class="[
                'w-5 h-5 text-gray-400 transition-transform duration-300 ease-in-out',
                isOpen ? 'rotate-90' : ''
            ]" />
        </div>

        <transition name="expand" @enter="onEnter" @after-enter="onAfterEnter" @leave="onLeave">
            <div v-if="isOpen" class="overflow-hidden">
                <div class="mt-4 border-t pt-4">
                    <p class="text-center text-gray-500 text-sm mb-2">Trình độ</p>

                    <div class="flex justify-around items-center">
                        <div class="flex flex-col items-center">
                            <span class="px-3 py-1 bg-blue-500 text-white rounded text-xs font-medium">DUPR</span>
                            <p class="mt-1 text-xl font-semibold text-gray-800">{{ dupr }}</p>
                        </div>

                        <div class="flex flex-col items-center">
                            <span class="px-3 py-1 bg-red-500 text-white rounded text-xs font-medium">PICKI</span>
                            <p class="mt-1 text-xl font-semibold text-gray-800">{{ vndupr }}</p>
                        </div>

                        <div class="flex flex-col items-center">
                            <span class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-xs font-medium">Điểm tự đánh
                                giá</span>
                            <p class="mt-1 text-xl font-semibold text-gray-800">{{ selfScore }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ChevronRightIcon } from "@heroicons/vue/24/outline";

defineProps({
    title: String,
    subtitle: String,
    icon: String,
    dupr: Number,
    vndupr: Number,
    selfScore: Number,
    isOpen: Boolean
});

const emit = defineEmits(["toggle"]);

const onEnter = (el) => {
    el.style.height = '0';
    el.style.opacity = '0';
    el.offsetHeight;
    el.style.transition = 'height 0.3s ease-out, opacity 0.3s ease-out';
    el.style.height = el.scrollHeight + 'px';
    el.style.opacity = '1';
};

const onAfterEnter = (el) => {
    el.style.height = 'auto';
};

const onLeave = (el) => {
    el.style.height = el.scrollHeight + 'px';
    el.offsetHeight;
    el.style.transition = 'height 0.3s ease-in, opacity 0.3s ease-in';
    el.style.height = '0';
    el.style.opacity = '0';
};
</script>

<style scoped>
.expand-enter-active,
.expand-leave-active {
    transition: height 0.3s ease, opacity 0.3s ease;
}
</style>