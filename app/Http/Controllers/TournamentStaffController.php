<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentStaffController extends Controller
{
    public function addStaff(Request $request, $tournamentId)
    {
        $validatedData = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $tournament = Tournament::findOrFail($tournamentId);
        $isOrganizer = $tournament->hasOrganizer(Auth::id());

        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền thêm người vào ban tổ chức', 403);
        }

        $userId = $validatedData['user_id'];
        if ($tournament->staff()->where('user_id', $userId)->exists()) {
            return ResponseHelper::error('Người dùng này đã là thành viên ban tổ chức của giải đấu', 409);
        }

        $tournament->staff()->attach($userId, [
            'role' => TournamentStaff::ROLE_ORGANIZER,
            'is_invite_by_organizer' => true
        ]);

        return ResponseHelper::success(null, 'Thêm người vào ban tổ chức thành công', 201);
    }

    public function addReferee(Request $request, $tournamentId)
    {
        $validatedData = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $tournament = Tournament::findOrFail($tournamentId);
        $isOrganizer = $tournament->hasOrganizer(Auth::id());

        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền thêm trọng tài', 403);
        }

        $userId = $validatedData['user_id'];
        if ($tournament->staff()->where('user_id', $userId)->exists()) {
            return ResponseHelper::error('Người dùng này đã là thành viên ban tổ chức của giải đấu', 409);
        }

        $tournament->staff()->attach($userId, [
            'role' => TournamentStaff::ROLE_REFEREE,
            'is_invite_by_organizer' => true
        ]);

        return ResponseHelper::success(null, 'Thêm trọng tài thành công', 201);
    }

    public function removeStaff(Request $request, $tournamentId)
    {
        $validatedData = $request->validate([
            'tournament_staff_id' => 'required|integer|exists:tournament_staff,id',
        ]);

        $tournament = Tournament::findOrFail($tournamentId);
        if (!$tournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền xóa người trong ban tổ chức', 403);
        }

        $tournamentStaff = TournamentStaff::where('id', $validatedData['tournament_staff_id'])
            ->where('tournament_id', $tournamentId)
            ->first();

        if (!$tournamentStaff) {
            return ResponseHelper::error('Không tìm thấy thành viên ban tổ chức', 404);
        }

        $tournamentStaff->delete();

        return response()->noContent();
    }
}
