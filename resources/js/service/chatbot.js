import axiosInstance from "@/utils/httpRequest.js";

/**
 * Gửi tin nhắn tới chatbot AI
 * @param {string} message 
 * @param {Array} history 
 * @returns {Promise<Object>}
 */
export const sendChatbotMessage = async (message, history = []) => {
  const response = await axiosInstance.post('/chatbot/message', {
    message,
    history
  });
  return response.data?.data;
};

/**
 * Lấy danh sách câu hỏi gợi ý nhanh ban đầu
 * @returns {Promise<Array>}
 */
export const getChatbotPrompts = async () => {
  const response = await axiosInstance.get('/chatbot/prompts');
  return response.data?.data || [];
};
