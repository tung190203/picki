<?php

namespace App\Enums;

enum ScoreType: string
{
    case SPCN = 'SPCN';
    case DUPR = 'DUPR';

    public function label(): string
    {
        return match($this) {
            self::SPCN => 'Điểm SPCN',
            self::DUPR => 'Điểm DUPR',
        };
    }

    public function toDbScoreType(): string
    {
        return match($this) {
            self::SPCN => 'spcn_score',
            self::DUPR => 'dupr_score',
        };
    }
}
