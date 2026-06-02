<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastLoginMiddleware
{
    /**
     * Chỉ cập nhật last_login / last_active_at nếu đã quá X phút (tránh ghi DB liên tục).
     * last_active_at: cập nhật thường xuyên hơn để track hoạt động thực tế.
     */
    private const THROTTLE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user instanceof User && $user->exists) {
            $lastLogin = $user->last_login;
            $shouldUpdate = !$lastLogin
                || $lastLogin->diffInMinutes(now()) >= self::THROTTLE_MINUTES;

            if ($shouldUpdate) {
                $user->update([
                    'last_login' => now(),
                    'last_active_at' => now(),
                ]);
            }
        }

        return $next($request);
    }
}
