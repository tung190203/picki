<?php

namespace App\Http\Resources\Admin\AdminPushNotification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'image_url' => $this->image_url,
            'action_type' => $this->action_type?->value,
            'action_id' => $this->action_id,
            'recipient_type' => $this->recipient_type?->value,
            'send_type' => $this->send_type?->value,
            'scheduled_at' => $this->scheduled_at?->toIsoString(),
            'sent_at' => $this->sent_at?->toIsoString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_color' => $this->status?->color(),
            'estimated_recipient_count' => $this->estimated_recipient_count,
            'actual_recipient_count' => $this->actual_recipient_count,
            'success_count' => $this->success_count,
            'failure_count' => $this->failure_count,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn() => $this->creator?->full_name),
            'created_at' => $this->created_at?->toIsoString(),
        ];
    }
}