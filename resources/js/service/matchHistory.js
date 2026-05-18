import axiosInstance from "@/utils/httpRequest.js";

export const matchHistoryService = {
  getList: (params) =>
    axiosInstance.get("/user/matches/list", { params }),
};
