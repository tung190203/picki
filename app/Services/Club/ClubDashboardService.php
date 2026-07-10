<?php

namespace App\Services\Club;

use App\Enums\ClubActivityStatus;
use App\Enums\ClubFundCollectionStatus;
use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubWalletTransactionStatus;
use App\Models\Club\Club;
use Illuminate\Support\Collection;

class ClubDashboardService
{

    public function getDashboardData(Club $club, ?int $userId = null): array
    {
        $club->loadMissing([
            'members',
            'mainWallet',
            'fundCollections',
            'activities',
            'notifications',
        ]);

        return [
            'statistics' => [
                'members' => $this->getMemberStatistics($club),
                'financial' => $this->getFinancialStatistics($club),
                'activities' => $this->getActivityStatistics($club),
                'notifications' => $this->getNotificationStatistics($club, $userId),
            ],
            'recent_activities' => $this->getRecentActivities($club),
            'recent_notifications' => $this->getRecentNotifications($club),
        ];
    }

    private function getMemberStatistics(Club $club): array
    {
        $allMembers = $club->members;
        $joinedMembers = $allMembers->where('membership_status', ClubMembershipStatus::Joined);
        $activeJoinedMembers = $joinedMembers->where('status', ClubMemberStatus::Active);

        return [
            'total' => $activeJoinedMembers->count(),
            'by_role' => [
                'admin' => $activeJoinedMembers->where('role', ClubMemberRole::Admin)->count(),
                'manager' => $activeJoinedMembers->where('role', ClubMemberRole::Manager)->count(),
                'treasurer' => $activeJoinedMembers->where('role', ClubMemberRole::Treasurer)->count(),
                'secretary' => $activeJoinedMembers->where('role', ClubMemberRole::Secretary)->count(),
                'member' => $activeJoinedMembers->where('role', ClubMemberRole::Member)->count(),
            ],
            'by_status' => [
                'pending' => $allMembers->where('status', ClubMemberStatus::Pending)->count(),
                'active' => $allMembers->where('status', ClubMemberStatus::Active)->count(),
                'inactive' => $allMembers->where('status', ClubMemberStatus::Inactive)->count(),
                'suspended' => $allMembers->where('status', ClubMemberStatus::Suspended)->count(),
            ],
            'by_membership_status' => [
                'pending' => $allMembers->where('membership_status', ClubMembershipStatus::Pending)->count(),
                'joined' => $allMembers->where('membership_status', ClubMembershipStatus::Joined)->count(),
                'rejected' => $allMembers->where('membership_status', ClubMembershipStatus::Rejected)->count(),
                'left' => $allMembers->where('membership_status', ClubMembershipStatus::Left)->count(),
                'cancelled' => $allMembers->where('membership_status', ClubMembershipStatus::Cancelled)->count(),
            ],
        ];
    }

    private function getFinancialStatistics(Club $club): array
    {
        $mainWallet = $club->mainWallet;

        return [
            'total_wallets' => $club->mainWallet ? 1 : 0,
            'main_wallet_balance' => $mainWallet ? $mainWallet->balance : 0,
            'pending_transactions' => $mainWallet
                ? $mainWallet->transactions()->where('status', ClubWalletTransactionStatus::Pending)->count()
                : 0,
            'active_collections' => $club->fundCollections
                ->where('status', ClubFundCollectionStatus::Active)
                ->count(),
        ];
    }

    private function getActivityStatistics(Club $club): array
    {
        $activities = $club->activities;

        return [
            'total' => $activities->count(),
            'scheduled' => $activities->where('status', ClubActivityStatus::Scheduled)->count(),
            'ongoing' => $activities->where('status', ClubActivityStatus::Ongoing)->count(),
            'completed' => $activities->where('status', ClubActivityStatus::Completed)->count(),
        ];
    }

    private function getNotificationStatistics(Club $club, ?int $userId): array
    {
        $notifications = $club->notifications;

        $unreadCount = 0;
        if ($userId) {
            $unreadCount = $club->notifications()
                ->whereHas('recipients', function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                      ->where('is_read', false);
                })
                ->count();
        }

        return [
            'total' => $notifications->count(),
            'pinned' => $notifications->where('is_pinned', true)->count(),
            'unread' => $unreadCount,
        ];
    }

    private function getRecentActivities(Club $club): Collection
    {
        // Use query builder for eager loading to avoid N+1
        return $club->activities()
            ->with(['creator', 'acceptedParticipants.user'])
            ->orderBy('start_time', 'desc')
            ->limit(5)
            ->get();
    }

    private function getRecentNotifications(Club $club): Collection
    {
        return $club->notifications()
            ->with(['type', 'creator', 'recipients'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }
}
