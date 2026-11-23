import axiosInstance from '@/utils/httpRequest.js';

export const getTournamentCourts = async (tournamentId) => {
  const res = await axiosInstance.get(`/tournaments/${tournamentId}/courts`);
  return res.data.data;
};

export const createTournamentCourt = async (tournamentId, payload = {}) => {
  const res = await axiosInstance.post(`/tournaments/${tournamentId}/courts`, payload);
  return res.data.data;
};

export const updateCourt = async (courtId, payload) => {
  const res = await axiosInstance.patch(`/courts/${courtId}`, payload);
  return res.data.data;
};

export const deleteCourt = async (courtId) => {
  const res = await axiosInstance.delete(`/courts/${courtId}`);
  return res.data;
};

export const copyTournamentCourts = async (tournamentId, fromTournamentId) => {
  const res = await axiosInstance.post(`/tournaments/${tournamentId}/courts/copy`, {
    from_tournament_id: fromTournamentId,
  });
  return res.data.data;
};

export const getCourtBoard = async (tournamentId) => {
  const res = await axiosInstance.get(`/tournaments/${tournamentId}/matches`);
  return res.data.data;
};

export const saveCourtBoardUnit = async (tournamentId, payload) => {
  const res = await axiosInstance.put(`/tournaments/${tournamentId}/court-board/units`, payload);
  return res.data.data;
};

export const autoAssignCourtBoard = async (tournamentId) => {
  const res = await axiosInstance.post(`/tournaments/${tournamentId}/court-board/auto`);
  return res.data.data;
};

export const getRefereeTournaments = async () => {
  const res = await axiosInstance.get('/referee/tournaments');
  return res.data.data;
};

export const getRefereeMatches = async (tournamentIds) => {
  const ids = Array.isArray(tournamentIds) ? tournamentIds.join(',') : tournamentIds;
  const res = await axiosInstance.get('/referee/matches', {
    params: { tournament_ids: ids },
  });
  return res.data.data;
};

export const claimMatch = async (matchId) => {
  const res = await axiosInstance.post(`/matches/${matchId}/claim`);
  return res.data.data;
};

export const moveMatch = async (matchId, payload) => {
  const res = await axiosInstance.post(`/matches/${matchId}/move`, payload);
  return res.data.data;
};

export const reassignMatch = async (matchId, refereeUserId) => {
  const res = await axiosInstance.post(`/matches/${matchId}/reassign`, {
    referee_user_id: refereeUserId,
  });
  return res.data.data;
};

export const getFreeMatches = async (params = {}) => {
  const res = await axiosInstance.get('/free-matches', { params });
  return res.data.data;
};

export const createFreeMatch = async (payload) => {
  const res = await axiosInstance.post('/free-matches', payload);
  return res.data.data;
};

export const updateFreeMatch = async (id, payload) => {
  const res = await axiosInstance.patch(`/free-matches/${id}`, payload);
  return res.data.data;
};
