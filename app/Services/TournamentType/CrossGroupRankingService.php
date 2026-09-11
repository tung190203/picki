<?php

namespace App\Services\TournamentType;

use App\Models\Group;
use App\Models\Tournament;
use App\Models\TournamentType;

/**
 * Service quản lý logic cross_group_ranking:
 * - Đánh giá runtime applicability từ config + group DB state
 * - Sync thể lệ xét Nhì/Ba vào tournament.description (idempotent)
 */
class CrossGroupRankingService
{
    /**
     * Cross-group ranking rule text block.
     */
    public const RULE_BLOCK_MARKER = "Quy tắc xét Nhì/Ba bảng:";

    public const RULE_TEXT = "Quy tắc xét Nhì/Ba bảng:
Khi các bảng có số đội không đều, thành tích các đội Nhì/Ba được quy đổi về cùng số trận. Ở các bảng có nhiều đội hơn bảng nhỏ nhất, kết quả các trận gặp đội xếp cuối bảng sẽ không được tính khi so sánh Nhì/Ba giữa các bảng. Việc loại trận chỉ phục vụ xét Nhì/Ba, không làm thay đổi thứ hạng trong bảng hoặc điểm trình.";

    /**
     * Default cross_group_ranking config when nothing is sent.
     */
    public const DEFAULT_CONFIG = [
        'enabled' => false,
        'apply_to' => ['runner_up', 'third_place'],
        'exclude_bottom_team_matches' => true,
    ];

    /**
     * Valid values for apply_to entries.
     */
    public const VALID_APPLY_TO = ['runner_up', 'third_place'];

    /**
     * Evaluate runtime applicability of cross-group ranking for a tournament type.
     *
     * Runtime applicability = rule thực sự chạy được (đủ teams, không đồng đều).
     * Dùng cho API 1.
     *
     * @param  TournamentType $type
     * @param  array         $rawConfig  Raw cross_group_ranking from request
     * @return array
     */
    public function evaluate(TournamentType $type, array $rawConfig = []): array
    {
        $config = $this->normalizeConfig($rawConfig);
        $enabled = $config['enabled'];

        $groupTeamCounts = $this->getGroupTeamCounts($type);
        $numberOfGroups = count($groupTeamCounts);
        $isUniform = $this->isUniform($groupTeamCounts);

        $applied = false;
        $minimumGroupSize = null;

        // Lấy num_advancing_teams từ config để xác định có cần so sánh cross-group hay không.
        // Cross-group comparison chỉ áp dụng khi num_advancing_teams = 1 (mỗi bảng chỉ lấy Nhất).
        // Khi num_advancing_teams >= 2, mỗi bảng đã có Nhất + Nhì (hoặc hơn) đi tiếp,
        // không cần so sánh Nhì/Ba giữa các bảng.
        $config = $type->format_specific_config ?? [];
        $mainConfig = is_array($config) && isset($config[0]) ? $config[0] : $config;
        $numAdvancing = max(0, (int) ($mainConfig['pool_stage']['num_advancing_teams'] ?? 0));

        if ($enabled
            && $type->format === TournamentType::FORMAT_MIXED
            && $numberOfGroups >= 2
            && !$isUniform
            && $numAdvancing === 1
        ) {
            $applied = true;
            $minimumGroupSize = min(array_filter($groupTeamCounts)) ?: null;
        }

        return [
            'enabled' => $enabled,
            'applied' => $applied,
            'minimum_group_size' => $minimumGroupSize,
            'group_team_counts' => $groupTeamCounts,
            'number_of_groups' => $numberOfGroups,
            'is_group_counts_uniform' => $isUniform,
        ];
    }

    /**
     * Configuration-time applicability check.
     *
     * Mục đích: quyết định có nên hiển thị "Quy tắc xét Nhì/Ba" trong mô tả giải
     * ngay khi user chọn xong thể thức (khi teams có thể chưa được assign).
     *
     * Điều kiện:
     *  - cross_group_ranking.enabled = true (user bật setting)
     *  - format = MIXED (Hỗn hợp)
     *  - Số bảng >= 2
     *  - num_advancing_teams = 1 (chỉ khi lấy 1 đội/bảng mới cần so sánh cross-group)
     *
     * Không yêu cầu !isUniform vì lúc user vừa chọn thể thức, các bảng có thể đang
     * trống chờ teams được assign sau.
     *
     * @param  TournamentType $type
     * @param  array         $rawConfig
     * @return array{enabled:bool, configured:bool, number_of_groups:int}
     */
    public function evaluateConfiguration(TournamentType $type, array $rawConfig = []): array
    {
        $config = $this->normalizeConfig($rawConfig);
        $enabled = $config['enabled'];

        $groupTeamCounts = $this->getGroupTeamCounts($type);
        $numberOfGroups = count($groupTeamCounts);

        // Lấy num_advancing_teams từ config.
        $fullConfig = $type->format_specific_config ?? [];
        $mainConfig = is_array($fullConfig) && isset($fullConfig[0]) ? $fullConfig[0] : $fullConfig;
        $numAdvancing = max(0, (int) ($mainConfig['pool_stage']['num_advancing_teams'] ?? 0));

        $configured = $enabled
            && $type->format === TournamentType::FORMAT_MIXED
            && $numberOfGroups >= 2
            && $numAdvancing === 1;

        return [
            'enabled' => $enabled,
            'configured' => $configured,
            'number_of_groups' => $numberOfGroups,
            'group_team_counts' => $groupTeamCounts,
        ];
    }

    /**
     * Normalize the raw cross_group_ranking config from request.
     * Returns a clean array with defaults for missing keys.
     */
    public function normalizeConfig(array $raw): array
    {
        $default = self::DEFAULT_CONFIG;

        if (empty($raw)) {
            return $default;
        }

        $enabled = array_key_exists('enabled', $raw)
            ? filter_var($raw['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : $default['enabled'];

        // Ensure enabled is bool
        if ($enabled === null) {
            $enabled = $default['enabled'];
        }

        $applyTo = [];
        if (isset($raw['apply_to']) && is_array($raw['apply_to'])) {
            foreach ($raw['apply_to'] as $item) {
                if (in_array($item, self::VALID_APPLY_TO, true)) {
                    $applyTo[] = $item;
                }
            }
        }
        if (empty($applyTo)) {
            $applyTo = $default['apply_to'];
        }

        $excludeBottom = array_key_exists('exclude_bottom_team_matches', $raw)
            ? filter_var($raw['exclude_bottom_team_matches'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : $default['exclude_bottom_team_matches'];
        if ($excludeBottom === null) {
            $excludeBottom = $default['exclude_bottom_team_matches'];
        }

        return [
            'enabled' => $enabled,
            'apply_to' => $applyTo,
            'exclude_bottom_team_matches' => $excludeBottom,
        ];
    }

    /**
     * Sync the cross-group ranking rule text into tournament.description.
     * Idempotent — safe to call multiple times.
     *
     * @param Tournament $tournament
     * @param array      $evaluation  Output từ evaluate() hoặc evaluateConfiguration()
     * @param array      $rawConfig
     */
    public function syncDescription(Tournament $tournament, array $evaluation, array $rawConfig = []): void
    {
        // Hỗ trợ cả 2 nguồn evaluation:
        // - evaluate() trả 'applied' (runtime)
        // - evaluateConfiguration() trả 'configured' (configuration-time)
        $shouldHaveRule = (bool) (
            ($evaluation['applied'] ?? false)
            || ($evaluation['configured'] ?? false)
        );

        $current = $tournament->description ?? '';
        $newDescription = $this->rebuildDescription($current, $shouldHaveRule);

        if ($newDescription !== $current) {
            $tournament->updateQuietly(['description' => $newDescription]);
        }
    }

    /**
     * Strip the cross-group ranking rule block from a description string.
     * Used by the idempotent rebuild logic.
     */
    public function stripRuleBlock(string $description): string
    {
        $marker = self::RULE_BLOCK_MARKER;
        $pos = strpos($description, $marker);

        if ($pos === false) {
            return $description;
        }

        // Everything before the rule block (trim trailing newlines)
        $userContent = rtrim(substr($description, 0, $pos));

        return $userContent;
    }

    /**
     * Rebuild description based on whether the rule should be present.
     */
    protected function rebuildDescription(string $current, bool $includeRule): string
    {
        $userContent = $this->stripRuleBlock($current);

        if ($includeRule) {
            $ruleBlock = self::RULE_TEXT;
            // Ensure at least one blank line between user content and rule
            $separator = ($userContent !== '' && !str_ends_with($userContent, "\n"))
                ? "\n\n"
                : '';
            return $userContent . $separator . $ruleBlock;
        }

        return $userContent;
    }

    /**
     * Get the number of teams in each group for the given tournament type.
     * Groups are ordered by their creation order.
     */
    protected function getGroupTeamCounts(TournamentType $type): array
    {
        return $type->groups()
            ->orderBy('id')
            ->withCount('teams')
            ->get()
            ->pluck('teams_count')
            ->all();
    }

    /**
     * Check whether all group team counts are identical.
     * Empty groups (0 teams) count toward uniformity check.
     */
    protected function isUniform(array $counts): bool
    {
        $nonEmpty = array_filter($counts);
        if (empty($nonEmpty)) {
            return true;
        }
        return count(array_unique($nonEmpty)) === 1;
    }
}
