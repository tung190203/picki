<?php

namespace App\Traits;

use App\Models\Participant;
use App\Models\MiniParticipant;

trait FormatsTeamMembers
{
    /**
     * Format a team member with guest-aware name and avatar.
     * Resolves Participant/MiniParticipant by tournament context to get guest_name/guest_avatar.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model $members
     * @param  int|null  $tournamentId
     * @param  int|null  $tournamentTypeId
     * @param  string    $type  'tournament' | 'mini'
     * @return \Illuminate\Support\Collection
     */
    public static function formatMembers($members, ?int $tournamentId = null, ?int $tournamentTypeId = null, string $type = 'tournament')
    {
        // Build participant lookup: participant.user_id => participant
        $participantMap = collect();
        if ($tournamentId && $type === 'tournament') {
            $participantMap = Participant::where('tournament_id', $tournamentId)
                ->whereIn('user_id', $members->pluck('id'))
                ->get()
                ->keyBy('user_id');
        } elseif ($tournamentTypeId && $type === 'mini') {
            $participantMap = MiniParticipant::where('mini_tournament_id', $tournamentTypeId)
                ->whereIn('user_id', $members->pluck('id'))
                ->get()
                ->keyBy('user_id');
        }

        return $members->map(function ($member) use ($participantMap, $type) {
            /** @var \App\Models\Participant|\App\Models\MiniParticipant|null $p */
            $p = $participantMap->get($member->id);
            $isGuest = $p?->is_guest;

            if ($type === 'mini') {
                return [
                    'id' => $member->id,
                    'user_id' => $member->id,
                    'full_name' => $isGuest ? ($p->guest_name ?? $member->full_name) : $member->full_name,
                    'avatar_url' => $isGuest ? ($p->guest_avatar ?? $member->avatar_url) : $member->avatar_url,
                    'is_guest' => $isGuest,
                ];
            }

            return [
                'id' => $member->id,
                'name' => $isGuest ? ($p->guest_name ?? $member->full_name) : $member->full_name,
                'avatar' => $isGuest ? ($p->guest_avatar ?? $member->avatar_url) : $member->avatar_url,
                'is_guest' => $isGuest,
            ];
        });
    }
}
