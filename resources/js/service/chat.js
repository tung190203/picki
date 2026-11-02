import axiosInstance from "@/utils/httpRequest.js";

export const inboxService = {
    getInbox: (params = {}) => {
        const cleanParams = { ...params };
        if (typeof cleanParams.is_unread === "boolean") {
            cleanParams.is_unread = cleanParams.is_unread ? 1 : 0;
        }
        return axiosInstance.get("/inbox", { params: cleanParams }).then((res) => res.data);
    },

    getStrangers: (params = {}) =>
        axiosInstance.get("/inbox/strangers", { params }).then((res) => res.data),

    getUnreadCounts: () =>
        axiosInstance.get("/inbox/unread-count").then((res) => res.data),

    markAllAsRead: (tab = "all") =>
        axiosInstance.post("/inbox/read-all", { tab }).then((res) => res.data),
};

export const chatService = {
    startDm: (receiver_id) =>
        axiosInstance.post("/chat/conversations/dm", { receiver_id }).then((res) => res.data),

    getGroupConversation: (type, target_id) =>
        axiosInstance.post("/chat/conversations/group", { type, target_id }).then((res) => res.data),

    getConversation: (conversationId) =>
        axiosInstance.get(`/chat/conversations/${conversationId}`).then((res) => res.data),

    getMessages: (conversationId, params = {}) =>
        axiosInstance.get(`/chat/conversations/${conversationId}/messages`, { params }).then((res) => res.data),

    sendMessage: (conversationId, payload) =>
        axiosInstance.post(`/chat/conversations/${conversationId}/messages`, payload).then((res) => res.data),

    markRead: (conversationId) =>
        axiosInstance.post(`/chat/conversations/${conversationId}/read`).then((res) => res.data),

    toggleMute: (conversationId, is_muted = undefined) =>
        axiosInstance
            .post(`/chat/conversations/${conversationId}/mute`, is_muted !== undefined ? { is_muted } : {})
            .then((res) => res.data),

    getShortcuts: (type, q = "") =>
        axiosInstance.get(`/chat/shortcuts/${type}`, { params: { q } }).then((res) => res.data),

    getBlocks: () =>
        axiosInstance.get("/chat/users/blocks").then((res) => res.data),

    blockUser: (userId) =>
        axiosInstance.post(`/chat/users/${userId}/block`).then((res) => res.data),

    unblockUser: (userId) =>
        axiosInstance.delete(`/chat/users/${userId}/unblock`).then((res) => res.data),

    reportChat: (payload) =>
        axiosInstance.post("/chat/reports", payload).then((res) => res.data),
};
