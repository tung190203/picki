import axiosInstance from "@/utils/httpRequest.js";

const BASE = '/tournaments';

export const getTournamentPayments = (id) =>
  axiosInstance.get(`${BASE}/${id}/payments`)
    .then((response) => response.data.data);

export const getMyTournamentPayment = (id) =>
  axiosInstance.get(`${BASE}/${id}/my-payment`)
    .then((response) => response.data.data);

export const submitTournamentPayment = (id, data) => {
  const formData = new FormData();
  Object.entries(data).forEach(([key, value]) => {
    if (value !== null && value !== undefined) {
      formData.append(key, value);
    }
  });
  return axiosInstance.post(`${BASE}/${id}/payments`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then((response) => response.data.data);
};

export const confirmTournamentPayment = (id, pid) =>
  axiosInstance.post(`${BASE}/${id}/payments/${pid}/confirm`)
    .then((response) => response.data.data);

export const rejectTournamentPayment = (id, pid, reason) =>
  axiosInstance.post(`${BASE}/${id}/payments/${pid}/reject`, { reason })
    .then((response) => response.data.data);

export const markTournamentUserPaid = (id, participantId) =>
  axiosInstance.post(`${BASE}/${id}/payments/${participantId}/mark-paid`)
    .then((response) => response.data.data);

export const remindTournamentUser = (id, participantId) =>
  axiosInstance.post(`${BASE}/${id}/payments/${participantId}/remind`)
    .then((response) => response.data.data);

export const remindAllTournamentPayments = (id) =>
  axiosInstance.post(`${BASE}/${id}/payments/remind-all`)
    .then((response) => response.data.data);

export const getTournamentFundCollection = (id) =>
  axiosInstance.get(`${BASE}/${id}/fund-collection`)
    .then((response) => response.data.data);
