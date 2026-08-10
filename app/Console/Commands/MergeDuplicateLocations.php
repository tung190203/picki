<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateLocations extends Command
{
    protected $signature = 'locations:merge-duplicates {--dry-run : Show what would be merged without making changes}';
    protected $description = 'Merge duplicate locations in the database';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        // Define duplicate mappings: source_id => target_id
        // Key = location name without prefix, Value = correct location_id
        $duplicates = [
             'ha-noi' => 1,      
             'ho-chi-minh' => 28,
        ];

        if (empty($duplicates)) {
            $this->info('No duplicate mappings defined. Edit the command file to add mappings.');
            return Command::SUCCESS;
        }

        $this->info($dryRun ? '🔍 DRY RUN - No changes will be made' : '⚠️  LIVE RUN - Changes will be applied');
        $this->newLine();

        $tables = [
            'competition_locations' => 'location_id',
            'users' => 'location_id',
            'clubs' => 'location_id',
        ];

        foreach ($duplicates as $sourceSlug => $targetId) {
            $source = DB::table('locations')->where('slug', $sourceSlug)->first();
            
            if (!$source) {
                $this->warn("Location with slug '{$sourceSlug}' not found, skipping.");
                continue;
            }

            $target = DB::table('locations')->where('id', $targetId)->first();
            
            if (!$target) {
                $this->error("Target location ID {$targetId} not found, skipping.");
                continue;
            }

            $this->section("Merging: {$source->name} (ID {$source->id}) → {$target->name} (ID {$targetId})");

            foreach ($tables as $table => $column) {
                $count = DB::table($table)->where($column, $source->id)->count();
                if ($count > 0) {
                    $this->line("  - {$table}: {$count} records");
                    
                    if (!$dryRun) {
                        DB::table($table)->where($column, $source->id)->update([$column => $targetId]);
                    }
                }
            }

            // Delete source location
            if (!$dryRun) {
                DB::table('locations')->where('id', $source->id)->delete();
            }
            $this->line("  - locations: deleted source (ID {$source->id})");
            $this->newLine();
        }

        if (!$dryRun) {
            $this->info('✅ Merge completed successfully!');
        } else {
            $this->info('✅ Dry run completed. Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }
}
