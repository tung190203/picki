<?php

namespace App\Http\Resources;

use App\Traits\FormatsTeamMembers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Matches;
use App\Models\TournamentType;

class MatchDetailResource extends JsonResource
{
    use FormatsTeamMembers;
    public function toArray(Request $request): array
    {
        $homeTeam = $this->homeTeam;
        $awayTeam = $this->awayTeam;
        $tournamentType = $this->tournamentType;

        // ✅ ROUND LẤY TRỰC TIẾP
        $roundNumber = $this->round;
        $roundName = "Vòng {$roundNumber}";

        $legs = collect();

        if ($tournamentType) {
            switch ($tournamentType->format) {

                /* =========================
                   ROUND ROBIN → 1 LEG
                ========================= */
                case TournamentType::FORMAT_ROUND_ROBIN:
                    $sets = $this->results->isEmpty()
                        ? (object)[]
                        : $this->results
                            ->groupBy('set_number')
                            ->mapWithKeys(fn ($setGroup, $setNumber) => [
                                'set_' . $setNumber => $setGroup->map(fn ($r) => [
                                    'team_id' => $r->team_id,
                                    'score' => $r->score,
                                    'won_match' => $r->won_match,
                                ])->values(),
                            ])
                            ->toArray();

                    $legs = collect([
                        [
                            'id' => $this->id,
                            'leg' => 1,
                            'status' => $this->status,
                            'court' => $this->court,
                            'scheduled_at' => $this->scheduled_at,
                            'sets' => $sets,
                        ],
                    ]);
                    break;

                /* =========================
                   ELIMINATION / MIXED
                ========================= */
                case TournamentType::FORMAT_ELIMINATION:
                case TournamentType::FORMAT_MIXED:
                default:
                    if ($this->home_team_id && $this->away_team_id) {

                        $legs = Matches::with('results')
                            ->where('tournament_type_id', $this->tournament_type_id)
                            ->where('round', $this->round) // ✅ QUAN TRỌNG
                            ->where(function ($q) {
                                $q->where(function ($sub) {
                                    $sub->where('home_team_id', $this->home_team_id)
                                        ->where('away_team_id', $this->away_team_id);
                                })->orWhere(function ($sub) {
                                    $sub->where('home_team_id', $this->away_team_id)
                                        ->where('away_team_id', $this->home_team_id);
                                });
                            })
                            ->orderBy('leg')
                            ->get()
                            ->map(function ($match) {

                                $sets = $match->results->isEmpty()
                                    ? (object)[]
                                    : $match->results
                                        ->groupBy('set_number')
                                        ->mapWithKeys(fn ($setGroup, $setNumber) => [
                                            'set_' . $setNumber => $setGroup->map(fn ($r) => [
                                                'team_id' => $r->team_id,
                                                'score' => $r->score,
                                                'won_match' => $r->won_match,
                                            ])->values(),
                                        ])
                                        ->toArray();

                                return [
                                    'id' => $match->id,                 // ✅ CẦN CHO UPDATE
                                    'leg' => $match->leg,
                                    'status' => $match->status,
                                    'court' => $match->court,
                                    'scheduled_at' => $match->scheduled_at,
                                    'sets' => $sets,
                                ];
                            });
                    }
                    break;
            }
        }

        return [
            'id' => $this->id, // match cha
            'name_of_match' => $this->name_of_match,
            'round' => $roundNumber,
            'round_name' => $roundName,

            'home_team' => $homeTeam ? [
                'id' => $homeTeam->id,
                'name' => $homeTeam->name,
                'members' => self::formatMembers(
                    $homeTeam->members,
                    $tournamentType?->tournament_id,
                    null,
                    'tournament'
                ),
            ] : null,

            'away_team' => $awayTeam ? [
                'id' => $awayTeam->id,
                'name' => $awayTeam->name,
                'members' => self::formatMembers(
                    $awayTeam->members,
                    $tournamentType?->tournament_id,
                    null,
                    'tournament'
                ),
            ] : null,

            // ✅ QUAN TRỌNG NHẤT
            'legs' => $legs->values(),

            'is_bye' => $this->is_bye,
            'is_loser_bracket' => $this->is_loser_bracket,
            'is_third_place' => $this->is_third_place,
            'winner_id' => $this->winner_id,
            'has_anchor' => collect()
            ->merge(
                $homeTeam
                    ? $homeTeam->members
                    : collect()
            )
            ->merge(
                $awayTeam
                    ? $awayTeam->members
                    : collect()
            )
            ->contains(function ($user) {
                return $user->is_anchor
                    || ($user->total_matches_has_anchor ?? 0) >= 10;
            }),
        ];
    }
}
