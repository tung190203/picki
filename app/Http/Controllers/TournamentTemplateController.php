<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\TournamentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentTemplateController extends Controller
{
    /**
     * Lấy danh sách mẫu giải đấu của user hiện tại.
     */
    public function index(Request $request)
    {
        $templates = TournamentTemplate::where('user_id', Auth::id())
            ->orderByDesc('id')
            ->get();

        return ResponseHelper::success(
            ['templates' => $templates],
            'Lấy danh sách mẫu giải đấu thành công'
        );
    }

    /**
     * Lưu mẫu giải đấu mới.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'settings' => 'required|array',
        ]);

        $userId = auth()->id();

        $count = TournamentTemplate::where('user_id', $userId)->count();
        if ($count >= 10) {
            return ResponseHelper::error('Bạn đã đạt giới hạn 10 mẫu giải đấu', 400);
        }

        $template = TournamentTemplate::create([
            'user_id' => $userId,
            'name' => $validated['name'],
            'settings' => $validated['settings'],
        ]);

        return ResponseHelper::success(
            $template,
            'Lưu mẫu giải đấu thành công',
            201
        );
    }

    /**
     * Cập nhật mẫu giải đấu.
     * API: POST /api/tournament-templates/{id}
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'settings' => 'required|array',
        ]);

        $template = TournamentTemplate::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$template) {
            return ResponseHelper::error('Mẫu giải đấu không tồn tại', 404);
        }

        $template->update([
            'name' => $validated['name'],
            'settings' => $validated['settings'],
        ]);

        return ResponseHelper::success(
            $template->fresh(),
            'Cập nhật mẫu giải đấu thành công'
        );
    }

    /**
     * Xoá mẫu giải đấu.
     */
    public function destroy($id)
    {
        $template = TournamentTemplate::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$template) {
            return ResponseHelper::error('Mẫu giải đấu không tồn tại', 404);
        }

        $template->delete();

        return ResponseHelper::success(
            null,
            'Xoá mẫu giải đấu thành công'
        );
    }
}
