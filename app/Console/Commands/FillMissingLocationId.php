<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FillMissingLocationId extends Command
{
    protected $signature = 'competition-locations:fill-missing-location-id {--dry-run : Show what would be changed without making changes}';
    protected $description = 'Fill missing location_id for competition_locations based on address matching with locations table';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        $this->info($dryRun ? '🔍 DRY RUN - No changes will be made' : '⚠️  LIVE RUN - Changes will be applied');
        $this->newLine();

        // Get all competition_locations without location_id
        $missingLocation = DB::table('competition_locations')
            ->whereNull('location_id')
            ->orWhere('location_id', 0)
            ->orWhere('location_id', '')
            ->get(['id', 'name', 'address']);

        $this->info("Found {$missingLocation->count()} competition_locations without location_id.");
        
        if ($missingLocation->isEmpty()) {
            $this->info('✅ All competition_locations have location_id set.');
            return Command::SUCCESS;
        }

        // Get all locations for matching
        $locations = DB::table('locations')->get(['id', 'name', 'slug']);
        $locationMap = [];
        foreach ($locations as $loc) {
            // Map both full name and slug for matching
            $locationMap[mb_strtolower($loc->name)] = $loc->id;
            $locationMap[$loc->slug] = $loc->id;
            
            // Also add variations
            $locationMap[trim($loc->name)] = $loc->id;
            // Remove "Tỉnh" prefix
            $locationMap[preg_replace('/^Tỉnh\s+/', '', $loc->name)] = $loc->id;
            // Remove "Thành phố" prefix  
            $locationMap[preg_replace('/^Thành phố\s+/', '', $loc->name)] = $loc->id;
        }

        $this->newLine();
        $this->info('Matching addresses to locations...');
        $this->newLine();

        $updated = 0;
        $notMatched = [];

        foreach ($missingLocation as $court) {
            $address = $court->address ?? '';
            $matched = false;

            // Try to find location in address
            foreach ($locationMap as $locationName => $locationId) {
                if (stripos($address, $locationName) !== false) {
                    $this->line("  Court ID {$court->id} ({$court->name}): '{$address}' → Location ID {$locationId}");
                    
                    if (!$dryRun) {
                        DB::table('competition_locations')
                            ->where('id', $court->id)
                            ->update(['location_id' => $locationId]);
                    }
                    
                    $updated++;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $notMatched[] = $court;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("✅ Dry run completed. {$updated} competition_locations would be updated.");
        } else {
            $this->info("✅ Updated {$updated} competition_locations with location_id.");
        }

        if (count($notMatched) > 0) {
            $this->newLine();
            $this->warn("⚠️  " . count($notMatched) . " competition_locations could not be matched:");
            foreach ($notMatched as $court) {
                $this->line("  - ID {$court->id}: {$court->name} ({$court->address})");
            }
        }

        return Command::SUCCESS;
    }
}
