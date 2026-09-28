import axiosInstance from "@/utils/httpRequest.js";

export const sendInvitation = async (miniTournamentId, userIds, isInviteAround = false) => {
    return axiosInstance.post(`/mini-participants/invite/${miniTournamentId}`, {
        user_ids: userIds,
        is_invite_around: isInviteAround,
    }).then((response) => response.data.data)
};

export const searchUsersForInvite = async ({ keyword = '', subTab = 'all', clubId = null, page = 1, perPage = 20 } = {}) => {
  const params = {
    tab: 'user',
    sub_tab: subTab,
    per_page: perPage,
    page,
  };
  if (keyword) params.keyword = keyword;
  if (subTab === 'same_club' && clubId) params.club_id = clubId;

  const res = await axiosInstance.get('/search', { params });
  // ResponseHelper shape: { status, message, data: { data: [...], meta: {...} } }
  const inner = res?.data?.data || {};
  const data = Array.isArray(inner) ? inner : (inner.data || []);
  const meta = inner.meta || {};

  const result = (data || []).map((u) => ({
    id: u.id,
    name: u.full_name ?? u.name,
    avatar_url: u.avatar_url,
    gender: u.gender,
    gender_text: u.gender_text,
    sports: u.sports || [],
    is_friend: u.is_friend ?? false,
    invited: false,
  }));

  return { result, meta };
};

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


export const adminConfirmMiniParticipant = async (miniTournamentId, participantId) => {
    return axiosInstance.post(`/mini-tournaments/${miniTournamentId}/participants/${participantId}/admin-confirm`)
        .then(r => r.data);
};

export const modifyParticipantAvatar = async (miniTournamentId, participantId, imageFile) => {
    const formData = new FormData();
    formData.append('avatar', imageFile);
    return axiosInstance.post(
        `/mini-tournaments/${miniTournamentId}/participants/${participantId}/modify-avatar`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
    ).then(r => r.data);
};
