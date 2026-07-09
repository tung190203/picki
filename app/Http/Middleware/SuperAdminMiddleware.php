<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return ResponseHelper::error('Unauthenticated', 401);
        }

        // OPTIMIZED: Use cached value instead of DB query on every request
        // The is_super_admin attribute is already loaded from JWT claims or database
        // Cache for 5 minutes to avoid repeated checks
        $cacheKey = "user_is_super_admin:{$user->id}";
        $isSuperAdmin = Cache::remember($cacheKey, 300, function () use ($user) {
            return (bool) $user->is_super_admin;
        });

        if (!$isSuperAdmin) {
            return ResponseHelper::error('Forbidden: Super Admin access required', 403);
        }

        return $next($request);
    }
}
