<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'internal_name' => $this->internal_name ?? $this->title ?? 'Banner #' . $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image_url' => $this->image_url,
            'link_type' => $this->link_type ?? 'none',
            'link_value' => $this->link_value ?? $this->link,
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'audience_segment_ids' => $this->audience_segment_ids ?? ['ALL'],
            'display_order' => $this->display_order ?? $this->order ?? 0,
            'is_enabled' => (bool) ($this->is_enabled ?? $this->is_active ?? true),
            'status_badge' => $this->status_badge,
            'days_remaining' => $this->days_remaining,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
