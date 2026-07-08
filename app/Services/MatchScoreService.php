<?php

namespace App\Services;

use App\Events\MatchScoreUpdated;
use App\Events\MatchScorePublicUpdated;
use App\Models\MatchResult;
use App\Models\Matches;
use Illuminate\Support\Facades\DB;

class MatchScoreService
{
    public function startMatch(int $matchId, int $servingTeamId, int $userId, ?string $startedAt = null): array
    {
        return DB::transaction(function () use ($matchId, $servingTeamId, $userId, $startedAt) {
            $match = Matches::lockForUpdate()->find($matchId);
            if (!$match) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
            }

            $resolvedStartedAt = $startedAt
                ? \Carbon\Carbon::createFromFormat('Y/m/d H:i:s', $startedAt, config('app.timezone'))
                : now();

            $match->update([
                'live_status' => 'playing',
                'started_at' => $resolvedStartedAt,
                'current_set' => 1,
                'serving_team_id' => $servingTeamId,
                'team1_timeout_used' => 0,
                'team2_timeout_used' => 0,
                'referee_id' => $userId,
                'match_version' => DB::raw('match_version + 1'),
            ]);

            MatchResult::updateOrCreate(
                ['match_id' => $matchId, 'team_id' => $match->home_team_id, 'set_number' => 1],
                ['team_score' => 0, 'opponent_score' => 0, 'serving_position' => 0]
            );
            MatchResult::updateOrCreate(
                ['match_id' => $matchId, 'team_id' => $match->away_team_id, 'set_number' => 1],
                ['team_score' => 0, 'opponent_score' => 0, 'serving_position' => 0]
            );

            $match->refresh();
            $match->load([
                'homeTeam',
                'awayTeam',
                'results' => fn ($q) => $q->where('set_number', $match->current_set),
                'referee',
            ]);

            event(new MatchScoreUpdated($match, $match->results));
            event(new MatchScorePublicUpdated($match, $match->results));

            return $this->formatMatchResponse($match);
        });
    }

    public function updateState(int $matchId, array $data, int $userId): array
    {
        return DB::transaction(function () use ($matchId, $data, $userId) {
            $match = Matches::lockForUpdate()->find($matchId);
            if (!$match) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
            }

            $setNumber = $data['set_number'];
            $homeTeamId = $match->home_team_id;
            $awayTeamId = $match->away_team_id;

            MatchResult::updateOrCreate(
                ['match_id' => $matchId, 'team_id' => $homeTeamId, 'set_number' => $setNumber],
                [
                    'team_score' => $data['team1_score'],
                    'opponent_score' => $data['team2_score'],
                    'serving_position' => $data['serving_position'] ?? 0,
                ]
            );
            MatchResult::updateOrCreate(
                ['match_id' => $matchId, 'team_id' => $awayTeamId, 'set_number' => $setNumber],
                [
                    'team_score' => $data['team2_score'],
                    'opponent_score' => $data['team1_score'],
                    'serving_position' => $data['serving_position'] ?? 0,
                ]
            );

            $updateFields = [
                'current_set' => $setNumber,
                'serving_team_id' => $data['serving_team_id'],
                'live_status' => $data['live_status'] ?? 'playing',
                'referee_id' => $userId,
                'match_version' => DB::raw('match_version + 1'),
            ];

            if (isset($data['team1_timeout_used'])) {
                $updateFields['team1_timeout_used'] = $data['team1_timeout_used'];
            }
            if (isset($data['team2_timeout_used'])) {
                $updateFields['team2_timeout_used'] = $data['team2_timeout_used'];
            }

            $match->update($updateFields);

            $match->refresh();
            $match->load([
                'homeTeam',
                'awayTeam',
                'results' => fn ($q) => $q->where('set_number', $setNumber),
                'referee',
            ]);

            event(new MatchScoreUpdated($match, $match->results));
            event(new MatchScorePublicUpdated($match, $match->results));

            return [
                'match_id' => $matchId,
                'current_set' => $setNumber,
                'version' => $match->match_version,
                'event_id' => $match->id,
                'updated_at' => $match->updated_at?->toIso8601String(),
                'referee_name' => $match->referee?->full_name,
            ];
        });
    }

    public function getCurrentState(int $matchId): array
    {
        $match = Matches::with([
            'homeTeam.members.sports.scores',
            'homeTeam.members.sports.sport',
            'awayTeam.members.sports.scores',
            'awayTeam.members.sports.sport',
            'results' => fn ($q) => $q->orderBy('set_number'),
            'group.tournamentType.tournament.competitionLocation',
            'referee',
        ])->findOrFail($matchId);

        return $this->formatMatchResponse($match);
    }

    protected function formatMatchResponse(Matches $match): array
    {
        $currentSetResults = $match->results->where('team_id', $match->home_team_id);
        $tournament = $match->group?->tournamentType?->tournament;
        $tournamentType = $match->group?->tournamentType;
        $location = $tournament?->competitionLocation;
        $rawMatchRules = $tournamentType?->match_rules ?? [];
        // match_rules in DB may be stored as [{...}] (array of one) or {...} (associative).
        // Normalize to a single associative array so the FE gets a stable shape.
        if (is_array($rawMatchRules) && count($rawMatchRules) > 0 && array_is_list($rawMatchRules)) {
            $matchRules = $rawMatchRules[0] ?? [];
        } else {
            $matchRules = is_array($rawMatchRules) ? $rawMatchRules : [];
        }

        $elapsedSeconds = null;
        if ($match->started_at) {
            $elapsedSeconds = max(0, now()->diffInSeconds($match->started_at));
        }

        $formatTeam = function ($team) {
            if (!$team) return null;
            $members = $team->members->map(function ($user) {
                $vnduprScore = $user->sports
                    ->where('sport_id', 1)
                    ->flatMap(fn ($s) => $s->scores ?? [])
                    ->first(fn ($score) => $score->score_type === 'vndupr_score')
                    ?->score_value;
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'avatar' => $user->avatar_url
                        ? (str_starts_with($user->avatar_url, 'http')
                            ? $user->avatar_url
                            : config('app.frontend_url') . '/storage/' . $user->avatar_url)
                        : null,
                    'vndupr' => $vnduprScore,
                ];
            })->values()->toArray();
            return [
                'id' => $team->id,
                'name' => $team->name,
                'avatar' => $team->avatar,
                'members' => $members,
            ];
        };

        return [
            'match_id' => $match->id,
            'live_status' => $match->live_status,
            'started_at' => $match->started_at?->toIso8601String(),
            'scheduled_at' => $match->scheduled_at?->toIso8601String(),
            'current_set' => $match->current_set,
            'serving_team_id' => $match->serving_team_id,
            'team1_timeout_used' => $match->team1_timeout_used,
            'team2_timeout_used' => $match->team2_timeout_used,
            'version' => $match->match_version,
            'elapsed_seconds' => $elapsedSeconds,
            'referee_name' => $match->referee?->full_name,
            'side_switch_interval' => $matchRules['side_switch_interval'] ?? null,
            'team1' => $formatTeam($match->homeTeam),
            'team2' => $formatTeam($match->awayTeam),
            'sets' => $currentSetResults->map(fn ($r) => [
                'set_number' => $r->set_number,
                'team1_score' => $r->team_score,
                'team2_score' => $r->opponent_score,
                'serving_position' => $r->serving_position,
            ])->values()->toArray(),
            'tournament' => $tournament ? [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'poster_url' => $tournament->poster_url,
                'start_date' => $tournament->start_date,
                'end_date' => $tournament->end_date,
                'location_name' => $location?->name,
                'location_address' => $location?->address,
            ] : null,
            'rules' => $tournamentType?->rules,
            'match_rules' => $matchRules,
        ];
    }
}
