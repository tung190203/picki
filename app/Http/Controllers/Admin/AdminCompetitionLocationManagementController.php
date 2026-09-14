<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminCompetitionLocationResource;
use App\Models\CompetitionLocation;
use App\Services\Admin\AdminCompetitionLocationManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCompetitionLocationManagementController extends Controller
{
    public function __construct(
        protected AdminCompetitionLocationManagementService $locationService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'keyword' => 'nullable|string|max:255',
            'sport_id' => 'nullable|integer|min:1',
            'status' => 'nullable|string',
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'active_matches_count', 'active_tournaments_count'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        $filters = array_filter([
            'keyword' => $validated['keyword'] ?? null,
            'sport_id' => $validated['sport_id'] ?? null,
            'status' => $validated['status'] ?? null,
        ], fn($v) => $v !== null);

        $data = $this->locationService->search(
            $validated['page'] ?? 1,
            $validated['limit'] ?? 15,
            $filters,
            $validated['sort_by'] ?? 'created_at',
            $validated['sort_dir'] ?? 'desc'
        );

        return ResponseHelper::paginated(
            $data->items(),
            [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ]
        );
    }

    public function show(int $id)
    {
        try {
            $data = $this->locationService->getOne($id);
            return ResponseHelper::single($data);
        } catch (ModelNotFoundException) {
            return ResponseHelper::error('Competition location not found.', 404);
        }
    }

    public function toggleBan(int $id, Request $request)
    {
        $validated = $request->validate([
            'is_banned' => ['required', 'boolean'],
        ]);

        $location = CompetitionLocation::find($id);

        if (!$location) {
            return ResponseHelper::error('Competition location not found.', 404);
        }

        $this->locationService->toggleBan($location, $validated['is_banned']);

        return ResponseHelper::success(null, $validated['is_banned']
            ? 'Competition location has been banned successfully.'
            : 'Competition location has been unbanned successfully.');
    }

    public function store(Request $request)
    {
        $this->parseJsonFields($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location_id' => 'nullable|integer|exists:locations,id',
            'address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:50',
            'opening_time' => 'nullable|string|max:50',
            'closing_time' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'website' => 'nullable|string|max:500',
            'note_booking' => 'nullable|string',
            'image' => 'nullable',
            'sport_ids' => 'nullable|array',
            'sport_ids.*' => 'integer|exists:sports,id',
            'facility_ids' => 'nullable|array',
            'facility_ids.*' => 'integer|exists:facilities,id',
            'yards' => 'nullable|array',
            'yards.*.yard_number' => 'nullable|string|max:50',
            'yards.*.yard_type' => 'nullable|integer',
        ]);

        $imageFile = $request->file('image');
        $location = $this->locationService->createLocation($validated, $imageFile);

        return ResponseHelper::single(
            (new AdminCompetitionLocationResource($location))->resolve(),
            'Thêm sân thi đấu thành công'
        );
    }

    public function update(Request $request, int $id)
    {
        $location = CompetitionLocation::find($id);
        if (!$location) {
            return ResponseHelper::error('Competition location not found.', 404);
        }

        $this->parseJsonFields($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location_id' => 'nullable|integer|exists:locations,id',
            'address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:50',
            'opening_time' => 'nullable|string|max:50',
            'closing_time' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'website' => 'nullable|string|max:500',
            'note_booking' => 'nullable|string',
            'image' => 'nullable',
            'sport_ids' => 'nullable|array',
            'sport_ids.*' => 'integer|exists:sports,id',
            'facility_ids' => 'nullable|array',
            'facility_ids.*' => 'integer|exists:facilities,id',
            'yards' => 'nullable|array',
            'yards.*.id' => 'nullable|integer',
            'yards.*.yard_number' => 'nullable|string|max:50',
            'yards.*.yard_type' => 'nullable|integer',
        ]);

        $imageFile = $request->file('image');
        $updatedLocation = $this->locationService->updateLocation($location, $validated, $imageFile);

        return ResponseHelper::single(
            (new AdminCompetitionLocationResource($updatedLocation))->resolve(),
            'Cập nhật sân thi đấu thành công'
        );
    }

    public function destroy(int $id)
    {
        $location = CompetitionLocation::find($id);
        if (!$location) {
            return ResponseHelper::error('Competition location not found.', 404);
        }

        $this->locationService->deleteLocation($location);

        return ResponseHelper::success(null, 'Xóa sân thi đấu thành công');
    }

    private function parseJsonFields(Request $request): void
    {
        foreach (['sport_ids', 'facility_ids', 'yards'] as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $decoded = json_decode($request->input($field), true);
                if (is_array($decoded)) {
                    $request->merge([$field => $decoded]);
                }
            }
        }
    }
}
