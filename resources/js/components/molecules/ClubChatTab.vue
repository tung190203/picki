<template>
    <div class="flex flex-col h-[560px] bg-white dark:bg-[#161F33] rounded-2xl border border-gray-100 dark:border-slate-800 overflow-hidden">
        <header class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-slate-800 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-[#D72D36] to-[#F57C83] text-white flex items-center justify-center font-bold text-sm">
                    <ChatBubbleLeftRightIcon class="w-5 h-5" />
                </div>
                <div>
                    <p class="font-semibold text-sm text-gray-800 dark:text-slate-100">{{ conversationName }}</p>
                    <p class="text-xs text-gray-400 h-4">{{ unreadCount }} tin nhắn chưa đọc</p>
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
                <div class="max-w-[80%]">
                    <!-- Tournament card -->
                    <div v-if="msg.attachment_type === 'tournament' && msg.attachment_meta"
                        class="rounded-2xl overflow-hidden border bg-white dark:bg-slate-800 mb-1 shadow-sm max-w-sm"
                        :class="[isOwn(msg) ? 'rounded-br-sm' : 'rounded-bl-sm', 'border-gray-100 dark:border-slate-700']">
                        <div class="relative h-36 bg-gradient-to-br from-[#D72D36] to-[#F57C83] overflow-hidden cursor-pointer"
                            @click="openTournament(msg.attachment_meta)">
                            <img v-if="msg.attachment_meta.poster_url" :src="msg.attachment_meta.poster_url"
                                class="w-full h-full object-cover transition-transform duration-300 hover:scale-105" alt="" />
                            <div v-else class="absolute inset-0 flex items-center justify-center">
                                <TrophyIcon class="w-12 h-12 text-white/80" />
                            </div>
                            <div class="absolute top-2 left-2 flex gap-1">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide bg-white/95 text-[#D72D36]">
                                    {{ msg.attachment_meta.kind === 'mini_tournament' ? 'Mini' : 'Giải đấu' }}
                                </span>
                                <span v-if="msg.attachment_meta.status_text"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-black/60 text-white">
                                    {{ msg.attachment_meta.status_text }}
                                </span>
                            </div>
                            <div v-if="msg.attachment_meta.has_fee && msg.attachment_meta.fee_amount"
                                class="absolute bottom-2 right-2 px-2 py-1 rounded-lg bg-yellow-400 text-gray-900 text-xs font-bold shadow">
                                {{ formatMoney(msg.attachment_meta.fee_amount) }}
                            </div>
                        </div>
                        <div class="px-3 py-2.5">
                            <p class="text-sm font-semibold text-gray-800 dark:text-slate-100 line-clamp-2 cursor-pointer hover:text-[#D72D36]"
                                @click="openTournament(msg.attachment_meta)">
                                {{ msg.attachment_meta.title }}
                            </p>
                            <div class="mt-1.5 space-y-0.5 text-[11px] text-gray-500 dark:text-slate-400">
                                <p v-if="msg.attachment_meta.sport_name" class="flex items-center gap-1">
                                    <span class="font-medium">{{ msg.attachment_meta.sport_name }}</span>
                                </p>
                                <p class="flex items-center gap-1.5">
                                    <ClockIcon class="w-3.5 h-3.5 shrink-0" />
                                    <span>{{ formatTournamentTime(msg.attachment_meta.start_at) }}</span>
                                </p>
                                <p v-if="msg.attachment_meta.location_name" class="flex items-center gap-1.5">
                                    <MapPinIcon class="w-3.5 h-3.5 shrink-0" />
                                    <span class="truncate">{{ msg.attachment_meta.location_name }}</span>
                                </p>
                            </div>
                            <div class="mt-2.5 flex gap-2">
                                <button @click="openTournament(msg.attachment_meta)"
                                    class="flex-1 px-3 py-1.5 rounded-lg text-xs font-medium border border-[#D72D36] text-[#D72D36] hover:bg-[#D72D36] hover:text-white transition-colors">
                                    Xem chi tiết
                                </button>
                                <button @click="joinTournament(msg.attachment_meta, msg.id)"
                                    :disabled="joiningId === msg.id || msg.attachment_meta.is_joined || msg.attachment_meta.status === 'closed' || msg.attachment_meta.status === 'cancelled' || msg.attachment_meta.status === 3 || msg.attachment_meta.status === 4"
                                    class="flex-1 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="msg.attachment_meta.is_joined
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-[#D72D36] text-white hover:bg-[#c4252e]'">
                                    <span v-if="joiningId === msg.id">Đang xử lý...</span>
                                    <span v-else-if="msg.attachment_meta.is_joined">Đã tham gia ✓</span>
                                    <span v-else>Đăng ký tham gia</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Image attachment -->
                    <div v-else-if="msg.attachment_type === 'image' && msg.attachment_meta?.url"
                        class="rounded-2xl overflow-hidden mb-1"
                        :class="isOwn(msg) ? 'rounded-br-sm' : 'rounded-bl-sm'">
                        <a :href="msg.attachment_meta.url" target="_blank" rel="noopener">
                            <img :src="msg.attachment_meta.url" class="max-w-full max-h-72 object-cover" alt="" />
                        </a>
                    </div>

                    <!-- Video attachment -->
                    <video v-else-if="msg.attachment_type === 'video' && msg.attachment_meta?.url"
                        controls class="rounded-2xl max-w-full max-h-72 mb-1"
                        :class="isOwn(msg) ? 'rounded-br-sm' : 'rounded-bl-sm'"
                        :src="msg.attachment_meta.url"></video>

                    <!-- File attachment -->
                    <a v-else-if="msg.attachment_type === 'file' && msg.attachment_meta?.url"
                        :href="msg.attachment_meta.url" target="_blank" rel="noopener"
                        class="flex items-center gap-2 px-3 py-2 rounded-2xl border mb-1"
                        :class="[
                            isOwn(msg)
                                ? 'bg-[#D72D36] text-white border-[#D72D36] rounded-br-sm'
                                : 'bg-white dark:bg-slate-800 text-gray-800 dark:text-slate-100 border-gray-100 dark:border-slate-700 rounded-bl-sm'
                        ]">
                        <PaperClipIcon class="w-4 h-4 shrink-0" />
                        <span class="text-sm truncate">{{ msg.attachment_meta.name || 'Tệp đính kèm' }}</span>
                        <span v-if="msg.attachment_meta.size" class="text-[10px] opacity-70">{{ formatSize(msg.attachment_meta.size) }}</span>
                    </a>

                    <!-- Text bubble (always render if content, even when attachment exists) -->
                    <div v-if="msg.content" class="px-3 py-2 rounded-2xl text-sm"
                        :class="isOwn(msg)
                            ? 'bg-[#D72D36] text-white rounded-br-sm'
                            : 'bg-white dark:bg-slate-800 text-gray-800 dark:text-slate-100 rounded-bl-sm border border-gray-100 dark:border-slate-700'">
                        <p class="break-words whitespace-pre-wrap">{{ msg.content }}</p>
                    </div>

                    <div class="flex items-center gap-2 mt-0.5 px-1" :class="isOwn(msg) ? 'justify-end' : 'justify-start'">
                        <p class="text-[10px] text-gray-400">
                            {{ msg.sender?.full_name || 'Bạn' }} · {{ formatTime(msg.created_at) }}
                        </p>
                        <span v-if="isOwn(msg) && msg.id === lastReadMessageId" class="text-[10px] text-[#D72D36] font-medium">Đã đọc</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attachment preview -->
        <div v-if="pendingAttachment" class="px-3 py-2 border-t border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/50 flex items-center gap-2 shrink-0">
            <img v-if="pendingAttachment.attachment_type === 'image'" :src="pendingAttachment.url" class="w-12 h-12 rounded object-cover" />
            <video v-else-if="pendingAttachment.attachment_type === 'video'" :src="pendingAttachment.url" class="w-12 h-12 rounded object-cover"></video>
            <PaperClipIcon v-else class="w-5 h-5 text-gray-400" />
            <span class="text-xs text-gray-600 dark:text-slate-300 truncate flex-1">
                {{ pendingAttachment.name || 'Đính kèm' }}
            </span>
            <button @click="pendingAttachment = null" class="text-gray-400 hover:text-red-500 text-xs">Hủy</button>
        </div>

        <footer class="p-3 border-t border-gray-100 dark:border-slate-800 shrink-0">
            <div class="flex items-end gap-2">
                <input ref="fileInput" type="file" class="hidden"
                    accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.zip"
                    @change="onFileSelected" />
                <button @click="openFilePicker" :disabled="sending"
                    class="p-2 rounded-full text-gray-500 hover:text-[#D72D36] disabled:opacity-40 transition-colors"
                    title="Đính kèm">
                    <PaperClipIcon class="w-5 h-5" />
                </button>
                <button @click="showTournamentPicker = true" :disabled="sending"
                    class="p-2 rounded-full text-gray-500 hover:text-[#D72D36] disabled:opacity-40 transition-colors"
                    title="Chia sẻ giải đấu">
                    <TrophyIcon class="w-5 h-5" />
                </button>
                <textarea v-model="draftMessage" rows="1" placeholder="Nhập tin nhắn..."
                    class="flex-1 resize-none px-3 py-2 text-sm bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl focus:outline-none focus:border-[#D72D36] text-gray-800 dark:text-slate-100 placeholder:text-gray-400 max-h-24"
                    :disabled="sending"
                    @keydown.enter.exact.prevent="sendMessage"></textarea>
                <button @click="sendMessage" :disabled="!canSend || sending"
                    class="p-2 rounded-full bg-[#D72D36] text-white hover:bg-[#c4252e] disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    title="Gửi">
                    <PaperAirplaneIcon class="w-5 h-5" />
                </button>
            </div>
        </footer>

        <!-- Tournament picker modal -->
        <Teleport to="body">
            <div v-if="showTournamentPicker"
                class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
                @click.self="showTournamentPicker = false">
                <div class="bg-white dark:bg-slate-900 rounded-2xl w-full max-w-md max-h-[80vh] flex flex-col">
                    <div class="p-4 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between">
                        <h3 class="font-semibold text-sm">Chia sẻ giải đấu</h3>
                        <button @click="showTournamentPicker = false" class="text-gray-400 hover:text-gray-600 text-sm">Đóng</button>
                    </div>
                    <div class="p-3 border-b border-gray-100 dark:border-slate-800">
                        <input v-model="tournamentSearch" type="text" placeholder="Tìm giải đấu..."
                            class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-[#D72D36]" />
                    </div>
                    <div class="flex-1 overflow-y-auto p-2 space-y-1">
                        <div v-if="loadingTournaments" class="text-center py-8 text-xs text-gray-400">Đang tải...</div>
                        <div v-else-if="filteredTournaments.length === 0" class="text-center py-8 text-xs text-gray-400">Không có giải đấu</div>
                        <button v-for="t in filteredTournaments" :key="t.id"
                            @click="pickTournament(t)"
                            class="w-full flex items-center gap-3 p-2 hover:bg-gray-50 dark:hover:bg-slate-800 rounded-xl text-left">
                            <img v-if="t.poster_url" :src="t.poster_url" class="w-12 h-12 rounded-lg object-cover bg-gray-100" />
                            <div v-else class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center">
                                <TrophyIcon class="w-5 h-5 text-gray-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 dark:text-slate-100 truncate">
                                    {{ t.name }}
                                    <span v-if="t.kind === 'mini_tournament'" class="text-[10px] text-[#D72D36] ml-1">Mini</span>
                                </p>
                                <p class="text-[11px] text-gray-400">
                                    <span v-if="t.sport_name">{{ t.sport_name }} · </span>{{ formatDate(t.start_at) }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, nextTick, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
    PaperAirplaneIcon,
    ChatBubbleLeftRightIcon,
    PaperClipIcon,
    TrophyIcon,
    ClockIcon,
    MapPinIcon,
} from '@heroicons/vue/24/outline'
import { useUserStore } from '@/store/auth'
import axiosInstance from '@/utils/httpRequest.js'
import { clubChatService } from '@/service/clubChat.js'
import { joinTournament as joinTournamentApi } from '@/service/participant.js'

const props = defineProps({
    clubId: { type: [Number, String], required: true },
    clubName: { type: String, default: 'CLB' }
})

const userStore = useUserStore()
const router = useRouter()
const currentUserId = computed(() => userStore.getUser?.id)
const conversationName = computed(() => `Nhóm chat ${props.clubName}`)

const loadingMessages = ref(false)
const sending = ref(false)
const messagesContainer = ref(null)
const fileInput = ref(null)
const messages = ref([])
const conversation = ref(null)
const draftMessage = ref('')
const echoChannel = ref(null)
const lastReadMessageId = ref(null)
const myLastReadId = ref(null)
const pendingAttachment = ref(null)
const showTournamentPicker = ref(false)
const tournaments = ref([])
const loadingTournaments = ref(false)
const tournamentSearch = ref('')
const joiningId = ref(null)

const seenKey = computed(() => `club_chat_seen_${props.clubId}`)

const isOwn = (msg) => msg.sender?.id === currentUserId.value

const unreadCount = computed(() => {
    if (!myLastReadId.value) return messages.value.length
    return messages.value.filter(m => !isOwn(m) && m.id > myLastReadId.value).length
})

const canSend = computed(() => draftMessage.value.trim() || pendingAttachment.value)

const filteredTournaments = computed(() => {
    const kw = tournamentSearch.value.toLowerCase().trim()
    if (!kw) return tournaments.value
    return tournaments.value.filter(t => t.name?.toLowerCase().includes(kw))
})

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

const formatDate = (str) => {
    if (!str) return ''
    const d = new Date(str)
    if (isNaN(d)) return ''
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const formatSize = (bytes) => {
    if (!bytes) return ''
    if (bytes < 1024) return `${bytes}B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)}KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)}MB`
}

const formatMoney = (n) => {
    if (n == null) return ''
    return new Intl.NumberFormat('vi-VN').format(n) + 'đ'
}

const formatTournamentTime = (str) => {
    if (!str) return ''
    const d = new Date(str)
    if (isNaN(d)) return ''
    const dateStr = d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
    const timeStr = d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
    return `${dateStr} · ${timeStr}`
}

const TOURNAMENT_STATUS_TEXT = {
    1: 'Bản nháp',
    2: 'Mở đăng ký',
    3: 'Đã đóng',
    4: 'Đã hủy',
    draft: 'Bản nháp',
    open: 'Mở đăng ký',
    closed: 'Đã đóng',
    cancelled: 'Đã hủy',
    scheduled: 'Sắp diễn ra',
    ongoing: 'Đang diễn ra',
    completed: 'Đã kết thúc',
}

const STATUS_OPEN = new Set([2, 'open', 'scheduled', 'ongoing'])

const openTournament = (meta) => {
    if (!meta?.tournament_id) return
    const name = meta.kind === 'mini_tournament' ? 'mini-tournament-detail' : 'tournament-detail'
    router.push({ name, params: { id: meta.tournament_id } })
}

const joinTournament = async (meta, msgId) => {
    if (!meta?.tournament_id || joiningId.value !== null) return
    joiningId.value = msgId
    try {
        if (meta.kind === 'mini_tournament') {
            await axiosInstance.post(`/mini-participants/join/${meta.tournament_id}`)
        } else {
            await joinTournamentApi(meta.tournament_id)
        }
        meta.is_joined = true
    } catch (e) {
        const msg = e?.response?.data?.message || 'Đăng ký thất bại, thử lại sau'
        alert(msg)
        console.error('Lỗi join tournament:', e)
    } finally {
        joiningId.value = null
    }
}

// Multi-tab dedupe
const getSeenIds = () => {
    try { return new Set(JSON.parse(localStorage.getItem(seenKey.value) || '[]')) }
    catch { return new Set() }
}
const rememberId = (id) => {
    const seen = getSeenIds()
    seen.add(id)
    const arr = Array.from(seen).slice(-200)
    localStorage.setItem(seenKey.value, JSON.stringify(arr))
}
const hasMessage = (id) => messages.value.some(m => m.id === id)

const scrollToBottom = async () => {
    await nextTick()
    if (messagesContainer.value) messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
}

const markLatestAsRead = async () => {
    const last = messages.value[messages.value.length - 1]
    if (!last || isOwn(last)) return
    if (last.id <= (myLastReadId.value || 0)) return
    try {
        await clubChatService.markRead(props.clubId, last.id)
        myLastReadId.value = last.id
    } catch (e) { console.error('Lỗi đánh dấu đã đọc:', e) }
}

const fetchConversation = async () => {
    try {
        const res = await clubChatService.getConversation(props.clubId)
        const conv = res.data?.data || res.data
        if (conv) conversation.value = conv
    } catch (e) { console.error('Lỗi tải conversation:', e) }
}

const fetchMessages = async () => {
    if (!conversation.value?.id) return
    loadingMessages.value = true
    try {
        const res = await clubChatService.getMessages(props.clubId, { per_page: 50, page: 1 })
        const list = res.data?.data || []
        messages.value = [...list].reverse()
        list.forEach(m => rememberId(m.id))
        await scrollToBottom()
        markLatestAsRead()
    } catch (e) { console.error('Lỗi tải messages:', e) }
    finally { loadingMessages.value = false }
}

const fetchReads = async () => {
    if (!conversation.value?.id) return
    try {
        const res = await clubChatService.getReads(props.clubId)
        const list = res.data?.data || []
        const own = list.find(r => r.user_id === currentUserId.value)
        if (own) myLastReadId.value = own.last_read_message_id
        const others = list.filter(r => r.user_id !== currentUserId.value)
        const maxRead = others.reduce((acc, r) => Math.max(acc, r.last_read_message_id || 0), 0)
        if (maxRead > (lastReadMessageId.value || 0)) lastReadMessageId.value = maxRead
    } catch (e) { console.error('Lỗi tải reads:', e) }
}

const openFilePicker = () => fileInput.value?.click()

const onFileSelected = async (e) => {
    const file = e.target.files?.[0]
    e.target.value = ''
    if (!file) return
    sending.value = true
    try {
        const res = await clubChatService.uploadFile(props.clubId, file)
        const data = res.data?.data || res.data
        pendingAttachment.value = data
    } catch (err) {
        console.error('Lỗi upload:', err)
        alert('Upload thất bại, thử lại sau')
    } finally {
        sending.value = false
    }
}

const pickTournament = (t) => {
    pendingAttachment.value = {
        attachment_type: 'tournament',
        kind: t.kind,
        tournament_id: t.id,
        title: t.name,
        start_at: t.start_at,
        poster_url: t.poster_url,
        sport_name: t.sport_name,
        status: t.status,
    }
    showTournamentPicker.value = false
}

const loadTournaments = async () => {
    loadingTournaments.value = true
    try {
        const res = await clubChatService.getTournaments(props.clubId)
        tournaments.value = res.data?.data || []
    } catch (e) {
        console.error('Lỗi tải tournaments:', e)
    } finally {
        loadingTournaments.value = false
    }
}

const sendMessage = async () => {
    if (!canSend.value || sending.value) return
    sending.value = true
    const content = draftMessage.value.trim()
    const att = pendingAttachment.value
    try {
        const payload = { content }
        if (att) {
            payload.attachment_type = att.attachment_type
            if (att.attachment_type === 'tournament') {
                payload.attachment_meta = {
                    tournament_id: att.tournament_id,
                    kind: att.kind,
                }
            } else {
                payload.attachment_meta = {
                    url: att.url,
                    path: att.path,
                    name: att.name,
                    size: att.size,
                    mime: att.mime,
                }
            }
        }
        const res = await clubChatService.sendMessage(props.clubId, payload)
        const newMsg = res.data?.data
        if (newMsg && !hasMessage(newMsg.id)) {
            messages.value.push({
                ...newMsg,
                sender: newMsg.sender || { id: currentUserId.value, full_name: userStore.getUser?.full_name }
            })
            rememberId(newMsg.id)
            draftMessage.value = ''
            pendingAttachment.value = null
            await scrollToBottom()
        }
    } catch (e) {
        console.error('Lỗi gửi tin nhắn:', e)
    } finally {
        sending.value = false
    }
}

const setupEcho = () => {
    if (echoChannel.value || !window.Echo || !props.clubId) return
    const channel = window.Echo.private(`club.${props.clubId}`)
    channel.listen('.club.chat.message.sent', (data) => {
        const msg = data?.message
        if (!msg || hasMessage(msg.id)) return
        messages.value.push(msg)
        rememberId(msg.id)
        nextTick(() => { scrollToBottom(); markLatestAsRead() })
    })
    channel.listen('.club.chat.message.read', (data) => {
        if (!data || data.user_id === currentUserId.value) return
        if (data.conversation_id !== conversation.value?.id) return
        const incomingId = data.last_read_message_id || 0
        if (incomingId > (lastReadMessageId.value || 0)) lastReadMessageId.value = incomingId
    })
    echoChannel.value = channel
}

const teardownEcho = () => {
    if (!echoChannel.value) return
    try {
        echoChannel.value.stopListening('.club.chat.message.sent')
        echoChannel.value.stopListening('.club.chat.message.read')
        echoChannel.value.unsubscribe()
    } catch {}
    echoChannel.value = null
}

watch(() => props.clubId, async (newId) => {
    teardownEcho()
    messages.value = []
    conversation.value = null
    lastReadMessageId.value = null
    myLastReadId.value = null
    pendingAttachment.value = null
    if (newId) {
        await fetchConversation()
        await Promise.all([fetchMessages(), fetchReads()])
        setupEcho()
    }
}, { immediate: true })

watch(showTournamentPicker, (open) => {
    if (open && tournaments.value.length === 0) loadTournaments()
})

onMounted(() => setupEcho())

onUnmounted(() => {
    teardownEcho()
})
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #E5E7EB; border-radius: 20px; }
</style>
