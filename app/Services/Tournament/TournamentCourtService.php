<?php

namespace App\Services\Tournament;

use App\Models\CourtAssignment;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentCourt;
use App\Models\TournamentStaff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentCourtService
{
    public function __construct(
        private TournamentCourtNotificationService $notifier,
    ) {}

    public function listCourts(Tournament $tournament): array
    {
        $courts = $tournament->courts()
            ->with('referee:id,full_name,avatar_url')
            ->get()
            ->map(fn (TournamentCourt $court) => $this->formatCourt($court))
            ->values();

        $referees = $this->getTournamentReferees($tournament);

        return [
            'courts' => $courts,
            'referees' => $referees,
        ];
    }

    public function createCourt(Tournament $tournament, ?string $name = null): TournamentCourt
    {
        $name = $name !== null ? trim($name) : $this->generateNextCourtName($tournament);

        $this->ensureUniqueName($tournament->id, $name);

        $maxOrder = (int) $tournament->courts()->max('sort_order');

        return TournamentCourt::create([
            'tournament_id' => $tournament->id,
            'name' => $name,
            'sort_order' => $maxOrder + 1,
        ]);
    }

    public function updateCourt(TournamentCourt $court, array $data, int $actorId): TournamentCourt
    {
        $tournament = $court->tournament;
        $oldRefereeId = $court->referee_user_id;

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $name = trim($data['name']);
            $this->ensureUniqueName($court->tournament_id, $name, $court->id);
            $court->name = $name;
        }

        $sortOrderChanged = false;
        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $newOrder = (int) $data['sort_order'];
            if ($newOrder !== (int) $court->sort_order) {
                $sortOrderChanged = true;
                $court->sort_order = $newOrder;
            }
        }

        $refereeChanged = false;
        if (array_key_exists('referee_user_id', $data)) {
            $newRefereeId = $data['referee_user_id'] !== null ? (int) $data['referee_user_id'] : null;

            if ($newRefereeId !== null) {
                $isReferee = TournamentStaff::where('tournament_id', $court->tournament_id)
                    ->where('user_id', $newRefereeId)
                    ->where('role', TournamentStaff::ROLE_REFEREE)
                    ->exists();

                if (!$isReferee) {
                    throw ValidationException::withMessages([
                        'referee_user_id' => ['Người dùng phải có vai trò Trọng tài trong BTC của giải.'],
                    ]);
                }
            }

            if ($newRefereeId !== $oldRefereeId) {
                $refereeChanged = true;
                $court->referee_user_id = $newRefereeId;
            }
        }

        DB::transaction(function () use ($court, $refereeChanged, $sortOrderChanged) {
            $court->save();

            if ($sortOrderChanged) {
                Matches::where('court_id', $court->id)->update(['court' => $court->sort_order]);
            }

            // Đổi trọng tài của sân: các trận chờ chuyển sang người mới;
            // trận đang đấu giữ nguyên người đang bắt cho tới khi xong.
            if ($refereeChanged) {
                Matches::where('court_id', $court->id)
                    ->where('status', Matches::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('live_status')
                            ->orWhere('live_status', Matches::LIVE_STATUS_WAITING);
                    })
                    ->update(['referee_id' => $court->referee_user_id]);
            }
        });

        if ($refereeChanged) {
            if ($oldRefereeId) {
                $this->notifier->notifyCourtUnassigned($court, $tournament, (int) $oldRefereeId, $actorId);
            }
            if ($court->referee_user_id) {
                $this->notifier->notifyCourtAssigned($court, $tournament, (int) $court->referee_user_id, $actorId);
            }
        }

        return $court->fresh('referee:id,full_name,avatar_url');
    }

    /**
     * @throws \RuntimeException with code 409 when court has live match
     */
    public function deleteCourt(TournamentCourt $court, int $actorId): void
    {
        $hasLiveMatch = Matches::where('court_id', $court->id)
            ->where('status', '!=', Matches::STATUS_COMPLETED)
            ->whereIn('live_status', [
                Matches::LIVE_STATUS_PLAYING,
                Matches::LIVE_STATUS_TIMEOUT,
                Matches::LIVE_STATUS_BETWEEN_SETS,
            ])
            ->exists();

        if ($hasLiveMatch) {
            throw new \RuntimeException('COURT_HAS_LIVE_MATCH', 409);
        }

        $oldRefereeId = $court->referee_user_id;
        $tournament = $court->tournament;

        DB::transaction(function () use ($court) {
            CourtAssignment::where('court_id', $court->id)->delete();

            Matches::where('court_id', $court->id)
                ->where('status', '!=', Matches::STATUS_COMPLETED)
                ->update([
                    'court_id' => null,
                    'court' => null,
                    'priority_at' => null,
                ]);

            $court->delete();
        });

        if ($oldRefereeId) {
            $this->notifier->notifyCourtUnassigned($court, $tournament, (int) $oldRefereeId, $actorId);
        }
    }

    /**
     * @throws \RuntimeException with code 409 when target tournament has live match on its courts
     */
    public function copyFromTournament(Tournament $target, Tournament $source, int $actorId): array
    {
        $existingCourtIds = $target->courts()->pluck('id');
        if ($existingCourtIds->isNotEmpty()) {
            $hasLiveMatch = Matches::whereIn('court_id', $existingCourtIds)
                ->where('status', '!=', Matches::STATUS_COMPLETED)
                ->whereIn('live_status', [
                    Matches::LIVE_STATUS_PLAYING,
                    Matches::LIVE_STATUS_TIMEOUT,
                    Matches::LIVE_STATUS_BETWEEN_SETS,
                ])
                ->exists();

            if ($hasLiveMatch) {
                throw new \RuntimeException('COURT_HAS_LIVE_MATCH', 409);
            }
        }

        $targetRefereeIds = TournamentStaff::where('tournament_id', $target->id)
            ->where('role', TournamentStaff::ROLE_REFEREE)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        $sourceCourts = $source->courts()->get();

        DB::transaction(function () use ($target, $existingCourtIds, $sourceCourts, $targetRefereeIds) {
            if ($existingCourtIds->isNotEmpty()) {
                CourtAssignment::where('tournament_id', $target->id)->delete();
                Matches::whereIn('court_id', $existingCourtIds)
                    ->where('status', '!=', Matches::STATUS_COMPLETED)
                    ->update(['court_id' => null, 'priority_at' => null]);
                TournamentCourt::whereIn('id', $existingCourtIds)->delete();
            }

            foreach ($sourceCourts as $src) {
                $refereeId = $src->referee_user_id && isset($targetRefereeIds[(int) $src->referee_user_id])
                    ? (int) $src->referee_user_id
                    : null;

                TournamentCourt::create([
                    'tournament_id' => $target->id,
                    'name' => $src->name,
                    'sort_order' => $src->sort_order,
                    'referee_user_id' => $refereeId,
                ]);
            }
        });

        return $this->listCourts($target->fresh());
    }

    public function getTournamentReferees(Tournament $tournament): array
    {
        $currentUserId = auth()->id();

        $refereeUsers = TournamentStaff::with('user:id,full_name,avatar_url')
            ->where('tournament_id', $tournament->id)
            ->where('role', TournamentStaff::ROLE_REFEREE)
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();

        $courtsByRef = $tournament->courts()
            ->whereNotNull('referee_user_id')
            ->get()
            ->groupBy('referee_user_id');

        return $refereeUsers->map(function ($user) use ($courtsByRef, $currentUserId) {
            $assigned = $courtsByRef->get($user->id, collect());
            return [
                'user_id' => $user->id,
                'name' => $user->full_name,
                'avatar_url' => $user->avatar_url,
                'initials' => $this->makeInitials($user->full_name),
                'is_me' => $currentUserId !== null && (int) $user->id === (int) $currentUserId,
                'court_ids' => $assigned->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ];
        })->sortBy(fn ($r) => empty($r['court_ids']) ? 0 : 1)->values()->all();
    }

    public function formatCourt(TournamentCourt $court): array
    {
        return [
            'id' => $court->id,
            'tournament_id' => $court->tournament_id,
            'name' => $court->name,
            'order' => $court->sort_order,
            'referee' => $court->referee ? [
                'user_id' => $court->referee->id,
                'name' => $court->referee->full_name,
                'avatar_url' => $court->referee->avatar_url,
                'initials' => $this->makeInitials($court->referee->full_name),
            ] : null,
        ];
    }

    public function makeInitials(?string $name): string
    {
        if (!$name) {
            return '';
        }

        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return '';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        return mb_strtoupper(
            mb_substr($parts[count($parts) - 2], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1)
        );
    }

    private function generateNextCourtName(Tournament $tournament): string
    {
        $existing = $tournament->courts()->pluck('name')->all();
        $n = 1;
        while (in_array("Sân {$n}", $existing, true)) {
            $n++;
        }

        return "Sân {$n}";
    }

    private function ensureUniqueName(int $tournamentId, string $name, ?int $ignoreId = null): void
    {
        $exists = TournamentCourt::where('tournament_id', $tournamentId)
            ->where('name', $name)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ["Tên sân '{$name}' đã tồn tại trong giải đấu."],
            ]);
        }
    }
}
