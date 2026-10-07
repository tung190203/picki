<?php

namespace App\Services\Club;

class ClubHomeCourtEnricher
{
    /**
     * Tính khoảng cách haversine (km) giữa 2 toạ độ.
     * Trả null nếu thiếu toạ độ ở bất kỳ đầu nào.
     */
    public static function distanceKm(mixed $anchorLat, mixed $anchorLng, mixed $courtLat, mixed $courtLng): ?float
    {
        $aLat = self::toFloat($anchorLat);
        $aLng = self::toFloat($anchorLng);
        $cLat = self::toFloat($courtLat);
        $cLng = self::toFloat($courtLng);

        if ($aLat === null || $aLng === null || $cLat === null || $cLng === null) {
            return null;
        }

        $earthRadius = 6371.0; // km
        $lat1 = deg2rad($aLat);
        $lat2 = deg2rad($cLat);
        $dLat = deg2rad($cLat - $aLat);
        $dLng = deg2rad($cLng - $aLng);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        $c = 2 * asin(min(1.0, sqrt($h)));

        return round($earthRadius * $c, 2);
    }

    private static function toFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') return null;
        if (!is_numeric($v)) return null;
        return (float) $v;
    }
}
