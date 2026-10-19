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
        // và trả active trước, ended sau. Picker chỉ cần giải/kèo đang diễn ra
        // → filter client-side theo end_time + status để khớp 3 loại entity
        // (activity: 'scheduled'/'ongoing'; tournament: 'upcoming'/'ongoing';
        //  mini_tournament: int 1=DRAFT, 2=OPEN, 3=CLOSED, 4=CANCELLED).
        const defaults = {
            page: 1,
            per_page: 50,
        }
        return axiosInstance.get(`/clubs/${clubId}/content`, { params: { ...defaults, ...params } })
            .then(res => {
                const items = res.data?.data?.items || []
                const now = Date.now()
                const isEnded = (data) => {
                    if (!data) return false
                    const status = data.status
                    if (status === 'cancelled' || status === 'canceled' || status === 'completed' || status === 'finished') return true
                    if (status === 3 || status === 4) return true // MiniTournament CLOSED/CANCELLED
                    const end = data.end_time || data.end_date || data.ends_at
                    if (end && new Date(end).getTime() < now) return true
                    return false
                }
                const list = items
                    .filter(i => i.type === 'tournament' || i.type === 'mini_tournament')
                    .filter(i => !isEnded(i.data))
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
