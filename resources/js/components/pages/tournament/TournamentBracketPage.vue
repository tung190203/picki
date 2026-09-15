<template>
    <div class="p-4 max-w-8xl mx-auto">
        <div class="flex items-center gap-4 mb-4">
            <ArrowLeftIcon class="w-6 h-6 text-gray-500 dark:text-slate-400 hover:text-[#D72D36] cursor-pointer" @click="goBack" />
            <h1 class="text-lg font-semibold text-gray-800 dark:text-slate-100">Sơ đồ thi đấu</h1>
        </div>
        <template v-if="bracket && bracket.format == 1">
            <BracketMixed :bracket="bracket" :tournament="tournament" :rank="ranks" @refresh="loadBracketData" />
        </template>
        <template v-else-if="bracket && bracket.format == 2">
            <BracketElimination :bracket="bracket" :tournament="tournament" :rank="ranks" @refresh="loadBracketData" />
        </template>
        <template v-else>
            <BracketRounded :bracket="bracket" :tournament="tournament" :rank="ranks" @refresh="loadBracketData" />
        </template>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import { toast } from 'vue3-toastify';
import { ArrowLeftIcon } from '@heroicons/vue/24/solid';
import { useRoute, useRouter } from 'vue-router';
import * as TournamentService from '@/service/tournament.js'
import * as TournamentTypeService from '@/service/tournamentType.js'
import BracketElimination from '@/components/molecules/BracketElimination.vue';
import BracketMixed from '@/components/molecules/BracketMixed.vue';
import BracketRounded from '@/components/molecules/BracketRounded.vue';

const route = useRoute();
const router = useRouter();
const id = route.params.id;
const tournament = ref([])
const bracket = ref([])
const ranks = ref([])

const detailTournament = async (tournamentId) => {
    try {
        const response = await TournamentService.getTournamentById(tournamentId)
        tournament.value = response
        if (tournament.value && tournament.value.tournament_types && tournament.value.tournament_types[0]) {
            await getBracket(tournament.value.tournament_types[0].id)
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'Lấy thông tin giải đấu thất bại');
    }
}

const getBracket = async (tournamentTypeId) => {
    try {
        const response = await TournamentTypeService.getBracketByTournamentTypeId(tournamentTypeId)
        bracket.value = response
    } catch (error) {
        toast.error(error.response?.data?.message || 'Lấy sơ đồ thi đấu thất bại');
    }
}

const loadBracketData = async () => {
    if (tournament.value && tournament.value.tournament_types && tournament.value.tournament_types[0]) {
        await getBracket(tournament.value.tournament_types[0].id)
        await getRanks()
    }
}

const getRanks = async () => {
    try {
        const response = await TournamentTypeService.getRanks(id)
        ranks.value = response
    } catch (error) {
        toast.error(error.response?.data?.message || 'Lấy bảng xếp hạng thất bại');
    }
}
onMounted(async () => {
    if (id) {
        await detailTournament(id)
        await getRanks()
    }
});

const goBack = () => {
  router.push({
    name: 'tournament-detail',
    params: { id: id },
    query: {
      tab: route.query.tab
    }
  })
}
</script>

<style scoped></style>
