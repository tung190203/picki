<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$columns = Schema::getColumnListing('clubs');
$locationRelated = array_filter($columns, fn($c) => str_contains(strtolower($c), 'location') || str_contains(strtolower($c), 'province') || str_contains(strtolower($c), 'city'));
echo "location-related columns in clubs: " . implode(', ', $locationRelated) . "\n";
echo "all columns: " . implode(', ', $columns) . "\n";
