import axios from 'axios';
import { API_ENDPOINT } from '@/constants/index.js';

const publicClient = axios.create({
  baseURL: import.meta.env.VITE_BASE_URL,
  timeout: 10000,
});

export const getPublicLiveScore = async (type, matchId) => {
  return publicClient
    .get(`${API_ENDPOINT.LIVE_SCORE}/${type}/${matchId}`)
    .then((res) => res.data);
};
