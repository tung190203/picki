<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubMixedContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->getResourceType();

        $base = [
            'id' => $this->resource['id'],
            'type' => $type,
        ];

        if ($type === 'activity') {
            return array_merge($base, $this->formatActivity());
        }

        if ($type === 'mini_tournament') {
            return array_merge($base, $this->formatMiniTournament());
        }

        if ($type === 'tournament') {
            return array_merge($base, $this->formatTournament());
        }

        return $base;
    }

    private function getResourceType(): string
    {
        // Array format from controller
        if (is_array($this->resource)) {
            return $this->resource['_type']
                ?? ($this->resource['mini_tournament_id'] ?? null ? 'activity' : 'mini_tournament');
        }

        // Model format
        if ($this->resource instanceof \App\Models\Club\ClubActivity) {
            return 'activity';
        }

        if ($this->resource instanceof \App\Models\MiniTournament) {
            return 'mini_tournament';
        }

        if ($this->resource instanceof \App\Models\Tournament) {
            return 'tournament';
        }

        return 'unknown';
    }

    private function formatActivity(): array
    {
        // Array format
        if (is_array($this->resource)) {
            return [
                'club_id' => $this->resource['club_id'] ?? null,
                'mini_tournament_id' => $this->resource['mini_tournament_id'] ?? null,
                'title' => $this->resource['title'] ?? null,
                'name' => $this->resource['title'] ?? null,
                'description' => $this->resource['description'] ?? null,
                'activity_type' => $this->resource['type'] ?? $this->resource['activity_type'] ?? null,
                'start_time' => $this->resource['start_time'] ?? null,
                'end_time' => $this->resource['end_time'] ?? null,
                'duration' => $this->resource['duration'] ?? null,
                'location' => $this->resource['address'] ?? $this->resource['location'] ?? null,
                'address' => $this->resource['address'] ?? null,
                'latitude' => $this->resource['latitude'] ?? null,
                'longitude' => $this->resource['longitude'] ?? null,
                'is_public' => (bool) ($this->resource['is_public'] ?? true),
                'is_recurring' => !empty($this->resource['recurring_schedule']),
                'recurring_schedule' => $this->resource['recurring_schedule'] ?? null,
                'recurrence_series_id' => $this->resource['recurrence_series_id'] ?? null,
                'fee_amount' => isset($this->resource['fee_amount']) ? (float) $this->resource['fee_amount'] : null,
                'fee_description' => $this->resource['fee_description'] ?? null,
                'max_participants' => isset($this->resource['max_participants']) ? (int) $this->resource['max_participants'] : null,
                'participants_count' => $this->resource['participants_count'] ?? null,
                'status' => $this->resource['status'] ?? null,
                'created_by' => $this->resource['created_by'] ?? null,
                'has_transaction' => (bool) ($this->resource['has_transaction'] ?? false),
                'fund_collection_id' => $this->resource['fund_collection_id'] ?? null,
                'created_at' => $this->resource['created_at'] ?? null,
            ];
        }

        // Model format
        $activity = $this->resource;
        return [
            'club_id' => $activity->club_id ?? null,
            'mini_tournament_id' => $activity->mini_tournament_id ?? null,
            'title' => $activity->title ?? null,
            'name' => $activity->title ?? null,
            'description' => $activity->description ?? null,
            'activity_type' => $activity->type ?? null,
            'start_time' => $activity->start_time?->format('c') ?? ($activity->start_time ?? null),
            'end_time' => $activity->end_time?->format('c') ?? ($activity->end_time ?? null),
            'duration' => $activity->duration ?? null,
            'location' => $activity->address ?? null,
            'address' => $activity->address ?? null,
            'latitude' => $activity->latitude ?? null,
            'longitude' => $activity->longitude ?? null,
            'is_public' => (bool) ($activity->is_public ?? true),
            'is_recurring' => $activity->recurring_schedule !== null,
            'recurring_schedule' => $activity->recurring_schedule ?? null,
            'recurrence_series_id' => $activity->recurrence_series_id ?? null,
            'fee_amount' => $activity->fee_amount !== null ? (float) $activity->fee_amount : null,
            'fee_description' => $activity->fee_description ?? null,
            'max_participants' => $activity->max_participants !== null ? (int) $activity->max_participants : null,
            'participants_count' => $activity->participants_count ?? null,
            'status' => $activity->status ?? null,
            'created_by' => $activity->created_by ?? null,
            'has_transaction' => (bool) ($activity->has_transaction ?? false),
            'fund_collection_id' => $activity->fund_collection_id ?? null,
            'created_at' => $activity->created_at?->toISOString() ?? ($activity->created_at ?? null),
        ];
    }

    private function formatMiniTournament(): array
    {
        // Array format
        if (is_array($this->resource)) {
            return [
                'club_id' => $this->resource['club_id'] ?? null,
                'name' => $this->resource['name'] ?? null,
                'description' => $this->resource['description'] ?? null,
                'start_time' => $this->resource['start_time'] ?? null,
                'end_time' => $this->resource['end_time'] ?? null,
                'duration' => $this->resource['duration'] ?? null,
                'location' => $this->resource['competition_location']['name'] ?? $this->resource['location'] ?? null,
                'address' => $this->resource['competition_location']['address'] ?? $this->resource['address'] ?? null,
                'latitude' => $this->resource['competition_location']['latitude'] ?? $this->resource['latitude'] ?? null,
                'longitude' => $this->resource['competition_location']['longitude'] ?? $this->resource['longitude'] ?? null,
                'poster' => $this->resource['poster'] ?? null,
                'sport' => $this->resource['sport'] ?? null,
                'format' => $this->resource['format'] ?? null,
                'play_mode' => $this->resource['play_mode'] ?? null,
                'has_fee' => (bool) ($this->resource['has_fee'] ?? false),
                'fee_amount' => isset($this->resource['fee_amount']) ? (float) $this->resource['fee_amount'] : null,
                'fee_per_person' => $this->resource['fee_per_person'] ?? null,
                'max_players' => $this->resource['max_players'] ?? null,
                'participants_count' => $this->resource['participants_count'] ?? null,
                'gender' => $this->resource['gender'] ?? null,
                'gender_text' => $this->resource['gender_text'] ?? null,
                'status' => $this->resource['status'] ?? null,
                'status_text' => $this->resource['status_text'] ?? null,
                'is_private' => (bool) ($this->resource['is_private'] ?? false),
                'apply_rule' => (bool) ($this->resource['apply_rule'] ?? false),
                'created_at' => $this->resource['created_at'] ?? null,
            ];
        }

        // Model format
        $tournament = $this->resource;
        return [
            'club_id' => $tournament->club_id ?? null,
            'name' => $tournament->name ?? null,
            'description' => $tournament->description ?? null,
            'start_time' => $tournament->start_time?->format('c') ?? ($tournament->start_time ?? null),
            'end_time' => $tournament->end_time?->format('c') ?? ($tournament->end_time ?? null),
            'duration' => $tournament->duration ?? null,
            'location' => $tournament->competitionLocation?->name ?? null,
            'address' => $tournament->competitionLocation?->address ?? null,
            'latitude' => $tournament->competitionLocation?->latitude ?? null,
            'longitude' => $tournament->competitionLocation?->longitude ?? null,
            'poster' => $tournament->poster ?? null,
            'sport' => $tournament->sport ?? null,
            'format' => $tournament->format ?? null,
            'play_mode' => $tournament->play_mode ?? null,
            'has_fee' => (bool) ($tournament->has_fee ?? false),
            'fee_amount' => $tournament->fee_amount !== null ? (float) $tournament->fee_amount : null,
            'fee_per_person' => $tournament->fee_per_person ?? null,
            'max_players' => $tournament->max_players ?? null,
            'participants_count' => $tournament->relationLoaded('participants') ? $tournament->participants->count() : null,
            'gender' => $tournament->gender ?? null,
            'gender_text' => $tournament->gender_text ?? null,
            'status' => $tournament->status ?? null,
            'status_text' => $tournament->status_text ?? null,
            'is_private' => (bool) ($tournament->is_private ?? false),
            'apply_rule' => (bool) ($tournament->apply_rule ?? false),
            'created_at' => $tournament->created_at?->toISOString() ?? ($tournament->created_at ?? null),
        ];
    }

    private function formatTournament(): array
    {
        // Array format
        if (is_array($this->resource)) {
            return [
                'club_id' => $this->resource['club_id'] ?? null,
                'name' => $this->resource['name'] ?? null,
                'description' => $this->resource['description'] ?? null,
                'start_date' => $this->resource['start_date'] ?? null,
                'end_date' => $this->resource['end_date'] ?? null,
                'poster' => $this->resource['poster'] ?? null,
                'sport' => $this->resource['sport'] ?? null,
                'format' => $this->resource['format'] ?? null,
                'has_fee' => (bool) ($this->resource['has_fee'] ?? false),
                'fee_amount' => isset($this->resource['fee_amount']) ? (float) $this->resource['fee_amount'] : null,
                'max_teams' => $this->resource['max_teams'] ?? null,
                'participants_count' => $this->resource['participants_count'] ?? null,
                'status' => $this->resource['status'] ?? null,
                'created_at' => $this->resource['created_at'] ?? null,
            ];
        }

        // Model format
        $tournament = $this->resource;
        return [
            'club_id' => $tournament->club_id ?? null,
            'name' => $tournament->name ?? null,
            'description' => $tournament->description ?? null,
            'start_date' => $tournament->start_date?->format('c') ?? ($tournament->start_date ?? null),
            'end_date' => $tournament->end_date?->format('c') ?? ($tournament->end_date ?? null),
            'poster' => $tournament->poster ?? null,
            'sport' => $tournament->sport ?? null,
            'format' => $tournament->format ?? null,
            'has_fee' => (bool) ($tournament->has_fee ?? false),
            'fee_amount' => $tournament->fee_amount !== null ? (float) $tournament->fee_amount : null,
            'max_teams' => $tournament->max_teams ?? null,
            'participants_count' => $tournament->participants_count ?? null,
            'status' => $tournament->status ?? null,
            'created_at' => $tournament->created_at?->toISOString() ?? ($tournament->created_at ?? null),
        ];
    }
}
