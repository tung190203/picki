<?php

namespace App\Enums;

enum MatchFormat: string
{
    case Standard = 'standard';
    case PartnerRotation = 'partner_rotation';
    case MixedGender = 'mixed_gender';
    case RankPairing = 'rank_pairing';

    public function label(): string
    {
        return match($this) {
            self::Standard => 'Tiêu chuẩn',
            self::PartnerRotation => 'Xoay vòng partner',
            self::MixedGender => 'Mix nam nữ',
            self::RankPairing => 'Ghép hạng A/B',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::Standard => '1 trận duy nhất',
            self::PartnerRotation => 'Mỗi người ghép cặp với tất cả người khác đúng 1 lần',
            self::MixedGender => 'Mỗi nam ghép cặp với mỗi nữ, BXH cá nhân',
            self::RankPairing => 'Mỗi A ghép cặp với mỗi B, BXH riêng theo hạng',
        };
    }
}
