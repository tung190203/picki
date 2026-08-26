<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="modelValue"
                 class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[10001] p-4"
                 @click.self="close">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
                    <!-- Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-800">
                            Cài đặt gợi ý trận
                        </h3>
                        <button @click="close" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Settings List -->
                    <div class="p-6 space-y-4">
                        <!-- fair_play -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">
                                Mỗi người đều được chơi số trận bằng nhau
                            </span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="localSettings.fair_play"
                                    @change="updateSetting('fair_play', $event.target.checked)"
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                            </label>
                        </div>

                        <!-- balance_team -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">
                                Ưu tiên cân trình độ giữa 2 đội
                            </span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="localSettings.balance_team"
                                    @change="updateSetting('balance_team', $event.target.checked)"
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                            </label>
                        </div>

                        <!-- prefer_high_tier_match -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">
                                Ưu tiên trận "căng tay"
                            </span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="localSettings.prefer_high_tier_match"
                                    @change="updateSetting('prefer_high_tier_match', $event.target.checked)"
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                            </label>
                        </div>

                        <!-- prevent_three_consecutive -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700 font-semibold">
                                KHÔNG ai đánh liên 3 trận
                            </span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="localSettings.prevent_three_consecutive"
                                    @change="updateSetting('prevent_three_consecutive', $event.target.checked)"
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                            </label>
                        </div>

                        <!-- organizer_as_backup -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">
                                Người của BTC là player backup
                            </span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="localSettings.organizer_as_backup"
                                    @change="updateSetting('organizer_as_backup', $event.target.checked)"
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script>
import { ref, watch } from 'vue';

export default {
    name: 'MatchSuggestionSettingsModal',
    props: {
        modelValue: {
            type: Boolean,
            default: false
        },
        settings: {
            type: Object,
            default: () => ({
                fair_play: true,
                balance_team: true,
                prefer_high_tier_match: true,
                prevent_three_consecutive: true,
                organizer_as_backup: false
            })
        }
    },
    emits: ['update:modelValue', 'update:settings'],
    setup(props, { emit }) {
        const localSettings = ref({ ...props.settings });

        watch(() => props.settings, (newSettings) => {
            localSettings.value = { ...newSettings };
        }, { deep: true });

        const close = () => {
            emit('update:modelValue', false);
        };

        const updateSetting = (key, value) => {
            localSettings.value[key] = value;
            emit('update:settings', { ...localSettings.value });
        };

        return {
            localSettings,
            close,
            updateSetting
        };
    }
}
</script>

<style scoped>
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.3s;
}
.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
</style>
