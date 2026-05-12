<template>
  <div class="flex flex-col h-full">
    <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 mx-10 space-y-4" @scroll="handleScroll">
      <div v-if="!loading">
        <div v-if="loadingMore" class="flex justify-center mb-4">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-[#5422C6]"></div>
        </div>

        <div v-for="message in messages" :key="message.id"
          :class="['flex items-start gap-3 mb-4', message.is_own ? 'flex-row-reverse' : '']">
          <div :class="['flex-1', message.is_own ? 'flex flex-col items-end' : '']">
            <div :class="[
              'rounded-md px-4 py-3 max-w-[50%]',
              message.is_own
                ? 'bg-[#f8f5ff] border border-1 border-[#5422C6]'
                : 'bg-[#EDEEF2] grid grid-cols-[auto_1fr] gap-3 items-start'
            ]">
              <div v-if="!message.is_own" class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0">
                <img :src="message.user.avatar_url || '/images/default-avatar.png'" :alt="message.user.full_name"
                  class="w-full h-full object-cover" />
              </div>
              <div class="min-w-0">
                <p v-if="!message.is_own" class="text-xs text-gray-500 mb-1">
                  {{ message.user.full_name }}
                </p>
                <p v-if="message.type === 'text'"
                  :class="['text-sm leading-relaxed break-words', message.is_own ? 'text-[#3E414C]' : 'text-gray-800']">
                  {{ message.content }}
                </p>

                <!-- Image -->
                <img v-else-if="message.type === 'image'" :src="getFileUrl(message.content)" alt="Image"
                  class="max-w-full max-h-80 w-auto h-auto rounded-md cursor-pointer hover:opacity-90 object-contain"
                  @click="openImagePreview(message.content)" />

                <!-- Voice -->
                <div v-else-if="message.type === 'voice'" class="flex items-center gap-2">
                  <button @click="toggleAudio(message.id)"
                    class="w-8 h-8 flex items-center justify-center bg-[#5422C6] text-white rounded-full hover:bg-[#4319a8] flex-shrink-0">
                    <span v-if="!playingAudio[message.id]">▶</span>
                    <span v-else>⏸</span>
                  </button>
                  <audio :ref="el => audioRefs[message.id] = el" :src="getFileUrl(message.content)"
                    @ended="playingAudio[message.id] = false"></audio>
                  <div class="flex-1 h-1 bg-gray-300 rounded-full min-w-0">
                    <div class="h-full bg-[#5422C6] rounded-full" :style="{ width: audioProgress[message.id] || '0%' }">
                    </div>
                  </div>
                </div>

                <!-- Emoji -->
                <span v-else-if="message.type === 'emoji'" class="text-4xl">
                  {{ message.content }}
                </span>

                <!-- File -->
                <a v-else-if="message.type === 'file'" :href="getFileUrl(message.content)" download target="_blank"
                  class="flex items-center gap-2 text-sm text-blue-600 hover:underline break-all">
                  <span class="flex-shrink-0">📎</span>
                  <span class="truncate">{{ message.meta?.filename || 'File' }}</span>
                  <span class="text-xs text-gray-500 flex-shrink-0">({{ formatFileSize(message.meta?.size) }})</span>
                </a>

                <!-- Time -->
                <p class="text-xs text-gray-400 mt-1">
                  {{ formatTime(message.created_at) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400">
          <p>Chưa có tin nhắn nào</p>
        </div>
      </div>

      <div v-else class="flex justify-center items-center h-full">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#5422C6]"></div>
      </div>
    </div>

    <!-- Input Area -->
    <div class="flex-shrink-0 border-gray-200 flex justify-between items-center px-4 py-4">
      <!-- Photo Upload -->
      <label for="photoInput" class="cursor-pointer">
        <PhotoIcon class="w-8 h-8 text-[#3E414C] mr-3 hover:text-[#5422C6]" />
        <input id="photoInput" type="file" class="hidden" @change="handleImageSelect" accept="image/*" />
      </label>

      <!-- Text Input -->
      <div class="flex-1 flex items-center relative" ref="emojiContainer">
        <input v-model="newMessage" type="text" placeholder="Viết tin nhắn" @keypress.enter="sendMessage"
          class="w-full border border-gray-300 bg-[#edeef2] rounded-full pl-4 pr-4 py-2 focus:outline-none focus:ring-1 focus:ring-[#5422C6] placeholder:text-[#BBBFCC] placeholder:text-sm" />
        <FaceSmileIcon class="w-7 h-7 text-[#3E414C] absolute right-3 cursor-pointer hover:text-[#5422C6]" @click="showEmoji = !showEmoji" />
        <EmojiPicker
        v-if="showEmoji"
          :native="true"
          @select="addEmoji"
          class="absolute z-50 right-0 bottom-12 select-none"
        />
      </div>

      <!-- Send Button -->
      <PaperAirplaneIcon @click="sendMessage"
        :class="['w-8 h-8 ml-3 cursor-pointer transition-colors', sending ? 'text-gray-400' : 'text-[#3E414C] hover:text-[#5422C6]']" />
    </div>

    <!-- Image Preview Modal -->
    <div v-if="showImagePreview" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4"
      @click.self="cancelImagePreview">
      <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center p-4 border-b">
          <h3 class="text-lg font-semibold text-gray-800">Gửi hình ảnh</h3>
          <button @click="cancelImagePreview" class="text-gray-500 hover:text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
              stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="flex-1 overflow-auto p-4 flex items-center justify-center bg-gray-100">
          <img :src="previewImageUrl" alt="Preview" class="max-w-full max-h-[400px] object-contain rounded-lg" />
        </div>

        <div class="p-4 border-t">
          <input v-model="imageCaption" type="text" placeholder="Thêm chú thích (tùy chọn)"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#5422C6] placeholder:text-gray-400" />
        </div>

        <div class="flex justify-end gap-3 p-4 border-t bg-gray-50">
          <button @click="cancelImagePreview"
            class="px-4 py-2 text-gray-700 hover:bg-gray-200 rounded-lg transition-colors">
            Hủy
          </button>
          <button @click="sendImageWithCaption" :disabled="uploading"
            :class="['px-6 py-2 rounded-lg transition-colors', uploading ? 'bg-gray-400 cursor-not-allowed' : 'bg-[#5422C6] hover:bg-[#4319a8] text-white']">
            {{ uploading ? 'Đang gửi...' : 'Gửi' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import { PaperAirplaneIcon, PhotoIcon } from '@heroicons/vue/24/solid'
import { FaceSmileIcon } from '@heroicons/vue/24/outline'
import * as SendMessageService from '@/service/message.js'
import { toast } from 'vue3-toastify'
import { useUserStore } from "@/store/auth";
import { storeToRefs } from "pinia";

const userStore = useUserStore();
const { getUser } = storeToRefs(userStore);
const props = defineProps({
    miniTournamentId: { type: Number, required: true }
})

// STATE
const messages = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const uploading = ref(false)
const sending = ref(false)
const newMessage = ref('')
const page = ref(1)
const meta = ref(null)
const messagesContainer = ref(null)
const audioRefs = reactive({})
const playingAudio = reactive({})
const audioProgress = reactive({})

const showImagePreview = ref(false)
const previewImageUrl = ref('')
const selectedImageFile = ref(null)
const imageCaption = ref('')
const showEmoji = ref(false)
const emojiContainer = ref(null)

function addEmoji(e) {
  newMessage.value +=e.i
}

function handleClickOutside(event) {
  if (emojiContainer.value && !emojiContainer.value.contains(event.target)) {
    showEmoji.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})

// FETCH MESSAGES
const fetchMessages = async (pageNumber = 1, isLoadMore = false) => {
  try {
    if (isLoadMore) loadingMore.value = true
    else loading.value = true

    const response = await SendMessageService.getMiniTournamentMessages(props.miniTournamentId, { per_page: 20, page: pageNumber })
    if (pageNumber === 1) messages.value = response.data || []
    else messages.value = [...(response.data || []), ...messages.value]
    meta.value = response.meta
  } catch (err) {
    console.error(err)
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

// SCROLL & LOAD MORE
const handleScroll = async () => {
  if (!messagesContainer.value || loadingMore.value) return
  const container = messagesContainer.value
  if (container.scrollTop < 100 && meta.value && meta.value.current_page < meta.value.last_page) {
    const oldHeight = container.scrollHeight
    page.value += 1
    await fetchMessages(page.value, true)
    await nextTick()
    container.scrollTop = container.scrollHeight - oldHeight + container.scrollTop
  }
}

const scrollToBottom = (smooth = false) => {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTo({ top: messagesContainer.value.scrollHeight, behavior: smooth ? 'smooth' : 'auto' })
  }
}

// SEND MESSAGE
const sendMessage = async () => {
  if (!newMessage.value.trim() || sending.value) return
  const content = newMessage.value.trim()
  newMessage.value = ''
  try {
    sending.value = true
    await SendMessageService.sendMiniTournamentMessage(props.miniTournamentId, { type: 'text', content })
    await nextTick()
    scrollToBottom(true)
  } catch (err) {
    console.error(err)
    toast.error('Không thể gửi tin nhắn')
    newMessage.value = content
  } finally {
    sending.value = false
  }
}

// IMAGE UPLOAD
const handleImageSelect = (event) => {
  const file = event.target.files[0]
  if (!file || !file.type.startsWith('image/')) return toast.error('Chỉ chọn hình ảnh')
  if (file.size > 10240 * 1024) return toast.error('Hình quá lớn (>10MB)')
  selectedImageFile.value = file
  previewImageUrl.value = URL.createObjectURL(file)
  showImagePreview.value = true
  imageCaption.value = ''
  event.target.value = ''
}

const cancelImagePreview = () => {
  showImagePreview.value = false
  if (previewImageUrl.value) URL.revokeObjectURL(previewImageUrl.value)
  previewImageUrl.value = ''
  selectedImageFile.value = null
  imageCaption.value = ''
}

const sendImageWithCaption = async () => {
  if (!selectedImageFile.value || uploading.value) return
  try {
    uploading.value = true
    const form = new FormData()
    form.append('type', 'image')
    form.append('file', selectedImageFile.value)
    await SendMessageService.sendMiniTournamentMessage(props.miniTournamentId, form)
    if (imageCaption.value.trim()) {
      await SendMessageService.sendMiniTournamentMessage(props.miniTournamentId, { type: 'text', content: imageCaption.value.trim() })
    }
    cancelImagePreview()
    await nextTick()
    scrollToBottom(true)
  } catch (err) {
    console.error(err)
    toast.error('Không gửi được hình ảnh')
  } finally {
    uploading.value = false
  }
}

// AUDIO CONTROLS
const toggleAudio = (id) => {
  const audio = audioRefs[id]; if (!audio) return
  if (playingAudio[id]) { audio.pause(); playingAudio[id] = false }
  else {
    Object.keys(playingAudio).forEach(i => { if (audioRefs[i]) audioRefs[i].pause(); playingAudio[i] = false })
    audio.play()
    playingAudio[id] = true
    audio.ontimeupdate = () => { audioProgress[id] = `${(audio.currentTime / audio.duration) * 100}%` }
  }
}

// FILE URL
const getFileUrl = (path) => path.startsWith('http') ? path : `${import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'}/storage/${path}`
const openImagePreview = (path) => window.open(getFileUrl(path), '_blank')
const formatTime = (str) => { const d = new Date(str); const now = new Date(); return (now - d) < 24 * 60 * 60 * 1000 ? d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) : d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' }) }
const formatFileSize = (bytes) => !bytes ? '' : bytes < 1024 ? bytes + ' B' : bytes < 1024 * 1024 ? (bytes / 1024).toFixed(1) + ' KB' : (bytes / (1024 * 1024)).toFixed(1) + ' MB'
const addIsOwn = (message) => ({
  ...message,
  is_own: message.user_id === getUser.value.id
})
// REALTIME LISTENER
let echoChannel = null
const setupRealtime = () => {
  if (!props.miniTournamentId) return
  echoChannel = window.Echo.private(`mini-tournament.${props.miniTournamentId}`)
  echoChannel.listen('.mini-tournament-sent', async (e) => {
    const messageWithOwnership = addIsOwn(e.message)
    messages.value.push(messageWithOwnership)
    await nextTick()
    scrollToBottom(true)
  })
    .error((error) => {
      console.error('❌ Echo error:', error)
    })
}

// LIFECYCLE
onMounted(async () => {
  await fetchMessages()
  await nextTick()
  scrollToBottom(false)
  setupRealtime()
})

onBeforeUnmount(() => {
  if (echoChannel) window.Echo.leave(`private-mini-tournament.${props.miniTournamentId}`)
})

// WATCH MINI TOURNAMENT CHANGE
watch(() => props.miniTournamentId, async (newId, oldId) => {
  if (oldId && echoChannel) window.Echo.leave(`private-mini-tournament.${oldId}`)
  page.value = 1
  await fetchMessages()
  await nextTick()
  scrollToBottom(false)
})
</script>

<style scoped>
.overflow-y-auto::-webkit-scrollbar {
  width: 0;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #555;
}
</style>
