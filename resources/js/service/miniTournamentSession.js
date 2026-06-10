import axiosInstance from '@/utils/httpRequest.js';
import { API_ENDPOINT } from '@/constants/index.js';

const baseEndpoint = API_ENDPOINT.MINI_TOURNAMENT;

export const updatePlayerGroup = async (id, participantIds) => {
  return axiosInstance.put(`${baseEndpoint}/${id}/player-group`, { participant_ids: participantIds })
    .then((response) => response.data);
};

export const startSession = async (id, scheduledCourtCount = 2) => {
  return axiosInstance.post(`${baseEndpoint}/${id}/start-session`, {
    scheduled_court_count: scheduledCourtCount,
  }).then((response) => response.data);
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
