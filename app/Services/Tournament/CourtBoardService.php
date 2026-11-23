<?php

namespace App\Services\Tournament;

use App\Models\CourtAssignment;
use App\Models\Group;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentCourt;
use App\Models\TournamentType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourtBoardService
{
    public function __construct(
        private TournamentCourtService $courtService,
        private TournamentCourtNotificationService $notifier,
    ) {}

    public function getBoard(Tournament $tournament): array
    {
        $typeIds = $tournament->tournamentTypes()->pluck('id');

        $allMatches = Matches::whereIn('tournament_type_id', $typeIds)
            ->where('is_bye', false)
            ->with([
                'homeTeam:id,name',
                'awayTeam:id,name',
            ])
            ->orderBy('round')
            ->orderBy('id')
            ->get();

        $assignments = CourtAssignment::where('tournament_id', $tournament->id)
            ->orderBy('sort_order')
            ->get();

        $units = [];

        // 1. Group units (vòng bảng)
        $groups = Group::whereIn('tournament_type_id', $typeIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        foreach ($groups as $group) {
            $groupMatches = $allMatches->where('group_id', $group->id);
            $groupAssignments = $assignments
                ->where('unit_type', CourtAssignment::UNIT_GROUP)
                ->where('group_id', $group->id);

            $courtCounts = $groupAssignments->map(function (CourtAssignment $a) use ($groupMatches) {
                $cnt = $groupMatches
                    ->where('status', Matches::STATUS_PENDING)
                    ->where('court_id', $a->court_id)
                    ->count();
                return [
                    'court_id' => $a->court_id,
                    'count' => $cnt,
                ];
            })->values()->all();

            $units[] = [
                'type' => 'group',
                'group_id' => $group->id,
                'label' => str_starts_with(mb_strtolower($group->name), 'bảng')
                    ? $group->name
                    : "Bảng {$group->name}",
                'match_count' => $groupMatches->count(),
                'courts' => $courtCounts,
                'matches' => $groupMatches->map(function (Matches $m) {
                    $teamA = $m->homeTeam?->name ?: 'Đội A';
                    $teamB = $m->awayTeam?->name ?: 'Đội B';
                    return [
                        'id' => $m->id,
                        'label' => "{$teamA} vs {$teamB}",
                        'team_a' => $teamA,
                        'team_b' => $teamB,
                        'court_id' => $m->court_id,
                        'scheduled_at' => $m->scheduled_at?->format('H:i'),
                        'status' => $m->status,
                    ];
                })->values()->all(),
            ];
        }

        // 2. Knockout slot units (trận loại trực tiếp)
        foreach ($tournament->tournamentTypes as $type) {
            $slots = $this->resolveKnockoutSlots($type, $allMatches->where('tournament_type_id', $type->id));
            foreach ($slots as $slot) {
                $assign = $assignments
                    ->where('unit_type', CourtAssignment::UNIT_SLOT)
                    ->where('slot_round', $slot['round'])
                    ->where('slot_no', $slot['no'])
                    ->first();

                $units[] = [
                    'type' => 'slot',
                    'round' => $slot['round'],
                    'no' => $slot['no'],
                    'label' => $slot['label'],
                    'match_id' => $slot['match_id'],
                    'court_id' => $slot['court_id'] ?? $assign?->court_id,
                ];
            }
        }

        return $units;
    }

    public function saveUnit(Tournament $tournament, array $payload, int $actorId): array
    {
        $unitType = $payload['unit_type'];
        $courtIds = array_values(array_unique(array_map('intval', $payload['court_ids'] ?? [])));

        $courtsById = $tournament->courts()->whereIn('id', $courtIds)->get()->keyBy('id');
        if (count($courtIds) !== $courtsById->count()) {
            throw ValidationException::withMessages([
                'court_ids' => ['Có sân không thuộc giải đấu này.'],
            ]);
        }

        // Ordered collection following client court_ids order
        $orderedCourts = collect($courtIds)->map(fn ($id) => $courtsById->get($id))->filter()->values();
        $affectedRefereeIds = [];

        DB::transaction(function () use ($tournament, $unitType, $payload, $orderedCourts, &$affectedRefereeIds) {
            $typeIds = $tournament->tournamentTypes()->pluck('id');

            if ($unitType === CourtAssignment::UNIT_GROUP) {
                $groupId = (int) ($payload['group_id'] ?? 0);
                $group = Group::whereIn('tournament_type_id', $typeIds)->findOrFail($groupId);

                $oldCourtIds = CourtAssignment::where('tournament_id', $tournament->id)
                    ->where('unit_type', CourtAssignment::UNIT_GROUP)
                    ->where('group_id', $group->id)
                    ->pluck('court_id')
                    ->all();

                CourtAssignment::where('tournament_id', $tournament->id)
                    ->where('unit_type', CourtAssignment::UNIT_GROUP)
                    ->where('group_id', $group->id)
                    ->delete();

                foreach ($orderedCourts as $idx => $court) {
                    CourtAssignment::create([
                        'tournament_id' => $tournament->id,
                        'unit_type' => CourtAssignment::UNIT_GROUP,
                        'group_id' => $group->id,
                        'court_id' => $court->id,
                        'sort_order' => $idx,
                    ]);
                }

                // Chia lại các trận CHƯA ĐẤU của bảng theo thứ tự giờ dự kiến, id
                $waitingMatches = Matches::where('group_id', $group->id)
                    ->where('is_bye', false)
                    ->where('status', Matches::STATUS_PENDING)
                    ->where(fn ($q) => $q->whereNull('live_status')->orWhere('live_status', Matches::LIVE_STATUS_WAITING))
                    ->orderByRaw('scheduled_at IS NULL, scheduled_at ASC')
                    ->orderBy('id')
                    ->get();

                $courtCount = $orderedCourts->count();
                foreach ($waitingMatches as $i => $match) {
                    if ($match->court_id) {
                        $oldCourtIds[] = (int) $match->court_id;
                    }
                    $dest = $courtCount > 0 ? $orderedCourts[$i % $courtCount] : null;
                    $match->update([
                        'court_id' => $dest?->id,
                        'referee_id' => $dest?->referee_user_id,
                    ]);
                }

                $allAffectedCourtIds = array_unique(array_merge($oldCourtIds, $orderedCourts->pluck('id')->all()));
                $affectedRefereeIds = TournamentCourt::whereIn('id', $allAffectedCourtIds)
                    ->whereNotNull('referee_user_id')
                    ->pluck('referee_user_id')
                    ->all();
            } else {
                $slotRound = (string) ($payload['slot_round'] ?? '');
                $slotNo = (int) ($payload['slot_no'] ?? 0);

                $oldCourtIds = CourtAssignment::where('tournament_id', $tournament->id)
                    ->where('unit_type', CourtAssignment::UNIT_SLOT)
                    ->where('slot_round', $slotRound)
                    ->where('slot_no', $slotNo)
                    ->pluck('court_id')
                    ->all();

                CourtAssignment::where('tournament_id', $tournament->id)
                    ->where('unit_type', CourtAssignment::UNIT_SLOT)
                    ->where('slot_round', $slotRound)
                    ->where('slot_no', $slotNo)
                    ->delete();

                $dest = $orderedCourts->first();
                if ($dest) {
                    CourtAssignment::create([
                        'tournament_id' => $tournament->id,
                        'unit_type' => CourtAssignment::UNIT_SLOT,
                        'slot_round' => $slotRound,
                        'slot_no' => $slotNo,
                        'court_id' => $dest->id,
                        'sort_order' => 0,
                    ]);
                }

                $slotMatches = $this->findMatchesForSlot($tournament, $slotRound, $slotNo);
                foreach ($slotMatches as $match) {
                    if ($match->status !== Matches::STATUS_PENDING) {
                        continue;
                    }
                    if ($match->live_status && $match->live_status !== Matches::LIVE_STATUS_WAITING) {
                        continue;
                    }
                    if ($match->court_id) {
                        $oldCourtIds[] = (int) $match->court_id;
                    }
                    $match->update([
                        'court_id' => $dest?->id,
                        'referee_id' => $dest?->referee_user_id,
                    ]);
                }

                $allAffectedCourtIds = array_unique(array_merge($oldCourtIds, $dest ? [$dest->id] : []));
                $affectedRefereeIds = TournamentCourt::whereIn('id', $allAffectedCourtIds)
                    ->whereNotNull('referee_user_id')
                    ->pluck('referee_user_id')
                    ->all();
            }
        });

        $this->notifier->notifyQueueChanged($affectedRefereeIds, $tournament->id, $actorId);

        return $this->getBoard($tournament);
    }

    public function autoAssign(Tournament $tournament, int $actorId): array
    {
        $courts = $tournament->courts()->get();
        if ($courts->isEmpty()) {
            throw ValidationException::withMessages([
                'courts' => ['Chưa có sân nào để chia tự động.'],
            ]);
        }

        $load = [];
        foreach ($courts as $court) {
            $load[$court->id] = 0;
        }

        $typeIds = $tournament->tournamentTypes()->pluck('id');
        $groups = Group::whereIn('tournament_type_id', $typeIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $allMatches = Matches::whereIn('tournament_type_id', $typeIds)
            ->where('is_bye', false)
            ->get();

        foreach ($groups as $group) {
            $courtId = $this->pickLeastLoadedCourt($load);
            $this->saveUnit($tournament, [
                'unit_type' => CourtAssignment::UNIT_GROUP,
                'group_id' => $group->id,
                'court_ids' => [$courtId],
            ], $actorId);
            $load[$courtId] += $allMatches->where('group_id', $group->id)->count();
        }

        foreach ($tournament->tournamentTypes as $type) {
            $slots = $this->resolveKnockoutSlots($type, $allMatches->where('tournament_type_id', $type->id));
            foreach ($slots as $slot) {
                $courtId = $this->pickLeastLoadedCourt($load);
                $this->saveUnit($tournament, [
                    'unit_type' => CourtAssignment::UNIT_SLOT,
                    'slot_round' => $slot['round'],
                    'slot_no' => $slot['no'],
                    'court_ids' => [$courtId],
                ], $actorId);
                $load[$courtId] += 1;
            }
        }

        return $this->getBoard($tournament);
    }

    /**
     * Áp dụng court_assignments đã lưu cho các trận loại trực tiếp vừa được tạo.
     */
    public function applyAssignmentsToNewKnockoutMatches(TournamentType $type): void
    {
        $tournament = $type->tournament;
        if (!$tournament) {
            return;
        }

        $matches = Matches::where('tournament_type_id', $type->id)
            ->whereNull('group_id')
            ->where('is_bye', false)
            ->get();

        if ($matches->isEmpty()) {
            return;
        }

        $assignments = CourtAssignment::where('tournament_id', $tournament->id)
            ->where('unit_type', CourtAssignment::UNIT_SLOT)
            ->with('court')
            ->get()
            ->keyBy(fn (CourtAssignment $a) => "{$a->slot_round}:{$a->slot_no}");

        if ($assignments->isEmpty()) {
            return;
        }

        $slots = $this->resolveKnockoutSlots($type, $matches);
        foreach ($slots as $slot) {
            $assign = $assignments->get("{$slot['round']}:{$slot['no']}");
            if (!$assign || !$assign->court) {
                continue;
            }

            foreach ($slot['matches'] as $m) {
                if ($m->court_id === null && $m->status === Matches::STATUS_PENDING) {
                    $m->update([
                        'court_id' => $assign->court->id,
                        'referee_id' => $assign->court->referee_user_id,
                    ]);
                }
            }
        }
    }

    /**
     * Resolve danh sách slot loại trực tiếp của 1 TournamentType.
     *
     * @return array<int, array{round: string, no: int, label: string, title: string, match_id: int|null, court_id: int|null, matches: Collection}>
     */
    public function resolveKnockoutSlots(TournamentType $type, Collection $typeMatches): array
    {
        $knockoutMatches = $typeMatches->whereNull('group_id')->values();
        if ($knockoutMatches->isEmpty()) {
            return [];
        }

        $finalRound = (int) ($knockoutMatches->filter(fn ($m) => !($m->is_third_place ?? false))->max('round') ?? 1);

        // Group legs of same pairing by (round, is_third_place, home_team_id, away_team_id) or leg=1 order
        $leg1Matches = $knockoutMatches
            ->sortBy('id')
            ->groupBy(function ($m) {
                if ($m->is_third_place) {
                    return 'third_' . $m->round;
                }
                if ($m->next_match_id && $m->next_position) {
                    return 'next_' . $m->next_match_id . '_' . $m->next_position;
                }
                return 'final_' . $m->round . '_' . ($m->bracket_type ?? 'main');
            });

        // Order slots by round ASC, third_place last before/after final
        $groupedByRoundCode = [];
        foreach ($leg1Matches as $legs) {
            $first = $legs->first();
            $code = $this->roundCode($first, $finalRound);
            $groupedByRoundCode[$code][] = $legs;
        }

        $slots = [];
        foreach ($groupedByRoundCode as $code => $slotGroups) {
            foreach ($slotGroups as $index => $legs) {
                $no = $index + 1;
                $first = $legs->first();

                $slots[] = [
                    'round' => $code,
                    'no' => $no,
                    'label' => $this->slotLabel($code, $no),
                    'match_id' => $first->id,
                    'court_id' => $first->court_id,
                    'matches' => $legs,
                ];
            }
        }

        return $slots;
    }

    private function findMatchesForSlot(Tournament $tournament, string $slotRound, int $slotNo): Collection
    {
        $result = collect();
        foreach ($tournament->tournamentTypes as $type) {
            $typeMatches = Matches::where('tournament_type_id', $type->id)
                ->whereNull('group_id')
                ->where('is_bye', false)
                ->get();

            $slots = $this->resolveKnockoutSlots($type, $typeMatches);
            foreach ($slots as $slot) {
                if ($slot['round'] === $slotRound && (int) $slot['no'] === $slotNo) {
                    $result = $result->merge($slot['matches']);
                }
            }
        }

        return $result;
    }

    private function roundCode(Matches $match, int $finalRound): string
    {
        if ($match->is_third_place) {
            return 'P3';
        }

        $diff = $finalRound - (int) $match->round;

        return match ($diff) {
            0 => 'F',
            1 => 'SF',
            2 => 'QF',
            3 => 'R16',
            4 => 'R32',
            default => 'R' . $match->round,
        };
    }

    private function slotLabel(string $code, int $no): string
    {
        return match ($code) {
            'F' => 'Chung kết',
            'P3' => 'Tranh hạng 3',
            'SF' => "Bán kết {$no}",
            'QF' => "Tứ kết {$no}",
            'R16' => "Vòng 1/8 - {$no}",
            'R32' => "Vòng 32 - {$no}",
            default => "{$code} - {$no}",
        };
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

    private function pickLeastLoadedCourt(array $load): int
    {
        $minCourtId = array_key_first($load);
        $minVal = $load[$minCourtId];

        foreach ($load as $courtId => $val) {
            if ($val < $minVal) {
                $minVal = $val;
                $minCourtId = $courtId;
            }
        }

        return (int) $minCourtId;
    }
}
