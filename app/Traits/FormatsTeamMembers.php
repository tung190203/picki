<?php

namespace App\Traits;

use App\Http\Resources\TeamMemberResource;
use App\Models\Participant;
use App\Models\MiniParticipant;

trait FormatsTeamMembers
{
    /**
     * Format a team member with guest-aware name and avatar.
     * Resolves Participant/MiniParticipant by tournament context to get guest_name/guest_avatar.
     *
     * Nhánh 'tournament': dùng TeamMemberResource để đảm bảo contract đầy đủ
     * (id, full_name, avatar, sports, tournament_participant).
     * Nhánh 'mini': giữ nguyên cấu trúc cũ vì mini tournament có contract riêng.
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
            // Load đủ relations để TeamMemberResource trả nested tournament_participant + sports
            $participantMap = Participant::where('tournament_id', $tournamentId)
                ->whereIn('user_id', $members->pluck('id'))
                ->with(['user.sports.scores', 'user.sports.sport', 'guarantor'])
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

            // Nhánh mini: giữ cấu trúc cũ
            if ($type === 'mini') {
                return [
                    'id' => $member->id,
                    'user_id' => $member->id,
                    'full_name' => $isGuest ? ($p->guest_name ?? $member->full_name) : $member->full_name,
                    'avatar_url' => $isGuest ? ($p->guest_avatar ?? $member->avatar_url) : $member->avatar_url,
                    'is_guest' => $isGuest,
                ];
            }

            // Nhánh tournament: hydrate member với tournamentParticipant rồi serialize qua TeamMemberResource
            if ($p) {
                $member->setRelation('tournamentParticipant', $p);
                // Ensure sports trên member từ participant->user
                if (!$member->relationLoaded('sports') && $p->relationLoaded('user')) {
                    $member->setRelation('sports', $p->user?->sports ?? collect());
                }
            } else {
                $member->setRelation('tournamentParticipant', null);
            }

            return (new TeamMemberResource($member))->resolve(request());
        });
    }
}
