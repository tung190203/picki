import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const tournamentEndpoint = API_ENDPOINT.TOURNAMENT;

export const getTournaments = async (params) => {
  return axiosInstance.get(`${tournamentEndpoint}/index`, { params })
    .then((response) => response.data.data);
}

export const getTournamentById = async (id) => {
  return axiosInstance.get(`${tournamentEndpoint}/${id}`)
    .then((response) => response.data.data);
}

export const storeTournament = async (tournamentData) => {
  const hasClubId = tournamentData instanceof FormData
    ? tournamentData.has('club_id') && tournamentData.get('club_id')
    : tournamentData?.club_id;

  if (hasClubId) {
    const clubId = tournamentData instanceof FormData
      ? tournamentData.get('club_id')
      : tournamentData.club_id;
    return axiosInstance.post(`/clubs/${clubId}/tournaments`, tournamentData)
      .then((response) => response.data.data);
  }

  return axiosInstance.post(`${tournamentEndpoint}/store`, tournamentData)
    .then((response) => response.data.data);
}

export const updateTournament = async (id, tournamentData) => {
  return axiosInstance.post(`${tournamentEndpoint}/update/${id}`, tournamentData)
    .then((response) => response.data.data);
}

export const deleteTournament = async (id) => {
  return axiosInstance.post(`${tournamentEndpoint}/delete`, { id })
    .then((response) => response.data.data);
}

export const getBracketByTournamentId = async (tournamentId) => {
  return axiosInstance.get(`/tournament-detail/${tournamentId}/bracket`)
    .then((response) => response.data.data);
}

/**
 * Lấy ảnh background hiện tại của bracket modal cho giải đấu.
 * Trả về { bracket_background_url } - null nếu chưa có (sẽ dùng ảnh mặc định).
 */
export const getBracketBackground = async (tournamentId) => {
  return axiosInstance.get(`${tournamentEndpoint}/${tournamentId}/bracket-background`)
    .then((response) => response.data.data);
}

/**
 * Upload ảnh background mới cho bracket modal của giải đấu.
 * @param {number} tournamentId
 * @param {File} file - File ảnh (jpg/jpeg/png/webp, max 5MB)
 * @returns Promise<{ tournament_id, bracket_background_url, message }>
 */
export const updateBracketBackground = async (tournamentId, file) => {
  const formData = new FormData();
  formData.append('bracket_background', file);
  return axiosInstance.post(`${tournamentEndpoint}/${tournamentId}/bracket-background`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then((response) => response.data.data);
}

/**
 * Xoá ảnh background bracket (về mặc định).
 */
export const removeBracketBackground = async (tournamentId) => {
  const formData = new FormData();
  formData.append('remove_background', '1');
  return axiosInstance.post(`${tournamentEndpoint}/${tournamentId}/bracket-background`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then((response) => response.data.data);
}

// Templates
const tournamentTemplateEndpoint = '/tournament-templates';

export const getTournamentTemplates = async () => {
  return axiosInstance.get(tournamentTemplateEndpoint)
    .then((response) => response.data);
}

export const saveTournamentTemplate = async (payload) => {
  return axiosInstance.post(tournamentTemplateEndpoint, payload)
    .then((response) => response.data);
}

export const updateTournamentTemplate = async (id, payload) => {
  return axiosInstance.post(`${tournamentTemplateEndpoint}/${id}`, payload)
    .then((response) => response.data);
}

export const deleteTournamentTemplate = async (id) => {
  return axiosInstance.delete(`${tournamentTemplateEndpoint}/${id}`)
    .then((response) => response.data);
}
