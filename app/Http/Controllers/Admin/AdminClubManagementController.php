<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Club\Club;
use App\Services\Admin\AdminClubManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminClubManagementController extends Controller
{
    public function __construct(
        protected AdminClubManagementService $clubService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'keyword' => 'nullable|string|max:255',
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'suspended', 'draft'])],
            'is_verified' => 'nullable',
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'members_count', 'active_matches_count', 'active_tournaments_count'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        $filters = array_filter([
            'keyword' => $validated['keyword'] ?? null,
            'status' => $validated['status'] ?? null,
            'is_verified' => $validated['is_verified'] ?? null,
        ], fn($v) => $v !== null);

        $data = $this->clubService->search(
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
            $data = $this->clubService->getOne($id);
            return ResponseHelper::single($data);
        } catch (ModelNotFoundException) {
            return ResponseHelper::error('Club not found.', 404);
        }
    }

    public function updateStatus(int $id, Request $request)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['active', 'banned'])],
        ]);

        $club = Club::find($id);

        if (!$club) {
            return ResponseHelper::error('Club not found.', 404);
        }

        try {
            $this->clubService->updateStatus($club, $validated['status']);
            return ResponseHelper::success(null, 'Club status updated successfully.');
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }
}
