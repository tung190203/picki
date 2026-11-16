<?php

namespace App\Http\Controllers\Chat;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Chat\ChatRecentSearch;
use App\Services\Chat\ChatRecentSearchService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatRecentSearchController extends Controller
{
    public function __construct(
        protected ChatRecentSearchService $recentSearchService
    ) {}

    /**
     * Lấy danh sách người dùng đã tìm kiếm gần đây để nhắn tin.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $limit = (int) ($validated['limit'] ?? ChatRecentSearch::MAX_RECENT_ITEMS);
        $items = $this->recentSearchService->getRecentSearches(Auth::user(), $limit);

        return ResponseHelper::success($items, 'Lấy danh sách tìm kiếm gần đây thành công');
    }

    /**
     * Lưu một người dùng vào danh sách tìm kiếm gần đây (hoặc đẩy lên đầu nếu đã tồn tại).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_user_id' => 'required_without:user_id|integer|exists:users,id',
            'user_id' => 'required_without:target_user_id|integer|exists:users,id',
        ]);

        $targetUserId = (int) ($validated['target_user_id'] ?? $validated['user_id']);

        try {
            $item = $this->recentSearchService->recordSearch(Auth::user(), $targetUserId);

            return ResponseHelper::success($item, 'Lưu tìm kiếm gần đây thành công');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    /**
     * Xóa một người dùng khỏi danh sách tìm kiếm gần đây.
     */
    public function destroy(int $targetUserId)
    {
        $deleted = $this->recentSearchService->removeSearch((int) Auth::id(), $targetUserId);

        if (!$deleted) {
            return ResponseHelper::error('Không tìm thấy mục tìm kiếm gần đây', 404);
        }

        return ResponseHelper::success(null, 'Đã xóa khỏi tìm kiếm gần đây');
    }

    /**
     * Xóa toàn bộ danh sách tìm kiếm gần đây.
     */
    public function clear()
    {
        $deletedCount = $this->recentSearchService->clearAll((int) Auth::id());

        return ResponseHelper::success(
            ['deleted_count' => $deletedCount],
            'Đã xóa toàn bộ tìm kiếm gần đây'
        );
    }

    /**
     * Tìm kiếm người dùng để nhắn tin (nếu không nhập từ khóa sẽ trả về danh sách tìm kiếm gần đây).
     */
    public function searchUsers(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
            'keyword' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $q = (string) ($validated['q'] ?? $validated['search'] ?? $validated['keyword'] ?? '');
        $limit = (int) ($validated['limit'] ?? 20);

        $items = $this->recentSearchService->searchUsersForChat(Auth::user(), $q, $limit);

        return ResponseHelper::success($items, 'Tìm kiếm người dùng thành công');
    }
}

