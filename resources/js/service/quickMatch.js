import axiosInstance from "@/utils/httpRequest.js";

export const quickMatchService = {
  create: (data) => axiosInstance.post('/quick-matches', data),
  get: (id) => axiosInstance.get(`/quick-matches/${id}`),
  scanQr: (qrCode) => axiosInstance.get(`/quick-matches/qr/${qrCode}`),
  confirm: (qrCode) => axiosInstance.post(`/quick-matches/confirm/${qrCode}`),
  updateScore: (id, score) => axiosInstance.put(`/quick-matches/${id}/score`, { score }),
};
