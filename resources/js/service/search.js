import axiosInstance from "@/utils/httpRequest.js";
import { API_ENDPOINT } from "@/constants/index.js";

/**
 * Unified search API v2 service.
 * Single endpoint for all tabs: match, tournament, club, user, court
 *
 * @param {Object} params
 * @param {string} [params.tab='match']           - match | tournament | club | user | court
 * @param {string} [params.time_filter='all']     - all | mine | today | this_week | this_month
 * @param {string} [params.keyword]              - search keyword
 * @param {number} [params.sport_id]              - filter by sport ID
 * @param {number} [params.location_id]           - filter by location ID
 * @param {number} [params.lat]                   - user latitude
 * @param {number} [params.lng]                  - user longitude
 * @param {number} [params.radius]                - search radius in meters
 * @param {number} [params.minLat]                - map bounds min latitude
 * @param {number} [params.maxLat]                - map bounds max latitude
 * @param {number} [params.minLng]                - map bounds min longitude
 * @param {number} [params.maxLng]                - map bounds max longitude
 * @param {number} [params.page]                  - page number (default 1)
 * @param {number} [params.per_page]              - items per page (default 15, max 200)
 * @param {boolean} [params.map_mode]             - true = return all items for map (no pagination)
 * @param {Object} [params.filters]               - filter bundle, e.g. { distance: [0, 50], rating: [3, 5] }
 */
export const search = async (params = {}) => {
    const { data } = await axiosInstance.get(API_ENDPOINT.SEARCH_V2, { params });
    return data;
};

/**
 * Build filter bundle from UI state.
 * Maps UI filter controls to the filters{} object expected by search-v2 API.
 */
export const buildFilters = (tab, uiState) => {
    const filters = {};

    if (tab === 'match' || tab === 'tournament') {
        // Distance range: [min_km, max_km]
        if (uiState.selectedRadiusValue === 'nearby' && uiState.radiusKm) {
            filters.distance = [0, uiState.radiusKm];
        }
        // Rating range: [min, max]
        if (uiState.selectedRating?.length) {
            const min = Math.min(...uiState.selectedRating);
            filters.rating = [min, 5];
        }
        // Time of day: array of 'morning'|'afternoon'|'evening'
        if (uiState.selectedTimePlay?.length) {
            filters.time_of_day = uiState.selectedTimePlay;
        }
        // Slot status: 'con_trong'|'da_day'
        if (uiState.slotStatus?.length) {
            filters.slot_status = uiState.slotStatus;
        }
        // Fee: 'free'|'paid' — multi-select array
        if (uiState.fee?.length) {
            filters.fee = uiState.fee;
        }
        // Type (match only): 'single'|'double'
        if (tab === 'match' && uiState.matchType) {
            filters.type = uiState.matchType;
        }
    }

    if (tab === 'club') {
        // Distance range
        if (uiState.selectedRadiusValue === 'nearby' && uiState.radiusKm) {
            filters.distance = [0, uiState.radiusKm];
        }
        // Joined only toggle
        if (uiState.joinedOnly) {
            filters.joined_only = true;
        }
    }

    if (tab === 'user') {
        // Distance range
        if (uiState.selectedRadiusValue === 'nearby' && uiState.radiusKm) {
            filters.distance = [0, uiState.radiusKm];
        }
        // Rating range
        if (uiState.selectedRating?.length) {
            const min = Math.min(...uiState.selectedRating);
            filters.rating = [min, 5];
        }
        // Gender: 'male'|'female'|'other'
        if (uiState.selectedGender === 'male' || uiState.selectedGender === 'female' || uiState.selectedGender === 'other') {
            filters.gender = uiState.selectedGender;
        }
        // Same club
        if (uiState.sameClubId) {
            filters.same_club_id = uiState.sameClubId;
        }
    }

    return Object.keys(filters).length > 0 ? filters : undefined;
};
