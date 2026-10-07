<?php

namespace App\Listeners;

use App\Badges\Rules\BadgeRuleInterface;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use App\Events\TournamentCompleted;
use App\Events\MatchResultConfirmed;
use App\Events\SuperAdmin\MiniTournamentCreated;
use App\Events\UserProfileCompleted;
use App\Badges\Rules\ChampionBadgeRule;
use App\Badges\Rules\PerfectRunBadgeRule;
use App\Badges\Rules\FirstChampionBadgeRule;
use App\Badges\Rules\DarkHorseBadgeRule;
use App\Badges\Rules\TournamentParticipationBadgeRule;
use App\Badges\Rules\GiantSlayerBadgeRule;
use App\Badges\Rules\GhostBadgeRule;
use App\Badges\Rules\MatchParticipationBadgeRule;
use App\Badges\Rules\WinStreakBadgeRule;
use App\Badges\Rules\RatingMilestoneBadgeRule;
use App\Badges\Rules\ExplorerBadgeRule;
use App\Badges\Rules\VietnamExplorerBadgeRule;
use App\Badges\Rules\TouringPlayerBadgeRule;
use App\Badges\Rules\HostBadgeRule;
use App\Badges\Rules\OrganizerBadgeRule;
use App\Badges\Rules\ProfileCompletedBadgeRule;

class BadgeAchievementSubscriber
{
    /**
     * @var array<string, array<string>> Mapping of event classes to their badge rule classes.
     */
    protected array $rules = [
        TournamentCompleted::class => [
            ChampionBadgeRule::class,
            PerfectRunBadgeRule::class,
            FirstChampionBadgeRule::class,
            DarkHorseBadgeRule::class,
            TournamentParticipationBadgeRule::class,
            OrganizerBadgeRule::class,
        ],
        MatchResultConfirmed::class => [
            GiantSlayerBadgeRule::class,
            GhostBadgeRule::class,
            MatchParticipationBadgeRule::class,
            WinStreakBadgeRule::class,
            RatingMilestoneBadgeRule::class,
            ExplorerBadgeRule::class,
            VietnamExplorerBadgeRule::class,
            TouringPlayerBadgeRule::class,
        ],
        MiniTournamentCreated::class => [
            HostBadgeRule::class,
        ],
        UserProfileCompleted::class => [
            ProfileCompletedBadgeRule::class,
        ],
    ];

    /**
     * Handle the events.
     */
    public function handleEvent($event): void
    {
        $eventClass = get_class($event);

        if (!isset($this->rules[$eventClass])) {
            return;
        }

        foreach ($this->rules[$eventClass] as $ruleClass) {
            try {
                /** @var BadgeRuleInterface $rule */
                $rule = app($ruleClass);

                if ($rule->condition($event)) {
                    $rule->handle($event);
                }
            } catch (\Exception $e) {
                Log::error("Huy hiệu " . $ruleClass . " của event " . $eventClass . " thất bại: " . $e->getMessage());
            }
        }
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  \Illuminate\Events\Dispatcher  $events
     * @return array
     */
    public function subscribe(Dispatcher $events): array
    {
        $listen = [];
        foreach (array_keys($this->rules) as $eventClass) {
            $listen[$eventClass] = [
                self::class . '@handleEvent',
            ];
        }

        return $listen;
    }
}