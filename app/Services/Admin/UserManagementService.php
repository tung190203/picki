<?php

namespace App\Services\Admin;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\VnduprHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserManagementService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function search(int $page, int $limit, ?string $keyword, ?string $status): LengthAwarePaginator
    {
        $query = User::query()
            ->with('sports.sport', 'sports.scores')
            ->select([
                'id',
                'full_name',
                'avatar_url',
                'location_id',
                'trust_score',
                'total_matches',
                'is_banned',
                'is_verified',
                'is_anchor',
                'last_login',
                'last_active_at',
                'created_at',
                'is_guest',
            ])
            ->when($keyword, fn($q) => $q->keyword($keyword))
            ->when($status === 'banned', fn($q) => $q->banned())
            ->when($status === 'active', fn($q) => $q->notBanned())
            ->when($status === 'verified', fn($q) => $q->where('is_verified', true))
            ->where('is_guest', false)
            ->orderByRaw("CASE WHEN last_active_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 1 ELSE 0 END DESC")
            ->orderByDesc('last_active_at');

        $paginated = $query->paginate($limit, ['*'], 'page', $page);

        return $paginated->setCollection(
            collect(UserResource::collection($paginated->getCollection())->resolve())
        );
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

        DB::transaction(function () use ($user, $admin, $reason, $oldScores) {
            VnduprHistory::where('user_id', $user->id)->delete();

            $user->update([
                'total_matches' => 0,
            ]);
        });

        $this->auditLogService->log(
            $admin,
            'reset_rating',
            User::class,
            $user->id,
            ['total_matches' => $oldScores ? $user->total_matches : null],
            ['total_matches' => 0],
            $reason
        );
    }

    public function verify(User $user, User $admin): void
    {
        $oldValues = ['is_verified' => $user->is_verified];

        $user->update(['is_verified' => true]);

        $this->auditLogService->log(
            $admin,
            'verify_user',
            User::class,
            $user->id,
            $oldValues,
            ['is_verified' => true]
        );
    }

    public function setAnchor(User $user, User $admin): void
    {
        $oldValues = ['is_anchor' => $user->is_anchor];

        $user->update(['is_anchor' => !$user->is_anchor]);

        $this->auditLogService->log(
            $admin,
            'toggle_anchor',
            User::class,
            $user->id,
            $oldValues,
            ['is_anchor' => !$user->is_anchor]
        );
    }
}
