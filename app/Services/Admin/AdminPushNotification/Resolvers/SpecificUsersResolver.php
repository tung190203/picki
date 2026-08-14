<?php

namespace App\Services\Admin\AdminPushNotification\Resolvers;

use App\Enums\AdminPushNotification\RecipientType;
use App\Models\AdminPushNotificationCampaign;
use App\Models\User;
use App\Services\Admin\AdminPushNotification\PushNotificationRecipientResolver;
use App\Services\Admin\AdminPushNotification\RecipientQueryBuilder;
use Illuminate\Database\Eloquent\Builder;

class SpecificUsersResolver implements PushNotificationRecipientResolver
{
    public function buildQuery(array $config): Builder
    {
        $userIds = $config['user_ids'] ?? [];
        if (empty($userIds) || !is_array($userIds)) {
            return User::query()->whereRaw('1 = 0'); // empty result
        }

        $userIds = array_map('intval', $userIds);

        $query = User::query()->whereIn('users.id', $userIds);

        return RecipientQueryBuilder::applyCommonFilters($query);
    }

    public function label(array $config): string
    {
        $userIds = $config['user_ids'] ?? [];
        $count = is_array($userIds) ? count($userIds) : 0;

        return sprintf('Danh sách %d người dùng', $count);
    }

    public function warnings(array $config): array
    {
        return [
            'Chỉ gửi cho những người dùng trong danh sách chọn (sau khi áp dụng filter banned/guest/merged).',
        ];
    }

    public static function makeFor(AdminPushNotificationCampaign $campaign): self
    {
        return new self();
    }
}