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

class ScoreVerificationService
{
    public function __construct(
        private ScoreVerificationRepository $repository,
        private ImageOptimizationService $imageService,
        private AwardBadgeService $badgeService
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
        $imagePath = $this->imageService->processAndSaveImage(
            $data['image'],
            'score-verifications',
            'verification_',
            1080,
            80
        );

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
        bool $awardAnchor
    ): array {
        if (!$this->repository->isPending($request->id)) {
            throw new \Exception('Request has already been processed');
        }

        return DB::transaction(function () use ($request, $reviewerId, $awardAnchor) {
            $lockedRequest = $this->repository->getWithLockForUpdate($request->id);

            if (!$lockedRequest) {
                throw new \Exception('Request has already been processed');
            }

            $lockedRequest->update(['award_anchor_badge' => $awardAnchor]);

            $updated = $this->repository->updateStatus(
                $lockedRequest,
                ScoreVerificationStatus::APPROVED,
                $reviewerId
            );

            if ($awardAnchor) {
                $this->badgeService->awardAnchorBadge($request->user_id);
                ActivityLog::log($request->user_id, 'badge_awarded', [
                    'badge' => 'Anchor',
                    'request_id' => $request->id,
                ]);
            }

            $this->updateUserSportScore($request);

            ActivityLog::log($request->user_id, 'verification_approved', [
                'request_id' => $request->id,
                'reviewer_id' => $reviewerId,
                'award_anchor_badge' => $awardAnchor,
            ]);

            $request->user->notify(new ScoreVerificationApprovedNotification($request));

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
            $data['image_url'] = $request->image_path;
            $data['reviewed_at'] = $request->reviewed_at;
            $data['reviewer'] = $request->reviewer ? [
                'id' => $request->reviewer->id,
                'full_name' => $request->reviewer->full_name,
            ] : null;
            $data['rejection_reason'] = $request->rejection_reason;
            $data['award_anchor_badge'] = $request->award_anchor_badge;
        }

        return $data;
    }

    private function updateUserSportScore(ScoreVerificationRequest $request): void
    {
        $userSports = UserSport::where('user_id', $request->user_id)->get();

        if ($userSports->isEmpty()) {
            return;
        }

        $scoreType = \App\Enums\ScoreType::from($request->score_type)->toDbScoreType();

        foreach ($userSports as $userSport) {
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
        }
    }
}
