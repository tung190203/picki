<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Club\Club;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPushNotificationLookupController extends Controller
{
    public function lookupClubs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'page' => ['integer', 'min:1'],
            'limit' => ['integer', 'min:1', 'max:50'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $limit = (int) ($validated['limit'] ?? 20);
        $keyword = $validated['keyword'] ?? '';

        $query = Club::query()
            ->whereNull('deleted_at')
            ->where('is_banned', false);

        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        $query->orderBy('name', 'asc');

        $paginated = $query->paginate($limit, ['id', 'name', 'avatar', 'logo_url'], 'page', $page);

        return ResponseHelper::paginated(
            $paginated->getCollection()->map(fn($club) => [
                'id' => $club->id,
                'name' => $club->name,
                'logo_url' => $club->logo_url ?? $club->avatar,
            ])->toArray(),
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }

    public function lookupUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'page' => ['integer', 'min:1'],
            'limit' => ['integer', 'min:1', 'max:50'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $limit = (int) ($validated['limit'] ?? 20);
        $keyword = $validated['keyword'] ?? '';

        $query = User::query()
            ->where('is_banned', false)
            ->where('is_guest', false)
            ->where('is_merged', false)
            ->whereNull('deleted_at');

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('id', $keyword);
            });
        }

        $query->orderBy('last_active_at', 'desc');

        $paginated = $query->paginate($limit, ['id', 'full_name', 'avatar_url', 'phone', 'email', 'is_online'], 'page', $page);

        return ResponseHelper::paginated(
            $paginated->getCollection()->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->full_name,
                'avatar_url' => $user->avatar_url,
                'phone' => $user->phone,
                'email' => $user->email,
                'is_online' => (bool) $user->is_online,
            ])->toArray(),
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }
}