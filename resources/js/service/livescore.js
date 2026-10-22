import axiosInstance from "@/utils/httpRequest.js";
import { API_ENDPOINT } from "@/constants/index.js";

const livescoreEndpoint = API_ENDPOINT.LIFESCORE;

/**
 * Danh sách giải đang diễn ra cho màn chọn trận Livescore.
 * Cần đăng nhập — đây là màn dành cho BTC, không phải khán giả.
 * Trả về list giải (mảng), không bọc trong key.
 */
export const getLivescoreTournaments = async () => {
  return axiosInstance.get(`${livescoreEndpoint}/tournaments`)
    .then((response) => response.data.data);
}

/**
 * Toàn bộ trận của 1 giải. Trả về list trận (mảng), không bọc trong key.
 */
export const getLivescoreTournamentMatches = async (tournamentId) => {
  return axiosInstance.get(`${livescoreEndpoint}/tournaments/${tournamentId}/matches`)
    .then((response) => response.data.data);
}
