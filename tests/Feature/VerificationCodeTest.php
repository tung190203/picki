<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_saves_otp_and_sends_matching_code_even_with_whitespace_and_uppercase(): void
    {
        Notification::fake();
        Event::fake();

        $this->postJson('/api/auth/register', [
            'login' => '  Test.User@Example.COM ',
        ])->assertOk();

        $user = User::where('email', 'test.user@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
            // Trigger via() and toMail() to verify DB write & mail OTP match
            $channels = $notification->via($user);
            $this->assertContains('mail', $channels);

            $record = DB::table('verification_codes')
                ->where('type', 'email')
                ->where('identifier', 'test.user@example.com')
                ->first();
            $this->assertNotNull($record);

            $mail = $notification->toMail($user);
            $this->assertSame($record->otp, $mail->viewData['otp']);

            return true;
        });

        $otp = DB::table('verification_codes')
            ->where('type', 'email')
            ->where('identifier', 'test.user@example.com')
            ->value('otp');

        $this->postJson('/api/auth/verify-otp', [
            'login' => 'test.user@example.com',
            'otp' => $otp,
        ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('verification_codes', [
            'type' => 'email',
            'identifier' => 'test.user@example.com',
        ]);
    }

    public function test_forgot_password_saves_and_verifies_otp(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'player@example.com']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => ' PLAYER@Example.com ',
        ])->assertOk();

        Mail::assertSent(ResetPasswordMail::class);

        $otp = DB::table('verification_codes')
            ->where('type', 'email')
            ->where('identifier', 'player@example.com')
            ->value('otp');

        $this->assertNotNull($otp);

        $this->postJson('/api/auth/verify-otp-password', [
            'email' => 'player@example.com',
            'otp' => $otp,
        ])->assertOk();
    }
}
