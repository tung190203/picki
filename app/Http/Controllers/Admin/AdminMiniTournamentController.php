<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\MiniTournament;
use App\Services\Admin\MiniTournamentManagementService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminMiniTournamentController extends Controller
{
    public function __construct(
        protected MiniTournamentManagementService $miniTournamentService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'keyword' => 'nullable|string',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        $data = $this->miniTournamentService->search(
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
        $miniTournament = MiniTournament::findOrFail($id);
        $admin = auth()->user();

        $this->miniTournamentService->approve($miniTournament, $admin);

        return ResponseHelper::success(null, 'Duyệt kèo đấu thành công');
    }

    public function feature(int $id)
    {
        $miniTournament = MiniTournament::findOrFail($id);
        $admin = auth()->user();

        $this->miniTournamentService->feature($miniTournament, $admin);

        return ResponseHelper::success(null, 'Nổi bật kèo đấu thành công');
    }

    public function unfeature(int $id)
    {
        $miniTournament = MiniTournament::findOrFail($id);
        $admin = auth()->user();

        $this->miniTournamentService->unfeature($miniTournament, $admin);

        return ResponseHelper::success(null, 'Bỏ nổi bật kèo đấu thành công');
    }

    public function destroy(int $id)
    {
        $miniTournament = MiniTournament::findOrFail($id);
        $admin = auth()->user();

        $this->miniTournamentService->delete($miniTournament, $admin);

        return ResponseHelper::success(null, 'Xóa kèo đấu thành công');
    }
}
