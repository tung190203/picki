<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * MiniTournamentStaff — vai trò trong 1 kèo đấu (MiniTournament).
 *
 * Mapping (chuẩn hoá từ 2026-08-27):
 *   ROLE_ADMIN    = 1  → Admin / organizer (alias ROLE_ORGANIZER)
 *   ROLE_STAFF    = 2  → BTC (mới)
 *   ROLE_REFEREE  = 3  → Trọng tài (trước đây là 2, đã migrate)
 *
 * - 1 kèo có thể có nhiều Admin (không giới hạn).
 * - 1 user chỉ giữ tối đa 1 role / kèo (validation ở controller).
 * - BTC có thể được gán Trọng tài; Admin có thể gán mọi role.
 */
class MiniTournamentStaff extends Model
{
    use HasFactory;

    protected $fillable = [
        'mini_tournament_id',
        'user_id',
        'role',
        'checked_in_at',
        'is_absent',
    ];

    const ROLE_ADMIN = 1;
    const ROLE_STAFF = 2;
    const ROLE_REFEREE = 3;

    /**
     * Backward-compat alias: code cũ dùng ROLE_ORGANIZER (giá trị 1).
     * Giữ để không phải sửa toàn bộ code cũ trong 1 lần.
     */
    const ROLE_ORGANIZER = self::ROLE_ADMIN;

    const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_STAFF,
        self::ROLE_REFEREE,
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function miniTournament()
    {
        return $this->belongsTo(MiniTournament::class, 'mini_tournament_id');
    }

    protected $casts = [
        'is_absent' => 'boolean',
        'checked_in_at' => 'datetime',
    ];

    // === Scopes ===

    public function scopeAdmin($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeStaff($query)
    {
        return $query->where('role', self::ROLE_STAFF);
    }

    public function scopeBtc($query)
    {
        return $query->where('role', self::ROLE_STAFF);
    }

    public function scopeReferee($query)
    {
        return $query->where('role', self::ROLE_REFEREE);
    }

    // === Instance helpers ===

    public function isAdmin(): bool
    {
        return (int) $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return (int) $this->role === self::ROLE_STAFF;
    }

    public function isBtc(): bool
    {
        return $this->isStaff();
    }

    public function isReferee(): bool
    {
        return (int) $this->role === self::ROLE_REFEREE;
    }

    // === Role text helpers ===

    /**
     * Trả về role dạng text cho FE.
     *
     * Trước đây: ROLE_ORGANIZER → 'organizer', ROLE_REFEREE → 'referee'.
     * Hiện tại: ROLE_ADMIN → 'admin', ROLE_STAFF → 'staff', ROLE_REFEREE → 'referee'.
     */
    public static function getRoleText($role)
    {
        return match ((int) $role) {
            self::ROLE_ADMIN => 'admin',
            self::ROLE_STAFF => 'staff',
            self::ROLE_REFEREE => 'referee',
            default => 'unknown',
        };
    }

    public function getRoleTextAttribute(): string
    {
        return self::getRoleText($this->role);
    }

    public function draftReminders()
    {
        return $this->hasMany(MiniTournamentDraftReminder::class, 'user_id')
            ->where('mini_tournament_id', $this->mini_tournament_id);
    }
}