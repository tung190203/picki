<?php

namespace App\Listeners;

use App\Badges\Rules\BadgeRuleInterface;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\QuickMatch;
use App\Services\BadgeService;
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

        $baseContext = $this->extractEventContext($event);

        foreach ($this->rules[$eventClass] as $ruleClass) {
            try {
                /** @var BadgeRuleInterface $rule */
                $rule = app($ruleClass);

                BadgeService::setTriggerContext(array_merge($baseContext, [
                    'rule' => $ruleClass,
                ]));

                if ($rule->condition($event)) {
                    $rule->handle($event);
                }
            } catch (\Exception $e) {
                Log::error("Huy hiệu " . $ruleClass . " của event " . $eventClass . " thất bại: " . $e->getMessage());
            } finally {
                BadgeService::clearTriggerContext();
            }
        }
    }

    /**
     * Trích xuất thông tin trận đấu / kèo đấu / giải đấu từ event để ghi log truy vết.
     */
    protected function extractEventContext($event): array
    {
        $context = [
            'event' => get_class($event),
        ];

        if ($event instanceof MatchResultConfirmed) {
            $match = $event->match;
            if ($match instanceof Matches) {
                $tournament = $match->tournamentType?->tournament ?? $match->group?->tournamentType?->tournament;
                $context += [
                    'source_type' => 'tournament_match',
                    'match_id' => $match->id,
                    'match_name' => $match->name ?? null,
                    'tournament_type_id' => $match->tournament_type_id,
                    'tournament_id' => $tournament?->id,
                    'tournament_name' => $tournament?->name,
                    'home_team_id' => $match->home_team_id,
                    'away_team_id' => $match->away_team_id,
                    'winner_team_id' => $match->winner_id,
                ];
            } elseif ($match instanceof MiniMatch) {
                $miniTournament = $match->miniTournament;
                $context += [
                    'source_type' => 'mini_match',
                    'mini_match_id' => $match->id,
                    'mini_match_name' => $match->name ?? null,
                    'mini_tournament_id' => $match->mini_tournament_id,
                    'mini_tournament_name' => $miniTournament?->name,
                    'team1_id' => $match->team1_id,
                    'team2_id' => $match->team2_id,
                    'team_win_id' => $match->team_win_id,
                    'participant1_id' => $match->participant1_id,
                    'participant2_id' => $match->participant2_id,
                    'participant_win_id' => $match->participant_win_id,
                ];
            } elseif ($match instanceof QuickMatch) {
                $context += [
                    'source_type' => 'quick_match',
                    'quick_match_id' => $match->id,
                    'quick_match_name' => $match->name ?? null,
                    'team_a' => $match->team_a,
                    'team_b' => $match->team_b,
                    'winner_side' => $match->winner,
                ];
            }
        } elseif ($event instanceof TournamentCompleted) {
            $tournament = $event->tournament;
            $context += [
                'source_type' => 'tournament',
                'tournament_id' => $tournament?->id,
                'tournament_name' => $tournament?->name,
            ];
        } elseif ($event instanceof MiniTournamentCreated) {
            $miniTournament = $event->miniTournament;
            $context += [
                'source_type' => 'mini_tournament',
                'mini_tournament_id' => $miniTournament?->id,
                'mini_tournament_name' => $miniTournament?->name,
            ];
        } elseif ($event instanceof UserProfileCompleted) {
            $context += [
                'source_type' => 'user_profile',
                'target_user_id' => $event->user?->id,
            ];
        }

        return array_filter($context, fn ($v) => $v !== null);
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