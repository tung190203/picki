import axiosInstance from "@/utils/httpRequest.js";
import {API_ENDPOINT, LOCAL_STORAGE_KEY} from "@/constants/index.js";
import axios from "axios";

const tournamentTypeEndpoint = API_ENDPOINT.TOURNAMENT_TYPE;

export const createTournamentType = async (tournamentData) => {
  return axiosInstance.post(`${tournamentTypeEndpoint}/store`, tournamentData)
    .then((response) => response.data.data);
}

export const updateTournamentType = async (tournamentTypeId, data) => {
  // Gửi PUT request - sử dụng axios.put (Laravel tự nhận PUT qua route match)
  return axiosInstance.put(`${tournamentTypeEndpoint}/${tournamentTypeId}`, data)
    .then((response) => response.data.data);
}

export const deleteTournamentType = async (tournamentTypeId) => {
  return axiosInstance.delete(`${tournamentTypeEndpoint}/${tournamentTypeId}`)
    .then((response) => response.data.data)
}

export const getBracketByTournamentTypeId = async (tournamentTypeId) => {
  return axiosInstance.get(`${tournamentTypeEndpoint}/${tournamentTypeId}/bracket`)
    .then((response) => response.data.data);
}


export const getRanks = async (tournamentId) => {
  return axiosInstance.get(`${tournamentTypeEndpoint}/${tournamentId}/rank`)
    .then((response) => response.data.data);
}

export const reGenerateMatches = async (tournamentTypeId) => {
  return axiosInstance.post(`${tournamentTypeEndpoint}/${tournamentTypeId}/regenerate-matches`)
    .then((response) => response.data.data);
}

export const groupWithTeamsForSort = async(tournamentId) => {
  return axiosInstance.get(`${tournamentTypeEndpoint}/${tournamentId}/groups-with-teams`).then((response) => response.data.data);
}

export const assignTeamsAndGenerate = async(tournamentTypeId, data) => {
  return axiosInstance.post(`${tournamentTypeEndpoint}/${tournamentTypeId}/assign-teams-and-generate`, data).then((response) => response.data.data);
}

export const autoGenerateTeamAndMatches = async(tournamentTypeId) => {
  return axiosInstance.post(`${tournamentTypeEndpoint}/${tournamentTypeId}/auto-generate-matches`).then((response) => response.data.data);
}

// ✅ Cross-group comparison ranking (xét đội Nhì/Ba khi bảng không đều)
export const getCrossGroupComparison = async (tournamentTypeId) => {
  return axiosInstance.get(`${tournamentTypeEndpoint}/${tournamentTypeId}/cross-group-comparison`).then((response) => response.data.data);
}

// API 2 dùng raw axios thay vì axiosInstance để tránh response interceptor (404 → not-found page)
// chỉ redirect khi GET dùng để load page; request từ modal candidate nên trả lỗi về component.
// Vẫn lấy token qua LOCAL_STORAGE_KEY để đảm bảy Bearer header luôn có khi user đã đăng nhập.
export const getCrossGroupComparisonTeamMatches = async (tournamentTypeId, teamId) => {
  const token = localStorage.getItem(LOCAL_STORAGE_KEY.LOGIN_TOKEN) || '';
  try {
    const response = await axios.get(
      `${import.meta.env.VITE_BASE_URL}${tournamentTypeEndpoint}/${tournamentTypeId}/cross-group-comparison/${teamId}/matches`,
      {
        headers: token ? { Authorization: `Bearer ${token}` } : {},
      }
    );
    return response.data.data;
  } catch (error) {
    // Trả error có cấu trúc giống axiosInstance để FE xử lý đồng nhất
    if (error.response) {
      const e = new Error(error.response.data?.message || 'Team không phải candidate');
      e.response = error.response;
      throw e;
    }
    throw error;
  }
}

// ✅ Knockout rebuild flow (NEW) — tách riêng khỏi luồng pairing_mode hiện tại
// Lấy danh sách team ứng viên vào vòng sau (kèm team_label), sau khi vòng bảng đã hoàn thành.
// Nếu vòng bảng chưa xong → trả về {pool_completed: false, candidates: []} (không error).
export const getKnockoutCandidates = async (tournamentTypeId) => {
  return axiosInstance.get(`${tournamentTypeEndpoint}/${tournamentTypeId}/knockout-candidates`)
    .then((response) => response.data.data);
}

// Rebuild pairing round=2 main bracket dựa trên manual_pairings (sau khi pool stage xong).
// CHỈ reassign home/away_team_id cho round=2 main, KHÔNG động vào round≥3 và resurrection bracket.
export const rebuildKnockoutPairing = async (tournamentTypeId, data) => {
  return axiosInstance.post(`${tournamentTypeEndpoint}/${tournamentTypeId}/knockout-rebuild-pairing`, data)
    .then((response) => response.data.data);
}

// ✅ Manual Tiebreaker (Bốc thăm / kéo-thả thủ công khi đội đồng hạng)
// Lấy các cụm team đồng hạng cần bốc thăm
export const getPendingTies = async (tournamentTypeId, groupId) => {
  return axiosInstance.get(`${tournamentTypeEndpoint}/${tournamentTypeId}/groups/${groupId}/pending-ties`)
    .then((response) => response.data.data);
}

// Lưu manual ranks (intra-group, sau khi BTC kéo-thả)
export const storeManualTiebreaker = async (tournamentTypeId, groupId, rankings) => {
  return axiosInstance.post(`${tournamentTypeEndpoint}/${tournamentTypeId}/groups/${groupId}/manual-tiebreaker`, { rankings })
    .then((response) => response.data);
}

// Reset manual ranks
export const resetManualTiebreaker = async (tournamentTypeId, groupId) => {
  return axiosInstance.delete(`${tournamentTypeEndpoint}/${tournamentTypeId}/groups/${groupId}/manual-tiebreaker`)
    .then((response) => response.data);
}
