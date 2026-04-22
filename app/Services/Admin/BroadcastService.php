<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BroadcastService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function index(int $page, int $limit): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return AuditLog::with('user')
            ->byAction('send_broadcast')
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
    }

    public function send(array $payload, User $admin): int
    {
        $title = $payload['title'];
        $content = $payload['content'];
        $target = $payload['target'] ?? 'all';
        $userIds = $payload['user_ids'] ?? [];
        $group = $payload['group'] ?? null;

        $query = User::query();

        if ($target === 'users' && !empty($userIds)) {
            $query->whereIn('id', $userIds);
        } elseif ($target === 'group' && $group) {
            switch ($group) {
                case 'new_users':
                    $query->where('created_at', '>=', now()->subDays(30));
                    break;
                case 'active_users':
                    $query->whereNotNull('last_active_at')
                          ->where('last_active_at', '>=', now()->subDays(7));
                    break;
                case 'inactive_users':
                    $query->where(function ($q) {
                        $q->whereNull('last_active_at')
                          ->orWhere('last_active_at', '<', now()->subDays(30));
                    });
                    break;
            }
        }

        $users = $query->get();

        DB::transaction(function () use ($users, $title, $content, $admin, $payload) {
            $systemNotification = SystemNotification::create([
                'title' => $title,
                'body' => $content,
                'data' => [
                    'type' => 'BROADCAST',
                    'target' => $payload['target'] ?? 'all',
                    'group' => $payload['group'] ?? null,
                ],
                'sent_at' => now(),
            ]);

            foreach ($users as $user) {
                $user->notify(new SystemNotificationNotification($systemNotification));
            }

            $this->auditLogService->log(
                $admin,
                'send_broadcast',
                SystemNotification::class,
                $systemNotification->id,
                null,
                [
                    'title' => $title,
                    'content' => $content,
                    'target' => $target,
                    'user_ids' => $payload['user_ids'] ?? [],
                    'group' => $group,
                    'recipient_count' => $users->count(),
                ]
            );
        });

        return $users->count();
    }
}
