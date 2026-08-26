import axiosInstance from "@/utils/httpRequest.js";

/**
 * Generate match suggestion for mini tournament
 * @param {number} miniTournamentId - ID of mini tournament
 * @param {object} payload - Request payload containing participants and settings
 * @returns {Promise} API response with suggestion data
 */
export const generateMatchSuggestion = async (miniTournamentId, payload) => {
    try {
        const response = await axiosInstance.post(
            `/match-suggestions/mini-tournaments/${miniTournamentId}/generate`,
            payload
        );
        return response.data;
    } catch (error) {
        console.error('Generate match suggestion error:', error);
        console.error('Response data:', error.response?.data);
        console.error('Response status:', error.response?.status);
        throw error;
    }
};

/**
 * Regenerate match suggestion (excludes players from previous suggestion)
 * @param {number} miniTournamentId - ID of mini tournament
 * @param {object} payload - Request payload with participants, settings, and previous_suggestion
 * @returns {Promise} API response with new suggestion data
 */
export const regenerateMatchSuggestion = async (miniTournamentId, payload) => {
    try {
        const response = await axiosInstance.post(
            `/match-suggestions/mini-tournaments/${miniTournamentId}/regenerate`,
            payload
        );
        return response.data;
    } catch (error) {
        console.error('Regenerate match suggestion error:', error);
        console.error('Response data:', error.response?.data);
        console.error('Response status:', error.response?.status);
        throw error;
    }
};
