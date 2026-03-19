<?php

namespace App\Http\Resources;

use App\Models\MiniTournamentStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiniTournamentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $participants = $this->relationLoaded('participants') ? $this->participants : collect();

        $qrUrl = $this->qr_code_url;
        if ($qrUrl && !str_starts_with($qrUrl, 'http')) {
            $qrUrl = asset('storage/' . ltrim($qrUrl, '/'));
        }

        $posterUrl = $this->poster;
        if ($posterUrl && !str_starts_with($posterUrl, 'http')) {
            $posterUrl = asset('storage/' . ltrim($posterUrl, '/'));
        }

        $data = [
            'id' => $this->id,
            'poster' => $posterUrl,
            'sport' => new SportResource($this->whenLoaded('sport')),
            'name' => $this->name,
            'description' => $this->description,
            'play_mode' => $this->play_mode,
            'format' => $this->format,

            // Updated time fields
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration' => $this->duration,

            'competition_location' => new CompetitionLocationResource($this->whenLoaded('competitionLocation')),
            'is_private' => $this->is_private,

            // Updated fee fields
            'has_fee' => $this->has_fee,
            'auto_split_fee' => $this->auto_split_fee,
            'fee_amount' => $this->fee_amount,
            'fee_description' => $this->fee_description,
            'qr_code_url' => $qrUrl,
            'payment_account_id' => $this->payment_account_id,
            // Computed fee properties
            'fee_per_person' => $this->fee_per_person,
            'total_fee_expected' => $this->total_fee_expected,
            'final_fee_per_person' => $this->final_fee_per_person,
            'auto_payment_created' => (bool) $this->auto_payment_created,

            'max_players' => $this->max_players,

            // Rating
            'min_rating' => $this->min_rating,
            'max_rating' => $this->max_rating,

            // Gender (replaced gender_policy)
            'gender' => $this->gender,
            'gender_text' => $this->gender_text,

            // Updated new fields
            'apply_rule' => $this->apply_rule,
            'allow_cancellation' => $this->allow_cancellation,
            'cancellation_duration' => $this->cancellation_duration,
            'auto_approve' => $this->auto_approve,
            'allow_participant_add_friends' => $this->allow_participant_add_friends,

            'status' => $this->status,
            'status_text' => $this->status_text,

            'staff' => $this->whenLoaded('staff', function () {
                return $this->staff
                    ->groupBy(fn($staff) => MiniTournamentStaff::getRoleText( $staff->pivot->role))
                    ->map(fn($group) => MiniTournamentStaffResource::collection($group));
            }),
            'participants' => MiniParticipantResource::collection($participants),
            'matches' => $this->whenLoaded('matches', function () {
                return MiniMatchResource::collection($this->matches);
            }),
            'all_users' => UserListResource::collection($this->all_users ?? collect()),

            // Recurring schedule
            // Same format as clubs: { period, week_days, recurring_date }
            'recurring_schedule' => $this->recurring_schedule,
            'recurrence_series_id' => $this->recurrence_series_id,
            'recurrence_series_cancelled_at' => $this->recurrence_series_cancelled_at,
        ];

        // Include game rule fields only if apply_rule is true
        if ($this->apply_rule) {
            $data['set_number'] = $this->set_number;
            $data['base_points'] = $this->base_points;
            $data['points_difference'] = $this->points_difference;
            $data['max_points'] = $this->max_points;
        }

        return $data;
    }
}
