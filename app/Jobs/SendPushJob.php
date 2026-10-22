<?php

namespace App\Jobs;

use App\Models\AdminPushNotificationResult;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $data = [],
        public ?int $campaignId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FirebaseService $firebase): void
    {
        try {
            $result = $firebase->sendToUser(
                $this->userId,
                $this->title,
                $this->body,
                $this->data
            );

            // Update tracking result if this is an admin push campaign
            if ($this->campaignId !== null) {
                $this->updateResult('success', null);
            }

            Log::debug('SendPushJob: FCM sent successfully', [
                'user_id' => $this->userId,
                'campaign_id' => $this->campaignId,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            // Update tracking result if this is an admin push campaign
            if ($this->campaignId !== null) {
                $this->updateResult('failed', $e->getMessage());
            }

            Log::error('SendPushJob: FCM send failed', [
                'user_id' => $this->userId,
                'campaign_id' => $this->campaignId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update the notification result in the database.
     */
    protected function updateResult(string $status, ?string $errorMessage): void
    {
        AdminPushNotificationResult::where('campaign_id', $this->campaignId)
            ->where('user_id', $this->userId)
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'error_message' => $errorMessage,
                'sent_at' => $status === 'success' ? now() : null,
            ]);
    }
}
