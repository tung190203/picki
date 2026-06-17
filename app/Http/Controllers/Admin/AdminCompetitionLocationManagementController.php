<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
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
}
