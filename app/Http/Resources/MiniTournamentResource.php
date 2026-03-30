<?php

namespace App\Http\Resources;

use App\Http\Resources\Club\ClubFundCollectionResource;
use App\Models\MiniTournamentStaff;
use App\Models\User;
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
        $model = $this->resource;
        $participants = ($model instanceof \Illuminate\Database\Eloquent\Model && $model->relationLoaded('participants'))
            ? $model->participants
            : collect();

        $qrUrl = $this->qr_code_url;
        if ($qrUrl && !str_starts_with($qrUrl, 'http')) {
            $qrUrl = asset('storage/' . ltrim($qrUrl, '/'));
        }

        $posterUrl = $this->poster;
        if ($posterUrl && !str_starts_with($posterUrl, 'http')) {
            $posterUrl = asset('storage/' . ltrim($posterUrl, '/'));
        }

        $currentUserId = $request->user()?->id;

        // Tính has_invitation: user hiện tại có lời mời đang chờ (is_invited=true, is_confirmed=false)
        $hasInvitation = false;
        $invitedBy = null;
        if ($currentUserId) {
            $myParticipant = $participants->first(fn($p) =>
                (int) $p->user_id === (int) $currentUserId
                && (bool) $p->is_invited === true
                && (bool) $p->is_confirmed === false
            );
            if ($myParticipant) {
                $hasInvitation = true;
                // Load invitedBy relation nếu chưa loaded
                if ($myParticipant->relationLoaded('invitedBy') && $myParticipant->invitedBy) {
                    $invitedBy = new UserListResource($myParticipant->invitedBy);
                } elseif ($myParticipant->invited_by) {
                    $inviter = User::find($myParticipant->invited_by);
                    if ($inviter) {
                        $invitedBy = new UserListResource($inviter);
                    }
                }
            }
        }

        $data = [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'poster' => $posterUrl,
            'sport' => new SportResource($this->whenLoaded('sport')),
            'name' => $this->name,
            'description' => $this->description,
            'play_mode' => $this->play_mode,
            'format' => $this->format,
            'club' => ($this->whenLoaded('club') && $this->club && $this->club->is_public !== false)
                ? new ClubResource($this->club)
                : null,

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

            // Invitation info for current user
            'has_invitation' => $hasInvitation,
            'invited_by' => $invitedBy,

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
            'fund_collection' => new ClubFundCollectionResource(
                $this->whenLoaded('fundCollection')
            ),

            // Recurring schedule
            // Same format as clubs: { period, week_days, recurring_date }
            'recurring_schedule' => $this->recurring_schedule,
            'recurrence_series_id' => $this->recurrence_series_id,
            'recurrence_series_cancelled_at' => $this->recurrence_series_cancelled_at,

            // Club fund integration
            'use_club_fund' => $this->use_club_fund,
            'club_fund_collection_id' => $this->club_fund_collection_id,
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
