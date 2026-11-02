/**
 * Mirror of App\Services\TournamentScoringTypeInferrer (PHP).
 *
 * Keep these constants aligned with the backend. The FE is allowed to render
 * a quick client-side validation hint, but the server is the source of truth.
 */

export const SIDE_OUT = 'side_out'
export const RALLY = 'rally'

export const ALLOWED_PLAYER_PER_TEAM = [2, 3, 4]
export const RALLY_PLAYER_THRESHOLD = 3

export const MIN_STAGES = 2
export const MAX_STAGES = 5
export const MIN_STAGE_GAP = 5
export const DEFAULT_STAGES = 3
export const DEFAULT_STAGE_VALUES = [10, 20, 30]

export function inferScoringType(playerPerTeam) {
  if (playerPerTeam == null) return null
  return playerPerTeam >= RALLY_PLAYER_THRESHOLD ? RALLY : SIDE_OUT
}

export function defaultStagePoints(count = DEFAULT_STAGES) {
    const n = Math.max(MIN_STAGES, Math.min(MAX_STAGES, count))
    const out = []
    for (let i = 0; i < n; i++) {
        out.push(DEFAULT_STAGE_VALUES[0] + i * 10)
    }
    return out
}

export function validateStagePoints(stagePoints) {
  if (!Array.isArray(stagePoints) || stagePoints.length === 0) {
    return { ok: false, error: 'Vui lòng nhập mốc điểm các chặng.' }
  }
  if (stagePoints.length < MIN_STAGES) {
    return { ok: false, error: `Số chặng tối thiểu là ${MIN_STAGES}.` }
  }
  if (stagePoints.length > MAX_STAGES) {
    return { ok: false, error: `Số chặng tối đa là ${MAX_STAGES}.` }
  }
  for (let i = 0; i < stagePoints.length; i++) {
    const v = Number(stagePoints[i])
    if (!Number.isInteger(v) || v < 1) {
      return { ok: false, error: `Mốc chặng ${i + 1} phải là số nguyên ≥ 1.` }
    }
    if (i > 0) {
      const prev = Number(stagePoints[i - 1])
      if (v <= prev) {
        return { ok: false, error: 'Các mốc điểm phải tăng nghiêm ngặt.' }
      }
      if (v - prev < MIN_STAGE_GAP) {
        return {
          ok: false,
          error: `Khoảng cách tối thiểu giữa 2 mốc liền kề là ${MIN_STAGE_GAP} điểm.`,
        }
      }
    }
  }
  return { ok: true, value: stagePoints.map((v) => Number(v)) }
}

export function resizeStagePoints(currentPoints, newCount) {
  const count = Math.max(MIN_STAGES, Math.min(MAX_STAGES, newCount))
  if (currentPoints.length === count) return currentPoints.slice()
  if (currentPoints.length < count) {
    const out = currentPoints.slice()
    let last = out[out.length - 1] ?? DEFAULT_STAGE_VALUES[0]
    while (out.length < count) {
      last = last + MIN_STAGE_GAP
      out.push(last)
    }
    return out
  }
  return currentPoints.slice(0, count)
}
