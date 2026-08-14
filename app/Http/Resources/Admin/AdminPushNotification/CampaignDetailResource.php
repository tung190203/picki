<?php

namespace App\Http\Resources\Admin\AdminPushNotification;

use App\Services\Admin\AdminPushNotification\CampaignRecipientResolverFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\AdminPushNotificationCampaign $campaign */
        $campaign = $this->resource;

        $base = (new CampaignResource($campaign))->toArray($request);

        $resolverData = CampaignRecipientResolverFactory::makeWithConfig($campaign);
        /** @var \App\Services\Admin\AdminPushNotification\PushNotificationRecipientResolver $resolver */
        $resolver = $resolverData['resolver'];
        $config = $resolverData['config'];

        $base['recipient_config'] = $campaign->recipient_config;
        $base['recipient_label'] = $resolver->label($config);
        $base['warnings'] = $resolver->warnings($config);
        $base['metadata'] = $campaign->metadata;
        $base['error_message'] = $campaign->error_message;
        $base['creator'] = $this->whenLoaded('creator', function () use ($campaign) {
            $campaign->loadMissing('creator');
            return [
                'id' => $campaign->creator->id,
                'full_name' => $campaign->creator->full_name,
                'email' => $campaign->creator->email,
            ];
        });

        return $base;
    }
}