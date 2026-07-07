<?php

namespace App\Services;

use App\Events\MatchScoreUpdated;
use App\Events\MatchScorePublicUpdated;
use App\Exceptions\VersionConflictException;
use App\Models\MatchResult;
use App\Models\Matches;
use Illuminate\Support\Facades\DB;

class MatchScoreService
{
    public function startMatch(int $matchId, int $servingTeamId, int $version, int $userId): array
    {
        return DB::transaction(function () use ($matchId, $servingTeamId, $version) {
            $match = Matches::lockForUpdate()->find($matchId);
            if (!$match || $match->match_version !== $version) {
                throw new VersionConflictException($match);
            }

            $match->update([
                'live_status' => 'playing',
                'started_at' => now(),
                'current_set' => 1,
                'serving_team_id' => $servingTeamId,
                'team1_timeout_used' => 0,
                'team2_timeout_used' => 0,
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
            $match->load(['homeTeam', 'awayTeam', 'results' => fn ($q) => $q->where('set_number', $match->current_set)]);

            event(new MatchScoreUpdated($match, $match->results));
            event(new MatchScorePublicUpdated($match, $match->results));

            return $this->formatMatchResponse($match);
        });
    }

    public function updateState(int $matchId, array $data, int $userId): array
    {
        return DB::transaction(function () use ($matchId, $data) {
            $match = Matches::lockForUpdate()->find($matchId);
            if (!$match || $match->match_version !== $data['version']) {
                throw new VersionConflictException($match);
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
            $match->load(['homeTeam', 'awayTeam', 'results' => fn ($q) => $q->where('set_number', $setNumber)]);

            event(new MatchScoreUpdated($match, $match->results));
            event(new MatchScorePublicUpdated($match, $match->results));

            return [
                'match_id' => $matchId,
                'current_set' => $setNumber,
                'version' => $match->match_version,
                'event_id' => $match->id,
                'updated_at' => $match->updated_at?->toIso8601String(),
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
        ])->findOrFail($matchId);

        return $this->formatMatchResponse($match);
    }

    protected function formatMatchResponse(Matches $match): array
    {
        $currentSetResults = $match->results->where('team_id', $match->home_team_id);
        $tournament = $match->group?->tournamentType?->tournament;
        $tournamentType = $match->group?->tournamentType;
        $location = $tournament?->competitionLocation;

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
                    'name' => $user->name,
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
            'match_rules' => $tournamentType?->match_rules,
        ];
    }
}
