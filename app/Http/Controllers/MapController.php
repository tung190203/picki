<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\MiniTournamentResource;
use App\Http\Resources\TournamentResource;
use App\Models\MiniTournament;
use App\Models\Tournament;
use Illuminate\Http\Request;

class MapController extends Controller
{
    private const VALIDATION_RULE = 'nullable';
    public function getMatch(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'nullable',
            'lng' => 'nullable',
            'radius' => 'nullable|numeric|min:1',
            'minLat' => self::VALIDATION_RULE,
            'maxLat' => self::VALIDATION_RULE,
            'minLng' => self::VALIDATION_RULE,
            'maxLng' => self::VALIDATION_RULE,
            'mini_tournament_per_page' => 'nullable|integer|min:1|max:200',
            'mini_tournament_page' => 'nullable|integer|min:1',
            'tournament_per_page' => 'nullable|integer|min:1|max:200',
            'tournament_page' => 'nullable|integer|min:1',
            'is_map' => 'nullable|boolean',
            'date_from' => 'nullable|date',
            'location_id' => 'nullable|integer|exists:locations,id',
            'sport_id' => 'nullable|integer|exists:sports,id',
            'keyword' => 'nullable|string|max:255',
            'rating' => 'nullable',
            'rating.*' => 'integer',
            'time_of_day' => 'nullable|array',
            'time_of_day.*' => 'in:morning,afternoon,evening',
            'slot_status' => 'nullable|array',
            'slot_status.*' => 'in:con_trong,da_day',
            'type' => 'nullable|array',
            'type.*' => 'in:single,double',
            'fee' => 'nullable|array',
            'fee.*' => 'in:free,paid',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
        ]);
        $today = now()->toDateString();

        $miniTournamentQuery = MiniTournament::withFullRelations()
            ->whereDate('start_time', '>=', $today)
            ->filter($validated);

        $tournamentQuery = Tournament::withFullRelations()
            ->whereDate('start_date', '>=', $today)
            ->filter($validated);
        $hasMiniTournamentFilter = collect([
            'sport_id', 'location_id', 'date_from', 'keyword',
            'lat', 'lng', 'radius', 'type', 'rating', 'fee',
            'min_price', 'max_price', 'time_of_day', 'slot_status',
        ])->some(fn($key) => $request->filled($key));

        $hasTournamentFilter = collect([
            'sport_id', 'location_id', 'date_from', 'keyword',
            'lat', 'lng', 'radius', 'rating', 'fee',
            'min_price', 'max_price', 'time_of_day', 'slot_status',
        ])->some(fn($key) => $request->filled($key));

        if (!$hasMiniTournamentFilter && (!empty($validated['minLat']) || !empty($validated['maxLat']) || !empty($validated['minLng']) || !empty($validated['maxLng']))) {
            $miniTournamentQuery->inBounds(
                $validated['minLat'] ?? null,
                $validated['maxLat'] ?? null,
                $validated['minLng'] ?? null,
                $validated['maxLng'] ?? null,
            );
        }
        if (!$hasTournamentFilter && (!empty($validated['minLat']) || !empty($validated['maxLat']) || !empty($validated['minLng']) || !empty($validated['maxLng']))) {
            $tournamentQuery->inBounds(
                $validated['minLat'] ?? null,
                $validated['maxLat'] ?? null,
                $validated['minLng'] ?? null,
                $validated['maxLng'] ?? null,
            );
        }
        if (!empty($validated['lat']) && !empty($validated['lng'])) {
            $miniTournamentQuery->orderByDistanceFromLocation($validated['lat'], $validated['lng']);
            $tournamentQuery->orderByDistanceFromLocation($validated['lat'], $validated['lng']);
        }        
        if (!empty($validated['lat']) && !empty($validated['lng']) && !empty($validated['radius'])) {
            $miniTournamentQuery->nearBy($validated['lat'], $validated['lng'], $validated['radius']);
            $tournamentQuery->nearBy($validated['lat'], $validated['lng'], $validated['radius']);
        }
        $isMap = filter_var($validated['is_map'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($isMap) {
            $miniTournament = $miniTournamentQuery->get();
            $miniTournamentMeta = [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $miniTournament->count(),
                'total' => $miniTournament->count(),
            ];
            $miniTournamentData = [
                'data' => MiniTournamentResource::collection($miniTournament),
                'meta' => $miniTournamentMeta,
            ];
            $tournament = $tournamentQuery->get();
            $tournamentMeta = [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $tournament->count(),
                'total' => $tournament->count(),
            ];
            $tournamentData = [
                'data' => TournamentResource::collection($tournament),
                'meta' => $tournamentMeta,
            ];
        } else {
            $miniTournament = $miniTournamentQuery->paginate(
                $validated['mini_tournament_per_page'] ?? MiniTournament::PER_PAGE,
                ['*'],
                'mini_tournament_page',
                $validated['mini_tournament_page'] ?? 1
            );
            $miniTournamentMeta = [
                'current_page' => $miniTournament->currentPage(),
                'last_page' => $miniTournament->lastPage(),
                'per_page' => $miniTournament->perPage(),
                'total' => $miniTournament->total(),
            ];
            $miniTournamentData = [
                'data' => MiniTournamentResource::collection($miniTournament->items()),
                'meta' => $miniTournamentMeta,
            ];
            $tournament = $tournamentQuery->paginate(
                $validated['tournament_per_page'] ?? Tournament::PER_PAGE,
                ['*'],
                'tournament_page',
                $validated['tournament_page'] ?? 1
            );
            $tournamentMeta = [
                'current_page' => $tournament->currentPage(),
                'last_page' => $tournament->lastPage(),
                'per_page' => $tournament->perPage(),
                'total' => $tournament->total(),
            ];
            $tournamentData = [
                'data' => TournamentResource::collection($tournament->items()),
                'meta' => $tournamentMeta,
            ];
        }
        $data = [
            'mini_tournaments' => $miniTournamentData,
            'tournaments' => $tournamentData,
        ];

        return ResponseHelper::success($data, 'Lấy dữ liệu thành công', 200);
    }
}
