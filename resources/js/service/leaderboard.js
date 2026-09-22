import axiosInstance from "@/utils/httpRequest.js";

export const getLeaderboard = async (params = {}) => {
  // ponytail: guard so BE 422 never trips from missing scope
  if (!params.scope) params.scope = 'top50';
  const response = await axiosInstance.get('/leaderboard', { params });
  return response.data.data;
};

export const getMyJoinedClubs = async () => {
  const response = await axiosInstance.get('/club/my-joined-clubs');
  return response.data.data;
};
