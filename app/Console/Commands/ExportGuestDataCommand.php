<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentParticipantPayment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportGuestDataCommand extends Command
{
    protected $signature = 'guest:export 
                            {--tournament_ids= : Comma-separated tournament IDs (default: 1270,1272,1273,1276,1277)}
                            {--output= : Output file path (default: guests_backup.json in project root)}';

    protected $description = 'Export guest data from specified tournaments to JSON file';

    public function handle(): int
    {
        $tournamentIds = $this->option('tournament_ids')
            ? explode(',', $this->option('tournament_ids'))
            : [1270, 1272, 1273, 1276, 1277];

        $outputPath = $this->option('output') 
            ? base_path($this->option('output'))
            : base_path('guests_backup.json');

        $this->info('Exporting guest data...');
        $this->info('Tournament IDs: ' . implode(', ', $tournamentIds));

        // 1. Get all guest users (is_guest = true)
        $guestUsers = User::where('is_guest', true)->get()->toArray();
        $this->info('Found ' . count($guestUsers) . ' guest users');
        $guestUserIds = collect($guestUsers)->pluck('id')->toArray();

        // 2. Get all participants that are guests OR belong to guest users
        $participantsQuery = Participant::with(['guarantor'])
            ->where(function ($query) use ($tournamentIds, $guestUserIds) {
                $query->whereIn('tournament_id', $tournamentIds)
                    ->where('is_guest', true);
            });

        // Also include participants from guest users in any tournament
        if (!empty($guestUserIds)) {
            $participantsQuery->orWhereIn('user_id', $guestUserIds);
        }

        $participants = $participantsQuery->get()->toArray();
        $this->info('Found ' . count($participants) . ' guest participants');
        $participantIds = collect($participants)->pluck('id')->toArray();

        // 3. Get all teams from these tournaments
        $teams = Team::whereIn('tournament_id', $tournamentIds)->get()->toArray();
        $teamIds = collect($teams)->pluck('id')->toArray();

        // 4. Get all team members from these teams
        $teamMembersQuery = TeamMember::query();
        if (!empty($teamIds)) {
            $teamMembersQuery->whereIn('team_id', $teamIds);
        }
        if (!empty($guestUserIds)) {
            $teamMembersQuery->orWhereIn('user_id', $guestUserIds);
        }
        if (!empty($participantIds)) {
            $teamMembersQuery->orWhereIn('participant_id', $participantIds);
        }
        $teamMembers = $teamMembersQuery->distinct()->get()->toArray();
        $this->info('Found ' . count($teamMembers) . ' team members');

        // 5. Get all guest user IDs from team members
        $teamMemberUserIds = collect($teamMembers)->pluck('user_id')->filter()->unique()->toArray();
        $allGuestUserIds = array_unique(array_merge($guestUserIds, $teamMemberUserIds));

        // 6. Get additional guest users from team members (if not already fetched)
        $additionalGuestUsers = [];
        if (!empty($teamMemberUserIds)) {
            $additionalGuestUsers = User::whereIn('id', $teamMemberUserIds)
                ->where('is_guest', true)
                ->get()
                ->toArray();
        }
        $allGuestUsers = collect($guestUsers)->merge($additionalGuestUsers)->unique('id')->values()->toArray();
        $this->info('Total guest users (including from teams): ' . count($allGuestUsers));

        // 7. Get payments for all participants
        $payments = [];
        if (!empty($participantIds)) {
            $payments = TournamentParticipantPayment::whereIn('participant_id', $participantIds)
                ->get()
                ->toArray();
            $this->info('Found ' . count($payments) . ' guest payments');
        }

        // Also get payments by user_id from guest users
        if (!empty($allGuestUserIds)) {
            $additionalPayments = TournamentParticipantPayment::whereIn('user_id', $allGuestUserIds)
                ->get()
                ->toArray();
            $payments = collect($payments)->merge($additionalPayments)->unique('id')->values()->toArray();
            $this->info('Total payments (including by user): ' . count($payments));
        }

        $data = [
            'exported_at' => now()->toDateTimeString(),
            'tournament_ids' => $tournamentIds,
            'participants' => $participants,
            'payments' => $payments,
            'team_members' => $teamMembers,
            'users' => $allGuestUsers,
            'teams' => $teams,
        ];

        // Ensure directory exists
        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Write JSON file
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($outputPath, $json);

        $this->info('Export completed successfully!');
        $this->info('Output file: ' . $outputPath);

        $summary = [
            'users' => count($allGuestUsers),
            'participants' => count($participants),
            'payments' => count($payments),
            'team_members' => count($teamMembers),
            'teams' => count($teams),
        ];

        $this->table(
            ['Table', 'Count'],
            collect($summary)->map(fn($count, $table) => [$table, $count])->values()->toArray()
        );

        return Command::SUCCESS;
    }
}
