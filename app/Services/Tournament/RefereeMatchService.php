<?php

namespace App\Services\Tournament;

use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentCourt;
use App\Models\TournamentStaff;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefereeMatchService
{
    private const PALETTE = ['c1', 'c2', 'c3', 'c4', 'c5', 'c6'];

    public function __construct(
        private TournamentCourtNotificationService $notifier,
    ) {}

    public function getRefereeTournaments(User $user): Collection
    {
        $today = now()->toDateString();

        $tournaments = Tournament::query()
            ->where('status', Tournament::OPEN)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
            ->whereHas('staff', fn ($q) => $q->where('user_id', $user->id))
            ->with([
                'competitionLocation:id,name',
                'staff' => fn ($q) => $q->where('user_id', $user->id),
                'courts' => fn ($q) => $q->where('referee_user_id', $user->id),
            ])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $myCourtIds = $tournaments->flatMap(fn ($t) => $t->courts->pluck('id'))->values();
        $waitingCounts = $myCourtIds->isNotEmpty()
            ? Matches::whereIn('court_id', $myCourtIds)
                ->where('is_bye', false)
                ->where('status', Matches::STATUS_PENDING)
                ->where(fn ($q) => $q->whereNull('live_status')->orWhere('live_status', Matches::LIVE_STATUS_WAITING))
                ->selectRaw('court_id, COUNT(*) as cnt')
                ->groupBy('court_id')
                ->pluck('cnt', 'court_id')
            : collect();

        return $tournaments->map(function (Tournament $t) use ($user, $waitingCounts) {
            $roles = $this->resolveUserRoleLabels($t, $user->id);
            $myCourts = $t->courts->map(fn (TournamentCourt $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'order' => $c->sort_order,
            ])->values();

            $waitingCount = $t->courts->sum(fn (TournamentCourt $c) => (int) ($waitingCounts[$c->id] ?? 0));

            return [
                'id' => $t->id,
                'name' => $t->name,
                'short' => $t->name,
                'poster_url' => $t->poster_url,
                'location' => $t->competitionLocation?->name,
                'start_date' => $t->start_date,
                'end_date' => $t->end_date,
                'roles' => $roles,
                'my_courts' => $myCourts,
                'waiting_count' => $waitingCount,
            ];
        })->values();
    }

    public function getMergedMatches(User $user, array $tournamentIds): array
    {
        $tournamentIds = array_values(array_unique(array_map('intval', $tournamentIds)));

        $tournaments = Tournament::with([
            'staff',
            'courts.referee:id,full_name',
            'tournamentTypes:id,tournament_id',
        ])->whereIn('id', $tournamentIds)->get();

        foreach ($tournaments as $t) {
            if (!$t->hasScoringPermission($user->id)) {
                abort(403, 'Bạn không thuộc BTC giải đấu này.');
            }
        }

        // Preserve requested order
        $tournaments = collect($tournamentIds)
            ->map(fn ($id) => $tournaments->firstWhere('id', $id))
            ->filter()
            ->values();

        $formattedTournaments = $tournaments->values()->map(function (Tournament $t, int $idx) use ($user) {
            return [
                'id' => $t->id,
                'short' => $t->name,
                'color' => self::PALETTE[$idx % count(self::PALETTE)],
                'roles' => $this->resolveUserRoleLabels($t, $user->id),
                'can_move' => $t->hasOrganizerOrStaff($user->id),
            ];
        });

        // Merge courts by name
        $courts = $this->mergeCourts($tournaments, $user->id);

        // Load matches for selected tournaments
        $typeToTournament = [];
        foreach ($tournaments as $t) {
            foreach ($t->tournamentTypes as $type) {
                $typeToTournament[$type->id] = $t;
            }
        }

        $todayStart = now()->startOfDay();
        $matches = Matches::whereIn('tournament_type_id', array_keys($typeToTournament))
            ->where('is_bye', false)
            ->where(function ($q) use ($todayStart) {
                // waiting or live
                $q->where('status', '!=', Matches::STATUS_COMPLETED)
                    // done today
                    ->orWhere(function ($doneQ) use ($todayStart) {
                        $doneQ->where('status', Matches::STATUS_COMPLETED)
                            ->where(function ($timeQ) use ($todayStart) {
                                $timeQ->where('finished_at', '>=', $todayStart)
                                    ->orWhere(fn ($fallback) => $fallback->whereNull('finished_at')->where('updated_at', '>=', $todayStart));
                            });
                    });
            })
            ->with([
                'group:id,name',
                'tournamentCourt:id,name,referee_user_id',
                'tournamentCourt.referee:id,full_name',
                'referee:id,full_name',
                'homeTeam.members:id,full_name',
                'homeTeam.guestMembers.participant:id,full_name',
                'awayTeam.members:id,full_name',
                'awayTeam.guestMembers.participant:id,full_name',
                'results',
            ])
            ->get();

        // Build global live-player map across ALL tournaments for busy(m)
        $busyLookup = $this->buildGlobalBusyLookup();

        // Determine is_next per court queue
        $waitingByCourt = $matches
            ->filter(fn (Matches $m) => $this->displayStatus($m) === 'waiting' && $m->court_id !== null)
            ->groupBy('court_id');

        $nextMatchIds = [];
        $busyByMatchId = [];

        foreach ($matches as $m) {
            if ($this->displayStatus($m) === 'waiting') {
                $busyByMatchId[$m->id] = $this->findBusyConflict($m, $busyLookup);
            } else {
                $busyByMatchId[$m->id] = null;
            }
        }

        foreach ($waitingByCourt as $courtId => $courtMatches) {
            $sorted = $this->sortWaitingQueue($courtMatches);
            $firstAvailable = $sorted->first(fn (Matches $m) => $busyByMatchId[$m->id] === null);
            if ($firstAvailable) {
                $nextMatchIds[$firstAvailable->id] = true;
            }
        }

        $formattedMatches = $matches->map(function (Matches $m) use ($typeToTournament, $user, $busyByMatchId, $nextMatchIds) {
            /** @var Tournament $tournament */
            $tournament = $typeToTournament[$m->tournament_type_id];
            $status = $this->displayStatus($m);
            $court = $m->tournamentCourt;
            $busy = $busyByMatchId[$m->id] ?? null;

            $isCourtReferee = $court && (int) $court->referee_user_id === (int) $user->id;
            $canMove = $status === 'waiting' && $tournament->hasOrganizerOrStaff($user->id);
            $canClaim = $status === 'waiting' && $isCourtReferee && $busy === null;

            $teamA = $this->formatTeamDisplay($m->homeTeam, 'Đội A');
            $teamB = $this->formatTeamDisplay($m->awayTeam, 'Đội B');

            return [
                'id' => $m->id,
                'tournament_id' => $tournament->id,
                'court_id' => $m->court_id,
                'court_name' => $court?->name,
                'scheduled_at' => $m->scheduled_at?->format('H:i'),
                'scheduled_at_iso' => $m->scheduled_at?->toISOString(),
                'priority_at' => $m->priority_at?->toISOString(),
                'round' => $this->formatRoundLabel($m),
                'title' => "{$teamA} vs {$teamB}",
                'team_a' => $teamA,
                'team_b' => $teamB,
                'status' => $status,
                'score' => $this->extractCurrentScore($m),
                'referee' => $m->referee ? [
                    'user_id' => $m->referee->id,
                    'name' => $m->referee->full_name,
                ] : null,
                'court_referee' => $court?->referee?->full_name,
                'busy' => $busy,
                'is_next' => isset($nextMatchIds[$m->id]),
                'can_claim' => $canClaim,
                'can_move' => $canMove,
            ];
        })->values();

        return [
            'tournaments' => $formattedTournaments,
            'courts' => $courts,
            'matches' => $formattedMatches,
        ];
    }

    /**
     * @throws \RuntimeException with code 409 or 403
     */
    public function claimMatch(int $matchId, User $user): Matches
    {
        return DB::transaction(function () use ($matchId, $user) {
            /** @var Matches|null $match */
            $match = Matches::where('id', $matchId)->lockForUpdate()->first();
            if (!$match) {
                throw new \RuntimeException('MATCH_NOT_FOUND', 404);
            }

            if ($this->displayStatus($match) !== 'waiting') {
                throw new \RuntimeException('ALREADY_CLAIMED', 409);
            }

            $court = $match->court_id ? TournamentCourt::find($match->court_id) : null;
            if (!$court || (int) $court->referee_user_id !== (int) $user->id) {
                throw new \RuntimeException('NOT_YOUR_COURT', 403);
            }

            $match->load([
                'homeTeam.members:id,full_name',
                'homeTeam.guestMembers.participant:id,full_name',
                'awayTeam.members:id,full_name',
                'awayTeam.guestMembers.participant:id,full_name',
            ]);

            $busyLookup = $this->buildGlobalBusyLookup();
            $busy = $this->findBusyConflict($match, $busyLookup);
            if ($busy !== null) {
                throw new \RuntimeException('PLAYER_BUSY', 409);
            }

            $match->update([
                'live_status' => Matches::LIVE_STATUS_PLAYING,
                'referee_id' => $user->id,
                'started_at' => $match->started_at ?? now(),
            ]);

            return $match->fresh();
        });
    }

    public function moveMatch(Matches $match, User $user, ?int $destCourtId, bool $toTop): Matches
    {
        if ($this->displayStatus($match) !== 'waiting') {
            throw new \RuntimeException('MATCH_NOT_WAITING', 409);
        }

        $tournament = $match->tournamentType?->tournament;
        if (!$tournament) {
            throw new \RuntimeException('TOURNAMENT_NOT_FOUND', 404);
        }

        $tournament->loadMissing('staff');
        $isAdminOrBtc = $tournament->hasOrganizerOrStaff($user->id);

        $destCourt = null;
        if ($destCourtId !== null) {
            $destCourt = TournamentCourt::find($destCourtId);
            if (!$destCourt || (int) $destCourt->tournament_id !== (int) $tournament->id) {
                throw ValidationException::withMessages([
                    'court_id' => ['Sân đích không thuộc giải đấu của trận này.'],
                ]);
            }
        }

        $isDestReferee = $destCourt && (int) $destCourt->referee_user_id === (int) $user->id;
        if (!$isAdminOrBtc && !$isDestReferee) {
            throw new \RuntimeException('FORBIDDEN', 403);
        }

        $oldCourtId = $match->court_id;
        $oldCourt = $oldCourtId ? TournamentCourt::withTrashed()->find($oldCourtId) : null;

        $match->update([
            'court_id' => $destCourt?->id,
            'referee_id' => $destCourt?->referee_user_id,
            'priority_at' => $toTop
                ? now()
                : ($destCourt?->id !== $oldCourtId ? null : $match->priority_at),
        ]);

        $match->loadMissing(['homeTeam:id,name', 'awayTeam:id,name']);

        if ($toTop && $destCourt && $destCourt->id === $oldCourtId) {
            $this->notifier->notifyMatchMovedToTop($match, $destCourt, $user->id);
        } else {
            $this->notifier->notifyMatchMoved($match, $oldCourt, $destCourt, $user->id);
        }

        return $match->fresh();
    }

    public function reassignMatch(Matches $match, User $user, int $newRefereeId): Matches
    {
        if ($this->displayStatus($match) !== 'live') {
            throw new \RuntimeException('MATCH_NOT_LIVE', 409);
        }

        $tournament = $match->tournamentType?->tournament;
        if (!$tournament) {
            throw new \RuntimeException('TOURNAMENT_NOT_FOUND', 404);
        }

        $tournament->loadMissing('staff');
        if (!$tournament->hasOrganizerOrStaff($user->id)) {
            throw new \RuntimeException('FORBIDDEN', 403);
        }

        $isReferee = TournamentStaff::where('tournament_id', $tournament->id)
            ->where('user_id', $newRefereeId)
            ->where('role', TournamentStaff::ROLE_REFEREE)
            ->exists();

        if (!$isReferee) {
            throw ValidationException::withMessages([
                'referee_user_id' => ['Người được giao phải có vai trò Trọng tài trong BTC của giải.'],
            ]);
        }

        $oldRefereeId = $match->referee_id;
        $match->update(['referee_id' => $newRefereeId]);

        $match->loadMissing(['homeTeam:id,name', 'awayTeam:id,name']);
        $this->notifier->notifyMatchReassigned($match, $oldRefereeId ? (int) $oldRefereeId : null, $newRefereeId, $user->id);

        return $match->fresh();
    }

    public function displayStatus(Matches $match): string
    {
        if ($match->status === Matches::STATUS_COMPLETED || $match->live_status === Matches::LIVE_STATUS_FINISHED) {
            return 'done';
        }

        if (in_array($match->live_status, [
            Matches::LIVE_STATUS_PLAYING,
            Matches::LIVE_STATUS_TIMEOUT,
            Matches::LIVE_STATUS_BETWEEN_SETS,
        ], true)) {
            return 'live';
        }

        return 'waiting';
    }

    private function sortWaitingQueue(Collection $matches): Collection
    {
        return $matches->sort(function (Matches $a, Matches $b) {
            $aHasPrio = $a->priority_at !== null;
            $bHasPrio = $b->priority_at !== null;

            if ($aHasPrio !== $bHasPrio) {
                return $aHasPrio ? -1 : 1;
            }
            if ($aHasPrio && $bHasPrio) {
                $cmp = $a->priority_at->timestamp <=> $b->priority_at->timestamp;
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            $aHasSched = $a->scheduled_at !== null;
            $bHasSched = $b->scheduled_at !== null;
            if ($aHasSched !== $bHasSched) {
                return $aHasSched ? -1 : 1;
            }
            if ($aHasSched && $bHasSched) {
                $cmp = $a->scheduled_at->timestamp <=> $b->scheduled_at->timestamp;
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return $a->id <=> $b->id;
        })->values();
    }

    private function buildGlobalBusyLookup(): array
    {
        $liveMatches = Matches::query()
            ->where('status', '!=', Matches::STATUS_COMPLETED)
            ->whereIn('live_status', [
                Matches::LIVE_STATUS_PLAYING,
                Matches::LIVE_STATUS_TIMEOUT,
                Matches::LIVE_STATUS_BETWEEN_SETS,
            ])
            ->with([
                'tournamentCourt:id,name',
                'homeTeam.members:id,full_name',
                'homeTeam.guestMembers.participant:id,full_name',
                'awayTeam.members:id,full_name',
                'awayTeam.guestMembers.participant:id,full_name',
            ])
            ->get();

        $lookup = [];
        foreach ($liveMatches as $live) {
            $courtName = $live->tournamentCourt?->name ?? ($live->court ? "Sân {$live->court}" : 'Sân khác');
            foreach ($this->extractPlayerIdentities($live) as $key => $name) {
                $lookup[$key][] = [
                    'match_id' => $live->id,
                    'player' => $name,
                    'court_name' => $courtName,
                ];
            }
        }

        return $lookup;
    }

    private function findBusyConflict(Matches $match, array $busyLookup): ?array
    {
        foreach ($this->extractPlayerIdentities($match) as $key => $name) {
            if (empty($busyLookup[$key])) {
                continue;
            }
            foreach ($busyLookup[$key] as $entry) {
                if ((int) $entry['match_id'] !== (int) $match->id) {
                    return [
                        'player' => $entry['player'] ?: $name,
                        'court_name' => $entry['court_name'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string> key => display name
     */
    private function extractPlayerIdentities(Matches $match): array
    {
        $identities = [];
        foreach ([$match->homeTeam, $match->awayTeam] as $team) {
            if (!$team) {
                continue;
            }
            foreach ($team->members as $member) {
                $identities["u:{$member->id}"] = $member->full_name ?? '';
            }
            foreach ($team->guestMembers as $guest) {
                $key = $guest->participant_id ? "p:{$guest->participant_id}" : "g:{$guest->id}";
                $identities[$key] = $guest->name ?? $guest->participant?->full_name ?? '';
            }
        }

        return $identities;
    }

    private function mergeCourts(Collection $tournaments, int $userId): array
    {
        $merged = [];
        $multiTournament = $tournaments->count() > 1;

        foreach ($tournaments as $t) {
            foreach ($t->courts as $court) {
                $name = $court->name;
                if (!isset($merged[$name])) {
                    $merged[$name] = [
                        'name' => $name,
                        'ids' => [],
                        'mine' => false,
                        'referee' => $court->referee?->full_name,
                        'order' => $court->sort_order,
                    ];
                }
                $merged[$name]['ids'][(string) $t->id] = $court->id;
                if ((int) $court->referee_user_id === $userId) {
                    $merged[$name]['mine'] = true;
                }
                if (!$merged[$name]['referee'] && $court->referee) {
                    $merged[$name]['referee'] = $court->referee->full_name;
                }
            }
        }

        $list = array_values($merged);
        if ($multiTournament) {
            usort($list, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        } else {
            usort($list, fn ($a, $b) => ($a['order'] <=> $b['order']) ?: strnatcasecmp($a['name'], $b['name']));
        }

        return $list;
    }

    private function resolveUserRoleLabels(Tournament $tournament, int $userId): array
    {
        $roles = [];
        if ($tournament->hasOrganizer($userId)) {
            $roles[] = 'Admin';
        }
        if ($tournament->hasBtc($userId)) {
            $roles[] = 'BTC';
        }
        if ($tournament->hasReferee($userId)) {
            $roles[] = 'Trọng tài';
        }

        return $roles;
    }

    private function formatRoundLabel(Matches $m): string
    {
        if ($m->group) {
            $groupName = str_starts_with(mb_strtolower($m->group->name), 'bảng')
                ? $m->group->name
                : "Bảng {$m->group->name}";
            return "{$groupName} · Lượt {$m->round}";
        }

        return $m->name_of_match ?: "Vòng {$m->round}";
    }

    private function formatTeamDisplay($team, string $fallback): string
    {
        if (!$team) {
            return $fallback;
        }

        $names = collect();
        foreach ($team->members as $member) {
            $names->push($member->full_name);
        }
        foreach ($team->guestMembers as $guest) {
            $names->push($guest->name ?? $guest->participant?->full_name);
        }

        $filtered = $names->filter()->values();
        return $filtered->isNotEmpty() ? $filtered->implode(' - ') : ($team->name ?: $fallback);
    }

    private function extractCurrentScore(Matches $m): array
    {
        if ($m->results->isEmpty()) {
            return [0, 0];
        }

        $targetSet = $m->current_set ?: $m->results->max('set_number');
        $setResults = $m->results->where('set_number', $targetSet);

        $home = $setResults->firstWhere('team_id', $m->home_team_id);
        $away = $setResults->firstWhere('team_id', $m->away_team_id);

        return [
            (int) ($home?->score ?? 0),
            (int) ($away?->score ?? 0),
        ];
    }
}
