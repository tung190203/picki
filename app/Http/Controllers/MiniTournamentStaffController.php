<?php

namespace App\Http\Controllers;

use App\Events\SuperAdmin\MiniTournamentMemberAdded;
use App\Helpers\ResponseHelper;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
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
        if ($tournament->staff()->where('user_id', $staffId)->exists()) {
            return ResponseHelper::error('Người dùng này đã là người tổ chức của giải đấu.', 409);
        }

        $tournament->staff()->attach($staffId, [
            'role' => MiniTournamentStaff::ROLE_ORGANIZER
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
                'role' => MiniTournamentStaff::ROLE_ORGANIZER,
            ],
            'staff'
        );

        return ResponseHelper::success(['message' => 'Thêm người tổ chức thành công'], 201);
    }
}
