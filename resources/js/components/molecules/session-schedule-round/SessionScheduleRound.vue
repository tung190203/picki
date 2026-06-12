<template>
    <div class="space-y-3">
        <!-- Matches list -->
        <div class="space-y-2">
            <div v-for="match in round.matches" :key="match.id"
                @click="match.is_bye ? null : $emit('match-click', match)"
                class="rounded-lg px-4 py-3 text-xs border-2 transition-all cursor-pointer"
                :class="[
                    match.is_bye
                        ? 'bg-gray-50 border-gray-200 opacity-60 cursor-default'
                        : getMatchCardClass(match)
                ]">

                <!-- Bye badge -->
                <div v-if="match.is_bye" class="text-center py-2">
                    <span class="text-[10px] text-gray-400 font-medium">BYE — nghỉ vòng này</span>
                </div>

                <!-- Main match content -->
                <div v-else>
                    <!-- Team row -->
                    <div class="flex items-center justify-between gap-2">
                        <!-- Team 1 -->
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <!-- Avatars stacked side-by-side -->
                            <div class="flex -space-x-1.5 shrink-0">
                                <div v-for="(member, mi) in (match.team1?.members || []).slice(0, 2)" :key="mi"
                                    class="w-7 h-7 rounded-full bg-red-50 border-2 border-white flex items-center justify-center overflow-hidden">
                                    <img v-if="member.avatar_url" :src="member.avatar_url" class="w-full h-full object-cover" alt="">
                                    <span class="text-[9px] font-bold text-red-500">
                                        {{ (member.full_name || '?').charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                                <div v-if="!match.team1?.members?.length"
                                    class="w-7 h-7 rounded-full bg-gray-100 border-2 border-white flex items-center justify-center">
                                    <span class="text-[9px] font-bold text-gray-400">?</span>
                                </div>
                            </div>
                            <!-- Names column (stacked, one per line) -->
                            <div class="flex flex-col min-w-0 flex-1">
                                <div class="flex flex-col">
                                    <span v-for="(member, mi) in (match.team1?.members || [])" :key="mi"
                                        class="text-sm font-medium text-[#3E414C] truncate leading-tight"
                                        :class="{ 'text-gray-400': isMatchCompleted(match) }">
                                        {{ member.full_name || 'TBD' }}
                                    </span>
                                    <span v-if="!match.team1?.members?.length" class="text-sm font-medium text-gray-400 truncate">
                                        TBD
                                    </span>
                                </div>
                                <!-- VN DUP per member -->
                                <div v-if="(match.team1?.members || []).length > 0"
                                    class="flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5">
                                    <span v-for="(member, mi) in (match.team1?.members || [])" :key="mi"
                                        class="text-[10px] text-gray-400 leading-tight">
                                        VN DUP: {{ getVnduprScore(member) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Score / VS -->
                        <div class="flex items-center gap-1.5 shrink-0">
                            <template v-if="match.status === 'completed' && match.score_1 != null">
                                <span class="text-sm font-bold"
                                    :class="match.score_1 > match.score_2 ? 'text-green-600' : 'text-gray-400'">
                                    {{ match.score_1 ?? 0 }}
                                </span>
                                <span class="text-gray-300">-</span>
                                <span class="text-sm font-bold"
                                    :class="match.score_2 > match.score_1 ? 'text-green-600' : 'text-gray-400'">
                                    {{ match.score_2 ?? 0 }}
                                </span>
                            </template>
                            <template v-else-if="match.status === 'disputed'">
                                <span class="text-[10px] px-2 py-0.5 bg-amber-100 text-amber-600 rounded-full">Tranh chấp</span>
                            </template>
                            <template v-else>
                                <span class="text-[10px] text-gray-400 px-1.5 py-0.5 bg-gray-100 rounded font-medium">vs</span>
                            </template>
                        </div>

                        <!-- Team 2 -->
                        <div class="flex items-center gap-2 flex-1 min-w-0 justify-end">
                            <!-- Names column (stacked, right-aligned) -->
                            <div class="flex flex-col min-w-0 flex-1 items-end">
                                <div class="flex flex-col items-end">
                                    <span v-for="(member, mi) in [...(match.team2?.members || [])]" :key="mi"
                                        class="text-sm font-medium text-[#3E414C] truncate leading-tight text-right"
                                        :class="{ 'text-gray-400': isMatchCompleted(match) }">
                                        {{ member.full_name || 'TBD' }}
                                    </span>
                                    <span v-if="!match.team2?.members?.length" class="text-sm font-medium text-gray-400 truncate text-right">
                                        TBD
                                    </span>
                                </div>
                                <!-- VN DUP per member -->
                                <div v-if="(match.team2?.members || []).length > 0"
                                    class="flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5 justify-end">
                                    <span v-for="(member, mi) in (match.team2?.members || [])" :key="mi"
                                        class="text-[10px] text-gray-400 leading-tight">
                                        VN DUP: {{ getVnduprScore(member) }}
                                    </span>
                                </div>
                            </div>
                            <!-- Avatars -->
                            <div class="flex -space-x-1.5 shrink-0">
                                <div v-for="(member, mi) in [...(match.team2?.members || [])].slice(0, 2)" :key="mi"
                                    class="w-7 h-7 rounded-full bg-blue-50 border-2 border-white flex items-center justify-center overflow-hidden">
                                    <img v-if="member.avatar_url" :src="member.avatar_url" class="w-full h-full object-cover" alt="">
                                    <span class="text-[9px] font-bold text-blue-500">
                                        {{ (member.full_name || '?').charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                                <div v-if="!match.team2?.members?.length"
                                    class="w-7 h-7 rounded-full bg-gray-100 border-2 border-white flex items-center justify-center">
                                    <span class="text-[9px] font-bold text-gray-400">?</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom row: court/time + status badge -->
                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                        <div class="flex items-center gap-3">
                            <span v-if="match.yard_number" class="text-[10px] text-gray-400">
                                Sân {{ match.yard_number }}
                            </span>
                            <span v-if="match.scheduled_at" class="text-[10px] text-gray-400">
                                {{ formatTime(match.scheduled_at) }}
                            </span>
                        </div>
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold"
                            :class="getMatchStatusBadgeClass(match)">
                            {{ getMatchStatusLabel(match) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>

export default {
    name: 'SessionScheduleRound',
    props: {
        round: {
            type: Object,
            required: true,
        },
    },
    emits: ['match-click'],
    setup(props) {
        const getMatchCardClass = (match) => {
            if (match.status === 'completed') {
                return 'bg-green-50 border-green-400 hover:shadow-md'
            }
            if (match.status === 'going_on') {
                return 'bg-red-50 border-red-400 hover:shadow-md'
            }
            if (match.status === 'waiting_confirm') {
                return 'bg-yellow-50 border-yellow-400 hover:shadow-md'
            }
            // pending but has scores entered (unsaved)
            if (hasUnsavedScores(match)) {
                return 'bg-orange-50 border-orange-400 hover:shadow-md'
            }
            // plain pending
            return 'bg-white border-gray-200 hover:shadow-sm hover:border-gray-300'
        }

        const hasUnsavedScores = (match) => {
            // Check if match has results_by_sets with scores > 0
            if (match.results_by_sets && Object.keys(match.results_by_sets).length > 0) {
                for (const set of Object.values(match.results_by_sets)) {
                    if (Array.isArray(set)) {
                        for (const r of set) {
                            if (r.score > 0) return true
                        }
                    }
                }
            }
            return false
        }

        const isMatchCompleted = (match) => {
            return match.status === 'completed'
        }

        const getMatchStatusBadgeClass = (match) => {
            if (match.status === 'completed') {
                return 'bg-green-100 text-green-700'
            }
            if (match.status === 'going_on') {
                return 'bg-red-100 text-red-600'
            }
            if (match.status === 'waiting_confirm') {
                return 'bg-yellow-100 text-yellow-600'
            }
            if (hasUnsavedScores(match)) {
                return 'bg-orange-100 text-orange-600'
            }
            return 'bg-gray-100 text-gray-500'
        }

        const getMatchStatusLabel = (match) => {
            if (match.status === 'completed') return 'Đã xác nhận'
            if (match.status === 'going_on') return 'Đang đấu'
            if (match.status === 'waiting_confirm') return 'Chờ xác nhận'
            if (hasUnsavedScores(match)) return 'Chưa lưu'
            return 'Chờ'
        }

        const formatTime = (dateString) => {
            if (!dateString) return ''
            const date = new Date(dateString)
            return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
        }

        const getVnduprScore = (member) => {
            if (!member?.user?.sports?.length) return '-'
            const sport = member.user.sports[0]
            if (!sport?.scores) return '-'
            if (Array.isArray(sport.scores)) {
                const scoreRecord = sport.scores.find(sc => sc?.score_type === 'vndupr_score')
                return scoreRecord?.score_value ?? '-'
            }
            return sport.scores.vndupr_score ?? '-'
        }

        return {
            getMatchCardClass,
            hasUnsavedScores,
            isMatchCompleted,
            getMatchStatusBadgeClass,
            getMatchStatusLabel,
            formatTime,
            getVnduprScore,
        }
    },
}
</script>
