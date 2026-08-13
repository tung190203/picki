<?php

namespace App\Services\Admin;

use App\Exceptions\BusinessException;
use App\Models\ClubMember;
use App\Models\MatchHistory;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniTeamMember;
use App\Models\Participant;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserMerge;
use App\Models\UserSport;
use App\Models\VnduprHistory;
use App\Services\Admin\AuditLogService;
use App\Services\Admin\UserMerge\DuplicateMatchDetector;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserMergeService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected DuplicateMatchDetector $duplicateMatchDetector
    ) {}

    public function searchUsers(string $keyword, int $page = 1, int $limit = 20): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['sports.scores', 'clubs', 'userBadges', 'playTimes'])
            ->where('is_guest', false)
            ->where('is_banned', false);

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('id', $keyword);
            });
        }

        $query->orderBy('last_active_at', 'desc');

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    public function preview(int $userAId, int $userBId): array
    {
        $userA = User::find($userAId);
        $userB = User::find($userBId);

        if (!$userA) {
            throw new BusinessException('Không tìm thấy user A', 404);
        }

        if (!$userB) {
            throw new BusinessException('Không tìm thấy user B', 404);
        }

        if ($userAId === $userBId) {
            throw new BusinessException('Hai user phải khác nhau', 400);
        }

        if ($userA->is_merged) {
            throw new BusinessException('User A đã được merge trước đó', 400);
        }

        if ($userB->is_merged) {
            throw new BusinessException('User B đã được merge trước đó', 400);
        }

        $duplicates = $this->duplicateMatchDetector->detect($userAId, $userBId);
        $duplicateCount = count($duplicates);

        $userAMatchCounts = $this->duplicateMatchDetector->getMatchCounts($userAId);
        $userBMatchCounts = $this->duplicateMatchDetector->getMatchCounts($userBId);

        $userARating = $this->getUserRating($userA);
        $userBRating = $this->getUserRating($userB);

        $estimatedRating = $this->estimateMergedRating($userAId, $userBId);

        return [
            'user_a' => [
                'id' => $userA->id,
                'full_name' => $userA->full_name,
                'phone' => $userA->phone,
                'email' => $userA->email,
                'avatar_url' => $userA->avatar_url,
                'vndupr_score' => $userARating,
                'total_match' => $userAMatchCounts['total'],
                'played_matches' => $userAMatchCounts['total'],
                'match_breakdown' => $userAMatchCounts,
            ],
            'user_b' => [
                'id' => $userB->id,
                'full_name' => $userB->full_name,
                'phone' => $userB->phone,
                'email' => $userB->email,
                'avatar_url' => $userB->avatar_url,
                'vndupr_score' => $userBRating,
                'total_match' => $userBMatchCounts['total'],
                'played_matches' => $userBMatchCounts['total'],
                'match_breakdown' => $userBMatchCounts,
            ],
            'duplicate_matches' => $duplicates,
            'duplicate_count' => $duplicateCount,
            'match_summary' => [
                'user_a_matches' => $userAMatchCounts['total'],
                'user_b_matches' => $userBMatchCounts['total'],
                'duplicate_matches' => $duplicateCount,
                'merged_matches' => $userAMatchCounts['total'] + $userBMatchCounts['total'] - $duplicateCount,
            ],
            'estimated_rating' => $estimatedRating,
            'can_continue' => $duplicateCount === 0,
        ];
    }

    public function previewFinal(
        int $userAId,
        int $userBId,
        int $survivorUserId,
        bool $duplicateOverride = false
    ): array {
        $userA = User::find($userAId);
        $userB = User::find($userBId);

        if (!$userA || !$userB) {
            throw new BusinessException('Không tìm thấy một trong hai user', 404);
        }

        if ($userAId === $userBId) {
            throw new BusinessException('Hai user phải khác nhau', 400);
        }

        if (!in_array($survivorUserId, [$userAId, $userBId])) {
            throw new BusinessException('Survivor phải là một trong hai user được chọn', 400);
        }

        $mergedUserId = $survivorUserId === $userAId ? $userBId : $userAId;
        $survivor = $survivorUserId === $userAId ? $userA : $userB;
        $mergedUser = $survivorUserId === $userAId ? $userB : $userA;

        if ($mergedUser->is_merged) {
            throw new BusinessException('Một trong hai user đã được merge trước đó', 400);
        }

        $duplicates = $this->duplicateMatchDetector->detect($userAId, $userBId);
        $duplicateCount = count($duplicates);

        $survivorRating = $this->getUserRating($survivor);
        $mergedRating = $this->getUserRating($mergedUser);

        if ($duplicateCount > 0 && !$duplicateOverride) {
            throw new BusinessException('DUPLICATE_OVERRIDE_REQUIRED: Có trận đấu trùng lặp, cần xác nhận override', 400);
        }

        $survivorMatchCounts = $this->duplicateMatchDetector->getMatchCounts($survivorUserId);
        $mergedMatchCounts = $this->duplicateMatchDetector->getMatchCounts($mergedUserId);
        $finalMatchCount = $survivorMatchCounts['total'] + $mergedMatchCounts['total'] - $duplicateCount;

        $estimatedRating = $this->estimateMergedRating($survivorUserId, $mergedUserId);

        $selectedInfo = $this->buildSelectedInfo($survivor);

        return [
            'survivor' => [
                'id' => $survivor->id,
                'full_name' => $survivor->full_name,
                'phone' => $survivor->phone,
                'email' => $survivor->email,
                'avatar_url' => $survivor->avatar_url,
                'vndupr_score' => $survivorRating,
                'total_match' => $survivorMatchCounts['total'],
            ],
            'merged_user' => [
                'id' => $mergedUser->id,
                'full_name' => $mergedUser->full_name,
                'phone' => $mergedUser->phone,
                'email' => $mergedUser->email,
                'avatar_url' => $mergedUser->avatar_url,
                'vndupr_score' => $mergedRating,
                'total_match' => $mergedMatchCounts['total'],
            ],
            'selected_info' => $selectedInfo,
            'match_summary' => [
                'survivor_matches' => $survivorMatchCounts['total'],
                'merged_user_matches' => $mergedMatchCounts['total'],
                'duplicate_matches' => $duplicateCount,
                'final_matches' => $finalMatchCount,
            ],
            'duplicate_matches' => $duplicates,
            'estimated_rating' => $estimatedRating,
            'login_warning' => true,
        ];
    }

    public function execute(
        int $userAId,
        int $userBId,
        int $survivorUserId,
        bool $duplicateOverride,
        string $confirmationName,
        User $admin
    ): UserMerge {
        if ($userAId === $userBId) {
            throw new BusinessException('Hai user phải khác nhau', 400);
        }

        if (!in_array($survivorUserId, [$userAId, $userBId])) {
            throw new BusinessException('Survivor phải là một trong hai user được chọn', 400);
        }

        if ($confirmationName !== User::find($survivorUserId)->full_name) {
            throw new BusinessException('Tên xác nhận không khớp với tên survivor', 400);
        }

        return DB::transaction(function () use ($userAId, $userBId, $survivorUserId, $duplicateOverride, $confirmationName, $admin) {
            $userA = User::where('id', $userAId)->lockForUpdate()->first();
            $userB = User::where('id', $userBId)->lockForUpdate()->first();

            if (!$userA || !$userB) {
                throw new BusinessException('Không tìm thấy một trong hai user', 404);
            }

            if ($userA->is_merged || $userB->is_merged) {
                throw new BusinessException('Một trong hai user đã được merge trước đó', 400);
            }

            $mergedUserId = $survivorUserId === $userAId ? $userBId : $userAId;
            $survivor = $survivorUserId === $userAId ? $userA : $userB;
            $mergedUser = $survivorUserId === $userAId ? $userB : $userA;

            $existingMerge = UserMerge::where(function ($q) use ($mergedUserId) {
                $q->where('merged_user_id', $mergedUserId)
                    ->orWhere('survivor_user_id', $mergedUserId);
            })
                ->where('status', 'completed')
                ->first();

            if ($existingMerge) {
                throw new BusinessException('User này đã được merge trước đó', 400);
            }

            $duplicates = $this->duplicateMatchDetector->detect($userAId, $userBId);
            $duplicateCount = count($duplicates);

            if ($duplicateCount > 0 && !$duplicateOverride) {
                throw new BusinessException('DUPLICATE_OVERRIDE_REQUIRED: Có trận đấu trùng lặp, cần xác nhận override', 400);
            }

            $survivorMatchCounts = $this->duplicateMatchDetector->getMatchCounts($survivorUserId);
            $mergedMatchCounts = $this->duplicateMatchDetector->getMatchCounts($mergedUserId);
            $finalMatchCount = $survivorMatchCounts['total'] + $mergedMatchCounts['total'] - $duplicateCount;

            $estimatedRating = $this->estimateMergedRating($survivorUserId, $mergedUserId);
            $selectedInfo = $this->buildSelectedInfo($survivor);

            $userMerge = UserMerge::create([
                'survivor_user_id' => $survivorUserId,
                'merged_user_id' => $mergedUserId,
                'performed_by' => $admin->id,
                'duplicate_count' => $duplicateCount,
                'duplicate_override' => $duplicateOverride,
                'matches_before_survivor' => $survivorMatchCounts['total'],
                'matches_before_merged' => $mergedMatchCounts['total'],
                'duplicate_matches_removed' => $duplicateCount,
                'matches_after_merge' => $finalMatchCount,
                'estimated_rating' => $estimatedRating,
                'selected_info_source' => $selectedInfo,
                'confirmation_name' => $confirmationName,
                'status' => 'pending',
                'metadata' => [
                    'survivor_snapshot' => [
                        'full_name' => $survivor->full_name,
                        'phone' => $survivor->phone,
                        'email' => $survivor->email,
                    ],
                    'merged_snapshot' => [
                        'full_name' => $mergedUser->full_name,
                        'phone' => $mergedUser->phone,
                        'email' => $mergedUser->email,
                    ],
                    'duplicate_matches' => $duplicates,
                ],
            ]);

            $this->transferUserReferences($mergedUserId, $survivorUserId);

            $this->removeDuplicateRecords($mergedUserId, $survivorUserId);

            $mergedUser->update([
                'is_merged' => true,
                'merged_into_user_id' => $survivorUserId,
            ]);

            $this->invalidateUserCaches($survivorUserId);

            $userMerge->update([
                'status' => 'completed',
                'completed_at' => now(),
                'final_rating' => $this->getUserRating($survivor),
            ]);

            $this->auditLogService->log(
                $admin,
                'merge_user',
                User::class,
                $mergedUserId,
                ['is_merged' => false],
                ['is_merged' => true, 'merged_into_user_id' => $survivorUserId],
                "Merged user {$mergedUserId} into survivor {$survivorUserId}"
            );

            return $userMerge;
        });
    }

    protected function transferUserReferences(int $fromUserId, int $toUserId): void
    {
        TeamMember::where('user_id', $fromUserId)
            ->whereNotIn('team_id', function ($query) use ($toUserId) {
                $query->select('team_id')
                    ->from('team_members')
                    ->where('user_id', $toUserId);
            })
            ->update(['user_id' => $toUserId]);

        MiniTeamMember::where('user_id', $fromUserId)
            ->whereNotIn('mini_team_id', function ($query) use ($toUserId) {
                $query->select('mini_team_id')
                    ->from('mini_team_members')
                    ->where('user_id', $toUserId);
            })
            ->update(['user_id' => $toUserId]);

        Participant::where('user_id', $fromUserId)
            ->whereNotIn('tournament_id', function ($query) use ($toUserId) {
                $query->select('tournament_id')
                    ->from('participants')
                    ->where('user_id', $toUserId);
            })
            ->update(['user_id' => $toUserId]);

        MiniParticipant::where('user_id', $fromUserId)
            ->whereNotIn('mini_tournament_id', function ($query) use ($toUserId) {
                $query->select('mini_tournament_id')
                    ->from('mini_participants')
                    ->where('user_id', $toUserId);
            })
            ->update(['user_id' => $toUserId]);

        MatchHistory::where('user_id', $fromUserId)
            ->whereNotIn('quick_match_id', function ($query) use ($toUserId) {
                $query->select('quick_match_id')
                    ->from('match_histories')
                    ->where('user_id', $toUserId);
            })
            ->update(['user_id' => $toUserId]);
    }

    protected function removeDuplicateRecords(int $mergedUserId, int $survivorUserId): void
    {
        UserSport::where('user_id', $mergedUserId)
            ->whereIn('sport_id', function ($query) use ($survivorUserId) {
                $query->select('sport_id')
                    ->from('user_sports')
                    ->where('user_id', $survivorUserId);
            })
            ->delete();

        UserBadge::where('user_id', $mergedUserId)
            ->whereIn('badge_id', function ($query) use ($survivorUserId) {
                $query->select('badge_id')
                    ->from('user_badges')
                    ->where('user_id', $survivorUserId);
            })
            ->delete();

        ClubMember::where('user_id', $mergedUserId)
            ->whereIn('club_id', function ($query) use ($survivorUserId) {
                $query->select('club_id')
                    ->from('club_members')
                    ->where('user_id', $survivorUserId);
            })
            ->delete();
    }

    protected function getUserRating(User $user): ?float
    {
        $sport = $user->sports()->first();
        if (!$sport) {
            return null;
        }

        $score = $sport->scores()
            ->where('score_type', 'vndupr_score')
            ->first();

        return $score ? (float) $score->score_value : null;
    }

    protected function estimateMergedRating(int $survivorUserId, int $mergedUserId): ?float
    {
        $survivorRating = $this->getUserRating(User::find($survivorUserId));
        $mergedRating = $this->getUserRating(User::find($mergedUserId));

        if ($survivorRating === null && $mergedRating === null) {
            return null;
        }

        if ($survivorRating === null) {
            return $mergedRating;
        }

        if ($mergedRating === null) {
            return $survivorRating;
        }

        $historyCount = VnduprHistory::where('user_id', $survivorUserId)->count();
        $mergedHistoryCount = VnduprHistory::where('user_id', $mergedUserId)->count();
        $totalCount = $historyCount + $mergedHistoryCount;

        if ($totalCount === 0) {
            return ($survivorRating + $mergedRating) / 2;
        }

        $survivorWeight = $historyCount / $totalCount;
        $mergedWeight = $mergedHistoryCount / $totalCount;

        return round($survivorRating * $survivorWeight + $mergedRating * $mergedWeight, 3);
    }

    protected function buildSelectedInfo(User $survivor): array
    {
        return [
            'name' => $survivor->full_name,
            'phone' => $survivor->phone,
            'email' => $survivor->email,
            'avatar_url' => $survivor->avatar_url,
        ];
    }

    protected function invalidateUserCaches(int $userId): void
    {
        Cache::forget("user:{$userId}:me_extras");
        Cache::forget("user:{$userId}:sport_stats");
    }

    public function getMergeHistory(int $page = 1, int $limit = 20, ?array $filters = []): LengthAwarePaginator
    {
        $query = UserMerge::with([
            'survivor' => function ($q) {
                $q->with(['sports.sport', 'sports.scores', 'deviceTokens']);
            },
            'mergedUser' => function ($q) {
                $q->with(['sports.sport', 'sports.scores', 'deviceTokens']);
            },
            'performer:id,full_name',
        ])->orderBy('created_at', 'desc');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('survivor', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%");
            })
                ->orWhereHas('mergedUser', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%");
                });
        }

        if (!empty($filters['performed_by'])) {
            $query->where('performed_by', $filters['performed_by']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    public function getMergeDetail(int $mergeId): UserMerge
    {
        return UserMerge::with(['survivor', 'mergedUser', 'performer'])
            ->findOrFail($mergeId);
    }
}
