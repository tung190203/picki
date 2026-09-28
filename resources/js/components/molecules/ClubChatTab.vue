<template>
    <div class="flex flex-col h-[480px] bg-white dark:bg-[#161F33] rounded-2xl border border-gray-100 dark:border-slate-800 overflow-hidden">
        <header class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-slate-800 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-[#D72D36] to-[#F57C83] text-white flex items-center justify-center font-bold text-sm">
                    <ChatBubbleLeftRightIcon class="w-5 h-5" />
                </div>
                <div>
                    <p class="font-semibold text-sm text-gray-800 dark:text-slate-100">{{ conversationName }}</p>
                    <p class="text-xs text-gray-400">Nhóm chat chung của CLB</p>
                </div>
            </div>
        </header>

        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50/50 dark:bg-[#0F172A]/50 custom-scrollbar">
            <div v-if="loadingMessages" class="text-center py-8 text-xs text-gray-400">Đang tải tin nhắn...</div>
            <div v-else-if="messages.length === 0" class="text-center py-12 text-sm text-gray-400">
                Chưa có tin nhắn nào. Hãy bắt đầu cuộc trò chuyện!
            </div>
            <div v-for="msg in messages" :key="msg.id"
                class="flex gap-2"
                :class="isOwn(msg) ? 'justify-end' : 'justify-start'">
                <div class="max-w-[75%]">
                    <div class="px-3 py-2 rounded-2xl text-sm"
                        :class="isOwn(msg)
                            ? 'bg-[#D72D36] text-white rounded-br-sm'
                            : 'bg-white dark:bg-slate-800 text-gray-800 dark:text-slate-100 rounded-bl-sm border border-gray-100 dark:border-slate-700'">
                        <p class="break-words whitespace-pre-wrap">{{ msg.content }}</p>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-0.5 px-1" :class="isOwn(msg) ? 'text-right' : 'text-left'">
                        {{ msg.sender?.short_name || msg.sender?.full_name || 'Bạn' }} · {{ formatTime(msg.created_at) }}
                    </p>
                </div>
            </div>
        </div>

        <footer class="p-3 border-t border-gray-100 dark:border-slate-800 shrink-0">
            <div class="flex items-end gap-2">
                <textarea v-model="draftMessage" rows="1" placeholder="Nhập tin nhắn..."
                    class="flex-1 resize-none px-3 py-2 text-sm bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl focus:outline-none focus:border-[#D72D36] text-gray-800 dark:text-slate-100 placeholder:text-gray-400 max-h-24"
                    :disabled="sending"
                    @keydown.enter.exact.prevent="sendMessage"></textarea>
                <button @click="sendMessage" :disabled="!draftMessage.trim() || sending"
                    class="p-2 rounded-full bg-[#D72D36] text-white hover:bg-[#c4252e] disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    title="Gửi">
                    <PaperAirplaneIcon class="w-5 h-5" />
                </button>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { ref, computed, nextTick, watch, onMounted, onUnmounted } from 'vue'
import { PaperAirplaneIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import { useUserStore } from '@/store/auth'
import { clubChatService } from '@/service/clubChat.js'

const props = defineProps({
    clubId: {
        type: [Number, String],
        required: true
    },
    clubName: {
        type: String,
        default: 'CLB'
    }
})

const userStore = useUserStore()
const currentUserId = computed(() => userStore.getUser?.id)

const conversationName = computed(() => `Nhóm chat ${props.clubName}`)

const loadingMessages = ref(false)
const sending = ref(false)
const messagesContainer = ref(null)
const messages = ref([])
const conversation = ref(null)
const draftMessage = ref('')
const echoChannel = ref(null)

const isOwn = (msg) => msg.sender?.id === currentUserId.value

const formatTime = (str) => {
    if (!str) return ''
    const d = new Date(str)
    if (isNaN(d)) return ''
    const now = new Date()
    if (now - d < 24 * 3600 * 1000) {
        return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
    }
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' })
}

const fetchConversation = async () => {
    try {
        const res = await clubChatService.getConversation(props.clubId)
        const conv = res.data?.data || res.data
        if (conv) {
            conversation.value = conv
        }
    } catch (error) {
        console.error('Lỗi tải conversation:', error)
    }
}

const fetchMessages = async () => {
    if (!conversation.value?.id) return
    loadingMessages.value = true
    try {
        const res = await clubChatService.getMessages(props.clubId, { per_page: 50, page: 1 })
        const list = res.data?.data || []
        messages.value = [...list].reverse()
        await nextTick()
        scrollToBottom()
    } catch (error) {
        console.error('Lỗi tải messages:', error)
    } finally {
        loadingMessages.value = false
    }
}

const scrollToBottom = async () => {
    await nextTick()
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
}

const sendMessage = async () => {
    const content = draftMessage.value.trim()
    if (!content || sending.value) return
    sending.value = true
    try {
        const res = await clubChatService.sendMessage(props.clubId, content)
        const newMsg = res.data?.data
        if (newMsg) {
            messages.value.push({
                ...newMsg,
                sender: newMsg.sender || { id: currentUserId.value, full_name: userStore.getUser?.full_name }
            })
            draftMessage.value = ''
            await scrollToBottom()
        }
    } catch (error) {
        console.error('Lỗi gửi tin nhắn:', error)
    } finally {
        sending.value = false
    }
}

const setupEcho = () => {
    if (echoChannel.value || !window.Echo || !props.clubId) return
    echoChannel.value = window.Echo.private(`club.${props.clubId}`)
    echoChannel.value.listen('.club.chat.message.sent', (data) => {
        const msg = data?.message
        if (!msg || msg.sender?.id === currentUserId.value) return
        messages.value.push(msg)
        nextTick(scrollToBottom)
    })
}

watch(() => props.clubId, async (newId) => {
    if (echoChannel.value) {
        echoChannel.value.stopListening('.club.chat.message.sent')
        echoChannel.value.unsubscribe()
        echoChannel.value = null
    }
    messages.value = []
    conversation.value = null
    if (newId) {
        await fetchConversation()
        await fetchMessages()
        setupEcho()
    }
}, { immediate: true })

onMounted(setupEcho)

onUnmounted(() => {
    if (echoChannel.value) {
        echoChannel.value.stopListening('.club.chat.message.sent')
        echoChannel.value.unsubscribe()
    }
})
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #E5E7EB; border-radius: 20px; }
</style>
