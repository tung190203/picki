<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'action' => 'nullable|string',
            'target_type' => 'nullable|string',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        $query = AuditLog::with('user')
            ->orderBy('created_at', 'desc');

        if (!empty($validated['action'])) {
            $query->byAction($validated['action']);
        }

        if (!empty($validated['target_type'])) {
            $query->where('target_type', $validated['target_type']);
        }

        $data = $query->paginate(
            $validated['limit'] ?? 15,
            ['*'],
            'page',
            $validated['page'] ?? 1
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
