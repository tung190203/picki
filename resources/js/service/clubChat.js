import axiosInstance from "@/utils/httpRequest.js";

export const clubChatService = {
    getConversation: (clubId) =>
        axiosInstance.get(`/clubs/${clubId}/chat/conversation`),

    getMessages: (clubId, params = {}) =>
        axiosInstance.get(`/clubs/${clubId}/chat/messages`, { params }),

    sendMessage: (clubId, content) =>
        axiosInstance.post(`/clubs/${clubId}/chat/messages`, { content, type: 'text' }),
};
