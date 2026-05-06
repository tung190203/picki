<?php

namespace App\Jobs;

use App\Models\Tournament;
use App\Services\ImageOptimizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OptimizeTournamentImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tournamentId;
    public ?string $posterStoragePath;
    public ?string $qrCodeStoragePath;
    public bool $deleteOldPoster;
    public ?string $oldPosterPath;
    public bool $deleteOldQrCode;
    public ?string $oldQrCodePath;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        int $tournamentId,
        ?string $posterStoragePath = null,
        ?string $qrCodeStoragePath = null,
        bool $deleteOldPoster = false,
        ?string $oldPosterPath = null,
        bool $deleteOldQrCode = false,
        ?string $oldQrCodePath = null,
    ) {
        $this->tournamentId = $tournamentId;
        $this->posterStoragePath = $posterStoragePath;
        $this->qrCodeStoragePath = $qrCodeStoragePath;
        $this->deleteOldPoster = $deleteOldPoster;
        $this->oldPosterPath = $oldPosterPath;
        $this->deleteOldQrCode = $deleteOldQrCode;
        $this->oldQrCodePath = $oldQrCodePath;
    }

    public function handle(ImageOptimizationService $imageService): void
    {
        $tournament = Tournament::find($this->tournamentId);

        if (!$tournament) {
            Log::warning("OptimizeTournamentImageJob: Tournament #{$this->tournamentId} not found.");
            return;
        }

        try {
            $updates = [];

            // Optimize poster
            if ($this->posterStoragePath) {
                $realPath = storage_path('app/' . $this->posterStoragePath);
                if (file_exists($realPath)) {
                    $optimizedPoster = $imageService->optimizeFromPath(
                        $realPath,
                        'tournaments/posters'
                    );
                    $updates['poster'] = $optimizedPoster;

                    // Delete raw temp file after optimization
                    @unlink($realPath);
                }
            }

            // Optimize QR code
            if ($this->qrCodeStoragePath) {
                $realPath = storage_path('app/' . $this->qrCodeStoragePath);
                if (file_exists($realPath)) {
                    $optimizedQr = $imageService->optimizeFromPath(
                        $realPath,
                        'tournaments/qr',
                        800,
                        75
                    );
                    $updates['qr_code_url'] = $optimizedQr;

                    // Delete raw temp file after optimization
                    @unlink($realPath);
                }
            }

            // Delete old images if requested
            if ($this->deleteOldPoster && $this->oldPosterPath) {
                $imageService->deleteOldImage($this->oldPosterPath);
            }

            if ($this->deleteOldQrCode && $this->oldQrCodePath) {
                $imageService->deleteOldImage($this->oldQrCodePath);
            }

            // Update tournament with optimized image paths
            if (!empty($updates)) {
                $tournament->update($updates);
            }

            Log::info("OptimizeTournamentImageJob: Tournament #{$this->tournamentId} images optimized.", $updates);
        } catch (\Throwable $e) {
            Log::error("OptimizeTournamentImageJob: Failed for tournament #{$this->tournamentId}.", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("OptimizeTournamentImageJob: Job failed for tournament #{$this->tournamentId}.", [
            'error' => $exception->getMessage(),
        ]);
    }
}
