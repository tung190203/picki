<?php

namespace App\Http\Controllers\Admin;

use App\Events\SuperAdmin\TournamentCreated;
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

    public function testSocket()
    {
        // Dùng anonymous class thay vì stdClass để có method participants() giả
        $testTournament = new class {
            public $id = 999999;
            public $name = '[TEST] Socket Test Tournament';
            public $description = 'Test event from AdminDashboard';
            public $status = 1;
            public $status_text = 'Bản nháp';
            public $sport = null;
            public $club = null;
            public $createdBy = null;
            public $start_date;
            public $end_date = null;
            public $poster_url = null;
            public $created_at;
            public $updated_at;

            public function participants()
            {
                return collect([]);
            }
        };

        $testTournament->start_date = now()->toDateString();
        $testTournament->created_at = now();
        $testTournament->updated_at = now();

        TournamentCreated::dispatch($testTournament);

        return ResponseHelper::success(null, 'Test socket event dispatched!');
    }
}
