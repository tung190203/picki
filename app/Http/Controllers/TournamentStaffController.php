<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\TournamentStaffResource;
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

    /**
     * Input: route id = tournaments.id, staffId = tournament_staff.id.
     * Output: JSON success + TournamentStaffResource.
     */
    public function markStaffCheckIn(int $id, int $staffId)
    {
        $tournamentStaff = TournamentStaff::where('id', $staffId)
            ->where('tournament_id', $id)
            ->first();

        if (!$tournamentStaff) {
            return ResponseHelper::error('Không tìm thấy thành viên ban tổ chức hoặc trọng tài', 404);
        }

        $tournament = Tournament::findOrFail($id);
        if (!$tournament->hasScoringPermission(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền thực hiện thao tác này', 403);
        }

        if ($tournamentStaff->checked_in_at) {
            return ResponseHelper::error('Thành viên đã check-in rồi. Không thể check-in lại.', 422);
        }

        $tournamentStaff->update([
            'checked_in_at' => now(),
            'is_absent' => false,
        ]);

        $tournamentStaff->load('user');

        return ResponseHelper::success(
            new TournamentStaffResource($tournamentStaff),
            'Đã đánh dấu check-in thành công'
        );
    }

    /**
     * Input: route id = tournaments.id, staffId = tournament_staff.id.
     * Output: JSON success + TournamentStaffResource.
     */
    public function markStaffAbsent(int $id, int $staffId)
    {
        $tournamentStaff = TournamentStaff::where('id', $staffId)
            ->where('tournament_id', $id)
            ->first();

        if (!$tournamentStaff) {
            return ResponseHelper::error('Không tìm thấy thành viên ban tổ chức hoặc trọng tài', 404);
        }

        $tournament = Tournament::findOrFail($id);
        if (!$tournament->hasScoringPermission(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền thực hiện thao tác này', 403);
        }

        if ($tournamentStaff->is_absent) {
            return ResponseHelper::error('Thành viên đã được đánh dấu vắng mặt rồi', 422);
        }

        if ($tournamentStaff->checked_in_at) {
            return ResponseHelper::error('Thành viên đã check-in. Không thể đánh dấu vắng mặt.', 422);
        }

        $tournamentStaff->update([
            'is_absent' => true,
        ]);

        $tournamentStaff->load('user');

        return ResponseHelper::success(
            new TournamentStaffResource($tournamentStaff),
            'Đã đánh dấu vắng mặt thành công'
        );
    }
}
