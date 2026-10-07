<?php

namespace App\Console\Commands;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Console\Command;

class EvaluateProfileBadges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'badges:evaluate-profile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate and grant PROFILE_COMPLETED badge for users who already completed their profile.';

    /**
     * Execute the console command.
     */
    public function handle(BadgeService $badgeService)
    {
        $this->info('Starting to evaluate profile completed badges...');

        $badgeCode = 'PROFILE_COMPLETED';
        $badge = Badge::where('code', $badgeCode)->first();

        if (!$badge) {
            $this->error("Badge with code {$badgeCode} not found in database. Please create it first.");
            return;
        }

        // Get all users who have completed their profile
        // but haven't received this badge yet.
        $userIds = User::where('is_profile_completed', true)
            ->whereNotIn('id', function ($query) use ($badge) {
                $query->select('user_id')
                    ->from('user_badges')
                    ->where('badge_id', $badge->id);
            })
            ->pluck('id');

        if ($userIds->isEmpty()) {
            $this->info('No eligible users found or all eligible users already have the badge.');
            return;
        }

        $count = 0;
        foreach ($userIds as $userId) {
            $badgeService->awardBadge($userId, $badgeCode);
            $count++;
        }

        $this->info("Granted {$badgeCode} badge to {$count} users.");
    }
}
