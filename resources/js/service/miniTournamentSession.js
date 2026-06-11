import axiosInstance from '@/utils/httpRequest.js';
import { API_ENDPOINT } from '@/constants/index.js';

const baseEndpoint = API_ENDPOINT.MINI_TOURNAMENT;

export const startSession = async (id, scheduledCourtCount = 2, participantIds = null, matchType = 'single') => {
    const payload = {
        scheduled_court_count: scheduledCourtCount,
        match_type: matchType,
    }
    if (participantIds) {
        payload.participant_ids = participantIds
    }
    return axiosInstance.put(`${baseEndpoint}/${id}/session`, payload)
        .then((response) => response.data);
};

export const getSchedule = async (id) => {
  return axiosInstance.get(`${baseEndpoint}/${id}/schedule`)
    .then((response) => response.data);
};

export const getLeaderboard = async (id) => {
  return axiosInstance.get(`${baseEndpoint}/${id}/leaderboard`)
    .then((response) => response.data);
};

export const finishSession = async (id) => {
  return axiosInstance.post(`${baseEndpoint}/${id}/finish-session`)
    .then((response) => response.data);
};

export const markAbsentPlayer = async (id, participantId, matchId) => {
  return axiosInstance.post(`${baseEndpoint}/${id}/mark-absent-player`, {
    participant_id: participantId,
    match_id: matchId,
  }).then((response) => response.data);
};

/**
 * Preview match/round counts before starting a session.
 * Does NOT call the backend — computes locally from participant data.
 *
 * @param {string} format - match format: 'partner_rotation' | 'mixed_gender' | 'rank_pairing'
 * @param {number} courtCount - number of courts (default 2)
 * @param {object} participantGroups - { male: [], female: [] } or { a: [], b: [] } or [] (for partner_rotation)
 * @param {number} totalParticipants - total confirmed participants (for partner_rotation)
 */
export const getMatchPreview = (format, courtCount = 2, participantGroups = {}, totalParticipants = 0) => {
  let totalMatches = 0;
  let totalRounds = 0;
  let unbalancedNotice = null;
  let groupSummary = null;

  if (format === 'partner_rotation') {
    const n = totalParticipants;
    totalMatches = (n * (n - 1)) / 2;
    totalRounds = Math.ceil(totalMatches / courtCount);

    const matchesPerPlayer = {};
    for (let i = 0; i < n; i++) {
      matchesPerPlayer[i] = n - 1;
    }
    groupSummary = { total: n };

    if (n === 6 || n === 7) {
      unbalancedNotice = `Số trận không đều: mỗi người đánh ${n - 1} trận.`;
    }
  } else if (format === 'mixed_gender') {
    const maleIds = participantGroups.male || [];
    const femaleIds = participantGroups.female || [];
    const m = maleIds.length;
    const f = femaleIds.length;
    totalMatches = m * f;
    totalRounds = Math.ceil(totalMatches / courtCount);
    groupSummary = { male: m, female: f };

    if (m !== f && m > 0 && f > 0) {
      unbalancedNotice = `Số trận chênh lệch: mỗi nam đánh ${f} trận, mỗi nữ đánh ${m} trận.`;
    }
  } else if (format === 'rank_pairing') {
    const aIds = participantGroups.a || [];
    const bIds = participantGroups.b || [];
    const na = aIds.length;
    const nb = bIds.length;
    totalMatches = na * nb;
    totalRounds = Math.ceil(totalMatches / courtCount);
    groupSummary = { a: na, b: nb };

    if (na !== nb && na > 0 && nb > 0) {
      unbalancedNotice = `Số trận chênh lệch: mỗi A đánh ${nb} trận, mỗi B đánh ${na} trận.`;
    }
  }

  return {
    total_matches: totalMatches,
    total_rounds: totalRounds,
    matches_per_round: courtCount,
    unbalanced_notice: unbalancedNotice,
    group_summary: groupSummary,
  };
};
