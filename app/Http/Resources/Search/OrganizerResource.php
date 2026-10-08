<?php

namespace App\Http\Resources\Search;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class OrganizerResource extends UserResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        // Extra fields chỉ có ở organizer identity
        $data['club'] = $this->resource->getAttribute('club');
        $data['organized_count'] = $this->resource->getAttribute('organized_count');
        $data['follower_count'] = $this->resource->getAttribute('follower_count');

        return $data;
    }
}
