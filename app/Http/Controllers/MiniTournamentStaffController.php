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
            'role' => 'sometimes|integer|in:' . MiniTournamentStaff::ROLE_ORGANIZER . ',' . MiniTournamentStaff::ROLE_REFEREE,
        ]);

        $tournament = MiniTournament::findOrFail($tournamentId);

        $isOrganizer = $tournament->hasOrganizer(Auth::id());

        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền thêm người tổ chức', 403);
        }
        $staffId = $validatedData['staff_id'];
        $role = $validatedData['role'] ?? MiniTournamentStaff::ROLE_ORGANIZER;

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
        return ResponseHelper::success(null, "Thêm {$roleText} thành công", 201);
    }
}
