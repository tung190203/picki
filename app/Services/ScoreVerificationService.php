<?php

namespace App\Services;

use App\Helpers\ActivityLog;
use App\Enums\ScoreVerificationStatus;
use App\Models\ScoreVerificationRequest;
use App\Models\User;
use App\Models\UserSport;
use App\Notifications\ScoreVerificationApprovedNotification;
use App\Notifications\ScoreVerificationRejectedNotification;
use App\Repositories\ScoreVerificationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ScoreVerificationService
{
    public function __construct(
        private ScoreVerificationRepository $repository,
        private ImageOptimizationService $imageService,
        private BadgeService $badgeService
    ) {}

    public function getCurrentPickiScore(int $userId): ?float
    {
        return DB::table('user_sport_scores')
            ->join('user_sport', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
            ->where('user_sport.user_id', $userId)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->max('user_sport_scores.score_value');
    }

    public function calculateDifference(float $submitted, float $current): float
    {
        return round(abs($submitted - $current), 3);
    }

    public function isOverThreshold(float $difference): bool
    {
        return $difference >= config('score_verification.max_difference');
    }

    public function getThreshold(): float
    {
        return config('score_verification.max_difference');
    }

    public function hasPendingRequest(int $userId, ?string $scoreType = null): bool
    {
        return $this->repository->findPendingByUser($userId, $scoreType) !== null;
    }

    public function getLatestRequest(int $userId): ?array
    {
        $request = $this->repository->getLatestByUser($userId);

        if (!$request) {
            return null;
        }

        return $this->enrichWithScoreData($request);
    }

    public function createRequest(int $userId, array $data): array
    {
        $imagePath = null;

        // Nếu có id -> đang resubmit request bị reject trước đó, dùng lại image cũ
        if (!empty($data['id'])) {
            $oldRequest = $this->repository->findOrFail($data['id']);

            // Verify old request belongs to user and is rejected
            if ($oldRequest->user_id !== $userId) {
                throw new \Exception('Request not found');
            }
            if ($oldRequest->status !== ScoreVerificationStatus::REJECTED) {
                throw new \Exception('Can only resubmit rejected requests');
            }

            $imagePath = $oldRequest->image_path;
        } else {
            // Tạo mới - cần upload image
            $imagePath = $this->imageService->processAndSaveImage(
                $data['image'],
                'score-verifications',
                'verification_',
                1080,
                80
            );
        }

        $request = $this->repository->create([
            'user_id' => $userId,
            'score_type' => $data['score_type'],
            'submitted_score' => $data['score'],
            'image_path' => $imagePath,
            'status' => ScoreVerificationStatus::PENDING,
        ]);

        return $this->enrichWithScoreData($request);
    }

    public function getDetail(int $id): array
    {
        $request = $this->repository->findOrFail($id);
        return $this->enrichWithScoreData($request, true);
    }

    public function getList(array $filters, int $perPage): array
    {
        $paginator = $this->repository->getList($filters, $perPage);

        $items = $paginator->map(fn($request) => $this->enrichWithScoreData($request))->toArray();

        return [
            'items' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ];
    }

    public function getDashboard(): array
    {
        $summary = $this->repository->getSummary();
        $list = $this->getList(['status' => ScoreVerificationStatus::PENDING], 20);

        return [
            'summary' => $summary,
            'items' => $list['items'],
            'meta' => $list['meta'],
        ];
    }

    public function approveRequest(
        ScoreVerificationRequest $request,
        int $reviewerId,
        bool $awardAnchor,
        ?float $pickiScore = null
    ): array {
        if (!$this->repository->canBeApprovedOrUpdated($request->id)) {
            throw new \Exception('Request cannot be processed in its current status');
        }

        return DB::transaction(function () use ($request, $reviewerId, $awardAnchor, $pickiScore) {
            $lockedRequest = $this->repository->getWithLockForApprove($request->id);

            if (!$lockedRequest) {
                throw new \Exception('Request cannot be processed in its current status');
            }

            $isAlreadyApproved = $lockedRequest->status === ScoreVerificationStatus::APPROVED;

            $updateData = [
                'award_anchor_badge' => $awardAnchor,
                'status' => ScoreVerificationStatus::APPROVED->value,
                'reviewer_id' => $reviewerId,
                'reviewed_at' => now(),
            ];

            if ($pickiScore !== null) {
                $updateData['picki_score'] = max(1.0, round($pickiScore, 3));
            }

            $lockedRequest->update($updateData);

            if ($awardAnchor) {
                $this->badgeService->awardBadge($request->user_id, 'ANCHOR', $reviewerId);
                ActivityLog::log($request->user_id, 'badge_awarded', [
                    'badge' => 'Anchor',
                    'request_id' => $request->id,
                ]);
            }

            $this->updateUserSportScore($lockedRequest, $pickiScore);

            ActivityLog::log($request->user_id, $isAlreadyApproved ? 'verification_score_updated' : 'verification_approved', [
                'request_id' => $request->id,
                'reviewer_id' => $reviewerId,
                'award_anchor_badge' => $awardAnchor,
                'picki_score' => $pickiScore,
            ]);

            // Chỉ gửi notification lần đầu khi duyệt (tránh spam thông báo khi admin sửa lại điểm)
            if (!$isAlreadyApproved) {
                $request->user->notify(new ScoreVerificationApprovedNotification($lockedRequest));
            }

            return ['status' => ScoreVerificationStatus::APPROVED];
        });
    }

    public function rejectRequest(
        ScoreVerificationRequest $request,
        int $reviewerId,
        string $reason
    ): array {
        if (!$this->repository->isPending($request->id)) {
            throw new \Exception('Request has already been processed');
        }

        return DB::transaction(function () use ($request, $reviewerId, $reason) {
            $lockedRequest = $this->repository->getWithLockForUpdate($request->id);

            if (!$lockedRequest) {
                throw new \Exception('Request has already been processed');
            }

            $updated = $this->repository->updateStatus(
                $lockedRequest,
                ScoreVerificationStatus::REJECTED,
                $reviewerId,
                $reason
            );

            ActivityLog::log($request->user_id, 'verification_rejected', [
                'request_id' => $request->id,
                'reviewer_id' => $reviewerId,
                'reason' => $reason,
            ]);

            $request->user->notify(new ScoreVerificationRejectedNotification($request, $reason));

            return ['status' => ScoreVerificationStatus::REJECTED];
        });
    }

    /**
     * API sửa lại điểm trình Picki của user sau khi request đã được approved
     */
    public function updatePickiScore(
        ScoreVerificationRequest $request,
        int $reviewerId,
        float $pickiScore,
        ?bool $awardAnchor = null
    ): array {
        if ($request->status !== ScoreVerificationStatus::APPROVED) {
            throw new \Exception('Chỉ có thể sửa điểm trình Picki cho yêu cầu đã được phê duyệt');
        }

        return DB::transaction(function () use ($request, $reviewerId, $pickiScore, $awardAnchor) {
            $lockedRequest = $this->repository->getApprovedWithLockForUpdate($request->id);

            if (!$lockedRequest) {
                throw new \Exception('Yêu cầu không hợp lệ hoặc chưa được phê duyệt');
            }

            $finalPickiScore = max(1.0, round($pickiScore, 3));

            $updateData = [
                'picki_score' => $finalPickiScore,
                'reviewer_id' => $reviewerId,
                'reviewed_at' => now(),
            ];

            if ($awardAnchor !== null) {
                $updateData['award_anchor_badge'] = $awardAnchor;
                if ($awardAnchor) {
                    $this->badgeService->awardBadge($request->user_id, 'ANCHOR', $reviewerId);
                    ActivityLog::log($request->user_id, 'badge_awarded', [
                        'badge' => 'Anchor',
                        'request_id' => $request->id,
                    ]);
                }
            }

            $lockedRequest->update($updateData);

            // Cập nhật vndupr_score trong user_sport_scores của user (Pickleball sport_id = 1)
            $this->updateUserPickiScoreOnly($request->user_id, $finalPickiScore);

            ActivityLog::log($request->user_id, 'verification_score_updated', [
                'request_id' => $request->id,
                'reviewer_id' => $reviewerId,
                'picki_score' => $finalPickiScore,
                'award_anchor_badge' => $awardAnchor,
            ]);

            return [
                'status' => ScoreVerificationStatus::APPROVED,
                'picki_score' => $finalPickiScore,
                'item' => $this->enrichWithScoreData($lockedRequest->fresh(['user', 'reviewer']), true),
            ];
        });
    }

    private function updateUserPickiScoreOnly(int $userId, float $pickiScore): void
    {
        $userSport = UserSport::firstOrCreate(
            ['user_id' => $userId, 'sport_id' => 1],
            ['tier' => null]
        );

        $finalPickiScore = max(1.0, round($pickiScore, 3));
        DB::table('user_sport_scores')->updateOrInsert(
            [
                'user_sport_id' => $userSport->id,
                'score_type' => 'vndupr_score',
            ],
            [
                'score_value' => $finalPickiScore,
                'updated_at' => now(),
            ]
        );
    }

    private function enrichWithScoreData(ScoreVerificationRequest $request, bool $isDetail = false): array
    {
        $currentScore = $this->getCurrentPickiScore($request->user_id);
        $difference = $currentScore !== null
            ? $this->calculateDifference((float) $request->submitted_score, $currentScore)
            : null;
        $threshold = $this->getThreshold();

        $data = [
            'id' => $request->id,
            'request_number' => $request->request_number,
            'score_type' => $request->score_type,
            'submitted_score' => $request->submitted_score,
            'picki_score' => $request->picki_score !== null ? (float) $request->picki_score : null,
            'current_picki_score' => $currentScore,
            'difference' => $difference,
            'threshold' => $threshold,
            'is_over_threshold' => $difference !== null ? $this->isOverThreshold($difference) : false,
            'status' => $request->status,
            'created_at' => $request->created_at,
            'is_new' => $request->created_at->diffInHours(now()) < 24,
        ];

        if ($request->relationLoaded('user')) {
            $data['user'] = [
                'id' => $request->user->id,
                'full_name' => $request->user->full_name,
                'avatar_url' => $request->user->avatar_url,
            ];
        }

        if ($isDetail || $request->relationLoaded('reviewer')) {
            $data['image_url'] = $request->image_path
                ? rtrim(config('app.url'), '/') . Storage::url($request->image_path)
                : null;
            $data['reviewed_at'] = $request->reviewed_at;
            $data['reviewer'] = $request->reviewer ? [
                'id' => $request->reviewer->id,
                'full_name' => $request->reviewer->full_name,
            ] : null;
            $data['rejection_reason'] = $request->rejection_reason;
            $data['award_anchor_badge'] = $request->award_anchor_badge;
            $data['picki_score'] = $request->picki_score !== null ? (float) $request->picki_score : null;
        }

        return $data;
    }

    private function updateUserSportScore(ScoreVerificationRequest $request, ?float $pickiScore = null): void
    {
        $userSport = UserSport::firstOrCreate(
            ['user_id' => $request->user_id, 'sport_id' => 1],
            ['tier' => null]
        );

        $scoreType = \App\Enums\ScoreType::from($request->score_type)->toDbScoreType();

        // 1. Cập nhật điểm SPCN hoặc DUPR (spcn_score hoặc dupr_score)
        DB::table('user_sport_scores')->updateOrInsert(
            [
                'user_sport_id' => $userSport->id,
                'score_type' => $scoreType,
            ],
            [
                'score_value' => $request->submitted_score,
                'updated_at' => now(),
            ]
        );

        // 2. Cập nhật điểm trình Picki (vndupr_score) nếu admin gửi lên điều chỉnh
        if ($pickiScore !== null) {
            $finalPickiScore = max(1.0, round($pickiScore, 3));
            DB::table('user_sport_scores')->updateOrInsert(
                [
                    'user_sport_id' => $userSport->id,
                    'score_type' => 'vndupr_score',
                ],
                [
                    'score_value' => $finalPickiScore,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
