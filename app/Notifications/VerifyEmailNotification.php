<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    protected string $type;
    protected string $identifier;
    protected ?string $otp = null;

    public function __construct(string $type, string $identifier)
    {
        $this->type = $type;
        $this->identifier = $type === 'email'
            ? mb_strtolower(trim($identifier))
            : trim($identifier);
    }

    public function via($notifiable)
    {
        $this->generateOtpCode($notifiable->id ?? null);

        if ($this->type === 'email') {
            return ['mail'];
        }

        // If user has email registered, also send email as fallback
        if (!empty($notifiable->email)) {
            return ['mail', 'database'];
        }

        return ['database'];
    }

    protected function generateOtpCode(?int $userId = null): string
    {
        if ($this->otp !== null) {
            return $this->otp;
        }

        $this->otp = (string) random_int(100000, 999999);

        DB::table('verification_codes')->updateOrInsert(
            ['type' => $this->type, 'identifier' => $this->identifier],
            [
                'otp' => $this->otp,
                'user_id' => $userId,
                'expires_at' => now()->addMinutes(10),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        \Illuminate\Support\Facades\Log::info("Generated OTP {$this->otp} for {$this->type}: {$this->identifier}");

        return $this->otp;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $otp = $this->otp ?? $this->generateOtpCode($notifiable->id ?? null);

        // Dữ liệu truyền qua view
        $data = [
            'otp' => $otp,
            'type' => $this->type,
        ];

        // Dùng custom view
        return (new MailMessage)
            ->subject('Mã xác minh tài khoản của bạn')
            ->view('emails.verify', $data);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'identifier' => $this->identifier,
        ];
    }
}
