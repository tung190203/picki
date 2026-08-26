<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $type;
    protected string $identifier;

    public function __construct(string $type, string $identifier)
    {
        $this->type = $type;
        $this->identifier = $identifier;
    }

    public function via($notifiable)
    {
        $this->generateOtpCode();

        if ($this->type === 'email') {
            return ['mail'];
        }

        // If user has email registered, also send email as fallback
        if (!empty($notifiable->email)) {
            return ['mail', 'database'];
        }

        return ['database'];
    }

    protected function generateOtpCode(): string
    {
        $otp = (string) rand(100000, 999999);

        DB::table('verification_codes')->updateOrInsert(
            ['type' => $this->type, 'identifier' => $this->identifier],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        \Illuminate\Support\Facades\Log::info("Generated OTP {$otp} for {$this->type}: {$this->identifier}");

        return $otp;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $record = DB::table('verification_codes')
            ->where('type', $this->type)
            ->where('identifier', $this->identifier)
            ->first();

        $otp = $record ? $record->otp : rand(100000, 999999);

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
