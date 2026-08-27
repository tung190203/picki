import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const tournamentStaffEndpoint = API_ENDPOINT.TOURNAMENT_STAFF;

/**
 * Thêm thành viên vào giải đấu (RBAC v2).
 *
 * @param {Number} tournamentId
 * @param {Number} userId - user_id của người được thêm
 * @param {Number} role - 1=Admin/organizer, 2=BTC/staff, 3=Trọng tài/referee (optional)
 * @param {Number|null} courtId - chỉ áp dụng khi role=3, scope trọng tài theo sân (optional)
 */
export const addTournamentStaff = async (tournamentId, userId, role = null, courtId = null) => {
  const payload = { user_id: userId }
  if (role !== null) payload.role = role
  if (courtId !== null) payload.court_id = courtId

  return axiosInstance.post(`${tournamentStaffEndpoint}/add/${tournamentId}`, payload)
    .then((response) => response.data)
}

/**
 * Backward-compat: chỉ thêm trọng tài.
 * @param {Number} tournamentId
 * @param {Number} userId
 * @param {Number|null} courtId - optional, scope theo sân
 */
export const addReferee = async (tournamentId, userId, courtId = null) => {
  const payload = { user_id: userId }
  if (courtId !== null) payload.court_id = courtId
  return axiosInstance.post(`${tournamentStaffEndpoint}/add-referee/${tournamentId}`, payload)
    .then((response) => response.data)
}

export const removeTournamentStaff = async (tournamentId, tournamentStaffId) => {
  return axiosInstance.delete(`${tournamentStaffEndpoint}/${tournamentId}`, {
    data: { tournament_staff_id: tournamentStaffId },
  }).then((response) => response.data)
}