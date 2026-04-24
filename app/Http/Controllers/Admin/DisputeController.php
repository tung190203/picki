<?php

namespace App\Http\Controllers\Admin;

use App\Events\SuperAdmin\DisputeOpened;
use App\Events\SuperAdmin\DisputeResolved;
use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\Dispute;
use App\Models\MiniMatch;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:open,resolved,rejected',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        $disputes = Dispute::query()
            ->with(['user'])
            ->when($validated['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->paginate($validated['limit'] ?? 15, ['*'], 'page', $validated['page'] ?? 1);

        return ResponseHelper::paginated(
            $disputes->items(),
            [
                'current_page' => $disputes->currentPage(),
                'per_page' => $disputes->perPage(),
                'total' => $disputes->total(),
                'last_page' => $disputes->lastPage(),
            ]
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|integer',
            'content' => 'nullable|string|max:1000',
        ]);

        $match = MiniMatch::findOrFail($data['match_id']);

        $dispute = Dispute::create([
            'match_id' => $data['match_id'],
            'raised_by' => auth()->id(),
            'content' => $data['content'] ?? '',
            'status' => 'open',
        ]);

        DisputeOpened::dispatch(
            $dispute->id,
            $dispute->match_id,
            $match->mini_tournament_id,
            $dispute->content
        );

        return ResponseHelper::success(
            $dispute->load('user'),
            'Khiếu nại đã được gửi',
            201
        );
    }

    public function resolve(Request $request, $id)
    {
        $data = $request->validate([
            'resolution' => 'nullable|string|max:1000',
        ]);

        $dispute = Dispute::findOrFail($id);

        if ($dispute->status !== 'open') {
            return ResponseHelper::error('Khiếu nại không ở trạng thái mở', 400);
        }

        $dispute->update([
            'status' => 'resolved',
            'handled_by' => auth()->id(),
        ]);

        DisputeResolved::dispatch(
            $dispute->id,
            $data['resolution'] ?? ''
        );

        return ResponseHelper::success($dispute->load('user', 'handler'), 'Khiếu nại đã được xử lý');
    }
}
