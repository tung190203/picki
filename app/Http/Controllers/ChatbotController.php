<?php

namespace App\Http\Controllers;

use App\Services\Chatbot\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Handle incoming chatbot message
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1500',
            'history' => 'nullable|array|max:10',
            'history.*.role' => 'required_with:history|string|in:user,model,assistant',
            'history.*.text' => 'nullable|string',
            'history.*.content' => 'nullable|string',
        ]);

        try {
            $user = null;
            if (Auth::guard('api')->check()) {
                $u = Auth::guard('api')->user();
                $user = [
                    'id' => $u->id,
                    'name' => $u->full_name ?: $u->name,
                    'email' => $u->email,
                ];
            }

            $message = trim($request->input('message'));
            $history = $request->input('history', []);

            $result = $this->geminiService->chat($message, $history, $user);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('ChatbotController error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra sự cố khi kết nối tới trợ lý AI. Vui lòng thử lại sau giây lát.',
                'data' => [
                    'reply' => 'Xin lỗi, hệ thống AI đang bận xử lý hoặc kết nối gián đoạn. Bạn có thể thử lại sau ít phút hoặc tra cứu thông tin trực tiếp trên thanh menu nhé!',
                    'cards' => [],
                    'suggestions' => ['Tìm giải đấu sắp tới', 'Tìm sân pickleball']
                ]
            ], 200); // 200 with fallback reply so UI doesn't crash
        }
    }

    /**
     * Get quick starter prompts
     */
    public function getPrompts(): JsonResponse
    {
        $prompts = [
            [
                'id' => 'upcoming_tournaments',
                'title' => 'Giải đấu sắp diễn ra',
                'prompt' => 'Có những giải đấu pickleball nào sắp diễn ra hoặc đang mở đăng ký không?'
            ],
            [
                'id' => 'find_courts',
                'title' => 'Tìm sân Pickleball',
                'prompt' => 'Tìm cho tôi các cụm sân Pickleball trên hệ thống Picki.'
            ],
            [
                'id' => 'rules_kitchen',
                'title' => 'Luật vùng bếp (Kitchen)',
                'prompt' => 'Luật vùng bếp Non-Volley Zone (Kitchen) trong Pickleball quy định như thế nào?'
            ],
            [
                'id' => 'scoring_rules',
                'title' => 'Cách tính điểm',
                'prompt' => 'Giải thích cho tôi cách tính điểm trong thi đấu Pickleball đánh đôi.'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $prompts,
        ]);
    }
}