import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const teamEndPoint = API_ENDPOINT.TEAMS;

export const autoAssignTeams = async (tournamentId) => {
  return axiosInstance.post(`${teamEndPoint}/auto-assign/${tournamentId}`)
    .then((response) => response?.data?.data || null);
}

export const getTeamsByTournamentId = async (tournamentId, data) => {
  return axiosInstance.get(`${teamEndPoint}/index/${tournamentId}`, { params: data })
    .then((response) => response?.data?.data || { teams: [] });
}

export const updateTeam = async (teamId, teamData) => {
  return axiosInstance.post(`${teamEndPoint}/update/${teamId}`, teamData)
    .then((response) => response?.data?.data || null);
}

export const createTeam = async (tournamentId, teamData) => {
  return axiosInstance.post(`${teamEndPoint}/create/${tournamentId}`, teamData)
    .then((response) => response?.data?.data || null);
}

export const deleteTeam = async (teamId) => {
  return axiosInstance.delete(`${teamEndPoint}/delete/${teamId}`)
    .then((response) => response?.data?.data || null);
}

export const addUserToTeam = async (teamId, userId) => {
  return axiosInstance.post(`${teamEndPoint}/add-member/${teamId}`, {user_id: userId})
  .then((response) => response?.data?.data || []);
}

export const removeMember = async (memberId, teamId) => {
  return axiosInstance.post(`${teamEndPoint}/remove-member/${teamId}`, {user_id: memberId})
  .then((response) => response?.data?.data || []);
}