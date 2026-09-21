<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Build suffixed email/phone values for a user that has been merged into a survivor.
 * Suffixing ensures unique-constraint-safe storage and keeps the original contact
 * human-readable enough for forensic lookups via merged_into_user_id.
 */
class MergedContactSuffix
{
    /**
     * Gmail-style "+suffix" — keeps the domain intact, safe for most providers.
     *
     * Example: an.nguyen+merged42a3f1@example.com
     */
    public static function email(?string $email, int $mergeId): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }

        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }

        [$local, $domain] = $parts;
        $local = Str::lower(preg_replace('/[^a-z0-9._%+\-]/i', '', $local));
        $local = $local === '' ? 'user' : $local;

        $uniq = substr(bin2hex(random_bytes(3)), 0, 4);

        return "{$local}+merged{$mergeId}{$uniq}@{$domain}";
    }

    /**
     * Prefix-style phone suffix — keeps the original digits and is unlikely to
     * clash with a future survivor holding the same digits.
     *
     * Example: merged42a3f1_0901234567
     */
    public static function phone(?string $phone, int $mergeId): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return $phone;
        }

        $uniq = substr(bin2hex(random_bytes(3)), 0, 4);

        return "merged{$mergeId}{$uniq}_{$digits}";
    }
}
