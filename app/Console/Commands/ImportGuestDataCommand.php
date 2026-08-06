<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentParticipantPayment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportGuestDataCommand extends Command
{
    protected $signature = 'guest:import 
                            {--file= : JSON file path to import from (default: guests_backup.json in project root)}
                            {--dry-run : Preview data without inserting}
                            {--skip-users : Skip importing users table}
                            {--skip-participants : Skip importing participants table}
                            {--skip-teams : Skip importing teams table}
                            {--skip-payments : Skip importing payments table}
                            {--skip-team-members : Skip importing team_members table}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Import guest data from JSON file to restore deleted guests';

    public function handle(): int
    {
        $filePath = $this->option('file')
            ? base_path($this->option('file'))
            : base_path('guests_backup.json');

        if (!file_exists($filePath)) {
            $this->error('File not found: ' . $filePath);
            return Command::FAILURE;
        }

        $json = file_get_contents($filePath);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON file: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        $this->info('Importing guest data from: ' . $filePath);
        $this->info('Exported at: ' . ($data['exported_at'] ?? 'unknown'));
        $this->info('Tournament IDs: ' . implode(', ', $data['tournament_ids'] ?? []));

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        // Display summary
        $this->newLine();
        $this->info('Data summary:');
        $this->table(
            ['Table', 'Records'],
            [
                ['users', count($data['users'] ?? [])],
                ['teams', count($data['teams'] ?? [])],
                ['participants', count($data['participants'] ?? [])],
                ['payments', count($data['payments'] ?? [])],
                ['team_members', count($data['team_members'] ?? [])],
            ]
        );

        // IMPORTANT: Import in correct order to maintain foreign key constraints
        // 1. Users first (no FK dependencies)
        // 2. Teams (no FK dependencies on our tables)
        // 3. Participants (depends on users)
        // 4. Payments (depends on participants)
        // 5. Team Members (depends on teams, users, participants)

        $summary = [];

        // 1. Import users
        if (!empty($data['users']) && !$this->option('skip-users')) {
            $summary['users'] = $this->importUsers($data['users'], $isDryRun);
        }

        // 2. Import teams
        if (!empty($data['teams']) && !$this->option('skip-teams')) {
            $summary['teams'] = $this->importTeams($data['teams'], $isDryRun);
        }

        // 3. Import participants
        if (!empty($data['participants']) && !$this->option('skip-participants')) {
            $summary['participants'] = $this->importParticipants($data['participants'], $isDryRun);
        }

        // 4. Import payments
        if (!empty($data['payments']) && !$this->option('skip-payments')) {
            $summary['payments'] = $this->importPayments($data['payments'], $isDryRun);
        }

        // 5. Import team members
        if (!empty($data['team_members']) && !$this->option('skip-team-members')) {
            $summary['team_members'] = $this->importTeamMembers($data['team_members'], $isDryRun);
        }

        // Display final summary
        $this->newLine();
        $this->info('Import Summary:');
        $summaryRows = [];
        foreach ($summary as $table => $result) {
            $status = $result['created'] . ' created, ' . $result['updated'] . ' updated, ' . $result['skipped'] . ' skipped';
            if ($isDryRun) {
                $status = '[DRY RUN] Would create ' . $result['created'] . ', update ' . $result['updated'] . ', skip ' . $result['skipped'];
            }
            if (isset($result['conflicts']) && $result['conflicts'] > 0) {
                $status .= ', ' . $result['conflicts'] . ' conflicts';
            }
            $summaryRows[] = [$table, $status];
        }

        if (empty($summaryRows)) {
            $this->warn('No tables were imported. Use --skip-* options or check your JSON file.');
        } else {
            $this->table(['Table', 'Status'], $summaryRows);
        }

        if (!$isDryRun) {
            $this->newLine();
            $this->info('========== VERIFICATION ==========');

            // Verify guest users restored
            $guestCount = User::where('is_guest', true)->count();
            $this->info("Guest users in DB: $guestCount");

            // Verify participants per tournament
            $tournamentIds = $data['tournament_ids'] ?? [];
            foreach ($tournamentIds as $tid) {
                $pCount = Participant::where('tournament_id', $tid)->where('is_guest', true)->count();
                $this->info("Tournament #$tid: $pCount guest participants");
            }

            // Verify payments
            $paymentCount = TournamentParticipantPayment::whereIn('tournament_id', $tournamentIds)->count();
            $this->info("Payments in these tournaments: $paymentCount");

            $this->info('==================================');
            $this->info('Import completed successfully!');
        }

        return Command::SUCCESS;
    }

    private function importUsers(array $users, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $conflicts = 0;

        if ($dryRun) {
            $this->info('  [DRY RUN] Would import ' . count($users) . ' users');
            return ['created' => count($users), 'updated' => 0, 'skipped' => 0, 'conflicts' => 0];
        }

        // Get fillable fields from model
        $fillable = (new User())->getFillable();
        $fillable[] = 'id'; // ID is fillable for import

        foreach ($users as $userData) {
            $id = $userData['id'] ?? null;
            if (!$id) {
                $skipped++;
                continue;
            }

            $filteredData = array_intersect_key($userData, array_flip($fillable));

            try {
                $existing = User::find($id);
                if ($existing) {
                    // Check if it's the same user by phone or email
                    $isSameUser = ($existing->phone === ($filteredData['phone'] ?? null))
                        || ($existing->email === ($filteredData['email'] ?? null));

                    if ($isSameUser) {
                        $this->info("    [SKIP] User ID $id already exists (same identity: " . ($existing->phone ?: $existing->email) . ")");
                        $skipped++;
                    } else {
                        $this->warn("    [CONFLICT] User ID $id exists with different data (existing: " . ($existing->phone ?: $existing->email) . ", incoming: " . ($filteredData['phone'] ?? $filteredData['email'] ?? 'unknown') . ")");
                        $conflicts++;
                    }
                } else {
                    // User doesn't exist - create with original ID using DB insert
                    DB::table('users')->insertOrIgnore($filteredData);
                    $this->info("    [CREATE] User ID $id: " . ($filteredData['phone'] ?: $filteredData['email'] ?? 'unknown'));
                    $created++;
                }
            } catch (\Exception $e) {
                $this->error("  [ERROR] Failed to import user ID $id: " . $e->getMessage());
                Log::error("Guest import failed for user $id", ['error' => $e->getMessage()]);
                $skipped++;
            }
        }

        $this->info("  Users: $created created, $updated updated, $skipped skipped, $conflicts conflicts");
        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'conflicts' => $conflicts];
    }

    private function importTeams(array $teams, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        if ($dryRun) {
            $this->info('  [DRY RUN] Would import ' . count($teams) . ' teams');
            return ['created' => count($teams), 'updated' => 0, 'skipped' => 0];
        }

        $fillable = (new \App\Models\Team())->getFillable();
        $fillable[] = 'id';

        foreach ($teams as $teamData) {
            $id = $teamData['id'] ?? null;
            if (!$id) {
                $skipped++;
                continue;
            }

            $filteredData = array_intersect_key($teamData, array_flip($fillable));

            try {
                $existing = \App\Models\Team::find($id);
                if ($existing) {
                    $existing->update($filteredData);
                    $updated++;
                } else {
                    // Create with original ID using DB insert
                    DB::table('teams')->insertOrIgnore($filteredData);
                    $created++;
                }
            } catch (\Exception $e) {
                $this->error("  [ERROR] Failed to import team ID $id: " . $e->getMessage());
                Log::error("Guest import failed for team $id", ['error' => $e->getMessage()]);
                $skipped++;
            }
        }

        $this->info("  Teams: $created created, $updated updated, $skipped skipped");
        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    private function importParticipants(array $participants, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        if ($dryRun) {
            $this->info('  [DRY RUN] Would import ' . count($participants) . ' participants');
            return ['created' => count($participants), 'updated' => 0, 'skipped' => 0];
        }

        $fillable = (new Participant())->getFillable();
        $fillable[] = 'id';

        foreach ($participants as $participantData) {
            $id = $participantData['id'] ?? null;
            if (!$id) {
                $skipped++;
                continue;
            }

            $filteredData = array_intersect_key($participantData, array_flip($fillable));

            // user_id is nullable - set to null if not found
            $userId = $filteredData['user_id'] ?? null;
            if ($userId && !User::find($userId)) {
                $this->warn("    User ID $userId not found, setting to null for participant ID $id");
                $filteredData['user_id'] = null;
            }

            try {
                $existing = Participant::find($id);
                if ($existing) {
                    $existing->update($filteredData);
                    $updated++;
                } else {
                    // Create with original ID using DB insert
                    DB::table('participants')->insertOrIgnore($filteredData);
                    $created++;
                }
            } catch (\Exception $e) {
                $this->error("  [ERROR] Failed to import participant ID $id: " . $e->getMessage());
                Log::error("Guest import failed for participant $id", ['error' => $e->getMessage()]);
                $skipped++;
            }
        }

        $this->info("  Participants: $created created, $updated updated, $skipped skipped");
        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    private function importPayments(array $payments, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        if ($dryRun) {
            $this->info('  [DRY RUN] Would import ' . count($payments) . ' payments');
            return ['created' => count($payments), 'updated' => 0, 'skipped' => 0];
        }

        $fillable = (new TournamentParticipantPayment())->getFillable();
        $fillable[] = 'id';

        foreach ($payments as $paymentData) {
            $id = $paymentData['id'] ?? null;
            if (!$id) {
                $skipped++;
                continue;
            }

            $filteredData = array_intersect_key($paymentData, array_flip($fillable));

            // CRITICAL: user_id is NOT NULL - must skip if user not found
            $userId = $filteredData['user_id'] ?? null;
            if ($userId && !User::find($userId)) {
                $this->warn("    [SKIP] Payment ID $id: user_id $userId not found in system");
                $skipped++;
                continue;
            }

            // participant_id is nullable - set to null if not found
            $participantId = $filteredData['participant_id'] ?? null;
            if ($participantId && !Participant::find($participantId)) {
                $this->warn("    Participant ID $participantId not found, setting to null for payment ID $id");
                $filteredData['participant_id'] = null;
            }

            try {
                $existing = TournamentParticipantPayment::find($id);
                if ($existing) {
                    $existing->update($filteredData);
                    $updated++;
                } else {
                    TournamentParticipantPayment::create($filteredData);
                    $created++;
                }
            } catch (\Exception $e) {
                $this->error("  [ERROR] Failed to import payment ID $id: " . $e->getMessage());
                Log::error("Guest import failed for payment $id", ['error' => $e->getMessage()]);
                $skipped++;
            }
        }

        $this->info("  Payments: $created created, $updated updated, $skipped skipped");
        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    private function importTeamMembers(array $teamMembers, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        if ($dryRun) {
            $this->info('  [DRY RUN] Would import ' . count($teamMembers) . ' team members');
            return ['created' => count($teamMembers), 'updated' => 0, 'skipped' => 0];
        }

        $fillable = (new TeamMember())->getFillable();
        $fillable[] = 'id';

        foreach ($teamMembers as $memberData) {
            $id = $memberData['id'] ?? null;
            if (!$id) {
                $skipped++;
                continue;
            }

            $filteredData = array_intersect_key($memberData, array_flip($fillable));

            // CRITICAL: user_id is NOT NULL - must skip if user not found
            $userId = $filteredData['user_id'] ?? null;
            if ($userId && !User::find($userId)) {
                $this->warn("    [SKIP] Team member ID $id: user_id $userId not found in system");
                $skipped++;
                continue;
            }

            // Check if team_id exists, if not skip
            $teamId = $filteredData['team_id'] ?? null;
            if ($teamId && !\App\Models\Team::find($teamId)) {
                $this->warn("    [SKIP] Team ID $teamId not found, skipping team member ID $id");
                $skipped++;
                continue;
            }

            // participant_id is nullable - set to null if not found
            $participantId = $filteredData['participant_id'] ?? null;
            if ($participantId && !Participant::find($participantId)) {
                $this->warn("    Participant ID $participantId not found, setting to null for team member ID $id");
                $filteredData['participant_id'] = null;
            }

            try {
                $existing = TeamMember::find($id);
                if ($existing) {
                    $existing->update($filteredData);
                    $updated++;
                } else {
                    TeamMember::create($filteredData);
                    $created++;
                }
            } catch (\Exception $e) {
                $this->error("  [ERROR] Failed to import team member ID $id: " . $e->getMessage());
                Log::error("Guest import failed for team member $id", ['error' => $e->getMessage()]);
                $skipped++;
            }
        }

        $this->info("  Team Members: $created created, $updated updated, $skipped skipped");
        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }
}
