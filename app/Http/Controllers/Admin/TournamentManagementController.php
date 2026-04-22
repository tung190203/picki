<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\Tournament;
use App\Services\Admin\TournamentManagementService;
use Illuminate\Http\Request;

class TournamentManagementController extends Controller
{
    public function __construct(
        protected TournamentManagementService $tournamentManagementService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'keyword' => 'nullable|string',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        $data = $this->tournamentManagementService->search(
            $validated['page'] ?? 1,
            $validated['limit'] ?? 15,
            $validated['status'] ?? null,
            $validated['keyword'] ?? null
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

    public function approve(int $id)
    {
        $tournament = Tournament::findOrFail($id);
        $admin = auth()->user();

        $this->tournamentManagementService->approve($tournament, $admin);

        return ResponseHelper::success(null, 'Approve tournament thành công');
    }

    public function feature(int $id)
    {
        $tournament = Tournament::findOrFail($id);
        $admin = auth()->user();

        $this->tournamentManagementService->feature($tournament, $admin);

        return ResponseHelper::success(null, 'Feature tournament thành công');
    }

    public function unfeature(int $id)
    {
        $tournament = Tournament::findOrFail($id);
        $admin = auth()->user();

        $this->tournamentManagementService->unfeature($tournament, $admin);

        return ResponseHelper::success(null, 'Unfeature tournament thành công');
    }

    public function destroy(int $id)
    {
        $tournament = Tournament::findOrFail($id);
        $admin = auth()->user();

        $this->tournamentManagementService->delete($tournament, $admin);

        return ResponseHelper::success(null, 'Delete tournament thành công');
    }
}
