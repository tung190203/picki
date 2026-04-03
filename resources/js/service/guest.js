import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const miniTournamentEndpoint = API_ENDPOINT.MINI_TOURNAMENT;
const tournamentEndpoint = API_ENDPOINT.TOURNAMENT;

/**
 * Thêm guest vào mini tournament
 * @param {number} miniTournamentId
 * @param {object} data - { guest_name, guest_phone, guarantor_user_id? }
 */
export const addGuest = async (miniTournamentId, data) => {
    return axiosInstance.post(`${miniTournamentEndpoint}/${miniTournamentId}/guests`, data)
        .then((response) => response.data);
};

/**
 * Lấy danh sách guest của một tournament (chỉ organizer)
 * @param {number} miniTournamentId
 */
export const getGuests = async (miniTournamentId) => {
    return axiosInstance.get(`${miniTournamentEndpoint}/${miniTournamentId}/guests`)
        .then((response) => response.data.data);
};

/**
 * Lấy danh sách guest mà user hiện tại bảo lãnh và CHƯA đóng tiền
 * @param {number} miniTournamentId
 */
export const getGuaranteedGuests = async (miniTournamentId) => {
    return axiosInstance.get(`${miniTournamentEndpoint}/${miniTournamentId}/guaranteed-guests`)
        .then((response) => response.data.data);
};

/**
 * Lấy danh sách người có thể làm guarantor
 * @param {number} miniTournamentId
 */
export const getGuarantorCandidates = async (miniTournamentId) => {
    return axiosInstance.get(`${miniTournamentEndpoint}/${miniTournamentId}/guarantor-candidates`)
        .then((response) => response.data.data);
};

/**
 * Thêm guest vào tournament (không phải mini-tournament)
 * @param {number} tournamentId
 * @param {object} data - { guest_name, guest_phone, guest_avatar?, guarantor_user_id?, estimated_level? }
 */
export const addTournamentGuest = async (tournamentId, data) => {
    return axiosInstance.post(`${tournamentEndpoint}/${tournamentId}/guests`, data)
        .then((response) => response.data);
};

/**
 * Lấy danh sách guest của một tournament
 * @param {number} tournamentId
 */
export const getTournamentGuests = async (tournamentId) => {
    return axiosInstance.get(`${tournamentEndpoint}/${tournamentId}/guests`)
        .then((response) => response.data.data);
};

/**
 * Lấy danh sách người có thể làm guarantor cho tournament
 * @param {number} tournamentId
 */
export const getTournamentGuarantorCandidates = async (tournamentId) => {
    return axiosInstance.get(`${tournamentEndpoint}/${tournamentId}/guarantor-candidates`)
        .then((response) => response.data.data);
};
