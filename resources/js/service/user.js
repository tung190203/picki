import axiosInstance from "@/utils/httpRequest.js";

export const userService = {
  getMiniTournaments: (params) =>
    axiosInstance.get("/user/mini-tournaments/list", { params }),

  getTournaments: (params) =>
    axiosInstance.get("/user/tournaments/list", { params }),

  getMatches: (params) =>
    axiosInstance.get("/user/matches/list", { params }),
};
