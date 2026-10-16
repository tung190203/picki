<?php

namespace App\Services;

use App\Events\SuperAdmin\TournamentMatchUpdated;
use App\Models\MatchResult;
use App\Models\MatchStage;
use App\Models\MatchStageMember;
use App\Models\Matches;
use App\Models\TeamMember;
use App\Models\TournamentType;
use App\Models\User;
use App\Models\VnduprHistory;
use App\Services\VnduprRatingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchStageService
{
    public function __construct(
        protected TournamentScoringTypeInferrer $scoringInferrer
    ) {}

    /**
     * Upsert stages for a rally match.
     *
     * @param Matches $match Match đã load kèm tournamentType.tournament.
     * @param array<int, array<string, mixed>> $stagesPayload Mảng các stage từ client, key: stage_no, cumulative_score_a, cumulative_score_b, members.
     * @param int $actorUserId User thực hiện (chỉ để log/audit; auth đã check ở controller).
     * @return \Illuminate\Support\Collection MatchStage collection vừa ghi.
     *
     * @throws ValidationException Khi validate thất bại.
     */
    public function upsertStages(Matches $match, array $stagesPayload, int $actorUserId): \Illuminate\Support\Collection
    {
        $this->loadMatchContext($match);

        if ($match->status === Matches::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'stages' => ['Trận đấu đã hoàn thành, không thể cập nhật stage.'],
            ]);
        }

        // Sắp xếp theo stage_no và validate từng stage
        usort($stagesPayload, fn ($a, $b) => ($a['stage_no'] ?? 0) <=> ($b['stage_no'] ?? 0));

        $stagePoints = $this->resolveStagePoints($match);
        $maxStages = count($stagePoints);

        // Validate: stage đầu tiên phải là stage_no=1
        if (empty($stagesPayload) || (int) $stagesPayload[0]['stage_no'] !== 1) {
            throw ValidationException::withMessages([
                'stages' => ['Phải bắt đầu từ stage_no=1.'],
            ]);
        }

        $validated = [];
        $prevCumulativeA = 0;
        $prevCumulativeB = 0;

        foreach ($stagesPayload as $idx => $stage) {
            $stageNo = (int) ($stage['stage_no'] ?? 0);
            $cumA = (int) ($stage['cumulative_score_a'] ?? 0);
            $cumB = (int) ($stage['cumulative_score_b'] ?? 0);

            if ($stageNo < 1 || $stageNo > $maxStages) {
                throw ValidationException::withMessages([
                    "stages.{$idx}.stage_no" => ["stage_no phải trong khoảng 1..{$maxStages}."],
                ]);
            }

            if ($cumA < 0 || $cumB < 0) {
                throw ValidationException::withMessages([
                    "stages.{$idx}.cumulative_score_a" => ["Điểm lũy kế phải ≥ 0."],
                ]);
            }

            $target = (int) $stagePoints[$stageNo - 1];
            if ($cumA > $target || $cumB > $target) {
                throw ValidationException::withMessages([
                    "stages.{$idx}.cumulative_score_a" => ["Điểm lũy kế vượt mốc chặng {$stageNo} ({$target})."],
                ]);
            }

            // Cấm gửi stage n+1 trước khi stage n đạt target (status completed).
            // Ngoại lệ: stage 1 không có prev.
            // Ngoại lệ 2: stage này chưa ghi (cumA=0 && cumB=0) → coi như placeholder, bỏ qua validate.
            $isPlaceholder = $cumA === 0 && $cumB === 0;
            if ($idx > 0 && !$isPlaceholder) {
                $prev = $validated[$idx - 1];
                $prevTarget = (int) $stagePoints[$prev['stage_no'] - 1];
                $prevReached = $prev['cumulative_score_a'] === $prevTarget
                    || $prev['cumulative_score_b'] === $prevTarget;
                if (!$prevReached) {
                    throw ValidationException::withMessages([
                        "stages.{$idx}.stage_no" => ["Chặng {$stageNo} chỉ ghi được sau khi chặng {$prev['stage_no']} chạm mốc."],
                    ]);
                }
            }

            // ponytail: cumulative_score_* is the per-stage score (delta), not a
            // running total. Earlier code compared cum vs prevCum and rejected
            // "0 < 20" as a decrease, but that's the natural next-stage state
            // (the team that lost simply scored 0). Treat cum == stage_score.
            $stageA = $isPlaceholder ? 0 : $cumA;
            $stageB = $isPlaceholder ? 0 : $cumB;

            if (!$isPlaceholder && $cumA === $target && $cumB === $target) {
                throw ValidationException::withMessages([
                    "stages.{$idx}.cumulative_score_a" => ["Cả hai đội cùng chạm mốc — không hợp lệ."],
                ]);
            }

            $reached = $isPlaceholder ? false : (($cumA === $target) || ($cumB === $target));
            $validated[] = [
                'stage_no' => $stageNo,
                'cumulative_score_a' => $cumA,
                'cumulative_score_b' => $cumB,
                'stage_score_a' => $stageA,
                'stage_score_b' => $stageB,
                'target_point' => $target,
                'status' => $reached ? MatchStage::STATUS_COMPLETED : MatchStage::STATUS_PENDING,
                'completed_at' => $reached ? now() : null,
                'members' => $stage['members'] ?? null,
            ];

            $prevCumulativeA = $cumA;
            $prevCumulativeB = $cumB;
        }

        return DB::transaction(function () use ($match, $validated) {
            // Lock match + load lại stages để idempotent
            $lockedMatch = Matches::lockForUpdate()->find($match->id);
            if ($lockedMatch->status === Matches::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'stages' => ['Trận đấu vừa hoàn thành trong lúc xử lý.'],
                ]);
            }

            $savedStages = collect();
            foreach ($validated as $row) {
                $existing = MatchStage::where('match_id', $match->id)
                    ->where('stage_no', $row['stage_no'])
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->isCompleted()) {
                    // Phase 3: stage đã completed → không cho ghi đè.
                    // Phase 4 sẽ xử lý luồng sửa kết quả.
                    $savedStages->push($existing);
                    continue;
                }

                $stageWinner = null;
                if ($row['status'] === MatchStage::STATUS_COMPLETED) {
                    $stageWinner = $row['cumulative_score_a'] > $row['cumulative_score_b']
                        ? $match->home_team_id
                        : $match->away_team_id;
                }

                $stage = MatchStage::updateOrCreate(
                    ['match_id' => $match->id, 'stage_no' => $row['stage_no']],
                    [
                        'cumulative_score_a' => $row['cumulative_score_a'],
                        'cumulative_score_b' => $row['cumulative_score_b'],
                        'stage_score_a' => $row['stage_score_a'],
                        'stage_score_b' => $row['stage_score_b'],
                        'status' => $row['status'],
                        'winner_team_id' => $stageWinner,
                        'target_point' => $row['target_point'],
                        'completed_at' => $row['completed_at'],
                    ],
                );

                $this->syncStageMembers($stage, $row['members'], $match);

                // Phase 4: trigger VNDUPR rating cho stage completed (idempotent).
                $this->applyStageRatingIfReady($stage, $match);

                // Mirror vào MatchResult để tương thích code cũ (standings, VNDUPR)
                $this->mirrorToMatchResult($stage, $match);

                $savedStages->push($stage);
            }

            // Quyết định trạng thái trận
            $this->maybeDecideMatchCompletion($match);

            // Re-fetch match từ DB để lấy attributes mới (winner_id, status, ...).
            // Dùng fresh() thay vì refresh() để tránh eager-load relations cũ (đã load từ controller)
            // mà có thể trả null khi DB state đã đổi.
            $fresh = \App\Models\Matches::with(['stages.members.user'])->find($match->id);
            if ($fresh) {
                $match->setRawAttributes($fresh->getAttributes(), true);
                $match->setRelations($fresh->getRelations());
            }

            return $savedStages;
        });
    }

    /**
     * Lấy stage_points từ match_rules; fallback default nếu thiếu.
     *
     * @return array<int, int>
     */
    protected function resolveStagePoints(Matches $match): array
    {
        $rules = $match->tournamentType->match_rules ?? [];
        $raw = $rules['stage_points'] ?? null;

        if (!is_array($raw) || empty($raw)) {
            return $this->scoringInferrer->defaultStagePoints();
        }

        // Dùng validateStagePoints để đảm bảo tăng nghiêm ngặt
        return $this->scoringInferrer->validateStagePoints($raw);
    }

    /**
     * Validate match phải là rally, có 2 đội, đủ member.
     */
    protected function loadMatchContext(Matches $match): void
    {
        $match->loadMissing('tournamentType.tournament');

        $tournament = $match->tournamentType?->tournament;
        if (!$tournament) {
            throw ValidationException::withMessages([
                'match' => ['Không tìm thấy tournament cho trận đấu này.'],
            ]);
        }

        $scoringType = $match->tournamentType->match_rules['scoring_type'] ?? null;
        if ($scoringType !== TournamentScoringTypeInferrer::RALLY) {
            throw ValidationException::withMessages([
                'match' => ['Trận đấu này không dùng Rally Scoring (player_per_team=2).'],
            ]);
        }

        if (!$match->home_team_id || !$match->away_team_id) {
            throw ValidationException::withMessages([
                'match' => ['Trận đấu chưa có đủ 2 đội.'],
            ]);
        }
    }

    /**
     * Sync members cho một stage.
     * - Nếu client gửi members: validate từng user_id thuộc đúng team.
     * - Nếu client KHÔNG gửi: KHÔNG auto-fill — để mảng rỗng. Phase 3 trước đây
     *   auto-fill toàn bộ team_members, nhưng điều đó làm stage 2/3 "ăn theo"
     *   danh sách member của stage 1 (mặc dù stage 1 mới là stage user thật sự
     *   chọn) — gây hiểu nhầm. Behavior mới: members phản ánh đúng intent của user.
     */
    protected function syncStageMembers(MatchStage $stage, ?array $membersPayload, Matches $match): void
    {
        $homeTeamId = $match->home_team_id;
        $awayTeamId = $match->away_team_id;

        $rows = [];

        if (is_array($membersPayload) && !empty($membersPayload)) {
            // Validate từng user phải thuộc đúng team
            $seen = [];
            foreach ($membersPayload as $idx => $m) {
                $userId = (int) ($m['user_id'] ?? 0);
                $teamId = (int) ($m['team_id'] ?? 0);
                if (!in_array($teamId, [$homeTeamId, $awayTeamId], true)) {
                    throw ValidationException::withMessages([
                        "members.{$idx}.team_id" => ["team_id không thuộc trận đấu."],
                    ]);
                }
                if ($userId <= 0) {
                    throw ValidationException::withMessages([
                        "members.{$idx}.user_id" => ["user_id bắt buộc."],
                    ]);
                }
                $exists = TeamMember::where('team_id', $teamId)
                    ->where('user_id', $userId)
                    ->exists();
                if (!$exists) {
                    throw ValidationException::withMessages([
                        "members.{$idx}.user_id" => ["User {$userId} không thuộc team {$teamId}."],
                    ]);
                }
                $key = "{$stage->id}:{$userId}";
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $rows[] = [
                    'match_stage_id' => $stage->id,
                    'team_id' => $teamId,
                    'user_id' => $userId,
                    'role' => $m['role'] ?? 'player',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        // ponytail: nếu membersPayload null/empty → KHÔNG auto-fill. Stage đó
        // đơn giản là chưa được gán member nào. Caller chịu trách nhiệm gửi
        // members khi muốn gán — không ngầm đoán.

        // Replace
        MatchStageMember::where('match_stage_id', $stage->id)->delete();
        if (!empty($rows)) {
            MatchStageMember::insert($rows);
        }
    }

    /**
     * Mirror stage_score sang MatchResult (set_number=stage_no) để tương thích code cũ.
     * KHÔNG tính won_match ở đây — để decideMatchCompletion xử lý khi match hoàn thành.
     *
     * LƯU Ý: match_results KHÔNG còn cột participant_id (đã drop từ 2025-10-15).
     * Unique key hiện tại là (match_id, team_id, set_number).
     */
    protected function mirrorToMatchResult(MatchStage $stage, Matches $match): void
    {
        if ($stage->stage_no < 1) {
            return;
        }
        $homeTeamId = $match->home_team_id;
        $awayTeamId = $match->away_team_id;

        MatchResult::updateOrCreate(
            ['match_id' => $match->id, 'team_id' => $homeTeamId, 'set_number' => $stage->stage_no],
            [
                'score' => $stage->stage_score_a,
                'team_score' => $stage->stage_score_a,
                'opponent_score' => $stage->stage_score_b,
                'serving_position' => 0,
                'won_match' => false,
            ],
        );
        MatchResult::updateOrCreate(
            ['match_id' => $match->id, 'team_id' => $awayTeamId, 'set_number' => $stage->stage_no],
            [
                'score' => $stage->stage_score_b,
                'team_score' => $stage->stage_score_b,
                'opponent_score' => $stage->stage_score_a,
                'serving_position' => 0,
                'won_match' => false,
            ],
        );
    }

    /**
     * Lấy hoặc tạo 1 Participant row đại diện cho team ở stage_no cụ thể.
     * Trả về participant_id.
     *
     * LƯU Ý: schema match_results có unique (match_id, participant_id) cũ
     * mà migration 2025_10_15 đã cố drop nhưng đôi khi vẫn còn (silent fail).
     * Để tránh vi phạm unique, mỗi (team, stage) có participant riêng.
     */
    protected function ensureParticipant(Matches $match, int $teamId, int $stageNo): int
    {
        $tournament = $match->tournamentType?->tournament;
        $key = '__stage_team_' . $teamId . '_' . $stageNo;
        $existing = \App\Models\Participant::where('tournament_id', $tournament->id)
            ->where('guest_name', $key)
            ->first();
        if ($existing) {
            return $existing->id;
        }
        $created = \App\Models\Participant::create([
            'tournament_id' => $tournament->id,
            'is_guest' => true,
            'guest_name' => $key,
        ]);
        return $created->id;
    }

    /**
     * Phase 4: trigger tính VNDUPR cho stage vừa completed.
     *
     * - Chỉ chạy khi stage.status === completed.
     * - Idempotent: nếu đã có VnduprHistory row cho stage → skip.
     * - Lấy members THỰC TẾ từ match_stage_members (chỉ user thật, không guest).
     * - Dùng stage_score (không phải cumulative) làm score input.
     * - Skip nếu trận có guest (giữ rule cũ của processMatchCompletionBig).
     */
    protected function applyStageRatingIfReady(MatchStage $stage, Matches $match): void
    {
        if (!$stage->isCompleted()) {
            return;
        }
        if (VnduprHistory::where('match_stage_id', $stage->id)->exists()) {
            return; // idempotent
        }

        $homeUserIds = $stage->members()
            ->where('team_id', $match->home_team_id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $awayUserIds = $stage->members()
            ->where('team_id', $match->away_team_id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($homeUserIds) && empty($awayUserIds)) {
            return;
        }

        // Skip nếu có guest trong members (giữ rule cũ)
        $hasGuest = \App\Models\TeamMember::whereIn('team_id', [$match->home_team_id, $match->away_team_id])
            ->whereIn('user_id', array_merge($homeUserIds, $awayUserIds))
            ->whereNotNull('participant_id')
            ->whereNull('user_id')
            ->exists();
        if ($hasGuest) {
            return;
        }

        $tournament = $match->tournamentType?->tournament;
        $sportId = (int) ($tournament->sport_id ?? 0);
        if ($sportId <= 0) {
            return;
        }

        app(VnduprRatingEngine::class)->computeAndApply([
            'home_member_user_ids' => $homeUserIds,
            'away_member_user_ids' => $awayUserIds,
            'home_team_id' => (int) $match->home_team_id,
            'away_team_id' => (int) $match->away_team_id,
            'home_score' => (int) $stage->stage_score_a,
            'away_score' => (int) $stage->stage_score_b,
            'winner_team_id' => $stage->winner_team_id,
            'match_id' => (int) $match->id,
            'match_stage_id' => (int) $stage->id,
            'sport_id' => $sportId,
        ]);
    }

    /**
     * Phase 4: Sửa kết quả 1 stage đã completed. Reverse rating cũ, cập nhật stage, recompute.
     *
     * Flow:
     *  1. lockForUpdate match + stage.
     *  2. engine.reverse($matchId, $stageId) undo rating cũ.
     *  3. update stage với payload mới (validate giống upsertStages).
     *  4. Re-tính rating nếu stage.status vẫn completed.
     *
     * Chỉ được sửa stage khi match.status chưa COMPLETED tổng. Sau khi tất cả stage
     * completed thì match auto COMPLETED; revision ở đây xử lý được cả khi đó.
     *
     * @param array $newPayload ['cumulative_score_a', 'cumulative_score_b', 'members'?]
     */
    public function reviseStage(Matches $match, int $stageNo, array $newPayload, int $actorUserId): MatchStage
    {
        $this->loadMatchContext($match);

        return DB::transaction(function () use ($match, $stageNo, $newPayload) {
            $lockedMatch = Matches::lockForUpdate()->find($match->id);
            $stage = MatchStage::where('match_id', $lockedMatch->id)
                ->where('stage_no', $stageNo)
                ->lockForUpdate()
                ->firstOrFail();

            // Reverse rating cũ của stage này (nếu có)
            if (VnduprHistory::where('match_stage_id', $stage->id)->exists()) {
                app(VnduprRatingEngine::class)->reverse($lockedMatch->id, $stage->id);
            }

            // Validate payload mới (tái sử dụng logic upsert)
            $cumA = (int) ($newPayload['cumulative_score_a'] ?? 0);
            $cumB = (int) ($newPayload['cumulative_score_b'] ?? 0);
            if ($cumA < 0 || $cumB < 0) {
                throw ValidationException::withMessages([
                    'cumulative_score_a' => ['Điểm lũy kế phải ≥ 0.'],
                ]);
            }
            $stagePoints = $this->resolveStagePoints($lockedMatch);
            $target = (int) $stagePoints[$stage->stage_no - 1];
            if ($cumA > $target || $cumB > $target) {
                throw ValidationException::withMessages([
                    'cumulative_score_a' => ["Vượt mốc chặng {$stage->stage_no} ({$target})."],
                ]);
            }

            // ponytail: cumulative_score_* is the per-stage score (delta). See
            // validateAndBuild() for the same reasoning. Drop the
            // "≥ prev cumulative" check that rejected valid 0s on the
            // losing side of a new stage.
            $stageScoreA = $cumA;
            $stageScoreB = $cumB;
            if ($cumA === $target && $cumB === $target) {
                throw ValidationException::withMessages([
                    'cumulative_score_a' => ['Cả hai đội cùng chạm mốc — không hợp lệ.'],
                ]);
            }

            $reached = ($cumA === $target) || ($cumB === $target);
            $stageWinner = $reached
                ? ($cumA > $cumB ? $lockedMatch->home_team_id : $lockedMatch->away_team_id)
                : null;

            $stage->update([
                'cumulative_score_a' => $cumA,
                'cumulative_score_b' => $cumB,
                'stage_score_a' => $stageScoreA,
                'stage_score_b' => $stageScoreB,
                'status' => $reached ? MatchStage::STATUS_COMPLETED : MatchStage::STATUS_PENDING,
                'winner_team_id' => $stageWinner,
                'completed_at' => $reached ? ($stage->completed_at ?? now()) : null,
            ]);

            // Nếu payload có members thì re-sync
            if (array_key_exists('members', $newPayload)) {
                $this->syncStageMembers($stage, $newPayload['members'], $lockedMatch);
            }

            // Tính lại rating nếu stage completed
            $this->applyStageRatingIfReady($stage->fresh(), $lockedMatch);

            // Recompute match completion + re-evaluate winner
            $this->maybeDecideMatchCompletion($lockedMatch);

            return $stage->fresh();
        });
    }

    /**
     * Nếu TẤT CẢ stages đã hoàn thành → set match.status=completed, winner_id.
     * Đánh won_match cho MatchResult để ranking/VNDUPR có thể chạy.
     *
     * Quy tắc: chỉ complete khi số stage đã ghi bằng đúng số stage_points dự kiến
     * VÀ tất cả chúng đều status=completed. Nếu thiếu stage → vẫn để pending.
     */
    public function maybeDecideMatchCompletion(Matches $match): void
    {
        $expectedStages = $this->resolveStagePoints($match);
        $stages = MatchStage::where('match_id', $match->id)->orderBy('stage_no')->get();
        if ($stages->isEmpty() || $stages->count() !== count($expectedStages)) {
            return;
        }

        foreach ($stages as $s) {
            if (!$s->isCompleted()) {
                return;
            }
        }

        $last = $stages->last();
        $winnerId = $last->cumulative_score_a > $last->cumulative_score_b
            ? $match->home_team_id
            : $match->away_team_id;

        $match->status = Matches::STATUS_COMPLETED;
        $match->winner_id = $winnerId;
        $match->save();

        // Cập nhật won_match cho MatchResult theo winner
        $stages->each(function (MatchStage $s) use ($match, $winnerId) {
            MatchResult::where('match_id', $match->id)
                ->where('set_number', $s->stage_no)
                ->update(['won_match' => DB::raw("team_id = {$winnerId}"), 'confirmed' => true]);
        });

        TournamentMatchUpdated::dispatch($match);
    }
}
