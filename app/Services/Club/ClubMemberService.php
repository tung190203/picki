<?php

namespace App\Services\Club;

use App\Models\Club\Club;
use App\Models\User;
use Illuminate\Support\Collection;

class ClubMemberService
{
    public function enrichMembersWithRanking(Collection $members): Collection
    {
        $members = $members->map(function ($member) {
            $user = $member->user;
            $score = 0;

            if ($user && $user->relationLoaded('sports')) {
                // Get VNDUPR score from user's sports
                foreach ($user->sports ?? [] as $us) {
                    $vndupr = $us->relationLoaded('scores')
                        ? $us->scores->where('score_type', 'vndupr_score')->sortByDesc('created_at')->first()
                        : null;
                    if ($vndupr) {
                        $score = (float) $vndupr->score_value;
                        break;
                    }
                }

                // Calculate win_rate and performance for each sport
                foreach ($user->sports ?? [] as $userSport) {
                    $stats = User::getSportStats($user->id, $userSport->sport_id);
                    $userSport->setAttribute('win_rate', $stats['win_rate']);
                    $userSport->setAttribute('performance', $stats['performance']);
                }
            }

            $member->user?->setAttribute('club_score', $score);
            return $member;
        })->sortByDesc(fn ($m) => $m->user?->club_score ?? 0)->values();

        // Add rank_in_club attribute
        $members->each(fn ($member, $index) => $member->setAttribute('rank_in_club', $index + 1));

        return $members;
    }

    public function countActiveAdmins(Club $club): int
    {
        return $club->countActiveAdmins();
    }

    public function hasAtLeastOneAdminAfterRemoving(Club $club, int $memberIdToRemove): bool
    {
        return $club->hasAtLeastOneAdminAfterRemoving($memberIdToRemove);
    }
}
