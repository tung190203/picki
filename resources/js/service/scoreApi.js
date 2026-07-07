import axiosInstance from "@/utils/httpRequest.js";
import { API_ENDPOINT } from "@/constants/index.js";

const scoreEndpoint = API_ENDPOINT.MATCHES + API_ENDPOINT.SCORE;

export const getCurrentScore = async (matchId) => {
    return axiosInstance.get(`${API_ENDPOINT.MATCHES}/${matchId}${API_ENDPOINT.SCORE}/current`)
        .then((response) => response.data.data);
};

export const startMatchScore = async (matchId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.MATCHES}/${matchId}${API_ENDPOINT.SCORE}/start`, data)
        .then((response) => response.data.data);
};

export const updateScore = async (matchId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.MATCHES}/${matchId}${API_ENDPOINT.SCORE}/update`, data)
        .then((response) => response.data.data);
};
