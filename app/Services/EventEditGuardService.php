<?php

namespace App\Services;

use App\Enums\ClubWalletTransactionSourceType;
use App\Models\Club\Club;
use App\Models\Club\ClubExpense;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubWallet;
use App\Models\Club\ClubWalletTransaction;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\TournamentFundCollection;
use Illuminate\Support\Facades\DB;

/**
 * Quy tắc sửa / xoá kèo & giải.
 *
 * 1. Chưa thu tiền + chưa nhập trận  → sửa + xoá thoải mái.
 * 2. Đã thu tiền HOẶC đã nhập trận   → chỉ sửa thông tin chung (gồm đổi CLB),
 *    KHÔNG được xoá.
 * 3. Đã đá xong tất cả trận         → không sửa, không xoá.
 *
 * "Đã thu tiền" = có payment ở trạng thái paid/confirmed.
 * "Đã nhập trận" = đã có ít nhất 1 trận được tạo.
 * "Đã xong"     = đã đá xong TẤT CẢ trận thật (`hasAllMatchesCompleted()`),
 *    khác với `is_completed` (status = CLOSED) — kèo auto-close lúc chưa đánh
 *    trận nào vẫn phải sửa/xoá được.
 */
class EventEditGuardService
{
    /**
     * Cache theo event trong 1 request để resource không query lặp.
     *
     * @var array<string, array>
     */
    private array $cache = [];

    /**
     * Đã có hành động thu tiền thật (người chơi đã nộp hoặc BTC đã xác nhận).
     */
    public function hasCollectedMoney(MiniTournament|Tournament $event): bool
    {
        return $this->remember($event, 'money', function () use ($event) {
            // Ưu tiên count đã eager-load (withCount) để khỏi query lặp.
            $count = $event->getAttribute('paid_payments_count');
            if ($count !== null) {
                return (int) $count > 0;
            }

            if ($event instanceof MiniTournament) {
                return $event->participantPayments()
                    ->whereIn('status', ['paid', 'confirmed'])
                    ->exists();
            }

            return $event->payments()
                ->whereIn('status', ['paid', 'confirmed'])
                ->exists();
        });
    }

    /**
     * Đã nhập trận đấu (có ít nhất 1 trận được tạo, chưa cần có điểm).
     */
    public function hasEnteredMatches(MiniTournament|Tournament $event): bool
    {
        return $this->remember($event, 'matches', function () use ($event) {
            // match_count được set bởi scopeWithFullRelations (selectSub).
            $count = $event->getAttribute('match_count');
            if ($count !== null) {
                return (int) $count > 0;
            }

            if ($event instanceof MiniTournament) {
                // `matches` đã eager-load trong scopeWithFullRelations.
                if ($event->relationLoaded('matches')) {
                    return $event->matches->isNotEmpty();
                }

                return $event->matches()->exists();
            }

            return $event->tournamentTypes()
                ->whereHas('matches')
                ->exists();
        });
    }

    /**
     * Đã có hành động thu tiền hoặc nhập trận → khoá xoá.
     * Đã hoàn thành thì cũng không xoá được.
     */
    public function isDeletionLocked(MiniTournament|Tournament $event): bool
    {
        return $this->isUpdateLocked($event)
            || $this->hasCollectedMoney($event)
            || $this->hasEnteredMatches($event);
    }

    /**
     * Kèo/giải đã xong → khoá cả sửa.
     *
     * Dùng `hasAllMatchesCompleted()` (đã đá xong TẤT CẢ trận thật) chứ không
     * dùng `is_completed` — vì `is_completed` chỉ kiểm tra status = CLOSED, mà
     * kèo auto-close lúc 0 trận cũng bị CLOSED → sẽ khoá xoá oan.
     */
    public function isUpdateLocked(MiniTournament|Tournament $event): bool
    {
        return $this->remember($event, 'completed', function () use ($event) {
            return $event->hasAllMatchesCompleted();
        });
    }

    /**
     * Thông báo lý do không được xoá.
     */
    public function deletionBlockReason(MiniTournament|Tournament $event): ?string
    {
        return $this->remember($event, 'reason', function () use ($event) {
            if ($this->isUpdateLocked($event)) {
                return 'Kèo/giải đã hoàn thành nên không thể xoá.';
            }

            if ($this->hasCollectedMoney($event)) {
                return 'Kèo/giải đã có thu tiền nên không thể xoá. Chỉ có thể sửa thông tin chung.';
            }

            if ($this->hasEnteredMatches($event)) {
                return 'Kèo/giải đã nhập trận đấu nên không thể xoá. Chỉ có thể sửa thông tin chung.';
            }

            return null;
        });
    }

    /**
     * Cache kết quả theo (model class, id, key) trong 1 request.
     */
    private function remember(MiniTournament|Tournament $event, string $key, callable $callback): mixed
    {
        $cacheKey = $event::class.':'.$event->getKey().':'.$key;

        if (! array_key_exists($cacheKey, $this->cache)) {
            $this->cache[$cacheKey] = $callback();
        }

        return $this->cache[$cacheKey];
    }

    /**
     * Chuyển toàn bộ dấu vết tài chính sang CLB mới khi đổi CLB.
     *
     * - ClubFundCollection / TournamentFundCollection → đổi club_id.
     * - ClubExpense → đổi club_id + chuyển giao dịch ví sang ví CLB mới.
     * - ClubWalletTransaction (source_type fund_collection / tournament_fee)
     *   → chuyển sang ví CLB mới.
     */
    public function moveFinanceToClub(MiniTournament|Tournament $event, int $newClubId): void
    {
        $oldClubId = (int) $event->club_id;
        if (! $newClubId || $oldClubId === $newClubId) {
            return;
        }

        DB::transaction(function () use ($event, $oldClubId, $newClubId) {
            $newWallet = $this->resolveWalletForClub($newClubId);

            // 1. Đợt thu quỹ của kèo (ClubFundCollection)
            $collections = collect();

            if ($event instanceof MiniTournament && $event->club_fund_collection_id) {
                $collection = ClubFundCollection::find($event->club_fund_collection_id);
                if ($collection) {
                    $collections->push($collection);
                }
            }

            if ($event instanceof Tournament && $event->club_fund_collection_id) {
                $collection = ClubFundCollection::find($event->club_fund_collection_id);
                if ($collection) {
                    $collections->push($collection);
                }
            }

            if ($event instanceof Tournament && $event->tournament_fund_collection_id) {
                $collection = TournamentFundCollection::find($event->tournament_fund_collection_id);
                if ($collection) {
                    $collection->update(['club_id' => $newClubId]);
                }
            }

            foreach ($collections as $collection) {
                $collection->update(['club_id' => $newClubId]);

                // Chuyển giao dịch ví của đợt thu sang ví CLB mới.
                // Giao dịch liên kết qua contribution.wallet_transaction_id
                // hoặc qua source_type=fund_collection + source_id=collection.id.
                $txIds = ClubWalletTransaction::where('source_type', ClubWalletTransactionSourceType::FundCollection)
                    ->where('source_id', $collection->id)
                    ->pluck('id');

                $txIds = $txIds->merge(
                    $collection->contributions()
                        ->whereNotNull('wallet_transaction_id')
                        ->pluck('wallet_transaction_id')
                )->unique()->values();

                ClubWalletTransaction::whereIn('id', $txIds)
                    ->update(['club_wallet_id' => $newWallet->id]);
            }

            // 2. Chi phí CLB cho kèo (ClubExpense) + giao dịch ví OUT
            if ($event instanceof MiniTournament) {
                $expenses = ClubExpense::where('mini_tournament_id', $event->id)->get();

                foreach ($expenses as $expense) {
                    $expense->update(['club_id' => $newClubId]);

                    if ($expense->wallet_transaction_id) {
                        ClubWalletTransaction::where('id', $expense->wallet_transaction_id)
                            ->update(['club_wallet_id' => $newWallet->id]);
                    }
                }
            }
        });
    }

    /**
     * Lấy (hoặc tạo) ví chính của CLB.
     */
    private function resolveWalletForClub(int $clubId): ClubWallet
    {
        $club = Club::with('mainWallet')->find($clubId);
        if (! $club) {
            throw new \InvalidArgumentException("CLB {$clubId} không tồn tại");
        }

        if ($club->mainWallet) {
            return $club->mainWallet;
        }

        return ClubWallet::create([
            'club_id' => $clubId,
            'currency' => 'VND',
        ]);
    }
}
