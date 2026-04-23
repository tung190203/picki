<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
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

        if (!$user->is_super_admin) {
            return ResponseHelper::error('Forbidden: Super Admin access required', 403);
        }

        return $next($request);
    }
}
