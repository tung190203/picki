import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const matchEndpoint = API_ENDPOINT.MATCHES;

export const detailMatches = async(id) => {
    return axiosInstance.get(`${matchEndpoint}/detail/${id}`).then((response) => response.data.data);
}

export const updateMatches = async(match_id, data) => {
    return axiosInstance.post(`${matchEndpoint}/update/${match_id}`, data).then((response) => response.data.data);
}

export const confirmResults = async(match_id) => {
    return axiosInstance.post(`${matchEndpoint}/confirm-result/${match_id}`).then((response) => response.data.data);
}

export const swapTeams = async(match_id, payload) => {
    return axiosInstance.post(`${matchEndpoint}/${match_id}/swap`, payload).then((response) => response.data.data);
}

export const advanceTeamManual = async(match_id, data) => {
    return axiosInstance.post(`${matchEndpoint}/${match_id}/advance-team-manual`, data).then((response) => response.data.data);
}

export const saveRallyStages = async(match_id, data) => {
    return axiosInstance.post(`${matchEndpoint}/${match_id}/stages`, data).then((response) => response.data.data);
}

export const getRallyStages = async(match_id) => {
    return axiosInstance.get(`${matchEndpoint}/${match_id}/stages`).then((response) => response.data.data);
}