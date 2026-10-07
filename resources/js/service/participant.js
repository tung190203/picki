import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const participantEndpoint = API_ENDPOINT.PARTICIPANT;

export const sendInvitation = async (tournamentId, userIds, clubGuestProfileIds = []) => {
  const payload = {};
  if (userIds?.length) payload.user_ids = userIds;
  if (clubGuestProfileIds?.length) payload.club_guest_profile_ids = clubGuestProfileIds;
  return axiosInstance.post(`${participantEndpoint}/invite-user/${tournamentId}`, payload).then((response) => response.data.data)
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

export const searchUsersForInvite = async ({ keyword = '', subTab = 'all', clubId = null, tournamentId = null, page = 1, perPage = 20 } = {}) => {
  const params = {
    tab: 'user',
    sub_tab: subTab,
    per_page: perPage,
    page,
  };
  if (keyword) params.keyword = keyword;
  if (subTab === 'same_club') {
    if (clubId) params.club_id = clubId;
    if (tournamentId) params.tournament_id = tournamentId;
  }

  const res = await axiosInstance.get('/search', { params });
  // ResponseHelper shape: { status, message, data: { data: [...], meta: {...} } }
  const inner = res?.data?.data || {};
  const data = Array.isArray(inner) ? inner : (inner.data || []);
  const meta = inner.meta || {};

  // Map search response → same shape as old candidates ({ result: [...] })
  const result = (data || []).map((u) => ({
    id: u.id,
    name: u.full_name ?? u.name,
    avatar_url: u.avatar_url,
    gender: u.gender,
    gender_text: u.gender_text,
    sports: u.sports || [],
    is_friend: u.is_friend ?? false,
    is_guest: Boolean(u.is_guest),
    is_virtual: Boolean(u.is_guest), // backward-compat cho UI cũ (đã migrate từ VM sang CLB guest)
    invited: false,
  }));

  return { result, meta };
};

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

// Tournament check-in / absent
export const markParticipantCheckIn = async (tournamentId, participantId) => {
    return axiosInstance.post(`/tournaments/${tournamentId}/participants/${participantId}/mark-check-in`)
        .then(r => r.data);
};

export const markParticipantAbsent = async (tournamentId, participantId) => {
    return axiosInstance.post(`/tournaments/${tournamentId}/participants/${participantId}/mark-absent`)
        .then(r => r.data);
};

export const selfCheckInTournament = async (tournamentId) => {
    return axiosInstance.post(`/tournaments/${tournamentId}/self/check-in`)
        .then(r => r.data);
};

export const selfMarkAbsentTournament = async (tournamentId) => {
    return axiosInstance.post(`/tournaments/${tournamentId}/self/absent`)
        .then(r => r.data);
};

export const adminConfirmParticipant = async (tournamentId, participantId) => {
    return axiosInstance.post(`/tournaments/${tournamentId}/participants/${participantId}/admin-confirm`)
        .then(r => r.data);
};