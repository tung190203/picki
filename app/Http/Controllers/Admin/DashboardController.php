<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Services\Admin\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index()
    {
        $stats = $this->dashboardService->getStats();
        return ResponseHelper::single($stats, 'Lấy dashboard thành công');
    }

    public function lists(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:users,matches,tournaments,recent_new_users,open_mini_tournaments,open_tournaments',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'keyword' => 'nullable|string',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 15;

        $data = $this->dashboardService->getList(
            $validated['type'],
            $page,
            $limit,
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
}
