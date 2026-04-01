<?php

namespace App\Services;

use Illuminate\Support\Collection;

class TournamentService
{
    /**
     * Tính bảng xếp hạng cho một group
     */
    public static function calculateGroupStandings($groupMatches): Collection
    {
        $standings = [];
        
        foreach ($groupMatches as $match) {
            if ($match->status !== 'completed') continue;

            $homeId = $match->home_team_id;
            $awayId = $match->away_team_id;

            // Khởi tạo standings cho home team
            if (!isset($standings[$homeId])) {
                $standings[$homeId] = [
                    'team' => self::formatTeam($match->homeTeam),
                    'played' => 0,
                    'won' => 0,
                    'draw' => 0,
                    'lost' => 0,
                    'sets_won' => 0,
                    'sets_lost' => 0,
                    'points_for' => 0,
                    'points_against' => 0,
                    'set_difference' => 0,
                    'points' => 0,
                ];
            }

            // Khởi tạo standings cho away team
            if (!isset($standings[$awayId])) {
                $standings[$awayId] = [
                    'team' => self::formatTeam($match->awayTeam),
                    'played' => 0,
                    'won' => 0,
                    'draw' => 0,
                    'lost' => 0,
                    'sets_won' => 0,
                    'sets_lost' => 0,
                    'points_for' => 0,
                    'points_against' => 0,
                    'set_difference' => 0,
                    'points' => 0,
                ];
            }

            // Cập nhật số trận đã đấu
            $standings[$homeId]['played']++;
            $standings[$awayId]['played']++;

            // Tính số set thắng cho mỗi đội
            $homeSetsWon = $match->results->where('team_id', $homeId)->where('won_match', true)->count();
            $awaySetsWon = $match->results->where('team_id', $awayId)->where('won_match', true)->count();

            $standings[$homeId]['sets_won'] += $homeSetsWon;
            $standings[$homeId]['sets_lost'] += $awaySetsWon;
            $standings[$awayId]['sets_won'] += $awaySetsWon;
            $standings[$awayId]['sets_lost'] += $homeSetsWon;

            // Tính tổng điểm số (score)
            $homePoints = $match->results->where('team_id', $homeId)->sum('score');
            $awayPoints = $match->results->where('team_id', $awayId)->sum('score');

            $standings[$homeId]['points_for'] += $homePoints;
            $standings[$homeId]['points_against'] += $awayPoints;
            $standings[$awayId]['points_for'] += $awayPoints;
            $standings[$awayId]['points_against'] += $homePoints;

            // Tính điểm xếp hạng dựa trên winner_id
            if ($match->winner_id == $homeId) {
                $standings[$homeId]['won']++;
                $standings[$homeId]['points'] += 3;
                $standings[$awayId]['lost']++;
            } elseif ($match->winner_id == $awayId) {
                $standings[$awayId]['won']++;
                $standings[$awayId]['points'] += 3;
                $standings[$homeId]['lost']++;
            } else {
                // Trường hợp hòa (nếu có)
                $standings[$homeId]['draw']++;
                $standings[$awayId]['draw']++;
                $standings[$homeId]['points']++;
                $standings[$awayId]['points']++;
            }
        }

        // Tính set difference và sắp xếp
        $standings = collect($standings)->map(function ($team) {
            $team['set_difference'] = $team['sets_won'] - $team['sets_lost'];
            return $team;
        })->sortByDesc('points')
          ->sortByDesc('set_difference')
          ->sortByDesc('sets_won')
          ->values();

        // Thêm rank
        $rank = 1;
        return $standings->map(function ($team) use (&$rank) {
            $team['rank'] = $rank++;
            return $team;
        });
    }
    
    /**
     * Format team data
     */
    /**
     * Format team data cho response
     * @param mixed $team
     * @param string|null $placeholderText
     * @param int|null $tournamentId Nếu truyền vào, sẽ fetch tournamentParticipant cho mỗi member
     */
    public static function formatTeam($team, ?string $placeholderText = null, ?int $tournamentId = null): ?array
    {
        if (!$team) {
            return [
                'id' => null,
                'name' => $placeholderText ?? 'TBD',
                'team_avatar' => null,
                'members' => [],
            ];
        }

        $members = $team->members->map(function ($user) use ($tournamentId) {
            $data = [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar ?? null,
            ];

            if ($tournamentId) {
                $participant = \App\Models\Participant::where('tournament_id', $tournamentId)
                    ->where('user_id', $user->id)
                    ->with('guarantor')
                    ->first();
                if ($participant) {
                    $data['tournament_participant'] = new \App\Http\Resources\ParticipantResource($participant);
                }
            }

            return $data;
        });

        return [
            'id' => $team->id,
            'name' => $team->name,
            'team_avatar' => $team->avatar,
            'members' => $members,
        ];
    }
}