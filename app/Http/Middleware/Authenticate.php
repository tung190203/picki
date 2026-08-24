<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        \Log::info('Authenticate middleware check', [
            'path' => $request->path(),
            'is_api' => $request->is('api/*'),
        ]);

        // For API routes, never redirect
        if ($request->is('api/*')) {
            return null;
        }

        // For other routes, also return null to avoid route('login') error
        return null;
    }
}
