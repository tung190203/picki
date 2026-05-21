<?php

namespace App\Http\Controllers;

use App\Events\QuickMatchConfirmed;
use App\Helpers\ResponseHelper;
use App\Http\Resources\CompetitionLocationResource;
use App\Http\Resources\QuickMatchResource;
use App\Models\MatchHistory;
use App\Models\QuickMatch;
use App\Notifications\QuickMatchInvitationNotification;
use App\Services\ImageOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuickMatchController extends Controller
{
    public function __construct(protected ImageOptimizationService $imageService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'avatar_url' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'note' => 'nullable|string|max:1000',
            'match_type' => ['nullable', Rule::in([QuickMatch::MATCH_TYPE_RANK, QuickMatch::MATCH_TYPE_CASUAL])],
            'team_a' => 'required|array|min:1|max:2',
            'team_a.*' => 'integer|exists:users,id',
            'team_b' => 'required|array|min:1|max:2',
            'team_b.*' => 'integer|exists:users,id',
            'scheduled_at' => 'nullable|date',
            'competition_location_id' => 'nullable|integer|exists:competition_locations,id',
            'is_referee_scoring' => 'nullable|boolean',
            'score' => 'required|array',
            'score.team_a' => 'required|array',
            'score.team_a.*' => 'integer|min:0',
            'score.team_b' => 'required|array',
            'score.team_b.*' => 'integer|min:0',
        ]);

        $creator = Auth::user();
        $isSuperAdmin = (bool) ($creator->is_super_admin ?? false);
        $isRefereeScoring = (bool) ($validated['is_referee_scoring'] ?? false);

        $matchType = $validated['match_type'] ?? QuickMatch::MATCH_TYPE_RANK;

        $avatarPath = null;
        if ($request->hasFile('avatar_url')) {
            $avatarPath = $this->imageService->optimizeThumbnail(
                $request->file('avatar_url'),
                'quick-matches/avatars',
                80
            );
        }

        $quickMatch = DB::transaction(function () use ($validated, $creator, $isSuperAdmin, $isRefereeScoring, $matchType, $avatarPath) {
            $score = $validated['score'] ?? null;
            $winner = $score ? (new QuickMatch())->determineWinner($score) : null;
            $status = $isSuperAdmin || $isRefereeScoring
                ? QuickMatch::STATUS_COMPLETED
                : QuickMatch::STATUS_PENDING;

            $data = [
                'name' => $validated['name'] ?? null,
                'avatar_url' => $avatarPath,
                'note' => $validated['note'] ?? null,
                'team_a' => $validated['team_a'],
                'team_b' => $validated['team_b'],
                'match_type' => $matchType,
                'status' => $status,
                'qr_code' => $status === QuickMatch::STATUS_PENDING ? Str::random(32) : null,
                'score' => $score,
                'winner' => $winner,
                'created_by' => $creator->id,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'competition_location_id' => $validated['competition_location_id'] ?? null,
                'is_referee_scoring' => $isRefereeScoring,
            ];

            $quickMatch = QuickMatch::create($data);

            if ($isSuperAdmin) {
                $this->saveMatchHistories($quickMatch); // tất cả players
                Broadcast::event(new QuickMatchConfirmed($quickMatch));
            } elseif ($isRefereeScoring) {
                // Luồng trọng tài nhập điểm: chỉ lưu vào profile thằng user request
                $this->saveMatchHistories($quickMatch, [$creator->id]);
                Broadcast::event(new QuickMatchConfirmed($quickMatch));
            } else {
                // Gửi notification cho team B
                $this->sendInvitationNotifications($quickMatch, $creator->id);
            }

            return $quickMatch;
        });

        $quickMatch->load('creator', 'competitionLocation');

        return ResponseHelper::success(
            new QuickMatchResource($quickMatch),
            $isSuperAdmin || $isRefereeScoring ? 'Tạo trận đấu nhanh thành công (đã xác nhận)' : 'Tạo trận đấu nhanh thành công',
        );
    }

    public function show(int $id): JsonResponse
    {
        $quickMatch = QuickMatch::with('creator')->find($id);

        if (!$quickMatch) {
            return ResponseHelper::error('Không tìm thấy trận đấu.', 404);
        }

        return ResponseHelper::success(new QuickMatchResource($quickMatch));
    }

    public function scanQr(string $qrCode): JsonResponse
    {
        $quickMatch = QuickMatch::with('competitionLocation')->where('qr_code', $qrCode)->first();

        if (!$quickMatch) {
            return ResponseHelper::error('Không tìm thấy trận đấu với mã này.', 404);
        }

        return ResponseHelper::success([
            'quick_match_id' => $quickMatch->id,
            'match_name' => $quickMatch->name,
            'match_type' => $quickMatch->match_type,
            'status' => $quickMatch->status,
            'competition_location' => $quickMatch->competitionLocation
                ? new CompetitionLocationResource($quickMatch->competitionLocation)
                : null,
        ]);
    }

    public function confirmViaQr(Request $request, string $qrCode): JsonResponse
    {
        $user = Auth::user();
        $userId = $user->id;

        $quickMatch = QuickMatch::where('qr_code', $qrCode)->first();

        if (!$quickMatch) {
            return ResponseHelper::error('Không tìm thấy trận đấu với mã này.', 404);
        }

        $teamBUserIds = $quickMatch->team_b ?? [];

        if (!in_array($userId, $teamBUserIds)) {
            return ResponseHelper::error('Bạn không thuộc trận đấu này và không có quyền xác nhận.', 403);
        }

        if ($quickMatch->status === QuickMatch::STATUS_COMPLETED) {
            return ResponseHelper::error('Trận đấu đã hoàn tất, không thể xác nhận.', 400);
        }

        DB::transaction(function () use ($quickMatch) {
            $quickMatch->update([
                'status' => QuickMatch::STATUS_COMPLETED,
                'confirmed_at' => now(),
            ]);
            $this->saveMatchHistories($quickMatch);
        });

        $quickMatch->load('creator', 'competitionLocation');

        Broadcast::event(new QuickMatchConfirmed($quickMatch));

        return ResponseHelper::success(
            new QuickMatchResource($quickMatch),
            'Xác nhận trận đấu thành công.'
        );
    }

    public function updateScore(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'score' => 'required|array',
            'score.team_a' => 'required|array',
            'score.team_a.*' => 'integer|min:0',
            'score.team_b' => 'required|array',
            'score.team_b.*' => 'integer|min:0',
        ]);

        $userId = Auth::id();

        $quickMatch = QuickMatch::with('creator')->find($id);

        if (!$quickMatch) {
            return ResponseHelper::error('Không tìm thấy trận đấu.', 404);
        }

        if (!$quickMatch->isPlayerInMatch($userId)) {
            return ResponseHelper::error('Bạn không có quyền cập nhật điểm trận đấu này.', 403);
        }

        if ($quickMatch->status === QuickMatch::STATUS_COMPLETED) {
            return ResponseHelper::error('Trận đấu đã hoàn tất, không thể cập nhật điểm.', 400);
        }

        $score = $validated['score'];

        DB::transaction(function () use ($quickMatch, $score) {
            $quickMatch->update(['score' => $score]);

            // Khi có score mới từ trạng thái pending -> confirmed, chuyển sang completed luôn
            $winner = $quickMatch->determineWinner($score);
            $quickMatch->update([
                'status' => QuickMatch::STATUS_COMPLETED,
                'winner' => $winner,
            ]);
        });

        $quickMatch->refresh();
        $quickMatch->load('creator', 'competitionLocation');

        Broadcast::event(new QuickMatchConfirmed($quickMatch));

        return ResponseHelper::success(
            new QuickMatchResource($quickMatch),
            'Cập nhật điểm thành công.'
        );
    }

    private function saveMatchHistories(QuickMatch $quickMatch, ?array $targetUserIds = null): void
    {
        $userIds = $targetUserIds ?? $quickMatch->allPlayerIds();
        $playedAt = now();

        foreach ($userIds as $userId) {
            $teamSide = $quickMatch->isPlayerInTeamA($userId) ? 'team_a' : 'team_b';

            MatchHistory::updateOrCreate(
                [
                    'user_id' => $userId,
                    'quick_match_id' => $quickMatch->id,
                ],
                [
                    'team_side' => $teamSide,
                    'played_at' => $playedAt,
                ]
            );
        }
    }

    private function sendInvitationNotifications(QuickMatch $quickMatch, int $invitedBy): void
    {
        $teamBUserIds = $quickMatch->team_b ?? [];

        foreach ($teamBUserIds as $userId) {
            $user = \App\Models\User::find($userId);
            if ($user) {
                $user->notify(new QuickMatchInvitationNotification($quickMatch, $invitedBy));
            }
        }
    }
}
