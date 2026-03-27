<?php

namespace App\Console\Commands;

use App\Models\MiniParticipant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupInactiveGuests extends Command
{
    protected $signature = 'guests:cleanup-inactive {--days=7 : So ngay khong hoat dong de xoa}';

    protected $description = 'Xoa cac tai khoan guest khong hoat dong sau N ngay';

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Tìm guest khong hoat dong truoc ngay: {$cutoffDate->toDateTimeString()}");

        // Lay danh sach guest can xoa
        $inactiveGuests = User::inactiveGuests($days)->get();

        if ($inactiveGuests->isEmpty()) {
            $this->info("Khong co guest khong hoat dong nao can xoa.");
            return 0;
        }

        $this->info("Tim thay {$inactiveGuests->count()} guest can xoa.");

        // Hien thi danh sach truoc khi xoa
        $this->table(
            ['ID', 'Ten', 'Phone', 'Last Active', 'Da tao'],
            $inactiveGuests->map(fn($u) => [
                $u->id,
                $u->full_name,
                $u->phone ?? '-',
                $u->last_active_at?->toDateTimeString() ?? 'Chua tung active',
                $u->created_at->toDateTimeString(),
            ])
        );

        if (!$this->confirm("Ban co chac muon xoa {$inactiveGuests->count()} guest?", true)) {
            $this->info('Da huy thao tac.');
            return 0;
        }

        $deletedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($inactiveGuests as $guest) {
            try {
                DB::transaction(function () use ($guest) {
                    $guestId = $guest->id;

                    // Xoa cac participant lien quan (chi xoa neu la guest, giu participant neu la user that)
                    // Vi guest luon co is_guest = true trong participant, nen chi xoa participant cua guest
                    $participantCount = MiniParticipant::where('user_id', $guestId)->where('is_guest', true)->count();

                    MiniParticipant::where('user_id', $guestId)
                        ->where('is_guest', true)
                        ->delete();

                    // Force delete user (xoa vinh vien)
                    $guest->forceDelete();

                    $this->line("Da xoa guest ID={$guestId} ({$participantCount} participant)");
                });

                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'guest_id' => $guest->id,
                    'name' => $guest->full_name,
                    'error' => $e->getMessage(),
                ];
                $skippedCount++;
            }
        }

        $this->newLine();
        $this->info("Da xoa: {$deletedCount} guest");
        if ($skippedCount > 0) {
            $this->warn("Bi loi/bo qua: {$skippedCount} guest");
        }

        if (!empty($errors)) {
            $this->error("Chi tiet loi:");
            $this->table(['Guest ID', 'Ten', 'Loi'], array_map(fn($e) => [
                $e['guest_id'], $e['name'], $e['error']
            ], $errors));
        }

        Log::channel('daily')->info("Cleanup inactive guests: da xoa {$deletedCount}/{$inactiveGuests->count()}, loi {$skippedCount}");

        return 0;
    }
}
