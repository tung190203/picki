<?php

namespace App\Services\Admin\AdminPushNotification\Resolvers;

use App\Enums\AdminPushNotification\ActivityLevel;
use App\Enums\AdminPushNotification\RecipientType;
use App\Models\AdminPushNotificationCampaign;
use App\Models\User;
use App\Services\Admin\AdminPushNotification\PushNotificationRecipientResolver;
use App\Services\Admin\AdminPushNotification\RecipientQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class ActivityLevelResolver implements PushNotificationRecipientResolver
{
    public function buildQuery(array $config): Builder
    {
        $level = ActivityLevel::tryFrom($config['level'] ?? '');

        if (!$level) {
            throw new InvalidArgumentException('Invalid activity level');
        }

        $query = User::query();

        switch ($level) {
            case ActivityLevel::HOT:
                $query->whereNotNull('users.last_active_at')
                    ->where('users.last_active_at', '>=', now()->subDays(7));
                break;
            case ActivityLevel::WARM:
                $query->whereNotNull('users.last_active_at')
                    ->where('users.last_active_at', '>=', now()->subDays(30))
                    ->where('users.last_active_at', '<', now()->subDays(7));
                break;
            case ActivityLevel::COLD:
                $query->where(function ($q) {
                    $q->whereNull('users.last_active_at')
                        ->orWhere('users.last_active_at', '<', now()->subDays(30));
                });
                break;
        }

        return RecipientQueryBuilder::applyCommonFilters($query);
    }

    public function label(array $config): string
    {
        $level = ActivityLevel::tryFrom($config['level'] ?? '');
        if (!$level) {
            return 'Mức độ hoạt động không hợp lệ';
        }

        return sprintf('%s - %s', $level->label(), $level->description());
    }

    public function warnings(array $config): array
    {
        $level = ActivityLevel::tryFrom($config['level'] ?? '');
        if (!$level) {
            return [];
        }

        return [
            $level->description(),
        ];
    }

    public static function makeFor(AdminPushNotificationCampaign $campaign): self
    {
        return new self();
    }
}