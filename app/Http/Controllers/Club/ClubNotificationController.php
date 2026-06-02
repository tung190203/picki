<?php

namespace App\Http\Controllers\Club;

use App\Enums\ClubMemberRole;
use App\Exceptions\BusinessException;
use App\Enums\ClubNotificationPriority;
use App\Enums\ClubNotificationStatus;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Club\ClubNotificationResource;
use App\Models\Club\Club;
use App\Models\Club\ClubNotification;
use App\Models\Club\ClubNotificationType;
use App\Services\Club\ClubNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClubNotificationController extends Controller
{
    private const MAX_PINNED_NOTIFICATIONS = 3;

    public function __construct(
        protected ClubNotificationService $notificationService
    ) {
    }

    public function index(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if ($request->has('is_pinned')) {
            $request->merge([
                'is_pinned' => filter_var($request->input('is_pinned'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'is_pinned' => 'sometimes|boolean',
            'status' => ['sometimes', Rule::enum(ClubNotificationStatus::class)],
            'type_id' => 'sometimes|exists:club_notification_types,id',
        ]);

        $member = $userId ? $club->activeMembers()->where('user_id', $userId)->first() : null;
        $canManage = $member && in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary]);

        $notifications = $this->notificationService->getNotifications($club, $userId, $validated, $canManage);
        $unreadCount = $this->notificationService->getUnreadCount($club, $userId);

        $data = ['notifications' => ClubNotificationResource::collection($notifications)];
        $meta = [
            'current_page' => $notifications->currentPage(),
            'per_page' => $notifications->perPage(),
            'total' => $notifications->total(),
            'last_page' => $notifications->lastPage(),
            'unread_count' => $unreadCount,
        ];
        return ResponseHelper::success($data, 'Lấy danh sách thông báo thành công', 200, $meta);
    }

    public function store(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền tạo thông báo', 403);
        }

        if ($request->has('is_pinned')) {
            $request->merge([
                'is_pinned' => filter_var($request->input('is_pinned'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }

        $validated = $request->validate([
            'club_notification_type_id' => 'required|exists:club_notification_types,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'attachment_url' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240',
            'priority' => ['sometimes', Rule::enum(ClubNotificationPriority::class)],
            'status' => ['sometimes', Rule::enum(ClubNotificationStatus::class)],
            'metadata' => 'nullable|array',
            'is_pinned' => 'sometimes|boolean',
            'scheduled_at' => 'required_if:status,scheduled|nullable|date|after:now',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $isPinned = $validated['is_pinned'] ?? false;
        if ($isPinned) {
            $pinnedCount = $club->notifications()->where('is_pinned', true)->count();
            if ($pinnedCount >= self::MAX_PINNED_NOTIFICATIONS) {
                return ResponseHelper::error(
                    'Đã đạt giới hạn 3 thông báo ghim. Vui lòng gỡ bớt thông báo ghim trước khi ghim thêm.',
                    422
                );
            }
        }

        $attachmentUrl = $validated['attachment_url'] ?? null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('club_notifications/attachments', 'public');
            $attachmentUrl = Storage::url($path);
        }

        $notification = $this->notificationService->createNotification($club, $validated, $userId, $attachmentUrl);
        $notification->load(['type', 'creator', 'recipients.user']);
        return ResponseHelper::success(new ClubNotificationResource($notification), 'Tạo thông báo thành công', 201);
    }

    public function show($clubId, $notificationId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        $notification = ClubNotification::where('club_id', $clubId)
            ->with(['type', 'creator', 'recipients.user'])
            ->findOrFail($notificationId);

        $member = $userId ? $club->activeMembers()->where('user_id', $userId)->first() : null;
        $canManage = $member && in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary]);

        if (!$canManage) {
            if ($notification->status !== ClubNotificationStatus::Sent) {
                return ResponseHelper::error('Bạn không có quyền xem thông báo này', 403);
            }

            // Kiểm tra quyền xem cho thành viên bình thường
            $hasAccess = $this->notificationService->canMemberViewNotification($notification, $userId, $member);
            if (!$hasAccess) {
                return ResponseHelper::error('Bạn không có quyền xem thông báo này', 403);
            }
        }

        return ResponseHelper::success(new ClubNotificationResource($notification), 'Lấy thông tin thông báo thành công');
    }

    public function update(Request $request, $clubId, $notificationId)
    {
        $notification = ClubNotification::where('club_id', $clubId)->findOrFail($notificationId);
        $userId = auth()->id();

        if ($notification->created_by !== $userId) {
            $club = $notification->club;
            $member = $club->activeMembers()->where('user_id', $userId)->first();
            if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
                return ResponseHelper::error('Không có quyền cập nhật thông báo này', 403);
            }
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'attachment_url' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240',
            'priority' => ['sometimes', Rule::enum(ClubNotificationPriority::class)],
            'status' => ['sometimes', Rule::enum(ClubNotificationStatus::class)],
            'metadata' => 'nullable|array',
            'scheduled_at' => 'required_if:status,scheduled|nullable|date',
        ]);

        $newAttachmentUrl = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('club_notifications/attachments', 'public');
            $newAttachmentUrl = Storage::url($path);
        }
        unset($validated['attachment']);

        $notification = $this->notificationService->updateNotification($notification, $validated, $newAttachmentUrl);
        $notification->load(['type', 'creator', 'recipients.user']);
        return ResponseHelper::success(new ClubNotificationResource($notification), 'Cập nhật thông báo thành công');
    }

    public function destroy($clubId, $notificationId)
    {
        $notification = ClubNotification::where('club_id', $clubId)->findOrFail($notificationId);
        $userId = auth()->id();

        if ($notification->created_by !== $userId) {
            $club = $notification->club;
            $member = $club->activeMembers()->where('user_id', $userId)->first();
            if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
                return ResponseHelper::error('Không có quyền xóa thông báo này', 403);
            }
        }

        $this->notificationService->deleteNotification($notification);

        return ResponseHelper::success('Xóa thông báo thành công');
    }

    public function togglePin($clubId, $notificationId)
    {
        $notification = ClubNotification::where('club_id', $clubId)->findOrFail($notificationId);
        $userId = auth()->id();

        $club = $notification->club;
        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền ghim thông báo', 403);
        }

        if (!$notification->is_pinned) {
            $pinnedCount = $club->notifications()->where('is_pinned', true)->count();
            if ($pinnedCount >= self::MAX_PINNED_NOTIFICATIONS) {
                return ResponseHelper::error(
                    'Đã đạt giới hạn 3 thông báo ghim. Vui lòng gỡ bớt thông báo ghim trước khi ghim thêm.',
                    422
                );
            }
        }

        $notification->togglePin();

        $notification->load(['type', 'creator', 'recipients.user']);
        return ResponseHelper::success(new ClubNotificationResource($notification), 'Đã cập nhật trạng thái ghim');
    }

    public function markAsRead($clubId, $notificationId)
    {
        $club = Club::findOrFail($clubId);
        $notification = ClubNotification::where('club_id', $clubId)->findOrFail($notificationId);
        $userId = auth()->id();

        $member = $userId ? $club->activeMembers()->where('user_id', $userId)->first() : null;
        $canManage = $member && in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary]);

        try {
            $this->notificationService->markAsRead($notification, $userId, $canManage);
            return ResponseHelper::success([], 'Đã đánh dấu đọc');
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi đánh dấu đọc thông báo', 403);
        }
    }

    public function markAllAsRead($clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $member = $club->activeMembers()->where('user_id', $userId)->first();
        $canManage = $member && in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary]);

        $message = $this->notificationService->markAllAsRead($club, $userId, $canManage);

        return ResponseHelper::success([], $message);
    }

    public function getNotificationTypes()
    {
        $types = ClubNotificationType::where('is_active', true)->get();

        return ResponseHelper::success($types, 'Lấy danh sách loại thông báo thành công');
    }

    public function send($clubId, $notificationId)
    {
        $notification = ClubNotification::where('club_id', $clubId)->findOrFail($notificationId);
        $userId = auth()->id();

        $club = $notification->club;
        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền gửi thông báo', 403);
        }

        try {
            $notification = $this->notificationService->sendNotification($notification, $club);
            $notification->load(['type', 'creator', 'recipients.user']);
            return ResponseHelper::success(new ClubNotificationResource($notification), 'Gửi thông báo thành công');
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi gửi thông báo', 422);
        }
    }
}
