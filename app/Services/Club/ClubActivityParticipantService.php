<?php

namespace App\Services\Club;

use App\Enums\ClubActivityParticipantStatus;
use App\Exceptions\BusinessException;
use App\Enums\ClubActivityStatus;
use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubWalletTransactionDirection;
use App\Enums\ClubWalletTransactionSourceType;
use App\Enums\ClubWalletTransactionStatus;
use App\Enums\PaymentMethod;
use App\Jobs\SendPushJob;
use App\Models\Club\ClubActivity;
use App\Models\Club\ClubActivityParticipant;
use App\Models\Club\ClubMember;
use App\Models\Club\ClubWalletTransaction;
use App\Models\User;
use App\Notifications\ClubActivityInvitationNotification;
use App\Notifications\ClubActivityJoinApprovedNotification;
use App\Notifications\ClubActivityJoinRejectedNotification;
use App\Notifications\ClubActivityJoinRequestNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClubActivityParticipantService
{
    public function getParticipants(ClubActivity $activity, ?string $statusFilter = null): array
    {
        $allParticipants = $activity->participants()->get();

        $query = $activity->participants()->with(['user', 'walletTransaction']);

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        } else {
            // Danh sách thành viên đăng ký = đã duyệt (Accepted) + đã check-in (Attended) để người check-in vẫn nằm trong list
            $query->whereIn('status', [
                ClubActivityParticipantStatus::Accepted,
                ClubActivityParticipantStatus::Attended,
            ]);
        }

        $participants = $query->orderBy('created_at')->get();

        return [
            'participants' => $participants,
            'total' => $participants->count(),
            'pending_count' => $allParticipants->where('status', ClubActivityParticipantStatus::Pending)->count(),
            'invited_count' => $allParticipants->where('status', ClubActivityParticipantStatus::Invited)->count(),
            'accepted_count' => $allParticipants->where('status', ClubActivityParticipantStatus::Accepted)->count(),
            'attended_count' => $allParticipants->where('status', ClubActivityParticipantStatus::Attended)->count(),
        ];
    }

    public function joinActivity(ClubActivity $activity, int $userId): ClubActivityParticipant
    {
        if ($activity->status === ClubActivityStatus::Cancelled) {
            throw new BusinessException('Sự kiện đã bị hủy');
        }

        if ($activity->status === ClubActivityStatus::Completed) {
            throw new BusinessException('Sự kiện đã kết thúc');
        }

        $club = $activity->club;
        $member = $club->activeMembers()->where('user_id', $userId)->first();

        if (!$member) {
            throw new BusinessException('Bạn không phải thành viên CLB');
        }

        $isCreator = (int) $activity->created_by === (int) $userId;
        $isAdminOrManager = in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary]);
        $shouldAutoAccept = $isCreator || $isAdminOrManager;

        // Check if user already has a participant record
        $existingParticipant = $activity->participants()->where('user_id', $userId)->first();

        if ($existingParticipant) {
            if (in_array($existingParticipant->status, [
                ClubActivityParticipantStatus::Pending,
                ClubActivityParticipantStatus::Invited,
                ClubActivityParticipantStatus::Accepted,
                ClubActivityParticipantStatus::Attended,
            ])) {
                throw new BusinessException('Bạn đã tham gia hoạt động này');
            }

            // If status is Declined or Absent, allow rejoin
            $newStatus = $shouldAutoAccept ? ClubActivityParticipantStatus::Accepted : ClubActivityParticipantStatus::Pending;

            $existingParticipant->update([
                'status' => $newStatus,
                'wallet_transaction_id' => null,
            ]);

            if (!$shouldAutoAccept) {
                $applicant = User::find($userId);
                if ($applicant) {
                    $this->notifyManagersOfJoinRequest($activity, $applicant);
                }
            }

            return $existingParticipant->fresh();
        }

        if ($activity->max_participants !== null && $activity->acceptedParticipants()->count() >= $activity->max_participants) {
            throw new BusinessException('Sự kiện đã đủ số lượng người tham gia');
        }

        $initialStatus = $shouldAutoAccept ? ClubActivityParticipantStatus::Accepted : ClubActivityParticipantStatus::Pending;

        $participant = ClubActivityParticipant::create([
            'club_activity_id' => $activity->id,
            'user_id' => $userId,
            'status' => $initialStatus,
        ]);

        if (!$shouldAutoAccept) {
            $applicant = User::find($userId);
            if ($applicant) {
                $this->notifyManagersOfJoinRequest($activity, $applicant);
            }
        }

        return $participant->fresh();
    }

    private function notifyManagersOfJoinRequest(ClubActivity $activity, User $applicant): void
    {
        $club = $activity->club;
        $managerUserIds = ClubMember::where('club_id', $club->id)
            ->where('membership_status', ClubMembershipStatus::Joined)
            ->where('status', ClubMemberStatus::Active)
            ->whereIn('role', [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])
            ->pluck('user_id')
            ->unique()
            ->filter(fn ($id) => $id !== $applicant->id);

        $applicantName = $applicant->full_name ?: $applicant->email;
        $message = "{$applicantName} muốn tham gia sự kiện {$activity->title} tại CLB {$club->name}";

        foreach ($managerUserIds as $recipientId) {
            $user = User::find($recipientId);
            if ($user) {
                $user->notify(new ClubActivityJoinRequestNotification($club, $activity, $applicant));
                SendPushJob::dispatch($user->id, 'Yêu cầu tham gia sự kiện', $message, [
                    'type' => 'CLUB_ACTIVITY_JOIN_REQUEST',
                    'club_id' => (string) $club->id,
                    'club_activity_id' => (string) $activity->id,
                ]);
            }
        }
    }

    public function inviteUsers(ClubActivity $activity, array $userIds, int $inviterId): array
    {
        if ($activity->status === ClubActivityStatus::Cancelled) {
            throw new BusinessException('Sự kiện đã bị hủy');
        }

        if ($activity->status === ClubActivityStatus::Completed) {
            throw new BusinessException('Sự kiện đã kết thúc');
        }

        $club = $activity->club;
        $member = $club->activeMembers()->where('user_id', $inviterId)->first();

        if (!$member) {
            throw new BusinessException('Bạn không phải thành viên CLB');
        }

        $canInvite = in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])
            || $activity->allow_member_invite;

        if (!$canInvite) {
            throw new BusinessException('Chỉ admin/manager/secretary hoặc khi sự kiện cho phép thành viên mời mới có quyền mời');
        }

        $currentCount = $activity->participants()->count();
        $maxParticipants = $activity->max_participants;

        $invited = [];
        foreach ($userIds as $userId) {
            if ($maxParticipants !== null && $currentCount >= $maxParticipants) {
                break;
            }

            if (!$activity->participants()->where('user_id', $userId)->exists()) {
                $participant = ClubActivityParticipant::create([
                    'club_activity_id' => $activity->id,
                    'user_id' => $userId,
                    'status' => ClubActivityParticipantStatus::Invited,
                ]);
                $participant->load('user');
                $invited[] = $participant;
                $currentCount++;

                $user = User::find($userId);
                if ($user) {
                    $message = "Bạn được mời tham gia sự kiện {$activity->title} tại CLB {$club->name}";
                    $user->notify(new ClubActivityInvitationNotification($club, $activity));
                    SendPushJob::dispatch($user->id, 'Lời mời tham gia sự kiện', $message, [
                        'type' => 'CLUB_ACTIVITY_INVITATION',
                        'club_id' => (string) $club->id,
                        'club_activity_id' => (string) $activity->id,
                    ]);
                }
            }
        }

        return [
            'invited_count' => count($invited),
            'participants' => $invited,
        ];
    }

    public function updateParticipantStatus(ClubActivityParticipant $participant, string $status): ClubActivityParticipant
    {
        return DB::transaction(function () use ($participant, $status) {
            $participant->update(['status' => $status]);

            return $participant;
        });
    }

    public function approveRequest(ClubActivityParticipant $participant, int $approverId): ClubActivityParticipant
    {
        $activity = $participant->activity;
        if ($activity->status === ClubActivityStatus::Cancelled) {
            throw new BusinessException('Sự kiện đã bị hủy');
        }
        if ($activity->status === ClubActivityStatus::Completed) {
            throw new BusinessException('Sự kiện đã kết thúc');
        }

        $club = $activity->club;
        $member = $club->activeMembers()->where('user_id', $approverId)->first();

        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            throw new BusinessException('Chỉ admin/manager/secretary mới có quyền duyệt yêu cầu');
        }

        if ($participant->status !== ClubActivityParticipantStatus::Pending) {
            throw new BusinessException('Chỉ có thể duyệt yêu cầu đang pending');
        }

        if ($activity->max_participants !== null && $activity->acceptedParticipants()->count() >= $activity->max_participants) {
            throw new BusinessException('Sự kiện đã đủ số lượng người tham gia');
        }

        $participant = DB::transaction(function () use ($participant) {
            $participant->update(['status' => ClubActivityParticipantStatus::Accepted]);

            return $participant;
        });

        $user = $participant->user;
        $club = $activity->club;
        if ($user && $club) {
            $message = "Yêu cầu tham gia sự kiện {$activity->title} đã được duyệt";
            $user->notify(new ClubActivityJoinApprovedNotification($club, $activity));
            SendPushJob::dispatch($user->id, 'Yêu cầu tham gia sự kiện đã được duyệt', $message, [
                'type' => 'CLUB_ACTIVITY_JOIN_APPROVED',
                'club_id' => (string) $club->id,
                'club_activity_id' => (string) $activity->id,
            ]);
        }

        return $participant;
    }

    public function rejectRequest(ClubActivityParticipant $participant, int $rejecterId): ClubActivityParticipant
    {
        $activity = $participant->activity;
        if ($activity->status === ClubActivityStatus::Cancelled) {
            throw new BusinessException('Sự kiện đã bị hủy');
        }

        $club = $activity->club;
        $member = $club->activeMembers()->where('user_id', $rejecterId)->first();

        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            throw new BusinessException('Chỉ admin/manager/secretary mới có quyền từ chối yêu cầu');
        }

        if ($participant->status !== ClubActivityParticipantStatus::Pending) {
            throw new BusinessException('Chỉ có thể từ chối yêu cầu đang pending');
        }

        $participant->decline();

        $user = $participant->user;
        if ($user && $club) {
            $message = "Yêu cầu tham gia sự kiện {$activity->title} đã bị từ chối";
            $user->notify(new ClubActivityJoinRejectedNotification($club, $activity));
            SendPushJob::dispatch($user->id, 'Yêu cầu tham gia sự kiện đã bị từ chối', $message, [
                'type' => 'CLUB_ACTIVITY_JOIN_REJECTED',
                'club_id' => (string) $club->id,
                'club_activity_id' => (string) $activity->id,
            ]);
        }

        return $participant;
    }

    public function acceptInvite(ClubActivityParticipant $participant, int $userId): ClubActivityParticipant
    {
        if ($participant->user_id !== $userId) {
            throw new BusinessException('Chỉ có thể chấp nhận lời mời của chính mình');
        }

        if ($participant->status !== ClubActivityParticipantStatus::Invited) {
            throw new BusinessException('Chỉ có thể chấp nhận lời mời đang invited');
        }

        $activity = $participant->activity;
        if ($activity->status === ClubActivityStatus::Cancelled) {
            throw new BusinessException('Sự kiện đã bị hủy');
        }
        if ($activity->status === ClubActivityStatus::Completed) {
            throw new BusinessException('Sự kiện đã kết thúc');
        }

        if ($activity->max_participants !== null && $activity->acceptedParticipants()->count() >= $activity->max_participants) {
            throw new BusinessException('Sự kiện đã đủ số lượng người tham gia');
        }

        return DB::transaction(function () use ($participant) {
            $participant->update(['status' => ClubActivityParticipantStatus::Accepted]);

            return $participant;
        });
    }

    public function declineInvite(ClubActivityParticipant $participant, int $userId): ClubActivityParticipant
    {
        if ($participant->user_id !== $userId) {
            throw new BusinessException('Chỉ có thể từ chối lời mời của chính mình');
        }

        if ($participant->status !== ClubActivityParticipantStatus::Invited) {
            throw new BusinessException('Chỉ có thể từ chối lời mời đang invited');
        }

        $participant->decline();

        return $participant;
    }

    public function cancelRequest(ClubActivityParticipant $participant, int $userId): void
    {
        if ($participant->user_id !== $userId) {
            throw new BusinessException('Chỉ có thể hủy yêu cầu của chính mình');
        }

        if ($participant->status !== ClubActivityParticipantStatus::Pending) {
            throw new BusinessException('Chỉ có thể hủy yêu cầu đang pending');
        }

        $participant->delete();
    }

    public function withdraw(ClubActivityParticipant $participant, int $userId): array
    {
        if ($participant->user_id !== $userId) {
            throw new BusinessException('Chỉ có thể rút khỏi sự kiện của chính mình');
        }

        $activity = $participant->activity;

        if (!$activity->isScheduled()) {
            throw new BusinessException('Chỉ có thể rút khỏi sự kiện đang scheduled');
        }

        if ($participant->status !== ClubActivityParticipantStatus::Accepted) {
            throw new BusinessException('Chỉ có thể rút khỏi sự kiện khi đã chấp nhận tham gia');
        }

        return DB::transaction(function () use ($participant, $activity) {
            $club = $activity->club;
            $mainWallet = $club->mainWallet;

            $hoursUntilStart = Carbon::now()->diffInHours($activity->start_time, false);
            $isBefore4Hours = $hoursUntilStart >= 4;

            $transaction = $participant->walletTransaction;

            if ($transaction && $mainWallet) {
                // Cancel the fee transaction
                $transaction->reject($participant->user_id);

                // If withdrawn after 4 hours => create penalty transaction
                if (!$isBefore4Hours) {
                    $penaltyAmount = $activity->penalty_amount ?? 0;

                    if ($penaltyAmount > 0) {
                        ClubWalletTransaction::create([
                            'club_wallet_id' => $mainWallet->id,
                            'direction' => ClubWalletTransactionDirection::In,
                            'amount' => $penaltyAmount,
                            'source_type' => ClubWalletTransactionSourceType::ActivityPenalty,
                            'source_id' => $activity->id,
                            'payment_method' => PaymentMethod::BankTransfer,
                            'status' => ClubWalletTransactionStatus::Pending,
                            'description' => "Phạt rút khỏi sự kiện: {$activity->title} (rút sau 4 tiếng - phí phạt " . number_format($penaltyAmount, 0, ',', '.') . " VND)",
                            'created_by' => $participant->user_id,
                        ]);
                    }
                }
            }

            // Update participant status to declined
            $participant->decline();

            $message = $isBefore4Hours
                ? 'Đã rút khỏi sự kiện. Khoản thu đã được hủy.'
                : "Đã rút khỏi sự kiện. Khoản thu đã được hủy và tạo khoản thu phạt (" . number_format($activity->penalty_amount ?? 0, 0, ',', '.') . " VND) do rút sau 4 tiếng.";

            return [
                'participant' => $participant,
                'message' => $message,
            ];
        });
    }

    public function deleteParticipant(ClubActivityParticipant $participant, int $deleterId): void
    {
        $activity = $participant->activity;
        $club = $activity->club;
        $member = $club->activeMembers()->where('user_id', $deleterId)->first();

        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            throw new BusinessException('Chỉ admin/manager/secretary mới có quyền xóa');
        }

        $participant->delete();
    }

    public function checkIn(ClubActivity $activity, string $token, int $userId): ClubActivityParticipant
    {
        if ($activity->status === ClubActivityStatus::Cancelled) {
            throw new BusinessException('Sự kiện đã bị hủy');
        }

        if ($activity->status === ClubActivityStatus::Completed) {
            throw new BusinessException('Sự kiện đã kết thúc, không thể check-in');
        }

        if ($activity->end_time && $activity->end_time->isPast()) {
            throw new BusinessException('Sự kiện đã qua giờ kết thúc, không thể check-in');
        }

        if (!$activity->check_in_token || $activity->check_in_token !== $token) {
            throw new BusinessException('Mã check-in không hợp lệ');
        }

        $member = $activity->club->activeMembers()->where('user_id', $userId)->first();
        if (!$member) {
            throw new BusinessException('Bạn không phải thành viên CLB');
        }

        $participant = ClubActivityParticipant::where('club_activity_id', $activity->id)
            ->where('user_id', $userId)
            ->with('user')
            ->first();

        if (!$participant) {
            throw new BusinessException('Bạn chưa tham gia hoạt động này');
        }

        if ($participant->status === ClubActivityParticipantStatus::Attended) {
            return $participant; // Already checked in
        }

        if ($participant->status !== ClubActivityParticipantStatus::Accepted) {
            throw new BusinessException('Chỉ có thể check-in khi đã được duyệt tham gia');
        }

        $participant->update([
            'status' => ClubActivityParticipantStatus::Attended,
            'checked_in_at' => now(),
            'is_absent' => false,
        ]);

        return $participant;
    }

    public function manualCheckIn(ClubActivityParticipant $participant, int $userId): ClubActivityParticipant
    {
        $activity = $participant->activity;

        if ($activity->status === ClubActivityStatus::Cancelled) {
            throw new BusinessException('Sự kiện đã bị hủy');
        }

        if ($activity->status === ClubActivityStatus::Completed) {
            throw new BusinessException('Sự kiện đã kết thúc, không thể check-in');
        }

        if ($activity->end_time && $activity->end_time->isPast()) {
            throw new BusinessException('Sự kiện đã qua giờ kết thúc, không thể check-in');
        }

        if ($participant->user_id !== $userId) {
            throw new BusinessException('Chỉ có thể check-in cho chính mình');
        }

        if ($participant->status === ClubActivityParticipantStatus::Attended) {
            return $participant;
        }

        if ($participant->status !== ClubActivityParticipantStatus::Accepted) {
            throw new BusinessException('Chỉ có thể check-in khi người tham gia đã được duyệt');
        }

        $participant->update([
            'status' => ClubActivityParticipantStatus::Attended,
            'checked_in_at' => now(),
            'is_absent' => false,
        ]);

        return $participant;
    }

    public function markAbsent(ClubActivityParticipant $participant, int $userId): ClubActivityParticipant
    {
        $activity = $participant->activity;

        if ($activity->status === ClubActivityStatus::Cancelled) {
            throw new BusinessException('Sự kiện đã bị hủy');
        }

        if ($activity->status === ClubActivityStatus::Completed) {
            throw new BusinessException('Sự kiện đã kết thúc, không thể báo vắng');
        }

        if ($participant->user_id !== $userId) {
            throw new BusinessException('Chỉ có thể báo vắng cho chính mình');
        }

        if (!in_array($participant->status, [ClubActivityParticipantStatus::Accepted, ClubActivityParticipantStatus::Attended])) {
            throw new BusinessException('Chỉ có thể báo vắng khi đã được duyệt tham gia');
        }

        // Báo vắng chỉ set cờ is_absent, không đổi status - user vẫn là participant, vẫn có thể check-in
        $participant->update(['is_absent' => true]);

        return $participant;
    }

    public function getCheckInList(ClubActivity $activity, int $requesterId): array
    {
        $member = $activity->club->activeMembers()->where('user_id', $requesterId)->first();

        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            throw new BusinessException('Chỉ admin/manager/secretary mới có quyền xem danh sách check-in');
        }

        $checkedIn = $activity->participants()
            ->where('status', ClubActivityParticipantStatus::Attended)
            ->with('user')
            ->get();

        $waiting = $activity->participants()
            ->where('status', ClubActivityParticipantStatus::Accepted)
            ->with('user')
            ->get();

        return [
            'checked_in' => $checkedIn,
            'waiting' => $waiting,
            'summary' => [
                'checked_in_count' => $checkedIn->count(),
                'waiting_count' => $waiting->count(),
            ],
        ];
    }

}
