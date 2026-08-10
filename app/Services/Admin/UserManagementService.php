<?php

namespace App\Services\Admin;

use App\Http\Resources\UserResource;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\User;
use App\Models\VnduprHistory;
use App\Services\BadgeService;
use App\Enums\BadgeType;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserManagementService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function search(int $page, int $limit, ?string $keyword, ?string $status): LengthAwarePaginator
    {
        $query = User::query()
            ->with([
                'sports.sport',
                'sports.scores',
                'deviceTokens',
            ])
            ->select([
                'id',
                'full_name',
                'avatar_url',
                'location_id',
                'trust_score',
                'total_matches_has_anchor',
                'is_banned',
                'is_verified',
                'is_anchor',
                'last_login',
                'last_active_at',
                'created_at',
                'is_guest',
            ])
            ->where('is_guest', false);

        if ($status === 'banned') {
            $query->where('is_banned', true);
        } elseif ($status === 'active') {
            $query->where('is_banned', false);
        } elseif ($status === 'verified') {
            $query->whereHas('userBadges');
        }

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        $query->orderByRaw(
            "IF(last_active_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE), 0, 1), last_active_at DESC"
        );

        $paginated = $query->paginate($limit, ['*'], 'page', $page);
        $items = $paginated->getCollection();

        if ($items->isNotEmpty()) {
            $this->preloadUserListData($items);
        }

        return $paginated->setCollection(
            collect(UserResource::collection($items)->resolve())
        );
    }

    /**
     * Preload per-user data so UserResource::toArray() does not trigger N+1 queries.
     * Loads in bulk: badges, vn_rank + weekly_change, advanced mini tournament flag,
     * and the latest SPCN/DUPR score verification requests.
     */
    protected function preloadUserListData($users): void
    {
        $userIds = $users->pluck('id')->all();
        if (empty($userIds)) {
            return;
        }

        $sportId = 1; // pickleball default — same as UserResource

        // 1) Badges (primary + list)
        $badges = app(BadgeService::class)->getBatchUserBadges($userIds);
        request()->attributes->set('batch_badges', $badges);

        // 2) VN rank + weekly_change in a single batch query each
        $this->preloadRanks($users, $userIds, $sportId);

        // 3) hasAdvancedMiniTournament: single batched query for the whole page
        $this->preloadAdvancedMiniTournamentFlag($users, $userIds);

        // 4) Latest SPCN + DUPR score verification requests (single query)
        $this->preloadScoreVerifications($users, $userIds);
    }

    protected function preloadRanks($users, array $userIds, int $sportId): void
    {
        $ranks = User::getBatchVNRanks($userIds, $sportId);
        $weeklyChanges = User::getBatchWeeklyChanges($userIds, $sportId);

        foreach ($users as $user) {
            $user->setAttribute('vn_rank', $ranks[$user->id] ?? null);
            $user->setAttribute('weekly_change', $weeklyChanges[$user->id] ?? null);
        }
    }

    protected function preloadAdvancedMiniTournamentFlag($users, array $userIds): void
    {
        $counts = DB::table('mini_tournament_staff as mts')
            ->join('mini_tournaments as mnt', 'mnt.id', '=', 'mts.mini_tournament_id')
            ->join('mini_participants as mp', 'mp.mini_tournament_id', '=', 'mnt.id')
            ->whereIn('mts.user_id', $userIds)
            ->where('mts.role', MiniTournamentStaff::ROLE_ORGANIZER)
            ->where('mnt.status', '!=', MiniTournament::STATUS_DRAFT)
            ->where('mnt.status', '!=', MiniTournament::STATUS_CANCELLED)
            ->where('mp.is_confirmed', 1)
            ->select('mts.user_id', DB::raw('COUNT(DISTINCT mnt.id) as cnt'))
            ->groupBy('mts.user_id')
            ->get();

        $countMap = [];
        foreach ($counts as $row) {
            $countMap[$row->user_id] = (int) $row->cnt;
        }

        foreach ($users as $user) {
            $user->setAttribute(
                'has_advanced_mini_tournament',
                ($countMap[$user->id] ?? 0) >= 3
            );
        }
    }

    protected function preloadScoreVerifications($users, array $userIds): void
    {
        // Latest SPCN + DUPR request per user
        $latestRequests = DB::table('score_verification_requests as svr')
            ->whereIn('svr.user_id', $userIds)
            ->whereIn('svr.score_type', ['SPCN', 'DUPR'])
            ->whereIn('svr.id', function ($q) use ($userIds) {
                $q->select(DB::raw('MAX(id)'))
                    ->from('score_verification_requests')
                    ->whereIn('user_id', $userIds)
                    ->whereIn('score_type', ['SPCN', 'DUPR'])
                    ->groupBy('user_id', 'score_type');
            })
            ->select('svr.id', 'svr.user_id', 'svr.score_type', 'svr.request_number',
                'svr.image_path', 'svr.submitted_score', 'svr.status',
                'svr.rejection_reason', 'svr.created_at')
            ->get();

        // Current vndupr score per user (for diff calculation)
        $currentScores = DB::table('user_sport_scores')
            ->join('user_sport', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
            ->whereIn('user_sport.user_id', $userIds)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->groupBy('user_sport.user_id')
            ->select('user_sport.user_id', DB::raw('MAX(user_sport_scores.score_value) as max_score'))
            ->pluck('max_score', 'user_id');

        $threshold = config('score_verification.max_difference', 0.5);
        $baseUrl = rtrim(config('app.url'), '/');

        $requestsByUser = [];
        foreach ($latestRequests as $row) {
            $currentScore = $currentScores[$row->user_id] ?? null;
            $difference = $currentScore !== null
                ? round(abs((float) $row->submitted_score - $currentScore), 3)
                : null;

            $requestsByUser[$row->user_id][$row->score_type] = [
                'id' => $row->id,
                'request_number' => $row->request_number,
                'image_url' => $row->image_path ? $baseUrl . Storage::url($row->image_path) : null,
                'submitted_score' => $row->submitted_score,
                'current_picki_score' => $currentScore,
                'difference' => $difference,
                'threshold' => $threshold,
                'is_over_threshold' => $difference !== null ? $difference >= $threshold : false,
                'status' => $row->status,
                'rejection_reason' => $row->status === 'REJECTED' ? $row->rejection_reason : null,
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toISOString() : null,
                'is_new' => $row->created_at && Carbon::parse($row->created_at)->diffInHours(now()) < 24,
            ];
        }

        foreach ($users as $user) {
            $user->setAttribute('spcn_request', $requestsByUser[$user->id]['SPCN'] ?? null);
            $user->setAttribute('dupr_request', $requestsByUser[$user->id]['DUPR'] ?? null);
        }
    }

    public function getDetail(int $userId): User
    {
        $user = User::with([
            'vnduprScores' => function ($q) {
                $q->latest()->limit(20);
            },
            'sports.sport',
            'sports.scores',
        ])->findOrFail($userId);

        $user->match_history = DB::table('participants')
            ->join('matches', 'participants.match_id', 'matches.id')
            ->where('participants.user_id', $userId)
            ->select([
                'matches.id',
                'matches.name_of_match',
                'matches.status',
                'matches.created_at',
            ])
            ->orderBy('matches.created_at', 'desc')
            ->limit(20)
            ->get();

        return $user;
    }

    public function ban(User $user, ?string $reason, ?string $note, User $admin): void
    {
        $oldValues = ['is_banned' => $user->is_banned, 'banned_at' => $user->banned_at];

        $user->update([
            'is_banned' => true,
            'banned_at' => now(),
            'ban_reason' => $reason,
            'banned_by' => $admin->id,
            'ban_note' => $note,
        ]);

        $this->auditLogService->log(
            $admin,
            'ban_user',
            User::class,
            $user->id,
            $oldValues,
            ['is_banned' => true, 'ban_reason' => $reason],
            $note
        );
    }

    public function unban(User $user, User $admin): void
    {
        $oldValues = ['is_banned' => $user->is_banned, 'banned_at' => $user->banned_at];

        $user->update([
            'is_banned' => false,
            'banned_at' => null,
            'ban_reason' => null,
            'banned_by' => null,
            'ban_note' => null,
        ]);

        $this->auditLogService->log(
            $admin,
            'unban_user',
            User::class,
            $user->id,
            $oldValues,
            ['is_banned' => false]
        );
    }

    public function resetRating(User $user, string $reason, User $admin): void
    {
        $oldScores = VnduprHistory::where('user_id', $user->id)
            ->where('score_type', 'vndupr_score')
            ->orderBy('created_at', 'desc')
            ->limit(1)
            ->first();

        DB::transaction(function () use ($user, $oldScores) {
            VnduprHistory::where('user_id', $user->id)->delete();

            $user->update([
                'total_matches_has_anchor' => 0,
            ]);
        });

        $this->auditLogService->log(
            $admin,
            'reset_rating',
            User::class,
            $user->id,
            ['total_matches_has_anchor' => $oldScores ? $user->total_matches_has_anchor : null],
            ['total_matches_has_anchor' => 0],
            $reason
        );
    }

    public function verify(User $user, User $admin): void
    {
        $badgeService = app(BadgeService::class);
        $oldHasBadge = $badgeService->hasBadge($user->id, BadgeType::VERIFIED);

        $badgeService->awardBadge($user->id, BadgeType::VERIFIED, $admin->id);

        $this->auditLogService->log(
            $admin,
            'verify_user',
            User::class,
            $user->id,
            ['has_verified_badge' => $oldHasBadge],
            ['has_verified_badge' => true]
        );
    }

    public function setAnchor(User $user, User $admin): void
    {
        $badgeService = app(BadgeService::class);
        $hasAnchor = $badgeService->hasBadge($user->id, BadgeType::ANCHOR);

        if ($hasAnchor) {
            $badgeService->revokeBadge($user->id, BadgeType::ANCHOR);
        } else {
            $badgeService->awardBadge($user->id, BadgeType::ANCHOR, $admin->id);
        }

        $this->auditLogService->log(
            $admin,
            'toggle_anchor',
            User::class,
            $user->id,
            ['has_anchor_badge' => $hasAnchor],
            ['has_anchor_badge' => !$hasAnchor]
        );
    }

    public function setPicki(User $user, User $admin): void
    {
        $badgeService = app(BadgeService::class);
        $oldHasBadge = $badgeService->hasBadge($user->id, BadgeType::PICKI);

        $badgeService->grant_picki($user->id, $admin->id);

        $this->auditLogService->log(
            $admin,
            'grant_picki_badge',
            User::class,
            $user->id,
            ['has_picki_badge' => $oldHasBadge],
            ['has_picki_badge' => true]
        );
    }

    public function revokePicki(User $user, User $admin): void
    {
        $badgeService = app(BadgeService::class);
        $oldHasBadge = $badgeService->hasBadge($user->id, BadgeType::PICKI);

        $badgeService->revokeBadge($user->id, BadgeType::PICKI);

        $this->auditLogService->log(
            $admin,
            'revoke_picki_badge',
            User::class,
            $user->id,
            ['has_picki_badge' => $oldHasBadge],
            ['has_picki_badge' => false]
        );
    }
}
