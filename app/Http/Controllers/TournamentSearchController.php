<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Traits\MapSearchTrait;
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

        $query = Tournament::withFullRelations()
            ->whereDate('start_date', '>=', now()->toDateString())
            ->filter($filter);

        $this->applyGeoFilters($query, $request, 'tournament');

        $result = $this->paginateOrGet($query, $request, 'tournament');

        return ResponseHelper::success([
            'data' => TournamentResource::collection($result['data']),
            'meta' => $result['meta'],
        ], 'Lấy dữ liệu tournament thành công', 200);
    }
}
