<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\CompetitionLocationResource;
use App\Http\Resources\FacilityResource;
use App\Models\CompetitionLocation;
use App\Models\CompetitionLocationYard;
use App\Models\Facility;
use Illuminate\Http\Request;

class CompetitionLocationController extends Controller
{
    private const VALIDATION_RULE = 'nullable';
    /**
     * Lọc và lấy danh sách địa điểm thi đấu (trình lọc sân bóng)
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'nullable',
            'lng' => 'nullable',
            'radius' => 'nullable|numeric|min:1',
            'minLat' => self::VALIDATION_RULE,
            'maxLat' => self::VALIDATION_RULE,
            'minLng' => self::VALIDATION_RULE,
            'maxLng' => self::VALIDATION_RULE,
            'sport_id' => 'nullable|integer|exists:sports,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'number_of_yards' => 'nullable|array',
            'number_of_yards.*' => 'integer|min:1',
            'keyword' => 'nullable|string|max:255',
            'is_followed' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:200',
            'yard_type' => 'nullable|array',
            'yard_type.*' => 'integer|in:' . implode(',', CompetitionLocationYard::YARD_TYPE),
            'facility_id' => 'nullable|array',
            'facility_id.*' => 'integer|exists:facilities,id',
            'is_map' => 'nullable|boolean',
        ]);

        $query = CompetitionLocation::withFullRelations()->active();

        if (!empty($validated['lat']) && !empty($validated['lng'])) {
            $query->orderByDistance($validated['lat'], $validated['lng']);
        }
        // 1. NearBy
        if (!empty($validated['lat']) && !empty($validated['lng']) && !empty($validated['radius'])) {
            $query->nearBy($validated['lat'], $validated['lng'], $validated['radius']);
        }

        // 2. Filter
        $query->filter($validated);

        // 3. Bounding Box
        $hasFilter = collect([
            'sport_id',
            'location_id',
            'is_followed',
            'keyword',
            'lat',
            'lng',
            'radius',
            'number_of_yards',
            'yard_type',
            'facility_id'
        ])->some(fn($key) => $request->filled($key));

        if (
            !$hasFilter &&
            (!empty($validated['minLat']) || !empty($validated['maxLat']) ||
                !empty($validated['minLng']) || !empty($validated['maxLng']))
        ) {
            $query->inBounds(
                $validated['minLat'],
                $validated['maxLat'],
                $validated['minLng'],
                $validated['maxLng']
            );
        }

        // 4. Phân trang hoặc lấy toàn bộ (nếu is_map=true)
        if (!empty($validated['is_map']) && $validated['is_map']) {
            $locations = $query->get();
            $meta = [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $locations->count(),
                'total' => $locations->count(),
            ];
        } else {
            $locations = $query->paginate($validated['per_page'] ?? CompetitionLocation::PER_PAGE);
            $meta = [
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
            ];
        }

        // 5. Các dữ liệu bổ sung
        $yardTypes = CompetitionLocationYard::select('yard_type')
            ->distinct()
            ->get()
            ->map(fn($yard) => [
                'id' => $yard->yard_type,
                'name' => $yard->yard_type_name,
            ]);

        $data = [
            'competition_locations' => CompetitionLocationResource::collection($locations),
            'yard_types' => $yardTypes,
            'facilities' => FacilityResource::collection(Facility::all()),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách địa điểm thi đấu thành công', 200, $meta);
    }
}
