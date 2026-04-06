import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const tournamentStaffEndpoint = API_ENDPOINT.TOURNAMENT_STAFF;

export const addTournamentStaff = async (tournamentId, userId) => {
  return axiosInstance.post(`${tournamentStaffEndpoint}/add/${tournamentId}`, {
    user_id: userId,
  }).then((response) => response.data)
}

export const addReferee = async (tournamentId, userId) => {
  return axiosInstance.post(`${tournamentStaffEndpoint}/add-referee/${tournamentId}`, {
    user_id: userId,
  }).then((response) => response.data)
}

export const removeTournamentStaff = async (tournamentId, tournamentStaffId) => {
  return axiosInstance.delete(`${tournamentStaffEndpoint}/${tournamentId}`, {
    data: { tournament_staff_id: tournamentStaffId },
  }).then((response) => response.data)
}
