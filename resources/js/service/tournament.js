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
