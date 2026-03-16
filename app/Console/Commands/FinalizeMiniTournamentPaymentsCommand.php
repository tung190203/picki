<?php

namespace App\Console\Commands;

use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinalizeMiniTournamentPaymentsCommand extends Command
{
    protected $signature = 'mini-tournament:finalize-payments';
    protected $description = 'Finalize payments for mini tournaments with auto_split_fee when end_time is reached';

    public function handle()
    {
        $now = Carbon::now();

        // Tìm tất cả kèo có auto_split_fee = true, end_time <= now, và chưa finalize
        $tournaments = MiniTournament::where('auto_split_fee', true)
            ->where('has_fee', true)
            ->whereNotNull('end_time')
            ->where('end_time', '<=', $now)
            ->whereNull('final_fee_per_person') // Chưa finalize
            ->get();

        $this->info("Found {$tournaments->count()} tournaments to finalize");

        foreach ($tournaments as $tournament) {
            try {
                $this->finalizeTournamentPayments($tournament);
                $this->info("✓ Finalized payments for tournament: {$tournament->id} - {$tournament->name}");
            } catch (\Exception $e) {
                $this->error("✗ Error finalizing tournament {$tournament->id}: {$e->getMessage()}");
            }
        }

        return 0;
    }

    private function finalizeTournamentPayments(MiniTournament $tournament)
    {
        DB::beginTransaction();
        try {
            // Đếm tất cả thành viên tham gia (bao gồm chủ kèo nếu là participant)
            $participantCount = $tournament->participants()->count();

            if ($participantCount === 0) {
                DB::rollBack();
                $this->warn("Tournament {$tournament->id} has no participants, skipping");
                return;
            }

            // Tính tiền chia
            $feePerPerson = round($tournament->fee_amount / $participantCount);

            // Lấy tất cả thành viên
            $participants = $tournament->participants()->get();

            // Tạo payment cho tất cả thành viên (nếu chưa có)
            foreach ($participants as $participant) {
                $existingPayment = MiniParticipantPayment::where('mini_tournament_id', $tournament->id)
                    ->where('participant_id', $participant->id)
                    ->first();

                if (!$existingPayment) {
                    MiniParticipantPayment::create([
                        'mini_tournament_id' => $tournament->id,
                        'participant_id' => $participant->id,
                        'user_id' => $participant->user_id,
                        'amount' => $feePerPerson,
                        'status' => MiniParticipantPayment::STATUS_PENDING,
                    ]);
                }
            }

            // Tạo payment cho chủ kèo (tự động xác nhận)
            $organizer = $tournament->staff()
                ->where('role', 1) // ROLE_ORGANIZER = 1
                ->first();

            if ($organizer) {
                $organizerParticipant = $tournament->participants()
                    ->where('user_id', $organizer->user_id)
                    ->first();

                if ($organizerParticipant) {
                    // Chủ kèo cũng là thành viên, payment đã được tạo ở trên
                    // Chỉ cần xác nhận payment của chủ kèo
                    $organizerPayment = MiniParticipantPayment::where('mini_tournament_id', $tournament->id)
                        ->where('participant_id', $organizerParticipant->id)
                        ->first();

                    if ($organizerPayment && $organizerPayment->status !== MiniParticipantPayment::STATUS_CONFIRMED) {
                        $organizerPayment->update([
                            'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                            'paid_at' => now(),
                            'confirmed_at' => now(),
                            'confirmed_by' => $organizer->user_id,
                        ]);
                    }
                } else {
                    // Chủ kèo không phải là thành viên, tạo payment riêng cho chủ kèo
                    $organizerPayment = MiniParticipantPayment::where('mini_tournament_id', $tournament->id)
                        ->where('user_id', $organizer->user_id)
                        ->first();

                    if (!$organizerPayment) {
                        MiniParticipantPayment::create([
                            'mini_tournament_id' => $tournament->id,
                            'participant_id' => null,
                            'user_id' => $organizer->user_id,
                            'amount' => $feePerPerson,
                            'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                            'paid_at' => now(),
                            'confirmed_at' => now(),
                            'confirmed_by' => $organizer->user_id,
                        ]);
                    }
                }
            }

            // Lưu final_fee_per_person để lock giá
            $tournament->update(['final_fee_per_person' => $feePerPerson]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
