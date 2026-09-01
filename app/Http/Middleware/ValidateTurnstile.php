<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateTurnstile
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secretKey = config('services.turnstile.secret') ?: env('TURNSTILE_SECRET_KEY');

        // Nếu chưa cấu hình Secret Key (ví dụ môi trường local/test), bỏ qua kiểm tra
        if (empty($secretKey)) {
            return $next($request);
        }

        // Bỏ qua xác thực Turnstile cho Mobile App (iOS / Android)
        if ($this->isMobileRequest($request)) {
            return $next($request);
        }

        $token = $request->input('cf-turnstile-response') 
            ?: $request->input('turnstile_token') 
            ?: $request->header('X-Turnstile-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng hoàn thành xác thực an toàn (Turnstile token thiếu).',
                'status_code' => 'TURNSTILE_TOKEN_MISSING'
            ], 422);
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            if (!$response->successful() || !$response->json('success')) {
                Log::warning('Turnstile verification failed', [
                    'ip' => $request->ip(),
                    'errors' => $response->json('error-codes')
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Xác thực an toàn không thành công. Vui lòng thử lại.',
                    'status_code' => 'TURNSTILE_VERIFICATION_FAILED',
                    'errors' => $response->json('error-codes')
                ], 422);
            }
        } catch (\Exception $e) {
            Log::error('Turnstile verification exception: ' . $e->getMessage());
            // Trong trường hợp Cloudflare gặp sự cố kết nối, log lại và có thể bỏ qua để không đứt đoạn trải nghiệm người dùng
        }

        return $next($request);
    }

    /**
     * Determine if the incoming request is from a mobile app client.
     */
    protected function isMobileRequest(Request $request): bool
    {
        $platform = strtolower((string) (
            $request->input('platform')
            ?: $request->header('X-Platform')
            ?: $request->header('X-App-Platform')
            ?: $request->header('X-Client-Platform')
            ?: $request->header('X-Client-Type')
            ?: ''
        ));

        return in_array($platform, ['ios', 'android', 'mobile', 'app']);
    }
}
