// src/composables/useMiniTournamentPermission.js
import { computed } from 'vue'

/**
 * RBAC 3-role permission composable cho MiniTournament (Kèo đấu).
 *
 * Mapping (chuẩn hoá theo backend MiniTournamentResource):
 *   organizer = Admin (role=1)
 *   staff     = BTC (role=2)
 *   referee   = Trọng tài (role=3)
 *
 * Backend API trả 3 list riêng biệt; composable này dùng trực tiếp 3 list
 * đó để check role — KHÔNG đoán từ numeric role để tránh bug.
 *
 * @param {Ref|Object} miniTournament - ref/object chứa MiniTournament detail (có .staff.organizer/staff/referee)
 * @param {Number} currentUserId - ID user hiện tại (auth)
 * @returns {Object} các computed: isAdmin, isBTC, isReferee, canXxx
 */
export function useMiniTournamentPermission(miniTournament, currentUserId) {
  const organizers = computed(
    () => miniTournament.value?.staff?.organizer ?? []
  )
  const staffs = computed(
    () => miniTournament.value?.staff?.staff ?? []
  )
  const referees = computed(
    () => miniTournament.value?.staff?.referee ?? []
  )

  const userId = computed(() => Number(currentUserId))

  const isAdmin = computed(() =>
    organizers.value.some(
      (s) => Number(s.user?.id ?? s.user_id) === userId.value
    )
  )

  const isBTC = computed(() =>
    staffs.value.some(
      (s) => Number(s.user?.id ?? s.user_id) === userId.value
    )
  )

  const isReferee = computed(() =>
    referees.value.some(
      (s) => Number(s.user?.id ?? s.user_id) === userId.value
    )
  )

  // Permission matrix — khớp với MiniTournamentPermission service
  return {
    // === Vai trò ===
    isAdmin,
    isBTC,
    isReferee,

    // === Vòng đời kèo ===
    canEditRules: computed(() => isAdmin.value || isBTC.value),
    canDelete: computed(() => isAdmin.value),

    // === Người tham gia ===
    canManageParticipants: computed(() => isAdmin.value || isBTC.value),
    canCheckIn: computed(() => isAdmin.value || isBTC.value),

    // === Vận hành trận đấu ===
    canOperateMatches: computed(() => isAdmin.value || isBTC.value),
    canScore: computed(
      () => isAdmin.value || isBTC.value || isReferee.value
    ),

    // === Tài chính ===
    canManageFinance: computed(() => isAdmin.value || isBTC.value),

    // === Vai trò ===
    canAssignRoles: computed(() => isAdmin.value),
    canAssignReferee: computed(
      () => isAdmin.value || isBTC.value
    ),
  }
}