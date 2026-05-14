<?php

namespace App\Jobs;

use App\Models\MiniTournament;
use App\Services\ImageOptimizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OptimizeMiniTournamentImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $miniTournamentId;
    public ?string $posterStoragePath;
    public ?string $qrCodeStoragePath;
    public bool $deleteOldPoster;
    public ?string $oldPosterUrl;
    public bool $deleteOldQrCode;
    public ?string $oldQrCodeUrl;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        int $miniTournamentId,
        ?string $posterStoragePath = null,
        ?string $qrCodeStoragePath = null,
        bool $deleteOldPoster = false,
        ?string $oldPosterUrl = null,
        bool $deleteOldQrCode = false,
        ?string $oldQrCodeUrl = null,
    ) {
        $this->miniTournamentId = $miniTournamentId;
        $this->posterStoragePath = $posterStoragePath;
        $this->qrCodeStoragePath = $qrCodeStoragePath;
        $this->deleteOldPoster = $deleteOldPoster;
        $this->oldPosterUrl = $oldPosterUrl;
        $this->deleteOldQrCode = $deleteOldQrCode;
        $this->oldQrCodeUrl = $oldQrCodeUrl;
    }

    public function handle(ImageOptimizationService $imageService): void
    {
        $miniTournament = MiniTournament::find($this->miniTournamentId);

        if (!$miniTournament) {
            Log::warning("OptimizeMiniTournamentImageJob: MiniTournament #{$this->miniTournamentId} not found.");
            return;
        }

        try {
            $updates = [];

            if ($this->posterStoragePath) {
                $realPath = storage_path('app/' . $this->posterStoragePath);
                if (file_exists($realPath)) {
                    $optimizedPoster = $imageService->optimizeFromPath(
                        $realPath,
                        'posters',
                        1920,
                        80
                    );
                    $updates['poster'] = asset('storage/' . $optimizedPoster);
                    @unlink($realPath);
                }
            }

            if ($this->qrCodeStoragePath) {
                $realPath = storage_path('app/' . $this->qrCodeStoragePath);
                if (file_exists($realPath)) {
                    $optimizedQr = $imageService->optimizeFromPath(
                        $realPath,
                        'qr_codes',
                        800,
                        75
                    );
                    $updates['qr_code_url'] = asset('storage/' . $optimizedQr);
                    @unlink($realPath);
                }
            }

            if ($this->deleteOldPoster && $this->oldPosterUrl) {
                $imageService->deleteOldImage($this->oldPosterUrl);
            }

            if ($this->deleteOldQrCode && $this->oldQrCodeUrl) {
                $imageService->deleteOldImage($this->oldQrCodeUrl);
            }

            if (!empty($updates)) {
                $miniTournament->update($updates);
            }

            Log::info("OptimizeMiniTournamentImageJob: MiniTournament #{$this->miniTournamentId} images optimized.", $updates);
        } catch (\Throwable $e) {
            Log::error("OptimizeMiniTournamentImageJob: Failed for miniTournament #{$this->miniTournamentId}.", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("OptimizeMiniTournamentImageJob: Job failed for miniTournament #{$this->miniTournamentId}.", [
            'error' => $exception->getMessage(),
        ]);
    }
}
