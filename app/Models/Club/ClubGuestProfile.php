<?php

namespace App\Models\Club;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * "CLB guest" — đại diện cho 1 user ảo (User.is_guest = true) do CLB tạo và quản lý.
 * Khi CLB tạo guest này, user có thể được mời vào MỌI mini-tournament/Tournament
 * (kể cả event không thuộc CLB gốc). Bảng này chỉ là "ownership record" để CLB
 * quản lý danh sách guest của mình.
 */
class ClubGuestProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_guest_profiles';

    protected $fillable = [
        'club_id',
        'user_id',
        'estimated_level',
        'created_by',
    ];

    protected $casts = [
        'estimated_level' => 'decimal:1',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
