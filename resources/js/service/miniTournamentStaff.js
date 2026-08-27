import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const miniTournamentStaffEndpoint = API_ENDPOINT.MINI_TOURNAMENT_STAFF;

/**
 * Thêm thành viên vào kèo.
 *
 * RBAC v2: role bắt buộc (1=Admin/organizer, 2=BTC/staff, 3=Trọng tài/referee)
 *
 * @param {Number} miniTournamentId
 * @param {Number} staffId - user_id của người được thêm
 * @param {Number} role - 1|2|3
 */
export const addMiniTournamentStaff = async (miniTournamentId, staffId, role) => {
  return axiosInstance.post(`${miniTournamentStaffEndpoint}/add/${miniTournamentId}`, {
    staff_id: staffId,
    role: role,
  }).then((response) => response.data.data)
}

/**
 * @deprecated Dùng addMiniTournamentStaff(id, staffId, role=3) — giữ cho backward-compat.
 */
export const addMiniTournamentReferee = async (miniTournamentId, userId) => {
  return addMiniTournamentStaff(miniTournamentId, userId, 3)
}

export const removeMiniTournamentStaff = async (miniTournamentId, staffId) => {
  return axiosInstance.delete(`${miniTournamentStaffEndpoint}/${miniTournamentId}/${staffId}`)
}