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
                                    <div v-for="(member, mi) in (match.team1?.members || [])" :key="mi"
                                        class="flex items-center gap-1">
                                        <span class="text-sm font-medium text-[#3E414C] truncate leading-tight"
                                            :class="{ 'text-gray-400': isMatchCompleted(match) }">
                                            {{ member.full_name || 'TBD' }}
                                        </span>
                                        <span class="text-[10px] text-blue-500 shrink-0">
                                            ({{ getVnduprScore(member) }})
                                        </span>
                                    </div>
                                    <span v-if="!match.team1?.members?.length" class="text-sm font-medium text-gray-400 truncate">
                                        TBD
                                    </span>
                                </div>
                            </div>
                            <div v-if="isMatchCompleted(match) && match.winner_id == match.team1?.id"
                                class="ml-1 text-[10px] px-1.5 py-0.5 bg-green-100 text-green-600 rounded font-semibold shrink-0">
                                Thắng
                            </div>
                        </div>

                        <!-- Score / VS -->
                        <div class="flex items-center gap-1.5 shrink-0 flex-col">
                            <!-- Per-set scores -->
                            <div v-if="match.results_by_sets && Object.keys(match.results_by_sets).length > 0" class="flex flex-col items-center gap-0.5">
                                <div class="flex items-center gap-1">
                                    <template v-for="(setKey, idx) in getOrderedSetKeys(match)" :key="setKey">
                                        <div class="flex items-center gap-0.5">
                                            <span class="text-[9px] text-gray-400 leading-none">{{ formatSetLabel(setKey) }}</span>
                                            <span class="text-[10px] font-bold"
                                                :class="getTeamSetScoreClass(match, setKey, 'team1')">
                                                {{ getSetScore(match, setKey, 'team1') }}
                                            </span>
                                            <span class="text-[8px] text-gray-300">-</span>
                                            <span class="text-[10px] font-bold"
                                                :class="getTeamSetScoreClass(match, setKey, 'team2')">
                                                {{ getSetScore(match, setKey, 'team2') }}
                                            </span>
                                        </div>
                                        <span v-if="idx < getOrderedSetKeys(match).length - 1" class="text-[8px] text-gray-300 mx-0.5">|</span>
                                    </template>
                                </div>
                            </div>
                            <!-- Fallback: aggregate score -->
                            <template v-else-if="match.status === 'completed' && match.score_1 != null">
                                <span class="text-sm font-bold"
                                    :class="match.score_1 > match.score_2 ? 'text-green-600' : 'text-gray-400'">
                                    {{ match.score_1 ?? 0 }}
                                </span>
                                <span class="text-gray-300 text-xs">-</span>
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
                                    <div v-for="(member, mi) in [...(match.team2?.members || [])]" :key="mi"
                                        class="flex items-center gap-1">
                                        <span class="text-sm font-medium text-[#3E414C] truncate leading-tight text-right"
                                            :class="{ 'text-gray-400': isMatchCompleted(match) }">
                                            {{ member.full_name || 'TBD' }}
                                        </span>
                                        <span class="text-[10px] text-blue-500 shrink-0">
                                            ({{ getVnduprScore(member) }})
                                        </span>
                                    </div>
                                    <span v-if="!match.team2?.members?.length" class="text-sm font-medium text-gray-400 truncate text-right">
                                        TBD
                                    </span>
                                </div>
                            </div>
                            <div v-if="isMatchCompleted(match) && match.winner_id == match.team2?.id"
                                class="mr-1 text-[10px] px-1.5 py-0.5 bg-green-100 text-green-600 rounded font-semibold shrink-0">
                                Thắng
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
            const user = member?.user
            if (!user) return '-'
            const scores = user.sports?.[0]?.scores
            if (!scores) return '-'
            const vndupr = parseFloat(scores.vndupr_score)
            const trinh = parseFloat(scores.trinh_score)
            const hasVndupr = !isNaN(vndupr) && vndupr > 0
            const hasTrinh = !isNaN(trinh) && trinh > 0
            if (hasVndupr && hasTrinh) return `${vndupr.toFixed(3)} / ${trinh.toFixed(3)}`
            if (hasVndupr) return vndupr.toFixed(3)
            if (hasTrinh) return trinh.toFixed(3)
            return '-'
        }

        const getOrderedSetKeys = (match) => {
            if (!match.results_by_sets) return []
            return Object.keys(match.results_by_sets).sort((a, b) => {
                const numA = parseInt(a.replace('set', ''))
                const numB = parseInt(b.replace('set', ''))
                return numA - numB
            })
        }

        const getSetScore = (match, setKey, team) => {
            const r = match.results_by_sets?.[setKey]
            if (!Array.isArray(r)) return '-'
            const teamId = team === 'team1' ? match.team1?.id : match.team2?.id
            const result = r.find(item => item.team?.id === teamId)
            return result?.score ?? '-'
        }

        const getTeamSetScoreClass = (match, setKey, team) => {
            const r = match.results_by_sets?.[setKey]
            if (!Array.isArray(r) || r.length < 2) return 'text-gray-400'
            const team1Id = match.team1?.id
            const team2Id = match.team2?.id
            const r1 = r.find(item => item.team?.id === team1Id)
            const r2 = r.find(item => item.team?.id === team2Id)
            const s1 = r1?.score ?? -1
            const s2 = r2?.score ?? -1
            if (s1 < 0 || s2 < 0) return 'text-gray-400'
            if (team === 'team1') {
                return s1 > s2 ? 'text-green-600' : 'text-gray-400'
            } else {
                return s2 > s1 ? 'text-green-600' : 'text-gray-400'
            }
        }

        const formatSetLabel = (setKey) => {
            const num = parseInt(setKey.replace('set', ''))
            const labels = ['Set 1', 'Set 2', 'Set 3']
            return labels[num - 1] || `Set ${num}`
        }

        return {
            getMatchCardClass,
            hasUnsavedScores,
            isMatchCompleted,
            getMatchStatusBadgeClass,
            getMatchStatusLabel,
            formatTime,
            getVnduprScore,
            getOrderedSetKeys,
            getSetScore,
            getTeamSetScoreClass,
            formatSetLabel,
        }
    },
}
</script>
