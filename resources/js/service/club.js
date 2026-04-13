import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

export const getAllClubs = async (params = {}) => {
  const { data } = await axiosInstance.get(API_ENDPOINT.CLUB, { params })
  return data
}

export const joinRequest = async (clubId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/join-requests`).then((response) => response.data);
}

export const joiningRequests = async (clubId) => {
    return axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/join-requests`).then((response) => response.data);
}

export const approveJoinRequest = async (clubId, requestId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/join-requests/${requestId}/approve`).then((response) => response.data);
}

export const rejectJoinRequest = async (clubId, requestId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/join-requests/${requestId}/reject`).then((response) => response.data);
}

export const myClubs = async (params = {}) => {
  return axiosInstance.get(`${API_ENDPOINT.CLUB}/my-clubs`, { params }).then(res => res.data.data)
}

export const clubDetail = async (clubId) => {
    return axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}`).then((response) => response.data.data);
}

export const updateClub = async (clubId, data) => {
    return axiosInstance.put(`${API_ENDPOINT.CLUB}/${clubId}`, data).then((response) => response.data);
}

export const createClub = async (data) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}`, data).then((response) => response.data);
}

export const deleteClub = async (clubId) => {
    return axiosInstance.delete(`${API_ENDPOINT.CLUB}/${clubId}`).then((response) => response.data);
}

export const getMembers = async (clubId, params = {}) => {
  const { data } = await axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/members`, { params })
  return data
}

export const removeMember = async (clubId, memberId) => {
    return axiosInstance.delete(`${API_ENDPOINT.CLUB}/${clubId}/members/${memberId}`).then((response) => response.data);
}

export const updateMemberRole = async (clubId, memberId, data) => {
    return axiosInstance.put(`${API_ENDPOINT.CLUB}/${clubId}/members/${memberId}`, data).then((response) => response.data);
}

export const cancelJoinRequest = async (clubId) => {
    return axiosInstance.delete(`${API_ENDPOINT.CLUB}/${clubId}/join-requests`).then((response) => response.data);
}

export const leaveClub = async (clubId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/leave`, data).then((response) => response.data);
}

export const acceptInvite = async (clubId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/invitations/accept`).then((response) => response.data);
}

export const declineInvite = async (clubId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/invitations/reject`).then((response) => response.data);
}

export const clubNotification = async (clubId, params = {}) => {
  const { data } = await axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/notifications`, { params })
  return data
}

export const getClubActivities = async (clubId, params = {}) => {
  const { data } = await axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/content`, { params })
  return data
}

export const getClubActivityDetail = async(clubId, activityId) => {
  return axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}`).then((response) => response.data);
}

export const getClubLeaderBoard = async (clubId, params = {}) => {
  const { data } = await axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/leaderboard`, { params })
  return data
}

export const getFund = async (clubId) => {
    return axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/fund`).then((response) => response.data);
}

export const searchLocation = async (params = {}) => {
  const { data } = await axiosInstance.get(`${API_ENDPOINT.CLUB}/search-location`, { params })
  return data
}

export const locationDetail = async (params = {}) => {
  const { data } = await axiosInstance.get(`${API_ENDPOINT.CLUB}/location-detail`, { params })
  return data
}

export const createActivity = async (clubId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities`, data).then((response) => response.data);
}

export const cancelActivity = async (clubId, activityId, data = {}) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}/cancel`, data).then((response) => response.data);
}

export const cancelRecurrenceSeries = async (clubId, activityId, data = {}) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}/recurrence-series/cancel`, data).then((response) => response.data);
}

export const getListJoinActivityRequest = async (clubId, activityId, params = {}) => {
  const { data } = await axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}/participants`, { params })
  return data
}

export const approveJoinActivityRequest = async (clubId, activityId, userId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}/participants/${userId}/approve`).then((response) => response.data);
}

export const rejectJoinActivityRequest = async (clubId, activityId, userId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}/participants/${userId}/reject`).then((response) => response.data);
}

export const joinActivityRequest = async (clubId, activityId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}/participants`).then((response) => response.data);
}

export const markAllAsRead = async (clubId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/notifications/mark-read-all`).then((response) => response.data);
}

export const markAsRead = async (clubId, notificationId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/notifications/${notificationId}/mark-read`).then((response) => response.data);
}


export const createNotification = async (clubId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/notifications`, data).then((response) => response.data);
}

export const deleteNotification = async (clubId, notificationId) => {
    return axiosInstance.delete(`${API_ENDPOINT.CLUB}/${clubId}/notifications/${notificationId}`).then((response) => response.data);
}

export const updateNotification = async (clubId, notificationId, data) => {
    return axiosInstance.put(`${API_ENDPOINT.CLUB}/${clubId}/notifications/${notificationId}`, data).then((response) => response.data);
}

export const togglePin = async (clubId, notificationId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/notifications/${notificationId}/pin`).then((response) => response.data);
}

export const updateActivity = async (clubId, activityId, data) => {
    const isFormData = data instanceof FormData
    if (isFormData) {
        if (!data.has('_method')) data.append('_method', 'PUT')
        return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}`, data).then((response) => response.data);
    }
    return axiosInstance.put(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}`, data).then((response) => response.data);
}

export const getNotificationType = async (clubId) => {
    const response = await axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/notifications/types`)
    const data = response?.data
    if (Array.isArray(data)) return data
    if (Array.isArray(data?.data)) return data.data
    if (Array.isArray(data?.data?.data)) return data.data.data
    return []
}

export const cancelActivityJoinRequest = async (clubId, activityId, participantId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}/participants/${participantId}/cancel`).then((response) => response.data);
}

export const withdrawActivityParticipation = async (clubId, activityId, participantId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}/participants/${participantId}/withdraw`).then((response) => response.data);
}

export const getAllTransaction = async (clubId, params = {}) => {
  const { data } = await axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/wallet-transactions`, { params })
  return data
}

export const getAllMyTransaction = async (clubId, params = {}) => {
  const { data } = await axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/wallet-transactions/my-transactions`, { params })
  return data
}

export const fundOverview = async (clubId) => {
    return axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/fund/overview`).then((response) => response.data);
}

export const getFundCollection = async (clubId, params = {}) => {
    return axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections`, { params }).then((response) => response.data);
}

export const getFundCollectionDetail = async (clubId, collectionId) => {
    return axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/${collectionId}`).then((response) => response.data);
}

export const createdFundRevenue = async (clubId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections`, data).then((response) => response.data);
}

export const deleteFundCollection = async (clubId, collectionId) => {
    return axiosInstance.delete(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/${collectionId}`).then((response) => response.data);
}

export const updateFundCollection = async (clubId, collectionId, data) => {
    return axiosInstance.put(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/${collectionId}`, data).then((response) => response.data);
}

export const createFundExpenses = async (clubId, data) => {
  return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/expenses`, data).then((response) => response.data);
}

export const listQrCodes = async (clubId) => {
    return axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/qr-codes`).then((response) => response.data);
}

export const createQrCode = async (clubId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/qr-codes`, data).then((response) => response.data);
}

export const destroyMainQrCode = async (clubId) => {
    return axiosInstance.delete(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/qr-codes/main`).then((response) => response.data);
}

export const getQrCode = async (clubId, collectionId) => {
    return axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/${collectionId}/qr-code`).then((response) => response.data);
}

export const deleteQrCode = async (clubId, qrCodeId) => {
    return axiosInstance.delete(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/qr-codes/${qrCodeId}`).then((response) => response.data);
}

export const getMyCollections = async (clubId, params = {}) => {
    return axiosInstance.get(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/my-collections`).then((response) => response.data);
}

export const confirmFundContribution = async (clubId, collectionId, contributionId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/${collectionId}/contributions/${contributionId}/confirm`).then((response) => response.data);
}

export const rejectFundContribution = async (clubId, collectionId, contributionId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/${collectionId}/contributions/${contributionId}/reject`, data).then((response) => response.data);
}

export const submitContributionReceipt = async (clubId, collectionId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/${collectionId}/contributions/receipt`, data).then((response) => response.data);
}

export const markPaidContribution = async (clubId, collectionId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/fund-collections/${collectionId}/contributions/mark-paid`, data).then((response) => response.data);
}

export const getClubCandidates = async (params = {}) => {
  const { data } = await axiosInstance.get(`${API_ENDPOINT.CLUB}/members/candidates`, { params })
  return data
}

export const inviteMember = async (clubId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/members`, data).then((response) => response.data);
}

export const checkInActivity = async (clubId, activityId, token) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}/check-in`, { token }).then((response) => response.data);
}

export const selfCheckinActivity = async (clubId, activityId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}/participants/self/check-in`).then((response) => response.data);
}

export const selfAbsentActivity = async (clubId, activityId) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/activities/${activityId}/participants/self/absent`).then((response) => response.data);
}

export const reportClub = async (clubId, data) => {
    return axiosInstance.post(`${API_ENDPOINT.CLUB}/${clubId}/report`, data).then((response) => response.data);
}
