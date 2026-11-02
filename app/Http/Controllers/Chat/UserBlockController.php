<?php

namespace App\Http\Controllers\Chat;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Chat\UserBlock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserBlockController extends Controller
{
    /**
     * Lấy danh sách người dùng đã bị chặn.
     */
    public function index()
    {
        $currentUserId = Auth::id();

        $blocks = UserBlock::where('user_id', $currentUserId)
            ->with('blockedUser:id,full_name,avatar_url')
            ->latest('id')
            ->get();

        return ResponseHelper::success($blocks, 'Lấy danh sách chặn thành công');
    }

    /**
     * Chặn một người dùng.
     */
    public function block(Request $request, int $userId)
    {
        $currentUserId = Auth::id();

        if ($currentUserId === $userId) {
            return ResponseHelper::error('Không thể tự chặn chính mình', 400);
        }

        $targetUser = User::find($userId);
        if (!$targetUser) {
            return ResponseHelper::error('Người dùng không tồn tại', 404);
        }

        $block = UserBlock::firstOrCreate([
            'user_id' => $currentUserId,
            'blocked_user_id' => $userId,
        ], [
            'reason' => $request->input('reason'),
        ]);

        return ResponseHelper::success($block, 'Đã chặn người dùng thành công');
    }

    /**
     * Bỏ chặn một người dùng.
     */
    public function unblock(int $userId)
    {
        $currentUserId = Auth::id();

        $deleted = UserBlock::where('user_id', $currentUserId)
            ->where('blocked_user_id', $userId)
            ->delete();

        if (!$deleted) {
            return ResponseHelper::error('Người dùng này chưa bị chặn', 404);
        }

        return ResponseHelper::success(null, 'Đã bỏ chặn người dùng thành công');
    }
}
