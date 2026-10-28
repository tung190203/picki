<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Notifications\ClubNotificationSentNotification;
use App\Services\Club\ClubNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected ClubNotificationService $clubNotificationService
    ) {
    }
    const DEFAULT_PER_PAGE = 15;
    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:all,unread,read',
            'q' => 'nullable|string',
            'per_page' => 'integer|min:1|max:200',
        ]);

        /** @var User $user */
        $user = auth()->user();
        $type = $validated['type'] ?? 'all';
        $keyword = isset($validated['q']) ? trim((string) $validated['q']) : '';

        if ($type === 'unread') {
            $query = $user->unreadNotifications()->latest();
        } elseif ($type === 'read') {
            $query = $user->notifications()
                ->whereNotNull('read_at')
                ->latest();
        } else {
            $query = $user->notifications()->latest();
        }

        if ($keyword !== '') {
            $jsonEscaped = substr((string) json_encode($keyword), 1, -1);
            $sqlEscapedJson = str_replace('\\', '\\\\', $jsonEscaped);

            $query->where(function ($sub) use ($keyword, $jsonEscaped, $sqlEscapedJson) {
                $sub->where('data', 'like', "%{$keyword}%");
                if ($jsonEscaped !== '' && $jsonEscaped !== $keyword) {
                    $sub->orWhere('data', 'like', "%{$sqlEscapedJson}%");
                }
            });
        }

        $notifications = $query->paginate(
            $validated['per_page'] ?? self::DEFAULT_PER_PAGE
        );

        // ✅ GLOBAL COUNT (KHÔNG PHỤ THUỘC FILTER)
        $totalCount = $user->notifications()->count();
        $unreadCount = $user->unreadNotifications()->count();
        $readCount = $totalCount - $unreadCount;

        return ResponseHelper::success([
            'notifications' => NotificationResource::collection($notifications),
        ], 'Lấy danh sách thông báo thành công', 200, [
            'current_page' => $notifications->currentPage(),
            'per_page' => $notifications->perPage(),
            'total'        => $keyword !== '' ? $notifications->total() : $totalCount,
            'unread_count' => $unreadCount,
            'read_count'   => $readCount,
            'last_page'    => $notifications->lastPage(),
        ]);
    }

    public function markAsRead(Request $request)
    {
        $validated = $request->validate([
            'notification_id' => 'nullable|exists:notifications,id',
        ]);

        /** @var User $user */
        $user = auth()->user();
        if (!empty($validated['notification_id'])) {
            $notification = $user->notifications()
                ->where('id', $validated['notification_id'])
                ->first();

            if ($notification && $notification->read_at === null) {
                $notification->markAsRead();

                if ($notification->type === ClubNotificationSentNotification::class) {
                    $clubNotificationId = $notification->data['club_notification_id'] ?? null;
                    if ($clubNotificationId) {
                        $this->clubNotificationService->syncClubRecipientRead(
                            (int) $clubNotificationId,
                            $user->id
                        );
                    }
                }
            }
        } else {
            $unreadList = $user->unreadNotifications;
            foreach ($unreadList as $n) {
                if ($n->type === ClubNotificationSentNotification::class) {
                    $clubNotificationId = $n->data['club_notification_id'] ?? null;
                    if ($clubNotificationId) {
                        $this->clubNotificationService->syncClubRecipientRead(
                            (int) $clubNotificationId,
                            $user->id
                        );
                    }
                }
            }
            $user->unreadNotifications->markAsRead();
        }

        return ResponseHelper::success(null, 'Đánh dấu thông báo đã đọc thành công');
    }

    public function delete(Request $request)
    {
        $validated = $request->validate([
            'notification_id' => 'nullable|exists:notifications,id',
        ]);

        /** @var User $user */
        $user = auth()->user();
        if (!empty($validated['notification_id'])) {
            $notification = $user->notifications()
                ->where('id', $validated['notification_id'])
                ->first();

            if ($notification) {
                $notification->delete();
            }
        } else {
            $user->notifications()->delete();
        }

        return ResponseHelper::success(null, 'Xóa thông báo thành công');
    }
}
