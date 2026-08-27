<?php

namespace App\Http\Controllers;

use App\Events\SuperAdmin\TournamentMemberAdded;
use App\Helpers\ResponseHelper;
use App\Http\Resources\TournamentStaffResource;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use App\Services\Permission\TournamentPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentStaffController extends Controller
{
    /**
     * Thêm thành viên vào giải đấu (Admin / BTC / Trọng tài).
     *
     * Body: { user_id, role?: 1|2|3, court_id?: int }
     *  - role = 1 (Admin / Organizer) → Admin
     *  - role = 2 (Staff)            → BTC
     *  - role = 3 (Referee)          → Trọng tài
     *  - court_id chỉ có hiệu lực khi role = 3 (giới hạn scope trọng tài theo sân).
     *
     * Quyền: Chỉ Admin (organizer) của giải mới được thêm.
     * 1 user chỉ giữ tối đa 1 role / giải.
     */
    public function addStaff(Request $request, $tournamentId)
    {
        $validatedData = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'nullable|integer|in:1,2,3',
            'court_id' => 'nullable|integer',
        ]);

        $tournament = Tournament::findOrFail($tournamentId);
        $role = (int) ($validatedData['role'] ?? TournamentStaff::ROLE_ORGANIZER);

        if (!TournamentPermission::canAssignRole($tournament, Auth::id(), $role)) {
            return ResponseHelper::error('Bạn không có quyền thêm thành viên vào ban tổ chức', 403);
        }

        $userId = $validatedData['user_id'];

        // 1 user chỉ giữ 1 role / giải
        if ($tournament->staff()->where('user_id', $userId)->exists()) {
            return ResponseHelper::error(
                'Người dùng đã là thành viên ban tổ chức của giải đấu',
                409
            );
        }

        // court_id chỉ áp dụng cho role REFEREE
        $courtId = null;
        if ($role === TournamentStaff::ROLE_REFEREE && isset($validatedData['court_id'])) {
            $courtId = (int) $validatedData['court_id'];
        }

        $tournament->staff()->attach($userId, [
            'role' => $role,
            'court_id' => $courtId,
        ]);

        $staffUser = User::find($userId);
        $tournament->load('staff');

        TournamentMemberAdded::dispatch(
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
                'court_id' => $courtId,
            ],
            'staff'
        );

        $roleText = TournamentStaff::ROLES ? match ($role) {
            TournamentStaff::ROLE_ORGANIZER => 'người tổ chức',
            TournamentStaff::ROLE_STAFF => 'BTC',
            TournamentStaff::ROLE_REFEREE => 'trọng tài',
            default => 'thành viên',
        } : 'thành viên';

        return ResponseHelper::success(null, "Thêm {$roleText} thành công", 201);
    }

    /**
     * Backward-compat endpoint: chỉ thêm Trọng tài (mặc định role = REFEREE, court_id optional).
     * Body: { user_id, court_id?: int }
     */
    public function addReferee(Request $request, $tournamentId)
    {
        $validatedData = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'court_id' => 'nullable|integer',
        ]);

        $tournament = Tournament::findOrFail($tournamentId);
        if (!TournamentPermission::canAssignRole($tournament, Auth::id(), TournamentStaff::ROLE_REFEREE)) {
            return ResponseHelper::error('Bạn không có quyền thêm trọng tài', 403);
        }

        $userId = $validatedData['user_id'];
        if ($tournament->staff()->where('user_id', $userId)->exists()) {
            return ResponseHelper::error('Người dùng này đã là thành viên ban tổ chức của giải đấu', 409);
        }

        $courtId = isset($validatedData['court_id']) ? (int) $validatedData['court_id'] : null;

        $tournament->staff()->attach($userId, [
            'role' => TournamentStaff::ROLE_REFEREE,
            'court_id' => $courtId,
        ]);

        return ResponseHelper::success(null, 'Thêm trọng tài thành công', 201);
    }

    /**
     * Xoá thành viên khỏi giải.
     *
     * Body: { tournament_staff_id: int }
     *
     * Quyền (matrix đồng bộ với mini-tournament):
     *  - Admin (organizer): xoá mọi role (Admin / BTC / Trọng tài)
     *  - BTC (staff): chỉ xoá được Trọng tài
     *  - Không có guard "organizer cuối cùng" — 1 giải có thể có nhiều Admin.
     */
    public function removeStaff(Request $request, $tournamentId)
    {
        $validatedData = $request->validate([
            'tournament_staff_id' => 'required|integer|exists:tournament_staff,id',
        ]);

        $tournament = Tournament::findOrFail($tournamentId);

        $staff = TournamentStaff::where('id', $validatedData['tournament_staff_id'])
            ->where('tournament_id', $tournamentId)
            ->first();

        if (!$staff) {
            return ResponseHelper::error('Không tìm thấy thành viên ban tổ chức', 404);
        }

        // Permission: caller phải có quyền thu hồi role này
        if (!TournamentPermission::canRevokeRole($tournament, Auth::id(), (int) $staff->role)) {
            return ResponseHelper::error('Bạn không có quyền xoá thành viên với vai trò này', 403);
        }

        $staff->delete();

        $roleText = (new TournamentStaff(['role' => $staff->role]))->role_text;
        return ResponseHelper::success(null, "Xoá {$roleText} thành công");
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

        $this->syncParticipantAttendanceFromStaff($tournamentStaff);

        return ResponseHelper::success(
            new TournamentStaffResource($tournamentStaff),
            'Đã đánh dấu check-in thành công'
        );
    }

    /**
     * Đánh dấu check-in nhiều staff cùng lúc.
     * Body: { staff_ids: int[] }
     */
    public function markCheckInAll(Request $request, int $id)
    {
        $validated = $request->validate([
            'staff_ids' => 'required|array|min:1',
            'staff_ids.*' => 'integer',
        ]);

        $staffIds = $validated['staff_ids'];

        $tournament = Tournament::findOrFail($id);
        if (!$tournament->hasScoringPermission(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $staffMembers = TournamentStaff::where('tournament_id', $id)
            ->whereIn('id', $staffIds)
            ->get();

        if ($staffMembers->isEmpty()) {
            return ResponseHelper::error('Không tìm thấy thành viên ban tổ chức nào trong danh sách', 404);
        }

        $updatedCount = 0;
        $skippedIds = [];

        foreach ($staffMembers as $staff) {
            if ($staff->checked_in_at) {
                $skippedIds[] = $staff->id;
                continue;
            }

            $staff->update([
                'checked_in_at' => now(),
                'is_absent' => false,
            ]);

            $this->syncParticipantAttendanceFromStaff($staff);
            $updatedCount++;
        }

        return ResponseHelper::success([
            'updated_count' => $updatedCount,
            'skipped_count' => count($skippedIds),
            'skipped_ids' => $skippedIds,
        ], "Đã đánh dấu check-in cho {$updatedCount} thành viên ban tổ chức");
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

        $this->syncParticipantAbsentFromStaff($tournamentStaff);

        return ResponseHelper::success(
            new TournamentStaffResource($tournamentStaff),
            'Đã đánh dấu vắng mặt thành công'
        );
    }

    /**
     * Sau khi cập nhật check-in ở tournament_staff, đồng thời cập nhật
     * bản ghi participants cùng user_id — nếu chưa có trạng thái.
     */
    private function syncParticipantAttendanceFromStaff(TournamentStaff $staff): void
    {
        $participant = Participant::where('tournament_id', $staff->tournament_id)
            ->where('user_id', $staff->user_id)
            ->first();

        if (!$participant || $participant->checked_in_at || $participant->is_absent) {
            return;
        }

        $participant->update([
            'is_confirmed' => true,
            'checked_in_at' => $staff->checked_in_at,
            'is_absent' => false,
        ]);
    }

    /**
     * Sau khi báo vắng ở tournament_staff, đồng thời báo vắng ở
     * bảng participants cùng user_id — nếu chưa có trạng thái.
     */
    private function syncParticipantAbsentFromStaff(TournamentStaff $staff): void
    {
        $participant = Participant::where('tournament_id', $staff->tournament_id)
            ->where('user_id', $staff->user_id)
            ->first();

        if (!$participant || $participant->checked_in_at || $participant->is_absent) {
            return;
        }

        $participant->update(['is_absent' => true]);
    }
}