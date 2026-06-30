<?php

namespace App\Services;

use App\Models\DeviceToken;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected ?string $projectId;
    protected string $credentialsPath;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id') ?: null;
        $this->credentialsPath = storage_path('app/firebase/firebase.json');
    }

    public function isConfigured(): bool
    {
        if (empty($this->projectId) || !file_exists($this->credentialsPath)) {
            Log::warning('Firebase chưa cấu hình: cần FIREBASE_PROJECT_ID trong .env và file storage/app/firebase/firebase.json');
            return false;
        }
        return true;
    }

    protected function getAccessToken(): string
    {
        return $this->getPersistentCache()->remember('firebase_access_token', 3300, function () {
            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/firebase.messaging'],
                $this->credentialsPath
            );

            $token = $credentials->fetchAuthToken();
            return $token['access_token'];
        });
    }

    /**
     * Dùng cache store persistent (file/redis) thay vì default driver,
     * tránh token bị mất khi CACHE_DRIVER=array (mỗi request/process tạo instance mới).
     */
    protected function getPersistentCache(): \Illuminate\Contracts\Cache\Repository
    {
        $driver = config('cache.default');
        if ($driver === 'array' || $driver === 'null') {
            Log::warning('FirebaseService: CACHE_DRIVER là array/null, chuyển sang file cache cho FCM token persistence');
            return cache()->store('file');
        }
        return cache()->store($driver);
    }

    public function sendToDevice(
        DeviceToken $device,
        string $title,
        string $body,
        array $data = []
    ): bool {
        if (!$this->isConfigured()) {
            return false;
        }
        if (!$device->is_enabled) {
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $device->token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data),

                // Cấu hình riêng cho Android
                'android' => [
                    'notification' => [
                        'sound' => 'noti_sound',
                        'channel_id' => 'picki',
                    ],
                ],

                // Cấu hình riêng cho iOS
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'noti_sound.aif',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        try {
            (new Client())->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 5,
            ]);

            return true;
        } catch (ClientException $e) {
            $body = json_decode($e->getResponse()->getBody(), true);
            $errorCode = data_get($body, 'error.details.0.errorCode');

            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'])) {
                $device->delete(); // token chết
            } else {
                Log::error('FCM push error', [
                    'device_id' => $device->id,
                    'error' => $body,
                ]);
            }

            return false;
        }
    }

    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = []
    ): void {
        DeviceToken::where('user_id', $userId)
            ->where('is_enabled', true)
            ->each(
                fn($device) =>
                $this->sendToDevice($device, $title, $body, $data)
            );
    }

    public function sendToTopic(
        string $topic,
        string $title,
        string $body,
        array $data = []
    ): bool {
        if (!$this->isConfigured()) {
            return false;
        }
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $payload = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data),

                // Cấu hình riêng cho Android
                'android' => [
                    'notification' => [
                        'sound' => 'noti_sound',
                        'channel_id' => 'picki',
                    ],
                ],

                // Cấu hình riêng cho iOS
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'noti_sound.aif',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        try {
            (new Client())->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('FCM topic push failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
