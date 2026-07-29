<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\MiniTournamentTemplate;
use App\Support\TournamentSettingsNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MiniTournamentTemplateController extends Controller
{
    /**
     * Lấy danh sách kèo mẫu của user hiện tại.
     */
    public function index(Request $request)
    {
        $templates = MiniTournamentTemplate::where('user_id', Auth::id())
            ->orderByDesc('id')
            ->get();

        return ResponseHelper::success(
            ['templates' => $templates],
            'Lấy danh sách kèo mẫu thành công'
        );
    }

    /**
     * Lưu kèo mẫu mới.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'settings' => 'required|array',
        ]);

        $userId = auth()->id();

        //check limit 10 kèo mẫu
        $count = MiniTournamentTemplate::where('user_id', $userId)->count();
        if ($count >=10) {
            return ResponseHelper::error('Bạn đã đạt giới hạn 10 kèo mẫu', 400);
        }

        $template = MiniTournamentTemplate::create([
            'user_id' => $userId,
            'name' => $validated['name'],
            'settings' => TournamentSettingsNormalizer::normalizeMiniTournamentSettings($validated['settings']),
        ]);

        return ResponseHelper::success(
            $template,
            'Lưu kèo mẫu thành công',
            201
        );
    }

    /**
     * Cập nhật kèo mẫu.
     * API: POST /api/mini-tournament-templates/{id}
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'settings' => 'required|array',
        ]);

        $template = MiniTournamentTemplate::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$template) {
            return ResponseHelper::error('Kèo mẫu không tồn tại', 404);
        }

        $template->update([
            'name' => $validated['name'],
            'settings' => TournamentSettingsNormalizer::normalizeMiniTournamentSettings($validated['settings']),
        ]);

        return ResponseHelper::success(
            $template->fresh(),
            'Cập nhật kèo mẫu thành công'
        );
    }

    /**
     * Xoá kèo mẫu.
     */
    public function destroy($id)
    {
        $template = MiniTournamentTemplate::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$template) {
            return ResponseHelper::error('Kèo mẫu không tồn tại', 404);
        }

        $template->delete();

        return ResponseHelper::success(
            null,
            'Xoá kèo mẫu thành công'
        );
    }
}

