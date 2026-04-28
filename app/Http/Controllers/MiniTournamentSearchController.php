<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Traits\MapSearchTrait;
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

        $query = MiniTournament::withFullRelations()
            ->whereDate('start_time', '>=', now()->toDateString())
            ->filter($filter);

        $this->applyGeoFilters($query, $request, 'mini');

        $result = $this->paginateOrGet($query, $request, 'mini');

        return ResponseHelper::success([
            'data' => MiniTournamentResource::collection($result['data']),
            'meta' => $result['meta'],
        ], 'Lấy dữ liệu mini-tournament thành công', 200);
    }
}
