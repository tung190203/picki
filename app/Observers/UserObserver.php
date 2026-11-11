<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserSport;
use App\Models\UserSportScore;
use App\Services\BadgeService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public const AUTO_VERIFY_THRESHOLD = 10;

    /**
     * Handle the User "created" event.
     * Tự động khởi tạo môn thể thao mặc định (Pickleball: sport_id = 1) và điểm trình sàn (vndupr_score = 1.0)
     * để tránh user đăng ký mới có điểm trình = 0.
     */
    public function created(User $user): void
    {
        if ($user->is_guest) {
            return;
        }

        try {
            $userSport = UserSport::firstOrCreate(
                ['user_id' => $user->id, 'sport_id' => 1],
                ['tier' => null]
            );

            UserSportScore::firstOrCreate(
                [
                    'user_sport_id' => $userSport->id,
                    'score_type' => 'vndupr_score',
                ],
                [
                    'score_value' => 1.0,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("Failed to initialize default sport score for user {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the User "updated" event.
     * Auto-verify user when total_matches_has_anchor crosses the threshold (>= 10).
     * Awards VERIFIED badge via BadgeService.
     */
    public function updated(User $user): void
    {
        // Clear role cache when role or is_super_admin changes
        if ($user->wasChanged('role') || $user->wasChanged('is_super_admin')) {
            User::clearRoleCache($user->id);
        }

        // Auto-verify logic using BadgeService
        if (!$user->wasChanged('total_matches_has_anchor')) {
            return;
        }

        // Check if user already has any badge using BadgeService
        if (app(BadgeService::class)->has_any_badge($user->id)) {
            return;
        }

        if ($user->is_guest) {
            return;
        }

        if ((int) $user->total_matches_has_anchor >= self::AUTO_VERIFY_THRESHOLD) {
            app(BadgeService::class)->awardBadge($user->id, 'VERIFIED');
        }
    }
}
