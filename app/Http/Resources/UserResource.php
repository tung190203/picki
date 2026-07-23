<?php

namespace App\Http\Resources;

use App\Models\Sport;
use App\Models\User;
use App\Models\ScoreVerificationRequest;
use App\Services\BadgeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ClubResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    private static ?Sport $_cachedPickleballSport = null;

    protected static function getPickleballSport(): ?Sport
    {
        if (self::$_cachedPickleballSport === null) {
            self::$_cachedPickleballSport = Sport::where('slug', 'pickleball')->first();
        }
        return self::$_cachedPickleballSport;
    }

    protected static function getLatestScoreVerification(int $userId, string $scoreType): ?array
    {
        $request = ScoreVerificationRequest::where('user_id', $userId)
            ->where('score_type', $scoreType)
            ->latest()
            ->first();

        if (!$request) {
            return null;
        }

        $currentScore = DB::table('user_sport_scores')
            ->join('user_sport', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
            ->where('user_sport.user_id', $userId)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->max('user_sport_scores.score_value');

        $difference = $currentScore !== null
            ? round(abs((float) $request->submitted_score - $currentScore), 3)
            : null;

        $threshold = config('score_verification.max_difference', 0.5);
        $isOverThreshold = $difference !== null ? $difference >= $threshold : false;

        return [
            'id' => $request->id,
            'request_number' => $request->request_number,
            'image_url' => $request->image_path
                ? rtrim(config('app.url'), '/') . Storage::url($request->image_path)
                : null,
            'submitted_score' => $request->submitted_score,
            'current_picki_score' => $currentScore,
            'difference' => $difference,
            'threshold' => $threshold,
            'is_over_threshold' => $isOverThreshold,
            'status' => $request->status,
            'rejection_reason' => $request->status === 'REJECTED' ? $request->rejection_reason : null,
            'created_at' => $request->created_at?->toISOString(),
            'is_new' => $request->created_at && $request->created_at->diffInHours(now()) < 24,
        ];
    }

    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [];
        }

        $vnRank = $this->vn_rank ?? null;

        if ($vnRank === null) {
            $sport = self::getPickleballSport();
            if ($sport) {
                $vnRank = $this->getVNRank($sport->id);
            }
        }

        $currentUser = $request->user();
        $isFollow = false;
        $isFriend = false;

        if ($currentUser) {
            if (isset($this->is_following_count)) {
                $isFollow = (bool) $this->is_following_count;
            }
            if (isset($this->is_followed_by_count) && isset($this->is_following_count)) {
                $isFriend = (bool) ($this->is_following_count && $this->is_followed_by_count);
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
            'trust_score' => (float) $this->trust_score,
            'gender' => $this->gender,
            'gender_text' => $this->gender_text,
            'date_of_birth' => $this->date_of_birth ? Carbon::parse($this->date_of_birth)->format('d-m-Y') : null,
            'age_years' => $this->age_years,
            'age_group' => $this->age_group,
            'play_times' => UserPlayTimeResource::collection($this->whenLoaded('playTimes')),
            'sports' => UserSportResource::collection($this->whenLoaded('sports')),
            'clubs' => ClubResource::collection($this->whenLoaded('clubs')),
            'is_follow' => $isFollow,
            'is_friend' => $isFriend,
            'vn_rank' => $vnRank,
            'weekly_change' => User::getWeeklyChange($this->id, 1),
            'last_login' => $this->last_login?->toISOString(),
            'is_online' => $this->isOnline(),
            'is_super_admin' => (bool)$this->is_super_admin,
            'primary_badge' => app(BadgeService::class)->getPrimaryBadge($this->id),
            'is_banned' => (bool)$this->is_banned,
            'is_guest' => (bool)$this->is_guest,
            'has_advanced_mini_tournament' => $this->resource->hasAdvancedMiniTournament(),
            'latest_used_qr' => $this->latest_used_qr,
            'created_at' => $this->created_at?->toISOString(),
            'spcn_request' => self::getLatestScoreVerification($this->id, 'SPCN'),
            'dupr_request' => self::getLatestScoreVerification($this->id, 'DUPR'),
            'badges' => app(BadgeService::class)->getUserBadges($this->id)['badges'],
        ];
    }
}
