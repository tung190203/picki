<template>
    <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
        <!-- Header: Court và Badge -->
        <div class="flex items-center justify-between mb-6">
            <div v-if="courtNumber" class="text-sm font-semibold text-gray-600">
                SÂN {{ courtNumber }}
            </div>
            <div v-else class="text-sm text-gray-400">
                Chưa phân sân
            </div>
            
            <div v-if="match?.is_high_tier_match" class="bg-red-100 text-red-700 text-xs font-bold px-3 py-1 rounded-full">
                CĂNG TAY
            </div>
        </div>

        <!-- Match Card: Team1 VS Team2 -->
        <div class="grid grid-cols-[2fr_auto_2fr] gap-6 items-center">
            <!-- Team 1 -->
            <div class="flex flex-col space-y-2">
                <div v-for="member in match?.team1?.members || []" :key="member.id" class="text-gray-800 font-medium">
                    {{ member.full_name }}
                </div>
                <div v-if="!match?.team1?.members?.length" class="text-gray-400">
                    Chưa có đội
                </div>
            </div>

            <!-- VS -->
            <div class="text-2xl font-bold text-gray-400">
                VS
            </div>

            <!-- Team 2 -->
            <div class="flex flex-col space-y-2">
                <div v-for="member in match?.team2?.members || []" :key="member.id" class="text-gray-800 font-medium">
                    {{ member.full_name }}
                </div>
                <div v-if="!match?.team2?.members?.length" class="text-gray-400">
                    Chưa có đội
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SuggestedMatchCard',
    props: {
        match: {
            type: Object,
            default: null
        },
        courtNumber: {
            type: Number,
            default: null
        }
    }
}
</script>
