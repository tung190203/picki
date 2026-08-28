import axiosInstance from "@/utils/httpRequest.js";

/**
 * Service riêng cho superadmin thao tác publish/unpublish/delete trên
 * MiniTournament và Tournament. Reuse các endpoint public hiện có của
 * MiniTournamentController / TournamentController — phía backend đã bypass
 * quyền owner khi user là superadmin.
 */

const MINI_TOURNAMENT = '/mini-tournaments';
const TOURNAMENT = '/tournaments';

export const updateMiniTournamentStatus = async (id, status) => {
  return axiosInstance.post(`${MINI_TOURNAMENT}/update/${id}`, { status })
    .then((response) => response.data);
};

export const adminDeleteMiniTournament = async (id) => {
  return axiosInstance.post(`${MINI_TOURNAMENT}/delete/${id}`, { id })
    .then((response) => response.data);
};

export const updateTournamentStatus = async (id, status) => {
  return axiosInstance.post(`${TOURNAMENT}/update/${id}`, { status })
    .then((response) => response.data);
};

export const adminDeleteTournament = async (id) => {
  return axiosInstance.post(`${TOURNAMENT}/delete`, { id })
    .then((response) => response.data);
};
