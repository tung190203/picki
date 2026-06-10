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

export const MATCH_FORMAT = {
  STANDARD: 'standard',
  PARTNER_ROTATION: 'partner_rotation',
  MIXED_GENDER: 'mixed_gender',
  RANK_PAIRING: 'rank_pairing',
};

export const MATCH_FORMAT_LABEL = {
  standard: 'Tiêu chuẩn',
  partner_rotation: 'Xoay vòng partner',
  mixed_gender: 'Mix nam nữ',
  rank_pairing: 'Ghép hạng A/B',
};

export const MATCH_FORMAT_DESC = {
  standard: '1 trận duy nhất',
  partner_rotation: 'Mỗi người ghép cặp với tất cả người khác đúng 1 lần',
  mixed_gender: 'Mỗi nam ghép cặp với mỗi nữ. BXH cá nhân.',
  rank_pairing: 'Mỗi A ghép cặp với mỗi B. BXH riêng theo hạng.',
};

export const SESSION_STATUS = {
  PENDING_GROUP: 'pending_group',
  READY: 'ready',
  ONGOING: 'ongoing',
  FINISHED: 'finished',
};

export const SESSION_STATUS_LABEL = {
  pending_group: 'Chờ phân nhóm',
  ready: 'Sẵn sàng',
  ongoing: 'Đang đấu',
  finished: 'Đã kết thúc',
};
