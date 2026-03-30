<?php

namespace App\Services\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubNotificationStatus;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\User;
use App\Jobs\SendPushJob;
use App\Notifications\ClubInvitationDeclinedNotification;
use App\Notifications\ClubJoinRequestApprovedNotification;
use App\Notifications\ClubJoinRequestReceivedNotification;
use App\Notifications\ClubJoinRequestRejectedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClubJoinRequestService
{
    public function __construct(
        private readonly \App\Services\Club\ClubNotificationService $notificationService,
    ) {}

    public function getJoinRequests(Club $club, array $filters): LengthAwarePaginator
    {
        $status = $filters['status'] ?? 'pending';

        $query = ClubMember::where('club_id', $club->id)
            ->whereNull('invited_by')
            ->with(['user' => User::FULL_RELATIONS, 'reviewer', 'inviter']);

        if ($status === 'pending') {
            $query->where('membership_status', ClubMembershipStatus::Pending);
        } elseif ($status === 'approved') {
            $query->where('membership_status', ClubMembershipStatus::Joined)->whereNotNull('reviewed_at');
        } elseif ($status === 'rejected') {
            $query->where('membership_status', ClubMembershipStatus::Rejected)->whereNotNull('rejection_reason');
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function sendJoinRequest(Club $club, int $userId, ?string $message = null): ClubMember
    {
        if (!$club->canSendJoinRequest($userId)) {
            if ($club->hasMember($userId)) {
                throw new \Exception('Bạn đã là thành viên của CLB này');
            }
            if ($club->hasPendingRequest($userId)) {
                throw new \Exception('Bạn đã gửi yêu cầu tham gia. Vui lòng chờ duyệt');
            }
            throw new \Exception('Bạn không thể gửi yêu cầu tham gia');
        }

        $member = DB::transaction(function () use ($club, $userId, $message) {
            $existing = ClubMember::where('club_id', $club->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->membership_status === ClubMembershipStatus::Joined) {
                    throw new \Exception('Bạn đã là thành viên của CLB này');
                }

                if ($existing->membership_status === ClubMembershipStatus::Pending) {
                    throw new \Exception('Bạn đã gửi yêu cầu tham gia. Vui lòng chờ duyệt');
                }

                if (in_array($existing->membership_status, [ClubMembershipStatus::Rejected, ClubMembershipStatus::Left, ClubMembershipStatus::Cancelled], true)) {
                    $existing->update([
                        'role' => ClubMemberRole::Member,
                        'membership_status' => ClubMembershipStatus::Pending,
                        'status' => ClubMemberStatus::Pending,
                        'message' => $message,
                        'invited_by' => null,
                        'left_at' => null,
                        'rejection_reason' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ]);
                    return $existing->fresh(['user' => User::FULL_RELATIONS, 'club']);
                }

                throw new \Exception('Không thể gửi yêu cầu tham gia');
            }

            return ClubMember::create([
                'club_id' => $club->id,
                'user_id' => $userId,
                'role' => ClubMemberRole::Member,
                'membership_status' => ClubMembershipStatus::Pending,
                'status' => ClubMemberStatus::Pending,
                'message' => $message,
            ]);
        });

        $applicant = User::find($userId);
        if ($applicant) {
            $this->notifyAdminsOfNewJoinRequest($club, $applicant);
        }

        return $member;
    }

    private function notifyAdminsOfNewJoinRequest(Club $club, User $applicant): void
    {
        $adminUserIds = ClubMember::where('club_id', $club->id)
            ->where('membership_status', ClubMembershipStatus::Joined)
            ->where('status', ClubMemberStatus::Active)
            ->whereIn('role', [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])
            ->pluck('user_id');

        $applicantName = $applicant->full_name ?: $applicant->email;
        $message = "Có yêu cầu tham gia mới từ {$applicantName} tại CLB {$club->name}";

        $users = User::whereIn('id', $adminUserIds)->get();
        foreach ($users as $user) {
            $user->notify(new ClubJoinRequestReceivedNotification($club, $applicant));
            SendPushJob::dispatch($user->id, 'Yêu cầu tham gia CLB mới', $message, [
                'type' => 'CLUB_JOIN_REQUEST',
                'club_id' => (string) $club->id,
                'applicant_id' => (string) $applicant->id,
            ]);
        }
    }

    public function cancelMyRequest(Club $club, int $userId): void
    {
        $member = ClubMember::where('club_id', $club->id)
            ->where('user_id', $userId)
            ->where('membership_status', ClubMembershipStatus::Pending)
            ->whereNull('invited_by')
            ->first();

        if (!$member) {
            throw new \Exception('Không tìm thấy yêu cầu tham gia nào của bạn');
        }

        $member->update([
            'membership_status' => ClubMembershipStatus::Cancelled,
            'status' => ClubMemberStatus::Inactive,
            'message' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function approveRequest(ClubMember $member, int $reviewerId, ?string $role = null): ClubMember
    {
        if ($member->invited_by !== null) {
            throw new \Exception('Đây là lời mời từ admin, chỉ người được mời mới có thể đồng ý hoặc từ chối qua mục Lời mời của tôi.');
        }

        $member->update([
            'membership_status' => ClubMembershipStatus::Joined,
            'status' => ClubMemberStatus::Active,
            'role' => $role ?? ClubMemberRole::Member,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'joined_at' => now(),
        ]);

        $user = $member->user;
        $club = $member->club;
        if ($user && $club) {
            $message = "Bạn đã được chấp nhận tham gia CLB {$club->name}";
            $user->notify(new ClubJoinRequestApprovedNotification($club));
            SendPushJob::dispatch($user->id, 'Yêu cầu tham gia CLB đã được duyệt', $message, [
                'type' => 'CLUB_JOIN_APPROVED',
                'club_id' => (string) $club->id,
            ]);

            // Backfill thông báo CLB đã gửi trước khi user gia nhập
            $this->notificationService->backfillNotificationsForNewMember($club, $user->id, now());
        }

        return $member;
    }

    public function rejectRequest(ClubMember $member, int $reviewerId, ?string $rejectionReason = null): void
    {
        if ($member->invited_by !== null) {
            throw new \Exception('Đây là lời mời từ admin, chỉ người được mời mới có thể đồng ý hoặc từ chối qua mục Lời mời của tôi.');
        }

        $member->update([
            'membership_status' => ClubMembershipStatus::Rejected,
            'status' => ClubMemberStatus::Inactive,
            'rejection_reason' => $rejectionReason,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        $user = $member->user;
        $club = $member->club;
        if ($user && $club) {
            $message = "Yêu cầu tham gia CLB {$club->name} đã bị từ chối";
            if ($rejectionReason) {
                $message .= ": {$rejectionReason}";
            }
            $user->notify(new ClubJoinRequestRejectedNotification($club, $rejectionReason));
            SendPushJob::dispatch($user->id, 'Yêu cầu tham gia CLB đã bị từ chối', $message, [
                'type' => 'CLUB_JOIN_REJECTED',
                'club_id' => (string) $club->id,
            ]);
        }
    }

    public function getMyInvitations(int $userId): \Illuminate\Support\Collection
    {
        return ClubMember::where('user_id', $userId)
            ->where('membership_status', ClubMembershipStatus::Pending)
            ->whereNotNull('invited_by')
            ->with(['user' => User::FULL_RELATIONS, 'club', 'inviter'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function acceptInvitation(int $clubId, int $userId): ClubMember
    {
        $member = ClubMember::where('club_id', $clubId)
            ->where('user_id', $userId)
            ->where('membership_status', ClubMembershipStatus::Pending)
            ->whereNotNull('invited_by')
            ->first();

        if (!$member) {
            throw new \Exception('Không tìm thấy lời mời tham gia CLB này');
        }

        $member->update([
            'membership_status' => ClubMembershipStatus::Joined,
            'status' => ClubMemberStatus::Active,
            'joined_at' => now(),
            'reviewed_at' => now(),
        ]);

        // Backfill thông báo CLB đã gửi trước khi user accept lời mời
        $club = $member->club;
        if ($club) {
            $this->notificationService->backfillNotificationsForNewMember($club, $userId, now());
        }

        return $member;
    }

    public function rejectInvitation(int $clubId, int $userId): void
    {
        $member = ClubMember::where('club_id', $clubId)
            ->where('user_id', $userId)
            ->where('membership_status', ClubMembershipStatus::Pending)
            ->whereNotNull('invited_by')
            ->with(['user', 'club'])
            ->first();

        if (!$member) {
            throw new \Exception('Không tìm thấy lời mời tham gia CLB này');
        }

        $club = $member->club;
        $declinedUser = $member->user;
        $inviterId = $member->invited_by;

        if ($inviterId && $declinedUser && $club) {
            $inviter = User::find($inviterId);
            $userName = $declinedUser->full_name ?: $declinedUser->email ?: 'Thành viên';
            $message = "{$userName} đã từ chối lời mời tham gia CLB {$club->name}";
            if ($inviter) {
                $inviter->notify(new ClubInvitationDeclinedNotification($club, $declinedUser));
                SendPushJob::dispatch($inviterId, 'Từ chối lời mời tham gia CLB', $message, [
                    'type' => 'CLUB_INVITATION_DECLINED',
                    'club_id' => (string) $club->id,
                ]);
            }
        }

        $member->delete();
    }
}
