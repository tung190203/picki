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

    public function getMembers(Club $club, array $filters): LengthAwarePaginator
    {
        $query = $club->members()->with(['user' => User::FULL_RELATIONS, 'reviewer']);

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

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }

    public function getMemberStatistics(Club $club): array
    {
        $allMembers = ClubMember::where('club_id', $club->id)->whereHas('user');
        $joined = clone $allMembers;
        $joined->where('membership_status', ClubMembershipStatus::Joined);

        return [
            'total' => (clone $joined)->where('status', ClubMemberStatus::Active)->count(),
            'by_role' => [
                'admin' => (clone $joined)->where('status', ClubMemberStatus::Active)->where('role', ClubMemberRole::Admin)->count(),
                'manager' => (clone $joined)->where('status', ClubMemberStatus::Active)->where('role', ClubMemberRole::Manager)->count(),
                'treasurer' => (clone $joined)->where('status', ClubMemberStatus::Active)->where('role', ClubMemberRole::Treasurer)->count(),
                'secretary' => (clone $joined)->where('status', ClubMemberStatus::Active)->where('role', ClubMemberRole::Secretary)->count(),
                'member' => (clone $joined)->where('status', ClubMemberStatus::Active)->where('role', ClubMemberRole::Member)->count(),
            ],
            'by_status' => [
                'pending' => (clone $allMembers)->where('status', ClubMemberStatus::Pending)->count(),
                'active' => (clone $allMembers)->where('status', ClubMemberStatus::Active)->count(),
                'inactive' => (clone $allMembers)->where('status', ClubMemberStatus::Inactive)->count(),
                'suspended' => (clone $allMembers)->where('status', ClubMemberStatus::Suspended)->count(),
            ],
            'by_membership_status' => [
                'pending' => (clone $allMembers)->where('membership_status', ClubMembershipStatus::Pending)->count(),
                'joined' => (clone $allMembers)->where('membership_status', ClubMembershipStatus::Joined)->count(),
                'rejected' => (clone $allMembers)->where('membership_status', ClubMembershipStatus::Rejected)->count(),
                'left' => (clone $allMembers)->where('membership_status', ClubMembershipStatus::Left)->count(),
                'cancelled' => (clone $allMembers)->where('membership_status', ClubMembershipStatus::Cancelled)->count(),
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
