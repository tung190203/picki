<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

class TeamMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * $this->resource có thể là:
     *   - User: real user member, có relation `tournamentParticipant` (Participant|null)
     *           đã được set bởi TournamentTeamMemberHydrator.
     *   - Participant: guest member (User=null), `tournamentParticipant` = chính nó.
     */
    public function toArray(Request $request): array
    {
        $participant = $this->relationLoaded('tournamentParticipant')
            ? $this->tournamentParticipant
            : null;

        $isGuest = (bool) ($participant?->is_guest);

        if ($isGuest) {
            // $this là Participant (không có User record)
            $fullName = $participant->guest_name ?? '';
            $avatarUrl = $participant->guest_avatar;
            $userId = null;
        } else {
            // $this là User
            $fullName = $this->full_name ?? '';
            $avatarUrl = $this->avatar_url;
            $userId = $this->id;
        }

        // Build sports array (mỗi User có thể có nhiều sport)
        $sportsArray = [];
        $sportsLoaded = $this->relationLoaded('sports') ? $this->sports : collect();
        foreach ($sportsLoaded as $sport) {
            $sportsArray[] = self::buildSportEntry($sport, $participant, $isGuest);
        }

        return [
            'id'                          => $userId ?? $participant?->id,
            'full_name'                   => $fullName,
            'avatar'                      => $avatarUrl,
            'sports'                      => $sportsArray,
            'tournament_participant'      => $participant
                ? (new ParticipantResource($participant))->withoutNestedUserSports()
                : null,
            'name'                        => $fullName,
            'avatar_url'                  => $avatarUrl,
            'is_confirmed'                => (bool) ($participant?->is_confirmed ?? false),
            'is_guest'                    => $isGuest,
            'guest_name'                  => $isGuest ? $participant?->guest_name : null,
            'guest_phone'                 => $isGuest ? $participant?->guest_phone : null,
            'guest_avatar'                => $isGuest ? $participant?->guest_avatar : null,
            'guarantor'                   => $participant?->relationLoaded('guarantor') && $participant->guarantor
                ? new UserListResource($participant->guarantor)
                : null,
            'guarantor_user_id'           => $isGuest ? (int) $participant?->guarantor_user_id : null,
            'guarantor_name'              => $isGuest ? $participant?->guarantor?->full_name : null,
            'estimated_level'             => $isGuest ? (float) ($participant?->estimated_level ?? 0) : null,
            'is_pending_confirmation'     => $isGuest ? (bool) ($participant?->is_pending_confirmation ?? false) : null,
            'checked_in_at'               => $participant?->checked_in_at,
            'is_absent'                   => (bool) ($participant?->is_absent ?? false),
            'gender'                      => $isGuest ? null : ($this->gender ?? null),
            'gender_text'                 => $isGuest ? null : ($this->gender_text ?? null),
        ];
    }

    private static function emptyStats(): array
    {
        return [
            'total_matches' => 0,
            'total_tournaments' => 0,
            'total_mini_tournaments' => 0,
            'total_prizes' => 0,
            'win_rate' => 0,
            'performance' => 0,
        ];
    }

    private static function buildSportEntry($sport, $participant, bool $isGuest): array
    {
        $scores = $sport->relationLoaded('scores') ? $sport->scores : collect();
        $types = ['personal_score', 'dupr_score', 'vndupr_score'];
        $formattedScores = [];
        foreach ($types as $type) {
            $latestScore = $scores->where('score_type', $type)->sortByDesc('created_at')->first();
            $scoreValue = $latestScore ? $latestScore->score_value : 0;
            $formattedScores[$type] = number_format($scoreValue, 3);
        }

        $stats = !empty($sport->user_id)
            ? User::getSportStats($sport->user_id, $sport->sport_id)
            : self::emptyStats();

        if ($isGuest) {
            $formattedScores['vndupr_score'] = number_format((float) ($participant?->estimated_level ?? 0), 3);
        }

        return [
            'sport_id'                 => $sport->sport_id,
            'sport_icon'               => $sport->relationLoaded('sport') ? optional($sport->sport)->icon : null,
            'sport_name'               => $sport->relationLoaded('sport') ? optional($sport->sport)->name : null,
            'scores'                   => $formattedScores,
            'total_matches'            => $stats['total_matches'],
            'total_tournaments'        => $stats['total_tournaments'],
            'total_mini_tournaments'   => $stats['total_mini_tournaments'],
            'total_prizes'             => $stats['total_prizes'],
            'win_rate'                 => $stats['win_rate'],
            'performance'              => $stats['performance'],
        ];
    }
}
