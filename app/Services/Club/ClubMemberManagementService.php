<?php

namespace App\Services\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Exceptions\BusinessException;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\SuperAdminDraft;
use App\Models\User;
use App\Jobs\SendPushJob;
use App\Notifications\ClubInvitationCancelledNotification;
use App\Notifications\ClubInvitationNotification;
use App\Notifications\ClubMemberKickedNotification;
use App\Notifications\ClubRoleChangeNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClubMemberManagementService
{
    public function __construct(
        protected ClubNotificationService $notificationService
    ) {}

    /**
     * Get paginated members with inline projection (no FULL_RELATIONS constant).
     * Supports cursor pagination via `cursor` parameter.
     *
     * @param  Club  $club
     * @param  array  $filters  Supports: search, role, status, per_page, cursor
     * @param  bool  $useCursor  If true, uses cursorPaginate instead of paginate
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Pagination\CursorPaginator
     */
    public function getMembers(Club $club, array $filters, bool $useCursor = false): LengthAwarePaginator|\Illuminate\Pagination\CursorPaginator
    {
        $query = $club->members()
            ->select(['club_members.id', 'club_members.user_id', 'club_members.club_id',
                'club_members.role', 'club_members.position', 'club_members.membership_status',
                'club_members.status', 'club_members.message', 'club_members.joined_at',
                'club_members.invited_by', 'club_members.reviewed_by', 'club_members.created_at'])
            ->with([
                'user' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender'])
                        ->with([
                            'vnduprScores' => function ($q) {
                                $q->select(['user_sport_scores.id', 'user_sport_scores.user_sport_id', 'user_sport_scores.score_type', 'user_sport_scores.score_value', 'user_sport_scores.created_at'])
                                    ->where('user_sport_scores.score_type', 'vndupr_score')
                                    ->latest('user_sport_scores.score_value')
                                    ->limit(1);
                            },
                            'sports' => function ($q) {
                                $q->select(['user_sport.id', 'user_sport.user_id', 'user_sport.sport_id'])
                                    ->with([
                                        'sport' => fn ($q) => $q->select(['id', 'name', 'icon']),
                                        'scores' => function ($q) {
                                            $q->select(['id', 'user_sport_id', 'score_type', 'score_value', 'created_at'])
                                                ->where('score_type', 'vndupr_score')
                                                ->latest('created_at')
                                                ->limit(10);
                                        },
                                    ]);
                            },
                        ]);
                },
                'reviewer' => fn ($q) => $q->select(['id', 'full_name', 'avatar_url']),
            ]);

        if (!empty($filters['search'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('full_name', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($useCursor && empty($filters['cursor'])) {
            return $query->cursorPaginate($filters['per_page'] ?? 20);
        }

        if ($useCursor && !empty($filters['cursor'])) {
            return $query->cursorPaginate($filters['per_page'] ?? 20);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getMemberStatistics(Club $club): array
    {
        $row = ClubMember::where('club_id', $club->id)
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN membership_status = 'joined' AND status = 'active' THEN 1 ELSE 0 END) AS active_joined,
                SUM(CASE WHEN membership_status = 'joined' AND status = 'active' AND role = 'admin' THEN 1 ELSE 0 END) AS admin_count,
                SUM(CASE WHEN membership_status = 'joined' AND status = 'active' AND role = 'manager' THEN 1 ELSE 0 END) AS manager_count,
                SUM(CASE WHEN membership_status = 'joined' AND status = 'active' AND role = 'secretary' THEN 1 ELSE 0 END) AS secretary_count,
                SUM(CASE WHEN membership_status = 'joined' AND status = 'active' AND role = 'treasurer' THEN 1 ELSE 0 END) AS treasurer_count,
                SUM(CASE WHEN membership_status = 'joined' AND status = 'active' AND role = 'member' THEN 1 ELSE 0 END) AS member_count,
                SUM(CASE WHEN membership_status = 'pending' THEN 1 ELSE 0 END) AS pending_request,
                SUM(CASE WHEN membership_status = 'joined' AND status = 'pending' THEN 1 ELSE 0 END) AS pending_join,
                SUM(CASE WHEN membership_status = 'joined' AND status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
                SUM(CASE WHEN membership_status = 'joined' AND status = 'suspended' THEN 1 ELSE 0 END) AS suspended,
                SUM(CASE WHEN membership_status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN membership_status = 'left' THEN 1 ELSE 0 END) AS left_club,
                SUM(CASE WHEN membership_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
            ")
            ->first();

        return [
            'total' => (int) $row->total,
            'by_role' => [
                'admin' => (int) $row->admin_count,
                'manager' => (int) $row->manager_count,
                'secretary' => (int) $row->secretary_count,
                'treasurer' => (int) $row->treasurer_count,
                'member' => (int) $row->member_count,
            ],
            'by_status' => [
                'pending' => (int) $row->pending_request + (int) $row->pending_join,
                'active' => (int) $row->active_joined,
                'inactive' => (int) $row->inactive,
                'suspended' => (int) $row->suspended,
            ],
            'by_membership_status' => [
                'pending' => (int) $row->pending_request + (int) $row->pending_join,
                'joined' => (int) $row->active_joined + (int) $row->inactive + (int) $row->suspended,
                'rejected' => (int) $row->rejected,
                'left' => (int) $row->left_club,
                'cancelled' => (int) $row->cancelled,
            ],
        ];
    }

    public function inviteMember(Club $club, array $data, int $inviterId): ClubMember
    {
        if ($club->hasMember($data['user_id'])) {
            throw new BusinessException('Người dùng đã là thành viên của CLB này');
        }

        $isSuperAdminDraft = SuperAdminDraft::where('user_id', $inviterId)->exists();

        $attributes = [
            'invited_by' => $inviterId,
            'role' => $data['role'] ?? ClubMemberRole::Member,
            'position' => $data['position'] ?? null,
            'membership_status' => $isSuperAdminDraft ? ClubMembershipStatus::Joined : ClubMembershipStatus::Pending,
            'status' => $isSuperAdminDraft ? ClubMemberStatus::Active : ClubMemberStatus::Pending,
            'message' => $data['message'] ?? null,
            'joined_at' => $isSuperAdminDraft ? now() : null,
            'left_at' => null,
            'reviewed_by' => $isSuperAdminDraft ? $inviterId : null,
            'reviewed_at' => $isSuperAdminDraft ? now() : null,
            'rejection_reason' => null,
        ];

        // updateOrCreate: invite người mới (tạo mới) hoặc re-invite người đã kick/left (update record cũ)
        $member = ClubMember::withTrashed()->updateOrCreate(
            [
                'club_id' => $club->id,
                'user_id' => $data['user_id'],
            ],
            array_merge($attributes, ['club_id' => $club->id, 'user_id' => $data['user_id']])
        );

        if ($member->trashed()) {
            $member->restore();
        }

        $invitedUser = User::find($data['user_id']);
        $inviter = User::find($inviterId);
        if ($invitedUser && $inviter) {
            $inviterName = $inviter->full_name ?: $inviter->email;
            if ($isSuperAdminDraft) {
                $message = "Bạn đã được thêm vào CLB {$club->name} bởi {$inviterName}";
                $invitedUser->notify(new \App\Notifications\ClubJoinRequestApprovedNotification($club));
            } else {
                $message = "Bạn được mời tham gia CLB {$club->name} bởi {$inviterName}";
                $invitedUser->notify(new ClubInvitationNotification($club, $member, $inviterName));
            }

            SendPushJob::dispatch($invitedUser->id, $isSuperAdminDraft ? 'Đã vào CLB' : 'Lời mời tham gia CLB', $message, [
                'type' => $isSuperAdminDraft ? 'CLUB_JOINED' : 'CLUB_INVITATION',
                'club_id' => (string) $club->id,
                'club_member_id' => (string) $member->id,
            ]);
        }

        return $member;
    }

    public function updateMember(ClubMember $member, array $data, int $userId, Club $club): ClubMember
    {
        $isSelfUpdate = $member->user_id === $userId;
        $currentUserMember = $club->activeMembers()->where('user_id', $userId)->first();
        $currentUserRole = $currentUserMember?->role;
        $oldRole = $member->role;

        $member = DB::transaction(function () use ($member, $data, $userId, $club, $isSelfUpdate, $currentUserRole) {
            if (isset($data['role'])) {
                $this->validateRoleUpdate($data['role'], $isSelfUpdate, $currentUserRole, $member, $club);
            }

            if (isset($data['status'])) {
                $this->validateStatusUpdate($data['status'], $isSelfUpdate, $member, $club);
            }

            if (isset($data['status']) && $data['status'] === ClubMemberStatus::Active && $member->membership_status === ClubMembershipStatus::Pending) {
                $member->update([
                    'membership_status' => ClubMembershipStatus::Joined,
                    'status' => ClubMemberStatus::Active,
                    'reviewed_by' => $userId,
                    'reviewed_at' => now(),
                    'joined_at' => now(),
                    'role' => $data['role'] ?? $member->role,
                ]);
            } elseif (isset($data['rejection_reason']) && $member->membership_status === ClubMembershipStatus::Pending) {
                $member->delete();
                throw new BusinessException('DELETED');
            } else {
                $member->update($data);
            }

            return $member->fresh();
        });

        if ($member->role->value !== $oldRole->value) {
            $this->notifyRoleChange($member, $club, $userId);
        }

        return $member;
    }

    public function kickMember(ClubMember $member, int $kickerId): void
    {
        if ($member->user_id === $kickerId) {
            throw new BusinessException('Bạn không thể đuổi chính mình khỏi CLB. Vui lòng sử dụng chức năng Rời CLB');
        }

        if ($member->role === ClubMemberRole::Admin && !$member->club->hasAtLeastOneAdminAfterRemoving($member->id)) {
            throw new BusinessException('Không thể đuổi admin này vì sẽ không còn admin nào trong CLB. Vui lòng chỉ định admin khác trước');
        }

        $member->update([
            'role' => ClubMemberRole::Member,
            'membership_status' => ClubMembershipStatus::Left,
            'status' => ClubMemberStatus::Suspended,
            'left_at' => now(),
        ]);

        $club = $member->club;
        $user = $member->user;
        if ($user && $club) {
            $user->notify(new ClubMemberKickedNotification($club));
            SendPushJob::dispatch($user->id, 'Bạn đã bị đuổi khỏi CLB', "Bạn đã bị đuổi khỏi CLB {$club->name}", [
                'type' => 'CLUB_MEMBER_KICKED',
                'club_id' => (string) $club->id,
            ]);
        }
    }

    public function cancelInvitation(ClubMember $member, int $inviterId): void
    {
        if ($member->membership_status !== ClubMembershipStatus::Pending || $member->invited_by !== $inviterId) {
            throw new BusinessException('Chỉ có thể hủy lời mời do chính bạn gửi');
        }

        $club = $member->club;
        $invitedUser = $member->user;
        $inviter = User::find($inviterId);
        $inviterName = $inviter ? ($inviter->full_name ?: $inviter->email) : 'Người mời';

        if ($invitedUser && $club) {
            $message = "Lời mời tham gia CLB {$club->name} đã bị hủy bởi {$inviterName}";
            $invitedUser->notify(new ClubInvitationCancelledNotification($club, $inviterName));
            SendPushJob::dispatch($invitedUser->id, 'Lời mời tham gia CLB đã bị hủy', $message, [
                'type' => 'CLUB_INVITATION_CANCELLED',
                'club_id' => (string) $club->id,
            ]);
        }

        $member->delete();
    }

    private function validateRoleUpdate(string $newRole, bool $isSelfUpdate, ?ClubMemberRole $currentUserRole, ClubMember $member, Club $club): void
    {
        $currentRoleValue = $currentUserRole?->value;
        $canUpdateRole = in_array($currentRoleValue, [ClubMemberRole::Admin->value, ClubMemberRole::Secretary->value], true);

        if (!$canUpdateRole) {
            throw new BusinessException('Chỉ admin hoặc thư ký mới có quyền thay đổi role của thành viên');
        }

        if ($currentRoleValue === ClubMemberRole::Secretary->value && $newRole === ClubMemberRole::Admin->value) {
            throw new BusinessException('Thư ký không có quyền chỉ định role Quản trị viên');
        }

        if ($isSelfUpdate) {
            throw new BusinessException('Bạn không thể thay đổi role của chính mình');
        }

        $isDowngradingAdmin = $member->role === ClubMemberRole::Admin && !in_array($newRole, [ClubMemberRole::Admin->value, ClubMemberRole::Manager->value], true);
        if ($isDowngradingAdmin && !$club->hasAtLeastOneAdminAfterRemoving($member->id)) {
            throw new BusinessException('Không thể thay đổi role của admin này vì sẽ không còn admin nào trong CLB');
        }
    }

    private function validateStatusUpdate(string $newStatus, bool $isSelfUpdate, ClubMember $member, Club $club): void
    {
        $isSuspending = in_array($newStatus, [ClubMemberStatus::Inactive->value, ClubMemberStatus::Suspended->value], true);

        if ($isSuspending && $member->role === ClubMemberRole::Admin) {
            if (!$club->hasAtLeastOneAdminAfterRemoving($member->id)) {
                $message = $isSelfUpdate
                    ? 'Bạn không thể tự suspend chính mình vì sẽ không còn admin nào trong CLB'
                    : 'Không thể suspend admin này vì sẽ không còn admin nào trong CLB';
                throw new BusinessException($message);
            }
        }
    }

    private function notifyRoleChange(ClubMember $member, Club $club, int $updaterId): void
    {
        $roleLabel = $member->role->label();
        $user = $member->user;

        // Một thông báo duy nhất: Laravel notification + push (không tạo thêm club notification để tránh trùng)
        if ($user) {
            $message = "Bạn được bổ nhiệm làm {$roleLabel} trong CLB {$club->name}";
            $user->notify(new ClubRoleChangeNotification($club, $member, $roleLabel, $updaterId));
            SendPushJob::dispatch($user->id, 'Bạn được bổ nhiệm làm ' . $roleLabel, $message, [
                'type' => 'CLUB_ROLE_CHANGE',
                'club_id' => (string) $club->id,
                'club_member_id' => (string) $member->id,
            ]);
        }
    }
}
