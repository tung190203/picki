<?php

namespace App\Notifications;

use App\Models\ScoreVerificationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ScoreVerificationRejectedNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ScoreVerificationRequest $request,
        public string $reason
    ) {}

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Yêu cầu bị từ chối',
            'body' => 'Yêu cầu xác minh đã bị từ chối.',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Yêu cầu bị từ chối',
            'message' => "Yêu cầu xác minh đã bị từ chối. Lý do: {$this->reason}",
            'data' => ['request_id' => $this->request->id],
        ];
    }
}
