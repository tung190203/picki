import axiosInstance from "@/utils/httpRequest.js";
import { API_ENDPOINT } from "@/constants/index.js";

const notificationTemplateEndpoint = API_ENDPOINT.NOTIFICATION_TEMPLATE || '/notification-templates';

export const getNotificationTemplates = async () => {
  return axiosInstance.get(notificationTemplateEndpoint)
    .then((response) => response.data);
}

export const getNotificationTemplateById = async (id) => {
  return axiosInstance.get(`${notificationTemplateEndpoint}/${id}`)
    .then((response) => response.data);
}

export const createNotificationTemplate = async (payload) => {
  return axiosInstance.post(notificationTemplateEndpoint, payload)
    .then((response) => response.data);
}

export const updateNotificationTemplate = async (id, payload) => {
  return axiosInstance.post(`${notificationTemplateEndpoint}/${id}`, payload)
    .then((response) => response.data);
}

export const deleteNotificationTemplate = async (id) => {
  return axiosInstance.delete(`${notificationTemplateEndpoint}/${id}`)
    .then((response) => response.data);
}
