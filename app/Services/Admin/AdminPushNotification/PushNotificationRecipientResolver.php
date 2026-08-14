<?php

namespace App\Services\Admin\AdminPushNotification;

use App\Models\AdminPushNotificationCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface PushNotificationRecipientResolver
{
    /**
     * Trả về Eloquent query User đang đủ điều kiện (chưa lấy device tokens).
     * Implementations PHẢI gọi RecipientQueryBuilder::applyCommonFilters() để
     * đảm bảo filter banned/guest/merged + có device enabled.
     */
    public function buildQuery(array $config): Builder;

    /**
     * Trả về label tiếng Việt cho recipient type để hiển thị ở FE.
     * Ví dụ: "Tất cả người dùng", "CLB Picki Hà Nội - 234 thành viên".
     */
    public function label(array $config): string;

    /**
     * Trả về array warnings cho FE (VD: ALL → "Bao gồm cả tài khoản test/demo").
     */
    public function warnings(array $config): array;

    /**
     * Tạo resolver instance phù hợp với recipient_type của campaign.
     */
    public static function makeFor(AdminPushNotificationCampaign $campaign): self;
}