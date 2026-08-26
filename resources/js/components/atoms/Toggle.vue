<template>
    <div :class="label || description ? 'flex items-center justify-between py-2' : 'inline-flex items-center'">
        <div class="flex-1 pr-4" v-if="label || description">
            <div v-if="label" class="text-sm font-medium text-gray-900 dark:text-slate-200">{{ label }}</div>
            <div v-if="description" class="text-xs text-gray-500 dark:text-slate-400 mt-1">{{ description }}</div>
        </div>
        <button type="button" @click="toggle"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full p-[2px] border-0 outline-none transition-colors duration-200 ease-in-out"
            :class="[
                isChecked ? 'bg-[#D72D36] shadow-sm' : 'bg-gray-300 dark:bg-[#3B4A63]',
                disabled ? 'opacity-50 cursor-not-allowed' : ''
            ]">
            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white !bg-white shadow-md border-0 transition-transform duration-200 ease-in-out"
                :class="isChecked ? 'translate-x-[20px]' : 'translate-x-0'" />
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: undefined
    },
    value: {
        type: Boolean,
        default: undefined
    },
    label: {
        type: String,
        default: ''
    },
    description: {
        type: String,
        default: ''
    },
    disabled: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'update']);

const isChecked = computed(() => {
    if (props.modelValue !== undefined) return props.modelValue;
    return props.value;
});

const toggle = () => {
    if (props.disabled) return;
    const newValue = !isChecked.value;
    emit('update:modelValue', newValue);
    emit('update', newValue);
};

</script>