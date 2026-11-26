<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VerificationController extends Controller
{
    public function verify(Request $request)
    {
        try {
            $payload = decrypt($request->token);

            if (now()->gt(Carbon::parse($payload['expires_at']))) {
                return ResponseHelper::error('Token xác minh đã hết hạn.', 400);
            }

            $user = User::where('email', $payload['email'])->first();
            $user->email_verified_at = now();
            $user->save();

            return ResponseHelper::success([], 'Xác minh email thành công.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Token xác minh không hợp lệ hoặc đã hết hạn.', 400);
        }
    }
    public function resend(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return ResponseHelper::error('Email không tồn tại.', 404);
        }
        if ($user->email_verified_at) {
            return ResponseHelper::error('Email đã được xác minh.', 400);
        }
        $user->notify(new VerifyEmailNotification('email', $user->email));

        return ResponseHelper::success([], 'Gửi lại email xác minh thành công.');
    }
}
