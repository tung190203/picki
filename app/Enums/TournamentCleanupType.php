<?php

namespace App\Enums;

/**
 * Phân loại tournament dùng cho notification dọn dẹp tự động.
 * Phân biệt "giải đấu" (Tournament) và "kèo đấu" (MiniTournament).
 */
enum TournamentCleanupType: string
{
    case Tournament = 'tournament';
    case MiniTournament = 'mini-tournament';

    public function label(): string
    {
        return match ($this) {
            self::Tournament => 'Giải đấu',
            self::MiniTournament => 'Kèo đấu',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
