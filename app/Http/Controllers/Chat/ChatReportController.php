<?php

namespace App\Http\Controllers\Chat;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Chat\ChatReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatReportController extends Controller
{
    /**
     * Gửi báo cáo vi phạm cuộc trò chuyện.
     */
    public function store(Request $request)
    {
        $request->validate([
            'reported_user_id' => 'required|integer|exists:users,id',
            'conversation_id'  => 'nullable|integer|exists:conversations,id',
            'message_id'       => 'nullable|integer|exists:chat_messages,id',
            'reason'           => 'required|string|max:255',
            'details'          => 'nullable|string|max:1000',
        ]);

        $currentUserId = Auth::id();
        $reportedUserId = (int) $request->reported_user_id;

        if ($currentUserId === $reportedUserId) {
            return ResponseHelper::error('Không thể tự báo cáo chính mình', 400);
        }

        $report = ChatReport::create([
            'reporter_id'      => $currentUserId,
            'reported_user_id' => $reportedUserId,
            'conversation_id'  => $request->conversation_id,
            'message_id'       => $request->message_id,
            'reason'           => $request->reason,
            'details'          => $request->details,
            'status'           => ChatReport::STATUS_PENDING,
        ]);

        return ResponseHelper::success(
            $report,
            'Báo cáo đã được gửi tới ban quản trị. Chúng tôi sẽ xem xét sớm nhất.',
            201
        );
    }
}
