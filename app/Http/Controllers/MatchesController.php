<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\MatchDetailResource;
use App\Http\Resources\MatchesResource;
use App\Jobs\SendPushJob;
use App\Models\Matches;
use App\Models\Team;
use App\Models\TeamRanking;
use App\Models\TournamentStaff;
use App\Models\TournamentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PoolAdvancementRule;
use App\Models\VnduprHistory;
use App\Services\TournamentService;
use Illuminate\Support\Facades\Auth;

class MatchesController extends Controller
{
    public function index(Request $request, $tournamenttypeId)
    {
        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:200',
        ]);
        $matches = Matches::withFullRelations()
            ->where('tournament_type_id', $tournamenttypeId)
            ->paginate($validated['per_page'] ?? Matches::PER_PAGE);

        $data = [
            'matches' => MatchesResource::collection($matches),
        ];

        $meta = [
            'current_page' => $matches->currentPage(),
            'last_page' => $matches->lastPage(),
            'per_page' => $matches->perPage(),
            'total' => $matches->total(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách trận đấu thành công', 200, $meta);
    }

    public function detail(Request $request, $matchId)
    {
        $match = Matches::withFullRelations()->find($matchId);
        if (!$match) {
            return ResponseHelper::error('Match not found', 404);
        }
        return ResponseHelper::success(new MatchDetailResource($match));
    }
    public function update(Request $request, $matchId)
    {
        $validated = $request->validate([
            'court' => 'nullable|integer',
            'results' => 'nullable|array',
            'results.*.id' => 'sometimes|exists:match_results,id',
            'results.*.set_number' => 'required|integer|min:1',
            'results.*.team_id' => 'required|integer|exists:teams,id',
            'results.*.score' => 'required|integer|min:0',
        ]);

        // 🔍 Lấy match + luật thi đấu
        $match = Matches::with('results', 'tournamentType')->find($matchId);
        if (!$match) {
            return ResponseHelper::error('Không tìm thấy trận đấu.', 404);
        }
        $tournament = $match->tournamentType->tournament->load('staff');
        $canScore = $tournament->hasScoringPermission(Auth::id());

        if (!$canScore) {
            return ResponseHelper::error('Bạn không có quyền thực hiện hành động này', 400);
        }

        if ($match->status === Matches::STATUS_COMPLETED || $match->home_team_confirm == 1 || $match->away_team_confirm == 1) {
            return ResponseHelper::error('Kết quả trận đấu đã được xác nhận không thể thay đổi điểm số', 400);
        }

        $match->update(['court' => $validated['court'] ?? $match->court]);
    
        // Validate cơ bản: mỗi set phải có đủ 2 đội
        $sets = collect($validated['results'] ?? [])->groupBy('set_number');
        $keepIds = [];

        foreach ($sets as $setNumber => $setResults) {
            // chỉ xử lý khi có đủ 2 đội trong set
            if ($setResults->count() !== 2) {
                return ResponseHelper::error("Set $setNumber thiếu kết quả của một đội. Vui lòng cung cấp điểm số cho cả hai đội.", 400);
            }

            $teamA = $setResults[0];
            $teamB = $setResults[1];
            $A = (int)$teamA['score'];
            $B = (int)$teamB['score'];
    
            // Kiểm tra điểm số không âm
            if ($A < 0 || $B < 0) {
                return ResponseHelper::error("Điểm số không hợp lệ trong set $setNumber.", 400);
            }
    
            // Lưu kết quả (won_match tạm thời = false, sẽ tính lại khi confirm)
            foreach ($setResults as $r) {
                $result = $match->results()->updateOrCreate(
                    [
                        'match_id' => $match->id,
                        'team_id' => $r['team_id'],
                        'set_number' => $r['set_number'],
                    ],
                    [
                        'score' => $r['score'],
                        'won_match' => false, // Sẽ được tính lại khi confirm
                    ]
                );
                $keepIds[] = $result->id;
            }
        }

        // 🧹 Xoá kết quả thừa
        $match->results()->whereNotIn('id', $keepIds)->delete();
        $match->update([
            'home_team_confirm' => 0,
            'away_team_confirm' => 0,
        ]);

        $match->load('results');

        return ResponseHelper::success(new MatchDetailResource($match));
    }

    private function calculateMatchWinner($match, $setsPerMatch)
    {
        $teamIds = $match->results->pluck('team_id')->unique()->values()->all();

        // Nếu không đủ 2 đội (dữ liệu bất thường) thì không quyết định
        if (count($teamIds) < 2) {
            return;
        }

        // Khởi tạo wins = 0 cho mỗi team
        $setWins = array_fill_keys($teamIds, 0);

        // Đếm số set thắng (won_match = true)
        foreach ($match->results as $r) {
            if ($r->won_match) {
                if (!isset($setWins[$r->team_id]))
                    $setWins[$r->team_id] = 0;
                $setWins[$r->team_id]++;
            }
        }

        // Nếu không có set nào được đánh dấu là won_match thì không quyết (dữ liệu chưa đủ)
        if (array_sum($setWins) === 0) {
            return;
        }
    
        // Tìm đội thắng nhiều set nhất; hòa → phá bằng tổng điểm ghi được
        $maxWins = max($setWins);
        $tiedTeams = array_keys(array_filter($setWins, fn($w) => $w === $maxWins));

        if (count($tiedTeams) === 1) {
            $winnerTeamId = $tiedTeams[0];
        } else {
            // phá hòa bằng tổng điểm ghi được qua tất cả set
            $totals = array_fill_keys($teamIds, 0);
            foreach ($match->results as $r) {
                $tid = $r->team_id;
                if (isset($totals[$tid])) {
                    $totals[$tid] += (int) $r->score;
                }
            }
            $tieTotals = array_intersect_key($totals, array_flip($tiedTeams));
            $maxPts = max($tieTotals);
            $byPts = array_keys(array_filter($tieTotals, fn($p) => $p === $maxPts));
            $winnerTeamId = count($byPts) === 1 ? $byPts[0] : null;
        }

        // Cập nhật match
        $match->update([
            'winner_id' => $winnerTeamId
        ]);
    
        // Chỉ tiến vào vòng sau nếu là 1 leg (logic cũ)
        $numLegs = $match->tournamentType->num_legs ?? 1;
        
        if (
            $numLegs == 1 && 
            $winnerTeamId &&
            in_array($match->tournamentType->format, [
                TournamentType::FORMAT_MIXED,
                TournamentType::FORMAT_ELIMINATION,
            ])
        ) {
            $this->advanceWinnerToNextRound($match, $winnerTeamId);
        }
        // Cập nhật lại bảng xếp hạng
        $this->recalculateRankings($match->tournament_type_id);
    }

    private function advanceWinnerToNextRound($match, $winnerTeamId)
    {
        $tournamentType = $match->tournamentType;
        if ((int) $match->round === 1 && $tournamentType->format === TournamentType::FORMAT_MIXED) {
            $this->checkAndAdvanceFromPool($match);
            return;
        }

        if ($match->next_match_id) {
            $nextMatch = Matches::find($match->next_match_id);
            if ($nextMatch) {
                if ($match->next_position === 'home') {
                    $nextMatch->update([
                        'home_team_id' => $winnerTeamId,
                        'status' => $nextMatch->is_bye ? Matches::STATUS_COMPLETED : Matches::STATUS_PENDING,
                        'winner_id' => $nextMatch->is_bye ? $winnerTeamId : $nextMatch->winner_id,
                    ]);
                } elseif ($match->next_position === 'away') {
                    $nextMatch->update([
                        'away_team_id' => $winnerTeamId,
                        'status' => $nextMatch->is_bye ? Matches::STATUS_COMPLETED : Matches::STATUS_PENDING,
                        'winner_id' => $nextMatch->is_bye ? $winnerTeamId : $nextMatch->winner_id,
                    ]);
                }

                // ✅ Nếu trận tiếp theo bỗng trở thành "đã xong" (bye), tiếp tục gửi nó lên vòng sau
                if ($nextMatch->status === Matches::STATUS_COMPLETED && $nextMatch->winner_id) {
                    $this->advanceWinnerToNextRound($nextMatch, $nextMatch->winner_id);
                }

                // ✅ Kiểm tra Best Loser nếu round hiện tại đã kết thúc
                $this->checkAndAssignBestLosersForElimination($match);
            }
        }

        // 🥉 Xử lý đội THUA vào trận tranh hạng 3 (nếu có)
        if ($match->loser_next_match_id) {
            // Xác định đội thua
            $loserTeamId = null;
            if ($match->home_team_id == $winnerTeamId) {
                $loserTeamId = $match->away_team_id;
            } elseif ($match->away_team_id == $winnerTeamId) {
                $loserTeamId = $match->home_team_id;
            }

            if ($loserTeamId) {
                $loserNextMatch = Matches::find($match->loser_next_match_id);
                if ($loserNextMatch) {
                    if ($match->loser_next_position === 'home') {
                        $loserNextMatch->update([
                            'home_team_id' => $loserTeamId,
                            'status' => Matches::STATUS_PENDING,
                        ]);
                    } elseif ($match->loser_next_position === 'away') {
                        $loserNextMatch->update([
                            'away_team_id' => $loserTeamId,
                            'status' => Matches::STATUS_PENDING,
                        ]);
                    }
                }
            }
        }
    }

    private function checkAndAdvanceFromPool($completedMatch)
    {
        $groupId = $completedMatch->group_id;
        if (!$groupId) return;

        $tournamentTypeId = $completedMatch->tournament_type_id;

        // 1. Kiểm tra xem tất cả các trận trong bảng đã xong chưa
        $allGroupMatches = Matches::where('group_id', $groupId)
            ->where('round', 1)
            ->get();

        $allCompleted = $allGroupMatches->every(fn($m) => $m->status === Matches::STATUS_COMPLETED);
        if (!$allCompleted) return;

        // 2. QUAN TRỌNG: Phải cập nhật lại Rank chuẩn theo Rule trước khi chọn đội đi tiếp
        $this->recalculateRankings($tournamentTypeId);

        // 3. Lấy bảng xếp hạng của các đội TRONG GROUP NÀY từ bảng TeamRanking
        // Chúng ta dựa vào việc team đó có thi đấu trong matches của Group này
        $teamIdsInGroup = $allGroupMatches->pluck('home_team_id')
            ->merge($allGroupMatches->pluck('away_team_id'))
            ->unique()
            ->filter();

        $standings = TeamRanking::where('tournament_type_id', $tournamentTypeId)
            ->whereIn('team_id', $teamIdsInGroup)
            ->orderBy('rank', 'asc') // Đội rank 1 (tổng) sẽ đứng đầu trong nhóm này
            ->get()
            ->values();

        // 4. Lấy luật tiến cử (Advancement Rules)
        $rules = PoolAdvancementRule::where('group_id', $groupId)
            ->orderBy('rank') // rank ở đây là vị trí trong bảng (1, 2...)
            ->get();

        if ($rules->isEmpty()) return;

        // ✅ Group rules theo rank để xử lý từng đội
        $rulesByRank = $rules->groupBy('rank');

        foreach ($rulesByRank as $rank => $rulesForRank) {
            // Lấy đội tương ứng với vị trí được quy định
            $teamAtPosition = $standings->get($rank - 1);

            if (!$teamAtPosition) continue;

            // ✅ Cập nhật TẤT CẢ các legs của đội này
            foreach ($rulesForRank as $rule) {
                $nextMatch = Matches::find($rule->next_match_id);
                if (!$nextMatch) continue;

                $updateData = ['status' => Matches::STATUS_PENDING];
                if ($rule->next_position === 'home') {
                    $updateData['home_team_id'] = $teamAtPosition->team_id;
                } else {
                    $updateData['away_team_id'] = $teamAtPosition->team_id;
                }

                $nextMatch->update($updateData);

                // Nếu trận knockout này đủ 2 đội, có thể update status thành ready/pending
                if ($nextMatch->home_team_id && $nextMatch->away_team_id) {
                    $nextMatch->update(['status' => Matches::STATUS_PENDING]);
                }
            }
        }

        $this->checkAllPoolsCompleted($tournamentTypeId);
    }
    private function checkAllPoolsCompleted($tournamentTypeId)
    {
        $allPoolMatches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('round', 1)
            ->get();

        if ($allPoolMatches->isEmpty()) {
            return;
        }

        $allCompleted = $allPoolMatches->every(fn($m) => $m->status === 'completed');

        if (!$allCompleted) {
            return;
        }

        // Tất cả pool đã hoàn thành
        $tournamentType = TournamentType::find($tournamentTypeId);
        if (!$tournamentType) {
            return;
        }

        $config = $tournamentType->format_specific_config ?? [];
        $mainConfig = is_array($config) && isset($config[0]) ? $config[0] : [];
        $advancedToNext = filter_var($mainConfig['advanced_to_next_round'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Lấy tất cả trận knockout round 2 (vòng đấu đầu tiên sau pool)
        $knockoutMatches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('round', 2)
            ->where('status', 'pending')
            ->get();

        if ($knockoutMatches->isEmpty()) {
            return;
        }

        // Tìm các trận có đội lẻ (is_bye = true hoặc có 1 team null)
        $byeMatches = $knockoutMatches->filter(function ($match) {
            return $match->is_bye || $match->home_team_id === null || $match->away_team_id === null;
        });

        if ($byeMatches->isEmpty()) {
            // Không có đội lẻ, tất cả đã sẵn sàng
            return;
        }

        if (!$advancedToNext) {
            // Nếu advanced_to_next_round = false, giữ nguyên bye
            // Các đội bye sẽ tự động đi tiếp
            return;
        }

        // advanced_to_next_round = true: Tìm best loser để đấu với đội lẻ
        $this->assignBestLosersToByeMatches($tournamentTypeId, $byeMatches);
    }

    private function assignBestLosersToByeMatches($tournamentTypeId, $byeMatches)
    {
        // 1. Lấy thông tin TournamentType và cấu hình ranking
        $tournamentType = TournamentType::find($tournamentTypeId);
        if (!$tournamentType) return;

        // Lấy config từ format_specific_config (Hỗ trợ cả dạng mảng bọc ngoài hoặc object trực tiếp)
        $config = $tournamentType->format_specific_config;
        if (is_array($config) && isset($config[0])) {
            $config = $config[0];
        }
    
        // ✅ FIX: Ép kiểu về INTEGER ngay từ đầu
        $rankingRules = collect($config['ranking'] ?? [1, 3])
            ->map(fn($id) => (int)$id)
            ->toArray();
    
        // 1. Lấy tất cả các groups
        $groups = DB::table('groups')
            ->join('matches', 'groups.id', '=', 'matches.group_id')
            ->where('matches.tournament_type_id', $tournamentTypeId)
            ->where('matches.round', 1)
            ->select('groups.id', 'groups.name')
            ->distinct()
            ->get();

        if ($groups->isEmpty()) return;

        // 3. Tính standings cho tất cả các group để tìm ứng viên
        $allGroupStandings = collect();
        foreach ($groups as $group) {
            $groupMatches = Matches::where('group_id', $group->id)
                ->where('round', 1)
                ->where('status', 'completed')
                ->with(['homeTeam.members', 'awayTeam.members', 'results'])
                ->get();
    
            if ($groupMatches->isEmpty()) continue;
    
            $standings = TournamentService::calculateGroupStandings($groupMatches);
            
            // Lấy danh sách rank đi tiếp
            $advancementRules = PoolAdvancementRule::where('group_id', $group->id)
                ->pluck('rank')
                ->toArray();
    
            foreach ($standings as $index => $standing) {
                $rank = $index + 1;
                
                // CHỈ LẤY ĐỘI KHÔNG ĐI TIẾP
                if (in_array($rank, $advancementRules)) {
                    continue;
                }
    
                // CHUẨN HÓA DỮ LIỆU
                $allGroupStandings->push([
                    'team_id' => $standing['team']['id'],
                    'group_name' => $group->name,
                    'rank_in_group' => $rank,
                    
                    // Điểm BXH
                    'points' => $standing['points'] ?? 0,
                    
                    // Tỷ lệ thắng
                    'win_rate' => isset($standing['wins'], $standing['played']) && $standing['played'] > 0
                        ? round(($standing['wins'] / $standing['played']) * 100, 2)
                        : 0,
                    
                    // Hiệu số set
                    'set_diff' => ($standing['sets_won'] ?? 0) - ($standing['sets_lost'] ?? 0),
                    
                    // Hiệu số điểm
                    'point_diff' => ($standing['points_for'] ?? $standing['points_won'] ?? 0) 
                                  - ($standing['points_against'] ?? $standing['points_lost'] ?? 0),
                ]);
            }
        }
    
        if ($allGroupStandings->isEmpty()) {
            return;
        }
    
        // 3. SẮP XẾP BEST LOSERS
        $bestLosers = $allGroupStandings->sort(function ($a, $b) use ($rankingRules) {
            foreach ($rankingRules as $ruleId) {
                $field = null;
    
                switch ($ruleId) { // ✅ Đã là INT rồi
                    case TournamentType::RANKING_WIN_DRAW_LOSE_POINTS: // 1
                        $field = 'points';
                        break;
                    case TournamentType::RANKING_WIN_RATE: // 2
                        $field = 'win_rate';
                        break;
                    case TournamentType::RANKING_SETS_WON: // 3
                        $field = 'set_diff';
                        break;
                    case TournamentType::RANKING_POINTS_WON: // 4
                        $field = 'point_diff';
                        break;
                        // Rule 5 (Head-to-head) bỏ qua khi so sánh giữa các bảng khác nhau
                        // Rule 6 (Random) xử lý sau cùng nếu cần
                }

                if ($field && isset($a[$field], $b[$field])) {
                    if ($a[$field] != $b[$field]) {
                        return $b[$field] <=> $a[$field];
                    }
                }
            }
            
            // ✅ CRITICAL FALLBACK: Luôn dùng point_diff nếu chưa được dùng
            if (!in_array(TournamentType::RANKING_POINTS_WON, $rankingRules)) {
                if ($a['point_diff'] != $b['point_diff']) {
                    return $b['point_diff'] <=> $a['point_diff'];
                }
            }
            
            // Sau đó mới dùng set_diff (nếu chưa dùng)
            if (!in_array(TournamentType::RANKING_SETS_WON, $rankingRules)) {
                if ($a['set_diff'] != $b['set_diff']) {
                    return $b['set_diff'] <=> $a['set_diff'];
                }
            }
            
            return $a['team_id'] <=> $b['team_id'];
        })->values();
    
        // 4. GÁN BEST LOSERS VÀO BYE MATCHES
        $numLegs = $tournamentType->num_legs ?? 1;
        $leg1ByeMatches = $byeMatches->where('leg', 1);
    
        $loserIndex = 0;
    
        foreach ($leg1ByeMatches as $leg1Match) {
            if ($loserIndex >= $bestLosers->count()) {
                break;
            }
    
            $bestLoser = $bestLosers[$loserIndex];
    
            // ✅ FIX: Xác định vị trí cần điền ở Leg 1
            $positionToFill = null;
            $existingTeamId = null;
    
            if ($leg1Match->home_team_id && !$leg1Match->away_team_id) {
                $positionToFill = 'away';
                $existingTeamId = $leg1Match->home_team_id;
            } elseif (!$leg1Match->home_team_id && $leg1Match->away_team_id) {
                $positionToFill = 'home';
                $existingTeamId = $leg1Match->away_team_id;
            } else {
                continue;
            }
    
            // CẬP NHẬT LEG 1
            $leg1Match->update([
                $positionToFill . '_team_id' => $bestLoser['team_id'],
                'is_bye' => false,
                'status' => Matches::STATUS_PENDING
            ]);
            $leg1Match->refresh();
    
            // ✅ TÌM VÀ CẬP NHẬT CÁC LEG KHÁC (theo round + teams)
            $allOtherLegs = Matches::where('tournament_type_id', $tournamentTypeId)
                ->where('round', $leg1Match->round)
                ->where('id', '!=', $leg1Match->id) // Loại chính nó
                ->where(function($query) use ($leg1Match) {
                    // Tìm matches có CẢ HAI teams giống với leg1Match (có thể đảo vị trí)
                    $query->where(function($q) use ($leg1Match) {
                        $q->where('home_team_id', $leg1Match->home_team_id)
                          ->where('away_team_id', $leg1Match->away_team_id);
                    })->orWhere(function($q) use ($leg1Match) {
                        $q->where('home_team_id', $leg1Match->away_team_id)
                          ->where('away_team_id', $leg1Match->home_team_id);
                    });
                })
                ->orderBy('leg')
                ->get();

            // ✅ CẬP NHẬT CÁC LEG KHÁC
            foreach ($allOtherLegs as $leg) {
                if ($leg->leg % 2 === 0) {
                    // Leg chẵn: Đảo
                    $leg->update([
                        'home_team_id' => $leg1Match->away_team_id,
                        'away_team_id' => $leg1Match->home_team_id,
                        'is_bye' => false,
                        'status' => Matches::STATUS_PENDING
                    ]);
                } else {
                    // Leg lẻ: Giữ nguyên
                    $leg->update([
                        'home_team_id' => $leg1Match->home_team_id,
                        'away_team_id' => $leg1Match->away_team_id,
                        'is_bye' => false,
                        'status' => Matches::STATUS_PENDING
                    ]);
                }
            }
    
            $loserIndex++;
        }
    }
    private function recalculateRankings($tournamentTypeId)
    {
        $tournamentType = TournamentType::find($tournamentTypeId);
        if (!$tournamentType) return;

        // Ép kiểu mảng ranking rules về Integer ngay từ đầu để tránh lỗi switch-case
        $config = $tournamentType->format_specific_config ?? [];
        if (is_array($config) && isset($config[0])) {
            $config = $config[0];
        }
        $rankingRules = collect($config['ranking'] ?? [1, 2])
            ->map(fn($id) => (int)$id)
            ->toArray();

        $tournament_id = $tournamentType->tournament_id;

        // 1️⃣ Lấy danh sách teams
        $teams = Team::where('tournament_id', $tournament_id)->select('id')->distinct()->get();
        if ($teams->isEmpty()) return;

        // 2️⃣ Khởi tạo mảng thống kê
        $stats = [];
        foreach ($teams as $team) {
            $stats[$team->id] = [
                'team_id'    => $team->id,
                'played'     => 0,
                'wins'       => 0,
                'losses'     => 0,
                'points'     => 0,
                'sets_won'   => 0,
                'sets_lost'  => 0,
                'points_won' => 0,
                'points_lost' => 0,
                'set_diff'   => 0,
                'point_diff' => 0,
                'win_rate'   => 0,
            ];
        }

        // 3️⃣ Lấy dữ liệu trận đấu đã hoàn thành
        $matches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('status', 'completed')
            ->with('results')
            ->get();

        foreach ($matches as $match) {
            $home = $match->home_team_id;
            $away = $match->away_team_id;
            $winner = $match->winner_id;
            $loser = ($winner == $home) ? $away : (($winner == $away) ? $home : null);

            foreach ([$home, $away] as $tid) {
                if ($tid && isset($stats[$tid])) {
                    $stats[$tid]['played']++;
                }
            }

            if ($winner && $loser && isset($stats[$winner]) && isset($stats[$loser])) {
                $stats[$winner]['wins']++;
                $stats[$winner]['points'] += 3;
                $stats[$loser]['losses']++;
            } elseif (!$winner && $home && $away && isset($stats[$home]) && isset($stats[$away])) {
                // Trận hòa: mỗi đội được 1 điểm
                $stats[$home]['points'] += 1;
                $stats[$away]['points'] += 1;
            }
            // Trận bye (is_bye=true): không cộng điểm, vẫn tính vào played ở trên

            foreach ($match->results as $r) {
                if (isset($stats[$r->team_id])) {
                    $stats[$r->team_id]['points_won'] += $r->score;
                    if ($r->won_match) {
                        $stats[$r->team_id]['sets_won']++;
                    } else {
                        $stats[$r->team_id]['sets_lost']++;
                    }
                }
            }

            // Tính points_lost để tính point_diff
            if ($home && $away && isset($stats[$home]) && isset($stats[$away])) {
                $homeScore = $match->results->where('team_id', $home)->sum('score');
                $awayScore = $match->results->where('team_id', $away)->sum('score');
                $stats[$home]['points_lost'] += $awayScore;
                $stats[$away]['points_lost'] += $homeScore;
            }
        }

        // 4️⃣ Tính toán các chỉ số phụ
        foreach ($stats as &$s) {
            $s['set_diff'] = $s['sets_won'] - $s['sets_lost'];
            $s['point_diff'] = $s['points_won'] - $s['points_lost'];
            $s['win_rate'] = $s['played'] > 0 ? round($s['wins'] / $s['played'] * 100, 2) : 0;
        }
        unset($s);

        // 5️⃣ Sắp xếp linh hoạt theo Ranking Rules
        $sorted = collect($stats)->sort(function ($a, $b) use ($rankingRules, $matches) {
            // Đội đã đánh luôn đứng trên đội chưa đánh
            if ($a['played'] == 0 && $b['played'] > 0) return 1;
            if ($b['played'] == 0 && $a['played'] > 0) return -1;

            foreach ($rankingRules as $ruleId) {
                switch ($ruleId) {
                    case TournamentType::RANKING_WIN_DRAW_LOSE_POINTS: // Rule 1
                        if ($a['points'] !== $b['points']) return $b['points'] <=> $a['points'];
                        break;
                    case TournamentType::RANKING_WIN_RATE: // Rule 2
                        if ($a['win_rate'] !== $b['win_rate']) return $b['win_rate'] <=> $a['win_rate'];
                        break;
                    case TournamentType::RANKING_SETS_WON: // Rule 3
                        if ($a['set_diff'] !== $b['set_diff']) return $b['set_diff'] <=> $a['set_diff'];
                        break;
                    case TournamentType::RANKING_POINTS_WON: // Rule 4
                        if ($a['point_diff'] !== $b['point_diff']) return $b['point_diff'] <=> $a['point_diff'];
                        break;
                    case TournamentType::RANKING_HEAD_TO_HEAD: // Rule 5
                        $h2h = $this->getHeadToHeadResult($a['team_id'], $b['team_id'], $matches);
                        if ($h2h !== 0) return $h2h;
                        break;
                    case TournamentType::RANKING_RANDOM_DRAW: // Rule 6
                        return $a['team_id'] <=> $b['team_id'];
                }
            }

            // Cầu chì cuối cùng: Nếu tất cả các luật cài đặt đều bằng nhau,
            // mặc định lấy Hiệu số điểm (Point Diff) để phân định, sau đó mới đến ID.
            if ($a['point_diff'] !== $b['point_diff']) return $b['point_diff'] <=> $a['point_diff'];
            return $a['team_id'] <=> $b['team_id'];
        })->values();

        // 6️⃣ Clear cũ & cập nhật mới
        TeamRanking::where('tournament_type_id', $tournamentTypeId)->delete();

        $rank = 1;
        foreach ($sorted as $s) {
            TeamRanking::create([
                'tournament_type_id' => $tournamentTypeId,
                'team_id' => $s['team_id'],
                'rank' => $rank++,
            ]);
        }
    }

    /**
     * So sánh đối đầu giữa 2 đội
     * Return: -1 nếu team A thắng, 1 nếu team B thắng, 0 nếu hòa hoặc chưa gặp
     */
    private function getHeadToHeadResult($teamA, $teamB, $matches)
    {
        $h2hMatches = $matches->filter(function ($match) use ($teamA, $teamB) {
            return ($match->home_team_id == $teamA && $match->away_team_id == $teamB) ||
                ($match->home_team_id == $teamB && $match->away_team_id == $teamA);
        });

        if ($h2hMatches->isEmpty())
            return 0;

        $teamAWins = 0;
        $teamBWins = 0;

        foreach ($h2hMatches as $match) {
            if ($match->winner_id == $teamA)
                $teamAWins++;
            elseif ($match->winner_id == $teamB)
                $teamBWins++;
        }

        if ($teamAWins > $teamBWins)
            return -1;
        elseif ($teamBWins > $teamAWins)
            return 1;

        return 0;
    }

    public function swapTeams(Request $request, $matchId)
    {
        $match = Matches::find($matchId);
        if (!$match) {
            return ResponseHelper::error('Match not found', 404);
        }

        $validated = $request->validate([
            'home_team_id' => 'nullable|exists:teams,id',
            'away_team_id' => 'nullable|exists:teams,id',
        ]);
        if (!in_array($match->status, haystack: ['pending', 'not_started'])) {
            return ResponseHelper::error('Trận đã bắt đầu hoặc hoàn tất, không thể hoán đổi đội.', 403);
        }
        $tournamentType = TournamentType::find($match->tournament_type_id);
        if (in_array($tournamentType->format, [TournamentType::FORMAT_ROUND_ROBIN]) && $match->round == 1) {
            return ResponseHelper::error('Cài đặt thể thức không cho phép hoán đổi các đội đấu vòng tròn (round robin).', 403);
        }
        if ($tournamentType->format === TournamentType::FORMAT_MIXED && $match->group && $match->round == 1) {
            return $this->handleMixedSwap($request, $match, $tournamentType);
        }

        // chỉ cho phép swap ở round 1 và khi chưa diễn ra
        if ($match->round != 1) {
            return ResponseHelper::error('Chỉ được hoán đổi đội ở Round 1.', 403);
        }

        $targetTeamId = $validated['away_team_id'] ?? $validated['home_team_id'];
        if (!$targetTeamId) {
            return ResponseHelper::error('Thiếu team cần swap.', 400);
        }

        // Tìm trận chứa target team ở round 1
        $otherMatch = Matches::where('tournament_type_id', $match->tournament_type_id)
            ->where('round', 1)
            ->where('id', '<>', $match->id)
            ->where(function ($q) use ($targetTeamId) {
                $q->where('home_team_id', $targetTeamId)
                    ->orWhere('away_team_id', $targetTeamId);
            })
            ->first();

        if (!$otherMatch) {
            return ResponseHelper::error('Có lỗi xảy ra khi đổi đội.', 404);
        }

        DB::transaction(function () use ($match, $otherMatch, $validated, $targetTeamId) {
            $swapIsHome = isset($validated['home_team_id']);

            // Xác định đội nào đang ở trận hiện tại cần bị thay thế
            $oldTeamToMove = $swapIsHome ? $match->home_team_id : $match->away_team_id;

            // Xác định vị trí của target team ở trận kia
            $targetIsHomeInOther = ($otherMatch->home_team_id == $targetTeamId);

            // Kiểm tra xem trận nào là bye
            $matchIsBye = ($match->home_team_id === null || $match->away_team_id === null);
            $otherMatchIsBye = ($otherMatch->home_team_id === null || $otherMatch->away_team_id === null);

            // Xác định đội nào đang có bye advantage
            $teamWithByeAdvantage = null;
            if ($matchIsBye) {
                $teamWithByeAdvantage = $match->home_team_id ?? $match->away_team_id;
            } elseif ($otherMatchIsBye) {
                $teamWithByeAdvantage = $otherMatch->home_team_id ?? $otherMatch->away_team_id;
            }

            // Bước 1: Thay đội ở trận hiện tại
            if ($swapIsHome) {
                $match->update(['home_team_id' => $targetTeamId]);
            } else {
                $match->update(['away_team_id' => $targetTeamId]);
            }

            // Bước 2: Đưa đội cũ vào vị trí của target team ở trận kia
            if ($targetIsHomeInOther) {
                $otherMatch->update(['home_team_id' => $oldTeamToMove]);
            } else {
                $otherMatch->update(['away_team_id' => $oldTeamToMove]);
            }

            // Bước 3: Cập nhật is_bye cho cả 2 trận
            $match->update([
                'is_bye' => ($match->home_team_id === null || $match->away_team_id === null),
            ]);
            $otherMatch->update([
                'is_bye' => ($otherMatch->home_team_id === null || $otherMatch->away_team_id === null),
            ]);

            // Bước 4: Nếu có đội có bye advantage, cập nhật tất cả các round sau
            if ($teamWithByeAdvantage) {
                // Tìm đội nào sẽ nhận bye advantage mới
                $newTeamWithBye = null;
                if ($match->is_bye) {
                    $newTeamWithBye = $match->home_team_id ?? $match->away_team_id;
                } elseif ($otherMatch->is_bye) {
                    $newTeamWithBye = $otherMatch->home_team_id ?? $otherMatch->away_team_id;
                }

                // Thay thế đội cũ có bye bằng đội mới trong tất cả các round sau
                if ($newTeamWithBye && $teamWithByeAdvantage != $newTeamWithBye) {
                    Matches::where('tournament_type_id', $match->tournament_type_id)
                        ->where('round', '>', 1)
                        ->where(function ($q) use ($teamWithByeAdvantage) {
                            $q->where('home_team_id', $teamWithByeAdvantage)
                                ->orWhere('away_team_id', $teamWithByeAdvantage);
                        })
                        ->get()
                        ->each(function ($m) use ($teamWithByeAdvantage, $newTeamWithBye) {
                            if ($m->home_team_id == $teamWithByeAdvantage) {
                                $m->update(['home_team_id' => $newTeamWithBye]);
                            }
                            if ($m->away_team_id == $teamWithByeAdvantage) {
                                $m->update(['away_team_id' => $newTeamWithBye]);
                            }
                        });
                }
            }

            // Reset kết quả & trạng thái cho round 1
            foreach ([$match, $otherMatch] as $m) {
                $m->update([
                    'winner_id' => null,
                    'status' => 'pending',
                ]);
                $m->results()->delete();
            }

            // Reset tất cả các trận từ round 2 trở đi
            Matches::where('tournament_type_id', $match->tournament_type_id)
                ->where('round', '>', 1)
                ->update([
                    'winner_id' => null,
                    'status' => 'pending',
                ]);
            Matches::where('tournament_type_id', $match->tournament_type_id)
                ->where('round', '>', 1)
                ->get()
                ->each(function ($m) {
                    $m->results()->delete();
                });
        });

        return ResponseHelper::success([
            'message' => 'Hoán đổi đội thành công',
            'match_1' => $match->fresh(),
            'match_2' => $otherMatch->fresh(),
        ]);
    }

    private function handleMixedSwap(Request $request, Matches $match, TournamentType $tournamentType)
    {
        $validated = $request->validate([
            'from_team_id' => 'required|exists:teams,id',
            'to_team_id'   => 'required|exists:teams,id',
        ]);

        $fromTeamId = $validated['from_team_id'];
        $toTeamId   = $validated['to_team_id'];

        // 🚫 Cùng bảng thì cấm
        $sameGroup = Matches::where('tournament_type_id', $tournamentType->id)
            ->where('round', 1)
            ->where(function ($q) use ($fromTeamId, $toTeamId) {
                $q->where(function ($q) use ($fromTeamId) {
                    $q->where('home_team_id', $fromTeamId)
                        ->orWhere('away_team_id', $fromTeamId);
                });
            })
            ->where(function ($q) use ($toTeamId) {
                $q->where('home_team_id', $toTeamId)
                    ->orWhere('away_team_id', $toTeamId);
            })
            ->exists();

        if ($sameGroup) {
            return ResponseHelper::error(
                'Không cho phép hoán đổi đội trong cùng bảng của thể thức mixed.',
                403
            );
        }

        // ✅ Swap GLOBAL toàn bộ round 1
        DB::transaction(function () use ($tournamentType, $fromTeamId, $toTeamId) {

            $matches = Matches::where('tournament_type_id', $tournamentType->id)
                ->where('round', 1)
                ->where(function ($q) use ($fromTeamId, $toTeamId) {
                    $q->whereIn('home_team_id', [$fromTeamId, $toTeamId])
                        ->orWhereIn('away_team_id', [$fromTeamId, $toTeamId]);
                })
                ->lockForUpdate()
                ->get();

            foreach ($matches as $m) {

                if ($m->home_team_id == $fromTeamId) {
                    $m->home_team_id = $toTeamId;
                } elseif ($m->home_team_id == $toTeamId) {
                    $m->home_team_id = $fromTeamId;
                }

                if ($m->away_team_id == $fromTeamId) {
                    $m->away_team_id = $toTeamId;
                } elseif ($m->away_team_id == $toTeamId) {
                    $m->away_team_id = $fromTeamId;
                }

                $m->update([
                    'is_bye' => ($m->home_team_id === null || $m->away_team_id === null),
                    'winner_id' => null,
                    'status' => 'pending',
                ]);

                $m->results()->delete();
            }

            // reset các round sau
            Matches::where('tournament_type_id', $tournamentType->id)
                ->where('round', '>', 1)
                ->update([
                    'winner_id' => null,
                    'status' => 'pending',
                ]);

            Matches::where('tournament_type_id', $tournamentType->id)
                ->where('round', '>', 1)
                ->get()
                ->each(fn($m) => $m->results()->delete());
        });

        return ResponseHelper::success(null, 'Đã hoán đổi toàn bộ các trận đấu giữa hai đội ở hai bảng khác nhau.', 200);
    }
    public function generateQr($matchId)
    {
        $match = Matches::findOrFail($matchId);
        $url = url("/api/matches/confirm-result/{$match->id}");

        return ResponseHelper::success(['qr_url' => $url], 'Thành công');
    }

    public function confirmResult($matchId)
    {
        $match = Matches::with([
            'results',
            'tournamentType.tournament',
            'homeTeam.members',
            'awayTeam.members',
        ])->findOrFail($matchId);

        $tournament = $match->tournamentType->tournament->load('staff');
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        $isReferee = $tournament->staff()
            ->wherePivot('user_id', Auth::id())
            ->wherePivot('role', TournamentStaff::ROLE_REFEREE)
            ->exists();
        $teamIds = [$match->home_team_id, $match->away_team_id];
        $userTeam = Team::whereIn('id', $teamIds)
            ->whereHas('members', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->first();

        // Kiểm tra quyền
        if (!$userTeam && !$isOrganizer && !$isReferee) {
            return ResponseHelper::error('Bạn không có quyền xác nhận kết quả trận đấu này', 403);
        }

        if ($match->status === Matches::STATUS_COMPLETED) {
            return ResponseHelper::error('Kết quả trận đấu đã được xác nhận trước đó', 400);
        }

        $rules = $match->tournamentType->match_rules ?? null;
        if (!$rules) {
            return ResponseHelper::error('Thể thức này chưa có luật thi đấu (match_rules).', 400);
        }
    
        $setsPerMatch = $rules[0]['sets_per_match'] ?? 3;
        $numLegs = $match->tournamentType->num_legs ?? 1;
    
        // ===================================
        // VALIDATE TOÀN BỘ KẾT QUẢ CÁC SET
        // ===================================
        $validationError = $this->validateAllMatchSets($match, $rules[0]);
        if ($validationError) {
            return ResponseHelper::error($validationError, 400);
        }
    
        // ===================================
        // XÁC ĐỊNH WINNER CỦA LEG (chỉ tính set thắng thực tế)
        // ===================================
        // reload để lấy won_match vừa được cập nhật bởi validateAllMatchSets
        $match->load('results');

        $sets = $match->results->groupBy('set_number');
        $wins = [];
        $pointTotals = []; // điểm tổng mỗi đội ghi được qua tất cả set (dùng phá hòa)

        foreach ($sets as $setNumber => $setResults) {
            // mỗi set phải có đủ kết quả của 2 đội
            if ($setResults->count() < 2) {
                continue;
            }

            // cộng điểm mỗi đội vào tổng (tất cả set, kể cả hòa điểm)
            foreach ($setResults as $r) {
                $tid = (int) $r->team_id;
                $pointTotals[$tid] = ($pointTotals[$tid] ?? 0) + (int) $r->score;
            }

            $sorted = $setResults->sortByDesc('score')->values();

            // hòa điểm → không tính set thắng, điểm vẫn giữ trong pointTotals
            if ($sorted[0]->score === $sorted[1]->score) {
                continue;
            }

            $winnerTeamId = $sorted[0]->team_id;
            $wins[$winnerTeamId] = ($wins[$winnerTeamId] ?? 0) + 1;
        }

        /**
         * Không có set hợp lệ
         */
        if (empty($wins)) {
            return ResponseHelper::error(
                "Chưa có set hợp lệ để xác định đội thắng",
                400
            );
        }

        /**
         * Tìm đội thắng nhiều set nhất; hòa → phá bằng tổng điểm ghi được
         */
        $maxWins = max($wins);
        $winnerTeamIds = array_keys(
            array_filter($wins, fn($count) => $count === $maxWins)
        );

        if (count($winnerTeamIds) > 1) {
            // so sánh tổng điểm ghi được
            $totals = [];
            foreach ($winnerTeamIds as $tid) {
                $totals[$tid] = $pointTotals[(int) $tid] ?? 0;
            }
            $maxPts = max($totals);
            $byPts = array_keys(array_filter($totals, fn($p) => $p === $maxPts));
            if (count($byPts) === 1) {
                $winnerTeamIds = $byPts; // phá hòa bằng điểm thành công
            } else {
                return ResponseHelper::error(
                    "Hai đội hoà số set thắng và bằng điểm, chưa xác định được đội thắng",
                    400
                );
            }
        }

        $legWinnerId = $winnerTeamIds[0];
    
        // ===================================
        // XỬ LÝ CONFIRM
        // ===================================
        if ($isOrganizer) {
            $match->home_team_confirm = true;
            $match->away_team_confirm = true;
        } else {
            if ($userTeam && $userTeam->id == $match->home_team_id) {
                $match->home_team_confirm = true;
            } elseif ($userTeam && $userTeam->id == $match->away_team_id) {
                $match->away_team_confirm = true;
            }
        }

        if ($match->home_team_confirm && $match->away_team_confirm) {
            $this->processMatchCompletionBig($match, $tournament, $setsPerMatch);
        }

        $match->save();

        $confirmedByAdmin = $isOrganizer;
        $confirmedByUser  = !$isOrganizer && $userTeam;

        if ($confirmedByUser) {

            $opponentTeam = $userTeam->id == $match->home_team_id
                ? $match->awayTeam
                : $match->homeTeam;
        
            $opponentUserIds = $opponentTeam->members->pluck('id')->toArray();
        
            $this->pushToUsers(
                $opponentUserIds,
                'Xác nhận kết quả trận đấu',
                Auth::user()->full_name . ' đã xác nhận kết quả trận đấu. Vui lòng xác nhận kết quả',
                [
                    'type' => 'MATCH_CONFIRM',
                    'match_id' => $match->id,
                    'by' => 'player',
                ]
            );
        }

        if ($confirmedByAdmin) {

            $homeUserIds = $match->homeTeam->members->pluck('id')->toArray();
            $awayUserIds = $match->awayTeam->members->pluck('id')->toArray();
        
            $allUserIds = array_unique(array_merge($homeUserIds, $awayUserIds));
        
            $this->pushToUsers(
                $allUserIds,
                'Kết quả trận đấu đã được xác nhận',
                'Ban tổ chức đã xác nhận kết quả trận đấu',
                [
                    'type' => 'MATCH_CONFIRM',
                    'match_id' => $match->id,
                    'by' => 'admin',
                ]
            );
        }        

        return ResponseHelper::success(
            new MatchesResource($match->fresh(['results', 'tournamentType.tournament', 'homeTeam.members', 'awayTeam.members'])),
            'Xác nhận kết quả thành công'
        );
    }

    // ============================================
    // 3. VALIDATE ALL MATCH SETS - HÀM PHỤ
    // ============================================
    // private function validateAllMatchSets($match, $rules)
    // {
    //     $pointsToWinSet = $rules['points_to_win_set'] ?? 11;
    //     $winningRule = $rules['winning_rule'] ?? 2;
    //     $maxPoints = $rules['max_points'] ?? $pointsToWinSet;

    //     $homeTeamId = $match->home_team_id;
    //     $awayTeamId = $match->away_team_id;

    //     $sets = $match->results->groupBy('set_number');

    //     if ($sets->isEmpty()) {
    //         return 'Trận đấu chưa có kết quả nào';
    //     }

    //     foreach ($sets as $setNumber => $setResults) {
    //         if ($setResults->count() !== 2) {
    //             return "Set $setNumber: Thiếu điểm số của một trong hai đội";
    //         }

    //         $teamA = $setResults->firstWhere('team_id', $homeTeamId);
    //         $teamB = $setResults->firstWhere('team_id', $awayTeamId);

    //         if (!$teamA || !$teamB) {
    //             return "Set $setNumber: Dữ liệu không hợp lệ";
    //         }

    //         $A = (int) $teamA->score;
    //         $B = (int) $teamB->score;

    //         // Validate logic thắng thua
    //         $validation = $this->validateMatchSetScore(
    //             $A,
    //             $B,
    //             $setNumber,
    //             $pointsToWinSet,
    //             $winningRule,
    //             $maxPoints,
    //             $homeTeamId,
    //             $awayTeamId
    //         );

    //         if ($validation['error']) {
    //             return $validation['message'];
    //         }

    //         // Cập nhật won_match với ID thực tế
    //         $winnerTeamId = $validation['winner'];
    //         $teamA->update(['won_match' => ($teamA->team_id == $winnerTeamId)]);
    //         $teamB->update(['won_match' => ($teamB->team_id == $winnerTeamId)]);
    //     }

    //     return null; // Không có lỗi
    // }

    // // ============================================
    // // 4. VALIDATE MATCH SET SCORE - LOGIC CHI TIẾT
    // // ============================================
    // private function validateMatchSetScore($A, $B, $setNumber, $pointsToWinSet, $winningRule, $maxPoints, $homeTeamId, $awayTeamId)
    // {
    //     if ($A < 0 || $B < 0) {
    //         return ['error' => true, 'message' => "Set $setNumber: Điểm số không hợp lệ"];
    //     }

    //     $scoreDiff = abs($A - $B);
    //     $isPointsToWinReached = ($A >= $pointsToWinSet || $B >= $pointsToWinSet);
    //     $isMaxPointsReached = ($A == $maxPoints || $B == $maxPoints);
    //     $winnerTeamId = null;
    //     $isSetCompleted = false;

    //     // Logic xác định thắng set
    //     if ($pointsToWinSet == $maxPoints) {
    //         if ($isMaxPointsReached) {
    //             $isSetCompleted = true;
    //             $winnerTeamId = $A > $B ? $homeTeamId : $awayTeamId;
    //         }
    //     } else {
    //         if ($isPointsToWinReached && $scoreDiff >= $winningRule) {
    //             $isSetCompleted = true;
    //             $winnerTeamId = $A > $B ? $homeTeamId : $awayTeamId;
    //         } elseif ($isMaxPointsReached) {
    //             if ($A == $B) {
    //                 return [
    //                     'error' => true,
    //                     'message' => "Set $setNumber: Điểm số hòa tại điểm tối đa $maxPoints. Set phải kết thúc với cách biệt."
    //                 ];
    //             }
    //             $isSetCompleted = true;
    //             $winnerTeamId = $A > $B ? $homeTeamId : $awayTeamId;
    //         }
    //     }

    //     if (!$isSetCompleted) {
    //         return [
    //             'error' => true,
    //             'message' => "Set $setNumber: Điểm số $A - $B chưa thỏa mãn luật thắng. Chỉ có thể lưu kết quả khi set đã hoàn thành."
    //         ];
    //     }

    //     // Anti-cheat điểm số
    //     $winningScore = max($A, $B);
    //     $losingScore = min($A, $B);

    //     if ($pointsToWinSet == $maxPoints) {
    //         if ($winningScore != $maxPoints) {
    //             return [
    //                 'error' => true,
    //                 'message' => "Set $setNumber: Điểm số $A - $B không hợp lệ với luật (thắng khi chạm $maxPoints)."
    //             ];
    //         }
    //         if ($losingScore == $maxPoints) {
    //             return [
    //                 'error' => true,
    //                 'message' => "Set $setNumber: Điểm số $A - $B không hợp lệ với luật (không thể hòa tại $maxPoints)."
    //             ];
    //         }
    //     } else {
    //         // Kết thúc trước maxPoints
    //         if ($winningScore < $maxPoints) {
    //             if (!($winningScore >= $pointsToWinSet && ($winningScore - $losingScore) >= $winningRule)) {
    //                 return [
    //                     'error' => true,
    //                     'message' => "Set $setNumber: Điểm số $A - $B không hợp lệ với luật (trước $maxPoints)."
    //                 ];
    //             }

    //             // Kiểm tra không kết thúc sớm hơn
    //             for ($i = $pointsToWinSet; $i < $winningScore; $i++) {
    //                 $diffAtPoint = $i - $losingScore;
    //                 if ($diffAtPoint >= $winningRule) {
    //                     return [
    //                         'error' => true,
    //                         'message' => "Set $setNumber: Điểm số $A - $B không hợp lệ. Set kết thúc sớm hơn tại $i - $losingScore."
    //                     ];
    //                 }
    //             }
    //         }
    //         // Kết thúc tại maxPoints
    //         else {
    //             if (!($winningScore == $maxPoints && $winningScore > $losingScore)) {
    //                 return [
    //                     'error' => true,
    //                     'message' => "Set $setNumber: Điểm số $A - $B không hợp lệ với luật (tại $maxPoints)."
    //                 ];
    //             }

    //             for ($i = $pointsToWinSet; $i < $maxPoints; $i++) {
    //                 $diffAtPoint = $i - $losingScore;
    //                 if ($diffAtPoint >= $winningRule) {
    //                     return [
    //                         'error' => true,
    //                         'message' => "Set $setNumber: Điểm số $A - $B không hợp lệ. Set kết thúc sớm hơn tại $i - $losingScore."
    //                     ];
    //                 }
    //             }
    //         }
    //     }

    //     if (!$winnerTeamId) {
    //         return ['error' => true, 'message' => "Set $setNumber: Lỗi xác định người thắng."];
    //     }

    //     return ['error' => false, 'winner' => $winnerTeamId];
    // }
    private function validateAllMatchSets($match, $rules)
    {
        $pointsToWinSet = $rules['points_to_win_set'] ?? 11;
        $winningRule = $rules['winning_rule'] ?? 2;
        $homeTeamId = $match->home_team_id;
        $awayTeamId = $match->away_team_id;

        $sets = $match->results->groupBy('set_number');

        if ($sets->isEmpty()) {
            return 'Trận đấu chưa có kết quả nào';
        }

        foreach ($sets as $setNumber => $setResults) {
            if ($setResults->count() !== 2) {
                return "Set $setNumber: Thiếu điểm số của một trong hai đội";
            }

            $teamA = $setResults->firstWhere('team_id', $homeTeamId);
            $teamB = $setResults->firstWhere('team_id', $awayTeamId);

            if (!$teamA || !$teamB) {
                return "Set $setNumber: Dữ liệu không hợp lệ";
            }

            $A = (int) $teamA->score;
            $B = (int) $teamB->score;

            // Validate logic thắng thua
            $validation = $this->validateMatchSetScore(
                $A,
                $B,
                $setNumber,
                $pointsToWinSet,
                $homeTeamId,
                $awayTeamId,
                $winningRule
            );

            if ($validation['error']) {
                return $validation['message'];
            }

            // Cập nhật won_match với ID thực tế
            $winnerTeamId = $validation['winner'];
            $teamA->update(['won_match' => ($teamA->team_id == $winnerTeamId)]);
            $teamB->update(['won_match' => ($teamB->team_id == $winnerTeamId)]);
        }

        return null; // Không có lỗi
    }

    // ============================================
    // 4. VALIDATE MATCH SET SCORE - LOGIC ĐƠN GIẢN
    // ============================================
    private function validateMatchSetScore($A, $B, $setNumber, $pointsToWinSet, $homeTeamId, $awayTeamId, $winningRule)
    {
        if ($A < 0 || $B < 0) {
            return ['error' => true, 'message' => "Set $setNumber: Điểm số không hợp lệ"];
        }

        // Check 1: Không được hòa
        if ($A == $B) {
            return ['error' => true, 'message' => "Set $setNumber: Không được có tỉ số hòa ($A - $B)"];
        }

        // Check 2: Ít nhất 1 đội phải đạt pointsToWinSet (11 điểm)
        $winningScore = max($A, $B);
        if ($winningScore < $pointsToWinSet) {
            return [
                'error' => true, 
                'message' => "Set $setNumber: Ít nhất 1 đội phải đạt $pointsToWinSet điểm (hiện tại: $A - $B)"
            ];
        }

        // Check 3: Tính cách biệt điểm
        $scoreDiff = abs($A - $B);
        if ($scoreDiff < $winningRule) {
            return [
                'error' => true,
                'message' => "Set $setNumber: Điểm cách biệt phải lớn hơn hoặc bằng $winningRule"
            ];
        }

        // Xác định winner
        $winnerTeamId = $A > $B ? $homeTeamId : $awayTeamId;

        return ['error' => false, 'winner' => $winnerTeamId];
    }

    // ============================================
    // 5. PROCESS MATCH COMPLETION - LOGIC ELO
    // ============================================
    private function processMatchCompletionBig($match, $tournament, $setsPerMatch)
    {
        $match->status = Matches::STATUS_COMPLETED;

        foreach ($match->results as $result) {
            $result->confirmed = true;
            $result->save();
        }

        // ===== ANCHOR MATCH LOGIC =====
        $allUsersInMatch = collect()
            ->merge($match->homeTeam->members)
            ->merge($match->awayTeam->members)
            ->unique('id')
            ->values();

        $hasAnchorInMatch = $allUsersInMatch->contains(function ($user) {
            return $user->is_anchor || ($user->total_matches_has_anchor ?? 0) >= 10;
        });

        if ($hasAnchorInMatch) {
            foreach ($allUsersInMatch as $user) {
                $isAnchor = $user->is_anchor || ($user->total_matches_has_anchor ?? 0) >= 10;
                if (!$isAnchor) {
                    $user->total_matches_has_anchor = ($user->total_matches_has_anchor ?? 0) + 1;
                    $user->save();
                }
            }
        }

        // Tính S (Actual Score)
        $scores = $match->results
            ->groupBy('team_id')
            ->map(fn($results) => $results->sum('score'));

        $homeScore = $scores->get($match->home_team_id, 0);
        $awayScore = $scores->get($match->away_team_id, 0);
        $totalScore = $homeScore + $awayScore;

        // Xác định đội thắng (dựa trên tổng điểm)
        if ($homeScore > $awayScore) {
            $winnerTeamId = $match->home_team_id;
        } elseif ($awayScore > $homeScore) {
            $winnerTeamId = $match->away_team_id;
        } else {
            $winnerTeamId = null; // hòa (nếu có)
        }

        // S_match
        $S_match_home = $winnerTeamId === $match->home_team_id ? 1.0 : 0.0;
        $S_match_away = $winnerTeamId === $match->away_team_id ? 1.0 : 0.0;

        // S_points
        $S_points_home = $totalScore > 0 ? $homeScore / $totalScore : 0;
        $S_points_away = $totalScore > 0 ? $awayScore / $totalScore : 0;

        // S_final
        $S_home = 0.5 * $S_match_home + 0.5 * $S_points_home;
        $S_away = 0.5 * $S_match_away + 0.5 * $S_points_away;

        // =====================================================
        // D. TÍNH R (AVERAGE RATING)
        // =====================================================
        $sportId = $tournament->sport_id;

        $getAverageRating = function ($team, $sportId) {
            $members = $team->members;
            if ($members->isEmpty()) return 0;

            $total = 0;
            foreach ($members as $member) {
                $userSport = DB::table('user_sport')
                    ->where('user_id', $member->id)
                    ->where('sport_id', $sportId)
                    ->first();

                if ($userSport) {
                    $score = DB::table('user_sport_scores')
                        ->where('user_sport_id', $userSport->id)
                        ->where('score_type', 'vndupr_score')
                        ->value('score_value');

                    $total += (float) ($score ?? 0);
                }
            }

            return $total / $members->count();
        };

        $homeRating = $getAverageRating($match->homeTeam, $sportId);
        $awayRating = $getAverageRating($match->awayTeam, $sportId);

        $E_home = 1 / (1 + pow(10, ($awayRating - $homeRating)));
        $E_away = 1 / (1 + pow(10, ($homeRating - $awayRating)));

        $teams = [
            [
                'team' => $match->homeTeam,
                'S' => $S_home,
                'E' => $E_home,
            ],
            [
                'team' => $match->awayTeam,
                'S' => $S_away,
                'E' => $E_away,
            ],
        ];

        $W = 0.6;

        foreach ($teams as $data) {
            $team = $data['team'];
            $S = $data['S'];
            $E = $data['E'];

            foreach ($team->members as $user) {
                $user->total_matches = ($user->total_matches ?? 0) + 1;
                $user->save();

                // Lấy R_old
                $userSport = DB::table('user_sport')
                    ->where('user_id', $user->id)
                    ->where('sport_id', $sportId)
                    ->first();

                $R_old = DB::table('user_sport_scores')
                    ->where('user_sport_id', $userSport?->id)
                    ->where('score_type', 'vndupr_score')
                    ->value('score_value') ?? 0;

                $history = VnduprHistory::where('user_id', $user->id)
                    ->orderByDesc('id')
                    ->take(15)
                    ->get()
                    ->sortBy('id')
                    ->values();

                $K = 0.3;

                if ($user->is_anchor) {
                    $K = 0.1;
                } else {
                    if ($user->total_matches <= 10) {
                        $K = 1;
                    } elseif ($user->total_matches <= 50) {
                        $K = 0.6;
                    }
                }

                if ($history->count() >= 2) {
                    $first = $history->first()->score_before;
                    $last = $history->last()->score_after;
                    if (($first - $last) > 0.5) {
                        $K = 1;
                    }
                }

                // $R_new = $hasAnchorInMatch
                //     ? $R_old + ($W * $K * ($S - $E))
                //     : $R_old;
                $R_new = $R_old + ($W * $K * ($S - $E));

                VnduprHistory::create([
                    'user_id' => $user->id,
                    'match_id' => $match->id,
                    'mini_match_id' => null,
                    'score_before' => $R_old,
                    'score_after' => $R_new,
                ]);

                if ($userSport) {
                    DB::table('user_sport_scores')->updateOrInsert(
                        [
                            'user_sport_id' => $userSport->id,
                            'score_type' => 'vndupr_score',
                        ],
                        [
                            'score_value' => $R_new,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }

        $this->checkAndAdvanceFromMultiLeg($match, $setsPerMatch);
    }

    private function checkAndAdvanceFromMultiLeg($match, $setsPerMatch)
    {
        $tournamentType = $match->tournamentType;
        $numLegs = $tournamentType->num_legs ?? 1;
    
        if ($numLegs == 1) {
            $this->calculateMatchWinner($match, $setsPerMatch);
            return;
        }
    
        $this->calculateMatchWinner($match, $setsPerMatch);
    
        $allLegs = Matches::where('tournament_type_id', $match->tournament_type_id)
            ->where('round', $match->round)
            ->where(function ($q) use ($match) {
                $q->where(function ($q2) use ($match) {
                    $q2->where('home_team_id', $match->home_team_id)
                        ->where('away_team_id', $match->away_team_id);
                })->orWhere(function ($q2) use ($match) {
                    $q2->where('home_team_id', $match->away_team_id)
                        ->where('away_team_id', $match->home_team_id);
                });
            })->with('results')->get();
    
        $allCompleted = $allLegs->every(fn($m) => $m->status === Matches::STATUS_COMPLETED);
    
        if (!$allCompleted) {
            return;
        }
    
        $leg1 = $allLegs->firstWhere('leg', 1);
        $homeTeamId = $leg1 ? $leg1->home_team_id : $match->home_team_id;
        $awayTeamId = $leg1 ? $leg1->away_team_id : $match->away_team_id;
    
        // ===== BƯỚC 1: Đếm số LEGS thắng =====
        $legWins = [
            $homeTeamId => 0,
            $awayTeamId => 0,
        ];
    
        foreach ($allLegs as $leg) {
            if ($leg->winner_id == $homeTeamId) {
                $legWins[$homeTeamId]++;
            } elseif ($leg->winner_id == $awayTeamId) {
                $legWins[$awayTeamId]++;
            }
        }
    
        // ===== BƯỚC 2: Tính số SET thắng và điểm số =====
        $teamStats = [
            $homeTeamId => [
                'set_wins' => 0,
                'points_for' => 0,
                'points_against' => 0
            ],
            $awayTeamId => [
                'set_wins' => 0,
                'points_for' => 0,
                'points_against' => 0
            ],
        ];
    
        foreach ($allLegs as $leg) {
            $legHomeId = $leg->home_team_id;
            $legAwayId = $leg->away_team_id;
    
            foreach ($leg->results->groupBy('set_number') as $setNumber => $setResults) {
                if ($setResults->count() < 2) continue;
    
                $homeResult = $setResults->firstWhere('team_id', $legHomeId);
                $awayResult = $setResults->firstWhere('team_id', $legAwayId);
    
                if (!$homeResult || !$awayResult) continue;
    
                $homeScore = (int) $homeResult->score;
                $awayScore = (int) $awayResult->score;
    
                // Cập nhật điểm ghi được và điểm bị ghi
                if ($legHomeId == $homeTeamId) {
                    $teamStats[$homeTeamId]['points_for'] += $homeScore;
                    $teamStats[$homeTeamId]['points_against'] += $awayScore;
                    $teamStats[$awayTeamId]['points_for'] += $awayScore;
                    $teamStats[$awayTeamId]['points_against'] += $homeScore;
                } else {
                    $teamStats[$homeTeamId]['points_for'] += $awayScore;
                    $teamStats[$homeTeamId]['points_against'] += $homeScore;
                    $teamStats[$awayTeamId]['points_for'] += $homeScore;
                    $teamStats[$awayTeamId]['points_against'] += $awayScore;
                }
    
                // Đếm số SET thắng
                if ($homeScore > $awayScore) {
                    if ($legHomeId == $homeTeamId) {
                        $teamStats[$homeTeamId]['set_wins']++;
                    } else {
                        $teamStats[$awayTeamId]['set_wins']++;
                    }
                } elseif ($awayScore > $homeScore) {
                    if ($legAwayId == $homeTeamId) {
                        $teamStats[$homeTeamId]['set_wins']++;
                    } else {
                        $teamStats[$awayTeamId]['set_wins']++;
                    }
                }
            }
        }
    
        // ===== BƯỚC 3: Tính hiệu số =====
        $homeSetWins = $teamStats[$homeTeamId]['set_wins'];
        $awaySetWins = $teamStats[$awayTeamId]['set_wins'];
        
        $homePointDiff = $teamStats[$homeTeamId]['points_for'] - $teamStats[$homeTeamId]['points_against'];
        $awayPointDiff = $teamStats[$awayTeamId]['points_for'] - $teamStats[$awayTeamId]['points_against'];
    
        // ===== BƯỚC 4: Xác định winner theo thứ tự ưu tiên =====
        $finalWinnerId = null;
    
        // 1️⃣ Ưu tiên số LEGS thắng
        if ($legWins[$homeTeamId] > $legWins[$awayTeamId]) {
            $finalWinnerId = $homeTeamId;
        } elseif ($legWins[$awayTeamId] > $legWins[$homeTeamId]) {
            $finalWinnerId = $awayTeamId;
        } elseif ($homeSetWins > $awaySetWins) {
            $finalWinnerId = $homeTeamId;
        } elseif ($awaySetWins > $homeSetWins) {
            $finalWinnerId = $awayTeamId;
        } elseif ($homePointDiff > $awayPointDiff) {
            $finalWinnerId = $homeTeamId;
        } elseif ($awayPointDiff > $homePointDiff) {
            $finalWinnerId = $awayTeamId;
        }
        if (!$finalWinnerId) {
            foreach ($allLegs as $leg) {
                $leg->update(['winner_id' => null]);
            }
            return;
        }
    
        // Cập nhật winner cho tất cả legs
        foreach ($allLegs as $leg) {
            if ($leg->winner_id !== $finalWinnerId) {
                $leg->update(['winner_id' => $finalWinnerId]);
            }
        }
    
        // ===== BƯỚC 6: Tiến vào vòng sau =====
        // ✅ XỬ LÝ ĐẶC BIỆT CHO ROUND 1 CỦA MIXED FORMAT (POOL STAGE)
        if ((int) $match->round === 1 && $tournamentType->format === TournamentType::FORMAT_MIXED) {
            $this->checkAndAdvanceFromPool($match);
            return;
        }
    
        // Xử lý các vòng knockout bình thường
        if (in_array($match->tournamentType->format, [
            TournamentType::FORMAT_MIXED,
            TournamentType::FORMAT_ELIMINATION,
        ])) {
            $this->syncWinnerToNextRoundLegs($leg1, $finalWinnerId);
            $this->handleLoserAdvancement($leg1);
        } else {
            \Log::info('ℹ️ Not a knockout format - skipping advancement');
        }
    
        // ===== BƯỚC 7: Cập nhật bảng xếp hạng =====
        $this->recalculateRankings($match->tournament_type_id);
    }

    private function syncWinnerToNextRoundLegs($match, $finalWinnerId)
    {
        $nextMatchId = $match->next_match_id;
        $nextPosition = $match->next_position;
    
        if (!$nextMatchId || !$finalWinnerId) {
            return;
        }
    
        // 1. Tìm trận đấu đích (Leg 1 của vòng sau)
        $targetLeg1 = Matches::find($nextMatchId);
        if (!$targetLeg1) {
            return;
        }
    
        // 2. ✅ CẬP NHẬT LEG 1 TRƯỚC
        if ($nextPosition === 'home') {
            $targetLeg1->update([
                'home_team_id' => $finalWinnerId,
                'status' => Matches::STATUS_PENDING,
                'is_bye' => $targetLeg1->away_team_id ? false : $targetLeg1->is_bye,
            ]);
        } else {
            $targetLeg1->update([
                'away_team_id' => $finalWinnerId,
                'status' => Matches::STATUS_PENDING,
                'is_bye' => $targetLeg1->home_team_id ? false : $targetLeg1->is_bye,
            ]);
        }
        $targetLeg1->refresh();
    
        // 3. ✅ TÌM VÀ CẬP NHẬT CÁC LEG KHÁC (theo round + teams)
        // CHỈ tìm các legs của CÙNG CẶP ĐẤU (cùng 2 teams, có thể đảo vị trí)
        $allOtherLegs = Matches::where('tournament_type_id', $match->tournament_type_id)
            ->where('round', $targetLeg1->round)
            ->where('id', '!=', $targetLeg1->id) // Loại chính nó (leg 1)
            ->where(function($query) use ($targetLeg1) {
                // Tìm matches có CẢ HAI teams giống với targetLeg1 (có thể đảo vị trí)
                $query->where(function($q) use ($targetLeg1) {
                    $q->where('home_team_id', $targetLeg1->home_team_id)
                      ->where('away_team_id', $targetLeg1->away_team_id);
                })->orWhere(function($q) use ($targetLeg1) {
                    $q->where('home_team_id', $targetLeg1->away_team_id)
                      ->where('away_team_id', $targetLeg1->home_team_id);
                });
            })
            ->orderBy('leg')
            ->get();
    
        // 4. ✅ CẬP NHẬT CÁC LEG KHÁC
        foreach ($allOtherLegs as $leg) {
            if ($leg->leg % 2 === 0) {
                // Leg chẵn: Đảo home ↔ away
                $leg->update([
                    'home_team_id' => $targetLeg1->away_team_id,
                    'away_team_id' => $targetLeg1->home_team_id,
                    'is_bye' => false,
                    'status' => Matches::STATUS_PENDING
                ]);
            } else {
                // Leg lẻ: Giữ nguyên như leg 1
                $leg->update([
                    'home_team_id' => $targetLeg1->home_team_id,
                    'away_team_id' => $targetLeg1->away_team_id,
                    'is_bye' => false,
                    'status' => Matches::STATUS_PENDING
                ]);
            }
        }
    }

    public function advanceTeamManual(Request $request, $matchId)
    {
        $validated = $request->validate([
            'winner_team_id' => 'required|exists:teams,id',
        ]);

        $match = Matches::with('tournamentType.tournament.staff', 'homeTeam', 'awayTeam')
            ->findOrFail($matchId);

        // Kiểm tra quyền organizer
        $tournament = $match->tournamentType->tournament;
        $isOrganizer = $tournament->hasOrganizer(Auth::id());

        if (!$isOrganizer) {
            return ResponseHelper::error('Chỉ BTC mới có quyền thực hiện thao tác này', 403);
        }
    
        $winnerTeamId = $validated['winner_team_id'];

        // Validate winner_team_id có thuộc trận này không
        if (!in_array($winnerTeamId, [$match->home_team_id, $match->away_team_id])) {
            return ResponseHelper::error('Đội được chọn không thuộc trận đấu này', 400);
        }

        // Lấy TẤT CẢ các legs của cặp đấu này
        $allLegs = Matches::where('tournament_type_id', $match->tournament_type_id)
            ->where('round', $match->round)
            ->where(function ($q) use ($match) {
                $q->where(function ($q2) use ($match) {
                    $q2->where('home_team_id', $match->home_team_id)
                        ->where('away_team_id', $match->away_team_id);
                })->orWhere(function ($q2) use ($match) {
                    $q2->where('home_team_id', $match->away_team_id)
                        ->where('away_team_id', $match->home_team_id);
                });
            })->get();

        // Kiểm tra tất cả legs đã hoàn thành chưa
        $allCompleted = $allLegs->every(fn($m) => $m->status === Matches::STATUS_COMPLETED);
        if (!$allCompleted) {
            return ResponseHelper::error('Tất cả các leg phải hoàn thành trước khi chọn đội thắng', 400);
        }

        DB::beginTransaction();
        try {
            $tournamentType = $match->tournamentType;
            
            // 1. Cập nhật winner_id cho TẤT CẢ các legs
            foreach ($allLegs as $leg) {
                $leg->update(['winner_id' => $winnerTeamId]);
            }
    
            // 2. Cập nhật bảng xếp hạng (cần làm trước khi advance)
            $this->recalculateRankings($match->tournament_type_id);
    
            // 3. ✅ XỬ LÝ ĐẶC BIỆT CHO ROUND 1 CỦA MIXED FORMAT (POOL STAGE)
            if ((int) $match->round === 1 && $tournamentType->format === TournamentType::FORMAT_MIXED) {
                // 3a. Tính toán vị trí của team trong bảng
                $groupId = $match->group_id;
                if (!$groupId) {
                    throw new \Exception('Trận đấu không thuộc bảng nào');
                }
    
                // Lấy tất cả teams trong bảng này
                $allGroupMatches = Matches::where('group_id', $groupId)
                    ->where('round', 1)
                    ->get();
    
                $teamIdsInGroup = $allGroupMatches->pluck('home_team_id')
                    ->merge($allGroupMatches->pluck('away_team_id'))
                    ->unique()
                    ->filter();
    
                // Lấy ranking hiện tại
                $currentRankings = TeamRanking::where('tournament_type_id', $match->tournament_type_id)
                    ->whereIn('team_id', $teamIdsInGroup)
                    ->orderBy('rank', 'asc')
                    ->get();
    
                // 3b. Tìm vị trí của winner_team trong bảng
                $winnerRanking = $currentRankings->firstWhere('team_id', $winnerTeamId);
                if (!$winnerRanking) {
                    throw new \Exception('Không tìm thấy ranking của đội thắng');
                }
    
                $rankInGroup = $currentRankings->search(function($r) use ($winnerTeamId) {
                    return $r->team_id == $winnerTeamId;
                }) + 1; // +1 vì index bắt đầu từ 0
    
                // 3c. Tìm PoolAdvancementRule tương ứng
                $advancementRule = PoolAdvancementRule::where('group_id', $groupId)
                    ->where('rank', $rankInGroup)
                    ->first();
    
                if (!$advancementRule) {
                    // Nếu không có rule cho rank này, có thể đội không đi tiếp
                    // hoặc gọi checkAndAdvanceFromPool để xử lý toàn bộ bảng
                    $this->checkAndAdvanceFromPool($match);
                } else {
                    // 3d. Đưa team vào trận knockout theo rule
                    $nextMatch = Matches::find($advancementRule->next_match_id);
                    if ($nextMatch) {
                        $updateData = [];
                        if ($advancementRule->next_position === 'home') {
                            $updateData['home_team_id'] = $winnerTeamId;
                        } else {
                            $updateData['away_team_id'] = $winnerTeamId;
                        }
                        $updateData['status'] = Matches::STATUS_PENDING;
                        
                        $nextMatch->update($updateData);
                    }
    
                    // Kiểm tra xem có cần xử lý các đội khác trong bảng không
                    $this->checkAllPoolsCompleted($match->tournament_type_id);
                }
            }
            // 4. Xử lý knockout bình thường
            elseif (in_array($tournamentType->format, [
                TournamentType::FORMAT_MIXED,
                TournamentType::FORMAT_ELIMINATION,
            ])) {
                $this->syncWinnerToNextRoundLegs($match, $winnerTeamId);
                
                // 4b. ✅ XỬ LÝ ĐỘI THUA VÀO TRẬN TRANH HẠNG 3
                $leg1 = $allLegs->firstWhere('leg', 1);
                if ($leg1 && $leg1->loser_next_match_id) {
                    // Xác định đội thua
                    $loserTeamId = ($winnerTeamId == $match->home_team_id) 
                        ? $match->away_team_id 
                        : $match->home_team_id;
    
                    // Lấy TẤT CẢ legs của trận tranh hạng 3
                    $loserNextMatch = Matches::find($leg1->loser_next_match_id);
                    if ($loserNextMatch) {
                        $thirdPlaceMatches = Matches::where('tournament_type_id', $match->tournament_type_id)
                            ->where('round', $loserNextMatch->round)
                            ->where('is_third_place', true)
                            ->orderBy('leg')
                            ->get();
    
                        // Fill loser vào tất cả legs của trận tranh hạng 3
                        foreach ($thirdPlaceMatches as $thirdMatch) {
                            $position = $leg1->loser_next_position;
                            
                            $updateData = ['status' => Matches::STATUS_PENDING];
                            
                            // Xử lý đảo vị trí cho leg chẵn/lẻ
                            if ($thirdMatch->leg % 2 !== 0) {
                                // Leg lẻ: giữ nguyên position
                                $updateData[$position . '_team_id'] = $loserTeamId;
                            } else {
                                // Leg chẵn: đảo position
                                $reversePosition = ($position === 'home') ? 'away' : 'home';
                                $updateData[$reversePosition . '_team_id'] = $loserTeamId;
                            }
    
                            $thirdMatch->update($updateData);
                        }
                    }
                }
            }
    
            DB::commit();

            return ResponseHelper::success(
                [
                    'winner_team' => $winnerTeamId == $match->home_team_id
                        ? $match->homeTeam
                        : $match->awayTeam,
                    'updated_legs' => $allLegs->pluck('id'),
                    'advancement_info' => [
                        'is_pool_stage' => ((int) $match->round === 1 && $tournamentType->format === TournamentType::FORMAT_MIXED),
                        'group_id' => $match->group_id ?? null,
                        'rank_in_group' => $rankInGroup ?? null,
                        'third_place_advanced' => isset($loserTeamId) && isset($thirdPlaceMatches) && $thirdPlaceMatches->isNotEmpty(),
                    ]
                ],
                'Đã chọn đội thắng và tiến vào vòng trong thành công',
                200
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
        }
    }
    private function handleLoserAdvancement(Matches $match)
    {
        // ✅ Tìm leg 1 của cặp đấu này để lấy thông tin loser advancement
        $leg1 = Matches::where('tournament_type_id', $match->tournament_type_id)
            ->where('round', $match->round)
            ->where('leg', 1)
            ->where(function($q) use ($match) {
                // Tìm leg 1 có cùng next_match_id (cùng cặp đấu)
                if ($match->next_match_id) {
                    $q->where('next_match_id', $match->next_match_id)
                      ->where('next_position', $match->next_position);
                } else {
                    // Nếu là trận cuối, tìm theo teams
                    $q->where(function($q2) use ($match) {
                        $q2->where('home_team_id', $match->home_team_id)
                           ->where('away_team_id', $match->away_team_id);
                    })->orWhere(function($q2) use ($match) {
                        $q2->where('home_team_id', $match->away_team_id)
                           ->where('away_team_id', $match->home_team_id);
                    });
                }
            })
            ->first();
    
        // Nếu không tìm thấy leg 1, thử tìm trong cùng round với is_bye = false
        if (!$leg1) {
            $allLegsInRound = Matches::where('tournament_type_id', $match->tournament_type_id)
                ->where('round', $match->round)
                ->where('is_bye', false)
                ->orderBy('leg')
                ->get();
            
            $leg1 = $allLegsInRound->firstWhere('leg', 1);
        }
        
        if (!$leg1 || !$leg1->loser_next_match_id) {
            return;
        }
    
        // Lấy TẤT CẢ legs của cặp đấu này
        $allLegs = Matches::where('tournament_type_id', $match->tournament_type_id)
            ->where('round', $match->round)
            ->where(function($q) use ($leg1) {
                if ($leg1->next_match_id) {
                    $q->where('next_match_id', $leg1->next_match_id)
                      ->where('next_position', $leg1->next_position);
                }
            })
            ->with('results')
            ->get();
    
        // Nếu không tìm thấy bằng next_match, tìm theo teams
        if ($allLegs->count() < 2) {
            $allLegs = Matches::where('tournament_type_id', $match->tournament_type_id)
                ->where('round', $match->round)
                ->where(function($q) use ($leg1) {
                    $q->where(function($q2) use ($leg1) {
                        $q2->where('home_team_id', $leg1->home_team_id)
                           ->where('away_team_id', $leg1->away_team_id);
                    })->orWhere(function($q2) use ($leg1) {
                        $q2->where('home_team_id', $leg1->away_team_id)
                           ->where('away_team_id', $leg1->home_team_id);
                    });
                })
                ->with('results')
                ->get();
        }
    
        // Kiểm tra tất cả legs đã hoàn thành chưa
        $allCompleted = $allLegs->every(fn($l) => $l->status === Matches::STATUS_COMPLETED);
        if (!$allCompleted) {
            return;
        }
    
        // Lấy winner_id (đã được set cho tất cả legs)
        $winnerId = $leg1->winner_id;
        if (!$winnerId) {
            return;
        }
    
        $baseHomeId = $leg1->home_team_id;
        $baseAwayId = $leg1->away_team_id;
    
        // Xác định loser
        $loserId = ($winnerId == $baseHomeId) ? $baseAwayId : $baseHomeId;
    
        // Lấy thông tin trận tranh hạng 3
        $loserNextMatch = Matches::find($leg1->loser_next_match_id);
        if (!$loserNextMatch) {
            return;
        }
    
        // Lấy TẤT CẢ legs của trận tranh hạng 3
        $thirdPlaceMatches = Matches::where('tournament_type_id', $match->tournament_type_id)
            ->where('round', $loserNextMatch->round)
            ->where('is_third_place', true)
            ->orderBy('leg')
            ->get();
    
        // Fill loser vào tất cả legs
        foreach ($thirdPlaceMatches as $thirdMatch) {
            $position = $leg1->loser_next_position; // ✅ Lấy từ leg 1
            
            $updateData = ['status' => Matches::STATUS_PENDING];
            
            // Xử lý đảo vị trí cho leg chẵn/lẻ
            if ($thirdMatch->leg % 2 !== 0) {
                // Leg lẻ: giữ nguyên position
                $updateData[$position . '_team_id'] = $loserId;
            } else {
                // Leg chẵn: đảo position
                $reversePosition = ($position === 'home') ? 'away' : 'home';
                $updateData[$reversePosition . '_team_id'] = $loserId;
            }
    
            $thirdMatch->update($updateData);
        }
    }

    private function pushToUsers(array $userIds, string $title, string $body, array $data = [])
    {
        foreach ($userIds as $userId) {
            SendPushJob::dispatch($userId, $title, $body, $data);
        }
    }

    /**
     * Logic tìm đội thua có thành tích tốt nhất trong round vừa kết thúc 
     * và gán vào các trận placeholder ở round kế tiếp.
     */
    private function checkAndAssignBestLosersForElimination(Matches $match)
    {
        $tournamentTypeId = $match->tournament_type_id;
        $round = (int)$match->round;

        // 1. Kiểm tra xem tất cả các trận trong round này đã xong chưa
        $allRoundMatches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('round', $round)
            ->get();
        if ($allRoundMatches->isEmpty() || !$allRoundMatches->every(fn($m) => $m->status === Matches::STATUS_COMPLETED)) {
            return;
        }

        // 2. Tìm match placeholder ở round tiếp theo
        // Ưu tiên tìm theo best_loser_source_round nếu có
        $waitingMatches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('round', $round + 1)
            ->where('best_loser_source_round', $round)
            ->get();

        // 🔍 Nếu không thấy, tìm các trận "khuyết" (away_team_id null và không có trận nào trỏ tới nó)
        if ($waitingMatches->isEmpty()) {
            $potentialMatches = Matches::where('tournament_type_id', $tournamentTypeId)
                ->where('round', $round + 1)
                ->whereNull('away_team_id')
                ->where('is_bye', 0)
                ->get();

            foreach ($potentialMatches as $pm) {
                $hasIncoming = Matches::where('tournament_type_id', $tournamentTypeId)
                    ->where('round', $round)
                    ->where('next_match_id', $pm->id)
                    ->where('next_position', 'away')
                    ->exists();
                
                if (!$hasIncoming) {
                    $waitingMatches->push($pm);
                }
            }
        }

        if ($waitingMatches->isEmpty()) {
            return;
        }

        // 3. Tìm danh sách đội thua trong round này
        $losers = [];
        foreach ($allRoundMatches as $rm) {
            if ($rm->is_bye) continue;

            $loserId = ($rm->winner_id == $rm->home_team_id) ? $rm->away_team_id : $rm->home_team_id;
            if (!$loserId) continue;

            // Tính toán điểm dựa trên kết quả trận đấu (số set thắng)
            $loserSetsWon = DB::table('match_results')
                ->where('match_id', $rm->id)
                ->where('team_id', $loserId)
                ->where('won_match', true) // Giả định field này đánh dấu thắng set
                ->count();

            $loserTotalScore = DB::table('match_results')
                ->where('match_id', $rm->id)
                ->where('team_id', $loserId)
                ->sum('score');

            $losers[] = [
                'id' => $loserId,
                'sets_won' => (int)$loserSetsWon,
                'total_score' => (int)$loserTotalScore,
            ];
        }

        // Sắp xếp đội thua: Ưu tiên số set thắng, sau đó là tổng điểm
        usort($losers, function ($a, $b) {
            if ($b['sets_won'] !== $a['sets_won']) {
                return $b['sets_won'] <=> $a['sets_won'];
            }
            return $b['total_score'] <=> $a['total_score'];
        });
        
        foreach ($waitingMatches as $index => $wm) {
            if (isset($losers[$index])) {
                $bestLoserId = $losers[$index]['id'];
                $wm->update([
                    'away_team_id' => $bestLoserId,
                    'is_bye' => false,
                ]);
                Log::info("Assigned Best Loser Team ID {$bestLoserId} to Match ID {$wm->id}");

                // ✅ Tiếp tục đệ quy nếu đây là trận bye (trường hợp hiếm nhưng có thể xảy ra)
                if ($wm->status === Matches::STATUS_COMPLETED && $wm->winner_id) {
                    $this->advanceWinnerToNextRound($wm, $wm->winner_id);
                }
            }
        }
    }
}
