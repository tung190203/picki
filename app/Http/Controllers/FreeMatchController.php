<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\FreeMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FreeMatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $matches = FreeMatch::where('creator_user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return ResponseHelper::success($matches);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'rules' => 'required|array',
            'rules.points_to_win' => 'required|integer|in:11,15,21',
            'rules.win_by' => 'sometimes|integer|in:1,2',
            'rules.sets' => 'required|integer|in:1,3',
            'team_a' => 'required|array|min:1|max:2',
            'team_a.*.user_id' => 'nullable|integer|exists:users,id',
            'team_a.*.name' => 'required|string|max:100',
            'team_b' => 'required|array|min:1|max:2',
            'team_b.*.user_id' => 'nullable|integer|exists:users,id',
            'team_b.*.name' => 'required|string|max:100',
            'score' => 'nullable|array',
            'status' => 'sometimes|string|in:playing,done',
        ]);

        $freeMatch = FreeMatch::create([
            'creator_user_id' => $request->user()->id,
            'title' => $validated['title'] ?? null,
            'rules' => $validated['rules'],
            'team_a' => $validated['team_a'],
            'team_b' => $validated['team_b'],
            'score' => $validated['score'] ?? null,
            'status' => $validated['status'] ?? FreeMatch::STATUS_PLAYING,
        ]);

        return ResponseHelper::success($freeMatch, 'Tạo trận ngoài giải thành công', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $freeMatch = FreeMatch::findOrFail($id);
        if ((int) $freeMatch->creator_user_id !== (int) $request->user()->id) {
            return ResponseHelper::error('Bạn không có quyền cập nhật trận này.', 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'rules' => 'sometimes|array',
            'team_a' => 'sometimes|array|min:1|max:2',
            'team_b' => 'sometimes|array|min:1|max:2',
            'score' => 'nullable|array',
            'status' => 'sometimes|string|in:playing,done',
        ]);

        $freeMatch->update($validated);

        return ResponseHelper::success($freeMatch->fresh(), 'Cập nhật trận ngoài giải thành công');
    }
}
