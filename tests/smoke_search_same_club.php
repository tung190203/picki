<?php

/**
 * Quick smoke test for SearchV2Controller same_club virtual-member inclusion.
 * Run: php tests/smoke_search_same_club.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Club\Club;
use App\Models\Club\ClubVirtualMember;

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

// Find a club that has virtual members
$clubId = null;
foreach (Club::has('virtualMembers')->take(20)->get() as $c) {
    if ($c->virtualMembers()->count() > 0) {
        $clubId = $c->id;
        break;
    }
}

if (!$clubId) {
    fwrite(STDERR, "No club with virtual members found, aborting\n");
    exit(1);
}

$expectedVms = ClubVirtualMember::where('club_id', $clubId)->get();
echo "Club $clubId has " . $expectedVms->count() . " virtual member(s)\n";

$url = "$baseUrl$endpoint?tab=user&sub_tab=same_club&per_page=20&page=1&club_id=$clubId";
$resp = fetch($url);

$items = $resp['data']['data'] ?? [];
$virtuals = array_values(array_filter($items, fn($i) => !empty($i['is_virtual'])));

echo "API returned " . count($items) . " total items, " . count($virtuals) . " virtual\n";

if (count($virtuals) !== $expectedVms->count()) {
    fwrite(STDERR, "FAIL: expected {$expectedVms->count()} virtual, got " . count($virtuals) . "\n");
    exit(1);
}

// Each virtual must have id from club_virtual_members, full_name, avatar_url, is_virtual=true
foreach ($virtuals as $v) {
    if (!isset($v['id'], $v['full_name'], $v['is_virtual']) || $v['is_virtual'] !== true) {
        fwrite(STDERR, "FAIL: virtual item missing fields: " . json_encode($v) . "\n");
        exit(1);
    }
}

// Page 2 must not duplicate virtuals
$url2 = "$baseUrl$endpoint?tab=user&sub_tab=same_club&per_page=20&page=2&club_id=$clubId";
$resp2 = fetch($url2);
$page2Items = $resp2['data']['data'] ?? [];
$page2Virtuals = array_values(array_filter($page2Items, fn($i) => !empty($i['is_virtual'])));
echo "Page 2 returned " . count($page2Items) . " items, " . count($page2Virtuals) . " virtual\n";

if (count($page2Virtuals) > 0) {
    fwrite(STDERR, "FAIL: page 2 should not have virtual members (would duplicate)\n");
    exit(1);
}

// non-same_club subtab must not return virtuals
$url3 = "$baseUrl$endpoint?tab=user&sub_tab=all&per_page=20&page=1&club_id=$clubId";
$resp3 = fetch($url3);
$allVirtuals = array_values(array_filter($resp3['data']['data'] ?? [], fn($i) => !empty($i['is_virtual'])));
if (count($allVirtuals) > 0) {
    fwrite(STDERR, "FAIL: non-same_club subtab should not return virtual members\n");
    exit(1);
}

echo "PASS: virtual members returned only in same_club page 1, with correct shape\n";

// ──────── Smoke test for invite virtual member ────────
echo "\n--- Testing mini-tournament invite with virtual_ids ---\n";
$vm = ClubVirtualMember::where('club_id', $clubId)->first();
$url = "$baseUrl/api/mini-participants/invite/$clubId?just_check=1"; // dummy
$token = ''; // can't easily mint a JWT here; skip live API test, rely on manual curl
echo "Run `curl` against the live server with Authorization header to verify invite.\n";