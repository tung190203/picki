<?php

namespace App\Observers;

use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\QuickMatch;
use Illuminate\Support\Facades\Cache;

/**
 * Clears the /me endpoint cache when any match is completed.
 * This ensures vn_rank is recalculated on the next /me call.
 */
class MatchCacheObserver
{
    /**
     * Handle all match model "updated" events.
     * Only clears cache when status transitions to 'completed'.
     */
    public function updated($match): void
    {
        if (! $this->statusChangedToCompleted($match)) {
            return;
        }

        $userIds = $this->extractUserIds($match);
        foreach (array_unique($userIds) as $userId) {
            Cache::forget("user:{$userId}:me_extras");
        }
    }

    protected function statusChangedToCompleted($model): bool
    {
        if (! $model->isDirty('status')) {
            return false;
        }

        $status = $model->getAttribute('status');

        if ($model instanceof MiniMatch) {
            return $status === MiniMatch::STATUS_COMPLETED;
        }
        if ($model instanceof QuickMatch) {
            return $status === QuickMatch::STATUS_COMPLETED;
        }
        if ($model instanceof Matches) {
            return $status === Matches::STATUS_COMPLETED;
        }

        return $status === 'completed';
    }

    protected function extractUserIds($match): array
    {
        if ($match instanceof MiniMatch) {
            return $this->extractMiniMatchUserIds($match);
        }
        if ($match instanceof QuickMatch) {
            return $this->extractQuickMatchUserIds($match);
        }
        if ($match instanceof Matches) {
            return $this->extractTournamentMatchUserIds($match);
        }
        return [];
    }

    protected function extractMiniMatchUserIds(MiniMatch $miniMatch): array
    {
        $userIds = [];

        if ($miniMatch->team1_id && $miniMatch->team1) {
            $userIds = array_merge(
                $userIds,
                $miniMatch->team1->members->pluck('user_id')->filter()->toArray()
            );
        }
        if ($miniMatch->team2_id && $miniMatch->team2) {
            $userIds = array_merge(
                $userIds,
                $miniMatch->team2->members->pluck('user_id')->filter()->toArray()
            );
        }
        if ($miniMatch->participant1_id && $miniMatch->participant1?->user_id) {
            $userIds[] = $miniMatch->participant1->user_id;
        }
        if ($miniMatch->participant2_id && $miniMatch->participant2?->user_id) {
            $userIds[] = $miniMatch->participant2->user_id;
        }

        return $userIds;
    }

    protected function extractQuickMatchUserIds(QuickMatch $quickMatch): array
    {
        return array_merge($quickMatch->team_a ?? [], $quickMatch->team_b ?? []);
    }

    protected function extractTournamentMatchUserIds(Matches $match): array
    {
        $userIds = [];

        if ($match->home_team_id && $match->homeTeam) {
            $userIds = array_merge(
                $userIds,
                $match->homeTeam->members->pluck('user_id')->filter()->toArray()
            );
        }
        if ($match->away_team_id && $match->awayTeam) {
            $userIds = array_merge(
                $userIds,
                $match->awayTeam->members->pluck('user_id')->filter()->toArray()
            );
        }

        return $userIds;
    }
}
