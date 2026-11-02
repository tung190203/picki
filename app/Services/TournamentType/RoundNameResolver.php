<?php

namespace App\Services\TournamentType;

use App\Models\Matches;
use App\Models\TournamentType;
use Illuminate\Support\Collection;

/**
 * Đặt tên vòng đấu cho một tập trận.
 *
 * `matches.round` chỉ là số thứ tự (1, 2, 3...) nên không dùng hiển thị được.
 * Tên vòng thật sự phụ thuộc 2 thứ:
 *   - thể thức của tournament_type (vòng bảng / vòng loại / đấu vòng tròn);
 *   - số cặp đấu trong vòng đó quyết định tên (1 cặp = Chung kết, 2 = Bán kết...).
 *
 * Logic map pairCount -> tên nằm sẵn ở {@see BracketService::getRoundName()};
 * service này chỉ bổ sung phần tính pairCount (gom các leg của một cặp thành 1)
 * và xử lý riêng vòng tranh hạng 3.
 */
class RoundNameResolver
{
    public function __construct(private BracketService $bracketService) {}

    /**
     * Map `[round => tên vòng]` cho các trận của MỘT tournament_type.
     *
     * Phải gọi riêng cho từng tournament_type vì số `round` được đánh số lại
     * từ đầu cho mỗi thể thức của cùng một giải.
     *
     * @param  Collection<int, Matches>  $matches
     * @return array<int, string>
     */
    public function resolve(Collection $matches, int $format, int $numLegs = 1): array
    {
        if ($matches->isEmpty()) {
            return [];
        }

        // Thể thức đấu vòng tròn: mọi vòng đều là vòng bảng.
        if ($format === TournamentType::FORMAT_ROUND_ROBIN) {
            return $matches->groupBy('round')
                ->mapWithKeys(fn ($roundMatches, $round) => [(int) $round => 'Vòng bảng'])
                ->all();
        }

        $chunkSize = max(1, $numLegs);

        return $matches->groupBy('round')
            ->mapWithKeys(function (Collection $roundMatches, $round) use ($format, $chunkSize) {
                $round = (int) $round;

                // Vòng tranh hạng 3: toàn bộ trận trong vòng đều là trận hạng 3.
                if ($roundMatches->every(fn (Matches $m) => (bool) $m->is_third_place)) {
                    return [$round => 'Tranh hạng Ba'];
                }

                // Số cặp đấu = số leg / numLegs. Tính trên branch (bracket_type)
                // nhiều cặp nhất để không bị nhân đôi khi có resurrection bracket
                // (branch 'sub' chỉ chứa một phần nhỏ trận của các vòng trùng).
                $pairCount = (int) ($roundMatches
                    ->groupBy(fn (Matches $m) => $m->bracket_type ?? 'main')
                    ->map(fn (Collection $branch) => $branch->sortBy('id')->chunk($chunkSize)->count())
                    ->max() ?? 0);

                return [$round => $this->bracketService->getRoundName($round, $pairCount, $format)];
            })
            ->all();
    }
}