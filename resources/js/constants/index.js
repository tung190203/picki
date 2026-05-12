export const LOCAL_STORAGE_KEY = {
    LOGIN_TOKEN: "access_token",
    REFRESH_TOKEN: 'refresh_token',
    ONBOARDING: "hasSeenOnboarding",
};

export const LOCAL_STORAGE_USER = {
  USER: "user",
};

export const ROLE = {
  PLAYER: 'player',
  REFEREE: 'referee',
  ADMIN: 'admin',
};

export const API_ENDPOINT = {
  AUTH: '/auth',
  USER: '/user',
  TOURNAMENT: '/tournaments',
  LOCATION: '/locations',
  SPORT: '/sports',
  CLUB: '/clubs',
  FOLLOW: '/follows',
  MINI_TOURNAMENT: '/mini-tournaments',
  COMPETITION_LOCATION: '/competition-locations',
  TOURNAMENT_TYPE: '/tournament-types',
  TEAMS: '/teams',
  PARTICIPANT: '/participants',
  TOURNAMENT_STAFF: '/tournament-staff',
  MINI_PARTICIPANT: '/mini-participants',
  MINI_TOURNAMENT_STAFF  : '/mini-tournament-staff',
  MINI_MATCHES: '/mini-matches',
  MESSAGE: {
    BASE: '/send-message',
    MINITOURNAMENT: () => `${API_ENDPOINT.MESSAGE.BASE}/mini-tournament`,
    TOURNAMENT: () => `${API_ENDPOINT.MESSAGE.BASE}/tournament`,
  },
  MATCHES: '/matches',
  // user_notification: thông báo riêng từng thành viên (không phải thông báo CLB)
  NOTIFICATION: '/user-notifications',
  MAP: '/map',
  PROMOTION: '/promotion',
  SEARCH_V2: '/search',
};

export const TOURNAMENT_STATUS = {
  UPCOMING: 'upcoming',
  ONGOING: 'ongoing',
  FINISHED: 'finished',
};

export const TOURNAMENT_STATUS_LABEL = {
  UPCOMING: 'Sắp diễn ra',
  ONGOING: 'Đang diễn ra',
  FINISHED: 'Đã kết thúc',
};

export const MATCH_STATUS = {
  PENDING: 'pending',
  COMPLETED: 'completed',
  DISPUTED: 'disputed',
};

export const MATCH_STATUS_LABEL = {
  PENDING: 'Chờ đấu',
  COMPLETED: 'Đã hoàn thành',
  DISPUTED: 'Tranh chấp',
};

export const TYPE_OF_TOURNAMENT = {
  SINGLE: 'single',
  DOUBLE: 'double',
  MIXED: 'mixed',
};

export const TYPE_OF_TOURNAMENT_LABEL = {
  SINGLE: 'Đơn',
  DOUBLE: 'Đôi',
  MIXED: 'Hỗn hợp',
};
