<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Events\SuperAdmin\UserRegistered;
use App\Http\Resources\UserResource;
use App\Jobs\SendPushJob;
use App\Mail\ResetPasswordMail;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Models\SystemSetting;
use App\Services\Club\ClubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Google_Client;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class AuthController extends Controller
{
    public function __construct(protected ClubService $clubService) {}

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'nullable|string',
            'token' => 'sometimes|string',
            'platform' => 'sometimes|in:ios,android'
        ]);

        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $credentials = [$loginField => $request->login, 'password' => $request->password];
        $exits = User::where($loginField, $request->login)->first();
        if (!$exits) {
            return ResponseHelper::error('Người dùng không tồn tại', 404, [
                'status_code' => 'USER_NOT_FOUND'
            ]);
        }
        if ($exits->is_banned) {
            return ResponseHelper::error('Tài khoản của bạn đã bị khóa: ' . ($exits->ban_reason ?? 'Vui lòng liên hệ hỗ trợ'), 403, [
                'status_code' => 'USER_BANNED',
                'ban_reason' => $exits->ban_reason,
            ]);
        }
        if (!$exits->email_verified_at) {
            $exits->notify(new VerifyEmailNotification($loginField, $request->login));
            return ResponseHelper::error('Vui lòng xác minh email trước khi đăng nhập', 403, [
                'status_code' => 'OTP_PENDING'
            ]);
        }

        if (!$exits->password) {
            $exits->notify(new VerifyEmailNotification($loginField, $request->login));
            return ResponseHelper::error('Bạn chưa hoàn tất đăng ký mật khẩu', 403, [
                'status_code' => 'PASSWORD_PENDING'
            ]);
        }

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return ResponseHelper::error('Sai thông tin đăng nhập', 401, [
                    'status_code' => 'INVALID_CREDENTIALS'
                ]);
            }
        } catch (JWTException $e) {
            return ResponseHelper::error('Không thể tạo token', 500, [
                'status_code' => 'TOKEN_ERROR'
            ]);
        }

        $user = $exits;

        $accessToken = JWTAuth::claims(['type' => 'access'])->fromUser($user);
        $refreshToken = JWTAuth::claims(['type' => 'refresh', 'exp' => now()->addDays(30)->timestamp])->fromUser($user);
        $user->last_login = now();
        $user->last_active_at = now();
        $user->save();

        if($request->token && $request->platform) {
            $this->handleDeviceLogin($user, $request->token, $request->platform);
        }

        return ResponseHelper::success($this->responseWithToken($accessToken, $refreshToken, $user), 'Đăng nhập thành công');
    }

    public function register(Request $request)
    {
        $request->validate(['login' => 'required|string']);

        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $existingUser = User::where($loginField, $request->login)->first();

        if ($existingUser) {
            if (!$existingUser->email_verified_at) {
                $existingUser->notify(new VerifyEmailNotification($loginField, $request->login));
                return ResponseHelper::error('Vui lòng xác minh email trước khi đăng nhập', 403, [
                    'status_code' => 'OTP_PENDING'
                ]);
            }
            if (!$existingUser->password) {
                $existingUser->notify(new VerifyEmailNotification($loginField, $request->login));
                return ResponseHelper::error('Bạn chưa hoàn tất đăng ký mật khẩu', 403, [
                    'status_code' => 'PASSWORD_PENDING'
                ]);
            }
            return ResponseHelper::error('Email hoặc số điện thoại đã được sử dụng.', 400, ['status_code' => 'REGISTERED']);
        }

        $user = User::create([
            $loginField => $request->login,
            'avatar_url' => null,
            'full_name' => 'PickiUser' . Str::random(5),
        ]);

        // Notify super admins about new user registration
        UserRegistered::dispatch($user);

        $user->notify(new VerifyEmailNotification($loginField, $request->login));

        return ResponseHelper::success(['status_code' => 'OTP_PENDING'], 'Đăng ký thành công. Vui lòng xác minh tài khoản.');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['login' => 'required|string', 'otp' => 'required|digits:6']);

        $type = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $record = DB::table('verification_codes')->where('type', $type)->where('identifier', $request->login)->first();

        if (!$record) {
            return ResponseHelper::error('Không tìm thấy mã xác minh.', 404, ['status_code' => 'OTP_NOT_FOUND']);
        }
        if ($record->otp !== $request->otp) {
            return ResponseHelper::error('Mã OTP không đúng.', 400, ['status_code' => 'OTP_INVALID']);
        }
        if (now()->greaterThan($record->expires_at)) {
            return ResponseHelper::error('Mã OTP đã hết hạn.', 400, ['status_code' => 'OTP_EXPIRED']);
        }

        $user = User::where($type, $request->login)->first();
        if (!$user) return ResponseHelper::error('Người dùng không tồn tại.', 404, ['status_code' => 'USER_NOT_FOUND']);

        $user->email_verified_at = now();
        $user->save();
        DB::table('verification_codes')->where('type', $type)->where('identifier', $request->login)->delete();

        $status = $user->password ? 'VERIFIED' : 'PASSWORD_PENDING';
        return ResponseHelper::success(['status_code' => $status], 'Xác minh thành công');
    }

    public function resendOtp(Request $request)
    {
        $request->validate(['login' => 'required|string']);
        $type = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($type, $request->login)->first();

        if (!$user) return ResponseHelper::error('Không tìm thấy người dùng.', 404, ['status_code' => 'USER_NOT_FOUND']);

        $user->notify(new VerifyEmailNotification($type, $request->login));
        return ResponseHelper::success(['status_code' => 'OTP_PENDING'], 'Mã OTP mới đã được gửi.');
    }

    public function fillPassword(Request $request)
    {
        $request->validate(['login' => 'required|string', 'password' => 'required|min:6|confirmed']);
        $type = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($type, $request->login)->first();

        if (!$user) return ResponseHelper::error('Không tìm thấy người dùng.', 404, ['status_code' => 'USER_NOT_FOUND']);
        if ($user->password) return ResponseHelper::error('Người dùng đã có mật khẩu.', 400, ['status_code' => 'PASSWORD_EXISTS']);

        $user->password = $request->password;
        $user->save();

        return ResponseHelper::success(['status_code' => 'COMPLETED'], 'Hoàn tất đăng ký');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) return ResponseHelper::error('Email không tồn tại', 404, ['status_code' => 'USER_NOT_FOUND']);

        Mail::to($user->email)->send(new ResetPasswordMail($request->email));
        return ResponseHelper::success(['status_code' => 'OTP_PENDING'], 'Đã gửi email đặt lại mật khẩu.');
    }

    public function verifyOtpPassword(Request $request)
    {
        $request->validate(['email' => 'required|email', 'otp' => 'required|digits:6']);
        $record = DB::table('verification_codes')->where('type', 'email')->where('identifier', $request->email)->first();

        if (!$record) return ResponseHelper::error('Không tìm thấy mã xác minh.', 404, ['status_code' => 'OTP_NOT_FOUND']);
        if ($record->otp !== $request->otp) return ResponseHelper::error('Mã OTP không đúng.', 400, ['status_code' => 'OTP_INVALID']);
        if (now()->greaterThan($record->expires_at)) return ResponseHelper::error('Mã OTP đã hết hạn.', 400, ['status_code' => 'OTP_EXPIRED']);

        DB::table('verification_codes')->where('type', 'email')->where('identifier', $request->email)->delete();
        return ResponseHelper::success(['status_code' => 'VERIFIED'], 'Xác minh OTP thành công. Bạn có thể đặt lại mật khẩu.');
    }

    public function resendOtpPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);
        $user = User::where('email', $request->email)->first();
        if (!$user) return ResponseHelper::error('Không tìm thấy người dùng.', 404, ['status_code' => 'USER_NOT_FOUND']);

        Mail::to($user->email)->send(new ResetPasswordMail($request->email));
        return ResponseHelper::success(['status_code' => 'OTP_PENDING'], 'Mã OTP mới đã được gửi đến email của bạn.');
    }

    public function resetPassword(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required|min:6|confirmed']);
        $user = User::where('email', $request->email)->first();
        if (!$user) return ResponseHelper::error('Người dùng không tồn tại.', 404, ['status_code' => 'USER_NOT_FOUND']);

        $user->password = $request->password;
        $user->save();

        return ResponseHelper::success(['status_code' => 'COMPLETED'], 'Mật khẩu đã được đặt lại thành công');
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->bearerToken();
        if (!$refreshToken) return ResponseHelper::error('Token không được cung cấp', 401, ['status_code' => 'TOKEN_MISSING']);

        try {
            $payload = JWTAuth::setToken($refreshToken)->getPayload();
            if ($payload->get('type') !== 'refresh') return ResponseHelper::error('Sai loại token', 401, ['status_code' => 'INVALID_TOKEN']);

            $user = User::find($payload->get('sub'));
            if (!$user) return ResponseHelper::error('Người dùng không tồn tại', 404, ['status_code' => 'USER_NOT_FOUND']);
            if ($user->is_banned) {
                return ResponseHelper::error('Tài khoản của bạn đã bị khóa: ' . ($user->ban_reason ?? 'Vui lòng liên hệ hỗ trợ'), 403, [
                    'status_code' => 'USER_BANNED',
                    'ban_reason' => $user->ban_reason,
                ]);
            }

            $user->update(['last_login' => now(), 'last_active_at' => now()]);

            $newAccessToken = JWTAuth::claims(['type' => 'access'])->fromUser($user);
            return ResponseHelper::success([
                'access_token' => $newAccessToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'status_code' => 'REFRESHED'
            ], 'Làm mới access token thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error('Refresh token không hợp lệ hoặc đã hết hạn', 401, ['status_code' => 'INVALID_TOKEN']);
        }
    }

    public function redirectToGoogle(Request $request)
    {
        $redirect = $request->query('redirect');
        $state = $redirect ? base64_encode(json_encode(['redirect' => $redirect])) : null;

        $driver = Socialite::driver('google')->stateless();
        if ($state) {
            $driver->with(['state' => $state]);
        }
        return $driver->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $state = $request->input('state');
            $redirectUrl = null;
            if ($state) {
                $decodedState = json_decode(base64_decode($state), true);
                $redirectUrl = $decodedState['redirect'] ?? null;
            }

            $googleUser = Socialite::driver('google')->stateless()->user();
            $user = User::where('email', $googleUser->getEmail())
                ->orWhere('google_id', $googleUser->getId())
                ->first();

            if ($user) {
                if (!$user->google_id) {
                    $user->google_id = $googleUser->getId();
                }
                if (!$user->avatar_url) {
                    $avatarContent = file_get_contents($googleUser->getAvatar());
                    $avatarName = 'avatars/' . uniqid() . '.jpg';
                    Storage::disk('public')->put($avatarName, $avatarContent);
                    $user->avatar_url = asset('storage/' . $avatarName);
                }

                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                }

                $user->save();
            } else {
                $avatarContent = file_get_contents($googleUser->getAvatar());
                $avatarName = 'avatars/' . uniqid() . '.jpg';
                Storage::disk('public')->put($avatarName, $avatarContent);

                $user = User::create([
                    'email' => $googleUser->getEmail(),
                    'full_name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => asset('storage/' . $avatarName),
                    'password' => Hash::make(Str::random(16)),
                    'email_verified_at' => now(),
                ]);
            }
            if ($user->is_banned) {
                return ResponseHelper::error('Tài khoản của bạn đã bị khóa: ' . ($user->ban_reason ?? 'Vui lòng liên hệ hỗ trợ'), 403, [
                    'status_code' => 'USER_BANNED',
                    'ban_reason' => $user->ban_reason,
                ]);
            }
            Auth::login($user);
            $accessToken = JWTAuth::claims(['type' => 'access'])->fromUser($user);
            $refreshToken = JWTAuth::claims(['type' => 'refresh', 'exp' => now()->addDays(30)->timestamp])->fromUser($user);
            $user->last_login = now();
            $user->last_active_at = now();
            $user->save();

            if ($request->header('User-Agent') && strpos($request->header('User-Agent'), 'MobileApp') !== false) {
                return ResponseHelper::success(array_merge($this->responseWithToken($accessToken, $refreshToken, $user), ['status_code' => 'VERIFIED']), 'Đăng nhập bằng Google thành công');
            } else {
                $queryParams = [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ];
                if ($redirectUrl) {
                    $queryParams['redirect'] = $redirectUrl;
                }
                return redirect(config('app.redirect_success_url') . '/login-success?' . http_build_query($queryParams));
            }
        } catch (\Exception $e) {
            return ResponseHelper::error('Không thể đăng nhập bằng Google', 500, ['status_code' => 'OAUTH_FAILED']);
        }
    }

    public function loginWithGoogle(Request $request)
    {
        \Log::info('Google login attempt', [
            'has_id_token' => $request->has('id_token'),
            'platform' => $request->input('platform'),
            'ip' => $request->ip(),
        ]);
        $request->validate([
            'id_token' => 'required|string',
            'token' => 'sometimes|string',
            'platform' => 'sometimes|in:ios,android',
        ]);

        $idToken = $request->input('id_token');
        $validClients = [
            'android' => config('services.google.android_client_id'),
            'ios' => config('services.google.ios_client_id'),
            'web' => config('services.google.client_id'),
        ];

        $client = new Google_Client();
        $payload = $client->verifyIdToken($idToken);

        if (!$payload) return ResponseHelper::error('Token Google không hợp lệ', 401, ['status_code' => 'INVALID_TOKEN']);

        $aud = $payload['aud'] ?? null;
        $platform = collect($validClients)->search($aud);
        if ($platform === false) return ResponseHelper::error('Client ID không hợp lệ', 401, ['status_code' => 'INVALID_CLIENT']);

        $user = User::where('email', $payload['email'])
            ->orWhere('google_id', $payload['sub'])
            ->first();

        if ($user) {
            if (!$user->google_id) {
                $user->google_id = $payload['sub'];
            }

            if (!$user->avatar_url) {
                $user->avatar_url = $payload['picture'] ?? null;
            }

            if (!$user->email_verified_at) {
                $user->email_verified_at = now();
            }

            $user->save();
        } else {
            $user = User::create([
                'email' => $payload['email'],
                'full_name' => $payload['name'] ?? null,
                'google_id' => $payload['sub'],
                'avatar_url' => $payload['picture'] ?? null,
                'password' => Hash::make(Str::random(16)),
                'email_verified_at' => now(),
            ]);
        }
        if ($user->is_banned) {
            return ResponseHelper::error('Tài khoản của bạn đã bị khóa: ' . ($user->ban_reason ?? 'Vui lòng liên hệ hỗ trợ'), 403, [
                'status_code' => 'USER_BANNED',
                'ban_reason' => $user->ban_reason,
            ]);
        }
        $accessToken = JWTAuth::claims(['type' => 'access'])->fromUser($user);
        $refreshToken = JWTAuth::claims(['type' => 'refresh', 'exp' => now()->addDays(30)->timestamp])->fromUser($user);
        $user->last_login = now();
        $user->last_active_at = now();
        $user->save();

        if($request->token && $request->platform) {
            $this->handleDeviceLogin($user, $request->token, $request->platform);
        }

        $response = $this->responseWithToken($accessToken, $refreshToken, $user);
        $response['platform'] = $platform;
        $response['status_code'] = 'VERIFIED';

        \Log::info('Google login success', [
            'user_id' => $user->id,
            'email' => $user->email,
            'platform' => $platform,
        ]);

        return ResponseHelper::success($response, 'Đăng nhập bằng Google thành công');
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->stateless()->redirect();
    }

    public function loginWithFacebook(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string',
            'token' => 'sometimes|string',
            'platform' => 'sometimes|in:ios,android',
        ]);
        $accessToken = $request->input('access_token');

        try {
            $fbResponse = Http::get('https://graph.facebook.com/me', [
                'fields' => 'id,name,email,picture',
                'access_token' => $accessToken,
            ])->json();

            if (!isset($fbResponse['id'])) {
                return ResponseHelper::error('Token Facebook không hợp lệ', 401, ['status_code' => 'INVALID_TOKEN']);
            }

            if (empty($fbResponse['email'])) {
                return ResponseHelper::error('Facebook account không có email', 400, ['status_code' => 'NO_EMAIL']);
            }
            $user = User::where('email', $fbResponse['email'])
                ->orWhere('facebook_id', $fbResponse['id'])
                ->first();

            if ($user) {
                if (!$user->facebook_id) {
                    $user->facebook_id = $fbResponse['id'];
                }

                $avatarUrl = $fbResponse['picture']['data']['url'] ?? null;
                if ($avatarUrl && !$user->avatar_url) {
                    $avatarContent = file_get_contents($avatarUrl);
                    $avatarName = 'avatars/' . uniqid() . '.jpg';
                    Storage::disk('public')->put($avatarName, $avatarContent);
                    $user->avatar_url = asset('storage/' . $avatarName);
                }

                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                }

                $user->save();
            } else {
                $avatarUrl = $fbResponse['picture']['data']['url'] ?? null;
                $avatarName = null;
                if ($avatarUrl) {
                    $avatarContent = file_get_contents($avatarUrl);
                    $avatarName = 'avatars/' . uniqid() . '.jpg';
                    Storage::disk('public')->put($avatarName, $avatarContent);
                }

                $user = User::create([
                    'email' => $fbResponse['email'],
                    'full_name' => $fbResponse['name'] ?? null,
                    'facebook_id' => $fbResponse['id'],
                    'avatar_url' => $avatarName ? asset('storage/' . $avatarName) : null,
                    'password' => Hash::make(Str::random(16)),
                    'email_verified_at' => now(),
                ]);
            }

            if ($user->is_banned) {
                return ResponseHelper::error('Tài khoản của bạn đã bị khóa: ' . ($user->ban_reason ?? 'Vui lòng liên hệ hỗ trợ'), 403, [
                    'status_code' => 'USER_BANNED',
                    'ban_reason' => $user->ban_reason,
                ]);
            }

            // JWT Token
            $accessTokenJWT = JWTAuth::claims(['type' => 'access'])->fromUser($user);
            $refreshTokenJWT = JWTAuth::claims(['type' => 'refresh', 'exp' => now()->addDays(30)->timestamp])->fromUser($user);

            // Cập nhật last_login
            $user->last_login = now();
            $user->last_active_at = now();
            $user->save();
            if($request->token && $request->platform) {
                $this->handleDeviceLogin($user, $request->token, $request->platform);
            }
            // Response
            $response = $this->responseWithToken($accessTokenJWT, $refreshTokenJWT, $user);
            $response['platform'] = 'mobile';
            $response['status_code'] = 'VERIFIED';

            return ResponseHelper::success($response, 'Đăng nhập bằng Facebook thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error('Không thể đăng nhập bằng Facebook', 500, ['status_code' => 'OAUTH_FAILED']);
        }
    }

    public function handleFacebookCallback(Request $request)
    {
        try {
            $fbUser = Socialite::driver('facebook')->stateless()->user();
            $user = User::where('email', $fbUser->getEmail())
                ->orWhere('facebook_id', $fbUser->getId())
                ->first();

            if ($user) {
                if (!$user->facebook_id) {
                    $user->facebook_id = $fbUser->getId();
                }

                if (!$user->avatar_url) {
                    $avatarContent = file_get_contents($fbUser->getAvatar());
                    $avatarName = 'avatars/' . uniqid() . '.jpg';
                    Storage::disk('public')->put($avatarName, $avatarContent);
                    $user->avatar_url = asset('storage/' . $avatarName);
                }

                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                }

                $user->save();
            } else {
                $avatarContent = file_get_contents($fbUser->getAvatar());
                $avatarName = 'avatars/' . uniqid() . '.jpg';
                Storage::disk('public')->put($avatarName, $avatarContent);

                $user = User::create([
                    'email' => $fbUser->getEmail(),
                    'full_name' => $fbUser->getName(),
                    'facebook_id' => $fbUser->getId(),
                    'avatar_url' => asset('storage/' . $avatarName),
                    'password' => Hash::make(Str::random(16)),
                    'email_verified_at' => now(),
                ]);
            }
            if ($user->is_banned) {
                return ResponseHelper::error('Tài khoản của bạn đã bị khóa: ' . ($user->ban_reason ?? 'Vui lòng liên hệ hỗ trợ'), 403, [
                    'status_code' => 'USER_BANNED',
                    'ban_reason' => $user->ban_reason,
                ]);
            }
            Auth::login($user);
            $accessToken = JWTAuth::claims(['type' => 'access'])->fromUser($user);
            $refreshToken = JWTAuth::claims(['type' => 'refresh', 'exp' => now()->addDays(30)->timestamp])->fromUser($user);
            $user->last_login = now();
            $user->last_active_at = now();
            $user->save();

            if ($request->header('User-Agent') && strpos($request->header('User-Agent'), 'MobileApp') !== false) {
                return ResponseHelper::success(array_merge($this->responseWithToken($accessToken, $refreshToken, $user), ['status_code' => 'VERIFIED']), 'Đăng nhập bằng Facebook thành công');
            } else {
                return redirect(config('app.redirect_success_url') . '/login-success?' . http_build_query([
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ]));
            }
        } catch (\Exception $e) {
            return ResponseHelper::error('Không thể đăng nhập bằng Facebook', 500, ['status_code' => 'OAUTH_FAILED']);
        }
    }

    public function redirectToApple()
    {
        return Socialite::driver('sign-in-with-apple')->scopes(['name', 'email'])->stateless()->redirect();
    }

    public function handleAppleCallback(Request $request)
    {
        try {
            $appleUser = Socialite::driver('sign-in-with-apple')->stateless()->user();

            $name = $appleUser->user['name'] ?? ('PickiUser' . substr($appleUser->getId(), -6));
            $email = $appleUser->getEmail();
            $user = User::where('email', $email)
                ->orWhere('apple_id', $appleUser->getId())
                ->first();

            if ($user) {
                if (!$user->apple_id) {
                    $user->apple_id = $appleUser->getId();
                }

                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                }

                $user->save();
            } else {
                $user = User::create([
                    'email' => $email,
                    'full_name' => $name,
                    'apple_id' => $appleUser->getId(),
                    'password' => Hash::make(Str::random(16)),
                    'email_verified_at' => now(),
                ]);
            }
            if ($user->is_banned) {
                return ResponseHelper::error('Tài khoản của bạn đã bị khóa: ' . ($user->ban_reason ?? 'Vui lòng liên hệ hỗ trợ'), 403, [
                    'status_code' => 'USER_BANNED',
                    'ban_reason' => $user->ban_reason,
                ]);
            }
            Auth::login($user);
            $accessToken = JWTAuth::claims(['type' => 'access'])->fromUser($user);
            $refreshToken = JWTAuth::claims(['type' => 'refresh', 'exp' => now()->addDays(30)->timestamp])->fromUser($user);
            $user->last_login = now();
            $user->last_active_at = now();
            $user->save();

            if ($request->header('User-Agent') && strpos($request->header('User-Agent'), 'MobileApp') !== false) {
                return ResponseHelper::success(array_merge($this->responseWithToken($accessToken, $refreshToken, $user), ['status_code' => 'VERIFIED']), 'Đăng nhập bằng Apple thành công');
            } else {
                return redirect(config('app.redirect_success_url') . '/login-success?' . http_build_query([
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ]));
            }
        } catch (\Exception $e) {
            return ResponseHelper::error('Không thể đăng nhập bằng Apple', 500, ['status_code' => 'OAUTH_FAILED']);
        }
    }

    public function loginWithApple(Request $request)
    {
        \Log::info('Apple login attempt', [
            'has_id_token' => $request->has('id_token'),
            'id_token_length' => strlen($request->input('id_token', '')),
            'id_token_prefix' => substr($request->input('id_token', ''), 0, 50),
            'platform' => $request->input('platform'),
            'ip' => $request->ip(),
        ]);
        $request->validate([
            'id_token' => 'required|string',
            'token' => 'sometimes|string',
            'platform' => 'sometimes|in:ios,android',
        ]);
        $idToken = $request->input('id_token');

        \Log::info('Apple login - Step 1: Fetching JWKS from Apple');

        try {
            $jwks = Http::get('https://appleid.apple.com/auth/keys')->json();
            \Log::info('Apple login - Step 1 success', ['jwks_keys_count' => count($jwks['keys'] ?? [])]);
        } catch (\Exception $e) {
            \Log::error('Apple login - Step 1 failed: fetch JWKS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ResponseHelper::error('Không thể kết nối Apple để xác thực', 500, ['status_code' => 'APPLE_JWKS_FAILED']);
        }

        \Log::info('Apple login - Step 2: Parsing JWKS keys');

        try {
            $keys = JWK::parseKeySet($jwks);
            \Log::info('Apple login - Step 2 success', ['keys_count' => count($keys)]);
        } catch (\Exception $e) {
            \Log::error('Apple login - Step 2 failed: parse JWKS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ResponseHelper::error('Không thể xử lý Apple key', 500, ['status_code' => 'APPLE_KEY_PARSE_FAILED']);
        }

        $payload = null;
        foreach ($keys as $keyIndex => $key) {
            try {
                $payload = JWT::decode($idToken, $key);
                \Log::info('Apple login - Step 3: Decoded successfully with key index ' . $keyIndex);
                break;
            } catch (\Throwable $e) {
                \Log::warning('Apple login - Key ' . $keyIndex . ' decode failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        if (!$payload) {
            \Log::error('Apple login - All keys failed to decode');
            return ResponseHelper::error('Token Apple không hợp lệ', 401, ['status_code' => 'INVALID_TOKEN']);
        }

        try {
            $data = json_decode(json_encode($payload), true);
            $appleId = $data['sub'];
            $email = $data['email'] ?? null;
            $name = $request->input('name');
            if (!$email) {
                $email = $appleId . '@privaterelay.appleid.com';
            }
            if (!$name) {
                $name = 'PickiUser' . substr($appleId, -6);
            }
            $user = User::where('apple_id', $appleId)
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                if (!$user->apple_id) {
                    $user->apple_id = $appleId;
                }

                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                }

                $user->save();
            } else {
                $user = User::create([
                    'email' => $email,
                    'full_name' => $name,
                    'apple_id' => $appleId,
                    'password' => Hash::make(Str::random(16)),
                    'email_verified_at' => now(),
                ]);
            }

            if ($user->is_banned) {
                return ResponseHelper::error('Tài khoản của bạn đã bị khóa: ' . ($user->ban_reason ?? 'Vui lòng liên hệ hỗ trợ'), 403, [
                    'status_code' => 'USER_BANNED',
                    'ban_reason' => $user->ban_reason,
                ]);
            }

            Auth::login($user);

            $accessToken = JWTAuth::claims(['type' => 'access'])->fromUser($user);
            $refreshToken = JWTAuth::claims(['type' => 'refresh', 'exp' => now()->addDays(30)->timestamp])->fromUser($user);
            $user->last_login = now();
            $user->last_active_at = now();
            $user->save();

            if($request->token && $request->platform) {
                $this->handleDeviceLogin($user, $request->token, $request->platform);
            }
            \Log::info("Before responseWithToken");

            $response = $this->responseWithToken($accessToken, $refreshToken, $user);

            \Log::info("After responseWithToken");

            $response['status_code'] = 'VERIFIED';

            \Log::info("After status_code added");

            \Log::info('Apple login success', [
                'user_id' => $user->id,
                'email' => $user->email,
                'apple_id' => $appleId,
            ]);

            \Log::info("Before ResponseHelper::success");

            $result = ResponseHelper::success($response, 'Đăng nhập bằng Apple thành công');

            \Log::info("After ResponseHelper::success - returning");

            \Log::info("Apple login - returning access token", [
                'access_token_length' => strlen($accessToken),
                'access_token_prefix' => substr($accessToken, 0, 20),
                'refresh_token_length' => strlen($refreshToken),
            ]);

            return $result;
        } catch (\Exception $e) {
            \Log::error('Apple login failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ResponseHelper::error('Không thể đăng nhập bằng Apple', 500, ['status_code' => 'OAUTH_FAILED']);
        }
    }

    public function me(Request $request)
    {
        $user = User::withMeRelations(1)->find($request->user()->id);
        if ($user->is_banned) {
            return ResponseHelper::error('Tài khoản của bạn đã bị khóa: ' . ($user->ban_reason ?? 'Vui lòng liên hệ hỗ trợ'), 403, [
                'status_code' => 'USER_BANNED',
                'ban_reason' => $user->ban_reason,
            ]);
        }

        // Load sport stats cho user hiện tại (loadSportStatsOnUsers nhắm vào $sport->preloaded_sport_stats
        // mà UserSportResource đọc). Vì clubs.members không load nữa nên chỉ truyền 1 user — rất nhẹ.
        User::loadSportStatsOnUsers(collect([$user]), 1);

        $user->vn_rank = User::getBatchVNRanks([$user->id], 1)[$user->id] ?? null;

        $clubs = $user->clubs;
        if ($clubs->isNotEmpty()) {
            $this->clubService->attachUnreadNotificationCount($clubs, $user->id);
        }

        return ResponseHelper::success(new UserResource($user), 'Lay thong tin nguoi dung thanh cong', 200);
    }

    private function responseWithToken(string $accessToken, string $refreshToken, object $user): array
    {
        \Log::info("responseWithToken Step 1");

        $user->loadFullRelations();

        \Log::info("responseWithToken Step 2 - loadFullRelations done");

        User::loadSportStatsOnUsers(collect([$user]), 1);

        \Log::info("responseWithToken Step 3 - loadSportStatsOnUsers done");

        $resource = new UserResource($user);

        \Log::info("responseWithToken Step 4 - UserResource created");

        $result = [
            'token' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ],
            'user' => $resource,
        ];

        \Log::info("responseWithToken Step 5 - returning");

        return $result;
    }

    private function handleDeviceLogin(User $user, string $currentToken, string $platform)
    {
        $oldDevices = DeviceToken::where('user_id', $user->id)
            ->where('token', '!=', $currentToken)
            ->where('is_enabled', true)
            ->exists();

        if ($oldDevices) {
            SendPushJob::dispatch(
                $user->id,
                'Đăng nhập thiết bị mới',
                'Tài khoản của bạn vừa được đăng nhập trên một thiết bị khác',
                [
                    'type' => 'NEW_DEVICE_LOGIN',
                    'platform' => $platform,
                    'time' => now()->toDateTimeString(),
                ]
            );
        }

        DeviceToken::updateOrCreate(
            [
                'token' => $currentToken,
                'user_id' => $user->id,
            ],
            [
                'platform' => $platform,
                'last_seen_at' => now(),
                'is_enabled' => true,
            ]
        );
    }
}
