<?php

namespace App\Notifications;

use App\Models\ScoreVerificationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ScoreVerificationApprovedNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ScoreVerificationRequest $request
    ) {}

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Xác minh điểm thành công',
            'body' => 'Điểm ' . $this->request->score_type . ' của bạn đã được xác minh.',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Xác minh điểm thành công',
            'message' => 'Điểm ' . $this->request->score_type . ' của bạn đã được xác minh.',
            'data' => ['request_id' => $this->request->id],
        ];
    }
}
