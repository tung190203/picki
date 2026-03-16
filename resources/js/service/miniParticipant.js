import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const miniParticipantEndpoint = API_ENDPOINT.MINI_PARTICIPANT;

export const sendInvitation = async (miniTournamentId, userId) => {
    return axiosInstance.post(`${miniParticipantEndpoint}/invite/${miniTournamentId}`, {
        user_id: userId,
    }).then((response) => response.data.data)
};

export const getMiniTournamentInviteGroups = async(miniTournamentId, payload) => {
  return axiosInstance.post(`${miniParticipantEndpoint}/candidates/${miniTournamentId}`, payload)
  .then((response) => response?.data?.data);
}

export const deleteStaff = async(staffId) => {
    return axiosInstance.post(`${miniParticipantEndpoint}/delete-staff/${staffId}`)
        .then((response) => response?.data?.data);
}

export const deleteMiniParticipant = async(miniParticipantId) => {
    return axiosInstance.post(`${miniParticipantEndpoint}/delete/${miniParticipantId}`)
        .then((response) => response?.data?.data);
}

export const joinMiniTournament = async(id) => {
    return axiosInstance.post(`${miniParticipantEndpoint}/join/${id}`).then((response) => response?.data?.data);
}

export const acceptInviteMiniTournament = async (miniParticipantId) => {
    return axiosInstance.post(`${miniParticipantEndpoint}/accept/${miniParticipantId}`).then((response) => response?.data?.data);
}

export const declineMiniTournament = async (miniParticipantId) => {
    return axiosInstance.post(`${miniParticipantEndpoint}/decline/${miniParticipantId}`).then((response) => response?.data);
}

export const confirmMiniParticipant = async (miniParticipantId) => {
    return axiosInstance.post(`${miniParticipantEndpoint}/confirm/${miniParticipantId}`).then((response) => response?.data?.data);
}

