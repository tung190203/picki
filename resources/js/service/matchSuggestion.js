import axiosInstance from "@/utils/httpRequest.js";

/**
 * Get match suggestions for mini tournament
 */
export const getSuggestions = async (miniTournamentId, options = {}) => {
    const response = await axiosInstance.post(
        `/match-suggestions/mini-tournaments/${miniTournamentId}/generate`,
        {
            participants: options.participants || [],
            settings: options.settings || {
                fair_play: true,
                balance_team: true,
                prefer_high_tier_match: true,
                prevent_three_consecutive: true,
                organizer_as_backup: false,
            },
        }
    );
    // Return the full response data
    return response.data;
};

/**
 * Generate match suggestion for mini tournament
 */
export const generateMatchSuggestion = async (miniTournamentId, payload) => {
    const response = await axiosInstance.post(
        `/match-suggestions/mini-tournaments/${miniTournamentId}/generate`,
        payload
    );
    return response.data;
};

/**
 * Regenerate match suggestion
 */
export const regenerateMatchSuggestion = async (miniTournamentId, payload) => {
    const response = await axiosInstance.post(
        `/match-suggestions/mini-tournaments/${miniTournamentId}/regenerate`,
        payload
    );
    return response.data;
};
