<?php

namespace App\Services\Chatbot;

use App\Models\CompetitionLocation;
use App\Models\Tournament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected string $model;
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.5-flash-lite');
    }

    /**
     * Send a message to the Gemini chatbot with intelligent RAG and database context.
     *
     * @param string $message
     * @param array $history
     * @param array|null $currentUser
     * @return array
     */
    public function chat(string $message, array $history = [], ?array $currentUser = null): array
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');

        // 1. Intent classification
        $isRule = str_contains($lower, 'luật') || 
                  str_contains($lower, 'quy tắc') || 
                  str_contains($lower, 'điều lệ') || 
                  str_contains($lower, 'kitchen') || 
                  str_contains($lower, 'vùng bếp') || 
                  str_contains($lower, 'tính điểm') || 
                  str_contains($lower, 'fault') || 
                  str_contains($lower, 'lỗi') || 
                  str_contains($lower, 'giao bóng') || 
                  str_contains($lower, 'phát bóng') || 
                  str_contains($lower, 'nảy');

        $isTournament = !$isRule && (
            str_contains($lower, 'giải đấu') || 
            str_contains($lower, 'tournament') || 
            str_contains($lower, 'tham gia giải') || 
            str_contains($lower, 'đăng ký giải') || 
            str_contains($lower, 'tìm giải') || 
            (str_contains($lower, 'giải') && !str_contains($lower, 'giải thích') && !str_contains($lower, 'giải đáp')) ||
            (str_contains($lower, 'đấu') && !str_contains($lower, 'thi đấu'))
        );

        $isCourt = !$isRule && (
            str_contains($lower, 'sân') || 
            str_contains($lower, 'địa điểm') || 
            str_contains($lower, 'court') || 
            str_contains($lower, 'bãi') || 
            str_contains($lower, 'chỗ chơi')
        );

        // 2. Fetch appropriate context and rich cards
        $contextData = '';
        $collectedCards = [];

        if ($isRule) {
            $contextData = $this->getRulesContext($lower);
        } elseif ($isTournament) {
            $tResult = $this->queryTournaments($message);
            $contextData = $tResult['context'];
            $collectedCards = $tResult['cards'];
        } elseif ($isCourt) {
            $cResult = $this->queryCourts($message);
            $contextData = $cResult['context'];
            $collectedCards = $cResult['cards'];
        }

        // 3. Try Gemini API
        if (!empty($this->apiKey)) {
            $aiResponse = $this->callGeminiWithFallback($message, $history, $currentUser, $contextData);
            if ($aiResponse !== null) {
                return [
                    'reply' => $aiResponse,
                    'cards' => $collectedCards,
                    'suggestions' => $this->generateSuggestions($message, $isRule, $isTournament, $isCourt),
                ];
            }
        }

        // 4. Robust local fallback if API is unreachable
        return $this->localFallbackResponse($message, $currentUser, $isRule, $isTournament, $isCourt, $collectedCards);
    }

    /**
     * Call Gemini API with model fallback chain
     */
    protected function callGeminiWithFallback(string $message, array $history, ?array $currentUser, string $contextData): ?string
    {
        $modelsToTry = array_unique(array_filter([
            $this->model,
            'gemini-2.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-3.6-flash',
            'gemini-flash-latest'
        ]));

        foreach ($modelsToTry as $model) {
            try {
                $reply = $this->executeGeminiChat($model, $message, $history, $currentUser, $contextData);
                if (!empty($reply)) {
                    return $reply;
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini model [{$model}] error: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Execute Gemini HTTP request
     */
    protected function executeGeminiChat(string $model, string $message, array $history, ?array $currentUser, string $contextData): ?string
    {
        $systemPrompt = $this->buildSystemPrompt($currentUser, $contextData);

        $contents = [];
        // Last 4 turns
        $recent = array_slice($history, -4);
        foreach ($recent as $turn) {
            $role = ($turn['role'] ?? 'user') === 'user' ? 'user' : 'model';
            $txt = $turn['text'] ?? ($turn['content'] ?? '');
            if (!empty($txt)) {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => (string) $txt]]
                ];
            }
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]]
        ];

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.6,
                'maxOutputTokens' => 2500,
            ],
        ];

        $endpoint = "{$this->apiUrl}/{$model}:generateContent?key={$this->apiKey}";

        $res = Http::timeout(12)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $this->apiKey,
            ])
            ->post($endpoint, $payload);

        if (!$res->successful()) {
            Log::warning("Gemini error status [{$res->status()}]: " . $res->body());
            return null;
        }

        $data = $res->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (empty(trim((string)$text))) {
            return null;
        }

        return $this->sanitizeResponseText((string)$text);
    }

    /**
     * Post-processing to guarantee natural human-like tone and eliminate awkward AI phrases
     */
    protected function sanitizeResponseText(string $text): string
    {
        $patterns = [
            '/\b(Chào|chào)\s+Khách[,!\.]?/iu' => 'Dạ chào bạn,',
            '/\b(giúp|hỗ trợ)\s+gì\s+cho\s+Khách\b/iu' => 'hỗ trợ gì cho bạn',
            '/\bcho\s+Khách\b/iu' => 'cho bạn',
            '/\bcủa\s+Khách\b/iu' => 'của bạn',
            '/\bgiúp\s+Khách\b/iu' => 'giúp bạn',
            '/\bvới\s+Khách\b/iu' => 'với bạn',
            '/\bKhách\s+có\s+thể\b/iu' => 'bạn có thể',
            '/\bKhách\s+ạ\b/iu' => 'bạn nhé',
            '/\bTôi có thể\b/iu' => 'Mình có thể',
            '/\bTôi là\b/iu' => 'Mình là',
            '/\bTôi sẵn sàng\b/iu' => 'Mình sẵn sàng',
            '/\bTôi\b/u' => 'mình',
        ];
        $text = preg_replace(array_keys($patterns), array_values($patterns), $text);

        // Strip emojis if any slipped through
        $text = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1FA70}-\x{1FAFF}]/u', '', $text);

        return trim($text);
    }

    /**
     * Query tournaments in database with proper route URLs
     */
    public function queryTournaments(string $query = ''): array
    {
        try {
            $q = Tournament::with(['competitionLocation'])
                ->where('is_private', false);

            $stopWords = [
                'có', 'giải đấu', 'giải', 'nào', 'sắp', 'diễn ra', 'diễn', 'ra', 'không', 'ko', 'k',
                'tìm', 'ở', 'tại', 'gần đây', 'gần', 'đây', 'xem', 'danh sách', 'cho', 'tôi', 'mình',
                'với', 'những', 'các', 'thế', 'thế nào', 'hỏi', 'về', 'ạ', 'nhé', 'nha', 'đang', 'mở', 'đăng ký'
            ];
            $cleanQuery = mb_strtolower($query, 'UTF-8');
            foreach ($stopWords as $w) {
                $cleanQuery = str_replace($w, ' ', $cleanQuery);
            }
            $cleanQuery = trim(preg_replace('/\s+/', ' ', $cleanQuery));

            if (!empty($cleanQuery) && mb_strlen($cleanQuery) >= 3) {
                $q->where(function ($sub) use ($cleanQuery) {
                    $sub->where('name', 'like', "%{$cleanQuery}%")
                        ->orWhere('description', 'like', "%{$cleanQuery}%");
                });
            }

            $tournaments = $q->orderBy('start_date', 'desc')
                ->limit(4)
                ->get();

            $context = "DANH SÁCH GIẢI ĐẤU TRÊN HỆ THỐNG PICKI:\n";
            $cards = [];

            foreach ($tournaments as $t) {
                $loc = $t->competitionLocation->name ?? 'Đang cập nhật địa điểm';
                $date = $t->start_date ? date('d/m/Y', strtotime($t->start_date)) : 'Sắp diễn ra';
                $fee = $t->has_fee ? number_format($t->fee_amount ?? 0) . 'đ' : 'Miễn phí';

                $context .= "- Giải: {$t->name} | Địa điểm: {$loc} | Ngày bắt đầu: {$date} | Lệ phí: {$fee}\n";

                $cards[] = [
                    'type' => 'tournament',
                    'id' => $t->id,
                    'title' => $t->name,
                    'subtitle' => $loc,
                    'date' => $date,
                    'fee' => $fee,
                    'status' => $t->status ?? 'open',
                    'image' => $t->poster_url ?: null,
                    // Official Vue route for tournament detail
                    'url' => "/tournament-detail/{$t->id}",
                ];
            }

            return ['context' => $context, 'cards' => $cards];
        } catch (\Throwable $e) {
            Log::error('Query tournaments error: ' . $e->getMessage());
            return ['context' => '', 'cards' => []];
        }
    }

    /**
     * Query courts in database
     */
    public function queryCourts(string $query = ''): array
    {
        try {
            $q = CompetitionLocation::where('is_banned', false);

            $stopWords = [
                'tìm', 'sân bóng', 'cụm sân', 'sân', 'ở', 'tại', 'gần đây', 'gần', 'đây',
                'có', 'nào', 'không', 'ko', 'k', 'cho', 'tôi', 'mình', 'xem', 'danh sách',
                'nhé', 'nha', 'ạ'
            ];
            $cleanQuery = mb_strtolower($query, 'UTF-8');
            foreach ($stopWords as $w) {
                $cleanQuery = str_replace($w, ' ', $cleanQuery);
            }
            $cleanQuery = trim(preg_replace('/\s+/', ' ', $cleanQuery));

            if (!empty($cleanQuery) && mb_strlen($cleanQuery) >= 3) {
                $q->where(function ($sub) use ($cleanQuery) {
                    $sub->where('name', 'like', "%{$cleanQuery}%")
                        ->orWhere('address', 'like', "%{$cleanQuery}%");
                });
            }

            $courts = $q->withCount('competitionLocationYards')
                ->orderBy('id', 'desc')
                ->limit(4)
                ->get();

            $context = "DANH SÁCH CỤM SÂN PICKLEBALL TRÊN HỆ THỐNG PICKI:\n";
            $cards = [];

            foreach ($courts as $c) {
                $address = $c->address ?: 'Đang cập nhật địa chỉ';
                $yards = ($c->competition_location_yards_count ?? 0) . ' sân';
                $phone = $c->phone ?: 'Chưa cập nhật SĐT';
                $hours = ($c->opening_time && $c->closing_time) ? "{$c->opening_time} - {$c->closing_time}" : 'Cả ngày';

                $context .= "- Cụm sân: {$c->name} | Địa chỉ: {$address} | Quy mô: {$yards} | Giờ hoạt động: {$hours} | SĐT: {$phone}\n";

                $cards[] = [
                    'type' => 'court',
                    'id' => $c->id,
                    'title' => $c->name,
                    'subtitle' => $address,
                    'phone' => $phone,
                    'yards' => $yards,
                    'hours' => $hours,
                    'image' => $c->avatar_url ?: $c->image ?: null,
                    'url' => '/map',
                ];
            }

            return ['context' => $context, 'cards' => $cards];
        } catch (\Throwable $e) {
            Log::error('Query courts error: ' . $e->getMessage());
            return ['context' => '', 'cards' => []];
        }
    }

    /**
     * Comprehensive official pickleball rules knowledge
     */
    public function getRulesContext(string $lower): string
    {
        return "QUY ĐỊNH VÀ LUẬT THI ĐẤU PICKLEBALL CHÍNH THỨC:

1. VÙNG BẾP (KITCHEN / NON-VOLLEY ZONE):
- Khu vực rộng 2.13m (7 feet) tính từ lưới ở mỗi bên sân.
- Người chơi KHÔNG ĐƯỢC phép đánh bóng trên không (vô-lê) khi đang đứng trong vùng bếp hoặc có bất kỳ bộ phận cơ thể/trang bị nào chạm vào vùng bếp (kể cả vạch bếp).
- Chỉ được phép bước vào vùng bếp để đánh bóng sau khi bóng đã nảy trên mặt đất trong vùng bếp.

2. QUY TẮC 2 LẦN NẢY (TWO-BOUNCE RULE):
- Sau khi giao bóng, đội nhận bóng phải để bóng nảy một lần trước khi đánh trả.
- Đội giao bóng sau đó cũng phải để bóng nảy một lần nữa trước khi đánh trả.
- Sau 2 lần nảy này (mỗi bên 1 lần), cả hai bên mới được phép đánh vô-lê trên không.

3. QUY TẮC GIAO BÓNG (SERVE):
- Giao bóng phải thực hiện dưới tay (underhand), điểm tiếp xúc giữa vợt và bóng phải ở dưới thắt lưng.
- Đỉnh đầu vợt phải thấp hơn điểm cao nhất của cổ tay khi chạm bóng.
- Bóng phải được đánh chéo sân vào ô giao bóng đối diện và phải bay vượt qua vạch bếp.

4. CÁCH TÍNH ĐIỂM (SCORING):
- Chỉ bên đang nắm quyền giao bóng mới có thể ghi điểm.
- Trận đấu thường diễn ra đến 11 điểm (thắng cách biệt 2 điểm), hoặc 15, 21 điểm tùy thể thức giải.
- Trong đánh đôi, điểm số được gọi bằng 3 chữ số: Điểm bên giao - Điểm bên nhận - Thứ tự người giao bóng (1 hoặc 2). Ví dụ '4 - 3 - 1'.";
    }

    /**
     * Local fallback response when API key is missing or offline
     */
    public function localFallbackResponse(
        string $message,
        ?array $currentUser,
        bool $isRule,
        bool $isTournament,
        bool $isCourt,
        array $cards = []
    ): array {
        $lower = mb_strtolower(trim($message), 'UTF-8');

        // Priority 1: Rule query
        if ($isRule) {
            $reply = "Dưới đây là tóm tắt các quy định cơ bản trong luật thi đấu Pickleball:\n\n" .
                     "1. **Vùng bếp (Kitchen / Non-Volley Zone)**: Khu vực 2.13m tính từ lưới. Người chơi không được vô-lê khi chân hoặc trang bị chạm vào vùng này (chỉ được vào đánh sau khi bóng đã nảy).\n\n" .
                     "2. **Quy tắc 2 lần nảy (Two-Bounce Rule)**: Sau khi giao bóng, bên đỡ phải chờ bóng nảy 1 lần mới đánh trả, và bên giao cũng phải chờ bóng nảy 1 lần trước khi đánh tiếp. Sau đó hai bên mới được tự do vô-lê.\n\n" .
                     "3. **Luật giao bóng**: Điểm chạm bóng phải dưới thắt lưng, đầu vợt thấp hơn cổ tay và bóng phải bay chéo sân qua vạch bếp.\n\n" .
                     "4. **Cách tính điểm đánh đôi**: Trận đấu chơi đến 11 điểm (thắng cách biệt 2 điểm). Chỉ bên giao bóng mới có thể ghi điểm. Điểm số gọi theo 3 số: Điểm giao - Điểm nhận - Thứ tự người giao (1 hoặc 2).";

            return [
                'reply' => $reply,
                'cards' => [],
                'suggestions' => ['Cách tính điểm đánh đôi', 'Lỗi chân vùng bếp', 'Tìm giải đấu sắp diễn ra']
            ];
        }

        // Priority 2: Tournament query
        if ($isTournament) {
            if (empty($cards)) {
                $tResult = $this->queryTournaments($message);
                $cards = $tResult['cards'];
            }

            if (!empty($cards)) {
                $reply = "Dưới đây là các giải đấu Pickleball đang có trên hệ thống Picki. Bạn có thể nhấn 'Chi tiết' để xem thể lệ và đăng ký tham gia:";
                return [
                    'reply' => $reply,
                    'cards' => $cards,
                    'suggestions' => ['Tìm sân bóng pickleball', 'Xem luật thi đấu pickleball']
                ];
            } else {
                return [
                    'reply' => "Hiện tại chưa có giải đấu mới phù hợp với từ khóa này. Bạn có thể xem danh sách giải đấu trên thanh menu chính của Picki nhé.",
                    'cards' => [],
                    'suggestions' => ['Tìm sân bóng pickleball', 'Luật thi đấu pickleball']
                ];
            }
        }

        // Priority 3: Court query
        if ($isCourt) {
            if (empty($cards)) {
                $cResult = $this->queryCourts($message);
                $cards = $cResult['cards'];
            }

            return [
                'reply' => "Dưới đây là danh sách một số cụm sân Pickleball trên hệ thống Picki. Bạn có thể xem địa chỉ và liên hệ đặt sân:",
                'cards' => $cards,
                'suggestions' => ['Có giải đấu nào sắp diễn ra không?', 'Luật vùng bếp pickleball']
            ];
        }

        // Priority 4: Greeting
        $hasName = !empty($currentUser['name']) && $currentUser['name'] !== 'Khách' && $currentUser['name'] !== 'PickiUser';
        $greetingTarget = $hasName ? $currentUser['name'] : 'bạn';

        $reply = "Dạ chào {$greetingTarget}! Picki có thể hỗ trợ gì cho bạn hôm nay ạ?\n\n" .
                 "Bạn có thể hỏi mình về:\n" .
                 "- Lịch thi đấu và đăng ký các giải Pickleball\n" .
                 "- Địa chỉ cụm sân và liên hệ đặt sân\n" .
                 "- Quy định, cách tính điểm và luật thi đấu";

        return [
            'reply' => $reply,
            'cards' => [],
            'suggestions' => [
                'Giải đấu sắp diễn ra',
                'Tìm sân Pickleball',
                'Luật vùng bếp (Kitchen)',
                'Cách tính điểm'
            ]
        ];
    }

    /**
     * Build system prompt for Gemini
     */
    protected function buildSystemPrompt(?array $currentUser, string $contextData): string
    {
        $hasName = !empty($currentUser['name']) && $currentUser['name'] !== 'Khách' && $currentUser['name'] !== 'PickiUser';
        $userGreetingInfo = $hasName 
            ? "Tên người dùng: {$currentUser['name']}. Xưng hô: gọi người dùng là 'bạn {$currentUser['name']}' hoặc 'bạn'."
            : "Xưng hô: Gọi người dùng là 'bạn'.";

        $prompt = "Bạn là nhân viên tư vấn hỗ trợ của nền tảng Pickleball Picki tại Việt Nam.
{$userGreetingInfo}

QUY TẮC XƯNG HÔ VÀ PHONG CÁCH (BẮT BUỘC TUÂN THỦ 100%):
1. Tự xưng là 'mình' hoặc 'Picki'. TUYỆT ĐỐI KHÔNG xưng là 'tôi', 'AI', 'bot' hay 'mô hình ngôn ngữ'.
2. Luôn gọi người dùng là 'bạn' (hoặc 'bạn + tên'). 
3. TUYỆT ĐỐI NGHIÊM CẤM dùng từ 'Khách' hay 'quý khách' để gọi người dùng. Không bao giờ được dùng các câu như 'Chào Khách', 'giúp gì cho Khách', 'Khách có cần...'.
4. Giọng điệu: Thân thiện, tôn trọng, tự nhiên như nhân viên chăm sóc khách hàng người thật tại Việt Nam.
5. TUYỆT ĐỐI KHÔNG sử dụng bất kỳ biểu tượng cảm xúc (emoji/icon) nào trong câu trả lời.

GIỚI HẠN PHẠM VI TRẢ LỜI (BẮT BUỘC NGHIÊM NGẶT):
Bạn CHỈ ĐƯỢC PHÉP trả lời các câu hỏi thuộc 2 chủ đề sau:
- Môn thể thao Pickleball: Luật thi đấu chính thức (luật vùng bếp Kitchen, luật 2 lần nảy, luật giao bóng, lỗi phát bóng, cách tính điểm đơn và đôi...), kỹ thuật, chiến thuật, thuật ngữ, dụng cụ vợt/bóng Pickleball.
- Hệ thống và nền tảng Picki: Các giải đấu trên hệ thống, đăng ký tham gia giải, danh sách và địa chỉ cụm sân Pickleball, đặt sân, câu lạc bộ, tính năng hệ thống.

NẾU NGƯỜI DÙNG HỎI BẤT CỨ CHỦ ĐỀ NÀO KHÔNG LIÊN QUAN ĐẾN PICKLEBALL HOẶC HỆ THỐNG PICKI (ví dụ: làm thơ, viết code, toán học, thời tiết, chính trị, các môn thể thao khác, việc cá nhân, kiến thức ngoài lề...):
Bạn PHẢI TỪ CHỐI LỊCH SỰ và KHÔNG TRẢ LỜI NỘI DUNG NGOÀI ĐÓ.
Mẫu câu từ chối:
'Dạ, mình chỉ hỗ trợ giải đáp các thông tin về môn Pickleball và hệ thống Picki (như giải đấu, sân bóng, luật thi đấu, tính điểm...). Bạn có cần mình hỗ trợ điều gì về Pickleball không ạ?'

KHI NGƯỜI DÙNG CHỈ CHÀO HỎI (ví dụ: 'xin chào', 'hi', 'hello', 'chào bạn'):
Hãy đáp lại tự nhiên, ngắn gọn:
'Dạ chào bạn! Picki có thể hỗ trợ gì cho bạn về giải đấu, sân chơi hay luật thi đấu Pickleball hôm nay ạ?'";

        if (!empty($contextData)) {
            $prompt .= "\n\nDỮ LIỆU THỰC TẾ TỪ HỆ THỐNG PICKI:\n" . $contextData;
        }

        return $prompt;
    }

    /**
     * Generate smart suggestions
     */
    protected function generateSuggestions(string $msg, bool $isRule, bool $isTournament, bool $isCourt): array
    {
        if ($isRule) {
            return ['Cách tính điểm đánh đôi', 'Lỗi vùng bếp (Kitchen)', 'Tìm giải đấu sắp diễn ra'];
        }
        if ($isTournament) {
            return ['Tìm sân bóng pickleball', 'Luật thi đấu pickleball'];
        }
        if ($isCourt) {
            return ['Giải đấu sắp diễn ra', 'Luật vùng bếp (Kitchen)'];
        }

        return ['Giải đấu sắp diễn ra', 'Tìm sân bóng', 'Luật thi đấu pickleball'];
    }
}
