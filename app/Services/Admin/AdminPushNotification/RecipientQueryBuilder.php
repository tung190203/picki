<?php

namespace App\Services\Admin\AdminPushNotification;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RecipientQueryBuilder
{
    /**
     * Áp dụng filter chung cho mọi resolver:
     * - Không bị banned
     * - Không phải guest
     * - Chưa bị merge
     * - Không bị soft delete
     * - Có ít nhất 1 device enabled
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function applyCommonFilters(Builder $query): Builder
    {
        return $query
            ->where('users.is_banned', false)
            ->where('users.is_guest', false)
            ->where('users.is_merged', false)
            ->whereNull('users.deleted_at')
            ->whereHas('deviceTokens', fn ($q) => $q->where('is_enabled', true));
    }
}