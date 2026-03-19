<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\MiniMatchResource;
use App\Jobs\SendPushJob;
use App\Models\MiniMatch;
use App\Models\MiniMatchResult;
use App\Models\MiniParticipant;
use App\Models\MiniTeam;
use App\Models\MiniTournament;
use App\Models\User;
use App\Models\VnduprHistory;
use App\Notifications\MiniMatchCreatedNotification;
use App\Notifications\MiniMatchResultConfirmedNotification;
use App\Notifications\MiniMatchUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MiniMatchController extends Controller
{
    private const VALIDATION_RULE = 'sometimes';
    /**
     * Lấy danh sách trận đấu trong mini tournament (theo vòng, thời gian, lọc theo người chơi)
     */
    public function index(Request $request, $miniTournamentId)
    {
        $request->validate([
            'filter' => 'nullable|string|in:matches,my_matches,leaderboard',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $miniTournament = MiniTournament::findOrFail($miniTournamentId);
        $filter = $request->input('filter', 'matches');
        $perPage = $request->input('per_page', MiniMatch::PER_PAGE);

        $query = MiniMatch::withFullRelations()
            ->where('mini_tournament_id', $miniTournament->id)
            ->orderBy('created_at', 'desc');

        if ($filter === 'my_matches') {
            $userId = Auth::id();

            $query->where(function ($q) use ($userId) {
                $q->whereHas('team1.members.user', function ($sub) use ($userId) {
                    $sub->where('user_id', $userId);
                })->orWhereHas('team2.members.user', function ($sub) use ($userId) {
                    $sub->where('user_id', $userId);
                });
            });
        }

        // Paginate
        $matches = $query->paginate($perPage);

        $data = [
            'matches' => MiniMatchResource::collection($matches),
        ];

        $meta = [
            'current_page' => $matches->currentPage(),
            'last_page'    => $matches->lastPage(),
            'per_page'     => $matches->perPage(),
            'total'        => $matches->total(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách trận đấu thành công', 200, $meta);
    }
    /**
     * Lấy thông tin chi tiết trận đấu
     */
    public function show($matchId)
    {
        $match = MiniMatch::withFullRelations()->findOrFail($matchId);

        return ResponseHelper::success(new MiniMatchResource($match), 'Lấy thông tin trận đấu thành công');
    }
    public function store(Request $request, $miniTournamentId)
    {
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền tạo trận đấu', 403);
        }

        $data = $request->validate([
            'team1' => 'required|array|min:1',
            'team2' => 'required|array|min:1',
            'team1.*' => 'exists:users,id',
            'team2.*' => 'exists:users,id',
            'team1_name' => 'nullable|string|max:255',
            'team2_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        $team1Count = count($data['team1']);
        $team2Count = count($data['team2']);

        if ($team1Count !== $team2Count) {
            return ResponseHelper::error('Số lượng người chơi của 2 đội phải bằng nhau', 422);
        }

        switch ($miniTournament->format) {
            case MiniTournament::FORMAT_SINGLE:
                if ($team1Count !== 1) {
                    return ResponseHelper::error('Kèo này chỉ cho phép tạo trận 1v1', 422);
                }
                break;
            case MiniTournament::FORMAT_DOUBLE:
            case MiniTournament::FORMAT_MENS_DOUBLES:
            case MiniTournament::FORMAT_WOMENS_DOUBLES:
            case MiniTournament::FORMAT_MIXED:
                if ($team1Count !== 2) {
                    return ResponseHelper::error('Kèo này chỉ cho phép tạo trận 2v2', 422);
                }
                break;
            default:
                if (!in_array($team1Count, [1, 2])) {
                    return ResponseHelper::error('Chỉ cho phép tạo trận 1v1 hoặc 2v2', 422);
                }
                break;
        }

        $allUserIds = array_unique(array_merge($data['team1'], $data['team2']));

        $validParticipants = MiniParticipant::where('mini_tournament_id', $miniTournament->id)
            ->where('is_confirmed', true)
            ->whereIn('user_id', $allUserIds)
            ->pluck('user_id')
            ->toArray();

        if (count($validParticipants) !== count($allUserIds)) {
            return ResponseHelper::error(
                'Có người chơi chưa tham gia hoặc chưa được duyệt trong kèo',
                422
            );
        }

        DB::beginTransaction();

        try {
            $team1 = MiniTeam::create([
                'mini_tournament_id' => $miniTournament->id,
                'name' => $data['team1_name'] ?? 'Team 1',
            ]);

            foreach ($data['team1'] as $userId) {
                $team1->members()->create(['user_id' => $userId]);
            }

            $team2 = MiniTeam::create([
                'mini_tournament_id' => $miniTournament->id,
                'name' => $data['team2_name'] ?? 'Team 2',
            ]);

            foreach ($data['team2'] as $userId) {
                $team2->members()->create(['user_id' => $userId]);
            }
            $defaultMatchName = $this->generateMatchName($miniTournament);

            $match = MiniMatch::create([
                'mini_tournament_id' => $miniTournament->id,
                'team1_id' => $team1->id,
                'team2_id' => $team2->id,
                'status' => MiniMatch::STATUS_PENDING,
                'name' => $data['name'] ?? $defaultMatchName,
            ]);

            DB::commit();
            $allParticipantIds = array_unique(array_merge($data['team1'], $data['team2']));
            $users = User::whereIn('id', $allParticipantIds)->get();
            $users->each(function ($user) use ($match) {
                $user->notify(new MiniMatchCreatedNotification($match));
            });

            return ResponseHelper::success(new MiniMatchResource($match->loadFullRelations()), 'Tạo trận đấu thành công', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage());
        }
    }
    /**
     * Cập nhật thông tin trận đấu trong kèo đấu
     */

    public function update(Request $request, $matchId)
    {
        $match = MiniMatch::withFullRelations()->findOrFail($matchId);

        $miniTournament = $match->miniTournament;

        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền sửa trận đấu', 403);
        }

        $data = $request->validate([
            'team1' => 'sometimes|array|min:1',
            'team2' => 'sometimes|array|min:1',
            'team1.*' => 'exists:users,id',
            'team2.*' => 'exists:users,id',
            'team1_name' => 'nullable|string|max:255',
            'team2_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        // ---- CHECK MATCH TYPE ----
        $team1Count = isset($data['team1']) ? count($data['team1']) : $match->team1->members->count();

        $team2Count = isset($data['team2']) ? count($data['team2']) : $match->team2->members->count();

        if ($team1Count !== $team2Count) {
            return ResponseHelper::error('Số lượng người chơi của 2 đội phải bằng nhau', 422);
        }

        switch ($miniTournament->format) {
            case MiniTournament::FORMAT_SINGLE:
                if ($team1Count !== 1) {
                    return ResponseHelper::error('Kèo này chỉ cho phép tạo trận 1v1', 422);
                }
                break;

            case MiniTournament::FORMAT_DOUBLE:
            case MiniTournament::FORMAT_MENS_DOUBLES:
            case MiniTournament::FORMAT_WOMENS_DOUBLES:
            case MiniTournament::FORMAT_MIXED:
                if ($team1Count !== 2) {
                    return ResponseHelper::error('Kèo này chỉ cho phép tạo trận 2v2', 422);
                }
                break;

            default:
                if (!in_array($team1Count, [1, 2])) {
                    return ResponseHelper::error('Chỉ cho phép tạo trận 1v1 hoặc 2v2', 422);
                }
        }

        DB::beginTransaction();

        try {
            // ---- UPDATE TEAM 1 ----
            if (isset($data['team1'])) {
                $this->syncTeamMembers($match->team1, $data['team1']);
            }

            if (!empty($data['team1_name'])) {
                $match->team1->update(['name' => $data['team1_name']]);
            }

            // ---- UPDATE TEAM 2 ----
            if (isset($data['team2'])) {
                $this->syncTeamMembers($match->team2, $data['team2']);
            }

            if (!empty($data['team2_name'])) {
                $match->team2->update(['name' => $data['team2_name']]);
            }

            // ---- UPDATE MATCH INFO ----
            $match->update([
                'name' => $data['name'] ?? $match->name,
            ]);

            DB::commit();
            $team1UserIds = $match->team1->members()->pluck('user_id')->toArray();
            $team2UserIds = $match->team2->members()->pluck('user_id')->toArray();
            $allParticipantIds = array_unique(array_merge($team1UserIds, $team2UserIds));
            $users = User::whereIn('id', $allParticipantIds)->get();

            $users->each(function ($user) use ($match) {
                $user->notify(new MiniMatchUpdatedNotification($match));
            });

            return ResponseHelper::success(
                new MiniMatchResource($match->loadFullRelations()),
                'Cập nhật trận đấu thành công'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage());
        }
    }

    protected function syncTeamMembers(MiniTeam $team, array $userIds)
    {
        $team->members()->delete();

        foreach ($userIds as $userId) {
            $team->members()->create(['user_id' => $userId]);
        }
    }

    /**
     * Thêm hoặc cập nhật kết quả 1 hiệp (set)
     */
    public function addSetResult(Request $request, $matchId)
    {
        $validated = $request->validate([
            'sets' => 'required|array|min:1',
            'sets.*.set_number' => 'required|integer|min:1',
            'sets.*.results' => 'required|array|size:2',
            'sets.*.results.*.team_id' => 'required|exists:mini_teams,id',
            'sets.*.results.*.score' => 'required|integer|min:0',
        ]);

        $match = MiniMatch::withFullRelations()->findOrFail($matchId);
        $tournament = $match->miniTournament->load('staff');

        // Kiểm tra quyền
        if (!$tournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error(
                'Người dùng không có quyền thêm kết quả trận đấu trong kèo đấu này',
                403
            );
        }

        // Kiểm tra trận đấu còn editable không
        if (!$match->isEditable()) {
            return ResponseHelper::error(
                'Trận đấu này đã được xác nhận kết quả',
                400
            );
        }

        // Validate team thuộc trận đấu
        $teamIds = [$match->team1_id, $match->team2_id];

        DB::transaction(function () use ($validated, $match, $teamIds) {

            foreach ($validated['sets'] as $set) {
                $inputResults = collect($set['results']);

                // Validate đủ 2 team
                if ($inputResults->count() !== 2) {
                    throw new \Exception('Cần cung cấp điểm số cho cả hai đội');
                }

                $teamA = $inputResults->firstWhere('team_id', $teamIds[0]);
                $teamB = $inputResults->firstWhere('team_id', $teamIds[1]);

                if (!$teamA || !$teamB) {
                    throw new \Exception(
                        'Team không hợp lệ hoặc không thuộc trận đấu này'
                    );
                }

                // Xóa set cũ (nếu update lại set)
                MiniMatchResult::where('mini_match_id', $match->id)
                    ->where('set_number', $set['set_number'])
                    ->delete();

                // Lưu kết quả set
                foreach ($set['results'] as $res) {
                    MiniMatchResult::create([
                        'mini_match_id' => $match->id,
                        'team_id' => $res['team_id'],
                        'score' => $res['score'],
                        'set_number' => $set['set_number'],
                        'won_set' => false, // sẽ tính khi confirm
                    ]);
                }
            }

            // Reset confirm
            $match->update([
                'team1_confirm' => false,
                'team2_confirm' => false,
            ]);
        });

        $match = MiniMatch::withFullRelations()->findOrFail($matchId);

        return ResponseHelper::success(
            new MiniMatchResource($match),
            'Lưu kết quả set thành công'
        );
    }

    /**
     * Xóa kết quả 1 hiệp
     */
    public function deleteSetResult($matchId, $setNumber)
    {
        $match = MiniMatch::with('miniTournament')->findOrFail($matchId);
        $tournament = $match->miniTournament->load('staff');
        if (!$tournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Người dùng không có quyền xoá kết quả trận đấu trong kèo đấu này', 403);
        }

        if (!$match->isEditable()) {
            return ResponseHelper::error('Trận đấu đã được xác nhận không thể xoá kết quả', 400);
        }

        MiniMatchResult::where('mini_match_id', $match->id)
            ->where('set_number', $setNumber)
            ->delete();

        return ResponseHelper::success(null, 'Kết quả hiệp đã được xóa');
    }

    /**
     * Xóa trận đấu
     */
    public function destroy(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return ResponseHelper::error('Danh sách trận đấu không hợp lệ', 400);
        }

        $matches = MiniMatch::with('miniTournament')
            ->whereIn('id', $ids)
            ->get();

        if ($matches->isEmpty()) {
            return ResponseHelper::error('Không tìm thấy trận đấu nào', 404);
        }

        foreach ($matches as $match) {
            $tournament = $match->miniTournament->load('staff');
            if (!$tournament->hasOrganizer(Auth::id())) {
                return ResponseHelper::error('Người dùng không có quyền xoá trận đấu này', 403);
            }
            if (!$match->isEditable()) {
                return ResponseHelper::error("Không thể xóa trận đấu đã xác nhận kết quả", 400);
            }
        }

        MiniMatchResult::whereIn('mini_match_id', $ids)->delete();
        MiniMatch::whereIn('id', $ids)->delete();

        return ResponseHelper::success(null, 'Xoá thành công');
    }

    /**
     * Tạo QR code để xác nhận kết quả trận đấu
     */

    public function generateQr($matchId)
    {
        $match = MiniMatch::with('miniTournament')->findOrFail($matchId);
        $url = url("/api/mini-matches/confirm-result/{$match->id}");

        return ResponseHelper::success(['qr_url' => $url], 'Thành công');
    }
    /**
     * Xác nhận kết quả trận đấu (thông qua QR code)
     */

    public function confirmResult($matchId)
    {
        $match = MiniMatch::withFullRelations()->findOrFail($matchId);

        if (!$match->isEditable()) {
            return ResponseHelper::error('Kết quả trận đấu đã được xác nhận trước đó', 400);
        }

        $tournament = $match->miniTournament;
        $sportId = $tournament->sport_id;
        $currentUserId = Auth::id();
        $isOrganizer = $tournament->hasOrganizer($currentUserId);

        // Kiểm tra quyền xác nhận
        $userTeam = null;
        if (!$isOrganizer) {
            if ($match->team1->members->contains('user_id', $currentUserId)) {
                $userTeam = $match->team1;
            } elseif ($match->team2->members->contains('user_id', $currentUserId)) {
                $userTeam = $match->team2;
            }

            if (!$userTeam) {
                return ResponseHelper::error('Bạn không có quyền xác nhận kết quả trận đấu này', 403);
            }
        }

        // ===================================
        // VALIDATE TOÀN BỘ KẾT QUẢ CÁC SET
        // ===================================
        $validationError = $this->validateAllSets($match, $tournament);
        if ($validationError) {
            return ResponseHelper::error($validationError, 400);
        }

        // Thực hiện confirm
        $result = DB::transaction(function () use ($match, $isOrganizer, $userTeam, $sportId) {
            if ($isOrganizer) {
                $match->team1_confirm = true;
                $match->team2_confirm = true;
            } else {
                if ($userTeam->id === $match->team1_id) $match->team1_confirm = true;
                if ($userTeam->id === $match->team2_id) $match->team2_confirm = true;
            }

            if ($match->team1_confirm && $match->team2_confirm) {
                $this->processMatchCompletion($match, $sportId);
            }

            $match->save();
            return $match;
        });

        // Gửi thông báo
        $team1UserIds = $match->team1->members()->pluck('user_id')->toArray();
        $team2UserIds = $match->team2->members()->pluck('user_id')->toArray();
        $allParticipantIds = array_unique(array_merge($team1UserIds, $team2UserIds));
        $recipientIds = array_diff($allParticipantIds, [$currentUserId]);

        if (!empty($recipientIds)) {
            $users = User::whereIn('id', $recipientIds)->get();
            foreach ($users as $user) {
                $user->notify(new MiniMatchResultConfirmedNotification($match));
            }
        }

        $confirmedByOrganizer = $isOrganizer;
        $confirmedByPlayer    = !$isOrganizer && $userTeam;
        $actorName = auth()->user()->full_name;

        if ($confirmedByPlayer) {

            $opponentTeam = $userTeam->id === $match->team1_id
                ? $match->team2
                : $match->team1;

            $recipientIds = $opponentTeam->members()
                ->where('user_id', '!=', $currentUserId)
                ->pluck('user_id')
                ->toArray();

            $this->pushToUsers(
                $recipientIds,
                'Xác nhận kết quả kèo đấu',
                "{$actorName} đã xác nhận kết quả kèo đấu",
                [
                    'type' => 'MINI_MATCH_CONFIRM',
                    'match_id' => $match->id,
                    'by' => 'player',
                ]
            );
        }

        if ($confirmedByOrganizer) {

            $team1UserIds = $match->team1->members()
                ->where('user_id', '!=', $currentUserId)
                ->pluck('user_id')
                ->toArray();

            $team2UserIds = $match->team2->members()
                ->where('user_id', '!=', $currentUserId)
                ->pluck('user_id')
                ->toArray();

            $recipientIds = array_unique(array_merge($team1UserIds, $team2UserIds));

            $this->pushToUsers(
                $recipientIds,
                'Kết quả kèo đấu đã được xác nhận',
                'Ban tổ chức đã xác nhận kết quả kèo đấu',
                [
                    'type' => 'MINI_MATCH_CONFIRM',
                    'match_id' => $match->id,
                    'by' => 'organizer',
                ]
            );
        }

        return ResponseHelper::success(
            new MiniMatchResource($result->refresh()),
            'Xác nhận kết quả thành công'
        );
    }

    private function validateAllSets($match, $tournament)
    {
        $pointsToWinSet = $tournament->base_points;
        $pointsDifference = $tournament->points_difference;
        $maxPoints = $tournament->max_points;
        $setNumber = $tournament->set_number;

        // Debug logging
        \Log::info('Validate All Sets', [
            'tournament_id' => $tournament->id,
            'base_points' => $pointsToWinSet,
            'points_difference' => $pointsDifference,
            'max_points' => $maxPoints,
            'set_number' => $setNumber,
            'apply_rule' => $tournament->apply_rule,
        ]);

        // Kiểm tra luật thi đấu
        if ($pointsToWinSet === null || $pointsDifference === null || $maxPoints === null) {
            return 'Kèo đấu chưa thiết lập đủ luật thi đấu';
        }

        // Kiểm tra apply_rule
        if (!$tournament->apply_rule) {
            // Nếu không áp dụng luật, chỉ cần có kết quả là được
            if ($match->results->isEmpty()) {
                return 'Trận đấu chưa có kết quả nào';
            }
            \Log::info('Apply rule is false, skipping validation');
            return null; // Bỏ qua validation
        }

        $team1Id = $match->team1_id;
        $team2Id = $match->team2_id;
        $allResults = $match->results->groupBy('set_number');

        if ($allResults->isEmpty()) {
            return 'Trận đấu chưa có kết quả nào';
        }

        $team1WonSets = 0;
        $team2WonSets = 0;

        // Validate từng set
        foreach ($allResults as $setNumber => $setResults) {
            if ($setResults->count() !== 2) {
                return "Set {$setNumber}: Thiếu điểm số của một trong hai đội";
            }

            $teamA = $setResults->firstWhere('team_id', $team1Id);
            $teamB = $setResults->firstWhere('team_id', $team2Id);

            if (!$teamA || !$teamB) {
                return "Set {$setNumber}: Dữ liệu không hợp lệ";
            }

            $A = (int) $teamA->score;
            $B = (int) $teamB->score;

            // Validate logic thắng thua
            $validation = $this->validateSetScore(
                $A,
                $B,
                $pointsToWinSet,
                $pointsDifference,
                $maxPoints,
                $team1Id,
                $team2Id
            );

            if ($validation['error']) {
                return "Set {$setNumber}: {$validation['message']}";
            }

            // Đếm số set thắng
            $winnerTeamId = $validation['winner'];
            if ($winnerTeamId === $team1Id) {
                $team1WonSets++;
            } else {
                $team2WonSets++;
            }

            // Cập nhật won_set
            $teamA->update(['won_set' => ($teamA->team_id == $winnerTeamId)]);
            $teamB->update(['won_set' => ($teamB->team_id == $winnerTeamId)]);
        }

        // Validate không hòa số set
        if ($team1WonSets === $team2WonSets) {
            return "Không thể xác nhận kết quả khi hòa số set ({$team1WonSets}-{$team2WonSets})";
        }

        // Validate số set tối đa (optional - nếu muốn strict)
        // Ví dụ: best of 3 thì tối đa 3 set
        $totalSets = $allResults->count();
        if ($totalSets > $setNumber) {
            return "Số set vượt quá giới hạn ({$totalSets}/{$setNumber})";
        }

        return null; // Không có lỗi
    }

    // ============================================
    // 4. VALIDATE SET SCORE - LOGIC THẮNG THUA
    // ============================================
    // private function validateSetScore($A, $B, $pointsToWinSet, $pointsDifference, $maxPoints, $team1Id, $team2Id)
    // {
    //     if ($A < 0 || $B < 0) {
    //         return ['error' => true, 'message' => 'Điểm số không hợp lệ'];
    //     }

    //     $scoreDiff = abs($A - $B);
    //     $isPointsToWinReached = ($A >= $pointsToWinSet || $B >= $pointsToWinSet);
    //     $isMaxPointsReached = ($A == $maxPoints || $B == $maxPoints);
    //     $winnerTeamId = null;

    //     if ($pointsToWinSet == $maxPoints) {
    //         // Trường hợp: 11-2-11
    //         if (!$isMaxPointsReached) {
    //             return ['error' => true, 'message' => "Chưa đạt điểm tối đa {$maxPoints}"];
    //         }
    //         $winnerTeamId = $A > $B ? $team1Id : $team2Id;
    //     } else {
    //         // Trường hợp: 11-2-15
    //         if ($isPointsToWinReached && $scoreDiff >= $pointsDifference) {
    //             $winnerTeamId = $A > $B ? $team1Id : $team2Id;
    //         } elseif ($isMaxPointsReached) {
    //             if ($A == $B) {
    //                 return ['error' => true, 'message' => "Điểm số hòa tại điểm tối đa {$maxPoints}"];
    //             }
    //             $winnerTeamId = $A > $B ? $team1Id : $team2Id;
    //         } else {
    //             return ['error' => true, 'message' => 'Set chưa hoàn thành hoặc chưa đủ điều kiện thắng'];
    //         }
    //     }

    //     // Anti-cheat
    //     $winningScore = max($A, $B);
    //     $losingScore = min($A, $B);

    //     if ($pointsToWinSet == $maxPoints) {
    //         if (!($winningScore == $maxPoints && $losingScore < $maxPoints)) {
    //             return ['error' => true, 'message' => 'Điểm số không hợp lệ (anti-cheat)'];
    //         }
    //     } else {
    //         if ($winningScore < $maxPoints) {
    //             if ($winningScore < $pointsToWinSet || ($winningScore - $losingScore) < $pointsDifference) {
    //                 return ['error' => true, 'message' => 'Điểm số không hợp lệ (anti-cheat)'];
    //             }
    //         } else {
    //             if ($winningScore != $maxPoints || $winningScore <= $losingScore) {
    //                 return ['error' => true, 'message' => 'Điểm số không hợp lệ (anti-cheat)'];
    //             }
    //         }
    //     }

    //     return ['error' => false, 'winner' => $winnerTeamId];
    // }
    private function validateSetScore($A, $B, $pointsToWinSet, $pointsDifference, $maxPoints, $team1Id, $team2Id)
    {
        \Log::info('Validate Set Score', [
            'A' => $A,
            'B' => $B,
            'pointsToWinSet' => $pointsToWinSet,
            'pointsDifference' => $pointsDifference,
            'maxPoints' => $maxPoints,
        ]);

        if ($A < 0 || $B < 0) {
            return ['error' => true, 'message' => 'Điểm số không hợp lệ'];
        }

        // Check 1: Không được hòa
        if ($A == $B) {
            return ['error' => true, 'message' => 'Không được có tỉ số hòa'];
        }

        $winningScore = max($A, $B);
        $losingScore = min($A, $B);
        $scoreDiff = $winningScore - $losingScore;

        \Log::info('Score Analysis', [
            'winningScore' => $winningScore,
            'losingScore' => $losingScore,
            'scoreDiff' => $scoreDiff,
        ]);

        // Check 2: Không được vượt quá max_points
        if ($winningScore > $maxPoints) {
            return ['error' => true, 'message' => "Điểm số không được vượt quá {$maxPoints}"];
        }

        // Check 3: Ít nhất 1 đội phải đạt pointsToWinSet
        if ($winningScore < $pointsToWinSet) {
            return ['error' => true, 'message' => "Ít nhất 1 đội phải đạt {$pointsToWinSet} điểm"];
        }

        // Check 4: Nếu chưa đạt max_points, phải đủ khoảng cách
        if ($winningScore < $maxPoints) {
            \Log::info('Checking points difference', [
                'winningScore < maxPoints' => true,
                'scoreDiff' => $scoreDiff,
                'pointsDifference' => $pointsDifference,
                'scoreDiff < pointsDifference' => ($scoreDiff < $pointsDifference),
            ]);
            
            if ($scoreDiff < $pointsDifference) {
                return ['error' => true, 'message' => "Phải thắng cách {$pointsDifference} điểm (hiện tại: {$scoreDiff})"];
            }
        }

        // Check 5: Nếu đạt max_points, người thắng phải có điểm cao hơn
        // (đã check ở Check 1 - không hòa)

        $winnerTeamId = $A > $B ? $team1Id : $team2Id;
        return ['error' => false, 'winner' => $winnerTeamId];
    }

    /**
     * Logic xử lý khi trận đấu hoàn tất (Tính winner, Elo/VNDUPR)
     */
    private function processMatchCompletion($match, $sportId)
    {
        // A. Xác định đội thắng
        $wins = $match->results->where('won_set', true)->groupBy('team_id')->map->count();
        $maxWins = $wins->max();
        $winnerTeams = $wins->filter(fn($c) => $c === $maxWins)->keys();

        $match->team_win_id = $winnerTeams->count() === 1 ? $winnerTeams->first() : null;
        $match->status = MiniMatch::STATUS_COMPLETED;
        $match->save();

        foreach ($match->results as $r) {
            $r->update(['status' => MiniMatchResult::STATUS_APPROVED]);
        }

        // ===== ANCHOR MATCH LOGIC (CHỈ CHẠY 1 LẦN / MINI MATCH) =====

        // Lấy toàn bộ user trong mini match
        $allUsersInMatch = collect()
            ->merge($match->team1->members->pluck('user'))
            ->merge($match->team2->members->pluck('user'))
            ->unique('id')
            ->values();

        // Kiểm tra trong trận có anchor không
        $hasAnchorInMatch = $allUsersInMatch->contains(function ($user) {
            return $user->is_anchor
                || ($user->total_matches_has_anchor ?? 0) >= 10;
        });

        // Nếu có anchor → cộng cho người KHÔNG phải anchor
        if ($hasAnchorInMatch) {
            foreach ($allUsersInMatch as $user) {
                $isAnchor = $user->is_anchor
                    || ($user->total_matches_has_anchor ?? 0) >= 10;

                if (!$isAnchor) {
                    $user->total_matches_has_anchor =
                        ($user->total_matches_has_anchor ?? 0) + 1;
                    $user->save();
                }
            }
        }

        // B. Tính toán S (Actual Score) & R (Average Rating)
        $scores = $match->results->groupBy('team_id')->map->sum('score');
        $t1Score = $scores->get($match->team1_id, 0);
        $t2Score = $scores->get($match->team2_id, 0);
        $totalScore = $t1Score + $t2Score;

        // S_match (thắng / thua)
        $winnerTeamId = $match->team_win_id;

        $S_match_t1 = $winnerTeamId === $match->team1_id ? 1.0 : 0.0;
        $S_match_t2 = $winnerTeamId === $match->team2_id ? 1.0 : 0.0;

        // S_points (tỷ lệ điểm)
        $S_points_t1 = $totalScore > 0 ? $t1Score / $totalScore : 0;
        $S_points_t2 = $totalScore > 0 ? $t2Score / $totalScore : 0;

        // S_final
        $S_t1 = (0.5 * $S_match_t1) + (0.5 * $S_points_t1);
        $S_t2 = (0.5 * $S_match_t2) + (0.5 * $S_points_t2);

        // =====================================================
        // D. TÍNH RATING TRUNG BÌNH (E)
        // =====================================================
        $calcAvgRating = function ($team) use ($sportId) {
            $ratings = $team->members->map(function ($member) use ($sportId) {
                $userSport = $member->user->sports->where('sport_id', $sportId)->first();
                if (!$userSport) return 0;
                $scoreRecord = $userSport->scores->where('score_type', 'vndupr_score')->first();
                return $scoreRecord ? (float)$scoreRecord->score_value : 0;
            });
            return $ratings->count() > 0 ? $ratings->avg() : 0;
        };

        $R_t1 = $calcAvgRating($match->team1);
        $R_t2 = $calcAvgRating($match->team2);

        $E_t1 = 1 / (1 + pow(10, ($R_t2 - $R_t1)));
        $E_t2 = 1 / (1 + pow(10, ($R_t1 - $R_t2)));

        // C. Cập nhật điểm cho từng Player
        $teamData = [
            ['team' => $match->team1, 'S' => $S_t1, 'E' => $E_t1],
            ['team' => $match->team2, 'S' => $S_t2, 'E' => $E_t2],
        ];

        $W = 0.2;

        foreach ($teamData as $data) {
            foreach ($data['team']->members as $member) {
                $user = $member->user;

                // 1. Cập nhật số trận
                $user->increment('total_matches');

                // 2. Lấy R_old từ relation
                $userSport = $user->sports->where('sport_id', $sportId)->first();
                $R_old = 0;
                if ($userSport) {
                    $scoreRecord = $userSport->scores->where('score_type', 'vndupr_score')->first();
                    $R_old = $scoreRecord ? (float)$scoreRecord->score_value : 0;
                }

                // 3. Tính K & Turbo
                $history = VnduprHistory::where('user_id', $user->id)->latest('id')->take(15)->get()->reverse();

                $K = 0.3;

                // 4. Chuẩn bị K theo total_matches
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
                    if (($history->first()->score_before - $history->last()->score_after) > 0.5) {
                        $K = 1;
                    }
                }

                // 5. Tính R_new
                // if ($hasAnchorInMatch) {
                //     $R_new = $R_old + ($W * $K * ($data['S'] - $data['E']));
                // } else {
                //     $R_new = $R_old;
                // }
                $R_new = $R_old + ($W * $K * ($data['S'] - $data['E']));

                // 6. Lưu History & Update Score
                VnduprHistory::create([
                    'user_id' => $user->id,
                    'mini_match_id' => $match->id,
                    'score_before' => $R_old,
                    'score_after' => $R_new,
                ]);

                if ($userSport) {
                    DB::table('user_sport_scores')->updateOrInsert(
                        ['user_sport_id' => $userSport->id, 'score_type' => 'vndupr_score'],
                        ['score_value' => $R_new, 'updated_at' => now()]
                    );
                }
            }
        }
    }

    /**
     * Trình lọc trận đấu (theo địa điểm, môn thể thao, từ khóa, thời gian, vị trí)
     */
    public function listMiniMatch(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'sometimes',
            'lng' => 'sometimes',
            'radius' => 'sometimes|numeric|min:1',
            'minLat' => self::VALIDATION_RULE,
            'maxLat' => self::VALIDATION_RULE,
            'minLng' => self::VALIDATION_RULE,
            'maxLng' => self::VALIDATION_RULE,
            'per_page' => 'sometimes|integer|min:1|max:200',
            'is_map' => 'sometimes|boolean',
            'location_id' => 'sometimes|integer|exists:locations,id',
            'sport_id' => 'sometimes|integer|exists:sports,id',
            'keyword' => 'sometimes|string|max:255',
            'rating' => 'sometimes',
            'rating.*' => 'integer',
            'slot_status' => 'sometimes|array',
            'slot_status.*' => 'in:one_slot,two_slot,full_slot',
            'type' => 'sometimes|array',
            'type.*' => 'in:single,double',
            'fee' => 'sometimes|array',
            'fee.*' => 'in:free,paid',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
        ]);

        $query = MiniMatch::withFullRelations()->filter($validated);

        $hasFilter = collect([
            'sport_id',
            'location_id',
            'keyword',
            'lat',
            'lng',
            'radius',
            'type',
            'rating',
            'fee',
            'min_price',
            'max_price',
            'slot_status'
        ])->some(fn($key) => $request->filled($key));

        if (!$hasFilter && (!empty($validated['minLat']) || !empty($validated['maxLat']) || !empty($validated['minLng']) || !empty($validated['maxLng']))) {
            $query->inBounds(
                $validated['minLat'],
                $validated['maxLat'],
                $validated['minLng'],
                $validated['maxLng']
            );
        }

        if (!empty($validated['lat']) && !empty($validated['lng']) && !empty($validated['radius'])) {
            $query->nearBy($validated['lat'], $validated['lng'], $validated['radius']);
        }

        $isMap = filter_var($validated['is_map'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isMap) {
            $matches = $query->get();
            $paginationMeta = [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $matches->count(),
                'total' => $matches->count(),
            ];
        } else {
            $matches = $query->paginate($validated['per_page'] ?? MiniMatch::PER_PAGE);
            $paginationMeta = [
                'current_page' => $matches->currentPage(),
                'last_page' => $matches->lastPage(),
                'per_page' => $matches->perPage(),
                'total' => $matches->total(),
            ];
        }

        return ResponseHelper::success(
            ['matches' => MiniMatchResource::collection($matches)],
            'Lấy danh sách Mini Match thành công',
            200,
            $paginationMeta
        );
    }

    private function generateMatchName(MiniTournament $miniTournament): string
    {
        $matchCount = MiniMatch::where('mini_tournament_id', $miniTournament->id)->count();
        return 'Trận ' . ($matchCount + 1) . ' kèo ' . $miniTournament->name;
    }

    private function processSets(MiniMatch $match, array $setsData): void
    {
        foreach ($setsData as $set) {
            MiniMatchResult::where('mini_match_id', $match->id)
                ->where('set_number', $set['set_number'])
                ->delete();

            foreach ($set['results'] as $res) {
                $teamId = $res['team'] === 'team1' ? $match->team1_id : $match->team2_id;
                MiniMatchResult::create([
                    'mini_match_id' => $match->id,
                    'team_id' => $teamId,
                    'score' => $res['score'],
                    'set_number' => $set['set_number'],
                    'won_set' => false,
                ]);
            }
        }

        $match->update([
            'team1_confirm' => false,
            'team2_confirm' => false,
        ]);
    }

    private function validateTeamFormat(MiniTournament $miniTournament, int $team1Count, int $team2Count)
    {
        if ($team1Count !== $team2Count) {
            return 'Số lượng người chơi của 2 đội phải bằng nhau';
        }

        switch ($miniTournament->format) {
            case MiniTournament::FORMAT_SINGLE:
                if ($team1Count !== 1) {
                    return 'Kèo này chỉ cho phép tạo trận 1v1';
                }
                break;
            case MiniTournament::FORMAT_DOUBLE:
            case MiniTournament::FORMAT_MENS_DOUBLES:
            case MiniTournament::FORMAT_WOMENS_DOUBLES:
            case MiniTournament::FORMAT_MIXED:
                if ($team1Count !== 2) {
                    return 'Kèo này chỉ cho phép tạo trận 2v2';
                }
                break;
            default:
                if (!in_array($team1Count, [1, 2])) {
                    return 'Chỉ cho phép tạo trận 1v1 hoặc 2v2';
                }
        }

        return null;
    }

    public function save(Request $request, $miniTournamentId)
    {
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $data = $request->validate([
            'match_id' => 'nullable|exists:mini_matches,id',
            'team1' => 'required|array|min:1',
            'team2' => 'required|array|min:1',
            'team1.*' => 'exists:users,id',
            'team2.*' => 'exists:users,id',
            'team1_name' => 'nullable|string|max:255',
            'team2_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'sets' => 'nullable|array',
            'sets.*.set_number' => 'required_with:sets|integer|min:1',
            'sets.*.results' => 'required_with:sets|array|size:2',
            'sets.*.results.*.team' => 'required_with:sets|in:team1,team2',
            'sets.*.results.*.score' => 'required_with:sets|integer|min:0',
        ]);

        $team1Count = count($data['team1']);
        $team2Count = count($data['team2']);

        $formatError = $this->validateTeamFormat($miniTournament, $team1Count, $team2Count);
        if ($formatError) {
            return ResponseHelper::error($formatError, 422);
        }

        $isUpdate = !empty($data['match_id']);

        DB::beginTransaction();

        try {
            if ($isUpdate) {
                $match = MiniMatch::withFullRelations()->findOrFail($data['match_id']);

                if ($match->mini_tournament_id !== $miniTournament->id) {
                    return ResponseHelper::error('Trận đấu không thuộc kèo đấu này', 422);
                }

                if (!$match->isEditable()) {
                    return ResponseHelper::error('Trận đấu đã được xác nhận, không thể sửa', 400);
                }

                if (isset($data['team1'])) {
                    $this->syncTeamMembers($match->team1, $data['team1']);
                }
                if (!empty($data['team1_name'])) {
                    $match->team1->update(['name' => $data['team1_name']]);
                }

                if (isset($data['team2'])) {
                    $this->syncTeamMembers($match->team2, $data['team2']);
                }
                if (!empty($data['team2_name'])) {
                    $match->team2->update(['name' => $data['team2_name']]);
                }

                $match->update([
                    'name' => $data['name'] ?? $match->name,
                ]);
            } else {
                $allUserIds = array_unique(array_merge($data['team1'], $data['team2']));

                $validParticipants = MiniParticipant::where('mini_tournament_id', $miniTournament->id)
                    ->where('is_confirmed', true)
                    ->whereIn('user_id', $allUserIds)
                    ->pluck('user_id')
                    ->toArray();

                if (count($validParticipants) !== count($allUserIds)) {
                    DB::rollBack();
                    return ResponseHelper::error('Có người chơi chưa tham gia hoặc chưa được duyệt trong kèo', 422);
                }

                $team1 = MiniTeam::create([
                    'mini_tournament_id' => $miniTournament->id,
                    'name' => $data['team1_name'] ?? 'Team 1',
                ]);
                foreach ($data['team1'] as $userId) {
                    $team1->members()->create(['user_id' => $userId]);
                }

                $team2 = MiniTeam::create([
                    'mini_tournament_id' => $miniTournament->id,
                    'name' => $data['team2_name'] ?? 'Team 2',
                ]);
                foreach ($data['team2'] as $userId) {
                    $team2->members()->create(['user_id' => $userId]);
                }

                $match = MiniMatch::create([
                    'mini_tournament_id' => $miniTournament->id,
                    'team1_id' => $team1->id,
                    'team2_id' => $team2->id,
                    'status' => MiniMatch::STATUS_PENDING,
                    'name' => $data['name'] ?? $this->generateMatchName($miniTournament),
                ]);
            }

            if (!empty($data['sets'])) {
                $this->processSets($match, $data['sets']);
            }

            DB::commit();

            $match = MiniMatch::withFullRelations()->findOrFail($match->id);

            if (!$isUpdate) {
                $allParticipantIds = array_unique(array_merge($data['team1'], $data['team2']));
                $users = User::whereIn('id', $allParticipantIds)->get();
                $users->each(function ($user) use ($match) {
                    $user->notify(new MiniMatchCreatedNotification($match));
                });
            }

            return ResponseHelper::success(
                new MiniMatchResource($match),
                $isUpdate ? 'Cập nhật trận đấu thành công' : 'Tạo trận đấu thành công',
                $isUpdate ? 200 : 201
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage());
        }
    }

    private function pushToUsers(array $userIds, string $title, string $body, array $data = [])
    {
        foreach ($userIds as $userId) {
            SendPushJob::dispatch($userId, $title, $body, $data);
        }
    }
}
