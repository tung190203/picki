import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const participantEndpoint = API_ENDPOINT.PARTICIPANT;

export const sendInvitation = async (tournamentId, userIds) => {
  return axiosInstance.post(`${participantEndpoint}/invite-user/${tournamentId}`, {
    user_ids: userIds,
  }).then((response) => response.data.data)
};

export const inviteStaffs = async (tournamentId, data) => {
  return axiosInstance.post(`${participantEndpoint}/invite-staff/${tournamentId}`, data).then((response) => response.data)
}

export const listInviteUsers = async (tournamentId, params) => {
  return axiosInstance.post(`${participantEndpoint}/list-invite/${tournamentId}`, {
    params,
  }).then((response) => response.data.data)
}

export const confirmParticipants = async (participantId) => {
  return axiosInstance.post(`${participantEndpoint}/confirm/${participantId}`)
    .then((response) => response.data.data);
}

export const getParticipantsNonTeam = async(tournamentId) => {
  return axiosInstance.post(`${participantEndpoint}/list-member/${tournamentId}`)
  .then((response) => response?.data?.data);
}

export const getTournamentInviteGroups = async(tournamentId, payload) => {
  return axiosInstance.post(`${participantEndpoint}/candidates/${tournamentId}`, payload)
  .then((response) => response?.data?.data);
}

export const deleteParticipant = async(participantId) => {
  return axiosInstance.post(`${participantEndpoint}/delete/${participantId}`)
  .then((response) => response?.data?.data);
}

export const joinTournament = async(id) => {
  return axiosInstance.post(`${participantEndpoint}/join/${id}`).then((response) => response?.data?.data);
}

export const acceptInviteTournament = async (participantId) => {
  return axiosInstance.post(`${participantEndpoint}/accept/${participantId}`).then((response) => response?.data?.data);
}

export const rejectParticipant = async (participantId) => {
  return axiosInstance.post(`${participantEndpoint}/delete/${participantId}`)
    .then((response) => response?.data?.data);
}