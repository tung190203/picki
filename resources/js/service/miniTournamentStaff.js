import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const miniTournamentStaffEndpoint = API_ENDPOINT.MINI_TOURNAMENT_STAFF;

export const addMiniTournamentStaff = async (miniTournamentId, staffId) => {
  return axiosInstance.post(`${miniTournamentStaffEndpoint}/add/${miniTournamentId}`, {
    staff_id: staffId,
  }).then((response) => response.data.data)
}

export const addMiniTournamentReferee = async (miniTournamentId, userId) => {
  return axiosInstance.post(`${miniTournamentStaffEndpoint}/add/${miniTournamentId}`, {
    staff_id: userId,
  }).then((response) => response.data.data)
}

export const removeMiniTournamentStaff = async (miniTournamentId, staffId) => {
  return axiosInstance.delete(`${miniTournamentStaffEndpoint}/${miniTournamentId}/${staffId}`)
}
