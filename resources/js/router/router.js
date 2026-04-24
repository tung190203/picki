import LoginPage from '@/components/pages/auth/login/LoginPage.vue'
import LoginSuccessPage from '@/components/pages/auth/login/LoginSuccessPage.vue'
import RegisterPage from '@/components/pages/auth/register/RegisterPage.vue'
import DashboardPage from '@/components/pages/dashboard/DashboardPage.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import VerifyEmailPage from '@/components/pages/auth/verify/VerifyEmailPage.vue'
import VerifyPage from '@/components/pages/auth/verify/VerifyPage.vue'
import ProfilePage from '@/components/pages/profile/ProfilePage.vue'
import ForgotPasswordPage from '@/components/pages/auth/password/ForgotPasswordPage.vue'
import ResetPasswordPage from '@/components/pages/auth/password/ResetPasswordPage.vue'
import Leaderboard from '@/components/pages/leader-board/Leaderboard.vue'
import ClubPage from '@/components/pages/club/ClubPage.vue'
import ClubFundPage from '@/components/pages/club/ClubFundPage.vue'
import CreateClubPage from '@/components/pages/club/CreateClubPage.vue'
import ClubDetailPage from '@/components/pages/club/ClubDetailPage.vue'
import ClubDetailActivityPage from '@/components/pages/club/ClubDetailActivityPage.vue'
import ClubCreateSchedulePage from '@/components/pages/club/ClubCreateSchedulePage.vue'
import ClubEditSchedulePage from '@/components/pages/club/ClubEditSchedulePage.vue'
import TournamentPage from '@/components/pages/tournament/TournamentPage.vue'
import TournamentDetail from '@/components/pages/tournament/TournamentDetail.vue'
import MiniTournamentDetail from '@/components/pages/mini-tournament/detail/MiniTournamentDetail.vue'
import PrivacyPolicyPage from '@/components/pages/legal/PrivacyPolicyPage.vue'
import CreateMiniTournamentPage from '@/components/pages/mini-tournament/create/CreateMiniTournamentPage.vue'
import CreateTournamentPage from '@/components/pages/tournament/CreateTournamentPage.vue'
import ForbiddenPage from '@/components/pages/common/error/ForbiddenPage.vue'
import AdminDashboard from '@/components/pages/admin/AdminDashboard.vue'
import RefereeDashboard from '@/components/pages/referee/RefereeDashboard.vue'
import OnboardingPage from "@/components/pages/onboarding/Onboarding.vue"
import CompleteRegistrationPage from '@/components/pages/auth/register/CompleteRegistrationPage.vue'
import VerifyChangePasswordPage from '@/components/pages/auth/verify/VerifyChangePasswordPage.vue'
import CompleteProfilePage from '@/components/pages/profile/CompleteProfilePage.vue'
import UpdateProfilePage from '@/components/pages/profile/UpdateProfilePage.vue'
import NotFoundPage from '@/components/pages/common/error/NotFoundPage.vue'
import UnderConstructionPage from '@/components/pages/common/error/UnderConstructionPage.vue'
import TournamentBracketPage from '@/components/pages/tournament/TournamentBracketPage.vue'
import GroupSortView from '@/components/pages/tournament/GroupSortView.vue'
import MatchVerifyPage from '@/components/pages/tournament/MatchVerifyPage.vue'
import TournamentLandingPage from '@/components/pages/tournament/TournamentLandingPage.vue'
import MiniMatchVerifyPage from '@/components/pages/mini-tournament/MiniMatchVerifyPage.vue'
import NotificationsPage from '@/components/pages/notifications/NotificationsPage.vue'
import SettingsPage from '@/components/pages/profile/SettingsPage.vue'
import MapPage from '@/components/pages/map/MapPage.vue'
import CheckInSuccessPage from '@/components/pages/checkin/CheckInSuccessPage.vue'
import { ROLE } from '@/constants/index.js'

export const route = [
  // --- AUTH ROUTES ---
  {
    path: '/onboarding',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'onboarding',
        component: OnboardingPage
      }
    ]
  },
  {
    path: '/complete-registration',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'complete-registration',
        component: CompleteRegistrationPage
      }
    ]
  },
  {
    path: '/login',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'login',
        component: LoginPage
      }
    ]
  },
  {
    path: '/register',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'register',
        component: RegisterPage
      }
    ]
  },
  {
    path: '/verify',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'verify',
        component: VerifyPage
      }
    ]
  },
  {
    path: '/forgot-password',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'forgot-password',
        component: ForgotPasswordPage
      }
    ]
  },
  {
    path: '/verify-change-password',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'verify-change-password',
        component: VerifyChangePasswordPage
      }
    ]
  },
  {
    path: '/reset-password',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'reset-password',
        component: ResetPasswordPage
      }
    ]
  },
  {
    path: '/complete-profile',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'complete-profile',
        component: CompleteProfilePage
      }
    ]
  },
  {
    path: '/update-profile',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'update-profile',
        component: UpdateProfilePage
      }
    ]
  },

  // --- APP ROUTES ---
  {
    path: '/',
    component: AppLayout,
    children: [
      {
        path: '',
        name: 'dashboard',
        component: DashboardPage,
        meta: {
          role: [ROLE.PLAYER, ROLE.ADMIN]
        }
      },
      {
        path: '/profile/:id',
        name: 'profile',
        component: ProfilePage,
        meta: {
          role: [ROLE.PLAYER, ROLE.ADMIN, ROLE.REFEREE]
        }
      },
      {
        path: '/friends',
        name: 'friends',
        component: UnderConstructionPage,
        meta: {
          role: [ROLE.PLAYER, ROLE.ADMIN]
        }
      },
      {
        path: '/leaderboard',
        name: 'leaderboard',
        component: Leaderboard,
        meta: {
          role: [ROLE.PLAYER, ROLE.ADMIN]
        }
      },
      {
        path: '/match/:id/verify',
        name: 'match-verify',
        component: MatchVerifyPage,
        meta: {
          role: [ROLE.REFEREE, ROLE.ADMIN, ROLE.PLAYER]
        }
      },
      {
        path: '/mini-match/:id/verify',
        name: 'mini-match-verify',
        component: MiniMatchVerifyPage,
        meta: {
          role: [ROLE.REFEREE, ROLE.ADMIN, ROLE.PLAYER]
        }
      },
      {
        path: '/clubs',
        meta: {
          role: [ROLE.PLAYER, ROLE.ADMIN]
        },
        children: [
          {
            path: '',
            name: 'club',
            component: ClubPage,
          },
          {
            path: 'create',
            name: 'create-club',
            component: CreateClubPage,
          },
          // {
          //   path: ':id/edit',
          //   name: 'edit-club',
          //   component: CreateClubPage,
          // },
          {
            path: ':id',
            name: 'club-detail',
            component: ClubDetailPage,
          },
          {
            path: ':id/fund',
            name: 'club-fund',
            component: ClubFundPage,
          },
          {
            path: ':id/activities/:activityId',
            name: 'club-detail-activity',
            component: ClubDetailActivityPage,
          },
          {
            path: ':id/create-activity',
            name: 'club-create-activity',
            component: ClubCreateSchedulePage,
          },
          {
            path: ':id/edit-activity/:activityId',
            name: 'club-activity-edit',
            component: ClubEditSchedulePage,
          }
        ]
      },
      {
        path: '/notifications',
        name: 'notifications',
        component: NotificationsPage,
        meta: {
          role: [ROLE.REFEREE, ROLE.ADMIN, ROLE.PLAYER]
        }
      },
      {
        path: '/settings',
        name: 'settings',
        component: SettingsPage,
        meta: {
          role: [ROLE.PLAYER, ROLE.ADMIN, ROLE.REFEREE]
        }
      },
      {
        path: '/tournament',
        meta: {
          role: [ROLE.PLAYER, ROLE.ADMIN]
        },
        children: [
          {
            path: 'create',
            name: 'create-tournament',
            component: CreateTournamentPage
          },
          {
            path: ':id/edit',
            name: 'edit-tournament',
            component: CreateTournamentPage
          },
          {
            path: '',
            name: 'tournament',
            component: TournamentPage
          }
        ]
      },
      {
        path: '/tournament-detail',
        meta: {
          role: [ROLE.PLAYER, ROLE.ADMIN]
        },
        children: [
          {
            path: ':id',
            name: 'tournament-detail',
            component: TournamentDetail,
            props: true
          },
          {
            path: ':id/bracket',
            name: 'tournament-bracket',
            component: TournamentBracketPage,
          },
          {
            path: ':id/groups/sort',
            name: 'tournament-groups-sort',
            component: GroupSortView
          }
        ]
      },
      {
        path: '/mini-tournament',
        meta: {
          role: [ROLE.PLAYER, ROLE.ADMIN]
        },
        children: [
          {
            path: 'create',
            name: 'create-mini-tournament',
            component: CreateMiniTournamentPage
          },
          {
            path: ':id/edit',
            name: 'edit-mini-tournament',
            component: CreateMiniTournamentPage
          }
        ]
      },
      {
        path: '/mini-tournament-detail',
        meta: {
          role: [ROLE.PLAYER, ROLE.ADMIN]
        },
        children: [
          {
            path: ':id',
            name: 'mini-tournament-detail',
            component: MiniTournamentDetail,
            props: true
          }
        ]
      },
      {
        path: '/map',
        name: 'map',
        component: MapPage,
        meta: {
          role: [ROLE.PLAYER, ROLE.ADMIN, ROLE.REFEREE]
        }
      },
      {
        path: '/referee/dashboard',
        name: 'referee.dashboard',
        component: RefereeDashboard,
        meta: {
          role: [ROLE.REFEREE, ROLE.ADMIN]
        }
      }
    ]
  },

  {
    path: '/admin/dashboard',
    name: 'admin.dashboard',
    component: AdminDashboard,
    meta: {
      requiresAdmin: true,
    }
  },
  {
    path: '/admin/moderation',
    name: 'admin.moderation',
    component: () => import('@/components/pages/admin/AdminModeration.vue'),
    meta: {
      requiresAdmin: true,
    }
  },
  {
    path: '/admin/configuration',
    name: 'admin.config',
    component: () => import('@/components/pages/admin/AdminConfig.vue'),
    meta: {
      requiresAdmin: true,
    }
  },

  // --- GENERAL & ERROR ROUTES ---
  {
    path: '/login-success',
    name: 'login-success',
    component: LoginSuccessPage
  },
  {
    path: '/verify-email',
    name: 'verify-email',
    component: VerifyEmailPage
  },
  {
    path: '/checkin/success',
    name: 'checkin-success',
    component: CheckInSuccessPage
  },
  {
    path: '/privacy-policy',
    name: 'privacy-policy',
    component: PrivacyPolicyPage
  },
  // --- PUBLIC LANDING PAGES ---
  {
    path: '/tournament-landing/:id',
    name: 'tournament-landing',
    component: TournamentLandingPage
  },
  {
    path: '/under-construction',
    name: 'under-construction',
    component: UnderConstructionPage
  },
  {
    path: '/not-found',
    name: 'not-found',
    component: NotFoundPage
  },
  {
    path: '/forbidden',
    name: 'forbidden',
    component: ForbiddenPage,
  },
  {
    path: '/club',
    redirect: '/clubs'
  },
  {
    path: '/club/create',
    redirect: '/clubs/create'
  },
  {
    path: '/club/:id',
    redirect: to => `/clubs/${to.params.id}`
  },
  {
    path: '/club/:id/fund',
    redirect: to => `/clubs/${to.params.id}/fund`
  },
  {
    path: '/club/:id/activity/:activityId',
    redirect: to => `/clubs/${to.params.id}/activity/${to.params.activityId}`
  },
  {
    path: '/club/:id/create-activity',
    redirect: to => `/clubs/${to.params.id}/create-activity`
  },
  {
    path: '/club/:id/edit-activity/:activityId',
    redirect: to => `/clubs/${to.params.id}/edit-activity/${to.params.activityId}`
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/not-found'
  }
]
