// src/service/mapProvider.js
import axiosInstance from "@/utils/httpRequest.js";
import { API_ENDPOINT } from "@/constants/index.js";

/**
 * Frontend service for map provider abstractions.
 *
 * Frontend NEVER talks to Goong REST directly. All provider calls go through
 * our Laravel backend, which is responsible for swapping providers
 * (Goong, OSM, future others) based on `GEOCODER_DRIVER` config.
 */

/**
 * Get public map config (map tiles key, style URL).
 * Used by useMap.js to initialize Goong JS at runtime.
 */
export const getPublicConfig = async () => {
  const res = await axiosInstance.get('/map/public-config');
  return res.data.data;
};

/**
 * Search places / autocomplete.
 * Returns array of {place_id, description, lat?, lng?}
 */
export const searchPlaces = async (query) => {
  const res = await axiosInstance.get(API_ENDPOINT.SEARCH_LOCATION, {
    params: { query },
  });
  return res.data.data;
};

/**
 * Get place details (lat, lng, address) by place ID.
 */
export const getPlaceDetail = async (placeId) => {
  const res = await axiosInstance.get(API_ENDPOINT.LOCATION_DETAIL, {
    params: { place_id: placeId },
  });
  return res.data.data;
};

// --- ADMIN ---

/**
 * Admin: get map provider settings (keys masked).
 */
export const adminGetMapProvider = async () => {
  const res = await axiosInstance.get('/admin/settings/map-provider');
  return res.data.data;
};

/**
 * Admin: update map provider keys.
 * Either or both keys can be updated.
 */
export const adminUpdateMapProvider = async ({ goong_api_key, goong_map_key }) => {
  const res = await axiosInstance.put('/admin/settings', {
    goong_api_key,
    goong_map_key,
  });
  return res.data;
};
