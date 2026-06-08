<template>
  <Teleport to="body">
    <div
      v-if="isVisible"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
      @click.self="close"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <h3 class="font-bold text-gray-900">Chia sẻ giải đấu</h3>
          <button @click="close" class="p-1 hover:bg-gray-100 rounded-full transition">
            <XMarkIcon class="w-5 h-5 text-gray-500" />
          </button>
        </div>

        <!-- Card Preview -->
        <div class="px-5 py-4">
          <p class="text-xs text-gray-400 mb-2 font-medium">Xem trước</p>
          <div ref="cardRef" class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="relative">
              <img
                v-if="tournament.poster"
                :src="tournament.poster"
                :alt="tournament.name"
                class="w-full h-48 object-cover"
              />
              <div v-else class="w-full h-48 bg-gradient-to-br from-[#BA110B] to-[#520011] flex items-center justify-center">
                <span class="text-white text-7xl font-bold opacity-30">{{ tournament.name?.charAt(0) }}</span>
              </div>
              <div class="absolute top-3 left-3 flex gap-2">
                <span v-if="tournament.sport" class="bg-black/60 text-white text-xs px-2 py-1 rounded-full backdrop-blur-sm flex items-center gap-1">
                  <img v-if="tournament.sport.icon" :src="tournament.sport.icon" class="w-3 h-3" :alt="tournament.sport.name" />
                  {{ tournament.sport.name }}
                </span>
                <span class="bg-black/60 text-white text-xs px-2 py-1 rounded-full backdrop-blur-sm">
                  {{ registrationStatus }}
                </span>
              </div>
            </div>
            <div class="p-3">
              <h4 class="font-bold text-gray-900 text-base leading-tight mb-2 line-clamp-2">{{ tournament.name }}</h4>
              <div class="flex items-center gap-3 text-xs text-gray-500">
                <span v-if="tournament.competition_location" class="flex items-center gap-1">
                  <MapPinIcon class="w-3 h-3" />
                  {{ tournament.competition_location.name }}
                </span>
                <span v-if="tournament.start_date" class="flex items-center gap-1">
                  <CalendarDaysIcon class="w-3 h-3" />
                  {{ formatEventDate(tournament.start_date) }}
                </span>
              </div>
              <div class="flex items-center gap-2 mt-3">
                <div class="w-4 h-4 rounded-sm bg-[#D72D36] flex items-center justify-center flex-shrink-0">
                  <span class="text-white text-[8px] font-bold leading-none">P</span>
                </div>
                <span class="text-xs text-gray-400 font-medium">picki.vn</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Share Buttons -->
        <div class="px-5 pb-5 space-y-2">
          <!-- Native Share (mobile) -->
          <button
            v-if="canNativeShare"
            @click="nativeShare"
            class="w-full flex items-center gap-3 px-4 py-3 bg-[#D72D36] text-white rounded-xl font-semibold hover:bg-red-700 transition"
          >
            <ShareIcon class="w-5 h-5" />
            Chia sẻ ngay
          </button>

          <!-- Desktop share options -->
          <div v-if="!canNativeShare" class="grid grid-cols-3 gap-2">
            <button @click="copyLink" class="flex flex-col items-center gap-1.5 px-3 py-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition">
              <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-sm">
                <LinkIcon class="w-5 h-5 text-gray-700" />
              </div>
              <span class="text-xs text-gray-600 font-medium">Sao chép</span>
            </button>
            <button @click="shareFacebook" class="flex flex-col items-center gap-1.5 px-3 py-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition">
              <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
              </div>
              <span class="text-xs text-gray-600 font-medium">Facebook</span>
            </button>
            <button @click="shareZalo" class="flex flex-col items-center gap-1.5 px-3 py-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition">
              <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h-2v-6h2v6zm4 0h-2v-6h2v6zm0-8H9V7h6v2z"/>
                </svg>
              </div>
              <span class="text-xs text-gray-600 font-medium">Zalo</span>
            </button>
          </div>

          <!-- Copy link feedback -->
          <div v-if="copySuccess" class="flex items-center gap-2 text-green-600 text-sm font-medium animate-pulse">
            <CheckCircleIcon class="w-4 h-4" />
            Đã sao chép liên kết!
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import {
  XMarkIcon,
  ShareIcon,
  LinkIcon,
  MapPinIcon,
  CalendarDaysIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline'
import { formatEventDate } from '@/composables/formatDatetime.js'

const props = defineProps({
  isVisible: Boolean,
  tournament: Object,
})
const emit = defineEmits(['close'])

const cardRef = ref(null)
const copySuccess = ref(false)

const canNativeShare = computed(() => typeof navigator !== 'undefined' && !!navigator.share)
const webShareUrl = 'https://picki.vn/tournament-landing'

const registrationStatus = computed(() => {
  if (!props.tournament) return ''
  const now = new Date()
  const open = props.tournament.registration_open_at ? new Date(props.tournament.registration_open_at) : null
  const close = props.tournament.registration_closed_at ? new Date(props.tournament.registration_closed_at) : null
  if (open && now < open) return 'Sắp mở'
  if (close && now > close) return 'Đã đóng'
  return 'Đang mở'
})

const shareUrl = computed(() => {
  const base = webShareUrl
  return `${base}/${props.tournament?.id}`
})

const shareTitle = computed(() => props.tournament?.name || '')
const shareText = computed(() => {
  const t = props.tournament
  if (!t) return ''
  const location = t.competition_location?.name || ''
  const date = t.start_date ? formatEventDate(t.start_date) : ''
  const parts = [location, date].filter(Boolean)
  return parts.length ? `📢 ${t.name} - ${parts.join(' • ')}` : `📢 ${t.name}`
})

function close() {
  emit('close')
}

async function nativeShare() {
  try {
    await navigator.share({
      title: shareTitle.value,
      text: shareText.value,
      url: shareUrl.value,
    })
  } catch (err) {
    if (err.name !== 'AbortError') {
      console.error('Share failed:', err)
    }
  }
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(shareUrl.value)
    copySuccess.value = true
    setTimeout(() => { copySuccess.value = false }, 2500)
  } catch {
    fallbackCopy(shareUrl.value)
  }
}

function fallbackCopy(text) {
  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.style.position = 'fixed'
  textarea.style.opacity = '0'
  document.body.appendChild(textarea)
  textarea.select()
  document.execCommand('copy')
  textarea.remove()
  copySuccess.value = true
  setTimeout(() => { copySuccess.value = false }, 2500)
}

function shareFacebook() {
  const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl.value)}`
  window.open(url, '_blank', 'width=600,height=400')
}

function shareZalo() {
  const url = `https://zalo.me/share?url=${encodeURIComponent(shareUrl.value)}&title=${encodeURIComponent(shareTitle.value)}`
  window.open(url, '_blank', 'width=600,height=400')
}
</script>
