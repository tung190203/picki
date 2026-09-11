<template>
  <div class="chatbot-widget-container font-sans">
    <!-- Floating Action Button (FAB) -->
    <transition name="pop-fade">
      <button
        v-if="!isOpen"
        id="picki-chatbot-fab"
        @click="toggleChat"
        class="fixed z-[9999] w-12 h-12 bg-primary hover:opacity-90 text-white rounded-full shadow-xl hover:scale-105 active:scale-95 transition-all duration-200 flex items-center justify-center border border-white/20"
        style="bottom: 72px; right: 12px;"
        title="Hỗ trợ Picki"
        aria-label="Hỗ trợ Picki"
      >
        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
      </button>
    </transition>

    <!-- Chat Window Popup -->
    <transition name="chat-slide">
      <div
        v-if="isOpen"
        id="picki-chatbot-window"
        class="fixed z-[9999] w-[360px] sm:w-[390px] max-w-[calc(100vw-24px)] h-[560px] max-h-[calc(100vh-60px)] bg-white dark:bg-[#161F33] rounded-2xl shadow-2xl border border-gray-200 dark:border-[#233148] flex flex-col overflow-hidden text-gray-800 dark:text-[#F8FAFC]"
        style="bottom: 16px; right: 12px;"
      >
        <!-- Header -->
        <div class="bg-primary px-4 py-3 text-white flex items-center justify-between shadow-sm select-none">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold text-sm text-white border border-white/30">
              P
            </div>
            <div>
              <h3 class="font-bold text-sm leading-tight text-white">Hỗ trợ Picki</h3>
              <p class="text-[11px] text-white/80 flex items-center gap-1.5 mt-0.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                Trực tuyến
              </p>
            </div>
          </div>

          <!-- Header Actions -->
          <div class="flex items-center gap-1">
            <button
              @click="resetChat"
              title="Làm mới đoạn chat"
              class="p-1.5 hover:bg-white/20 rounded-lg transition-colors text-white/80 hover:text-white"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </button>
            <button
              @click="toggleChat"
              title="Đóng"
              class="p-1.5 hover:bg-white/20 rounded-lg transition-colors text-white/80 hover:text-white"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Messages Body -->
        <div ref="messagesBox" class="chat-messages-scroll flex-1 p-3.5 overflow-y-auto space-y-3.5 bg-gray-50/60 dark:bg-[#0B0F19] text-xs sm:text-[13px]">
          <!-- Initial Welcome -->
          <div class="flex items-start gap-2.5">
            <div class="w-7 h-7 rounded-full bg-primary text-white font-bold flex items-center justify-center flex-shrink-0 text-xs mt-0.5">
              P
            </div>
            <div class="flex-1 space-y-2">
              <div class="bg-white dark:bg-[#1E293B] border border-gray-200 dark:border-[#233148] rounded-2xl rounded-tl-sm p-3 shadow-2xs text-gray-700 dark:text-slate-200 leading-relaxed">
                <p class="font-semibold text-gray-900 dark:text-[#F8FAFC] mb-1">Xin chào bạn!</p>
                <p class="mb-2">Mình là nhân viên hỗ trợ của Picki. Bạn có thể hỏi mình thông tin về:</p>
                <ul class="space-y-1 list-none pl-0 text-gray-600 dark:text-slate-300 text-xs">
                  <li class="flex items-center gap-1.5">• Giải đấu và lịch thi đấu</li>
                  <li class="flex items-center gap-1.5">• Địa chỉ cụm sân và liên hệ đặt sân</li>
                  <li class="flex items-center gap-1.5">• Quy định và luật thi đấu Pickleball</li>
                </ul>
              </div>

              <!-- Quick Prompts Pills -->
              <div v-if="messages.length <= 1" class="pt-1">
                <p class="text-[11px] font-medium text-gray-400 dark:text-slate-400 mb-1.5">Gợi ý câu hỏi:</p>
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="p in quickPrompts"
                    :key="p.id"
                    @click="sendQuickPrompt(p.prompt)"
                    class="text-xs px-3 py-1.5 bg-white dark:bg-[#1E293B] hover:bg-primary hover:text-white dark:hover:bg-primary dark:hover:text-white text-gray-700 dark:text-slate-200 rounded-full transition-all border border-gray-200 dark:border-[#334155] font-medium text-left shadow-2xs active:scale-95"
                  >
                    {{ p.title }}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Messages Stream -->
          <template v-for="(msg, index) in messages" :key="index">
            <!-- User Message -->
            <div v-if="msg.role === 'user'" class="flex items-end justify-end gap-2">
              <div class="max-w-[82%] bg-primary text-white rounded-2xl rounded-br-sm px-3.5 py-2.5 shadow-2xs text-xs sm:text-[13px] leading-relaxed break-words">
                {{ msg.text }}
              </div>
            </div>

            <!-- Bot Message -->
            <div v-else class="flex items-start gap-2.5">
              <div class="w-7 h-7 rounded-full bg-primary text-white font-bold flex items-center justify-center flex-shrink-0 text-xs mt-0.5">
                P
              </div>

              <div class="flex-1 space-y-2 max-w-[86%]">
                <!-- Text Bubble -->
                <div class="bg-white dark:bg-[#1E293B] border border-gray-200 dark:border-[#233148] rounded-2xl rounded-tl-sm p-3.5 shadow-2xs text-gray-800 dark:text-[#F8FAFC] text-xs sm:text-[13px] leading-relaxed">
                  <div class="inline markdown-content" v-html="formatMarkdown(msg.text)"></div>
                  <span v-if="msg.isTyping" class="inline-block w-1.5 h-3.5 bg-primary ml-0.5 animate-pulse align-middle rounded-xs"></span>
                </div>

                <!-- Interactive Cards (Tournaments or Courts) -->
                <div v-if="msg.cards && msg.cards.length > 0 && !msg.isTyping" class="space-y-2 pt-1 transition-all duration-300">
                  <div
                    v-for="card in msg.cards"
                    :key="card.id"
                    class="bg-white dark:bg-[#1E293B] rounded-xl border border-gray-200 dark:border-[#233148] p-2.5 shadow-2xs hover:shadow-sm dark:hover:border-primary/50 transition-shadow flex items-center gap-3 group"
                  >
                    <!-- Image or Neutral Icon -->
                    <div class="card-thumbnail-box w-14 h-14 min-w-[56px] min-h-[56px] max-w-[56px] max-h-[56px] rounded-lg bg-gray-100 dark:bg-[#0F172A] flex-shrink-0 overflow-hidden flex items-center justify-center relative border border-gray-200 dark:border-[#334155]">
                      <img
                        v-if="card.image"
                        :src="card.image"
                        :alt="card.title"
                        class="w-full h-full object-cover rounded-lg group-hover:scale-105 transition-transform duration-300 block"
                        @error="handleImageError"
                      />
                      <svg v-else-if="card.type === 'tournament'" class="w-6 h-6 text-gray-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                      </svg>
                      <svg v-else class="w-6 h-6 text-gray-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                    </div>

                    <!-- Card Info -->
                    <div class="flex-1 min-w-0">
                      <h4 class="font-bold text-gray-900 dark:text-[#F8FAFC] text-xs truncate leading-tight group-hover:text-primary transition-colors">
                        {{ card.title }}
                      </h4>
                      <p v-if="card.subtitle" class="text-[11px] text-gray-500 dark:text-slate-400 truncate mt-0.5">
                        {{ card.subtitle }}
                      </p>
                      <div class="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 mt-1 text-[11px] text-gray-600 dark:text-slate-300">
                        <span v-if="card.date" class="text-gray-700 dark:text-slate-300">
                          {{ card.date }}
                        </span>
                        <span v-if="card.fee" class="font-medium text-emerald-700 dark:text-emerald-400">
                          {{ card.fee }}
                        </span>
                        <span v-if="card.yards" class="text-gray-600 dark:text-slate-300">
                          {{ card.yards }}
                        </span>
                      </div>
                    </div>

                    <!-- Action Link -->
                    <router-link
                      :to="card.url"
                      @click="isOpen = false"
                      class="flex-shrink-0 px-2.5 py-1.5 bg-gray-100 dark:bg-[#0F172A] hover:bg-primary text-gray-700 dark:text-slate-300 hover:text-white dark:hover:text-white rounded-lg text-xs font-medium transition-colors flex items-center gap-1 border border-transparent dark:border-[#334155]"
                    >
                      Chi tiết
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                      </svg>
                    </router-link>
                  </div>
                </div>

                <!-- Suggestions Chips -->
                <div v-if="msg.suggestions && msg.suggestions.length > 0 && !msg.isTyping" class="flex flex-wrap gap-1.5 pt-0.5 transition-all duration-300">
                  <button
                    v-for="(sug, sIdx) in msg.suggestions"
                    :key="sIdx"
                    @click="sendQuickPrompt(sug)"
                    class="text-xs px-2.5 py-1 bg-white dark:bg-[#1E293B] hover:bg-primary hover:text-white text-gray-700 dark:text-slate-300 rounded-full border border-gray-200 dark:border-[#334155] transition-colors shadow-2xs"
                  >
                    {{ sug }}
                  </button>
                </div>
              </div>
            </div>
          </template>

          <!-- Loading Indicator -->
          <div v-if="isLoading" class="flex items-start gap-2.5">
            <div class="w-7 h-7 rounded-full bg-primary text-white font-bold flex items-center justify-center flex-shrink-0 text-xs mt-0.5">
              P
            </div>
            <div class="bg-white dark:bg-[#1E293B] border border-gray-200 dark:border-[#233148] rounded-2xl rounded-tl-sm px-3.5 py-2.5 shadow-2xs">
              <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 dark:bg-slate-500 animate-bounce" style="animation-delay: 0ms"></span>
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 dark:bg-slate-500 animate-bounce" style="animation-delay: 150ms"></span>
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 dark:bg-slate-500 animate-bounce" style="animation-delay: 300ms"></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Input Area -->
        <div class="p-3 bg-white dark:bg-[#161F33] border-t border-gray-200 dark:border-[#233148]">
          <form
            @submit.prevent="handleSend"
            class="chatbot-input-container relative flex items-center gap-1.5 bg-gray-50 dark:bg-[#0F172A] border border-gray-300 dark:border-[#2D3C52] rounded-2xl p-1.5 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary/20 transition-all"
          >
            <textarea
              ref="inputArea"
              v-model="inputMessage"
              rows="1"
              placeholder="Nhập nội dung cần hỗ trợ..."
              @keydown.enter.exact.prevent="handleSend"
              @input="autoResize"
              :disabled="isLoading"
              class="chatbot-textarea flex-1 text-xs sm:text-[13px] text-gray-800 dark:text-[#F8FAFC] placeholder-gray-400 dark:placeholder-slate-500 px-2.5 py-1 resize-none max-h-28 leading-relaxed no-scrollbar"
            ></textarea>

            <button
              type="submit"
              :disabled="!canSend"
              class="chatbot-send-btn w-8 h-8 flex-shrink-0 rounded-xl flex items-center justify-center transition-all duration-200"
              :class="canSend ? 'bg-primary text-white shadow-xs hover:opacity-90 active:scale-95' : 'bg-gray-200 dark:bg-[#1E293B] text-gray-400 dark:text-slate-500 cursor-not-allowed'"
              title="Gửi"
            >
              <svg v-if="!isLoading" class="w-4 h-4 transform rotate-90 translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
              </svg>
              <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </button>
          </form>

          <div class="flex items-center justify-between text-[10px] text-gray-400 dark:text-slate-500 mt-1.5 px-1 select-none">
            <span>Nhấn Enter để gửi</span>
            <span>Hỗ trợ trực tuyến</span>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { sendChatbotMessage, getChatbotPrompts } from '@/service/chatbot.js';

const STORAGE_KEY = 'picki_chatbot_history_v1';

const isOpen = ref(false);
const isLoading = ref(false);
const inputMessage = ref('');
const messages = ref([]);
const quickPrompts = ref([]);
const messagesBox = ref(null);
const inputArea = ref(null);

const canSend = computed(() => {
  return inputMessage.value.trim().length > 0 && !isLoading.value;
});

onMounted(async () => {
  loadStoredHistory();
  try {
    const prompts = await getChatbotPrompts();
    if (prompts && prompts.length > 0) {
      quickPrompts.value = prompts;
    } else {
      quickPrompts.value = [
        { id: '1', title: 'Giải đấu sắp tới', prompt: 'Có những giải đấu pickleball nào sắp diễn ra?' },
        { id: '2', title: 'Tìm sân bóng', prompt: 'Tìm các cụm sân pickleball ở Hà Nội hoặc TP.HCM' },
        { id: '3', title: 'Luật vùng bếp', prompt: 'Luật vùng bếp trong pickleball là gì?' },
        { id: '4', title: 'Cách tính điểm', prompt: 'Cách tính điểm trong thi đấu pickleball đánh đôi' }
      ];
    }
  } catch (err) {
    quickPrompts.value = [
      { id: '1', title: 'Giải đấu sắp tới', prompt: 'Có những giải đấu pickleball nào sắp diễn ra?' },
      { id: '2', title: 'Tìm sân bóng', prompt: 'Tìm các cụm sân pickleball trên hệ thống' },
      { id: '3', title: 'Luật vùng bếp', prompt: 'Luật vùng bếp trong pickleball là gì?' }
    ];
  }
});

const toggleChat = () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    scrollToBottom();
    nextTick(() => {
      inputArea.value?.focus();
    });
  }
};

let typingTimer = null;

const resetChat = () => {
  if (typingTimer) {
    clearInterval(typingTimer);
    typingTimer = null;
  }
  messages.value = [];
  try {
    sessionStorage.removeItem(STORAGE_KEY);
  } catch (e) {}
  scrollToBottom();
};

const loadStoredHistory = () => {
  try {
    const stored = sessionStorage.getItem(STORAGE_KEY);
    if (stored) {
      messages.value = JSON.parse(stored);
    }
  } catch (e) {
    messages.value = [];
  }
};

const saveHistory = () => {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages.value));
  } catch (e) {}
};

/**
 * Hiệu ứng hiện chữ tuần tự (Typewriter / Streaming text)
 */
const typeOutMessage = (fullText, cards = [], suggestions = []) => {
  return new Promise((resolve) => {
    if (typingTimer) {
      clearInterval(typingTimer);
      typingTimer = null;
    }

    const newMsg = {
      role: 'model',
      text: '',
      cards: [],
      suggestions: [],
      isTyping: true,
      timestamp: new Date().toISOString()
    };
    messages.value.push(newMsg);

    const targetMsg = messages.value[messages.value.length - 1];
    let currentIndex = 0;
    const totalLength = fullText.length;
    // Tốc độ hiện chữ: 3-5 ký tự mỗi 18ms (~60 FPS mượt mà)
    const chunkSize = totalLength > 600 ? 6 : (totalLength > 250 ? 4 : 2);
    const intervalMs = 18;

    typingTimer = setInterval(() => {
      currentIndex += chunkSize;
      if (currentIndex >= totalLength) {
        targetMsg.text = fullText;
        targetMsg.cards = cards;
        targetMsg.suggestions = suggestions;
        targetMsg.isTyping = false;
        clearInterval(typingTimer);
        typingTimer = null;
        saveHistory();
        scrollToBottom();
        resolve();
      } else {
        targetMsg.text = fullText.slice(0, currentIndex);
        scrollToBottom();
      }
    }, intervalMs);
  });
};

const handleSend = async () => {
  const text = inputMessage.value.trim();
  if (!text || isLoading.value) return;

  // Add user message
  messages.value.push({
    role: 'user',
    text: text,
    timestamp: new Date().toISOString()
  });

  inputMessage.value = '';
  if (inputArea.value) {
    inputArea.value.style.height = 'auto';
  }

  saveHistory();
  scrollToBottom();

  isLoading.value = true;

  try {
    const historyPayload = messages.value.slice(-6).map(m => ({
      role: m.role,
      text: m.text
    }));

    const response = await sendChatbotMessage(text, historyPayload);
    // Tắt loading dots trước khi bắt đầu hiện chữ tuần tự
    isLoading.value = false;

    const replyText = response?.reply || 'Xin lỗi, hiện tại tôi chưa tìm thấy thông tin phù hợp.';
    await typeOutMessage(replyText, response?.cards || [], response?.suggestions || []);
  } catch (error) {
    console.error('Chatbot error:', error);
    isLoading.value = false;
    await typeOutMessage(
      'Đã có sự cố kết nối, bạn vui lòng thử lại sau giây lát nhé.',
      [],
      ['Tìm giải đấu sắp tới', 'Tìm sân pickleball']
    );
  } finally {
    isLoading.value = false;
    scrollToBottom();
  }
};

const sendQuickPrompt = (promptText) => {
  inputMessage.value = promptText;
  handleSend();
};

const autoResize = () => {
  if (inputArea.value) {
    inputArea.value.style.height = 'auto';
    inputArea.value.style.height = Math.min(inputArea.value.scrollHeight, 96) + 'px';
  }
};

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesBox.value) {
      messagesBox.value.scrollTop = messagesBox.value.scrollHeight;
    }
  });
};

const handleImageError = (event) => {
  event.target.style.display = 'none';
};

// Markdown formatting without emojis
const formatMarkdown = (text) => {
  if (!text) return '';
  let html = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/!\[(.*?)\]\((.*?)\)/g, '<img src="$2" alt="$1" class="max-w-full h-auto rounded-lg my-2 max-h-48 object-cover border border-gray-200 dark:border-[#233148]" />')
    .replace(/\*\*(.*?)\*\*/g, '<strong class="font-semibold text-gray-900 dark:text-[#F8FAFC]">$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/^### (.*$)/gim, '<h4 class="font-bold text-gray-900 dark:text-[#F8FAFC] mt-2 mb-1">$1</h4>')
    .replace(/^## (.*$)/gim, '<h3 class="font-bold text-gray-900 dark:text-[#F8FAFC] text-sm mt-2.5 mb-1">$1</h3>')
    .replace(/^\- (.*$)/gim, '<li class="ml-4 list-disc">$1</li>')
    .replace(/\n/g, '<br />');

  return html;
};
</script>

<style scoped>
.pop-fade-enter-active,
.pop-fade-leave-active {
  transition: all 0.2s ease-out;
}
.pop-fade-enter-from,
.pop-fade-leave-to {
  opacity: 0;
  transform: scale(0.9) translateY(8px);
}

.chat-slide-enter-active,
.chat-slide-leave-active {
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.chat-slide-enter-from,
.chat-slide-leave-to {
  opacity: 0;
  transform: translateY(16px) scale(0.98);
}

/* Custom scrollbar only for message box */
.chat-messages-scroll::-webkit-scrollbar {
  width: 4px;
}
.chat-messages-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.chat-messages-scroll::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 9999px;
}
.chat-messages-scroll::-webkit-scrollbar-thumb:hover {
  background: #cbd5e1;
}

:global(html.dark) .chat-messages-scroll::-webkit-scrollbar-thumb {
  background: #334155;
}
:global(html.dark) .chat-messages-scroll::-webkit-scrollbar-thumb:hover {
  background: #475569;
}

/* Triệt tiêu 100% border, outline, background-color do app.css áp lên textarea */
:global(html.dark) #picki-chatbot-window textarea.chatbot-textarea,
:global(html.dark) textarea.chatbot-textarea,
#picki-chatbot-window textarea.chatbot-textarea,
textarea.chatbot-textarea {
  background: transparent !important;
  background-color: transparent !important;
  border: none !important;
  border-width: 0 !important;
  outline: none !important;
  box-shadow: none !important;
  padding: 4px 8px !important;
  line-height: 1.45 !important;
}

:global(html.dark) #picki-chatbot-window textarea.chatbot-textarea:focus,
#picki-chatbot-window textarea.chatbot-textarea:focus {
  outline: none !important;
  box-shadow: none !important;
  border: none !important;
}

/* Khóa chặt kích thước ảnh thumbnail card giải đấu, ngăn không cho ảnh vỡ to */
.card-thumbnail-box {
  width: 56px !important;
  height: 56px !important;
  min-width: 56px !important;
  min-height: 56px !important;
  max-width: 56px !important;
  max-height: 56px !important;
}

.card-thumbnail-box img {
  width: 100% !important;
  height: 100% !important;
  max-width: 100% !important;
  max-height: 100% !important;
  object-fit: cover !important;
  display: block !important;
}

/* Giới hạn kích thước bất kỳ ảnh nào trong nội dung tin nhắn bot */
:deep(.markdown-content img),
.markdown-content img {
  max-width: 100% !important;
  max-height: 180px !important;
  border-radius: 8px !important;
  object-fit: cover !important;
  margin-top: 6px !important;
  margin-bottom: 6px !important;
}

/* Hide scrollbar completely on textarea */
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.no-scrollbar::-webkit-scrollbar {
  display: none !important;
  width: 0 !important;
  height: 0 !important;
}
</style>
