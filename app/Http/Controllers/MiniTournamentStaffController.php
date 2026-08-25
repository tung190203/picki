<?php

namespace App\Http\Controllers;

use App\Events\SuperAdmin\MiniTournamentMemberAdded;
use App\Helpers\ResponseHelper;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MiniTournamentStaffController extends Controller
{
    public function addStaff(Request $request, $tournamentId)
    {
        $validatedData = $request->validate([
            'staff_id' => 'required|integer|exists:users,id',
        ]);

        $tournament = MiniTournament::findOrFail($tournamentId);

        $isOrganizer = $tournament->hasOrganizer(Auth::id());

        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền thêm người tổ chức', 403);
        }
        $staffId = $validatedData['staff_id'];
        $role = MiniTournamentStaff::ROLE_REFEREE;

        if ($tournament->staff()->where('user_id', $staffId)->exists()) {
            return ResponseHelper::error('Người dùng này đã là thành viên ban tổ chức của giải đấu.', 409);
        }

        $tournament->staff()->attach($staffId, [
            'role' => $role
        ]);

        $staffUser = User::find($staffId);
        $tournament->load('staff');
        MiniTournamentMemberAdded::dispatch(
            $tournament->id,
            $tournament->name,
            [
                'id' => $staffUser->id,
                'user' => [
                    'id' => $staffUser->id,
                    'full_name' => $staffUser->full_name,
                    'avatar_url' => $staffUser->avatar_url,
                ],
                'role' => $role,
            ],
            'staff'
        );

        $roleText = MiniTournamentStaff::getRoleText($role);
        return ResponseHelper::success(null, "Thêm trọng tài thành công", 201);
    }

    public function removeStaff($tournamentId, $staffId)
    {
        $tournament = MiniTournament::findOrFail($tournamentId);

        $isOrganizer = $tournament->hasOrganizer(Auth::id());

        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền xoá người tổ chức', 403);
        }

        $staff = MiniTournamentStaff::where('mini_tournament_id', $tournamentId)
            ->where('id', $staffId)
            ->first();

        if (!$staff) {
            return ResponseHelper::error('Không tìm thấy thành viên ban tổ chức', 404);
        }

        // Không cho phép xoá chính mình nếu là organizer cuối cùng
        if ((int) $staff->role === MiniTournamentStaff::ROLE_ORGANIZER) {
            $organizerCount = MiniTournamentStaff::where('mini_tournament_id', $tournamentId)
                ->where('role', MiniTournamentStaff::ROLE_ORGANIZER)
                ->count();

            if ($organizerCount <= 1 && (int) $staff->user_id === Auth::id()) {
                return ResponseHelper::error('Không thể xoá organizer cuối cùng của kèo đấu', 400);
            }
        }

        $staff->delete();

        $roleText = MiniTournamentStaff::getRoleText($staff->role);
        return ResponseHelper::success(null, "Xoá {$roleText} thành công");
    }
}
