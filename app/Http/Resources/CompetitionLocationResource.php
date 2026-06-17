<?php

namespace App\Http\Resources;

use App\Models\CompetitionLocationYard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompetitionLocationResource extends JsonResource
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
            'location' => new LocationResource($this->whenLoaded('location')),
            'name' => $this->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'image' => $this->image,
            'address' => $this->address,
            'phone' => $this->phone,
            'opening_time' => $this->opening_time,
            'closing_time' => $this->closing_time,
            'note_booking' => $this->note_booking,
            'website' => $this->website,
            'is_banned' => (bool) ($this->is_banned ?? false),
            'sports' => SportResource::collection($this->whenLoaded('sports')),
            'yard_types' => $this->whenLoaded('competitionLocationYards', function () {
                return $this->competitionLocationYards->pluck('yard_type')->unique()->map(function ($type) {
                    return [
                        'type' => $type,
                        'name' => match ($type) {
                            CompetitionLocationYard::TYPE_INDOOR => 'Trong nhà',
                            CompetitionLocationYard::TYPE_OUTDOOR => 'Ngoài trời',
                            CompetitionLocationYard::TYPE_PRIVATE_RENTAL => 'Thuê riêng',
                            CompetitionLocationYard::TYPE_PAY_FEE => 'Đóng phí',
                            CompetitionLocationYard::TYPE_ROOF => 'Mái che',
                            default => 'Unknown',
                        },
                    ];
                })->values();
            }),
            'facilities' => FacilityResource::collection($this->whenLoaded('facilities')),
        ];
    }
}
