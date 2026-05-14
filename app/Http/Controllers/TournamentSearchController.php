<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Traits\MapSearchTrait;
use App\Http\Resources\Map\MapTournamentResource;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use Illuminate\Http\Request;

class TournamentSearchController extends Controller
{
    use MapSearchTrait;

    public function search(Request $request)
    {
        $validated = $request->validate($this->baseValidationRules());

        $filter = $this->buildFilter($request, 'tournament');

        $userId = auth()->check() ? auth()->id() : null;
        $subTab = $request->input('sub_tab');

        $query = Tournament::withFullRelations()
            ->whereDate('start_date', '>=', now()->toDateString())
            ->filter($filter)
            ->applyTimeline($subTab, $userId);

        $this->applyGeoFilters($query, $request, 'tournament');

        $isMap = filter_var($request->input('map_mode') ?? $request->input('is_map') ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isMap) {
            $items = $query
                ->select([
                    'id', 'name', 'poster', 'start_date', 'start_date',
                    'has_fee', 'fee_amount', 'max_player', 'sport_id', 'status',
                ])
                ->with(['sport', 'competitionLocation'])
                ->withCount('participants')
                ->get();

            return ResponseHelper::success([
                'data' => MapTournamentResource::collection($items),
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => $items->count(),
                    'total'        => $items->count(),
                    'map_mode'     => true,
                ],
            ], 'Lấy dữ liệu tournament thành công', 200);
        }

        $result = $this->paginateOrGet($query, $request, 'tournament');

        return ResponseHelper::success([
            'data' => TournamentResource::collection($result['data']),
            'meta' => $result['meta'],
        ], 'Lấy dữ liệu tournament thành công', 200);
    }
}
