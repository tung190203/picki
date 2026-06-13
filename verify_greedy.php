<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$scheduler = new App\Services\RoundRobinSchedulerService();

foreach ([6, 7, 8] as $n) {
    $ids = range(1, $n);
    $result = $scheduler->generatePartnerRotationSchedule($ids, 'double');
    
    $byeCount = 0;
    $matchCount = 0;
    $pairSet = [];
    
    foreach ($result['rounds'] as $rnd) {
        foreach ($rnd['matches'] as $m) {
            $matchCount++;
            if (!empty($m['is_bye'])) { $byeCount++; continue; }
            $p1 = $m['team1_players'][0] ?? null;
            $p2 = $m['team2_players'][0] ?? null;
            if ($p1 && $p2) {
                $key = $p1 < $p2 ? "$p1-$p2" : "$p2-$p1";
                $pairSet[$key] = ($pairSet[$key] ?? 0) + 1;
            }
        }
    }
    
    $expectedPairs = ($n * ($n - 1)) / 2;
    echo "$n players: rounds=" . count($result['rounds']) . " bye=$byeCount matches=$matchCount pairs=" . count($pairSet) . " (exp $expectedPairs)\n";
    echo "  bye distribution per round:\n";
    foreach ($result['rounds'] as $ri => $rnd) {
        $rbye = 0;
        $rm = 0;
        foreach ($rnd['matches'] as $m) {
            if (!empty($m['is_bye'])) $rbye++;
            else $rm++;
        }
        echo "  Round " . ($ri+1) . ": $rm regular matches, $rbye bye\n";
    }
}
