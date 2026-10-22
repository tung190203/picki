<?php

namespace App\Http\Controllers\Chat;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\Chat\InboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InboxController extends Controller
{
    public function __construct(
        protected InboxService $inboxService
    ) {
    }

    /**
     * Lấy danh sách Inbox hợp nhất theo tab (all, messages, alerts) và lọc chưa đọc.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'tab' => 'nullable|in:all,messages,alerts',
            'is_unread' => 'nullable',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $data = $this->inboxService->getInbox($user, $validated);

        return ResponseHelper::success($data, 'Lấy danh sách inbox thành công');
    }

    /**
     * Hàng đợi Tin nhắn từ người lạ (Strangers queue).
     */
    public function strangers(Request $request)
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $data = $this->inboxService->getStrangersQueue($user, $page, $perPage);

        return ResponseHelper::success($data, 'Lấy danh sách tin nhắn người lạ thành công');
    }

    /**
     * Đếm số lượng chưa đọc (total, messages, alerts, strangers).
     */
    public function unreadCount()
    {
        $user = Auth::user();
        $counts = $this->inboxService->getUnreadCounts($user);

        return ResponseHelper::success($counts, 'Lấy số lượng chưa đọc thành công');
    }

    /**
     * Tìm kiếm hợp nhất trong Inbox (Tin nhắn + Thông báo).
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $user = Auth::user();
        $query = $validated['q'];
        $perPage = (int) ($validated['per_page'] ?? 20);

        $results = $this->inboxService->search($user, $query, $perPage);

        return ResponseHelper::success($results, 'Tìm kiếm inbox thành công');
    }

    /**
     * Đánh dấu đã đọc tất cả theo tab.
     */
    public function markAllAsRead(Request $request)
    {
        $validated = $request->validate([
            'tab' => 'nullable|in:all,messages,strangers,alerts',
        ]);

        $user = Auth::user();
        $tab = $validated['tab'] ?? 'all';

        $this->inboxService->markAllAsRead($user, $tab);

        return ResponseHelper::success(null, 'Đánh dấu đã đọc thành công');
    }
}
