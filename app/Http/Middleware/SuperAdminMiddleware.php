<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return ResponseHelper::error('Unauthenticated', 401);
        }

        $isSuperAdmin = User::where('id', $user->id)->where('is_super_admin', true)->exists();

        if (!$isSuperAdmin) {
            return ResponseHelper::error('Forbidden: Super Admin access required', 403);
        }

        return $next($request);
    }
}
