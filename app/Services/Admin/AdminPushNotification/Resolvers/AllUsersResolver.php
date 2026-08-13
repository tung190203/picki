<?php

namespace App\Services\Admin\AdminPushNotification\Resolvers;

use App\Enums\AdminPushNotification\RecipientType;
use App\Models\AdminPushNotificationCampaign;
use App\Models\User;
use App\Services\Admin\AdminPushNotification\PushNotificationRecipientResolver;
use App\Services\Admin\AdminPushNotification\RecipientQueryBuilder;
use Illuminate\Database\Eloquent\Builder;

class AllUsersResolver implements PushNotificationRecipientResolver
{
    public function buildQuery(array $config): Builder
    {
        $query = User::query();
        return RecipientQueryBuilder::applyCommonFilters($query);
    }

    public function label(array $config): string
    {
        return RecipientType::ALL->label();
    }

    public function warnings(array $config): array
    {
        return [
            'Thông báo sẽ được gửi cho TẤT CẢ người dùng đang hoạt động.',
            'Bao gồm cả tài khoản test/demo. Vui lòng xác nhận trước khi gửi.',
        ];
    }

    public static function makeFor(AdminPushNotificationCampaign $campaign): self
    {
        return new self();
    }
}