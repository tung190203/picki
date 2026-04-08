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

export const deleteStaff = async(staffId, action = null, newGuarantorUserId = null) => {
    const payload = {};
    if (action) {
        payload.action = action;
    }
    if (newGuarantorUserId) {
        payload.new_guarantor_user_id = newGuarantorUserId;
    }
    return axiosInstance.post(`${miniParticipantEndpoint}/delete-staff/${staffId}`, payload)
        .then((response) => response?.data);
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

// Mini-Tournament check-in / absent
export const markMiniParticipantCheckIn = async (miniTournamentId, participantId) => {
    return axiosInstance.post(`/mini-tournaments/${miniTournamentId}/participants/${participantId}/mark-check-in`)
        .then(r => r.data);
};

export const markMiniParticipantAbsent = async (miniTournamentId, participantId) => {
    return axiosInstance.post(`/mini-tournaments/${miniTournamentId}/participants/${participantId}/mark-absent`)
        .then(r => r.data);
};

export const selfCheckInMini = async (miniTournamentId) => {
    return axiosInstance.post(`/mini-participants/self/check-in/${miniTournamentId}`)
        .then(r => r.data);
};

export const selfMarkAbsentMini = async (miniTournamentId) => {
    return axiosInstance.post(`/mini-participants/self/absent/${miniTournamentId}`)
        .then(r => r.data);
};
