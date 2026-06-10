import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT} from "@/constants/index.js";

const authEndpoint = API_ENDPOINT.AUTH;
const userEndpoint = API_ENDPOINT.USER;

export const login = async (data) => {
  return axiosInstance.post(`${authEndpoint}/login`, data).then((response) => response.data.data);
};

export const register = async (data) => {
  return axiosInstance.post(`${authEndpoint}/register`, data).then((response) => response.data.data);
};

export const verifyOtp = async (data) => {
  return axiosInstance.post(`${authEndpoint}/verify-otp`, data).then((response) => response.data.data);
}

export const resendOtp = async (data) => {
  return axiosInstance.post(`${authEndpoint}/resend-otp`, data).then((response) => response.data.data);
}

export const fillPassword = async (data) => {
  return axiosInstance.post(`${authEndpoint}/fill-password`, data).then((response) => response.data.data);
}

export const forgotPassword = async (data) => {
  return axiosInstance.post(`${authEndpoint}/forgot-password`, data).then((response) => response.data.data);
}

export const verifyOtpPassword = async (data) => {
  return axiosInstance.post(`${authEndpoint}/verify-otp-password`, data).then((response) => response.data.data);
}

export const resendOtpPassword = async (data) => {
  return axiosInstance.post(`${authEndpoint}/resend-otp-password`, data).then((response) => response.data.data);
}

export const resetPassword = async (data) => {
  return axiosInstance.post(`${authEndpoint}/reset-password`, data).then((response) => response.data.data);
};

export const updateUser = async (data) => {
  return axiosInstance.post(`${userEndpoint}/update`, data).then((response) => response.data.data);
}

export const detailUser = async (id) => {
  return axiosInstance.get(`${userEndpoint}/${id}`).then((response) => response.data.data);
}

export const getUserData = async (params = {}) => {
  return axiosInstance.get(`${userEndpoint}/index`, { params }).then((response) => response.data);
}

export const getMe = async () => {
  return axiosInstance.get('/me').then((response) => response.data.data);
}