<?php

namespace App\Http\Controllers;

use App\Events\SuperAdmin\MiniTournamentMemberAdded;
use App\Helpers\ResponseHelper;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\User;
use App\Services\Permission\MiniTournamentPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MiniTournamentStaffController extends Controller
{
    /**
     * Thêm thành viên vào kèo (Admin / BTC / Trọng tài).
     *
     * Body: { staff_id, role: 1|2|3 }
     *  - role = 1 (Admin)         → Admin (organizer)
     *  - role = 2 (BTC / Staff)   → BTC
     *  - role = 3 (Referee)       → Trọng tài
     *
     * Quyền:
     *  - Admin: được add với mọi role
     *  - BTC:   chỉ được add role = 3 (Trọng tài)
     *  - Trọng tài: ❌
     *
     * 1 user chỉ giữ tối đa 1 role / kèo.
     */
    public function addStaff(Request $request, $tournamentId)
    {
        $validatedData = $request->validate([
            'staff_id' => 'required|integer|exists:users,id',
            'role' => 'required|integer|in:1,2,3',
        ]);

        $tournament = MiniTournament::findOrFail($tournamentId);

        // Permission: caller phải có quyền gán role này
        if (!MiniTournamentPermission::canAssignRole($tournament, Auth::id(), (int) $validatedData['role'])) {
            return ResponseHelper::error('Bạn không có quyền gán vai trò này cho kèo đấu', 403);
        }

        $staffId = $validatedData['staff_id'];

        // 1 user chỉ giữ 1 role / kèo (theo quyết định với Thắng)
        if ($tournament->staff()->where('user_id', $staffId)->exists()) {
            return ResponseHelper::error(
                'Người dùng đã là thành viên của kèo (mỗi user chỉ giữ 1 role)',
                409
            );
        }

        $role = (int) $validatedData['role'];

        $tournament->staff()->attach($staffId, [
            'role' => $role,
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

    /**
     * Xoá thành viên khỏi kèo.
     *
     * Quyền: Admin hoặc BTC (BTC chỉ xoá được Trọng tài).
     * Theo plan v2: KHÔNG có guard "organizer cuối cùng" — 1 kèo có thể có nhiều Admin.
     */
    public function removeStaff($tournamentId, $staffId)
    {
        $tournament = MiniTournament::findOrFail($tournamentId);

        $staff = MiniTournamentStaff::where('mini_tournament_id', $tournamentId)
            ->where('id', $staffId)
            ->first();

        if (!$staff) {
            return ResponseHelper::error('Không tìm thấy thành viên ban tổ chức', 404);
        }

        // Permission: caller phải có quyền thu hồi role này
        if (!MiniTournamentPermission::canRevokeRole($tournament, Auth::id(), (int) $staff->role)) {
            return ResponseHelper::error('Bạn không có quyền xoá thành viên với vai trò này', 403);
        }

        $staff->delete();

        $roleText = MiniTournamentStaff::getRoleText($staff->role);
        return ResponseHelper::success(null, "Xoá {$roleText} thành công");
    }
}