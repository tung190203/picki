<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CompetitionLocation extends Model
{
    use HasFactory;

    protected $table = 'competition_locations';

    protected $fillable = [
        'name',
        'location_id',
        'latitude',
        'longitude',
        'image',
        'address',
        'phone',
        'opening_time',
        'closing_time',
        'note_booking',
        'website',
        'avatar_url'
    ];

    const PER_PAGE = 15;

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
    public function miniTournaments()
    {
        return $this->hasMany(MiniTournament::class, 'location_id');
    }

    public function sports()
    {
        return $this->belongsToMany(Sport::class, 'competition_location_sport', 'competition_location_id', 'sport_id');
    }

    public function follows()
    {
        return $this->morphMany(Follow::class, 'followable');
    }

    public function competitionLocationYards()
    {
        return $this->hasMany(CompetitionLocationYard::class, 'competition_location_id');
    }

    public function facilities()
{
        return $this->belongsToMany(Facility::class, 'competition_location_facility', 'competition_location_id', 'facility_id');
}


    public static function scopeWithFullRelations($query)
    {
        return $query->with(['location', 'sports', 'follows', 'competitionLocationYards','facilities']);
    }

    public function scopeInBounds($query, $minLat, $maxLat, $minLng, $maxLng)
    {
        return $query->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng]);
    }

    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when(
                !empty($filters['sport_id']),
                fn($q) => $q->whereHas('sports', fn($sq) => $sq->where('sports.id', $filters['sport_id']))
            )
            ->when(
                !empty($filters['location_id']),
                fn($q) => $q->where('location_id', $filters['location_id'])
            )
            ->when(
                !empty($filters['keyword']),
                fn($q) => $q->where(function ($sub) use ($filters) {
                    $sub->where('name', 'like', '%' . $filters['keyword'] . '%')
                        ->orWhere('address', 'like', '%' . $filters['keyword'] . '%')
                        ->orWhereHas(
                            'location',
                            fn($lq) =>
                            $lq->where('name', 'like', '%' . $filters['keyword'] . '%')
                        );
                })
            )->when(
                isset($filters['is_followed']) && auth()->check(),
                fn($q) => $filters['is_followed']
                ? $q->whereHas('follows', fn($fq) => $fq->where('user_id', auth()->id()))
                : $q->whereDoesntHave('follows', fn($fq) => $fq->where('user_id', auth()->id()))
            )
            ->when(
                !empty($filters['number_of_yards']),
                fn($q) => $q->withCount('competitionLocationYards')
                             ->having('competition_location_yards_count', '>=', $filters['number_of_yards'])
            )            
            ->when(
                !empty($filters['yard_type']) && is_array($filters['yard_type']),
                fn($q) => $q->whereHas('competitionLocationYards', fn($yq) => $yq->whereIn('yard_type', $filters['yard_type']))
            )
            ->when(
                !empty($filters['facility_id']) && is_array($filters['facility_id']),
                fn($q) => $q->whereHas('facilities', fn($fq) => $fq->whereIn('facilities.id', $filters['facility_id']))
            );
    }

    public function scopeNearBy($query, float $lat, float $lng, float $radiusKm)
    {
        $haversine = "(6371 * acos(cos(radians($lat))
                * cos(radians(latitude))
                * cos(radians(longitude) - radians($lng))
                + sin(radians($lat))
                * sin(radians(latitude))))";

        return $query->select('*')
            ->selectRaw("$haversine AS distance")
            ->having('distance', '<=', $radiusKm)
            ->orderBy('distance');
    }
    public function scopeOrderByDistance($query, $lat, $lng)
    {
        return $query
            ->addSelect('*')
            ->addSelect(DB::raw("
                (
                    6371 * acos(
                        cos(radians($lat))
                        * cos(radians(latitude))
                        * cos(radians(longitude) - radians($lng))
                        + sin(radians($lat))
                        * sin(radians(latitude))
                    )
                )
            AS distance"))
            ->orderByRaw('(latitude IS NULL OR longitude IS NULL)')
            ->orderBy('distance', 'asc');
    }
}
