/**
 * Phase 5 — Rally Scoring display composable.
 * Mirror of TournamentScoringTypeInferrer logic from backend.
 *
 * Usage:
 *   const { isRally, scoringType } = useRallyScoringDisplay(tournamentData)
 *   const { isRally: isRally2, lastStage } = useMatchRallyDisplay(match)
 */

import { computed } from 'vue'
import { inferScoringType, RALLY } from '@/composables/rallyScoring.js'

/**
 * Detect whether a tournament is Rally Scoring based on tournament data.
 * @param {Object|null} tournamentData - tournament object with player_per_team
 */
export function useRallyScoringDisplay(tournamentData) {
  const playerPerTeam = computed(() => tournamentData?.value?.player_per_team ?? null)
  const scoringType = computed(() => inferScoringType(playerPerTeam.value))
  const isRally = computed(() => scoringType.value === RALLY)
  return { isRally, scoringType }
}

/**
 * Extract Rally Scoring stage data from a match object.
 * Works with both list-level match data and detail-level match data.
 * @param {Object} match - match object, may have rally_stages or stages
 */
export function useMatchRallyDisplay(match) {
  /**
   * rally_stages from MatchDetailResource (list/detail).
   * Also accepts stages from getStages API response.
   */
  const rallyStages = computed(() => {
    return match.value?.rally_stages ?? match.value?.stages ?? []
  })

  const hasRallyData = computed(() => rallyStages.value.length > 0)

  /**
   * Last completed stage (or last stage with data) for aggregate display.
   */
  const lastStage = computed(() => {
    if (!rallyStages.value.length) return null
    return [...rallyStages.value].sort((a, b) => a.stage_no - b.stage_no).at(-1)
  })

  /**
   * Last completed stage only (for winner determination).
   */
  const lastCompletedStage = computed(() => {
    const completed = rallyStages.value
      .filter(s => s.status === 'completed')
      .sort((a, b) => a.stage_no - b.stage_no)
    return completed.at(-1) ?? null
  })

  const rallyHomeScore = computed(() => lastStage.value?.cumulative_score_a ?? null)
  const rallyAwayScore = computed(() => lastStage.value?.cumulative_score_b ?? null)

  /**
   * Determine if home team won the match based on rally stages.
   */
  const rallyHomeWinner = computed(() => {
    if (!lastCompletedStage.value) return false
    return lastCompletedStage.value.winner_team_id === match.value?.home_team?.id
  })

  const rallyAwayWinner = computed(() => {
    if (!lastCompletedStage.value) return false
    return lastCompletedStage.value.winner_team_id === match.value?.away_team?.id
  })

  return {
    rallyStages,
    hasRallyData,
    lastStage,
    lastCompletedStage,
    rallyHomeScore,
    rallyAwayScore,
    rallyHomeWinner,
    rallyAwayWinner,
  }
}
