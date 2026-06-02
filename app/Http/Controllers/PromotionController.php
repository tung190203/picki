<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\PromotionService;
use App\Exceptions\BusinessException;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(
        protected PromotionService $promotionService
    ) {
    }

    public function recipients(Request $request)
    {
        $validated = $request->validate([
            'promotable_type' => 'required|string|in:' . implode(',', PromotionService::PROMOTABLE_TYPES),
            'promotable_id' => 'required|integer|min:1',
        ]);

        try {
            $userId = auth()->id();
            $recipients = $this->promotionService->getRecipients(
                $userId,
                $validated['promotable_type'],
                (int) $validated['promotable_id'],
                PromotionService::RECIPIENT_LIMIT
            );

            $data = $recipients->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->full_name,
                'avatar_url' => $u->avatar_url,
                'thumbnail' => $u->thumbnail,
            ])->values()->all();

            return ResponseHelper::success([
                'recipients' => $data,
                'total' => count($data),
            ], 'Lấy danh sách người nhận thành công');
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi gửi quảng bá', 403);
        }
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'promotable_type' => 'required|string|in:' . implode(',', PromotionService::PROMOTABLE_TYPES),
            'promotable_id' => 'required|integer|min:1',
            'recipient_ids' => 'sometimes|array',
            'recipient_ids.*' => 'integer|exists:users,id',
        ]);

        try {
            $userId = auth()->id();
            $recipientIds = isset($validated['recipient_ids']) ? $validated['recipient_ids'] : null;

            $result = $this->promotionService->sendPromotion(
                $userId,
                $validated['promotable_type'],
                (int) $validated['promotable_id'],
                $recipientIds
            );

            return ResponseHelper::success([
                'sent_count' => $result['sent_count'],
                'recipients' => $result['recipients'],
            ], "Đã gửi quảng bá tới {$result['sent_count']} người");
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi gửi quảng bá', 403);
        }
    }
}
