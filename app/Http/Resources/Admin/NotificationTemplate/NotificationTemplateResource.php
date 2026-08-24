<?php

namespace App\Http\Resources\Admin\NotificationTemplate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'content' => $this->content,
            'action_type' => $this->action_type?->value,
            'action_type_label' => $this->action_type?->label(),
            'action_id' => $this->action_id,
            'recipient_type' => $this->recipient_type?->value,
            'recipient_type_label' => $this->recipient_type?->label(),
            'recipient_config' => $this->recipient_config,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn() => $this->creator?->full_name),
            'created_at' => $this->created_at?->toIsoString(),
            'updated_at' => $this->updated_at?->toIsoString(),
        ];
    }
}
