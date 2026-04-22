<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Services\Admin\BroadcastService;
use Illuminate\Http\Request;

class BroadcastController extends Controller
{
    public function __construct(
        protected BroadcastService $broadcastService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 15;

        $data = $this->broadcastService->index($page, $limit);

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

    public function send(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'target' => 'required|in:all,users,group',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'group' => 'nullable|in:new_users,active_users,inactive_users',
        ]);

        $admin = auth()->user();
        $count = $this->broadcastService->send($validated, $admin);

        return ResponseHelper::success([
            'sent_count' => $count,
        ], "Gửi broadcast thành công tới {$count} người dùng");
    }
}
