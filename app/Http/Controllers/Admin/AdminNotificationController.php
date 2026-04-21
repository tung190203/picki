<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    const DEFAULT_PER_PAGE = 15;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:all,unread,read',
            'per_page' => 'integer|min:1|max:200',
        ]);

        $user = auth()->user();
        $type = $validated['type'] ?? 'all';

        if ($type === 'unread') {
            $query = $user->unreadNotifications()->latest();
        } elseif ($type === 'read') {
            $query = $user->notifications()
                ->whereNotNull('read_at')
                ->latest();
        } else {
            $query = $user->notifications()->latest();
        }

        $notifications = $query->paginate(
            $validated['per_page'] ?? self::DEFAULT_PER_PAGE
        );

        $totalCount = $user->notifications()->count();
        $unreadCount = $user->unreadNotifications()->count();

        return ResponseHelper::success(
            ['notifications' => NotificationResource::collection($notifications)],
            'Lấy danh sách thông báo thành công',
            200,
            [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $totalCount,
                'unread_count' => $unreadCount,
                'last_page' => $notifications->lastPage(),
            ]
        );
    }
}
