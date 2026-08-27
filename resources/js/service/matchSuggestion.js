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
            // Include fixed pairs if provided
            fixed_pairs: options.fixed_pairs || [],
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

// ============================================
// Player Pairs API (Fixed Pairing)
// ============================================

/**
 * Get all player pairs for a mini tournament
 */
export const getPlayerPairs = async (miniTournamentId) => {
    const response = await axiosInstance.get(`/mini-tournaments/${miniTournamentId}/player-pairs`);
    return response.data;
};

/**
 * Create a new player pair (auto-removes old pairs for both players)
 * Always uses user_id - backend resolves guest flag via mini_participants when needed
 */
export const createPlayerPair = async (miniTournamentId, player1Id, player2Id) => {
    const response = await axiosInstance.post(
        `/mini-tournaments/${miniTournamentId}/player-pairs`,
        {
            player1_id: player1Id,
            player2_id: player2Id,
        }
    );
    return response.data;
};

/**
 * Delete a player pair
 */
export const deletePlayerPair = async (miniTournamentId, pairId) => {
    const response = await axiosInstance.delete(`/mini-tournaments/${miniTournamentId}/player-pairs/${pairId}`);
    return response.data;
};
