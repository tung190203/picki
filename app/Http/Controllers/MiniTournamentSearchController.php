<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Traits\MapSearchTrait;
use App\Http\Resources\Map\MapMiniTournamentResource;
use App\Http\Resources\MiniTournamentResource;
use App\Models\MiniTournament;
use Illuminate\Http\Request;

class MiniTournamentSearchController extends Controller
{
    use MapSearchTrait;

    public function search(Request $request)
    {
        $validated = $request->validate($this->baseValidationRules());

        $filter = $this->buildFilter($request, 'mini');

        $userId = auth()->check() ? auth()->id() : null;
        $subTab = $request->input('sub_tab');

        $query = MiniTournament::withFullRelations()
            ->whereDate('start_time', '>=', now()->toDateString())
            ->filter($filter)
            ->applyTimeline($subTab, $userId);

        $this->applyGeoFilters($query, $request, 'mini');

        $isMap = filter_var($request->input('map_mode') ?? $request->input('is_map') ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isMap) {
            $items = $query
                ->select([
                    'id', 'name', 'poster', 'start_time', 'end_time',
                    'has_fee', 'fee_amount', 'max_players', 'sport_id', 'status',
                ])
                ->with(['sport', 'competitionLocation'])
                ->withCount('participants')
                ->get();

            return ResponseHelper::success([
                'data' => MapMiniTournamentResource::collection($items),
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => $items->count(),
                    'total'        => $items->count(),
                    'map_mode'     => true,
                ],
            ], 'Lấy dữ liệu mini-tournament thành công', 200);
        }

        $result = $this->paginateOrGet($query, $request, 'mini');

        return ResponseHelper::success([
            'data' => MiniTournamentResource::collection($result['data']),
            'meta' => $result['meta'],
        ], 'Lấy dữ liệu mini-tournament thành công', 200);
    }
}
