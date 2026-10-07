<?php

/**
 * Quick smoke test for SearchV2Controller same_club CLB-guest inclusion.
 * Run: php tests/smoke_search_same_club.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Club\Club;
use App\Models\Club\ClubGuestProfile;

$baseUrl = 'http://localhost:8000';
$endpoint = '/api/search';

function fetch(string $url): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body, true) ?? [];
}

// Find a club that has CLB guest profiles
$clubId = null;
foreach (Club::has('guestProfiles')->take(20)->get() as $c) {
    if ($c->guestProfiles()->count() > 0) {
        $clubId = $c->id;
        break;
    }
}

if (!$clubId) {
    fwrite(STDERR, "No club with guest profiles found, aborting\n");
    exit(1);
}

$expectedGuests = ClubGuestProfile::where('club_id', $clubId)->get();
echo "Club $clubId has " . $expectedGuests->count() . " CLB guest profile(s)\n";

$url = "$baseUrl$endpoint?tab=user&sub_tab=same_club&per_page=20&page=1&club_id=$clubId";
$resp = fetch($url);

$items = $resp['data']['data'] ?? [];
$guests = array_values(array_filter($items, fn($i) => !empty($i['is_guest'])));

echo "API returned " . count($items) . " total items, " . count($guests) . " CLB guest(s)\n";

if (count($guests) !== $expectedGuests->count()) {
    fwrite(STDERR, "FAIL: expected {$expectedGuests->count()} guests, got " . count($guests) . "\n");
    exit(1);
}

// Each guest must have id, full_name, is_guest=true, club_guest_profile_id
foreach ($guests as $g) {
    if (!isset($g['id'], $g['full_name'], $g['is_guest'], $g['club_guest_profile_id']) || $g['is_guest'] !== true) {
        fwrite(STDERR, "FAIL: guest item missing fields: " . json_encode($g) . "\n");
        exit(1);
    }
}

// Page 2 must not duplicate guests
$url2 = "$baseUrl$endpoint?tab=user&sub_tab=same_club&per_page=20&page=2&club_id=$clubId";
$resp2 = fetch($url2);
$page2Items = $resp2['data']['data'] ?? [];
$page2Guests = array_values(array_filter($page2Items, fn($i) => !empty($i['is_guest'])));
echo "Page 2 returned " . count($page2Items) . " items, " . count($page2Guests) . " guest(s)\n";

if (count($page2Guests) > 0) {
    fwrite(STDERR, "FAIL: page 2 should not have CLB guests (would duplicate)\n");
    exit(1);
}

// non-same_club subtab must not return guests
$url3 = "$baseUrl$endpoint?tab=user&sub_tab=all&per_page=20&page=1&club_id=$clubId";
$resp3 = fetch($url3);
$allGuests = array_values(array_filter($resp3['data']['data'] ?? [], fn($i) => !empty($i['is_guest'])));
if (count($allGuests) > 0) {
    fwrite(STDERR, "FAIL: non-same_club subtab should not return CLB guests\n");
    exit(1);
}

echo "PASS: CLB guests returned only in same_club page 1, with correct shape\n";

// ──────── Smoke test for invite CLB guest member ────────
echo "\n--- Testing mini-tournament invite with club_id ---\n";
$profile = ClubGuestProfile::where('club_id', $clubId)->first();
echo "Run `curl` against the live server with Authorization header to verify invite with club_id={$clubId}, user_id={$profile?->user_id}.\n";
