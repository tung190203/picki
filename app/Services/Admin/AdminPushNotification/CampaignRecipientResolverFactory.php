<?php

namespace App\Services\Admin\AdminPushNotification;

use App\Enums\AdminPushNotification\RecipientType;
use App\Models\AdminPushNotificationCampaign;
use App\Services\Admin\AdminPushNotification\Resolvers\ActivityLevelResolver;
use App\Services\Admin\AdminPushNotification\Resolvers\AllUsersResolver;
use App\Services\Admin\AdminPushNotification\Resolvers\ClubMembersResolver;
use App\Services\Admin\AdminPushNotification\Resolvers\SpecificUsersResolver;
use InvalidArgumentException;

class CampaignRecipientResolverFactory
{
    public static function make(AdminPushNotificationCampaign $campaign): PushNotificationRecipientResolver
    {
        return match ($campaign->recipient_type) {
            RecipientType::ALL => AllUsersResolver::makeFor($campaign),
            RecipientType::CLUB => ClubMembersResolver::makeFor($campaign),
            RecipientType::ACTIVITY => ActivityLevelResolver::makeFor($campaign),
            RecipientType::USERS => SpecificUsersResolver::makeFor($campaign),
            default => throw new InvalidArgumentException("Unsupported recipient_type: {$campaign->recipient_type->value}"),
        };
    }

    /**
     * @return array{resolver: PushNotificationRecipientResolver, config: array}
     */
    public static function makeWithConfig(AdminPushNotificationCampaign $campaign): array
    {
        $config = $campaign->recipient_config ?? [];
        $resolver = self::make($campaign);

        return ['resolver' => $resolver, 'config' => $config];
    }
}