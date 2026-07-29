<?php

namespace App\Support;

class TournamentSettingsNormalizer
{
    /**
     * Danh sách các fields của tournament settings cùng kiểu dữ liệu mong muốn.
     * 'string'   → giữ nguyên string (trim), nếu rỗng thì null
     * 'int'      → ép về int, nếu rỗng / không phải số thì null
     * 'bool'     → ép về bool
     * 'date'     → giữ nguyên (đã là ISO string), nếu rỗng thì null
     * 'nullable' → chấp nhận mọi giá trị, nếu rỗng thì null
     *
     * Field nào không có trong danh sách sẽ bị loại bỏ để tránh lưu field thừa.
     */
    private const FIELD_TYPES = [
        'sport_id' => 'int',
        'name' => 'string',
        'description' => 'string',
        'competition_location_id' => 'int',
        'competition_location_name' => 'string',
        'start_date' => 'date',
        'registration_open_at' => 'date',
        'registration_closed_at' => 'date',
        'early_registration_deadline' => 'date',
        'duration' => 'int',
        'enable_dupr' => 'bool',
        'enable_vndupr' => 'bool',
        'min_level' => 'int',
        'max_level' => 'int',
        'age_group' => 'int',
        'gender_policy' => 'int',
        'participant' => 'string',
        'max_team' => 'int',
        'player_per_team' => 'int',
        'max_player' => 'int',
        'is_private' => 'bool',
        'auto_approve' => 'bool',
        'creator_join' => 'int',
        'club_id' => 'int',
        'has_fee' => 'bool',
        'fee_amount' => 'int',
        'auto_split_fee' => 'bool',
        'fee_description' => 'string',
        'qr_code_url' => 'string',
        'zalo_link' => 'string',
        'main_phone' => 'string',
        'sub_phone' => 'string',
        'is_public_branch' => 'bool',
        'is_own_score' => 'bool',
    ];

    /**
     * Các fields của mini tournament (không có participant, max_team/player_per_team/max_player,
     * creator_join, is_public_branch, is_own_score).
     */
    private const MINI_FIELD_TYPES = [
        'sport_id' => 'int',
        'name' => 'string',
        'description' => 'string',
        'competition_location_id' => 'int',
        'competition_location_name' => 'string',
        'start_date' => 'date',
        'registration_open_at' => 'date',
        'registration_closed_at' => 'date',
        'early_registration_deadline' => 'date',
        'duration' => 'int',
        'enable_dupr' => 'bool',
        'enable_vndupr' => 'bool',
        'min_level' => 'int',
        'max_level' => 'int',
        'age_group' => 'int',
        'gender_policy' => 'int',
        'is_private' => 'bool',
        'auto_approve' => 'bool',
        'club_id' => 'int',
        'has_fee' => 'bool',
        'fee_amount' => 'int',
        'auto_split_fee' => 'bool',
        'fee_description' => 'string',
        'qr_code_url' => 'string',
        'zalo_link' => 'string',
        'main_phone' => 'string',
        'sub_phone' => 'string',
    ];

    /**
     * Normalize settings array cho tournament.
     * - Empty strings / 0 không hợp lệ → null
     * - Loại bỏ keys không nằm trong whitelist
     * - Ép kiểu dữ liệu đúng theo FIELD_TYPES
     */
    public static function normalizeTournamentSettings(?array $settings): array
    {
        return self::normalize($settings, self::FIELD_TYPES);
    }

    /**
     * Normalize settings array cho mini tournament.
     */
    public static function normalizeMiniTournamentSettings(?array $settings): array
    {
        return self::normalize($settings, self::MINI_FIELD_TYPES);
    }

    /**
     * Normalize chung: chỉ giữ các field trong whitelist, ép kiểu dữ liệu,
     * empty string / không hợp lệ → null.
     */
    private static function normalize(?array $settings, array $types): array
    {
        if (!is_array($settings)) {
            return [];
        }

        $normalized = [];

        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, $types)) {
                continue;
            }

            $normalized[$key] = self::cast($value, $types[$key]);
        }

        return $normalized;
    }

    private static function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'string' => self::castString($value),
            'int' => self::castInt($value),
            'bool' => self::castBool($value),
            'date' => self::castDate($value),
            default => self::castNullable($value),
        };
    }

    private static function castString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            return null;
        }
        $str = trim((string) $value);
        return $str === '' ? null : $str;
    }

    private static function castInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric(trim($value))) {
            return (int) trim($value);
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return null;
    }

    private static function castBool(mixed $value): bool
    {
        return match (true) {
            is_bool($value) => $value,
            $value === null, $value === '' => false,
            is_string($value) => in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true),
            is_numeric($value) => (int) $value !== 0,
            default => (bool) $value,
        };
    }

    private static function castDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value)) {
            return null;
        }
        $str = trim((string) $value);
        return $str === '' ? null : $str;
    }

    private static function castNullable(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return $value;
    }
}