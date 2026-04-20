<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tournamentId = 106;

echo "=== Testing LeaderboardController for tournament {$tournamentId} ===\n\n";

// Import controller
$controller = new App\Http\Controllers\LeaderboardController();

// Create a fake request
$request = Illuminate\Http\Request::create("/api/tournaments/{$tournamentId}/leaderboard", 'GET');

// Inject tournamentId via route parameters
$request->setRouteResolver(function () use ($tournamentId) {
    $route = new Illuminate\Routing\Route('GET', '/api/tournaments/{tournamentId}/leaderboard', []);
    $route->bind($request);
    $route->setParameter('tournamentId', $tournamentId);
    return $route;
});

try {
    $response = $controller->index($request, $tournamentId);
    $data = json_decode($response->getContent(), true);

    if (empty($data['data']['leaderboard'])) {
        echo "ERROR: leaderboard is empty!\n";
    } else {
        echo "SUCCESS: " . count($data['data']['leaderboard']) . " teams\n\n";
        echo "=== Top 5 ===\n";
        foreach (array_slice($data['data']['leaderboard'], 0, 5) as $i => $team) {
            echo ($i + 1) . ". Team {$team['id']}: rank={$team['rank']} | matches={$team['total_matches']} | win_rate={$team['win_rate']}% | vndupr={$team['vndupr_avg']}\n";
        }
        echo "\n=== Expected (from DB) ===\n";
        echo "1. Team 630: rank=1\n";
        echo "2. Team 535: rank=2\n";
        echo "3. Team 634: rank=3\n";
        echo "4. Team 652: rank=4\n";
        echo "5. Team 656: rank=5\n";
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
