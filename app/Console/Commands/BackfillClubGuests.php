<?php

namespace App\Console\Commands;

use App\Models\Club\Club;
use App\Models\Club\ClubGuest;
use App\Models\Club\ClubMember;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\Participant;
use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Backfill club_guests từ participants/mini_participants của Tournament và MiniTournament
 * thuộc về mỗi CLB. Bỏ qua user đã là (hoặc đã từng là) member của CLB đó.
 *
 * Usage:
 *   php artisan clubs:backfill-guests                  # toàn bộ CLB
 *   php artisan clubs:backfill-guests --club=7        # 1 CLB
 *   php artisan clubs:backfill-guests --dry-run       # chỉ xem, không ghi
 *   php artisan clubs:backfill-guests --only-finished # chỉ lấy tournament CLOSED / mini CLOSED
 */
class BackfillClubGuests extends Command
{
    protected $signature = 'clubs:backfill-guests
        {--club= : ID CLB cụ thể (mặc định: tất cả)}
        {--dry-run : Chỉ thống kê, không ghi DB}
        {--only-finished : Chỉ lấy tournament status=CLOSED và mini status=CLOSED}';

    protected $description = 'Backfill bảng club_guests từ participants/mini_participants của tournament thuộc CLB';

    public function handle(): int
    {
        $clubIdOpt = $this->option('club');
        $dryRun = (bool) $this->option('dry-run');
        $onlyFinished = (bool) $this->option('only-finished');

        $clubsQuery = Club::query();
        if ($clubIdOpt) {
            $clubsQuery->where('id', (int) $clubIdOpt);
        }
        $clubs = $clubsQuery->orderBy('id')->get();

        if ($clubs->isEmpty()) {
            $this->warn('Khong co CLB nao.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Backfill club_guests cho %d CLB (dry-run=%s, only-finished=%s)',
            $clubs->count(),
            $dryRun ? 'yes' : 'no',
            $onlyFinished ? 'yes' : 'no'
        ));
        $this->newLine();

        $totalInserted = 0;
        $totalIncremented = 0;
        $totalSkippedMembers = 0;

        foreach ($clubs as $club) {
            $stats = $this->processClub($club, $dryRun, $onlyFinished);
            $totalInserted += $stats['inserted'];
            $totalIncremented += $stats['incremented'];
            $totalSkippedMembers += $stats['skipped_members'];

            $this->line(sprintf(
                '  CLB #%d "%s" — insert=%d, increment=%d, skip-member=%d',
                $club->id,
                $club->name,
                $stats['inserted'],
                $stats['incremented'],
                $stats['skipped_members']
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            '=== Hoan tat: insert=%d, increment=%d, skip-member=%d ===',
            $totalInserted,
            $totalIncremented,
            $totalSkippedMembers
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{inserted:int, incremented:int, skipped_members:int}
     */
    protected function processClub(Club $club, bool $dryRun, bool $onlyFinished): array
    {
        $tournamentIds = $club->tournaments()->pluck('id')->all();
        $miniIds = $club->miniTournaments()->pluck('id')->all();

        if (empty($tournamentIds) && empty($miniIds)) {
            return ['inserted' => 0, 'incremented' => 0, 'skipped_members' => 0];
        }

        $memberUserIds = ClubMember::withTrashed()
            ->where('club_id', $club->id)
            ->whereIn('membership_status', ['joined', 'left'])
            ->pluck('user_id')
            ->all();

        $merged = [];
        if (!empty($tournamentIds)) {
            $this->mergeInto(
                $merged,
                $this->buildTournamentQuery($tournamentIds, $memberUserIds, $onlyFinished)
            );
        }
        if (!empty($miniIds)) {
            $this->mergeInto(
                $merged,
                $this->buildMiniQuery($miniIds, $memberUserIds, $onlyFinished)
            );
        }

        if (empty($merged)) {
            return ['inserted' => 0, 'incremented' => 0, 'skipped_members' => count($memberUserIds)];
        }

        $existing = ClubGuest::where('club_id', $club->id)
            ->whereIn('user_id', array_keys($merged))
            ->get()
            ->keyBy('user_id');

        $now = now();
        $rowsToInsert = [];
        $rowsToUpdate = [];
        $inserted = 0;
        $incremented = 0;

        foreach ($merged as $userId => $info) {
            $existingRow = $existing->get($userId);
            $firstAt = $this->toCarbon($info['first']);
            $lastAt = $this->toCarbon($info['last']);

            if (!$existingRow) {
                $rowsToInsert[] = [
                    'club_id' => $club->id,
                    'user_id' => $userId,
                    'play_count' => $info['count'],
                    'first_played_at' => $firstAt,
                    'last_played_at' => $lastAt,
                    'is_invited' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $inserted++;
                continue;
            }

            $existingLast = $this->toCarbon($existingRow->last_played_at);
            $existingFirst = $this->toCarbon($existingRow->first_played_at);
            $existingCount = (int) $existingRow->play_count;

            $newLast = $existingLast === null || $lastAt->gt($existingLast) ? $lastAt : $existingLast;
            $newFirst = $existingFirst === null || $firstAt->lt($existingFirst) ? $firstAt : $existingFirst;
            $newCount = max($existingCount, $info['count']);

            $dirty = !$newLast->equalTo($existingLast)
                || !$newFirst->equalTo($existingFirst)
                || $newCount !== $existingCount;

            if ($dirty) {
                $rowsToUpdate[] = [
                    'id' => $existingRow->id,
                    'play_count' => $newCount,
                    'first_played_at' => $newFirst,
                    'last_played_at' => $newLast,
                    'updated_at' => $now,
                ];
                $incremented++;
            }
        }

        if (!$dryRun) {
            if (!empty($rowsToInsert)) {
                ClubGuest::insert($rowsToInsert);
            }
            foreach ($rowsToUpdate as $row) {
                ClubGuest::where('id', $row['id'])->update([
                    'play_count' => $row['play_count'],
                    'first_played_at' => $row['first_played_at'],
                    'last_played_at' => $row['last_played_at'],
                    'updated_at' => $row['updated_at'],
                ]);
            }
        }

        return [
            'inserted' => $inserted,
            'incremented' => $incremented,
            'skipped_members' => count($memberUserIds),
        ];
    }

    /**
     * Build tournament participant query — fresh builder moi lan goi.
     */
    protected function buildTournamentQuery(array $tournamentIds, array $memberUserIds, bool $onlyFinished)
    {
        $q = Participant::whereIn('tournament_id', $tournamentIds)
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', $memberUserIds);

        if ($onlyFinished) {
            $q->whereHas('tournament', fn ($qq) => $qq->where('status', Tournament::CLOSED));
        }
        return $q;
    }

    protected function buildMiniQuery(array $miniIds, array $memberUserIds, bool $onlyFinished)
    {
        $q = MiniParticipant::whereIn('mini_tournament_id', $miniIds)
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', $memberUserIds);

        if ($onlyFinished) {
            $q->whereHas('miniTournament', fn ($qq) => $qq->where('status', MiniTournament::STATUS_CLOSED));
        }
        return $q;
    }

    /**
     * Lay N participants to build query, aggregate (count, first, last) theo user_id,
     * roi merge vao $merged.
     *
     * @param  array<int, array{count:int, first:?Carbon, last:?Carbon}>  $merged
     */
    protected function mergeInto(array &$merged, $query): void
    {
        $driver = DB::connection()->getDriverName();

        $rows = $query
            ->selectRaw("user_id, COUNT(*) as cnt, MIN(created_at) as first_at, MAX(created_at) as last_at")
            ->groupBy('user_id')
            ->get();

        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            $cnt = (int) $r->cnt;
            $firstAt = $r->first_at ? Carbon::parse($r->first_at) : null;
            $lastAt = $r->last_at ? Carbon::parse($r->last_at) : null;

            if (!isset($merged[$uid])) {
                $merged[$uid] = ['count' => $cnt, 'first' => $firstAt, 'last' => $lastAt];
                continue;
            }

            $merged[$uid]['count'] += $cnt;
            if ($firstAt && (!$merged[$uid]['first'] || $firstAt->lt($merged[$uid]['first']))) {
                $merged[$uid]['first'] = $firstAt;
            }
            if ($lastAt && (!$merged[$uid]['last'] || $lastAt->gt($merged[$uid]['last']))) {
                $merged[$uid]['last'] = $lastAt;
            }
        }
    }

    protected function toCarbon($v): ?Carbon
    {
        if ($v === null || $v === '') return null;
        return $v instanceof Carbon ? $v->copy() : Carbon::parse($v);
    }
}