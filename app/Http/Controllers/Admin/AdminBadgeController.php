<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBadgeController extends Controller
{
    public function index(Request $request)
    {
        $badges = Badge::query()
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($subQ) use ($request) {
                    $subQ->where('name', 'like', '%' . $request->search . '%')
                         ->orWhere('code', 'like', '%' . $request->search . '%');
                });
            })
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->orderBy('created_at', 'desc')
            ->orderBy('priority', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $badges->items(),
            'meta' => [
                'current_page' => $badges->currentPage(),
                'last_page' => $badges->lastPage(),
                'total' => $badges->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:badges,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|image|max:2048',
            'icon_url' => 'nullable|string',
            'type' => 'required|string|max:50',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('icon')) {
            $path = $request->file('icon')->store('badges', 'public');
            $validated['icon_url'] = '/storage/' . $path;
        }

        $badge = Badge::create($validated);

        return response()->json(['data' => $badge], 201);
    }

    public function show(Badge $badge)
    {
        return response()->json(['data' => $badge]);
    }

    public function update(Request $request, Badge $badge)
    {
        $validated = $request->validate([
            'code' => ['string', Rule::unique('badges', 'code')->ignore($badge->id)],
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|image|max:2048',
            'icon_url' => 'nullable|string',
            'type' => 'string|max:50',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('icon')) {
            $path = $request->file('icon')->store('badges', 'public');
            $validated['icon_url'] = '/storage/' . $path;
        }

        $badge->update($validated);

        return response()->json(['data' => $badge]);
    }

    public function destroy(Badge $badge)
    {
        $usersCount = \DB::table('user_badges')->where('badge_id', $badge->id)->count();

        if ($usersCount > 0) {
            return response()->json([
                'message' => "Không thể xoá! Đang có {$usersCount} người dùng sở hữu huy hiệu này."
            ], 400);
        }

        $badge->delete();
        return response()->json(['message' => 'Badge deleted successfully']);
    }

    public function assignToUser(Request $request, int $userId)
    {
        $request->validate(['code' => 'required|string|exists:badges,code']);
        $badgeService = app(BadgeService::class);
        $userBadge = $badgeService->awardBadge($userId, $request->code, auth()->id());
        
        return response()->json(['message' => 'Assigned successfully', 'data' => $userBadge]);
    }

    public function revokeFromUser(int $userId, string $code)
    {
        $badgeService = app(BadgeService::class);
        $badgeService->revokeBadge($userId, $code);
        
        return response()->json(['message' => 'Revoked successfully']);
    }
}