<?php

namespace App\Http\Resources;

use App\Models\Sport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ClubResource;

class UserResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [];
        }

        $vnRank = $this->vn_rank;

        // Fallback for single user requests or when attributes are missing
        if (is_null($vnRank)) {
            $sport = Sport::where('slug', 'pickleball')->first();
            if ($sport) {
                $sportId = $sport->id;
                $vnRank = $this->getVNRank($sportId);
            }
        }

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'visibility' => $this->visibility,
            'avatar_url' => $this->avatar_url,
            'thumbnail' => $this->thumbnail,
            'location_id' => $this->location_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'about' => $this->about,
            'role' => $this->role,
            'email_verified_at' => $this->email_verified_at,
            'is_profile_completed' => $this->is_profile_completed,
            'gender' => $this->gender,
            'gender_text' => $this->gender_text,
            'date_of_birth' => Carbon::parse($this->date_of_birth)->format('d-m-Y'),
            'age_years' => $this->age_years,
            'age_group' => $this->age_group,
            'play_times' => UserPlayTimeResource::collection($this->whenLoaded('playTimes')),
            'sports' => UserSportResource::collection($this->whenLoaded('sports')),
            'clubs' => ClubResource::collection($this->whenLoaded('clubs')),
            'is_follow' => isset($this->is_following_count) ? (bool)$this->is_following_count : ($request->user() ? $request->user()->isFollowing($this->resource) : false),
            'is_friend' => (isset($this->is_following_count) && isset($this->is_followed_by_count))
                ? ($this->is_following_count && $this->is_followed_by_count)
                : ($request->user() ? $request->user()->isFriendWith($this->resource) : false),
            'vn_rank' => $vnRank ?? null,
            'last_login' => $this->last_login?->toISOString(),
            'is_online' => $this->isOnline(),
            'total_tournaments' => (int) $this->total_tournaments,
            'total_mini_tournaments' => (int) $this->total_mini_tournaments,
            'is_super_admin' => (bool)$this->is_super_admin,
        ];
    }
}
