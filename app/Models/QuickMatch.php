<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuickMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'note',
        'team_a',
        'team_b',
        'match_type',
        'qr_code',
        'status',
        'score',
        'winner',
        'confirmed_at',
        'created_by',
        'scheduled_at',
        'competition_location_id',
    ];

    protected $casts = [
        'team_a' => 'array',
        'team_b' => 'array',
        'score' => 'array',
        'confirmed_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COMPLETED = 'completed';

    const MATCH_TYPE_RANK = 'rank';
    const MATCH_TYPE_CASUAL = 'casual';

    const WINNER_TEAM_A = 'team_a';
    const WINNER_TEAM_B = 'team_b';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function competitionLocation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CompetitionLocation::class, 'competition_location_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MatchHistory::class);
    }

    public function teamAMembers()
    {
        $ids = $this->team_a ?? [];
        return User::whereIn('id', $ids)->get();
    }

    public function teamBMembers()
    {
        $ids = $this->team_b ?? [];
        return User::whereIn('id', $ids)->get();
    }

    public function allPlayerIds(): array
    {
        return array_merge($this->team_a ?? [], $this->team_b ?? []);
    }

    public function isPlayerInTeamA(int $userId): bool
    {
        return in_array($userId, $this->team_a ?? []);
    }

    public function isPlayerInTeamB(int $userId): bool
    {
        return in_array($userId, $this->team_b ?? []);
    }

    public function isPlayerInMatch(int $userId): bool
    {
        return $this->isPlayerInTeamA($userId) || $this->isPlayerInTeamB($userId);
    }

    public function generateQrCode(): string
    {
        return bin2hex(random_bytes(16)); // 32-char hex string
    }

    public function determineWinner(?array $score = null): ?string
    {
        $score = $score ?? ($this->score ?? []);
        $teamAScores = $score['team_a'] ?? [];
        $teamBScores = $score['team_b'] ?? [];

        if (empty($teamAScores) && empty($teamBScores)) {
            return null;
        }

        $totalA = array_sum($teamAScores);
        $totalB = array_sum($teamBScores);

        if ($totalA > $totalB) {
            return self::WINNER_TEAM_A;
        }

        if ($totalB > $totalA) {
            return self::WINNER_TEAM_B;
        }

        return null; // hòa
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
