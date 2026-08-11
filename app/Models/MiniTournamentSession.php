<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiniTournamentSession extends Model
{
    protected $table = 'mini_tournament_sessions';

    protected $fillable = [
        'mini_tournament_id',
        'tried_suggestions',
        'rotation_index',
        'last_generated_at',
    ];

    protected $casts = [
        'rotation_index' => 'integer',
        'last_generated_at' => 'datetime',
    ];

    /**
     * tried_suggestions is stored as JSON but represents a list of sorted 4-user-id arrays.
     * Each entry is a unique identifier for a 4-player combo that has already been
     * returned to the user; the regeneration flow skips any combo whose sorted
     * user_id list is already present.
     *
     * @return array<int, array<int>>
     */
    protected function triedSuggestions(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null || $value === '') {
                    return [];
                }
                $decoded = json_decode($value, true);
                if (!is_array($decoded)) {
                    return [];
                }
                $normalized = [];
                foreach ($decoded as $entry) {
                    if (is_array($entry)) {
                        $sorted = array_values(array_map('intval', $entry));
                        sort($sorted);
                        $normalized[] = $sorted;
                    }
                }
                return $normalized;
            },
            set: function ($value) {
                if ($value === null) {
                    return null;
                }
                $normalized = [];
                foreach ($value as $entry) {
                    if (is_array($entry)) {
                        $sorted = array_values(array_map('intval', $entry));
                        sort($sorted);
                        $normalized[] = $sorted;
                    }
                }
                return json_encode($normalized);
            },
        );
    }

    public function miniTournament(): BelongsTo
    {
        return $this->belongsTo(MiniTournament::class);
    }

    /**
     * Build a canonical signature (sorted user_ids) for a 4-player match.
     *
     * @param array<int> $userIds
     */
    public static function signature(array $userIds): array
    {
        $ids = array_values(array_map('intval', array_filter($userIds, fn($v) => $v !== null)));
        sort($ids);
        return $ids;
    }

    /**
     * Whether a given signature is already in the tried list.
     *
     * @param array<int> $signature
     */
    public function hasTried(array $signature): bool
    {
        foreach ($this->tried_suggestions ?? [] as $existing) {
            if ($existing === $signature) {
                return true;
            }
        }
        return false;
    }

    /**
     * Push a new signature (idempotent - duplicates are ignored).
     *
     * @param array<int> $signature
     */
    public function remember(array $signature): void
    {
        if ($this->hasTried($signature)) {
            return;
        }
        $list = $this->tried_suggestions ?? [];
        $list[] = $signature;
        $this->tried_suggestions = $list;
    }

    /**
     * Clear history (used when rotation wraps back to the first combo).
     */
    public function clearHistory(): void
    {
        $this->tried_suggestions = [];
    }
}
