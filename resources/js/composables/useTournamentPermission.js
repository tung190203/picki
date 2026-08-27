// src/composables/useTournamentPermission.js
import { computed } from 'vue'

/**
 * RBAC 3-role permission composable cho Tournament (Giải đấu).
 *
 * Mapping (theo backend TournamentStaffResource):
 *   tournament_staff[] → role: 1=Admin (organizer), 2=BTC (staff), 3=Trọng tài (referee)
 *   referee có thể có court_id để giới hạn scope theo sân.
 *
 * @param {Ref|Object} tournament - ref/object Tournament detail (có .tournament_staff[])
 * @param {Number} currentUserId - ID user hiện tại (auth)
 * @returns {Object} các computed: isAdmin, isBTC, isReferee, canXxx
 */
export function useTournamentPermission(tournament, currentUserId) {
  const staff = computed(
    () => tournament.value?.tournament_staff ?? []
  )

  const userId = computed(() => Number(currentUserId))

  const myStaff = computed(() =>
    staff.value.find(
      (s) => Number(s.user_id ?? s.user?.id) === userId.value
    )
  )

  const isAdmin = computed(() => Number(myStaff.value?.role) === 1)
  const isBTC = computed(() => Number(myStaff.value?.role) === 2)
  const isReferee = computed(() => Number(myStaff.value?.role) === 3)

  const myCourtId = computed(() => {
    if (!isReferee.value) return null
    return myStaff.value?.court_id ?? null
  })

  return {
    // === Vai trò ===
    isAdmin,
    isBTC,
    isReferee,
    myStaff,

    // === Vòng đời giải ===
    canEditRules: computed(() => isAdmin.value || isBTC.value),
    canPublish: computed(() => isAdmin.value || isBTC.value),
    canCloseRegistration: computed(() => isAdmin.value || isBTC.value),
    canStart: computed(() => isAdmin.value || isBTC.value),
    canFinish: computed(() => isAdmin.value || isBTC.value),
    canDelete: computed(() => isAdmin.value),

    // === VĐV ===
    canManageAthletes: computed(() => isAdmin.value || isBTC.value),

    // === Thi đấu ===
    canOperateBracket: computed(() => isAdmin.value || isBTC.value),
    canManageDisputes: computed(() => isAdmin.value || isBTC.value),

    /**
     * Quyền nhập điểm. Trọng tài chỉ được score trận ở court được gán.
     * @param {Number|null} courtId - ID sân của trận (nếu có)
     */
    canScore: computed(() => {
      return (courtId) => {
        if (isAdmin.value || isBTC.value) return true
        if (!isReferee.value) return false
        // Trọng tài không giới hạn court (court_id null) → được mọi sân
        if (myCourtId.value === null) return true
        // Trọng tài có court_id → chỉ được đúng sân đó
        if (courtId !== null && Number(courtId) === Number(myCourtId.value)) {
          return true
        }
        return false
      }
    }),

    // === Tài chính ===
    canManageFinance: computed(() => isAdmin.value || isBTC.value),
    canManageSponsorship: computed(() => isAdmin.value || isBTC.value),

    // === Vai trò ===
    canAssignRoles: computed(() => isAdmin.value),
  }
}