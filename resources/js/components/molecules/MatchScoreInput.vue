<template>
    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-3">{{ label }}</label>

        <div v-for="(score, index) in localScores" :key="index" class="mb-4">
            <div class="grid grid-cols-[2fr_1fr_2fr] gap-4 items-center">
                <div class="border border-1 border-[#DCDEE6] rounded-lg p-3">
                    <button @click="incrementScore(index, '1')"
                            class="w-full bg-[#EDEEF2] rounded px-3 py-2 text-gray-600 hover:bg-gray-300 transition-colors mb-2 flex items-center justify-center">
                        <PlusIcon class="w-5 h-5" />
                    </button>
                    <div class="text-center text-2xl font-bold mb-2">{{ score.team1 }}</div>
                    <button @click="decrementScore(index, '1')"
                            class="w-full bg-[#EDEEF2] rounded px-3 py-2 text-gray-600 hover:bg-gray-300 transition-colors flex items-center justify-center">
                        <MinusIcon class="w-5 h-5" />
                    </button>
                </div>

                <div class="flex flex-col items-center gap-2">
                    <span class="text-sm font-semibold">Set {{ index + 1 }}</span>
                    <button v-if="localScores.length > 1 && canEdit" @click="removeSet(index)"
                            class="text-red-500 hover:text-red-700 transition-colors">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <div class="border border-1 border-[#DCDEE6] rounded-lg p-3">
                    <button @click="incrementScore(index, '2')"
                            class="w-full bg-[#EDEEF2] rounded px-3 py-2 text-gray-600 hover:bg-gray-300 transition-colors mb-2 flex items-center justify-center">
                        <PlusIcon class="w-5 h-5" />
                    </button>
                    <div class="text-center text-2xl font-bold mb-2">{{ score.team2 }}</div>
                    <button @click="decrementScore(index, '2')"
                            class="w-full bg-[#EDEEF2] rounded px-3 py-2 text-gray-600 hover:bg-gray-300 transition-colors flex items-center justify-center">
                        <MinusIcon class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </div>

        <button v-if="canEdit" @click="addSet"
                class="w-full flex justify-center items-center gap-2 border p-3 rounded-lg text-[#838799] hover:bg-gray-100 transition-colors mb-4">
            <PlusIcon class="w-5 h-5" />
            <span class="text-sm font-semibold">Thêm hiệp</span>
        </button>

        <button v-if="canEdit" @click="$emit('open-referee')"
                class="w-full flex justify-center items-center gap-2 border-2 border-red-400 p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors font-semibold">
            <ClipboardIcon class="w-5 h-5" />
            <span class="text-sm">Nhập điểm trọng tài</span>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { MinusIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/solid'
import { ClipboardIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: {
        type: Array,
        required: true,
        default: () => [{ team1: 0, team2: 0 }]
    },
    canEdit: {
        type: Boolean,
        default: true
    },
    label: {
        type: String,
        default: 'Kết quả'
    }
})

const emit = defineEmits(['update:modelValue', 'open-referee'])

const localScores = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

const SCORE_UI_MAX = 999

const incrementScore = (idx, team) => {
    const newScores = [...localScores.value]
    newScores[idx] = { ...newScores[idx] }
    
    if (team === '1' && newScores[idx].team1 < SCORE_UI_MAX) newScores[idx].team1++
    if (team === '2' && newScores[idx].team2 < SCORE_UI_MAX) newScores[idx].team2++
    localScores.value = newScores
}

const decrementScore = (idx, team) => {
    const newScores = [...localScores.value]
    newScores[idx] = { ...newScores[idx] }

    if (team === '1' && newScores[idx].team1 > 0) newScores[idx].team1--
    if (team === '2' && newScores[idx].team2 > 0) newScores[idx].team2--
    localScores.value = newScores
}

const addSet = () => {
    const newScores = [...localScores.value]
    newScores.push({ team1: 0, team2: 0 })
    localScores.value = newScores
}

const removeSet = (idx) => {
    const newScores = [...localScores.value]
    if (newScores.length > 1) {
        newScores.splice(idx, 1)
        localScores.value = newScores
    }
}
</script>
