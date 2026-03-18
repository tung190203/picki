<?php

namespace App\Services\Club;

use App\Enums\ClubActivityParticipantStatus;
use App\Enums\ClubFundCollectionStatus;
use App\Enums\ClubFundContributionStatus;
use App\Jobs\SendPushJob;
use App\Models\Club\Club;
use App\Models\Club\ClubActivity;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubWallet;
use App\Models\User;
use App\Notifications\ClubFundCollectionCancelledNotification;
use App\Notifications\ClubFundCollectionCreatedNotification;
use App\Notifications\ClubFundCollectionReminderNotification;
use App\Services\ImageOptimizationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClubFundCollectionService
{
    public function __construct(
        protected ImageOptimizationService $imageService
    ) {
    }

    public function getCollections(Club $club, array $filters): LengthAwarePaginator
    {
        $query = $club->fundCollections()
            ->activeAndNotExpired()
            ->with([
                'creator',
                'contributions' => function ($q) {
                    $q->where('status', ClubFundContributionStatus::Pending)
                        ->with('user');
                },
            ])
            ->withCount([
                'contributions',
                'contributions as confirmed_count' => function ($q) {
                    $q->where('status', ClubFundContributionStatus::Confirmed);
                },
                'contributions as pending_count' => function ($q) {
                    $q->where('status', ClubFundContributionStatus::Pending);
                },
                'assignedMembers',
            ]);

        $this->updateExpiredCollections($club);

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', '%' . $term . '%')
                    ->orWhere('description', 'like', '%' . $term . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function createCollection(Club $club, array $data, int $userId): ClubFundCollection
    {
        if (!$club->canManageFinance($userId)) {
            throw new \Exception('Chỉ admin/manager/secretary/treasurer mới có quyền tạo đợt thu');
        }

        $activityId = $data['activity_id'] ?? null;
        if ($activityId) {
            $activity = ClubActivity::where('club_id', $club->id)->findOrFail($activityId);
            if (empty($data['member_ids'])) {
                $data['member_ids'] = $activity->participants()
                    ->whereIn('status', [ClubActivityParticipantStatus::Accepted, ClubActivityParticipantStatus::Attended])
                    ->pluck('user_id')
                    ->unique()
                    ->values()
                    ->all();
            }
            // Nếu không có QR trong request, kế thừa QR từ activity
            if (empty($data['qr_code_url']) && !empty($activity->qr_code_url)) {
                $data['qr_code_url'] = $activity->qr_code_url;
            }
        }

        $endDate = $data['end_date'] ?? $data['deadline'] ?? null;
        $titleOrDescription = $data['title'] ?? $data['description'] ?? '';
        $memberCount = !empty($data['member_ids']) ? count($data['member_ids']) : 0;
        $amountPerMember = $this->calculateAmountPerMember($data, $memberCount);

        $targetAmount = isset($data['target_amount'])
            ? (float) $data['target_amount']
            : (float) ($amountPerMember * $memberCount);

        // included_in_club_fund áp dụng cho tất cả khoản thu.
        // Nếu false: dùng QR riêng upload lên, không ghi transaction vào ví chính.
        // Nếu true (mặc định): ưu tiên dùng QR ví chính, fallback sang QR riêng nếu ví chính chưa có.
        $includedInClubFund = array_key_exists('included_in_club_fund', $data)
            ? filter_var($data['included_in_club_fund'], FILTER_VALIDATE_BOOLEAN)
            : true;

        // Luôn lưu qr_code_url nếu có (để làm fallback khi CLB chưa có QR chung)
        $collectionQrUrl = $data['qr_code_url'] ?? null;

        $collection = ClubFundCollection::create([
            'club_id' => $club->id,
            'club_activity_id' => $activityId,
            'title' => $titleOrDescription,
            'description' => $titleOrDescription,
            'target_amount' => $targetAmount,
            'amount_per_member' => $memberCount > 0 ? $amountPerMember : null,
            'collected_amount' => 0,
            'currency' => $data['currency'] ?? 'VND',
            'start_date' => $data['start_date'],
            'end_date' => $endDate,
            'status' => ClubFundCollectionStatus::Active,
            'qr_code_url' => $collectionQrUrl,
            'created_by' => $userId,
            'included_in_club_fund' => $includedInClubFund,
        ]);

        if ($activityId) {
            ClubActivity::where('id', $activityId)->update(['has_transaction' => true]);
        }

        if (!empty($data['member_ids'])) {
            $syncData = [];
            foreach ($data['member_ids'] as $memberId) {
                $syncData[$memberId] = ['amount_due' => $amountPerMember];
            }
            $collection->assignedMembers()->sync($syncData);

            // Notify assigned members: "Bạn có khoản thu mới cần đóng"
            $collectionTitle = $collection->title ?: $collection->description ?: 'Đợt thu quỹ';
            $message = "Bạn có khoản thu mới cần đóng: {$collectionTitle} tại CLB {$club->name}";
            if ($amountPerMember > 0) {
                $message .= ' - Số tiền: ' . number_format($amountPerMember, 0, ',', '.') . ' VND';
            }

            foreach ($data['member_ids'] as $memberUserId) {
                $user = User::find($memberUserId);
                if ($user) {
                    $user->notify(new ClubFundCollectionCreatedNotification($club, $collection, $amountPerMember));
                    SendPushJob::dispatch($user->id, 'Khoản thu mới cần đóng', $message, [
                        'type' => 'CLUB_FUND_COLLECTION_CREATED',
                        'club_id' => (string) $club->id,
                        'club_fund_collection_id' => (string) $collection->id,
                    ]);
                }
            }
        }

        return $collection;
    }

    public function getCollectionDetail(ClubFundCollection $collection): array
    {
        $confirmedContributions = $collection->contributions()
            ->where('status', ClubFundContributionStatus::Confirmed)
            ->with('user')
            ->get();

        $pendingContributions = $collection->contributions()
            ->where('status', ClubFundContributionStatus::Pending)
            ->with('user')
            ->get();

        $confirmedByUser = $confirmedContributions->keyBy('user_id');
        $pendingByUser = $pendingContributions->keyBy('user_id');
        $amountPerMember = (float) ($collection->amount_per_member ?? 0);

        $memberSources = $this->getMemberSources($collection, $amountPerMember);

        $approvedPayments = $this->buildApprovedPayments($memberSources, $confirmedByUser);
        $waitingApprovalPayments = $this->buildWaitingApprovalPayments($memberSources, $pendingByUser);
        $noPaymentYet = $this->buildNoPaymentYet($memberSources, $confirmedByUser, $pendingByUser);

        $memberUserIds = $memberSources->pluck('user.id');
        $approvedPayments = $this->addNonMemberContributions($approvedPayments, $confirmedContributions, $memberUserIds, $amountPerMember);
        $waitingApprovalPayments = $this->addNonMemberContributions($waitingApprovalPayments, $pendingContributions, $memberUserIds, $amountPerMember);

        return [
            'collection' => $collection,
            'approved_payments' => $approvedPayments,
            'waiting_approval_payments' => $waitingApprovalPayments,
            'no_payment_yet' => $noPaymentYet,
            'summary' => [
                'total_members' => $memberSources->count(),
                'approved_count' => $approvedPayments->count(),
                'waiting_approval_count' => $waitingApprovalPayments->count(),
                'no_payment_count' => $noPaymentYet->count(),
            ],
        ];
    }

    public function updateCollection(ClubFundCollection $collection, array $data, int $userId): ClubFundCollection
    {
        $club = $collection->club;
        if (!$club->canManageFinance($userId) || $collection->created_by !== $userId) {
            throw new \Exception('Không có quyền cập nhật đợt thu này');
        }

        if ($collection->status !== ClubFundCollectionStatus::Active) {
            throw new \Exception('Chỉ có thể cập nhật đợt thu đang active');
        }

        if (array_key_exists('amount_per_member', $data) && $data['amount_per_member'] !== null) {
            $newAmount = (float) $data['amount_per_member'];
            DB::table('club_fund_collection_members')
                ->where('club_fund_collection_id', $collection->id)
                ->update(['amount_due' => $newAmount]);
        }

        $collection->update($data);
        return $collection;
    }

    public function cancelCollection(ClubFundCollection $collection, int $userId): void
    {
        $club = $collection->club;
        if (!$club->canManageFinance($userId)) {
            throw new \Exception('Chỉ admin/manager/secretary/treasurer mới có quyền hủy đợt thu');
        }

        if ($collection->status !== ClubFundCollectionStatus::Active) {
            throw new \Exception('Chỉ có thể hủy đợt thu đang active');
        }

        $collectionTitle = $collection->title ?: $collection->description ?: 'Đợt thu quỹ';
        $message = "Đợt thu {$collectionTitle} tại CLB {$club->name} đã bị hủy";

        $assignedUserIds = $collection->assignedMembers()->pluck('users.id')->unique();
        foreach ($assignedUserIds as $memberUserId) {
            if ($memberUserId == $userId) {
                continue;
            }
            $user = User::find($memberUserId);
            if ($user) {
                $user->notify(new ClubFundCollectionCancelledNotification($club, $collection));
                SendPushJob::dispatch($user->id, 'Đợt thu đã bị hủy', $message, [
                    'type' => 'CLUB_FUND_COLLECTION_CANCELLED',
                    'club_id' => (string) $club->id,
                    'club_fund_collection_id' => (string) $collection->id,
                ]);
            }
        }

        $collection->update(['status' => ClubFundCollectionStatus::Cancelled]);
    }

    public function getQrCodes(Club $club, array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;

        return $club->fundCollections()
            ->whereNotNull('qr_code_url')
            ->where('qr_code_url', '!=', '')
            ->with(['creator', 'assignedMembers'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function deleteQrCode(Club $club, int $qrCodeId, int $userId): void
    {
        if (!$club->canManageFinance($userId)) {
            throw new \Exception('Chỉ admin/manager/secretary/treasurer mới có quyền xóa mã QR');
        }

        $collection = $club->fundCollections()
            ->whereNotNull('qr_code_url')
            ->where('qr_code_url', '!=', '')
            ->findOrFail($qrCodeId);

        if ($collection->status !== ClubFundCollectionStatus::Pending) {
            throw new \Exception('Chỉ có thể xóa mã QR chưa gắn với đợt thu (trạng thái Pending)');
        }

        if ($collection->contributions()->exists()) {
            $count = $collection->contributions()->count();
            throw new \Exception("Mã QR đã có {$count} lượt nộp tiền, không thể xóa.");
        }

        $collection->delete();
    }

    public function sendReminder(ClubFundCollection $collection, int $targetUserId, int $requesterId): void
    {
        $club = $collection->club;
        if (!$club->canManageFinance($requesterId)) {
            throw new \Exception('Chỉ admin/manager/secretary/treasurer mới có quyền nhắc nhở');
        }

        if ($collection->status !== ClubFundCollectionStatus::Active) {
            throw new \Exception('Chỉ có thể nhắc nhở cho đợt thu đang active');
        }

        $detail = $this->getCollectionDetail($collection);
        $noPaymentYet = $detail['no_payment_yet'];
        $targetItem = $noPaymentYet->firstWhere('user.id', $targetUserId);

        if (!$targetItem) {
            throw new \Exception('Thành viên này không thuộc danh sách chưa đóng hoặc đã đóng khoản thu');
        }

        $user = \App\Models\User::findOrFail($targetUserId);
        $collectionTitle = $collection->title ?: $collection->description ?: 'Đợt thu quỹ';
        $clubName = $club->name;
        $amountDue = (float) ($targetItem['amount_due'] ?? 0);

        $user->notify(new ClubFundCollectionReminderNotification($collection, $collectionTitle, $clubName, $amountDue));

        $message = "Bạn được nhắc nhở đóng khoản thu {$collectionTitle} ở CLB {$clubName}";
        if ($amountDue > 0) {
            $message .= ' - Số tiền: ' . number_format($amountDue, 0, ',', '.') . ' VND';
        }
        SendPushJob::dispatch($user->id, 'Nhắc nhở đóng khoản thu', $message, [
            'type' => 'CLUB_FUND_REMINDER',
            'club_id' => (string) $club->id,
            'club_fund_collection_id' => (string) $collection->id,
        ]);
    }

    public function needPaymentForUser(ClubFundCollection $collection, int $userId): bool
    {
        if (!$collection->relationLoaded('assignedMembers')) {
            $hasMembers = $collection->assignedMembers()->exists();
            if (!$hasMembers) {
                return true;
            }
            return $collection->assignedMembers()->where('user_id', $userId)->exists();
        }
        if ($collection->assignedMembers->isEmpty()) {
            return true;
        }
        return $collection->assignedMembers->contains('id', $userId);
    }

    /**
     * Lưu QR thanh toán vào main wallet của CLB (mỗi CLB chỉ có 1 mã QR).
     */
    public function upsertMainWalletQr(Club $club, array $data, int $userId): ClubWallet
    {
        if (!$club->canManageFinance($userId)) {
            throw new \Exception('Chỉ admin/manager/secretary/treasurer mới có quyền cập nhật mã QR');
        }

        $wallet = ClubWallet::firstOrCreate(
            ['club_id' => $club->id],
            ['currency' => 'VND']
        );

        $updateData = [
            'qr_note' => array_key_exists('qr_note', $data) ? $data['qr_note'] : $wallet->qr_note,
        ];

        if (!empty($data['image'])) {
            $updateData['qr_code_url'] = $this->imageService->optimizeThumbnail($data['image'], 'qr_codes', 90);
        }

        $wallet->update($updateData);

        return $wallet->fresh();
    }

    public function deleteMainWalletQr(Club $club, int $userId): void
    {
        if (!$club->canManageFinance($userId)) {
            throw new \Exception('Chỉ admin/manager/secretary/treasurer mới có quyền xóa mã QR');
        }

        $wallet = ClubWallet::where('club_id', $club->id)->first();

        if (!$wallet || empty($wallet->qr_code_url)) {
            throw new \Exception('CLB chưa có mã QR thanh toán quỹ.');
        }

        $wallet->update([
            'qr_code_url' => null,
            'qr_note'     => null,
        ]);
    }

    public function createOrAttachQrCode(Club $club, array $data, int $userId): ClubFundCollection
    {
        if (!$club->canManageFinance($userId)) {
            throw new \Exception('Chỉ admin/manager/secretary/treasurer mới có quyền tạo/gắn mã QR');
        }

        $qrCodeUrl = $this->imageService->optimizeThumbnail($data['image'], 'qr_codes', 90);

        if (!empty($data['collection_id'])) {
            return $this->attachQrToExistingCollection($club, (int) $data['collection_id'], $qrCodeUrl);
        }

        return $this->createPendingQrCode($club, $qrCodeUrl, $data['content'] ?? null, $userId);
    }

    private function attachQrToExistingCollection(Club $club, int $collectionId, string $qrCodeUrl): ClubFundCollection
    {
        $collection = $club->fundCollections()->findOrFail($collectionId);

        if ($collection->status !== ClubFundCollectionStatus::Active) {
            throw new \Exception('Chỉ có thể gắn mã QR vào đợt thu đang active');
        }

        $collection->update(['qr_code_url' => $qrCodeUrl]);

        return $collection->fresh();
    }

    private function createPendingQrCode(Club $club, string $qrCodeUrl, ?string $content, int $userId): ClubFundCollection
    {
        $title = $content ? Str::limit($content, 255) : 'Mã QR chờ gắn đợt thu';
        $today = now()->format('Y-m-d');

        return ClubFundCollection::create([
            'club_id' => $club->id,
            'title' => $title,
            'description' => $content ?? '',
            'target_amount' => 0,
            'amount_per_member' => null,
            'collected_amount' => 0,
            'currency' => 'VND',
            'start_date' => $today,
            'end_date' => null,
            'status' => ClubFundCollectionStatus::Pending,
            'qr_code_url' => $qrCodeUrl,
            'created_by' => $userId,
        ]);
    }

    public function getMyCollections(Club $club, int $userId): array
    {
        $assignedCollections = $club->fundCollections()
            ->activeAndNotExpired()
            ->with(['creator'])
            ->get();

        $collectionIds = $assignedCollections->pluck('id');

        $contributions = $collectionIds->isEmpty()
            ? collect()
            : \App\Models\Club\ClubFundContribution::whereIn('club_fund_collection_id', $collectionIds)
                ->where('user_id', $userId)
                ->get()
                ->keyBy('club_fund_collection_id');

        $myAmountDueByCollection = $collectionIds->isEmpty()
            ? collect()
            : DB::table('club_fund_collection_members')
                ->where('user_id', $userId)
                ->whereIn('club_fund_collection_id', $collectionIds)
                ->pluck('amount_due', 'club_fund_collection_id');

        $result = $assignedCollections->map(function ($collection) use ($contributions, $myAmountDueByCollection) {
            $contribution = $contributions->get($collection->id);
            $amountDue = $myAmountDueByCollection->has($collection->id)
                ? (float) $myAmountDueByCollection->get($collection->id)
                : (float) ($collection->amount_per_member ?? 0);

            return [
                'id' => $collection->id,
                'title' => $collection->title,
                'description' => $collection->description,
                'amount_due' => (float) $amountDue,
                'currency' => $collection->currency,
                'end_date' => $collection->end_date?->format('Y-m-d'),
                'status' => $collection->status->value,
                'qr_code_url' => $collection->qr_code_url,
                'my_contribution' => $contribution ? [
                    'id' => $contribution->id,
                    'amount' => (float) $contribution->amount,
                    'status' => $contribution->status->value,
                    'created_at' => $contribution->created_at->toISOString(),
                ] : null,
                'payment_status' => $contribution ? $contribution->status->value : 'unpaid',
                'is_overdue' => $collection->end_date && now()->isAfter($collection->end_date),
            ];
        });

        return [
            'need_payment' => $result->filter(fn($item) => $item['payment_status'] === 'unpaid')->values(),
            'pending' => $result->filter(fn($item) => $item['payment_status'] === 'pending')->values(),
            'confirmed' => $result->filter(fn($item) => $item['payment_status'] === 'confirmed')->values(),
        ];
    }

    private function updateExpiredCollections(Club $club): void
    {
        $club->fundCollections()
            ->where('status', ClubFundCollectionStatus::Active)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->startOfDay())
            ->update(['status' => ClubFundCollectionStatus::Completed]);
    }

    private function calculateAmountPerMember(array $data, int $memberCount): float
    {
        if (isset($data['amount_per_member'])) {
            return (float) $data['amount_per_member'];
        }

        return $memberCount > 0
            ? (float) ($data['target_amount'] / $memberCount)
            : (float) $data['target_amount'];
    }

    private function getMemberSources(ClubFundCollection $collection, float $amountPerMember): Collection
    {
        $assignedMembers = $collection->assignedMembers()->withPivot('amount_due')->get();

        if ($assignedMembers->isNotEmpty()) {
            return $assignedMembers->map(function ($user) use ($amountPerMember) {
                return [
                    'user' => $user,
                    'amount_due' => (float) ($user->pivot?->amount_due ?? $amountPerMember),
                ];
            });
        }

        $clubMembers = $collection->club->activeMembers()->with('user')->get();
        return $clubMembers->map(function ($member) use ($amountPerMember) {
            return [
                'user' => $member->user,
                'amount_due' => $amountPerMember,
            ];
        })->filter(fn ($item) => $item['user'] !== null)->values();
    }

    private function buildApprovedPayments(Collection $memberSources, Collection $confirmedByUser): Collection
    {
        return $memberSources->filter(function ($item) use ($confirmedByUser) {
            return $confirmedByUser->has($item['user']->id);
        })->map(function ($item) use ($confirmedByUser) {
            $contribution = $confirmedByUser->get($item['user']->id);
            return [
                'user' => $item['user'],
                'amount_due' => (float) $item['amount_due'],
                'amount_paid' => (float) $contribution->amount,
                'payment_status' => ClubFundContributionStatus::Confirmed->value,
                'paid_at' => $contribution->created_at?->toISOString(),
                'contribution' => $contribution,
            ];
        })->values();
    }

    private function buildWaitingApprovalPayments(Collection $memberSources, Collection $pendingByUser): Collection
    {
        return $memberSources->filter(function ($item) use ($pendingByUser) {
            return $pendingByUser->has($item['user']->id);
        })->map(function ($item) use ($pendingByUser) {
            $contribution = $pendingByUser->get($item['user']->id);
            return [
                'user' => $item['user'],
                'amount_due' => (float) $item['amount_due'],
                'amount_paid' => (float) $contribution->amount,
                'payment_status' => ClubFundContributionStatus::Pending->value,
                'paid_at' => $contribution->created_at?->toISOString(),
                'contribution' => $contribution,
            ];
        })->values();
    }

    private function buildNoPaymentYet(Collection $memberSources, Collection $confirmedByUser, Collection $pendingByUser): Collection
    {
        return $memberSources->filter(function ($item) use ($confirmedByUser, $pendingByUser) {
            return !$confirmedByUser->has($item['user']->id) && !$pendingByUser->has($item['user']->id);
        })->map(function ($item) {
            return [
                'user' => $item['user'],
                'amount_due' => (float) $item['amount_due'],
                'amount_paid' => 0,
                'payment_status' => 'unpaid',
                'paid_at' => null,
                'contribution' => null,
            ];
        })->values();
    }

    private function addNonMemberContributions(Collection $payments, Collection $contributions, Collection $memberUserIds, float $amountPerMember): Collection
    {
        $nonMemberContributions = $contributions->filter(function ($contribution) use ($memberUserIds) {
            return !$memberUserIds->contains($contribution->user_id);
        });

        foreach ($nonMemberContributions as $contribution) {
            if ($contribution->user) {
                $payments->push([
                    'user' => $contribution->user,
                    'amount_due' => $amountPerMember,
                    'amount_paid' => (float) $contribution->amount,
                    'payment_status' => $contribution->status->value,
                    'paid_at' => $contribution->created_at?->toISOString(),
                    'contribution' => $contribution,
                ]);
            }
        }

        return $payments;
    }

}
