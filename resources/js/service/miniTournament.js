import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const miniTournamentEndpoint = API_ENDPOINT.MINI_TOURNAMENT;
// API base URL đã được cấu hình trong axiosInstance (thường là '/api'),
// nên chỉ cần path tương đối tới resource.
const miniTournamentTemplateEndpoint = '/mini-tournament-templates';

export const storeMiniTournament = async (data) => {
  return axiosInstance.post(`${miniTournamentEndpoint}/store`, data)
    .then((response) => response.data.data);
}

export const getMiniTournamentById = async (id) => {
  return axiosInstance.get(`${miniTournamentEndpoint}/${id}`)
    .then((response) => response.data.data);
}

export const updateMiniTournament = async (id, data) => {
  return axiosInstance.post(`${miniTournamentEndpoint}/update/${id}`, data)
    .then((response) => response.data.data);
}

export const deleteMiniTournament = async (id) => {
    return axiosInstance.post(`${miniTournamentEndpoint}/delete/` + id)
        .then((response) => response.data.data);
}

// Payments
export const getMiniTournamentPayments = async (id) => {
  return axiosInstance.get(`${miniTournamentEndpoint}/${id}/payments`)
    .then((response) => response.data.data);
}

export const confirmMiniTournamentPayment = async (tournamentId, paymentId, data = {}) => {
  return axiosInstance.post(`${miniTournamentEndpoint}/${tournamentId}/payments/${paymentId}/confirm`, data)
    .then((response) => response.data.data);
}

export const rejectMiniTournamentPayment = async (tournamentId, paymentId, data = {}) => {
  return axiosInstance.post(`${miniTournamentEndpoint}/${tournamentId}/payments/${paymentId}/reject`, data)
    .then((response) => response.data.data);
}

export const remindMiniTournamentPayment = async (tournamentId, participantId) => {
  return axiosInstance.post(`${miniTournamentEndpoint}/${tournamentId}/payments/remind/${participantId}`)
    .then((response) => response.data.data);
}

export const remindAllMiniTournamentPayments = async (tournamentId) => {
  return axiosInstance.post(`${miniTournamentEndpoint}/${tournamentId}/payments/remind-all`)
    .then((response) => response.data.data);
}

export const getMyMiniTournamentPayment = async (tournamentId) => {
  return axiosInstance.get(`${miniTournamentEndpoint}/${tournamentId}/my-payment`)
    .then((response) => response.data.data);
}

export const payMiniTournament = async (tournamentId, data) => {
  // data nên là FormData chứa: participant_id, receipt_image (file), note?
  return axiosInstance.post(`${miniTournamentEndpoint}/${tournamentId}/pay`, data)
    .then((response) => response.data);
}

// Templates
export const saveMiniTournamentTemplate = async (payload) => {
  // payload: { name, settings }
  return axiosInstance.post(miniTournamentTemplateEndpoint, payload)
    .then((response) => response.data); // { data, message, ... }
}

export const getMiniTournamentTemplates = async () => {
  return axiosInstance.get(miniTournamentTemplateEndpoint)
    .then((response) => response.data); // { data: { templates: [...] }, message }
}
