import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const miniMatchEndpoint = API_ENDPOINT.MINI_MATCHES;

export const getListMiniMatches = async (mini_tournament_id, { page = 1, filter = '' } = {}) => {
    return axiosInstance.get(
        `${miniMatchEndpoint}/index/${mini_tournament_id}`,
        {
            params: {
                page,
                filter
            }
        }
    ).then(res => res.data)
}

export const deleteMiniMatches = async(data) => {

    return axiosInstance.post(`${miniMatchEndpoint}/delete`, data).then((response) => response.data.data);
}

export const detailMiniMatches = async(id) => {
    return axiosInstance.get(`${miniMatchEndpoint}/${id}`).then((response) => response.data.data);
}

export const updateOrCreateSetMiniMatches = async(mini_match_id, data) => {
    return axiosInstance.post(`${miniMatchEndpoint}/add-set/${mini_match_id}`, data).then((response) => response.data.data);
}
export const confirmResults = async(mini_match_id) => {
    return axiosInstance.post(`${miniMatchEndpoint}/confirm-result/${mini_match_id}`).then((response) => response.data.data);
}

export const createMiniMatch = async(mini_tournament_id, data) => {
    return axiosInstance.post(`${miniMatchEndpoint}/store/${mini_tournament_id}`, data).then((response) => response.data.data);
}

export const saveMiniMatch = async(mini_tournament_id, data) => {
    return axiosInstance.post(`${miniMatchEndpoint}/save/${mini_tournament_id}`, data).then((response) => response.data.data);
}

