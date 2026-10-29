<?php

namespace App\Console\Commands;

use App\Badges\Rules\GiantSlayerBadgeRule;
use App\Models\Badge;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\QuickMatch;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Console\Command;

class ReconcileGiantSlayerBadges extends Command
{
    protected $signature = 'badges:reconcile-giant-slayer
                            {--dry-run : Xem trước danh sách thu hồi và cấp mới mà không thay đổi DB}
                            {--no-revoke : Chỉ cấp bù cho người đủ điều kiện, không thu hồi huy hiệu cũ}';

    protected $description = 'Quét lại toàn bộ lịch sử trận đấu để thu hồi huy hiệu GIANT_SLAYER cấp sai và cấp đúng cho người thắng đối thủ có điểm trình cao hơn >= ' . GiantSlayerBadgeRule::MIN_RATING_DIFF;

    public function handle(BadgeService $badgeService, GiantSlayerBadgeRule $rule): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $noRevoke = (bool) $this->option('no-revoke');

        $badge = Badge::where('code', 'GIANT_SLAYER')->first();
        if (!$badge) {
            $this->error('Không tìm thấy huy hiệu GIANT_SLAYER trong bảng badges.');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn('🔍 CHẾ ĐỘ DRY-RUN: Sẽ không ghi thay đổi vào database.');
        }

        $this->info('Đang quét lịch sử các trận đấu đã hoàn thành...');

        $qualifiedContextsByUser = [];

        // 1. Quét giải đấu (Matches)
        Matches::with(['tournamentType.tournament', 'group.tournamentType.tournament'])
            ->whereNotNull('winner_id')
            ->where('is_bye', false)
            ->chunkById(200, function ($matches) use ($rule, &$qualifiedContextsByUser) {
                foreach ($matches as $match) {
                    $evaluation = $rule->evaluateMatch($match);
                    foreach ($evaluation['winner_user_ids'] as $uid) {
                        $qualifiedContextsByUser[$uid] ??= $evaluation['context'];
                    }
                }
            });

        // 2. Quét kèo đấu (MiniMatch)
        MiniMatch::with(['miniTournament'])
            ->where('is_bye', false)
            ->where(function ($q) {
                $q->whereNotNull('team_win_id')
                    ->orWhereNotNull('participant_win_id');
            })
            ->chunkById(200, function ($miniMatches) use ($rule, &$qualifiedContextsByUser) {
                foreach ($miniMatches as $miniMatch) {
                    $evaluation = $rule->evaluateMatch($miniMatch);
                    foreach ($evaluation['winner_user_ids'] as $uid) {
                        $qualifiedContextsByUser[$uid] ??= $evaluation['context'];
                    }
                }
            });

        // 3. Quét QuickMatch
        QuickMatch::whereNotNull('winner')
            ->chunkById(200, function ($quickMatches) use ($rule, &$qualifiedContextsByUser) {
                foreach ($quickMatches as $quickMatch) {
                    $evaluation = $rule->evaluateMatch($quickMatch);
                    foreach ($evaluation['winner_user_ids'] as $uid) {
                        $qualifiedContextsByUser[$uid] ??= $evaluation['context'];
                    }
                }
            });

        $eligibleIds = array_map('intval', array_keys($qualifiedContextsByUser));
        $existingHolderIds = UserBadge::where('badge_id', $badge->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $toRevokeIds = array_values(array_diff($existingHolderIds, $eligibleIds));
        $toAwardIds = array_values(array_diff($eligibleIds, $existingHolderIds));

        $this->newLine();
        $this->info('📊 KẾT QUẢ ĐỐI SOÁT GIANT_SLAYER:');
        $this->line(' - Số người chơi đủ điều kiện thực tế: ' . count($eligibleIds));
        $this->line(' - Số người chơi đang giữ huy hiệu hiện tại: ' . count($existingHolderIds));
        $this->line(' - Số người chơi bị cấp sai cần thu hồi: ' . count($toRevokeIds));
        $this->line(' - Số người chơi đủ điều kiện cần cấp bù: ' . count($toAwardIds));

        // Thu hồi các trường hợp cấp sai
        if (!$noRevoke && !empty($toRevokeIds)) {
            $this->newLine();
            $revokeUsers = User::whereIn('id', $toRevokeIds)->pluck('full_name', 'id');
            foreach ($toRevokeIds as $uid) {
                $name = $revokeUsers[$uid] ?? 'N/A';
                $this->line("   [Thu hồi] User #{$uid} ({$name})");
            }

            if (!$dryRun) {
                $deleted = UserBadge::where('badge_id', $badge->id)
                    ->whereIn('user_id', $toRevokeIds)
                    ->delete();
                foreach ($toRevokeIds as $uid) {
                    $badgeService->writeBadgeAuditLog(
                        action: 'REVOKED',
                        userId: $uid,
                        userName: $revokeUsers[$uid] ?? null,
                        badge: $badge,
                        context: [
                            'reason' => 'Thu hồi huy hiệu GIANT_SLAYER cấp sai qua lệnh badges:reconcile-giant-slayer',
                        ]
                    );
                }
                $this->info("✅ Đã thu hồi {$deleted} huy hiệu GIANT_SLAYER cấp sai.");
            }
        }

        // Cấp bù cho các trường hợp đủ điều kiện nhưng chưa có
        if (!empty($toAwardIds)) {
            $this->newLine();
            $awardUsers = User::whereIn('id', $toAwardIds)->pluck('full_name', 'id');
            $awardedCount = 0;

            foreach ($toAwardIds as $uid) {
                $name = $awardUsers[$uid] ?? 'N/A';
                $ctx = $qualifiedContextsByUser[$uid] ?? [];
                $this->line("   [Cấp bù] User #{$uid} ({$name}) - " . ($ctx['reason'] ?? ''));
                if (!$dryRun) {
                    if ($badgeService->awardBadge($uid, 'GIANT_SLAYER', null, $ctx)) {
                        $awardedCount++;
                    }
                }
            }

            if (!$dryRun) {
                $this->info("✅ Đã cấp bù {$awardedCount} huy hiệu GIANT_SLAYER.");
            }
        }

        return Command::SUCCESS;
    }
}

