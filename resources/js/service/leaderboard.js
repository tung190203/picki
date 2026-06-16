import axiosInstance from "@/utils/httpRequest.js";

export const getLeaderboard = async (params = {}) => {
  const response = await axiosInstance.get('/leaderboard', { params });
  return response.data.data;
};

export const getMyJoinedClubs = async () => {
  const response = await axiosInstance.get('/club/my-joined-clubs');
  return response.data.data;
};
