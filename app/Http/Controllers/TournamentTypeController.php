<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\TournamentTypeResource;
use App\Models\Group;
use App\Models\Matches;
use App\Models\PoolAdvancementRule;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Services\TournamentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentTypeController extends Controller
{
    /**
     * ============================================================================
     * DEPENDENCY INJECTION - Services
     * ============================================================================
     */
    public function __construct(
        private \App\Services\TournamentType\MatchGeneratorService $matchGenerator,
        private \App\Services\TournamentType\BracketService $bracketService,
        private \App\Services\TournamentType\StandingsService $standingsService,
        private \App\Services\TournamentType\TeamPairingService $teamPairingService
    ) {}

 /**
  * ============================================================================
  * CONSTANTS - Thêm vào đầu class TournamentTypeController
  * ============================================================================
  */
const PAIRING_MODE_SEQUENTIAL = 'sequential';  // Tuần tự: A-B, C-D, E-F, G-H
const PAIRING_MODE_SYMMETRIC = 'symmetric';    // Đối xứng: A-H, B-G, C-F, D-E
const PAIRING_MODE_MANUAL = 'manual';
    /**
     * Tạo mới một thể thức cho giải đấu
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tournament_id' => 'required|integer|exists:tournaments,id',
            'format' => 'required|integer|in:' . implode(',', TournamentType::FORMATS),
            'num_legs' => 'integer|nullable|in:' . implode(',', TournamentType::NUM_LEGS_OPTIONS),
            'match_rules' => 'array|nullable',
            'format_specific_config' => 'array|nullable',
            'rules' => 'string|nullable',
            'rules_file_path' => 'string|nullable',
        ]);

        $tournament = Tournament::withFullRelations()->find($validated['tournament_id']);
        if($tournament->teams()->count() < 2) {
            return ResponseHelper::error('Cần có ít nhất 2 đội tham gia để tạo thể thức', 422);
        }

        $poolStage = $validated['format_specific_config']['pool_stage'] ?? null;
        if ($poolStage) {
            $numCompeting = (int) ($poolStage['number_competing_teams'] ?? 0);
            $numAdvancing = (int) ($poolStage['num_advancing_teams'] ?? 0);

            if ($numCompeting < $numAdvancing) {
                return ResponseHelper::error('Số đội trong bảng phải > số đội đi tiếp', 422);
            }
        }

        $matchRules = $validated['match_rules'] ?? [];
        if($matchRules) {
            $setPerMatch = (int) ($matchRules['sets_per_match'] ?? 0);
            $winningRule = (int) ($matchRules['winning_rule'] ?? 0);
            if($setPerMatch > 0 && $winningRule > 0) {
                if($winningRule > $setPerMatch) {
                    return ResponseHelper::error('Quy tắc thắng phải nhỏ hơn số set trong trận', 422);
                }
            }
        }

        DB::beginTransaction();
        try {
            if ($request->hasFile('rules_file_path')) {
                $path = $request->file('rules_file_path')->store('tournament_rules', 'public');
                $validated['rules_file_path'] = $path;
            }
            $type = TournamentType::createWithFormat(
                $validated['tournament_id'],
                $validated['format'],
                [
                    'match_rules' => $validated['match_rules'] ?? [],
                    'format_specific_config' => $validated['format_specific_config'] ?? [],
                    'rules_file_path' => $validated['rules_file_path'] ?? null,
                ]
            );

            // rules không được create trực tiếp trong createWithFormat => set sau
            if (array_key_exists('rules', $validated)) {
                $type->rules = $validated['rules'];
                $type->save();
            }
            if (array_key_exists('num_legs', $validated)) {
                $type->num_legs = $validated['num_legs'];
                $type->save();
            }

            // ✅ TẠO BẢNG TRỐNG CHO FORMAT MIXED
            if ($type->format === TournamentType::FORMAT_MIXED) {
                $this->createEmptyGroups($type);
            } else {
                $this->autoGenerateMatches($type);
            }

            DB::commit();
            return ResponseHelper::success(new TournamentTypeResource($type), 'Tạo thể thức thành công');
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error('Lỗi khi tạo thể thức: ' . $e->getMessage(), 500);
        }
    }

    public function autoGenerateMatches(TournamentType $tournamentType)
    {
        $completedMatches = $tournamentType->matches()
            ->where('status', 'completed')
            ->exists();

        if ($completedMatches) {
            return ResponseHelper::error(
                'Không thể sắp xếp lại. Đã có trận đấu hoàn thành.',
                400
            );
        }

        DB::beginTransaction();
        try {
            // Xóa assignment và matches cũ (nếu có)
            foreach ($tournamentType->groups as $group) {
                $group->teams()->detach();
            }
            $tournamentType->matches()->each(function ($match) {
                $match->results()->delete();
                $match->delete();
            });
            if ($tournamentType->format == TournamentType::FORMAT_MIXED) {
                $tournamentType->advancementRules()->delete();
            }

            // ✅ GỌI HÀM GENERATE CŨ (TỰ ĐỘNG CHIA ĐỘI)
            $this->generateMatchesForType($tournamentType);

            DB::commit();
            return ResponseHelper::success(
                new TournamentTypeResource($tournamentType->fresh()),
                'Tự động tạo lịch thi đấu thành công'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error('Lỗi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * ✅ TẠO CÁC BẢNG TRỐNG DỰA VÀO CONFIG
     */
    protected function createEmptyGroups(TournamentType $type)
    {
        $config = $type->format_specific_config ?? [];
        $mainConfig = is_array($config) && isset($config[0]) ? $config[0] : [];
        $poolConfig = $mainConfig['pool_stage'] ?? [];
        $numGroups = max(1, (int)($poolConfig['number_competing_teams'] ?? 2));

        // Xóa groups cũ nếu có
        $type->groups()->delete();

        // Tạo groups mới
        for ($i = 0; $i < $numGroups; $i++) {
            $type->groups()->create([
                'name' => 'Bảng ' . chr(65 + $i) // A, B, C, D...
            ]);
        }
    }

    /**
     * Cập nhật thông tin & thông số thể thức hiện tại
     * - Chỉ merge thay đổi, không reset toàn bộ (trừ khi explicit)
     */
    public function update(Request $request, TournamentType $tournamentType)
    {
        $validated = $request->validate([
            'match_rules' => 'array|nullable',
            'format_specific_config' => 'array|nullable',
            'rules' => 'string|nullable',
            'rules_file_path' => 'string|nullable',
            'num_legs' => 'integer|nullable|in:' . implode(',', TournamentType::NUM_LEGS_OPTIONS),
        ]);

        $poolStage = $validated['format_specific_config']['pool_stage'] ?? null;
        if ($poolStage) {
            $numCompeting = (int) ($poolStage['number_competing_teams'] ?? 0);
            $numAdvancing = (int) ($poolStage['num_advancing_teams'] ?? 0);

            if ($numCompeting < $numAdvancing) {
                return ResponseHelper::error('Số đội trong bảng phải > số đội đi tiếp', 422);
            }
        }

        $matchRules = $validated['match_rules'] ?? [];
        if($matchRules) {
            $setPerMatch = (int) ($matchRules['sets_per_match'] ?? 0);
            $winningRule = (int) ($matchRules['winning_rule'] ?? 0);
            if($setPerMatch > 0 && $winningRule > 0) {
                if($winningRule > $setPerMatch) {
                    return ResponseHelper::error('Quy tắc thắng phải nhỏ hơn số set trong trận', 422);
                }
            }
        }

        DB::beginTransaction();
        try {
            // ✅ KIỂM TRA SỐ BẢNG CÓ THAY ĐỔI KHÔNG
            $oldNumGroups = $tournamentType->groups()->count();
            $newNumGroups = null;

            if (!empty($validated['format_specific_config'])) {
                $mainConfig = is_array($validated['format_specific_config']) && isset($validated['format_specific_config'][0])
                    ? $validated['format_specific_config'][0]
                    : $validated['format_specific_config'];
                $poolConfig = $mainConfig['pool_stage'] ?? [];
                $newNumGroups = max(1, (int)($poolConfig['number_competing_teams'] ?? 2));
            }

            // Xoá toàn bộ cấu hình cũ trước khi cập nhật
            $tournamentType->match_rules = [];
            $tournamentType->format_specific_config = [];

            // Ghi đè hoàn toàn config mới (nếu có)
            if (!empty($validated['match_rules'])) {
                $tournamentType->match_rules = $validated['match_rules'];
            }

            if (!empty($validated['format_specific_config'])) {
                $tournamentType->format_specific_config = $validated['format_specific_config'];
            }

            // Ghi đè rules và file path
            if (array_key_exists('rules', $validated)) {
                $tournamentType->rules = $validated['rules'];
            }

            if ($request->hasFile('rules_file_path')) {
                $path = $request->file('rules_file_path')->store('tournament_rules', 'public');
                $tournamentType->rules_file_path = $path;
            } elseif (array_key_exists('rules_file_path', $validated)) {
                $tournamentType->rules_file_path = $validated['rules_file_path'];
            }

            // Ghi đè số lượt đấu
            if (array_key_exists('num_legs', $validated)) {
                $tournamentType->num_legs = $validated['num_legs'];
            }

            $tournamentType->save();

            // ✅ NẾU SỐ BẢNG THAY ĐỔI -> TẠO LẠI BẢNG TRỐNG
            if ($tournamentType->format === TournamentType::FORMAT_MIXED &&
                $newNumGroups !== null &&
                $newNumGroups !== $oldNumGroups) {

                // Xóa assignment và matches cũ
                foreach ($tournamentType->groups as $group) {
                    $group->teams()->detach();
                }
                $tournamentType->matches()->each(function ($match) {
                    $match->results()->delete();
                    $match->delete();
                });

                // Tạo lại groups
                $this->createEmptyGroups($tournamentType);
            } else {
                // ✅ NẾU KHÔNG THAY ĐỔI SỐ BẢNG -> CHỈ REGENERATE MATCHES
                $this->generateMatchesForType($tournamentType);
            }

            DB::commit();
            return ResponseHelper::success(new TournamentTypeResource($tournamentType->fresh()), 'Cập nhật thể thức thành công');
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error('Lỗi khi cập nhật thể thức: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xem thông tin thể thức
     */
    public function show(TournamentType $tournamentType)
    {
        return ResponseHelper::success(new TournamentTypeResource($tournamentType));
    }

    /**
     * Xoá thể thức
     */
    public function destroy(TournamentType $tournamentType)
    {
        $completedMatches = $tournamentType->matches()
        ->where('status', 'completed')
        ->exists();

        if ($completedMatches) {
            return ResponseHelper::error(
                'Không thể chia lại cặp đấu. Đã có trận đấu hoàn thành thuộc thể thức này.',
                400
            );
        }

        $tournamentType->delete();

        return ResponseHelper::success('Xoá thể thức thành công');
    }

    protected function generateMatchesForType(TournamentType $type)
    {
        $type->matches()->delete();
        $teams = $type->tournament->teams()->with('members')->get();
        if (count($teams) < 2) {
            return;
        }

        $config = $type->format_specific_config ?? [];
        $numLegs = $type->num_legs ?? 1;

        // Chọn branch theo format
        switch ($type->format) {
            case TournamentType::FORMAT_ROUND_ROBIN:
                $this->generateRoundRobin($type, $teams, $numLegs);
                break;

            case TournamentType::FORMAT_ELIMINATION:
                $this->generateElimination($type, $teams, $config, $numLegs);
                break;

            case TournamentType::FORMAT_MIXED:
            default:
                $this->generateMixed($type, $teams, $config, $numLegs);
                break;
        }
    }

    private function generateRoundRobin(TournamentType $type, $teams, $numLegs)
    {
        $teamCount = count($teams);
        if ($teamCount < 2) return;
        $scheduleTeams = collect($teams)->pluck('id')->toArray();
        $isOdd = $teamCount % 2 !== 0;
        if ($isOdd) {
            $scheduleTeams[] = 'BYE';
            $teamCount++;
        }
        $totalRounds = $teamCount - 1;
        $matches = [];
        $matchNumber = 0;

        for ($leg = 1; $leg <= $numLegs; $leg++) {
            for ($round = 1; $round <= $totalRounds; $round++) {
                $halfSize = $teamCount / 2;
                $homeTeams = array_slice($scheduleTeams, 0, $halfSize);
                $awayTeams = array_slice($scheduleTeams, $halfSize);
                $awayTeams = array_reverse($awayTeams);

                for ($i = 0; $i < $halfSize; $i++) {
                    $homeId = $homeTeams[$i];
                    $awayId = $awayTeams[$i];

                    if ($homeId === 'BYE' || $awayId === 'BYE') {
                        continue;
                    }

                    $matchNumber++;

                    $isReturnLeg = ($leg % 2 === 0);
                    $finalHomeId = ($isReturnLeg) ? $awayId : $homeId;
                    $finalAwayId = ($isReturnLeg) ? $homeId : $awayId;

                    $matches[] = [
                        'name_of_match' => "Trận đấu số {$matchNumber}",
                        'home_team_id' => $finalHomeId,
                        'away_team_id' => $finalAwayId,
                        'tournament_type_id' => $type->id,
                        'leg' => $leg,
                        'round' => $round,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $firstTeam = array_shift($scheduleTeams);
                $lastTeam = array_pop($scheduleTeams);
                array_unshift($scheduleTeams, $firstTeam, $lastTeam);
            }
        }

        // Insert tất cả một lần
        if (!empty($matches)) {
            Matches::insert($matches);
        }
    }

    private function generateElimination(TournamentType $type, $teams, $config, $numLegs)
    {
        $teamCount = count($teams);
        if ($teamCount < 2) return;

        // ✅ FIX: Xử lý config dạng array hoặc object linh hoạt hơn
        $mainConfig = is_array($config) && isset($config[0]) ? $config[0] : $config;

        $seedingRules = $mainConfig['seeding_rules'] ?? [];
        // Chấp nhận cả string "true", "on", 1 hoặc boolean true
        $advancedToNext = filter_var($mainConfig['advanced_to_next_round'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $byeSelectionOrder = $advancedToNext; // Alias để đồng bộ
        $hasThirdPlace = filter_var($mainConfig['has_third_place_match'] ?? false, FILTER_VALIDATE_BOOLEAN); 

        // -------------------------------
        // STEP 1: Seeding (giữ nguyên logic cũ)
        // -------------------------------
        foreach ($seedingRules as $rule) {
            switch ($rule) {
                case TournamentType::SEED_LEVEL:
                    $sportId = $type->tournament->sport_id ?? null;
                    $allUserIds = collect($teams)
                        ->flatMap(fn($team) => collect($team->members)->pluck('user_id'))
                        ->filter()->unique()->values()->all();
                    if (!$sportId || empty($allUserIds)) {
                        $teams = $teams->shuffle()->values();
                        break;
                    }

                    $userScores = DB::table('user_sport as us')
                        ->join('user_sport_scores as uss', 'us.id', '=', 'uss.user_sport_id')
                        ->where('us.sport_id', $sportId)
                        ->where('uss.score_type', 'vndupr_score')
                        ->whereIn('us.user_id', $allUserIds)
                        ->pluck('uss.score_value', 'us.user_id')
                        ->map(fn($v) => (float)$v)
                        ->toArray();

                    $teams = $teams->map(function ($team) use ($userScores) {
                        $userIds = collect($team->members)->pluck('user_id')->filter();
                        $scores = $userIds->map(fn($uid) => $userScores[$uid] ?? 0)->toArray();
                        $team->_seed_meta = ['level' => count($scores) ? array_sum($scores) / count($scores) : 0];
                        return $team;
                    })->sortByDesc(fn($t) => $t->_seed_meta['level'])->values();
                    break;

                case TournamentType::SEED_SAME_CLUB_AVOID:
                    $byClub = $teams->groupBy(fn($t) => $t->club_id ?? 'no_club');
                    $interleaved = collect();
                    while ($byClub->isNotEmpty()) {
                        foreach ($byClub as $club => $arr) {
                            if ($arr->isNotEmpty()) $interleaved->push($arr->shift());
                            if ($arr->isEmpty()) $byClub->forget($club);
                            else $byClub->put($club, $arr);
                        }
                    }
                    $teams = $interleaved->values();
                    break;

                default:
                    $teams = $teams->shuffle()->values();
                    break;
            }
        }

        // -------------------------------
        // STEP 2: Generate bracket (với numLegs)
        // -------------------------------
        $round = 1;
        $currentTeams = $teams->values()->all();
        $matchMap = [];

        while (count($currentTeams) > 1) {
            $nextRoundTeams = [];
            $roundPairs = [];
            $numTeams = count($currentTeams);
            $hasOdd = $numTeams % 2 !== 0;

            $byeTeam = $hasOdd ? array_pop($currentTeams) : null;

            $matchNumber = 0;
            for ($i = 0; $i < count($currentTeams); $i += 2) {
                $home = $currentTeams[$i];
                $away = $currentTeams[$i + 1] ?? null;
                $matchNumber++;

                $pairMatchIds = [];
                for ($leg = 1; $leg <= $numLegs; $leg++) {
                    $isReturn = ($leg % 2 === 0);
                    $match = $type->matches()->create([
                        'tournament_type_id' => $type->id,
                        'name_of_match' => "Trận đấu số {$matchNumber}",
                        'home_team_id' => $isReturn ? ($away->id ?? null) : ($home->id ?? null),
                        'away_team_id' => $isReturn ? ($home->id ?? null) : ($away->id ?? null),
                        'round' => $round,
                        'leg' => $leg,
                        'is_bye' => false
                    ]);
                    $pairMatchIds[] = $match->id;
                }

                $roundPairs[] = (object)[
                    'match_ids' => $pairMatchIds,
                    'home' => $home,
                    'away' => $away
                ];
                $nextRoundTeams[] = (object)['id' => null, '_from_pair_index' => count($roundPairs) - 1];
            }

            // xử lý đội bye (đội lẻ)
            if ($byeTeam) {
                $pairMatchIds = [];
                for ($leg = 1; $leg <= $numLegs; $leg++) {
                    $matchData = [
                        'tournament_type_id' => $type->id,
                        'name_of_match' => "Trận đấu số " . ($matchNumber + 1),
                        'home_team_id' => $byeTeam->id ?? null,
                        'away_team_id' => null,
                        'round' => $round,
                        'leg' => $leg,
                    ];

                    if ($byeSelectionOrder && $round > 1) {
                        // Nếu bật advanced_to_next_round: đây là trận placeholder cho best loser
                        $matchData['is_bye'] = false;
                        $matchData['best_loser_source_round'] = $round - 1;
                    } else {
                        // Mặc định: đây là trận bye (vào thẳng)
                        $matchData['is_bye'] = true;
                        $matchData['status'] = Matches::STATUS_COMPLETED;
                        $matchData['winner_id'] = $byeTeam->id ?? null;
                    }

                    $match = $type->matches()->create($matchData);
                    $pairMatchIds[] = $match->id;
                }

                $roundPairs[] = (object)['match_ids' => $pairMatchIds, 'home' => $byeTeam, 'away' => null];

                if ($byeSelectionOrder && $round > 1) {
                    // Nếu là trận placeholder, đội thắng (có thể là best loser) sẽ đi tiếp
                    $nextRoundTeams[] = (object)['id' => null, '_from_pair_index' => count($roundPairs) - 1];
                } else {
                    // Nếu là bye, đội đó mặc định đi tiếp
                    $nextRoundTeams[] = $byeTeam;
                }
            }

            $matchMap[$round] = $roundPairs;
            $currentTeams = $nextRoundTeams;
            $round++;
        }

        // -------------------------------
        // STEP 3: Gán next_match_id & next_position
        // -------------------------------
        $roundKeys = array_keys($matchMap);
        for ($i = 0; $i < count($roundKeys) - 1; $i++) {
            $currRoundPairs = $matchMap[$roundKeys[$i]];
            $nextRoundPairs = $matchMap[$roundKeys[$i + 1]] ?? [];

            foreach ($currRoundPairs as $pairIndex => $pair) {
                $nextPairIndex = floor($pairIndex / 2);
                $nextPos = ($pairIndex % 2 === 0) ? 'home' : 'away';

                $nextPair = $nextRoundPairs[$nextPairIndex] ?? null;
                if ($nextPair) {
                    $targetMatchId = $nextPair->match_ids[0];

                    foreach ($pair->match_ids as $mId) {
                        $match = Matches::find($mId);
                        if (!$match) continue;

                        $match->update([
                            'next_match_id' => $targetMatchId,
                            'next_position' => $nextPos
                        ]);

                        // ✅ Propagate byes recursively
                        $currentM = $match;
                        $currentTId = $match->winner_id;
                        $targetMId = $targetMatchId;
                        $tPos = $nextPos;

                        while ($currentM && $currentM->status === Matches::STATUS_COMPLETED && $currentTId && $targetMId) {
                            $targetM = Matches::find($targetMId);
                            if (!$targetM) break;

                            $targetM->update(["{$tPos}_team_id" => $currentTId]);

                            if ($targetM->is_bye) {
                                $targetM->update([
                                    'status' => Matches::STATUS_COMPLETED,
                                    'winner_id' => $currentTId
                                ]);
                                
                                // Move to the next round's target
                                if ($targetM->next_match_id) {
                                    $currentM = $targetM;
                                    $targetMId = $targetM->next_match_id;
                                    $tPos = $targetM->next_position;
                                } else {
                                    break;
                                }
                            } else {
                                break;
                            }
                        }
                    }
                }
            }
        }

        // ✅ STEP 4: TẠO TRẬN TRANH HẠNG 3 (NẾU CÓ)
        if ($hasThirdPlace && count($roundKeys) >= 2) {
            $finalRound = max($roundKeys);
            $semiRound = $finalRound - 1;
            $semiPairs = $matchMap[$semiRound] ?? [];

            if (count($semiPairs) >= 2) {
                $firstSemiPair = $semiPairs[0];
                $secondSemiPair = $semiPairs[1];

                $firstSemiId = $firstSemiPair->match_ids[0];
                $secondSemiId = $secondSemiPair->match_ids[0];

                $firstThirdPlaceId = null;

                // Tạo trận tranh hạng 3 cho từng leg
                for ($leg = 1; $leg <= $numLegs; $leg++) {
                    $third = $type->matches()->create([
                        'tournament_type_id' => $type->id,
                        'round' => $finalRound + 1,
                        'leg' => $leg,
                        'is_third_place' => true,
                        'status' => 'pending',
                        'name_of_match' => "Tranh hạng Ba",
                    ]);

                    if ($leg === 1) {
                        $firstThirdPlaceId = $third->id;
                    }
                }

                // Link semi-final losers vào trận tranh hạng 3
                DB::table('matches')
                    ->where('id', $firstSemiId)
                    ->update([
                        'loser_next_match_id' => $firstThirdPlaceId,
                        'loser_next_position' => 'home',
                    ]);
                DB::table('matches')
                    ->where('id', $secondSemiId)
                    ->update([
                        'loser_next_match_id' => $firstThirdPlaceId,
                        'loser_next_position' => 'away',
                    ]);
            }
        }
    }
    private function generateMixed(TournamentType $type, $teams, $config, $numLegs)
    {
        $matchNumber = 0;

        $mainConfig = is_array($config) && isset($config[0]) ? $config[0] : [];
        $poolConfig = $mainConfig['pool_stage'] ?? [];
        $knockoutConfig = $mainConfig['knockout_stage'] ?? [];

        $numAdvancing = max(1, (int)($poolConfig['num_advancing_teams'] ?? 1));
        $advancedToNext = filter_var($mainConfig['advanced_to_next_round'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasThirdPlace = filter_var($mainConfig['has_third_place_match'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // ✅ LẤY PAIRING MODE TỪ CONFIG (MẶC ĐỊNH: SEQUENTIAL)
        $pairingMode = $knockoutConfig['pairing_mode'] ?? null;  // Không set default ở đây
        $manualPairings = $knockoutConfig['manual_pairings'] ?? null;

        // ✅ KIỂM TRA: Có groups với teams assigned không?
        $groups = $type->groups()->with('teams.members')->get();
        $hasAssignedTeams = $groups->isNotEmpty() && $groups->some(fn($g) => $g->teams->isNotEmpty());

        if ($hasAssignedTeams) {
            // ✅ TRƯỜNG HỢP 1: ĐÃ ASSIGN TEAMS VÀO BẢNG
            $chunks = $groups->map(fn($g) => $g->teams)->filter(fn($chunk) => $chunk->count() > 0)->values();
        } else {
            // ✅ TRƯỜNG HỢP 2: AUTO GENERATE - CHIA ĐỘI TỰ ĐỘNG (LOGIC CŨ)
            $teamCount = $teams->count();
            if ($teamCount < 2) return;

            $numGroups = max(1, (int)($poolConfig['number_competing_teams'] ?? 2));
            $baseTeamsPerGroup = floor($teamCount / $numGroups);
            $remainder = $teamCount % $numGroups;

            $chunks = collect();
            $offset = 0;
            for ($i = 0; $i < $numGroups; $i++) {
                $groupSize = $baseTeamsPerGroup + ($i < $remainder ? 1 : 0);
                if ($groupSize > 0) {
                    $groupTeams = $teams->slice($offset, $groupSize)->values();
                    $chunks->push($groupTeams);
                    // ✅ LƯU TEAMS VÀO GROUP_TEAM
                    $group = $groups->get($i);
                    if ($group) {
                        $syncData = [];
                        foreach ($groupTeams as $order => $team) {
                            $syncData[$team->id] = ['order' => $order];
                        }
                        $group->teams()->sync($syncData);
                    }

                    $offset += $groupSize;
                }
            }

            $chunks = $chunks->filter(fn($chunk) => $chunk->count() > 0)->values();
        }
        $advancingByRank = collect();
        $groupObjects = collect();

        // ===== PHASE 2: TẠO VÒNG BẢNG (ROUND ROBIN) =====
        foreach ($chunks as $index => $chunk) {
            $chunk = $chunk->values();
            $count = $chunk->count();

            // Nếu chỉ có 1 đội trong group -> tạo bye match
            if ($count === 1) {
                $matchNumber++;
                    $group = $type->groups()->create(['name' => 'Bảng ' . chr(65 + $index)]);

                $byeMatch = $type->matches()->create([
                    'tournament_type_id' => $type->id,
                    'home_team_id' => $chunk[0]->id,
                    'away_team_id' => null,
                    'round' => 1,
                    'leg' => 1,
                    'is_bye' => true,
                    'status' => 'pending',
                    'name_of_match' => "Trận đấu số {$matchNumber}",
                ]);

                if (!isset($advancingByRank[0])) {
                    $advancingByRank[0] = collect();
                }
                $advancingByRank[0]->push((object)[
                    'team_id' => $chunk[0]->id,
                    '_bye_match' => $byeMatch,
                    '_group_id' => null,
                    '_group_index' => $index,
                    '_rank' => 1,
                ]);
                continue;
            }

            // ✅ Group bình thường (2+ đội)
            $group = $groups->get($index);
            if (!$group) {
                continue;
            }
            $groupObjects->push($group);

            // Thuật toán Round Robin (Circle Method)
            $scheduleTeams = $chunk->pluck('id')->toArray();
            $isOdd = $count % 2 !== 0;
            if ($isOdd) {
                $scheduleTeams[] = 'BYE';
                $count++;
            }
            $totalRounds = $count - 1;

            // Tạo matches cho từng leg
            for ($leg = 1; $leg <= $numLegs; $leg++) {
                $currentSchedule = $scheduleTeams; // Reset schedule mỗi leg

                for ($round = 1; $round <= $totalRounds; $round++) {
                    $halfSize = $count / 2;
                    $homeTeams = array_slice($currentSchedule, 0, $halfSize);
                    $awayTeams = array_reverse(array_slice($currentSchedule, $halfSize));

                    for ($i = 0; $i < $halfSize; $i++) {
                        $homeId = $homeTeams[$i];
                        $awayId = $awayTeams[$i];

                        if ($homeId === 'BYE' || $awayId === 'BYE') {
                            continue;
                        }

                        $matchNumber++;

                        // Đảo sân cho lượt về
                        $isReturnLeg = ($leg % 2 === 0);
                        $finalHomeId = $isReturnLeg ? $awayId : $homeId;
                        $finalAwayId = $isReturnLeg ? $homeId : $awayId;

                        $type->matches()->create([
                            'group_id' => $group->id,
                            'tournament_type_id' => $type->id,
                            'home_team_id' => $finalHomeId,
                            'away_team_id' => $finalAwayId,
                            'round' => 1, // Pool stage luôn là round 1
                            'leg' => $leg,
                            'is_bye' => false,
                            'status' => 'pending',
                            'name_of_match' => "Trận đấu số {$matchNumber}",
                        ]);
                    }

                    // Rotate schedule (Circle Method)
                    $firstTeam = array_shift($currentSchedule);
                    $lastTeam = array_pop($currentSchedule);
                    array_unshift($currentSchedule, $firstTeam, $lastTeam);
                }
            }

            // Thu thập placeholder theo hạng cho knockout
            for ($k = 0; $k < min($numAdvancing, $chunk->count()); $k++) {
                if (!isset($advancingByRank[$k])) {
                    $advancingByRank[$k] = collect();
                }

                $advancingByRank[$k]->push((object)[
                    'team_id' => null,
                    '_from_group' => $group->id,
                    '_group_index' => $index,
                    '_rank' => $k + 1,
                ]);
            }
        }

        // ✅ SẮP XẾP ĐỘI ADVANCING THEO MODE ĐÃ CHỌN
        $advancing = $this->arrangeAdvancingTeams($advancingByRank, $pairingMode, $manualPairings);

        // ✅ KIỂM TRA SỐ ĐỘI ADVANCING
        $totalAdvancing = $advancing->count();
        $willHaveBye = ($totalAdvancing % 2 !== 0);

        // ✅ CHỈ CHO PHÉP BEST LOSER KHI CẦN THIẾT
        if ($willHaveBye && !$advancedToNext) {
            // Padding thêm 1 placeholder rỗng để tránh lỗi
            $advancing->push((object)[
                'team_id' => null,
                '_placeholder' => true,
            ]);
        }

        // ===== PHASE 4: TẠO KNOCKOUT STAGE =====
        $knockoutRounds = $this->generateKnockoutStage(
            $type,
            $advancing,
            $hasThirdPlace,
            $advancedToNext,
            $numLegs,
            $matchNumber
        );

        // ===== PHASE 5: TẠO POOL ADVANCEMENT RULES =====
        $this->createPoolAdvancementRules($type, $knockoutRounds, $advancing, $groupObjects);
    }

    private function generateKnockoutStage(TournamentType $type, $teams, $hasThirdPlace, $advancedToNext = false, $numLegs = 1, &$matchNumber = 0)
    {
        $teamList = is_array($teams) ? collect($teams) : $teams->values();
        $roundIndex = 2;
        $rounds = collect();

        while ($teamList->count() > 1) {
            $matchIds = collect();
            $nextRoundTeams = collect();
            $teamCount = $teamList->count();
            $numMatches = intdiv($teamCount, 2);
            $hasBye = ($teamCount % 2 === 1);

            // Tạo các trận đấu bình thường
            for ($i = 0; $i < $numMatches; $i++) {
                $homeIdx = $i * 2;
                $awayIdx = $i * 2 + 1;

                $home = $teamList->get($homeIdx);
                $away = $teamList->get($awayIdx);
                $firstMatchId = null;

                for ($leg = 1; $leg <= $numLegs; $leg++) {
                    $isReturn = ($leg % 2 === 0);
                    $matchNumber++;

                    $match = $type->matches()->create([
                        'tournament_type_id' => $type->id,
                        'home_team_id' => $isReturn ? $this->getTeamId($away) : $this->getTeamId($home),
                        'away_team_id' => $isReturn ? $this->getTeamId($home) : $this->getTeamId($away),
                        'round' => $roundIndex,
                        'leg' => $leg,
                        'status' => 'pending',
                        'is_bye' => false,
                        'name_of_match' => "Trận đấu số {$matchNumber}",
                    ]);

                    if ($leg === 1) {
                        $firstMatchId = $match->id;
                    }
                }

                $matchIds->push($firstMatchId);

                $nextRoundTeams->push((object)[
                    'team_id' => null,
                    '_from_match' => $firstMatchId,
                ]);
            }

            // Xử lý đội bye
            if ($hasBye) {
                $byeTeam = $teamList->get($teamCount - 1);
                $byeTeamId = $this->getTeamId($byeTeam);

                if ($advancedToNext) {
                    // ✅ FIX: Tạo trận bye vs best loser và THÊM VÀO $matchIds
                    $firstByeMatchId = null;
                    for ($leg = 1; $leg <= $numLegs; $leg++) {
                        $matchNumber++;
                        $byeMatch = $type->matches()->create([
                            'tournament_type_id' => $type->id,
                            'home_team_id' => $byeTeamId,
                            'away_team_id' => null,
                            'round' => $roundIndex,
                            'leg' => $leg,
                            'status' => 'pending',
                            'is_bye' => true,
                            'best_loser_source_round' => $roundIndex - 1,
                            'name_of_match' => "Trận đấu số {$matchNumber}",
                        ]);
                        if ($leg === 1) {
                            $firstByeMatchId = $byeMatch->id;
                        }
                    }

                    // ✅ QUAN TRỌNG: Thêm bye match vào matchIds để nó được link
                    $matchIds->push($firstByeMatchId);

                    $nextRoundTeams->push((object)[
                        'team_id' => null,
                        '_from_match' => $firstByeMatchId,
                    ]);
                } else {
                    // Bye đơn giản (không có best loser)
                    $matchNumber++;
                    $byeMatch = $type->matches()->create([
                        'tournament_type_id' => $type->id,
                        'home_team_id' => $byeTeamId,
                        'away_team_id' => null,
                        'round' => $roundIndex,
                        'leg' => 1,
                        'status' => 'pending',
                        'is_bye' => true,
                        'name_of_match' => "Trận đấu số {$matchNumber}",
                    ]);

                    $matchIds->push($byeMatch->id);

                    $nextRoundTeams->push((object)[
                        'team_id' => $byeTeamId,
                        '_bye_match' => $byeMatch,
                    ]);
                }
            }

            $rounds->put($roundIndex, $matchIds);
            $teamList = $nextRoundTeams;
            $roundIndex++;
        }

        if ($rounds->isEmpty()) {
            return collect();
        }

        // Link các trận vào round tiếp theo
        $finalRound = $roundIndex - 1;
        for ($r = 2; $r < $finalRound; $r++) {
            $currMatchIds = $rounds->get($r, collect());
            $nextMatchIds = $rounds->get($r + 1, collect());

            foreach ($currMatchIds as $idx => $matchId) {
                $match = $type->matches()->find($matchId);
                if (!$match) continue;

                // ✅ FIX: Bỏ điều kiện skip bye match để link được
                // Trước: if ($match->is_bye && !$match->away_team_id) continue;
                // Sau: Cho phép link cả bye match

                $targetIdx = intdiv($idx, 2);
                $targetId = $nextMatchIds->get($targetIdx);
                if (!$targetId) continue;

                $position = ($idx % 2 === 0) ? 'home' : 'away';

                $match->update([
                    'next_match_id' => $targetId,
                    'next_position' => $position,
                ]);
            }
        }

        // Xử lý trận tranh hạng 3
        if ($hasThirdPlace) {
            $semiRound = $finalRound - 1;
            $semiIds = $rounds->get($semiRound, collect());

            if ($semiIds->count() >= 2) {
                $firstSemiId = $semiIds->get(0);
                $secondSemiId = $semiIds->get(1);
                $firstThirdPlaceId = null;

                for ($leg = 1; $leg <= $numLegs; $leg++) {
                    $matchNumber++;
                    $third = $type->matches()->create([
                        'tournament_type_id' => $type->id,
                        'round' => $finalRound + 1,
                        'leg' => $leg,
                        'is_third_place' => true,
                        'status' => 'pending',
                        'name_of_match' => "Trận đấu số {$matchNumber}",
                    ]);

                    if ($leg === 1) {
                        $firstThirdPlaceId = $third->id;
                    }
                }

                DB::table('matches')
                    ->where('id', $firstSemiId)
                    ->update([
                        'loser_next_match_id' => $firstThirdPlaceId,
                        'loser_next_position' => 'home',
                    ]);
                DB::table('matches')
                    ->where('id', $secondSemiId)
                    ->update([
                        'loser_next_match_id' => $firstThirdPlaceId,
                        'loser_next_position' => 'away',
                    ]);
            }
        }

        return $rounds;
    }

    private function createPoolAdvancementRules(TournamentType $type, $knockoutRounds, $advancing, $groupObjects)
    {
        if (!($knockoutRounds instanceof Collection)) {
            $knockoutRounds = collect($knockoutRounds);
        }

        $firstRoundMatchIds = $knockoutRounds->get(2, collect());
        if ($firstRoundMatchIds->isEmpty()) {
            return;
        }

        $numLegs = (int) ($type->num_legs ?? 1);

        $allRound2Matches = Matches::where('tournament_type_id', $type->id)
            ->where('round', 2)
            ->orderBy('id', 'asc')
            ->get();

        if ($allRound2Matches->isEmpty()) {
            return;
        }
        $matchPairs = $allRound2Matches->chunk($numLegs)->values();
        $knockoutIndex = 0;
        $totalSlots = $matchPairs->count() * 2; // Mỗi cặp (dù 1 hay 2 lượt) vẫn chỉ có 2 vị trí trống (Home/Away)

        foreach ($advancing as $placeholder) {
            if ($knockoutIndex >= $totalSlots) {
                break;
            }
            // Xác định cặp đấu và vị trí (đội thứ 1 vào Home cặp 1, đội thứ 2 vào Away cặp 1,...)
            $pairIndex = intdiv($knockoutIndex, 2);
            $basePosition = ($knockoutIndex % 2 === 0) ? 'home' : 'away';

            $matchPair = $matchPairs->get($pairIndex);
            if (!$matchPair) {
                $knockoutIndex++;
                continue;
            }
            if (property_exists($placeholder, '_from_group') && $placeholder->_from_group !== null) {
                $groupId = $placeholder->_from_group;
                $rank = $placeholder->_rank ?? 1;

                foreach ($matchPair as $legMatch) {
                    $isReturnLeg = ($legMatch->leg % 2 === 0);
                    $actualPosition = $isReturnLeg
                        ? ($basePosition === 'home' ? 'away' : 'home')
                        : $basePosition;

                    PoolAdvancementRule::updateOrCreate([
                        'tournament_type_id' => $type->id,
                        'group_id' => $groupId,
                        'rank' => $rank,
                        'next_match_id' => $legMatch->id,
                    ], [
                        'next_position' => $actualPosition,
                    ]);
                }
            }
            // === XỬ LÝ CÓ SẴN TEAM ID (CHO TRƯỜNG HỢP BYE HOẶC ĐÃ XÁC ĐỊNH) ===
            elseif (property_exists($placeholder, 'team_id') && $placeholder->team_id) {
                foreach ($matchPair as $legMatch) {
                    $isReturnLeg = ($legMatch->leg % 2 === 0);
                    $actualPosition = $isReturnLeg
                        ? ($basePosition === 'home' ? 'away' : 'home')
                        : $basePosition;

                    $legMatch->update([
                        $actualPosition . '_team_id' => $placeholder->team_id,
                        'status' => 'pending',
                    ]);
                }
            }
            $knockoutIndex++;
        }
    }
    /**
     * Apply pool advancement sau khi hoàn thành vòng bảng
     * Gọi method này từ service khi tất cả matches của pool đã completed
     */
    public function applyPoolAdvancement(TournamentType $type)
    {
        $groups = $type->groups()->with(['matches'])->get();

        foreach ($groups as $group) {
            $matches = $group->matches;

            // Tính standings
            $standings = TournamentService::calculateGroupStandings($matches);

            // ✅ Lấy TẤT CẢ các rules cho group này (bao gồm cả các legs)
            $rules = PoolAdvancementRule::where('group_id', $group->id)
                ->orderBy('rank')
                ->orderBy('next_match_id') // ← Sắp xếp theo match để xử lý tuần tự
                ->get();

            // ✅ Group rules theo rank để xử lý từng đội
            $rulesByRank = $rules->groupBy('rank');

            foreach ($rulesByRank as $rank => $rulesForRank) {
                // Lấy team theo ranking
                $teamAtRank = $standings->get($rank - 1);
                if (!$teamAtRank) continue;

                $advancingTeamId = $teamAtRank['team_id'];

                // ✅ Cập nhật TẤT CẢ các legs của đội này
                foreach ($rulesForRank as $rule) {
                    $targetMatch = Matches::find($rule->next_match_id);
                    if (!$targetMatch) continue;

                    $targetMatch->update([
                        $rule->next_position . '_team_id' => $advancingTeamId,
                        'status' => 'pending',
                    ]);
                }
            }
        }
    }
    private function getTeamId($placeholder)
    {
        return $this->matchGenerator->getTeamId($placeholder);
    }
     /**
     * Lấy toàn bộ bracket cho tournament type
     * Trả về cấu trúc phân theo round để hiển thị bracket chart
     */
    public function getBracket(TournamentType $tournamentType)
    {
        try {
            $format = $tournamentType->format;

            switch ($format) {
                case TournamentType::FORMAT_ROUND_ROBIN:
                    return $this->getRoundRobinSchedule($tournamentType);

                case TournamentType::FORMAT_ELIMINATION:
                    return $this->getEliminationBracket($tournamentType);

                case TournamentType::FORMAT_MIXED:
                    return $this->getMixedBracket($tournamentType);

                default:
                    return ResponseHelper::error('Format không hợp lệ', 400);
            }
        } catch (\Throwable $e) {
            return ResponseHelper::error('Lỗi khi lấy bracket: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Round Robin - trả về danh sách trận theo thứ tự
     */
    private function getRoundRobinSchedule(TournamentType $type)
    {
        $tournamentId = $type->tournament_id;
        $allMatches = $type->matches()
            ->with(['homeTeam.members', 'awayTeam.members', 'results'])
            ->get();
        $totalRounds = $allMatches->max('round') ?? 1;

        // 1. Nhóm theo Round trước để tạo cấu trúc giống Bracket của Elimination
        $rounds = $allMatches->groupBy('round')->map(function ($roundMatches, $round) use ($type, $totalRounds, $tournamentId) {

            // 2. Trong mỗi Round, nhóm các Leg thành 1 cặp đấu
            $groupedMatches = $roundMatches->groupBy(function ($match) {
                $teams = [$match->home_team_id, $match->away_team_id];
                sort($teams);
                return implode('_', $teams);
            })->values();

            return [
                'round' => $round,
                'round_name' => "Vòng " . $round, // Hoặc dùng hàm getRoundName nếu muốn
                'matches' => $groupedMatches->map(function ($legs) use($round, $totalRounds, $tournamentId) {
                    $leg1 = $legs->firstWhere('leg', 1) ?? $legs->first();
                    $baseHomeId = $leg1->home_team_id;
                    $baseAwayId = $leg1->away_team_id;

                    $homeTotal = 0;
                    $awayTotal = 0;

                    $isFinal = ($round == $totalRounds) && ($legs->count() > 0);

                    // 3. Format Legs giống hệt Elimination
                    $formattedLegs = $legs->map(function ($leg) use ($baseHomeId, $baseAwayId, &$homeTotal, &$awayTotal) {
                        $res = $this->calculateSingleMatchWins($leg); // Hàm tính set thắng 2-1, 2-0...

                        $homeLegScore = 0;
                        $awayLegScore = 0;

                        // Logic tính điểm thắng (3đ cho thắng trận, 0đ cho thua) giống Elimination
                        if ($leg->home_team_id == $baseHomeId) {
                            $homeLegScore = ($res['home'] > $res['away']) ? 3 : 0;
                            $awayLegScore = ($res['away'] > $res['home']) ? 3 : 0;
                        } else {
                            $homeLegScore = ($res['away'] > $res['home']) ? 3 : 0;
                            $awayLegScore = ($res['home'] > $res['away']) ? 3 : 0;
                        }

                        if ($leg->status === 'completed') {
                            $homeTotal += $homeLegScore;
                            $awayTotal += $awayLegScore;
                        }

                        return [
                            'id' => $leg->id,
                            'leg' => $leg->leg,
                            'court' => $leg->court,
                            'home_score' => $homeLegScore,
                            'away_score' => $awayLegScore,
                            'status' => $leg->status,
                            'scheduled_at' => $leg->scheduled_at,
                            'is_completed' => $leg->status === 'completed',
                            // Group sets để Modal CreateMatch hiển thị đúng
                            'sets' => $leg->results->groupBy('set_number')->map(function($setGroup) use ($leg) {
                                return $setGroup->map(fn($s) => ['team_id' => $s->team_id, 'score' => $s->score])->values();
                            })
                        ];
                    })->values();

                    return [
                        'match_id' => $leg1->id,
                        'home_team' => $this->formatTeam($leg1->homeTeam, null, $tournamentId),
                        'away_team' => $this->formatTeam($leg1->awayTeam, null, $tournamentId),
                        'is_bye' => $leg1->is_bye,
                        'is_final' => $isFinal,
                        'legs' => $formattedLegs,
                        'aggregate_score' => [
                            'home' => $homeTotal,
                            'away' => $awayTotal,
                        ],
                        'status' => $legs->every(fn($l) => $l->status === 'completed') ? 'completed' : 'pending',
                    ];
                })->values()
            ];
        })->values();

        return ResponseHelper::success([
            'format' => TournamentType::FORMAT_ROUND_ROBIN,
            'format_type_text' => 'round_robin',
            'bracket' => $rounds, // Dùng key 'bracket' để FE dùng chung logic map
        ]);
    }

    // Hàm bổ trợ tính set thắng
    private function calculateSingleMatchWins($match)
    {
        return $this->bracketService->calculateSingleMatchWins($match);
    }

    /**
     * Elimination - trả về bracket theo round
     */
    private function getEliminationBracket(TournamentType $type)
    {
        // Closure tính điểm và định dạng sets
        $calculateLegDetails = function ($leg) {
            $homeTeamId = $leg->home_team_id;
            $awayTeamId = $leg->away_team_id;

            $sets = [];
            $homeSetWins = 0;
            $awaySetWins = 0;

            $groupedSets = $leg->results->groupBy('set_number');

            foreach ($groupedSets as $setNumber => $setGroup) {
                $home = $setGroup->firstWhere('team_id', $homeTeamId);
                $away = $setGroup->firstWhere('team_id', $awayTeamId);

                $homeScore = (int) ($home->score ?? 0);
                $awayScore = (int) ($away->score ?? 0);

                if ($homeScore > $awayScore) {
                    $homeSetWins++;
                } elseif ($awayScore > $homeScore) {
                    $awaySetWins++;
                }

                $sets['set_' . $setNumber] = [
                    ['team_id' => $homeTeamId, 'score' => $homeScore],
                    ['team_id' => $awayTeamId, 'score' => $awayScore],
                ];
            }

            // 👉 QUYẾT ĐỊNH THẮNG LEG
            if ($homeSetWins > $awaySetWins) {
                return [
                    'sets' => $sets,
                    'home_score_calculated' => 3,
                    'away_score_calculated' => 0,
                    'winner_team_id' => $homeTeamId,
                ];
            }

            if ($awaySetWins > $homeSetWins) {
                return [
                    'sets' => $sets,
                    'home_score_calculated' => 0,
                    'away_score_calculated' => 3,
                    'winner_team_id' => $awayTeamId,
                ];
            }

            // Không đủ dữ liệu → chưa xác định
            return [
                'sets' => $sets,
                'home_score_calculated' => 0,
                'away_score_calculated' => 0,
                'winner_team_id' => null,
            ];
        };

        $tournamentId = $type->tournament_id;
        $matches = $type->matches()
            ->with(['homeTeam.members', 'awayTeam.members', 'results'])
            ->orderBy('round')
            ->orderBy('leg')
            ->get();

        // ✅ FIX: Tìm round chung kết (round cao nhất KHÔNG PHẢI tranh hạng 3)
        $finalRound = $matches
            ->filter(fn($m) => !($m->is_third_place ?? false))
            ->max('round') ?? 1;

        $bracket = $matches
            ->groupBy('round')
            ->map(function ($roundMatches, $round) use ($calculateLegDetails, $type, $finalRound, $tournamentId) {

                // ✅ SỬA: Group 2 leg thành 1 match - Dùng match_pair_id hoặc logic ổn định
                $grouped = $roundMatches->groupBy(function ($match) {
                    // Nếu có match_pair_id (nên thêm vào DB)
                    if (isset($match->match_pair_id)) {
                        return 'pair_' . $match->match_pair_id;
                    }

                    // TH1: Dùng next_match_id + next_position (ổn định nhất)
                    if ($match->next_match_id && $match->next_position) {
                        return 'to_' . $match->next_match_id . '_' . $match->next_position;
                    }

                    // TH2: Trận cuối (Final/Third Place) - không có next
                    if ($match->is_third_place) {
                        return 'third_place_' . $match->round;
                    }

                    if (!$match->next_match_id) {
                        return 'final_' . $match->round;
                    }

                    // TH3: Fallback - gom theo min ID của 2 leg
                    // Leg 1 & 2 thường có ID liên tiếp
                    $baseId = floor($match->id / 2) * 2;
                    return 'match_' . $baseId;
                })->values();

                // ✅ XỬ LÝ TÊN ROUND
                $roundName = $this->getRoundName($round, $grouped->count(), $type->format);

                // ✅ NẾU TẤT CẢ MATCHES TRONG ROUND LÀ THIRD PLACE
                if ($roundMatches->every(fn($m) => $m->is_third_place ?? false)) {
                    $roundName = 'Tranh hạng Ba';
                }

                return [
                    'round' => $round,
                    'round_name' => $roundName,
                    'matches' => $grouped->map(function ($matchGroup) use ($calculateLegDetails, $round, $finalRound) {

                        $first = $matchGroup->first();
                        $homeTeamId = $first->home_team_id;
                        $awayTeamId = $first->away_team_id;

                        $homeTotal = 0;
                        $awayTotal = 0;

                        // ✅ FIX: Dùng $finalRound thay vì $maxRound
                        $isFinal = ($round == $finalRound) && !($first->is_third_place ?? false);

                        $legs = $matchGroup->map(function ($leg) use (
                            $calculateLegDetails,
                            &$homeTotal,
                            &$awayTotal,
                            $homeTeamId,
                            $awayTeamId
                        ) {
                            $details = $calculateLegDetails($leg);
                            if ($leg->status === 'completed') {
                                if ($details['winner_team_id'] === $homeTeamId) {
                                    $homeTotal += 3;
                                } elseif ($details['winner_team_id'] === $awayTeamId) {
                                    $awayTotal += 3;
                                }
                            }

                            return [
                                'id' => $leg->id,
                                'leg' => $leg->leg,
                                'court' => $leg->court,
                                'home_score' => $details['home_score_calculated'],
                                'away_score' => $details['away_score_calculated'],
                                'status' => $leg->status,
                                'scheduled_at' => $leg->scheduled_at,
                                'is_completed' => $leg->status === 'completed',
                                'sets' => $details['sets'],
                            ];
                        })->values();

                        $awayPlaceholder = $first->is_bye ? 'Vào thẳng' : ($first->best_loser_source_round ? 'Best Loser' : null);

                        return [
                            'match_id' => $first->id,
                            'home_team' => $this->formatTeam($first->homeTeam, null, $tournamentId),
                            'away_team' => $this->formatTeam($first->awayTeam, $awayPlaceholder, $tournamentId),
                            'is_bye' => $first->is_bye,
                            'is_third_place' => $first->is_third_place ?? false,
                            'is_final' => $isFinal,
                            'legs' => $legs,
                            'aggregate_score' => [
                                'home' => $homeTotal,
                                'away' => $awayTotal,
                            ],
                            'winner_team_id' => $this->determineWinner($homeTotal, $awayTotal, $homeTeamId, $awayTeamId, $first, $matchGroup),
                            'next_match_id' => $first->next_match_id,
                            'next_position' => $first->next_position,
                            'best_loser_source_round' => $first->best_loser_source_round,
                        ];
                    })->values(),
                ];
            })->values();

        return ResponseHelper::success([
            'format' => TournamentType::FORMAT_ELIMINATION,
            'format_type_text' => 'elimination',
            'bracket' => $bracket,
            'total_rounds' => $bracket->count(),
        ]);
    }

    private function getMixedBracket(TournamentType $type)
    {
        $tournamentId = $type->tournament_id;
        $calculateLegDetails = function ($leg) {
            $homeTeamId = $leg->home_team_id;
            $awayTeamId = $leg->away_team_id;

            $sets = [];
            $homeSetWins = 0;
            $awaySetWins = 0;

            $groupedSets = $leg->results->groupBy('set_number');

            foreach ($groupedSets as $setNumber => $setGroup) {
                $home = $setGroup->firstWhere('team_id', $homeTeamId);
                $away = $setGroup->firstWhere('team_id', $awayTeamId);

                $homeScore = (int) ($home->score ?? 0);
                $awayScore = (int) ($away->score ?? 0);

                if ($homeScore > $awayScore) $homeSetWins++;
                elseif ($awayScore > $homeScore) $awaySetWins++;

                $sets['set_' . $setNumber] = [
                    ['team_id' => $homeTeamId, 'score' => $homeScore],
                    ['team_id' => $awayTeamId, 'score' => $awayScore],
                ];
            }

            if ($homeSetWins > $awaySetWins) {
                return [
                    'sets' => $sets,
                    'home_score_calculated' => 3,
                    'away_score_calculated' => 0,
                    'winner_team_id' => $homeTeamId,
                ];
            }

            if ($awaySetWins > $homeSetWins) {
                return [
                    'sets' => $sets,
                    'home_score_calculated' => 0,
                    'away_score_calculated' => 3,
                    'winner_team_id' => $awayTeamId,
                ];
            }

            return [
                'sets' => $sets,
                'home_score_calculated' => 0,
                'away_score_calculated' => 0,
                'winner_team_id' => null,
            ];
        };

        // ===== POOL STAGE =====
        $poolMatches = $type->matches()
            ->with(['homeTeam.members', 'awayTeam.members', 'group', 'results'])
            ->where('round', 1)
            ->orderBy('group_id')
            ->orderBy('leg')
            ->get();

        $poolStage = $poolMatches->groupBy('group_id')->map(function ($groupMatches, $groupId) use ($calculateLegDetails) {
            $group = $groupMatches->first()->group;

            $grouped = $groupMatches->groupBy(function ($match) {
                if (!$match->home_team_id && !$match->away_team_id) {
                    return 'match_' . $match->id;
                }

                return collect([
                    $match->home_team_id,
                    $match->away_team_id,
                ])->sort()->implode('_');
            })->values();

            return [
                'group_id' => $groupId,
                'group_name' => $group?->name ?? 'Bye',
                'matches' => $grouped->map(function ($matchGroup) use ($calculateLegDetails) {
                    $first = $matchGroup->first();
                    $homeTeamId = $first->home_team_id;
                    $awayTeamId = $first->away_team_id;

                    $homeTotal = 0;
                    $awayTotal = 0;

                    $legs = $matchGroup->map(function ($leg) use (
                        $calculateLegDetails,
                        &$homeTotal,
                        &$awayTotal,
                        $homeTeamId,
                        $awayTeamId
                    ) {
                        $details = $calculateLegDetails($leg);

                        if ($leg->status === 'completed') {
                            if ($details['winner_team_id'] === $homeTeamId) $homeTotal += 3;
                            elseif ($details['winner_team_id'] === $awayTeamId) $awayTotal += 3;
                        }

                        return [
                            'id' => $leg->id,
                            'leg' => $leg->leg,
                            'court' => $leg->court,
                            'home_score' => $details['home_score_calculated'],
                            'away_score' => $details['away_score_calculated'],
                            'status' => $leg->status,
                            'scheduled_at' => $leg->scheduled_at,
                            'is_completed' => $leg->status === 'completed',
                            'sets' => $details['sets'],
                        ];
                    })->values();

                    return [
                        'match_id' => $first->id,
                        'home_team' => $this->formatTeam($first->homeTeam, null, $tournamentId),
                        'away_team' => $this->formatTeam($first->awayTeam, null, $tournamentId),
                        'is_bye' => $first->is_bye,
                        'is_final' => false, // ✅ Pool stage không có final
                        'legs' => $legs,
                        'aggregate_score' => [
                            'home' => $homeTotal,
                            'away' => $awayTotal,
                        ],
                        'winner_team_id' =>
                            $homeTotal > $awayTotal ? $homeTeamId :
                            ($awayTotal > $homeTotal ? $awayTeamId : null),
                        'status' => $matchGroup->every(fn ($l) => $l->status === 'completed') ? 'completed' : 'pending',
                    ];
                })->values(),
                'standings' => $this->calculateGroupStandings($groupMatches),
            ];
        })->values();

        // ===== ADVANCEMENT RULES =====
        $advancementRules = PoolAdvancementRule::where('tournament_type_id', $type->id)
            ->with('group')
            ->get()
            ->groupBy('next_match_id');

        // ===== KNOCKOUT STAGE =====
        $knockoutMatches = $type->matches()
            ->with(['homeTeam.members', 'awayTeam.members', 'results'])
            ->where('round', '>=', 2)
            ->orderBy('round')
            ->orderBy('leg')
            ->get();

        // ✅ FIX: Tìm round chung kết knockout (round cao nhất KHÔNG PHẢI tranh hạng 3)
        $finalKnockoutRound = $knockoutMatches
            ->filter(fn($m) => !($m->is_third_place ?? false))
            ->max('round') ?? 2;

        $knockoutStage = $knockoutMatches->groupBy('round')->map(function ($roundMatches, $round) use (
            $calculateLegDetails,
            $type,
            $advancementRules,
            $finalKnockoutRound
        ) {
            $numLegs = (int) ($type->num_legs ?? 1);
            $sortedMatches = $roundMatches->sortBy('id')->values();
            $matchGroups = $sortedMatches->chunk($numLegs);

            $roundName = $this->getRoundName($round, $matchGroups->count(), $type->format);

            if ($roundMatches->every(fn($m) => $m->is_third_place ?? false)) {
                $roundName = 'Tranh hạng Ba';
            }

            return [
                'round' => $round,
                'round_name' => $roundName,
                'matches' => $matchGroups->map(function ($matchGroup) use (
                    $calculateLegDetails,
                    $advancementRules,
                    $round,
                    $finalKnockoutRound
                ) {
                    $first = $matchGroup->first();

                    $homeTeamId = $first->home_team_id;
                    $awayTeamId = $first->away_team_id;

                    // ===== PLACEHOLDER LOGIC ĐÚNG THEO Ý MÀY =====
                    $rulesForThisMatch = $advancementRules->get($first->id, collect());

                    $homePlaceholder = null;
                    $awayPlaceholder = null;

                    foreach ($rulesForThisMatch as $rule) {
                        $text = trim(
                            $this->getRankText($rule->rank) . ' ' . ($rule->group?->name ?? '')
                        );

                        if ($rule->next_position === 'home') $homePlaceholder = $text;
                        if ($rule->next_position === 'away') $awayPlaceholder = $text;
                    }

                    // 👉 RULE BỊ LẺ ⇒ SINH "NHÌ TỐT NHẤT"
                    if ($rulesForThisMatch->count() === 1) {
                        $onlyRule = $rulesForThisMatch->first();

                        if ($onlyRule->next_position === 'home' && !$awayPlaceholder) {
                            $awayPlaceholder = 'Nhì tốt nhất';
                        }

                        if ($onlyRule->next_position === 'away' && !$homePlaceholder) {
                            $homePlaceholder = 'Nhì tốt nhất';
                        }
                    }

                    $homeTotal = 0;
                    $awayTotal = 0;

                    // ✅ FIX: Dùng $finalKnockoutRound thay vì $maxKnockoutRound
                    $isFinal = ($round == $finalKnockoutRound) && !($first->is_third_place ?? false);

                    $legs = $matchGroup->map(function ($leg) use (
                        $calculateLegDetails,
                        &$homeTotal,
                        &$awayTotal,
                        $homeTeamId,
                        $awayTeamId
                    ) {
                        $details = $calculateLegDetails($leg);

                        if ($leg->status === 'completed') {
                            if ($details['winner_team_id'] === $homeTeamId) $homeTotal += 3;
                            elseif ($details['winner_team_id'] === $awayTeamId) $awayTotal += 3;
                        }

                        return [
                            'id' => $leg->id,
                            'leg' => $leg->leg,
                            'court' => $leg->court,
                            'home_score' => $details['home_score_calculated'],
                            'away_score' => $details['away_score_calculated'],
                            'status' => $leg->status,
                            'scheduled_at' => $leg->scheduled_at,
                            'is_completed' => $leg->status === 'completed',
                            'sets' => $details['sets'],
                        ];
                    })->values();

                    return [
                        'match_id' => $first->id,
                        'home_team' => $this->formatTeam($first->homeTeam, $homePlaceholder, $tournamentId),
                        'away_team' => $this->formatTeam($first->awayTeam, $awayPlaceholder, $tournamentId),
                        'is_bye' => $first->is_bye,
                        'is_third_place' => $first->is_third_place ?? false,
                        'is_final' => $isFinal, // ✅ FIXED
                        'legs' => $legs,
                        'aggregate_score' => [
                            'home' => $homeTotal,
                            'away' => $awayTotal,
                        ],
                        'winner_team_id' => $this->determineWinner($homeTotal, $awayTotal, $homeTeamId, $awayTeamId, $first, $matchGroup),
                        'status' => $matchGroup->every(fn ($l) => $l->status === 'completed') ? 'completed' : 'pending',
                    ];
                })->values(),
            ];
        })->values();

        return ResponseHelper::success([
            'format' => TournamentType::FORMAT_MIXED,
            'format_type_text' => 'mixed',
            'pool_stage' => $poolStage,
            'knockout_stage' => $knockoutStage,
        ]);
    }

    private function getRankText(int $rank): string
    {
        return match($rank) {
            1 => 'Nhất',
            2 => 'Nhì',
            3 => 'Ba',
            4 => 'Tư',
            default => "Hạng {$rank}",
        };
    }

    /**
     * Format team data cho response
     */
    public function formatTeam($team, ?string $placeholderText = null, ?int $tournamentId = null): ?array
    {
        return $this->bracketService->formatTeam($team, $placeholderText, $tournamentId);
    }


    /**
     * Lấy bracket với cấu trúc mới: poolStage, leftSide, rightSide, finalMatch
     * Logic chia nhánh đẩy hết về backend, FE chỉ render
     * Hỗ trợ Mixed format (có pool stage + knockout stage)
     */
    public function getBracketNew(TournamentType $tournamentType)
    {
        try {
            $format = $tournamentType->format;

            // Chỉ hỗ trợ Mixed format cho bây giờ
            if ($format !== TournamentType::FORMAT_MIXED) {
                return ResponseHelper::error('API này hiện chỉ hỗ trợ format Mixed', 400);
            }

            return $this->getMixedBracketNew($tournamentType);
        } catch (\Throwable $e) {
            return ResponseHelper::error('Lỗi khi lấy bracket: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy bracket Mixed format với cấu trúc mới
     */
    private function getMixedBracketNew(TournamentType $type)
    {
        $tournamentId = $type->tournament_id;
        $calculateLegDetails = function ($leg) {
            $homeTeamId = $leg->home_team_id;
            $awayTeamId = $leg->away_team_id;

            $sets = [];
            $homeSetWins = 0;
            $awaySetWins = 0;

            $groupedSets = $leg->results->groupBy('set_number');

            foreach ($groupedSets as $setNumber => $setGroup) {
                $home = $setGroup->firstWhere('team_id', $homeTeamId);
                $away = $setGroup->firstWhere('team_id', $awayTeamId);

                $homeScore = (int) ($home->score ?? 0);
                $awayScore = (int) ($away->score ?? 0);

                if ($homeScore > $awayScore) $homeSetWins++;
                elseif ($awayScore > $homeScore) $awaySetWins++;

                $sets['set_' . $setNumber] = [
                    ['team_id' => $homeTeamId, 'score' => $homeScore],
                    ['team_id' => $awayTeamId, 'score' => $awayScore],
                ];
            }

            if ($homeSetWins > $awaySetWins) {
                return [
                    'sets' => $sets,
                    'home_score_calculated' => 3,
                    'away_score_calculated' => 0,
                    'winner_team_id' => $homeTeamId,
                ];
            }

            if ($awaySetWins > $homeSetWins) {
                return [
                    'sets' => $sets,
                    'home_score_calculated' => 0,
                    'away_score_calculated' => 3,
                    'winner_team_id' => $awayTeamId,
                ];
            }

            return [
                'sets' => $sets,
                'home_score_calculated' => 0,
                'away_score_calculated' => 0,
                'winner_team_id' => null,
            ];
        };

        // ===== POOL STAGE (Round 1) =====
        $poolMatches = $type->matches()
            ->with(['homeTeam.members', 'awayTeam.members', 'group', 'results'])
            ->where('round', 1)
            ->orderBy('group_id')
            ->orderBy('leg')
            ->get();

        $poolStage = $poolMatches->groupBy('group_id')->map(function ($groupMatches, $groupId) use ($calculateLegDetails, $tournamentId) {
            $group = $groupMatches->first()->group;

            $grouped = $groupMatches->groupBy(function ($match) {
                if (!$match->home_team_id && !$match->away_team_id) {
                    return 'match_' . $match->id;
                }
                return collect([$match->home_team_id, $match->away_team_id])->sort()->implode('_');
            })->values();

            $matchesData = $grouped->map(function ($matchGroup) use ($calculateLegDetails, $tournamentId) {
                $first = $matchGroup->first();
                $homeTeamId = $first->home_team_id;
                $awayTeamId = $first->away_team_id;

                $homeTotal = 0;
                $awayTotal = 0;

                // Lấy leg đầu tiên để lấy scheduled_at, court
                $firstLeg = $matchGroup->first();

                // Tính status từ tất cả legs
                $status = $matchGroup->every(fn($l) => $l->status === 'completed') ? 'completed' :
                         ($matchGroup->some(fn($l) => $l->status === 'pending') ? 'pending' : 'cancelled');

                // Tính tổng score từ các legs
                $matchGroup->each(function ($leg) use ($calculateLegDetails, &$homeTotal, &$awayTotal, $homeTeamId, $awayTeamId) {
                    $details = $calculateLegDetails($leg);
                    if ($leg->status === 'completed') {
                        if ($details['winner_team_id'] === $homeTeamId) $homeTotal += 3;
                        elseif ($details['winner_team_id'] === $awayTeamId) $awayTotal += 3;
                    }
                });

                return [
                    'match_id' => $first->id,
                    'home_team' => $this->formatTeam($first->homeTeam, null, $tournamentId),
                    'away_team' => $this->formatTeam($first->awayTeam, null, $tournamentId),
                    'home_score' => $homeTotal,
                    'away_score' => $awayTotal,
                    'status' => $status,
                    'is_bye' => $first->is_bye,
                    'is_third_place' => false,
                    'scheduled_at' => $firstLeg->scheduled_at,
                    'court' => $firstLeg->court,
                    'winner_team_id' => $homeTotal > $awayTotal ? $homeTeamId : ($awayTotal > $homeTotal ? $awayTeamId : null),
                ];
            })->values();

            return [
                'group_id' => $groupId,
                'group_name' => $group?->name ?? 'Bảng ' . $groupId,
                'matches' => $matchesData,
                'standings' => $this->calculateGroupStandings($groupMatches),
            ];
        })->values();

        // ===== KNOCKOUT STAGE (Round >= 2) =====
        $knockoutMatches = $type->matches()
            ->with(['homeTeam.members', 'awayTeam.members', 'results'])
            ->where('round', '>=', 2)
            ->orderBy('round')
            ->orderBy('leg')
            ->get();

        $finalKnockoutRound = $knockoutMatches
            ->filter(fn($m) => !($m->is_third_place ?? false))
            ->max('round') ?? 2;

        $allKnockoutRounds = $knockoutMatches->groupBy('round')->map(function ($roundMatches, $round) use ($calculateLegDetails, $type, $finalKnockoutRound, $tournamentId) {
            $numLegs = (int) ($type->num_legs ?? 1);
            $sortedMatches = $roundMatches->sortBy('id')->values();
            $matchGroups = $sortedMatches->chunk($numLegs);

            $roundName = $this->getRoundName($round, $matchGroups->count(), $type->format);
            if ($roundMatches->every(fn($m) => $m->is_third_place ?? false)) {
                $roundName = 'Tranh hạng Ba';
            }

            $matchesData = $matchGroups->map(function ($matchGroup) use ($calculateLegDetails, $round, $finalKnockoutRound, $tournamentId) {
                $first = $matchGroup->first();
                $homeTeamId = $first->home_team_id;
                $awayTeamId = $first->away_team_id;

                $homeTotal = 0;
                $awayTotal = 0;

                $firstLeg = $matchGroup->first();
                $status = $matchGroup->every(fn($l) => $l->status === 'completed') ? 'completed' :
                         ($matchGroup->some(fn($l) => $l->status === 'pending') ? 'pending' : 'cancelled');

                $matchGroup->each(function ($leg) use ($calculateLegDetails, &$homeTotal, &$awayTotal, $homeTeamId, $awayTeamId) {
                    $details = $calculateLegDetails($leg);
                    if ($leg->status === 'completed') {
                        if ($details['winner_team_id'] === $homeTeamId) $homeTotal += 3;
                        elseif ($details['winner_team_id'] === $awayTeamId) $awayTotal += 3;
                    }
                });

                return [
                    'match_id' => $first->id,
                    'home_team' => $this->formatTeam($first->homeTeam, null, $tournamentId),
                    'away_team' => $this->formatTeam($first->awayTeam, null, $tournamentId),
                    'home_score' => $homeTotal,
                    'away_score' => $awayTotal,
                    'status' => $status,
                    'is_bye' => $first->is_bye,
                    'is_third_place' => $first->is_third_place ?? false,
                    'scheduled_at' => $firstLeg->scheduled_at,
                    'court' => $firstLeg->court,
                    'winner_team_id' => $this->determineWinner($homeTotal, $awayTotal, $homeTeamId, $awayTeamId, $first, $matchGroup),
                    'next_match_id' => $first->next_match_id,
                    'next_position' => $first->next_position,
                ];
            })->values();

            return [
                'round' => $round,
                'round_name' => $roundName,
                'matches' => $matchesData,
            ];
        })->values();

        // Chia knockout thành leftSide, rightSide, finalMatch
        $leftSide = collect();
        $rightSide = collect();
        $finalMatch = null;
        $thirdPlaceMatch = null;

        foreach ($allKnockoutRounds as $roundData) {
            $round = $roundData['round'];
            $isFinalRound = $round == $finalKnockoutRound;

            if ($isFinalRound) {
                $finalMatchData = $roundData['matches']->first(function ($match) {
                    return !($match['is_third_place'] ?? false);
                });

                if ($finalMatchData) {
                    $finalMatch = [
                        'match_id' => $finalMatchData['match_id'],
                        'round' => $round,
                        'round_name' => $roundData['round_name'],
                        'home_team' => $finalMatchData['home_team'],
                        'away_team' => $finalMatchData['away_team'],
                        'home_score' => $finalMatchData['home_score'],
                        'away_score' => $finalMatchData['away_score'],
                        'status' => $finalMatchData['status'],
                        'is_bye' => $finalMatchData['is_bye'],
                        'is_third_place' => false,
                        'scheduled_at' => $finalMatchData['scheduled_at'],
                        'court' => $finalMatchData['court'],
                        'winner_team_id' => $finalMatchData['winner_team_id'],
                    ];
                }

                $thirdPlaceData = $roundData['matches']->first(function ($match) {
                    return $match['is_third_place'] ?? false;
                });

                if ($thirdPlaceData) {
                    $thirdPlaceMatch = [
                        'match_id' => $thirdPlaceData['match_id'],
                        'round' => $round,
                        'round_name' => 'Tranh hạng Ba',
                        'home_team' => $thirdPlaceData['home_team'],
                        'away_team' => $thirdPlaceData['away_team'],
                        'home_score' => $thirdPlaceData['home_score'],
                        'away_score' => $thirdPlaceData['away_score'],
                        'status' => $thirdPlaceData['status'],
                        'is_bye' => $thirdPlaceData['is_bye'],
                        'is_third_place' => true,
                        'scheduled_at' => $thirdPlaceData['scheduled_at'],
                        'court' => $thirdPlaceData['court'],
                        'winner_team_id' => $thirdPlaceData['winner_team_id'],
                    ];
                }
                continue;
            }

            $leftMatches = collect();
            $rightMatches = collect();

            foreach ($roundData['matches'] as $match) {
                if ($match['next_match_id'] && $match['next_position']) {
                    if ($match['next_position'] === 'home') {
                        $leftMatches->push($match);
                    } else {
                        $rightMatches->push($match);
                    }
                } else {
                    // Fallback: chia đều
                    if ($match['match_id'] % 2 == 0) {
                        $leftMatches->push($match);
                    } else {
                        $rightMatches->push($match);
                    }
                }
            }

            if ($leftMatches->isNotEmpty()) {
                $leftSide->push([
                    'round' => $round,
                    'round_name' => $roundData['round_name'],
                    'matches' => $leftMatches->values()->all(),
                ]);
            }

            if ($rightMatches->isNotEmpty()) {
                $rightSide->push([
                    'round' => $round,
                    'round_name' => $roundData['round_name'],
                    'matches' => $rightMatches->values()->all(),
                ]);
            }
        }

        return ResponseHelper::success([
            'format' => TournamentType::FORMAT_MIXED,
            'format_type_text' => 'mixed',
            'poolStage' => $poolStage->values()->all(),
            'leftSide' => $leftSide->values()->all(),
            'rightSide' => $rightSide->values()->all(),
            'finalMatch' => $finalMatch,
            'thirdPlaceMatch' => $thirdPlaceMatch,
        ]);
    }

    /**
     * Tính tổng điểm aggregate (cho 2 legs)
     */
    private function calculateAggregateScore($matchGroup)
    {
        $homeTotal = 0;
        $awayTotal = 0;

        foreach ($matchGroup as $match) {
            if ($match->status === 'completed') {
                // $homeTotal += $match->home_score ?? 0;
                // $awayTotal += $match->away_score ?? 0;
                if($match->winner_id === $match->away_team_id){
                    $awayTotal += 3;
                }else {
                    $homeTotal += 3;
                }
            }
        }

        return [
            'home' => $homeTotal,
            'away' => $awayTotal,
        ];
    }

    /**
     * Tính bảng xếp hạng cho group
     */
    private function calculateGroupStandings($groupMatches)
    {
        return $this->standingsService->calculateGroupStandings($groupMatches);
    }

    /**
     * Lấy tên round
     */
    private function getRoundName($round, $pairCount, $format)
    {
        return $this->bracketService->getRoundName($round, $pairCount, $format);
    }

    public function getRank($tournament_id)
    {
        $type = TournamentType::where('tournament_id', $tournament_id)->first();
        if (!$type) {
            return ResponseHelper::error('Tournament type not found', 404);
        }

        // Lấy ranking rules từ config, hỗ trợ cả dạng [{...}] và object trực tiếp
        $config = $type->format_specific_config ?? [];
        if (is_array($config) && isset($config[0])) {
            $config = $config[0];
        }
        $rankingRules = collect($config['ranking'] ?? [1, 4, 5])
            ->map(fn($id) => (int)$id)
            ->toArray();

        // Lấy toàn bộ trận đã hoàn thành để dùng cho rule đối đầu (5)
        $allMatches = Matches::where('tournament_type_id', $type->id)
            ->where('status', 'completed')
            ->get();

        $groups = $type->groups()->get();

        // TH 1: Nếu không chia bảng (Tính rank chung)
        if ($groups->isEmpty()) {
            // ✅ LẤY TẤT CẢ ĐỘI THAM GIA GIẢI
            $allTeams = $type->tournament->teams()->with('members')->get();

            // Tính stats cho từng đội
            $rankings = $allTeams->map(function ($team) use ($type) {
                $stats = $this->getTeamStats($team->id, $type->id);
                return array_merge([
                    'team_id' => $team->id,
                    'team_name' => $team->name ?? 'Unknown',
                    'team_avatar' => $team->avatar ?? '',
                ], $stats);
            });

            // ✅ SẮP XẾP THEO rankingRules (1,4,5...) giống TeamRanking, có dùng đối đầu
            $rankings = $rankings->sort(function ($a, $b) use ($rankingRules, $allMatches) {
                // Đội đã đánh luôn đứng trên đội chưa đánh
                if (($a['played'] ?? 0) == 0 && ($b['played'] ?? 0) > 0) return 1;
                if (($b['played'] ?? 0) == 0 && ($a['played'] ?? 0) > 0) return -1;

                foreach ($rankingRules as $ruleId) {
                    switch ($ruleId) {
                        case TournamentType::RANKING_WIN_DRAW_LOSE_POINTS: // 1
                            if ($a['points'] !== $b['points']) {
                                return $b['points'] <=> $a['points'];
                            }
                            break;
                        case TournamentType::RANKING_WIN_RATE: // 2
                            if (($a['win_rate'] ?? 0) !== ($b['win_rate'] ?? 0)) {
                                return ($b['win_rate'] ?? 0) <=> ($a['win_rate'] ?? 0);
                            }
                            break;
                        case TournamentType::RANKING_SETS_WON: // 3
                            // getRank không có sets_won, bỏ qua hoặc có thể map từ stats nếu cần mở rộng sau
                            break;
                        case TournamentType::RANKING_POINTS_WON: // 4
                            if ($a['point_diff'] !== $b['point_diff']) {
                                return $b['point_diff'] <=> $a['point_diff'];
                            }
                            break;
                        case TournamentType::RANKING_HEAD_TO_HEAD: // 5
                            $h2h = $this->getHeadToHeadResultForRank(
                                $a['team_id'],
                                $b['team_id'],
                                $allMatches
                            );
                            if ($h2h !== 0) {
                                return $h2h;
                            }
                            break;
                        case TournamentType::RANKING_RANDOM_DRAW: // 6
                            return $a['team_id'] <=> $b['team_id'];
                    }
                }

                // Fallback cuối cùng: hiệu số điểm rồi id
                if ($a['point_diff'] !== $b['point_diff']) {
                    return $b['point_diff'] <=> $a['point_diff'];
                }
                return $a['team_id'] <=> $b['team_id'];
            })->values();

            // ✅ GÁN RANK SAU KHI ĐÃ SẮP XẾP
            $rankings = $rankings->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            });

            return ResponseHelper::success(['rankings' => $rankings]);
        }

        // TH 2: Nếu có chia bảng
        $groupRankings = $groups->map(function ($group) use ($type, $rankingRules, $allMatches) {
            // ✅ LẤY TẤT CẢ ĐỘI TRONG BẢNG (từ group_team pivot table)
            $teamsInGroup = $group->teams()->with('members')->get();

            // Nếu không có đội nào được assign vào bảng này
            if ($teamsInGroup->isEmpty()) {
                return [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'rankings' => [],
                ];
            }

            // Tính stats cho từng đội
            $rankings = $teamsInGroup->map(function ($team) use ($type, $group) {
                $stats = $this->getTeamStatsInGroup($team->id, $type->id, $group->id);
                return array_merge([
                    'team_id' => $team->id,
                    'team_name' => $team->name ?? 'Unknown',
                    'team_avatar' => $team->avatar ?? '',
                ], $stats);
            });

            // Chỉ dùng các trận thuộc group này cho rule đối đầu
            $groupMatches = $allMatches->where('group_id', $group->id)->values();

            // ✅ SẮP XẾP THEO rankingRules (1,4,5...) giống TeamRanking, có dùng đối đầu trong group
            $rankings = $rankings->sort(function ($a, $b) use ($rankingRules, $groupMatches) {
                // Đội đã đánh luôn đứng trên đội chưa đánh
                if (($a['played'] ?? 0) == 0 && ($b['played'] ?? 0) > 0) return 1;
                if (($b['played'] ?? 0) == 0 && ($a['played'] ?? 0) > 0) return -1;

                foreach ($rankingRules as $ruleId) {
                    switch ($ruleId) {
                        case TournamentType::RANKING_WIN_DRAW_LOSE_POINTS: // 1
                            if ($a['points'] !== $b['points']) {
                                return $b['points'] <=> $a['points'];
                            }
                            break;
                        case TournamentType::RANKING_WIN_RATE: // 2
                            if (($a['win_rate'] ?? 0) !== ($b['win_rate'] ?? 0)) {
                                return ($b['win_rate'] ?? 0) <=> ($a['win_rate'] ?? 0);
                            }
                            break;
                        case TournamentType::RANKING_SETS_WON: // 3
                            break;
                        case TournamentType::RANKING_POINTS_WON: // 4
                            if ($a['point_diff'] !== $b['point_diff']) {
                                return $b['point_diff'] <=> $a['point_diff'];
                            }
                            break;
                        case TournamentType::RANKING_HEAD_TO_HEAD: // 5
                            $h2h = $this->getHeadToHeadResultForRank(
                                $a['team_id'],
                                $b['team_id'],
                                $groupMatches
                            );
                            if ($h2h !== 0) {
                                return $h2h;
                            }
                            break;
                        case TournamentType::RANKING_RANDOM_DRAW: // 6
                            return $a['team_id'] <=> $b['team_id'];
                    }
                }

                if ($a['point_diff'] !== $b['point_diff']) {
                    return $b['point_diff'] <=> $a['point_diff'];
                }
                return $a['team_id'] <=> $b['team_id'];
            })->values();

            // ✅ GÁN RANK SAU KHI ĐÃ SẮP XẾP
            $rankings = $rankings->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            });

            return [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'rankings' => $rankings,
            ];
        });

        return ResponseHelper::success(['group_rankings' => $groupRankings]);
    }

    /**
     * ✅ HÀM MỚI: Tính stats cho đội trong 1 group cụ thể
     */
    private function getTeamStatsInGroup($teamId, $tournamentTypeId, $groupId)
    {
        $matches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('group_id', $groupId) // ✅ Chỉ lấy trận trong group này
            ->where('status', 'completed')
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId);
            })
            ->with('results')
            ->get();

        return $this->calculateStatsFromMatches($matches, $teamId);
    }

    /**
     * ✅ REFACTOR: Tách logic tính toán stats ra hàm riêng
     * ✅ MỖI LEG THẮNG = 3 ĐIỂM (không tính theo cặp đấu)
     */
    private function calculateStatsFromMatches($matches, $teamId)
    {
        // ✅ NẾU CHƯA CÓ TRẬN NÀO → TRẢ VỀ STATS RỖNG
        if ($matches->isEmpty()) {
            return [
                'team_id' => $teamId,
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'points' => 0,
                'point_diff' => 0,
                'win_rate' => 0,
            ];
        }

        $totalPoints = 0;
        $wins = 0;
        $draws = 0;
        $losses = 0;
        $pWon = 0;
        $pLost = 0;

        // ✅ TÍNH ĐIỂM TỪNG LEG (không group)
        foreach ($matches as $leg) {
            $homeSetWins = 0;
            $awaySetWins = 0;

            $sets = $leg->results->groupBy('set_number');
            foreach ($sets as $setGroup) {
                $home = $setGroup->firstWhere('team_id', $leg->home_team_id);
                $away = $setGroup->firstWhere('team_id', $leg->away_team_id);

                $homeScore = (int)($home->score ?? 0);
                $awayScore = (int)($away->score ?? 0);

                if ($homeScore > $awayScore) $homeSetWins++;
                elseif ($awayScore > $homeScore) $awaySetWins++;

                // Cộng dồn điểm cho tính point diff
                if ($leg->home_team_id == $teamId) {
                    $pWon += $homeScore;
                    $pLost += $awayScore;
                } elseif ($leg->away_team_id == $teamId) {
                    $pWon += $awayScore;
                    $pLost += $homeScore;
                }
            }

            // ✅ XÁC ĐỊNH THẮNG/THUA/HÒA CHO LEG NÀY
            $isMyTeamHome = ($leg->home_team_id == $teamId);
            $mySetWins = $isMyTeamHome ? $homeSetWins : $awaySetWins;
            $opponentSetWins = $isMyTeamHome ? $awaySetWins : $homeSetWins;

            if ($mySetWins > $opponentSetWins) {
                // ✅ THẮNG LEG NÀY → +3 ĐIỂM
                $wins++;
                $totalPoints += 3;
            } elseif ($mySetWins == $opponentSetWins) {
                // ✅ HÒA LEG NÀY → +1 ĐIỂM
                $draws++;
                $totalPoints += 1;
            } else {
                // ✅ THUA LEG NÀY → +0 ĐIỂM
                $losses++;
            }
        }

        $played = $matches->count(); // Số leg đã chơi

        return [
            'team_id' => $teamId,
            'played' => $played,
            'wins' => $wins,
            'draws' => $draws,
            'losses' => $losses,
            'points' => $totalPoints,
            'point_diff' => $pWon - $pLost,
            'win_rate' => $played > 0 ? round(($wins / $played) * 100, 2) : 0,
        ];
    }

    /**
     * ✅ CẬP NHẬT getTeamStats để dùng chung logic
     */
    private function getTeamStats($teamId, $tournamentTypeId)
    {
        $matches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('status', 'completed')
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId);
            })
            ->with('results')
            ->get();

        return $this->calculateStatsFromMatches($matches, $teamId);
    }
    
    /**
     * So sánh đối đầu phục vụ getRank (dùng local matches truyền vào)
     * Return: -1 nếu team A thắng, 1 nếu team B thắng, 0 nếu hòa hoặc chưa gặp
     */
    private function getHeadToHeadResultForRank($teamA, $teamB, $matches)
    {
        $h2hMatches = $matches->filter(function ($match) use ($teamA, $teamB) {
            return ($match->home_team_id == $teamA && $match->away_team_id == $teamB) ||
                ($match->home_team_id == $teamB && $match->away_team_id == $teamA);
        });

        if ($h2hMatches->isEmpty()) {
            return 0;
        }

        $teamAWins = 0;
        $teamBWins = 0;

        foreach ($h2hMatches as $match) {
            if ($match->winner_id == $teamA) {
                $teamAWins++;
            } elseif ($match->winner_id == $teamB) {
                $teamBWins++;
            }
        }

        if ($teamAWins > $teamBWins) {
            return -1;
        } elseif ($teamBWins > $teamAWins) {
            return 1;
        }

        return 0;
    }
    public function getAdvancementStatus(TournamentType $tournamentType)
    {
        if ($tournamentType->format !== TournamentType::FORMAT_MIXED) {
            return ResponseHelper::error('Chỉ áp dụng cho format Mixed', 400);
        }

        $groups = $tournamentType->groups()->with(['matches.homeTeam', 'matches.awayTeam'])->get();

        $groupStatus = $groups->map(function ($group) use ($tournamentType) {
            $matches = $group->matches;
            $totalMatches = $matches->count();
            $completedMatches = $matches->where('status', 'completed')->count();
            $isCompleted = $totalMatches > 0 && $completedMatches === $totalMatches;

            $rules = PoolAdvancementRule::where('group_id', $group->id)
                ->with('nextMatch')
                ->orderBy('rank')
                ->get()
                ->map(function ($rule) use ($isCompleted, $matches) {
                    $advancedTeam = null;

                    if ($isCompleted) {
                        $standings = TournamentService::calculateGroupStandings($matches);
                        $teamAtRank = $standings->get($rule->rank - 1);
                        $advancedTeam = $teamAtRank ? $teamAtRank['team'] : null;
                    }

                    return [
                        'rank' => $rule->rank,
                        'next_match_id' => $rule->next_match_id,
                        'next_position' => $rule->next_position,
                        'team' => $advancedTeam,
                        'is_advanced' => $isCompleted && $advancedTeam !== null,
                    ];
                });

            return [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'total_matches' => $totalMatches,
                'completed_matches' => $completedMatches,
                'progress_percent' => $totalMatches > 0 ? round(($completedMatches / $totalMatches) * 100, 1) : 0,
                'is_completed' => $isCompleted,
                'advancement_rules' => $rules,
            ];
        });

        $overallCompleted = $groupStatus->every(fn($g) => $g['is_completed']);

        return ResponseHelper::success([
            'tournament_type_id' => $tournamentType->id,
            'groups' => $groupStatus,
            'all_pools_completed' => $overallCompleted,
            'knockout_ready' => $overallCompleted,
        ]);
    }

    public function regenerateMatches(TournamentType $tournamentType)
    {
        $completedMatches = $tournamentType->matches()
            ->where('status', 'completed')
            ->exists();

        if ($completedMatches) {
            return ResponseHelper::error(
                'Không thể chia lại cặp đấu. Đã có trận đấu hoàn thành thuộc thể thức này.',
                400
            );
        }

        DB::beginTransaction();
        try {
            $tournamentType->load('tournament.teams.members');
            if ($tournamentType->format == TournamentType::FORMAT_MIXED) {
                $tournamentType->advancementRules()->delete();
                $tournamentType->groups()->delete();
            }
            $tournamentType->matches()->each(function ($match) {
                $match->results()->delete();
                $match->delete();
            });
            $this->generateMatchesForType($tournamentType);

            DB::commit();
            return ResponseHelper::success(
                new TournamentTypeResource($tournamentType->fresh()),
                'Chia lại cặp đấu thành công.'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error('Lỗi khi chia lại cặp đấu: ' . $e->getMessage(), 500);
        }
    }
    /**
     * Lưu đội vào các bảng và generate matches
     * POST /api/tournament-types/{tournamentType}/assign-teams-and-generate
     */
    public function assignTeamsAndGenerate(Request $request, TournamentType $tournamentType)
    {
        $isDraft = $request->boolean('is_draft');
        $rules = [
            'groups' => ['required', 'array'],
            'groups.*.group_id' => ['required', 'exists:groups,id'],
            'groups.*.team_ids' => ['present', 'array'], // 🔥 QUAN TRỌNG
            'groups.*.team_ids.*' => ['exists:teams,id'],
            'is_draft' => ['sometimes', 'boolean'],
        ];

        // Publish mới bắt min:1
        if (!$isDraft) {
            $rules['groups.*.team_ids'][] = 'min:1';
        }

        $validated = $request->validate($rules);
        if (!$isDraft) {
            $completedMatches = $tournamentType->matches()
                ->where('status', 'completed')
                ->exists();

            if ($completedMatches) {
                return ResponseHelper::error(
                    'Không thể sắp xếp lại. Đã có trận đấu hoàn thành.',
                    400
                );
            }
        }

        DB::beginTransaction();
        try {
            // 1. Xóa các assignment cũ và matches cũ
            foreach ($tournamentType->groups as $group) {
                $group->teams()->detach();
            }
            if (!$isDraft) {
                $tournamentType->matches()->each(function ($match) {
                    $match->results()->delete();
                    $match->delete();
                });
                if ($tournamentType->format === TournamentType::FORMAT_MIXED) {
                    $tournamentType->advancementRules()->delete();
                }
            }
            foreach ($validated['groups'] as $groupData) {
                $group = Group::findOrFail($groupData['group_id']);

                $syncData = [];
                foreach ($groupData['team_ids'] as $order => $teamId) {
                    $syncData[$teamId] = ['order' => $order];
                }
                $group->teams()->sync($syncData);
            }
            if (!$isDraft) {
                $this->generateMatchesForTypeWithAssignedTeams($tournamentType);
            }
            DB::commit();

            return ResponseHelper::success(
                new TournamentTypeResource($tournamentType->fresh()),
                $isDraft
                    ? 'Đã lưu tiến trình sắp xếp đội'
                    : 'Sắp xếp đội và tạo lịch thi đấu thành công'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error('Lỗi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate matches với đội đã được assign vào bảng
     */
    protected function generateMatchesForTypeWithAssignedTeams(TournamentType $type)
    {
        $config = $type->format_specific_config ?? [];
        $numLegs = $type->num_legs ?? 1;

        switch ($type->format) {
            case TournamentType::FORMAT_ROUND_ROBIN:
                $teams = $type->tournament->teams()->with('members')->get();
                $this->generateRoundRobin($type, $teams, $numLegs);
                break;

            case TournamentType::FORMAT_ELIMINATION:
                $teams = $type->tournament->teams()->with('members')->get();
                $this->generateElimination($type, $teams, $config, $numLegs);
                break;

            case TournamentType::FORMAT_MIXED:
                $this->generateMixedWithAssignedTeams($type, $config, $numLegs);
                break;
        }
    }

    private function arrangeAdvancingTeams($advancingByRank, $pairingMode = null, $manualPairings = null)
    {
        // ✅ NORMALIZE: Trim và lowercase
        $pairingMode = $pairingMode ? strtolower(trim($pairingMode)) : null;

        switch ($pairingMode) {
            case self::PAIRING_MODE_SYMMETRIC:
            case 'symmetric':
                return $this->arrangeAdvancingTeamsSymmetric($advancingByRank);

            case self::PAIRING_MODE_MANUAL:
            case 'manual':
                return $this->arrangeAdvancingTeamsManual($advancingByRank, $manualPairings);

            case self::PAIRING_MODE_SEQUENTIAL:
            case 'sequential':
            case null:
            case '':
            default:
                return $this->arrangeAdvancingTeamsSequential($advancingByRank);
        }
    }

    /**
     * ============================================================================
     * PAIRING MODE 1: SEQUENTIAL (Tuần tự)
     * ============================================================================
     */
    /**
    * Pattern: Nhất A vs Nhì B, Nhất B vs Nhì A, Nhất C vs Nhì D, Nhất D vs Nhì C...
    * Ví dụ 8 bảng: A-B, B-A, C-D, D-C, E-F, F-E, G-H, H-G
    */
    private function arrangeAdvancingTeamsSequential($advancingByRank)
    {
        $advancing = collect();

        $firstPlaceTeams = $advancingByRank->get(0, collect());
        $secondPlaceTeams = $advancingByRank->get(1, collect());

        $numFirstPlace = $firstPlaceTeams->count();
        $numSecondPlace = $secondPlaceTeams->count();

        // ✅ PATTERN TUẦN TỰ: Nhất A, Nhì B, Nhất B, Nhì A, Nhất C, Nhì D, Nhất D, Nhì C...
        for ($i = 0; $i < max($numFirstPlace, $numSecondPlace); $i += 2) {
            // Cặp thứ i: Nhất[i] vs Nhì[i+1]
            if ($i < $numFirstPlace) {
                $advancing->push($firstPlaceTeams->get($i));
            }
            if (($i + 1) < $numSecondPlace) {
                $advancing->push($secondPlaceTeams->get($i + 1));
            }

            // Cặp thứ i+1: Nhất[i+1] vs Nhì[i]
            if (($i + 1) < $numFirstPlace) {
                $advancing->push($firstPlaceTeams->get($i + 1));
            }
            if ($i < $numSecondPlace) {
                $advancing->push($secondPlaceTeams->get($i));
            }
        }

        // Xử lý các hạng còn lại (hạng 3, 4...)
        foreach ($advancingByRank as $rank => $teamsAtRank) {
            if ($rank < 2) continue;
            foreach ($teamsAtRank as $team) {
                $advancing->push($team);
            }
        }

        return $advancing;
    }

    /**
     * ============================================================================
     * PAIRING MODE 2: SYMMETRIC (Đối xứng)
     * ============================================================================
     */
    /**
     * Pattern: Nhất A vs Nhì H, Nhất B vs Nhì G, Nhất C vs Nhì F, Nhất D vs Nhì E...
     * Ví dụ 8 bảng: A-H, B-G, C-F, D-E, E-D, F-C, G-B, H-A
     */
    private function arrangeAdvancingTeamsSymmetric($advancingByRank)
    {
        $advancing = collect();

        $firstPlaceTeams = $advancingByRank->get(0, collect());
        $secondPlaceTeams = $advancingByRank->get(1, collect());

        $numFirstPlace = $firstPlaceTeams->count();
        $numSecondPlace = $secondPlaceTeams->count();

        // Pattern đối xứng: lấy từ 2 đầu mảng
        for ($i = 0; $i < max($numFirstPlace, $numSecondPlace); $i++) {
            // Thêm nhất bảng thứ i (A, B, C, D...)
            if ($i < $numFirstPlace) {
                $advancing->push($firstPlaceTeams->get($i));
            }

            // Thêm nhì bảng đối xứng từ cuối lên (H, G, F, E...)
            $oppositeIndex = $numSecondPlace - 1 - $i;
            if ($oppositeIndex >= 0 && $oppositeIndex < $numSecondPlace) {
                $advancing->push($secondPlaceTeams->get($oppositeIndex));
            }
        }

        // Xử lý các hạng còn lại
        foreach ($advancingByRank as $rank => $teamsAtRank) {
            if ($rank < 2) continue;
            foreach ($teamsAtRank as $team) {
                $advancing->push($team);
            }
        }

        return $advancing;
    }

    /**
     * ============================================================================
     * PAIRING MODE 3: MANUAL (Thủ công)
     * ============================================================================
     */
    /**
     * Sắp xếp theo danh sách thủ công
     * $manualPairings format:
     * [
     *   ['group' => 'A', 'rank' => 1, 'position' => 0],  // Vị trí 0
     *   ['group' => 'C', 'rank' => 2, 'position' => 1],  // Vị trí 1
     *   ['group' => 'B', 'rank' => 1, 'position' => 2],  // Vị trí 2
     *   ...
     * ]
     */
    private function arrangeAdvancingTeamsManual($advancingByRank, $manualPairings)
    {
        if (empty($manualPairings)) {
            // Fallback về sequential nếu không có manual config
            return $this->arrangeAdvancingTeamsSequential($advancingByRank);
        }

        $advancing = collect();

        // Tạo map để tra cứu nhanh: "groupId_rank" => team object
        $teamMap = [];
        foreach ($advancingByRank as $rank => $teamsAtRank) {
            foreach ($teamsAtRank as $team) {
                $groupId = $team->_from_group ?? null;
                if ($groupId) {
                    $key = "{$groupId}_{$rank}";
                    $teamMap[$key] = $team;
                }
            }
        }

        // Sắp xếp theo thứ tự manual
        usort($manualPairings, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        foreach ($manualPairings as $pairing) {
            $groupId = $pairing['group_id'] ?? null;
            $rank = $pairing['rank'] ?? 1;
            $key = "{$groupId}_{$rank}";

            if (isset($teamMap[$key])) {
                $advancing->push($teamMap[$key]);
            }
        }

        return $advancing;
    }

    private function generateMixedWithAssignedTeams(TournamentType $type, $config, $numLegs)
    {
        $matchNumber = 0;
        $mainConfig = is_array($config) && isset($config[0]) ? $config[0] : [];
        $poolConfig = $mainConfig['pool_stage'] ?? [];
        $knockoutConfig = $mainConfig['knockout_stage'] ?? [];

        $numAdvancing = max(1, (int)($poolConfig['num_advancing_teams'] ?? 1));
        $advancedToNext = filter_var($mainConfig['advanced_to_next_round'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasThirdPlace = filter_var($mainConfig['has_third_place_match'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // ✅ LẤY PAIRING MODE TỪ CONFIG (MẶC ĐỊNH: SEQUENTIAL)
        $pairingMode = $knockoutConfig['pairing_mode'] ?? null;  // Không set default ở đây
        $manualPairings = $knockoutConfig['manual_pairings'] ?? null;

        $groups = $type->groups()->with('teams.members')->get();
        $advancingByRank = collect();
        $groupObjects = collect();

        // ===== PHASE 2: TẠO VÒNG BẢNG =====
        foreach ($groups as $group) {
            $groupObjects->push($group);

            // ✅ LẤY ĐỘI TỪ PIVOT TABLE
            $chunk = $group->teams; // Đã có relation và orderBy
            $count = $chunk->count();

            if ($count === 1) {
                $matchNumber++;
                $byeMatch = $type->matches()->create([
                    'tournament_type_id' => $type->id,
                    'home_team_id' => $chunk[0]->id,
                    'away_team_id' => null,
                    'round' => 1,
                    'leg' => 1,
                    'is_bye' => true,
                    'status' => 'pending',
                    'name_of_match' => "Trận đấu số {$matchNumber}",
                ]);

                if (!isset($advancingByRank[0])) {
                    $advancingByRank[0] = collect();
                }
                $advancingByRank[0]->push((object)[
                    'team_id' => $chunk[0]->id,
                    '_bye_match' => $byeMatch,
                    '_group_id' => null,
                    '_group_index' => $groups->search($group),
                    '_rank' => 1,
                ]);
                continue;
            }

            // ✅ ROUND ROBIN VỚI ĐÚNG THỨ TỰ ĐÃ ASSIGN
            $scheduleTeams = $chunk->pluck('id')->toArray();
            $isOdd = $count % 2 !== 0;
            if ($isOdd) {
                $scheduleTeams[] = 'BYE';
                $count++;
            }
            $totalRounds = $count - 1;

            for ($leg = 1; $leg <= $numLegs; $leg++) {
                $currentSchedule = $scheduleTeams;

                for ($round = 1; $round <= $totalRounds; $round++) {
                    $halfSize = $count / 2;
                    $homeTeams = array_slice($currentSchedule, 0, $halfSize);
                    $awayTeams = array_reverse(array_slice($currentSchedule, $halfSize));

                    for ($i = 0; $i < $halfSize; $i++) {
                        $homeId = $homeTeams[$i];
                        $awayId = $awayTeams[$i];

                        if ($homeId === 'BYE' || $awayId === 'BYE') {
                            continue;
                        }

                        $matchNumber++;
                        $isReturnLeg = ($leg % 2 === 0);
                        $finalHomeId = $isReturnLeg ? $awayId : $homeId;
                        $finalAwayId = $isReturnLeg ? $homeId : $awayId;

                        $type->matches()->create([
                            'group_id' => $group->id,
                            'tournament_type_id' => $type->id,
                            'home_team_id' => $finalHomeId,
                            'away_team_id' => $finalAwayId,
                            'round' => 1,
                            'leg' => $leg,
                            'is_bye' => false,
                            'status' => 'pending',
                            'name_of_match' => "Trận đấu số {$matchNumber}",
                        ]);
                    }

                    $firstTeam = array_shift($currentSchedule);
                    $lastTeam = array_pop($currentSchedule);
                    array_unshift($currentSchedule, $firstTeam, $lastTeam);
                }
            }

            // Thu thập placeholder
            for ($k = 0; $k < min($numAdvancing, $chunk->count()); $k++) {
                if (!isset($advancingByRank[$k])) {
                    $advancingByRank[$k] = collect();
                }

                $advancingByRank[$k]->push((object)[
                    'team_id' => null,
                    '_from_group' => $group->id,
                    '_group_index' => $groups->search($group),
                    '_rank' => $k + 1,
                ]);
            }
        }

        // ✅ SẮP XẾP ĐỘI ADVANCING THEO MODE ĐÃ CHỌN
        $advancing = $this->arrangeAdvancingTeams($advancingByRank, $pairingMode, $manualPairings);

        $totalAdvancing = $advancing->count();
        $willHaveBye = ($totalAdvancing % 2 !== 0);

        if ($willHaveBye && !$advancedToNext) {
            $advancing->push((object)[
                'team_id' => null,
                '_placeholder' => true,
            ]);
        }

        $knockoutRounds = $this->generateKnockoutStage(
            $type,
            $advancing,
            $hasThirdPlace,
            $advancedToNext,
            $numLegs,
            $matchNumber
        );

        $this->createPoolAdvancementRules($type, $knockoutRounds, $advancing, $groupObjects);
    }

    /**
     * Lấy groups với teams đã assign (nếu có)
     * GET /api/tournament-types/{tournamentType}/groups-with-teams
     */
    public function getGroupsWithTeams(TournamentType $tournamentType)
    {
        if ($tournamentType->format !== TournamentType::FORMAT_MIXED) {
            return ResponseHelper::error('Chỉ áp dụng cho format Mixed', 400);
        }

        $groups = $tournamentType->groups()->with('teams.members')->get();

        // ✅ NẾU CHƯA CÓ BẢNG -> TẠO MỚI
        if ($groups->isEmpty()) {
            $this->createEmptyGroups($tournamentType);
            $groups = $tournamentType->groups()->with('teams.members')->get();
        }

        $availableTeams = $tournamentType->tournament->teams()
            ->with('members')
            ->whereNotIn('id', function($query) use ($tournamentType) {
                $query->select('team_id')
                    ->from('group_team')
                    ->whereIn('group_id', $tournamentType->groups()->pluck('id'));
            })
            ->get();

        // ✅ LẤY SPORT_ID TỪ TOURNAMENT
        $sportId = $tournamentType->tournament->sport_id ?? null;

        return ResponseHelper::success([
            'groups' => $groups->map(fn($g) => [
                'group_id' => $g->id,
                'group_name' => $g->name,
                'teams' => $g->teams->map(fn($t) => [
                    'team_id' => $t->id,
                    'team_name' => $t->name,
                    'team_avatar' => $t->avatar,
                    'vndupr_avg' => $this->calculateTeamVnduprAvg($t, $sportId), // ✅ THÊM VNDUPR
                    'members' => $t->members->map(fn($m) => [
                        'user_id' => $m->user_id ?? $m->id ?? null,
                        'full_name' => $m->full_name ?? $m->user->full_name ?? 'Unknown',
                        'avatar_url' => $m->avatar_url ?? $m->user->avatar_url ?? null,
                    ]),
                ]),
            ]),
            'available_teams' => $availableTeams->map(fn($t) => [
                'team_id' => $t->id,
                'team_name' => $t->name,
                'team_avatar' => $t->avatar,
                'vndupr_avg' => $this->calculateTeamVnduprAvg($t, $sportId), // ✅ THÊM VNDUPR
                'members' => $t->members->map(fn($m) => [
                    'user_id' => $m->user_id ?? $m->id ?? null,
                    'full_name' => $m->full_name ?? $m->user->full_name ?? 'Unknown',
                    'avatar_url' => $m->avatar_url ?? $m->user->avatar_url ?? null,
                ]),
            ]),
            'config' => [
                'num_groups' => $groups->count(),
                'num_advancing' => $tournamentType->format_specific_config[0]['pool_stage']['num_advancing_teams'] ?? 1,
            ]
        ]);
    }
    /**
     * ✅ HÀM TÍNH ĐIỂM VNDUPR TRUNG BÌNH CỦA ĐỘI
     */
    private function calculateTeamVnduprAvg($team, $sportId)
    {
        if (!$sportId) {
            return null;
        }

        // ✅ XỬ LÝ NHIỀU TRƯỜNG HỢP RELATION
        $userIds = collect($team->members)
            ->map(function($member) {
                // TH1: Member là User trực tiếp (hasMany users)
                if (isset($member->id) && !isset($member->user_id)) {
                    return $member->id;
                }
                // TH2: Member là pivot/intermediate table (belongsToMany)
                if (isset($member->user_id)) {
                    return $member->user_id;
                }
                // TH3: Member có relation user
                if (isset($member->user) && isset($member->user->id)) {
                    return $member->user->id;
                }
                return null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($userIds)) {
            return null;
        }

        // Query điểm VNDUPR từ database
        $scores = DB::table('user_sport as us')
            ->join('user_sport_scores as uss', 'us.id', '=', 'uss.user_sport_id')
            ->where('us.sport_id', $sportId)
            ->where('uss.score_type', 'vndupr_score')
            ->whereIn('us.user_id', $userIds)
            ->pluck('uss.score_value', 'us.user_id')
            ->map(fn($v) => (float)$v)
            ->values()
            ->all();

        if (empty($scores)) {
            return null;
        }

        // Tính trung bình
        $average = array_sum($scores);

        return round($average, 2);
    }
    private function determineWinner($homeTotal, $awayTotal, $homeTeamId, $awayTeamId, $firstMatch, $matchGroup)
    {
        if (!$matchGroup->every(fn($l) => $l->status === 'completed')) {
            return null;
        }

        if ($homeTotal > $awayTotal) return $homeTeamId;
        if ($awayTotal > $homeTotal) return $awayTeamId;

        // TH HÒA → Kiểm tra advance thủ công
        if ($homeTotal === $awayTotal && $firstMatch->next_match_id) {
            $nextMatch = Matches::find($firstMatch->next_match_id);
            if ($nextMatch) {
                $advancedTeamId = ($firstMatch->next_position === 'home')
                    ? $nextMatch->home_team_id
                    : $nextMatch->away_team_id;

                if ($advancedTeamId === $homeTeamId) return $homeTeamId;
                if ($advancedTeamId === $awayTeamId) return $awayTeamId;
            }
        }

        return null;
    }
}
