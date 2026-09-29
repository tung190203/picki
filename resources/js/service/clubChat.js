import axiosInstance from "@/utils/httpRequest.js";

export const clubChatService = {
    getConversation: (clubId) =>
        axiosInstance.get(`/clubs/${clubId}/chat/conversation`),

    getMessages: (clubId, params = {}) =>
        axiosInstance.get(`/clubs/${clubId}/chat/messages`, { params }),

    sendMessage: (clubId, payload) => {
        // payload: { content, attachment_type?, attachment_meta? }
        if (typeof payload === 'string') {
            return axiosInstance.post(`/clubs/${clubId}/chat/messages`, { content: payload, type: 'text' })
        }
        return axiosInstance.post(`/clubs/${clubId}/chat/messages`, payload)
    },

    uploadFile: (clubId, file) => {
        const form = new FormData()
        form.append('file', file)
        return axiosInstance.post(`/clubs/${clubId}/chat/upload`, form, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
    },

    getTournaments: (clubId, params = {}) => {
        // Reuse /clubs/{id}/content — backend đã merge tournament + mini-tournament + activity
        const defaults = {
            page: 1,
            per_page: 50,
            statuses: ['scheduled', 'ongoing'],
        }
        return axiosInstance.get(`/clubs/${clubId}/content`, { params: { ...defaults, ...params } })
            .then(res => {
                const items = res.data?.data?.items || []
                // Chỉ lấy tournament + mini_tournament cho picker share
                const list = items
                    .filter(i => i.type === 'tournament' || i.type === 'mini_tournament')
                    .map(i => ({
                        id: i.id,
                        kind: i.type,
                        name: i.data?.name || '',
                        start_at: i.data?.start_time || null,
                        poster_url: i.data?.poster_url || null,
                        sport_name: i.data?.sport?.name || null,
                        status: i.data?.status ?? null,
                    }))
                return { ...res, data: { ...(res.data || {}), data: list } }
            })
    },

    markRead: (clubId, messageId) =>
        axiosInstance.post(`/clubs/${clubId}/chat/read`, { message_id: messageId }),

    getReads: (clubId) =>
        axiosInstance.get(`/clubs/${clubId}/chat/reads`),
};
