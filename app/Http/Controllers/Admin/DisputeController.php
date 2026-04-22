<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
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

        $disputes = \App\Models\Dispute::query()
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
}
