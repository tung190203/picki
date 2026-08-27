<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TournamentStaff — vai trò trong 1 giải đấu (Tournament).
 *
 * Mapping (đã đúng sẵn — không cần migrate data):
 *   ROLE_ORGANIZER = 1  → Admin
 *   ROLE_STAFF     = 2  → BTC
 *   ROLE_REFEREE   = 3  → Trọng tài
 *
 * Trọng tài (REFEREE) có thể được gán `court_id` để giới hạn scope theo sân.
 * - null = score được trên mọi sân
 * - giá trị cụ thể = chỉ score trận ở sân đó
 *
 * NOTE: court_id KHÔNG có FK constraint vì bảng `competition_courts` chưa tồn tại.
 * Khi nào có bảng này chính thức, có thể FK sau.
 */
class TournamentStaff extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'role',
        'checked_in_at',
        'is_absent',
        'court_id',
    ];

    const ROLE_ORGANIZER = 1;
    const ROLE_STAFF = 2;
    const ROLE_REFEREE = 3;

    /**
     * Business alias giúp đọc code rõ ràng hơn (mapping 1-1 với ROLE_ORGANIZER).
     */
    const ROLE_ADMIN = self::ROLE_ORGANIZER;
    const ROLE_BTC = self::ROLE_STAFF;

    const ROLES = [
        self::ROLE_ORGANIZER,
        self::ROLE_STAFF,
        self::ROLE_REFEREE,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Sân được gán cho trọng tài. Hiện tại không có FK constraint
     * vì bảng `competition_courts` chưa tồn tại.
     *
     * Khi nào model CompetitionCourt được tạo chính thức, có thể thêm:
     *   return $this->belongsTo(CompetitionCourt::class, 'court_id');
     */

    protected $casts = [
        'is_absent' => 'boolean',
        'checked_in_at' => 'datetime',
        'court_id' => 'integer',
    ];

    // === Scopes ===

    public function scopeAdmin($query)
    {
        return $query->where('role', self::ROLE_ORGANIZER);
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
        return (int) $this->role === self::ROLE_ORGANIZER;
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

    // === Role text ===

    public function getRoleTextAttribute()
    {
        return match ((int) $this->role) {
            self::ROLE_ORGANIZER => 'Organizer',
            self::ROLE_STAFF => 'Staff',
            self::ROLE_REFEREE => 'Referee',
            default => 'Unknown',
        };
    }
}