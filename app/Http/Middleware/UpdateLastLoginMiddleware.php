<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastLoginMiddleware
{
    private const ACTIVE_THRESHOLD_MINUTES = 1;

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user instanceof User && $user->exists) {
            $lastActive = $user->last_active_at;
            $shouldUpdate = !$lastActive
                || $lastActive->diffInMinutes(now()) >= self::ACTIVE_THRESHOLD_MINUTES;

            if ($shouldUpdate) {
                $wasOnline = $lastActive && $lastActive->diffInMinutes(now()) < 15;
                $user->update(['last_active_at' => now()]);
                if ($wasOnline === false) {
                    try {
                        event(new \App\Events\UserOnlineStatusChanged(
                            $user->id,
                            $user->full_name,
                            $user->avatar_url,
                            true
                        ));
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('UserOnlineStatusChanged broadcast failed', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return $next($request);
    }
}
