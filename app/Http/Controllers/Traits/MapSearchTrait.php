<?php

namespace App\Http\Controllers\Traits;

use App\Models\MiniTournament;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait MapSearchTrait
{
    /**
     * Các filter key chỉ áp dụng cho mini-tournament.
     */
    protected function miniOnlyFilterKeys(): array
    {
        return ['type'];
    }

    /**
     * Các filter key chỉ áp dụng cho tournament.
     */
    protected function tournamentOnlyFilterKeys(): array
    {
        return ['min_price', 'max_price'];
    }

    /**
     * Build filter array từ request, loại bỏ filter không áp dụng cho model.
     */
    protected function buildFilter(Request $request, string $modelType = 'both'): array
    {
        $allKeys = [
            'sport_id', 'location_id', 'date_from', 'keyword',
            'rating', 'time_of_day', 'slot_status',
            'type', 'fee', 'min_price', 'max_price',
        ];

        $filter = [];
        foreach ($allKeys as $key) {
            if ($request->has($key) || $request->filled($key)) {
                $filter[$key] = $request->input($key);
            }
        }

        if ($modelType === 'mini') {
            foreach ($this->tournamentOnlyFilterKeys() as $key) {
                unset($filter[$key]);
            }
        } elseif ($modelType === 'tournament') {
            foreach ($this->miniOnlyFilterKeys() as $key) {
                unset($filter[$key]);
            }
        }

        return $filter;
    }

    /**
     * Resolve pagination params cho một model cụ thể.
     * Ưu tiên: page/perPage (mới) > fallback param cũ > default.
     *
     * @param Request $request
     * @param string  $type 'mini' | 'tournament'
     * @return array ['page' => int, 'perPage' => int]
     */
    protected function resolvePagination(Request $request, string $type): array
    {
        $defaults = [
            'mini'      => MiniTournament::PER_PAGE,
            'tournament' => Tournament::PER_PAGE,
        ];

        $oldParams = [
            'mini'      => ['page' => 'mini_tournament_page',    'perPage' => 'mini_tournament_per_page'],
            'tournament' => ['page' => 'tournament_page',         'perPage' => 'tournament_per_page'],
        ];

        // Ưu tiên: page/perPage mới
        if ($request->has('page') || $request->has('perPage')) {
            $page    = max(1, (int) $request->input('page', 1));
            $perPage = max(1, min(200, (int) $request->input('perPage', $defaults[$type])));
            return ['page' => $page, 'perPage' => $perPage];
        }

        // Fallback: param cũ
        $oldPage    = $request->input($oldParams[$type]['page']);
        $oldPerPage = $request->input($oldParams[$type]['perPage']);

        if ($oldPage !== null || $oldPerPage !== null) {
            $page    = max(1, (int) ($oldPage ?? 1));
            $perPage = max(1, min(200, (int) ($oldPerPage ?? $defaults[$type])));
            return ['page' => $page, 'perPage' => $perPage];
        }

        // Default
        return ['page' => 1, 'perPage' => $defaults[$type]];
    }

    /**
     * Áp dụng các geo filters lên query.
     */
    protected function applyGeoFilters(Builder $query, Request $request, string $modelType): Builder
    {
        $lat  = $request->input('lat');
        $lng  = $request->input('lng');
        $radius = $request->input('radius');

        // Order by distance (always if lat+lng present)
        if ($lat !== null && $lng !== null) {
            if ($modelType === 'mini') {
                $query->orderByDistanceFromLocation((float) $lat, (float) $lng);
            } else {
                $query->orderByDistanceFromLocation((float) $lat, (float) $lng);
            }
        }

        // Radius filter (only when lat+lng+radius all present)
        if ($lat !== null && $lng !== null && $radius !== null) {
            if ($modelType === 'mini') {
                $query->nearBy((float) $lat, (float) $lng, (float) $radius);
            } else {
                $query->nearBy((float) $lat, (float) $lng, (float) $radius);
            }
        }

        // Bounds filter (only when no other filter is present)
        $filterKeys = ['sport_id', 'location_id', 'date_from', 'keyword', 'lat', 'lng', 'radius', 'type', 'rating', 'fee', 'time_of_day', 'slot_status'];
        if ($modelType === 'tournament') {
            $filterKeys = array_merge($filterKeys, ['min_price', 'max_price']);
        }
        $hasFilter = collect($filterKeys)->some(fn($key) => $request->filled($key));

        $minLat = $request->input('minLat');
        $maxLat = $request->input('maxLat');
        $minLng = $request->input('minLng');
        $maxLng = $request->input('maxLng');
        $hasBounds = $minLat !== null || $maxLat !== null || $minLng !== null || $maxLng !== null;

        if (!$hasFilter && $hasBounds) {
            $query->inBounds(
                $minLat !== null ? (float) $minLat : null,
                $maxLat !== null ? (float) $maxLat : null,
                $minLng !== null ? (float) $minLng : null,
                $maxLng !== null ? (float) $maxLng : null,
            );
        }

        return $query;
    }

    /**
     * Paginate hoặc get tất cả tuỳ is_map flag.
     * Trả về ['data' => Collection, 'meta' => array].
     */
    protected function paginateOrGet(Builder $query, Request $request, string $modelType): array
    {
        $isMap = filter_var($request->input('is_map', false), FILTER_VALIDATE_BOOLEAN);

        if ($isMap) {
            $items = $query->get();
            return [
                'data' => $items,
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => $items->count(),
                    'total'        => $items->count(),
                ],
            ];
        }

        $pagination = $this->resolvePagination($request, $modelType);
        $paginator = $query->paginate($pagination['perPage'], ['*'], 'page', $pagination['page']);

        return [
            'data' => collect($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
    }

    /**
     * Shared validation rules cho cả hai search endpoint.
     */
    protected function baseValidationRules(): array
    {
        return [
            'lat'              => 'nullable',
            'lng'              => 'nullable',
            'radius'           => 'nullable|numeric|min:1',
            'minLat'           => 'nullable',
            'maxLat'           => 'nullable',
            'minLng'           => 'nullable',
            'maxLng'           => 'nullable',
            'page'             => 'nullable|integer|min:1',
            'perPage'          => 'nullable|integer|min:1|max:200',
            'mini_tournament_page'    => 'nullable|integer|min:1',
            'mini_tournament_per_page'=> 'nullable|integer|min:1|max:200',
            'tournament_page'         => 'nullable|integer|min:1',
            'tournament_per_page'    => 'nullable|integer|min:1|max:200',
            'is_map'           => 'nullable|boolean',
            'date_from'        => 'nullable|date',
            'location_id'      => 'nullable|integer|exists:locations,id',
            'sport_id'         => 'nullable|integer|exists:sports,id',
            'keyword'          => 'nullable|string|max:255',
            'rating'           => 'nullable',
            'rating.*'         => 'integer',
            'time_of_day'      => 'nullable|array',
            'time_of_day.*'    => 'in:morning,afternoon,evening',
            'slot_status'      => 'nullable|array',
            'slot_status.*'    => 'in:one_slot,two_slot,full_slot',
            'type'             => 'nullable|array',
            'type.*'           => 'in:single,double',
            'fee'              => 'nullable|array',
            'fee.*'            => 'in:free,paid',
            'min_price'        => 'nullable|numeric|min:0',
            'max_price'        => 'nullable|numeric|min:0',
        ];
    }
}
