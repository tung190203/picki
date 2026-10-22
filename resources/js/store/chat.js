import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { inboxService, chatService } from "@/service/chat.js";
import { useUserStore } from "@/store/auth.js";

export const useChatStore = defineStore("chat", () => {
    const userStore = useUserStore();
    // State
    const unreadCounts = ref({
        total_unread: 0,
        messages_unread: 0,
        alerts_unread: 0,
        strangers_unread: 0,
    });

    const isChatOpen = ref(false);
    const activeConversation = ref(null);
    const messages = ref([]);
    const loadingMessages = ref(false);

    const isStrangersModalOpen = ref(false);
    const isShortcutPickerOpen = ref(false);

    // Getters
    const hasUnread = computed(() => unreadCounts.value.total_unread > 0);
    const strangersUnread = computed(() => unreadCounts.value.strangers_unread);

    // Actions
    const fetchUnreadCounts = async () => {
        try {
            const res = await inboxService.getUnreadCounts();
            if (res?.data) {
                unreadCounts.value = res.data;
            }
        } catch (err) {
            console.error("fetchUnreadCounts error:", err);
        }
    };

    const openChat = async (conversationId) => {
        try {
            loadingMessages.value = true;
            isChatOpen.value = true;

            // Lấy thông tin cuộc trò chuyện
            const convRes = await chatService.getConversation(conversationId);
            activeConversation.value = convRes.data;

            // Lấy tin nhắn
            const msgRes = await chatService.getMessages(conversationId);
            messages.value = msgRes.data || [];

            // Đánh dấu đã đọc
            await chatService.markRead(conversationId);
            fetchUnreadCounts();
            window.dispatchEvent(
                new CustomEvent("chat:conversation-read", {
                    detail: { conversationId },
                })
            );

            // Lắng nghe socket phòng chat
            subscribeConversationChannel(conversationId);
        } catch (err) {
            console.error("openChat error:", err);
        } finally {
            loadingMessages.value = false;
        }
    };

    const startDm = async (userId) => {
        const res = await chatService.startDm(userId);
        if (res?.data?.id) {
            await openChat(res.data.id);
        }
        return res;
    };

    const openGroupChat = async (type, targetId) => {
        const res = await chatService.getGroupConversation(type, targetId);
        if (res?.data?.id) {
            await openChat(res.data.id);
        }
        return res;
    };

    const closeChat = () => {
        if (activeConversation.value) {
            unsubscribeConversationChannel(activeConversation.value.id);
        }
        isChatOpen.value = false;
        activeConversation.value = null;
        messages.value = [];
    };

    const sendMessage = async (payload) => {
        if (!activeConversation.value) return;
        const convId = activeConversation.value.id;
        const wasStranger = Boolean(activeConversation.value.is_stranger);

        const res = await chatService.sendMessage(convId, payload);
        if (res?.data) {
            // Thêm tin nhắn vào danh sách nếu chưa có
            const exists = messages.value.some((m) => m.id === res.data.id);
            if (!exists) {
                messages.value.push(res.data);
            }

            // Nếu đây là cuộc trò chuyện người lạ vừa được trả lời
            if (wasStranger) {
                activeConversation.value.is_stranger = false;
                window.dispatchEvent(
                    new CustomEvent("chat:replied-stranger", {
                        detail: { conversationId: convId },
                    })
                );
                fetchUnreadCounts();
            }
        }
        return res;
    };

    // Socket realtime channel handling
    const subscribeConversationChannel = (conversationId) => {
        if (!window.Echo) return;

        window.Echo.private(`conversation.${conversationId}`)
            .listen(".message.sent", (event) => {
                if (event?.message) {
                    const exists = messages.value.some((m) => m.id === event.message.id);
                    if (!exists) {
                        messages.value.push(event.message);
                    }
                    // Tự động đánh dấu đã đọc nếu đang mở phòng chat và tin nhắn là từ người khác
                    const currentUserId = userStore.getUser?.id;
                    const senderId = event.message.user_id;
                    if (
                        isChatOpen.value &&
                        activeConversation.value?.id === conversationId &&
                        currentUserId &&
                        Number(senderId) !== Number(currentUserId)
                    ) {
                        chatService.markRead(conversationId);
                        fetchUnreadCounts();
                        window.dispatchEvent(
                            new CustomEvent("chat:conversation-read", {
                                detail: { conversationId },
                            })
                        );
                    }
                }
            })
            .listen(".messages.read", (event) => {
                if (event?.last_read_message_id) {
                    const currentUserId = userStore.getUser?.id;
                    const readerId = Number(event.reader_id);
                    // Chỉ cập nhật trạng thái "Đã xem" cho tin nhắn của mình khi người đọc là người khác (đối phương)
                    if (currentUserId && readerId && readerId === Number(currentUserId)) {
                        return;
                    }

                    const readId = Number(event.last_read_message_id);
                    messages.value.forEach((m) => {
                        if (m.is_own && Number(m.id) <= readId) {
                            m.status = "seen";
                        }
                    });
                }
            });
    };

    const unsubscribeConversationChannel = (conversationId) => {
        if (!window.Echo) return;
        window.Echo.leave(`conversation.${conversationId}`);
    };

    const listenUserChannel = (userId) => {
        if (!window.Echo || !userId) return;

        window.Echo.private(`user.${userId}`)
            .listen(".conversation.updated", (event) => {
                fetchUnreadCounts();
                window.dispatchEvent(
                    new CustomEvent("chat:conversation-updated", {
                        detail: event,
                    })
                );
            });
    };

    return {
        unreadCounts,
        hasUnread,
        strangersUnread,
        isChatOpen,
        activeConversation,
        messages,
        loadingMessages,
        isStrangersModalOpen,
        isShortcutPickerOpen,
        fetchUnreadCounts,
        openChat,
        startDm,
        openGroupChat,
        closeChat,
        sendMessage,
        listenUserChannel,
    };
});
